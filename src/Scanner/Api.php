<?php
namespace SlimSEOPro\LinkManager\Scanner;

use SlimSEOPro\LinkManager\Api\Base;
use SlimSEOPro\LinkManager\Helper;
use SlimSEOPro\LinkManager\Database\Links as DbLinks;
use WP_REST_Server;

class Api extends Base {
	private $term_scanner;
	private $post_scanner;

	public function __construct( TermsScanner $term_scanner, PostsScanner $post_scanner ) {
		parent::__construct();

		$this->term_scanner = $term_scanner;
		$this->post_scanner = $post_scanner;
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, 'scanner/status', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __NAMESPACE__ . '\\Status', 'is_running' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'scanner/start', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'start' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'scanner/stop', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __NAMESPACE__ . '\\Status', 'stop' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'scanner/log', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __NAMESPACE__ . '\\ErrorLog', 'get' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );

		register_rest_route( self::NAMESPACE, 'scanner/clear-log', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __NAMESPACE__ . '\\ErrorLog', 'clear' ],
			'permission_callback' => [ $this, 'has_permission' ],
		] );
	}

	public function start(): bool {
		Status::update_total_scanned( 'terms', 0 );
		Status::update_total_scanned( 'posts', 0 );
		Status::update_total_scanned( 'links', 0 );

		update_option( Common::get_total_object_option_name( 'terms' ), 0 );
		update_option( Common::get_total_object_option_name( 'posts' ), 0 );
		update_option( Common::get_total_object_option_name( 'links' ), 0 );

		Status::start();
		ErrorLog::clear();

		$tbl_links = new DbLinks();
		$tbl_links->truncate();

		Helper::purge_cache();

		if ( $this->term_scanner->start_scanner() ) {
			return true;
		}

		if ( $this->post_scanner->start_scanner() ) {
			return true;
		}

		Status::stop();

		return false;
	}
}
