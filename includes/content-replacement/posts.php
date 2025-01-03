<?php
namespace WPS3\S3\Offload\ContentReplacement;

class Posts {
    public function process_posts($keys) {
        foreach($keys as $key => $data) {
            if($data['changed'] == false) {
                continue;
            }

            $postId = str_replace('post-', '', $key);

            wp_update_post([
                'ID' => $postId,
                'post_content' => $data['value'],
            ]);
        }
        
    }
}

add_action('wps3_replacement_process_posts', array(new Posts(), 'process_posts'));