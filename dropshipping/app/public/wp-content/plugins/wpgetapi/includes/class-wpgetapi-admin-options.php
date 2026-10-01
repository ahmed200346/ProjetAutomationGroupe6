<?php
/**
 * Settings Required
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WpGetApi_Admin_Options' ) ) :

	/**
	 * CMB2 Theme Options
	 * @version 0.1.0
	 */
	class WpGetApi_Admin_Options {

		/**
		 * Array of metaboxes/fields
		 * @var array
		 */
		public $option_metabox = array();

		/**
		 * Array of metaboxes/fields
		 * @var array
		 */
		public $metabox_id = '';

		/**
		 * Options Page title
		 * @var string
		 */
		protected $title = '';

		/**
		 * Options Tab Pages
		 * @var array
		 */
		public $options_pages = array();

		/**
		 * Is pro plugin installed
		 * @var array
		 */
		public $pro_plugin = false;

		/**
		 * Holds an instance of the object
		 *
		 * @var Myprefix_Admin
		 **/
		private static $instance = null;

		/**
		 * Instance of WPGetAPI_Notices class.
		 *
		 * @var WPGetAPI_Notices
		 */
		private $notice;

		/**
		 * Constructor
		 * @since 0.1.0
		 */
		private function __construct() {}

		/**
		 * Returns the running object
		 *
		 * @return Myprefix_Admin
		 **/
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
				self::$instance->hooks();
			}
			return self::$instance;
		}

		/**
		 * Initiate our hooks
		 * @since 0.1.0
		 */
		public function hooks() {
			add_action( 'admin_init', array( $this, 'init' ) );
			add_action( 'admin_menu', array( $this, 'add_options_pages' ) );

			add_action( 'cmb2_admin_init', array( $this, 'init_custom_fields' ) );

			add_action( 'cmb2_save_options-page_fields', array( $this, 'redirect' ), 1, 2 );

			add_action( 'admin_footer', array( $this, 'load_testing_javascript' ) );

			add_action( 'wp_ajax_wpgetapi_test_endpoint', array( $this, 'test_the_endpoint' ) );

			add_action( 'wp_ajax_wpgetapi_export_endpoints', array( $this, 'export_endpoints' ) );
			add_action( 'wp_ajax_wpgetapi_import_endpoints', array( $this, 'import_endpoints' ) );

			add_action( 'plugins_loaded', array( $this, 'plugins_loaded_handler' ) );
			add_action( 'all_admin_notices', array( $this, 'render_admin_notices' ) );

			add_action( 'wp_ajax_wpgetapi_notice_dismiss', array( $this, 'wpgetapi_notice_dismiss_handler' ) );

			add_action( 'cmb2_options-page_process_fields_wpgetapi_setup', array( $this, 'cleanup_wpgetapi_removed_api_options' ), 10, 2 );
		}

		/**
		 * Setup our custom fields
		 * @since  0.1.0
		 */
		public function init_custom_fields() {
			require_once WPGETAPIDIR . 'includes/class-wpgetapi-parameter-field.php';
			WpGetApi_Parameter_Field::init_parameter();
		}

		/**
		 * Register our setting to WP
		 * @since  0.1.0
		 */
		public function init() {
			$this->pro_plugin = is_plugin_active( 'wpgetapi-extras/wpgetapi-extras.php' ) ? true : false;
			$option_tabs      = self::option_fields();
			foreach ( $option_tabs as $index => $option_tab ) {
				register_setting( $option_tab['id'], $option_tab['id'] );
			}
		}


		/**
		 * Get our saved API's from setup
		 * @since  0.1.0
		 */
		public function get_apis() {
			$setup = get_option( 'wpgetapi_setup' );
			if ( empty( $setup ) || ! isset( $setup['apis'] ) || empty( $setup['apis'] ) ) {
				return;
			}
			return $setup['apis'];
		}


		/**
		 * Add the options metabox to the array of metaboxes
		 * @since  0.1.0
		 */
		public function option_fields() {

			//Only need to initiate the array once per page-load
			if ( ! empty( $this->option_metabox ) ) {
				return $this->option_metabox;
			}

			// setup tab
			$option_metabox[] = array(
				'title'      => __( 'Setup', 'wpgetapi' ),
				'menu_title' => __( 'Setup', 'wpgetapi' ),
				'id'         => 'wpgetapi_setup',
				'desc'       => __( 'Add the details of the API(s) you are using.', 'wpgetapi' ) . ' ' . __( 'Endpoints will be setup in the next step after hitting save.', 'wpgetapi' ),
				'show_on'    => array(
					'key'   => 'options-page',
					'value' => array( 'wpgetapi_setup' ),
				),
				'show_names' => true,
				'fields'     => $this->setup_fields(),
			);

			// get our saved API's and create as tabs
			if ( $this->get_apis() ) {

				foreach ( $this->get_apis() as $index => $api ) {

					$name       = isset( $api['name'] ) && $api['name'] != '' ? sanitize_text_field( $api['name'] ) : 'API ' . absint( $index );
					$api_id     = isset( $api['id'] ) && $api['id'] != '' ? sanitize_text_field( $api['id'] ) : $name;
					$metabox_id = strtolower( str_replace( '-', '_', sanitize_file_name( 'wpgetapi-' . $api_id ) ) );
					$base_url   = isset( $api['base_url'] ) && $api['base_url'] != '' ? esc_url_raw( $api['base_url'], array( 'http', 'https' ) ) : '';
					$type       = isset( $api['auth_type'] ) && $api['auth_type'] != '' ? sanitize_text_field( $api['auth_type'] ) : '';

					// tab
					$option_metabox[] = array(
						'title'      => $name,
						'menu_title' => $name,
						'id'         => $metabox_id,
						'desc'       => '',
						'show_on'    => array(
							'key'   => 'options-page',
							'value' => array( $metabox_id ),
						),
						'show_names' => true,
						'fields'     => $this->endpoint_fields( $type, $api_id, $base_url ),
					);

					// Hook to clean up data when API endpoints are removed.
					add_action( 'cmb2_options-page_process_fields_wpgetapi_' . $api['id'], array( $this, 'cleanup_removed_api_endpoints' ), 10, 2 );

				}
			}

			$option_metabox = apply_filters( 'wpgetapi_admin_pages', $option_metabox );

			return $option_metabox;
		}

		public function setup_fields() {

			$fields[] = array(
				'id'          => 'apis',
				'type'        => 'group',
				'name'        => '',
				'description' => '',
				'options'     => array(
					'group_title'    => __( 'API {#}', 'wpgetapi' ),
					'add_button'     => __( 'Add New API', 'wpgetapi' ),
					'remove_button'  => __( 'Remove API', 'wpgetapi' ),
					'sortable'       => true, // beta
					'remove_confirm' => __( 'Are you sure you want to delete this API?', 'wpgetapi' ),
				),
				'fields'      => array(
					array(
						'name'       => __( 'API Name', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'The name of this API.', 'wpgetapi' ) . ' ' . esc_attr__( 'Call it whatever you like.', 'wpgetapi' ) . '"></span>',
						'id'         => 'name',
						'type'       => 'text',
						'classes'    => 'api-name',
						'attributes' => array(
							'required'    => true,
							'placeholder' => 'Google Maps',
						),
						'desc'       => '',
					),
					array(
						'name'            => __( 'Unique ID', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'Choose your own unique ID for this API.', 'wpgetapi' ) . ' ' . esc_attr__( 'Use lowercase letters and underscores only!', 'wpgetapi' ) . '"></span>',
						'id'              => 'id',
						'type'            => 'text',
						'classes'         => 'api-id',
						'attributes'      => array(
							'required'    => true,
							'placeholder' => 'google_maps',
						),
						'desc'            => '',
						'sanitization_cb' => 'wpgetapi_sanitize_unique_id',
					),
					array(
						'name'       => __( 'Base URL', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'The base URL of the API you are connecting to.', 'wpgetapi' ) . '"></span>',
						'id'         => 'base_url',
						'type'       => 'text',
						'classes'    => 'api-url',
						'attributes' => array(
							'required'    => true,
							'placeholder' => 'https://maps.googleapis.com/maps/api',
						),
						'desc'       => '',
					),

				),
			);

			return $fields;
		}

		/**
		 * Endpoint settings.
		 * @return sale info array
		 *
		 */
		public function endpoint_fields( $type = '', $api_id = '', $base_url = '' ) {

			$fields = array();

			$cache_disabled = $this->pro_plugin ? '' : 'disabled';
			$cache_url      = $this->pro_plugin ? '<a target="_blank" href="https://wpgetapi.com/docs/caching-api-calls/">Caching Help</a>' : '<a target="_blank" href="https://wpgetapi.com/downloads/pro-plugin/?utm_campaign=Pro&utm_medium=Admin&utm_source=Cache">View PRO Plugin</a>';
			$cache_desc     = $this->pro_plugin ? '' : 'Available in PRO plugin.';

			$endpoint_fields = apply_filters(
				'wpgetapi_fields_endpoints',
				array(
					array(
						'name'            => __( 'Unique ID', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'Choose your own unique ID for this endpoint.', 'wpgetapi' ) . ' ' . esc_attr__( 'Use lowercase letters and underscores only!', 'wpgetapi' ) . '"></span>',
						'id'              => 'id',
						'type'            => 'text',
						'classes'         => 'field-id field-half',
						'attributes'      => array(
							'required'    => true,
							'placeholder' => 'new_endpoint',
						),
						'desc'            => '',
						'before_row'      => 'wpgetapi_output_top_of_endpoint',
						'api_id'          => $api_id,
						'sanitization_cb' => 'wpgetapi_sanitize_unique_id',

					),
					array(
						'name'       => __( 'Endpoint', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'This Endpoint will be appended to the Base URL to create the full URL that will be called.', 'wpgetapi' ) . '"></span>',
						'id'         => 'endpoint',
						'type'       => 'text',
						'classes'    => 'field-endpoint field-half',
						'attributes' => array(
							'required'    => true,
							'placeholder' => '/newendpoint',
						),
						'desc'       => '',
					),
					array(
						'name'       => __( 'Method', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'The Request Method for this endpoint.', 'wpgetapi' ) . ' ' . esc_attr__( 'If you are displaying data from the API, it will usually be GET.', 'wpgetapi' ) . ' ' . esc_attr__( 'If you are sending to the API, it will usually be POST.', 'wpgetapi' ) . '"></span>',
						'id'         => 'method',
						'type'       => 'select',
						'classes'    => 'field-method field-half',
						'attributes' => array(
							'required' => true,
						),
						'options'    => array(
							'GET'    => 'GET',
							'POST'   => 'POST',
							'PUT'    => 'PUT',
							'PATCH'  => 'PATCH',
							'DELETE' => 'DELETE',
						),
						'desc'       => '',
					),

					array(
						'name'       => __( 'Results Format', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1. WPGetAPI data formatting JSON data document */
									esc_attr__( 'The format that the API data will be returned in.', 'wpgetapi' ) . ' ' . esc_attr__( 'To format the data as HTML, click the link below to learn how. %1s', 'wpgetapi' ),
									htmlentities( '<br><a target="_blank" href="https://wpgetapi.com/docs/formatting-json-data/?utm_campaign=Shortcode JSON&utm_medium=Admin&utm_source=User">Format as HTML</a>' )
								) .
							'"></span>',
						'id'         => 'results_format',
						'type'       => 'select',
						'classes'    => 'field-results-format field-half',
						'attributes' => array(
							'required' => true,
						),
						'options_cb' => 'wpgetapi_results_format_options',
						'desc'       => '',
					),

					array(
						'name'       => __( 'Timeout', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'How long the connection should stay open in seconds.', 'wpgetapi' ) . ' ' . esc_attr__( 'Default is 10.', 'wpgetapi' ) . ' ' . esc_attr__( 'If you receive curl 28 errors, try increasing this value.', 'wpgetapi' ) . '"></span>',
						'id'         => 'timeout',
						'type'       => 'text',
						'default'    => '10',
						'classes'    => 'field-timeout field-half',
						'attributes' => array(
							'placeholder' => '10',
						),
						'desc'       => '',
					),

					array(
						'name'       => __( 'Cache Time', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1. Caching help anchor link in a new line */
									esc_attr__( 'The time in seconds to cache this request for. %1s', 'wpgetapi' ),
									htmlentities( '<br>' . $cache_url )
								) .
							'"></span>',
						'id'         => 'cache_time',
						'type'       => 'text',
						'classes'    => 'field-cache-time field-half',
						'attributes' => array(
							'placeholder'   => '3600',
							$cache_disabled => $cache_disabled,
						),
						'desc'       => $cache_desc,
						'after_row'  => '</div>',
					),

					array(
						'name'       => __( 'Query String', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1. Query string parameter more info link */
									esc_attr__( 'Parameters as name/value pairs that will be appended to the URL.', 'wpgetapi' ) . ' ' . esc_attr__( 'The query string is the part of the URL after the question mark, like so: https://yoururl.com<b>?key=value&key3=value3</b> %1s', 'wpgetapi' ),
									htmlentities( '<br><a target="_blank" href="https://wpgetapi.com/docs/adding-query-string-parameters/?utm_campaign=Query String&utm_medium=Admin&utm_source=User">More Info</a>' )
								) .
							'"></span>',
						'id'         => 'query_parameters',
						'type'       => 'parameter',
						'classes'    => 'field-query-parameters',
						'repeatable' => true,
						'desc'       => '',
						'before_row' => '<div class="fields-wrapper">',
					),
					array(
						'name'       => __( 'Headers', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1: Header parameter more info link */
									esc_attr__( 'Parameters as name/value pairs, sent in the Headers. %1s', 'wpgetapi' ),
									htmlentities( '<br><a target="_blank" href="https://wpgetapi.com/docs/sending-headers-in-request/?utm_campaign=Headers&utm_medium=Admin&utm_source=User">More Info</a>' )
								) .
							'"></span>',
						'id'         => 'header_parameters',
						'type'       => 'parameter',
						'classes'    => 'field-header-parameters',
						'repeatable' => true,
						'desc'       => '',
					),
					array(
						'name'       => __( 'Body', 'wpgetapi' ) .
							'<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1: Body parameter more info link */
									esc_attr__( 'Parameters as name/value pairs, sent in the Body as POST fields. %1s', 'wpgetapi' ),
									htmlentities( '<br><a target="_blank" href="https://wpgetapi.com/docs/sending-post-fields-in-request/?utm_campaign=Body&utm_medium=Admin&utm_source=User">More Info</a>' )
								) .
							'"></span>',
						'id'         => 'body_parameters',
						'type'       => 'parameter',
						'classes'    => 'field-body-parameters',
						'repeatable' => true,
						'desc'       => '',
					),
					array(
						'name'      => __( 'Encode Body', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' . esc_attr__( 'Encoding of the above Body parameters.', 'wpgetapi' ) . '"></span>',
						'id'        => 'body_json_encode',
						'type'      => 'select',
						'classes'   => 'field-body-json-encode',
						'options'   => array(
							'false'  => __( 'No encoding (raw)', 'wpgetapi' ),
							'true'   => __( 'JSON encode', 'wpgetapi' ),
							'url'    => __( 'URL encode (x-www-form-urlencoded)', 'wpgetapi' ),
							'base64' => __( 'Base64 encode', 'wpgetapi' ),
							'xml'    => __( 'XML format (available in PRO)', 'wpgetapi' ),

						),
						'desc'      => '',
						'after_row' => '</div>',
					),
					array(
						'name'    => __( 'Actions', 'wpgetapi' ) . '<span class="dashicons dashicons-editor-help" data-tip="' .
								sprintf(
									/* translators: 1: Pro plugin link */
									esc_attr__( 'Call this endpoint when your chosen action runs.', 'wpgetapi' ) . ' ' . esc_attr__( 'This feature is available in the PRO plugin. %1s', 'wpgetapi' ),
									htmlentities( '<br><a target="_blank" href="https://wpgetapi.com/downloads/pro-plugin/?utm_campaign=Pro&utm_medium=Admin&utm_source=Actions">View PRO Plugin</a>' )
								) .
							'"></span>',
						'id'      => 'actions',
						'type'    => 'select',
						'classes' => 'field-actions',
						'desc'    => __( 'Available in PRO plugin.', 'wpgetapi' ),
						'options' => array(
							''                        => __( '-- No Action --', 'wpgetapi' ),
							'new_post_published'      => __( 'Post/Custom Post - New Post Published', 'wpgetapi' ),
							'transition_post_status'  => __( 'Post/Custom Post - Status Changed', 'wpgetapi' ),
							'delete_post'             => __( 'Post/Custom Post - Delete Post', 'wpgetapi' ),

							'user_register'           => __( 'User - New User Registered', 'wpgetapi' ),
							'delete_user'             => __( 'User - Delete User', 'wpgetapi' ),
							'user_login'              => __( 'User - User Logs In', 'wpgetapi' ),
							'woocommerce_new_product' => __( 'WooCommerce - New Product Created', 'wpgetapi' ),
							'woocommerce_new_order'   => __( 'WooCommerce - New Order Created', 'wpgetapi' ),
							'woocommerce_order_status_changed' => __( 'WooCommerce - Order Status Changed', 'wpgetapi' ),
							'contact_form_7'          => __( 'Contact Form 7', 'wpgetapi' ),
							'gravity_forms'           => __( 'Gravity Forms', 'wpgetapi' ),
							'wpforms'                 => __( 'WPForms', 'wpgetapi' ),
							'jetformbuilder'          => __( 'JetFormBuilder', 'wpgetapi' ),
							'formidable_forms'        => __( 'Formidable Forms', 'wpgetapi' ),
							'pmp'                     => __( 'Paid Memberships Pro - After Checkout', 'wpgetapi' ),
							'edd'                     => __( 'Easy Digital Downloads - Complete Purchase', 'wpgetapi' ),
						),
						'default' => '',
					),

				)
			);

			$fields[] = array(
				'id'           => 'endpoints',
				'type'         => 'group',
				'name'         => '',
				'description'  => '',
				'options'      => array(
					'group_title'    => __( 'Endpoint {#}', 'wpgetapi' ),
					'add_button'     => __( 'Add Endpoint', 'wpgetapi' ),
					'remove_button'  => __( 'Remove Endpoint', 'wpgetapi' ),
					'sortable'       => true, // beta
										//'closed'        => true,
					'remove_confirm' => __( 'Are you sure you want to delete this endpoint?', 'wpgetapi' ),
				),
				'before_group' => '<pre class="url"><div>Base URL:</div> <span>' . $base_url . '</span></pre>
		    <div class="import-export"><a href="#TB_inline?&width=600&height=550&inlineId=my-content-id" class="button thickbox">Import/Export</a></div>
		    <div class="collapse open"><a class="button" title="Collapse all endpoints"><span class="dashicons dashicons-arrow-down"></span></a></div>',
				'fields'       => $endpoint_fields,
			);

			return apply_filters( 'wpgetapi_fields', $fields );
		}


		public function add_options_pages() {
			// Set our title
			$menu_title  = __( 'WPGetAPI', 'wpgetapi' );
			$this->title = __( 'WPGetAPI', 'wpgetapi' );

			add_thickbox();

			$option_tabs = self::option_fields();

			foreach ( $option_tabs as $index => $option_tab ) {
				if ( $index == 0 ) {

					$this->options_pages[] = add_menu_page(
						$this->title,
						$menu_title,
						'manage_options',
						$option_tab['id'],
						array( $this, 'admin_page_display' ),
						'none'
					); //Link admin menu to first tab

					add_submenu_page( $option_tabs[0]['id'], $menu_title, $option_tab['menu_title'], 'manage_options', $option_tab['id'], array( $this, 'admin_page_display' ) ); //Duplicate menu link for first submenu page
				} else {
					$this->options_pages[] = add_submenu_page( $option_tabs[0]['id'], $menu_title, $option_tab['menu_title'], 'manage_options', $option_tab['id'], array( $this, 'admin_page_display' ) );
				}
			}

			foreach ( $this->options_pages as $page ) {
				// Include CMB CSS in the head to avoid FOUC
				add_action( "admin_print_styles-{$page}", array( 'CMB2_Hookup', 'enqueue_cmb_css' ) );
			}
		}

		/**
		 * Admin page markup. Mmply handled by CMB2
		 * @since  0.1.0
		 */
		public function admin_page_display() {

			$option_tabs = self::option_fields(); //get all option tabs
			$tab_forms   = array();

			$license_status = '';
			if ( $this->pro_plugin ) {
				$license_status = get_option( 'wpgetapi_pro_license_status' );
			}

			?>

		<div class="wrap wpgetapi <?php echo esc_attr( $license_status ); ?>">

			<div class="main_content_cell">

				<h1 class="wp-heading-inline">
					<img width="24" height="22" src="<?php echo esc_url( WPGETAPIURL . 'assets/img/wpgetapi-icon.png' ); ?>" /> <?php echo esc_html( $this->title ); ?> <span class="vnum"><?php echo esc_html( WPGETAPIVERSION ); ?></span>
				</h1>

				<div class="outer-box">
					<!-- Options Page Nav Tabs -->
					<h2 class="nav-tab-wrapper">
						<?php
						foreach ( $option_tabs as $option_tab ) :

							$tab_slug  = $option_tab['id'];
							$nav_class = 'nav-tab';
							if ( isset( $_GET['page'] ) && $tab_slug == $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are not processing form submission.
								$nav_class  .= ' nav-tab-active'; //add active class to current tab
								$tab_forms[] = $option_tab; //add current tab to forms to be rendered
							}

							?>
							
							<a class="<?php echo esc_attr( $nav_class ); ?>" href="<?php esc_url( menu_page_url( $tab_slug ) ); ?>"><?php echo esc_attr( $option_tab['menu_title'] ); ?></a>

						<?php endforeach; ?>
					</h2>
					<!-- End of Nav Tabs -->

						<?php
						//render all tab forms (normally just 1 form)
						foreach ( $tab_forms as $tab_form ) :
							?>
					
						<div id="<?php echo esc_attr( $tab_form['id'] ); ?>" class="cmb-form group">
							<div class="metabox-holder">

								<div id="my-content-id" style="display:none;">
									<div>
												<?php echo $this->get_import_export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed form and input html content. ?>
									</div>
								</div>

								<div class="pmpbox pad">
									<h3 class="title"><?php echo esc_html( $tab_form['title'] ); ?></h3>
									<div class="desc"><?php echo wp_kses_post( $tab_form['desc'] ); ?></div>
										<?php cmb2_metabox_form( $tab_form, $tab_form['id'], $tab_form ); ?>
								</div>
							</div>
						</div>

						<?php endforeach; ?>

				</div>

			</div>

			<div class="sidebar_cell">
				
					<?php
					$sidebar = self::sidebar_display();
					echo apply_filters( 'wpgetapi_admin_sidebar_display', $sidebar ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed mixed html content.
					?>

			</div>

		</div>

			<?php
		}


		/**
		 * Test endpoint ajax call
		 *
		 * @return void
		 */
		public function test_the_endpoint() {

			$nonce = empty( $_POST['nonce'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpgetapi_test_endpoint_nonce' ) || ! WP_Get_API::instance()->current_user_can_manage() ) {
				die( 'Security check' );
			}

			$api_id      = isset( $_POST['api_id'] ) ? sanitize_text_field( wp_unslash( $_POST['api_id'] ) ) : '';
			$endpoint_id = isset( $_POST['endpoint_id'] ) ? sanitize_text_field( wp_unslash( $_POST['endpoint_id'] ) ) : '';
			$data        = wpgetapi_endpoint( $api_id, $endpoint_id, array( 'test_endpoint' => true ) );

			$output = array(
				'data'        => $data,
				'endpoint_id' => $endpoint_id,
			);
			echo wp_json_encode( $output );

			wp_die(); // this is required to terminate immediately and return a proper response
		}

		public function get_import_export() {
			ob_start();
			?>

		<div class="wpgetapi-export">
			<h3><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Endpoints', 'wpgetapi' ); ?></h3>
			<p><?php esc_html_e( 'Export the endpoints for this API.', 'wpgetapi' ); ?><br><?php esc_html_e( 'After clicking the button below, copy the entire contents of the export.', 'wpgetapi' ); ?></p>
			<button class="button button-primary"><?php esc_html_e( 'Export Endpoints', 'wpgetapi' ); ?></button>
			<div class="message"></div>
		</div>

		<div class="wpgetapi-import">
			<h3><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Endpoints', 'wpgetapi' ); ?></h3>
			<p><?php esc_html_e( 'Paste your exported JSON data into the field below to import endpoints into this API.', 'wpgetapi' ); ?></p>
			<textarea></textarea>
			<button class="button button-primary"><?php esc_html_e( 'Import Endpoints', 'wpgetapi' ); ?></button>
			<div class="message"></div>
		</div>

			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		/**
		 * AJAX function to get our endpoints for export.
		 *
		 */
		public function export_endpoints() {
			$nonce = empty( $_POST['nonce'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpgetapi_export_endpoints_nonce' ) || ! WP_Get_API::instance()->current_user_can_manage() ) {
				die( 'Security check' );
			}

			$api_id = ! empty( $_POST['api_id'] ) ? sanitize_text_field( wp_unslash( $_POST['api_id'] ) ) : '';
			$data   = get_option( $api_id );

			if ( ! isset( $data['endpoints'] ) ) {
				wp_die();
			}

			$encryption = new WpGetApi_Encryption();

			foreach ( $data['endpoints'] as $i => $endpoint ) {
				if ( isset( $endpoint['query_parameters'] ) ) {
					foreach ( $endpoint['query_parameters'] as $i2 => $query_parameters ) {
						if ( isset( $query_parameters['value'] ) ) {
							$data['endpoints'][ $i ]['query_parameters'][ $i2 ]['value'] = $encryption->decrypt( $query_parameters['value'] );
						}
					}
				}
				if ( isset( $endpoint['header_parameters'] ) ) {
					foreach ( $endpoint['header_parameters'] as $i2 => $header_parameters ) {
						if ( isset( $header_parameters['value'] ) ) {
							$data['endpoints'][ $i ]['header_parameters'][ $i2 ]['value'] = $encryption->decrypt( $header_parameters['value'] );
						}
					}
				}
				if ( isset( $endpoint['body_parameters'] ) ) {
					foreach ( $endpoint['body_parameters'] as $i2 => $body_parameters ) {
						if ( isset( $body_parameters['value'] ) ) {
							$data['endpoints'][ $i ]['body_parameters'][ $i2 ]['value'] = $encryption->decrypt( $body_parameters['value'] );
						}
					}
				}
			}

			echo wp_json_encode( $data );
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		/**
		 * AJAX function to import our endpoints.
		 *
		 */
		public function import_endpoints() {
			$nonce = empty( $_POST['nonce'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'import_endpoint_nonce' ) || ! WP_Get_API::instance()->current_user_can_manage() ) {
				die( 'Security check' );
			}

			$api_id        = ! empty( $_POST['api_id'] ) ? sanitize_text_field( wp_unslash( $_POST['api_id'] ) ) : '';
			$textarea      = ! empty( $_POST['textarea'] ) ? sanitize_textarea_field( wp_unslash( $_POST['textarea'] ) ) : '';
			$existing_data = get_option( $api_id );

			if ( ! $textarea ) {
				echo 'No data found.';
				wp_die();
			}

			$new_data = json_decode( $textarea, true );
			if ( ! isset( $new_data['endpoints'] ) ) {
				echo 'No endpoints found.';
				wp_die();
			}

			$encryption = new WpGetApi_Encryption();

			foreach ( $new_data['endpoints'] as $i => $endpoint ) {
				if ( isset( $endpoint['query_parameters'] ) && is_array( $endpoint['query_parameters'] ) ) {
					foreach ( $endpoint['query_parameters'] as $i2 => $query_parameters ) {
						if ( isset( $query_parameters['value'] ) ) {
							$new_data['endpoints'][ $i ]['query_parameters'][ $i2 ]['value'] = $encryption->encrypt( $query_parameters['value'] );
						}
					}
				}
				if ( isset( $endpoint['header_parameters'] ) ) {
					foreach ( $endpoint['header_parameters'] as $i2 => $header_parameters ) {
						if ( isset( $header_parameters['value'] ) ) {
							$new_data['endpoints'][ $i ]['header_parameters'][ $i2 ]['value'] = $encryption->encrypt( $header_parameters['value'] );
						}
					}
				}
				if ( isset( $endpoint['body_parameters'] ) ) {
					foreach ( $endpoint['body_parameters'] as $i2 => $body_parameters ) {
						if ( isset( $body_parameters['value'] ) ) {
							$new_data['endpoints'][ $i ]['body_parameters'][ $i2 ]['value'] = $encryption->encrypt( $body_parameters['value'] );
						}
					}
				}
			}

			// if no existing endpoints, simply add it
			if ( ! $existing_data ) {
				update_option( $api_id, $new_data, 'no' );
			} else {

				$merged['endpoints'] = array_merge( $existing_data['endpoints'], $new_data['endpoints'] );
				update_option( $api_id, $merged, 'no' );
			}

			echo 'Successfully imported. Reloading...';
			wp_die(); // this is required to terminate immediately and return a proper response
		}


		public function load_testing_javascript() {
			?>

		<script type="text/javascript" >

		(function($) {

			$(function() {

				/**
				 * Collapse the endpoints
				 * 
				 */
				$('body').on( 'click', '.wpgetapi .collapse .button', function(){

					var $this = $(this);
					var $collapse = $(this).parents('.collapse');
					var status = 'open';

					$collapse.toggleClass('open');

					if( ! $collapse.hasClass( 'open' ) )
						status = 'closed';

					var $endpoints = $( '#endpoints_repeat .postbox.cmb-repeatable-grouping' );
					$endpoints.each( function() {

						if( status == 'closed' ) {
							$( this ).addClass('closed');
						} else {
							$( this ).removeClass('closed');
						}
						
					});

				});

				/**
				 * Run the export
				 * 
				 */
				$('body').on( 'click', '.wpgetapi-export .button', function(){

					var $this = $(this);
					var api_id = $('.wpgetapi.wrap').find( '.cmb-form.group' ).attr( 'id' );

					// disable button
					$this.attr( 'disabled', true );

					var data = {
						'action': 'wpgetapi_export_endpoints',
						'api_id': api_id,
						'nonce': '<?php echo esc_js( wp_create_nonce( 'wpgetapi_export_endpoints_nonce' ) ); ?>'
					};

					// send and get response
					jQuery.post(ajaxurl, data, function( response ) {
						$( '.wpgetapi-export .message' ).show().html( response );
						// enable button
						$( '.wpgetapi-export .button' ).attr( 'disabled', false );

					});

				});

				/**
				 * Run the import
				 * 
				 */
				$('body').on( 'click', '.wpgetapi-import .button', function(){

					var $this = $(this);
					var api_id = $('.wpgetapi.wrap').find( '.cmb-form.group' ).attr( 'id' );
					var textarea = $('.wpgetapi-import textarea').val();

					// disable button
					$this.attr( 'disabled', true );

					var data = {
						'action': 'wpgetapi_import_endpoints',
						'textarea': textarea,
						'api_id': api_id,
						'nonce': "<?php echo esc_js( wp_create_nonce( 'import_endpoint_nonce' ) ); ?>",
					};

					// send and get response
					jQuery.post(ajaxurl, data, function( response ) {

						$( '.wpgetapi-import .message' ).html( response );

						if( response == 'Successfully imported. Reloading...' ) {
							setTimeout(function(){
								location.reload();
								}, 1000); 
						} else {
							$( '.wpgetapi-import .button' ).attr( 'disabled', false );
						}

					});

				});

				/**
				 * Run the endpoint test
				 * 
				 */
				$('body').on( 'click', '.wpgetapi .test-endpoint-button', function(){ //delegated

					var $this = $(this);
					var $parent = $this.closest( '.cmb-repeatable-grouping' );
					var area = $parent.find( '.wpgetapi-test-area' );
					var api_id = $( area ).data( 'api' );
					var endpoint_id = $( area ).data( 'endpoint' );

					// disable button
					$this.attr( 'disabled', true );

					var data = {
						'action': 'wpgetapi_test_endpoint',
						'api_id': api_id,
						'endpoint_id': endpoint_id,
						'nonce': '<?php echo esc_js( wp_create_nonce( 'wpgetapi_test_endpoint_nonce' ) ); ?>'
					};

					// send and get response
					jQuery.post(ajaxurl, data, function( response ) {
						
						var output = JSON.parse( response );

						$( '.wpgetapi-test-area[data-endpoint="' + output.endpoint_id + '"] .handle' ).show();
						$( '.wpgetapi-test-area[data-endpoint="' + output.endpoint_id + '"] .wpgetapi-result' ).html( output.data);

						// enable button
						$( '.wpgetapi .test-endpoint-button' ).attr( 'disabled', false );

					});

				});

				/**
				 * Duplicate button
				 * 
				 */
				$('body').on( 'click', '.wpgetapi .duplicate-endpoint-button', function( e ){

					e.preventDefault();
					var $this = $( this );

					// Duplicate current group values to the new group
					var $current_group = $this.closest( '.cmb-repeatable-grouping' );				
					var $new_group = $current_group.clone();
					var node_name = $new_group.prop('nodeName') || 'div';

					//Get new ID number
					var next_num = $( '.cmb-repeatable-grouping' ).length;
					var current_num = $current_group.attr('data-iterator');

					var getRowId = function( id ) {
						id = id.split( '-' );
						id.splice( id.length - 1, 1 );
						id.push( next_num );
						return id.join( '-' );
					};

					var cleanGroup = function($group, prev_num, new_num) {
						var repeat_update = 'input:not([type="button"]),select,textarea,label';
						var $elements = $group.find( repeat_update );
						var $other = $group.find( '[id]' ).not( repeat_update );

						// Update all elements with new ID
						if ( $other.length ) {
							$other.each( function() {
								var $_this = $( this );
								var old_id = $_this.attr( 'id' );
								var new_id = old_id.replace( '_'+ prev_num, '_'+ new_num );
								var $buttons = $group.find('[data-selector="'+ old_id +'"]');
								$_this.attr( 'id', new_id );

								// Replace data-selector vars
								if ( $buttons.length ) {
									$buttons.attr( 'data-selector', new_id ).data( 'selector', new_id );
								}
							});
						}

						// Update all elements with new ID, Name and For
						if ( $elements.length ) {
							$elements.each( function() {
								var $_this = $( this );	
								var attrs = {};
								var old_for = $_this.attr( 'for' );
								if( old_for ){
									attrs['for'] = old_for.replace( '_'+ prev_num, '_'+ new_num ) 
								}else{
									var old_name = $_this.attr( 'name' );
									var old_id = $_this.attr( 'id' );

									attrs['name'] = old_name ? old_name.replace( '['+ prev_num +'][', '['+ new_num +'][' ) : '';
									attrs['id']   = old_id ? old_id.replace( '_' + prev_num + '_', '_' + new_num + '_' ) : '';
								}
								$_this.attr( attrs );
							} );
						}
						return $group;
					};

					var $new_group = $( '<'+ node_name +' id="'+ getRowId( $current_group.attr('id') ) +'" class="postbox cmb-row cmb-repeatable-grouping" data-iterator="'+ next_num +'">'+ $current_group.html() +'</'+ node_name +'>' );
					$new_group = cleanGroup( $new_group, current_num, next_num );
					
					//Replace unique ID of endpoint
					var new_endpoint_id = $new_group.find('#endpoints_'+ next_num +'_id').val() +'_copy';
					$new_group.find('#endpoints_'+ next_num +'_id').val( new_endpoint_id );
					$new_group.find('.cmb-group-title span').text( new_endpoint_id );
					$new_group.find('span.endpoint_id').text( new_endpoint_id );
					
					//Remove Test Endpoint button and Test results from new group
					$new_group.find('.test-endpoint-button, .wpgetapi-test-area').remove();

					// Append the new group after the current group
					$current_group.after($new_group);

					// Scroll to duplicate Endpoint
					$('html, body').animate({
						scrollTop: $( "#cmb-group-endpoints-"+next_num ).offset().top - 50
					}, 1000);
				});

				$( '.wpgetapi-test-area .handle' ).on('click', function( e ) {
					e.preventDefault();
					$( this ).closest( '.cmb-repeatable-grouping' ).find( '.wpgetapi-result' ).toggle();
				});

			});
			
		}(jQuery));

		</script> 
			<?php
		}


		/**
		 * Sidebar markup.
		 * @since  1.4.1
		 */
		public function sidebar_display() {

			ob_start();

			// do our setup page
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'wpgetapi_setup' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are not processing form submission.
				return $this->sidebar_setup_page();
			}

			?>

			<div class="box">
				<h4><?php esc_html_e( 'Endpoint Instructions', 'wpgetapi' ); ?></h4>
				<ol>
					<li><?php echo wp_kses( __( 'Give your endpoint a <b>Unique ID</b>', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li><?php echo wp_kses( __( 'Add the <b>Endpoint</b> which can be found in your API docs', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li><?php echo wp_kses( __( 'Set the <b>Method</b> - GET for getting data & POST for sending data to the API', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li><?php echo wp_kses( __( 'Set the desired <b>Results Format</b>', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li><?php esc_html_e( 'Other fields are optional &amp; will depend on your API', 'wpgetapi' ); ?></li>
					<li><?php esc_html_e( 'Hit the Save button', 'wpgetapi' ); ?></li>
					<li><?php esc_html_e( 'Copy the Template Tag or Shortcode & paste into page, post or theme file', 'wpgetapi' ); ?></li>
				</ol>
			</div>
			  
			<?php echo $this->help_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed mixed html content. ?>

			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}


		/**
		 * Sidebar for our setup page.
		 * @since 1.4.4
		 */
		public function sidebar_setup_page() {

			ob_start();
			?>

			<div class="box">
				<h4><?php esc_html_e( 'Setup Instructions', 'wpgetapi' ); ?></h4>
				<ol>
					<li><?php echo wp_kses( __( 'Name your API in the <b>API Name</b> field', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li><?php echo wp_kses( __( 'Give your API a <b>Unique ID</b>', 'wpgetapi' ), array( 'b' => array() ) ); ?></li>
					<li>
					<?php
					echo wp_kses( __( 'Add the <b>Base URL</b> of your API.', 'wpgetapi' ), array( 'b' => array() ) );
					echo ' ';
					esc_html_e( 'This can be found in the docs of your API', 'wpgetapi' );
					?>
					</li>
					<li><?php esc_html_e( 'Hit the Save button', 'wpgetapi' ); ?></li>
					<li><?php esc_html_e( 'Visit the new tab that will be created', 'wpgetapi' ); ?></li>
				</ol>
			</div>
			
			<?php echo $this->help_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed mixed html content. ?>

			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}


		/**
		 *
		 * @since 1.4.4
		 */
		public function help_box() {

			ob_start();
			?>

				<div class="box help-box">    
					<h4><?php esc_html_e( 'Getting Help', 'wpgetapi' ); ?></h4>     
				<ul>
					<li>
					<?php
					printf(
						/* translators: 1. Quick start guide page link */
						esc_html__( 'View the %1$s', 'wpgetapi' ),
						'<a target="_blank" href="https://wpgetapi.com/docs/quick-start-guide/?utm_campaign=Docs&utm_medium=Admin&utm_source=User">Quick Start Guide</a>'
					);
					?>
					</li>
					<li>
					<?php
					printf(
						/* translators: 1. FAQ page link */
						esc_html__( 'View the %1$s', 'wpgetapi' ),
						'<a target="_blank" href="https://wpgetapi.com/docs/frequently-asked-questions/?utm_campaign=Docs&utm_medium=Admin&utm_source=User">FAQs</a>'
					);
					?>
					</li>

						<?php if ( ! $this->pro_plugin ) { ?>
					<a class="button" target="_blank" href="https://wpgetapi.com/downloads/pro-plugin/?utm_campaign=Pro&utm_medium=Admin&utm_source=User"><?php esc_html_e( 'Do more with the PRO Plugin', 'wpgetapi' ); ?></a>
					<?php } ?>
				</ul>  
			</div>

			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}


		/**
		 * Tabs don't immediately appear on save, so this hack!
		 * @since  0.1.0
		 */
		public function redirect( $object_id, $updated ) {

			if ( strpos( $object_id, 'wpgetapi_' ) !== false && strpos( $object_id, 'wpgetapi_api_importer' ) == false ) {

				add_settings_error( 'wpgetapi-notices', '', 'Saved, reloading page...', 'updated' );
				settings_errors( 'wpgetapi-notices' );
				?>

			<script>
				setTimeout(function() {
					location.reload();
				}, 200);
			</script>
				<?php

			}
		}


		/**
		 * Public getter method for retrieving protected/private variables
		 * @since  0.1.0
		 * @param  string  $field Field to retrieve
		 * @return mixed          Field value or exception is thrown
		 */
		public function __get( $field ) {
			// Allowed fields to retrieve
			if ( in_array( $field, array( 'key', 'fields', 'title', 'options_pages' ), true ) ) {
				return $this->{$field};
			}
			if ( 'option_metabox' === $field ) {
				return $this->option_fields();
			}
			throw new Exception( esc_html__( 'Invalid property:', 'wpgetapi' ) . ' ' . esc_html( $field ) );
		}

		/**
		 * Runs when plugins_loaded hook is fired
		 *
		 * @return void
		 */
		public function plugins_loaded_handler() {
			//Runs when plugins_loaded action gets fired
			if ( is_admin() ) {
				$this->notice = new WPGetAPI_Notices();
			}
		}

		/**
		 * Hook admin notices on admin dashboard page and admin WPGetAPI pages.
		 *
		 * @global string $pagenow The filename of the current screen.
		 *
		 * @return void
		 */
		public function render_admin_notices() {
			global $pagenow;

			if ( ! WP_Get_API::instance()->current_user_can_manage() ) {
				return;
			}

			$installed_at  = $this->notice->get_wpgetapi_plugin_installed_timestamp();
			$time_now      = $this->notice->get_time_now();
			$installed_for = $time_now - $installed_at;

			$dismissed_dash_notice_until = get_option( 'wpgetapi_dismiss_dash_notice_until', 0 );

			// If the admin dashboard page or the WPGetAPI page, Then show notice
			if ( 'index.php' == $pagenow && ( $installed_at && $time_now > $dismissed_dash_notice_until && $installed_for > ( 14 * 86400 ) && ! WP_Get_API::instance()->is_any_premium_addon_plugin_active() ) || ( defined( 'WPGETAPI_FORCE_DASHNOTICE' ) && WPGETAPI_FORCE_DASHNOTICE ) ) {
				$this->include_template( 'notices/thanks-for-using-main-dash.php' );
			} elseif ( WP_Get_API::instance()->is_wpgetapi_admin_page() && $installed_at && $installed_for > 14 * 86400 ) {
				$this->notice->do_notice( false, 'top' );
			}
		}

		/**
		 * Output, or return, the results of running a template (from the 'templates' directory, unless a filter over-rides it).
		 *
		 * @param  string      $path                   - path to the template
		 * @param  bool        $return_instead_of_echo - by default, the template is echo-ed; set this to instead return it
		 * @param  array       $extract_these          - variables to inject into the template's run context
		 * @return void|string
		 */
		public function include_template( $path, $return_instead_of_echo = false, $extract_these = array() ) {
			if ( $return_instead_of_echo ) {
				ob_start();
			}

			if ( ! isset( $template_file ) ) {
				$template_file = WPGETAPIDIR . 'templates/' . $path;
			}

			$template_file = apply_filters( 'wpgetapi_template', $template_file, $path );

			do_action( 'wpgetapi_before_template', $path, $template_file, $return_instead_of_echo, $extract_these );

			if ( ! file_exists( $template_file ) ) {
				echo esc_html__( 'Error:', 'wpgetapi' ) . ' ' . esc_html__( 'template not found', 'wpgetapi' ) . ' ( ' . esc_html( $template_file ) . ' )';
			} else {
				extract( $extract_these ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- As we are using same function on the other plugins.
				include $template_file;
			}

			do_action( 'wpgetapi_after_template', $path, $template_file, $return_instead_of_echo, $extract_these );

			if ( $return_instead_of_echo ) {
				return ob_get_clean();
			}
		}

		/**
		 * Dismiss notice ajax hadler.
		 */
		public function wpgetapi_notice_dismiss_handler() {
			$nonce = empty( $_POST['nonce'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpgetapi-notice-ajax-nonce' ) ) {
				wp_send_json_error(
					array(
						'result'        => false,
						'error_code'    => 'security_check',
						'error_message' => __( 'The security check failed; try refreshing the page.', 'wpgetapi' ),
					)
				);
			}

			if ( ! WP_Get_API::instance()->current_user_can_manage() ) {
				wp_send_json_error(
					array(
						'result'        => false,
						'error_code'    => 'security_check',
						'error_message' => __( 'You are not allowed to run this command.', 'wpgetapi' ),
					)
				);
			}

			$subaction = isset( $_POST['subaction'] ) ? sanitize_text_field( wp_unslash( $_POST['subaction'] ) ) : '';
			$data      = isset( $_POST['data'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) ) : array();
			$time_now  = $this->notice->get_time_now();

			if ( in_array( $subaction, array( 'wpgetapi_dismiss_dash_notice_until', 'wpgetapi_dismiss_season' ) ) ) {
				update_option( $subaction, ( $time_now + 366 * 86400 ) );
			} elseif ( in_array( $subaction, array( 'wpgetapi_dismiss_page_notice_until', 'wpgetapi_dismiss_notice' ) ) ) {
				update_option( $subaction, ( $time_now + 84 * 86400 ) );
			} elseif ( 'wpgetapi_dismiss_review_notice' == $subaction ) {
				if ( empty( $data['dismiss_forever'] ) ) {
					update_option( $subaction, $time_now + 84 * 86400 );
				} else {
					update_option( $subaction, 100 * ( 365.25 * 86400 ) );
				}
			}

			wp_send_json_success(
				array(
					'result' => true,
				)
			);
		}

		/**
		 * Fires before fields have been processed/saved.
		 *
		 * Compares the existing API setup with the new one. If any APIs have been removed,
		 * their associated endpoint options are deleted from the database.
		 *
		 * @link https://cmb2.io/api/source-class-CMB2.html#850
		 *
		 * @param array $cmb       The CMB2 object.
		 * @param int   $object_id The ID of the current object.
		 */
		public function cleanup_wpgetapi_removed_api_options( $cmb, $object_id ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $object_id is required by the action hook.
			$new_setup = $cmb->data_to_save;
			$old_setup = get_option( 'wpgetapi_setup' );

			$old_api_ids = isset( $old_setup['apis'] ) ? array_column( $old_setup['apis'], 'id' ) : array();
			$new_api_ids = isset( $new_setup['apis'] ) ? array_column( $new_setup['apis'], 'id' ) : array();

			$removed_api_ids = array_diff( $old_api_ids, $new_api_ids );

			if ( empty( $removed_api_ids ) ) {
				return;
			}

			foreach ( $removed_api_ids as $removed_api_id ) {
				/**
				 * Fires before an API setup is removed from wpgetapi_setup.
				 *
				 * This action allows add-on plugins to clean up their own data or options
				 * associated with the removed API or its endpoints, before the core plugin
				 * deletes its related option.
				 *
				 * @param string $api_id The ID of the API that is being removed.
				 */
				do_action( 'wpgetapi_api_before_removed', $removed_api_id );

				delete_option( 'wpgetapi_' . $removed_api_id );
			}
		}

		/**
		 * Fires before fields have been processed/saved.
		 *
		 * This compares the existing API endpoints with the newly saved ones.
		 * If any endpoints have been removed, it fires a hook so that add-on
		 * plugins can clean up their own data, before the core plugin
		 * deletes its related options.
		 *
		 * @link https://cmb2.io/api//source-class-CMB2.html#850
		 *
		 * @param array $cmb       The CMB2 object.
		 * @param int   $object_id The ID of the current CMB2 options object.
		 * @return void
		 */
		public function cleanup_removed_api_endpoints( $cmb, $object_id ) {
			$new_endpoints = isset( $cmb->data_to_save['endpoints'] ) ? $cmb->data_to_save['endpoints'] : array();
			$api_id        = str_replace( 'wpgetapi_', '', $object_id );
			$old_api_setup = get_option( $object_id );

			if ( empty( $old_api_setup ) || ! isset( $old_api_setup['endpoints'] ) ) {
				return;
			}

			$new_endpoint_ids = array_column( $new_endpoints, 'id' );
			$old_endpoint_ids = array_column( $old_api_setup['endpoints'], 'id' );

			$removed_endpoint_ids = array_diff( $old_endpoint_ids, $new_endpoint_ids );

			if ( empty( $removed_endpoint_ids ) ) {
				return;
			}

			foreach ( $removed_endpoint_ids as $removed_endpoint_id ) {
				/**
				 * Fires before an API endpoint is removed from an API setup.
				 *
				 * This allows add-on plugins to clean up their own data or options
				 * associated with the removed endpoint, before the core plugin
				 * deletes its related option.
				 *
				 * @param string $removed_endpoint_id The ID of the endpoint being removed.
				 * @param string $api_id              The ID of the API the endpoint belonged to.
				 */
				do_action( 'wpgetapi_before_endpoint_removed', $removed_endpoint_id, $api_id );
			}
		}
	}

	/**
	 * Helper function to get/return the WpGetApi_Admin_Options object
	 * @since  0.1.0
	 * @return WpGetApi_Admin_Options object
	 */
	function wpgetapi_admin_options() {
		return WpGetApi_Admin_Options::get_instance();
	}

	// Get it started
	wpgetapi_admin_options();

endif;
