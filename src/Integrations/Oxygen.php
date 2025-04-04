<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helpers\Data;
use SlimSEOPro\LinkManager\Helper;

class Oxygen extends Base {
	protected $location                     = 'oxygen';
	protected $regenerate_page_css_meta_key = 'regenerate_page_css';
	protected $ct_builder_json_name         = 'ct_builder_json';
	protected $ct_builder_shortcodes_name   = 'ct_builder_shortcodes';

	protected function is_active(): bool {
		return defined( 'CT_VERSION' );
	}

	public function setup() {
		if ( ! $this->is_active() ) {
			return;
		}

		parent::setup();

		add_action( 'template_redirect', [ $this, 'regenerate_css' ] );
	}

	protected function get_content( int $post_id ): string {
		if ( 'ct_template' === get_post_type( $post_id ) ) {
			return '';
		}

		// Assign global $post object to make Oxygen able to scan post content from the WordPress editor.
		global $post;
		$temp = $post;
		$post = get_post( $post_id ); // phpcs:ignore

		$template = $this->get_template( $post_id );
		$content  = $this->parse_json( $template, $post_id ) ?: $this->parse_shortcodes( $template, $post_id );

		$post = $temp; // phpcs:ignore

		// Trick to regenerate page CSS when loading oxygen page on frontend
		update_post_meta( $post_id, $this->regenerate_page_css_meta_key, 1 );

		return $content;
	}

	/**
	 * Get the template ID for a post.
	 * @see ct_oxygen_admin_menu()
	 */
	private function get_template( int $post_id ): int {
		$template = intval( get_post_meta( $post_id, 'ct_other_template', true ) );

		if ( $template === -1 ) {
			return $post_id;
		}

		if ( $template > 0 ) {
			return $template;
		}

		if ( $post_id === intval( get_option( 'page_on_front' ) ) || $post_id === intval( get_option( 'page_for_posts' ) ) ) {
			$template = ct_get_archives_template( $post_id );

			if ( ! $template ) {
				$template = ct_get_posts_template( $post_id );
			}
		} else {
			$template = ct_get_posts_template( $post_id );
		}

		return $template ? $template->ID : $post_id;
	}

	private function parse_json( int $template, int $post_id ): string {
		$json = get_post_meta( $template, $this->ct_builder_json_name, true );

		if ( empty( $json ) || ! function_exists( 'do_oxygen_elements' ) ) {
			return '';
		}

		global $oxygen_doing_oxygen_elements;
		$oxygen_doing_oxygen_elements = true; // phpcs:ignore

		// Cannot parse inner content in a non-frontend context, so we have to parse it manually.
		$content = str_contains( $json, 'ct_inner_content' ) ? Data::get_content( $post_id ) : '';

		$json     = json_decode( $json, true );
		$content .= do_oxygen_elements( $json );

		return $content;
	}

