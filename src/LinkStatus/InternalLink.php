<?php
namespace SlimSEOPro\LinkManager\LinkStatus;

class InternalLink {
	public static function get_status_code( array $link ) {
		// If the link does not have target_id/target_type > It's not a specific post/term > Check its http status likes external link
		if ( empty( $link['target_id'] ) || empty( $link['target_type'] ) ) {
			return ExternalLink::get_status_code( $link );
		}

		if ( false !== stripos( $link['target_type'], 'tax:' ) ) {
			$term = get_term( $link['target_id'] );

			// If term is not existing > Check its http status likes external link
			return empty( $term->term_id ) ? ExternalLink::get_status_code( $link ) : 200;
		}

		$post = get_post( $link['target_id'] );

		// If post is not existing > Check its http status likes external link
		return empty( $post->ID ) ? ExternalLink::get_status_code( $link ) : 200;
	}
}
