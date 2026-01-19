<?php
/**
 * NoBloat User Foundry - BuddyPress Profile Migration
 *
 * Migrates user profile data from BuddyPress XProfile to NoBloat User Foundry.
 * Handles field mapping from BP's flexible EAV model to NBUF's fixed column structure.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Migration_BP_Profile
 *
 * Migrates user profile data from BuddyPress XProfile to NoBloat User Foundry.
 */
class NBUF_Migration_BP_Profile {


	/**
	 * Migrate BuddyPress XProfile data to NBUF
	 *
	 * @param  array<string, mixed> $options Migration options (backup_unmapped, field_mapping_override).
	 * @return array<string, mixed> Migration results with counts and errors.
	 */
	public static function migrate_profile_data( array $options = array() ): array {
		global $wpdb;

		$results = array(
			'total_users'     => 0,
			'migrated'        => 0,
			'skipped'         => 0,
			'fields_mapped'   => 0,
			'fields_unmapped' => array(),
			'errors'          => array(),
		);

		/* Check if BuddyPress is active */
		if ( ! function_exists( 'buddypress' ) ) {
			$results['errors'][] = __( 'BuddyPress plugin is not active.', 'nobloat-user-foundry' );
			return $results;
		}

		/* Get BuddyPress table name */
		$bp = buddypress();
		if ( empty( $bp->profile->table_name_data ) || empty( $bp->profile->table_name_fields ) ) {
			$results['errors'][] = __( 'BuddyPress XProfile tables not found.', 'nobloat-user-foundry' );
			return $results;
		}

		$bp_data_table   = $bp->profile->table_name_data;
		$bp_fields_table = $bp->profile->table_name_fields;

		/*
		Get all BP xprofile fields (exclude "Name" field which is ID 1 typically)
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation reading from BuddyPress tables.
		$bp_fields = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, type, parent_id
				FROM %i
				WHERE parent_id = 0
				AND id != 1
				ORDER BY id ASC',
				$bp_fields_table
			)
		);

		/* Build field mapping */
		$field_mapping = self::build_field_mapping( $bp_fields );

		/* Allow override via options */
		if ( ! empty( $options['field_mapping_override'] ) && is_array( $options['field_mapping_override'] ) ) {
			$field_mapping = array_merge( $field_mapping, $options['field_mapping_override'] );
		}

		/*
		Get unique users who have xprofile data
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation reading from BuddyPress tables.
		$users = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT user_id
				FROM %i
				WHERE user_id > 0
				ORDER BY user_id ASC',
				$bp_data_table
			)
		);

		$results['total_users'] = count( $users );

		/* Migrate each user */
		foreach ( $users as $user_id ) {
			try {
				/*
				Get all BP xprofile data for this user
				*/
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation reading from BuddyPress tables.
				$bp_user_data = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT field_id, value
						FROM %i
						WHERE user_id = %d
						AND value != ''",
						$bp_data_table,
						$user_id
					)
				);

				if ( empty( $bp_user_data ) ) {
						++$results['skipped'];
						continue;
				}

				/* Build NBUF profile data */
				$nbuf_data = array();

				foreach ( $bp_user_data as $row ) {
					/* Check if this field is mapped */
					if ( isset( $field_mapping[ $row->field_id ] ) ) {
						$nbuf_field = $field_mapping[ $row->field_id ];

						/* Convert value based on field type */
						$value = self::convert_field_value( $row->value, $nbuf_field );

						$nbuf_data[ $nbuf_field ] = $value;
						++$results['fields_mapped'];
					} elseif ( ! in_array( $row->field_id, $results['fields_unmapped'], true ) ) {
						/* Track unmapped fields */
						$results['fields_unmapped'][] = $row->field_id;
					}
				}

				/* Insert/update NBUF profile table */
				if ( ! empty( $nbuf_data ) ) {
					$table                = $wpdb->prefix . 'nbuf_user_profile';
					$nbuf_data['user_id'] = $user_id;

					/*
					* Check if row exists.
					*/
                 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation.
					$exists = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT user_id FROM %i WHERE user_id = %d',
							$table,
							$user_id
						)
					);

					if ( $exists ) {
							/* Update existing */
							$nbuf_data['updated_at'] = current_time( 'mysql', true );
                           // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation.
						$wpdb->update(
							$table,
							$nbuf_data,
							array( 'user_id' => $user_id ),
							array_fill( 0, count( $nbuf_data ), '%s' ),
							array( '%d' )
						);
					} else {
						/* Insert new */
						$nbuf_data['created_at'] = current_time( 'mysql', true );
						$nbuf_data['updated_at'] = current_time( 'mysql', true );
                  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation.
						$wpdb->insert(
							$table,
							$nbuf_data,
							array_fill( 0, count( $nbuf_data ), '%s' )
						);
					}

