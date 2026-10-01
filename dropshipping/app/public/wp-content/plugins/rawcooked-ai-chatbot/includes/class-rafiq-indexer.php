<?php
/**
 * Content indexer: collects products, pages and posts, chunks them,
 * embeds the chunks and stores everything in the vector store.
 *
 * Indexing is batched so the admin UI can loop over small steps without
 * hitting PHP timeouts on large catalogs.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Indexer {

	const BATCH_SIZE    = 8;
	const CHUNK_SIZE    = 1200;
	const CHUNK_OVERLAP = 150;

	/**
	 * Last embedding failure message, for aggregated reporting in run_step().
	 *
	 * @var string
	 */
	private static $last_embed_error = '';

	/**
	 * IDs (with type prefix) of everything that should be indexed.
	 *
	 * @return string[] e.g. [ 'product:12', 'page:2', 'post:7' ].
	 */
	public static function get_queue() {
		$queue = array();

		if ( Rafiq_Settings::get( 'index_products' ) && post_type_exists( 'product' ) ) {
			foreach ( self::published_ids( 'product' ) as $id ) {
				$queue[] = 'product:' . $id;
			}
		}
		if ( Rafiq_Settings::get( 'index_pages' ) ) {
			foreach ( self::published_ids( 'page' ) as $id ) {
				$queue[] = 'page:' . $id;
			}
		}
		if ( Rafiq_Settings::get( 'index_posts' ) ) {
			foreach ( self::published_ids( 'post' ) as $id ) {
				$queue[] = 'post:' . $id;
			}
		}
		return $queue;
	}

	private static function published_ids( $post_type ) {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Process one batch of the queue.
	 *
	 * @param int  $offset Position in the queue.
	 * @param bool $force  Re-embed even if the content hash is unchanged.
	 * @return array { done, offset, total, indexed, skipped, errors[] }
	 */
	public static function run_step( $offset = 0, $force = false ) {
		$queue = self::get_queue();
		$total = count( $queue );
		$slice = array_slice( $queue, $offset, self::BATCH_SIZE );

		$indexed  = 0;
		$skipped  = 0;
		$no_embed = 0;
		$errors   = array();

		foreach ( $slice as $item ) {
			list( $type, $id ) = explode( ':', $item );
			$id = (int) $id;
			try {
				$result = self::index_item( $type, $id, $force );
				if ( 'skipped' === $result ) {
					$skipped++;
				} else {
					$indexed++;
					if ( 'no_embed' === $result ) {
						$no_embed++;
					}
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf( '%s #%d: %s', $type, $id, $e->getMessage() );
			}
		}

		if ( $no_embed > 0 ) {
			$errors[] = sprintf(
				/* translators: 1: number of items, 2: error detail */
				__( '%1$d item(s) indexed for keyword search only — embeddings failed (%2$s). Fix the provider settings and use "Rebuild from scratch" to add semantic search.', 'rawcooked-ai-chatbot' ),
				$no_embed,
				self::$last_embed_error
			);
		}

		$next = $offset + count( $slice );
		return array(
			'done'    => $next >= $total,
			'offset'  => $next,
			'total'   => $total,
			'indexed' => $indexed,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Index one post/page/product.
	 *
	 * @return string 'indexed' | 'skipped'
	 * @throws Exception When embedding fails.
	 */
	public static function index_item( $type, $post_id, $force = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			Rafiq_Vector_Store::delete_source( $type, $post_id );
			return 'skipped';
		}

		$text = ( 'product' === $type ) ? self::product_text( $post_id ) : self::post_text( $post );
		$text = trim( $text );
		if ( '' === $text ) {
			Rafiq_Vector_Store::delete_source( $type, $post_id );
			return 'skipped';
		}

		$embed_provider = Rafiq_Settings::resolve_embedding_provider();
		$hash           = md5( $text . '|' . $embed_provider );

		if ( ! $force && Rafiq_Vector_Store::get_source_hash( $type, $post_id ) === $hash ) {
			return 'skipped';
		}

		$pieces = self::chunk_text( $text );
		$chunks = array();

		$vectors     = array();
		$embed_error = '';
		if ( '' !== $embed_provider ) {
			try {
				$provider = Rafiq_Provider::make( $embed_provider );
				$vectors  = $provider->embed( $pieces );
			} catch ( Exception $e ) {
				// Degrade gracefully: store the chunks without embeddings so
				// keyword search still works. The altered hash makes a later
				// run (with a working provider) re-embed this source.
				$vectors                = array();
				$embed_error            = $e->getMessage();
				self::$last_embed_error = $embed_error;
				$hash                   = 'noembed:' . $hash;
			}
		}

		foreach ( $pieces as $i => $piece ) {
			$chunks[] = array(
				'content'   => $piece,
				'embedding' => isset( $vectors[ $i ] ) ? $vectors[ $i ] : null,
			);
		}

		Rafiq_Vector_Store::save_source(
			$type,
			$post_id,
			get_the_title( $post ),
			get_permalink( $post ),
			$chunks,
			$hash
		);
		return ( '' !== $embed_error ) ? 'no_embed' : 'indexed';
	}

	/**
	 * Embed the user query with the configured provider (null = keyword-only mode).
	 *
	 * @return float[]|null
	 */
	public static function embed_query( $query ) {
		$embed_provider = Rafiq_Settings::resolve_embedding_provider();
		if ( '' === $embed_provider || Rafiq_Vector_Store::count_embedded() === 0 ) {
			return null;
		}
		try {
			$provider = Rafiq_Provider::make( $embed_provider );
			$vectors  = $provider->embed( array( $query ) );
			return isset( $vectors[0] ) ? $vectors[0] : null;
		} catch ( Exception $e ) {
			return null; // Degrade gracefully to keyword search.
		}
	}

	private static function post_text( WP_Post $post ) {
		// Render blocks and shortcodes without firing third-party
		// the_content filters (which inject unrelated markup to index).
		$content = do_blocks( $post->post_content );
		$content = do_shortcode( $content );
		$content = wp_strip_all_tags( $content );
		return $post->post_title . "\n\n" . self::normalize( $content );
	}

	private static function product_text( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$post = get_post( $product_id );
			return $post ? self::post_text( $post ) : '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$parts   = array();
		$parts[] = $product->get_name();

		$price = $product->get_price();
		if ( '' !== $price ) {
			$parts[] = sprintf(
				/* translators: %s: formatted price */
				__( 'Price: %s', 'rawcooked-ai-chatbot' ),
				wp_strip_all_tags( wc_price( $price ) )
			);
		}
		$parts[] = $product->is_in_stock()
			? __( 'Availability: in stock', 'rawcooked-ai-chatbot' )
			: __( 'Availability: out of stock', 'rawcooked-ai-chatbot' );

		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $categories ) && $categories ) {
			$parts[] = __( 'Categories:', 'rawcooked-ai-chatbot' ) . ' ' . implode( ', ', $categories );
		}

		$short = wp_strip_all_tags( $product->get_short_description() );
		if ( $short ) {
			$parts[] = $short;
		}
		$description = wp_strip_all_tags( $product->get_description() );
		if ( $description ) {
			$parts[] = $description;
		}

		return self::normalize( implode( "\n", $parts ) );
	}

	private static function normalize( $text ) {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Split text into overlapping chunks, preferring paragraph boundaries.
	 *
	 * @return string[]
	 */
	public static function chunk_text( $text ) {
		if ( mb_strlen( $text ) <= self::CHUNK_SIZE ) {
			return array( $text );
		}

		$paragraphs = preg_split( '/\n\n+/', $text );
		$chunks     = array();
		$current    = '';

		foreach ( $paragraphs as $paragraph ) {
			// Hard-split paragraphs longer than a chunk.
			while ( mb_strlen( $paragraph ) > self::CHUNK_SIZE ) {
				$head      = mb_substr( $paragraph, 0, self::CHUNK_SIZE );
				$paragraph = mb_substr( $paragraph, self::CHUNK_SIZE - self::CHUNK_OVERLAP );
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}
				$chunks[] = $head;
			}

			$candidate = ( '' === $current ) ? $paragraph : $current . "\n\n" . $paragraph;
			if ( mb_strlen( $candidate ) > self::CHUNK_SIZE ) {
				$chunks[] = $current;
				// Keep a tail of the previous chunk as overlap.
				$tail    = mb_substr( $current, max( 0, mb_strlen( $current ) - self::CHUNK_OVERLAP ) );
				$current = $tail . "\n\n" . $paragraph;
			} else {
				$current = $candidate;
			}
		}
		if ( '' !== trim( $current ) ) {
			$chunks[] = $current;
		}
		return array_values( array_filter( array_map( 'trim', $chunks ) ) );
	}

	/**
	 * Re-index a single post shortly after it is saved.
	 */
	public static function schedule_post_reindex( $post_id, WP_Post $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$map = array(
			'product' => 'index_products',
			'page'    => 'index_pages',
			'post'    => 'index_posts',
		);
		if ( ! isset( $map[ $post->post_type ] ) || ! Rafiq_Settings::get( $map[ $post->post_type ] ) ) {
			return;
		}
		wp_schedule_single_event( time() + 30, 'rafiq_reindex_single', array( $post->post_type, $post_id ) );
	}

	public static function reindex_single( $type, $post_id ) {
		try {
			self::index_item( $type, (int) $post_id );
		} catch ( Exception $e ) {
			// Silent: the nightly full index will retry.
			do_action( 'rafiq_index_error', $type, $post_id, $e->getMessage() );
		}
	}

	/**
	 * Nightly cron: full incremental pass (hash check keeps it cheap).
	 */
	public static function cron_full_index() {
		$offset = 0;
		do {
			$result = self::run_step( $offset );
			$offset = $result['offset'];
		} while ( ! $result['done'] );
	}
}
