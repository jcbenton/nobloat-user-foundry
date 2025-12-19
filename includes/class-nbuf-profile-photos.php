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
		$profiles_enabled = NBUF_Options::get( 'nbuf_enable_profiles', false );
		$gravatar_enabled = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );

		/* Register sub-tab section hooks */
		if ( $profiles_enabled || $gravatar_enabled ) {
			add_action( 'nbuf_account_profile_photo_subtab', array( __CLASS__, 'render_profile_photo_subtab' ) );
		}

		if ( $profiles_enabled ) {
			add_action( 'nbuf_account_cover_photo_subtab', array( __CLASS__, 'render_cover_photo_subtab' ) );
			add_action( 'nbuf_account_profile_settings_subtab', array( __CLASS__, 'render_profile_settings_subtab' ) );
			/* Backward compatibility hook */
			add_action( 'nbuf_account_visibility_subtab', array( __CLASS__, 'render_visibility_subtab' ) );

			/* Override WordPress get_avatar if custom photos enabled */
			add_filter( 'get_avatar', array( __CLASS__, 'custom_avatar' ), 10, 5 );

			/* AJAX handlers for photo uploads */
			add_action( 'wp_ajax_nbuf_upload_profile_photo', array( __CLASS__, 'ajax_upload_profile_photo' ) );
			add_action( 'wp_ajax_nbuf_upload_cover_photo', array( __CLASS__, 'ajax_upload_cover_photo' ) );
			add_action( 'wp_ajax_nbuf_delete_profile_photo', array( __CLASS__, 'ajax_delete_profile_photo' ) );
			add_action( 'wp_ajax_nbuf_delete_cover_photo', array( __CLASS__, 'ajax_delete_cover_photo' ) );

			/* Admin user profile - photo sections */
			add_action( 'show_user_profile', array( __CLASS__, 'render_admin_photos_section' ), 5 );
			add_action( 'edit_user_profile', array( __CLASS__, 'render_admin_photos_section' ), 5 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );

			/* Admin AJAX handlers for photo deletion */
			add_action( 'wp_ajax_nbuf_admin_delete_profile_photo', array( __CLASS__, 'ajax_admin_delete_profile_photo' ) );
			add_action( 'wp_ajax_nbuf_admin_delete_cover_photo', array( __CLASS__, 'ajax_admin_delete_cover_photo' ) );
		}
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
	 * Generate SVG initials avatar as data URI
	 *
	 * @param  string $first_name First name.
	 * @param  string $last_name  Last name.
	 * @param  int    $size       Size in pixels.
	 * @return string SVG data URI.
	 */
	public static function get_svg_avatar( $first_name, $last_name, $size = 96 ) {
		$svg = self::get_svg_avatar_html( $first_name, $last_name, $size );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for SVG data URI encoding, not code obfuscation.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Generate SVG initials avatar as raw HTML
	 *
	 * @param  string $first_name First name.
	 * @param  string $last_name  Last name.
	 * @param  int    $size       Size in pixels.
	 * @return string SVG HTML markup.
	 */
	public static function get_svg_avatar_html( $first_name, $last_name, $size = 96 ) {
		/* Get initials */
		$initials = self::get_initials( $first_name, $last_name );

		/* Get background color based on initials (consistent per user) */
		$bg_color = self::get_avatar_color( $initials );

		/* Text color (white for readability) */
		$text_color = '#ffffff';

		/* Font size (40% of avatar size) */
		$font_size = round( $size * 0.4 );

		/* SVG markup */
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" class="nbuf-svg-avatar nbuf-avatar nbuf-avatar-large">
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
	public static function get_gravatar_url( $email, $size = 96 ) {
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
	 * Enqueue admin scripts for user profile pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.nbuf-admin-photo-preview {
				display: inline-block;
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 10px;
				margin-bottom: 10px;
			}
			.nbuf-admin-photo-preview img {
				display: block;
				max-width: 100%;
				height: auto;
				border-radius: 4px;
			}
			.nbuf-admin-photo-preview.profile-photo {
				max-width: 150px;
			}
			.nbuf-admin-photo-preview.profile-photo img {
				width: 96px;
				height: 96px;
				object-fit: cover;
			}
			.nbuf-admin-photo-preview.cover-photo {
				max-width: 400px;
			}
			.nbuf-admin-photo-placeholder {
				color: #646970;
				font-style: italic;
				padding: 20px;
				text-align: center;
			}
			.nbuf-admin-photo-actions {
				margin-top: 10px;
			}
			.nbuf-admin-delete-photo {
				color: #b32d2e !important;
			}
			.nbuf-admin-delete-photo:hover {
				color: #a00 !important;
			}
			'
		);
	}

	/**
	 * Render photos section on admin user profile
	 *
	 * @param WP_User $user User object being edited.
	 */
	public static function render_admin_photos_section( $user ) {
		$profile_photo_url = self::get_profile_photo( $user->ID, 96 );
		$cover_photo_url   = self::get_cover_photo( $user->ID );
		$user_data         = NBUF_User_Data::get( $user->ID );

		/* Check if user has a custom uploaded profile photo (not SVG fallback) */
		$has_custom_profile = $user_data && ! empty( $user_data->profile_photo_url ) && ! empty( $user_data->profile_photo_path );
		$has_cover          = ! empty( $cover_photo_url );

		/* Only show if user can edit users */
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$delete_profile_nonce = wp_create_nonce( 'nbuf_admin_delete_profile_photo' );
		$delete_cover_nonce   = wp_create_nonce( 'nbuf_admin_delete_cover_photo' );
		?>
		<h2><?php esc_html_e( 'User Photos', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label><?php esc_html_e( 'Profile Photo', 'nobloat-user-foundry' ); ?></label></th>
				<td>
					<div class="nbuf-admin-photo-preview profile-photo">
						<img src="<?php echo esc_attr( $profile_photo_url ); ?>" alt="<?php esc_attr_e( 'Profile photo', 'nobloat-user-foundry' ); ?>">
					</div>
					<?php if ( $has_custom_profile ) : ?>
						<div class="nbuf-admin-photo-actions">
							<a href="#" class="nbuf-admin-delete-photo" data-type="profile" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $delete_profile_nonce ); ?>">
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete Photo', 'nobloat-user-foundry' ); ?>
							</a>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'User has no custom photo (showing default avatar).', 'nobloat-user-foundry' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Cover Photo', 'nobloat-user-foundry' ); ?></label></th>
				<td>
					<?php if ( $has_cover ) : ?>
						<div class="nbuf-admin-photo-preview cover-photo">
							<img src="<?php echo esc_url( $cover_photo_url ); ?>" alt="<?php esc_attr_e( 'Cover photo', 'nobloat-user-foundry' ); ?>">
						</div>
						<div class="nbuf-admin-photo-actions">
							<a href="#" class="nbuf-admin-delete-photo" data-type="cover" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $delete_cover_nonce ); ?>">
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete Photo', 'nobloat-user-foundry' ); ?>
							</a>
						</div>
					<?php else : ?>
						<div class="nbuf-admin-photo-preview cover-photo">
							<div class="nbuf-admin-photo-placeholder">
								<?php esc_html_e( 'No cover photo set', 'nobloat-user-foundry' ); ?>
							</div>
						</div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<script>
		jQuery(document).ready(function($) {
			$('.nbuf-admin-delete-photo').on('click', function(e) {
				e.preventDefault();

				var $link = $(this);
				var type = $link.data('type');
				var userId = $link.data('user');
				var nonce = $link.data('nonce');
				var confirmMsg = type === 'profile'
					? '<?php echo esc_js( __( 'Are you sure you want to delete this user\'s profile photo?', 'nobloat-user-foundry' ) ); ?>'
					: '<?php echo esc_js( __( 'Are you sure you want to delete this user\'s cover photo?', 'nobloat-user-foundry' ) ); ?>';

				if (!confirm(confirmMsg)) {
					return;
				}

				$link.text('<?php echo esc_js( __( 'Deleting...', 'nobloat-user-foundry' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_admin_delete_' + type + '_photo',
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error deleting photo.', 'nobloat-user-foundry' ) ); ?>');
							$link.html('<span class="dashicons dashicons-trash"></span> <?php echo esc_js( __( 'Delete Photo', 'nobloat-user-foundry' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Error deleting photo.', 'nobloat-user-foundry' ) ); ?>');
						$link.html('<span class="dashicons dashicons-trash"></span> <?php echo esc_js( __( 'Delete Photo', 'nobloat-user-foundry' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
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
		$photo_url    = self::get_profile_photo( $user_id, $size );
		$photo_url_2x = self::get_profile_photo( $user_id, $size * 2 );

		/*
		 * Handle data URIs (SVG avatars) separately since esc_url() doesn't allow data: scheme.
		 * We control the SVG generation so it's safe to use esc_attr() for data URIs.
		 */
		$is_data_uri    = 0 === strpos( $photo_url, 'data:' );
		$is_data_uri_2x = 0 === strpos( $photo_url_2x, 'data:' );

		$src    = $is_data_uri ? esc_attr( $photo_url ) : esc_url( $photo_url );
		$srcset = $is_data_uri_2x ? esc_attr( $photo_url_2x ) : esc_url( $photo_url_2x );

		/* Build avatar HTML */
		$avatar = sprintf(
			'<img alt="%s" src="%s" srcset="%s 2x" class="avatar avatar-%d photo nbuf-avatar" height="%d" width="%d" loading="lazy" decoding="async" />',
			esc_attr( $alt ),
			$src,
			$srcset,
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

		/* Save photo URL to user data and disable Gravatar (old photo already deleted by image processor) */
		NBUF_User_Data::update(
			$user_id,
			array(
				'profile_photo_url'  => $processed['url'],
				'profile_photo_path' => $processed['path'],
				'use_gravatar'       => 0,
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
	 * AJAX: Admin delete profile photo (moderation)
	 */
	public static function ajax_admin_delete_profile_photo() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_admin_delete_profile_photo', 'nonce' );

		/* Check admin capability */
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete user photos.', 'nobloat-user-foundry' ) ) );
		}

		/* Get user ID from POST */
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'nobloat-user-foundry' ) ) );
		}

		/* Verify user exists */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'nobloat-user-foundry' ) ) );
		}

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
	 * AJAX: Admin delete cover photo (moderation)
	 */
	public static function ajax_admin_delete_cover_photo() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_admin_delete_cover_photo', 'nonce' );

		/* Check admin capability */
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete user photos.', 'nobloat-user-foundry' ) ) );
		}

		/* Get user ID from POST */
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'nobloat-user-foundry' ) ) );
		}

		/* Verify user exists */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'nobloat-user-foundry' ) ) );
		}

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
	 * Render Profile Photo sub-tab content
	 *
	 * @param int $user_id User ID.
	 */
	public static function render_profile_photo_subtab( $user_id ) {
		$user_data        = NBUF_User_Data::get( $user_id );
		$profiles_enabled = NBUF_Options::get( 'nbuf_enable_profiles', false );
		$gravatar_enabled = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
		$use_gravatar     = $user_data && ! empty( $user_data->use_gravatar );
		$has_custom_profile = $user_data && ! empty( $user_data->profile_photo_url );

		/* Get current photo - either custom upload, gravatar, or SVG avatar */
		$user = get_userdata( $user_id );
		?>
		<h3><?php esc_html_e( 'Profile Photo', 'nobloat-user-foundry' ); ?></h3>
		<p class="nbuf-method-description"><?php esc_html_e( 'Manage your profile photo appearance. Choose between a custom upload, Gravatar, or default initials avatar.', 'nobloat-user-foundry' ); ?></p>

		<div class="nbuf-photo-upload-group">
			<div class="nbuf-current-photo">
				<?php
				if ( $has_custom_profile ) {
					/* Custom uploaded photo */
					$profile_photo_url = self::get_profile_photo( $user_id, 150 );
					echo '<img src="' . esc_url( $profile_photo_url ) . '" alt="' . esc_attr__( 'Profile Photo', 'nobloat-user-foundry' ) . '" class="nbuf-avatar nbuf-avatar-large" width="150" height="150">';
				} elseif ( $gravatar_enabled && $use_gravatar && $user ) {
					/* Gravatar */
					$gravatar_url = self::get_gravatar_url( $user->user_email, 150 );
					echo '<img src="' . esc_url( $gravatar_url ) . '" alt="' . esc_attr__( 'Profile Photo', 'nobloat-user-foundry' ) . '" class="nbuf-avatar nbuf-avatar-large" width="150" height="150">';
				} else {
					/* SVG initials avatar - output directly */
					$first_name = $user ? ( ! empty( $user->first_name ) ? $user->first_name : $user->user_login ) : '';
					$last_name  = $user ? $user->last_name : '';
					echo self::get_svg_avatar_html( $first_name, $last_name, 150 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated internally with escaped values.
				}
				?>
			</div>

			<div class="nbuf-photo-options">
				<p class="description nbuf-section-spacing">
					<?php esc_html_e( 'Choose how your profile photo is displayed:', 'nobloat-user-foundry' ); ?>
				</p>

				<?php if ( $profiles_enabled ) : ?>
					<div class="nbuf-photo-option">
						<div class="nbuf-photo-actions">
							<button type="button" class="nbuf-button nbuf-button-primary nbuf-photo-upload-btn" id="nbuf_profile_photo_upload_btn">
								<?php esc_html_e( 'Upload Custom Photo', 'nobloat-user-foundry' ); ?>
							</button>
							<input type="file" id="nbuf_profile_photo_upload" accept="image/*" class="nbuf-file-input-hidden">

							<?php if ( $has_custom_profile ) : ?>
								<button type="button" class="nbuf-button nbuf-button-primary nbuf-photo-delete-btn" id="nbuf_delete_profile_photo">
									<?php esc_html_e( 'Delete Photo', 'nobloat-user-foundry' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<p class="description nbuf-form-help">
							<?php esc_html_e( 'JPG, PNG, GIF, or WebP. Max 5MB. Images are automatically converted to WebP for optimal performance.', 'nobloat-user-foundry' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( $gravatar_enabled && ! $has_custom_profile ) : ?>
					<?php
					/* Get the correct form action URL (supports Universal Router virtual pages) */
					$gravatar_form_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
						? NBUF_Universal_Router::get_url( 'account', 'profile' )
						: get_permalink();
					?>
					<form method="post" action="<?php echo esc_url( $gravatar_form_url ); ?>" class="nbuf-gravatar-form">
						<?php wp_nonce_field( 'nbuf_account_gravatar', 'nbuf_gravatar_nonce', false ); ?>
						<input type="hidden" name="nbuf_account_action" value="update_gravatar">
						<input type="hidden" name="nbuf_active_tab" value="profile">
						<input type="hidden" name="nbuf_active_subtab" value="profile-photo">

						<div class="nbuf-photo-option nbuf-photo-option-gravatar">
							<label class="nbuf-gravatar-label">
								<input type="checkbox" name="nbuf_use_gravatar" id="nbuf_use_gravatar" value="1" <?php checked( $use_gravatar ); ?>>
								<strong><?php esc_html_e( 'Use Gravatar', 'nobloat-user-foundry' ); ?></strong>
							</label>
							<p class="description nbuf-gravatar-desc">
								<?php esc_html_e( 'Display your Gravatar image linked to your email address.', 'nobloat-user-foundry' ); ?>
								<br>
								<em class="nbuf-gravatar-note"><?php esc_html_e( 'Note: Gravatar requires external API calls which may have privacy implications.', 'nobloat-user-foundry' ); ?></em>
							</p>
							<button type="submit" class="nbuf-button nbuf-button-primary nbuf-gravatar-submit">
								<?php esc_html_e( 'Save Gravatar Setting', 'nobloat-user-foundry' ); ?>
							</button>
						</div>
					</form>
				<?php endif; ?>

				<?php if ( ! $profiles_enabled && ! $gravatar_enabled ) : ?>
					<p class="description">
						<?php esc_html_e( 'Your profile displays a default avatar based on your initials.', 'nobloat-user-foundry' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( $profiles_enabled ) : ?>
				<p class="description nbuf-description-warning nbuf-margin-top">
					<strong><?php esc_html_e( 'Privacy Notice:', 'nobloat-user-foundry' ); ?></strong>
					<?php esc_html_e( 'Uploaded photos are stored with randomly-generated URLs. Photos are publicly accessible to anyone who has the URL.', 'nobloat-user-foundry' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Cover Photo sub-tab content
	 *
	 * @param int $user_id User ID.
	 */
	public static function render_cover_photo_subtab( $user_id ) {
		$user_data       = NBUF_User_Data::get( $user_id );
		$cover_photo_url = self::get_cover_photo( $user_id );
		$has_cover       = ! empty( $cover_photo_url );
		?>
		<h3><?php esc_html_e( 'Cover Photo', 'nobloat-user-foundry' ); ?></h3>
		<p class="nbuf-method-description"><?php esc_html_e( 'Cover photos appear at the top of your public profile page.', 'nobloat-user-foundry' ); ?></p>

		<div class="nbuf-photo-upload-group">
			<?php if ( $has_cover ) : ?>
				<div class="nbuf-current-cover">
					<img src="<?php echo esc_url( $cover_photo_url ); ?>" alt="<?php esc_attr_e( 'Cover Photo', 'nobloat-user-foundry' ); ?>" class="nbuf-cover-photo">
				</div>
			<?php endif; ?>

			<div class="nbuf-photo-actions">
				<button type="button" class="nbuf-button nbuf-button-primary nbuf-photo-upload-btn" id="nbuf_cover_photo_upload_btn">
					<?php echo $has_cover ? esc_html__( 'Change Cover Photo', 'nobloat-user-foundry' ) : esc_html__( 'Upload Cover Photo', 'nobloat-user-foundry' ); ?>
				</button>
				<input type="file" id="nbuf_cover_photo_upload" accept="image/*" class="nbuf-file-input-hidden">

				<?php if ( $has_cover ) : ?>
					<button type="button" class="nbuf-button nbuf-button-primary nbuf-photo-delete-btn" id="nbuf_delete_cover_photo">
						<?php esc_html_e( 'Delete Cover', 'nobloat-user-foundry' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<p class="description nbuf-form-help">
				<?php esc_html_e( 'JPG, PNG, GIF, or WebP. Max 10MB. Recommended size: 1500x500px.', 'nobloat-user-foundry' ); ?>
			</p>

			<p class="description nbuf-description-warning nbuf-margin-top">
				<strong><?php esc_html_e( 'Privacy Notice:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'Uploaded photos are stored with randomly-generated URLs. Photos are publicly accessible to anyone who has the URL.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Profile Settings sub-tab content (consolidated visibility + directory)
	 *
	 * @param int $user_id User ID.
	 */
	public static function render_profile_settings_subtab( $user_id ) {
		$user_data       = NBUF_User_Data::get( $user_id );
		$profile_privacy = ( $user_data && ! empty( $user_data->profile_privacy ) ) ? $user_data->profile_privacy : NBUF_Options::get( 'nbuf_profile_default_privacy', 'private' );
		$show_in_directory = $user_data ? (int) $user_data->show_in_directory : 0;

		/* Get user's visible fields preference (default to all enabled) */
		$visible_fields = array();
		if ( $user_data && ! empty( $user_data->visible_fields ) ) {
			$visible_fields = maybe_unserialize( $user_data->visible_fields );
			if ( ! is_array( $visible_fields ) ) {
				$visible_fields = array();
			}
		}
		?>
		<h3><?php esc_html_e( 'Profile Settings', 'nobloat-user-foundry' ); ?></h3>
		<p class="nbuf-method-description"><?php esc_html_e( 'Configure your profile visibility and control which information appears on your public profile.', 'nobloat-user-foundry' ); ?></p>

		<div class="nbuf-profile-settings-section">

			<!-- Profile Privacy Settings -->
			<div class="nbuf-profile-settings-group">
				<h4><?php esc_html_e( 'Profile Privacy', 'nobloat-user-foundry' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Control who can see your public profile page.', 'nobloat-user-foundry' ); ?>
				</p>

				<select name="nbuf_profile_privacy" class="regular-text nbuf-profile-select">
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
					<div class="nbuf-profile-url-meta">
						<strong><?php esc_html_e( 'Your Profile URL', 'nobloat-user-foundry' ); ?></strong>
						<a href="<?php echo esc_url( $profile_url ); ?>" target="_blank" class="nbuf-profile-url-link"><?php echo esc_html( $profile_url ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Member Directory Settings -->
			<div class="nbuf-profile-settings-group">
				<h4><?php esc_html_e( 'Member Directory', 'nobloat-user-foundry' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Control whether your profile appears in the public member directory.', 'nobloat-user-foundry' ); ?>
				</p>

				<label class="nbuf-checkbox-label">
					<input type="checkbox" name="nbuf_show_in_directory" value="1" <?php checked( $show_in_directory, 1 ); ?>>
					<span><?php esc_html_e( 'Show my profile in the member directory', 'nobloat-user-foundry' ); ?></span>
				</label>
				<input type="hidden" name="nbuf_directory_submitted" value="1">

				<?php
				$directory_url = class_exists( 'NBUF_Universal_Router' ) ? NBUF_Universal_Router::get_url( 'members' ) : '';
				if ( $directory_url ) :
					?>
					<div class="nbuf-directory-url-meta">
						<strong><?php esc_html_e( 'Member Directory', 'nobloat-user-foundry' ); ?></strong>
						<a href="<?php echo esc_url( $directory_url ); ?>" target="_blank" class="nbuf-directory-link"><?php echo esc_html( $directory_url ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Profile Fields Visibility -->
			<div class="nbuf-profile-settings-group">
				<div class="nbuf-profile-fields-section">
					<h4><?php esc_html_e( 'Visible Profile Fields', 'nobloat-user-foundry' ); ?></h4>
					<p class="description">
						<?php esc_html_e( 'Select which fields to show on your public profile page.', 'nobloat-user-foundry' ); ?>
					</p>

					<?php
					/* Native WordPress fields */
					$native_fields = array(
						'display_name' => __( 'Display Name', 'nobloat-user-foundry' ),
						'first_name'   => __( 'First Name', 'nobloat-user-foundry' ),
						'last_name'    => __( 'Last Name', 'nobloat-user-foundry' ),
						'user_url'     => __( 'Website', 'nobloat-user-foundry' ),
						'description'  => __( 'Biography', 'nobloat-user-foundry' ),
					);

					/* Get enabled custom fields for account page */
					$enabled_custom_fields = array();
					if ( class_exists( 'NBUF_Profile_Data' ) ) {
						$enabled_keys   = NBUF_Profile_Data::get_account_fields();
						$field_registry = NBUF_Profile_Data::get_field_registry();
						$custom_labels  = NBUF_Options::get( 'nbuf_profile_field_labels', array() );

						foreach ( $field_registry as $category ) {
							if ( isset( $category['fields'] ) && is_array( $category['fields'] ) ) {
								foreach ( $category['fields'] as $key => $default_label ) {
									if ( in_array( $key, $enabled_keys, true ) ) {
										/* Use custom label if set, otherwise default */
										$label = ! empty( $custom_labels[ $key ] ) ? $custom_labels[ $key ] : $default_label;
										$enabled_custom_fields[ $key ] = $label;
									}
								}
							}
						}
					}

					/* Combine all available fields */
					$all_fields = array_merge( $native_fields, $enabled_custom_fields );

					if ( ! empty( $all_fields ) ) :
						?>
						<!-- Marker field to detect form submission even when no checkboxes are checked -->
						<input type="hidden" name="nbuf_visible_fields_submitted" value="1">
						<div class="nbuf-profile-field-grid">
							<?php foreach ( $all_fields as $field_key => $field_label ) : ?>
								<label>
									<input type="checkbox" name="nbuf_visible_fields[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $visible_fields, true ) ); ?>>
									<span><?php echo esc_html( $field_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No profile fields are currently enabled.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * DEPRECATED: Render Visibility sub-tab content
	 * Kept for backward compatibility. Use render_profile_settings_subtab() instead.
	 *
	 * @deprecated Use render_profile_settings_subtab()
	 * @param int $user_id User ID.
	 */
	public static function render_visibility_subtab( $user_id ) {
		self::render_profile_settings_subtab( $user_id );
	}
}
