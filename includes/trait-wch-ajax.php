<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Ajax {
	/* --------------------------------------------------------------------- *
	 *  AJAX: order ophalen.
	 * --------------------------------------------------------------------- */

	public function ajax_lookup_order() {
		check_ajax_referer( 'wch_nonce', 'nonce' );

		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $order_number || '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Vul een geldig ordernummer en e-mailadres in.', 'wc-herroepingsfunctie' ) ) );
		}

		$rate_limited = $this->check_rate_limit( 'lookup_ip', $this->get_ip(), 30, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_limited ) ) {
			wp_send_json_error( array( 'message' => $rate_limited->get_error_message() ) );
		}

		$rate_limited = $this->check_rate_limit( 'lookup_email_ip', $this->get_ip() . '|' . $email, 10, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_limited ) ) {
			wp_send_json_error( array( 'message' => $rate_limited->get_error_message() ) );
		}

		$order = $this->find_order( $order_number );

		// Bewust generieke melding om order-enumeratie te voorkomen.
		$generic = __( 'We konden geen bestelling vinden met deze gegevens. Controleer uw ordernummer en e-mailadres.', 'wc-herroepingsfunctie' );

		if ( ! $order || ! $this->email_matches( $order, $email ) ) {
			wp_send_json_error( array( 'message' => $generic ) );
		}

		$items = $this->get_eligible_items( $order );

		if ( empty( $items ) ) {
			wp_send_json_error( array(
				'message' => __( 'Voor de producten in deze bestelling is herroeping via dit formulier niet beschikbaar (bijvoorbeeld omdat ze al zijn herroepen of zijn uitgesloten). Neem bij vragen contact met ons op.', 'wc-herroepingsfunctie' ),
			) );
		}

		wp_send_json_success( array(
			'customer_name' => trim( $order->get_formatted_billing_full_name() ),
			'items'         => $items,
		) );
	}

	/* --------------------------------------------------------------------- *
	 *  AJAX: herroeping verwerken.
	 * --------------------------------------------------------------------- */

	public function ajax_submit_withdrawal() {
		check_ajax_referer( 'wch_nonce', 'nonce' );

		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$reason       = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$item_ids     = isset( $_POST['item_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['item_ids'] ) ) ) ) ) : array();

		if ( '' === $order_number || '' === $email || ! is_email( $email ) || empty( $item_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Onvolledige aanvraag. Probeer het opnieuw.', 'wc-herroepingsfunctie' ) ) );
		}

		$rate_limited = $this->check_rate_limit( 'submit_ip', $this->get_ip(), 20, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_limited ) ) {
			wp_send_json_error( array( 'message' => $rate_limited->get_error_message() ) );
		}

		$rate_limited = $this->check_rate_limit( 'submit_email_ip', $this->get_ip() . '|' . $email, 6, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_limited ) ) {
			wp_send_json_error( array( 'message' => $rate_limited->get_error_message() ) );
		}

		// Server-side opnieuw verifiëren (vertrouw de client niet).
		$order = $this->find_order( $order_number );
		if ( ! $order || ! $this->email_matches( $order, $email ) ) {
			wp_send_json_error( array( 'message' => __( 'We konden uw bestelling niet verifiëren.', 'wc-herroepingsfunctie' ) ) );
		}

		// Alleen herroepbare items toestaan; herleid naam/aantal uit de order.
		$eligible = $this->get_eligible_items( $order );
		$eligible_map = array();
		foreach ( $eligible as $it ) {
			$eligible_map[ (int) $it['id'] ] = $it;
		}

		$selected = array();
		foreach ( $item_ids as $iid ) {
			if ( isset( $eligible_map[ $iid ] ) ) {
				$selected[] = $eligible_map[ $iid ];
			}
		}

		if ( empty( $selected ) ) {
			wp_send_json_error( array( 'message' => __( 'Geen geldige producten geselecteerd.', 'wc-herroepingsfunctie' ) ) );
		}

		$settings = $this->get_settings();

		$submitted_utc = gmdate( 'Y-m-d H:i:s' );
		$display_time  = wp_date( 'd-m-Y H:i' ); // Lokale weergave in site-tijdzone.
		$name          = trim( $order->get_formatted_billing_full_name() );
		$ip            = ( 'yes' === $settings['bewaar_ip'] ) ? $this->get_ip() : '';

		// 1. Loggen in eigen tabel.
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . WCH_TABLE,
			array(
				'order_id'         => $order->get_id(),
				'order_number'     => $order->get_order_number(),
				'customer_name'    => $name,
				'customer_email'   => $email,
				'items'            => wp_json_encode( $selected ),
				'reason'           => $reason,
				'ip_address'       => $ip,
				'submitted_at_utc' => $submitted_utc,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'We konden uw herroeping niet registreren. Probeer het later opnieuw of neem contact met ons op.', 'wc-herroepingsfunctie' ) ) );
		}

		// 2. Ordernotitie toevoegen (bewijslast + zichtbaar in beheer).
		$lines = array();
		foreach ( $selected as $it ) {
			$lines[] = $it['name'] . ' x ' . $it['qty'];
		}
		$note = sprintf(
			/* translators: 1: timestamp, 2: items, 3: reason */
			__( "Herroeping ontvangen op %1\$s via de online herroepingsfunctie.\nProducten: %2\$s\nReden: %3\$s", 'wc-herroepingsfunctie' ),
			$display_time,
			implode( '; ', $lines ),
			'' !== $reason ? $reason : __( '(geen opgegeven)', 'wc-herroepingsfunctie' )
		);
		$order->add_order_note( $note );

		// 3. Optioneel orderstatus aanpassen (alleen flaggen, geen automatische refund).
		if ( 'yes' === $settings['order_status'] && ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
			$order->update_status( 'on-hold', __( 'In afwachting van afhandeling herroeping. ', 'wc-herroepingsfunctie' ) );
		}

		// 4. Ontvangstbevestiging naar klant (duurzame drager).
		$customer_mail_sent = $this->send_customer_confirmation( $order, $email, $name, $selected, $reason, $display_time, $settings );
		if ( ! $customer_mail_sent ) {
			$order->add_order_note( __( 'Let op: de automatische ontvangstbevestiging voor deze herroeping kon niet worden verzonden.', 'wc-herroepingsfunctie' ) );
		}

		// 5. Interne melding.
		$this->send_admin_notification( $order, $name, $email, $selected, $reason, $display_time, $settings );

		if ( $customer_mail_sent ) {
			/* translators: 1: timestamp, 2: customer email address */
			$message = __( 'Uw herroeping is geregistreerd op %1$s. We hebben een bevestiging gestuurd naar %2$s. We nemen uw verzoek verder in behandeling.', 'wc-herroepingsfunctie' );
		} else {
			/* translators: 1: timestamp, 2: customer email address */
			$message = __( 'Uw herroeping is geregistreerd op %1$s, maar de bevestigingsmail naar %2$s kon niet automatisch worden verzonden. Neem contact met ons op als u geen bevestiging ontvangt.', 'wc-herroepingsfunctie' );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: submission timestamp, 2: email address */
				$message,
				$display_time,
				$email
			),
		) );
	}
}
