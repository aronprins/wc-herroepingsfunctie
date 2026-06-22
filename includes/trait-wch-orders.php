<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Orders {
	/* --------------------------------------------------------------------- *
	 *  Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * Zoek order op ordernummer (ID of _order_number meta).
	 *
	 * @return WC_Order|false
	 */
	private function find_order( $order_number ) {
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_key'   => '_order_number',
			'meta_value' => $order_number,
			'return'     => 'objects',
		) );

		if ( ! empty( $orders ) && $orders[0] instanceof WC_Order ) {
			return $orders[0];
		}

		if ( is_numeric( $order_number ) ) {
			$maybe = wc_get_order( (int) $order_number );
			if ( $maybe instanceof WC_Order && (string) $maybe->get_order_number() === (string) $order_number ) {
				return $maybe;
			}
		}

		return false;
	}

	private function email_matches( $order, $email ) {
		return strtolower( trim( $order->get_billing_email() ) ) === strtolower( trim( $email ) );
	}

	private function get_withdrawn_item_ids( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . WCH_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT items FROM {$table} WHERE order_id = %d", $order_id ) );

		$item_ids = array();
		foreach ( (array) $rows as $row ) {
			$items = json_decode( (string) $row, true );
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( isset( $item['id'] ) ) {
					$item_ids[] = absint( $item['id'] );
				}
			}
		}

		return array_unique( array_filter( $item_ids ) );
	}

	/**
	 * Geef de herroepbare regelitems van een order terug.
	 *
	 * @return array[] [ ['id'=>line_item_id, 'name'=>..., 'qty'=>...], ... ]
	 */
	private function get_eligible_items( $order ) {
		$settings   = $this->get_settings();
		$excl_cats  = array_map( 'absint', (array) $settings['uitgesloten_cats'] );
		$excl_ids   = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $settings['uitgesloten_ids'] ) ) ) );
		$skip_virt  = ( 'yes' === $settings['sluit_virtueel_uit'] );
		$withdrawn  = $this->get_withdrawn_item_ids( $order->get_id() );

		$result = array();

		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			return apply_filters( 'wch_eligible_items', $result, $order );
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			if ( in_array( (int) $item_id, $withdrawn, true ) ) {
				continue;
			}
			$qty          = (int) $item->get_quantity();
			$refunded_qty = absint( $order->get_qty_refunded_for_item( $item_id ) );
			$remaining    = max( 0, $qty - $refunded_qty );
			if ( 0 >= $remaining ) {
				continue;
			}

			$product_id = $item->get_product_id();
			$product    = $item->get_product();

			// Uitsluitingen.
			if ( in_array( $product_id, $excl_ids, true ) ) {
				continue;
			}
			if ( $product && $skip_virt && ( $product->is_virtual() || $product->is_downloadable() ) ) {
				continue;
			}
			if ( ! empty( $excl_cats ) ) {
				$terms = wc_get_product_term_ids( $product_id, 'product_cat' );
				if ( array_intersect( $excl_cats, (array) $terms ) ) {
					continue;
				}
			}

			$result[] = array(
				'id'   => (int) $item_id,
				'name' => $item->get_name(),
				'qty'  => $remaining,
			);
		}

		/**
		 * Filter om de herroepbare items aan te passen (bijv. eigen logica voor
		 * bederfelijke producten of bedenktijd op basis van leverdatum).
		 */
		return apply_filters( 'wch_eligible_items', $result, $order );
	}

	private function get_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	private function check_rate_limit( $scope, $identifier, $limit, $window ) {
		$identifier = strtolower( trim( (string) $identifier ) );
		if ( '' === $identifier ) {
			$identifier = 'unknown';
		}

		$key    = 'wch_rate_' . md5( $scope . '|' . $identifier . '|' . wp_salt( 'nonce' ) );
		$record = get_transient( $key );
		$count  = is_array( $record ) && isset( $record['count'] ) ? (int) $record['count'] : 0;
		$count++;

		set_transient( $key, array( 'count' => $count ), absint( $window ) );

		if ( $count > absint( $limit ) ) {
			return new WP_Error( 'wch_rate_limited', __( 'Te veel pogingen. Wacht even en probeer het later opnieuw.', 'wc-herroepingsfunctie' ) );
		}

		return false;
	}
}
