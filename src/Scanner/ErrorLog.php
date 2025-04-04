<?php
namespace SlimSEOPro\LinkManager\Scanner;

use Throwable;
use SlimSEOPro\LinkManager\Helper;

class ErrorLog {
	const OPTION_NAME = 'slim_seo_link_manager_scan_log';

	public static function log( string $message, Throwable $e ): void {
		$log = "$message\n" . self::format_error( $e );

		$option   = get_option( self::OPTION_NAME ) ?: [];
		$option[] = $log;
		update_option( self::OPTION_NAME, $option );

		Helper::purge_cache();
	}

	private static function format_error( Throwable $e ): string {
		$output = sprintf(
			// Translators: %1$s - Current date, %2$s - Error message, %3$s - File, %4$s - Line.
			__( "- Date : %1\$s\n- Error: %2\$s\n- File : %3\$s\n- Line : %4\$s", 'slim-seo-link-manager' ),
			current_time( 'c' ),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine()
		);

		if ( defined( WP_DEBUG ) && WP_DEBUG ) {
			$output .= sprintf(
				// Translators: %s - Full trace of the error.
				__( "Trace:\n%s", 'slim-seo-link-manager' ),
				$e->getTraceAsString()
			);
		}

		return $output;
	}

	public static function clear(): bool {
		$result = delete_option( self::OPTION_NAME );

		Helper::purge_cache();

		return $result;
	}

	public static function get(): string {
		$option = get_option( self::OPTION_NAME ) ?: [];

		return esc_html( implode( "\n\n", $option ) );
	}
}
