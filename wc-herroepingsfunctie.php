<?php
/**
 * Plugin Name:       WooCommerce Herroepingsfunctie (NL)
 * Plugin URI:        https://aronandsharon.com
 * Description:        Wettelijk verplichte online herroepingsfunctie voor webshops (art. 6:230oa BW / Richtlijn (EU) 2023/2673). Toont de echte bestelling, ondersteunt gedeeltelijke herroeping, tweestapsbevestiging, automatische ontvangstbevestiging en logging.
 * Version:           1.1.6
 * Author:            Aron & Sharon
 * Author URI:        https://aronandsharon.com
 * License:           GPL-2.0-or-later
 * Text Domain:       wc-herroepingsfunctie
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Update URI:        https://github.com/aronprins/wc-herroepingsfunctie
 *
 * LET OP: deze plugin wordt geleverd "as-is" en vervangt geen juridisch advies.
 * Test op een staging-omgeving en laat de juridische teksten/uitzonderingen
 * door een jurist controleren vóór livegang.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Directe toegang verbieden.
}

define( 'WCH_VERSION', '1.1.6' );
define( 'WCH_OPTION', 'wch_settings' );
define( 'WCH_TABLE', 'wch_herroepingen' );
define( 'WCH_FILE', __FILE__ );
define( 'WCH_PLUGIN_SLUG', 'wc-herroepingsfunctie' );
define( 'WCH_PLUGIN_HOMEPAGE', 'https://aronandsharon.com' );
define( 'WCH_UPDATE_URI', 'https://github.com/aronprins/wc-herroepingsfunctie' );
define( 'WCH_GITHUB_RELEASE_API', 'https://api.github.com/repos/aronprins/wc-herroepingsfunctie/releases/latest' );
define( 'WCH_GITHUB_RELEASE_TRANSIENT', 'wch_github_release_latest' );

require_once __DIR__ . '/includes/trait-wch-i18n.php';
require_once __DIR__ . '/includes/trait-wch-github-updater.php';
require_once __DIR__ . '/includes/trait-wch-lifecycle.php';
require_once __DIR__ . '/includes/trait-wch-settings.php';
require_once __DIR__ . '/includes/trait-wch-account.php';
require_once __DIR__ . '/includes/trait-wch-shortcode.php';
require_once __DIR__ . '/includes/trait-wch-ajax.php';
require_once __DIR__ . '/includes/trait-wch-checkout-waiver.php';
require_once __DIR__ . '/includes/trait-wch-orders.php';
require_once __DIR__ . '/includes/trait-wch-mailer.php';
require_once __DIR__ . '/includes/trait-wch-admin.php';
require_once __DIR__ . '/includes/class-wc-herroepingsfunctie.php';

WC_Herroepingsfunctie::instance();
