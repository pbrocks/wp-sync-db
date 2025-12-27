<?php

/**
 * Class WPMDB_PHP_Checker
 *
 * Validates PHP version compatibility and disables the plugin if requirements aren't met.
 *
 * Checks if the site's PHP version meets the minimum requirement. If not, the plugin is
 * deactivated with an appropriate notice shown to the user.
 *
 * To increase the required PHP version, update:
 * 1. The WPMDB_MINIMUM_PHP_VERSION constant in setup-plugin.php
 * 2. The "Requires PHP" header in wp-sync-db.php
 * 3. All documentation (README.md, readme.txt, etc.)
 *
 * @see https://pento.net/2014/02/18/dont-let-your-plugin-be-activated-on-incompatible-sites/
 */
class WPMDB_PHP_Checker {

	/**
	 * Path to the main plugin file
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Base error message template
	 *
	 * @var string
	 */
	public static $base_message;

	/**
	 * Link to PHP upgrade documentation
	 *
	 * @var string
	 */
	public static $php_doc_link;

	/**
	 * Minimum required PHP version
	 *
	 * @var string
	 */
	public static $min_php;

	/**
	 * Constructor
	 *
	 * Initializes the PHP version checker and registers admin hooks.
	 *
	 * @param string $path    Path to the main plugin file
	 * @param string $min_php Minimum required PHP version
	 */
	public function __construct( $path, $min_php ) {
		$this->path         = $path;

		self::$min_php      =  $min_php; // To increase the minimum PHP required, change this value _AND_ WPMDB_MINIMUM_PHP_VERSION in the main plugin files

		// This string is not translated. It is used so early in the plugin load
		// process that translations are not loaded and can not properly
		// be loaded.
		self::$base_message = '%s requires PHP version %s or higher and cannot be activated. You are currently running version %s. <a href="%s">Learn&nbsp;More&nbsp;»</a>';
		self::$php_doc_link = 'https://deliciousbrains.com/wp-migrate-db-pro/doc/upgrading-php/';

		add_action( 'admin_init', array( $this, 'maybe_deactivate_plugin' ) );
	}

	/**
	 * Display error and die when PHP version is too low for WP Migrate Lite
	 *
	 * Called during plugin activation if PHP version check fails.
	 *
	 * @return void Dies with error message
	 */
	public static function wpmdb_php_version_too_low() {
		wp_die( sprintf( self::$base_message, 'WP Migrate Lite', self::$min_php, PHP_VERSION, self::$php_doc_link ) );
	}

	/**
	 * Display error and die when PHP version is too low for WP Migrate Pro
	 *
	 * Called during plugin activation if PHP version check fails.
	 *
	 * @return void Dies with error message
	 */
	public static function wpmdb_pro_php_version_too_low() {
		wp_die( sprintf( self::$base_message, 'WP Migrate', self::$min_php, PHP_VERSION, self::$php_doc_link ) );
	}

	/**
	 * Deactivate the plugin if PHP version is incompatible
	 *
	 * Checks if the plugin is active and the PHP version is below minimum.
	 * If so, deactivates the plugin and displays an admin notice.
	 *
	 * @return void
	 */
	public function maybe_deactivate_plugin() {
		if ( version_compare( PHP_VERSION, self::$min_php, '>=' ) || ! is_plugin_active( plugin_basename( $this->path ) ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( $this->path ) );
		add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	/**
	 * Check if PHP version is compatible
	 *
	 * Compares the current PHP version against the minimum required version.
	 *
	 * @return bool True if compatible, false otherwise
	 */
	public function is_compatible_check() {
		if ( version_compare( PHP_VERSION, self::$min_php, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice when plugin is disabled due to PHP version
	 *
	 * Shows a styled warning notice informing the user that the plugin
	 * was deactivated due to incompatible PHP version.
	 *
	 * @return void
	 */
	public function disabled_notice() {
		$str = '
		<div class="updated" style="border-left: 4px solid #ffba00;">
				<p>%s %s</p>
		</div>';

		$plugin  = 'wp-migrate-db-pro.php' === basename( $this->path ) ? __( 'WP Migrate' ) : __( 'WP Migrate Lite' );
		$message = sprintf( __( 'requires PHP version %s or higher to run and has been deactivated. You are currently running version %s. <a href="%s">Learn More »</a>', 'wp-sync-db' ), self::$min_php, PHP_VERSION, self::$php_doc_link );

		echo sprintf( $str, $plugin, $message );
	}
}
