<?php
namespace WPS3\S3\Offload;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use WP_Error;
use WPS3\S3\Offload\Cache\File;
use WPS3\S3\Offload\Cache\Redis;

class Syncer
{
    /**
     * S3Client
     *
     * @var S3Client
     */
    private $s3;

    private $options;

    private static $NOCACHEMODE = true;

    private static $REGISTERED_STREAM = false;
    private static $s3DataCache = [];    

    private static $BaseDir = "";

    public function __construct()
    {
        $this->options = $this->getOptions();

        require_once(WPS3_PLUGIN_BASE_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

        $this->s3 = new S3Client([
            'version'          => 'latest',
            'region'           => $this->options['region'],
            'bucket'            => $this->options['bucket'],
            'endpoint'            => $this->options['endpoint'],
            'use_path_style_endpoint' => !empty($this->options['pathstyle']),

            'credentials'      => [
                'key'    => $this->options['aws_key'],
                'secret' => $this->options['aws_secret'],
            ],
            'signatureVersion' => 'v4',
        ]);

        $uploadDir = wp_get_upload_dir();
        self::$BaseDir = $uploadDir["basedir"];
    }

    public function upload($localFilename, $targetFilename)
    {

        $this->registerStreamWrapper();

        $copy_result = copy($localFilename, 's3://' . $this->options['bucket'] . '/' . $targetFilename);

        if (! $copy_result) {
            return new WP_Error('unable-to-copy-to-s3', 'Unable to copy the temp image to S3');
        }
    }

    public function load_hooks()
    {

        add_filter('get_attached_file', [$this, 'get_attached_file'], 1, 2);
        // add_filter('wp_get_attachment_metadata', [$this, 'wp_get_attachment_metadata'], 1, 2);
    }

    public function wp_get_attachment_metadata($metadata, $attachment_id)
    {

        if (
            !empty($metadata["s3_public_url"]) &&
            !is_file(self::$BaseDir . DIRECTORY_SEPARATOR . $metadata["file"])
        ) {

            $targetFile = self::$BaseDir . DIRECTORY_SEPARATOR . $metadata["file"];

            if (!is_file($targetFile)) {

                $response = wp_remote_get($metadata["s3_public_url"]);

                file_put_contents($targetFile, $response["body"]);
            }
        }

        return $metadata;
    }

    public function get_attached_file($file, $attachment_id)
    {
        if(!empty($attachment_id) && !empty(self::$s3DataCache[$attachment_id])) {
            return self::$s3DataCache[$attachment_id];
        }

        if (!empty($file) && file_exists($file)) {
            $s3_public_url = get_post_meta($attachment_id, 's3_public_url', true);
        } else {
            $s3_public_url = false;
        }

        if (!empty($file) && (!empty($s3_public_url) || !file_exists($file))) {
            $this->registerStreamWrapper();

            $relPath = get_post_meta($attachment_id, '_wp_attached_file', true);

            self::$s3DataCache[$attachment_id] = 's3://' . $this->options['bucket'] . '/' . $relPath;

            return self::$s3DataCache[$attachment_id];
        }

        return $file;
    }


    public function upload_dir($dir)
    {
        $folder = trim($this->options['folder'], '/');
        if (!empty($folder)) $folder .= '/';

        $this->registerStreamWrapper();

        $dir['path'] = 's3://' . rtrim($this->options['bucket'] . '/' . $folder, "/") . $dir['subdir'];
        $dir['path'] = rtrim($dir['path'], "/");

        $dir['url'] = trim($this->options['url_prefix'], ' /') . '/' . $folder . trim($dir['subdir'], '/ ');

        $dir['basedir'] = 's3://' . rtrim($this->options['bucket'] . '/' . $folder, "/");
        $dir['baseurl'] = trim($this->options['url_prefix'], ' /') . '/' . $folder;

        // echo "<pre>";
        // var_dump($dir);

        return $dir;
    }

    private function getOptions()
    {

        if (!defined('WPS3_URL_PREFIX')) {
            if (defined('WPS3_PATHSTYLE') && constant('WPS3_PATHSTYLE') == true) {
                define('WPS3_URL_PREFIX', rtrim(WPS3_ENDPOINT, '/') . WPS3_BUCKET);
            } else {
                define('WPS3_URL_PREFIX', str_replace('https://', 'https://' . WPS3_BUCKET . '.', WPS3_ENDPOINT));
            }
        }

        return array(
            'type'       => defined('WPS3_PROVIDER') ? constant('WPS3_PROVIDER') : 'aws',
            'aws_key'    => constant('WPS3_KEY'),
            'aws_secret' => constant('WPS3_SECRET'),
            'bucket'     => constant('WPS3_BUCKET'),
            'region'     => constant('WPS3_REGION'),
            'folder'     => defined('WPS3_FOLDER') ? constant('WPS3_FOLDER') : '',

            'url_prefix' => rtrim(constant('WPS3_URL_PREFIX'), '/') . '/',

            'endpoint'     => defined('WPS3_ENDPOINT') ? constant('WPS3_ENDPOINT') : '',
            'pathstyle'     => defined('WPS3_PATHSTYLE') ? constant('WPS3_PATHSTYLE') == true : false,

        );
    }

    public function getCacheHandler() {

        if(defined("WP_REDIS_HOST")) {

            require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR. 'Redis.php');

            $cacheHandler = new Redis();

            return;

        } else {
        
            require_once(WPS3_PLUGIN_BASE_DIR . 'includes' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR. 'File.php');

            $hash = sha1(getcwd());
            $tempDir = sys_get_temp_dir();
            $cacheHandler = new File($tempDir . DIRECTORY_SEPARATOR . "wp-" . $hash);

            return $cacheHandler;

        }
    }

