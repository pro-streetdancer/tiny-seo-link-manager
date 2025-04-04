<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Helper;

class Suggestion {
	private $controller;

	const KEYWORD_MIN_COUNT    = 5;
	const MIN_COMMON_WORDS     = 2;
	const MIN_MATCH_PERCENTAGE = 0.2;

	public function __construct( Controller $controller ) {
		$this->controller = $controller;
	}

	public function suggest_links( int $post_id, bool $same_taxonomies, int $limit = 20 ): array {
		$content        = Common::get_content( $post_id );
		$args           = Common::build_query_args( $post_id, $same_taxonomies );
		$args           = apply_filters( 'slim_seo_link_manager_link_suggestions_args', $args );
		$all_posts      = Helper::get_posts( $args );
		$target_objects = array_map( function ( $post ) {
			return [
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'url'           => get_permalink( $post->ID ),
				'editURL'       => htmlspecialchars_decode( get_edit_post_link( $post->ID ) ),
				'words'         => array_map( 'strtolower', $this->controller->sentence_to_words( $post->post_title ) ),
				'datePublished' => gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) ),
			];
		}, $all_posts );

		$suggestions = $this->suggest_links_from_text( $content, $target_objects );

		if ( $limit ) {
			$suggestions = array_slice( $suggestions, 0, $limit );
		}

		return $suggestions;
	}

	public function suggest_external_links( int $post_id, int $limit = 20 ): array {
		if ( ! Common::is_enable_interlink_external_sites() ) {
			return [];
		}

		$home_url = trailingslashit( home_url() );
		$data     = new Data();
		$objects  = $data->get_all_except( $home_url );

		if ( empty( $objects ) ) {
			return [];
		}

		$linked_sites   = new LinkedSites();
		$sites          = $linked_sites->get_all();
		$target_objects = [];

		foreach ( $objects as $object ) {
			if ( ! isset( $sites[ $object['site_url'] ] ) ) {
				continue;
			}

			$object['words'] = explode( ',', $object['words'] );

			$target_objects[] = $object;
		}

		if ( empty( $target_objects ) ) {
			return [];
		}

		$content     = Common::get_content( $post_id );
		$suggestions = $this->suggest_links_from_text( $content, $target_objects );

		if ( $limit ) {
			$suggestions = array_slice( $suggestions, 0, $limit );
		}

		return $suggestions;
	}

	public function suggest_keywords( int $post_id, int $limit = 20 ): array {
		$content = Common::get_content( $post_id );

		// To suggest keywords, we don't need HTML.
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );

		// Divide text to sentences. Don't use $this->get_sentence() as it's used for HTML content.
		$sentences = preg_split( '/((?<=[.?!])\s+|\n)/s', $content, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_values( array_filter( array_map( 'trim', $sentences ) ) );

		// Add post title as well.
		$sentences[] = get_post_field( 'post_title', $post_id );

		$words_list = array_reduce( $sentences, function ( $result, $sentence ) {
			$words               = $this->controller->parse_words( $sentence );
			$sentence_word_count = count( $words );
			$words_list          = [];

			for ( $i = 0; $i < $sentence_word_count; $i++ ) {
				// 1-word keyword.
				$words_list[] = [ $words[ $i ] ];

				// 2-words keyword.
				if ( $i < $sentence_word_count - 1 ) {
					$words_list[] = [
						$words[ $i ],
						$words[ $i + 1 ],
					];
				}

				// 3-words keyword.
				if ( $i < $sentence_word_count - 2 ) {
					$words_list[] = [
						$words[ $i ],
						$words[ $i + 1 ],
						$words[ $i + 2 ],
					];
				}
			}

			foreach ( $words_list as $key => $value ) {
				$initial_length = count( $value );
				$value          = $this->controller->normalize_words( $value );

				if ( $initial_length > count( $value ) ) {
					unset( $words_list[ $key ] );
				} else {
					$words_list[ $key ] = implode( ' ', $value );
				}
			}

			return array_merge( $result, $words_list );
		}, [] );

		// Get keywords that occurs more than the KEYWORD_MIN_COUNT.
		$words_list = array_count_values( $words_list );
		$words_list = array_filter( $words_list, function ( $value ) {
			return $value >= self::KEYWORD_MIN_COUNT;
		} );
		arsort( $words_list );

		$keywords = array_keys( $words_list );

		if ( $limit ) {
			$keywords = array_slice( $keywords, 0, $limit );
		}

		return $keywords;
	}

	private function suggest_links_from_text( string $text, array $target_objects ): array {
		$suggestions = [];
		$sentences   = $this->controller->get_sentences( $text );

		foreach ( $sentences as $sentence ) {
			if ( empty( $sentence ) ) {
				continue;
			}

			$suggestions_for_sentence = [];
			$words                    = array_map( 'strtolower', $this->controller->sentence_to_words( $sentence ) );
			$words_count              = count( $words );

			if ( ! $words_count ) {
				continue;
			}

			foreach ( $target_objects as $target_object ) {
				$intersect_words       = array_intersect( $target_object['words'], $words );
				$intersect_words_count = count( $intersect_words );
				$matched_percentage    = floatval( $intersect_words_count / $words_count );

				if ( $matched_percentage >= self::MIN_MATCH_PERCENTAGE && $intersect_words_count >= self::MIN_COMMON_WORDS ) {
					$suggestions_for_sentence[] = [
						'target_object'      => $target_object,
						'intersect_words'    => $intersect_words,
						'matched_percentage' => $matched_percentage,
					];
				}
			}

			if ( empty( $suggestions_for_sentence ) ) {
				continue;
			}

			// Sort by matched percentage.
			if ( count( $suggestions_for_sentence ) > 1 ) {
				usort( $suggestions_for_sentence, function ( $l1, $l2 ) {
					return $l1['matched_percentage'] <=> $l2['matched_percentage'];
				} );
			}

			$suggestions[] = array_merge( [
				'sentence' => $sentence,
			], reset( $suggestions_for_sentence ) );
		}

		return $suggestions;
	}
}
