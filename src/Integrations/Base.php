<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\LinkStatus\BackgroundChecking as LinkStatusChecking;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\Helper;

abstract class Base {
	protected $location;

	public function __construct() {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

	abstract protected function is_active(): bool;

	public function setup() {
		if ( ! $this->is_active() ) {
			return;
		}

		// Parse links from the page builder's content and save into the database.
		add_action( 'save_post', [ $this, 'parse_links_for_post' ], 30 );

		// Filter content to suggest links & keywords.
		add_filter( 'slim_seo_link_manager_content', [ $this, 'filter_content_for_suggestions' ], 10, 2 );

		// For post scanner to get links.
		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'get_all_links_from_post' ], 20, 2 );

		// Link updater.
		add_filter( 'slim_seo_link_manager_allow_update_link_url', [ $this, 'allow' ], 20, 2 );
		add_action( 'slim_seo_link_manager_update_link_url', [ $this, 'update_link_url' ], 20, 3 );

		add_filter( 'slim_seo_link_manager_post_types', [ $this, 'remove_post_types' ] );

		// Unlink
		add_filter( 'slim_seo_link_manager_allow_unlink', [ $this, 'allow' ], 20, 2 );
		add_action( 'slim_seo_link_manager_unlink', [ $this, 'unlink' ], 20 );
	}

	public function parse_links_for_post( int $post_id ) {
		$source_id   = wp_is_post_revision( $post_id ) ?: $post_id;
		$source_type = get_post_type( $source_id );
		$post_types  = Helper::get_post_types();

		// phpcs:ignore
		if ( ! in_array( $source_type, $post_types ) ) {
			return;
		}

		$links = $this->get_links( $source_id );

		if ( empty( $links ) ) {
			return;
		}

		$tbl_links = new DbLinks();
		$tbl_links->delete_all( $source_id, $source_type );
		$tbl_links->add( $links );

		// Check link status in background
		$links = $tbl_links->get_links_by_object( $source_id, $source_type );

		$check_link_status = new LinkStatusChecking();

		foreach ( $links as $link ) {
			$check_link_status->push_to_queue( $link );
		}

		$check_link_status->save()->dispatch();
	}

	protected function get_links( int $source_id ): array {
		$links   = [];
		$content = $this->get_content( $source_id );

		if ( ! $content ) {
			return $links;
		}

		$source_type = get_post_type( $source_id );
		$links       = Helper::get_links_from_text( $content, $source_id, $source_type, $this->location );

		if ( ! empty( $links ) ) {
			$links = array_map( function ( $link ) {
				$link = array_merge( $link, Helper::get_info_from_url( $link['url'] ) );

				return $link;
			}, $links );
		}

		return $links;
	}

	/**
	 * Get content from page builders.
	 */
	abstract protected function get_content( int $post_id ): string;

	public function filter_content_for_suggestions( string $content, int $post_id ): string {
		return $this->get_content( $post_id ) ?: $content;
	}

	public function get_all_links_from_post( array $links, int $post_id ): array {
		$builder_links = $this->get_links( $post_id );

		if ( empty( $builder_links ) ) {
			return $links;
		}

		$links = array_filter( $links, function ( $link ) {
			return $link['location'] !== 'post_content';
		} );

		$links = array_merge( $links, $builder_links );

		return $links;
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {}

	public function remove_post_types( array $post_types ): array {
		return $post_types;
	}

	public function allow( bool $allow, array $link ): bool {
		if ( $this->location !== $link['location'] ) {
			return $allow;
		}

		return true;
	}

	public function unlink( array $link ) {}
}
