<?php
namespace SlimSEOPro\LinkManager\Export;

use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use SlimSEOPro\LinkManager\Helper;

class CSV {
	protected static function get_report_data( string $report, array $args = [] ): array {
		$data      = [];
		$tbl_links = new DbLinks;

		if ( ! empty( $args['mainReport'] ) ) {
			$links = $tbl_links->get_top( $report, 0, 0, $args['keyword'] ?? '', $args['orderBy'] ?? '', $args['order'] ?? '' );

			if ( empty( $links ) ) {
				return $data;
			}

			foreach ( $links as $link ) {
				$row = [];

				switch ( $report ) {
					case 'linked_pages':
					case 'orphan_pages':
						$link = Helper::get_link_detail( $link, 'target' );

						$row[] = esc_html( $link['target_name'] );
						$row[] = esc_url( 'orphan_pages' === $report ? get_permalink( $link['target_id'] ) : $link['url'] );

						break;

					case 'links_status':
						$row[] = $link['status'];

						break;

					case 'external_links':
						$row[] = esc_url( $link['url'] );

						break;

					case 'keywords':
						$row[] = 'image' === $link['anchor_type'] && '' === $link['anchor_text'] ? __( 'Image', 'slim-seo-link-manager' ) : esc_html( $link['anchor_text'] );

						break;
				}

				if ( 'orphan_pages' !== $report ) {
					$row[] = $link['amount'];
				}

				$data[] = $row;
			}

			return $data;
		}

		if ( 'linked_pages' === $report ) {
			$links = $tbl_links->get_links_by_object( $args['object_id'], $args['object_type'], 'target', 0, 0, $args['orderBy'] ?? '', $args['order'] ?? '' );

			if ( empty( $links ) ) {
				return $data;
			}

			foreach ( $links as $link ) {
				$link = Helper::get_link_detail( $link, 'source' );

				$data[] = [
					esc_html( $link['source_name'] ),
					esc_url( $link['source_url'] ),
					esc_html( $link['anchor_text'] ),
					empty( $link['nofollow'] ),
					$link['status'],
				];
			}

			return $data;
		}

		$links = $tbl_links->get_links_by_column_value( $args['column_name'], $args['value'], $args['anchor_type'] ?? '', 0, 0, $args['orderBy'] ?? '', $args['order'] ?? '' );

		if ( empty( $links ) ) {
			return $data;
		}

		foreach ( $links as $link ) {
			$link = Helper::get_link_detail( $link, 'all' );
			$row  = [ esc_html( $link['source_name'] ), esc_url( $link['source_url'] ) ];

			if ( in_array( $report, [ 'links_status', 'keywords' ], true ) ) {
				if ( 'external' === $link['type'] || empty( $link['target_name'] ) ) {
					$row[] = esc_url( $link['url'] );
					$row[] = esc_url( $link['url'] );
				} else {
					$row[] = esc_html( $link['target_name'] );
					$row[] = esc_url( $link['url'] );
				}
			}

			if ( 'keywords' !== $report ) {
				$row[] = $link['anchor_text'];
			}

			$row[] = empty( $link['nofollow'] );
			$row[] = $link['status'];

			$data[] = $row;
		}

		return $data;
	}

	public static function get_data( string $report, array $args = [] ) {
		$report_data = self::get_report_data( $report, $args );

		if ( empty( $report_data ) ) {
			return __( 'No data', 'slim-seo-link-manager' );
		}

		$header    = [];
		$file_name = "{$report}.csv";

		if ( ! empty( $args['mainReport'] ) ) {
			switch ( $report ) {
				case 'orphan_pages':
				case 'linked_pages':
					$header[] = esc_html( __( 'Page', 'slim-seo-link-manager' ) );
					$header[] = esc_html( __( 'URL', 'slim-seo-link-manager' ) );

					break;

				case 'links_status':
					$header[] = esc_html( __( 'Status', 'slim-seo-link-manager' ) );

					break;

				case 'external_links':
					$header[] = esc_html( __( 'URL', 'slim-seo-link-manager' ) );

					break;

				case 'keywords':
					$header[] = esc_html( __( 'Keyword', 'slim-seo-link-manager' ) );

					break;
			}

			if ( 'orphan_pages' !== $report ) {
				$header[] = esc_html( __( 'Amount', 'slim-seo-link-manager' ) );
			}
		} else {
			$header[] = esc_html( __( 'Source', 'slim-seo-link-manager' ) );
			$header[] = esc_html( __( 'Source URL', 'slim-seo-link-manager' ) );

			if ( 'linked_pages' === $report ) {
				$file_name = sanitize_title( Helper::get_link_title( [
					'source_id'   => $args['object_id'],
					'source_type' => $args['object_type'],
				], 'source' ) ) . '.csv';
			} else {
				$file_name = sanitize_title( $args['value'] ) . '.csv';

				if ( in_array( $report, [ 'links_status', 'keywords' ], true ) ) {
					$header[] = esc_html( __( 'Target', 'slim-seo-link-manager' ) );
					$header[] = esc_html( __( 'Target URL', 'slim-seo-link-manager' ) );
				}
			}

			if ( 'keywords' !== $report ) {
				$header[] = esc_html( __( 'Anchor text', 'slim-seo-link-manager' ) );
			}

			$header[] = esc_html( __( 'Follow', 'slim-seo-link-manager' ) );
			$header[] = esc_html( __( 'Status', 'slim-seo-link-manager' ) );

			if ( 'keywords' === $report && empty( $args['value'] ) ) {
				$file_name = 'keywords_none.csv';
			}
		}

		$report_data = array_merge( [ $header ], $report_data );

		return [
			'file_name' => $file_name,
			'data'      => $report_data,
		];
	}
}
