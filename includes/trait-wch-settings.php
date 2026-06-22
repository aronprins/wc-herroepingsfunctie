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
			if ( $this->is_explicit_default_translation_value( $key, $settings, $saved ) ) {
				continue;
			}

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

	private function get_default_translation_preview_fields() {
		return array(
			'intro_tekst'        => '#wch_intro',
			'confirm_knop_tekst' => '#wch_btn',
			'email_onderwerp'    => '#wch_subj',
			'waiver_text'        => '#wch_waiver_text',
			'waiver_error'       => '#wch_waiver_error',
			'waiver_button_text' => '#wch_waiver_button',
		);
	}

	private function get_saved_default_translation_preview_state() {
		$saved = get_option( WCH_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		return array(
			'locale' => isset( $saved['_default_translation_locale'] ) ? sanitize_text_field( $saved['_default_translation_locale'] ) : '',
			'fields' => isset( $saved['_default_translation_fields'] ) ? array_values( array_intersect( $this->get_translatable_setting_keys(), (array) $saved['_default_translation_fields'] ) ) : array(),
		);
	}

	private function get_submitted_default_translation_preview_state( $input, $settings ) {
		$state = array(
			'locale' => '',
			'fields' => array(),
		);

		$locale = isset( $input['_default_translation_locale'] ) ? sanitize_text_field( $input['_default_translation_locale'] ) : '';
		if ( '' === $locale ) {
			return $state;
		}

		$translation_sets = $this->get_available_default_translation_sets();
		if ( ! isset( $translation_sets[ $locale ] ) ) {
			return $state;
		}

		$submitted_fields = isset( $input['_default_translation_fields'] ) ? array_map( 'sanitize_key', (array) $input['_default_translation_fields'] ) : array();
		$submitted_fields = array_intersect( $this->get_translatable_setting_keys(), $submitted_fields );

		foreach ( $submitted_fields as $key ) {
			if ( isset( $settings[ $key ], $translation_sets[ $locale ]['values'][ $key ] ) && (string) $settings[ $key ] === (string) $translation_sets[ $locale ]['values'][ $key ] ) {
				$state['fields'][] = $key;
			}
		}

		if ( empty( $state['fields'] ) ) {
			return $state;
		}

		$state['locale'] = $locale;
		return $state;
	}

	private function is_explicit_default_translation_value( $key, $settings, $saved ) {
		if ( ! isset( $saved['_default_translation_locale'], $saved['_default_translation_fields'], $settings[ $key ] ) ) {
			return false;
		}

		if ( ! in_array( $key, (array) $saved['_default_translation_fields'], true ) ) {
			return false;
		}

		$locale           = sanitize_text_field( $saved['_default_translation_locale'] );
		$translation_sets = $this->get_available_default_translation_sets();

		return isset( $translation_sets[ $locale ]['values'][ $key ] ) && (string) $settings[ $key ] === (string) $translation_sets[ $locale ]['values'][ $key ];
	}

	private function get_available_default_translation_sets() {
		$sets         = array();
		$fields       = $this->get_translatable_setting_keys();
		$raw_defaults = $this->default_settings( false );
		$files        = glob( plugin_dir_path( WCH_FILE ) . 'languages/wc-herroepingsfunctie-*.mo' );

		if ( ! is_array( $files ) ) {
			return $sets;
		}

		foreach ( $files as $mofile ) {
			if ( ! preg_match( '/wc-herroepingsfunctie-([A-Za-z0-9_]+)\.mo$/', basename( $mofile ), $matches ) ) {
				continue;
			}

			$locale = $matches[1];
			$mo     = $this->load_translation_preview_mo( $mofile );

			if ( ! $mo ) {
				continue;
			}

			$values = array();
			foreach ( $fields as $key ) {
				if ( isset( $raw_defaults[ $key ] ) ) {
					$values[ $key ] = $mo->translate( $raw_defaults[ $key ] );
				}
			}

			$sets[ $locale ] = array(
				'locale' => $locale,
				'label'  => $this->get_translation_locale_label( $locale ),
				'values' => $values,
			);
		}

		uasort(
			$sets,
			function ( $a, $b ) {
				return strnatcasecmp( $a['label'], $b['label'] );
			}
		);

		return $sets;
	}

	private function load_translation_preview_mo( $mofile ) {
		if ( ! class_exists( 'MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}

		$mo = new MO();
		if ( ! $mo->import_from_file( $mofile ) ) {
			return false;
		}

		return $mo;
	}

	private function get_translation_locale_label( $locale ) {
		$labels = array(
			'bg_BG' => 'Bulgarian (Bulgaria)',
			'cs_CZ' => 'Czech (Czechia)',
			'da_DK' => 'Danish (Denmark)',
			'de_DE' => 'German (Germany)',
			'el'    => 'Greek',
			'en_GB' => 'English (United Kingdom)',
			'en_US' => 'English (United States)',
			'es_ES' => 'Spanish (Spain)',
			'et'    => 'Estonian',
			'fi'    => 'Finnish',
			'fr_FR' => 'French (France)',
			'ga_IE' => 'Irish (Ireland)',
			'hr'    => 'Croatian',
			'hu_HU' => 'Hungarian (Hungary)',
			'is_IS' => 'Icelandic (Iceland)',
			'it_IT' => 'Italian (Italy)',
			'lt_LT' => 'Lithuanian (Lithuania)',
			'lv'    => 'Latvian',
			'mt_MT' => 'Maltese (Malta)',
			'nb_NO' => 'Norwegian Bokmal (Norway)',
			'nl_NL' => 'Dutch (Netherlands)',
			'pl_PL' => 'Polish (Poland)',
			'pt_PT' => 'Portuguese (Portugal)',
			'ro_RO' => 'Romanian (Romania)',
			'sk_SK' => 'Slovak (Slovakia)',
			'sl_SI' => 'Slovenian (Slovenia)',
			'sv_SE' => 'Swedish (Sweden)',
		);

		return isset( $labels[ $locale ] ) ? $labels[ $locale ] . ' - ' . $locale : $locale;
	}
}
