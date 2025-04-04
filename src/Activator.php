<?php
namespace SlimSEOPro\LinkManager;

class Activator {
	private $plugin = 'slim-seo-link-manager/slim-seo-link-manager.php';

	public function __construct() {
		add_filter( "plugin_action_links_{$this->plugin}", [ $this, 'add_plugin_action_links' ] );
		add_filter( 'plugin_row_meta', [ $this, 'add_plugin_meta_links' ], 10, 2 );

		add_action( 'activated_plugin', [ $this, 'redirect' ], 10, 2 );
	}

	public function add_plugin_action_links( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=slim-seo#link-manager' ) ) . '">' . __( 'Report', 'slim-seo-link-manager' ) . '</a>';
		return $links;
	}

	public function add_plugin_meta_links( array $meta, string $file ) {
		if ( $file !== $this->plugin ) {
			return $meta;
		}

		$meta[] = '<a href="https://docs.wpslimseo.com/slim-seo-link-manager/installation/" target="_blank">' . esc_html__( 'Documentation', 'slim-seo-link-manager' ) . '</a>';
		return $meta;
	}

	public function redirect( $plugin, $network_wide = false ) {
		$is_cli    = 'cli' === php_sapi_name();
		$is_plugin = $this->plugin === $plugin;

		$action           = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$checked          = isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) ? count( $_POST['checked'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$is_bulk_activate = $action === 'activate-selected' && $checked > 1;
		$is_doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ( ! $is_plugin || $network_wide || $is_cli || $is_bulk_activate || $is_doing_ajax ) {
			return;
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=slim-seo#link-manager' ) );
		die;
	}
}
