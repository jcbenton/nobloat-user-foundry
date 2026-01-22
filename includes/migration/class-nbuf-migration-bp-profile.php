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
	 * @param  array<string, mixed> $options Migration options (backup_unmapped, field_mapping_override, batch_size, batch_offset).
	 * @return array<string, mixed> Migration results with counts and errors.
	 */
	public static function migrate_profile_data( array $options = array() ): array {
		global $wpdb;

		/* Parse batch options */
		$batch_size   = isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 0;
		$batch_offset = isset( $options['batch_offset'] ) ? absint( $options['batch_offset'] ) : 0;
		$copy_photos  = isset( $options['copy_photos'] ) ? (bool) $options['copy_photos'] : true;

		$results = array(
			'total'           => 0,
			'imported'        => 0,
			'migrated'        => 0,
			'skipped'         => 0,
			'photos_migrated' => 0,
			'covers_migrated' => 0,
			'fields_mapped'   => 0,
			'fields_unmapped' => array(),
			'errors'          => array(),
			'batch_complete'  => false,
		);

		/* Check if BuddyPress is active */
		if ( ! function_exists( 'buddypress' ) ) {
			$results['errors'][]       = __( 'BuddyPress plugin is not active.', 'nobloat-user-foundry' );
			$results['batch_complete'] = true;
			return $results;
		}

		/* Get BuddyPress table name */
		$bp = buddypress();
		if ( empty( $bp->profile->table_name_data ) || empty( $bp->profile->table_name_fields ) ) {
			$results['errors'][]       = __( 'BuddyPress XProfile tables not found.', 'nobloat-user-foundry' );
			$results['batch_complete'] = true;
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
		 * Get unique users who have xprofile data.
		 * If batch_size is set, use LIMIT/OFFSET for pagination.
		 */
		if ( $batch_size > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation reading from BuddyPress tables.
			$users = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT user_id
					FROM %i
					WHERE user_id > 0
					ORDER BY user_id ASC
					LIMIT %d OFFSET %d',
					$bp_data_table,
					$batch_size,
					$batch_offset
				)
			);

			/* Check if this is the last batch */
			$results['batch_complete'] = ( count( $users ) < $batch_size );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation reading from BuddyPress tables.
			$users                     = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT user_id
					FROM %i
					WHERE user_id > 0
					ORDER BY user_id ASC',
					$bp_data_table
				)
			);
			$results['batch_complete'] = true;
		}

		$results['total'] = count( $users );

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

					/* Migrate BuddyPress avatar and cover photo if enabled */
					if ( $copy_photos ) {
						$photo_migrated = self::migrate_user_avatar( $user_id );
						if ( $photo_migrated ) {
							++$results['photos_migrated'];
						}

						$cover_migrated = self::migrate_user_cover( $user_id );
						if ( $cover_migrated ) {
							++$results['covers_migrated'];
						}
					}

					++$results['migrated'];
					++$results['imported'];
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

		/*
		 * Log migration to admin audit only when batch is complete.
		 * This prevents duplicate logging for each batch.
		 * Note: History logging is handled by NBUF_Migration::ajax_execute_migration_batch()
		 */
		if ( $results['batch_complete'] && $results['migrated'] > 0 && class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				get_current_user_id(),
				'migration_profiles',
				'success',
				sprintf(
					/* translators: %d: Number of users migrated */
					__( 'Migrated %d user profiles from BuddyPress XProfile (batch)', 'nobloat-user-foundry' ),
					$results['migrated']
				),
				null,
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

	/**
	 * Migrate BuddyPress avatar for a single user
	 *
	 * Copies the user's BP avatar to NBUF's photo directory with WebP conversion.
	 * BuddyPress stores avatars in: wp-content/uploads/avatars/{user_id}/
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if avatar was migrated, false otherwise.
	 */
	private static function migrate_user_avatar( int $user_id ): bool {
		/* Check if BuddyPress avatar functions exist */
		if ( ! function_exists( 'bp_core_fetch_avatar' ) || ! function_exists( 'bp_core_avatar_upload_path' ) ) {
			return false;
		}

		/* Check if NBUF_Image_Processor exists */
		if ( ! class_exists( 'NBUF_Image_Processor' ) ) {
			return false;
		}

		/* Get the BP avatar upload path */
		$bp_avatar_base = bp_core_avatar_upload_path();
		if ( empty( $bp_avatar_base ) ) {
			return false;
		}

		/* BuddyPress stores user avatars in: {upload_path}/avatars/{user_id}/ */
		$user_avatar_dir = trailingslashit( $bp_avatar_base ) . 'avatars/' . $user_id . '/';

		/* Validate directory exists */
		if ( ! is_dir( $user_avatar_dir ) ) {
			return false;
		}

		/*
		 * Security: Validate path using realpath() to prevent traversal attacks.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$user_avatar_dir_real = realpath( $user_avatar_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$bp_avatar_base_real = realpath( $bp_avatar_base );

		if ( ! $user_avatar_dir_real || ! $bp_avatar_base_real ) {
			return false;
		}

		if ( 0 !== strpos( $user_avatar_dir_real, $bp_avatar_base_real ) ) {
			/* Path traversal attempt detected */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'path_traversal_attempt',
					'critical',
					__( 'Path traversal attempt detected during BP avatar migration', 'nobloat-user-foundry' ),
					array(
						'user_avatar_dir' => $user_avatar_dir,
						'context'         => 'bp_avatar_migration',
					),
					$user_id
				);
			}
			return false;
		}

		/*
		 * Find the full-size avatar file.
		 * BP naming convention: {user_id}-bpfull.{ext} or sometimes just numbered files.
		 * Look for common patterns.
		 */
		$avatar_file = null;
		$extensions  = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );

		// Try BP's standard naming first: {timestamp}-bpfull.{ext}.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Directory may not be readable.
		$files = @scandir( $user_avatar_dir_real );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				/* Skip . and .. */
				if ( '.' === $file || '..' === $file ) {
					continue;
				}

				/* Look for full-size avatar (bpfull) first */
				if ( false !== strpos( $file, '-bpfull.' ) ) {
					$avatar_file = $file;
					break;
				}
			}

			/* If no bpfull found, look for any image file (fallback) */
			if ( ! $avatar_file ) {
				foreach ( $files as $file ) {
					if ( '.' === $file || '..' === $file ) {
						continue;
					}

					$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
					if ( in_array( $ext, $extensions, true ) ) {
						/* Skip thumbnail versions */
						if ( false !== strpos( $file, '-bpthumb.' ) ) {
							continue;
						}
						$avatar_file = $file;
						break;
					}
				}
			}
		}

		if ( ! $avatar_file ) {
			return false;
		}

		/* Build full path and validate */
		$source_path = $user_avatar_dir_real . '/' . basename( $avatar_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$source_path_real = realpath( $source_path );

		if ( ! $source_path_real || ! file_exists( $source_path_real ) ) {
			return false;
		}

		/* Verify file is within user avatar directory */
		if ( 0 !== strpos( $source_path_real, $user_avatar_dir_real ) ) {
			return false;
		}

		/* Process and copy the image using NBUF_Image_Processor */
		$processed = NBUF_Image_Processor::process_image(
			$source_path_real,
			$user_id,
			NBUF_Image_Processor::TYPE_PROFILE
		);

		if ( is_wp_error( $processed ) ) {
			return false;
		}

		/* Update user data with new photo path/URL if NBUF_User_Data exists */
		if ( class_exists( 'NBUF_User_Data' ) && ! empty( $processed['path'] ) ) {
			NBUF_User_Data::update(
				$user_id,
				array(
					'profile_photo_path' => $processed['path'],
					'profile_photo_url'  => $processed['url'] ?? '',
				)
			);
		}

		return true;
	}

	/**
	 * Migrate BuddyPress cover photo for a single user
	 *
	 * Copies the user's BP cover photo to NBUF's photo directory with WebP conversion.
	 * BuddyPress stores cover photos in: wp-content/uploads/buddypress/members/{user_id}/cover-image/
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if cover was migrated, false otherwise.
	 */
	private static function migrate_user_cover( int $user_id ): bool {
		/* Check if NBUF_Image_Processor exists */
		if ( ! class_exists( 'NBUF_Image_Processor' ) ) {
			return false;
		}

		/* Get WordPress upload directory */
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return false;
		}

		/*
		 * BuddyPress stores cover photos in: {uploads}/buddypress/members/{user_id}/cover-image/
		 * This is the standard location since BP 2.4.0
		 */
		$bp_cover_dir = trailingslashit( $upload_dir['basedir'] ) . 'buddypress/members/' . $user_id . '/cover-image/';

		/* Check if directory exists */
		if ( ! is_dir( $bp_cover_dir ) ) {
			return false;
		}

		/*
		 * Security: Validate path using realpath() to prevent traversal attacks.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$bp_cover_dir_real = realpath( $bp_cover_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$upload_base_real = realpath( $upload_dir['basedir'] );

		if ( ! $bp_cover_dir_real || ! $upload_base_real ) {
			return false;
		}

		if ( 0 !== strpos( $bp_cover_dir_real, $upload_base_real ) ) {
			/* Path traversal attempt detected */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'path_traversal_attempt',
					'critical',
					__( 'Path traversal attempt detected during BP cover migration', 'nobloat-user-foundry' ),
					array(
						'bp_cover_dir' => $bp_cover_dir,
						'context'      => 'bp_cover_migration',
					),
					$user_id
				);
			}
			return false;
		}

		/*
		 * Find the cover image file.
		 * BP typically stores the cover as a timestamped filename.
		 */
		$cover_file = null;
		$extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Directory may not be readable.
		$files = @scandir( $bp_cover_dir_real );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				/* Skip . and .. */
				if ( '.' === $file || '..' === $file ) {
					continue;
				}

				$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $extensions, true ) ) {
					$cover_file = $file;
					break;
				}
			}
		}

		if ( ! $cover_file ) {
			return false;
		}

		/* Build full path and validate */
		$source_path = $bp_cover_dir_real . '/' . basename( $cover_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$source_path_real = realpath( $source_path );

		if ( ! $source_path_real || ! file_exists( $source_path_real ) ) {
			return false;
		}

		/* Verify file is within cover directory */
		if ( 0 !== strpos( $source_path_real, $bp_cover_dir_real ) ) {
			return false;
		}

		/* Process and copy the image using NBUF_Image_Processor */
		$processed = NBUF_Image_Processor::process_image(
			$source_path_real,
			$user_id,
			NBUF_Image_Processor::TYPE_COVER
		);

		if ( is_wp_error( $processed ) ) {
			return false;
		}

		/* Update user data with new cover path/URL if NBUF_User_Data exists */
		if ( class_exists( 'NBUF_User_Data' ) && ! empty( $processed['path'] ) ) {
			NBUF_User_Data::update(
				$user_id,
				array(
					'cover_photo_path' => $processed['path'],
					'cover_photo_url'  => $processed['url'] ?? '',
				)
			);
		}

		return true;
	}
}
