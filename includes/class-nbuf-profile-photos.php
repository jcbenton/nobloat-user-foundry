<?php
/**
 * NoBloat User Foundry - Profile Photos
 *
 * Handles profile and cover photo management with three options:
 * 1. SVG initials avatar (default, no external calls)
 * 2. Gravatar (optional, with privacy warning)
 * 3. Custom uploaded photo
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Profile_Photos
 *
 * Manages profile and cover photos with SVG, Gravatar, and custom uploads.
 */
class NBUF_Profile_Photos {


	/**
	 * Initialize profile photos
	 */
	public static function init() {
		/* Check if profiles are enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_profiles', false );
		if ( ! $enabled ) {
			return;
		}

		/* Override WordPress get_avatar if custom photos enabled */
		add_filter( 'get_avatar', array( __CLASS__, 'custom_avatar' ), 10, 5 );

		/* Add profile photos section to account page */
		add_action( 'nbuf_account_profile_photos_section', array( __CLASS__, 'render_account_photos_section' ) );

		/* AJAX handlers for photo uploads */
		add_action( 'wp_ajax_nbuf_upload_profile_photo', array( __CLASS__, 'ajax_upload_profile_photo' ) );
		add_action( 'wp_ajax_nbuf_upload_cover_photo', array( __CLASS__, 'ajax_upload_cover_photo' ) );
		add_action( 'wp_ajax_nbuf_delete_profile_photo', array( __CLASS__, 'ajax_delete_profile_photo' ) );
		add_action( 'wp_ajax_nbuf_delete_cover_photo', array( __CLASS__, 'ajax_delete_cover_photo' ) );
	}

	/**
	 * Get profile photo URL for a user
	 *
	 * @param  int $user_id User ID.
	 * @param  int $size    Size in pixels (default 96).
	 * @return string Photo URL or SVG data URI
	 */
	public static function get_profile_photo( $user_id, $size = 96 ) {
		/* Get user data */
		$user_data = NBUF_User_Data::get( $user_id );
		$user      = get_userdata( $user_id );

		if ( ! $user ) {
			return self::get_svg_avatar( '', '', $size );
		}

		/* Check if user has custom uploaded photo */
		if ( $user_data && ! empty( $user_data->profile_photo_url ) && ! empty( $user_data->profile_photo_path ) ) {
			/* Validate photo path for security */
			$validation = self::validate_photo_path( $user_data->profile_photo_path, $user_id );
			if ( ! is_wp_error( $validation ) ) {
				/*
				 * SECURITY: Reconstruct URL from validated path instead of trusting stored URL.
				 * This prevents attacks where path validation passes but URL points elsewhere.
				 */
				$upload_dir = wp_upload_dir();
				$base_url   = trailingslashit( $upload_dir['baseurl'] );
				$base_path  = trailingslashit( $upload_dir['basedir'] );

				/* Get path relative to uploads directory */
				$relative_path     = str_replace( $base_path, '', $user_data->profile_photo_path );
				$reconstructed_url = $base_url . $relative_path;

				return esc_url( $reconstructed_url );
			}
			/* If validation fails, fall through to default avatar */
		}

		/* Check if Gravatar is enabled */
		$gravatar_enabled = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
		if ( $gravatar_enabled ) {
			/* Check if user has opted in to Gravatar */
			$user_gravatar_enabled = $user_data && ! empty( $user_data->use_gravatar );
			if ( $user_gravatar_enabled ) {
				return self::get_gravatar_url( $user->user_email, $size );
			}
		}

		/* Default: SVG initials avatar */
		$first_name = ! empty( $user->first_name ) ? $user->first_name : '';
		$last_name  = ! empty( $user->last_name ) ? $user->last_name : '';

		/* Fallback to username if no name */
		if ( empty( $first_name ) && empty( $last_name ) ) {
			$first_name = $user->user_login;
		}

		return self::get_svg_avatar( $first_name, $last_name, $size );
	}

