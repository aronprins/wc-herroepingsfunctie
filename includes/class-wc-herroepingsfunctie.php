<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hoofdklasse.
 */
final class WC_Herroepingsfunctie {

	use WCH_I18n;
	use WCH_Github_Updater;
	use WCH_Lifecycle;
	use WCH_Settings;
	use WCH_Account;
	use WCH_Shortcode;
	use WCH_Ajax;
	use WCH_Checkout_Waiver;
	use WCH_Orders;
	use WCH_Mailer;
	use WCH_Admin;

	const WAIVER_FIELD_ID        = 'wc-herroepingsfunctie/withdrawal-waiver';
	const WAIVER_META_AGREED    = '_wch_withdrawal_waiver_agreed';
	const WAIVER_META_VERSION   = '_wch_withdrawal_waiver_version';
	const WAIVER_META_TEXT      = '_wch_withdrawal_waiver_text';
	const WAIVER_META_TIMESTAMP = '_wch_withdrawal_waiver_timestamp';
	const WAIVER_META_SOURCE    = '_wch_withdrawal_waiver_source';

	/** @var WC_Herroepingsfunctie */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Activatie / deactivatie.
		register_activation_hook( WCH_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WCH_FILE, array( $this, 'deactivate' ) );

		// HPOS-compatibiliteit declareren (moet vroeg gebeuren).
		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCH_FILE, true );
			}
		} );

		// Zorg dat WooCommerce actief is.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		// WordPress update metadata voor GitHub Releases. Dit staat los van WooCommerce.
		add_filter( 'update_plugins_github.com', array( $this, 'filter_github_release_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'filter_github_release_plugin_information' ), 10, 3 );
		add_action( 'load-update-core.php', array( $this, 'clear_github_release_cache_for_forced_update_check' ), 1 );
	}

	/**
	 * Initialiseren (na plugins_loaded, zodat WooCommerce beschikbaar is).
	 */
	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'WooCommerce Herroepingsfunctie vereist dat WooCommerce actief is.', 'wc-herroepingsfunctie' );
				echo '</p></div>';
			} );
			return;
		}

		// HPOS-compatibiliteit is al gedeclareerd in de constructor.

		// Frontend.
		add_shortcode( 'herroepingsfunctie', array( $this, 'render_shortcode' ) );

		// AJAX (ingelogd én niet-ingelogd).
		add_action( 'wp_ajax_wch_lookup', array( $this, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_nopriv_wch_lookup', array( $this, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_wch_submit', array( $this, 'ajax_submit_withdrawal' ) );
		add_action( 'wp_ajax_nopriv_wch_submit', array( $this, 'ajax_submit_withdrawal' ) );

		// "Mijn account" endpoint + menu-item.
		add_action( 'init', array( $this, 'add_account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_item' ) );
		add_action( 'woocommerce_account_herroepen_endpoint', array( $this, 'render_account_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );

		// Beheer.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'option_page_capability_wch_settings_group', array( $this, 'settings_capability' ) );

		// Checkout: afstandsverklaring voor directe digitale levering.
		add_action( 'woocommerce_init', array( $this, 'register_checkout_block_waiver_field' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_block_assets' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_classic_checkout_waiver' ), 20 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_classic_checkout_waiver' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic_checkout_waiver' ), 10, 2 );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'save_block_checkout_waiver_field' ), 10, 4 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_block_checkout_waiver_from_request' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'save_block_checkout_waiver_order_meta' ) );
		add_action( '__experimental_woocommerce_blocks_validate_location_additional_fields', array( $this, 'validate_experimental_block_waiver_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_waiver_admin_order_meta' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_waiver_email_block' ), 10, 4 );
		add_filter( 'woocommerce_order_button_text', array( $this, 'filter_classic_order_button_text' ) );
	}
}
