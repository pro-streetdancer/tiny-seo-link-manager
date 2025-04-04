<?php
namespace SlimSEOPro\LinkManager\LinkUpdater;

use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\LinkStatus\Common as LinkStatusCommon;

class Common {
	public static function allow_update_link_url( array $link ) {
		return apply_filters( 'slim_seo_link_manager_allow_update_link_url', false, $link );
	}

	public static function update_link_url( array $link, string $old_url, string $new_url, bool $is_tool = true ): array {
		if ( ! self::allow_update_link_url( $link ) ) {
			$link['not_allow_update_link_url'] = true;

			return $link;
		}

		$link['url']    = Helper::replace_string( $old_url, $new_url, $link['url'] );
		$link['status'] = LinkStatusCommon::get_status_code( $link );
		$full_url       = Helper::get_full_url( $link['url'] );
		$link['type']   = ! str_starts_with( $full_url, untrailingslashit( home_url() ) ) ? 'external' : 'internal';
		$link           = array_merge( $link, Helper::get_info_from_url( $full_url ) );

		switch ( $link['location'] ) {
			case 'post_content':
				$post_content = get_post_field( 'post_content', $link['source_id'] );

				if ( $is_tool ) {
					$post_content = Helper::replace_string( '"' . $old_url . '"', '"' . $new_url . '"', $post_content );
					$post_content = Helper::replace_string( "'" . $old_url . "'", '"' . $new_url . '"', $post_content );
				} else {
					$post_content = Helper::replace_string( $old_url, $new_url, $post_content );
				}

				wp_update_post( [
					'ID'           => $link['source_id'],
					'post_content' => $post_content,
				] );

				break;

			case 'term_description':
				$term = get_term( $link['source_id'] );

				if ( ! empty( $term->description ) ) {
					$term_description = $term->description;

					if ( $is_tool ) {
						$term_description = Helper::replace_string( '"' . $old_url . '"', '"' . $new_url . '"', $term_description );
						$term_description = Helper::replace_string( "'" . $old_url . "'", '"' . $new_url . '"', $term_description );
					} else {
						$term_description = Helper::replace_string( $old_url, $new_url, $term_description );
					}

					wp_update_term( $link['source_id'], $term->taxonomy, [
						'description' => $term_description,
					] );
				}

				break;

			default:
				do_action( 'slim_seo_link_manager_update_link_url', $link, $old_url, $new_url );

				break;
		}

		$tbl_links = new DbLinks();
		$tbl_links->update( $link );

		return $link;
	}

	public static function get_redirect_url( string $url ): string {
		// phpcs:disable
		$curl_handle = curl_init();

		// Set common user agent.
		// @link https://www.useragents.me/#most-common-desktop-useragents
		curl_setopt( $curl_handle, CURLOPT_USERAGENT, $_SERVER[ 'HTTP_USER_AGENT' ] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36' );
		curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 60 );
		curl_setopt( $curl_handle, CURLOPT_URL, $url );
		curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( $curl_handle, CURLOPT_MAXREDIRS, 10 );		
		
		curl_exec( $curl_handle );

		$final_url = curl_getinfo( $curl_handle, CURLINFO_EFFECTIVE_URL );

		curl_close( $curl_handle );
		// phpcs:enable

		return $final_url;
	}

	public static function update_links( array $links, string $new_permalink ) {
		$home_url         = untrailingslashit( home_url() );
		$link_updater     = new Updater();
		$run_link_updater = false;

		foreach ( $links as $link ) {
			if ( ( $link['target_id'] ?? '' ) === ( $link['source_id'] ?? '' ) ) {
				continue;
			}

			$link_url      = explode( '?', $link['url'] );
			$old_permalink = untrailingslashit( $link_url[0] );

			if ( $old_permalink === $home_url || $old_permalink === $new_permalink ) {
				continue;
			}

			$link['old_permalink'] = $old_permalink;
			$link['new_permalink'] = $new_permalink;

			$link_updater->push_to_queue( $link );

			$run_link_updater = true;
		}

		if ( $run_link_updater ) {
			$link_updater->save()->dispatch();
		}
	}
}
