<?php
namespace SlimSEOPro\LinkManager\Scanner;

class Common {
	public static function get_total_object_option_name( string $name = 'links' ): string {
		$names = [
			'terms' => 'slim_seo_link_manager_total_terms',
			'posts' => 'slim_seo_link_manager_total_posts',
			'links' => 'slim_seo_link_manager_total_links',
		];

		return $names[ $name ] ?? $names['links'];
	}

	public static function get_total_scanned_option_name( string $name = 'links' ): string {
		$names = [
			'terms' => 'slim_seo_link_manager_total_scanned_terms',
			'posts' => 'slim_seo_link_manager_total_scanned_posts',
			'links' => 'slim_seo_link_manager_total_scanned_links',
		];

		return $names[ $name ] ?? $names['links'];
	}
}
