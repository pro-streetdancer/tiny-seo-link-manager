<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

use SlimSEOPro\LinkManager\Helper;

class ExternalLink {
	public static function get_status_code( array $link ) {
		$url = Helper::get_full_url( $link['url'], $link );

		// Check if URL is in Cache
		$link_cache = Cache::get( $url );

		if ( ! empty( $link_cache ) && SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE !== $link_cache['status'] ) {
			return $link_cache['status'];
		}

		// Check http code by CURL
		$http_code = Common::http_code_by_curl( $url );

		// If http code cannot be checked by CURL or status = 403, then double check it by HTTP Header
		if ( SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE === $http_code || 403 === $http_code ) {
			$http_code = Common::http_code_by_header( $url );

			// If http code still cannot be checked > Error
			if ( SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE === $http_code ) {
				$http_code = SLIM_SEO_LINK_MANAGER_ERROR_STATUS_CODE;
			}
		}

		Cache::update( $url, $http_code );

		return $http_code;
	}
}
