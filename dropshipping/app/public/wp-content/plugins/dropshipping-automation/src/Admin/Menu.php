<?php

namespace DSA\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {
	/** @var array<string, array<string, string>> */
	private $pages;

	/** @var array<int, string> */
	private $page_hooks = array();

	public function __construct() {
		$this->pages = array(
			'dsa-dashboard' => array(
				'label'             => __( "Vue d'ensemble", 'dsa' ),
				'icon'              => 'dashicons-chart-area',
				'description'       => __( 'Retrouvez ici la santé de votre activité et les dernières exécutions.', 'dsa' ),
				'empty_title'       => __( 'Votre espace de pilotage est prêt', 'dsa' ),
				'empty_description' => __( 'Les indicateurs et exécutions apparaîtront ici lorsque le pipeline sera connecté.', 'dsa' ),
			),
			'dsa-workflows' => array(
				'label'             => __( 'Workflows', 'dsa' ),
				'icon'              => 'dashicons-controls-repeat',
				'description'       => __( 'Configurez les workflows de scraping et leur planification.', 'dsa' ),
				'empty_title'       => __( 'Aucun workflow configuré', 'dsa' ),
				'empty_description' => __( 'Les workflows produits et tendances seront disponibles dans cette vue.', 'dsa' ),
			),
			'dsa-trends' => array(
				'label'             => __( 'Tendances & Prix', 'dsa' ),
				'icon'              => 'dashicons-chart-line',
				'description'       => __( 'Explorez les signaux collectés et les prix moyens par catégorie.', 'dsa' ),
				'empty_title'       => __( 'Aucune tendance à afficher', 'dsa' ),
				'empty_description' => __( 'Les statistiques et prix apparaîtront après les premières collectes.', 'dsa' ),
			),
			'dsa-products' => array(
				'label'             => __( 'Produits', 'dsa' ),
				'icon'              => 'dashicons-products',
				'description'       => __( 'Consultez les produits shortlistés, à valider ou déjà publiés.', 'dsa' ),
				'empty_title'       => __( 'Aucun produit pour le moment', 'dsa' ),
				'empty_description' => __( 'Les produits évalués et soumis à validation seront regroupés ici.', 'dsa' ),
			),
			'dsa-suppliers' => array(
				'label'             => __( 'Fournisseurs & Sourcing', 'dsa' ),
				'icon'              => 'dashicons-store',
				'description'       => __( 'Suivez les fournisseurs configurés et les marges de sourcing.', 'dsa' ),
				'empty_title'       => __( 'Aucun fournisseur connecté', 'dsa' ),
				'empty_description' => __( 'Le statut et les marges des fournisseurs seront visibles dans cette vue.', 'dsa' ),
			),
			'dsa-orders' => array(
				'label'             => __( 'Commandes & Suivi', 'dsa' ),
				'icon'              => 'dashicons-location-alt',
				'description'       => __( 'Suivez les commandes transmises et leurs numéros de suivi.', 'dsa' ),
				'empty_title'       => __( 'Aucune commande à suivre', 'dsa' ),
				'empty_description' => __( 'Les commandes et informations de suivi seront affichées ici.', 'dsa' ),
			),
			'dsa-support' => array(
				'label'             => __( 'SAV & Avis', 'dsa' ),
				'icon'              => 'dashicons-format-chat',
				'description'       => __( 'Centralisez les retours clients utiles à la boucle de feedback.', 'dsa' ),
				'empty_title'       => __( 'Aucun retour client', 'dsa' ),
				'empty_description' => __( 'Questions, remboursements et avis apparaîtront une fois les intégrations disponibles.', 'dsa' ),
			),
			'dsa-logs' => array(
				'label'             => __( 'Journaux & Notifications', 'dsa' ),
				'icon'              => 'dashicons-list-view',
				'description'       => __( 'Consultez les exécutions, les erreurs et les digests envoyés.', 'dsa' ),
				'empty_title'       => __( 'Aucune exécution enregistrée', 'dsa' ),
				'empty_description' => __( 'L’historique des workflows et leurs notifications seront conservés ici.', 'dsa' ),
			),
			'dsa-settings' => array(
				'label'             => __( 'Réglages', 'dsa' ),
				'icon'              => 'dashicons-admin-generic',
				'description'       => __( 'Préparez les réglages généraux, les providers IA et les notifications.', 'dsa' ),
				'empty_title'       => __( 'Réglages en préparation', 'dsa' ),
				'empty_description' => __( 'Les options de configuration seront proposées dans cette vue.', 'dsa' ),
			),
		);
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	public function get_pages() {
		return $this->pages;
	}

	public function register_pages() {
		$first_page = reset( $this->pages );
		$parent_hook = add_menu_page(
			__( 'Dropshipping', 'dsa' ),
			__( 'Dropshipping', 'dsa' ),
			'manage_options',
			'dsa-dashboard',
			array( $this, 'render_overview' ),
			'dashicons-chart-area',
			56
		);

		$this->page_hooks[] = $parent_hook;
		add_submenu_page(
			'dsa-dashboard',
			$first_page['label'],
			$first_page['label'],
			'manage_options',
			'dsa-dashboard',
			array( $this, 'render_overview' )
		);

		foreach ( $this->pages as $slug => $page ) {
			if ( 'dsa-dashboard' === $slug ) {
				continue;
			}

			$this->page_hooks[] = add_submenu_page(
				'dsa-dashboard',
				$page['label'],
				$page['label'],
				'manage_options',
				$slug,
				function () use ( $slug ) {
					$this->render_page( $slug );
				}
			);
		}
	}

	public function render_overview() {
		$this->render_page( 'dsa-dashboard' );
	}

	public function render_page( $slug ) {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $this->pages[ $slug ] ) ) {
			wp_die( esc_html__( 'Vous n’avez pas accès à cette page.', 'dsa' ), '', array( 'response' => 403 ) );
		}

