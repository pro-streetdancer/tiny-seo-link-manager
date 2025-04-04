<?php
namespace SlimSEOPro\LinkManager;

class IgnoreLinks {
	public static function is_ignored( string $url ): bool {
		if ( '_wp_link_placeholder' === $url ) {
			return true;
		}

		if ( str_starts_with( $url, '#' ) ) {
			return true;
		}

		if ( self::ignore_by_scheme( $url ) ) {
			return true;
		}

		if ( self::ignore_by_prefix( $url ) ) {
			return true;
		}

		if ( self::ignore_by_keyword( $url ) ) {
			return true;
		}

		return ! apply_filters( 'slim_seo_link_manager_process_url', true, $url );
	}

	/**
	 * Ignore a URL by schemes.
	 * List of schemes are available at https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml
	 * Use only permanent schemes, and don't count "http" and "https".
	 */
	private static function ignore_by_scheme( string $url ): bool {
		$schemes = [
			'aaa',
			'aaas',
			'about',
			'acap',
			'acct',
			'cap',
			'cid',
			'coap',
			'coap+tcp',
			'coap+ws',
			'coaps',
			'coaps+tcp',
			'coaps+ws',
			'crid',
			'data',
			'dav',
			'dict',
			'dns',
			'dtn',
			'example',
			'file',
			'ftp',
			'geo',
			'go',
			'gopher',
			'h323',
			'iax',
			'icap',
			'im',
			'imap',
			'info',
			'ipn',
			'ipp',
			'ipps',
			'iris',
			'iris.beep',
			'iris.lwz',
			'iris.xpc',
			'iris.xpcs',
			'jabber',
			'ldap',
			'leaptofrogans',
			'mailto',
			'mid',
			'msrp',
			'msrps',
			'mt',
			'mtqp',
			'mupdate',
			'news',
			'nfs',
			'ni',
			'nih',
			'nntp',
			'opaquelocktoken',
			'pkcs11',
			'pop',
			'pres',
			'reload',
			'rtsp',
			'rtsps',
			'rtspu',
			'service',
			'session',
			'shttp (OBSOLETE)',
			'sieve',
			'sip',
			'sips',
			'sms',
			'snmp',
			'soap.beep',
			'soap.beeps',
			'stun',
			'stuns',
			'tag',
			'tel',
			'telnet',
			'tftp',
			'thismessage',
			'tip',
			'tn3270',
			'turn',
			'turns',
			'tv',
			'urn',
			'vemmi',
			'vnc',
			'ws',
			'wss',
			'xcon',
			'xcon-userid',
			'xmlrpc.beep',
			'xmlrpc.beeps',
			'xmpp',
			'z39.50r',
			'z39.50s',
		];

		foreach ( $schemes as $scheme ) {
			if ( str_starts_with( $url, "$scheme:" ) ) {
				return true;
			}
		}

		return false;
	}

	private static function ignore_by_prefix( string $url ): bool {
		if ( Url::is_external( $url ) ) {
			return false;
		}

		$url = Url::normalize( $url, true, true, false );

		$option   = get_option( Api\Settings::OPTION_NAME ) ?: [];
		$prefixes = $option['ignore_link_prefixes'] ?? '';
		$prefixes = array_filter( array_map( 'trim', explode( "\n", $prefixes ) ) );
		if ( empty( $prefixes ) ) {
			return false;
		}

		foreach ( $prefixes as $prefix ) {
			$prefix = Url::normalize( $prefix, true, true, false );
			if ( str_starts_with( $url, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function ignore_by_keyword( string $url ): bool {
		$option   = get_option( Api\Settings::OPTION_NAME ) ?: [];
		$keywords = $option['ignore_link_keywords'] ?? '';
		$keywords = array_filter( array_map( 'trim', explode( "\n", $keywords ) ) );
		if ( empty( $keywords ) ) {
			return false;
		}

		foreach ( $keywords as $keyword ) {
			if ( str_contains( $url, $keyword ) ) {
				return true;
			}
		}

		return false;
	}
}
