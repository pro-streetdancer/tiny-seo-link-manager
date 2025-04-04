<?php
namespace SlimSEOPro\LinkManager;

use eLightUp\PluginUpdater\Manager;

class Post {
	private $manager;

	public function __construct( Manager $manager ) {
		$this->manager = $manager;

		add_action( 'admin_print_styles-post-new.php', [ $this, 'enqueue' ] );
		add_action( 'admin_print_styles-post.php', [ $this, 'enqueue' ] );

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save' ] );
		add_action( 'post_updated', [ $this, 'post_updated' ] );
	}

	public function enqueue() {
		global $post;

		$post_types = $this->get_post_types();

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		wp_enqueue_style( 'slim-seo-link-manager', SLIM_SEO_LINK_MANAGER_URL . 'css/link-manager.css', [ 'wp-components' ], filemtime( SLIM_SEO_LINK_MANAGER_DIR . '/css/link-manager.css' ) );

		if ( $this->manager->option->get_license_status() !== 'active' ) {
			return;
		}

		wp_enqueue_style( 'slim-seo-react-tabs', 'https://cdn.jsdelivr.net/gh/elightup/slim-seo@master/css/react-tabs.css', [], SLIM_SEO_LINK_MANAGER_VER );
		wp_enqueue_style( 'slim-seo-settings', 'https://cdn.jsdelivr.net/gh/elightup/slim-seo@master/css/settings.css', [], SLIM_SEO_LINK_MANAGER_VER );
		wp_enqueue_script( 'slim-seo-link-manager', SLIM_SEO_LINK_MANAGER_URL . 'js/post.js', [ 'wp-element', 'wp-components', 'wp-i18n', 'jquery' ], filemtime( SLIM_SEO_LINK_MANAGER_DIR . '/js/post.js' ), true );

		$localized_data = [
			'rest'                    => untrailingslashit( rest_url() ),
			'nonce'                   => wp_create_nonce( 'wp_rest' ),
			'postID'                  => $post->ID,
			'postType'                => $post->post_type,
			'postURL'                 => get_permalink( $post->ID ),
			'postStatus'              => get_post_status( $post->ID ),
			'showExternalSuggestions' => LinkSuggestions\Common::is_enable_interlink_external_sites(),
		];

		wp_localize_script( 'slim-seo-link-manager', 'SSLinkManager', $localized_data );
	}

	public function add_meta_box() {
		$context    = apply_filters( 'slim_seo_meta_box_context', 'normal' );
		$priority   = apply_filters( 'slim_seo_meta_box_priority', 'high' );
		$post_types = $this->get_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box( 'link-manager', __( 'Link Manager', 'slim-seo-link-manager' ), [ $this, 'render' ], $post_type, $context, $priority );
		}
	}

	private function get_post_types(): array {
		$post_types = apply_filters( 'slim_seo_meta_box_post_types', Helper::get_post_types() );

		return $post_types;
	}

	public function save( $post_id ) {
		if ( ! check_ajax_referer( 'save-link-manager', 'sslm_nonce', false ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sslm      = $_POST['sslm'] ?? [];
		$source_id = wp_is_post_revision( $post_id ) ?: $post_id;

		// Convert outbound links
		$outbound_links = array_map( function ( $outbound_link ) {
			return json_decode( stripslashes( $outbound_link ), true );
		}, $sslm['outbound_links'] ?? [] );
		$outbound_links = apply_filters( 'slim_seo_link_manager_outbound_links', $outbound_links, $source_id );

		// Update outbound links in database
		$tbl_links = new Database\Links();
		$tbl_links->delete_all( $source_id, get_post_type( $source_id ) );
		$tbl_links->add( $outbound_links );
	}

	public function post_updated( $post_id ) {
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$tbl_links = new Database\Links();
		$links     = $tbl_links->get_links_by_object( $post_id, get_post_type( $post_id ), 'target' );

		if ( empty( $links ) ) {
			return;
		}

		$new_permalink = untrailingslashit( get_permalink( $post_id ) );

		LinkUpdater\Common::update_links( $links, $new_permalink );
	}

	public function render() {
		$status = $this->manager->option->get_license_status();

		if ( $status === 'active' ) {
			wp_nonce_field( 'save-link-manager', 'sslm_nonce' );
			echo '<div id="sslm-post"></div>';
			return;
		}

		$messages = Helper::plugin_warning_messages();
		?>
		<div class="ss-license-warning">
			<h2>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'License Warning', 'slim-seo-link-manager' ) ?>
			</h2>
			<?= wp_kses_post( sprintf( $messages[ $status ], admin_url( 'options-general.php?page=slim-seo#license' ), 'https://elu.to/sua' ) ); ?>
		</div>
		<?php
	}
}
