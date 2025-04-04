<?php
/**
 * Plugin Name: Tiny SEO Link Manager
 * Plugin URI:  https://wpslimseo.com
 * Description: A link manager plugin for WordPress.
 * Author:      eLightUp
 * Author URI:  https://elightup.com
 * Version:     1.10.8
 * Text Domain: slim-seo-link-manager
 * Domain Path: /languages/
 */

namespace SlimSEOPro\LinkManager;

defined( 'ABSPATH' ) || die;

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_DIR' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_DIR', __DIR__ );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_URL' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_VER' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_VER', '1.10.8' );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_IS_SCANNER_RUNNING' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_IS_SCANNER_RUNNING', 'slim_seo_link_manager_is_scanner_running' );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_DEFAULT_STATUS_CODE', 'N/A' );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_ERROR_STATUS_CODE' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_ERROR_STATUS_CODE', 'ERROR' );
}

if ( ! defined( 'SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME' ) ) {
	define( 'SLIM_SEO_LINK_MANAGER_LINKS_CACHE_NAME', 'sslm_links_cache' );
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', function () {
	$slim_seo_link_manager_loader = new Loader();
	$slim_seo_link_manager_loader->init();

	new Activator;
	new Deactivator( __FILE__ );
} );
