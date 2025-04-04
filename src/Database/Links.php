<?php
// phpcs:ignoreFile
namespace SlimSEOPro\LinkManager\Database;

use SlimSEOPro\LinkManager\Helper;

class Links {
	public function __construct() {
		global $wpdb;

		$wpdb->tables[]       = 'slim_seo_links';
		$wpdb->slim_seo_links = $wpdb->prefix . 'slim_seo_links';
	}

	public function get_total(): int {
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->slim_seo_links}" );
	}

	public function get_all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->slim_seo_links}", ARRAY_A );
	}

	public function get( int $link_id ): array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->slim_seo_links}
				WHERE `id` = %d",
				$link_id
			),
			ARRAY_A
		);

		return ! empty( $result ) ? $result : [];
	}

	public function get_total_links_by_object( int $object_id, string $object_type, string $type = 'source' ): int {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(*)
				FROM {$wpdb->slim_seo_links}
				WHERE " . ( 'source' === $type ? "`source_id` = %d AND `source_type` = '%s'" : "`target_id` = %d AND `target_type` = '%s'" ),
				$object_id,
				$object_type
			)
		);
	}

	public function get_links_by_object( int $object_id, string $object_type, string $type = 'source', int $limit = 0, int $offset = 0, $order_by = '', $order = 'DESC' ): array {
		global $wpdb;
 
		$saved_order_by = $order_by;
		$saved_limit    = $limit;

		if ( 'page' === $order_by ) {
			$order_by = 'target' === $type ? '`source_id`' : '`target_id`';
			$limit    = 0;
		}
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->slim_seo_links}
				WHERE " . ( 'source' === $type ? "`source_id` = %d AND `source_type` = '%s'" : "`target_id` = %d AND `target_type` = '%s'" )
				. ( $order_by ? " ORDER BY {$order_by} {$order}" : '' )
				. ( $limit ? " LIMIT {$limit} OFFSET {$offset}" : '' ),
				$object_id,
				$object_type
			),
			ARRAY_A
		);
		
		if ( 'page' === $saved_order_by ) {
			$results = $this->sort_results_by_page( $results, $type, $order );

			if ( $saved_limit ) {
				$results = array_slice( $results, $offset, $saved_limit );
			}
		}

		return $results;
	}

	public function get_total_top( string $get, string $search_keyword = '' ): int {
		global $wpdb;

		$group_by  = '';
		$condition = '';

		switch ( $get ) {
			case 'linked_pages':
				$group_by  = '`target_id`, `target_type`';
				$condition = "WHERE `target_id` <> '0'";

				break;

			case 'external_links':
				$group_by  = '`url`';
				$condition = "WHERE `type` = 'external'";

				break;

			case 'keywords':
				$group_by = '`anchor_text`, `anchor_type`';

				break;

			case 'links_status':
				$group_by = '`status`';

				break;

			case 'orphan_pages':
				return count( $this->get_orphan_pages( 0, 0, $search_keyword ) );
		}

		if ( $search_keyword ) {
			$search_condition = Helper::get_sql_condition_by_keyword( $get, $search_keyword );

			if ( $search_condition ) {
				$condition .= $search_condition;
			}
		}

		return $wpdb->get_var(
			"SELECT COUNT(*)
			FROM (
				SELECT COUNT(*)
				FROM {$wpdb->slim_seo_links}
				{$condition}
				GROUP BY {$group_by}
			) AS TopLinks"
		);
	}

	public function get_top( string $get, int $limit = 0, int $offset = 0, $search_keyword = '', $order_by = '', $order = '' ): array {
		global $wpdb;

		$fields_to_get   = '';
		$group_by        = '';
		$condition       = '';
		$order_by        = $order_by ? $order_by : '`amount`';
		$order           = $order ? $order : 'DESC';
		$second_order_by = '';
		$saved_order_by  = $order_by;
		$saved_limit     = $limit;
		
		switch ( $get ) {
			case 'linked_pages':
				if ( 'page' === $order_by ) {
					$order_by = '`target_id`';
					$limit    = 0;
				}

				$fields_to_get   = '`url`, `target_id`, `target_type`';
				$group_by        = '`target_id`, `target_type`';
				$condition       = "WHERE `target_id` <> '0'";
				$second_order_by = ', `target_id` ASC';

				break;

			case 'external_links':
				$fields_to_get   = '`url`';
				$group_by        = '`url`';
				$condition       = "WHERE `type` = 'external'";
				$second_order_by = ', `url` ASC';

				break;

			case 'keywords':
				$fields_to_get   = '`anchor_text`, `anchor_type`';
				$group_by        = '`anchor_text`, `anchor_type`';
				$second_order_by = ', `anchor_text` ASC';

				break;

			case 'links_status':
				$fields_to_get = '`status`';
				$group_by      = '`status`';

				break;

			case 'orphan_pages':
				return $this->get_orphan_pages( $limit, $offset, $search_keyword, $order_by, $order );
		}

		if ( $search_keyword ) {
			$condition .= Helper::get_sql_condition_by_keyword( $get, $search_keyword );
		}

		$sql_query = "
			SELECT COUNT(*) as amount, {$fields_to_get}
			FROM {$wpdb->slim_seo_links}
			{$condition}
			GROUP BY {$group_by}
			ORDER BY {$order_by} {$order} {$second_order_by}
		";

		if ( $limit ) {
			$sql_query .= " LIMIT {$limit} OFFSET {$offset}";
		}
		
		$results = $wpdb->get_results( $sql_query, ARRAY_A );

		if ( 'page' === $saved_order_by ) {
			$results = $this->sort_results_by_page( $results, 'source', $order );

			if ( $saved_limit ) {
				$results = array_slice( $results, $offset, $saved_limit );
			}
		}

		return $results;
	}

	public function get_total_links_by_column_value( string $column_name, string $value, string $anchor_type = '' ): int {
		global $wpdb;

		$sql_query = $wpdb->prepare(
			"SELECT count(*)
			FROM {$wpdb->slim_seo_links}
			WHERE {$column_name} = '%s'",
			$value
		);

		if ( $anchor_type ) {
			$sql_query .= " AND anchor_type = '{$anchor_type}'";
		}

		return $wpdb->get_var( $sql_query );
	}

	public function get_links_by_column_value( string $column_name, string $value, string $anchor_type = '', int $limit = 0, int $offset = 0, $order_by = '', $order = '' ): array {
		global $wpdb;

		$sql_query = $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->slim_seo_links}
			WHERE `$column_name` = %s",
			$value
		);

		if ( $anchor_type ) {
			$sql_query .= " AND anchor_type = '{$anchor_type}'";
		}

		$saved_order_by = $order_by;
		$saved_limit    = $limit;

		if ( $order_by ) {
			if ( 'page' === $order_by ) {
				$order_by = '`source_id`';
				$limit    = 0;
			} elseif ( 'target_page' === $order_by ) {				
				$order_by = '`target_id`';
				$limit    = 0;
			}

			$sql_query .= " ORDER BY {$order_by} {$order}";
		}

		if ( $limit ) {
			$sql_query .= " LIMIT {$limit} OFFSET {$offset}";
		}

		$results = $wpdb->get_results( $sql_query, ARRAY_A );

		if ( 'page' === $saved_order_by ) {
			$results = $this->sort_results_by_page( $results, 'target', $order );

			if ( $saved_limit ) {
				$results = array_slice( $results, $offset, $saved_limit );
			}
		} elseif ( 'target_page' === $saved_order_by ) {
			$results = $this->sort_results_by_page( $results, 'source', $order );

			if ( $saved_limit ) {
				$results = array_slice( $results, $offset, $saved_limit );
			}
		}

		return $results;
	}

	public function search_links_by_url( string $url, int $limit = 0, array $exclude = [] ): array {
		global $wpdb;
		
		$url_converted_special_characters = htmlspecialchars( $url );

		$exclude = $exclude ? ' AND `id` NOT IN (' . implode( ', ', $exclude ) . ')' : '';
		$limit   = $limit ? " LIMIT $limit" : '';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->slim_seo_links} WHERE ( `url` = %s OR `url` = %s ) $exclude $limit",
				$url,
				$url_converted_special_characters
			),
			ARRAY_A
		);
	}

	public function get_orphan_pages( int $limit = 0, int $offset = 0, $search_keyword = '', $order_by = '', $order = '' ): array {
		global $wpdb;

		$post_types = implode( ', ', array_map( function( $post_type ) {
			return "'" . $post_type . "'";
		}, Helper::get_post_types() ) );

		$order_sql = '';

		if ( $order_by ) {
			if ( 'page' === $order_by ) {
				$order_sql = " ORDER BY `post_title` {$order}";
			}
		}

		return $wpdb->get_results(
			"SELECT `ID` as 'target_id', `post_type` as 'target_type'
			FROM {$wpdb->posts}
			WHERE `post_status` = 'publish'
				AND `post_type` IN ({$post_types})
				AND `post_title` LIKE '%{$search_keyword}%'
				AND `ID` NOT IN (
					SELECT DISTINCT `target_id`
					FROM {$wpdb->slim_seo_links}
					WHERE `target_type` IN ({$post_types})
				)
			"
			. ( $order_sql ? $order_sql : '' )
			. ( $limit ? " LIMIT {$limit} OFFSET {$offset}" : '' ),
			ARRAY_A
		);
	}

	public function add( array $links ) {
		global $wpdb;

		foreach ( $links as $link ) {
			unset( $link['id'] );

			$wpdb->insert(
				$wpdb->slim_seo_links,
				$link
			);
		}
	}

	public function update( array $link ) {
		global $wpdb;

		$link['updated_at'] = gmdate( 'Y-m-d H:i:s', strtotime( 'now' ) );

		$wpdb->update(
			$wpdb->slim_seo_links,
			$link,
			[ 'id' => $link['id'] ]
		);
	}

	public function delete_all( int $object_id, string $object_type, string $type = 'source' ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->slim_seo_links,
			'source' === $type ? [
				'source_id'   => $object_id,
				'source_type' => $object_type,
			] : [
				'target_id'   => $object_id,
				'target_type' => $object_type,
			]
		);
	}

	public function delete( int $id ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->slim_seo_links,
			[
				'id' => $id,
			]
		);
	}

	public function truncate() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE $wpdb->slim_seo_links" );
	}

	protected function get_page_ordered_list( $order, $get = 'key' ) {
		global $wpdb;

		$post_types = Helper::get_post_types();

		if ( empty( $post_types ) ) {
			return [];
		}

		$list              = [];
		$post_types_output = implode( ', ', array_map( function( $post_type ) {
			return "'" . $post_type . "'";
		}, $post_types ) );

		$posts = $wpdb->get_results(
			"SELECT `ID`, `post_type`, `post_title`
			FROM $wpdb->posts
			WHERE `post_status` in ( 'publish', 'private', 'draft' )
				AND `post_type` in ( $post_types_output )"
		);

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$list[ $post->post_type . '-' . $post->ID ] = $post->post_title;
			}
		}

		$terms = $wpdb->get_results(
			"SELECT t.term_id as `term_id`, t.name as `name`, tt.taxonomy as `taxonomy`
			FROM $wpdb->terms AS t
			INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
			INNER JOIN $wpdb->term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN $wpdb->posts AS p ON p.ID = tr.object_id
			WHERE p.post_type in ( $post_types_output )"
		);
		
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$list[ 'tax: ' . $term->taxonomy . '-' . $term->term_id ] = $term->name;
			}
		}

		if ( empty( $list ) ) {
			return [];
		}

		uasort( $list, 'strcmp' );
		
		if ( 'DESC' === $order ) {
			$list = array_reverse( $list, true );
		}
		
		return 'key' === $get ? array_keys( $list ) : $list;
	}

	protected function sort_results_by_page( $results, $type = 'source', $order = 'DESC' ) {
		$page_ordered_list = $this->get_page_ordered_list( $order );

		if ( empty( $page_ordered_list ) ) {
			return $results;
		}

		usort( $results, function ( $result1, $result2 ) use ( $page_ordered_list, $type ) {
			$s1 = $s2 = 0;

			if ( 'target' === $type ) {
				$s1 = array_search( $result1['source_type'] . '-' . $result1['source_id'], $page_ordered_list );
				$s2 = array_search( $result2['source_type'] . '-' . $result2['source_id'], $page_ordered_list );
			} else {
				$s1 = array_search( $result1['target_type'] . '-' . $result1['target_id'], $page_ordered_list );
				$s2 = array_search( $result2['target_type'] . '-' . $result2['target_id'], $page_ordered_list );
			}

			return $s1 - $s2;
		});

		return $results;
	}
}
