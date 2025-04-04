<?php
namespace SlimSEOPro\LinkManager;

use eLightUp\PluginUpdater\Manager;
use SlimSEOPro\LinkManager\Helpers\Data;

class Settings {
	private $manager;

	public function __construct( Manager $manager ) {
		$this->manager = $manager;

		add_filter( 'slim_seo_settings_tabs', [ $this, 'add_tab' ] );
		add_filter( 'slim_seo_settings_panes', [ $this, 'add_pane' ] );
		add_action( 'admin_print_styles-settings_page_slim-seo', [ $this, 'enqueue' ] );

		add_filter( 'slim_seo_upgradeable', '__return_false' );
	}

	public function add_tab( array $tabs ): array {
		$tabs['link-manager'] = __( 'Link Manager', 'slim-seo-link-manager' );

		return $tabs;
	}

	public function add_pane( array $panes ): array {
		$status = $this->manager->option->get_license_status();

		if ( $status === 'active' ) {
			Helper::purge_cache();

			$panes['link-manager'] = '<div id="link-manager" class="ss-tab-pane"><div id="sslm-dashboard"></div></div>';

			return $panes;
		}

		$messages = Helper::plugin_warning_messages();

		ob_start();
		?>
		<div id="link-manager" class="ss-tab-pane">
			<div class="ss-license-warning">
				<h2>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'License Warning', 'slim-seo-link-manager' ) ?>
				</h2>
				<?= wp_kses_post( sprintf( $messages[ $status ], $this->manager->settings_page, $this->manager->my_account_url ) ); ?>
			</div>
		</div>
		<?php
		$panes['link-manager'] = ob_get_clean();
		return $panes;
	}

	public function enqueue() {
		wp_enqueue_style( 'slim-seo-settings', 'https://cdn.jsdelivr.net/gh/elightup/slim-seo@master/css/settings.css', [], SLIM_SEO_LINK_MANAGER_VER );
		wp_enqueue_script( 'slim-seo-settings', 'https://cdn.jsdelivr.net/gh/elightup/slim-seo@master/js/settings.js', [], SLIM_SEO_LINK_MANAGER_VER, true );

		wp_enqueue_style( 'slim-seo-link-manager', SLIM_SEO_LINK_MANAGER_URL . 'css/link-manager.css', [ 'wp-components' ], filemtime( SLIM_SEO_LINK_MANAGER_DIR . '/css/link-manager.css' ) );

		if ( $this->manager->option->get_license_status() !== 'active' ) {
			return;
		}

		wp_enqueue_style( 'slim-seo-react-tabs', 'https://cdn.jsdelivr.net/gh/elightup/slim-seo@master/css/react-tabs.css', [], SLIM_SEO_LINK_MANAGER_VER );
		wp_enqueue_script( 'slim-seo-link-manager-dashboard', SLIM_SEO_LINK_MANAGER_URL . 'js/dashboard.js', [ 'wp-element', 'wp-components', 'wp-i18n' ], filemtime( SLIM_SEO_LINK_MANAGER_DIR . '/js/dashboard.js' ), true );

		$linked_sites = new LinkSuggestions\LinkedSites();

		$localized_data = [
			'rest'              => untrailingslashit( rest_url() ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'settingsPageURL'   => untrailingslashit( admin_url( 'options-general.php?page=slim-seo' ) ),
			'tabID'             => 'link-manager',
			'postTypes'         => $this->get_post_types(),
			'settings'          => (array) get_option( Api\Settings::OPTION_NAME ) ?: [],
			'linkedSites'       => $linked_sites->get_all( false ),
			'redirectCodesList' => Helper::redirect_codes_list(),
		];

		wp_localize_script( 'slim-seo-link-manager-dashboard', 'SSLinkManager', $localized_data );
	}

	private function get_post_types() {
		$post_types = Data::get_post_types();

		$options = [];
		foreach ( $post_types as $post_type ) {
			$options[] = [
				'value' => $post_type->name,
				'label' => sprintf( '%s (%s)', $post_type->labels->singular_name, $post_type->name ),
			];
		}
		return $options;
	}
}
