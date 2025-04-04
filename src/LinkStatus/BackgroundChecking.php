<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;

class BackgroundChecking extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'check_link_status';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $link ): bool {
		$keep_item_in_queue = false;

		$link['status'] = Common::get_status_code( $link );

		if ( ! isset( $link['id'] ) ) {
			return $keep_item_in_queue;
		}

		$tbl_links = new DbLinks();
		$tbl_links->update( $link );

		return $keep_item_in_queue;
	}
}
