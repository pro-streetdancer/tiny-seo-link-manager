<?php
namespace SlimSEOPro\LinkManager\Helpers;

class Data {
	public static function get_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $post_types['attachment'] );

		$post_types = apply_filters( 'slim_seo_link_manager_post_types', $post_types );

		return $post_types;
	}

	public static function get_content( int $post_id ): string {
		$content = get_post_field( 'post_content', $post_id );
		$content = do_shortcode( $content );
		$content = do_blocks( $content );

		return $content;
	}
}
