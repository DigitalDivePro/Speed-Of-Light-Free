<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_SOL_Admin {
	private $plugin;
	private $cloudflare;

	public function __construct( DD_SpeedOfLight_FPC $plugin, DD_SOL_Cloudflare $cloudflare ) {
		$this->plugin     = $plugin;
		$this->cloudflare = $cloudflare;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_head', array( $this, 'admin_styles' ) );
		add_action( 'wp_head', array( $this, 'frontend_styles' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		add_filter( 'plugin_action_links_' . DD_SOL_FPC_BASENAME, array( $this, 'add_settings_link' ) );
	}

	public function add_admin_menu() {
		$upgrade_url = 'https://speedoflight.pro';

		add_menu_page(
			esc_html__( 'CDN Cache', 'speed-of-light-free' ),
			esc_html__( 'Speed of Light Free', 'speed-of-light-free' ),
			'manage_options',
			'dd-speed-of-light',
			array( $this, 'create_admin_page' ),
			'dashicons-cloud',
			99
		);

		add_submenu_page(
			'dd-speed-of-light',
			esc_html__( 'Performance Tweaks', 'speed-of-light-free' ),
			esc_html__( 'Tweaks', 'speed-of-light-free' ),
			'manage_options',
			'dd-speed-of-light-tweaks',
			array( $this, 'create_admin_page' )
		);

		add_submenu_page(
			'dd-speed-of-light',
			esc_html__( 'Extras', 'speed-of-light-free' ),
			esc_html__( 'Extras', 'speed-of-light-free' ),
			'manage_options',
			'dd-speed-of-light-extras',
			array( $this, 'create_admin_page' )
		);

		add_submenu_page(
			'dd-speed-of-light',
			esc_html__( 'Upgrades', 'speed-of-light-free' ),
			esc_html__( 'Upgrades', 'speed-of-light-free' ),
			'manage_options',
			'dd-speed-of-light-upgrade',
			array( $this, 'create_upgrade_page' )
		);

		global $submenu;
		if ( isset( $submenu['dd-speed-of-light'][0][0] ) ) {
			$submenu['dd-speed-of-light'][0][0] = esc_html__( 'Cloudflare', 'speed-of-light-free' );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'dd-speed-of-light' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'dd-sol-admin',
			DD_SOL_FPC_URL . 'assets/css/admin.css',
			array(),
			DD_SOL_FPC_VERSION
		);

		wp_enqueue_script(
			'dd-sol-admin',
			DD_SOL_FPC_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			DD_SOL_FPC_VERSION,
			true
		);

		wp_localize_script(
			'dd-sol-admin',
			'ddSolAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonces'       => array(
					'purge' => wp_create_nonce( 'scfpc_purge_nonce' ),
					'rules' => wp_create_nonce( 'scfpc_rules_nonce' ),
				),
				'strings'      => array(
					'purgeConfirm'     => esc_html__( 'Purge entire cache? WARNING: This may temporarily increase server load.', 'speed-of-light-free' ),
					'updateConfirm'    => esc_html__( 'This will overwrite Cloudflare cache rules. Proceed?', 'speed-of-light-free' ),
					'purgeSuccess'     => esc_html__( 'Purged Successfully!', 'speed-of-light-free' ),
					'purgeHomeSuccess' => esc_html__( 'Homepage purged!', 'speed-of-light-free' ),
					'purgeUrlsSuccess' => esc_html__( 'Selected URLs purged!', 'speed-of-light-free' ),
					'purgeUrlsError'   => esc_html__( 'Please provide at least one valid URL.', 'speed-of-light-free' ),
							'rulesSuccess'     => esc_html__( 'Rules Deployed!', 'speed-of-light-free' ),
							'saveSuccess'      => esc_html__( 'Settings saved.', 'speed-of-light-free' ),
				),
				'isConfigured' => (bool) $this->plugin->is_configured(),
			)
		);
	}

	public function create_admin_page() {
		$last_update  = get_option( 'scfpc_last_rule_update', esc_html__( 'Never', 'speed-of-light-free' ) );
		$is_active   = $this->plugin->is_configured();
		$upgrade_url = 'https://speedoflight.pro';
		$status_text = $is_active ? esc_html__( 'Connected', 'speed-of-light-free' ) : esc_html__( 'Disconnected', 'speed-of-light-free' );
		$status_desc = $is_active
			? esc_html__( 'Cloudflare edge caching is active.', 'speed-of-light-free' )
			: esc_html__( 'Connect Cloudflare credentials to activate caching.', 'speed-of-light-free' );

		$tab     = $this->get_current_tab();
		$tab_map = array(
			'cloudflare' => array(
				'label'    => esc_html__( 'Cloudflare', 'speed-of-light-free' ),
				'slug'     => 'dd-speed-of-light',
				'sections' => array( 'scfpc_main', 'scfpc_config' ),
			),
			'tweaks'     => array(
				'label'    => esc_html__( 'Tweaks', 'speed-of-light-free' ),
				'slug'     => 'dd-speed-of-light-tweaks',
				'sections' => array( 'scfpc_tweaks' ),
			),
			'extras'     => array(
				'label'    => esc_html__( 'Extras', 'speed-of-light-free' ),
				'slug'     => 'dd-speed-of-light-extras',
				'sections' => array( 'scfpc_extras' ),
			),
			'upgrade'    => array(
				'label'    => esc_html__( 'Upgrades', 'speed-of-light-free' ),
				'slug'     => 'dd-speed-of-light-upgrade',
				'sections' => array(),
			),
		);
		?>
		<div class="wrap sol-admin">
			<div class="sol-shell">
				<div class="sol-header">
					<div class="sol-title">
						<span class="sol-title-icon dashicons dashicons-cloud" aria-hidden="true"></span>
						<div>
							<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
								<h1><?php echo esc_html__( 'Speed of Light', 'speed-of-light-free' ); ?></h1>
								<span class="sol-free-button"><?php echo esc_html__( 'Free', 'speed-of-light-free' ); ?></span>
							</div>
							<p><?php echo esc_html__( 'Maximizes Cloudflare Free with AdSense-safe full-page edge caching.', 'speed-of-light-free' ); ?></p>
						</div>
					</div>
					<div class="sol-header-actions">
						<a class="button sol-button sol-button-ghost" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade to Pro', 'speed-of-light-free' ); ?></a>
						<button type="button" class="button sol-button sol-button-outline" data-sol-modal="sol-modal-how"><?php echo esc_html__( 'How SOL Works', 'speed-of-light-free' ); ?></button>
						<button type="submit" class="button sol-button sol-button-primary" form="sol-settings-form"><?php echo esc_html__( 'Save Settings', 'speed-of-light-free' ); ?></button>
					</div>
				</div>
				<?php settings_errors(); ?>
				<div id="sol-toast" class="sol-toast" role="status" aria-live="polite"></div>
				<h2 class="nav-tab-wrapper sol-tabs">
				<?php
				foreach ( $tab_map as $key => $info ) :
					$active = $tab === $key ? ' nav-tab-active' : '';
					$url    = admin_url( 'admin.php?page=' . $info['slug'] );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $info['label'] ); ?></a>
				<?php endforeach; ?>
				</h2>
				<div class="sol-grid <?php echo $tab === 'cloudflare' ? '' : 'sol-grid--single'; ?>">
					<div class="sol-card sol-form">
						<form method="post" action="options.php" id="sol-settings-form">
						<?php
							settings_fields( 'scfpc_option_group' );
							echo '<input type="hidden" name="scfpc_settings[active_tab]" value="' . esc_attr( $tab ) . '">';
							$sections = $tab_map[ $tab ]['sections'] ?? array();
							$this->render_sections( $sections );
							submit_button( esc_html__( 'Save Settings', 'speed-of-light-free' ) );
						?>
						</form>
					</div>
				<?php if ( $tab === 'cloudflare' ) : ?>
					<div class="sol-actions">
						<div class="sol-card">
							<div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
								<h3><?php echo esc_html__( 'Edge Status', 'speed-of-light-free' ); ?></h3>
								<span class="sol-pill <?php echo $is_active ? 'sol-pill--active' : 'sol-pill--inactive'; ?>"><?php echo esc_html( $status_text ); ?></span>
							</div>
							<p class="sol-muted"><?php echo esc_html( $status_desc ); ?></p>
							<div class="sol-meta">
								<div class="sol-meta-row">
									<span><?php echo esc_html__( 'Last Deployed', 'speed-of-light-free' ); ?></span>
									<strong><?php echo esc_html( $last_update ); ?></strong>
								</div>
							</div>
						</div>
						<div class="sol-card">
							<div class="sol-action-block">
								<strong><?php echo esc_html__( 'Deploy Rules', 'speed-of-light-free' ); ?></strong>
								<p><?php echo esc_html__( 'Deploys Cloudflare edge cache rules for safe full-page caching.', 'speed-of-light-free' ); ?></p>
								<div class="sol-action-buttons">
									<button type="button" id="scfpc-update-rules" class="button sol-button sol-button-primary" <?php disabled( ! $this->plugin->is_configured() ); ?>><?php echo esc_html__( 'Deploy Rules', 'speed-of-light-free' ); ?></button>
								</div>
								<span id="scfpc-rules-spinner" class="spinner" style="float:none;"></span>
							</div>
							<div class="sol-action-block">
								<strong><?php echo esc_html__( 'Manual Purge', 'speed-of-light-free' ); ?></strong>
								<p><?php echo esc_html__( 'Clear the entire edge cache or target specific URLs.', 'speed-of-light-free' ); ?></p>
								<div class="sol-action-buttons">
									<button type="button" id="scfpc-purge-all" class="button sol-button sol-button-outline" <?php disabled( ! $this->plugin->is_configured() ); ?>><?php echo esc_html__( 'Purge Everything', 'speed-of-light-free' ); ?></button>
									<button type="button" id="scfpc-purge-home" class="button sol-button sol-button-outline" <?php disabled( ! $this->plugin->is_configured() ); ?>><?php echo esc_html__( 'Purge Homepage', 'speed-of-light-free' ); ?></button>
								</div>
								<textarea id="scfpc-purge-urls-input" class="large-text code" rows="3" placeholder="<?php echo esc_attr__( 'One URL per line (https://example.com/page)', 'speed-of-light-free' ); ?>"></textarea>
								<div class="sol-action-buttons">
									<button type="button" id="scfpc-purge-urls" class="button sol-button sol-button-outline" <?php disabled( ! $this->plugin->is_configured() ); ?>><?php echo esc_html__( 'Purge URLs', 'speed-of-light-free' ); ?></button>
								</div>
								<span id="scfpc-purge-spinner" class="spinner" style="float:none;"></span>
							</div>
							<div id="scfpc-response" style="margin-top:12px; font-weight:600; padding:10px; border-radius:10px; text-align:center; display:none;"></div>
						</div>
						<div class="sol-card sol-upgrade">
							<h3><?php echo esc_html__( 'Pro version is built for serious production sites', 'speed-of-light-free' ); ?></h3>
							<p><?php echo esc_html__( 'Speed of Light Pro delivers local-first HTML caching and a Turbo Preload engine that warms thousands of URLs fast, without risky optimizations or logic that will break your website.', 'speed-of-light-free' ); ?></p>
							<div class="sol-action-buttons">
								<button type="button" class="button sol-button sol-button-outline" data-sol-modal="sol-modal-pro"><?php echo esc_html__( 'See Pro Features', 'speed-of-light-free' ); ?></button>
								<a class="button sol-button sol-button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade Now', 'speed-of-light-free' ); ?></a>
							</div>
						</div>
					</div>
				<?php endif; ?>
				</div>
			</div>
			<div class="sol-modal-overlay" data-sol-overlay>
				<div class="sol-modal" id="sol-modal-how" role="dialog" aria-modal="true" aria-labelledby="sol-modal-how-title" tabindex="-1">
				<button type="button" class="sol-modal-close" data-sol-close aria-label="<?php echo esc_attr__( 'Close', 'speed-of-light-free' ); ?>">x</button>
				<h3 id="sol-modal-how-title"><?php echo esc_html__( 'How Speed of Light Free works', 'speed-of-light-free' ); ?></h3>
				<p class="sol-muted"><?php echo esc_html__( 'Free is an edge-first Cloudflare caching layer that keeps WordPress behavior intact.', 'speed-of-light-free' ); ?></p>
				<ul>
					<li><?php echo esc_html__( 'Visitor request hits Cloudflare, not WordPress first.', 'speed-of-light-free' ); ?></li>
					<li><?php echo esc_html__( 'SOL deploys Cloudflare cache rules and headers to define what is safe to cache.', 'speed-of-light-free' ); ?></li>
					<li><?php echo esc_html__( 'Logged-in users, WooCommerce sessions, and POST requests are bypassed by default.', 'speed-of-light-free' ); ?></li>
					<li><?php echo esc_html__( 'Eligible pages are served directly from Cloudflare edge cache.', 'speed-of-light-free' ); ?></li>
					<li><?php echo esc_html__( 'If not eligible, the request reaches WordPress normally and Cloudflare stores the response per your TTL settings.', 'speed-of-light-free' ); ?></li>
				</ul>
				<p class="sol-muted"><?php echo esc_html__( 'What Free does not include', 'speed-of-light-free' ); ?></p>
				<ul>
					<li><?php echo esc_html__( 'No local server HTML cache or advanced-cache.php drop-in.', 'speed-of-light-free' ); ?></li>
					<li><?php echo esc_html__( 'No Turbo Preload engine in the Free version.', 'speed-of-light-free' ); ?></li>
				</ul>
				<p class="sol-muted"><?php echo esc_html__( 'Want the full engine? Pro adds local-first HTML caching with advanced-cache.php and Turbo Preload.', 'speed-of-light-free' ); ?></p>
				<div class="sol-modal-actions">
					<a class="button sol-button sol-button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade to Pro', 'speed-of-light-free' ); ?></a>
					<button type="button" class="button sol-button sol-button-outline" data-sol-close><?php echo esc_html__( 'Close', 'speed-of-light-free' ); ?></button>
				</div>
			</div>
						<div class="sol-modal" id="sol-modal-pro" role="dialog" aria-modal="true" aria-labelledby="sol-modal-pro-title" tabindex="-1">
					<button type="button" class="sol-modal-close" data-sol-close aria-label="<?php echo esc_attr__( 'Close', 'speed-of-light-free' ); ?>">x</button>
					<h3 id="sol-modal-pro-title"><?php echo esc_html__( 'Speed of Light Pro', 'speed-of-light-free' ); ?></h3>
					<p class="sol-modal-lede"><?php echo esc_html__( 'Speed of Light Pro is a local-first WordPress caching plugin with Turbo Preload, built for real speed without risky shortcuts or hidden logic.', 'speed-of-light-free' ); ?></p>
					<div class="sol-modal-grid">
						<div class="sol-modal-section">
							<h4><?php echo esc_html__( 'Core caching engine', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'True HTML page caching served before WordPress loads via advanced-cache.php.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Local-first cache with no external services, APIs, or SaaS dependencies.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Deterministic behavior: same request, same result.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Ultra-fast delivery with minimal PHP execution and disk overhead.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section">
							<h4><?php echo esc_html__( 'Turbo preload engine', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'Batch preloading warms thousands of URLs quickly for large sites.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Optimized execution path avoids per-URL WordPress bootstraps.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Designed to prevent timeouts and memory spikes.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Manual, controlled runs with no runaway background processes.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section">
							<h4><?php echo esc_html__( 'Safety + compatibility', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'Logged-in users are never cached. POST requests bypass cache.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'WooCommerce, memberships, and LMS are safe by default.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Selective exclusions, predictable invalidation, and one-click clearing.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Transparent logic with no JS delaying, DOM rewriting, or CSS guessing.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section">
							<h4><?php echo esc_html__( 'Ownership + licensing', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'No telemetry, no tracking, no remote calls.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Unlimited sites with fair annual pricing and no per-site tax.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'All core performance features included, no tier lockouts.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section sol-modal-section--wide">
							<h4><?php echo esc_html__( 'Who this is for', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'Developers, agencies, sysadmins, and site owners who value stability.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section sol-modal-section--wide">
							<h4><?php echo esc_html__( 'What it does not do', 'speed-of-light-free' ); ?></h4>
							<ul>
								<li><?php echo esc_html__( 'No risky JS deferral, no CSS pruning guesses, no DOM mutation.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'No PageSpeed score chasing or fragile one-click magic.', 'speed-of-light-free' ); ?></li>
								<li><?php echo esc_html__( 'Just fast cached delivery you can trust.', 'speed-of-light-free' ); ?></li>
							</ul>
						</div>
						<div class="sol-modal-section sol-modal-section--wide">
							<h4><?php echo esc_html__( 'Pro screenshots', 'speed-of-light-free' ); ?></h4>
							<div class="sol-shot-grid">
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/Speedoflight-dashboard.png' ); ?>" alt="<?php echo esc_attr__( 'Speed of Light Pro dashboard', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Dashboard', 'speed-of-light-free' ); ?></span>
								</div>
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/local-cache.png' ); ?>" alt="<?php echo esc_attr__( 'Local cache overview', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Local cache', 'speed-of-light-free' ); ?></span>
								</div>
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/asset-cache.png' ); ?>" alt="<?php echo esc_attr__( 'Asset cache settings', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Asset cache', 'speed-of-light-free' ); ?></span>
								</div>
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/Cache-aware.png' ); ?>" alt="<?php echo esc_attr__( 'Cache aware features', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Cache aware', 'speed-of-light-free' ); ?></span>
								</div>
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/extra-options.png' ); ?>" alt="<?php echo esc_attr__( 'Extra options', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Extra options', 'speed-of-light-free' ); ?></span>
								</div>
								<div class="sol-shot">
									<img src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/kiling-bloat-features.png' ); ?>" alt="<?php echo esc_attr__( 'Killing bloat features', 'speed-of-light-free' ); ?>">
									<span><?php echo esc_html__( 'Killing bloat', 'speed-of-light-free' ); ?></span>
								</div>
							</div>
						</div>
					</div>
					<div class="sol-modal-cta">
						<div class="sol-modal-cta-text">
							<h4><?php echo esc_html__( 'Ready to upgrade?', 'speed-of-light-free' ); ?></h4>
							<p><?php echo esc_html__( 'Unlock local-first HTML caching and Turbo Preload for every site you manage.', 'speed-of-light-free' ); ?></p>
						</div>
						<div class="sol-modal-actions">
							<a class="button sol-button sol-button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade to Pro', 'speed-of-light-free' ); ?></a>
							<button type="button" class="button sol-button sol-button-outline" data-sol-close><?php echo esc_html__( 'Close', 'speed-of-light-free' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	public function create_upgrade_page() {
		$upgrade_url = 'https://speedoflight.pro';
		?>
		<div class="wrap sol-admin">
			<div class="sol-shell">
				<div class="sol-header">
					<div class="sol-title">
						<span class="sol-title-icon dashicons dashicons-cloud" aria-hidden="true"></span>
						<div>
							<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
								<h1><?php echo esc_html__( 'Upgrades', 'speed-of-light-free' ); ?></h1>
							</div>
							<p><?php echo esc_html__( 'Wondering what pro has to offer? Well check out some of the amazing features below.', 'speed-of-light-free' ); ?></p>
						</div>
					</div>
					<div class="sol-header-actions">
						<a class="button sol-button sol-button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade to Pro', 'speed-of-light-free' ); ?></a>
					</div>
				</div>
				<div class="sol-card">
					<h3><?php echo esc_html__( 'Below are some screenshots of the Pro version', 'speed-of-light-free' ); ?></h3>
					<div class="sol-shot-grid">
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/Speedoflight-dashboard.png' ); ?>" alt="<?php echo esc_attr__( 'Speed of Light Pro dashboard', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'Fancy Dashboard', 'speed-of-light-free' ); ?></span>
						</div>
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/local-cache.png' ); ?>" alt="<?php echo esc_attr__( 'Local cache overview', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'WPLocal cache (Full page caching)', 'speed-of-light-free' ); ?></span>
						</div>
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/asset-cache.png' ); ?>" alt="<?php echo esc_attr__( 'Asset cache settings', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'WP Asset cache', 'speed-of-light-free' ); ?></span>
						</div>
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/Cache-aware.png' ); ?>" alt="<?php echo esc_attr__( 'Cache aware features', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'WP Cache aware', 'speed-of-light-free' ); ?></span>
						</div>
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/extra-options.png' ); ?>" alt="<?php echo esc_attr__( 'Extra options', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'Extra WP options', 'speed-of-light-free' ); ?></span>
						</div>
						<div class="sol-shot">
							<img data-sol-lightbox="1" src="<?php echo esc_url( DD_SOL_FPC_URL . 'includes/img/kiling-bloat-features.png' ); ?>" alt="<?php echo esc_attr__( 'Killing bloat features', 'speed-of-light-free' ); ?>">
							<span><?php echo esc_html__( 'Killing WP bloat', 'speed-of-light-free' ); ?></span>
						</div>
					</div>
				</div>
				<div class="sol-card sol-upgrade" style="margin-top:18px; text-align:center;">
					<h3><?php echo esc_html__( 'READY TO GO PRO?', 'speed-of-light-free' ); ?></h3>
					<p class="sol-muted"><?php echo esc_html__( 'Got pro and unlock local-first full page caching and so many more features.', 'speed-of-light-free' ); ?></p>
					<a class="button sol-button sol-button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Upgrade to Pro', 'speed-of-light-free' ); ?></a>
				</div>

			<div class="sol-lightbox" id="sol-lightbox" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Screenshot preview', 'speed-of-light-free' ); ?>">
				<button type="button" class="sol-lightbox-close" aria-label="<?php echo esc_attr__( 'Close', 'speed-of-light-free' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				<img src="" alt="" />
			</div>
			</div>
		</div>
		<?php
	}

	private function get_current_tab() {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$page = sanitize_key( $page ? $page : 'dd-speed-of-light' );
		if ( $page === 'dd-speed-of-light-tweaks' ) {
			return 'tweaks';
		}
		if ( $page === 'dd-speed-of-light-extras' ) {
			return 'extras';
		}
		if ( $page === 'dd-speed-of-light-upgrade' ) {
			return 'upgrade';
		}
		$tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$tab = sanitize_key( $tab ? $tab : '' );
		if ( in_array( $tab, array( 'cloudflare', 'tweaks', 'extras', 'upgrade' ), true ) ) {
			return $tab;
		}
		return 'cloudflare';
	}

	private function render_sections( array $allowed_sections ) {
		$page = 'simple-cf-page-cache';
		global $wp_settings_sections, $wp_settings_fields;
		if ( empty( $wp_settings_sections[ $page ] ) ) {
			return;
		}

		foreach ( (array) $wp_settings_sections[ $page ] as $section_id => $section ) {
			if ( ! in_array( $section_id, $allowed_sections, true ) ) {
				continue;
			}
			if ( $section['title'] ) {
				echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
			}
			if ( ! empty( $section['callback'] ) ) {
				call_user_func( $section['callback'], $section );
			}
			if ( ! empty( $wp_settings_fields[ $page ][ $section_id ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( $page, $section_id );
				echo '</table>';
			}
		}
	}

	public function page_init() {
		register_setting( 'scfpc_option_group', 'scfpc_settings', array( $this, 'sanitize_settings' ) );

		add_settings_section( 'scfpc_main', esc_html__( 'API Credentials', 'speed-of-light-free' ), null, 'simple-cf-page-cache' );
		add_settings_field( 'cf_email', esc_html__( 'Cloudflare Email', 'speed-of-light-free' ), array( $this, 'field_email' ), 'simple-cf-page-cache', 'scfpc_main' );
		add_settings_field( 'cf_api_key', esc_html__( 'Global API Key', 'speed-of-light-free' ), array( $this, 'field_api_key' ), 'simple-cf-page-cache', 'scfpc_main' );
		add_settings_field( 'cf_zone_id', esc_html__( 'Zone ID', 'speed-of-light-free' ), array( $this, 'field_zone_id' ), 'simple-cf-page-cache', 'scfpc_main' );

		add_settings_section( 'scfpc_config', esc_html__( 'Cache Configuration', 'speed-of-light-free' ), null, 'simple-cf-page-cache' );
		add_settings_field( 'cf_ttl', esc_html__( 'Edge Cache TTL (Cloudflare)', 'speed-of-light-free' ), array( $this, 'field_ttl' ), 'simple-cf-page-cache', 'scfpc_config' );
		add_settings_field( 'cf_cache_header', esc_html__( 'Cache-Control Header', 'speed-of-light-free' ), array( $this, 'field_cache_header' ), 'simple-cf-page-cache', 'scfpc_config' );
		add_settings_field( 'cf_exclusions', esc_html__( 'Custom Exclusions', 'speed-of-light-free' ), array( $this, 'field_exclusions' ), 'simple-cf-page-cache', 'scfpc_config' );

		add_settings_section( 'scfpc_tweaks', esc_html__( 'Performance Tweaks', 'speed-of-light-free' ), null, 'simple-cf-page-cache' );
		add_settings_field( 'opt_disable_emojis', esc_html__( 'Disable WP Emojis (Recommended)', 'speed-of-light-free' ), array( $this, 'field_disable_emojis' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_remove_jqmigrate', esc_html__( 'Remove jQuery Migrate (Caution)', 'speed-of-light-free' ), array( $this, 'field_remove_jqmigrate' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_disable_xmlrpc', esc_html__( 'Disable XML-RPC (Recommended)', 'speed-of-light-free' ), array( $this, 'field_disable_xmlrpc' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_limit_heartbeat', esc_html__( 'Limit Admin Heartbeat (Recommended)', 'speed-of-light-free' ), array( $this, 'field_limit_heartbeat' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_disable_oembed', esc_html__( 'Disable oEmbed Discovery (Caution)', 'speed-of-light-free' ), array( $this, 'field_disable_oembed' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_preload_lcp', esc_html__( 'Preload LCP Image (Recommended)', 'speed-of-light-free' ), array( $this, 'field_preload_lcp' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_preload_css', esc_html__( 'Preload Theme CSS (Caution)', 'speed-of-light-free' ), array( $this, 'field_preload_css' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_youtube_facade', esc_html__( 'Smart YouTube Previews (Recommended)', 'speed-of-light-free' ), array( $this, 'field_youtube_facade' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_lazy_iframes', esc_html__( 'Aggressive Lazy Load (Caution)', 'speed-of-light-free' ), array( $this, 'field_lazy_iframes' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_adsense_boost', esc_html__( 'Boost AdSense RPM (Recommended)', 'speed-of-light-free' ), array( $this, 'field_adsense_boost' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_disable_wp_embed', esc_html__( 'Disable wp-embed.js (Recommended)', 'speed-of-light-free' ), array( $this, 'field_disable_wp_embed' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_remove_shortlink', esc_html__( 'Remove Shortlink Tag (Recommended)', 'speed-of-light-free' ), array( $this, 'field_remove_shortlink' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_remove_rsd_wlw', esc_html__( 'Remove RSD & WLW Links (Recommended)', 'speed-of-light-free' ), array( $this, 'field_remove_rsd_wlw' ), 'simple-cf-page-cache', 'scfpc_tweaks' );
		add_settings_field( 'opt_tweaks_key', esc_html__( 'Key', 'speed-of-light-free' ), array( $this, 'field_label_key' ), 'simple-cf-page-cache', 'scfpc_tweaks' );

		add_settings_section( 'scfpc_extras', esc_html__( 'Extras', 'speed-of-light-free' ), null, 'simple-cf-page-cache' );
		add_settings_field( 'opt_dd_branding', esc_html__( 'Enable DigitalDive Branding (Bonus)', 'speed-of-light-free' ), array( $this, 'field_dd_branding' ), 'simple-cf-page-cache', 'scfpc_extras' );
		add_settings_field( 'opt_remove_generator', esc_html__( 'Remove WP Version (Recommended)', 'speed-of-light-free' ), array( $this, 'field_remove_generator' ), 'simple-cf-page-cache', 'scfpc_extras' );
		add_settings_field( 'opt_rss_thumbnails', esc_html__( 'Add Thumbnails to RSS (Bonus)', 'speed-of-light-free' ), array( $this, 'field_rss_thumbnails' ), 'simple-cf-page-cache', 'scfpc_extras' );
		add_settings_field( 'opt_mask_login_errors', esc_html__( 'Mask Login Errors (Recommended)', 'speed-of-light-free' ), array( $this, 'field_mask_login_errors' ), 'simple-cf-page-cache', 'scfpc_extras' );
		add_settings_field( 'opt_clean_admin_footer', esc_html__( 'Clean Admin Footer (Bonus)', 'speed-of-light-free' ), array( $this, 'field_clean_admin_footer' ), 'simple-cf-page-cache', 'scfpc_extras' );
		add_settings_field( 'opt_extras_key', esc_html__( 'Key', 'speed-of-light-free' ), array( $this, 'field_label_key' ), 'simple-cf-page-cache', 'scfpc_extras' );
	}

	public function sanitize_settings( $input ) {
		$existing = $this->plugin->get_options();
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$tab = sanitize_key( $input['active_tab'] ?? 'cloudflare' );

		$fields_by_tab = array(
			'cloudflare' => array( 'cf_email', 'cf_api_key', 'cf_zone_id', 'cf_exclusions', 'cf_ttl', 'cf_cache_header' ),
			'tweaks'     => array(
				'opt_disable_emojis',
				'opt_remove_jqmigrate',
				'opt_disable_xmlrpc',
				'opt_limit_heartbeat',
				'opt_disable_oembed',
				'opt_preload_lcp',
				'opt_preload_css',
				'opt_youtube_facade',
				'opt_lazy_iframes',
				'opt_adsense_boost',
				'opt_disable_wp_embed',
				'opt_remove_shortlink',
				'opt_remove_rsd_wlw',
			),
			'extras'     => array(
				'opt_dd_branding',
				'opt_remove_generator',
				'opt_rss_thumbnails',
				'opt_mask_login_errors',
				'opt_clean_admin_footer',
			),
		);

		$new_input = $existing;

		if ( $tab === 'cloudflare' ) {
			$new_input['cf_email']      = sanitize_email( $input['cf_email'] ?? ( $existing['cf_email'] ?? '' ) );
			$new_input['cf_api_key']    = sanitize_text_field( trim( $input['cf_api_key'] ?? ( $existing['cf_api_key'] ?? '' ) ) );
			$new_input['cf_zone_id']    = sanitize_text_field( trim( $input['cf_zone_id'] ?? ( $existing['cf_zone_id'] ?? '' ) ) );
			$new_input['cf_exclusions'] = sanitize_textarea_field( $input['cf_exclusions'] ?? ( $existing['cf_exclusions'] ?? $this->plugin->get_default_exclusions() ) );
			if ( empty( $new_input['cf_exclusions'] ) ) {
				$new_input['cf_exclusions'] = $this->plugin->get_default_exclusions();
			}
			$allowed_ttls               = array( '3600', '7200', '14400', '86400', '604800', '2592000' );
			$ttl_input                  = sanitize_text_field( $input['cf_ttl'] ?? ( $existing['cf_ttl'] ?? '2592000' ) );
			$new_input['cf_ttl']        = in_array( (string) $ttl_input, $allowed_ttls, true ) ? (string) $ttl_input : '2592000';

			$cache_header = sanitize_key( $input['cf_cache_header'] ?? ( $existing['cf_cache_header'] ?? 'edge-only' ) );
			$presets      = $this->plugin->get_cache_header_presets();
			if ( ! isset( $presets[ $cache_header ] ) ) {
				$cache_header = 'edge-only';
			}
			$new_input['cf_cache_header'] = $cache_header;
		}

		foreach ( $fields_by_tab as $section_tab => $fields ) {
			if ( $section_tab === 'cloudflare' ) {
				continue;
			}

			$process = $tab === $section_tab;
			if ( ! $process ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$new_input[ $field ] = ! empty( $input[ $field ] ) ? 1 : 0;
			}
		}

		return $new_input;
	}

	public function admin_styles() {
		if ( current_user_can( 'manage_options' ) ) {
			$this->output_icon_css();
		}
	}

	public function frontend_styles() {
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			$this->output_icon_css();
		}
	}

	private function output_icon_css() {
		echo '<style>#wp-admin-bar-scfpc-cf-menu .dashicons-cloud { font-family: "dashicons"; } #wp-admin-bar-scfpc-cf-menu.scfpc-active-icon .ab-icon, #wp-admin-bar-scfpc-cf-menu.scfpc-active-icon .ab-icon:before { color: #F48120 !important; } #wp-admin-bar-scfpc-cf-menu.scfpc-inactive-icon .ab-icon, #wp-admin-bar-scfpc-cf-menu.scfpc-inactive-icon .ab-icon:before { color: #aaa !important; } #toplevel_page_dd-speed-of-light .wp-submenu a[href="admin.php?page=dd-speed-of-light-upgrade"] { color: #16a34a !important; font-weight: 600; }</style>';
	}

	public function admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon_class = $this->plugin->is_configured() ? 'scfpc-active-icon' : 'scfpc-inactive-icon';
		$wp_admin_bar->add_node(
			array(
				'id'    => 'scfpc-cf-menu',
				'title' => '<span class="ab-icon dashicons dashicons-cloud"></span> ' . ( $this->plugin->is_configured() ? esc_html__( 'CDN', 'speed-of-light-free' ) : esc_html__( 'CDN (Setup Needed)', 'speed-of-light-free' ) ),
				'href'  => admin_url( 'admin.php?page=dd-speed-of-light' ),
				'meta'  => array( 'class' => $icon_class ),
			)
		);

		if ( $this->plugin->is_configured() ) {
			$ajax_url     = admin_url( 'admin-ajax.php' );
			$confirm_text = esc_js( __( 'Purge entire CDN cache? WARNING: This may temporarily increase server load.', 'speed-of-light-free' ) );
			$purging_text = esc_js( __( 'Purging...', 'speed-of-light-free' ) );
			$purged_text  = esc_js( __( 'Purged Successfully!', 'speed-of-light-free' ) );
			$wp_admin_bar->add_node(
				array(
					'id'     => 'scfpc-purge-all',
					'parent' => 'scfpc-cf-menu',
					'title'  => esc_html__( 'Purge Everything', 'speed-of-light-free' ),
					'href'   => '#',
					'meta'   => array(
						'onclick' => 'if(confirm("' . $confirm_text . '")){ var b=this; b.innerHTML="' . $purging_text . '"; fetch("' . $ajax_url . '?action=scfpc_purge_all&nonce=' . wp_create_nonce( 'scfpc_purge_nonce' ) . '").then(r=>r.json()).then(d=>{ alert(d.success?"' . $purged_text . '":d.data); window.location.reload(); }); } return false;',
					),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'scfpc-settings',
				'parent' => 'scfpc-cf-menu',
				'title'  => esc_html__( 'Settings', 'speed-of-light-free' ),
				'href'   => admin_url( 'admin.php?page=dd-speed-of-light' ),
			)
		);
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=dd-speed-of-light">' . esc_html__( 'Settings', 'speed-of-light-free' ) . '</a>';
		$buy_pro_link  = '<a href="https://speedoflight.pro" target="_blank" rel="noopener noreferrer" style="color:#0e6f62; font-weight:600;">' . esc_html__( 'Buy Pro', 'speed-of-light-free' ) . '</a>';
		return array_merge( array( $settings_link, $buy_pro_link ), $links );
	}

	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'dd-sol-status', esc_html__( 'Speed of Light (Free)', 'speed-of-light-free' ), array( $this, 'render_dashboard_widget' ) );
	}

	public function render_dashboard_widget() {
		$is_active    = $this->plugin->is_configured();
		$status_label = $is_active ? esc_html__( 'Active', 'speed-of-light-free' ) : esc_html__( 'Inactive', 'speed-of-light-free' );
		$status_color = $is_active ? '#166534' : '#6b7280';
		$status_bg    = $is_active ? '#dcfce7' : '#f3f4f6';

		$last_purge  = get_option( 'scfpc_last_rule_update', esc_html__( 'Never', 'speed-of-light-free' ) );
		$ttl_seconds = $this->plugin->get_ttl();

		$ttl_map = array(
			3600    => __( '1 Hour', 'speed-of-light-free' ),
			7200    => __( '2 Hours', 'speed-of-light-free' ),
			14400   => __( '4 Hours', 'speed-of-light-free' ),
			86400   => __( '1 Day', 'speed-of-light-free' ),
			604800  => __( '7 Days', 'speed-of-light-free' ),
			2592000 => __( '30 Days', 'speed-of-light-free' ),
		);
		/* translators: %s: number of seconds */
		$ttl_label = $ttl_map[ $ttl_seconds ] ?? sprintf( __( '~%s seconds', 'speed-of-light-free' ), number_format_i18n( $ttl_seconds ) );

		$expiry_estimate = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() + $ttl_seconds );

		$hit_rate_display = esc_html__( 'N/A', 'speed-of-light-free' );
		$hit_period       = '';
		if ( $is_active ) {
			$stats = $this->cloudflare->get_cache_stats();
			if ( ! is_wp_error( $stats ) && is_array( $stats ) && isset( $stats['hit_rate'] ) ) {
				if ( $stats['hit_rate'] !== null ) {
					$hit_rate_display = number_format_i18n( $stats['hit_rate'], 2 ) . '%';
				}
				$hit_period = $stats['period'] ?? '';
			}
		}
		?>
		<div style="font-size:13px;">
			<div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
				<strong><?php echo esc_html__( 'Status', 'speed-of-light-free' ); ?>:</strong>
				<span style="background: <?php echo esc_attr( $status_bg ); ?>; color: <?php echo esc_attr( $status_color ); ?>; padding:4px 8px; border-radius:99px; font-weight:600;"><?php echo esc_html( $status_label ); ?></span>
			</div>
			<ul style="margin:0; padding-left:16px; line-height:1.6;">
				<li><strong><?php echo esc_html__( 'Cache Hit Rate', 'speed-of-light-free' ); ?>:</strong> <?php echo esc_html( $hit_rate_display ); ?>
				<?php
				if ( $hit_period ) {
					echo ' <span style="color:#6b7280;">(' . esc_html( $hit_period ) . ')</span>'; }
				?>
					</li>
				<li><strong><?php echo esc_html__( 'Last Purge', 'speed-of-light-free' ); ?>:</strong> <?php echo esc_html( $last_purge ); ?></li>
				<li><strong><?php echo esc_html__( 'Edge TTL', 'speed-of-light-free' ); ?>:</strong> <?php echo esc_html( $ttl_label ); ?> <span style="color:#6b7280;">(<?php echo esc_html__( 'theoretical expiry', 'speed-of-light-free' ); ?>: <?php echo esc_html( $expiry_estimate ); ?>)</span></li>
			</ul>
			<?php if ( ! $is_active ) : ?>
				<p style="margin-top:12px; color:#b91c1c;"><?php echo esc_html__( 'Configure Cloudflare credentials to activate caching.', 'speed-of-light-free' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// Settings fields
	public function field_email() {
		echo '<input type="email" name="scfpc_settings[cf_email]" value="' . esc_attr( $this->plugin->get_option( 'cf_email', '' ) ) . '" class="regular-text">';
	}

	public function field_api_key() {
		echo '<input type="password" name="scfpc_settings[cf_api_key]" value="' . esc_attr( $this->plugin->get_option( 'cf_api_key', '' ) ) . '" class="regular-text"><p class="description">' . esc_html__( 'Global API Key (Profile > API Tokens)', 'speed-of-light-free' ) . '</p>';
	}

	public function field_zone_id() {
		echo '<input type="text" name="scfpc_settings[cf_zone_id]" value="' . esc_attr( $this->plugin->get_option( 'cf_zone_id', '' ) ) . '" class="regular-text"><p class="description">' . esc_html__( 'Found on Cloudflare Overview page (bottom right)', 'speed-of-light-free' ) . '</p>';
	}

	public function field_exclusions() {
		echo '<textarea name="scfpc_settings[cf_exclusions]" rows="4" class="large-text code">' . esc_textarea( $this->plugin->get_option( 'cf_exclusions', '' ) ) . '</textarea><p class="description">' . wp_kses_post( __( 'One path per line (e.g. <code>/checkout*</code>). Logged-in users, Admin, and API are ALWAYS excluded automatically.', 'speed-of-light-free' ) ) . '</p>';
	}

	public function field_ttl() {
		$val  = $this->plugin->get_option( 'cf_ttl', '2592000' );
		$opts = array(
			3600    => __( '1 Hour', 'speed-of-light-free' ),
			7200    => __( '2 Hours', 'speed-of-light-free' ),
			14400   => __( '4 Hours', 'speed-of-light-free' ),
			86400   => __( '1 Day', 'speed-of-light-free' ),
			604800  => __( '7 Days', 'speed-of-light-free' ),
			2592000 => __( '30 Days', 'speed-of-light-free' ),
		);
		echo '<select name="scfpc_settings[cf_ttl]">';
		foreach ( $opts as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $val, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
	}

	public function field_cache_header() {
		$current  = $this->plugin->get_option( 'cf_cache_header', 'edge-only' );
		$ttl      = $this->plugin->get_ttl();
		$presets  = $this->plugin->get_cache_header_presets();
		echo '<select name="scfpc_settings[cf_cache_header]" style="min-width:340px;">';
		foreach ( $presets as $key => $preset ) {
			$header_value = sprintf( $preset['header'], $ttl );
			$label        = $preset['label'] . ' - ' . $header_value;
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Pick the Cache-Control header we inject. Edge TTL drives s-maxage; presets tune browser cache time to balance AdSense RPM and page speed.', 'speed-of-light-free' ) . '</p>';
	}


	public function field_disable_wp_embed() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_disable_wp_embed]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_disable_wp_embed', 0 ), false ) . '> ' . esc_html__( 'Disable wp-embed.js on the front end.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_remove_shortlink() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_remove_shortlink]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_remove_shortlink', 0 ), false ) . '> ' . esc_html__( 'Remove shortlink tag from the head.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_remove_rsd_wlw() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_remove_rsd_wlw]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_remove_rsd_wlw', 0 ), false ) . '> ' . esc_html__( 'Remove RSD and WLW manifest links.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_mask_login_errors() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_mask_login_errors]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_mask_login_errors', 0 ), false ) . '> ' . esc_html__( 'Hide detailed login error messages.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_clean_admin_footer() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_clean_admin_footer]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_clean_admin_footer', 0 ), false ) . '> ' . esc_html__( 'Remove WordPress footer text in admin.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_label_key() {
		echo '<div style="font-size:12px; color:#6b7280; line-height:1.5;"><strong>' . esc_html__( 'Key:', 'speed-of-light-free' ) . '</strong> ' . esc_html__( 'Recommended = safe for most sites. Caution = may affect functionality. Bonus = optional extras.', 'speed-of-light-free' ) . '</div>';
	}

	public function field_disable_emojis() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_disable_emojis]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_disable_emojis', 0 ), false ) . '> ' . esc_html__( 'Disable WP Emojis (Recommended)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_remove_jqmigrate() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_remove_jqmigrate]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_remove_jqmigrate', 0 ), false ) . '> ' . esc_html__( 'Remove jQuery Migrate (Caution)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_disable_xmlrpc() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_disable_xmlrpc]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_disable_xmlrpc', 0 ), false ) . '> ' . esc_html__( 'Disable XML-RPC (Recommended)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_limit_heartbeat() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_limit_heartbeat]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_limit_heartbeat', 0 ), false ) . '> ' . esc_html__( 'Limit Admin Heartbeat (Recommended)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_disable_oembed() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_disable_oembed]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_disable_oembed', 0 ), false ) . '> ' . esc_html__( 'Disable oEmbed Discovery (Caution)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_preload_lcp() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_preload_lcp]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_preload_lcp', 0 ), false ) . '> ' . esc_html__( 'Preload LCP Image (Recommended)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_preload_css() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_preload_css]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_preload_css', 0 ), false ) . '> ' . esc_html__( 'Preload Theme CSS (Caution)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_youtube_facade() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_youtube_facade]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_youtube_facade', 0 ), false ) . '> ' . esc_html__( 'Smart YouTube Previews (Recommended)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_lazy_iframes() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_lazy_iframes]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_lazy_iframes', 0 ), false ) . '> ' . esc_html__( 'Aggressive Lazy Load (Caution)', 'speed-of-light-free' ) . '</label>';
	}

	public function field_adsense_boost() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_adsense_boost]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_adsense_boost', 0 ), false ) . '> ' . esc_html__( 'Preconnects to Google Ad servers for faster ad rendering (Higher Viewability = Higher RPM).', 'speed-of-light-free' ) . '</label>';
	}

	public function field_dd_branding() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_dd_branding]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_dd_branding', 0 ), false ) . '> ' . esc_html__( 'Activates the black/white admin theme and custom login page.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_remove_generator() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_remove_generator]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_remove_generator', 0 ), false ) . '> ' . esc_html__( 'Remove the WordPress generator tag.', 'speed-of-light-free' ) . '</label>';
	}

	public function field_rss_thumbnails() {
		echo '<label><input type="checkbox" name="scfpc_settings[opt_rss_thumbnails]" value="1" ' . checked( 1, $this->plugin->get_option( 'opt_rss_thumbnails', 0 ), false ) . '> ' . esc_html__( 'Adds featured images to RSS feed items.', 'speed-of-light-free' ) . '</label>';
	}
}
