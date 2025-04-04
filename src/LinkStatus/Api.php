<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

use SlimSEOPro\LinkManager\Api\Base;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\LinkStatus\BackgroundChecking as LinkStatusChecking;
use WP_REST_Server;
use WP_REST_Request;

class Api extends Base {
	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'link-status/check', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'check' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function check( WP_REST_Request $request ): array {
		$links    = $request->get_param( 'links' );
		$statuses = [];

		if ( empty( $links ) ) {
			return $statuses;
		}

		$links_checking_bg = [];

		foreach ( $links as $link ) {
			$status        = 0;
			$link_in_cache = false;

			// If it's internal link and it has target_id, check now
			if ( 'internal' === ( $link['type'] ?? '' ) && ! empty( $link['target_id'] ) ) {
				$status = Common::get_status_code( $link );
			} else {
				// Check Cache
				$link_cache = Cache::get( $link['url'] );

				if ( ! empty( $link_cache ) && SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE !== $link_cache['status'] ) {
					$status        = $link_cache['status'];
					$link_in_cache = true;
				}
			}

			$should_check_status = intval( $link['should_check_status'] ?? 0 );

			// Unset unused fields
			unset( $link['should_check_status'] );
			unset( $link['target_name'] );
			unset( $link['edit_link'] );
			unset( $link['allow_unlink'] );
			unset( $link['allow_update_link'] );

			// phpcs:disable
			// If status = 0 > link is not internal or in Cache
			if ( ! $status ) {
				// Add link to $links_checking_bg for background checking
				if ( 1 === $should_check_status ) {
					$links_checking_bg[] = $link;
				}
			} else {
				if ( ! empty( $link['id'] ) && ! $link_in_cache ) {
					$link['status'] = $status;

					$tbl_links = new DbLinks;
					$tbl_links->update( $link );
				}
			}
			// phpcs:enable

			$statuses[ $link['url'] ] = $status;
		}

		if ( ! empty( $links_checking_bg ) ) {
			$check_link_status = new LinkStatusChecking();

			foreach ( $links_checking_bg as $link ) {
				$check_link_status->push_to_queue( $link );
			}

			$check_link_status->save()->dispatch();
		}

		return $statuses;
	}
}
