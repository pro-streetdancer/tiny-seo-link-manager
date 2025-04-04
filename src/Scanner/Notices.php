<?php
namespace SlimSEOPro\LinkManager\Scanner;

class Notices {
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'output_scanner_status' ] );
	}

	public function output_scanner_status() {
		if ( ! Status::is_running() ) {
			return;
		}

		$total_scanned_terms = Status::get_total_scanned( 'terms' );
		$total_terms         = get_option( Common::get_total_object_option_name( 'terms' ) );
		$total_scanned_posts = Status::get_total_scanned( 'posts' );
		$total_posts         = get_option( Common::get_total_object_option_name( 'posts' ) );
		$total_scanned_links = Status::get_total_scanned( 'links' );
		$total_links         = get_option( Common::get_total_object_option_name( 'links' ) );

		if (
			$total_scanned_terms > $total_terms
			|| $total_scanned_posts > $total_posts
			|| $total_scanned_links > $total_links
		) {
			Status::stop();

			return;
		}

		$message = '';

		if ( $total_terms ) {
			$message .= sprintf(
				// Translators: %1$d - scanned terms, %2$d - total terms.
				'<li>' . __( 'Scanned links in %1$d/%2$d terms', 'slim-seo-link-manager' ) . '</li>',
				$total_scanned_terms,
				$total_terms
			);
		}

		if ( $total_posts ) {
			$message .= sprintf(
				// Translators: %1$d - scanned posts, %2$d - total posts.
				'<li>' . __( 'Scanned links in %1$d/%2$d posts', 'slim-seo-link-manager' ) . '</li>',
				$total_scanned_posts,
				$total_posts
			);
		}

		if ( $total_scanned_links ) {
			$message .= sprintf(
				// Translators: %1$d - scanned links, %2$d - total links.
				'<li>' . __( 'Checked HTTP status of %1$d/%2$d links.', 'slim-seo-link-manager' ) . '</li>',
				$total_scanned_links,
				$total_links
			);
		}

		printf(
			'<div class="notice notice-success is-dismissible">
				<p>%s</p>
				<ul>%s</ul>
			</div>',
			esc_html__( 'Links scanner is still processing...', 'slim-seo-link-manager' ),
			wp_kses_post( $message )
		);
	}
}
