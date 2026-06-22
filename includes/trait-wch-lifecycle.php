<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Lifecycle {
	public function activate() {
		global $wpdb;
		$table           = $wpdb->prefix . WCH_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_number VARCHAR(100) NOT NULL DEFAULT '',
			customer_name VARCHAR(190) NOT NULL DEFAULT '',
			customer_email VARCHAR(190) NOT NULL DEFAULT '',
			items LONGTEXT NULL,
			reason TEXT NULL,
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			submitted_at_utc DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY customer_email (customer_email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Standaardinstellingen zetten als ze nog niet bestaan.
		if ( false === get_option( WCH_OPTION ) ) {
			add_option( WCH_OPTION, $this->default_settings( false ) );
		}

		// Endpoint registreren en permalinks verversen.
		$this->add_account_endpoint();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

}
