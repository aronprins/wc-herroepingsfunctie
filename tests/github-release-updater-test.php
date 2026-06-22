<?php
/**
 * Plain PHP tests for the GitHub Releases updater.
 *
 * These tests stub the small WordPress surface needed by the updater, so they
 * can run without a WordPress or WooCommerce install:
 *
 * php tests/github-release-updater-test.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$wch_test_filters    = array();
$wch_test_transients = array();
$wch_test_deleted_transients = array();
$wch_test_is_admin = true;
$wch_test_caps = array(
	'update_plugins' => true,
);

function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}

function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

function apply_filters( $hook, $value ) {
	global $wch_test_filters;

	return array_key_exists( $hook, $wch_test_filters ) ? $wch_test_filters[ $hook ] : $value;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function get_site_transient( $key ) {
	global $wch_test_transients;

	return array_key_exists( $key, $wch_test_transients ) ? $wch_test_transients[ $key ] : false;
}

function set_site_transient( $key, $value, $expiration = 0 ) {
	global $wch_test_transients;

	unset( $expiration );
	$wch_test_transients[ $key ] = $value;
	return true;
}

function delete_site_transient( $key ) {
	global $wch_test_transients, $wch_test_deleted_transients;

	$wch_test_deleted_transients[] = $key;
	unset( $wch_test_transients[ $key ] );
	return true;
}

function wp_remote_get( $url, $args = array() ) {
	unset( $url, $args );

	return array(
		'response' => array( 'code' => 500 ),
		'body'     => '',
	);
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

function is_wp_error( $value ) {
	return false;
}

function is_admin() {
	global $wch_test_is_admin;

	return $wch_test_is_admin;
}

function current_user_can( $capability ) {
	global $wch_test_caps;

	return ! empty( $wch_test_caps[ $capability ] );
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( $path, '/' );
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/wc-herroepingsfunctie.php';

function wch_set_release_fixture( $release ) {
	global $wch_test_filters;

	$wch_test_filters['wch_github_release_updater_response'] = $release;
}

function wch_release_fixture( $version, $overrides = array() ) {
	$tag_name = isset( $overrides['tag_name'] ) ? $overrides['tag_name'] : 'v' . $version;
	$release  = array(
		'tag_name'     => $tag_name,
		'html_url'     => WCH_UPDATE_URI . '/releases/tag/' . $tag_name,
		'draft'        => false,
		'prerelease'   => false,
		'published_at' => '2026-06-22T12:00:00Z',
		'body'         => "Release notes\n\n- Updater test fixture",
		'assets'       => array(
			array(
				'name'                 => WCH_PLUGIN_SLUG . '-' . $version . '.zip',
				'browser_download_url' => WCH_UPDATE_URI . '/releases/download/' . $tag_name . '/' . WCH_PLUGIN_SLUG . '-' . $version . '.zip',
				'content_type'         => 'application/zip',
				'size'                 => 12345,
				'digest'               => 'sha256:abc123',
			),
		),
	);

	return array_replace_recursive( $release, $overrides );
}

function wch_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function wch_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$plugin      = WC_Herroepingsfunctie::instance();
$plugin_file = 'wc-herroepingsfunctie/wc-herroepingsfunctie.php';
$plugin_data = array(
	'Version'   => WCH_VERSION,
	'UpdateURI' => WCH_UPDATE_URI,
);

$wch_test_transients[ WCH_GITHUB_RELEASE_TRANSIENT ] = array( 'version' => WCH_VERSION );
$_GET['force-check'] = '1';
$plugin->clear_github_release_cache_for_forced_update_check();
wch_assert_true( ! isset( $wch_test_transients[ WCH_GITHUB_RELEASE_TRANSIENT ] ), 'forced update checks clear the GitHub release cache' );
wch_assert_same( array( WCH_GITHUB_RELEASE_TRANSIENT ), $wch_test_deleted_transients, 'forced update check deletes the expected transient once' );
unset( $_GET['force-check'] );

$version_parts = array_map( 'absint', explode( '.', WCH_VERSION ) );
while ( count( $version_parts ) < 3 ) {
	$version_parts[] = 0;
}
$version_parts[2]++;
$future_version = implode( '.', array_slice( $version_parts, 0, 3 ) );

wch_set_release_fixture( wch_release_fixture( $future_version ) );
$payload = $plugin->filter_github_release_update( false, $plugin_data, $plugin_file, array() );
wch_assert_same( $future_version, $payload['version'], 'newer release version is exposed' );
wch_assert_same( WCH_UPDATE_URI . '/releases/download/v' . $future_version . '/wc-herroepingsfunctie-' . $future_version . '.zip', $payload['package'], 'newer release package URL is exposed' );
wch_assert_same( false, $payload['autoupdate'], 'updater does not force automatic updates' );

$sentinel = array( 'existing' => true );
$payload  = $plugin->filter_github_release_update( $sentinel, $plugin_data, 'other-plugin/other-plugin.php', array() );
wch_assert_same( $sentinel, $payload, 'other github-hosted plugins are ignored' );

wch_set_release_fixture( wch_release_fixture( WCH_VERSION ) );
$payload = $plugin->filter_github_release_update( false, $plugin_data, $plugin_file, array() );
wch_assert_same( WCH_VERSION, $payload['version'], 'current release can populate no-update metadata' );
wch_assert_same( '', $payload['package'], 'current release does not expose an install package as an update' );

wch_set_release_fixture( wch_release_fixture( $future_version, array( 'prerelease' => true ) ) );
$payload = $plugin->filter_github_release_update( false, $plugin_data, $plugin_file, array() );
wch_assert_same( false, $payload, 'prereleases are ignored' );

wch_set_release_fixture(
	wch_release_fixture(
			$future_version,
			array(
				'assets' => array(
					array(
						'name'                 => 'source.zip',
						'browser_download_url' => WCH_UPDATE_URI . '/releases/download/v' . $future_version . '/source.zip',
					),
				),
			)
	)
);
$payload = $plugin->filter_github_release_update( false, $plugin_data, $plugin_file, array() );
wch_assert_same( false, $payload, 'release without exact installable ZIP is ignored' );

wch_set_release_fixture(
	wch_release_fixture(
			$future_version,
			array(
				'assets' => array(
					array(
						'name'                 => 'wc-herroepingsfunctie-' . $future_version . '.zip',
						'browser_download_url' => 'https://example.test/wc-herroepingsfunctie-' . $future_version . '.zip',
					),
				),
			)
	)
);
$payload = $plugin->filter_github_release_update( false, $plugin_data, $plugin_file, array() );
wch_assert_same( false, $payload, 'non-GitHub asset URL is ignored' );

wch_set_release_fixture( wch_release_fixture( $future_version ) );
$info = $plugin->filter_github_release_plugin_information( false, 'plugin_information', (object) array( 'slug' => WCH_PLUGIN_SLUG ) );
wch_assert_true( is_object( $info ), 'plugin information returns an object' );
wch_assert_same( $future_version, $info->version, 'plugin information reports latest version' );
wch_assert_true( false !== strpos( $info->sections['changelog'], 'Updater test fixture' ), 'plugin information includes release notes' );

$info = $plugin->filter_github_release_plugin_information( false, 'plugin_information', (object) array( 'slug' => 'other-plugin' ) );
wch_assert_same( false, $info, 'plugin information ignores other slugs' );

echo "GitHub release updater tests passed.\n";
