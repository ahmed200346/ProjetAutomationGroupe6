<?php
/**
 * Tools the model can call, executed server-side.
 *
 * Privacy model: every tool runs with the identity of the WordPress user
 * attached to the REST request (cookie + nonce). Order tools only ever
 * read the CURRENT user's orders — the model physically cannot query
 * another customer, whatever the prompt says. Anonymous visitors get a
 * "please log in" result instead of data.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Tools {

	/**
	 * Tool definitions for the current context.
	 *
	 * @param int $user_id Current user id (0 = anonymous).
	 * @return array Internal tool-definition format.
	 */
	public static function definitions( $user_id ) {
		$tools = array();
		$has_wc = function_exists( 'wc_get_product' );

		if ( $has_wc && Rafiq_Settings::get( 'tool_product_search' ) ) {
			$tools[] = array(
				'name'        => 'search_products',
				'description' => 'Search the store catalog. Returns live name, price, stock status and link for matching products. Use it whenever the visitor asks about a product, a price or availability.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'Search terms, e.g. "margherita pizza".',
						),
					),
					'required'   => array( 'query' ),
				),
			);
		}

		if ( $has_wc && Rafiq_Settings::get( 'tool_orders' ) ) {
			$tools[] = array(
				'name'        => 'get_my_orders',
				'description' => 'List the most recent orders of the currently logged-in customer (status, date, total, items). Works only for the logged-in visitor; returns a login notice otherwise.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => new stdClass(), // Encodes as {} for JSON-schema validity.
				),
			);
			$tools[] = array(
				'name'        => 'get_order_status',
				'description' => 'Get the status and details of one specific order belonging to the currently logged-in customer.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_number' => array(
							'type'        => 'string',
							'description' => 'The order number the customer mentioned.',
						),
					),
					'required'   => array( 'order_number' ),
				),
			);
		}

		return $tools;
	}

	/**
	 * Execute a tool call. Always returns a string for the model.
	 *
	 * @param string $name    Tool name.
	 * @param array  $args    Arguments from the model.
	 * @param int    $user_id Current user id (0 = anonymous).
	 * @return string
	 */
	public static function execute( $name, array $args, $user_id ) {
		/**
		 * Short-circuits tool execution, letting add-ons implement their own
		 * tools. Return any string to bypass the built-in handlers.
		 *
		 * @param null|string $result  Null to run the built-in tools.
		 * @param string      $name    Tool name.
		 * @param array       $args    Model-supplied arguments.
		 * @param int         $user_id Current user id (0 = anonymous).
		 */
		$external = apply_filters( 'rafiq_execute_tool', null, $name, $args, $user_id );
		if ( null !== $external ) {
			return (string) $external;
		}

		try {
			switch ( $name ) {
				case 'search_products':
					return self::search_products( isset( $args['query'] ) ? (string) $args['query'] : '' );
				case 'get_my_orders':
					return self::get_my_orders( $user_id );
				case 'get_order_status':
					return self::get_order_status( isset( $args['order_number'] ) ? (string) $args['order_number'] : '', $user_id );
			}
			return 'Unknown tool.';
		} catch ( Exception $e ) {
			return 'Tool error: ' . $e->getMessage();
		}
	}

	private static function search_products( $query ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return 'WooCommerce is not active on this site.';
		}
		$query = sanitize_text_field( $query );
		if ( '' === $query ) {
			return 'Empty query.';
		}

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 5,
				's'      => $query,
			)
		);

		if ( ! $products ) {
			return 'No products found for: ' . $query;
		}

		$lines = array();
		foreach ( $products as $product ) {
			$lines[] = wp_json_encode(
				array(
					'name'     => $product->get_name(),
					'price'    => wp_strip_all_tags( wc_price( $product->get_price() ) ),
					'in_stock' => $product->is_in_stock(),
					'url'      => get_permalink( $product->get_id() ),
					'summary'  => mb_substr( wp_strip_all_tags( $product->get_short_description() ), 0, 200 ),
				)
			);
		}
		return implode( "\n", $lines );
	}

	private static function get_my_orders( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 'WooCommerce is not active on this site.';
		}
		if ( ! $user_id ) {
			return 'The visitor is not logged in. Ask them to log in to their account to see their orders.';
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		if ( ! $orders ) {
			return 'This customer has no orders yet.';
		}

		$lines = array();
		foreach ( $orders as $order ) {
			$lines[] = wp_json_encode( self::order_summary( $order ) );
		}
		return implode( "\n", $lines );
	}

	private static function get_order_status( $order_number, $user_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return 'WooCommerce is not active on this site.';
		}
		if ( ! $user_id ) {
			return 'The visitor is not logged in. Ask them to log in to their account to check an order.';
		}
		$order_number = preg_replace( '/\D+/', '', $order_number );
		if ( '' === $order_number ) {
			return 'Invalid order number.';
		}

		$order = wc_get_order( (int) $order_number );
		// Ownership check: never leak another customer's order.
		if ( ! $order || (int) $order->get_customer_id() !== (int) $user_id ) {
			return 'No order with this number was found on this account.';
		}
		return wp_json_encode( self::order_summary( $order ) );
	}

	/**
	 * Minimal order projection: no addresses, no email, no payment details.
	 */
	private static function order_summary( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		return array(
			'order_number' => $order->get_order_number(),
			'date'         => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : '',
			'status'       => wc_get_order_status_name( $order->get_status() ),
			'total'        => wp_strip_all_tags( $order->get_formatted_order_total() ),
			'items'        => $items,
		);
	}
}
