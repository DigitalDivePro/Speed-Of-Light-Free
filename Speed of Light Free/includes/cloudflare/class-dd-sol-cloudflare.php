<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_SOL_Cloudflare {
	private $plugin;
	private $api_endpoint = 'https://api.cloudflare.com/client/v4/';

	public function __construct( DD_SpeedOfLight_FPC $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_ajax_scfpc_purge_all', array( $this, 'ajax_purge_all' ) );
		add_action( 'wp_ajax_scfpc_update_rules', array( $this, 'ajax_update_rules' ) );

		add_action( 'save_post', array( $this, 'purge_on_post_save' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'purge_on_comment' ), 10, 2 );
		add_action( 'wp_update_nav_menu', array( $this, 'purge_everything_action' ) );
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'purge_everything_action' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'purge_everything_action' ) );
		add_action( 'switch_theme', array( $this, 'purge_everything_action' ) );
		add_action( 'customize_save_after', array( $this, 'purge_everything_action' ) );

		add_action( 'wp_ajax_scfpc_purge_homepage', array( $this, 'ajax_purge_homepage' ) );
		add_action( 'wp_ajax_scfpc_purge_urls', array( $this, 'ajax_purge_urls' ) );
	}

	/**
	 * Fetch cache hit rate stats from Cloudflare (cached in a transient).
	 *
	 * @return array|WP_Error {
	 *   @type float|null $hit_rate 0-100 percentage or null if unavailable.
	 *   @type string     $period   Human label for the window.
	 * }
	 */
	public function get_cache_stats() {
		if ( ! $this->plugin->is_configured() ) {
			return new WP_Error( 'missing_creds', __( 'Missing Cloudflare credentials', 'speed-of-light-free' ) );
		}

		$zone_id = $this->plugin->get_zone_id();
		if ( ! $zone_id ) {
			return new WP_Error( 'missing_zone', __( 'Missing Zone ID', 'speed-of-light-free' ) );
		}

		$cache_key = 'dd_sol_cache_stats_' . md5( $zone_id );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		// 24h window; "since" in seconds relative to now per CF docs.
		$res = $this->call_api( 'GET', "zones/$zone_id/analytics/dashboard?since=-86400&continuous=true" );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$hit_rate = null;
		if ( ! empty( $res['result']['totals']['cacheHitRate'] ) ) {
			$raw      = $res['result']['totals']['cacheHitRate'];
			$hit_rate = is_numeric( $raw ) ? min( 100, max( 0, floatval( $raw ) ) ) : null;
		}

		$data = array(
			'hit_rate' => $hit_rate,
			'period'   => __( 'Last 24 hours', 'speed-of-light-free' ),
		);

		set_transient( $cache_key, $data, 10 * MINUTE_IN_SECONDS );
		return $data;
	}

	public function ajax_update_rules() {
		check_ajax_referer( 'scfpc_rules_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'speed-of-light-free' ) );
		}

		if ( ! $this->plugin->is_configured() ) {
			wp_send_json_error( __( 'Missing Cloudflare credentials', 'speed-of-light-free' ) );
		}

		$zone_id = $this->plugin->get_zone_id();
		if ( ! $zone_id ) {
			wp_send_json_error( __( 'Missing Zone ID', 'speed-of-light-free' ) );
		}


		$bypass_conditions = array(
			'(http.request.method eq "POST")',
			'(http.request.uri.query ne "")',
			'starts_with(http.request.uri.path, "/robots.txt")',
			'starts_with(http.request.uri.path, "/ads.txt")',
			'starts_with(http.request.uri.path, "/sitemap")',
			'starts_with(http.request.uri.path, "/wp-admin")',
			'starts_with(http.request.uri.path, "/wp-login.php")',
			'starts_with(http.request.uri.path, "/wp-json")',
			'starts_with(http.request.uri.path, "/xmlrpc.php")',
			'starts_with(http.request.uri.path, "/wp-comments-post.php")',
			'(http.cookie contains "wordpress_logged_in")',
			'(http.cookie contains "comment_author_")',
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$bypass_conditions[] = '(http.cookie contains "wp_woocommerce_session")';
			$bypass_conditions[] = 'starts_with(http.request.uri.path, "/cart")';
			$bypass_conditions[] = 'starts_with(http.request.uri.path, "/checkout")';
			$bypass_conditions[] = 'starts_with(http.request.uri.path, "/my-account")';
		}

		$exclusions = $this->plugin->get_option( 'cf_exclusions', '' );
		if ( ! empty( $exclusions ) ) {
			$lines = explode( "\n", $exclusions );
			foreach ( $lines as $line ) {
				$line = sanitize_text_field( $line );
				$line = trim( $line );
				if ( ! $line ) {
					continue;
				}
				$clean_line = str_replace( array( '*', '"', '\'' ), '', $line );
				if ( $clean_line ) {
					$bypass_conditions[] = '(http.request.uri.path contains "' . $clean_line . '")';
				}
			}
		}

		$bypass_expression = implode( ' or ', $bypass_conditions );

		$domain = $this->plugin->get_domain_host();
		if ( empty( $domain ) ) {
			wp_send_json_error( __( 'Could not determine site domain for rules.', 'speed-of-light-free' ) );
		}
		$ttl          = $this->plugin->get_ttl();
		$cache_header = $this->plugin->get_cache_header_config();
		$browser_ttl  = isset( $cache_header['browser_ttl'] ) ? (int) $cache_header['browser_ttl'] : 0;

		$cache_conds   = array( '(http.host eq "' . $domain . '")' );
		$cache_conds[] = 'not (http.cookie contains "wordpress_logged_in")';
		$cache_conds[] = 'not (http.cookie contains "wp_woocommerce_session")';
		$cache_conds[] = 'not (http.cookie contains "comment_author_")';

		$cache_expression = implode( ' and ', $cache_conds );

		$rules = array(
			array(
				'expression'        => $bypass_expression,
				'description'       => 'Speed of Light:Bypass Cache',
				'action'            => 'set_cache_settings',
				'action_parameters' => array(
					'cache' => false,
				),
			),
			array(
				'expression'        => $cache_expression,
				'description'       => 'Speed of Light:Edge Cache',
				'action'            => 'set_cache_settings',
			'action_parameters' => array(
				'cache'       => true,
				'edge_ttl'    => array(
					'mode'    => 'override_origin',
					'default' => $ttl,
				),
				'browser_ttl' => array(
					'mode'    => 'override_origin',
					'default' => $browser_ttl,
				),
			),
		),
	);

		$endpoint = "zones/$zone_id/rulesets/phases/http_request_cache_settings/entrypoint";
		$res      = $this->call_api( 'PUT', $endpoint, array( 'rules' => $rules ) );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( 'Rule Error: ' . $res->get_error_message() );
		}

		$this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'purge_everything' => true ) );
		update_option( 'scfpc_last_rule_update', current_time( 'mysql' ) );

		wp_send_json_success( __( 'Rules Deployed!', 'speed-of-light-free' ) );
	}

	public function ajax_purge_all() {
		check_ajax_referer( 'scfpc_purge_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Denied', 'speed-of-light-free' ) );
		}

		if ( ! $this->plugin->is_configured() ) {
			wp_send_json_error( __( 'Missing Cloudflare credentials', 'speed-of-light-free' ) );
		}

		$zone_id = $this->plugin->get_zone_id();
		$res     = $this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'purge_everything' => true ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}

		wp_send_json_success( __( 'Cache Purged', 'speed-of-light-free' ) );
	}

	public function ajax_purge_homepage() {
		check_ajax_referer( 'scfpc_purge_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Denied', 'speed-of-light-free' ) );
		}
		if ( ! $this->plugin->is_configured() ) {
			wp_send_json_error( __( 'Missing Cloudflare credentials', 'speed-of-light-free' ) );
		}

		$home    = home_url( '/' );
		$zone_id = $this->plugin->get_zone_id();
		$res     = $this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'files' => array( $home ) ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}

		wp_send_json_success( __( 'Homepage purged', 'speed-of-light-free' ) );
	}

	public function ajax_purge_urls() {
		check_ajax_referer( 'scfpc_purge_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Denied', 'speed-of-light-free' ) );
		}
		if ( ! $this->plugin->is_configured() ) {
			wp_send_json_error( __( 'Missing Cloudflare credentials', 'speed-of-light-free' ) );
		}

		$raw   = wp_unslash( $_POST['urls'] ?? '' );
		$raw   = sanitize_textarea_field( $raw );
		$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		if ( empty( $lines ) ) {
			wp_send_json_error( __( 'Please provide at least one URL.', 'speed-of-light-free' ) );
		}

		$urls = array();
		foreach ( $lines as $line ) {
			$clean_url = esc_url_raw( $line );
			if ( $clean_url && wp_http_validate_url( $clean_url ) ) {
				$urls[] = $clean_url;
			}
		}

		if ( empty( $urls ) ) {
			wp_send_json_error( __( 'No valid URLs provided.', 'speed-of-light-free' ) );
		}

		$urls    = array_slice( array_unique( $urls ), 0, 30 );
		$zone_id = $this->plugin->get_zone_id();
		$res     = $this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'files' => $urls ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}

		wp_send_json_success( __( 'Selected URLs purged', 'speed-of-light-free' ) );
	}

	public function purge_on_post_save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
			return;
		}

		$this->smart_purge( $post_id );
	}

	public function purge_on_comment( $comment_id, $comment_approved ) {
		if ( 1 !== (int) $comment_approved ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		$this->smart_purge( (int) $comment->comment_post_ID );
	}

	public function purge_everything_action() {
		$zone_id = $this->plugin->get_zone_id();
		if ( ! $zone_id ) {
			return;
		}

		$this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'purge_everything' => true ) );
	}

	private function smart_purge( $post_id ) {
		$zone_id = $this->plugin->get_zone_id();
		if ( ! $zone_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$urls = array(
			get_permalink( $post_id ),
			home_url( '/' ),
			get_post_type_archive_link( get_post_type( $post_id ) ),
			get_feed_link(),
			get_author_posts_url( $post->post_author ),
		);

		if ( get_option( 'show_on_front' ) === 'page' ) {
			$page_for_posts = get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$urls[] = get_permalink( $page_for_posts );
			}
		}

		$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
		foreach ( $taxonomies as $tax ) {
			$terms = get_the_terms( $post_id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$urls[] = get_term_link( $term );
				}
			}
		}

		$urls = array_slice( array_unique( array_filter( $urls ) ), 0, 30 );
		if ( ! empty( $urls ) ) {
			$this->call_api( 'POST', "zones/$zone_id/purge_cache", array( 'files' => $urls ) );
		}
	}

	private function call_api( $method, $endpoint, $body = null ) {
		if ( ! $this->plugin->is_configured() ) {
			return new WP_Error( 'missing_creds', 'Missing credentials' );
		}

		$url     = $this->api_endpoint . $endpoint;
		$headers = array(
			'X-Auth-Email' => $this->plugin->get_option( 'cf_email', '' ),
			'X-Auth-Key'   => $this->plugin->get_option( 'cf_api_key', '' ),
			'Content-Type' => 'application/json',
		);

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 45,
		);

		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body_decoded ) ) {
			return new WP_Error( 'api_error', __( 'Invalid API response', 'speed-of-light-free' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			$msg = $body_decoded['errors'][0]['message'] ?? 'API Error';
			return new WP_Error( 'api_error', $msg );
		}

		return $body_decoded;
	}
}
