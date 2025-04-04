<?php
namespace SlimSEOPro\LinkManager\LinkUpdater;

use SlimSEOPro\LinkManager\Api\Base;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\Helper;
use WP_REST_Server;
use WP_REST_Request;

class Api extends Base {
	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'link-updater/search', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'search' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'link-updater/update', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'link-updater/change', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'change' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function search( WP_REST_Request $request ): int {
		$url       = $request->get_param( 'url' );
		$tbl_links = new DbLinks();
		$links     = $tbl_links->search_links_by_url( $url );

		return count( $links );
	}

	public function update( WP_REST_Request $request ): array {
		$old_url     = $request->get_param( 'old_url' );
		$new_url     = $request->get_param( 'new_url' );
		$dry_run     = $request->get_param( 'dry_run' );
		$updated_ids = $request->get_param( 'updated_ids' );
		$tbl_links   = new DbLinks();
		$links       = $tbl_links->search_links_by_url( $old_url, 1, array_map( 'intval', ! empty( $updated_ids ) ? $updated_ids : [] ) );

		if ( ! empty( $links ) ) {
			$link        = $links[0];
			$link_url    = $link['url'];
			$status_text = '';

			if ( empty( $dry_run ) ) {
				$status_text = __( 'Updated link ', 'slim-seo-link-manager' );
				$result      = Common::update_link_url( $link, $old_url, $new_url );

				if ( ! empty( $result['not_allow_update_link_url'] ) ) {
					$status_text = __( 'Unable to updated link ', 'slim-seo-link-manager' );
				}
			}

			return [
				'type'    => 'continue',
				'id'      => $link['id'],
				'message' => $status_text . sprintf(
					// Translators: %1$s - source link, %2$s - destination link
					__( '%1$s in %2$s', 'slim-seo-link-manager' ),
					$link_url,
					'<a href="' . Helper::get_edit_link( $link, 'source' ) . '" target="_blank">' . Helper::get_link_title( $link, 'source' ) . '</a>'
				),
			];
		} else {
			return [
				'message' => __( 'Done!', 'slim-seo-link-manager' ),
				'type'    => 'done',
			];
		}
	}

	public function change( WP_REST_Request $request ) {
		$link_id   = $request->get_param( 'link_id' );
		$new_url   = $request->get_param( 'new_url' );
		$tbl_links = new DbLinks();
		$link      = $tbl_links->get( $link_id );

		if ( empty( $link ) ) {
			return false;
		}

		if ( empty( $new_url ) ) {
			$new_url = Common::get_redirect_url( $link['url'] );
		}

		if ( empty( $new_url ) ) {
			return false;
		}

		if ( $new_url === $link['url'] ) {
			return true;
		}

		$result = Common::update_link_url( $link, $link['url'], $new_url, false );

		if ( ! empty( $result['not_allow_update_link_url'] ) ) {
			return false;
		}

		return true;
	}
}