    public function registerStreamWrapper()
    {
        if(self::$REGISTERED_STREAM === false) {
            // echo "<pre>";debug_print_backtrace(0, 10);
            StreamWrapper::register(
                $this->s3,
                's3',
                $this->getCacheHandler(),
                true
            );

            self::$REGISTERED_STREAM = true;
        }
    }

    public function delete_attachment($key)
    {

        try {
            $this->s3->deleteObject([
                'Bucket' => $this->options['bucket'],
                'Key'    => $key,
            ]);
        } catch (S3Exception $exp) {
            throw $exp;
        }
    }

    public function syncAfterUpload($attachment_id, $metadata)
    {
        global $wpdb;

        $metadata = $this->sync($attachment_id, $metadata);

        if(!empty($metadata["s3_public_url"])) {
            $wpdb->update( $wpdb->posts, [ 'guid' => $metadata["s3_public_url"] ], [ 'ID' => $attachment_id ] );
        }

        return $metadata;
    }

    public function uploadToS3($local_filename, $target_filekey, $mimetype = null)
    {
        if(!is_file($local_filename)) {
            return;
        }

        if($mimetype === null) {
            $mimetype = mime_content_type($local_filename);
        }

        $params = [
            'Bucket' => $this->options['bucket'],
            'Key' => $target_filekey,
            'Body' => file_get_contents($local_filename),
            'ContentType' => $mimetype
        ];
        
        try {
            $this->s3->PutObject($params);
        } catch (S3Exception $exception) {
            throw $exception;
        }
    }

    public function testConnection() {
        $testString = "TEST" . sha1(mt_rand(100000,999999));
        $filename = mt_rand(100000,999999);

        $testfile = tempnam(sys_get_temp_dir(), 'S3Test');
        file_put_contents($testfile, $testString);

        $targetKey = "test/testfile" . $filename;
        $this->uploadToS3($testfile, $targetKey);

        $newUrl = $this->s3->getObjectUrl($this->options['bucket'], $targetKey);

        if(trim(file_get_contents($newUrl)) != $testString) {
            throw new \Exception("UPload test not successfully");
        }

        $this->s3->deleteObject([
            'Bucket' => $this->options['bucket'],
            'Key'    => $targetKey,
        ]);

        return true;

    }

    public function checkConnection() {
        $params = [
            "Bucket" => $this->options['bucket'],
            "MaxKeys" => 1
        ];

        $result = $this->s3->listObjects( $params );
    }
        
