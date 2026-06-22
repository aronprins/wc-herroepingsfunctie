<?php
/**
 * Plugin Name:       WooCommerce Herroepingsfunctie (NL)
 * Plugin URI:        https://example.com/
 * Description:        Wettelijk verplichte online herroepingsfunctie voor webshops (art. 6:230oa BW / Richtlijn (EU) 2023/2673). Toont de echte bestelling, ondersteunt gedeeltelijke herroeping, tweestapsbevestiging, automatische ontvangstbevestiging en logging.
 * Version:           1.1.2
 * Author:            Custom
 * License:           GPL-2.0-or-later
 * Text Domain:       wc-herroepingsfunctie
 * Requires Plugins:  woocommerce
 *
 * LET OP: dit is een vertrekpunt. Test op een staging-omgeving en laat de
 * juridische teksten/uitzonderingen door een jurist controleren vóór livegang.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Directe toegang verbieden.
}

define( 'WCH_VERSION', '1.1.2' );
define( 'WCH_OPTION', 'wch_settings' );
define( 'WCH_TABLE', 'wch_herroepingen' );
define( 'WCH_FILE', __FILE__ );

/**
 * Hoofdklasse.
 */
final class WC_Herroepingsfunctie {

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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
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

	/* --------------------------------------------------------------------- *
	 *  Activatie: databasetabel + endpoint.
	 * --------------------------------------------------------------------- */

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
			add_option( WCH_OPTION, $this->default_settings() );
		}

		// Endpoint registreren en permalinks verversen.
		$this->add_account_endpoint();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	private function default_settings() {
		return array(
			'intro_tekst'        => 'Wilt u (een deel van) uw bestelling herroepen binnen de wettelijke bedenktijd van 14 dagen? Vul hieronder uw ordernummer en e-mailadres in.',
			'confirm_knop_tekst' => 'Herroeping bevestigen',
			'email_onderwerp'    => 'Bevestiging van uw herroeping',
			'admin_email'        => get_option( 'admin_email' ),
			'uitgesloten_cats'   => array(),
			'uitgesloten_ids'    => '',
			'sluit_virtueel_uit' => 'no',
			'order_status'       => 'no', // 'yes' = order op 'in afwachting' zetten.
			'bewaar_ip'          => 'no',
			'waiver_enabled'                  => 'yes',
			'waiver_text'                     => 'I agree that the product is delivered immediately after payment, and I acknowledge that I thereby waive my statutory 14-day right of withdrawal.',
			'waiver_version'                  => '1.0',
			'waiver_error'                    => 'Please agree to immediate digital delivery and acknowledge that you waive your right of withdrawal to complete this order.',
			'waiver_button_text'              => 'Buy now (payment required)',
			'waiver_countries'                => $this->get_default_waiver_countries_string(),
			'waiver_unknown_country_requires' => 'yes',
		);
	}

	private function get_settings() {
		$saved = get_option( WCH_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->default_settings() );
	}

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

	/* --------------------------------------------------------------------- *
	 *  Frontend formulier.
	 * --------------------------------------------------------------------- */

	public function render_shortcode( $atts ) {
		$settings = $this->get_settings();
		$nonce    = wp_create_nonce( 'wch_nonce' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$app_id   = wp_unique_id( 'wch-app-' );

		ob_start();
		?>
		<div class="wch-wrap" id="<?php echo esc_attr( $app_id ); ?>">
			<style>
				.wch-wrap{max-width:640px;margin:1.5em 0;font-size:16px;line-height:1.5;}
				.wch-wrap h3{margin:0 0 .5em;}
				.wch-step{display:none;}
				.wch-step.is-active{display:block;}
				.wch-field{margin-bottom:1em;}
				.wch-field label{display:block;font-weight:600;margin-bottom:.25em;}
				.wch-field input[type=text],.wch-field input[type=email],.wch-field textarea{width:100%;padding:.6em;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;}
				.wch-btn{display:inline-block;padding:.7em 1.4em;border:0;border-radius:6px;cursor:pointer;font-size:1em;}
				.wch-btn-primary{background:#1f2d3d;color:#fff;}
				.wch-btn-secondary{background:#e9ecef;color:#1f2d3d;margin-right:.5em;}
				.wch-item{display:flex;align-items:flex-start;gap:.6em;padding:.6em 0;border-bottom:1px solid #eee;}
				.wch-item label{font-weight:400;}
				.wch-notice{padding:.8em 1em;border-radius:6px;margin:1em 0;}
				.wch-error{background:#fdecea;color:#611a15;}
				.wch-success{background:#edf7ed;color:#1e4620;}
				.wch-muted{color:#666;font-size:.9em;}
				.wch-summary{background:#f7f7f8;padding:1em;border-radius:6px;}
				.wch-hidden{display:none;}
			</style>

			<div class="wch-notice wch-error wch-hidden" data-role="error"></div>

			<!-- Stap 1: identificatie -->
			<div class="wch-step is-active" data-step="1">
				<h3><?php esc_html_e( 'Bestelling herroepen', 'wc-herroepingsfunctie' ); ?></h3>
				<p><?php echo esc_html( $settings['intro_tekst'] ); ?></p>
				<div class="wch-field">
					<label for="wch-order"><?php esc_html_e( 'Ordernummer', 'wc-herroepingsfunctie' ); ?></label>
					<input type="text" id="wch-order" autocomplete="off">
				</div>
				<div class="wch-field">
					<label for="wch-email"><?php esc_html_e( 'E-mailadres van de bestelling', 'wc-herroepingsfunctie' ); ?></label>
					<input type="email" id="wch-email" autocomplete="email">
				</div>
				<button type="button" class="wch-btn wch-btn-primary" data-action="lookup">
					<?php esc_html_e( 'Bestelling ophalen', 'wc-herroepingsfunctie' ); ?>
				</button>
			</div>

			<!-- Stap 2: selectie -->
			<div class="wch-step" data-step="2">
				<h3><?php esc_html_e( 'Wat wilt u herroepen?', 'wc-herroepingsfunctie' ); ?></h3>
				<p class="wch-muted"><?php esc_html_e( 'Selecteer de producten waarvoor u de overeenkomst wilt herroepen. U bent niet verplicht een reden op te geven.', 'wc-herroepingsfunctie' ); ?></p>
				<div data-role="items"></div>
				<div class="wch-field" style="margin-top:1em;">
					<label for="wch-reason"><?php esc_html_e( 'Reden (optioneel)', 'wc-herroepingsfunctie' ); ?></label>
					<textarea id="wch-reason" rows="3"></textarea>
				</div>
				<button type="button" class="wch-btn wch-btn-secondary" data-action="back"><?php esc_html_e( 'Terug', 'wc-herroepingsfunctie' ); ?></button>
				<button type="button" class="wch-btn wch-btn-primary" data-action="toconfirm"><?php esc_html_e( 'Doorgaan', 'wc-herroepingsfunctie' ); ?></button>
			</div>

			<!-- Stap 3: bevestigen -->
			<div class="wch-step" data-step="3">
				<h3><?php esc_html_e( 'Herroeping bevestigen', 'wc-herroepingsfunctie' ); ?></h3>
				<p><?php esc_html_e( 'Hierbij deelt u mede dat u de overeenkomst voor de onderstaande producten herroept. U ontvangt direct een ontvangstbevestiging per e-mail.', 'wc-herroepingsfunctie' ); ?></p>
				<div class="wch-summary" data-role="summary"></div>
				<p style="margin-top:1em;">
					<button type="button" class="wch-btn wch-btn-secondary" data-action="back2"><?php esc_html_e( 'Terug', 'wc-herroepingsfunctie' ); ?></button>
					<button type="button" class="wch-btn wch-btn-primary" data-action="submit"><?php echo esc_html( $settings['confirm_knop_tekst'] ); ?></button>
				</p>
			</div>

			<!-- Stap 4: klaar -->
			<div class="wch-step" data-step="4">
				<div class="wch-notice wch-success" data-role="done"></div>
			</div>
		</div>

		<script>
		(function(){
			var app = document.getElementById(<?php echo wp_json_encode( $app_id ); ?>);
			if(!app || app.dataset.init){ return; }
			app.dataset.init = '1';

			var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var state   = { order:'', email:'', customerName:'', items:[], selected:[] };

			function $(sel){ return app.querySelector(sel); }
			function show(step){
				app.querySelectorAll('.wch-step').forEach(function(s){ s.classList.remove('is-active'); });
				app.querySelector('[data-step="'+step+'"]').classList.add('is-active');
			}
			function err(msg){
				var box = app.querySelector('[data-role=error]');
				if(!msg){ box.classList.add('wch-hidden'); return; }
				box.textContent = msg; box.classList.remove('wch-hidden');
			}
			function post(action, data){
				var body = new URLSearchParams();
				body.append('action', action);
				body.append('nonce', nonce);
				Object.keys(data).forEach(function(k){
					if(Array.isArray(data[k])){ data[k].forEach(function(v){ body.append(k+'[]', v); }); }
					else { body.append(k, data[k]); }
				});
				return fetch(ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
					.then(function(r){ return r.json(); });
			}
			function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

			app.addEventListener('click', function(e){
				var a = e.target.getAttribute('data-action');
				if(!a){ return; }

				if(a==='lookup'){
					err('');
					state.order = $('#wch-order').value.trim();
					state.email = $('#wch-email').value.trim();
					if(!state.order || !state.email){ err('Vul uw ordernummer en e-mailadres in.'); return; }
					e.target.disabled = true;
					post('wch_lookup', {order_number:state.order, email:state.email}).then(function(res){
						e.target.disabled = false;
						if(!res || !res.success){ err(res && res.data ? res.data.message : 'Er ging iets mis.'); return; }
						state.customerName = res.data.customer_name || '';
						state.items = res.data.items;
						var html = '';
						state.items.forEach(function(it){
							html += '<div class="wch-item">'
								+ '<input type="checkbox" id="wch-it-'+esc(it.id)+'" value="'+esc(it.id)+'" data-itemcb>'
								+ '<label for="wch-it-'+esc(it.id)+'">'+esc(it.name)+' &times; '+esc(it.qty)+'</label>'
								+ '</div>';
						});
						app.querySelector('[data-role=items]').innerHTML = html;
						show(2);
					}).catch(function(){ e.target.disabled=false; err('Er ging iets mis. Probeer het later opnieuw.'); });
				}

				if(a==='back'){ err(''); show(1); }
				if(a==='back2'){ err(''); show(2); }

				if(a==='toconfirm'){
					err('');
					var cbs = app.querySelectorAll('[data-itemcb]:checked');
					if(cbs.length === 0){ err('Selecteer minimaal één product om te herroepen.'); return; }
					state.selected = Array.prototype.map.call(cbs, function(c){ return c.value; });
					var names = state.items.filter(function(it){ return state.selected.indexOf(String(it.id)) > -1; });
					var sum = '<p><strong>Naam:</strong> '+esc(state.customerName || '-')+'<br>'
						+ '<strong>E-mailadres voor bevestiging:</strong> '+esc(state.email)+'</p>';
					sum += '<ul style="margin:.5em 0 .5em 1.2em;">';
					names.forEach(function(it){ sum += '<li>'+esc(it.name)+' &times; '+esc(it.qty)+'</li>'; });
					sum += '</ul>';
					var reason = $('#wch-reason').value.trim();
					if(reason){ sum += '<p><strong>Reden:</strong> '+esc(reason)+'</p>'; }
					sum += '<p class="wch-muted">Bestelling: '+esc(state.order)+'</p>';
					app.querySelector('[data-role=summary]').innerHTML = sum;
					show(3);
				}

				if(a==='submit'){
					err('');
					e.target.disabled = true;
					post('wch_submit', {
						order_number: state.order,
						email: state.email,
						reason: $('#wch-reason').value.trim(),
						item_ids: state.selected
					}).then(function(res){
						e.target.disabled = false;
						if(!res || !res.success){ err(res && res.data ? res.data.message : 'Er ging iets mis.'); show(2); return; }
						app.querySelector('[data-role=done]').innerHTML = esc(res.data.message);
						show(4);
					}).catch(function(){ e.target.disabled=false; err('Er ging iets mis. Probeer het later opnieuw.'); show(2); });
				}
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

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

		$message = $customer_mail_sent
			? __( 'Uw herroeping is geregistreerd op %1$s. We hebben een bevestiging gestuurd naar %2$s. We nemen uw verzoek verder in behandeling.', 'wc-herroepingsfunctie' )
			: __( 'Uw herroeping is geregistreerd op %1$s, maar de bevestigingsmail naar %2$s kon niet automatisch worden verzonden. Neem contact met ons op als u geen bevestiging ontvangt.', 'wc-herroepingsfunctie' );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: submission timestamp, 2: email address */
				$message,
				$display_time,
				$email
			),
		) );
	}

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
					'buttonText'             => $settings['waiver_button_text'],
					'countryCodes'           => $this->get_waiver_country_codes(),
					'unknownCountryRequires' => 'yes' === $settings['waiver_unknown_country_requires'],
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

	/* --------------------------------------------------------------------- *
	 *  Beheer: instellingen + overzicht.
	 * --------------------------------------------------------------------- */

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Herroepingen', 'wc-herroepingsfunctie' ),
			__( 'Herroepingen', 'wc-herroepingsfunctie' ),
			'manage_woocommerce',
			'wch-herroepingen',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'Herroepingsfunctie instellingen', 'wc-herroepingsfunctie' ),
			__( 'Herroeping instellingen', 'wc-herroepingsfunctie' ),
			'manage_woocommerce',
			'wch-instellingen',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'wch_settings_group', WCH_OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function settings_capability() {
		return 'manage_woocommerce';
	}

	public function sanitize_settings( $input ) {
		$out = $this->default_settings();
		if ( ! is_array( $input ) ) {
			return $out;
		}

		$out['intro_tekst']        = isset( $input['intro_tekst'] ) ? sanitize_textarea_field( $input['intro_tekst'] ) : $out['intro_tekst'];
		$out['confirm_knop_tekst'] = isset( $input['confirm_knop_tekst'] ) ? sanitize_text_field( $input['confirm_knop_tekst'] ) : $out['confirm_knop_tekst'];
		$out['email_onderwerp']    = isset( $input['email_onderwerp'] ) ? sanitize_text_field( $input['email_onderwerp'] ) : $out['email_onderwerp'];
		$admin_email               = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $out['admin_email'];
		$out['admin_email']        = is_email( $admin_email ) ? $admin_email : get_option( 'admin_email' );
		$out['uitgesloten_cats']   = isset( $input['uitgesloten_cats'] ) ? array_map( 'absint', (array) $input['uitgesloten_cats'] ) : array();
		$out['uitgesloten_ids']    = isset( $input['uitgesloten_ids'] ) ? sanitize_text_field( $input['uitgesloten_ids'] ) : '';
		$out['sluit_virtueel_uit'] = ( isset( $input['sluit_virtueel_uit'] ) && 'yes' === $input['sluit_virtueel_uit'] ) ? 'yes' : 'no';
		$out['order_status']       = ( isset( $input['order_status'] ) && 'yes' === $input['order_status'] ) ? 'yes' : 'no';
		$out['bewaar_ip']          = ( isset( $input['bewaar_ip'] ) && 'yes' === $input['bewaar_ip'] ) ? 'yes' : 'no';
		$out['waiver_enabled']                  = ( isset( $input['waiver_enabled'] ) && 'yes' === $input['waiver_enabled'] ) ? 'yes' : 'no';
		$out['waiver_text']                     = isset( $input['waiver_text'] ) ? sanitize_textarea_field( $input['waiver_text'] ) : $out['waiver_text'];
		$out['waiver_version']                  = isset( $input['waiver_version'] ) ? sanitize_text_field( $input['waiver_version'] ) : $out['waiver_version'];
		$out['waiver_error']                    = isset( $input['waiver_error'] ) ? sanitize_text_field( $input['waiver_error'] ) : $out['waiver_error'];
		$out['waiver_button_text']              = isset( $input['waiver_button_text'] ) ? sanitize_text_field( $input['waiver_button_text'] ) : $out['waiver_button_text'];
		$out['waiver_countries']                = isset( $input['waiver_countries'] ) ? $this->sanitize_waiver_country_codes( $input['waiver_countries'] ) : $out['waiver_countries'];
		$out['waiver_unknown_country_requires'] = ( isset( $input['waiver_unknown_country_requires'] ) && 'yes' === $input['waiver_unknown_country_requires'] ) ? 'yes' : 'no';
		return $out;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s    = $this->get_settings();
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Herroepingsfunctie – instellingen', 'wc-herroepingsfunctie' ); ?></h1>
			<p><?php esc_html_e( 'Plaats het formulier op een goed vindbare pagina met de shortcode', 'wc-herroepingsfunctie' ); ?> <code>[herroepingsfunctie]</code>. <?php esc_html_e( 'Het staat ook automatisch in "Mijn account" onder "Herroepen". Zet bovendien een duidelijke link in de footer.', 'wc-herroepingsfunctie' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wch_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="wch_intro"><?php esc_html_e( 'Introductietekst', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><textarea id="wch_intro" name="<?php echo esc_attr( WCH_OPTION ); ?>[intro_tekst]" rows="3" class="large-text"><?php echo esc_textarea( $s['intro_tekst'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="wch_btn"><?php esc_html_e( 'Tekst bevestigingsknop', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_btn" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[confirm_knop_tekst]" value="<?php echo esc_attr( $s['confirm_knop_tekst'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="wch_subj"><?php esc_html_e( 'Onderwerp bevestigingsmail', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_subj" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[email_onderwerp]" value="<?php echo esc_attr( $s['email_onderwerp'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="wch_admin"><?php esc_html_e( 'Interne meldingen naar', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_admin" type="email" name="<?php echo esc_attr( WCH_OPTION ); ?>[admin_email]" value="<?php echo esc_attr( $s['admin_email'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uitgesloten categorieën', 'wc-herroepingsfunctie' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( WCH_OPTION ); ?>[uitgesloten_cats][]" multiple size="6" style="min-width:280px;">
								<?php
								if ( ! is_wp_error( $cats ) ) {
									foreach ( $cats as $cat ) {
										printf(
											'<option value="%1$d" %2$s>%3$s</option>',
											(int) $cat->term_id,
											in_array( (int) $cat->term_id, (array) $s['uitgesloten_cats'], true ) ? 'selected' : '',
											esc_html( $cat->name )
										);
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Bijv. maatwerk, verzegelde hygiëneproducten of bederfelijke waren – producten zonder herroepingsrecht.', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wch_ids"><?php esc_html_e( 'Uitgesloten product-ID\'s', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_ids" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[uitgesloten_ids]" value="<?php echo esc_attr( $s['uitgesloten_ids'] ); ?>" class="regular-text" placeholder="123, 456, 789"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Digitale producten uitsluiten', 'wc-herroepingsfunctie' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( WCH_OPTION ); ?>[sluit_virtueel_uit]" value="yes" <?php checked( 'yes', $s['sluit_virtueel_uit'] ); ?>> <?php esc_html_e( 'Virtuele/downloadbare producten niet tonen in de herroepingsfunctie', 'wc-herroepingsfunctie' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Orderstatus aanpassen', 'wc-herroepingsfunctie' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( WCH_OPTION ); ?>[order_status]" value="yes" <?php checked( 'yes', $s['order_status'] ); ?>> <?php esc_html_e( 'Order op "in afwachting" zetten bij een herroeping (geen automatische terugbetaling)', 'wc-herroepingsfunctie' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'IP-adres bewaren', 'wc-herroepingsfunctie' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( WCH_OPTION ); ?>[bewaar_ip]" value="yes" <?php checked( 'yes', $s['bewaar_ip'] ); ?>> <?php esc_html_e( 'IP-adres loggen als extra bewijs (vermeld dit in uw privacyverklaring)', 'wc-herroepingsfunctie' ); ?></label></td>
					</tr>
					<tr>
						<th colspan="2"><h2><?php esc_html_e( 'Checkout-afstandsverklaring voor digitale producten', 'wc-herroepingsfunctie' ); ?></h2></th>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Afstandsverklaring inschakelen', 'wc-herroepingsfunctie' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_enabled]" value="yes" <?php checked( 'yes', $s['waiver_enabled'] ); ?>> <?php esc_html_e( 'Verplichte checkbox tonen bij carts die uitsluitend virtuele/downloadbare producten bevatten', 'wc-herroepingsfunctie' ); ?></label>
							<p class="description"><?php esc_html_e( 'Werkt met classic checkout en met de WooCommerce Checkout Block. De plugin verplicht de checkbox server-side alleen voor volledig digitale carts en de hieronder ingestelde factuurlanden.', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wch_waiver_countries"><?php esc_html_e( 'Landen waarvoor afstandsverklaring geldt', 'wc-herroepingsfunctie' ); ?></label></th>
						<td>
							<textarea id="wch_waiver_countries" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_countries]" rows="3" class="large-text code"><?php echo esc_textarea( $s['waiver_countries'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Komma-, puntkomma- of spatiegescheiden ISO 3166-1 alpha-2 landcodes. Standaard: EU + EER (AT, BE, BG, HR, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LT, LU, MT, NL, PL, PT, RO, SK, SI, ES, SE, IS, LI, NO).', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Onbekend factuurland', 'wc-herroepingsfunctie' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_unknown_country_requires]" value="yes" <?php checked( 'yes', $s['waiver_unknown_country_requires'] ); ?>> <?php esc_html_e( 'Checkbox verplichten zolang het factuurland nog leeg of onbekend is', 'wc-herroepingsfunctie' ); ?></label>
							<p class="description"><?php esc_html_e( 'Aanbevolen fail-closed gedrag: zodra een niet-ingesteld land is geselecteerd, verdwijnt de checkbox en wordt deze niet verplicht.', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wch_waiver_text"><?php esc_html_e( 'Checkboxtekst', 'wc-herroepingsfunctie' ); ?></label></th>
						<td>
							<textarea id="wch_waiver_text" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_text]" rows="4" class="large-text"><?php echo esc_textarea( $s['waiver_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Deze tekst wordt opgeslagen op de order en opgenomen in de klantmail als duurzame bevestiging.', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wch_waiver_version"><?php esc_html_e( 'Tekstversie', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_waiver_version" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_version]" value="<?php echo esc_attr( $s['waiver_version'] ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Verhoog deze versie wanneer de checkboxtekst wijzigt.', 'wc-herroepingsfunctie' ); ?></span></td>
					</tr>
					<tr>
						<th><label for="wch_waiver_error"><?php esc_html_e( 'Validatiemelding', 'wc-herroepingsfunctie' ); ?></label></th>
						<td><input id="wch_waiver_error" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_error]" value="<?php echo esc_attr( $s['waiver_error'] ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="wch_waiver_button"><?php esc_html_e( 'Betaalknoptekst', 'wc-herroepingsfunctie' ); ?></label></th>
						<td>
							<input id="wch_waiver_button" type="text" name="<?php echo esc_attr( WCH_OPTION ); ?>[waiver_button_text]" value="<?php echo esc_attr( $s['waiver_button_text'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Classic checkout en Checkout Block gebruiken deze tekst alleen wanneer de digitale-cart en factuurlandregels gelden.', 'wc-herroepingsfunctie' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_list_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . WCH_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", 200 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ontvangen herroepingen', 'wc-herroepingsfunctie' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Datum', 'wc-herroepingsfunctie' ); ?></th>
						<th><?php esc_html_e( 'Order', 'wc-herroepingsfunctie' ); ?></th>
						<th><?php esc_html_e( 'Klant', 'wc-herroepingsfunctie' ); ?></th>
						<th><?php esc_html_e( 'Producten', 'wc-herroepingsfunctie' ); ?></th>
						<th><?php esc_html_e( 'Reden', 'wc-herroepingsfunctie' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Nog geen herroepingen ontvangen.', 'wc-herroepingsfunctie' ); ?></td></tr>
				<?php else : foreach ( $rows as $r ) :
					$items = json_decode( (string) $r->items, true );
					$names = array();
					if ( is_array( $items ) ) {
						foreach ( $items as $it ) {
							$names[] = ( isset( $it['name'] ) ? $it['name'] : '' ) . ' x ' . ( isset( $it['qty'] ) ? $it['qty'] : '' );
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td>
							<?php
							$o = wc_get_order( (int) $r->order_id );
							if ( $o ) {
								printf( '<a href="%s">#%s</a>', esc_url( $o->get_edit_order_url() ), esc_html( $r->order_number ) );
							} else {
								echo esc_html( $r->order_number );
							}
							?>
						</td>
						<td><?php echo esc_html( $r->customer_name ); ?><br><span class="description"><?php echo esc_html( $r->customer_email ); ?></span></td>
						<td><?php echo esc_html( implode( '; ', $names ) ); ?></td>
						<td><?php echo esc_html( $r->reason ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

WC_Herroepingsfunctie::instance();
