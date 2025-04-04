<?php
namespace SlimSEOPro\LinkManager\Api;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\LinkUpdater\Common as LinkUpdaterCommon;
use WP_REST_Server;
use WP_REST_Request;

class Links extends Base {
	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'total_links', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_total_links' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'total_links_by_object', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_total_links_by_object' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'links_by_object', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_links_by_object' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'link_object_name', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_link_object_name' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'links_from_text', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'get_links_from_text' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'total_top_links', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_total_top_links' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'top_links', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_top_links' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'total_links_by_column_value', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_total_links_by_column_value' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'links_by_column_value', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_links_by_column_value' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'find_redirect_url', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'redirect_url' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'unlink', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'unlink' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function get_total_links(): int {
		$tbl_links = new DbLinks();

		return $tbl_links->get_total() ?: -1;
	}

	public function get_total_links_by_object( WP_REST_Request $request ): int {
		$object_id   = $request->get_param( 'object_id' );
		$object_type = $request->get_param( 'object_type' );
		$type        = $request->get_param( 'type' );
		$tbl_links   = new DbLinks();

		return intval( $tbl_links->get_total_links_by_object( $object_id, $object_type, $type ) );
	}

	public function get_links_by_object( WP_REST_Request $request ): array {
		$object_id   = $request->get_param( 'object_id' );
		$object_type = $request->get_param( 'object_type' );
		$type        = $request->get_param( 'type' );
		$limit       = $request->get_param( 'limit' );
		$offset      = $request->get_param( 'offset' );
		$order_by    = $request->get_param( 'orderBy' ) ?? '';
		$order       = $request->get_param( 'order' ) ?? '';
		$tbl_links   = new DbLinks();
		$links       = $tbl_links->get_links_by_object( $object_id, $object_type, $type, $limit, $offset, $order_by, $order );

		if ( empty( $links ) ) {
			return [];
		}

		$links = array_map( function ( $link ) use ( $type ) {
			$link = Helper::get_link_detail( $link, 'source' === $type ? 'target' : 'source' );

			if ( 'source' === $type ) {
				if ( ! empty( $link['target_id'] ) ) {
					$link['edit_link'] = Helper::get_edit_link( $link );
				}
			} else {
				$link['edit_link'] = Helper::get_edit_link( $link, 'source' );
			}

			$link['edit_link']         = ! empty( $link['edit_link'] ) ? $link['edit_link'] : '';
			$link['allow_unlink']      = Helper::allow_unlink( $link );
			$link['allow_update_link'] = LinkUpdaterCommon::allow_update_link_url( $link );

			return $link;
		}, $links );

		return $links;
	}

	public function get_link_object_name( WP_REST_Request $request ): string {
		$object_id   = $request->get_param( 'object_id' );
		$object_type = $request->get_param( 'object_type' );

		if ( false !== stripos( $object_type, 'tax:' ) ) {
			$term = get_term( $object_id );

			return html_entity_decode( $term->name ?? '' );
		} else {
			return html_entity_decode( get_the_title( $object_id ) ?? '' );
		}

		return '';
	}

	public function get_links_from_text( WP_REST_Request $request ): array {
		$post_links = $request->get_param( 'links' );

		if ( empty( $post_links ) ) {
			return [];
		}

		$source_id   = (int) $request->get_param( 'source_id' );
		$source_type = $request->get_param( 'source_type' );
		$location    = $request->get_param( 'location' );
		$links_cache = $request->get_param( 'linksCache' );
		$links       = Helper::get_links_from_text( implode( '', $post_links ), $source_id, $source_type, $location );

		foreach ( $links as $link_index => $link ) {
			$is_link_in_cache = false;

			foreach ( $links_cache as $link_cache ) {
				if ( $link_cache['url'] !== $link['url'] ) {
					continue;
				}

				$is_link_in_cache = true;

				unset( $link_cache['nofollow'], $link_cache['anchor_text'], $link_cache['type'] );

				$links[ $link_index ] = array_merge( $links[ $link_index ], $link_cache );

				break;
			}

			if ( $is_link_in_cache ) {
				continue;
			}

			$link                        = Helper::get_link_detail( $link, 'target' );
			$link['should_check_status'] = $source_id === (int) $link['target_id'] && $source_type === $link['target_type'] ? 0 : 1;

			if ( ! empty( $link['target_id'] ) ) {
				$link['edit_link'] = Helper::get_edit_link( $link );
				$link['edit_link'] = ! empty( $link['edit_link'] ) ? $link['edit_link'] : '';
			}

			$links[ $link_index ] = $link;
		}

		return $links;
	}

