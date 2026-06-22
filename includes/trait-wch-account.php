<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Account {
	/* --------------------------------------------------------------------- *
	 *  "Mijn account" endpoint.
	 * --------------------------------------------------------------------- */

	public function add_account_endpoint() {
		add_rewrite_endpoint( 'herroepen', EP_ROOT | EP_PAGES );
	}

	public function add_query_var( $vars ) {
		$vars[] = 'herroepen';
		return $vars;
	}

	public function account_menu_item( $items ) {
		// Voeg "Herroepen" toe vóór "Uitloggen".
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new['herroepen'] = __( 'Herroepen', 'wc-herroepingsfunctie' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['herroepen'] ) ) {
			$new['herroepen'] = __( 'Herroepen', 'wc-herroepingsfunctie' );
		}
		return $new;
	}

	public function render_account_endpoint() {
		echo $this->render_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
