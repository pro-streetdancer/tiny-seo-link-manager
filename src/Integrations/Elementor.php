<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helper;

class Elementor extends Base {
	protected $location      = 'elementor';
	protected $meta_key_name = '_elementor_data';

	protected function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' );
	}

	protected function get_content( int $post_id ): string {
		$frontend = new \Elementor\Frontend;

		return $frontend->get_builder_content( $post_id ) ?: '';
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		global $wpdb;

		$sql = sprintf(
			'UPDATE %s
			SET `meta_value` = REPLACE(`meta_value`, "%s", "%s")
			WHERE `post_id` = %s AND `meta_key` = "%s"',
			$wpdb->postmeta,
			str_replace( '/', '\\\/', $old_url ),
			str_replace( '/', '\\\/', $new_url ),
			$link['source_id'],
			$this->meta_key_name
		);

		$wpdb->query( $sql ); // phpcs:ignore
	}

	public function remove_post_types( array $post_types ): array {
		unset( $post_types['elementor_library'] );
		unset( $post_types['e-landing-page'] );

		return $post_types;
	}

	protected function search_and_unlink( &$elements, $url ) {
		foreach ( $elements as $key => &$value ) {
			if ( is_array( $value ) ) {
				if ( ! empty( $value['link']['url'] ) && $value['link']['url'] === $url ) {
					unset( $value['link'] );
					unset( $value['link_to'] );
				}

				$this->search_and_unlink( $value, $url );
			} elseif ( is_string( $value ) ) {
				$value = Helper::remove_hyperlink( $value, $url );
			}
		}
	}

	public function unlink( array $link ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$elements = get_post_meta( $link['source_id'], $this->meta_key_name, true );

		if ( ! is_string( $elements ) || empty( $elements ) ) {
			return;
		}

		$elements = json_decode( $elements, true );

		$this->search_and_unlink( $elements, $link['url'] );

		update_post_meta( $link['source_id'], $this->meta_key_name, wp_slash( wp_json_encode( $elements ) ) );
	}
}
