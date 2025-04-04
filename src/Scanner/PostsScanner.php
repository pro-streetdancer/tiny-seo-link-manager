<?php
namespace SlimSEOPro\LinkManager\Scanner;

use Throwable;
use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;

class PostsScanner extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'posts_scanner';
	private $link_scanner;

	public function __construct( LinksScanner $link_scanner ) {
		parent::__construct();

		$this->link_scanner = $link_scanner;

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $post_id ): bool {
		$keep_item_in_queue  = false;
		$total_scanned_posts = Status::get_total_scanned( 'posts' );

		Status::update_total_scanned( 'posts', $total_scanned_posts + 1 );

		try {
			$post_type = get_post_type( $post_id );
			$links     = Helper::get_links_from_text( get_post_field( 'post_content', $post_id ), $post_id, $post_type, 'post_content' );
			$links     = apply_filters( 'slim_seo_link_manager_get_all_links_from_post', $links, $post_id );

			if ( empty( $links ) ) {
				return $keep_item_in_queue;
			}

			foreach ( $links as $link_index => $link ) {
				$link = Helper::get_link_detail( $link, 'target' );

				unset( $link['target_name'] );

				/**
				 * When re-checking status of an external link
				 * it will check the url in Cache first (see LinkStatus\Cache::get)
				 * if updated_at was in the last 24 hours, it will use the status in Cache instead of trying a new request
				 * so we should set updated_at to yesterday to ignore Cache
				 */
				$link['updated_at'] = gmdate( 'Y-m-d H:i:s', strtotime( 'now' ) - DAY_IN_SECONDS );

				$links[ $link_index ] = $link;
			}

			$tbl_links = new DbLinks();

			$tbl_links->add( $links );
		} catch ( Throwable $e ) {
			ErrorLog::log(
				// Translators: %s - The post ID.
				sprintf( __( 'Cannot parse links for post ID %s.', 'slim-seo-link-manager' ), $post_id ),
				$e
			);
		}

		return $keep_item_in_queue;
	}

	protected function complete() {
		parent::complete();

		$this->link_scanner->start_scanner();
	}

	public function start_scanner() {
		$posts = Helper::get_posts( [ 'fields' => 'ids' ] );

		if ( empty( $posts ) ) {
			return false;
		}

		update_option( Common::get_total_object_option_name( 'posts' ), count( $posts ) );

		Helper::purge_cache();

		foreach ( $posts as $post_id ) {
			$this->push_to_queue( $post_id );
		}

		$this->save()->dispatch();

		return true;
	}
}
