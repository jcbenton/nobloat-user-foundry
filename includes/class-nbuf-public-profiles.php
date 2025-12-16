<?php
/**
 * Public Profile Pages
 *
 * Handles public-facing user profile pages with customizable privacy settings.
 *
 * Features:
 * - Custom URL structure (e.g., site.com/profile/username)
 * - User-controlled privacy (public/members-only/private)
 * - Responsive profile template with cover photo
 * - Minimal default CSS with extensive classes for customization
 * - SEO-friendly with proper meta tags
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Public_Profiles
 *
 * Handles public-facing user profile pages with customizable privacy.
 */
class NBUF_Public_Profiles {


	/**
	 * Initialize public profiles
	 */
	public static function init() {
		/* Profile URLs are now handled by NBUF_Universal_Router at /user-foundry/profile/{username}/ */

		/* Enqueue profile page CSS */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_profile_css' ) );
	}

	/**
	 * Check if current user can view a profile
	 *
	 * @param  int $user_id User ID to check.
	 * @return bool True if can view, false otherwise.
	 */
	public static function can_view_profile( $user_id ) {
		$user_data = NBUF_User_Data::get( $user_id );
		$privacy   = ( $user_data && ! empty( $user_data->profile_privacy ) ) ? $user_data->profile_privacy : NBUF_Options::get( 'nbuf_profile_default_privacy', 'private' );

		// Public profiles are visible to everyone.
		if ( 'public' === $privacy ) {
			return true;
		}

		// Private profiles are only visible to the owner.
		if ( 'private' === $privacy ) {
			return is_user_logged_in() && get_current_user_id() === $user_id;
		}

		// Members-only profiles require login.
		if ( 'members_only' === $privacy ) {
			return is_user_logged_in();
		}

		return false;
	}

	/**
	 * Render profile page
	 *
	 * @param WP_User $user User object.
	 */
	public static function render_profile_page( $user ) {
		// Get user data.
		$user_data = NBUF_User_Data::get( $user->ID );

		// Get profile photo.
		$profile_photo = NBUF_Profile_Photos::get_profile_photo( $user->ID, 150 );

		// Get cover photo.
		$cover_photo = ( $user_data && ! empty( $user_data->cover_photo_url ) ) ? $user_data->cover_photo_url : '';
		$allow_cover = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );

		// Get display name.
		$display_name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;

		// Get user registration date.
		$registered      = $user->user_registered;
		$registered_date = mysql2date( get_option( 'date_format' ), $registered );

		/* Get user's visible fields preference */
		$visible_fields = array();
		if ( $user_data && ! empty( $user_data->visible_fields ) ) {
			$visible_fields = maybe_unserialize( $user_data->visible_fields );
			if ( ! is_array( $visible_fields ) ) {
				$visible_fields = array();
			}
		}

		/* Prepare profile fields to display */
		$profile_fields = array();

		/* Native WordPress fields */
		$native_fields = array(
			'display_name' => array(
				'label' => __( 'Display Name', 'nobloat-user-foundry' ),
				'value' => $user->display_name,
			),
			'first_name'   => array(
				'label' => __( 'First Name', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'first_name', true ),
			),
			'last_name'    => array(
				'label' => __( 'Last Name', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'last_name', true ),
			),
			'user_url'     => array(
				'label' => __( 'Website', 'nobloat-user-foundry' ),
				'value' => $user->user_url,
				'type'  => 'url',
			),
			'description'  => array(
				'label' => __( 'Biography', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'description', true ),
				'type'  => 'textarea',
			),
		);

		/* Get custom profile data */
		$profile_data = null;
		if ( class_exists( 'NBUF_Profile_Data' ) ) {
			$profile_data     = NBUF_Profile_Data::get( $user->ID );
			$field_registry   = NBUF_Profile_Data::get_field_registry();
			$custom_labels    = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
			$enabled_fields   = NBUF_Profile_Data::get_account_fields();

			/* Build custom fields array */
			foreach ( $field_registry as $category ) {
				if ( isset( $category['fields'] ) && is_array( $category['fields'] ) ) {
					foreach ( $category['fields'] as $key => $default_label ) {
						if ( in_array( $key, $enabled_fields, true ) ) {
							$label = ! empty( $custom_labels[ $key ] ) ? $custom_labels[ $key ] : $default_label;
							$value = $profile_data && isset( $profile_data->$key ) ? $profile_data->$key : '';

							/* Determine field type */
							$field_type = 'text';
							if ( in_array( $key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
								$field_type = 'url';
							} elseif ( in_array( $key, array( 'work_email', 'supervisor_email', 'secondary_email' ), true ) ) {
								$field_type = 'email';
							}

							$profile_fields[ $key ] = array(
								'label' => $label,
								'value' => $value,
								'type'  => $field_type,
							);
						}
					}
				}
			}
		}

		/* Merge native and custom fields */
		$all_fields = array_merge( $native_fields, $profile_fields );

		/* Filter to only visible fields */
		$display_fields = array();
		foreach ( $all_fields as $key => $field_data ) {
			if ( in_array( $key, $visible_fields, true ) && ! empty( $field_data['value'] ) ) {
				$display_fields[ $key ] = $field_data;
			}
		}

		// Start output buffering.
		ob_start();

		// Output HTML head.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $display_name ); ?> - <?php bloginfo( 'name' ); ?></title>
		<?php wp_head(); ?>
		</head>
		<body <?php body_class( 'nbuf-profile-page-body' ); ?>>

		<div class="nbuf-profile-page">
			<!-- Profile Header with Cover Photo -->
			<div class="nbuf-profile-header">
		<?php if ( $allow_cover && ! empty( $cover_photo ) ) : ?>
					<div class="nbuf-profile-cover" style="background-image: url('<?php echo esc_url( $cover_photo ); ?>');">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php else : ?>
					<div class="nbuf-profile-cover nbuf-profile-cover-default">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php endif; ?>

				<div class="nbuf-profile-avatar-wrap">
					<?php
					/* Data URIs (SVG avatars) need esc_attr, regular URLs use esc_url */
					$photo_src = 0 === strpos( $profile_photo, 'data:' ) ? esc_attr( $profile_photo ) : esc_url( $profile_photo );
					?>
					<img src="<?php echo $photo_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above based on type. ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="nbuf-profile-avatar" width="150" height="150" loading="lazy">
				</div>
			</div>

			<!-- Profile Info -->
			<div class="nbuf-profile-content">
				<div class="nbuf-profile-info">
					<h1 class="nbuf-profile-name"><?php echo esc_html( $display_name ); ?></h1>
					<p class="nbuf-profile-username">@<?php echo esc_html( $user->user_login ); ?></p>

					<div class="nbuf-profile-meta">
						<span class="nbuf-profile-meta-item">
							<svg class="nbuf-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M8 8a3 3 0 100-6 3 3 0 000 6zm0 1.5c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
							</svg>
		<?php
			/* translators: %s: User registration date */
			printf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( $registered_date ) );
		?>
						</span>
					</div>
				</div>

