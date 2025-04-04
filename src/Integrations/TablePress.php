<?php
namespace SlimSEOPro\LinkManager\Integrations;

class TablePress {
	protected $location = 'shortcode: table';

	public function __construct() {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

	public function setup() {
		if ( ! function_exists( 'tb_tp_fs' ) ) {
			return;
		}

		add_filter( 'slim_seo_link_manager_post_types', [ $this, 'remove_post_types' ], 90 );
		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'remove_edit_links' ], 90 );
		add_filter( 'slim_seo_link_manager_outbound_links', [ $this, 'remove_edit_links' ], 90 );
	}

	public function remove_post_types( array $post_types ): array {
		unset( $post_types['tablepress_table'] );
		return $post_types;
	}

	public function remove_edit_links( $links ) {
		if ( empty( $links ) ) {
			return $links;
		}

		$links = array_filter( $links, function ( $link ) {
			return $this->location !== $link['location'] || false === stripos( $link['url'], 'page=tablepress' );
		} );

		return $links;
	}
}
