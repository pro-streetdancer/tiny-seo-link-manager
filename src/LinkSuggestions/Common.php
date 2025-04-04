<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Database\Links;
use SlimSEOPro\LinkManager\Api\Settings as ApiSettings;

class Common {
	const AMOUNT_OF_SENDING_SUGGESSTION_ITEMS = 500;

	public static function generate_secret_key(): string {
		$secret_key = wp_rand();
		$secret_key = md5( $secret_key );

		return $secret_key;
	}

	public static function get_content( int $post_id ): string {
		$content = get_post_field( 'post_content', $post_id );
		$content = do_shortcode( $content );
		$content = do_blocks( $content );
		$content = (string) apply_filters( 'slim_seo_link_manager_content', $content, $post_id );

		// Remove script
		if ( false !== strpos( $content, '<script' ) ) {
			$content = mb_ereg_replace( '<script(?:[^>]*)>(.*?)<\/script>', '', $content );
		}

		// Remove style
		if ( false !== strpos( $content, '<style' ) ) {
			$content = mb_ereg_replace( '<style(?:[^>]*)>(.*?)<\/style>', '', $content );
		}

		return $content;
	}

	public static function get_linked_posts( int $post_id ): array {
		$tbl_links      = new Links;
		$outbound_links = $tbl_links->get_links_by_object( $post_id, get_post_type( $post_id ) );

		// Get links to posts only.
		$outbound_links = array_filter( $outbound_links, function ( $outbound_link ) {
			return ! empty( $outbound_link['target_id'] ) && ! str_contains( $outbound_link['target_type'], 'tax:' );
		} );

		return wp_list_pluck( $outbound_links, 'target_id' );
	}

	public static function get_post_terms( int $post_id ): array {
		$post_terms = [];
		$taxonomies = get_post_taxonomies( $post_id );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( empty( $post_terms[ $taxonomy ] ) ) {
					$post_terms[ $taxonomy ] = [];
				}
				$post_terms[ $taxonomy ][] = $term->term_id;
			}
		}

		return $post_terms;
	}

	public static function build_query_args( int $object_id = 0, bool $same_taxonomies = false ): array {
		if ( ! $object_id ) {
			return [];
		}

		$args = [];

		// Ignore the current post
		$ignored_posts = [ $object_id ];

		// Add posts that are already linked in the current post to list of ignored posts
		$linked_posts = self::get_linked_posts( $object_id );

		if ( ! empty( $linked_posts ) ) {
			$ignored_posts = array_merge( $ignored_posts, $linked_posts );
		}

		$args['post__not_in'] = $ignored_posts;

		if ( ! $same_taxonomies ) {
			return $args;
		}

		// Same taxonomies.
		$terms = self::get_post_terms( $object_id );

		if ( empty( $terms ) ) {
			return $args;
		}

		$tax_query = [ 'relation' => 'OR' ];

		foreach ( $terms as $taxonomy => $term_ids ) {
			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'terms'    => $term_ids,
			];
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery
		$args['tax_query'] = $tax_query;

		return $args;
	}

	public static function is_enable_interlink_external_sites(): bool {
		$option = get_option( ApiSettings::OPTION_NAME ) ?: [];

		return ! empty( $option['enable_interlink_external_sites'] );
	}

	public static function get_option_table_name( int $blog_id = 0 ): string {
		global $wpdb;

		$blog_id = $blog_id ? $blog_id : ( is_multisite() ? get_current_blog_id() : 1 );

		$option_table = 1 === $blog_id ? "{$wpdb->base_prefix}options" : "{$wpdb->base_prefix}{$blog_id}_options";

		return $option_table;
	}

	public static function get_secret_key( int $blog_id = 0 ): string {
		global $wpdb;

		$option_table = self::get_option_table_name( $blog_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var(
			// phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT `option_value` FROM {$option_table} WHERE `option_name` = '%s'", ApiSettings::OPTION_NAME )
		);

		if ( empty( $result ) ) {
			return '';
		}

		$result = maybe_unserialize( $result );

		if ( empty( $result['enable_interlink_external_sites'] ) || empty( $result['interlink_external_sites_secret_key'] ) ) {
			return '';
		}

		return $result['interlink_external_sites_secret_key'];
	}

	public static function remote_post( array $args ) {
		$request = wp_remote_post( $args['site'] . 'wp-json/slim-seo-link-manager/public-suggestions/' . $args['action'], [
			'body' => array_merge( [
				'secret_key' => $args['secret_key'],
				'site'       => $args['home_url'],
			], $args['params'] ?? [] ),
		] );

		if ( is_wp_error( $request ) ) {
			return [
				'message' => sprintf(
					'%s %s',
					__( 'Cannot connect to', 'slim-seo-link-manager' ),
					$args['site']
				),
			];
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode( $response );

		if ( empty( $response->status ) ) {
			return [
				'message' => ! empty( $response->message ) ? $response->message : ( $args['failure_message'] ?? '' ),
			];
		}

		return (array) $response;
	}

	public static function add_data_to_external_site( array $args ) {
		$data        = new Data();
		$list        = $data->get_all( $args['home_url'] );
		$send_data   = new SendData();
		$list_length = count( $list );

		for ( $i = 0; $i < $list_length; $i += self::AMOUNT_OF_SENDING_SUGGESSTION_ITEMS ) {
			$items = array_slice( $list, $i, self::AMOUNT_OF_SENDING_SUGGESSTION_ITEMS );
			$items = array_map( function ( $item ) {
				unset( $item['id'] );

				return $item;
			}, $items );

			$send_data->push_to_queue( [
				'secret_key' => $args['secret_key'],
				'site'       => $args['site'],
				'action'     => 'add_data',
				'home_url'   => $args['home_url'],
				'params'     => [
					'list' => $items,
				],
			] );
		}

		$send_data->save()->dispatch();
	}

	public static function validate_incomming_access( array $args = [] ): bool {
		if ( ! empty( $args['secret_key'] ) && $args['secret_key'] !== self::get_secret_key() ) {
			return false;
		}

		$linked_sites = new LinkedSites();

		if ( ! empty( $args['site'] ) && ! $linked_sites->check_site_exists( $args['site'] ) ) {
			return false;
		}

		return true;
	}

	public static function delete_linked_sites() {
		$linked_sites = new LinkedSites();
		$sites        = $linked_sites->get_all();

		$linked_sites->delete_all();

		$secret_key  = self::get_secret_key();
		$home_url    = trailingslashit( home_url() );
		$delete_site = new DeleteSite();

		foreach ( $sites as $site => $site_data ) {
			if ( 'internal' === $site_data['type'] ) {
				$target_linked_sites = new LinkedSites( $site_data['blog_id'] );
				$target_linked_sites->delete( $home_url );

				continue;
			}

			$delete_site->push_to_queue( [
				'secret_key' => $secret_key,
				'site'       => $site,
				'home_url'   => $home_url,
			] );
		}

		$delete_site->save()->dispatch();
	}
}
