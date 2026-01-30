<?php
/**
 * Plugin Name: Speed of Light (Free)
 * Description: Conservative Cloudflare full-page caching that is safe for any type of website.
 * Version: 1.1.0
 * Author: DigitalDive
 * Author URI: https://digitaldive.pro
 * Text Domain: speed-of-light-free
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DD_SOL_FPC_VERSION', '1.1.0' );
define( 'DD_SOL_FPC_FILE', __FILE__ );
define( 'DD_SOL_FPC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DD_SOL_FPC_URL', plugin_dir_url( __FILE__ ) );
define( 'DD_SOL_FPC_BASENAME', plugin_basename( __FILE__ ) );
define( 'DD_SOL_FPC_TEXTDOMAIN', 'speed-of-light-free' );

require_once DD_SOL_FPC_PATH . 'includes/class-dd-speed-of-light-fpc.php';

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain(
			'speed-of-light-free',
			false,
			dirname( DD_SOL_FPC_BASENAME ) . '/languages'
		);
	}
);

function dd_speed_of_light_fpc() {
	return DD_SpeedOfLight_FPC::instance();
}

dd_speed_of_light_fpc();

register_activation_hook(
	__FILE__,
	function () {
		if ( get_option( 'scfpc_settings', null ) === null ) {
			add_option( 'scfpc_settings', array(), '', 'no' );
		}
	}
);
