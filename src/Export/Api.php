<?php
namespace SlimSEOPro\LinkManager\Export;

use SlimSEOPro\LinkManager\Api\Base;
use WP_REST_Server;
use WP_REST_Request;

class Api extends Base {
	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'export/csv', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'csv' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function csv( WP_REST_Request $request ) {
		$report = $request->get_param( 'report' );
		$args   = $request->get_param( 'args' ) ?? [];
		$result = CSV::get_data( $report, $args );

		return $result;
	}
}
