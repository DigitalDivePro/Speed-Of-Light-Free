<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_SOL_Branding {
	private $plugin;

	public function __construct( DD_SpeedOfLight_FPC $plugin ) {
		$this->plugin = $plugin;
		$options      = $plugin->get_options();

		if ( ! empty( $options['opt_dd_branding'] ) ) {
			add_action( 'admin_head', array( $this, 'output_admin_bw_theme' ), 100 );
			add_action( 'wp_head', array( $this, 'output_admin_bw_theme' ), 100 );
			add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
			add_action( 'login_footer', array( $this, 'output_login_footer' ) );
			add_filter( 'login_headerurl', array( $this, 'filter_login_url' ) );
			add_filter( 'login_headertext', array( $this, 'filter_login_title' ) );
		}

		if ( ! empty( $options['opt_remove_generator'] ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( ! empty( $options['opt_mask_login_errors'] ) ) {
			add_filter(
				'login_errors',
				function () {
					return esc_html__( 'Login failed. Please try again.', 'speed-of-light-free' );
				}
			);
		}

		if ( ! empty( $options['opt_clean_admin_footer'] ) ) {
			add_filter( 'admin_footer_text', '__return_empty_string', 100 );
			add_filter( 'update_footer', '__return_empty_string', 100 );
		}

		if ( ! empty( $options['opt_rss_thumbnails'] ) ) {
			add_filter( 'the_excerpt_rss', array( $this, 'add_rss_post_thumbnail' ) );
			add_filter( 'the_content_feed', array( $this, 'add_rss_post_thumbnail' ) );
		}
	}

	public function output_admin_bw_theme() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		?>
		<style>
		/* BEGIN tfam admin black/white theme */
		#wpadminbar {
			background: #000 !important;
			border-bottom: 1px solid #111 !important;
			box-shadow: none !important;
		}
		#wpadminbar .ab-item,
		#wpadminbar a.ab-item,
		#wpadminbar .ab-label,
		#wpadminbar .ab-icon {
			color: #fff !important;
			opacity: 0.95;
		}
		#wpadminbar .ab-top-menu > li > .ab-item:hover {
			background: rgba(255,255,255,0.08) !important;
		}

		#adminmenu, 
		#adminmenu .wp-submenu, 
		#adminmenuback, 
		#adminmenuwrap {
			background: #000 !important;
			color: #fff !important;
			border: 0 !important;
		}
		#adminmenu a {
			color: #fff !important;
		}
		#adminmenu .wp-menu-image, 
		#adminmenu .wp-menu-image .dashicons, 
		#adminmenu .wp-menu-image img {
			color: #fff !important;
			opacity: .95;
			filter: none !important;
		}
		#adminmenu li.current a.menu-top,
		#adminmenu li a:hover {
			background: rgba(255,255,255,0.08) !important;
			color: #fff !important;
		}
		#adminmenu .wp-submenu a {
			color: #ccc !important;
		}

		.wp-admin #wpcontent,
		.wp-admin #wpbody,
		.wp-admin #wpbody-content,
		.wrap {
			background: #fff !important;
			color: #000 !important;
		}

		.wp-admin .postbox, 
		.wp-admin .metabox-holder .stuffbox {
			background: #fff !important;
			border: 1px solid #ddd !important;
			color: #111 !important;
		}

		#wpadminbar input, 
		#wpadminbar .ab-text, 
		.quicklinks .ab-item input {
			background: rgba(255,255,255,0.15) !important;
			color: #fff !important;
			border-color: rgba(255,255,255,0.2) !important;
		}
		/* END tfam admin black/white theme */
		</style>
		<?php
	}

	public function enqueue_login_assets() {
		wp_enqueue_style(
			'dd-sol-login',
			DD_SOL_FPC_URL . 'assets/css/login.css',
			array(),
			DD_SOL_FPC_VERSION
		);

		$site_icon_url = get_site_icon_url();
		if ( $site_icon_url ) {
			$custom_css = 'body.login #login h1 a { background-image: url(' . esc_url_raw( $site_icon_url ) . ') !important; background-size: contain; background-position: center; background-repeat: no-repeat; width: 84px; height: 84px; text-indent: -9999px; }';
			wp_add_inline_style( 'dd-sol-login', $custom_css );
		}

		wp_enqueue_script(
			'dd-sol-login',
			DD_SOL_FPC_URL . 'assets/js/login.js',
			array(),
			DD_SOL_FPC_VERSION,
			true
		);
	}

	public function output_login_footer() {
		?>
		<div id="dd-login-footer" style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
			<a href="https://digitaldive.pro" target="_blank" rel="noopener noreferrer" style="color: #ccc; font-size: 13px; text-decoration: none; transition: color 0.3s ease;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#ccc'">
				<?php echo esc_html__( 'Powered by DigitalDive', 'speed-of-light-free' ); ?>
			</a>
		</div>
		<?php
	}

	public function filter_login_url() {
		return home_url();
	}

	public function filter_login_title() {
		return get_bloginfo( 'name' );
	}

	public function add_rss_post_thumbnail( $content ) {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}
		if ( has_post_thumbnail( $post->ID ) ) {
			$content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . $content;
		}
		return $content;
	}
}
