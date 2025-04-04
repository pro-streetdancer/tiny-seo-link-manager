<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helpers\Data;
use SlimSEOPro\LinkManager\Helper;

class Divi extends Base {
	protected $location = 'divi';

	public function is_active(): bool {
		return defined( 'ET_BUILDER_THEME' );
	}

	protected function get_content( int $post_id ): string {
		if ( ! get_post_meta( $post_id, '_et_builder_version', true ) ) {
			return '';
		}

		$content = get_post_field( 'post_content', $post_id );

		return $this->render_shortcodes( $content );
	}

	private function render_shortcodes( string $content ): string {
		// Render all normal shortcodes.
		$content = do_shortcode( $content );

		// Remove Divi structural shortcodes.
		// `do_shortcode()` doesn't render them, so we have to remove them manually.
		$shortcodes = [
			'et_pb_section',
			'et_pb_row',
			'et_pb_row_inner',
			'et_pb_column',
		];
		foreach ( $shortcodes as $shortcode ) {
			$content = preg_replace( "/\[\/?{$shortcode}[^\]]*?\]/is", '', $content );
		}

		// Remove other left shortcodes, usually buggy ones.
		$content = preg_replace( '/\[\/?[a-z0-9_-]+?[^\]]*?\]/is', '', $content );

		return $content;
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$post_content = Data::get_content( $link['source_id'] );
		$post_content = Helper::replace_string( $old_url, $new_url, $post_content );

		wp_update_post( [
			'ID'           => $link['source_id'],
			'post_content' => $post_content,
		] );
	}

	public function remove_post_types( array $post_types ): array {
		unset( $post_types['et_pb_layout'] );

		return $post_types;
	}

	public function unlink( array $link ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$post_content = Data::get_content( $link['source_id'] );
		$post_content = Helper::remove_hyperlink( $post_content, $link['url'] );

		wp_update_post( [
			'ID'           => $link['source_id'],
			'post_content' => $post_content,
		] );
	}
}
