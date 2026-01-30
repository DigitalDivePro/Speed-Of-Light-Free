<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once DD_SOL_FPC_PATH . 'includes/cloudflare/class-dd-sol-cloudflare.php';
require_once DD_SOL_FPC_PATH . 'includes/admin/class-dd-sol-admin.php';
require_once DD_SOL_FPC_PATH . 'includes/branding/class-dd-sol-branding.php';
require_once DD_SOL_FPC_PATH . 'includes/performance/class-dd-sol-performance.php';
require_once DD_SOL_FPC_PATH . 'includes/frontend/class-dd-sol-frontend.php';

class DD_SpeedOfLight_FPC {
	private static $instance;

	private $options       = array();
	private $is_configured = false;

	/** @var DD_SOL_Cloudflare */
	private $cloudflare;

	/** @var DD_SOL_Admin */
	private $admin;

	/** @var DD_SOL_Branding */
	private $branding;

	/** @var DD_SOL_Performance */
	private $performance;

	/** @var DD_SOL_Frontend */
	private $frontend;

	private function __construct() {
		$this->load_options();

		$this->cloudflare  = new DD_SOL_Cloudflare( $this );
		$this->branding    = new DD_SOL_Branding( $this );
		$this->performance = new DD_SOL_Performance( $this );
		$this->frontend    = new DD_SOL_Frontend( $this );
		$this->admin       = new DD_SOL_Admin( $this, $this->cloudflare );
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function load_options() {
		$options = get_option( 'scfpc_settings', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( empty( $options['cf_exclusions'] ) ) {
			$options['cf_exclusions'] = $this->get_default_exclusions();
		}

		$this->options       = $options;
		$this->is_configured = ! empty( $options['cf_email'] ) &&
			! empty( $options['cf_api_key'] ) &&
			! empty( $options['cf_zone_id'] );
	}

	public function get_options() {
		return $this->options;
	}

	public function get_default_exclusions() {
		return implode( "\n", array(
			'/wp-admin',
			'/wp-login.php',
			'/wp-comments-post.php',
			'/cart',
			'/checkout',
			'/my-account',
			'/wc-api',
			'/wp-json',
			'/feed',
			'/xmlrpc.php',
			'/preview',
		) );
	}


	public function get_option( $key, $default_value = null ) {
		return $this->options[ $key ] ?? $default_value;
	}

	public function is_configured() {
		return $this->is_configured;
	}

	public function get_zone_id() {
		return $this->get_option( 'cf_zone_id', '' );
	}

	public function get_ttl() {
		$ttl = (int) $this->get_option( 'cf_ttl', 2592000 );
		if ( 0 === $ttl ) {
			$ttl = 2592000;
		}
		return $ttl;
	}

	/**
	 * Cache-Control header presets optimized for speed vs. AdSense freshness.
	 *
	 * @return array
	 */
	public function get_cache_header_presets() {
		return array(
			'edge-only'     => array(
				'label'       => __( 'Safest: 0s browser cache (AdSense-safe) / Long Edge', 'speed-of-light-free' ),
				'header'      => 'public, max-age=0, s-maxage=%d',
				'browser_ttl' => 0,
			),
			'adsense-30s'   => array(
				'label'       => __( 'AdSense Friendly: 30s browser cache + Edge', 'speed-of-light-free' ),
				'header'      => 'public, max-age=30, s-maxage=%d, stale-while-revalidate=30',
				'browser_ttl' => 30,
			),
			'balanced-60s'  => array(
				'label'       => __( 'Balanced: 60s browser cache + Edge', 'speed-of-light-free' ),
				'header'      => 'public, max-age=60, s-maxage=%d, stale-while-revalidate=60',
				'browser_ttl' => 60,
			),
			'speed-5m'      => array(
				'label'       => __( 'Speed First: 5m browser cache (still AdSense friendly)', 'speed-of-light-free' ),
				'header'      => 'public, max-age=300, s-maxage=%d, stale-while-revalidate=60, stale-if-error=600',
				'browser_ttl' => 300,
			),
			'speed-10m'     => array(
				'label'       => __( 'Aggressive Speed: 10m browser cache', 'speed-of-light-free' ),
				'header'      => 'public, max-age=600, s-maxage=%d, stale-while-revalidate=120, stale-if-error=1200',
				'browser_ttl' => 600,
			),
		);
	}

	/**
	 * Return the active Cache-Control preset with computed header and browser TTL.
	 *
	 * @return array
	 */
	public function get_cache_header_config() {
		$presets = $this->get_cache_header_presets();
		$choice  = sanitize_key( $this->get_option( 'cf_cache_header', 'edge-only' ) );

		if ( ! isset( $presets[ $choice ] ) ) {
			$choice = 'edge-only';
		}

		$preset = $presets[ $choice ];
		$ttl    = $this->get_ttl();

		return array(
			'key'         => $choice,
			'value'       => sprintf( $preset['header'], $ttl ),
			'browser_ttl' => (int) $preset['browser_ttl'],
			'label'       => $preset['label'],
		);
	}

	public function get_domain_host() {
		$domain = wp_parse_url( get_site_url(), PHP_URL_HOST );
		return $domain ? $domain : '';
	}

	public function is_api_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
		$request_uri = sanitize_text_field( $request_uri );

		return strpos( $request_uri, '/wp-json/' ) !== false;
	}
}
