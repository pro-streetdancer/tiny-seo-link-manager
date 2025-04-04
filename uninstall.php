<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || die;

$sslm_option = get_option( 'slim_seo_link_manager_settings' );
if ( empty( $sslm_option['delete_data'] ) ) {
	return;
}

delete_option( 'sslm_links_cache' );
delete_option( 'sslm_db_version' );
delete_option( 'slim_seo_link_manager_total_terms' );
delete_option( 'slim_seo_link_manager_total_posts' );
delete_option( 'slim_seo_link_manager_total_links' );
delete_option( 'slim_seo_link_manager_is_scanner_running' );
delete_option( 'slim_seo_link_manager_total_scanned_terms' );
delete_option( 'slim_seo_link_manager_total_scanned_posts' );
delete_option( 'slim_seo_link_manager_total_scanned_links' );
delete_option( 'slim_seo_link_manager_scan_log' );
delete_option( 'slim_seo_link_manager_settings' );
delete_option( 'slim-seo-link-manager_license' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}slim_seo_links" );
