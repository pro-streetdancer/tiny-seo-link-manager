<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

class SendData extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_suggestions_send_data';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $item ): bool {
		$keep_item_in_queue = false;

		$response = Common::remote_post( [
			'secret_key' => $item['secret_key'],
			'site'       => $item['site'],
			'action'     => $item['action'],
			'home_url'   => $item['home_url'],
			'params'     => $item['params'],
		] );

		return $keep_item_in_queue;
	}
}
