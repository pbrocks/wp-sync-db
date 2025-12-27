<?php

namespace DeliciousBrains\WPMDB\Common\Addon;

use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Properties\Properties;

/**
 * Abstract base class for WP Migrate DB addons
 *
 * Provides common functionality for addon version checking, licensing,
 * and compatibility validation.
 *
 * @package DeliciousBrains\WPMDB\Common\Addon
 */
abstract class AddonAbstract
{

    /**
     * Minimum required core plugin version
     *
     * @var string
     */
    protected $version_required;

    /**
     * Plugin properties instance
     *
     * @var Properties
     */
    protected $properties;

    /**
     * Addon manager instance
     *
     * @var Addon
     */
    protected $addon;

    /**
     * Dynamic properties instance
     *
     * @var DynamicProperties
     */
    protected $dynamic_properties;

    /**
     * Plugin slug (e.g., 'wp-migrate-db-pro-cli')
     *
     * @var string
     */
    protected $plugin_slug;

    /**
     * Installed addon version
     *
     * @var string
     */
    protected $plugin_version;

    /**
     * Human-readable addon name
     *
     * @var string
     */
    protected $addon_name;

    /**
     * Plugin basename (e.g., 'plugin-folder/plugin-file.php')
     *
     * @var string|false
     */
    protected $plugin_basename = false;

    /**
     * Whether the addon is licensed
     *
     * @var bool
     */
    protected $licensed = false;

    /**
     * Constructor
     *
     * Initializes addon with dependency injection.
     *
     * @param Addon      $addon      Addon manager instance
     * @param Properties $properties Plugin properties
     */
    function __construct(
        Addon $addon,
        Properties $properties
    ) {
        $this->addon                        = $addon;
        $this->properties                   = $properties;
        $this->dynamic_properties           = DynamicProperties::getInstance();
        $this->dynamic_properties->is_addon = true;
    }

    /**
     * Check if addon meets version requirements
     *
     * Validates that both the core plugin and this addon meet the minimum
     * version requirements for compatibility.
     *
     * @param string $version_required Minimum required core plugin version
     *
     * @return bool True if requirements are met, false otherwise
     */
    function meets_version_requirements($version_required)
    {
        $wpmdb_pro_version      = $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'];
        $result                 = version_compare($wpmdb_pro_version, $version_required, '>=');
        $this->version_required = $version_required;


        if ($result) {
            // If pre-1.1.2 version of Media Files addon,
            // then it's not supported by this version of core
            if (empty($this->properties->plugin_version)) {
                $result = false;
            } else { // Check that this version of core supports the addon version
                $plugin_basename        = sprintf('%1$s/%1$s.php', $this->plugin_slug);
                $this->plugin_basename  = $plugin_basename;
                $required_addon_version = $this->addon->getAddons()[$plugin_basename]['required_version'];
                $result                 = version_compare($this->properties->plugin_version, $required_addon_version, '>=');
            }
        }

        if (false == $result) {
            $this->hook_version_requirement_actions();

        }

        return $result;
    }

    /**
     * Register hooks for version requirement notifications
     *
     * Adds filter to display version mismatch warnings.
     *
     * @return void
     */
    function hook_version_requirement_actions()
    {
        add_filter('wpmdb_notification_strings', array($this, 'version_requirement_actions'));
    }

    /**
     * Add version requirement notification
     *
     * Clears relevant transients and adds a notification about version requirements.
     *
     * @param array $notifications Array of notifications
     *
     * @return array Modified notifications array
     */
    function version_requirement_actions($notifications)
    {
        $addon_requirement_check = get_site_option('wpmdb_addon_requirement_check', array());

        // we only want to delete the transients once, here we keep track of which versions we've checked
        if (!isset($addon_requirement_check[$this->properties->plugin_slug]) || $addon_requirement_check[$this->properties->plugin_slug] != $GLOBALS['wpmdb_meta'][$this->properties->plugin_slug]['version']) {
            delete_site_transient('wpmdb_upgrade_data');
            delete_site_transient('update_plugins');
            $addon_requirement_check[$this->properties->plugin_slug] = $GLOBALS['wpmdb_meta'][$this->properties->plugin_slug]['version'];
            update_site_option('wpmdb_addon_requirement_check', $addon_requirement_check);
        }

        $notice_id = $this->plugin_basename . '-notice';

        $notifications[$notice_id] = [
            'message' => $this->version_requirement_warning(),
            'link'    => false,
            'id'      => $notice_id,
        ];

        return $notifications;
    }

    /**
     * Generate version requirement warning message
     *
     * Creates a formatted warning message indicating the version mismatch
     * and providing an update link.
     *
     * @return string HTML warning message
     */
    function version_requirement_warning()
    {
        $str = '<strong>Update Required</strong> &mdash; ';

        $addon_name     = $this->addon_name;
        $required       = $this->version_required;
        $installed      = $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'];
        $wpmdb_basename = sprintf('%s/%s.php', $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['folder'], 'wp-sync-db');
        $update         = wp_nonce_url(network_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($wpmdb_basename)), 'upgrade-plugin_' . $wpmdb_basename);
        $str            .= sprintf(__('The version of %1$s you have installed requires version %2$s of WP Migrate. You currently have %3$s installed. <strong><a href="%4$s">Update Now</a></strong>', 'wp-sync-db'), $addon_name, $required, $installed, $update);

        return $str;
    }


    /**
     * Set the licensed status of the addon
     *
     * @param bool $is_licensed Whether the addon is licensed
     *
     * @return void
     */
    public function set_licensed($is_licensed)
    {
        $this->licensed = $is_licensed;
    }

}
