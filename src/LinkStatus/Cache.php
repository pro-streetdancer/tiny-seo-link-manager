<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;

class Cache {
	public static function get( string $url ): array {
		// Check link in WP option first
		$links_cache = get_option( SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME );
		$links_cache = ! empty( $links_cache ) ? $links_cache : [];
		$now         = strtotime( 'now' );

		if ( isset( $links_cache[ $url ] ) && $links_cache[ $url ]['updated_at'] + DAY_IN_SECONDS > $now ) {
			return $links_cache[ $url ];
		}

		// Check link in slim_seo_links table
		$tbl_links = new DbLinks();
		$links     = $tbl_links->search_links_by_url( $url );

		if ( empty( $links ) ) {
			return [];
		}

		foreach ( $links as $link ) {
			if ( ! empty( $link['updated_at'] ) && strtotime( $link['updated_at'] ) + DAY_IN_SECONDS > $now ) {
				return $link;
			}
		}

		return [];
	}

	public static function update( string $url, $http_code ) {
		$links_cache = self::cleanup( [], false );
		$now         = strtotime( 'now' );

		$links_cache[ $url ] = [
			'updated_at' => $now,
			'status'     => $http_code,
		];

		update_option( SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME, $links_cache, false );
	}

	private static function cleanup( array $links_cache = [], bool $should_update = true ): array {
		$now = strtotime( 'now' );

		if ( empty( $links_cache ) ) {
			$links_cache = get_option( SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME );
		}

		if ( empty( $links_cache ) ) {
			return [];
		}

		foreach ( $links_cache as $url => $data ) {
			if ( $data['updated_at'] + DAY_IN_SECONDS < $now ) {
				unset( $links_cache[ $url ] );
			}
		}

		if ( $should_update ) {
			update_option( SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME, $links_cache );
		}

		return $links_cache;
	}
}
