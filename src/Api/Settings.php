<?php
namespace SlimSEOPro\LinkManager\Api;

use SlimSEOPro\LinkManager\LinkSuggestions\Common as LinkSuggestionsCommon;
use SlimSEOPro\LinkManager\LinkSuggestions\Data as LinkSuggestionsData;
use SlimSEOPro\LinkManager\LinkSuggestions\GenerateData as LinkSuggestionsGenerateData;
use SlimSEOPro\LinkManager\LinkSuggestions\Controller as LinkSuggestionsController;
use WP_REST_Server;
use WP_REST_Request;

class Settings extends Base {
	const OPTION_NAME = 'slim_seo_link_manager_settings';

	private $controller;

	public function __construct( LinkSuggestionsController $controller ) {
		parent::__construct();

		$this->controller = $controller;
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'settings', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'update' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function update( WP_REST_Request $request ): bool {
		$data = $request->get_body_params();

		// Ensure data contains only defined keys.
		$keys = [
			'ignore_link_prefixes',
			'ignore_link_keywords',
			'ignore_post_types',
			'delete_data',
			'enable_interlink_external_sites',
			'interlink_external_sites_secret_key',
		];
		$data = array_intersect_key( $data, array_flip( $keys ) );

		// Sanitize.
		$data['ignore_link_prefixes'] = sanitize_textarea_field( $data['ignore_link_prefixes'] ?? '' );
		$data['ignore_link_keywords'] = sanitize_textarea_field( $data['ignore_link_keywords'] ?? '' );

		$data['ignore_post_types'] = (array) $data['ignore_post_types'] ?? [];
		$data['ignore_post_types'] = array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $data['ignore_post_types'] ) ) ) );

		$data['delete_data'] = ! empty( $data['delete_data'] );

		$data['enable_interlink_external_sites']     = ! empty( $data['enable_interlink_external_sites'] );
		$data['interlink_external_sites_secret_key'] = sanitize_textarea_field( $data['interlink_external_sites_secret_key'] ?? '' );

		$data = array_filter( $data );

		$enable_interlink_external_sites    = ! empty( $data['enable_interlink_external_sites'] );
		$is_enable_interlink_external_sites = LinkSuggestionsCommon::is_enable_interlink_external_sites();
		$delete_linked_sites                = false;

		if ( $enable_interlink_external_sites && ! $is_enable_interlink_external_sites ) {
			$link_suggestions_data = new LinkSuggestionsData();
			$link_suggestions_data->create_table();

			$link_suggestions_generate_data = new LinkSuggestionsGenerateData( $this->controller );
			$link_suggestions_generate_data->push_to_queue( [] );
			$link_suggestions_generate_data->save()->dispatch();
		} elseif ( ! $enable_interlink_external_sites && $is_enable_interlink_external_sites ) {
			$link_suggestions_data = new LinkSuggestionsData();

			if ( is_multisite() ) {
				$home_url = trailingslashit( home_url() );

				$link_suggestions_data->delete( $home_url );
			} else {
				$link_suggestions_data->drop_table();
			}

			$delete_linked_sites = true;
		}

		if ( ( $data['interlink_external_sites_secret_key'] ?? '' ) !== LinkSuggestionsCommon::get_secret_key() ) {
			$delete_linked_sites = true;
		}

		if ( $delete_linked_sites ) {
			LinkSuggestionsCommon::delete_linked_sites();
		}

		if ( empty( $data ) ) {
			return delete_option( self::OPTION_NAME );
		}

		return update_option( self::OPTION_NAME, $data );
	}
}
