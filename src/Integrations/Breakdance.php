<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helper;

class Breakdance extends Base {
	protected $location      = 'breakdance';
	protected $meta_key_name = '_breakdance_data';

	protected function is_active(): bool {
		return defined( '__BREAKDANCE_VERSION' );
	}

	protected function get_content( int $post_id ): string {
		return \Breakdance\Admin\get_mode( $post_id ) === 'breakdance' ? \Breakdance\Data\get_tree_as_html( $post_id ) : '';
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

	protected function get_tree( int $post_id ) {
		return \Breakdance\Data\get_tree( $post_id );
	}

	protected function search_and_unlink( &$tree, $url ) {
		foreach ( $tree as $key => &$value ) {
			if ( is_array( $value ) ) {
				if ( 'content' === $key && ! empty( $value['link']['url'] ) && $value['link']['url'] === $url ) {
					unset( $value['link'] );
				}

				$this->search_and_unlink( $value, $url );
			} elseif ( 'text' === $key && is_string( $value ) ) {
				$value = Helper::remove_hyperlink( $value, $url );
			}
		}
	}

	public function unlink( array $link ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		$tree = $this->get_tree( $link['source_id'] );

		$this->search_and_unlink( $tree, $link['url'] );

		\Breakdance\Data\set_meta(
			$link['source_id'],
			$this->meta_key_name,
			[
				'tree_json_string' => wp_json_encode( $tree ),
			]
		);
	}
}
