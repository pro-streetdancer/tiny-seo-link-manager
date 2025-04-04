<?php
// phpcs:ignoreFile
namespace SlimSEOPro\LinkManager\LinkSuggestions;

class Data {
	public function __construct() {
		global $wpdb;

		$wpdb->tables[]                       = 'slim_seo_link_suggestions_data';
		$wpdb->slim_seo_link_suggestions_data = $wpdb->base_prefix . 'slim_seo_link_suggestions_data';
	}

	public function add( array $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->slim_seo_link_suggestions_data,
			$data
		);
	}

	public function delete( string $site_url ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->slim_seo_link_suggestions_data,
			[ 'site_url' => $site_url ]
		);
	}

	public function update( array $data ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->slim_seo_link_suggestions_data,
			$data,
			[ 'id' => $data['id'] ]
		);
	}

	public function delete_object( int $object_id, string $object_type, string $site_url ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->slim_seo_link_suggestions_data,
			[
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'site_url'    => $site_url,
			]
		);
	}

	public function get_all_except( string $site_url ) {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$wpdb->slim_seo_link_suggestions_data} WHERE `site_url` <> '{$site_url}'", ARRAY_A );
	}

	public function get( int $object_id, string $object_type, string $site_url ) {
		global $wpdb;

		return $wpdb->get_row(
			"SELECT *
			FROM {$wpdb->slim_seo_link_suggestions_data}
			WHERE `object_id` = {$object_id}
				AND `object_type` = '{$object_type}'
				AND `site_url` = '{$site_url}'",

			ARRAY_A
		);
	}

	public function get_all( string $site_url ) {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$wpdb->slim_seo_link_suggestions_data} WHERE `site_url` = '{$site_url}'", ARRAY_A );
	}

	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql_query       = "
			CREATE TABLE IF NOT EXISTS {$wpdb->slim_seo_link_suggestions_data} (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`site_url` varchar(255) NOT NULL,
				`object_id` bigint(20) unsigned NOT NULL,
				`object_type` varchar(255) NOT NULL,
				`title` varchar(255) NOT NULL,
				`url` varchar(255) NOT NULL,
				`words` varchar(255) NOT NULL,
				`datePublished` datetime NOT NULL,
				
				PRIMARY KEY (`id`),
				
				KEY `site_url` (`site_url`),
				KEY `object_id` (`object_id`)				
			) $charset_collate;
		";

		dbDelta( $sql_query );
	}

	public function drop_table() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->slim_seo_link_suggestions_data}" );
	}

	public function table_exist(): bool {
		global $wpdb;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->slim_seo_link_suggestions_data}'" ) === $wpdb->slim_seo_link_suggestions_data ) {
			return true;
		}

		return false;
	}
}
