<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Mailer {
	private function items_to_html( $items ) {
		$html = '<ul>';
		foreach ( $items as $it ) {
			$html .= '<li>' . esc_html( $it['name'] ) . ' &times; ' . esc_html( $it['qty'] ) . '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	private function send_html_mail( $to, $subject, $body ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$wrapped = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;line-height:1.5;">' . $body . '</div>';
		return wp_mail( $to, $subject, $wrapped, $headers );
	}

	private function send_customer_confirmation( $order, $email, $name, $items, $reason, $time, $settings ) {
		$subject = $settings['email_onderwerp'];

		$body  = '<p>' . esc_html__( 'Beste', 'wc-herroepingsfunctie' ) . ' ' . esc_html( $name ) . ',</p>';
		$body .= '<p>' . esc_html__( 'We bevestigen de ontvangst van uw herroeping. Hieronder vindt u de inhoud van uw verklaring en het tijdstip van indiening. Bewaar deze e-mail als bewijs.', 'wc-herroepingsfunctie' ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Ingediend op:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $time ) . '<br>';
		$body .= '<strong>' . esc_html__( 'Naam:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $name ) . '<br>';
		$body .= '<strong>' . esc_html__( 'E-mailadres voor bevestiging:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $email ) . '<br>';
		$body .= '<strong>' . esc_html__( 'Bestelling:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $order->get_order_number() ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Herroepen producten:', 'wc-herroepingsfunctie' ) . '</strong></p>';
		$body .= $this->items_to_html( $items );
		if ( '' !== $reason ) {
			$body .= '<p><strong>' . esc_html__( 'Door u opgegeven reden:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $reason ) . '</p>';
		}
		$body .= '<p>' . esc_html__( 'We nemen uw verzoek verder in behandeling, inclusief een eventuele terugbetaling conform de wettelijke termijnen.', 'wc-herroepingsfunctie' ) . '</p>';
		$body .= '<p>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';

		return $this->send_html_mail( $email, $subject, $body );
	}

	private function send_admin_notification( $order, $name, $email, $items, $reason, $time, $settings ) {
		$to      = $settings['admin_email'] ? $settings['admin_email'] : get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %s: order number */
			__( 'Nieuwe herroeping voor bestelling %s', 'wc-herroepingsfunctie' ),
			$order->get_order_number()
		);

		$body  = '<p>' . esc_html__( 'Er is een herroeping ingediend via de online herroepingsfunctie.', 'wc-herroepingsfunctie' ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Bestelling:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $order->get_order_number() ) . '<br>';
		$body .= '<strong>' . esc_html__( 'Klant:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $name ) . ' (' . esc_html( $email ) . ')<br>';
		$body .= '<strong>' . esc_html__( 'Ingediend op:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $time ) . '</p>';
		$body .= $this->items_to_html( $items );
		if ( '' !== $reason ) {
			$body .= '<p><strong>' . esc_html__( 'Reden:', 'wc-herroepingsfunctie' ) . '</strong> ' . esc_html( $reason ) . '</p>';
		}
		$edit = $order->get_edit_order_url();
		if ( $edit ) {
			$body .= '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Bekijk de bestelling', 'wc-herroepingsfunctie' ) . '</a></p>';
		}

		return $this->send_html_mail( $to, $subject, $body );
	}
}