    /**
     * Called to sync an existing media file
     * 
     * @param $attachment_id int the Attachment to sync
     * @param $metadata array If set after upload, then these metadata are used instead of postmeta
     * 
     */
    public function sync($attachment_id, $metadata = null)
    {
        $oldGuid = get_the_guid($attachment_id);

        $contentReplacementInstance = ContentReplacement::getInstance();

        $this->registerStreamWrapper();

        $folder = trim($this->options['folder'], '/');
        if (!empty($folder)) $folder .= '/';

        if (empty($metadata)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }

        if (self::$NOCACHEMODE) unset($metadata['s3']); // no cache and transfer again
        
        $oldUrl = wp_get_attachment_url($attachment_id);
        $updated = false;

        if(!empty($metadata['file'])) {
            $urlBasename = str_replace($metadata['file'], '', $oldUrl);
        } else {
            $fileName = get_post_meta($attachment_id, "_wp_attached_file", true);
            $urlBasename = dirname($fileName);
        }

        if (!isset($metadata['s3'])) {
            if (!empty($metadata['original_image'])) {
                $dirname = dirname($metadata["file"]);
                $filename = $dirname . DIRECTORY_SEPARATOR . $metadata['original_image'];
            } elseif (!empty($metadata['file'])) {
                $filename = $metadata['file'];
            } else {
                $filename = get_post_meta($attachment_id, '_wp_attached_file', true);
            }

            if (
                is_file(WP_CONTENT_DIR . '/uploads/' . $filename) === false && 
                is_file(WP_CONTENT_DIR . '/uploads/' . str_replace("-scaled", "", $filename)) === true
            ) {
                return $metadata;
            }

            try {

                $this->uploadToS3(
                    WP_CONTENT_DIR . '/uploads/' . $filename,
                    $folder . $filename
                );

                $newUrl = $this->s3->getObjectUrl($this->options['bucket'], $folder . $filename);
            } catch (S3Exception $exception) {
                throw $exception;
            }

            $type = wp_check_filetype_and_ext(WP_CONTENT_DIR . '/uploads/' . $filename, basename($filename));

            $metadata['s3_public_url'] = $newUrl;
            update_post_meta($attachment_id, 's3_public_url', $metadata['s3_public_url']);

            $contentReplacementInstance->replace($oldGuid, $newUrl);
            $contentReplacementInstance->replace($oldUrl, $newUrl);

            $metadata['s3'] = [
                'url' => trim($this->options['url_prefix'], ' /') . '/' . $filename,
                'key' => $filename,
                'mime' => $type,
            ];

            $updated = true;
        }

        if (!empty($metadata['sizes'])) {

            if (empty($filename)) {
                if (!empty($metadata['file'])) {
                    $filename = $metadata['file'];
                } else {
                    $filename = get_post_meta($attachment_id, '_wp_attached_file', true);
                }
            }

            foreach ($metadata['sizes'] as $sizeKey => $sizeData) {
                $dirname = dirname($filename);

                $oldUrl = $urlBasename . $dirname . '/' . $sizeData['file'];
                if (self::$NOCACHEMODE) unset($sizeData['s3']); // no cache and transfer again

                if (!isset($sizeData['s3'])) {
                    try {

                        try {
                            $this->uploadToS3(
                                WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $dirname . DIRECTORY_SEPARATOR . $sizeData['file'],
                                $folder . $dirname . DIRECTORY_SEPARATOR . $sizeData['file'],
                                !empty($sizeData["mime-type"]) ? $sizeData["mime-type"] : null
                            );

                            // $this->s3->PutObject($params);

                            $newUrl = $this->s3->getObjectUrl($this->options['bucket'], $folder . $dirname . DIRECTORY_SEPARATOR . $sizeData['file']);
                        } catch (S3Exception $exception) {
                            throw $exception;
                        }
                    } catch (\Exception $exp) {
                        throw $exp;
                    }

                    $contentReplacementInstance->replace($oldUrl, $newUrl);

                    $metadata['sizes'][$sizeKey]['s3'] = [ 
                        'url' => $newUrl,
                        'key' => $dirname . DIRECTORY_SEPARATOR . $sizeData['file'],
                    ];

                    $updated = true;
                }
            }
        }

        if ($updated) {
            wp_update_attachment_metadata($attachment_id, $metadata); 
        }

        return $metadata;
    }
    
}