					/* Clear cache */
					$cache_key = "nbuf_profile_{$user_id}";
					wp_cache_delete( $cache_key, 'nbuf_profile_data' );

					++$results['migrated'];
				} else {
					++$results['skipped'];
				}
			} catch ( Exception $e ) {
				$results['errors'][] = sprintf(
				/* translators: 1: User ID, 2: Error message */
					__( 'User ID %1$d: %2$s', 'nobloat-user-foundry' ),
					$user_id,
					$e->getMessage()
				);
			}
		}

		/* Log migration to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'migration',
				'bp_profile_migrated',
				sprintf(
				/* translators: %d: Number of users migrated */
					__( 'Migrated %d user profiles from BuddyPress XProfile', 'nobloat-user-foundry' ),
					$results['migrated']
				),
				$results
			);
		}

		return $results;
	}

	/**
	 * Build field mapping from BP field names to NBUF field keys
	 *
	 * @param  array<int, object> $bp_fields BuddyPress field objects.
	 * @return array<int, string> Field mapping (bp_field_id => nbuf_field_key).
	 */
	private static function build_field_mapping( array $bp_fields ): array {
		$mapping = array();

		/* Get NBUF field registry */
		$nbuf_fields = NBUF_Profile_Data::get_field_registry();
		$nbuf_flat   = array();

		foreach ( $nbuf_fields as $category ) {
			foreach ( $category['fields'] as $key => $label ) {
				$nbuf_flat[ $key ] = strtolower( $label );
			}
		}

		/* Normalize BP field names and match to NBUF fields */
		foreach ( $bp_fields as $bp_field ) {
			$bp_name_lower      = strtolower( trim( $bp_field->name ) );
			$bp_name_normalized = self::normalize_field_name( $bp_name_lower );

			/* Try exact match first */
			foreach ( $nbuf_flat as $nbuf_key => $nbuf_label ) {
				$nbuf_label_normalized = self::normalize_field_name( $nbuf_label );

				if ( $bp_name_normalized === $nbuf_label_normalized ) {
					$mapping[ $bp_field->id ] = $nbuf_key;
					continue 2;
				}
			}

			/* Try common aliases */
			$aliases = self::get_field_aliases();
			foreach ( $aliases as $alias => $nbuf_key ) {
				$alias_normalized = self::normalize_field_name( $alias );
				if ( $bp_name_normalized === $alias_normalized ) {
					if ( array_key_exists( $nbuf_key, $nbuf_flat ) ) {
						$mapping[ $bp_field->id ] = $nbuf_key;
						continue 2;
					}
				}
			}

			/* Try substring matching for complex names */
			foreach ( $nbuf_flat as $nbuf_key => $nbuf_label ) {
				$nbuf_label_normalized = self::normalize_field_name( $nbuf_label );

				/* Check if BP name contains NBUF label */
				if ( false !== strpos( $bp_name_normalized, $nbuf_label_normalized ) ) {
					$mapping[ $bp_field->id ] = $nbuf_key;
					continue 2;
				}

				/* Check if NBUF label contains BP name */
				if ( false !== strpos( $nbuf_label_normalized, $bp_name_normalized ) ) {
					$mapping[ $bp_field->id ] = $nbuf_key;
					continue 2;
				}
			}
		}

		/**
		 * Filter the field mapping.
		 *
		 * @param array $mapping   Field mapping (bp_field_id => nbuf_field_key)
		 * @param array $bp_fields BuddyPress field objects
		 */
		return apply_filters( 'nbuf_bp_profile_field_mapping', $mapping, $bp_fields );
	}

	/**
	 * Normalize field name for matching
	 *
	 * @param  string $name Field name.
	 * @return string Normalized name.
	 */
	private static function normalize_field_name( string $name ): string {
		/* Remove special characters and extra spaces */
		$normalized = preg_replace( '/[^a-z0-9\s]/', '', strtolower( $name ) );
		$normalized = preg_replace( '/\s+/', '', $normalized );

		return $normalized;
	}

	/**
	 * Get common field name aliases
	 *
	 * @return array<string, string> Aliases (alias => nbuf_field_key)
	 */
	private static function get_field_aliases(): array {
		return array(
			'tel'              => 'phone',
			'telephone'        => 'phone',
			'phonenumber'      => 'phone',
			'mobile'           => 'mobile_phone',
			'cell'             => 'mobile_phone',
			'cellphone'        => 'mobile_phone',
			'organization'     => 'company',
			'employer'         => 'company',
			'companyname'      => 'company',
			'position'         => 'job_title',
			'title'            => 'job_title',
			'role'             => 'job_title',
			'street'           => 'address_line1',
			'streetaddress'    => 'address_line1',
			'zipcode'          => 'postal_code',
			'zip'              => 'postal_code',
			'postcode'         => 'postal_code',
			'province'         => 'state',
			'region'           => 'state',
			'dob'              => 'date_of_birth',
			'birthdate'        => 'date_of_birth',
			'birthday'         => 'date_of_birth',
			'webpage'          => 'website',
			'site'             => 'website',
			'url'              => 'website',
			'homepage'         => 'website',
			'secondarywebsite' => 'website',
			'about'            => 'bio',
			'biography'        => 'bio',
			'aboutme'          => 'bio',
			'description'      => 'bio',
			'intro'            => 'bio',
			'summary'          => 'bio',
			'nick'             => 'nickname',
			'alias'            => 'nickname',
			'handle'           => 'nickname',
			'screenname'       => 'nickname',
			'twitter'          => 'twitter',
			'twitterhandle'    => 'twitter',
			'linkedin'         => 'linkedin',
			'linkedinurl'      => 'linkedin',
			'facebook'         => 'facebook',
			'facebookurl'      => 'facebook',
			'instagram'        => 'instagram',
			'instagramhandle'  => 'instagram',
			'github'           => 'github',
			'githuburl'        => 'github',
			'youtube'          => 'youtube',
			'youtubeurl'       => 'youtube',
			'university'       => 'school_name',
			'college'          => 'school_name',
			'education'        => 'school_name',
			'timezone'         => 'timezone',
			'tz'               => 'timezone',
			'emergencycontact' => 'emergency_contact',
			'emergency'        => 'emergency_contact',
			'languages'        => 'languages',
			'languagesspoken'  => 'languages',
			'nationality'      => 'nationality',
			'citizenship'      => 'nationality',
			'country'          => 'country',
			'nation'           => 'country',
		);
	}

	/**
	 * Convert field value based on NBUF field type
	 *
	 * @param  mixed  $value      BP field value.
	 * @param  string $nbuf_field NBUF field key.
	 * @return string Converted value.
	 */
	private static function convert_field_value( $value, string $nbuf_field ): string {
		/* Handle serialized arrays from BP (selectbox, checkbox, etc.) */
		if ( is_serialized( $value ) ) {
			$unserialized = maybe_unserialize( $value );

			if ( is_array( $unserialized ) ) {
				/* Join array values with commas */
				$value = implode( ', ', $unserialized );
			}
		}

		/* Date field conversions */
		$date_fields = array( 'date_of_birth', 'hire_date', 'termination_date' );
		if ( in_array( $nbuf_field, $date_fields, true ) ) {
			/* Try to parse and format as Y-m-d */
			$timestamp = strtotime( $value );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d', $timestamp );
			}
		}

		/* Sanitize based on field type */
		if ( 'bio' === $nbuf_field || 'professional_memberships' === $nbuf_field || 'certifications' === $nbuf_field || 'emergency_contact' === $nbuf_field ) {
			/* Allow HTML for text fields */
			return wp_kses_post( $value );
		}

		/* Email fields */
		$email_fields = array( 'secondary_email', 'work_email', 'supervisor_email' );
		if ( in_array( $nbuf_field, $email_fields, true ) ) {
			return sanitize_email( $value );
		}

		/* URL fields */
		$url_fields = array( 'website', 'facebook', 'linkedin', 'twitter', 'instagram', 'github', 'youtube', 'tiktok', 'twitch', 'reddit', 'soundcloud', 'vimeo', 'spotify', 'pinterest' );
		if ( in_array( $nbuf_field, $url_fields, true ) ) {
			return esc_url_raw( $value );
		}

		/* Default: sanitize as text */
		return sanitize_text_field( $value );
	}

	/**
	 * Get migration preview (first N users)
	 *
	 * @param  int $limit Number of users to preview.
	 * @return array<int, array<string, mixed>> Preview data.
	 */
	public static function get_migration_preview( int $limit = 10 ): array {
		global $wpdb;

		if ( ! function_exists( 'buddypress' ) ) {
			return array();
		}

		$bp = buddypress();
		if ( empty( $bp->profile->table_name_data ) || empty( $bp->profile->table_name_fields ) ) {
			return array();
		}

		$bp_data_table   = $bp->profile->table_name_data;
		$bp_fields_table = $bp->profile->table_name_fields;

		/*
		* Get BP fields.
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration preview reading from BuddyPress tables.
		$bp_fields = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, type
				FROM %i
				WHERE parent_id = 0
				AND id != 1
				ORDER BY id ASC',
				$bp_fields_table
			)
		);

		$field_mapping = self::build_field_mapping( $bp_fields );

		/*
		* Get sample users.
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration preview reading from BuddyPress tables.
		$users = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT d.user_id, u.user_login, u.user_email
				FROM %i d
				INNER JOIN %i u ON d.user_id = u.ID
				WHERE d.user_id > 0
				ORDER BY d.user_id ASC
				LIMIT %d',
				$bp_data_table,
				$wpdb->users,
				$limit
			)
		);

		$preview = array();

		foreach ( $users as $user ) {
			/*
			* Get BP data for this user.
			*/
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration preview reading from BuddyPress tables.
			$bp_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.field_id, d.value, f.name
					FROM %i d
					INNER JOIN %i f ON d.field_id = f.id
					WHERE d.user_id = %d
					AND d.value != ''
					AND f.id != 1",
					$bp_data_table,
					$bp_fields_table,
					$user->user_id
				)
			);

			$mapped_fields   = array();
			$unmapped_fields = array();

			foreach ( $bp_data as $row ) {
					$field_info = array(
						'bp_name'  => $row->name,
						'bp_value' => $row->value,
					);

					if ( isset( $field_mapping[ $row->field_id ] ) ) {
						$nbuf_field               = $field_mapping[ $row->field_id ];
						$field_info['nbuf_field'] = $nbuf_field;
						$field_info['nbuf_value'] = self::convert_field_value( $row->value, $nbuf_field );
						$mapped_fields[]          = $field_info;
					} else {
						$unmapped_fields[] = $field_info;
					}
			}

			$preview[] = array(
				'user_id'         => $user->user_id,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'mapped_fields'   => $mapped_fields,
				'unmapped_fields' => $unmapped_fields,
			);
		}

		return $preview;
	}

	/**
	 * Get count of users with BP xprofile data
	 *
	 * @return int Number of users
	 */
	public static function get_user_count(): int {
		global $wpdb;

		if ( ! function_exists( 'buddypress' ) ) {
			return 0;
		}

		$bp = buddypress();
		if ( empty( $bp->profile->table_name_data ) ) {
			return 0;
		}

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query reading from BuddyPress tables.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id)
				FROM %i
				WHERE user_id > 0',
				$bp->profile->table_name_data
			)
		);

		return absint( $count );
	}

	/**
	 * Get BuddyPress field statistics
	 *
	 * @return array<string, mixed> Field statistics
	 */
	public static function get_field_statistics(): array {
		global $wpdb;

		if ( ! function_exists( 'buddypress' ) ) {
			return array();
		}

		$bp = buddypress();
		if ( empty( $bp->profile->table_name_fields ) || empty( $bp->profile->table_name_data ) ) {
			return array();
		}

		$bp_fields_table = $bp->profile->table_name_fields;
		$bp_data_table   = $bp->profile->table_name_data;

		/*
		* Get all fields.
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query reading from BuddyPress tables.
		$bp_fields = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, type
				FROM %i
				WHERE parent_id = 0
				AND id != 1
				ORDER BY id ASC',
				$bp_fields_table
			)
		);

		$field_mapping = self::build_field_mapping( $bp_fields );
		$nbuf_fields   = NBUF_Profile_Data::get_field_registry();

		$stats = array(
			'total_bp_fields'   => count( $bp_fields ),
			'mapped_fields'     => count( $field_mapping ),
			'unmapped_fields'   => count( $bp_fields ) - count( $field_mapping ),
			'total_nbuf_fields' => count( NBUF_Profile_Data::get_all_field_keys() ),
			'field_details'     => array(),
		);

		/* Get usage count for each field */
		foreach ( $bp_fields as $field ) {
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query reading from BuddyPress tables.
			$usage_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id)
					FROM %i
					WHERE field_id = %d
					AND value != ''",
					$bp_data_table,
					$field->id
				)
			);

			$is_mapped  = isset( $field_mapping[ $field->id ] );
			$nbuf_field = $is_mapped ? $field_mapping[ $field->id ] : null;

			$stats['field_details'][] = array(
				'bp_id'      => $field->id,
				'bp_name'    => $field->name,
				'bp_type'    => $field->type,
				'usage'      => absint( $usage_count ),
				'is_mapped'  => $is_mapped,
				'nbuf_field' => $nbuf_field,
			);
		}

		return $stats;
	}
}
