<?php
/**
 * User Profile Data Management
 *
 * Handles all user profile data stored in custom nbuf_user_profile table.
 * Manages extended user fields like phone, company, address, etc.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for profile data management.
 * Custom nbuf_user_profile table stores extended user fields and cannot use
 * WordPress's standard meta APIs. Caching is not implemented as data changes
 * frequently and caching would introduce stale data issues.
 */

/**
 * Profile data management class.
 *
 * Provides interface for reading/writing user profile data.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @author     NoBloat
 */
class NBUF_Profile_Data {


	/**
	 * Get all available profile fields organized by category.
	 *
	 * @since  1.0.0
	 * @return array    Field registry with categories, keys, and labels.
	 */
	public static function get_field_registry() {
		return array(
			'basic_contact' => array(
				'label'  => 'Basic Contact',
				'fields' => array(
					'phone'           => 'Phone Number',
					'mobile_phone'    => 'Mobile Phone',
					'work_phone'      => 'Work Phone',
					'fax'             => 'Fax',
					'preferred_name'  => 'Preferred Name',
					'nickname'        => 'Nickname',
					'pronouns'        => 'Pronouns',
					'gender'          => 'Gender',
					'date_of_birth'   => 'Date of Birth',
					'timezone'        => 'Timezone',
					'secondary_email' => 'Secondary Email',
				),
			),
			'address'       => array(
				'label'  => 'Address',
				'fields' => array(
					'address'       => 'Address (Full)',
					'address_line1' => 'Address Line 1',
					'address_line2' => 'Address Line 2',
					'city'          => 'City',
					'state'         => 'State/Province',
					'postal_code'   => 'Postal Code',
					'country'       => 'Country',
				),
			),
			'professional'  => array(
				'label'  => 'Professional',
				'fields' => array(
					'company'                  => 'Company',
					'job_title'                => 'Job Title',
					'department'               => 'Department',
					'division'                 => 'Division',
					'employee_id'              => 'Employee ID',
					'badge_number'             => 'Badge Number',
					'manager_name'             => 'Manager Name',
					'supervisor_email'         => 'Supervisor Email',
					'office_location'          => 'Office Location',
					'hire_date'                => 'Hire Date',
					'termination_date'         => 'Termination Date',
					'work_email'               => 'Work Email',
					'employment_type'          => 'Employment Type',
					'license_number'           => 'License Number',
					'professional_memberships' => 'Professional Memberships',
					'security_clearance'       => 'Security Clearance',
					'shift'                    => 'Shift',
					'remote_status'            => 'Remote Status',
				),
			),
			'education'     => array(
				'label'  => 'Education',
				'fields' => array(
					'student_id'      => 'Student ID',
					'school_name'     => 'School/University',
					'degree'          => 'Degree',
					'major'           => 'Major/Field of Study',
					'graduation_year' => 'Graduation Year',
					'gpa'             => 'GPA',
					'certifications'  => 'Certifications',
				),
			),
			'social_media'  => array(
				'label'  => 'Social Media',
				'fields' => array(
					'twitter'          => 'Twitter/X Handle',
					'facebook'         => 'Facebook Profile',
					'linkedin'         => 'LinkedIn Profile',
					'instagram'        => 'Instagram Handle',
					'github'           => 'GitHub Username',
					'youtube'          => 'YouTube Channel',
					'tiktok'           => 'TikTok Handle',
					'discord_username' => 'Discord Username',
					'whatsapp'         => 'WhatsApp Number',
					'telegram'         => 'Telegram Handle',
					'viber'            => 'Viber Number',
					'twitch'           => 'Twitch Channel',
					'reddit'           => 'Reddit Username',
					'snapchat'         => 'Snapchat Handle',
					'soundcloud'       => 'SoundCloud Profile',
					'vimeo'            => 'Vimeo Channel',
					'spotify'          => 'Spotify Profile',
					'pinterest'        => 'Pinterest Profile',
				),
			),
			'personal'      => array(
				'label'  => 'Personal',
				'fields' => array(
					'bio'               => 'Biography',
					'website'           => 'Website',
					'nationality'       => 'Nationality',
					'languages'         => 'Languages Spoken',
					'emergency_contact' => 'Emergency Contact',
				),
			),
		);
	}

	/**
	 * Get flat array of all available field keys.
	 *
	 * @since  1.0.0
	 * @return array    All field keys.
	 */
	public static function get_all_field_keys() {
		$registry = self::get_field_registry();
		$fields   = array();

		foreach ( $registry as $category ) {
			$fields = array_merge( $fields, array_keys( $category['fields'] ) );
		}

		return $fields;
	}

