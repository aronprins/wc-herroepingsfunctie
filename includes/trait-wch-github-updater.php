<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Github_Updater {
	/* --------------------------------------------------------------------- *
	 *  GitHub Releases updater.
	 * --------------------------------------------------------------------- */

	public function filter_github_release_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( plugin_basename( WCH_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $update;
		}

		$payload = $this->build_github_update_payload( $release );
		return $payload ? $payload : $update;
	}

	public function filter_github_release_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || WCH_PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_github_release();
		$version = $release ? $release['version'] : WCH_VERSION;
		$changelog = $release && '' !== $release['body'] ? $release['body'] : 'Release details are available on GitHub.';

		return (object) array(
			'name'          => 'WooCommerce Herroepingsfunctie (NL)',
			'slug'          => WCH_PLUGIN_SLUG,
			'version'       => $version,
			'author'        => 'Custom',
			'homepage'      => WCH_UPDATE_URI,
			'requires'      => $this->get_plugin_header_value( 'Requires at least', '6.5' ),
			'tested'        => $this->get_readme_field_value( 'Tested up to', '7.0' ),
			'requires_php'  => $this->get_plugin_header_value( 'Requires PHP', '7.4' ),
			'last_updated'  => $release ? $release['published_at'] : '',
			'download_link' => $release ? $release['asset']['browser_download_url'] : '',
			'sections'      => array(
				'description' => '<p>' . $this->esc_html_fallback( 'WooCommerce compliance plugin for a public online withdrawal flow and a digital checkout waiver for immediate digital delivery.' ) . '</p>',
				'changelog'   => $this->format_plugin_information_text( $changelog ),
			),
		);
	}

	private function get_latest_github_release() {
		$filtered_response = apply_filters( 'wch_github_release_updater_response', null );
		if ( null !== $filtered_response ) {
			return $this->normalize_github_release_response( $filtered_response );
		}

		$cached = get_site_transient( WCH_GITHUB_RELEASE_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			WCH_GITHUB_RELEASE_API,
			array(
				'timeout'    => 10,
				'headers'    => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
				'user-agent' => 'wc-herroepingsfunctie/' . WCH_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$release = $this->normalize_github_release_response( wp_remote_retrieve_body( $response ) );
		if ( ! $release ) {
			return null;
		}

		$ttl = defined( 'HOUR_IN_SECONDS' ) ? 6 * HOUR_IN_SECONDS : 21600;
		set_site_transient( WCH_GITHUB_RELEASE_TRANSIENT, $release, $ttl );

		return $release;
	}

	private function normalize_github_release_response( $response ) {
		if ( is_string( $response ) ) {
			$response = json_decode( $response, true );
		}

		if ( ! is_array( $response ) || ! empty( $response['draft'] ) || ! empty( $response['prerelease'] ) ) {
			return null;
		}

		$tag_name = isset( $response['tag_name'] ) ? (string) $response['tag_name'] : '';
		$version  = preg_replace( '/^v/i', '', $tag_name );
		if ( '' === $version || ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return null;
		}

		$asset = $this->find_github_release_zip_asset( $response, $version );
		if ( ! $asset ) {
			return null;
		}

		return array(
			'version'      => $version,
			'tag_name'     => $tag_name,
			'html_url'     => isset( $response['html_url'] ) ? esc_url_raw( $response['html_url'] ) : WCH_UPDATE_URI . '/releases/tag/' . rawurlencode( $tag_name ),
			'body'         => isset( $response['body'] ) ? (string) $response['body'] : '',
			'published_at' => isset( $response['published_at'] ) ? sanitize_text_field( $response['published_at'] ) : '',
			'asset'        => $asset,
		);
	}

	private function find_github_release_zip_asset( $response, $version ) {
		if ( empty( $response['assets'] ) || ! is_array( $response['assets'] ) ) {
			return null;
		}

		$expected_name = WCH_PLUGIN_SLUG . '-' . $version . '.zip';
		foreach ( $response['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['name'] ) || $expected_name !== (string) $asset['name'] ) {
				continue;
			}

			$download_url = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( ! $this->is_trusted_github_release_asset_url( $download_url ) ) {
				continue;
			}

			return array(
				'name'                 => $expected_name,
				'browser_download_url' => esc_url_raw( $download_url ),
				'digest'               => isset( $asset['digest'] ) ? sanitize_text_field( $asset['digest'] ) : '',
				'size'                 => isset( $asset['size'] ) ? absint( $asset['size'] ) : 0,
			);
		}

		return null;
	}

	private function is_trusted_github_release_asset_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return false;
		}

		return 'https' === strtolower( $parts['scheme'] )
			&& 'github.com' === strtolower( $parts['host'] )
			&& 0 === strpos( $parts['path'], '/aronprins/wc-herroepingsfunctie/releases/download/' );
	}

	private function build_github_update_payload( $release ) {
		if ( empty( $release['version'] ) || empty( $release['asset']['browser_download_url'] ) ) {
			return null;
		}

		$is_newer = version_compare( $release['version'], WCH_VERSION, '>' );

		return array(
			'id'           => WCH_UPDATE_URI,
			'slug'         => WCH_PLUGIN_SLUG,
			'version'      => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $is_newer ? $release['asset']['browser_download_url'] : '',
			'tested'       => $this->get_readme_field_value( 'Tested up to', '7.0' ),
			'requires_php' => $this->get_plugin_header_value( 'Requires PHP', '7.4' ),
			'autoupdate'   => false,
		);
	}

	private function get_plugin_header_value( $header, $fallback ) {
		static $headers = null;

		if ( null === $headers ) {
			$headers = array();
			$contents = file_get_contents( WCH_FILE, false, null, 0, 8192 );
			if ( is_string( $contents ) ) {
				foreach ( preg_split( '/\R/', $contents ) as $line ) {
					if ( preg_match( '/^\s*\*\s*([^:]+):\s*(.+)$/', $line, $matches ) ) {
						$headers[ strtolower( trim( $matches[1] ) ) ] = trim( $matches[2] );
					}
				}
			}
		}

		$key = strtolower( $header );
		return isset( $headers[ $key ] ) ? $headers[ $key ] : $fallback;
	}

	private function get_readme_field_value( $field, $fallback ) {
		static $fields = null;

		if ( null === $fields ) {
			$fields = array();
			$readme = plugin_dir_path( WCH_FILE ) . 'readme.txt';
			if ( is_readable( $readme ) ) {
				$contents = file_get_contents( $readme, false, null, 0, 4096 );
				if ( is_string( $contents ) ) {
					foreach ( preg_split( '/\R/', $contents ) as $line ) {
						if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {
							$fields[ strtolower( trim( $matches[1] ) ) ] = trim( $matches[2] );
						}
					}
				}
			}
		}

		$key = strtolower( $field );
		return isset( $fields[ $key ] ) ? $fields[ $key ] : $fallback;
	}

	private function format_plugin_information_text( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '<p>' . $this->esc_html_fallback( 'Release details are available on GitHub.' ) . '</p>';
		}

		$paragraphs = preg_split( "/\R{2,}/", $text );
		$html       = '';
		foreach ( $paragraphs as $paragraph ) {
			$html .= '<p>' . nl2br( $this->esc_html_fallback( trim( $paragraph ) ) ) . '</p>';
		}

		return $html;
	}

	private function esc_html_fallback( $text ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $text );
		}

		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/* --------------------------------------------------------------------- *
	 *  Activatie: databasetabel + endpoint.
	 * --------------------------------------------------------------------- */

}