		$current_slug = $slug;
		$page = $this->pages[ $slug ];
		if ( 'dsa-dashboard' === $slug ) {
			require DSA_PLUGIN_DIR . 'views/dashboard-page.php';
			return;
		}
		if ( 'dsa-settings' === $slug ) {
			require DSA_PLUGIN_DIR . 'views/settings-page.php';
			return;
		}
		if ( 'dsa-logs' === $slug ) {
			require DSA_PLUGIN_DIR . 'views/logs-page.php';
			return;
		}
		if ( 'dsa-workflows' === $slug ) {
			require DSA_PLUGIN_DIR . 'views/admin-page.php';
			return;
		}
		require DSA_PLUGIN_DIR . 'views/admin-page.php';
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'dsa-admin', DSA_PLUGIN_URL . 'assets/css/admin.css', array(), DSA_VERSION );
		wp_enqueue_script( 'dsa-admin', DSA_PLUGIN_URL . 'assets/js/admin.js', array(), DSA_VERSION, true );
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
		if ( false !== strpos( $hook_suffix, 'dsa-workflows' ) || false !== strpos( $hook_suffix, 'dsa-products' ) || false !== strpos( $hook_suffix, 'dsa-suppliers' ) || false !== strpos( $hook_suffix, 'dsa-orders' ) || false !== strpos( $hook_suffix, 'dsa-support' ) ) {
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
		if ( false !== strpos( $hook_suffix, 'dsa-dashboard' ) ) {
			wp_localize_script(
				'dsa-admin',
				'dsaDashboardData',
				$this->dashboard_script_data( true )
			);
		}
		if ( false !== strpos( $hook_suffix, 'dsa-workflows' ) ) {
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
		if ( false !== strpos( $hook_suffix, 'dsa-settings' ) ) {
			wp_localize_script(
				'dsa-admin',
				'dsaProvidersData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'dsa/v1/providers/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		if ( false !== strpos( $hook_suffix, 'dsa-trends' ) ) {
			wp_enqueue_script( 'dsa-chartjs', DSA_PLUGIN_URL . 'assets/js/vendor/chart.umd.js', array(), DSA_VERSION, true );
			wp_enqueue_script( 'dsa-trends', DSA_PLUGIN_URL . 'assets/js/trends.js', array( 'dsa-chartjs' ), DSA_VERSION, true );
			wp_localize_script(
				'dsa-trends',
				'dsaTrendsData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'dsa/v1/trends' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'logUrl' => esc_url_raw( admin_url( 'admin.php?page=dsa-logs' ) ),
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

		if ( false !== strpos( $hook_suffix, 'dsa-products' ) ) {
			wp_enqueue_style( 'dsa-storefront', DSA_PLUGIN_URL . 'assets/css/storefront.css', array(), DSA_VERSION );
		}
	}

	public function dashboard_links( $admin = false ) {
		$links = array();
		foreach ( array_keys( $this->pages ) as $slug ) {
			$links[ $slug ] = $admin
				? admin_url( 'admin.php?page=' . $slug )
				: add_query_arg( 'dsa_page', $slug, home_url( '/' ) );
		}
		return $links;
	}

	public function dashboard_script_data( $admin = false ) {
		return array(
			'restUrl' => esc_url_raw( rest_url( 'dsa/v1/dashboard' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'links'   => $this->dashboard_links( $admin ),
			'messages' => array(
				'metrics' => array(
					'detected' => __( 'Produits détectés', 'dsa' ),
					'shortlist' => __( 'Produits en shortlist', 'dsa' ),
					'published' => __( 'Produits publiés', 'dsa' ),
					'margin' => __( 'Marge moyenne', 'dsa' ),
					'orders' => __( 'Commandes en cours', 'dsa' ),
					'errors' => __( 'Taux d’erreur', 'dsa' ),
				),
				'delta_new' => __( 'Nouveau sur la période précédente', 'dsa' ),
				'previous_period' => __( '% vs période précédente', 'dsa' ),
				'updated' => __( 'Indicateurs actualisés pour %s.', 'dsa' ),
				'run_queued' => __( 'Exécution ajoutée à Action Scheduler.', 'dsa' ),
				'request_error' => __( 'Une erreur est survenue.', 'dsa' ),
				'pipeline_status' => array(
					'ok' => __( 'OK', 'dsa' ),
					'warning' => __( 'Avertissement', 'dsa' ),
					'error' => __( 'Erreur', 'dsa' ),
					'inactive' => __( 'Inactif', 'dsa' ),
				),
				'empty_chart' => __( 'Aucun signal détecté sur cette période.', 'dsa' ),
				'empty_schedule' => __( 'Aucune exécution planifiée.', 'dsa' ),
				'empty_runs' => __( 'Aucune exécution enregistrée.', 'dsa' ),
				'empty_alerts' => __( 'Aucune action requise.', 'dsa' ),
				'run_status' => array(
					'completed' => __( 'Terminée', 'dsa' ),
					'failed' => __( 'Échec', 'dsa' ),
					'simulated' => __( 'Simulée', 'dsa' ),
					'running' => __( 'En cours', 'dsa' ),
				),
			),
		);
	}

	public function add_body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, $this->page_hooks, true ) ) {
			$classes .= ' dsa-admin-screen';
			if ( false !== strpos( $screen->id, 'dsa-products' ) ) {
				$classes .= ' dsa-storefront-body';
			}
		}

		return $classes;
	}
}