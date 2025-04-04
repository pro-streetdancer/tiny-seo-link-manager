<?php
namespace SlimSEOPro\LinkManager\LinkSuggestions;

class DeleteSite extends \SlimSEO_LinkManager_WP_Background_Process {
	protected $action = 'link_suggestions_delete_site';

	public function __construct() {
		parent::__construct();

		add_filter( "{$this->identifier}_post_args", [ 'SlimSEOPro\LinkManager\Helper', 'background_processing_dispatch_post_args' ] );
	}

	protected function task( $item ): bool {
		$keep_item_in_queue = false;

		$data = new Data();

		if ( $data->table_exist() ) {
			$data->delete( $item['site'] );
		}

		Common::remote_post( [
			'secret_key' => $item['secret_key'],
			'site'       => $item['site'],
			'action'     => 'delete_site',
			'home_url'   => $item['home_url'],
		] );

		return $keep_item_in_queue;
	}
}
