<?php
namespace SlimSEOPro\LinkManager\Scanner;

use SlimSEOPro\LinkManager\Helper;

class Status {
	private static $term_scanner;
	private static $post_scanner;
	private static $link_scanner;

	public static function set_term_scanner( TermsScanner $term_scanner ) {
		self::$term_scanner = $term_scanner;
	}

	public static function set_post_scanner( PostsScanner $post_scanner ) {
		self::$post_scanner = $post_scanner;
	}

	public static function set_link_scanner( LinksScanner $link_scanner ) {
		self::$link_scanner = $link_scanner;
	}

	public static function start() {
		update_option( SLIM_SEO_LINK_MANAGER_IS_SCANNER_RUNNING, true );

		Helper::purge_cache();
	}

	public static function stop(): bool {
		self::$term_scanner->cancel();
		self::$post_scanner->cancel();
		self::$link_scanner->cancel();

		$result = delete_option( SLIM_SEO_LINK_MANAGER_IS_SCANNER_RUNNING );

		Helper::purge_cache();

		return $result;
	}

	public static function is_running(): bool {
		return ! empty( get_option( SLIM_SEO_LINK_MANAGER_IS_SCANNER_RUNNING ) );
	}

	public static function get_total_scanned( string $name ): int {
		return intval( get_option( Common::get_total_scanned_option_name( $name ) ) );
	}

	public static function update_total_scanned( string $name, int $value ) {
		update_option( Common::get_total_scanned_option_name( $name ), $value );

		Helper::purge_cache();
	}
}
