<?php
namespace SlimSEOPro\LinkManager\Scanner;

use Throwable;
use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;

class TermsScanner extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'terms_scanner';
	private $post_scanner;
	private $link_scanner;

	public function __construct( PostsScanner $post_scanner, LinksScanner $link_scanner ) {
		parent::__construct();

		$this->post_scanner = $post_scanner;
		$this->link_scanner = $link_scanner;

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $term_id ): bool {
		$keep_item_in_queue  = false;
		$total_scanned_terms = Status::get_total_scanned( 'terms' );

		Status::update_total_scanned( 'terms', $total_scanned_terms + 1 );

		try {
			$term = get_term( $term_id );

			if ( empty( $term->description ) ) {
				return $keep_item_in_queue;
			}

			$links = Helper::get_links_from_text( $term->description, $term_id, "tax: {$term->taxonomy}", 'term_description' );
			$links = apply_filters( 'slim_seo_link_manager_get_all_links_from_term', $links, $term_id );

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
				// Translators: %s - The term ID.
				sprintf( __( 'Cannot parse links for term ID %s.', 'slim-seo-link-manager' ), $term_id ),
				$e
			);
		}

		return $keep_item_in_queue;
	}

	protected function complete() {
		parent::complete();

		if ( $this->post_scanner->start_scanner() ) {
			return;
		}

		$this->link_scanner->start_scanner();
	}

	public function start_scanner() {
		$terms = Helper::get_terms( [ 'fields' => 'ids' ] );

		if ( empty( $terms ) ) {
			return false;
		}

		update_option( Common::get_total_object_option_name( 'terms' ), count( $terms ) );

		Helper::purge_cache();

		foreach ( $terms as $term_id ) {
			$this->push_to_queue( $term_id );
		}

		$this->save()->dispatch();

		return true;
	}
}
