<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Api\Base;
use SlimSEOPro\LinkManager\Helper;
use WP_REST_Server;
use WP_REST_Request;

class Api extends Base {
	private $suggestion;

	public function set_controller( Controller $controller ) {
		$this->suggestion = new Suggestion( $controller );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'suggestions/links', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'links' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/keywords', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'keywords' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/search', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'search' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/generate_secret_key', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'generate_secret_key' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/external_links', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'external_links' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/add_linked_site', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'add_linked_site' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'suggestions/delete_linked_site', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'delete_linked_site' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function links( WP_REST_Request $request ): array {
		$object_id        = (int) $request->get_param( 'object_id' );
		$same_taxonomies  = (bool) $request->get_param( 'same_taxonomies' );
		$link_suggestions = $this->suggestion->suggest_links( $object_id, $same_taxonomies );

		return $link_suggestions;
	}

	public function keywords( WP_REST_Request $request ): array {
		$object_id = (int) $request->get_param( 'object_id' );

		return $this->suggestion->suggest_keywords( $object_id );
	}

	public function search( WP_REST_Request $request ): array {
		$object_id       = (int) $request->get_param( 'object_id' );
		$same_taxonomies = (bool) $request->get_param( 'same_taxonomies' );

		$args      = Common::build_query_args( $object_id, $same_taxonomies );
		$args['s'] = $request->get_param( 'keyword' );
		$args      = apply_filters( 'slim_seo_link_manager_search_args', $args );

		$posts = Helper::get_posts( $args );
		$pages = [];
		if ( empty( $posts ) ) {
			return $pages;
		}

		foreach ( $posts as $post ) {
			$pages[] = [
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'url'           => get_permalink( $post->ID ),
				'editURL'       => htmlspecialchars_decode( get_edit_post_link( $post->ID ) ),
				'datePublished' => gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) ),
			];
		}

		return $pages;
	}

	public function generate_secret_key( WP_REST_Request $request ): string {
		return Common::generate_secret_key();
	}

	public function external_links( WP_REST_Request $request ): array {
		$object_id = (int) $request->get_param( 'object_id' );

		return $this->suggestion->suggest_external_links( $object_id );
	}

	public function add_linked_site( WP_REST_Request $request ): array {
		$site     = trailingslashit( $request->get_param( 'site' ) );
		$home_url = trailingslashit( home_url() );

		if ( $site === $home_url ) {
			return [
				'message' => __( 'Cannot add current site!', 'slim-seo-link-manager' ),
			];
		}

		$linked_sites = new LinkedSites();

		if ( $linked_sites->check_site_exists( $site ) ) {
			return [
				'message' => sprintf(
					'%s %s',
					$site,
					__( 'is already added before!', 'slim-seo-link-manager' )
				),
			];
		}

		$site_data = array_merge( [
			'site' => $site,
		], LinkedSites::check_site_info( $site ) );

		$secret_key = Common::get_secret_key();

		if ( 'internal' === $site_data['type'] ) {
			if ( $secret_key !== Common::get_secret_key( $site_data['blog_id'] ) ) {
				return [
					'message' => sprintf(
						'%s %s (%s)',
						$site,
						__( 'is not using same secret key', 'slim-seo-link-manager' ),
						$secret_key
					),
				];
			}

			$linked_sites->add( $site_data );

			$target_linked_sites = new LinkedSites( $site_data['blog_id'] );
			$target_linked_sites->add( [
				'site'    => $home_url,
				'type'    => 'internal',
				'blog_id' => get_current_blog_id(),
			] );

			return [
				'site' => $site_data,
			];
		}

		$response = Common::remote_post( [
			'secret_key'      => $secret_key,
			'site'            => $site,
			'action'          => 'add_site',
			'home_url'        => $home_url,
			'failure_message' => sprintf(
				'%s %s',
				__( 'Cannot add', 'slim-seo-link-manager' ),
				$site
			),
		] );

		if ( ! empty( $response['message'] ) ) {
			return [
				'message' => $response['message'],
			];
		}

		$linked_sites->add( $site_data );

		Common::add_data_to_external_site( [
			'site'       => $site,
			'secret_key' => $secret_key,
			'home_url'   => $home_url,
		] );

		Common::remote_post( [
			'secret_key' => $secret_key,
			'site'       => $site,
			'home_url'   => $home_url,
			'action'     => 'request_data',
		] );

		return [
			'site' => $site_data,
		];
	}

	public function delete_linked_site( WP_REST_Request $request ): array {
		$site         = trailingslashit( $request->get_param( 'site' ) );
		$linked_sites = new LinkedSites();
		$site_data    = $linked_sites->get( $site );
		$home_url     = trailingslashit( home_url() );

		if ( 'internal' === $site_data['type'] ) {
			$linked_sites->delete( $site );

			$target_linked_sites = new LinkedSites( $site_data['blog_id'] );
			$target_linked_sites->delete( $home_url );

			return [];
		}

		$secret_key = Common::get_secret_key();
		$response   = Common::remote_post( [
			'secret_key'      => $secret_key,
			'site'            => $site,
			'action'          => 'delete_site',
			'home_url'        => $home_url,
			'failure_message' => sprintf(
				'%s %s',
				__( 'Cannot delete', 'slim-seo-link-manager' ),
				$site
			),
		] );

		if ( ! empty( $response['message'] ) ) {
			return [
				'message' => $response['message'],
			];
		}

		$linked_sites->delete( $site );

		$data = new Data();
		$data->delete( $site );

		return [];
	}
}
