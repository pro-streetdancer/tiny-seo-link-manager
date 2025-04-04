<?php
namespace SlimSEOPro\LinkManager\Scanner;

use Throwable;
use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\LinkStatus\Common as LinkStatusCommon;

class LinksScanner extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'links_scanner';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $link_id ): bool {
		$keep_item_in_queue = false;

		try {
			$tbl_links = new DbLinks();
			$link      = $tbl_links->get( $link_id );

			if ( empty( $link ) ) {
				return $keep_item_in_queue;
			}

			$link['status'] = LinkStatusCommon::get_status_code( $link );

			$total_scanned_links = Status::get_total_scanned( 'links' );

			Status::update_total_scanned( 'links', $total_scanned_links + 1 );

			$tbl_links->update( $link );
		} catch ( Throwable $e ) {
			ErrorLog::log(
				// Translators: %s - The link ID.
				sprintf( __( 'Cannot get HTTP status code for link ID %s.', 'slim-seo-link-manager' ), $link_id ),
				$e
			);
		}

		return $keep_item_in_queue;
	}

	protected function complete() {
		parent::complete();

		Status::stop();
	}

	public function start_scanner() {
		$tbl_links = new DbLinks();
		$links     = $tbl_links->get_all();

		if ( empty( $links ) ) {
			Status::stop();
			return;
		}

		update_option( Common::get_total_object_option_name( 'links' ), count( $links ) );

		Helper::purge_cache();

		foreach ( $links as $link ) {
			$this->push_to_queue( $link['id'] );
		}

		$this->save()->dispatch();
	}
}
