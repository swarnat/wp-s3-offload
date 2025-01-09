<?php
namespace WPS3\S3\Offload;

class ContentReplacement {
    private static $INSTANCE = null;

    private static $CACHE = [];

    public static function getInstance() {
        if(empty(self::$INSTANCE)) {
            self::$INSTANCE = new self();
        }
        return self::$INSTANCE;
    }

    // 
    public function __construct() {
        $this->preFillContentCache();
        
        $files = glob(__DIR__ . '/content-replacement/*.php');
        foreach($files as $file) {
            require_once($file);
        }

        do_action('wps3_replacement_contentcache', $this);        
                
    }

    public static function addContentCache($namespace, $key, $content, $options = []) {
        if(empty(self::$CACHE[$namespace])) {
            self::$CACHE[$namespace] = [];
        }
        
        self::$CACHE[$namespace][$key] = [
            'value' => $content,
            'changed' => false,
            'extra' => $options
        ];
    }

    public function preFillContentCache() {
        $pages = get_posts( array(        
            'post_type' => 'page',
            'post_status'    => 'publish,draft',
            'posts_per_page' => '-1',
        ) );

        foreach($pages as $page) {
            self::addContentCache('posts', 'post-' . $page->ID, $page->post_content);
        }

    }

    public function processReplacedContent() {
        
        foreach(self::$CACHE as $namespace => $keys) {

            do_action('wps3_replacement_process_' . $namespace, $keys);

        }

    }

    private function do_replace_in_array($array, $oldUrl, $newUrl) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Rekursiver Aufruf, wenn der Wert ein Array ist
                $array[$key] = $this->do_replace_in_array($value, $oldUrl, $newUrl);
            } elseif (is_string($value)) {
                // Ersetzen, wenn der Wert ein String ist
                $array[$key] = str_replace($oldUrl, $newUrl, $value);
            }
        }
        return $array;    
    }

    public function replace_array($array, $oldUrl, $newUrl) {

        $old = $array;
        $array["value"] = $this->do_replace_in_array($array["value"], $oldUrl, $newUrl);
        
        if($old != $array) {
            $array["changed"] = true;
        }

        return $array;
    }

    public function replace($oldUrl, $newUrl) {

        foreach(self::$CACHE as $namespace => $keys) {
            foreach($keys as $key => $data) {
                
                if(is_string($data['value'])) {
                    if(strpos($data['value'], $oldUrl) !== false) {

                        self::$CACHE[$namespace][$key]['value'] = str_replace($oldUrl, $newUrl, self::$CACHE[$namespace][$key]['value']);
                        self::$CACHE[$namespace][$key]['changed'] = true;

                    }
                } else {

                    self::$CACHE[$namespace][$key] = apply_filters('wps3_replacement_replace_' . $namespace, $data, $oldUrl, $newUrl);
                }
            }
        }

        do_action('wps3_replacement_replace', $oldUrl, $newUrl);

        global $wpdb;
        $postMetas = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", '%' . $oldUrl . '%'), ARRAY_A);

        foreach($postMetas as $postMeta) {
            $value = get_post_meta($postMeta['post_id'], $postMeta['meta_key'], true);

            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            $value = str_replace($oldUrl, $newUrl, $value);

            $value = json_decode($value, true);
            
            update_post_meta($postMeta['post_id'], $postMeta['meta_key'], $value);
        }
    }



}