<?php
/**
 * Plugin Name: WPD Migration Plugin
 * Version: 1.0.0
 * Author: Stefan Warnat
 * License: GPL
 * Text Domain: wpd-migration
 */

add_action('init', function() {
    if(is_admin()) {
        require_once(__DIR__ . '/includes/AdminPage.php');
    }
});