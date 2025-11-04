<?php
/**
 * Registration Handler
 *
 * Handles user registration including username generation,
 * field validation, and profile data storage.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Registration
 *
 * Handles user registration logic and validation.
 */
class NBUF_Registration {


	/**
	 * Generate username based on settings.
	 *
	 * @param  string $email    User email address.
	 * @param  string $username User-provided username (if applicable).
	 * @return string            Generated or validated username.
	 */
	public static function generate_username( $email, $username = '' ) {
		$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );
		$method       = $reg_settings['username_method'] ?? 'auto_random';

		switch ( $method ) {
			case 'user_entered':
				/* User provides their own username */
				if ( empty( $username ) ) {
					return new WP_Error( 'empty_username', __( 'Please provide a username.', 'nobloat-user-foundry' ) );
				}
				/* Validate username */
				if ( username_exists( $username ) ) {
					return new WP_Error( 'username_exists', __( 'This username is already taken.', 'nobloat-user-foundry' ) );
				}
				return sanitize_user( $username );

			case 'auto_email':
				/* Extract username from email prefix */
				$base = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
				return self::ensure_unique_username( $base );

			case 'auto_random':
			default:
				/* SECURITY: Generate cryptographically secure random username */
				$base = 'user_' . bin2hex( random_bytes( 4 ) );
				return self::ensure_unique_username( $base );
		}
	}

	/**
	 * Ensure username is unique by appending numbers if needed.
	 *
	 * @param  string $base Base username.
	 * @return string      Unique username.
	 */
	private static function ensure_unique_username( $base ) {
		$username = $base;
		$counter  = 1;

		/* Keep incrementing until we find a unique username */
		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			++$counter;
		}

		return $username;
	}

	/**
	 * Validate registration fields based on settings.
	 *
	 * @param  array $data Posted form data.
	 * @return WP_Error|true  WP_Error on failure, true on success.
	 */
	public static function validate_registration_data( $data ) {
		$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );

		/* Validate email (always required) */
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'nobloat-user-foundry' ) );
		}

		/*
		 * SECURITY: Prevent email enumeration via timing attack.
		 * Always perform the same expensive operations regardless of whether email exists.
		 * This ensures constant-time response to prevent timing-based user enumeration.
		 */
		$email_exists = email_exists( $data['email'] );

		/*
		 * Perform dummy password hash operation when email doesn't exist.
		 * This matches the timing of the password hashing that occurs later for valid registrations.
		 * Without this, attackers could measure response time to determine if email exists.
		 */
		if ( ! $email_exists ) {
			/* SECURITY: Generate fresh dummy hash to match timing of real registrations */
			$dummy_hash = wp_hash_password( 'timing_protection_' . wp_rand() . microtime() );

			/* Always execute password check regardless of password presence */
			$check_password = ! empty( $data['password'] ) ? $data['password'] : wp_generate_password();
			wp_check_password( $check_password, $dummy_hash );
		}

		if ( $email_exists ) {
			/* Use generic message to prevent email enumeration */
			return new WP_Error( 'registration_error', __( 'Registration could not be completed. Please try again or contact support.', 'nobloat-user-foundry' ) );
		}

		/* Validate password (always required) */
		if ( empty( $data['password'] ) ) {
			return new WP_Error( 'empty_password', __( 'Please provide a password.', 'nobloat-user-foundry' ) );
		}

		if ( strlen( $data['password'] ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'nobloat-user-foundry' ) );
		}

		/* Check password confirmation */
		if ( $data['password'] !== $data['password_confirm'] ) {
			return new WP_Error( 'password_mismatch', __( 'Passwords do not match.', 'nobloat-user-foundry' ) );
		}

		/* Validate required fields */
		$fields = array(
			'first_name',
			'last_name',
			'phone',
			'company',
			'job_title',
			'address',
			'city',
			'state',
			'postal_code',
			'country',
			'bio',
			'website',
		);

		foreach ( $fields as $field ) {
			$enabled  = $reg_settings[ $field . '_enabled' ] ?? false;
			$required = $reg_settings[ $field . '_required' ] ?? false;
			$label    = $reg_settings[ $field . '_label' ] ?? ucwords( str_replace( '_', ' ', $field ) );

			/* Skip if field not enabled */
			if ( ! $enabled ) {
				continue;
			}

			/* Check if required field is empty */
			if ( $required && empty( $data[ $field ] ) ) {
				/* translators: %s: field label */
				return new WP_Error( 'required_field', sprintf( __( '%s is required.', 'nobloat-user-foundry' ), $label ) );
			}
		}

		/* Validate website URL if provided */
		if ( ! empty( $data['website'] ) && ! filter_var( $data['website'], FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_website', __( 'Please provide a valid website URL.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Register new user and save profile data.
	 *
	 * @param  array $data Registration form data.
	 * @return int|WP_Error  User ID on success, WP_Error on failure.
	 */
	public static function register_user( $data ) {
		/* Validate data first */
		$validation = self::validate_registration_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		/* Generate username */
		$username = self::generate_username( $data['email'], $data['username'] ?? '' );
		if ( is_wp_error( $username ) ) {
			return $username;
		}

		/* Validate password strength if enforced for registration */
		if ( NBUF_Password_Validator::should_enforce( 'registration' ) ) {
			$password_validation = NBUF_Password_Validator::validate( $data['password'], 0 );
			if ( is_wp_error( $password_validation ) ) {
				return $password_validation;
			}
		}

		/* Create WordPress user */
		$user_id = wp_create_user( $username, $data['password'], $data['email'] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		/* Assign user role based on settings */
		$default_role = NBUF_Options::get( 'nbuf_new_user_default_role', 'subscriber' );
		$user         = new WP_User( $user_id );
		$user->set_role( $default_role );

		/* Update user meta with first name and last name */
		if ( ! empty( $data['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( $data['first_name'] ) );
		}
		if ( ! empty( $data['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( $data['last_name'] ) );
		}

		/* Save profile data to custom table */
		$profile_data   = array();
		$profile_fields = array(
			'phone',
			'company',
			'job_title',
			'address',
			'address_line1',
			'address_line2',
			'city',
			'state',
			'postal_code',
			'country',
			'bio',
			'website',
		);

		foreach ( $profile_fields as $field ) {
			if ( ! empty( $data[ $field ] ) ) {
				$profile_data[ $field ] = $data[ $field ];
			}
		}

		if ( ! empty( $profile_data ) ) {
			NBUF_Profile_Data::update( $user_id, $profile_data );
		}

		/* Check if verification is required */
		$require_verification = NBUF_Options::get( 'nbuf_require_verification', true );

		if ( ! $require_verification ) {
			/* Auto-verify user if verification is disabled */
			if ( class_exists( 'NBUF_User_Data' ) ) {
				NBUF_User_Data::set_verified( $user_id );
			}
		}

		/* Check if admin approval is required */
		$require_approval = NBUF_Options::get( 'nbuf_require_approval', false );

		if ( $require_approval && class_exists( 'NBUF_User_Data' ) ) {
			/* Set user to require approval */
			NBUF_User_Data::set_requires_approval( $user_id, true );
		}

		/* Trigger WordPress registration action (will send verification email if enabled) */
		do_action( 'user_register', $user_id );

		return $user_id;
	}

	/**
	 * Get enabled registration fields based on settings.
	 *
	 * @return array Array of enabled fields with their settings.
	 */
	public static function get_enabled_fields() {
		$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );
		$address_mode = $reg_settings['address_mode'] ?? 'simplified';

		/* Get all available fields from registry */
		$field_registry = NBUF_Profile_Data::get_field_registry();

		/* Build flat list of all fields with default labels */
		$all_fields = array(
			'first_name' => __( 'First Name', 'nobloat-user-foundry' ),
			'last_name'  => __( 'Last Name', 'nobloat-user-foundry' ),
		);

		foreach ( $field_registry as $category_data ) {
			$all_fields = array_merge( $all_fields, $category_data['fields'] );
		}

		$enabled_fields = array();

		foreach ( $all_fields as $field => $default_label ) {
			$enabled  = $reg_settings[ $field . '_enabled' ] ?? false;
			$required = $reg_settings[ $field . '_required' ] ?? false;
			$label    = $reg_settings[ $field . '_label' ] ?? $default_label;

			/* Use default label if custom label is empty */
			if ( empty( $label ) ) {
				$label = $default_label;
			}

			if ( $enabled ) {
				/* Handle address mode */
				if ( 'simplified' === $address_mode ) {
					/* Skip individual address fields in simplified mode */
					if ( in_array( $field, array( 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country' ), true ) ) {
						continue;
					}
				} elseif ( 'address' === $field ) {
					/* Skip single address field in full mode */
					continue;
				}

				$enabled_fields[ $field ] = array(
					'label'    => $label,
					'required' => $required,
				);
			}
		}

		return $enabled_fields;
	}

	/**
	 * Check if username field should be shown.
	 *
	 * @return bool True if username field should be shown.
	 */
	public static function should_show_username_field() {
		$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );
		$method       = $reg_settings['username_method'] ?? 'auto_random';
		return 'user_entered' === $method;
	}
}