	/**
	 * Get cover photo URL for a user
	 *
	 * @param  int $user_id User ID.
	 * @return string|null Cover photo URL or null
	 */
	public static function get_cover_photo( $user_id ) {
		$user_data = NBUF_User_Data::get( $user_id );

		if ( $user_data && ! empty( $user_data->cover_photo_url ) && ! empty( $user_data->cover_photo_path ) ) {
			/* Validate photo path for security */
			$validation = self::validate_photo_path( $user_data->cover_photo_path, $user_id );
			if ( ! is_wp_error( $validation ) ) {
				/*
				 * SECURITY: Reconstruct URL from validated path instead of trusting stored URL.
				 * This prevents attacks where path validation passes but URL points elsewhere.
				 */
				$upload_dir = wp_upload_dir();
				$base_url   = trailingslashit( $upload_dir['baseurl'] );
				$base_path  = trailingslashit( $upload_dir['basedir'] );

				/* Get path relative to uploads directory */
				$relative_path     = str_replace( $base_path, '', $user_data->cover_photo_path );
				$reconstructed_url = $base_url . $relative_path;

				return esc_url( $reconstructed_url );
			}
			/* If validation fails, return null */
		}

		return null;
	}

	/**
	 * Generate SVG initials avatar
	 *
	 * @param  string $first_name First name.
	 * @param  string $last_name  Last name.
	 * @param  int    $size       Size in pixels.
	 * @return string SVG data URI.
	 */
	public static function get_svg_avatar( $first_name, $last_name, $size = 96 ) {
		/* Get initials */
		$initials = self::get_initials( $first_name, $last_name );

		/* Get background color based on initials (consistent per user) */
		$bg_color = self::get_avatar_color( $initials );

		/* Text color (white for readability) */
		$text_color = '#ffffff';

		/* Font size (40% of avatar size) */
		$font_size = round( $size * 0.4 );

		/* SVG markup */
		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" class="nbuf-svg-avatar">
				<rect width="%d" height="%d" fill="%s"/>
				<text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="%d" font-weight="600" fill="%s">%s</text>
			</svg>',
			$size,
			$size,
			$size,
			$size,
			$size,
			$size,
			esc_attr( $bg_color ),
			$font_size,
			esc_attr( $text_color ),
			esc_html( $initials )
		);

		/*
		 * Return as data URI
		 */
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for SVG data URI encoding, not code obfuscation.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Get user initials
	 *
	 * @param  string $first_name First name.
	 * @param  string $last_name  Last name.
	 * @return string Initials (1-2 characters).
	 */
	private static function get_initials( $first_name, $last_name ) {
		$first_initial = ! empty( $first_name ) ? mb_substr( $first_name, 0, 1 ) : '';
		$last_initial  = ! empty( $last_name ) ? mb_substr( $last_name, 0, 1 ) : '';

		/* If we have both, use both */
		if ( $first_initial && $last_initial ) {
			return mb_strtoupper( $first_initial . $last_initial );
		}

		/* If only first name, use first 2 chars */
		if ( $first_initial ) {
			return mb_strtoupper( mb_substr( $first_name, 0, 2 ) );
		}

		/* Fallback */
		return '?';
	}

	/**
	 * Get consistent color for avatar based on initials
	 *
	 * @param  string $initials User initials.
	 * @return string Hex color code.
	 */
	private static function get_avatar_color( $initials ) {
		/* Professional, business-like color palette */
		$colors = array(
			'#0073aa', // WordPress blue.
			'#00a0d2', // Light blue.
			'#826eb4', // Purple.
			'#a88cd4', // Light purple.
			'#c0392b', // Red.
			'#e74c3c', // Light red.
			'#16a085', // Teal.
			'#27ae60', // Green.
			'#2980b9', // Deep blue.
			'#8e44ad', // Deep purple.
			'#d35400', // Orange.
			'#c0392b', // Deep red.
		);

		/* Generate consistent index from initials */
		$hash = 0;
		$len  = mb_strlen( $initials );
		for ( $i = 0; $i < $len; $i++ ) {
			$hash = ord( mb_substr( $initials, $i, 1 ) ) + ( ( $hash << 5 ) - $hash );
		}

		$index = abs( $hash ) % count( $colors );

		return $colors[ $index ];
	}

