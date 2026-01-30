<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_SOL_Performance {
	private $plugin;

	public function __construct( DD_SpeedOfLight_FPC $plugin ) {
		$this->plugin = $plugin;
		$this->apply_performance_tweaks();
	}

	private function apply_performance_tweaks() {
		$options = $this->plugin->get_options();

		if ( ! empty( $options['opt_disable_emojis'] ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		}

		if ( ! empty( $options['opt_disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter(
				'xmlrpc_methods',
				function ( $methods ) {
					unset( $methods['pingback.ping'] );
					return $methods;
				}
			);
			add_filter(
				'wp_headers',
				function ( $headers ) {
					unset( $headers['X-Pingback'] );
					return $headers;
				}
			);
		}

		if ( ! empty( $options['opt_limit_heartbeat'] ) ) {
			add_filter(
				'heartbeat_settings',
				function ( $settings ) {
					$settings['interval'] = 60;
					return $settings;
				}
			);
		}

		if ( ! empty( $options['opt_preload_lcp'] ) ) {
			add_action( 'wp_head', array( $this, 'output_lcp_preload' ), 5 );
		}

		if ( ! empty( $options['opt_youtube_facade'] ) ) {
			add_filter( 'the_content', array( $this, 'tweak_youtube_facade' ), 20 );
			add_action( 'wp_footer', array( $this, 'tweak_youtube_assets' ) );
		}

		if ( ! empty( $options['opt_lazy_iframes'] ) ) {
			add_filter( 'the_content', array( $this, 'tweak_lazy_load_content' ), 20 );
		}

		if ( ! empty( $options['opt_remove_jqmigrate'] ) ) {
			add_action(
				'wp_default_scripts',
				function ( $scripts ) {
					if ( ! empty( $scripts->registered['jquery'] ) && ! empty( $scripts->registered['jquery']->deps ) ) {
						$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
					}
				}
			);
		}

		if ( ! empty( $options['opt_disable_oembed'] ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			remove_filter( 'tiny_mce_plugins', 'wp_oembed_add_mce_plugin' );
			add_filter(
				'rewrite_rules_array',
				function ( $rules ) {
					foreach ( $rules as $rule => $rewrite ) {
						if ( false !== strpos( $rewrite, 'embed=true' ) ) {
							unset( $rules[ $rule ] );
						}
					}
					return $rules;
				}
			);
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
			add_filter( 'embed_oembed_discover', '__return_false' );
		}

		if ( ! empty( $options['opt_disable_wp_embed'] ) ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_deregister_script( 'wp-embed' );
				},
				100
			);
		}

		if ( ! empty( $options['opt_remove_shortlink'] ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( ! empty( $options['opt_remove_rsd_wlw'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( ! empty( $options['opt_preload_css'] ) ) {
			add_action( 'wp_head', array( $this, 'output_css_preloads' ), 7 );
		}

		if ( ! empty( $options['opt_adsense_boost'] ) ) {
			add_action(
				'wp_head',
				function () {
					if ( is_admin() || is_feed() || is_robots() || is_embed() ) {
						return;
					}
					if ( is_user_logged_in() ) {
						return;
					}
					$domains = array(
						'https://googleads.g.doubleclick.net',
						'https://pagead2.googlesyndication.com',
						'https://tpc.googlesyndication.com',
						'https://adservice.google.com',
					);
					foreach ( $domains as $domain ) {
						$safe_domain = esc_url( $domain );
						// phpcs:ignore WordPress.Security.EscapeOutput
						printf( '<link rel="preconnect" href="%s" crossorigin>' . "\n", $safe_domain );
						// phpcs:ignore WordPress.Security.EscapeOutput
						printf( '<link rel="dns-prefetch" href="%s">' . "\n", $safe_domain );
					}
				},
				1
			);
		}
	}

	public function output_css_preloads() {
		if ( is_admin() || $this->plugin->is_api_request() || is_feed() || is_robots() || is_embed() || is_user_logged_in() ) {
			return;
		}

		global $wp_styles;
		if ( ! isset( $wp_styles ) || ! ( $wp_styles instanceof WP_Styles ) ) {
			return;
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $home_host ) {
			return;
		}

		$printed = 0;
		foreach ( (array) $wp_styles->queue as $handle ) {
			if ( $printed >= 2 ) {
				break;
			}
			if ( empty( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}

			$src = $wp_styles->registered[ $handle ]->src ?? '';
			if ( ! $src ) {
				continue;
			}

			if ( strpos( $src, '//' ) === 0 ) {
				$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
			} elseif ( strpos( $src, 'http' ) !== 0 ) {
				$src = rtrim( $wp_styles->base_url ?? '', '/' ) . '/' . ltrim( $src, '/' );
			}

			$src_host = wp_parse_url( $src, PHP_URL_HOST );
			if ( $src_host && $src_host !== $home_host ) {
				continue;
			}

			if ( strpos( $src, '/themes/' ) === false ) {
				continue;
			}

			echo '<link rel="preload" as="style" href="' . esc_url( $src ) . '" />' . "\n";
			++$printed;
		}
	}

	public function output_lcp_preload() {
		if ( is_admin() || $this->plugin->is_api_request() || is_feed() || is_robots() || is_embed() || is_search() || is_404() || is_user_logged_in() ) {
			return;
		}

		if ( ! is_singular() && ! is_front_page() ) {
			return;
		}

		$url = $this->get_lcp_image_url();
		if ( ! $url || ( strpos( $url, 'http' ) !== 0 ) ) {
			return;
		}

		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high">' . "\n";
	}

	private function get_lcp_image_url() {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		if ( is_singular() && has_post_thumbnail( $post->ID ) ) {
			$thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
			if ( ! empty( $thumb ) ) {
				return $thumb;
			}
		}

		if ( is_front_page() && ! is_home() ) {
			$front_id = get_option( 'page_on_front' );
			if ( $front_id && has_post_thumbnail( $front_id ) ) {
				$thumb = get_the_post_thumbnail_url( $front_id, 'full' );
				if ( ! empty( $thumb ) ) {
					return $thumb;
				}
			}
		}

		$content = $post->post_content ?? '';
		if ( empty( $content ) ) {
			return '';
		}

		$content_no_noscript = preg_replace( '/<noscript>.*?<\/noscript>/is', '', $content );
		$pattern             = '/<img[^>]+src=["\'](https?:\/\/[^"\']+\.(?:jpe?g|png|webp|avif|gif))["\'][^>]*>/i';
		if ( preg_match( $pattern, $content_no_noscript, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	public function tweak_youtube_facade( $content ) {
		if ( is_admin() || is_feed() ) {
			return $content;
		}

		$pattern = '/<iframe\b[^>]*src=["\'](?:https?:)?\/\/(?:www\.)?(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})["\'][^>]*><\/iframe>/i';
		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$video_id = $matches[1];
				$thumb_url = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
				$facade    = '<div class="sol-yt-facade" data-id="' . esc_attr( $video_id ) . '">';
				$facade   .= '<img class="sol-yt-thumb" src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr__( 'Video preview', 'speed-of-light-free' ) . '" loading="lazy">';
				$facade  .= '<div class="sol-yt-play"></div></div>';
				return $facade;
			},
			$content
		);
	}

	public function tweak_youtube_assets() {
		?>
		<style>.sol-yt-facade{position:relative;width:100%;height:0;padding-bottom:56.25%;background:#000;cursor:pointer;overflow:hidden}.sol-yt-thumb{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:.8;transition:opacity .3s}.sol-yt-facade:hover .sol-yt-thumb{opacity:1}.sol-yt-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:68px;height:48px;background:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 48"><path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.63 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/><path d="M45 24 27 14v20" fill="white"/></svg>') no-repeat center center}.sol-yt-loaded iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0}</style>
		<script>document.addEventListener("DOMContentLoaded",function(){var e=document.querySelectorAll(".sol-yt-facade");for(var t=0;t<e.length;t++)e[t].addEventListener("click",function(){var e=document.createElement("iframe");e.setAttribute("src","https://www.youtube.com/embed/"+this.dataset.id+"?autoplay=1&rel=0"),e.setAttribute("frameborder","0"),e.setAttribute("allowfullscreen","1"),e.setAttribute("allow","accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"),this.innerHTML="",this.classList.add("sol-yt-loaded"),this.appendChild(e)})});</script>
		<?php
	}

	public function tweak_lazy_load_content( $content ) {
		if ( is_admin() || is_feed() ) {
			return $content;
		}

		return preg_replace_callback(
			'/(<noscript>.*?<\/noscript>)|(<img\s[^>]*>)|(<iframe\s[^>]*>)/is',
			function ( $matches ) {
				if ( ! empty( $matches[1] ) ) {
					return $matches[1];
				}

				$tag = $matches[0];
				if ( strpos( $tag, '<img' ) === 0 && strpos( $tag, 'decoding=' ) === false ) {
					$tag = str_replace( '<img ', '<img decoding="async" ', $tag );
				}

				if ( strpos( $tag, '<iframe' ) === 0 && strpos( $tag, 'loading=' ) === false ) {
					$tag = str_replace( '<iframe ', '<iframe loading="lazy" ', $tag );
				}

				return $tag;
			},
			$content
		);
	}
}
