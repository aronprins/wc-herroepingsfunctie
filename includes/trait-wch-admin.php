<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Admin {
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
		$out = $this->default_settings( false );
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
			<?php settings_errors(); ?>
			<p><?php esc_html_e( 'Plaats het formulier op een goed vindbare pagina met de shortcode', 'wc-herroepingsfunctie' ); ?> <code>[herroepingsfunctie]</code>. <?php esc_html_e( 'Het staat ook automatisch in "Mijn account" onder "Herroepen". Zet bovendien een duidelijke link in de footer.', 'wc-herroepingsfunctie' ); ?></p>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Juridische disclaimer', 'wc-herroepingsfunctie' ); ?></strong></p>
				<p><?php esc_html_e( 'Deze plugin wordt geleverd "as-is" en vervangt geen juridisch advies. Laat alle juridische teksten, uitzonderingen, landenlijsten, checkout-afstandsverklaringen en vertalingen controleren door een jurist voordat u deze plugin in productie gebruikt.', 'wc-herroepingsfunctie' ); ?></p>
			</div>
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