	/**
	 * Get Gravatar URL
	 *
	 * @param  string $email User email.
	 * @param  int    $size  Size in pixels.
	 * @return string Gravatar URL.
	 */
	private static function get_gravatar_url( $email, $size = 96 ) {
		/*
		 * SECURITY NOTE: MD5 is used here per Gravatar API specification.
		 * This is NOT a security vulnerability - Gravatar requires MD5 hashes.
		 * MD5 weakness is irrelevant for public avatar URL generation.
		 * See: https://en.gravatar.com/site/implement/hash/
		 */
		$hash = md5( strtolower( trim( $email ) ) );
		return sprintf(
			'https://www.gravatar.com/avatar/%s?s=%d&d=mp&r=g',
			$hash,
			$size
		);
	}

	/**
	 * Override WordPress get_avatar filter
	 *
	 * @param  string $avatar      Avatar HTML.
	 * @param  mixed  $id_or_email User ID or email.
	 * @param  int    $size        Size in pixels.
	 * @param  string $default     Default avatar.
	 * @param  string $alt         Alt text.
	 * @return string Modified avatar HTML.
	 */
	public static function custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- $default parameter required by WordPress get_avatar filter hook signature.
		/* Get user ID */
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		/* If no user ID found, return original avatar */
		if ( ! $user_id ) {
			return $avatar;
		}

		/* Get custom photo URL */
		$photo_url = self::get_profile_photo( $user_id, $size );

		/* Build avatar HTML */
		$avatar = sprintf(
			'<img alt="%s" src="%s" srcset="%s 2x" class="avatar avatar-%d photo nbuf-avatar" height="%d" width="%d" loading="lazy" decoding="async" />',
			esc_attr( $alt ),
			esc_url( $photo_url ),
			esc_url( self::get_profile_photo( $user_id, $size * 2 ) ),
			(int) $size,
			(int) $size,
			(int) $size
		);

