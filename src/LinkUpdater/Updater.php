<?php
namespace SlimSEOPro\LinkManager\LinkUpdater;

class Updater extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_updater';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $link ): bool {
		$keep_item_in_queue = false;
		$old_permalink      = $link['old_permalink'];
		$new_permalink      = $link['new_permalink'];
		$current_site_id    = 1;
		$site_id            = 1;

		if ( is_multisite() ) {
			$current_site_id = get_current_blog_id();
			$site_id         = $link['site_id'] ?? $current_site_id;

			unset( $link['site_id'] );
		}

		unset( $link['old_permalink'] );
		unset( $link['new_permalink'] );

		if ( $current_site_id !== $site_id ) {
			switch_to_blog( $site_id );
		}

		$result = Common::update_link_url( $link, $old_permalink, $new_permalink, false );

		if ( $current_site_id !== $site_id ) {
			restore_current_blog();
		}

		return $keep_item_in_queue;
	}
}