	private function parse_shortcodes( int $template, int $post_id ): string {
		$shortcodes = get_post_meta( $template, $this->ct_builder_shortcodes_name, true );

		if ( empty( $shortcodes ) ) {
			return '';
		}

		// Cannot parse inner content in a non-frontend context, so we have to parse it manually.
		$content  = str_contains( $shortcodes, 'ct_inner_content' ) ? Data::get_content( $post_id ) : '';
		$content .= ct_do_shortcode( $shortcodes );

		return $content;
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		// Update post content and custom fields in case Oxygen template has dynamic data
		$custom_fields = get_post_meta( $link['source_id'] );

		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $custom_field_key => $custom_field ) {
				foreach ( $custom_field as $value ) {
					if ( ! is_string( $value ) ) {
						continue;
					}

					$new_value = Helper::replace_string( $old_url, $new_url, $value );

					if ( $new_value === $value ) {
						continue;
					}

					update_post_meta( $link['source_id'], $custom_field_key, $new_value, $value );
				}
			}
		}

		$post_content     = Data::get_content( $link['source_id'] );
		$new_post_content = Helper::replace_string( $old_url, $new_url, $post_content );

		if ( $post_content !== $new_post_content ) {
			wp_update_post( [
				'ID'           => $link['source_id'],
				'post_content' => $new_post_content,
			] );
		}

		// Update template data
		$template = $this->get_template( $link['source_id'] );

		global $wpdb;

		$sql = sprintf(
			'UPDATE %s
			SET `meta_value` = REPLACE(`meta_value`, "%s", "%s")
			WHERE `post_id` = %s AND `meta_key` = "%s"',
			$wpdb->postmeta,
			str_replace( '/', '\\\/', $old_url ),
			str_replace( '/', '\\\/', $new_url ),
			$template,
			$this->ct_builder_json_name
		);

		$wpdb->query( $sql ); // phpcs:ignore

		$sql = sprintf(
			'UPDATE %s
			SET `meta_value` = REPLACE(`meta_value`, "%s", "%s")
			WHERE `post_id` = %s AND `meta_key` = "%s"',
			$wpdb->postmeta,
			$old_url,
			$new_url,
			$template,
			$this->ct_builder_shortcodes_name
		);

		$wpdb->query( $sql ); // phpcs:ignore
	}

	public function allow( bool $allow, array $link ): bool {
		$template = $this->get_template( $link['source_id'] );

		return 'ct_template' === get_post_type( $template ) ? $allow : true;
	}

	public function remove_post_types( array $post_types ): array {
		unset( $post_types['ct_template'] );
		unset( $post_types['oxy_user_library'] );
		return $post_types;
	}

	public function regenerate_css() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id             = get_the_ID();
		$regenerate_page_css = get_post_meta( $post_id, $this->regenerate_page_css_meta_key, true );

		if ( ! $regenerate_page_css ) {
			return;
		}

		oxygen_vsb_cache_page_css( $post_id );
		delete_post_meta( $post_id, $this->regenerate_page_css_meta_key );
	}

	protected function search_and_unlink( &$elements, $url ) {
		foreach ( $elements as $key => &$value ) {
			if ( is_array( $value ) ) {
				if (
					in_array( ( $value['name'] ?? '' ), [ 'ct_link_text', 'ct_link' ], true )
					&& $url === ( $value['options']['original']['url'] ?? '' )
				) {
					unset( $value['options']['original']['url'] );

					$value['name'] = 'oxy_rich_text';
				}

				$this->search_and_unlink( $value, $url );
			} elseif ( 'ct_content' === $key && is_string( $value ) ) {
				$value = Helper::remove_hyperlink( $value, $url );
			}
		}
	}

	public function unlink( array $link ) {
		if ( $this->location !== $link['location'] ) {
			return;
		}

		// Update post content and custom fields in case Oxygen template has dynamic data
		$custom_fields = get_post_meta( $link['source_id'] );

		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $custom_field_key => $custom_field ) {
				foreach ( $custom_field as $value ) {
					if ( ! is_string( $value ) ) {
						continue;
					}

					$new_value = Helper::remove_hyperlink( $value, $link['url'] );

					if ( $new_value === $value ) {
						continue;
					}

					update_post_meta( $link['source_id'], $custom_field_key, $new_value, $value );
				}
			}
		}

		$post_content     = Data::get_content( $link['source_id'] );
		$new_post_content = Helper::remove_hyperlink( $post_content, $link['url'] );

		if ( $post_content !== $new_post_content ) {
			wp_update_post( [
				'ID'           => $link['source_id'],
				'post_content' => $new_post_content,
			] );
		}

		// Update template data
		$template = $this->get_template( $link['source_id'] );

		$shortcodes = get_post_meta( $template, $this->ct_builder_shortcodes_name, true );

		if ( ! empty( $shortcodes ) ) {
			// Remove link in text
			$shortcodes = Helper::remove_hyperlink( $shortcodes, $link['url'] );

			// Remove link in textlink
			$shortcodes = Helper::replace_string( '"url":"' . $link['url'] . '",', '', $shortcodes );

			update_post_meta( $template, $this->ct_builder_shortcodes_name, $shortcodes );
		}

		$json_data = get_post_meta( $template, $this->ct_builder_json_name, true );

		if ( ! empty( $json_data ) ) {
			$json_data = json_decode( $json_data, true );

			$this->search_and_unlink( $json_data, $link['url'] );

			update_post_meta( $template, $this->ct_builder_json_name, wp_slash( wp_json_encode( $json_data ) ) );
		}
	}
}
