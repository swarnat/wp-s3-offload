<?php

namespace WPS3\S3\Offload;

use Imagick;
use WP_Error;
use WP_Image_Editor_Imagick;

class ImageEditorImagick extends WP_Image_Editor_Imagick {

	/**
	 * @var ?Imagick
	 */
	protected $image;

	/**
	 * @var ?string
	 */
	protected $file;

	/**
	 * @var ?array{width: int, height: int}
	 */
	protected $size;

	/**
	 * @var ?string
	 */
	protected $remote_filename = null;

	/**
	 * Hold on to a reference of all temp local files.
	 *
	 * These are cleaned up on __destruct.
	 *
	 * @var array
	 */
	protected $temp_files_to_cleanup = [];

	/**
	 * Loads image from $this->file into new Imagick Object.
	 *
	 * @return true|WP_Error True if loaded; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image instanceof Imagick ) {
			return true;
		}

		if ( $this->file && ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File doesn&#8217;t exist?' ), $this->file );
		}

		$file_extension = strtolower( pathinfo( $this->file, PATHINFO_EXTENSION ) );
		$upload_dir = wp_upload_dir();

		// echo "<pre>";


		if ( ! $this->file || strpos( $this->file, $upload_dir['basedir'] ) === 0 ) {
			return parent::load();
		}

		$temp_filename = tempnam( get_temp_dir(), 's3-uploads' ) . "." . $file_extension;
		$this->temp_files_to_cleanup[] = $temp_filename;
		
		// var_dump(__LINE__, $this->file, $temp_filename);
		copy( $this->file, $temp_filename );
		$this->remote_filename = $this->file;
		$this->file = $temp_filename;

		$result = parent::load();
		
		$this->file = $this->remote_filename;
		// var_dump($this->file);
		// var_dump($upload_dir);

		// debug_print_backtrace(0, 10);

		// var_dump( $this->file);
		// exit();

		return $result;
	}

	/**
	 * Imagick by default can't handle s3:// paths
	 * for saving images. We have instead save it to a file file,
	 * then copy it to the s3:// path as a workaround.
	 *
	 * @param Imagick $image
	 * @param ?string $filename
	 * @param ?string $mime_type
	 * @return WP_Error|array{path: string, file: string, width: int, height: int, mime-type: string}
	 */
	public function _save( $image, $filename = null, $mime_type = null ) {
		/**
		 * @var ?string $filename
		 * @var string $extension
		 * @var string $mime_type
		 */
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		$upload_dir = wp_upload_dir();

		// var_dump($upload_dir, $filename);

		// if ( strpos( $filename, $upload_dir['basedir'] ) !== 0 ) {
		// 	/** @var false|string */
		$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );
		
		$s3TargetFileName =  str_replace($upload_dir['basedir'] . DIRECTORY_SEPARATOR, "", $filename);
		$s3TargetFileName =  preg_replace("#s3\:\/\/[^\/]+/#", "", $s3TargetFileName);

		/**
		 * @var WP_Error|array{path: string, file: string, width: int, height: int, mime-type: string}
		 */
		$parent_call = parent::_save( $image, $temp_filename ?: $filename, $mime_type );

		if ( is_wp_error( $parent_call ) ) {
			if ( $temp_filename ) {
				unlink( $temp_filename );
			}

			return $parent_call;
		} else {
			/**
			 * @var array{path: string, file: string, width: int, height: int, mime-type: string} $save
			 */
			$save = $parent_call;
		}
		
		// echo "<pre>";
		// var_dump($parent_call, $s3TargetFileName, $save);
		// exit();

		// echo "<pre>";var_dump($save['path'], is_file($save['path']), $s3TargetFileName);
		// $copy_result = copy($save['path'], $s3TargetFileName);

		// if ( ! $copy_result ) {
		// 	return new WP_Error( 'unable-to-copy-to-s3', 'Unable to copy the temp image to S3' );
		// }

		// var_dump(__LINE__, $save['path'], $s3TargetFileName);
		$syncer = Loader::getSyncer();
		$syncer->upload($save['path'], $s3TargetFileName);

		unlink( $save['path'] );
		if ( $temp_filename ) {
			unlink( $temp_filename );
		}

		$response = [
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'] ?? 0,
			'height'    => $this->size['height'] ?? 0,
			'mime-type' => $mime_type,
		];
		// var_dump($response);
		// exit();
		return $response;
	}

	public function __destruct() {
		array_map( 'unlink', $this->temp_files_to_cleanup );
		parent::__destruct();
	}
}
