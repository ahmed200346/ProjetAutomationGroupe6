<?php

namespace DSA\Frontend;

defined( 'ABSPATH' ) || exit;

final class Storefront {
	public function register() {
		add_filter( 'template_include', array( $this, 'use_storefront_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function use_storefront_template( $template ) {
		if ( is_front_page() && ! is_admin() ) {
			return DSA_PLUGIN_DIR . 'templates/public-storefront.php';
		}

		return $template;
	}

	public function enqueue_assets() {
		if ( ! is_front_page() || is_admin() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'dsa-admin', DSA_PLUGIN_URL . 'assets/css/admin.css', array(), DSA_VERSION );
		wp_enqueue_style( 'dsa-storefront', DSA_PLUGIN_URL . 'assets/css/storefront.css', array(), DSA_VERSION );
		wp_enqueue_script( 'dsa-admin', DSA_PLUGIN_URL . 'assets/js/admin.js', array(), DSA_VERSION, true );
		$current_slug = isset( $_GET['dsa_page'] ) && is_scalar( $_GET['dsa_page'] ) ? sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) : 'dsa-dashboard';
		if ( current_user_can( 'manage_options' ) && in_array( $current_slug, array( 'dsa-dashboard', 'dsa-workflows', 'dsa-trends', 'dsa-products', 'dsa-suppliers', 'dsa-orders', 'dsa-support', 'dsa-settings', 'dsa-logs' ), true ) ) {
			wp_enqueue_style( 'dsa-notifications', DSA_PLUGIN_URL . 'assets/css/notifications.css', array( 'dsa-admin' ), DSA_VERSION );
			wp_enqueue_script( 'dsa-notifications', DSA_PLUGIN_URL . 'assets/js/notifications.js', array(), DSA_VERSION, true );
			wp_localize_script(
				'dsa-notifications',
				'dsaNotificationsData',
				array(
					'apiUrl' => esc_url_raw( rest_url( 'dsa/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'messages' => array(
						'error' => __( 'Impossible de charger ces informations.', 'dsa' ),
						'empty' => __( 'Aucune notification.', 'dsa' ),
						'failed' => __( 'Échec', 'dsa' ),
						'completed' => __( 'Terminée', 'dsa' ),
						'details' => __( 'Détail', 'dsa' ),
						'noRuns' => __( 'Aucune exécution pour ces filtres.', 'dsa' ),
						'runs' => __( 'exécutions', 'dsa' ),
						'webhookConfigured' => __( 'Webhook enregistré; valeur masquée.', 'dsa' ),
						'webhookEmpty' => __( 'Aucun webhook enregistré.', 'dsa' ),
						'saved' => __( 'Réglages enregistrés.', 'dsa' ),
						'testSent' => __( 'E-mail de test envoyé.', 'dsa' ),
					),
				)
			);
		}
		if ( current_user_can( 'manage_options' ) && in_array( $current_slug, array( 'dsa-workflows', 'dsa-products', 'dsa-suppliers', 'dsa-orders', 'dsa-support' ), true ) ) {
			wp_enqueue_style( 'dsa-business-pages', DSA_PLUGIN_URL . 'assets/css/business-pages.css', array( 'dsa-admin' ), DSA_VERSION );
			wp_enqueue_script( 'dsa-business-pages', DSA_PLUGIN_URL . 'assets/js/business-pages.js', array( 'dsa-admin' ), DSA_VERSION, true );
			wp_localize_script(
				'dsa-business-pages',
				'dsaBusinessData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'dsa/v1/demo' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
		if ( isset( $_GET['dsa_page'] ) && 'dsa-dashboard' === sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) && current_user_can( 'manage_options' ) ) {
			wp_localize_script(
				'dsa-admin',
				'dsaDashboardData',
				( new \DSA\Admin\Menu() )->dashboard_script_data()
			);
		}
		if ( isset( $_GET['dsa_page'] ) && 'dsa-trends' === sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) && current_user_can( 'manage_options' ) ) {
			wp_enqueue_script( 'dsa-chartjs', DSA_PLUGIN_URL . 'assets/js/vendor/chart.umd.js', array(), DSA_VERSION, true );
			wp_enqueue_script( 'dsa-trends', DSA_PLUGIN_URL . 'assets/js/trends.js', array( 'dsa-chartjs' ), DSA_VERSION, true );
			wp_localize_script(
				'dsa-trends',
				'dsaTrendsData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'dsa/v1/trends' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'logUrl' => esc_url_raw( add_query_arg( 'dsa_page', 'dsa-logs', home_url( '/' ) ) ),
					'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
					'messages' => array(
						'loading' => __( 'Chargement des données…', 'dsa' ),
						'empty' => __( 'Aucune donnée pour cette sélection.', 'dsa' ),
						'error' => __( 'Impossible de charger ce graphique.', 'dsa' ),
						'noRun' => __( 'Aucune exécution enregistrée.', 'dsa' ),
						'unknown' => __( 'Inconnu', 'dsa' ),
						'allSources' => __( 'Toutes les sources', 'dsa' ),
						'signalLabel' => __( 'Signaux', 'dsa' ),
						'sparklineLabel' => __( 'Tendance : %s', 'dsa' ),
						'csvHeaders' => array(
							'products' => array( __( 'Produit', 'dsa' ), __( 'Catégorie', 'dsa' ), __( 'Mentions', 'dsa' ), __( 'Croissance %', 'dsa' ), __( 'Prix moyen', 'dsa' ), __( 'Marge estimée %', 'dsa' ), __( 'Score', 'dsa' ), __( 'Tendance', 'dsa' ) ),
							'demanded' => array( __( 'Catégorie', 'dsa' ), __( 'Produit demandé', 'dsa' ), __( 'Signaux', 'dsa' ), __( 'Mentions', 'dsa' ) ),
						),
						'status' => array(
							'completed' => __( 'Terminée', 'dsa' ),
							'failed' => __( 'Échec', 'dsa' ),
							'running' => __( 'En cours', 'dsa' ),
							'simulated' => __( 'Simulée', 'dsa' ),
						),
						'csvProducts' => __( 'Classement des produits tendance', 'dsa' ),
						'csvDemanded' => __( 'Produits demandés sur 30 jours', 'dsa' ),
					),
				)
			);
		}
				if ( isset( $_GET['dsa_page'] ) && 'dsa-workflows' === sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) && current_user_can( 'manage_options' ) ) {
					wp_localize_script(
						'dsa-admin',
						'dsaWorkflowData',
						array(
							'restUrl'  => esc_url_raw( rest_url( 'dsa/v1/workflows' ) ),
							'nonce'    => wp_create_nonce( 'wp_rest' ),
							'timezone' => wp_timezone_string() ?: 'UTC',
						)
					);
				}
		if ( isset( $_GET['dsa_page'] ) && 'dsa-settings' === sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) && current_user_can( 'manage_options' ) ) {
			wp_localize_script(
				'dsa-admin',
				'dsaProvidersData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'dsa/v1/providers/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	public function render_dashboard() {
		$pages = ( new \DSA\Admin\Menu() )->get_pages();
		$current_slug = isset( $_GET['dsa_page'] ) ? sanitize_key( wp_unslash( $_GET['dsa_page'] ) ) : 'dsa-dashboard';
		if ( ! isset( $pages[ $current_slug ] ) ) {
			$current_slug = 'dsa-dashboard';
		}
		$page = $pages[ $current_slug ];
		$admin_settings_access = 'dsa-settings' === $current_slug && current_user_can( 'manage_options' );
		$admin_dashboard_access = in_array( $current_slug, array( 'dsa-dashboard', 'dsa-trends' ), true ) && current_user_can( 'manage_options' );
		if ( 'dsa-logs' === $current_slug && current_user_can( 'manage_options' ) ) {
			require DSA_PLUGIN_DIR . 'views/logs-page.php';
			return;
		}

		require DSA_PLUGIN_DIR . 'views/public-dashboard.php';
	}

	public function render_catalog() {
		$demo_mode = \DSA\Storage\DemoMode::enabled();
		$search    = isset( $_GET['dsa_search'] ) && is_scalar( $_GET['dsa_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['dsa_search'] ) ) : '';
		$country   = isset( $_GET['dsa_country'] ) && is_scalar( $_GET['dsa_country'] ) ? sanitize_key( wp_unslash( (string) $_GET['dsa_country'] ) ) : '';
		$quality   = isset( $_GET['dsa_quality'] ) && is_scalar( $_GET['dsa_quality'] ) ? sanitize_key( wp_unslash( (string) $_GET['dsa_quality'] ) ) : '';
		$category  = isset( $_GET['dsa_category'] ) && is_scalar( $_GET['dsa_category'] ) ? sanitize_title( wp_unslash( (string) $_GET['dsa_category'] ) ) : '';
		$raw_price = isset( $_GET['dsa_max_price'] ) && is_scalar( $_GET['dsa_max_price'] ) ? wp_unslash( (string) $_GET['dsa_max_price'] ) : '';
		$max_price = is_numeric( $raw_price )
			? max( 0, (float) $raw_price )
			: 0;

		$allowed_countries = $demo_mode ? array() : array(
			'CN' => __( 'Chine', 'dsa' ),
			'DE' => __( 'Allemagne', 'dsa' ),
			'FR' => __( 'France', 'dsa' ),
			'GB' => __( 'Royaume-Uni', 'dsa' ),
			'US' => __( 'États-Unis', 'dsa' ),
		);
		$quality_options = array(
			'3'   => __( '3 étoiles et plus', 'dsa' ),
			'4'   => __( '4 étoiles et plus', 'dsa' ),
			'4.5' => __( '4,5 étoiles et plus', 'dsa' ),
		);

		if ( ! isset( $allowed_countries[ $country ] ) ) {
			$country = '';
		}
		if ( ! isset( $quality_options[ $quality ] ) ) {
			$quality = '';
		}

		$categories = ! $demo_mode && taxonomy_exists( 'product_cat' )
			? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) )
			: array();
		if ( $demo_mode ) {
			$repositories = new \DSA\Storage\Repositories\RepositoryFactory();
			$categories = array_map(
				static function ( $category ) {
					return (object) array( 'name' => $category['nom'], 'slug' => $category['slug'] );
				},
				$repositories->categories()->all()
			);
		}
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$products = array();
		$product_count = 0;
		$woocommerce_available = $demo_mode || function_exists( 'wc_get_products' );
		if ( $demo_mode ) {
			$repositories = new \DSA\Storage\Repositories\RepositoryFactory();
			$category_id = 0;
			foreach ( $categories as $product_category ) {
				if ( $category === $product_category->slug ) {
					foreach ( $repositories->categories()->all() as $demo_category ) {
						if ( $product_category->slug === $demo_category['slug'] ) {
							$category_id = $demo_category['id'];
							break;
						}
					}
					break;
				}
			}
			$products = $repositories->products()->filter(
				array(
					'status' => 'published',
					'category_id' => $category_id,
					'search' => $search,
					'max_price' => $max_price,
					'minimum_rating' => $quality,
				)
			);
			$product_count = count( $products );
		} elseif ( $woocommerce_available ) {
			$query = array(
				'status'   => 'publish',
				'limit'    => 12,
				'page'     => 1,
				'paginate' => true,
				'orderby'  => 'price',
				'order'    => 'ASC',
			);

			if ( '' !== $search ) {
				$query['s'] = $search;
			}
			if ( '' !== $category ) {
				$query['category'] = array( $category );
			}
			if ( $max_price > 0 ) {
				$query['max_price'] = (string) $max_price;
			}
			if ( '' !== $country || '' !== $quality ) {
				$query['meta_query'] = array( 'relation' => 'AND' );
				if ( '' !== $country ) {
					$query['meta_query'][] = array(
						'key'   => '_dsa_origin_country',
						'value' => $country,
					);
				}
				if ( '' !== $quality ) {
					$query['meta_query'][] = array(
						'key'     => '_dsa_quality_score',
						'value'   => (float) $quality,
						'type'    => 'DECIMAL(3,1)',
						'compare' => '>=',
					);
				}
			}

			$result = wc_get_products( $query );
			if ( is_object( $result ) && isset( $result->products ) && is_array( $result->products ) ) {
				$products = $result->products;
				$product_count = isset( $result->total ) ? absint( $result->total ) : count( $products );
			}
		}

		require DSA_PLUGIN_DIR . 'views/public-catalog.php';
	}
}