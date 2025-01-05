<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP Media S3 offloading
 * Version:           1.2.2
 * Description:		  Upload and manage files on S3 Storage to Offload media
 * Author:            Stefan Warnat
 * License:           LGPL-3.0+
 * License URI:       http://www.gnu.org/licenses/lgpl-3.0.txt
 * Text Domain:       wp-s3-offload
 * Domain Path:       /languages
 */

use WPS3\S3\Offload\Loader;

define( 'WPS3_PLUGIN_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPS3_PLUGIN_BASE_URL', plugin_dir_url( __FILE__ ) );

require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'loader.php');

$loader = new Loader();
$loader->run();