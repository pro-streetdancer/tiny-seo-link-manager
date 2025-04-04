<?php
namespace SlimSEOPro\LinkManager;

use SlimSEOPro\LinkManager\Helpers\Data;

class Helper {
	public static function get_post_types(): array {
		$post_types = Data::get_post_types();

		// Ignore post types set in the settings.
		$option            = get_option( Api\Settings::OPTION_NAME ) ?: [];
		$ignore_post_types = (array) ( $option['ignore_post_types'] ?? [] );
		$post_types        = array_diff_key( $post_types, array_flip( $ignore_post_types ) );

		return array_keys( $post_types );
	}

	public static function get_taxonomies(): array {
		$taxonomies = get_taxonomies( [ 'public' => true ] );

		return $taxonomies;
	}

	public static function get_info_from_url( string $url ): array {
		$info = [
			'target_id'   => 0,
			'target_type' => '',
		];

		// Check if url is a post
		$post_id = url_to_postid( $url );

		if ( $post_id ) {
			return array_merge( $info, [
				'target_id'   => $post_id,
				'target_type' => get_post_type( $post_id ),
			] );
		}

		// Check if url is a term
		$taxonomies = self::get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			] );

			foreach ( $terms as $term ) {
				$term_link = get_term_link( $term );

				if ( $term_link && $url === $term_link ) {
					return array_merge( $info, [
						'target_id'   => $term->term_id,
						'target_type' => "tax: {$taxonomy}",
					] );
				}
			}
		}

		return $info;
	}

	public static function get_link_detail( array $link, string $get = 'source' ): array {
		if ( ( 'source' === $get || 'all' === $get )
			&& ! empty( $link['source_id'] )
			&& ! empty( $link['source_type'] )
		) {
			if ( false !== stripos( $link['source_type'], 'tax:' ) ) {
				$term = get_term( $link['source_id'] );

				if ( ! empty( $term->name ) ) {
					$link = array_merge( $link, [
						'source_name' => html_entity_decode( $term->name ),
						'source_url'  => get_term_link( $term ),
					] );
				}
			} else {
				$link = array_merge( $link, [
					'source_name' => html_entity_decode( get_the_title( $link['source_id'] ) ),
					'source_url'  => get_permalink( $link['source_id'] ),
				] );
			}
		}

		if ( ( 'target' === $get || 'all' === $get )
			&& 'external' !== ( $link['type'] ?? '' )
		) {
			if ( empty( $link['target_id'] ) ) {
				$link = array_merge( $link, self::get_info_from_url( self::get_full_url( $link['url'], $link ) ) );
			}

			if ( ! empty( $link['target_id'] ) && ! empty( $link['target_type'] ) ) {
				if ( false !== stripos( $link['target_type'], 'tax:' ) ) {
					$term = get_term( $link['target_id'] );

					if ( ! empty( $term->name ) ) {
						$link = array_merge( $link, [
							'target_name' => html_entity_decode( $term->name ),
						] );
					}
				} else {
					$link = array_merge( $link, [
						'target_name' => html_entity_decode( get_the_title( $link['target_id'] ) ),
					] );
				}
			}
		}

		return $link;
	}

	public static function get_links_from_text( string $text, int $source_id, string $source_type, string $location = 'post_content' ): array {
		$links = [];

		if ( empty( $text ) ) {
			return $links;
		}

		// Consider using single quote (') and double quote (") around attributes.
		// Use "s" modifier to match multiline.
		preg_match_all( '/<a.*?href=([\'"])([^>]*?)\1[^>]*?>(.*?)<\/a>/is', $text, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return $links;
		}

		$home_url   = untrailingslashit( home_url() );
		$source_url = (string) get_permalink( $source_id );

		foreach ( $matches as $match ) {
			$url = $match[2];

			if (
				(
					$source_url
					&& str_starts_with( untrailingslashit( $url ), untrailingslashit( $source_url ) )
					&& ( 0 === strpos( $url, untrailingslashit( $source_url ) . '#' ) || 0 === strpos( $url, trailingslashit( $source_url ) . '#' ) )
				)
				|| IgnoreLinks::is_ignored( $url )
			) {
				continue;
			}

			$full_link = $match[0];
			$link      = [
				'source_id'   => $source_id,
				'source_type' => $source_type,
				'target_id'   => 0,
				'target_type' => '',
				'url'         => $url,
				'type'        => 'internal',
				'anchor_text' => $match[3],
				'anchor_type' => 'text',
				'location'    => $location,
				'nofollow'    => 0,
				'status'      => SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE,
			];

			// Check if anchor is an image then anchor text is image's alt
			// Consider using single quote (') and double quote (") around attributes.
			// Use "s" modifier to match multiline.
			$image_pattern = '/<img.*?alt=([\'"])([^>]*?)\1[^>]*?>/is';

			if ( preg_match( $image_pattern, $link['anchor_text'] ) ) {
				$link['anchor_type'] = 'image';
				$link['anchor_text'] = preg_replace( $image_pattern, '$2', $link['anchor_text'] );
			}

			// Strip all tags of anchor text
			$link['anchor_text'] = wp_strip_all_tags( $link['anchor_text'] );

			// Check if is an external link.
			if ( ! str_starts_with( self::get_full_url( $link['url'], $link ), $home_url ) ) {
				$link['type'] = 'external';
			}

			// Check rel
			// Consider using single quote (') and double quote (") around attributes.
			// Use "s" modifier to match multiline.
			$rel_pattern = '/<a.*?rel=([\'"])([^>]*?)\1[^>]*?>/is';

			if ( preg_match( $rel_pattern, $full_link ) ) {
				$rel = preg_replace( $rel_pattern, '$2', $full_link );

				if ( false !== stripos( $rel, 'nofollow' ) ) {
					$link['nofollow'] = 1;
				}
			}

			$links[] = $link;
		}

		return $links;
	}

	public static function get_posts( array $args = [] ): array {
		$posts = get_posts( array_merge( [
			'post_type'      => self::get_post_types(),
			'post_status'    => [ 'publish' ],
			'posts_per_page' => -1,
		], $args ) );

		return $posts;
	}

	public static function get_terms( array $args = [] ): array {
		$post_types = self::get_post_types();
		$terms      = [];

		if ( empty( $post_types ) ) {
			return $terms;
		}

		foreach ( $post_types as $post_type ) {
			$taxonomies = get_object_taxonomies( $post_type, 'names' );

			if ( empty( $taxonomies ) ) {
				continue;
			}

			foreach ( $taxonomies as $taxonomy ) {
				$tax_terms = get_terms( array_merge( [
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				], $args ) );

				if ( empty( $tax_terms ) ) {
					continue;
				}

				$terms = array_merge( $terms, $tax_terms );
			}
		}

		return $terms;
	}

	public static function get_full_url( string $url, array $link = [] ): string {
		// Do nothing if that's a full URL.
		if ( Url::is_absolute( $url ) ) {
			return $url;
		}

		// If that's a hash (#something).
		if ( str_starts_with( $url, '#' ) && ! empty( $link['source_id'] ) ) {
			if ( str_starts_with( $link['target_type'] ?? '', 'tax:' ) ) {
				// Term.
				$term     = get_term( $link['target_id'] );
				$base_url = $term && ! is_wp_error( $term ) ? get_term_link( $term ) : '';
				$base_url = is_wp_error( $base_url ) ? '' : $base_url;
			} else {
				// Post.
				$base_url = (string) get_permalink( $link['source_id'] );
			}

			return trailingslashit( $base_url ) . $url;
		}

		// Relative URLs.
		return home_url( $url );
	}

	public static function background_processing_dispatch_post_args( array $args ): array {
		$args['timeout'] = 20;

		return $args;
	}

	public static function get_sql_condition_by_keyword( string $get, string $keyword ): string {
		global $wpdb;

		$condition = '';
		$keyword   = esc_sql( $keyword );

		switch ( $get ) {
			case 'linked_pages':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$target_post_ids = $wpdb->get_col(
					"SELECT `ID`
					FROM {$wpdb->posts}
					WHERE `post_title` LIKE '%{$keyword}%'" // phpcs:ignore
				);

				if ( ! empty( $target_post_ids ) ) {
					$condition = ' AND `target_id` in ( ' . implode( ',', $target_post_ids ) . ' )';
				} else {
					$condition = '  AND `target_id` = -1';
				}

				break;

			case 'external_links':
				$condition = " AND `url` LIKE '%{$keyword}%'";

				break;

			case 'keywords':
				$condition = " WHERE `anchor_text` LIKE '%{$keyword}%'";

				break;
		}

		return $condition;
	}

	public static function get_view_link( array $link, string $prefix = 'target' ): string {
		if ( false !== stripos( $link[ "{$prefix}_type" ], 'tax:' ) ) {
			$term = get_term( $link[ "{$prefix}_id" ] );

			return ! empty( $term->term_id ) ? get_term_link( $term ) : '';
		}

		return get_permalink( $link[ "{$prefix}_id" ] ) ?: '';
	}

	public static function get_edit_link( array $link, string $prefix = 'target' ): string {
		if ( false !== stripos( $link[ "{$prefix}_type" ], 'tax:' ) ) {
			$term = get_term( $link[ "{$prefix}_id" ] );

			return ! empty( $term->term_id ) ? get_edit_term_link( $term ) : '';
		}

		return get_edit_post_link( $link[ "{$prefix}_id" ] ) ?: '';
	}

	public static function get_link_title( array $link, string $prefix = 'target' ): string {
		if ( false !== stripos( $link[ "{$prefix}_type" ], 'tax:' ) ) {
			$term = get_term( $link[ "{$prefix}_id" ] );

			return ! empty( $term->term_id ) ? html_entity_decode( $term->name ) : '';
		}

		return get_the_title( $link[ "{$prefix}_id" ] );
	}

	public static function replace_string( string $old_str, string $new_str, string $str ): string {
		$replaced = 0;
		$str      = str_replace( $old_str, $new_str, $str, $replaced );

		if ( ! $replaced ) {
			$str = str_replace( htmlspecialchars( $old_str, ENT_NOQUOTES ), $new_str, $str );
		}

		return $str;
	}

	public static function remove_hyperlink( string $text, string $url ): string {
		$text = preg_replace( '/<a\s+[^>]*href="' . preg_quote( $url, '/' ) . '"[^>]*>(.*?)<\/a>/', '$1', $text );

		return $text;
	}

	public static function allow_unlink( array $link ): bool {
		return apply_filters( 'slim_seo_link_manager_allow_unlink', false, $link );
	}

	public static function unlink( array $link ) {
		if ( ! self::allow_unlink( $link ) ) {
			return false;
		}

		switch ( $link['location'] ) {
			case 'post_content':
				$post_content = get_post_field( 'post_content', $link['source_id'] );
				$post_content = self::remove_hyperlink( $post_content, $link['url'] );

				wp_update_post( [
					'ID'           => $link['source_id'],
					'post_content' => $post_content,
				] );

				break;

			case 'term_description':
				$term = get_term( $link['source_id'] );

				if ( ! empty( $term->description ) ) {
					$term_description = $term->description;
					$term_description = self::remove_hyperlink( $term_description, $link['url'] );

					wp_update_term( $link['source_id'], $term->taxonomy, [
						'description' => $term_description,
					] );
				}

				break;

			default:
				do_action( 'slim_seo_link_manager_unlink', $link );

				break;
		}

		$tbl_links = new Database\Links();
		$tbl_links->delete( $link['id'] );

		return true;
	}

	public static function purge_cache() {
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
	}

	public static function redirect_codes_list() {
		return [ 301, 302 ];
	}

	public static function plugin_warning_messages() {
		$messages = [
			// Translators: %1$s - URL to the settings page.
			'no_key'  => __( 'You have not set your Slim SEO Link Manager license key yet. Please <a href="%1$s" target="_blank">enter your license key</a> to continue.', 'slim-seo-link-manager' ),
			// Translators: %1$s - URL to the settings page.
			'invalid' => __( 'Your license key for Slim SEO Link Manager is <b>invalid</b>. Please <a href="%1$s" target="_blank">update your license key</a> to continue.', 'slim-seo-link-manager' ),
			// Translators: %1$s - URL to the settings page.
			'error'   => __( 'Your license key for Slim SEO Link Manager is <b>invalid</b>. Please <a href="%1$s" target="_blank">update your license key</a> to continue.', 'slim-seo-link-manager' ),
			// Translators: %2$s - URL to the My Account page.
			'expired' => __( 'Your license key for Slim SEO Link Manager is <b>expired</b>. Please <a href="%2$s" target="_blank">renew your license</a> to continue.', 'slim-seo-link-manager' ),
		];

		return apply_filters( 'slim_seo_link_manager_plugin_warning_messages', $messages );
	}
}
