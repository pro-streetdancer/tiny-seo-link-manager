<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

class Common {
	public static function http_code_by_curl( string $url ) {
		// phpcs:disable
		$http_code   = SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE;
		$curl_handle = curl_init( $url );

		curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );

		// Set common user agent.
		// @link https://www.useragents.me/#most-common-desktop-useragents
		curl_setopt( $curl_handle, CURLOPT_USERAGENT, $_SERVER[ 'HTTP_USER_AGENT' ] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36' );
		curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 10 );

		$curl_exec = curl_exec( $curl_handle );

		if ( ! empty( $curl_exec ) ) {
			$http_code = curl_getinfo( $curl_handle, CURLINFO_HTTP_CODE );
		}

		curl_close( $curl_handle );
		// phpcs:enable

		return $http_code;
	}

	public static function http_code_by_header( string $url ) {
		// phpcs:disable
		ini_set( 'default_socket_timeout', 10 );

		$http_code = SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE;
		$headers   = @get_headers( $url );

		if ( $headers ) {
			$response_code = substr( $headers[ 0 ], 9, 3 );

			if ( is_numeric( $response_code ) ) {
				$http_code = $response_code;
			}
		}
		// phpcs:enable

		return $http_code;
	}

	public static function get_status_code( array $link ) {
		if ( empty( $link['type'] ) ) {
			return SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE;
		}

		return 'external' === $link['type'] ? ExternalLink::get_status_code( $link ) : InternalLink::get_status_code( $link );
	}
}
