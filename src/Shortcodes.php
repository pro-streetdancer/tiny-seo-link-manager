<?php
namespace SlimSEOPro\LinkManager;

class Shortcodes {
	public function __construct() {
		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'get_all_links_from_post' ], 30, 2 );
		add_filter( 'slim_seo_link_manager_outbound_links', [ $this, 'outbound_links' ], 30, 2 );
	}

	public function get_all_links_from_post( array $links, int $post_id ): array {
		$shortcodes_links = $this->get_links( $post_id );

		if ( empty( $shortcodes_links ) ) {
			return $links;
		}

		$links = array_merge( $links, $shortcodes_links );

		return $links;
	}

	public function outbound_links( $links, int $post_id ) {
		$shortcodes_links = $this->get_links( $post_id );

		if ( empty( $shortcodes_links ) ) {
			return $links;
		}

		// Remove all links that are from shortcodes
		$links = array_filter( $links, function ( $link ) {
			return false === stripos( $link['location'], 'shortcode:' );
		} );

		$links = array_merge( $links, $shortcodes_links );

		return $links;
	}

	public function get_links( int $post_id ): array {
		$shortcode_regex = get_shortcode_regex();
		$links           = [];

		if ( empty( $shortcode_regex ) ) {
			return $links;
		}

		$post_type    = get_post_type( $post_id );
		$post_content = get_post_field( 'post_content', $post_id );

		if ( preg_match_all( '/' . $shortcode_regex . '/s', $post_content, $matches ) ) {
			$shortcodes       = $matches[0];
			$shortcodes_names = $matches[2];

			if ( empty( $shortcodes ) || ! is_array( $shortcodes ) ) {
				return $links;
			}

			foreach ( $shortcodes as $shortcode_index => $shortcode ) {
				$shortcode_name = $shortcodes_names[ $shortcode_index ] ?? '';

				if ( empty( $shortcode_name ) ) {
					continue;
				}

				$output = do_shortcode( $shortcode );

				if ( empty( $output ) ) {
					continue;
				}

				$links_from_text = Helper::get_links_from_text( $output, $post_id, $post_type, "shortcode: {$shortcode_name}" );

				if ( empty( $links_from_text ) ) {
					continue;
				}

				$links = array_merge( $links, $links_from_text );
			}
		}

		return $links;
	}
}
