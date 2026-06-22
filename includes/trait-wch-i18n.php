<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_I18n {
	public function load_textdomain() {
		load_plugin_textdomain( 'wc-herroepingsfunctie', false, dirname( plugin_basename( WCH_FILE ) ) . '/languages' );

		if ( is_textdomain_loaded( 'wc-herroepingsfunctie' ) ) {
			return;
		}

		$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$fallback = $this->get_locale_translation_fallback( $locale );
		if ( '' === $fallback || $fallback === $locale ) {
			return;
		}

		$mofile = plugin_dir_path( WCH_FILE ) . 'languages/wc-herroepingsfunctie-' . $fallback . '.mo';
		if ( is_readable( $mofile ) ) {
			load_textdomain( 'wc-herroepingsfunctie', $mofile );
		}
	}

	private function get_locale_translation_fallback( $locale ) {
		$language = strtolower( strtok( (string) $locale, '_' ) );
		$map      = array(
			'bg' => 'bg_BG',
			'cs' => 'cs_CZ',
			'da' => 'da_DK',
			'de' => 'de_DE',
			'el' => 'el',
			'en' => 'en_GB',
			'es' => 'es_ES',
			'et' => 'et',
			'fi' => 'fi',
			'fr' => 'fr_FR',
			'ga' => 'ga_IE',
			'hr' => 'hr',
			'hu' => 'hu_HU',
			'is' => 'is_IS',
			'it' => 'it_IT',
			'lt' => 'lt_LT',
			'lv' => 'lv',
			'mt' => 'mt_MT',
			'nb' => 'nb_NO',
			'nl' => 'nl_NL',
			'no' => 'nb_NO',
			'pl' => 'pl_PL',
			'pt' => 'pt_PT',
			'ro' => 'ro_RO',
			'sk' => 'sk_SK',
			'sl' => 'sl_SI',
			'sv' => 'sv_SE',
		);

		return isset( $map[ $language ] ) ? $map[ $language ] : '';
	}
}
