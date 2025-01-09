<?php
namespace WPS3\S3\Offload\ContentReplacement;

use WPS3\S3\Offload\ContentReplacement;

class RevolutionSlider {
    public function prefill() {
        global $wpdb;

        $slides = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix . \RevSliderFront::TABLE_SLIDES, ARRAY_A);

        foreach($slides as $slide) {
            ContentReplacement::addContentCache(
                'revslider', 
                'wp_revslider_slides-' . $slide["id"], 
                array(
                    "params" => json_decode($slide["params"], true),
                    "layers" => json_decode($slide["layers"], true),
                )
            );
        }

        $slides = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix . \RevSliderFront::TABLE_SLIDES . "7", ARRAY_A);

        foreach($slides as $slide) {
            ContentReplacement::addContentCache(
                'revslider', 
                'wp_revslider_slides7-' . $slide["id"], 
                array(
                    "params" => json_decode($slide["params"], true),
                    "layers" => json_decode($slide["layers"], true),
                )
            );
        }
    }

    public function process_posts($keys) {
        global $wpdb;
        
        foreach($keys as $key => $data) {
            // var_dump("process_posts", $key, $data['changed']);
            if($data['changed'] == false) {
                continue;
            }
            $params = json_encode($data["value"]["params"]);
            $layers = json_encode($data["value"]["layers"]);

            if(strpos($key, "wp_revslider_slides-") !== false) {
                $sliderId = str_replace('wp_revslider_slides-', '', $key);

                $wpdb->update( $wpdb->prefix . \RevSliderFront::TABLE_SLIDES, [ 'params' => $params, 'layers' => $layers ], [ 'id' => $sliderId ] );
            }

            if(strpos($key, "wp_revslider_slides7-") !== false) {
                $sliderId = str_replace('wp_revslider_slides7-', '', $key);

                $wpdb->update( $wpdb->prefix . \RevSliderFront::TABLE_SLIDES . "7", [ 'params' => $params, 'layers' => $layers ], [ 'id' => $sliderId ] );
            }
            // wp_update_post([
            //     'ID' => $sliderId,
            //     'post_content' => $data['value'],
            // ]);
        }
        
    }
}

add_action('wps3_replacement_contentcache', array(new RevolutionSlider(), 'prefill'));
add_action('wps3_replacement_process_revslider', array(new RevolutionSlider(), 'process_posts'));

add_action('wps3_replacement_replace_revslider', array(ContentReplacement::getInstance(), "replace_array"), 10, 3);