		return $avatar;
	}

	/**
	 * AJAX: Upload profile photo
	 */
	public static function ajax_upload_profile_photo() {
		/* SECURITY: Multi-layer CSRF protection */
		check_ajax_referer( 'nbuf_upload_profile_photo', 'nonce' );

		/* Verify AJAX request method */
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request method.', 'nobloat-user-foundry' ) ) );
		}

		/* SECURITY: Verify request origin to prevent cross-site attacks */
		$referer  = wp_get_referer();
		$site_url = site_url();
		if ( ! $referer || 0 !== strpos( $referer, $site_url ) ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'csrf_origin_mismatch',
					'critical',
					'AJAX photo upload blocked - invalid origin',
					array(
						'referer'  => $referer,
						'expected' => $site_url,
						'user_id'  => get_current_user_id(),
					)
				);
			}
			wp_send_json_error( array( 'message' => __( 'Invalid request origin.', 'nobloat-user-foundry' ) ) );
		}

		/* Check if user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to upload a photo.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/*
		 * SECURITY: Rate limiting to prevent upload abuse.
		 * Allow 1 upload every 10 seconds per user.
		 */
		$rate_key = 'photo_upload_profile_' . $user_id;
		$attempt  = get_transient( $rate_key );
		if ( false !== $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Too many upload attempts. Please wait 10 seconds.', 'nobloat-user-foundry' ) ) );
		}
		set_transient( $rate_key, time(), 10 );

		/* Check if file was uploaded */
		if ( empty( $_FILES['profile_photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'nobloat-user-foundry' ) ) );
		}

		/* Validate file size (from settings) */
		$max_size_mb = NBUF_Options::get( 'nbuf_profile_photo_max_size', 5 );
		$max_size    = $max_size_mb * 1024 * 1024;
		if ( isset( $_FILES['profile_photo']['size'] ) && $_FILES['profile_photo']['size'] > $max_size ) {
			wp_send_json_error(
				array(
					/* translators: %d: Maximum file size in MB */
					'message' => sprintf( __( 'File is too large. Maximum size is %dMB.', 'nobloat-user-foundry' ), $max_size_mb ),
				)
			);
		}

		/*
		* Validate file type using WordPress core function
		*/
     // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by wp_check_filetype_and_ext().
		$filetype = wp_check_filetype_and_ext(
			isset( $_FILES['profile_photo']['tmp_name'] ) ? wp_unslash( $_FILES['profile_photo']['tmp_name'] ) : '',
			isset( $_FILES['profile_photo']['name'] ) ? wp_unslash( $_FILES['profile_photo']['name'] ) : ''
		);
     // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		if ( ! in_array( $filetype['ext'], $allowed_types, true ) || ! $filetype['type'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.', 'nobloat-user-foundry' ) ) );
		}

		/* Handle upload to temporary location */
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload( $_FILES['profile_photo'], array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		/* Process image (convert to WebP, optimize, resize) */
		$processed = NBUF_Image_Processor::process_image(
			$upload['file'],
			$user_id,
			NBUF_Image_Processor::TYPE_PROFILE
		);

		/*
		 * SECURITY: Delete temporary upload with proper error handling.
		 * No @ error suppression - log failures for security audit.
		 */
		if ( file_exists( $upload['file'] ) && is_file( $upload['file'] ) ) {
			$deleted = wp_delete_file( $upload['file'] );
			if ( ! $deleted ) {
				/* Log deletion failure for security monitoring */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'file_deletion_failed',
						'warning',
						'Failed to delete temporary upload file',
						array(
							'file_path' => $upload['file'],
							'user_id'   => get_current_user_id(),
							'operation' => 'photo_upload_cleanup',
						)
					);
				}
			}
		}

		if ( is_wp_error( $processed ) ) {
			wp_send_json_error( array( 'message' => $processed->get_error_message() ) );
		}

		/*
		 * SECURITY: Validate photo path before saving to database.
		 * If validation fails, securely delete the file with proper verification.
		 */
		$validation = self::validate_photo_path( $processed['path'], $user_id );
		if ( is_wp_error( $validation ) ) {
			/*
			 * Delete the uploaded file since validation failed.
			 * SECURITY: Re-validate file exists and is in correct location before deletion.
			 */
			$real_path   = realpath( $processed['path'] );
			$upload_base = realpath( wp_upload_dir()['basedir'] );

			if ( $real_path && $upload_base && 0 === strpos( $real_path, $upload_base ) && is_file( $real_path ) ) {
				$deleted = wp_delete_file( $real_path );
				if ( ! $deleted ) {
					/* Log deletion failure */
					if ( class_exists( 'NBUF_Security_Log' ) ) {
						NBUF_Security_Log::log(
							'file_deletion_failed',
							'warning',
							'Failed to delete invalid photo after validation failure',
							array(
								'file_path' => $real_path,
								'user_id'   => $user_id,
								'operation' => 'photo_validation_cleanup',
							)
						);
					}
				}
			}
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		/* Save photo URL to user data (old photo already deleted by image processor) */
		NBUF_User_Data::update(
			$user_id,
			array(
				'profile_photo_url'  => $processed['url'],
				'profile_photo_path' => $processed['path'],
			)
		);

		/* Return success */
		wp_send_json_success(
			array(
				'message' => __( 'Profile photo uploaded successfully.', 'nobloat-user-foundry' ),
				'url'     => $processed['url'],
				'format'  => $processed['format'],
			)
		);
	}

	/**
	 * AJAX: Upload cover photo
	 */
	public static function ajax_upload_cover_photo() {
		/* SECURITY: Multi-layer CSRF protection */
		check_ajax_referer( 'nbuf_upload_cover_photo', 'nonce' );

		/* Verify AJAX request method */
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request method.', 'nobloat-user-foundry' ) ) );
		}

		/* SECURITY: Verify request origin to prevent cross-site attacks */
		$referer  = wp_get_referer();
		$site_url = site_url();
		if ( ! $referer || 0 !== strpos( $referer, $site_url ) ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'csrf_origin_mismatch',
					'critical',
					'AJAX photo upload blocked - invalid origin',
					array(
						'referer'  => $referer,
						'expected' => $site_url,
						'user_id'  => get_current_user_id(),
					)
				);
			}
			wp_send_json_error( array( 'message' => __( 'Invalid request origin.', 'nobloat-user-foundry' ) ) );
		}

		/* Check if user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to upload a photo.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/*
		 * SECURITY: Rate limiting to prevent upload abuse.
		 * Allow 1 upload every 10 seconds per user.
		 */
		$rate_key = 'photo_upload_cover_' . $user_id;
		$attempt  = get_transient( $rate_key );
		if ( false !== $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Too many upload attempts. Please wait 10 seconds.', 'nobloat-user-foundry' ) ) );
		}
		set_transient( $rate_key, time(), 10 );

		/* Check if file was uploaded */
		if ( empty( $_FILES['cover_photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'nobloat-user-foundry' ) ) );
		}

		/* Validate file size (from settings) */
		$max_size_mb = NBUF_Options::get( 'nbuf_cover_photo_max_size', 10 );
		$max_size    = $max_size_mb * 1024 * 1024;
		if ( isset( $_FILES['cover_photo']['size'] ) && $_FILES['cover_photo']['size'] > $max_size ) {
			wp_send_json_error(
				array(
					/* translators: %d: Maximum file size in MB */
					'message' => sprintf( __( 'File is too large. Maximum size is %dMB.', 'nobloat-user-foundry' ), $max_size_mb ),
				)
			);
		}

		/*
		* Validate file type using WordPress core function
		*/
     // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by wp_check_filetype_and_ext().
		$filetype = wp_check_filetype_and_ext(
			isset( $_FILES['cover_photo']['tmp_name'] ) ? wp_unslash( $_FILES['cover_photo']['tmp_name'] ) : '',
			isset( $_FILES['cover_photo']['name'] ) ? wp_unslash( $_FILES['cover_photo']['name'] ) : ''
		);
     // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		if ( ! in_array( $filetype['ext'], $allowed_types, true ) || ! $filetype['type'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.', 'nobloat-user-foundry' ) ) );
		}

		/* Handle upload to temporary location */
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload( $_FILES['cover_photo'], array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		/* Process image (convert to WebP, optimize, resize) */
		$processed = NBUF_Image_Processor::process_image(
			$upload['file'],
			$user_id,
			NBUF_Image_Processor::TYPE_COVER
		);

		/*
		 * SECURITY: Delete temporary upload with proper error handling.
		 * No @ error suppression - log failures for security audit.
		 */
		if ( file_exists( $upload['file'] ) && is_file( $upload['file'] ) ) {
			$deleted = wp_delete_file( $upload['file'] );
			if ( ! $deleted ) {
				/* Log deletion failure for security monitoring */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'file_deletion_failed',
						'warning',
						'Failed to delete temporary upload file',
						array(
							'file_path' => $upload['file'],
							'user_id'   => get_current_user_id(),
							'operation' => 'photo_upload_cleanup',
						)
					);
				}
			}
		}

		if ( is_wp_error( $processed ) ) {
			wp_send_json_error( array( 'message' => $processed->get_error_message() ) );
		}

		/*
		 * SECURITY: Validate photo path before saving to database.
		 * If validation fails, securely delete the file with proper verification.
		 */
		$validation = self::validate_photo_path( $processed['path'], $user_id );
		if ( is_wp_error( $validation ) ) {
			/*
			 * Delete the uploaded file since validation failed.
			 * SECURITY: Re-validate file exists and is in correct location before deletion.
			 */
			$real_path   = realpath( $processed['path'] );
			$upload_base = realpath( wp_upload_dir()['basedir'] );

			if ( $real_path && $upload_base && 0 === strpos( $real_path, $upload_base ) && is_file( $real_path ) ) {
				$deleted = wp_delete_file( $real_path );
				if ( ! $deleted ) {
					/* Log deletion failure */
					if ( class_exists( 'NBUF_Security_Log' ) ) {
						NBUF_Security_Log::log(
							'file_deletion_failed',
							'warning',
							'Failed to delete invalid photo after validation failure',
							array(
								'file_path' => $real_path,
								'user_id'   => $user_id,
								'operation' => 'photo_validation_cleanup',
							)
						);
					}
				}
			}
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		/* Save photo URL to user data (old photo already deleted by image processor) */
		NBUF_User_Data::update(
			$user_id,
			array(
				'cover_photo_url'  => $processed['url'],
				'cover_photo_path' => $processed['path'],
			)
		);

		/* Return success */
		wp_send_json_success(
			array(
				'message' => __( 'Cover photo uploaded successfully.', 'nobloat-user-foundry' ),
				'url'     => $processed['url'],
				'format'  => $processed['format'],
			)
		);
	}

	/**
	 * AJAX: Delete profile photo
	 */
	public static function ajax_delete_profile_photo() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_delete_profile_photo', 'nonce' );

		/* Check if user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/* Delete photo */
		self::delete_user_photo( $user_id, 'profile' );

		/* Return success */
		wp_send_json_success(
			array(
				'message' => __( 'Profile photo deleted successfully.', 'nobloat-user-foundry' ),
			)
		);
	}

	/**
	 * AJAX: Delete cover photo
	 */
	public static function ajax_delete_cover_photo() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_delete_cover_photo', 'nonce' );

		/* Check if user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/* Delete photo */
		self::delete_user_photo( $user_id, 'cover' );

		/* Return success */
		wp_send_json_success(
			array(
				'message' => __( 'Cover photo deleted successfully.', 'nobloat-user-foundry' ),
			)
		);
	}

	/**
	 * Delete user photo file and database entry
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Photo type ('profile' or 'cover').
	 */
	private static function delete_user_photo( $user_id, $type = 'profile' ) {
		$user_data = NBUF_User_Data::get( $user_id );

		$url_key  = $type . '_photo_url';
		$path_key = $type . '_photo_path';

		/* Store file path before clearing metadata */
		$file_to_delete = ( $user_data && ! empty( $user_data->$path_key ) ) ? $user_data->$path_key : null;

		/* Remove from database FIRST to prevent orphaned metadata if file deletion fails */
		NBUF_User_Data::update(
			$user_id,
			array(
				$url_key  => '',
				$path_key => '',
			)
		);

		/* Delete physical file after metadata is cleared - validate path to prevent directory traversal */
		if ( $file_to_delete ) {
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'];

			/* Ensure file is within uploads directory */
			$real_path = realpath( $file_to_delete );
			$real_base = realpath( $base_dir );

			if ( $real_path && $real_base && strpos( $real_path, $real_base ) === 0 && file_exists( $real_path ) ) {
				wp_delete_file( $real_path );
			}
		}
	}

	/**
	 * Validate photo file path is legitimate and safe
	 *
	 * Performs comprehensive security validation on photo file paths to prevent:
	 * - Path traversal attacks
	 * - Symlink attacks
	 * - Access to files outside user's photo directory
	 * - Non-image file access
	 *
	 * @param string $file_path Path to validate.
	 * @param int    $user_id   Expected user ID.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_photo_path( $file_path, $user_id ) {
		/* Check 1: Path not empty */
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'Photo path is empty', 'nobloat-user-foundry' ) );
		}

		/* Check 2: Resolve real path (handles symlinks and relative paths) */
		$real_path = realpath( $file_path );
		if ( ! $real_path ) {
			return new WP_Error( 'invalid_path', __( 'Photo path does not exist or is not accessible', 'nobloat-user-foundry' ) );
		}

		/* Check 3: Verify it's within uploads directory */
		$upload_dir = wp_upload_dir();
		$real_base  = realpath( $upload_dir['basedir'] );

		if ( ! $real_base || 0 !== strpos( $real_path, $real_base ) ) {
			return new WP_Error( 'path_outside_uploads', __( 'Photo path is outside uploads directory', 'nobloat-user-foundry' ) );
		}

		/* Check 4: Verify it's within the specific user's nobloat directory */
		$expected_dir = trailingslashit( $real_base ) . 'nobloat/users/' . absint( $user_id ) . '/';
		if ( 0 !== strpos( $real_path, $expected_dir ) ) {
			return new WP_Error(
				'path_wrong_user',
				sprintf(
					/* translators: %d: User ID */
					__( 'Photo path is not for user %d', 'nobloat-user-foundry' ),
					$user_id
				)
			);
		}

		/* Check 5: Verify it's a file and readable */
		if ( ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
			return new WP_Error( 'not_readable', __( 'Photo file is not readable', 'nobloat-user-foundry' ) );
		}

		/* Check 6: Verify MIME type is a valid image */
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $real_path );
			finfo_close( $finfo );

			$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
			if ( ! in_array( $mime, $allowed_mimes, true ) ) {
				return new WP_Error(
					'invalid_mime',
					sprintf(
						/* translators: %s: Detected MIME type */
						__( 'Photo file is not a valid image type. Detected: %s', 'nobloat-user-foundry' ),
						$mime
					)
				);
			}
		}

		/* Check 7: Verify file size is reasonable (prevent huge files) */
		$file_size   = filesize( $real_path );
		$max_size_mb = NBUF_Options::get( 'nbuf_profile_photo_max_size', 5 );
		$max_size    = $max_size_mb * 1024 * 1024;

		if ( $file_size > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %d: Maximum file size in MB */
					__( 'Photo file is too large. Maximum size is %dMB.', 'nobloat-user-foundry' ),
					$max_size_mb
				)
			);
		}

		/* All checks passed */
		return true;
	}

	/**
	 * Render profile photos section for account page
	 *
	 * @param int $user_id User ID.
	 */
	public static function render_account_photos_section( $user_id ) {
		$user_data        = NBUF_User_Data::get( $user_id );
		$gravatar_enabled = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
		$cover_enabled    = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );
		$use_gravatar     = $user_data && ! empty( $user_data->use_gravatar );

		/* Get current photo URLs */
		$profile_photo_url  = self::get_profile_photo( $user_id, 150 );
		$cover_photo_url    = self::get_cover_photo( $user_id );
		$has_custom_profile = $user_data && ! empty( $user_data->profile_photo_url );
		$has_cover          = ! empty( $cover_photo_url );

		?>
		<div class="nbuf-profile-photos-section nbuf-section">
			<h3><?php esc_html_e( 'Profile & Cover Photos', 'nobloat-user-foundry' ); ?></h3>

			<!-- Profile Photo -->
			<div class="nbuf-photo-upload-group">
				<h4><?php esc_html_e( 'Profile Photo', 'nobloat-user-foundry' ); ?></h4>
				<div class="nbuf-current-photo">
					<img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="<?php esc_attr_e( 'Profile Photo', 'nobloat-user-foundry' ); ?>" class="nbuf-avatar nbuf-avatar-large" width="150" height="150">
				</div>

				<div class="nbuf-photo-actions">
					<label for="nbuf_profile_photo_upload" class="nbuf-button nbuf-button-secondary">
		<?php esc_html_e( 'Upload New Photo', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="file" id="nbuf_profile_photo_upload" accept="image/*" style="display:none;">

		<?php if ( $has_custom_profile ) : ?>
						<button type="button" class="nbuf-button nbuf-button-danger" id="nbuf_delete_profile_photo">
			<?php esc_html_e( 'Delete Photo', 'nobloat-user-foundry' ); ?>
						</button>
		<?php endif; ?>
				</div>

				<p class="description">
		<?php esc_html_e( 'JPG, PNG, GIF, or WebP. Max 5MB.', 'nobloat-user-foundry' ); ?>
				</p>

				<p class="description" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 10px;">
					<strong><?php esc_html_e( 'Privacy Notice:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Uploaded photos are stored with randomly-generated URLs to enhance privacy. However, photos are publicly accessible to anyone who has the URL. Do not upload photos containing sensitive or confidential information.', 'nobloat-user-foundry' ); ?>
				</p>

		<?php if ( $gravatar_enabled ) : ?>
					<div class="nbuf-gravatar-toggle" style="margin-top: 15px;">
						<label>
							<input type="checkbox" name="nbuf_use_gravatar" value="1" <?php checked( $use_gravatar ); ?>>
			<?php esc_html_e( 'Use Gravatar instead', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
			<?php esc_html_e( 'Note: Gravatar requires external API calls which may have privacy implications.', 'nobloat-user-foundry' ); ?>
						</p>
					</div>
		<?php endif; ?>
			</div>

		<?php if ( $cover_enabled ) : ?>
				<!-- Cover Photo -->
				<div class="nbuf-photo-upload-group">
					<h4><?php esc_html_e( 'Cover Photo', 'nobloat-user-foundry' ); ?></h4>

			<?php if ( $has_cover ) : ?>
						<div class="nbuf-current-cover">
							<img src="<?php echo esc_url( $cover_photo_url ); ?>" alt="<?php esc_attr_e( 'Cover Photo', 'nobloat-user-foundry' ); ?>" class="nbuf-cover-photo" style="max-width: 100%; height: auto;">
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No cover photo uploaded yet.', 'nobloat-user-foundry' ); ?></p>
					<?php endif; ?>

					<div class="nbuf-photo-actions">
						<label for="nbuf_cover_photo_upload" class="nbuf-button nbuf-button-secondary">
			<?php
			if ( $has_cover ) {
				esc_html_e( 'Change Cover Photo', 'nobloat-user-foundry' );
			} else {
				esc_html_e( 'Upload Cover Photo', 'nobloat-user-foundry' );
			}
			?>
						</label>
						<input type="file" id="nbuf_cover_photo_upload" accept="image/*" style="display:none;">

			<?php if ( $has_cover ) : ?>
							<button type="button" class="nbuf-button nbuf-button-danger" id="nbuf_delete_cover_photo">
				<?php esc_html_e( 'Delete Cover', 'nobloat-user-foundry' ); ?>
							</button>
			<?php endif; ?>
					</div>

					<p class="description">
			<?php esc_html_e( 'JPG, PNG, GIF, or WebP. Max 10MB. Recommended size: 1500x500px.', 'nobloat-user-foundry' ); ?>
					</p>

					<p class="description" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 10px;">
						<strong><?php esc_html_e( 'Privacy Notice:', 'nobloat-user-foundry' ); ?></strong>
			<?php esc_html_e( 'Uploaded photos are stored with randomly-generated URLs to enhance privacy. However, photos are publicly accessible to anyone who has the URL. Do not upload photos containing sensitive or confidential information.', 'nobloat-user-foundry' ); ?>
					</p>
				</div>
		<?php endif; ?>

		<?php
		/* Profile Privacy Settings - only show if public profiles are enabled */
		$public_profiles_enabled = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
		if ( $public_profiles_enabled ) :
			$profile_privacy = ( $user_data && ! empty( $user_data->profile_privacy ) ) ? $user_data->profile_privacy : NBUF_Options::get( 'nbuf_profile_default_privacy', 'members_only' );
			?>
				<div class="nbuf-profile-privacy-group" style="margin-top: 25px; padding-top: 25px; border-top: 1px solid #ddd;">
					<h4><?php esc_html_e( 'Profile Visibility', 'nobloat-user-foundry' ); ?></h4>
					<p class="description">
			<?php esc_html_e( 'Control who can see your public profile page.', 'nobloat-user-foundry' ); ?>
					</p>

					<select name="nbuf_profile_privacy" class="regular-text">
						<option value="private" <?php selected( $profile_privacy, 'private' ); ?>>
			<?php esc_html_e( 'Private - Only you can see your profile', 'nobloat-user-foundry' ); ?>
						</option>
						<option value="members_only" <?php selected( $profile_privacy, 'members_only' ); ?>>
			<?php esc_html_e( 'Members Only - Only logged-in users', 'nobloat-user-foundry' ); ?>
						</option>
						<option value="public" <?php selected( $profile_privacy, 'public' ); ?>>
			<?php esc_html_e( 'Public - Anyone can see', 'nobloat-user-foundry' ); ?>
						</option>
					</select>

			<?php
			$profile_url = NBUF_Public_Profiles::get_profile_url( $user_id );
			if ( $profile_url ) :
				?>
						<p class="description" style="margin-top: 10px;">
					<?php
					printf(
					/* translators: %s: Profile URL */
						esc_html__( 'Your profile URL: %s', 'nobloat-user-foundry' ),
						'<a href="' . esc_url( $profile_url ) . '" target="_blank"><code>' . esc_html( $profile_url ) . '</code></a>'
					);
					?>
						</p>
			<?php endif; ?>
				</div>
		<?php endif; ?>
		</div>


		
		<?php
	}
}
