<?php
namespace SlimSEOPro\LinkManager;

class CustomFields {
	public function __construct() {
		add_filter( 'slim_seo_link_manager_get_all_links_from_post', [ $this, 'get_all_links_from_post' ], 30, 2 );
		add_filter( 'slim_seo_link_manager_outbound_links', [ $this, 'outbound_links' ], 30, 2 );

		add_filter( 'slim_seo_link_manager_get_all_links_from_term', [ $this, 'get_all_links_from_term' ], 30, 2 );

		add_filter( 'slim_seo_link_manager_allow_update_link_url', [ $this, 'allow' ], 30, 2 );
		add_action( 'slim_seo_link_manager_update_link_url', [ $this, 'update_link_url' ], 30, 3 );

		add_filter( 'slim_seo_link_manager_allow_unlink', [ $this, 'allow' ], 30, 2 );
		add_action( 'slim_seo_link_manager_unlink', [ $this, 'unlink' ], 30 );
	}

	public function get_all_links_from_post( array $links, int $post_id ): array {
		$custom_fields_links = $this->get_post_custom_fields_links( $post_id );

		if ( empty( $custom_fields_links ) ) {
			return $links;
		}

		$links = array_merge( $links, $custom_fields_links );

		return $links;
	}

	public function outbound_links( $links, int $post_id ) {
		$custom_fields_links = $this->get_post_custom_fields_links( $post_id );

		if ( empty( $custom_fields_links ) ) {
			return $links;
		}

		// Remove all links that are not from post content
		$links = array_filter( $links, function ( $link ) {
			return $link['location'] === 'post_content';
		} );
		$links = array_merge( $links, $custom_fields_links );

		return $links;
	}

	public function get_post_custom_fields_links( int $post_id ): array {
		$custom_fields = apply_filters( 'slim_seo_link_manager_post_custom_fields', [] );
		$links         = [];

		if ( empty( $custom_fields ) ) {
			return $links;
		}

		$post_type = get_post_type( $post_id );

		foreach ( $custom_fields as $custom_field ) {
			$custom_field_data = get_post_meta( $post_id, $custom_field, true );

			if ( empty( $custom_field_data ) ) {
				continue;
			}

			$custom_field_links = Helper::get_links_from_text( $custom_field_data, $post_id, $post_type, "custom_field: {$custom_field}" );

			if ( empty( $custom_field_links ) ) {
				continue;
			}

			$custom_field_links = array_map( function ( $link ) {
				$link = array_merge( $link, Helper::get_info_from_url( $link['url'] ) );

				return $link;
			}, $custom_field_links );

			$links = array_merge( $links, $custom_field_links );
		}

		return $links;
	}

	public function get_all_links_from_term( array $links, int $term_id ): array {
		$custom_fields_links = $this->get_term_custom_fields_links( $term_id );

		if ( empty( $custom_fields_links ) ) {
			return $links;
		}

		$links = array_merge( $links, $custom_fields_links );

		return $links;
	}

	public function get_term_custom_fields_links( int $term_id ): array {
		$custom_fields = apply_filters( 'slim_seo_link_manager_term_custom_fields', [] );
		$links         = [];

		if ( empty( $custom_fields ) ) {
			return $links;
		}

		$term = get_term( $term_id );

		foreach ( $custom_fields as $custom_field ) {
			$custom_field_data = get_term_meta( $term_id, $custom_field, true );

			if ( empty( $custom_field_data ) ) {
				continue;
			}

			$custom_field_links = Helper::get_links_from_text( $custom_field_data, $term_id, "tax: {$term->taxonomy}", "term_custom_field: {$custom_field}" );

			if ( empty( $custom_field_links ) ) {
				continue;
			}

			$custom_field_links = array_map( function ( $link ) {
				$link = array_merge( $link, Helper::get_info_from_url( $link['url'] ) );

				return $link;
			}, $custom_field_links );

			$links = array_merge( $links, $custom_field_links );
		}

		return $links;
	}

	public function allow( bool $allow, array $link ): bool {
		return $this->is_link_from_post_custom_field( $link ) || $this->is_link_from_term_custom_field( $link ) ? true : $allow;
	}

	public function update_link_url( array $link, string $old_url, string $new_url ) {
		$custom_field_data = $this->get_custom_field_data( $link );

		if ( empty( $custom_field_data ) ) {
			return;
		}

		$new_data = Helper::replace_string( $old_url, $new_url, $custom_field_data );

		$this->update_custom_field_data( $link, $new_data );
	}

	public function unlink( array $link ) {
		$custom_field_data = $this->get_custom_field_data( $link );

		if ( empty( $custom_field_data ) ) {
			return;
		}

		$new_data = Helper::remove_hyperlink( $custom_field_data, $link['url'] );

		$this->update_custom_field_data( $link, $new_data );
	}

	protected function is_link_from_post_custom_field( array $link ): bool {
		return str_starts_with( $link['location'], 'custom_field:' );
	}

	protected function is_link_from_term_custom_field( array $link ): bool {
		return str_starts_with( $link['location'], 'term_custom_field:' );
	}

	protected function get_meta_type_from_link( array $link ): string {
		$meta_type = $this->is_link_from_post_custom_field( $link ) ? 'post' : ( $this->is_link_from_term_custom_field( $link ) ? 'term' : '' );

		return $meta_type;
	}

	protected function get_custom_field_name_from_link( array $link ): string {
		$link_locations    = explode( ': ', $link['location'] );
		$custom_field_name = $link_locations[1] ?? '';

		return $custom_field_name;
	}

	protected function get_custom_field_data( array $link ) {
		$meta_type         = $this->get_meta_type_from_link( $link );
		$custom_field_name = $this->get_custom_field_name_from_link( $link );

		if ( empty( $meta_type ) || empty( $custom_field_name ) ) {
			return '';
		}

		$custom_field_data = get_metadata( $meta_type, $link['source_id'], $custom_field_name, true );

		return $custom_field_data;
	}

	protected function update_custom_field_data( array $link, $new_data ) {
		$meta_type         = $this->get_meta_type_from_link( $link );
		$custom_field_name = $this->get_custom_field_name_from_link( $link );

		if ( empty( $meta_type ) || empty( $custom_field_name ) ) {
			return '';
		}

		update_metadata( $meta_type, $link['source_id'], $custom_field_name, $new_data );
	}
}