	public function get_total_top_links( WP_REST_Request $request ): int {
		$get            = $request->get_param( 'get' );
		$search_keyword = $request->get_param( 'searchKeyword' ) ?? '';
		$tbl_links      = new DbLinks();

		return intval( $tbl_links->get_total_top( $get, $search_keyword ) ?: -1 );
	}

	public function get_top_links( WP_REST_Request $request ): array {
		$get            = $request->get_param( 'get' );
		$limit          = intval( $request->get_param( 'limit' ) );
		$offset         = intval( $request->get_param( 'offset' ) );
		$order_by       = $request->get_param( 'orderBy' );
		$order          = $request->get_param( 'order' );
		$search_keyword = $request->get_param( 'searchKeyword' );
		$tbl_links      = new DbLinks();
		$links          = $tbl_links->get_top( $get, $limit, $offset, $search_keyword, $order_by, $order );

		if ( in_array( $get, [ 'linked_pages', 'orphan_pages' ], true ) && ! empty( $links ) ) {
			$links = array_map( function ( $link ) {
				$link              = Helper::get_link_detail( $link, 'target' );
				$link['view_link'] = Helper::get_view_link( $link );
				$link['edit_link'] = Helper::get_edit_link( $link );

				return $link;
			}, $links );
		}

		return $links;
	}

	public function get_total_links_by_column_value( WP_REST_Request $request ): int {
		$column_name = $request->get_param( 'column_name' );
		$value       = $request->get_param( 'value' );
		$anchor_type = $request->get_param( 'anchor_type' ) ?? '';
		$tbl_links   = new DbLinks();

		return intval( $tbl_links->get_total_links_by_column_value( $column_name, $value, $anchor_type ) );
	}

	public function get_links_by_column_value( WP_REST_Request $request ): array {
		$column_name = $request->get_param( 'column_name' );
		$value       = $request->get_param( 'value' );
		$anchor_type = $request->get_param( 'anchor_type' ) ?? '';
		$limit       = intval( $request->get_param( 'limit' ) );
		$offset      = intval( $request->get_param( 'offset' ) );
		$order_by    = $request->get_param( 'orderBy' );
		$order       = $request->get_param( 'order' );
		$tbl_links   = new DbLinks();
		$links       = $tbl_links->get_links_by_column_value( $column_name, $value, $anchor_type, $limit, $offset, $order_by, $order );

		if ( empty( $links ) ) {
			return [];
		}

		$links = array_map( function ( $link ) {
			$link                      = Helper::get_link_detail( $link, 'all' );
			$link['edit_link']         = Helper::get_edit_link( $link, 'source' );
			$link['allow_unlink']      = Helper::allow_unlink( $link );
			$link['allow_update_link'] = LinkUpdaterCommon::allow_update_link_url( $link );

			return $link;
		}, $links );

		return $links;
	}

	public function redirect_url( WP_REST_Request $request ): string {
		$url = $request->get_param( 'url' );

		return LinkUpdaterCommon::get_redirect_url( $url );
	}

	public function unlink( WP_REST_Request $request ) {
		$link_id   = $request->get_param( 'link_id' );
		$tbl_links = new DbLinks();
		$link      = $tbl_links->get( $link_id );

		if ( empty( $link ) ) {
			return false;
		}

		$success = Helper::unlink( $link );

		return $success;
	}
}
