<?php
namespace SlimSEOPro\LinkManager;

class Misc {
	public function __construct() {
		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'text' ], 40, 2 );
		add_filter( 'slim_seo_link_manager_outbound_links', [ $this, 'text' ], 40, 2 );
	}

	public function text( array $links, int $post_id ): array {
		$location = 'custom_text';

		// Remove all links that are from $location
		$links = array_filter( $links, function ( $link ) use ( $location ) {
			return $link['location'] !== $location;
		} );

		$text = apply_filters( 'slim_seo_link_manager_text', '', $post_id );

		if ( empty( $text ) ) {
			return $links;
		}

		$links_from_text = Helper::get_links_from_text( $text, $post_id, get_post_type( $post_id ), $location );

		if ( empty( $links_from_text ) ) {
			return $links;
		}

		$links = array_merge( $links, $links_from_text );

		return $links;
	}
}
