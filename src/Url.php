<?php
namespace SlimSEOPro\LinkManager;

class Url {
	public static function is_absolute( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL );
	}

	public static function is_relative( string $url ): bool {
		return ! self::is_absolute( $url );
	}

	public static function is_internal( string $url ): bool {
		if ( self::is_relative( $url ) ) {
			return true;
		}

		$home_url = untrailingslashit( home_url() );
		return str_starts_with( $url, $home_url );
	}

	public static function is_external( string $url ): bool {
		return ! self::is_internal( $url );
	}

	public static function to_relative( string $url ): string {
		if ( self::is_relative( $url ) || self::is_external( $url ) ) {
			return $url;
		}

		$home_url = untrailingslashit( home_url() );
		return substr( $url, strlen( $home_url ) );
	}

	public static function normalize( string $url, $unslash = true, $ltrim = true, $rtrim = true ): string {
		$url = $unslash ? wp_unslash( $url ) : $url;
		$url = sanitize_text_field( $url );
		$url = html_entity_decode( $url );
		$url = self::to_relative( $url );
		$url = $ltrim ? ltrim( $url, '/' ) : $url;
		$url = $rtrim ? rtrim( $url, '/' ) : $url;

		return $url ? $url : '/';
	}
}
