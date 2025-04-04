<?php
namespace SlimSEOPro\LinkManager;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;

class Term {
	public function __construct() {
		add_action( 'create_term', [ $this, 'create' ] );
		add_action( 'edit_term', [ $this, 'edit' ] );

		add_action( 'saved_term', [ $this, 'term_saved' ] );
	}

	public function create( $term_id ) {
		$this->save( $term_id );
	}

	public function edit( $term_id ) {
		// If it's not called from edit term page directly then return
		// phpcs:ignore
		if ( 'editedtag' !== ( $_POST['action'] ?? '' ) ) {
			return;
		}

		$this->save( $term_id );
	}

	public function save( $term_id ) {
		$term  = get_term( $term_id );
		$links = [];

		if ( ! empty( $term->description ) ) {
			$links = Helper::get_links_from_text( $term->description, $term_id, "tax: {$term->taxonomy}", 'term_description' );
		}

		$links = apply_filters( 'slim_seo_link_manager_get_all_links_from_term', $links, $term_id );

		if ( empty( $links ) ) {
			return;
		}

		foreach ( $links as $link_index => $link ) {
			$link = Helper::get_link_detail( $link, 'target' );

			unset( $link['target_name'] );

			$links[ $link_index ] = $link;
		}

		$tbl_links = new DbLinks();

		$tbl_links->delete_all( $term_id, "tax: {$term->taxonomy}" );

		$tbl_links->add( $links );
	}

	public function term_saved( $term_id ) {
		$term      = get_term( $term_id );
		$tbl_links = new DbLinks();
		$links     = $tbl_links->get_links_by_object( $term_id, "tax: {$term->taxonomy}", 'target' );

		if ( empty( $links ) ) {
			return;
		}

		$new_permalink = untrailingslashit( get_term_link( $term_id ) );

		LinkUpdater\Common::update_links( $links, $new_permalink );
	}
}
