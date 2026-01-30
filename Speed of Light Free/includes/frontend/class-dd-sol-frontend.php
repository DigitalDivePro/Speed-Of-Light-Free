<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_SOL_Frontend {
	private $plugin;

	public function __construct( DD_SpeedOfLight_FPC $plugin ) {
		$this->plugin = $plugin;

		add_action( 'template_redirect', array( $this, 'add_custom_header' ) );
	}

	public function add_custom_header() {
		if ( is_admin() || $this->plugin->is_api_request() || is_feed() ) {
			return;
		}

		if ( headers_sent() ) {
			return;
		}

		$method = strtoupper( sanitize_text_field( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			header( 'x-speed-of-light: bypass' );
			header( 'Cache-Control: private, no-store, no-cache, max-age=0' );
			return;
		}

		if ( is_user_logged_in() || post_password_required() || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
			header( 'x-speed-of-light: bypass' );
			header( 'Cache-Control: private, no-store, no-cache, max-age=0' );
			return;
		}

		if ( ! $this->plugin->is_configured() ) {
			header( 'x-speed-of-light: miss' );
			return;
		}

		$cache_header = $this->plugin->get_cache_header_config();
		header( 'x-speed-of-light: hit' );
		header( 'Cache-Control: ' . $cache_header['value'] );
	}
}
