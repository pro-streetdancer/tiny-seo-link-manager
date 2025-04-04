<?php
namespace SlimSEOPro\LinkManager;

use SlimSEO\Settings\Page;
use SlimSEO\Updater\Tab;
use eLightUp\PluginUpdater\Manager;
use SlimSEO\Updater\Settings as UpdaterSettings;

class Loader {
	public function init() {
		$this->load_third_party_libs();

		new Common;
		new CustomFields;

		if ( is_admin() ) {
			// Create custom table
			$this->create_table();

			$manager = $this->updater();

			// Setup settings page
			Page::setup();
			new Settings( $manager );

			new Shortcodes;
			new Misc;

			new Post( $manager );
			new Term;
		}

		// Integrations
		new Integrations\Bricks;
		new Integrations\Oxygen;
		new Integrations\Elementor;
		new Integrations\Divi;
		new Integrations\TablePress;
		new Integrations\Breakdance;
		new Integrations\TranslatePress;

		// Link suggestion.
		$suggestion_controller = new LinkSuggestions\Controller;
		$suggestion_controller->init();
		$suggestion_api = new LinkSuggestions\Api;
		$suggestion_api->set_controller( $suggestion_controller );
		new LinkSuggestions\GenerateData( $suggestion_controller );
		new LinkSuggestions\PublicApi;
		new LinkSuggestions\SendData;
		new LinkSuggestions\DeleteSite;
		new LinkSuggestions\UpdateLinkedSiteData;
		new LinkSuggestions\InternalSiteUpdateLink;

		// Load APIs
		new Api\Links;
		new Api\Settings( $suggestion_controller );

		// Scanner.
		$link_scanner = new Scanner\LinksScanner;
		$post_scanner = new Scanner\PostsScanner( $link_scanner );
		$term_scanner = new Scanner\TermsScanner( $post_scanner, $link_scanner );
		Scanner\Status::set_term_scanner( $term_scanner );
		Scanner\Status::set_post_scanner( $post_scanner );
		Scanner\Status::set_link_scanner( $link_scanner );
		new Scanner\Api( $term_scanner, $post_scanner );
		if ( is_admin() ) {
			new Scanner\Notices;
		}

		// Link Updater
		new LinkUpdater\Updater();
		new LinkUpdater\Api();

		// Export
		new Export\Api();

		// Link Status
		new LinkStatus\Api;
		new LinkStatus\BackgroundChecking;
	}

	private function create_table() {
		$option_name = 'sslm_db_version';
		$db_version  = get_option( $option_name );

		if ( $db_version < SLIM_SEO_LINK_MANAGER_VER ) {
			$table = new Database\Table();

			$table->create_ss_links_table();

			update_option( $option_name, SLIM_SEO_LINK_MANAGER_VER );
		}
	}

	private function updater() {
		Tab::setup();
		$manager_args      = apply_filters( 'slim_seo_link_manager_manager_args', [
			'api_url'            => 'https://wpslimseo.com/index.php',
			'my_account_url'     => 'https://wpslimseo.com/my-account/',
			'buy_url'            => 'https://wpslimseo.com/slim-seo-link-manager/',
			'slug'               => 'slim-seo-link-manager',
			'settings_page'      => admin_url( 'options-general.php?page=slim-seo#license' ),
			'settings_page_slug' => 'slim-seo',
		] );
		$manager           = new Manager( $manager_args );
		$settings          = new UpdaterSettings( $manager, $manager->checker, $manager->option );
		$manager->settings = $settings;
		$manager->setup();

		return $manager;
	}

	private function load_third_party_libs() {
		$third_party_dir              = SLIM_SEO_LINK_MANAGER_DIR . '/third-party';
		$wp_background_processing_dir = "$third_party_dir/deliciousbrains/wp-background-processing/classes";

		require_once "$wp_background_processing_dir/wp-async-request.php";
		require_once "$wp_background_processing_dir/wp-background-process.php";
	}
}
