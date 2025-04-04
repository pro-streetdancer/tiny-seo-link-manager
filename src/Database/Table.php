<?php
namespace SlimSEOPro\LinkManager\Database;

class Table {
	public function __construct() {
		global $wpdb;

		$wpdb->tables[]       = 'slim_seo_links';
		$wpdb->slim_seo_links = $wpdb->prefix . 'slim_seo_links';
	}

	public function create_ss_links_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql_query       = "
			CREATE TABLE IF NOT EXISTS {$wpdb->slim_seo_links} (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`source_id` bigint(20) unsigned DEFAULT NULL,
				`source_type` varchar(255) DEFAULT NULL,
				`target_id` bigint(20) unsigned DEFAULT NULL,
				`target_type` varchar(255) DEFAULT NULL,
				`url` varchar(255) NOT NULL,
				`type` enum('internal', 'external') NOT NULL DEFAULT 'internal',
				`anchor_text` varchar(255) DEFAULT NULL,
				`anchor_type` enum('text', 'image') DEFAULT 'text',
				`updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
				`location` varchar(255) DEFAULT 'post_content',
				`nofollow` tinyint(1) unsigned DEFAULT '0',
				`status` varchar(32) DEFAULT NULL,
				
				PRIMARY KEY (`id`),
				
				KEY `source_id` (`source_id`),
				KEY `target_id` (`target_id`),
				KEY `status` (`status`)
			) $charset_collate;
		";

		dbDelta( $sql_query );
	}
}
