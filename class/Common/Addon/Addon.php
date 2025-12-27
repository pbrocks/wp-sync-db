<?php

namespace DeliciousBrains\WPMDB\Common\Addon;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\Helpers;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Pro\Api;
use DeliciousBrains\WPMDB\WPMDBDI;

/**
 * Class Addon
 *
 * Manages addon compatibility and versioning/downloading addons
 *
 * @package DeliciousBrains\WPMDB\Pro
 */
class Addon
{

    /**
     * @var ErrorLog
     */
    private $log;
    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var array
     */
    public $addons;

    /**
     * @var Properties
     */
    protected $props;

    public function __construct(
        ErrorLog $log,
        Settings $settings,
        Properties $properties
    ) {
        $this->props    = $properties;
        $this->log      = $log;
        $this->settings = $settings;

        $this->setAddons();
    }

    /**
     * Get the list of registered addons
     *
     * @return array List of addons with their required versions
     */
    public function getAddons()
    {
        return $this->addons;
    }

    /**
     * Set versions of Addons required for this version of WP Migrate DB Pro
     *
     * Defines the minimum compatible versions for each addon that can
     * be used with the current version of the core plugin.
     *
     * @return void
     */
    public function setAddons()
    {
        $this->addons = array(
            'wp-migrate-db-pro-media-files/wp-migrate-db-pro-media-files.php'               => array(
                'name'             => 'Media Files',
                'required_version' => '1.4.18',
            ),
            'wp-migrate-db-pro-cli/wp-migrate-db-pro-cli.php'                               => array(
                'name'             => 'CLI',
                'required_version' => '1.3.6',
            ),
            'wp-migrate-db-pro-multisite-tools/wp-migrate-db-pro-multisite-tools.php'       => array(
                'name'             => 'Multisite Tools',
                'required_version' => '1.2.7',
            ),
            'wp-migrate-db-pro-theme-plugin-files/wp-migrate-db-pro-theme-plugin-files.php' => array(
                'name'             => 'Theme & Plugin Files',
                'required_version' => '1.0.6',
            ),
        );
    }

    /**
     * Register addon-related hooks and set up addon data
     *
     * Initializes the addon list and allows filtering of temporary table prefix.
     *
     * @return void
     */
    public function register()
    {
        $this->setAddons();

        // allow developers to change the temporary prefix applied to the tables
        $this->props->temp_prefix = apply_filters('wpmdb_temporary_prefix', $this->props->temp_prefix);
    }

    /**
     * Check if an addon version is outdated
     *
     * Compares the installed version of an addon against the minimum required
     * version defined in the addons array.
     *
     * @param string $addon_basename The basename of the addon plugin (e.g., 'wp-migrate-db-pro-cli/wp-migrate-db-pro-cli.php')
     *
     * @return bool True if the addon is outdated, false otherwise
     */
    public function is_addon_outdated($addon_basename)
    {
        $addon_slug = current(explode('/', $addon_basename));

        // If pre-1.1.2 version of Media Files addon, then it is outdated
        if (!isset($GLOBALS['wpmdb_meta'][$addon_slug]['version'])) {
            return true;
        }

        $installed_version = $GLOBALS['wpmdb_meta'][$addon_slug]['version'];
        $required_version  = $this->addons[$addon_basename]['required_version'];

        return version_compare($installed_version, $required_version, '<');
    }

    /**
     * Get the plugin name from WordPress plugin data
     *
     * Retrieves the human-readable plugin name from the plugin headers.
     *
     * @param string|false $plugin Optional. Plugin basename. Defaults to the current plugin.
     *
     * @return string|false The plugin name or false if not found or not in admin
     */
    public function get_plugin_name($plugin = false)
    {
        if (!is_admin()) {
            return false;
        }

        $plugin_basename = (false !== $plugin ? $plugin : $this->props->plugin_basename);

        $plugins = get_plugins();

        if (!isset($plugins[$plugin_basename]['Name'])) {
            return false;
        }

        return $plugins[$plugin_basename]['Name'];
    }

    /**
     * Get the latest available version for a plugin
     *
     * Retrieves the latest stable or beta version from the upgrade data API.
     * Beta versions are only returned if the user has opted in to beta updates.
     *
     * @param string $slug The plugin slug
     *
     * @return string|false The latest version number or false if not available
     */
    public function get_latest_version($slug)
    {
        if ( ! Util::isPro()) {
            return false;
        }

        $data = $this->get_upgrade_data();

        if (!isset($data[$slug])) {
            return false;
        }

        $latest_version = empty ($data[$slug]['version']) ? false : $data[$slug]['version'];

        if (!isset($data[$slug]['beta_version'])) {
            // No beta version available
            return $latest_version;
        }

        if (version_compare($data[$slug]['version'], $data[$slug]['beta_version'], '>')) {
            // Stable version greater than the beta
            return $latest_version;
        }

        if (\DeliciousBrains\WPMDB\Pro\Beta\BetaManager::is_rolling_back_plugins()) {
            // We are in the process of rolling back to stable versions
            return $latest_version;
        }

        //Reload the settings to get fresh beta optin value
        $this->settings->load_settings();

        if (!\DeliciousBrains\WPMDB\Pro\Beta\BetaManager::has_beta_optin($this->settings->get_settings())) {
            // Not opted in to beta updates
            // The required version isn't a beta version
            return $latest_version;
        }

        return $data[$slug]['beta_version'];
    }

    /**
     * Get upgrade data from the Delicious Brains API
     *
     * Retrieves version information for all addons from the API, with caching
     * via transients. Falls back to current version if API request fails.
     *
     * @return array|false Array of upgrade data or false on error
     */
    public function get_upgrade_data()
    {
        $api  = WPMDBDI::getInstance()->get('api');
        $info = get_site_transient('wpmdb_upgrade_data');

        if (isset($info['version'])) {
            delete_site_transient( Helpers::get_licence_response_transient_key() );
            delete_site_transient('wpmdb_upgrade_data');
            $info = false;
        }

        if ($info) {
            return $info;
        }

        $data = $api->dbrains_api_request('upgrade_data');

        $data = json_decode($data, true);

        /*
        We need to set the transient even when there's an error,
        otherwise we'll end up making API requests over and over again
        and slowing things down big time.
        */
        $default_upgrade_data = array('wp-migrate-db-pro' => array('version' => $GLOBALS['wpmdb_meta'][$this->props->core_slug]['version']));

        if (!$data) {
            set_site_transient('wpmdb_upgrade_data', $default_upgrade_data, $this->props->transient_retry_timeout);
            $this->log->log_error('Error trying to decode JSON upgrade data.');

            return false;
        }

        if (isset($data['errors'])) {
            set_site_transient('wpmdb_upgrade_data', $default_upgrade_data, $this->props->transient_retry_timeout);
            $this->log->log_error('Error trying to get upgrade data.', $data['errors']);

            return false;
        }

        set_site_transient('wpmdb_upgrade_data', $data, $this->props->transient_timeout);

        return $data;
    }

}
