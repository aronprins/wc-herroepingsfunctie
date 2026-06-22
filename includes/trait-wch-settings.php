<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Settings {
	private function default_settings( $translate = true ) {
		$settings = array(
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

		if ( ! $translate ) {
			return $settings;
		}

		foreach ( $this->get_translatable_setting_keys() as $key ) {
			$settings[ $key ] = $this->translate_default_setting_value( $key, $settings[ $key ] );
		}

		return $settings;
	}

	private function get_settings() {
		$saved    = get_option( WCH_OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$settings = wp_parse_args( $saved, $this->default_settings( false ) );
		return $this->localize_default_setting_values( $settings, $saved );
	}

	private function localize_default_setting_values( $settings, $saved ) {
		$localized_defaults = $this->default_settings( true );
		$raw_defaults       = $this->default_settings( false );

		foreach ( $this->get_translatable_setting_keys() as $key ) {
			if ( ! array_key_exists( $key, $saved ) || ( isset( $raw_defaults[ $key ] ) && (string) $saved[ $key ] === (string) $raw_defaults[ $key ] ) ) {
				$settings[ $key ] = $localized_defaults[ $key ];
			}
		}

		return $settings;
	}

	private function get_translatable_setting_keys() {
		return array(
			'intro_tekst',
			'confirm_knop_tekst',
			'email_onderwerp',
			'waiver_text',
			'waiver_error',
			'waiver_button_text',
		);
	}

	private function translate_default_setting_value( $key, $fallback ) {
		switch ( $key ) {
			case 'intro_tekst':
				return __( 'Wilt u (een deel van) uw bestelling herroepen binnen de wettelijke bedenktijd van 14 dagen? Vul hieronder uw ordernummer en e-mailadres in.', 'wc-herroepingsfunctie' );
			case 'confirm_knop_tekst':
				return __( 'Herroeping bevestigen', 'wc-herroepingsfunctie' );
			case 'email_onderwerp':
				return __( 'Bevestiging van uw herroeping', 'wc-herroepingsfunctie' );
			case 'waiver_text':
				return __( 'I agree that the product is delivered immediately after payment, and I acknowledge that I thereby waive my statutory 14-day right of withdrawal.', 'wc-herroepingsfunctie' );
			case 'waiver_error':
				return __( 'Please agree to immediate digital delivery and acknowledge that you waive your right of withdrawal to complete this order.', 'wc-herroepingsfunctie' );
			case 'waiver_button_text':
				return __( 'Buy now (payment required)', 'wc-herroepingsfunctie' );
			default:
				return $fallback;
		}
	}
}
