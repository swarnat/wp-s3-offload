<?php

namespace WPS3\S3\Offload;

class Frontend
{
	public function load_hooks()
	{

		add_filter('wp_get_attachment_url', [$this, 'change_file_url_in_media'], 10, 2);
		add_filter('wp_calculate_image_srcset', [$this, 'calculate_image_srcset'], 10, 5);

		add_filter('wp_image_editors', [$this, 'filter_editors'], 9);
	}

	private function is_upload_to_s3($post_id)
	{
		$s3_public_url = get_post_meta($post_id, 's3_public_url', true);

		if (empty($s3_public_url)) {
			return false;
		}

		return true;
	}

	public function filter_editors(array $editors): array
	{
		$position = array_search('WP_Image_Editor_Imagick', $editors);
		if ($position !== false) {
			unset($editors[$position]);
		}

		array_unshift($editors, \WPS3\S3\Offload\ImageEditorImagick::class);

		return $editors;
	}

	public function calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
	{
		$upload_dir    = wp_get_upload_dir();
		$image_baseurl = trailingslashit($upload_dir['baseurl']);

		if(!empty($image_meta["s3_public_url"])) {
			$image_meta["sizes"]["full"] = [
				"file" => basename($image_meta["file"]),
				"width" => $image_meta["width"],
				"height" => $image_meta["height"],
				"s3" => [
					"url" => $image_meta["s3_public_url"]
				]
			];
		}

		//var_dump($image_baseurl);

		foreach ($sources as $index => $source) {
			$imgBasename = basename($source['url']);

			foreach ($image_meta['sizes'] as $size) {
				if (empty($size['s3'])) continue;

				if ($size['file'] == $imgBasename) {
					$sources[$index]['url'] = $size['s3']['url'];
					break;
				}
			}
		}

		return $sources;
	}

	public function change_file_url_in_media($url, $attachment_id)
	{
		if (! $this->is_upload_to_s3($attachment_id)) {
			return $url;
		}

		return get_post_meta($attachment_id, 's3_public_url', true);
	}
}
