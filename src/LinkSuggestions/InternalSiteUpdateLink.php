<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\LinkUpdater\Updater;

class InternalSiteUpdateLink extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_suggestions_internal_site_update_link';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $item ): bool {
		$keep_item_in_queue = false;

		$current_site_id = get_current_blog_id();
		$site_id         = $item['site_id'];

		if ( $current_site_id !== $site_id ) {
			switch_to_blog( $site_id );
		}

		$tbl_links = new DbLinks();
		$links     = $tbl_links->search_links_by_url( $item['old_url'] );

		if ( ! empty( $links ) ) {
			$link_updater = new Updater;

			foreach ( $links as $link ) {
				$link['old_permalink'] = $item['old_url'];
				$link['new_permalink'] = $item['new_url'];
				$link['site_id']       = $site_id;

				$link_updater->push_to_queue( $link );
			}

			$link_updater->save()->dispatch();
		}

		if ( $current_site_id !== $site_id ) {
			restore_current_blog();
		}

		return $keep_item_in_queue;
	}
}
