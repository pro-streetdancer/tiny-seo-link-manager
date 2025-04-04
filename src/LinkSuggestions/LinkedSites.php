<?php
// phpcs:ignoreFile
namespace SlimSEOPro\LinkManager\LinkSuggestions;

class LinkedSites {
	const LINKED_SITES = 'slim_seo_link_manager_linked_sites';
	private $option_table;

	public function __construct( $blog_id = 0 ) {
		$blog_id = $blog_id ? $blog_id : ( is_multisite() ? get_current_blog_id() : 1 );
		
		$this->option_table = Common::get_option_table_name( $blog_id );
	}

	public function get_all( $raw = true ): array {
		global $wpdb;

		$linked_sites = $wpdb->get_var(
			$wpdb->prepare( "SELECT `option_value` FROM {$this->option_table} WHERE `option_name` = '%s'", self::LINKED_SITES )
		);

		if ( empty( $linked_sites ) ) {
			return [];
		}

		$linked_sites = unserialize( $linked_sites );

		if ( $raw ) {
			return $linked_sites;
		}

		$sites = [];

		foreach ( $linked_sites as $linked_site => $linked_site_data ) {
			$linked_site_data['site'] = $linked_site;

			$sites[] = $linked_site_data;
		}

		return $sites;
	}

	public function get_all_external(): array {
		$sites          = $this->get_all();
		$external_sites = [];

		foreach ( $sites as $site => $site_data ) {
			if ( 'internal' === $site_data['type'] ) {
				continue;
			}

			$external_sites[ $site ] = $site_data;
		}

		return $external_sites;
	}

	public function get_all_internal(): array {
		$sites          = $this->get_all();
		$internal_sites = [];

		foreach ( $sites as $site => $site_data ) {
			if ( 'external' === $site_data['type'] ) {
				continue;
			}

			$internal_sites[ $site ] = $site_data;
		}

		return $internal_sites;
	}

	public function add( array $data ) {
		global $wpdb;

		$site = $data['site'];

		unset( $data['site'] );

		$linked_sites = $this->get_all();
		$has_data     = ! empty( $linked_sites );

		$linked_sites[ $site ] = $data;

		$linked_sites = serialize( $linked_sites );

		if ( $has_data ) {
			$wpdb->update(
				$this->option_table,
				[ 'option_value' => $linked_sites ],
				[ 'option_name' => self::LINKED_SITES ]
			);
		} else {
			$wpdb->delete(
				$this->option_table,
				[ 'option_name' => self::LINKED_SITES ]
			);

			$wpdb->insert(
				$this->option_table,
				[
					'option_name'  => self::LINKED_SITES,
					'option_value' => $linked_sites,
					'autoload'     => 'no',
				]
			);
		}
	}

	public function get( string $site ): array {
		$linked_sites = $this->get_all();

		return $linked_sites[ $site ] ?? [];
	}

	public function delete( string $site ) {
		global $wpdb;

		$linked_sites = $this->get_all();

		unset( $linked_sites[ $site ] );

		$linked_sites = serialize( $linked_sites );

		$wpdb->update(
			$this->option_table,
			[ 'option_value' => $linked_sites ],
			[ 'option_name' => self::LINKED_SITES ]
		);
	}

	public function delete_all() {
		global $wpdb;

		$wpdb->delete(
			$this->option_table,
			[ 'option_name' => self::LINKED_SITES ]
		);
	}

	public function check_site_exists( string $site ): bool {
		$linked_sites = $this->get_all();

		return isset( $linked_sites[ $site ] );
	}

	public static function check_site_info( string $site ): array {
		global $wpdb;

		if ( ! is_multisite() ) {
			return [
				'type' => 'external',
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$blogs = $wpdb->get_col( "SELECT DISTINCT `blog_id` FROM {$wpdb->blogs}" );

		foreach ( $blogs as $blog_id ) {
			$option_table = Common::get_option_table_name( $blog_id );

			// phpcs:ignore WordPress.DB
			$home_url = $wpdb->get_var( "SELECT `option_value` FROM {$option_table} WHERE `option_name` = 'home'" );

			if ( ! empty( $home_url ) && trailingslashit( $home_url ) === $site ) {
				return [
					'type'    => 'internal',
					'blog_id' => $blog_id,
				];
			}
		}

		return [
			'type' => 'external',
		];
	}
}
