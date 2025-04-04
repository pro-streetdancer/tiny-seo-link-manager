<?php
namespace SlimSEOPro\LinkManager\Api;

abstract class Base {
	const NAMESPACE = 'slim-seo-link-manager';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes();

	public function has_permission(): bool {
		return current_user_can( 'edit_posts' );
	}
}
