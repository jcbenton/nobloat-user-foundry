<?php
/**
 * NoBloat User Foundry - Image Processor
 *
 * Handles image optimization, WebP conversion, resizing, and EXIF stripping
 * for uploaded profile and cover photos.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Image_Processor
 *
 * Processes and optimizes user-uploaded images.
 */
class NBUF_Image_Processor {


	/**
	 * Image type constants
	 */
	const TYPE_PROFILE = 'profile';
	const TYPE_COVER   = 'cover';

	/**
	 * Get available image processor
	 *
	 * @return string|false 'imagick', 'gd', or false if neither available
	 */
	public static function get_available_processor() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			return 'imagick';
		}

		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			return 'gd';
		}

		return false;
	}

	/**
	 * Check if WebP conversion is supported
	 *
	 * @return bool True if WebP is supported
	 */
	public static function webp_supported() {
		$processor = self::get_available_processor();

		if ( 'imagick' === $processor ) {
			$imagick = new Imagick();
			return in_array( 'WEBP', $imagick->queryFormats(), true );
		}

		if ( 'gd' === $processor && function_exists( 'imagewebp' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Process uploaded image
	 *
	 * Main entry point for image processing. Handles conversion, optimization,
	 * and saving to the nobloat directory.
	 *
	 * @param  string $source_path   Full path to source image.
	 * @param  int    $user_id       User ID.
	 * @param  string $type          Image type (profile or cover).
	 * @param  array  $options       Optional processing options.
	 * @return array|WP_Error Success with file info or WP_Error on failure.
	 */
	public static function process_image( $source_path, $user_id, $type, $options = array() ) {
		/* Validate input */
		if ( ! file_exists( $source_path ) ) {
			return new WP_Error( 'file_not_found', __( 'Source image file not found.', 'nobloat-user-foundry' ) );
		}

		if ( ! in_array( $type, array( self::TYPE_PROFILE, self::TYPE_COVER ), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid image type specified.', 'nobloat-user-foundry' ) );
		}

		/* Get settings */
		$convert_to_webp = isset( $options['convert_to_webp'] ) ? $options['convert_to_webp'] : NBUF_Options::get( 'nbuf_convert_images_to_webp', true );
		$webp_quality    = isset( $options['quality'] ) ? $options['quality'] : NBUF_Options::get( 'nbuf_webp_quality', 85 );
		$strip_exif      = isset( $options['strip_exif'] ) ? $options['strip_exif'] : NBUF_Options::get( 'nbuf_strip_exif_data', true );

		/* Get max dimensions */
		if ( self::TYPE_PROFILE === $type ) {
			$max_width  = NBUF_Options::get( 'nbuf_profile_photo_max_width', 1024 );
			$max_height = $max_width; /* Profile photos are square */
		} else {
			$max_width  = NBUF_Options::get( 'nbuf_cover_photo_max_width', 1920 );
			$max_height = NBUF_Options::get( 'nbuf_cover_photo_max_height', 600 );
		}

		/*
		 * SECURITY: Get image info with proper error handling (no error suppression).
		 * Validate file exists and is readable before processing.
		 */
		if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			return new WP_Error( 'file_not_accessible', __( 'Image file is not accessible.', 'nobloat-user-foundry' ) );
		}

		/* Validate file size before processing (prevent memory exhaustion attacks) */
		$file_size = filesize( $source_path );
		if ( false === $file_size || $file_size > ( 50 * 1024 * 1024 ) ) {
			return new WP_Error( 'file_too_large', __( 'Image file exceeds maximum size (50MB).', 'nobloat-user-foundry' ) );
		}

		/* Get image info without error suppression */
		$image_info = getimagesize( $source_path );
		if ( false === $image_info ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_validation_failed',
					'warning',
					'getimagesize failed during image processing',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'image_validation',
					)
				);
			}
			return new WP_Error( 'invalid_image', __( 'Invalid or corrupt image file.', 'nobloat-user-foundry' ) );
		}

		$mime_type = $image_info['mime'];

		/* Validate image type */
		$allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new WP_Error( 'unsupported_type', __( 'Unsupported image type. Please upload JPG, PNG, GIF, or WebP.', 'nobloat-user-foundry' ) );
		}

		/* Create user directory */
		$upload_dir = self::get_upload_directory( $user_id );
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir;
		}

		/*
		 * Generate filename with random token for privacy
		 * Token prevents enumeration of user photos by sequential ID guessing
		 * GDPR: Implements "appropriate technical measures" (Article 32)
		 */
		$filename_base = ( self::TYPE_PROFILE === $type ) ? 'profile-photo' : 'cover-photo';

		/*
		 * SECURITY: Generate cryptographically secure random token.
		 * Use random_bytes() instead of wp_generate_password() for security tokens.
		 */
		$random_token = bin2hex( random_bytes( 16 ) ); /* 32 hex characters, cryptographically secure */

		/* Delete old photo if exists (before creating new one with different token) */
		self::delete_photo( $user_id, $type );

		/* Try WebP conversion if enabled */
		if ( $convert_to_webp && self::webp_supported() ) {
			$filename    = $filename_base . '-' . $random_token . '.webp';
			$output_path = $upload_dir['path'] . $filename;
			$result      = self::convert_to_webp( $source_path, $output_path, $webp_quality, $max_width, $max_height, $strip_exif );

			if ( ! is_wp_error( $result ) ) {
				return array(
					'path'      => $output_path,
					'url'       => $upload_dir['url'] . $filename,
					'format'    => 'webp',
					'optimized' => true,
				);
			}

			/* WebP conversion failed, fall back to optimization */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'webp_conversion_failed',
					'info',
					'WebP conversion failed, falling back to optimization',
					array(
						'user_id' => $user_id,
						'error'   => $result->get_error_message(),
					)
				);
			}
		}

		/* Fallback: Optimize original format */
		$extension = self::get_extension_from_mime( $mime_type );
		if ( ! $extension ) {
			return new WP_Error( 'unknown_format', __( 'Could not determine image format.', 'nobloat-user-foundry' ) );
		}

		$filename    = $filename_base . '-' . $random_token . '.' . $extension;
		$output_path = $upload_dir['path'] . $filename;

		/* Process based on mime type */
		if ( 'image/jpeg' === $mime_type || 'image/jpg' === $mime_type ) {
			$result = self::optimize_jpeg( $source_path, $output_path, $webp_quality, $max_width, $max_height, $strip_exif );
		} elseif ( 'image/png' === $mime_type ) {
			$result = self::optimize_png( $source_path, $output_path, $max_width, $max_height, $strip_exif );
		} else {
			/* GIF or already WebP - just resize and copy */
			$result = self::resize_and_copy( $source_path, $output_path, $max_width, $max_height );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'path'      => $output_path,
			'url'       => $upload_dir['url'] . $filename,
			'format'    => $extension,
			'optimized' => true,
		);
	}

	/**
	 * Convert image to WebP format
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output WebP path.
	 * @param  int    $quality      Quality 1-100.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @param  bool   $strip_exif   Strip EXIF data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function convert_to_webp( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif ) {
		$processor = self::get_available_processor();

		if ( 'imagick' === $processor ) {
			return self::convert_to_webp_imagick( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif );
		}

		if ( 'gd' === $processor ) {
			return self::convert_to_webp_gd( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif );
		}

		return new WP_Error( 'no_processor', __( 'No image processor available.', 'nobloat-user-foundry' ) );
	}

	/**
	 * Convert to WebP using Imagick
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output WebP path.
	 * @param  int    $quality      Quality 1-100.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @param  bool   $strip_exif   Strip EXIF data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function convert_to_webp_imagick( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif ) {
		try {
			$image = new Imagick( $source_path );

			/* Auto-rotate based on EXIF orientation */
			$image->autoOrientImage();

			/* Strip EXIF if requested */
			if ( $strip_exif ) {
				$image->stripImage();
			}

			/* Resize if needed */
			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();

			if ( $width > $max_width || $height > $max_height ) {
				$image->resizeImage( $max_width, $max_height, Imagick::FILTER_LANCZOS, 1, true );
			}

			/* Convert to WebP */
			$image->setImageFormat( 'webp' );
			$image->setImageCompressionQuality( $quality );

			/* Write file */
			$success = $image->writeImage( $output_path );

			/* Clean up */
			$image->clear();
			$image->destroy();

			if ( ! $success ) {
				return new WP_Error( 'write_failed', __( 'Failed to write WebP image.', 'nobloat-user-foundry' ) );
			}

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'imagick_error', $e->getMessage() );
		}
	}

	/**
	 * Convert to WebP using GD
	 *
	 * Note: GD always strips EXIF data, so $strip_exif parameter is kept for API consistency.
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output WebP path.
	 * @param  int    $quality      Quality 1-100.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @param  bool   $strip_exif   Strip EXIF data (GD always strips).
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function convert_to_webp_gd( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		/* SECURITY: Get image info without error suppression */
		$image_info = getimagesize( $source_path );
		if ( false === $image_info ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_validation_failed',
					'warning',
					'getimagesize failed during image processing',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'image_validation',
					)
				);
			}
			return new WP_Error( 'invalid_image', __( 'Invalid or corrupt image file.', 'nobloat-user-foundry' ) );
		}

		$mime_type = $image_info['mime'];

		/*
		 * SECURITY: Create image resource without error suppression.
		 * Proper error handling detects malicious or corrupt images.
		 */
		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/jpg':
				$source_image = imagecreatefromjpeg( $source_path );
				break;

			case 'image/png':
				$source_image = imagecreatefrompng( $source_path );
				break;

			case 'image/gif':
				$source_image = imagecreatefromgif( $source_path );
				break;

			case 'image/webp':
				$source_image = imagecreatefromwebp( $source_path );
				break;

			default:
				return new WP_Error( 'unsupported_type', __( 'Unsupported image type for WebP conversion.', 'nobloat-user-foundry' ) );
		}

		if ( false === $source_image ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_create_failed',
					'warning',
					'imagecreatefrom function failed during image processing',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'image_resource_creation',
					)
				);
			}
			return new WP_Error( 'create_failed', __( 'Failed to create image resource. File may be corrupt.', 'nobloat-user-foundry' ) );
		}

		/* Get dimensions */
		$width  = imagesx( $source_image );
		$height = imagesy( $source_image );

		/* Validate dimensions to prevent division by zero */
		if ( $width <= 0 || $height <= 0 ) {
			imagedestroy( $source_image );
			return new WP_Error(
				'invalid_dimensions',
				__( 'Image has invalid dimensions (width or height is zero).', 'nobloat-user-foundry' )
			);
		}

		/* Calculate new dimensions if resize needed */
		if ( $width > $max_width || $height > $max_height ) {
			$ratio = min( $max_width / $width, $max_height / $height );

			$new_width  = (int) round( $width * $ratio );
			$new_height = (int) round( $height * $ratio );

			/* Create new image */
			$new_image = imagecreatetruecolor( $new_width, $new_height );

			/* Preserve transparency */
			imagealphablending( $new_image, false );
			imagesavealpha( $new_image, true );

			/* Resize */
			imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

			/* Replace source */
			imagedestroy( $source_image );
			$source_image = $new_image;
		}

		/* Convert to WebP */
		$success = imagewebp( $source_image, $output_path, $quality );

		/* Clean up */
		imagedestroy( $source_image );

		if ( ! $success ) {
			return new WP_Error( 'webp_failed', __( 'Failed to convert image to WebP.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Optimize JPEG image
	 *
	 * Fallback when WebP conversion fails or is disabled.
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output JPEG path.
	 * @param  int    $quality      Quality 1-100.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @param  bool   $strip_exif   Strip EXIF data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function optimize_jpeg( $source_path, $output_path, $quality, $max_width, $max_height, $strip_exif ) {
		$processor = self::get_available_processor();

		if ( 'imagick' === $processor ) {
			try {
				$image = new Imagick( $source_path );

				/* Auto-rotate based on EXIF orientation */
				$image->autoOrientImage();

				/* Strip EXIF if requested */
				if ( $strip_exif ) {
					$image->stripImage();
				}

				/* Resize if needed */
				$width  = $image->getImageWidth();
				$height = $image->getImageHeight();

				if ( $width > $max_width || $height > $max_height ) {
					$image->resizeImage( $max_width, $max_height, Imagick::FILTER_LANCZOS, 1, true );
				}

				/* Optimize */
				$image->setImageFormat( 'jpeg' );
				$image->setImageCompressionQuality( $quality );

				/* Write file */
				$success = $image->writeImage( $output_path );

				/* Clean up */
				$image->clear();
				$image->destroy();

				if ( ! $success ) {
					return new WP_Error( 'write_failed', __( 'Failed to write optimized JPEG.', 'nobloat-user-foundry' ) );
				}

				return true;

			} catch ( Exception $e ) {
				return new WP_Error( 'imagick_error', $e->getMessage() );
			}
		}

		/* GD fallback */
		return self::optimize_jpeg_gd( $source_path, $output_path, $quality, $max_width, $max_height );
	}

	/**
	 * Optimize JPEG using GD
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output JPEG path.
	 * @param  int    $quality      Quality 1-100.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function optimize_jpeg_gd( $source_path, $output_path, $quality, $max_width, $max_height ) {
		/* SECURITY: Create image resource without error suppression */
		$source_image = imagecreatefromjpeg( $source_path );
		if ( false === $source_image ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_create_failed',
					'warning',
					'imagecreatefromjpeg failed during JPEG optimization',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'jpeg_optimization',
					)
				);
			}
			return new WP_Error( 'create_failed', __( 'Failed to create JPEG resource. File may be corrupt.', 'nobloat-user-foundry' ) );
		}

		/* Get dimensions */
		$width  = imagesx( $source_image );
		$height = imagesy( $source_image );

		/* Validate dimensions to prevent division by zero */
		if ( $width <= 0 || $height <= 0 ) {
			imagedestroy( $source_image );
			return new WP_Error(
				'invalid_dimensions',
				__( 'Image has invalid dimensions (width or height is zero).', 'nobloat-user-foundry' )
			);
		}

		/* Resize if needed */
		if ( $width > $max_width || $height > $max_height ) {
			$ratio = min( $max_width / $width, $max_height / $height );

			$new_width  = (int) round( $width * $ratio );
			$new_height = (int) round( $height * $ratio );

			$new_image = imagecreatetruecolor( $new_width, $new_height );
			imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

			imagedestroy( $source_image );
			$source_image = $new_image;
		}

		/* Save with optimization */
		$success = imagejpeg( $source_image, $output_path, $quality );

		/* Clean up */
		imagedestroy( $source_image );

		if ( ! $success ) {
			return new WP_Error( 'jpeg_failed', __( 'Failed to save optimized JPEG.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Optimize PNG image
	 *
	 * Fallback when WebP conversion fails or is disabled.
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output PNG path.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @param  bool   $strip_exif   Strip EXIF data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function optimize_png( $source_path, $output_path, $max_width, $max_height, $strip_exif ) {
		$processor = self::get_available_processor();

		if ( 'imagick' === $processor ) {
			try {
				$image = new Imagick( $source_path );

				/* Strip EXIF if requested */
				if ( $strip_exif ) {
					$image->stripImage();
				}

				/* Resize if needed */
				$width  = $image->getImageWidth();
				$height = $image->getImageHeight();

				if ( $width > $max_width || $height > $max_height ) {
					$image->resizeImage( $max_width, $max_height, Imagick::FILTER_LANCZOS, 1, true );
				}

				/* Optimize PNG */
				$image->setImageFormat( 'png' );
				$image->setImageCompressionQuality( 6 );

				/* Write file */
				$success = $image->writeImage( $output_path );

				/* Clean up */
				$image->clear();
				$image->destroy();

				if ( ! $success ) {
					return new WP_Error( 'write_failed', __( 'Failed to write optimized PNG.', 'nobloat-user-foundry' ) );
				}

				return true;

			} catch ( Exception $e ) {
				return new WP_Error( 'imagick_error', $e->getMessage() );
			}
		}

		/* GD fallback */
		return self::optimize_png_gd( $source_path, $output_path, $max_width, $max_height );
	}

	/**
	 * Optimize PNG using GD
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output PNG path.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function optimize_png_gd( $source_path, $output_path, $max_width, $max_height ) {
		/* SECURITY: Create image resource without error suppression */
		$source_image = imagecreatefrompng( $source_path );
		if ( false === $source_image ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_create_failed',
					'warning',
					'imagecreatefrompng failed during PNG optimization',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'png_optimization',
					)
				);
			}
			return new WP_Error( 'create_failed', __( 'Failed to create PNG resource. File may be corrupt.', 'nobloat-user-foundry' ) );
		}

		/* Get dimensions */
		$width  = imagesx( $source_image );
		$height = imagesy( $source_image );

		/* Validate dimensions to prevent division by zero */
		if ( $width <= 0 || $height <= 0 ) {
			imagedestroy( $source_image );
			return new WP_Error(
				'invalid_dimensions',
				__( 'Image has invalid dimensions (width or height is zero).', 'nobloat-user-foundry' )
			);
		}

		/* Resize if needed */
		if ( $width > $max_width || $height > $max_height ) {
			$ratio = min( $max_width / $width, $max_height / $height );

			$new_width  = (int) round( $width * $ratio );
			$new_height = (int) round( $height * $ratio );

			$new_image = imagecreatetruecolor( $new_width, $new_height );

			/* Preserve transparency */
			imagealphablending( $new_image, false );
			imagesavealpha( $new_image, true );

			imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

			imagedestroy( $source_image );
			$source_image = $new_image;
		}

		/* Save with compression level 6 (0-9, higher = more compression) */
		$success = imagepng( $source_image, $output_path, 6 );

		/* Clean up */
		imagedestroy( $source_image );

		if ( ! $success ) {
			return new WP_Error( 'png_failed', __( 'Failed to save optimized PNG.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Resize and copy image without format conversion
	 *
	 * Used for GIF or when source is already WebP.
	 *
	 * @param  string $source_path  Source image path.
	 * @param  string $output_path  Output image path.
	 * @param  int    $max_width    Maximum width.
	 * @param  int    $max_height   Maximum height.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function resize_and_copy( $source_path, $output_path, $max_width, $max_height ) {
		/* SECURITY: Get image info without error suppression */
		$image_info = getimagesize( $source_path );
		if ( false === $image_info ) {
			/* Log the actual PHP error for security monitoring */
			$error = error_get_last();
			if ( $error && class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'image_validation_failed',
					'warning',
					'getimagesize failed during image processing',
					array(
						'file_path' => $source_path,
						'error'     => $error['message'],
						'operation' => 'image_validation',
					)
				);
			}
			return new WP_Error( 'invalid_image', __( 'Invalid or corrupt image file.', 'nobloat-user-foundry' ) );
		}

		$width  = $image_info[0];
		$height = $image_info[1];

		/* If image is small enough, just copy it */
		if ( $width <= $max_width && $height <= $max_height ) {
			if ( ! copy( $source_path, $output_path ) ) {
				NBUF_Security_Log::log(
					'image_copy_failed',
					'critical',
					'Failed to copy image file during resize operation',
					array(
						'source_path' => basename( $source_path ),
						'output_path' => basename( $output_path ),
						'width'       => $width,
						'height'      => $height,
					)
				);
				return new WP_Error( 'copy_failed', __( 'Failed to copy image file.', 'nobloat-user-foundry' ) );
			}
			return true;
		}

		/* Otherwise, use WordPress built-in image editor */
		$editor = wp_get_image_editor( $source_path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$editor->resize( $max_width, $max_height, false );
		$result = $editor->save( $output_path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get upload directory for user
	 *
	 * Creates /wp-content/uploads/nobloat/{user_id}/ directory.
	 *
	 * @param  int $user_id User ID.
	 * @return array|WP_Error Array with 'path' and 'url' keys, or WP_Error.
	 */
	public static function get_upload_directory( $user_id ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_error', $upload_dir['error'] );
		}

		$nobloat_dir = trailingslashit( $upload_dir['basedir'] ) . 'nobloat/';
		$user_dir    = $nobloat_dir . absint( $user_id ) . '/';

		/*
		 * SECURITY: Create directories with restrictive permissions.
		 * 0750 = owner + group only, no world access.
		 */
		if ( ! file_exists( $nobloat_dir ) ) {
			if ( ! wp_mkdir_p( $nobloat_dir ) ) {
				return new WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', 'nobloat-user-foundry' ) );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Required for security: restrictive permissions (0750).
			$chmod_result = chmod( $nobloat_dir, 0750 );
			if ( false === $chmod_result ) {
				NBUF_Security_Log::log(
					'chmod_failed',
					'critical',
					'Failed to set restrictive permissions on nobloat upload directory',
					array(
						'directory'            => $nobloat_dir,
						'intended_permissions' => '0750',
					)
				);
				return new WP_Error( 'chmod_failed', __( 'Could not secure upload directory.', 'nobloat-user-foundry' ) );
			}

			/*
			 * SECURITY: Add .htaccess to block direct file access.
			 * Photos should only be served through WordPress with permission checks.
			 */
			$htaccess_file     = trailingslashit( $nobloat_dir ) . '.htaccess';
			$htaccess_content  = "# NoBloat User Foundry - Block direct access\n";
			$htaccess_content .= "# Photos are served through WordPress with permission checks\n";
			$htaccess_content .= "Order Deny,Allow\n";
			$htaccess_content .= "Deny from all\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Required for .htaccess creation.
			$htaccess_result = file_put_contents( $htaccess_file, $htaccess_content );
			if ( false === $htaccess_result ) {
				NBUF_Security_Log::log(
					'htaccess_write_failed',
					'critical',
					'Failed to create .htaccess file for photo upload directory',
					array(
						'file_path' => $htaccess_file,
						'directory' => $nobloat_dir,
					)
				);
				/* Continue anyway - directory permissions still provide some protection */
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Required for security: restrictive permissions (0640).
				$htaccess_chmod = chmod( $htaccess_file, 0640 );
				if ( false === $htaccess_chmod ) {
					NBUF_Security_Log::log(
						'chmod_failed',
						'warning',
						'Failed to set permissions on .htaccess file',
						array(
							'file_path'            => $htaccess_file,
							'intended_permissions' => '0640',
						)
					);
				}
			}
		}

		if ( ! file_exists( $user_dir ) ) {
			if ( ! wp_mkdir_p( $user_dir ) ) {
				return new WP_Error( 'mkdir_failed', __( 'Could not create user upload directory.', 'nobloat-user-foundry' ) );
			}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Required for security: restrictive permissions (0750).
			$user_chmod = chmod( $user_dir, 0750 );
			if ( false === $user_chmod ) {
				NBUF_Security_Log::log(
					'chmod_failed',
					'critical',
					'Failed to set restrictive permissions on user upload directory',
					array(
						'directory'            => $user_dir,
						'user_id'              => $user_id,
						'intended_permissions' => '0750',
					)
				);
				return new WP_Error( 'chmod_failed', __( 'Could not secure user upload directory.', 'nobloat-user-foundry' ) );
			}
		}

		/*
		 * Verify directory is writable
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Required for upload directory validation.
		if ( ! is_writable( $user_dir ) ) {
			return new WP_Error( 'directory_not_writable', __( 'Upload directory is not writable.', 'nobloat-user-foundry' ) );
		}

		$nobloat_url = trailingslashit( $upload_dir['baseurl'] ) . 'nobloat/';
		$user_url    = $nobloat_url . absint( $user_id ) . '/';

		return array(
			'path' => $user_dir,
			'url'  => $user_url,
		);
	}

	/**
	 * Get file extension from MIME type
	 *
	 * @param  string $mime_type MIME type.
	 * @return string|false Extension without dot, or false.
	 */
	private static function get_extension_from_mime( $mime_type ) {
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		return isset( $extensions[ $mime_type ] ) ? $extensions[ $mime_type ] : false;
	}

	/**
	 * Delete user's photo
	 *
	 * Handles both old filenames (profile-photo.webp) and new tokenized filenames
	 * (profile-photo-a3f8k2d9h5m7.webp) by using glob pattern matching.
	 *
	 * @param  int    $user_id User ID.
	 * @param  string $type    Image type (profile or cover).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_photo( $user_id, $type ) {
		$upload_dir = self::get_upload_directory( $user_id );
		if ( is_wp_error( $upload_dir ) ) {
			return false;
		}

		$filename_base = ( self::TYPE_PROFILE === $type ) ? 'profile-photo' : 'cover-photo';

		/*
		 * Use glob to find all matching files (handles both old and new formats)
		 * Old format: profile-photo.webp
		 * New format: profile-photo-a3f8k2d9h5m7.webp
		 */
		$pattern = $upload_dir['path'] . $filename_base . '*';
		$files   = glob( $pattern );
		$deleted = false;

		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $files as $file_path ) {
				if ( is_file( $file_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file deletion required for user photos.
					wp_delete_file( $file_path );
					$deleted = true;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Cleanup all photos for a user
	 *
	 * Deletes the entire user photo directory (/uploads/nobloat/{user_id}/).
	 * Called when user account is deleted (if GDPR setting enabled).
	 *
	 * @param  int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function cleanup_user_photos( $user_id ) {
		/* Check if photo deletion on user deletion is enabled */
		$delete_enabled = NBUF_Options::get( 'nbuf_gdpr_delete_user_photos', true );
		if ( ! $delete_enabled ) {
			return false;
		}

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		/* Get user directory */
		$upload_dir = wp_upload_dir();
		$user_dir   = trailingslashit( $upload_dir['basedir'] ) . 'nobloat/' . $user_id . '/';

		if ( ! file_exists( $user_dir ) ) {
			return false; /* Directory doesn't exist, nothing to delete */
		}

		/* Delete all files in user directory */
		$files = glob( $user_dir . '*' );
		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file deletion required for GDPR compliance.
					wp_delete_file( $file );
				}
			}
		}

		/*
		 * Delete directory
		 */
		if ( is_dir( $user_dir ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct directory deletion required for GDPR compliance.
			$rmdir_result = rmdir( $user_dir );
			if ( false === $rmdir_result ) {
				NBUF_Security_Log::log(
					'rmdir_failed',
					'warning',
					'Failed to delete user photo directory during cleanup',
					array(
						'directory' => $user_dir,
						'user_id'   => $user_id,
						'operation' => 'gdpr_cleanup',
					)
				);
			}
		}

		/* Log deletion for audit trail */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'privacy',
				'photos_deleted_account_closure',
				array( 'directory' => $user_dir )
			);
		}

		return true;
	}
}