		<?php if ( ! empty( $display_fields ) ) : ?>
				<div class="nbuf-profile-fields">
					<h2 class="nbuf-profile-fields-title"><?php esc_html_e( 'Profile Information', 'nobloat-user-foundry' ); ?></h2>
					<div class="nbuf-profile-fields-grid">
			<?php foreach ( $display_fields as $key => $field_data ) : ?>
						<div class="nbuf-profile-field">
							<div class="nbuf-profile-field-label"><?php echo esc_html( $field_data['label'] ); ?></div>
							<div class="nbuf-profile-field-value">
				<?php
				$field_type  = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
				$field_value = $field_data['value'];

				if ( 'url' === $field_type && ! empty( $field_value ) ) {
					/* Display as clickable link */
					echo '<a href="' . esc_url( $field_value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'email' === $field_type && ! empty( $field_value ) ) {
					/* Display as mailto link */
					echo '<a href="mailto:' . esc_attr( $field_value ) . '">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'textarea' === $field_type && ! empty( $field_value ) ) {
					/* Display as formatted text with paragraphs */
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() handles escaping.
					echo wpautop( wp_kses_post( $field_value ) );
				} else {
					/* Display as plain text */
					echo esc_html( $field_value );
				}
				?>
							</div>
						</div>
			<?php endforeach; ?>
					</div>
				</div>
		<?php endif; ?>

		<?php
		/**
		 * Hook for adding custom content to profile page
		 *
		 * @param WP_User $user User object.
		 * @param array   $user_data User data from custom table.
		 */
		do_action( 'nbuf_public_profile_content', $user, $user_data );
		?>

		<?php if ( is_user_logged_in() && get_current_user_id() === $user->ID ) : ?>
					<div class="nbuf-profile-actions">
			<?php
			$account_page_id = NBUF_Options::get( 'nbuf_page_account' );
			if ( $account_page_id ) :
				?>
							<a href="<?php echo esc_url( get_permalink( $account_page_id ) ); ?>" class="nbuf-button nbuf-button-primary">
				<?php esc_html_e( 'Edit Profile', 'nobloat-user-foundry' ); ?>
							</a>
			<?php endif; ?>
					</div>
		<?php endif; ?>
			</div>
		</div>

		<?php wp_footer(); ?>
		</body>
		</html>
		<?php

		// Output the buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output with escaped content.
	}

	/**
	 * Enqueue profile page CSS
	 */
	public static function enqueue_profile_css() {
		$username = get_query_var( 'nbuf_profile_user' );

		if ( empty( $username ) ) {
			return;
		}

		/* Add body class for CSS specificity (beats page builder styles) */
		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = 'nbuf-page';
				$classes[] = 'nbuf-page-profile';
				return $classes;
			}
		);

		/* Use CSS Manager for file-based loading with minification */
		if ( class_exists( 'NBUF_CSS_Manager' ) ) {
			NBUF_CSS_Manager::enqueue_css(
				'nbuf-profile',
				'profile',
				'nbuf_profile_custom_css',
				'nbuf_css_write_failed_profile'
			);
		}
	}

	/**
	 * Get default profile CSS from template file
	 *
	 * @return string CSS code.
	 */
	public static function get_default_css() {
		if ( class_exists( 'NBUF_CSS_Manager' ) ) {
			return NBUF_CSS_Manager::load_default_css( 'profile' );
		}
		return '';
	}

	/**
	 * Get profile CSS (database first, then disk default)
	 *
	 * @return string CSS code.
	 */
	public static function get_profile_css() {
		$css = NBUF_Options::get( 'nbuf_profile_custom_css', '' );
		if ( empty( $css ) ) {
			$css = self::get_default_css();
		}
		return $css;
	}

	/**
	 * Get profile URL for a user
	 *
	 * @param  int|string $user User ID or username.
	 * @return string Profile URL.
	 */
	public static function get_profile_url( $user ) {
		if ( is_numeric( $user ) ) {
			$user_obj = get_userdata( $user );
			if ( ! $user_obj ) {
				return '';
			}
			$username = $user_obj->user_login;
		} else {
			$username = $user;
		}

		/* Profile URLs are handled by Universal Router */
		return NBUF_Universal_Router::get_url( 'profile', $username );
	}
}
