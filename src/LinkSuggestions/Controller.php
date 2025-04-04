<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use Exception;
use voku\helper\StopWords;

class Controller {
	private $stop_words = [];

	public function init() {
		if ( ! Common::is_enable_interlink_external_sites() ) {
			return;
		}

		add_action( 'post_updated', [ $this, 'post_updated' ], 20, 3 );
		add_action( 'permalink_structure_changed', [ $this, 'permalink_structure_changed' ] );
		add_filter( 'pre_update_option_home', [ $this, 'update_home_url' ], 20, 2 );
	}

	public function post_updated( $post_id, $post_after, $post_before ) {
		$home_url       = trailingslashit( home_url() );
		$data           = new Data();
		$linked_sites   = new LinkedSites();
		$external_sites = $linked_sites->get_all_external();
		$secret_key     = Common::get_secret_key();

		if ( 'publish' !== $post_after->post_status ) {
			$data->delete_object( $post_id, $post_after->post_type, $home_url );

			if ( ! empty( $external_sites ) ) {
				$send_data = new SendData();

				foreach ( $external_sites as $site => $site_data ) {
					$send_data->push_to_queue( [
						'secret_key' => $secret_key,
						'site'       => $site,
						'action'     => 'delete_data',
						'home_url'   => $home_url,
						'params'     => [
							'delete_all' => false,
							'item'       => [
								'object_id'   => $post_id,
								'object_type' => $post_after->post_type,
								'home_url'    => $home_url,
							],
						],
					] );
				}

				$send_data->save()->dispatch();
			}

			return;
		}

		$result = $data->get( $post_id, $post_after->post_type, $home_url );

		if ( empty( $result ) ) {
			$item = [
				'object_id'     => $post_id,
				'object_type'   => $post_after->post_type,
				'title'         => $post_after->post_title,
				'url'           => get_permalink( $post_id ),
				'words'         => implode( ',', array_map( 'strtolower', $this->sentence_to_words( $post_after->post_title ) ) ),
				'datePublished' => gmdate( 'Y-m-d H:i:s', strtotime( $post_after->post_date ) ),
				'site_url'      => $home_url,
			];

			$data->add( $item );

			if ( ! empty( $external_sites ) ) {
				$send_data = new SendData();

				foreach ( $external_sites as $site => $site_data ) {
					$send_data->push_to_queue( [
						'secret_key' => $secret_key,
						'site'       => $site,
						'action'     => 'add_data',
						'home_url'   => $home_url,
						'params'     => [
							'list' => [ $item ],
						],
					] );
				}

				$send_data->save()->dispatch();
			}

			return;
		}

		if (
			( $post_after->post_title ?? '' ) === ( $post_before->post_title ?? '' )
			&& ( $post_after->post_name ?? '' ) === ( $post_before->post_name ?? '' )
		) {
			return;
		}

		$old_url = $result['url'];
		$new_url = get_permalink( $post_id );

		$result['title'] = $post_after->post_title;
		$result['url']   = $new_url;
		$result['words'] = implode( ',', array_map( 'strtolower', $this->sentence_to_words( $post_after->post_title ) ) );

		$data->update( $result );

		if ( $old_url !== $new_url ) {
			$internal_sites = $linked_sites->get_all_internal();

			if ( ! empty( $internal_sites ) ) {
				$internal_site_update_link = new InternalSiteUpdateLink();

				foreach ( $internal_sites as $site => $site_data ) {
					$internal_site_update_link->push_to_queue( [
						'site_id' => $site_data['blog_id'],
						'old_url' => $old_url,
						'new_url' => $new_url,
					] );
				}

				$internal_site_update_link->save()->dispatch();
			}
		}

		if ( ! empty( $external_sites ) ) {
			$send_data = new SendData();

			unset( $result['id'] );

			foreach ( $external_sites as $site => $site_data ) {
				$send_data->push_to_queue( [
					'secret_key' => $secret_key,
					'site'       => $site,
					'action'     => 'update_data',
					'home_url'   => $home_url,
					'params'     => [
						'item' => $result,
					],
				] );
			}

			$send_data->save()->dispatch();
		}
	}

	public function permalink_structure_changed() {
		$link_suggestions_generate_data = new GenerateData( $this );
		$link_suggestions_generate_data->push_to_queue( [] );
		$link_suggestions_generate_data->save()->dispatch();
	}

	public function update_home_url( $value, $old_value ) {
		if ( $value !== $old_value ) {
			Common::delete_linked_sites();
		}

		return $value;
	}

	public function normalize_words( array $words ): array {
		// Remove special characters, numbers and convert word to lower case
		$words = array_map( function ( $word ) {
			return strtolower( preg_replace( '/[0-9.~()<>?;:`!@#$%^&*()\[\]{}_+=|\\-]/', '', $word ) );
		}, $words );

		// Remove stop words
		$this->get_stop_words();

		$words = array_filter( $words, function ( $word ) {
			return $word && ! in_array( $word, $this->stop_words, true );
		} );

		return $words;
	}

	public function get_stop_words(): void {
		if ( ! empty( $this->stop_words ) ) {
			return;
		}

		try {
			$locale           = get_locale();
			$stop_words       = new StopWords();
			$this->stop_words = $stop_words->getStopWordsFromLanguage( ! empty( $locale ) ? substr( $locale, 0, 2 ) : 'en' );
		} catch ( Exception $e ) {
			return;
		}
	}

	public function sentence_to_words( string $sentence ): array {
		$words = $this->parse_words( $sentence );
		$words = $this->normalize_words( $words );
		$words = array_unique( $words );

		return $words;
	}

	public function parse_words( string $sentence ): array {
		if ( str_contains( get_locale(), 'zh' ) ) {
			return $this->parse_chinese_words( $sentence );
		}

		$words = explode( ' ', $sentence );
		$words = array_values( array_filter( array_map( 'trim', $words ) ) );

		return $words;
	}

	private function parse_chinese_words( string $sentence ): array {
		$lib_dir = SLIM_SEO_LINK_MANAGER_DIR . '/third-party/nikslab/parse-chinese-text';
		require_once "$lib_dir/parseChinese.php";

		$words = parseChinese( $sentence, "$lib_dir/mandarin_words.txt" );

		return $words;
	}

	public function get_sentences( string $text ): array {
		// Strip all HTML excepts <a>.
		$text = trim( wp_kses( $text, [
			'a' => [
				'href' => [],
			],
		] ) );

		// Divide text to sentences
		$sentences = preg_split( '/((?<=[.?!])\s+|\n)/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_values( array_filter( array_map( 'trim', $sentences ) ) );

		foreach ( $sentences as $index => $sentence ) {
			// Remove sentence if it already has link.
			if ( preg_match( '/<a [^>]*?>/is', $sentence ) ) {
				unset( $sentences[ $index ] );
				continue;
			}

			$sentences[ $index ] = trim( wp_strip_all_tags( strip_shortcodes( $sentence ) ) );
		}

		return array_values( array_filter( $sentences ) );
	}
}
