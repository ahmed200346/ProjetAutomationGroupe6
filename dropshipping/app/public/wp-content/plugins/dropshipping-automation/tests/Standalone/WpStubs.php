<?php
/**
 * Stubs WordPress minimaux (espace de noms global) pour le harnais autonome.
 * Défini uniquement si la fonction WP correspondante est absente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'DSA_PLUGIN_DIR' ) ) {
	define( 'DSA_PLUGIN_DIR', dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['dsa_test_options'] = array();
$GLOBALS['dsa_test_mails']   = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $value ) ) );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		$value = trim( (string) $value );
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^https?:\/\//i', $value ) ) {
			return '';
		}
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return esc_url( $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $value, $decimals = 0 ) {
		return number_format( (float) $value, (int) $decimals, ',', ' ' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['dsa_test_options'] ) ? $GLOBALS['dsa_test_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['dsa_test_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['dsa_test_options'][ $name ] );
		return true;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( $gmt ? 'UTC' : date_default_timezone_get() ) );
		return 'mysql' === $type ? $now->format( 'Y-m-d H:i:s' ) : $now->getTimestamp();
	}
}
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
		$GLOBALS['dsa_test_mails'][] = compact( 'to', 'subject', 'message', 'headers' );
		return true;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt';
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $headers = array();
		private $body = '';

		public function __construct( $method = 'GET', $route = '' ) {}

		public function set_header( $name, $value ) {
			$this->headers[ strtolower( $name ) ] = $value;
		}

		public function get_header( $name ) {
			return $this->headers[ strtolower( $name ) ] ?? '';
		}

		public function set_body( $body ) {
			$this->body = $body;
		}

		public function get_json_params() {
			$decoded = json_decode( $this->body, true );
			return is_array( $decoded ) ? $decoded : null;
		}
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) {
		$GLOBALS['dsa_test_actions'][] = $args;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}
