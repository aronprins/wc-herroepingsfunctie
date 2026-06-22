<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Checkout_Waiver {
	/* --------------------------------------------------------------------- *
	 *  Checkout: afstandsverklaring voor directe digitale levering.
	 * --------------------------------------------------------------------- */

	public function register_checkout_block_waiver_field() {
		$settings = $this->get_settings();
		if ( 'yes' !== $settings['waiver_enabled'] ) {
			return;
		}

		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			woocommerce_register_additional_checkout_field( $this->get_block_waiver_field_args( 'order', true ) );
			return;
		}

		if ( function_exists( '__experimental_woocommerce_blocks_register_checkout_field' ) ) {
			__experimental_woocommerce_blocks_register_checkout_field( $this->get_block_waiver_field_args( 'additional', false ) );
		}
	}

	private function get_block_waiver_field_args( $location, $supports_conditional_schema ) {
		$settings = $this->get_settings();

		$args = array(
			'id'                => self::WAIVER_FIELD_ID,
			'label'             => $settings['waiver_text'],
			'optionalLabel'     => $settings['waiver_text'],
			'location'          => $location,
			'type'              => 'checkbox',
			'error_message'     => $settings['waiver_error'],
			'sanitize_callback' => array( $this, 'sanitize_waiver_checkbox_value' ),
			'validate_callback' => array( $this, 'validate_block_waiver_checkbox_value' ),
		);

		if ( ! $supports_conditional_schema ) {
			return $args;
		}

		$physical_cart_schema = array(
			'cart' => array(
				'properties' => array(
					'needs_shipping' => array(
						'const' => true,
					),
				),
			),
		);

		$args['hidden'] = $physical_cart_schema;

		return $args;
	}

	public function enqueue_checkout_block_assets() {
		$settings = $this->get_settings();
		if ( 'yes' !== $settings['waiver_enabled'] ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			return;
		}

		wp_register_script(
			'wch-checkout-blocks',
			plugins_url( 'assets/js/checkout-blocks.js', WCH_FILE ),
			array( 'wc-blocks-checkout' ),
			WCH_VERSION,
			true
		);
		wp_add_inline_script(
			'wch-checkout-blocks',
			'window.wchCheckoutWaiver = ' . wp_json_encode(
				array(
					'buttonText'                 => $settings['waiver_button_text'],
					'fallbackButtonText'         => __( 'Place Order', 'woocommerce' ),
					'cartHasOnlyDigitalProducts' => $this->cart_has_only_digital_products(),
					'countryCodes'               => $this->get_waiver_country_codes(),
					'unknownCountryRequires'     => 'yes' === $settings['waiver_unknown_country_requires'],
				)
			) . ';',
			'before'
		);
		wp_enqueue_script( 'wch-checkout-blocks' );
	}

	public function render_classic_checkout_waiver() {
		if ( ! $this->cart_requires_checkout_waiver() ) {
			return;
		}

		$settings = $this->get_settings();
		woocommerce_form_field(
			'wch_withdrawal_waiver_agreed',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row', 'validate-required' ),
				'required' => true,
				'label'    => $settings['waiver_text'],
			),
			''
		);
	}

	public function validate_classic_checkout_waiver() {
		if ( ! $this->cart_requires_checkout_waiver() ) {
			return;
		}

		if ( ! $this->request_has_waiver_agreement() ) {
			$settings = $this->get_settings();
			wc_add_notice( $settings['waiver_error'], 'error' );
		}
	}

	public function save_classic_checkout_waiver( $order, $data ) {
		if ( ! $order instanceof WC_Order || ! $this->cart_requires_checkout_waiver() || ! $this->request_has_waiver_agreement() ) {
			return;
		}

		$this->store_withdrawal_waiver_on_order( $order, 'classic_checkout' );
	}

	public function save_block_checkout_waiver_field( $field_key, $field_value, $group, $wc_object ) {
		$billing_country = $this->get_checkout_billing_country();
		if (
			'' === $this->normalize_country_code( $billing_country ) ||
			self::WAIVER_FIELD_ID !== $field_key ||
			! in_array( $group, array( 'other', 'additional' ), true ) ||
			! ( $wc_object instanceof WC_Order ) ||
			! $this->cart_requires_checkout_waiver_for_country( $billing_country ) ||
			! $this->is_truthy( $field_value )
		) {
			return;
		}

		$this->store_withdrawal_waiver_on_order( $wc_object, 'checkout_block' );
	}

	public function save_block_checkout_waiver_from_request( $order, $request ) {
		if ( ! $order instanceof WC_Order || 'yes' === $order->get_meta( self::WAIVER_META_AGREED, true ) ) {
			return;
		}

		$billing_country = $this->get_checkout_billing_country_from_request( $request );
		$requires        = $this->cart_requires_checkout_waiver_for_country( $billing_country );
		$has_agreement   = $this->request_has_block_waiver_agreement( $request );

		if ( $requires && ! $has_agreement ) {
			$this->throw_store_api_checkout_error();
		}

		if ( $requires && $has_agreement ) {
			$this->store_withdrawal_waiver_on_order( $order, 'checkout_block' );
		}
	}

	public function save_block_checkout_waiver_order_meta( $order ) {
		if ( ! $order instanceof WC_Order || 'yes' === $order->get_meta( self::WAIVER_META_AGREED, true ) ) {
			return;
		}

		if ( $this->cart_requires_checkout_waiver() && $this->order_has_block_waiver_value( $order ) ) {
			$this->store_withdrawal_waiver_on_order( $order, 'checkout_block' );
		}
	}

	public function sanitize_waiver_checkbox_value( $field_value ) {
		return $this->is_truthy( $field_value ) ? '1' : '0';
	}

	public function validate_block_waiver_checkbox_value( $field_value ) {
		$billing_country = $this->get_checkout_billing_country();
		if (
			'' !== $this->normalize_country_code( $billing_country ) &&
			$this->cart_requires_checkout_waiver_for_country( $billing_country ) &&
			! $this->is_truthy( $field_value )
		) {
			$settings = $this->get_settings();
			return new WP_Error( 'wch_withdrawal_waiver_required', $settings['waiver_error'] );
		}
	}

	public function validate_experimental_block_waiver_fields( $errors, $fields, $group ) {
		$billing_country = $this->get_checkout_billing_country();
		if ( '' === $this->normalize_country_code( $billing_country ) || ! ( $errors instanceof WP_Error ) || ! $this->cart_requires_checkout_waiver_for_country( $billing_country ) ) {
			return;
		}

		$value = is_array( $fields ) && isset( $fields[ self::WAIVER_FIELD_ID ] ) ? $fields[ self::WAIVER_FIELD_ID ] : '';
		if ( ! $this->is_truthy( $value ) ) {
			$settings = $this->get_settings();
			$errors->add( 'wch_withdrawal_waiver_required', $settings['waiver_error'] );
		}
	}

	public function filter_classic_order_button_text( $text ) {
		if ( ! $this->cart_requires_checkout_waiver() ) {
			return $text;
		}

		$settings = $this->get_settings();
		return '' !== trim( (string) $settings['waiver_button_text'] ) ? $settings['waiver_button_text'] : $text;
	}

	public function render_waiver_admin_order_meta( $order ) {
		if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( self::WAIVER_META_AGREED, true ) ) {
			return;
		}

		printf(
			'<p><strong>%1$s</strong><br>%2$s<br>%3$s<br>%4$s</p>',
			esc_html__( 'Afstandsverklaring herroepingsrecht:', 'wc-herroepingsfunctie' ),
			esc_html(
				sprintf(
					/* translators: 1: version, 2: timestamp */
					__( 'Akkoord vastgelegd (versie %1$s) op %2$s.', 'wc-herroepingsfunctie' ),
					$order->get_meta( self::WAIVER_META_VERSION, true ),
					$order->get_meta( self::WAIVER_META_TIMESTAMP, true )
				)
			),
			esc_html( $order->get_meta( self::WAIVER_META_TEXT, true ) ),
			esc_html(
				sprintf(
					/* translators: %s: source */
					__( 'Bron: %s', 'wc-herroepingsfunctie' ),
					$order->get_meta( self::WAIVER_META_SOURCE, true )
				)
			)
		);
	}

	public function render_waiver_email_block( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( self::WAIVER_META_AGREED, true ) ) {
			return;
		}

		$text      = $order->get_meta( self::WAIVER_META_TEXT, true );
		$version   = $order->get_meta( self::WAIVER_META_VERSION, true );
		$timestamp = $order->get_meta( self::WAIVER_META_TIMESTAMP, true );

		if ( $plain_text ) {
			echo "\n\n" . esc_html__( 'Waiver of the right of withdrawal', 'wc-herroepingsfunctie' ) . "\n";
			echo esc_html( $text ) . "\n";
			echo esc_html(
				sprintf(
					/* translators: 1: version, 2: timestamp */
					__( 'Recorded as version %1$s on %2$s.', 'wc-herroepingsfunctie' ),
					$version,
					$timestamp
				)
			) . "\n";
			return;
		}

		echo '<h2>' . esc_html__( 'Waiver of the right of withdrawal', 'wc-herroepingsfunctie' ) . '</h2>';
		echo '<p>' . esc_html( $text ) . '</p>';
		echo '<p><small>' . esc_html(
			sprintf(
				/* translators: 1: version, 2: timestamp */
				__( 'Recorded as version %1$s on %2$s.', 'wc-herroepingsfunctie' ),
				$version,
				$timestamp
			)
		) . '</small></p>';
	}

	private function cart_requires_checkout_waiver() {
		$settings = $this->get_settings();
		if ( 'yes' !== $settings['waiver_enabled'] || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		return $this->cart_requires_checkout_waiver_for_country( $this->get_checkout_billing_country() );
	}

	private function cart_requires_checkout_waiver_for_country( $country ) {
		$settings = $this->get_settings();
		if ( 'yes' !== $settings['waiver_enabled'] || ! $this->cart_has_only_digital_products() ) {
			return false;
		}

		return $this->country_requires_checkout_waiver( $country );
	}

	private function cart_has_only_digital_products() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product || ( ! $product->is_downloadable() && ! $product->is_virtual() ) ) {
				return false;
			}
		}

		return true;
	}

	private function country_requires_checkout_waiver( $country ) {
		$country = $this->normalize_country_code( $country );
		if ( '' === $country ) {
			$settings = $this->get_settings();
			return 'yes' === $settings['waiver_unknown_country_requires'];
		}

		return in_array( $country, $this->get_waiver_country_codes(), true );
	}

	private function get_checkout_billing_country() {
		if ( isset( $_POST['billing_country'] ) ) {
			return $this->normalize_country_code( wp_unslash( $_POST['billing_country'] ) );
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$billing_country = WC()->customer->get_billing_country();
			if ( '' !== $this->normalize_country_code( $billing_country ) ) {
				return $billing_country;
			}

			return WC()->customer->get_shipping_country();
		}

		return '';
	}

	private function get_checkout_billing_country_from_request( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $this->get_checkout_billing_country();
		}

		$billing_address = $request->get_param( 'billing_address' );
		if ( is_array( $billing_address ) && isset( $billing_address['country'] ) ) {
			$country = $this->normalize_country_code( $billing_address['country'] );
			if ( '' !== $country ) {
				return $country;
			}
		}

		$shipping_address = $request->get_param( 'shipping_address' );
		if ( is_array( $shipping_address ) && isset( $shipping_address['country'] ) ) {
			$country = $this->normalize_country_code( $shipping_address['country'] );
			if ( '' !== $country ) {
				return $country;
			}
		}

		return $this->get_checkout_billing_country();
	}

	private function order_has_block_waiver_value( $order ) {
		$value = $order->get_meta( '_wc_other/' . self::WAIVER_FIELD_ID, true );
		if ( $this->is_truthy( $value ) ) {
			return true;
		}

		$legacy_fields = $order->get_meta( '_additional_fields', true );
		return is_array( $legacy_fields ) && isset( $legacy_fields[ self::WAIVER_FIELD_ID ] ) && $this->is_truthy( $legacy_fields[ self::WAIVER_FIELD_ID ] );
	}

	private function store_withdrawal_waiver_on_order( $order, $source ) {
		if ( 'yes' === $order->get_meta( self::WAIVER_META_AGREED, true ) ) {
			return;
		}

		$settings = $this->get_settings();
		$order->update_meta_data( self::WAIVER_META_AGREED, 'yes' );
		$order->update_meta_data( self::WAIVER_META_VERSION, $settings['waiver_version'] );
		$order->update_meta_data( self::WAIVER_META_TEXT, $settings['waiver_text'] );
		$order->update_meta_data( self::WAIVER_META_TIMESTAMP, current_time( 'mysql' ) );
		$order->update_meta_data( self::WAIVER_META_SOURCE, sanitize_key( $source ) );
	}

	private function is_truthy( $value ) {
		return true === $value || 1 === $value || '1' === $value || 'yes' === $value || 'true' === $value || 'on' === $value;
	}

	private function request_has_waiver_agreement() {
		$value = isset( $_POST['wch_withdrawal_waiver_agreed'] ) ? sanitize_text_field( wp_unslash( $_POST['wch_withdrawal_waiver_agreed'] ) ) : '';
		return $this->is_truthy( $value );
	}

	private function request_has_block_waiver_agreement( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return false;
		}

		$additional_fields = $request->get_param( 'additional_fields' );
		if ( is_array( $additional_fields ) && isset( $additional_fields[ self::WAIVER_FIELD_ID ] ) ) {
			return $this->is_truthy( $additional_fields[ self::WAIVER_FIELD_ID ] );
		}

		$extensions = $request->get_param( 'extensions' );
		if ( is_array( $extensions ) && isset( $extensions['wc-herroepingsfunctie'][ self::WAIVER_FIELD_ID ] ) ) {
			return $this->is_truthy( $extensions['wc-herroepingsfunctie'][ self::WAIVER_FIELD_ID ] );
		}

		return false;
	}

	private function throw_store_api_checkout_error() {
		$settings = $this->get_settings();
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'wch_withdrawal_waiver_required',
				$settings['waiver_error'],
				400
			);
		}

		throw new Exception( esc_html( $settings['waiver_error'] ) );
	}

	private function get_default_waiver_country_codes() {
		return array(
			'AT',
			'BE',
			'BG',
			'HR',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GR',
			'HU',
			'IE',
			'IT',
			'LV',
			'LT',
			'LU',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE',
			'IS',
			'LI',
			'NO',
		);
	}

	private function get_default_waiver_countries_string() {
		return implode( ',', $this->get_default_waiver_country_codes() );
	}

	private function get_waiver_country_codes() {
		$settings = $this->get_settings();
		$codes    = $this->parse_country_codes( $settings['waiver_countries'] );
		return ! empty( $codes ) ? $codes : $this->get_default_waiver_country_codes();
	}

	private function sanitize_waiver_country_codes( $value ) {
		$codes = $this->parse_country_codes( $value );
		return ! empty( $codes ) ? implode( ',', $codes ) : $this->get_default_waiver_countries_string();
	}

	private function parse_country_codes( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}

		$parts = preg_split( '/[\s,;]+/', strtoupper( (string) $value ) );
		$codes = array();
		foreach ( (array) $parts as $part ) {
			$code = $this->normalize_country_code( $part );
			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	private function normalize_country_code( $country ) {
		$country = strtoupper( trim( sanitize_text_field( (string) $country ) ) );
		return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
	}
}
