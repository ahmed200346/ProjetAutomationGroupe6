<?php
/**
 * Vector store backed by a custom MySQL table.
 *
 * Chunks of site content are stored with their embedding (JSON array of
 * floats). Retrieval is hybrid: a FULLTEXT keyword pass narrows candidates,
 * then cosine similarity re-ranks them in PHP. With no embedding provider
 * configured the keyword pass alone is used, so the bot keeps working
 * without any API key.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Vector_Store {

	const TABLE = 'rafiq_chunks';

	/** Max rows loaded for in-PHP cosine ranking. */
	const CANDIDATE_CAP = 400;

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				source_type VARCHAR(20) NOT NULL,
				source_id BIGINT UNSIGNED NOT NULL,
				chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
				title TEXT NOT NULL,
				url TEXT NOT NULL,
				content LONGTEXT NOT NULL,
				embedding LONGTEXT NULL,
				content_hash CHAR(32) NOT NULL DEFAULT '',
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY source (source_type, source_id),
				FULLTEXT KEY content_ft (title, content)
			) {$charset};"
		);
	}

	public static function drop() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count() {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_embedded() {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE embedding IS NOT NULL AND embedding != ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Current stored hash for a source, to skip unchanged content.
	 */
	public static function get_source_hash( $source_type, $source_id ) {
		global $wpdb;
		$table = self::table_name();
		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content_hash FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_type,
				$source_id
			)
		);
	}

	public static function delete_source( $source_type, $source_id ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE source_type = %s AND source_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_type,
				$source_id
			)
		);
	}

	public static function delete_all() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Replace all chunks of one source.
	 *
	 * @param string $source_type product|post|page.
	 * @param int    $source_id   Post ID.
	 * @param string $title       Source title.
	 * @param string $url         Permalink.
	 * @param array  $chunks      Array of [ 'content' => string, 'embedding' => float[]|null ].
	 * @param string $hash        Hash of the full source content.
	 */
	public static function save_source( $source_type, $source_id, $title, $url, array $chunks, $hash ) {
		global $wpdb;
		self::delete_source( $source_type, $source_id );
		$table = self::table_name();
		$now   = current_time( 'mysql', true );

		foreach ( $chunks as $i => $chunk ) {
			$embedding = null;
			if ( ! empty( $chunk['embedding'] ) && is_array( $chunk['embedding'] ) ) {
				$embedding = wp_json_encode( self::round_vector( $chunk['embedding'] ) );
			}
			$wpdb->insert(
				$table,
				array(
					'source_type'  => $source_type,
					'source_id'    => $source_id,
					'chunk_index'  => $i,
					'title'        => $title,
					'url'          => $url,
					'content'      => $chunk['content'],
					'embedding'    => $embedding,
					'content_hash' => $hash,
					'updated_at'   => $now,
				),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Hybrid retrieval.
	 *
	 * @param string       $query           User query.
	 * @param float[]|null $query_embedding Embedding of the query, null for keyword-only.
	 * @param int          $limit           Number of chunks to return.
	 * @return array[] Each: id, source_type, source_id, title, url, content, score.
	 */
	public static function search( $query, $query_embedding, $limit = 6 ) {
		$candidates = self::keyword_candidates( $query, $query_embedding ? self::CANDIDATE_CAP : $limit * 4 );

		if ( $query_embedding ) {
			// Widen the pool with recent rows when keyword search finds little.
			if ( count( $candidates ) < self::CANDIDATE_CAP ) {
				$candidates = self::merge_rows( $candidates, self::any_embedded_rows( self::CANDIDATE_CAP - count( $candidates ) ) );
			}
			foreach ( $candidates as &$row ) {
				$vector       = ! empty( $row['embedding'] ) ? json_decode( $row['embedding'], true ) : null;
				$row['score'] = is_array( $vector ) ? self::cosine( $query_embedding, $vector ) : 0.0;
			}
			unset( $row );
			usort(
				$candidates,
				static function ( $a, $b ) {
					return $b['score'] <=> $a['score'];
				}
			);
		} else {
			foreach ( $candidates as $i => &$row ) {
				$row['score'] = isset( $row['ft_score'] ) ? (float) $row['ft_score'] : 1.0 / ( $i + 1 );
			}
			unset( $row );
		}

		$results = array_slice( $candidates, 0, $limit );
		foreach ( $results as &$row ) {
			unset( $row['embedding'], $row['ft_score'] );
		}
		unset( $row );
		return $results;
	}

	private static function keyword_candidates( $query, $limit ) {
		global $wpdb;
		$table = self::table_name();
		$query = trim( wp_strip_all_tags( (string) $query ) );
		if ( '' === $query ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name derives from $wpdb->prefix.
				"SELECT id, source_type, source_id, title, url, content, embedding,
				MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE) AS ft_score
				FROM {$table}
				WHERE MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE)
				ORDER BY ft_score DESC LIMIT %d",
				$query,
				$query,
				$limit
			),
			ARRAY_A
		);

		if ( null === $rows || false === $rows ) {
			$rows = array();
		}

		// LIKE fallback when FULLTEXT is unavailable or finds nothing.
		// Exactly three terms (padded by repetition) so the SQL stays a
		// literal string with a fixed set of placeholders.
		if ( empty( $rows ) ) {
			$rows  = array();
			$words = array_values(
				array_filter(
					preg_split( '/\s+/', $query ),
					static function ( $w ) {
						return mb_strlen( $w ) >= 3;
					}
				)
			);
			$words = array_slice( $words, 0, 3 );
			if ( $words ) {
				while ( count( $words ) < 3 ) {
					$words[] = $words[0];
				}
				$like = array();
				foreach ( $words as $word ) {
					$like[] = '%' . $wpdb->esc_like( $word ) . '%';
				}
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name derives from $wpdb->prefix.
						"SELECT id, source_type, source_id, title, url, content, embedding FROM {$table}
						WHERE (title LIKE %s OR content LIKE %s)
						OR (title LIKE %s OR content LIKE %s)
						OR (title LIKE %s OR content LIKE %s)
						LIMIT %d",
						$like[0],
						$like[0],
						$like[1],
						$like[1],
						$like[2],
						$like[2],
						$limit
					),
					ARRAY_A
				);
				$rows = is_array( $rows ) ? $rows : array();
			}
		}
		return $rows;
	}

	private static function any_embedded_rows( $limit ) {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_type, source_id, title, url, content, embedding FROM {$table}
				WHERE embedding IS NOT NULL AND embedding != '' ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function merge_rows( array $a, array $b ) {
		$seen = array();
		foreach ( $a as $row ) {
			$seen[ $row['id'] ] = true;
		}
		foreach ( $b as $row ) {
			if ( empty( $seen[ $row['id'] ] ) ) {
				$a[] = $row;
			}
		}
		return $a;
	}

	public static function cosine( array $a, array $b ) {
		$len = min( count( $a ), count( $b ) );
		if ( 0 === $len ) {
			return 0.0;
		}
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		for ( $i = 0; $i < $len; $i++ ) {
			$dot += $a[ $i ] * $b[ $i ];
			$na  += $a[ $i ] * $a[ $i ];
			$nb  += $b[ $i ] * $b[ $i ];
		}
		if ( 0.0 === $na || 0.0 === $nb ) {
			return 0.0;
		}
		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}

	private static function round_vector( array $vector ) {
		return array_map(
			static function ( $v ) {
				return round( (float) $v, 5 );
			},
			$vector
		);
	}
}
