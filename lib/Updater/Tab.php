<?php
namespace SlimSEO\Updater;

class Tab {
	public static function setup() {
		add_filter( 'slim_seo_settings_tabs', [ __CLASS__, 'add_tab' ], 99 );
		add_filter( 'slim_seo_settings_panes', [ __CLASS__, 'add_pane' ] );
	}

	public static function add_tab( $tabs ) {
		$tabs['license'] = __( 'License', 'slim-seo' );
		return $tabs;
	}

	public static function add_pane( $panes ) {
		ob_start();
		?>
		<div id="license" class="ss-tab-pane">
			<p>
				<?php
				printf(
					// Translators: %s - Link to My Account page.
					wp_kses_post( __( 'Please enter your <a href="%s" target="_blank">license key</a> to enable automatic updates for Slim SEO plugins.', 'slim-seo' ) ),
					'https://elu.to/sua'
				);
				?>
			</p>
			<?php do_action( 'slim_seo_settings_license' ) ?>
			<?php submit_button( __( 'Save Changes', 'slim-seo' ) ); ?>
		</div>
		<?php
		$panes['license'] = ob_get_clean();
		return $panes;
	}
}