	/**
	 * Get enabled profile fields based on settings.
	 *
	 * @since  1.0.0
	 * @return array    Enabled field keys.
	 */
	public static function get_enabled_fields() {
		$enabled = NBUF_Options::get( 'nbuf_enabled_profile_fields', array() );

		/* If no settings exist yet, enable basic defaults */
		if ( empty( $enabled ) ) {
			$enabled = array( 'phone', 'company', 'job_title', 'website' );
		}

		return apply_filters( 'nbuf_profile_enabled_fields', $enabled );
	}

	/**
	 * Get user profile data from custom table.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return object|null         Profile data object or null if not found.
	 */
	public static function get( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';

		$data = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		return $data;
	}

	/**
	 * Get specific field value from user profile.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $field   Field name.
	 * @return mixed               Field value or null.
	 */
	public static function get_field( $user_id, $field ) {
		$data = self::get( $user_id );
		return $data && isset( $data->$field ) ? $data->$field : null;
	}

	/**
	 * Update or insert user profile data.
	 *
	 * BuddyPress pattern: Empty values are set to NULL to reduce table bloat.
	 * If all fields become empty, the entire row is deleted.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id User ID.
	 * @param  array $fields  Associative array of field => value pairs.
	 * @return bool               True on success, false on failure.
	 */
	public static function update( $user_id, $fields ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';

		/* Get all available fields from registry */
		$allowed_fields = self::get_all_field_keys();

		$clean_data    = array();
		$has_non_empty = false;

		foreach ( $fields as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				/* Apply filter for custom sanitization */
				$sanitized = apply_filters( "nbuf_sanitize_profile_field_{$key}", $value, $key );

				/* If filter didn't handle it, use default sanitization based on field type */
				if ( $sanitized === $value ) {
					/* Text areas */
					if ( in_array( $key, array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' ), true ) ) {
						$sanitized = sanitize_textarea_field( $value );
					} elseif ( in_array( $key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
						/* URLs */
						$sanitized = esc_url_raw( $value );
					} elseif ( in_array( $key, array( 'work_email', 'supervisor_email' ), true ) ) {
						/* Emails */
						$sanitized = sanitize_email( $value );
					} elseif ( in_array( $key, array( 'date_of_birth', 'hire_date', 'termination_date' ), true ) ) {
						/* Dates - Validate date format YYYY-MM-DD */
						$sanitized = sanitize_text_field( $value );
						if ( ! empty( $sanitized ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized ) ) {
							$sanitized = null;
						}
					} else {
						/* Default: text fields */
						$sanitized = sanitize_text_field( $value );
					}
				}

				/* BuddyPress pattern: Set empty values to NULL instead of empty strings */
				if ( empty( $sanitized ) ) {
					$clean_data[ $key ] = null;
				} else {
					$clean_data[ $key ] = $sanitized;
					$has_non_empty      = true;
				}
			}
		}

		if ( empty( $clean_data ) ) {
			return false;
		}

		/* Allow extensions to modify data before save */
		do_action( 'nbuf_before_profile_update', $user_id, $fields );

		/* Check if profile exists */
		$exists = self::get( $user_id );

		/* If all fields are empty, delete the row instead of storing NULLs (BuddyPress pattern) */
		if ( ! $has_non_empty ) {
			if ( $exists ) {
				$result = self::delete( $user_id );
			} else {
				/* Nothing to do - no data to store and no row exists */
				$result = true;
			}
		} elseif ( $exists ) {
			/* Update existing profile */
			$result = $wpdb->update(
				$table_name,
				$clean_data,
				array( 'user_id' => $user_id ),
				null,
				array( '%d' )
			);
		} else {
			/* Insert new profile */
			$clean_data['user_id'] = $user_id;
			$result                = $wpdb->insert(
				$table_name,
				$clean_data
			);
		}

		/* Invalidate unified user cache */
		if ( false !== $result && class_exists( 'NBUF_User' ) ) {
			NBUF_User::invalidate_cache( $user_id, 'profile' );
		}

		/* Allow extensions to react after save */
		if ( false !== $result ) {
			do_action( 'nbuf_after_profile_update', $user_id, $fields, $clean_data );
		}

		return false !== $result;
	}

	/**
	 * Delete user profile data.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function delete( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';

		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all profile data as associative array.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return array              Profile data as array.
	 */
	public static function get_all_fields( $user_id ) {
		$data = self::get( $user_id );

		if ( ! $data ) {
			return array();
		}

		return array(
			'phone'         => $data->phone,
			'company'       => $data->company,
			'job_title'     => $data->job_title,
			'address'       => $data->address,
			'address_line1' => $data->address_line1,
			'address_line2' => $data->address_line2,
			'city'          => $data->city,
			'state'         => $data->state,
			'postal_code'   => $data->postal_code,
			'country'       => $data->country,
			'bio'           => $data->bio,
			'website'       => $data->website,
		);
	}

	/**
	 * Check if user has any profile data.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if profile exists, false otherwise.
	 */
	public static function exists( $user_id ) {
		$data = self::get( $user_id );
		return null !== $data;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
