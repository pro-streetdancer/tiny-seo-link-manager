<?php
namespace SlimSEOPro\LinkManager;

class Common {
	public function __construct() {
		add_filter( 'slim_seo_link_manager_allow_update_link_url', [ $this, 'allow' ], 20, 2 );
		add_filter( 'slim_seo_link_manager_allow_unlink', [ $this, 'allow' ], 20, 2 );
	}

	public function allow( bool $allow, array $link ): bool {
		if (
			'post_content' === $link['location']
			|| 'term_description' === $link['location']
		) {
			$allow = true;
		}

		return $allow;
	}
}
