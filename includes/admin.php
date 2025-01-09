<?php

namespace WPS3\S3\Offload;

use WP_Query;

class Admin
{
    private static $ActivePlugins = [];

    public function load_hooks()
    {

        add_filter('admin_init', [$this, 'admin_init'], 999);
        add_filter('media_row_actions', [$this, 'media_row_actions'], 10, 3);

        add_action('admin_post_s3_sync', [$this, 'admin_post_s3_sync'], 10, 0);
        add_action('admin_post_s3_sync_batch', [$this, 'admin_post_s3_sync_batch'], 10, 0);
        add_action('admin_post_s3_test_connection', [$this, 'admin_post_s3_test_connection'], 10, 0);

        add_action('delete_attachment', [$this, 'handle_delete_media'], 10);

        // attach js to sync media in library
        add_action('admin_enqueue_scripts', [$this, 'register_wp_admin_scripts']);

        add_action('admin_menu', array($this, 'admin_menu'));
        
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'wp_generate_attachment_metadata' ], 10, 3 );
        // add_filter( 'wp_handle_upload_prefilter', [ $this, 'before_media_upload' ] );
        // add_filter( 'wp_handle_sideload_prefilter', [ $this, 'before_media_upload' ] );
        
        // add_action( 'add_attachment', [ $this, 'after_media_upload' ] );

        $this->checkConfiguration();
    }

    public function admin_init() {
        $this->apply_workarounds();
    }
    public function admin_menu()
    {
        add_options_page('WP S3 Media', 'WP S3 Media', 'manage_options', 'wp-s3-media-offloading', array($this, 'options_page'));
    }

    public function is_plugin_active($plugin) {
        return in_array( $plugin, self::$ActivePlugins, true ) || is_plugin_active_for_network( $plugin );
    }

    public function apply_workarounds() {
        self::$ActivePlugins = (array) get_option( 'active_plugins', array() );

        ######## 
        $plugin = "wp-optimize/wp-optimize.php";
        if($this->is_plugin_active($plugin)) {
            $manager = \Updraft_Smush_Manager();
            remove_filter('manage_media_columns', array($manager, 'manage_media_columns'));
        }

        ######## 

        $plugin = "shortpixel-image-optimiser/wp-shortpixel.php";
        if($this->is_plugin_active($plugin)) {
            // enable the offloading mode for plugin
            add_filter('shortpixel/file/virtual/heavy_features', "__return_false");
        }
    }


    public function wp_generate_attachment_metadata($metadata, $attachment_id, $type)
    {

        $syncer = Loader::getSyncer();
        $metadata = $syncer->syncAfterUpload($attachment_id, $metadata);

        // remove_filter( 'upload_dir', [$syncer, 'upload_dir'], 999, 1 );
        
        return $metadata;

    }


    public function options_page()
    {
        $mandatoryConstants = ['WPS3_KEY', 'WPS3_SECRET', 'WPS3_BUCKET', 'WPS3_REGION'];

        $missing = [];
        foreach ($mandatoryConstants as $constant) {
            if (defined($constant) === false) {
                $missing[] = $constant;
            }
        }

        if (!empty($missing)) {
?>
            <div class="error notice" style="background-color:#d63638; color:white;">
                <p><strong>Missing required configuration keys</strong></p>
                <p><?php _e('Please define at least the following configuration variables: ' . implode(', ', $missing), 'my_plugin_textdomain'); ?></p>
                <pre style="background-color:#eee; color:#555;padding:10px; border:1px solid #555;">
define('WPS3_KEY', 'ACCESSID');
define('WPS3_SECRET', 'SECRETKEY');
define('WPS3_BUCKET', 'BUCKETNAME');
define('WPS3_REGION', 'eu-de');

define('WPS3_ENDPOINT', 'S3-ENDPOINT');
// define('WPS3_URL_PREFIX', 'CUSTOM-BUCKET-URL');
                    </pre>
                <a href="https://github.com/swarnat/wp-s3-offloading?tab=readme-ov-file#setup" target="_blank">read documentation</a>
            </div>
        <?php
            return;
        }

        try {
            $syncerObj = new Syncer();
            $syncerObj->checkConnection();
        } catch (\Exception $exp) {
        ?>
            <div class="error notice">
                <p><strong>Error during S3 connection check</strong></p>
                <p><?php echo $exp->getMessage(); ?></p>
            </div>
            <?php
            return;
        }


        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'inherit',
            'meta_query' => array(
                array(
                    'key' => 's3_public_url',
                    'compare' => 'NOT EXISTS'
                ),
            )
        );

        $the_query = new WP_Query($args);

        $missing_number = $the_query->found_posts;

        require_once(WPS3_PLUGIN_BASE_DIR . 'templates/admin-options.php');
    }

    private function checkConfiguration()
    {
        $mandatoryConstants = ['WPS3_KEY', 'WPS3_SECRET', 'WPS3_BUCKET', 'WPS3_REGION'];

        $missing = [];
        foreach ($mandatoryConstants as $constant) {
            if (defined($constant) === false) {
                $missing[] = $constant;
            }
        }

        if (!empty($missing)) {
            add_action('admin_notices', function () use ($missing) {
            ?>
                <div class="error notice">
                    <p><strong>Missing required configuration keys</strong></p>
                    <p><?php _e('Please define at least the following configuration variables: ' . implode(', ', $missing), 'my_plugin_textdomain'); ?></p>
                    <a href="https://github.com/swarnat/wp-s3-offloading?tab=readme-ov-file#setup" target="_blank">read documentation</a>
                </div>
<?php
            });
        }
    }

    function register_wp_admin_scripts()
    {

        wp_enqueue_script('wp-s3-offloading-admin', plugins_url('resources/js/admin.js', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'wp-s3-offload.php'), array('jquery'), '1.0.0', true);

        wp_localize_script(
            'wp-s3-offloading-admin',
            'wps3_ajax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'title' => get_the_title()
            )
        );

        
    }

    public function handle_delete_media($attachment_id)
    {
        $file_url = get_post_meta($attachment_id, 's3_public_url', true);

        if ($this->is_upload_to_s3($attachment_id)) {
            $syncerObj = new Syncer();

            $metadata = wp_get_attachment_metadata($attachment_id);

            if (!empty($metadata['s3']['key'])) {
                $syncerObj->delete_attachment($metadata['s3']['key']);
            }

            delete_post_meta($attachment_id, 's3_public_url');

            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size) {
                    if (!empty($size['s3'])) {
                        $syncerObj->delete_attachment($size['s3']['key']);
                    }
                }
            }
        }
    }

    private function is_upload_to_s3($post_id)
    {
        $s3_public_url = get_post_meta($post_id, 's3_public_url', true);

        if (empty($s3_public_url)) {
            return false;
        }

        return true;
    }

    public function media_row_actions($actions, $post, $detached)
    {
        $s3_public_url = get_post_meta($post->ID, 's3_public_url', true);

        if(empty($s3_public_url)) {
            $actions['s3sync'] = sprintf(
                '<a href="#" class="s3-sync-file-btn" data-url="%s">%s</a>',
                wp_nonce_url("admin-post.php?action=s3_sync&amp;post=$post->ID", 'sync-s3-' . $post->ID),
                __('Sync Storage')
            );
        }

        return $actions;
    }

    public function admin_post_s3_test_connection()
    {
        try {
            $syncerObj = new Syncer();
            $testResult = $syncerObj->testConnection();

        } catch (\Exception $exp) {
            echo $exp->getMessage();
            exit();
        }

        echo "OK";
    }

    public function admin_post_s3_sync_batch()
    {
        $response = [
            'done' => [],
        ];

        $attachments = get_posts( array(        
            'post_type' => 'attachment',
            'posts_per_page' => 5,
            'meta_query' => array(
				array(
					'key'     => 's3_public_url',
					'compare' => 'NOT EXISTS'
				)
			)
        ) );
        
        $syncerObj = new Syncer();

        foreach($attachments as $attachment) {

            $response['done'][] = array(
                'id' => $attachment->ID,
                'name' => $attachment->post_name
            );

            $syncerObj->sync($attachment->ID);            
        }

        $contentReplacementInstance = ContentReplacement::getInstance();
        $contentReplacementInstance->processReplacedContent();

        echo json_encode($response);
    }

    public function admin_post_s3_sync()
    {
        $attachment_id = (int)$_REQUEST['post'];

        $syncerObj = new Syncer();
        $syncerObj->sync($attachment_id);

        $contentReplacementInstance = ContentReplacement::getInstance();
        $contentReplacementInstance->processReplacedContent();

    }

}
