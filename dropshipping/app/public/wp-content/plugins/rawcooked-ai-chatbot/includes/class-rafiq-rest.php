<?php
/**
 * REST API: public chat endpoint + admin endpoints (indexing, provider test).
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Rest {

	const NAMESPACE_URI = 'rafiq/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_URI,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => array( __CLASS__, 'chat_permission' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( __CLASS__, 'sanitize_message' ),
					),
					'history' => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'session' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => array( __CLASS__, 'sanitize_session' ),
					),
					'lead_name' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lead_email' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/index',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'index_step' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/index-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'index_status' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/test-provider',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_provider' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'conversations_list' ),
					'permission_callback' => array( __CLASS__, 'admin_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'conversations_delete_all' ),
					'permission_callback' => array( __CLASS__, 'admin_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/conversations/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'conversation_get' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_URI,
			'/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'leads_list' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);
	}

	public static function conversations_list() {
		return rest_ensure_response(
			array(
				'stats'  => Rafiq_Conversations::stats(),
				'recent' => Rafiq_Conversations::recent( 25 ),
			)
		);
	}

	public static function conversation_get( WP_REST_Request $request ) {
		$row = Rafiq_Conversations::get_transcript( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'rafiq_not_found', __( 'Conversation not found.', 'rawcooked-ai-chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function conversations_delete_all() {
		Rafiq_Conversations::delete_all();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function leads_list() {
		return rest_ensure_response( Rafiq_Conversations::leads() );
	}

	public static function sanitize_message( $value ) {
		$value = sanitize_textarea_field( (string) $value );
		return mb_substr( $value, 0, 4000 );
	}

	public static function sanitize_session( $value ) {
		return substr( preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $value ), 0, 64 );
	}

	/**
	 * The chat endpoint is open to visitors but still nonce-protected
	 * (same-site only) and rate-limited per IP.
	 */
	public static function chat_permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rafiq_bad_nonce', __( 'Session expired, please reload the page.', 'rawcooked-ai-chatbot' ), array( 'status' => 403 ) );
		}
		if ( ! self::rate_limit_ok() ) {
			return new WP_Error( 'rafiq_rate_limited', __( 'Too many messages, please wait a minute.', 'rawcooked-ai-chatbot' ), array( 'status' => 429 ) );
		}
		return true;
	}

	public static function admin_permission() {
		return current_user_can( 'manage_options' );
	}

	private static function rate_limit_ok() {
		$limit = (int) Rafiq_Settings::get( 'rate_limit' );
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'rafiq_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	public static function chat( WP_REST_Request $request ) {
		$message = (string) $request->get_param( 'message' );
		if ( '' === trim( $message ) ) {
			return new WP_Error( 'rafiq_empty', __( 'Empty message.', 'rawcooked-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$history = array();
		$raw     = $request->get_param( 'history' );
		if ( is_array( $raw ) ) {
			foreach ( array_slice( $raw, -40 ) as $turn ) {
				if ( is_array( $turn ) && isset( $turn['role'], $turn['content'] ) && is_string( $turn['content'] ) ) {
					$history[] = array(
						'role'    => ( 'assistant' === $turn['role'] ) ? 'assistant' : 'user',
						'content' => mb_substr( sanitize_textarea_field( $turn['content'] ), 0, 4000 ),
					);
				}
			}
		}

		try {
			$result = Rafiq_Chat::handle( $message, $history, get_current_user_id() );
		} catch ( Exception $e ) {
			return new WP_Error(
				'rafiq_provider_error',
				__( 'The assistant is unavailable right now. Please try again shortly.', 'rawcooked-ai-chatbot' ),
				array(
					'status' => 502,
					'detail' => current_user_can( 'manage_options' ) ? $e->getMessage() : null,
				)
			);
		}

		$session = (string) $request->get_param( 'session' );
		if ( $session && Rafiq_Settings::get( 'log_conversations' ) ) {
			Rafiq_Conversations::log_turn(
				$session,
				get_current_user_id(),
				$message,
				$result['reply'],
				! empty( $result['no_context'] ),
				array(
					'name'  => (string) $request->get_param( 'lead_name' ),
					'email' => (string) $request->get_param( 'lead_email' ),
				)
			);
		}
		/**
		 * Fires after a reply has been produced and logged.
		 *
		 * @param string $session Session key (may be empty).
		 * @param string $message Visitor message.
		 * @param array  $result  reply, sources, no_context.
		 * @param int    $user_id Current user id (0 = anonymous).
		 */
		do_action( 'rafiq_after_reply', $session, $message, $result, get_current_user_id() );

		unset( $result['no_context'] ); // Internal flag, not for the widget.

		return rest_ensure_response( $result );
	}

	public static function index_step( WP_REST_Request $request ) {
		$offset = max( 0, (int) $request->get_param( 'offset' ) );
		$force  = (bool) $request->get_param( 'force' );
		$reset  = (bool) $request->get_param( 'reset' );

		if ( $reset && 0 === $offset ) {
			Rafiq_Vector_Store::delete_all();
			$force = true;
		}

		$result = Rafiq_Indexer::run_step( $offset, $force );
		$result['chunks'] = Rafiq_Vector_Store::count();
		return rest_ensure_response( $result );
	}

	public static function index_status() {
		return rest_ensure_response(
			array(
				'total_items'        => count( Rafiq_Indexer::get_queue() ),
				'chunks'             => Rafiq_Vector_Store::count(),
				'chunks_embedded'    => Rafiq_Vector_Store::count_embedded(),
				'embedding_provider' => Rafiq_Settings::resolve_embedding_provider(),
			)
		);
	}

	public static function test_provider() {
		try {
			$provider = Rafiq_Provider::make( (string) Rafiq_Settings::get( 'provider' ) );
			$result   = $provider->chat(
				'You are a connection test. Reply with exactly: OK',
				array(
					array(
						'role'    => 'user',
						'content' => 'ping',
					),
				),
				array(),
				array(
					'temperature' => 0,
					'max_tokens'  => 20,
				)
			);
			return rest_ensure_response(
				array(
					'ok'    => true,
					'reply' => $result['text'],
				)
			);
		} catch ( Exception $e ) {
			return rest_ensure_response(
				array(
					'ok'    => false,
					'error' => $e->getMessage(),
				)
			);
		}
	}
}
