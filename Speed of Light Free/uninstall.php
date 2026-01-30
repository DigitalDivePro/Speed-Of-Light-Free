<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Speed_Of_Light_FPC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'scfpc_settings' );
delete_option( 'scfpc_last_rule_update' );
