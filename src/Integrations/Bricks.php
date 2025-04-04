<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helper;

class Bricks extends Base {
	protected $location = 'bricks';

	public function is_active(): bool {
		return defined( 'BRICKS_VERSION' );
	}

	protected function get_page_content( int $post_id ) {
		// Set the post_id in 'Database' to avoid errors
		\Bricks\Database::$page_data['preview_or_post_id'] = $post_id;

		if ( ! \Bricks\Helpers::render_with_bricks( $post_id ) ) {
			return '';
		}

		$bricks_data = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );

		return $bricks_data;
	}

	protected function get_content( int $post_id ): string {
		$bricks_data = $this->get_page_content( $post_id );

		return $bricks_data ? (string) \Bricks\Frontend::render_data( $bricks_data ) : '';
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$bricks_data = $this->get_page_content( $link['source_id'] );

		if ( empty( $bricks_data ) ) {
			return;
		}

		array_walk_recursive( $bricks_data, function ( &$value ) use ( $old_url, $new_url ) {
			$value = Helper::replace_string( $old_url, $new_url, $value );
		} );

		update_post_meta( $link['source_id'], BRICKS_DB_PAGE_CONTENT, $bricks_data );
	}

	public function unlink( array $link ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$bricks_data = $this->get_page_content( $link['source_id'] );

		if ( empty( $bricks_data ) ) {
			return;
		}

		$url = $link['url'];

		array_walk_recursive( $bricks_data, function ( &$value ) use ( $url ) {
			$value = Helper::remove_hyperlink( $value, $url );
		} );

		update_post_meta( $link['source_id'], BRICKS_DB_PAGE_CONTENT, $bricks_data );
	}

	public function remove_post_types( array $post_types ): array {
		unset( $post_types['bricks_template'] );

		return $post_types;
	}
}
