<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

class UpdateLinkedSiteData extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_suggestions_update_linked_site_data';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $item ): bool {
		$keep_item_in_queue = false;

		$response = Common::remote_post( [
			'secret_key' => $item['secret_key'],
			'site'       => $item['site'],
			'action'     => 'delete_data',
			'home_url'   => $item['home_url'],
			'params'     => [
				'delete_all' => true,
			],
		] );

		if ( empty( $response['message'] ) ) {
			Common::add_data_to_external_site( [
				'site'       => $item['site'],
				'secret_key' => $item['secret_key'],
				'home_url'   => $item['home_url'],
			] );
		}

		return $keep_item_in_queue;
	}
}
