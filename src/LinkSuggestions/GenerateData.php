<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

use SlimSEOPro\LinkManager\Helper;

class GenerateData extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_suggestions_data';
	private $controller;

	public function __construct( Controller $controller ) {
		parent::__construct();

		$this->controller = $controller;

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $item ): bool {
		$keep_item_in_queue = false;

		$home_url = trailingslashit( home_url() );
		$posts    = Helper::get_posts();
		$data     = new Data();

		$data->delete( $home_url );

		foreach ( $posts as $post ) {
			$data->add( [
				'object_id'     => $post->ID,
				'object_type'   => $post->post_type,
				'title'         => $post->post_title,
				'url'           => get_permalink( $post->ID ),
				'words'         => implode( ',', array_map( 'strtolower', $this->controller->sentence_to_words( $post->post_title ) ) ),
				'datePublished' => gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) ),
				'site_url'      => $home_url,
			] );
		}

		$linked_sites   = new LinkedSites();
		$external_sites = $linked_sites->get_all_external();

		if ( empty( $external_sites ) ) {
			return $keep_item_in_queue;
		}

		$secret_key = Common::get_secret_key();

		foreach ( $external_sites as $site => $site_data ) {
			$update_linked_site_data = new UpdateLinkedSiteData();
			$update_linked_site_data->push_to_queue( [
				'secret_key' => $secret_key,
				'site'       => $site,
				'home_url'   => $home_url,
			] );
		}

		$update_linked_site_data->save()->dispatch();

		return $keep_item_in_queue;
	}
}
