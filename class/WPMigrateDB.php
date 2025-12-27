<?php

namespace DeliciousBrains\WPMDB;

use DeliciousBrains\WPMDB\Common\BackupExport;
use DeliciousBrains\WPMDB\Common\Compatibility\CompatibilityManager;
use DeliciousBrains\WPMDB\Common\Migration\Flush;
use DeliciousBrains\WPMDB\Common\Plugin\Assets;
use DeliciousBrains\WPMDB\Common\Profile\ProfileManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\SettingsManager;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Common\MF\MediaFilesAddon;
use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesAddon;

class WPMigrateDB
{

    /**
     * @var CompatibilityManager
     */
    private $compatibility_manager;
    /**
     * @var Properties
     */
    private $props;
    /**
     * @var Util
     */
    private $util;
    /**
     * @var ProfileManager
     */
    private $profile_manager;
    /**
     * @var BackupExport
     */
    private $backup_export;
    /**
     * @var SettingsManager
     */
    private $settings_manager;
    /**
     * @var Assets
     */
    private $assets;
    /**
     * @var Flush
     */
    private $flush;
    /**
     * @var MediaFilesAddon
     */
    private $media_files_addon;
    /**
     * @var ThemePluginFilesAddon
     */
    private $tp_addon;

    public function __construct($pro = false) { }

    public function register()
    {
        $container = WPMDBDI::getInstance();

        $this->props = $container->get(Properties::class);

        $this->util                  = $container->get(Util::class);
        $this->profile_manager       = $container->get(ProfileManager::class);
        $this->flush                  = $container->get(Flush::class);
        $this->backup_export         = $container->get(BackupExport::class);
        $this->compatibility_manager = $container->get(CompatibilityManager::class);
        $this->settings_manager      = $container->get(SettingsManager::class);
        $this->assets                = $container->get(Assets::class);
        $this->media_files_addon     = $container->get(MediaFilesAddon::class);
        $this->tp_addon              = $container->get(ThemePluginFilesAddon::class);

        add_action('init', array($this, 'loadPluginTextDomain'));
        // For Firefox extend "Cache-Control" header to include 'no-store' so that refresh after migration doesn't override JS set values.
        add_filter('nocache_headers', array($this->util, 'nocache_headers'));

        $this->profile_manager->register();
        $this->backup_export->register();
        $this->compatibility_manager->register();
        $this->settings_manager->register();
        $this->assets->register();
        $this->flush->register();

        // Enable addons for this fork
        $this->media_files_addon->set_licensed(true);
        $this->tp_addon->set_licensed(true);
        $this->media_files_addon->register();
        $this->tp_addon->register();
    }

    public function loadPluginTextDomain()
    {
        load_plugin_textdomain('wp-migrate-db', false, dirname(plugin_basename($this->props->plugin_file_path)) . '/languages/');
    }
}
