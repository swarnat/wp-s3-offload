<?php

namespace WPS3\S3\Offload;

class Loader
{

    private static Syncer $Syncer;

    public static function getSyncer(): Syncer
    {
        return self::$Syncer;
    }

    public function run()
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'content-replacement.php');
            require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'admin.php');

            $admin = new Admin();
            $admin->load_hooks();
        }

        require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'syncer.php');
        require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'frontend.php');

        $frontend = new Frontend();
        $frontend->load_hooks();

        self::$Syncer = new Syncer();
        // self::$Syncer->registerStreamWrapper();

        // get_attached_file
        self::$Syncer->load_hooks();

        // 
    }
}
