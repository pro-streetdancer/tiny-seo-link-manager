<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Api\Base;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\LinkUpdater\Updater;
use WP_REST_Server;
use WP_REST_Request;

class PublicApi extends Base {
	public function register_routes() {
		if ( ! Common::is_enable_interlink_external_sites() ) {
			return;
		}

		register_rest_route( self::NAMESPACE, 'public-suggestions/add_site', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'add_site' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, 'public-suggestions/delete_site', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'delete_site' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, 'public-suggestions/add_data', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'add_data' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, 'public-suggestions/delete_data', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'delete_data' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, 'public-suggestions/update_data', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update_data' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, 'public-suggestions/request_data', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'request_data' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function add_site( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );

		if ( ! Common::validate_incomming_access( [ 'secret_key' => $secret_key ] ) ) {
			return [
				'status'  => 0,
				'message' => __( 'Secret key is not matched!', 'slim-seo-link-manager' ),
			];
		}

		$linked_sites = new LinkedSites();
		$linked_sites->add( [
			'site' => $site,
			'type' => 'external',
		] );

		return [
			'status' => 1,
		];
	}

	public function delete_site( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );

		if ( ! Common::validate_incomming_access( [ 'secret_key' => $secret_key ] ) ) {
			return [
				'status'  => 0,
				'message' => __( 'Secret key is not matched!', 'slim-seo-link-manager' ),
			];
		}

		$linked_sites = new LinkedSites();
		$linked_sites->delete( $site );

		$data = new Data();
		$data->delete( $site );

		return [
			'status' => 1,
		];
	}

	public function add_data( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );
		$list       = (array) $request->get_param( 'list' );

		if (
			! Common::validate_incomming_access( [
				'secret_key' => $secret_key,
				'site'       => $site,
			] )
		) {
			return;
		}

		$data = new Data();

		foreach ( $list as $item ) {
			$data->add( $item );
		}
	}

	public function delete_data( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );
		$delete_all = $request->get_param( 'delete_all' );
		$item       = $request->get_param( 'item' );

		if (
			! Common::validate_incomming_access( [
				'secret_key' => $secret_key,
				'site'       => $site,
			] )
		) {
			return;
		}

		$data = new Data();

		if ( ! empty( $delete_all ) ) {
			$data->delete( $site );

			return;
		}

		$data->delete_object( $item['object_id'], $item['object_type'], $item['home_url'] );
	}

	public function update_data( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );
		$item       = $request->get_param( 'item' );

		if (
			! Common::validate_incomming_access( [
				'secret_key' => $secret_key,
				'site'       => $site,
			] )
		) {
			return;
		}

		$data   = new Data();
		$result = $data->get( $item['object_id'], $item['object_type'], $item['site_url'] );

		if ( empty( $result ) ) {
			$data->add( $item );

			return;
		}

		$old_url = $result['url'];
		$new_url = $item['url'];

		$result['title'] = $item['title'];
		$result['url']   = $item['url'];
		$result['words'] = $item['words'];

		$data->update( $result );

		if ( $old_url !== $new_url ) {
			$tbl_links = new DbLinks();
			$links     = $tbl_links->search_links_by_url( $old_url );

			if ( ! empty( $links ) ) {
				$link_updater = new Updater;

				foreach ( $links as $link ) {
					$link['old_permalink'] = $old_url;
					$link['new_permalink'] = $new_url;

					$link_updater->push_to_queue( $link );
				}

				$link_updater->save()->dispatch();
			}
		}
	}

	public function request_data( WP_REST_Request $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$site       = $request->get_param( 'site' );

		if (
			! Common::validate_incomming_access( [
				'secret_key' => $secret_key,
				'site'       => $site,
			] )
		) {
			return;
		}

		$home_url = trailingslashit( home_url() );

		Common::add_data_to_external_site( [
			'site'       => $site,
			'secret_key' => $secret_key,
			'home_url'   => $home_url,
		] );
	}
}
