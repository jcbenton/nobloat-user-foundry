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
				'wide'  => true,
			),
			'description'  => array(
				'label' => __( 'Biography', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'description', true ),
				'type'  => 'textarea',
				'wide'  => true,
			),
		);

		/* Get custom profile data */
		$profile_data = null;
		if ( class_exists( 'NBUF_Profile_Data' ) ) {
			$profile_data   = NBUF_Profile_Data::get( $user->ID );
			$field_registry = NBUF_Profile_Data::get_field_registry();
			$custom_labels  = NBUF_Options::get( 'nbuf_profile_field_labels', array() );

			/* Build custom fields array - include all fields from registry that user selected as visible */
			foreach ( $field_registry as $category ) {
				if ( isset( $category['fields'] ) && is_array( $category['fields'] ) ) {
					foreach ( $category['fields'] as $key => $default_label ) {
						/* Include field if it's in the user's visible fields selection */
						if ( in_array( $key, $visible_fields, true ) ) {
							$label = ! empty( $custom_labels[ $key ] ) ? $custom_labels[ $key ] : $default_label;
							$value = $profile_data && isset( $profile_data->$key ) ? $profile_data->$key : '';

							/* Determine field type */
							$field_type = 'text';
							if ( in_array( $key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
								$field_type = 'url';
							} elseif ( in_array( $key, array( 'work_email', 'supervisor_email', 'secondary_email' ), true ) ) {
								$field_type = 'email';
							}

							/* Fields that should span full width */
							$wide = in_array( $key, array( 'website', 'bio' ), true );

							$profile_fields[ $key ] = array(
								'label' => $label,
								'value' => $value,
								'type'  => $field_type,
								'wide'  => $wide,
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

		/* Build cover photo HTML */
		if ( $allow_cover && ! empty( $cover_photo ) ) {
			$cover_photo_html = '<div class="nbuf-profile-cover" style="background-image: url(\'' . esc_url( $cover_photo ) . '\');"><div class="nbuf-profile-cover-overlay"></div></div>';
		} else {
			$cover_photo_html = '<div class="nbuf-profile-cover nbuf-profile-cover-default"><div class="nbuf-profile-cover-overlay"></div></div>';
		}

		/* Build profile fields HTML */
		$profile_fields_html = '';
		if ( ! empty( $display_fields ) ) {
			$profile_fields_html .= '<div class="nbuf-profile-fields">';
			$profile_fields_html .= '<h2 class="nbuf-profile-fields-title">' . esc_html__( 'Profile Information', 'nobloat-user-foundry' ) . '</h2>';
			$profile_fields_html .= '<div class="nbuf-profile-fields-grid">';

			foreach ( $display_fields as $field_key => $field_data ) {
				$wide_class   = ! empty( $field_data['wide'] ) ? ' nbuf-profile-field-wide' : '';
				$field_type   = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
				$field_value  = $field_data['value'];
				$field_output = '';

				if ( 'url' === $field_type && ! empty( $field_value ) ) {
					$field_output = '<a href="' . esc_url( $field_value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'email' === $field_type && ! empty( $field_value ) ) {
					$field_output = '<a href="mailto:' . esc_attr( $field_value ) . '">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'textarea' === $field_type && ! empty( $field_value ) ) {
					$field_output = wpautop( wp_kses_post( $field_value ) );
				} else {
					$field_output = esc_html( $field_value );
				}

				$profile_fields_html .= '<div class="nbuf-profile-field' . esc_attr( $wide_class ) . '">';
				$profile_fields_html .= '<div class="nbuf-profile-field-label">' . esc_html( $field_data['label'] ) . '</div>';
				$profile_fields_html .= '<div class="nbuf-profile-field-value">' . $field_output . '</div>';
				$profile_fields_html .= '</div>';
			}

			$profile_fields_html .= '</div></div>';

			/**
			 * Hook for adding custom content to profile page
			 *
			 * @param WP_User $user      User object.
			 * @param object  $user_data User data from custom table.
			 */
			ob_start();
			do_action( 'nbuf_public_profile_content', $user, $user_data );
			$profile_fields_html .= ob_get_clean();
		}

		/* Build edit profile button */
		$edit_profile_button = '';
		if ( is_user_logged_in() && get_current_user_id() === $user->ID ) {
			$account_url = class_exists( 'NBUF_URL' ) ? NBUF_URL::get( 'account' ) : '';
			if ( $account_url ) {
				$edit_profile_button  = '<div class="nbuf-profile-actions">';
				$edit_profile_button .= '<button type="button" class="nbuf-button nbuf-button-primary" onclick="window.location.href=\'' . esc_url( $account_url ) . '\'">' . esc_html__( 'Edit Profile', 'nobloat-user-foundry' ) . '</button>';
				$edit_profile_button .= '</div>';
			}
		}

		/* Capture wp_head output */
		ob_start();
		wp_head();
		$wp_head = ob_get_clean();

		/* Capture wp_footer output */
		ob_start();
		wp_footer();
		$wp_footer = ob_get_clean();

		/* Get body classes */
		$body_class = implode( ' ', get_body_class( 'nbuf-profile-page-body' ) );

		/* Data URIs (SVG avatars) need esc_attr, regular URLs use esc_url */
		$photo_src = 0 === strpos( $profile_photo, 'data:' ) ? esc_attr( $profile_photo ) : esc_url( $profile_photo );

		/* Load template */
		$template = NBUF_Template_Manager::load_template( 'public-profile-html' );

		/* Build replacements */
		$replacements = array(
			'{language_attributes}' => get_language_attributes(),
			'{charset}'             => esc_attr( get_bloginfo( 'charset' ) ),
			'{display_name}'        => esc_html( $display_name ),
			'{site_name}'           => esc_html( get_bloginfo( 'name' ) ),
			'{wp_head}'             => $wp_head,
			'{body_class}'          => esc_attr( $body_class ),
			'{cover_photo_html}'    => $cover_photo_html,
			'{profile_photo}'       => $photo_src,
			'{username}'            => esc_html( $user->user_login ),
			/* translators: %s: User registration date */
			'{joined_date}'         => sprintf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( $registered_date ) ),
			'{profile_fields_html}' => $profile_fields_html,
			'{edit_profile_button}' => $edit_profile_button,
			'{wp_footer}'           => $wp_footer,
		);

		/*
		 * Replace placeholders and output.
		 */
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All values escaped above.
		echo str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
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
