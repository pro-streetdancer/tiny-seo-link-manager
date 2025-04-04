<?php
namespace SlimSEOPro\LinkManager\Integrations;

use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Helpers\Data;

class TranslatePress {
	private $trp;
	protected $location            = 'translatepress';
	protected $translated_language = '';

	public function __construct() {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

	public function setup() {
		if ( ! defined( 'TRP_PLUGIN_VERSION' ) ) {
			return;
		}

		$this->trp = \TRP_Translate_Press::get_trp_instance();

		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'get_all_links_from_post' ], 50, 2 );
		add_filter( 'slim_seo_link_manager_outbound_links', [ $this, 'outbound_links' ], 50, 2 );
		add_filter( 'slim_seo_link_manager_allow_update_link_url', [ $this, 'allow' ], 50, 2 );
		add_action( 'slim_seo_link_manager_update_link_url', [ $this, 'update_link_url' ], 50, 3 );
	}

	public function get_all_links_from_post( array $links, int $post_id ): array {
		$translated_links = $this->get_links( $post_id );

		if ( empty( $translated_links ) ) {
			return $links;
		}

		$links = array_merge( $links, $translated_links );

		return $links;
	}

	public function outbound_links( $links, int $post_id ) {
		$translated_links = $this->get_links( $post_id );

		if ( empty( $translated_links ) ) {
			return $links;
		}

		// Remove all links that are from TranslatePress
		$links = array_filter( $links, function ( $link ) {
			return false === stripos( $link['location'], $this->location );
		} );
		$links = array_merge( $links, $translated_links );

		return $links;
	}

	public function allow( bool $allow, array $link ): bool {
		return str_starts_with( $link['location'], $this->location ) ? true : $allow;
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		if ( false === stripos( $link['location'], $this->location ) ) {
			return;
		}

		global $wpdb;

		$location = explode( ': ', $link['location'] );
		$language = $location[1];

		// Check translated slug of internal link
		if ( 'internal' === $link['type'] ) {
			if ( empty( $link['target_id'] ) || empty( $link['target_type'] ) || ! in_array( $link['target_type'], Helper::get_post_types(), true ) ) {
				return;
			}

			$translated_slug = $this->get_translated_slug_by_post( $link['target_id'], $language );

			// Only PRO version has translated slug for internal link. If it's empty then that's FREE version, do nothing
			if ( empty( $translated_slug ) ) {
				return;
			}

			$old_slug = $this->get_slug_from_url( $old_url );

			if ( $old_slug !== $translated_slug ) {
				return;
			}

			$new_slug = $this->get_slug_from_url( $new_url );

			$this->update_translated_slug( $link['target_id'], $new_slug, $language );

			return;
		}

		// Check translated string of external link in custom table
		$dictionary_table_name = $wpdb->prefix . 'trp_dictionary_' . strtolower( $this->get_setting( 'default-language' ) ) . '_' . strtolower( $language );

		// phpcs:ignore
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$dictionary_table_name'" ) !== $dictionary_table_name ) {
			return;
		}

		$results = $wpdb->get_results( "SELECT * FROM {$dictionary_table_name} WHERE `translated` LIKE '%" . esc_sql( $old_url ) . "%'", ARRAY_A ); // phpcs:ignore

		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $result ) {
			$sql = sprintf(
				'UPDATE %s
				SET `translated` = REPLACE(`translated`, "%s", "%s")
				WHERE `id` = %s',
				$dictionary_table_name,
				$old_url,
				$new_url,
				$result['id']
			);

			$wpdb->query( $sql ); // phpcs:ignore
		}
	}

	protected function get_links( int $post_id ): array {
		$source_id        = wp_is_post_revision( $post_id ) ?: $post_id;
		$source_type      = get_post_type( $source_id );
		$post_types       = Helper::get_post_types();
		$translated_links = [];

		// phpcs:ignore
		if ( ! in_array( $source_type, $post_types ) ) {
			return $translated_links;
		}

		$publish_languages = $this->get_setting( 'publish-languages' );

		if ( empty( $publish_languages ) ) {
			return $translated_links;
		}

		$post_content = Data::get_content( $source_id );
		$post_content = apply_filters( 'the_content', $post_content ); // phpcs:ignore

		if ( empty( $post_content ) ) {
			return $translated_links;
		}

		$translated_links = [];
		$default_language = $this->get_setting( 'default-language' );

		// Disable this hook because it prevents getting translated URL from admin side
		add_filter( 'trp_add_language_to_home_url_check_for_admin', '__return_false' );

		foreach ( $publish_languages as $publish_language ) {
			if ( $publish_language === $default_language ) {
				continue;
			}

			/**
			 * TranslatePress has a function to reset language to default language when checking via AJAX - trp_reset_language, priority 99999999.
			 * So if we do not set the translated language, the links scanner cannot get translated links
			 */
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$this->translated_language = $publish_language;

				add_filter( 'trp_before_translate_content', [ $this, 'set_translated_language' ], 999999999 );
			}

			$translated_content = trp_translate( $post_content, $publish_language );
			$links              = Helper::get_links_from_text( $translated_content, $source_id, $source_type, $this->location . ': ' . $publish_language );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				remove_filter( 'trp_before_translate_content', [ $this, 'set_translated_language' ] );
			}

			if ( empty( $links ) ) {
				continue;
			}

			foreach ( $links as $link_index => $link ) {
				if ( 'external' === $link['type'] ) {
					continue;
				}

				$link = array_merge( $link, Helper::get_info_from_url( $this->get_url( $link['url'], $default_language ) ) );

				/**
				 * If an internal link has custom slug (PRO only), our function get_info_from_url cannot get target_id.
				 * We have to use this code to get it
				 */
				if ( empty( $link['target_id'] ) ) {
					$slug = $this->get_slug_from_url( $link['url'] );

					if ( $slug ) {
						$post_id = $this->get_post_by_slug( $slug, $publish_language );

						if ( $post_id ) {
							$link['target_id']   = $post_id;
							$link['target_type'] = get_post_type( $post_id );
						}
					}
				}

				$links[ $link_index ] = $link;
			}

			$translated_links = array_merge( $translated_links, $links );
		}

		remove_filter( 'trp_add_language_to_home_url_check_for_admin', '__return_false' );

		return $translated_links;
	}

	private function get_setting( string $name ) {
		$settings_component = $this->trp->get_component( 'settings' );
		$settings           = $settings_component->get_settings();

		return $settings[ $name ] ?? '';
	}

	private function get_url( string $url, string $language ): string {
		$url_converter = $this->trp->get_component( 'url_converter' );

		return $url_converter->get_url_for_language( $language, $url, '' );
	}

	/**
	 * Reference: translatepress-business/add-ons-advanced/seo-pack/includes/class-slug-manager.php > get_original_slug function
	 */
	private function get_translated_slugs( string $slug, string $language ): array {
		$translated_slug_meta = $this->get_translated_slug_meta();

		if ( empty( $translated_slug_meta ) ) {
			return [];
		}

		$human_translated_slug_meta     = $translated_slug_meta['human'];
		$automatic_translated_slug_meta = $translated_slug_meta['automatic'];
		$slug_decoded                   = urldecode( $slug );
		$slug_encoded                   = urlencode( $slug_decoded ); // phpcs:ignore

		global $wpdb;

		$sql = sprintf(
			"SELECT *
			FROM $wpdb->postmeta
			WHERE ( `meta_key` = '%s' OR `meta_key` = '%s' )
			AND ( `meta_value` = '%s' OR `meta_value` = '%s' )",
			$human_translated_slug_meta . $language,
			$automatic_translated_slug_meta . $language,
			$slug_decoded,
			$slug_encoded
		);

		$translated_slugs = $wpdb->get_results( $sql ); // phpcs:ignore

		if ( empty( $translated_slugs ) ) {
			return [];
		}

		return $translated_slugs;
	}

	private function get_translated_slug_meta(): array {
		if ( ! class_exists( '\TRP_IN_SP_Meta_Based_Strings' ) ) {
			return [];
		}

		$meta_based_strings = new \TRP_IN_SP_Meta_Based_Strings();
		$meta               = [
			'human'     => $meta_based_strings->get_human_translated_slug_meta(),
			'automatic' => $meta_based_strings->get_automatic_translated_slug_meta(),
		];

		return $meta;
	}

	private function get_post_by_slug( string $slug, string $language ): int {
		$translated_slugs = $this->get_translated_slugs( $slug, $language );

		if ( empty( $translated_slugs ) ) {
			return 0;
		}

		return (int) $translated_slugs[0]->post_id;
	}

	public function set_translated_language( $output ) {
		global $TRP_LANGUAGE; // phpcs:ignore

		$TRP_LANGUAGE = $this->translated_language; // phpcs:ignore

		return $output;
	}

	private function get_translated_slug_by_post( int $post_id, string $language ): string {
		$translated_slug_meta = $this->get_translated_slug_meta();
		$translated_slug      = '';

		if ( empty( $translated_slug_meta ) ) {
			return $translated_slug;
		}

		$translated_slug = get_post_meta( $post_id, $translated_slug_meta['human'] . $language, true );

		if ( empty( $translated_slug ) ) {
			$translated_slug = get_post_meta( $post_id, $translated_slug_meta['automatic'] . $language, true );
		}

		return $translated_slug ?: '';
	}

	private function update_translated_slug( int $post_id, string $slug, string $language ) {
		$translated_slug_meta = $this->get_translated_slug_meta();

		if ( empty( $translated_slug_meta ) ) {
			return;
		}

		update_post_meta( $post_id, $translated_slug_meta['human'] . $language, $slug );
		update_post_meta( $post_id, $translated_slug_meta['automatic'] . $language, $slug );
	}

	private function get_slug_from_url( string $url ): string {
		$parsed_url = wp_parse_url( $url );
		$url_paths  = explode( '/', untrailingslashit( $parsed_url['path'] ) );
		$slug       = end( $url_paths );

		return $slug;
	}
}
