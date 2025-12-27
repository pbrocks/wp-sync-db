<?php
/**
 * Plugin Name: WP Sync DB PB
 * Plugin URI: https://github.com/pbrocks/wp-sync-db
 * Description: Forked from WPEngine's Migrate DB. Sync your prodcution database with local. Export full sites including media, themes, and plugins. Find and replace content with support for serialized data.
 * Author: pbrocks
 * Version: 3.0.5
 * Author URI: https://github.com/pbrocks
 * Update URI: false
 * Network: True
 * Text Domain: wp-sync-db
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Suppress PHP 8+ deprecation notices and WordPress translation timing notices
if ( ! defined( 'WPMDB_SUPPRESS_DEPRECATIONS' ) ) {
    define( 'WPMDB_SUPPRESS_DEPRECATIONS', true );
}
if ( WPMDB_SUPPRESS_DEPRECATIONS ) {
    error_reporting( error_reporting() & ~E_DEPRECATED & ~E_USER_NOTICE );
}

// Set plugin version from header
if ( ! function_exists( 'get_plugin_data' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$wpmdb_plugin_data = get_plugin_data( __FILE__, false, false );
$GLOBALS['wpmdb_meta']['wp-migrate-db']['version'] = $wpmdb_plugin_data['Version'];

if ( ! defined( 'WPMDB_FILE' ) ) {
	// Defines the path to the main plugin file.
	define( 'WPMDB_FILE', __FILE__ );

	// Defines the path to be used for includes.
	define( 'WPMDB_PATH', plugin_dir_path( WPMDB_FILE ) );
}

// TODO: Replace with checked-in prefixed libraries >>>
// NOTE: This path is updated during the build process.
$plugin_root = '/';

if ( ! defined( 'WPMDB_VENDOR_DIR' ) ) {
	define( 'WPMDB_VENDOR_DIR', __DIR__ . $plugin_root . "vendor" );
}

require WPMDB_VENDOR_DIR . '/autoload.php';
// TODO: Replace with checked-in prefixed libraries <<<

require 'setup-plugin.php';

if ( version_compare( PHP_VERSION, WPMDB_MINIMUM_PHP_VERSION, '>=' ) ) {
	require_once WPMDB_PATH . 'class/autoload.php';
	require_once WPMDB_PATH . 'setup-mdb.php';
}

function wpmdb_remove_mu_plugin() {
	do_action( 'wp_migrate_db_remove_compatibility_plugin' );
}

if ( file_exists( WPMDB_PATH . 'ext/wpmdb-ext-functions.php' ) ) {
	require_once WPMDB_PATH . 'ext/wpmdb-ext-functions.php';
}
