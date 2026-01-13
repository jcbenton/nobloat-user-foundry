<?php
/**
 * NoBloat User Foundry - Ultimate Member Migration Handler
 *
 * Handles migration of user data from Ultimate Member plugin.
 * Includes field discovery, mapping, and data import.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Member migration handler
 *
 * Extends abstract migration class with UM-specific logic.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 */
class NBUF_Migration_Ultimate_Member extends NBUF_Abstract_Migration_Plugin {


	/**
	 * Get plugin display name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Ultimate Member';
	}

	/**
	 * Get plugin slug
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'ultimate-member';
	}

	/**
	 * Get plugin file path
	 *
	 * @return string
	 */
	public function get_plugin_file() {
		return 'ultimate-member/ultimate-member.php';
	}

	/**
	 * Get count of users with UM data
	 *
	 * @return int
	 */
	public function get_user_count() {
		global $wpdb;

		/*
		Count users with UM meta data
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id)
			FROM {$wpdb->usermeta}
			WHERE meta_key LIKE 'um_%' OR meta_key LIKE '_um_%' OR meta_key = 'account_status'"
		);

		return absint( $count );
	}

	/**
	 * Get default field mapping
	 *
	 * Returns automatic field mappings from UM → NoBloat.
	 *
	 * @return array
	 */
	public function get_default_field_mapping() {
		return array(
			/* Core UM fields */
			'account_status'       => array(
				'target'    => 'nbuf_user_data.is_verified',
				'transform' => 'um_account_status_to_verified',
			),
			'_um_last_login'       => array(
				'target'    => 'nbuf_user_data.last_login_at',
				'transform' => 'um_timestamp_to_datetime',
			),

			/* Contact fields */
			'phone_number'         => array(
				'target'    => 'phone',
				'transform' => 'sanitize_text',
			),
			'mobile_number'        => array(
				'target'    => 'phone',
				'transform' => 'sanitize_text',
				'priority'  => 10, /* Use only if phone_number is empty */
			),
			'secondary_user_email' => array(
				'target'    => 'secondary_email',
				'transform' => 'sanitize_email',
			),

			/* Personal fields */
			'description'          => array(
				'target'    => 'bio',
				'transform' => 'sanitize_textarea',
			),
			'bio'                  => array(
				'target'    => 'bio',
				'transform' => 'sanitize_textarea',
			),
			'about_me'             => array(
				'target'    => 'bio',
				'transform' => 'sanitize_textarea',
			),
			'nickname'             => 'nickname',
			'birth_date'           => 'date_of_birth',
			'gender'               => 'gender',

			/* Address fields */
			'country'              => 'country',
			'address'              => 'address_line1',
			'city'                 => 'city',
			'state'                => 'state',
			'postal_code'          => 'postal_code',
			'zipcode'              => 'postal_code', /* Alternate name */

		/* Professional fields */
			'company'              => 'company',
			'job_title'            => 'job_title',

			/* Website */
			'user_url'             => 'website',
			'website'              => 'website',
			'secondary_website'    => 'website',
			'secondary_url'        => 'website',

			/* Social Media - UM predefined fields */
			'facebook'             => 'facebook',
			'twitter'              => 'twitter',
			'linkedin'             => 'linkedin',
			'instagram'            => 'instagram',
			'github'               => 'github',
			'youtube'              => 'youtube',
			'tiktok'               => 'tiktok',
			'discord'              => 'discord_username',
			'whatsapp'             => 'whatsapp',
			'telegram'             => 'telegram',
			'viber'                => 'viber',
			'twitch'               => 'twitch',
			'reddit'               => 'reddit',
			'snapchat'             => 'snapchat',
			'soundcloud'           => 'soundcloud',
			'vimeo'                => 'vimeo',
			'spotify'              => 'spotify',
			'pinterest'            => 'pinterest',
		);
	}

	/**
	 * Discover custom fields from UM
	 *
	 * Scans UM forms and usermeta for custom fields not in default mapping.
	 *
	 * @return array Custom fields with sample data
	 */
	public function discover_custom_fields() {
		global $wpdb;

		$custom_fields   = array();
		$default_mapping = $this->get_default_field_mapping();

		/*
		Get all UM form IDs
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$form_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'um_form' AND post_status = 'publish'"
		);

		/* Scan each form for custom fields */
		foreach ( $form_ids as $form_id ) {
			$form_fields = get_post_meta( $form_id, '_um_custom_fields', true );

			if ( ! empty( $form_fields ) && is_array( $form_fields ) ) {
				foreach ( $form_fields as $field_id => $field_data ) {
					/* Skip if already in default mapping */
					if ( isset( $default_mapping[ $field_id ] ) ) {
						continue;
					}

					/*
					Get sample values from usermeta
					*/
                 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$samples = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT meta_value
							FROM {$wpdb->usermeta}
							WHERE meta_key = %s
							AND meta_value IS NOT NULL
							AND meta_value != ''
							LIMIT 3",
							$field_id
						)
					);

					if ( ! empty( $samples ) ) {
								$custom_fields[ $field_id ] = array(
									'field_key'   => $field_id,
									'field_type'  => isset( $field_data['type'] ) ? $field_data['type'] : 'text',
									'field_label' => isset( $field_data['label'] ) ? $field_data['label'] : $field_id,
									'samples'     => $samples,
								);
					}
				}
			}
		}

		/*
		Also scan usermeta for any UM-prefixed keys not in forms
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$um_meta_keys = $wpdb->get_col(
			"SELECT DISTINCT meta_key
			FROM {$wpdb->usermeta}
			WHERE (meta_key LIKE 'um_%' OR meta_key LIKE '_um_%')
			AND meta_key NOT IN ('um_member_directory_data', '_um_last_login', 'um_account_status')
			LIMIT 50"
		);

		foreach ( $um_meta_keys as $meta_key ) {
			/* Skip if already mapped or discovered */
			if ( isset( $default_mapping[ $meta_key ] ) || isset( $custom_fields[ $meta_key ] ) ) {
				continue;
			}

			/*
			Get sample values
			*/
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$samples = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value
					FROM {$wpdb->usermeta}
					WHERE meta_key = %s
					AND meta_value IS NOT NULL
					AND meta_value != ''
					LIMIT 3",
					$meta_key
				)
			);

			if ( ! empty( $samples ) ) {
					$custom_fields[ $meta_key ] = array(
						'field_key'   => $meta_key,
						'field_type'  => 'text',
						'field_label' => ucwords( str_replace( array( 'um_', '_um_', '_' ), array( '', '', ' ' ), $meta_key ) ),
						'samples'     => $samples,
					);
			}
		}

		return $custom_fields;
	}

	/**
	 * Preview import data
	 *
	 * @param  int   $limit         Number of users to preview.
	 * @param  array $field_mapping Custom field mapping.
	 * @return array
	 */
	public function preview_import( $limit = 10, $field_mapping = array() ) {
		global $wpdb;

		$preview = array();

		/*
		Get users with UM data
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE um.meta_key LIKE %s OR um.meta_key LIKE %s OR um.meta_key = 'account_status'
				LIMIT %d",
				$wpdb->esc_like( 'um_' ) . '%',
				$wpdb->esc_like( '_um_' ) . '%',
				$limit
			)
		);

		foreach ( $users as $user ) {
			$preview[] = array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'data'         => $this->get_user_preview_data( $user->ID, $field_mapping ),
			);
		}

		return $preview;
	}

	/**
	 * Get preview data for single user
	 *
	 * @param  int   $user_id       User ID.
	 * @param  array $field_mapping Custom field mapping.
	 * @return array
	 */
	private function get_user_preview_data( $user_id, $field_mapping = array() ) {
		$data = array();

		/* Get account status and show mapping preview */
		$account_status         = get_user_meta( $user_id, 'account_status', true );
		$data['account_status'] = $account_status ? $account_status : 'unknown';

		/* Show what the status will map to */
		switch ( $account_status ) {
			case 'approved':
				$data['will_map_to'] = 'Verified + Approved';
				break;
			case 'awaiting_email_confirmation':
				$data['will_map_to'] = 'Unverified';
				break;
			case 'awaiting_admin_review':
				$data['will_map_to'] = 'Verified, Awaiting Approval';
				break;
			case 'inactive':
			case 'rejected':
				$data['will_map_to'] = 'Disabled (Rejected)';
				break;
			default:
				$data['will_map_to'] = 'Unverified';
				break;
		}

		/* Get profile fields with sample values */
		$default_mapping = $this->get_default_field_mapping();
		$all_mappings    = array_merge( $default_mapping, $field_mapping );

		$field_count = 0;
		foreach ( $all_mappings as $um_field => $target ) {
			if ( $field_count >= 5 ) {
				break; /* Limit preview data */
			}

			$value = get_user_meta( $user_id, $um_field, true );
			if ( ! empty( $value ) ) {
				$data[ $um_field ] = is_string( $value ) ? substr( $value, 0, 50 ) : $value;
				++$field_count;
			}
		}

		return $data;
	}

	/**
	 * Import single user data
	 *
	 * @param  int   $user_id       User ID.
	 * @param  array $options       Import options.
	 * @param  array $field_mapping Custom field mapping.
	 * @return bool
	 */
	public function import_user( $user_id, $options = array(), $field_mapping = array() ) {
		global $wpdb;

		/* Merge default and custom mappings */
		$default_mapping = $this->get_default_field_mapping();
		$all_mappings    = array_merge( $default_mapping, $field_mapping );

		/*
		 * Get account status and import to user_data
		 * Map UM statuses to NoBloat's account status system
		 */
		$account_status = get_user_meta( $user_id, 'account_status', true );

		/* Initialize user_data fields based on UM account status */
		$user_data = array(
			'user_id'           => $user_id,
			'is_verified'       => 0,
			'verified_date'     => null,
			'requires_approval' => 0,
			'is_approved'       => 1,
			'approved_by'       => null,
			'approved_date'     => null,
			'approval_notes'    => null,
			'is_disabled'       => 0,
			'disabled_reason'   => null,
		);

		/* Map UM status to NoBloat status */
		switch ( $account_status ) {
			case 'approved':
				/* User is fully approved and verified */
				$user_data['is_verified']   = 1;
				$user_data['verified_date'] = current_time( 'mysql', true );
				$user_data['is_approved']   = 1;
				$user_data['approved_date'] = current_time( 'mysql', true );
				break;

			case 'awaiting_email_confirmation':
				/* User needs to verify email */
				$user_data['is_verified'] = 0;
				break;

			case 'awaiting_admin_review':
				/* User verified email but awaiting admin approval */
				$user_data['is_verified']       = 1;
				$user_data['verified_date']     = current_time( 'mysql', true );
				$user_data['requires_approval'] = 1;
				$user_data['is_approved']       = 0;
				$user_data['approval_notes']    = 'Migrated from Ultimate Member - awaiting review';
				break;

			case 'inactive':
			case 'rejected':
				/* User was rejected or inactive */
				$user_data['is_disabled']     = 1;
				$user_data['disabled_reason'] = 'rejected';
				break;

			default:
				/* Unknown status - treat as needing verification */
				$user_data['is_verified'] = 0;
				break;
		}

		/*
		 * Migrate profile and cover photos
		 * Copy photos from UM directory to NoBloat directory with optional WebP conversion
		 *
		 * Security: Uses realpath() validation and basename() sanitization to prevent path traversal attacks
		 */
		$copy_photos = isset( $options['copy_photos'] ) ? $options['copy_photos'] : true;

		if ( $copy_photos ) {
			/* Validate user_id is a positive integer */
			$user_id_safe = absint( $user_id );
			if ( $user_id_safe <= 0 ) {
				/* Invalid user ID, skip photo migration */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						0,
						'security',
						'invalid_user_id_photo_migration',
						__( 'Invalid user ID during photo migration', 'nobloat-user-foundry' ),
						array( 'attempted_user_id' => $user_id )
					);
				}
				$user_id_safe = 0;
			}

			if ( $user_id_safe > 0 ) {
				$profile_photo_filename = get_user_meta( $user_id_safe, 'profile_photo', true );
				$cover_photo_filename   = get_user_meta( $user_id_safe, 'cover_photo', true );

				/* Sanitize filenames to prevent path traversal */
				$profile_photo_filename = ! empty( $profile_photo_filename ) ? basename( $profile_photo_filename ) : '';
				$cover_photo_filename   = ! empty( $cover_photo_filename ) ? basename( $cover_photo_filename ) : '';

				/* Get UM upload directory (handle case where UM might not be active) */
				$upload_dir  = wp_upload_dir();
				$um_base_dir = trailingslashit( $upload_dir['basedir'] ) . 'ultimatemember/';
				$um_user_dir = trailingslashit( $um_base_dir ) . $user_id_safe . '/';

				/*
				 * Validate directory path using realpath()
				 */
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
				$um_user_dir_real = realpath( $um_user_dir );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
				$um_base_dir_real = realpath( $um_base_dir );

				if ( ! $um_user_dir_real || ! $um_base_dir_real || 0 !== strpos( $um_user_dir_real, $um_base_dir_real ) ) {
					/* Directory validation failed, log security event */
					if ( class_exists( 'NBUF_Audit_Log' ) ) {
						NBUF_Audit_Log::log(
							$user_id_safe,
							'security',
							'path_traversal_attempt',
							__( 'Path traversal attempt detected during photo migration', 'nobloat-user-foundry' ),
							array(
								'um_user_dir' => $um_user_dir,
								'context'     => 'photo_migration_directory',
							)
						);
					}
					$um_user_dir_real = false;
				}

				/* Copy and convert profile photo */
				if ( $um_user_dir_real && ! empty( $profile_photo_filename ) ) {
					$source_path = $um_user_dir_real . '/' . $profile_photo_filename;

					/*
					 * Verify path is within allowed directory
					 */
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
					$source_path_real = realpath( $source_path );
					if ( ! $source_path_real || 0 !== strpos( $source_path_real, $um_user_dir_real ) || ! file_exists( $source_path_real ) ) {
						/* Log path traversal attempt */
						if ( class_exists( 'NBUF_Audit_Log' ) ) {
							NBUF_Audit_Log::log(
								$user_id_safe,
								'security',
								'path_traversal_attempt',
								__( 'Path traversal attempt detected during profile photo migration', 'nobloat-user-foundry' ),
								array(
									'filename'    => $profile_photo_filename,
									'source_path' => $source_path,
									'context'     => 'profile_photo_migration',
								)
							);
						}
					} else {
						/* Path is safe, proceed with processing */
						$processed = NBUF_Image_Processor::process_image(
							$source_path_real,
							$user_id_safe,
							NBUF_Image_Processor::TYPE_PROFILE
						);

						if ( ! is_wp_error( $processed ) ) {
							$user_data['profile_photo_url']  = $processed['url'];
							$user_data['profile_photo_path'] = $processed['path'];
						}
					}
				}

				/* Copy and convert cover photo */
				if ( $um_user_dir_real && ! empty( $cover_photo_filename ) ) {
					$source_path = $um_user_dir_real . '/' . $cover_photo_filename;

					/*
					 * Verify path is within allowed directory
					 */
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
					$source_path_real = realpath( $source_path );
					if ( ! $source_path_real || 0 !== strpos( $source_path_real, $um_user_dir_real ) || ! file_exists( $source_path_real ) ) {
						/* Log path traversal attempt */
						if ( class_exists( 'NBUF_Audit_Log' ) ) {
							NBUF_Audit_Log::log(
								$user_id_safe,
								'security',
								'path_traversal_attempt',
								__( 'Path traversal attempt detected during cover photo migration', 'nobloat-user-foundry' ),
								array(
									'filename'    => $cover_photo_filename,
									'source_path' => $source_path,
									'context'     => 'cover_photo_migration',
								)
							);
						}
					} else {
						/* Path is safe, proceed with processing */
						$processed = NBUF_Image_Processor::process_image(
							$source_path_real,
							$user_id_safe,
							NBUF_Image_Processor::TYPE_COVER
						);

						if ( ! is_wp_error( $processed ) ) {
							$user_data['cover_photo_url']  = $processed['url'];
							$user_data['cover_photo_path'] = $processed['path'];
						}
					}
				}
			}
		}

		/*
		 * Migrate profile privacy setting
		 * UM values: 'Everyone', 'Only me'
		 * NoBloat values: 'public', 'members_only', 'private'
		 */
		$um_privacy = get_user_meta( $user_id, 'profile_privacy', true );
		if ( ! empty( $um_privacy ) ) {
			/* Map UM privacy to NoBloat privacy */
			switch ( $um_privacy ) {
				case 'Everyone':
					$user_data['profile_privacy'] = 'public';
					break;

				case 'Only me':
					$user_data['profile_privacy'] = 'private';
					break;

				default:
					/* If unknown value, default to public */
					$user_data['profile_privacy'] = 'public';
					break;
			}
		}

		$user_data_table = $wpdb->prefix . 'nbuf_user_data';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration requires direct database query for bulk operations.
		$wpdb->replace(
			$user_data_table,
			$user_data,
			array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		/* Import profile data */
		$profile_data = array( 'user_id' => $user_id );

		foreach ( $all_mappings as $um_field => $target ) {
			$value = get_user_meta( $user_id, $um_field, true );

			if ( empty( $value ) ) {
				continue;
			}

			/* Handle complex mappings with transform */
			if ( is_array( $target ) ) {
				$target_field = $target['target'];
				$transform    = isset( $target['transform'] ) ? $target['transform'] : null;
				$priority     = isset( $target['priority'] ) ? $target['priority'] : 1;

				/* Skip if field already set and this is lower priority */
				if ( isset( $profile_data[ $target_field ] ) && $priority > 1 ) {
					continue;
				}

				/* Skip non-profile fields */
				if ( 0 === strpos( $target_field, 'nbuf_' ) || 0 === strpos( $target_field, 'audit_' ) ) {
					continue;
				}

				$target_field_name = $target_field;
			} else {
				$target_field_name = $target;
			}

			/* Sanitize value based on field type */
			$sanitized_value = $this->sanitize_um_field( $value, $um_field );

			if ( ! is_null( $sanitized_value ) ) {
				$profile_data[ $target_field_name ] = $sanitized_value;
			}
		}

		/* Insert or update profile data */
		if ( count( $profile_data ) > 1 ) { /* More than just user_id */
			$profile_table = $wpdb->prefix . 'nbuf_user_profile';

			/*
			Check if record exists
			*/
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT user_id FROM %i WHERE user_id = %d',
					$profile_table,
					$user_id
				)
			);

			if ( $exists ) {
				/*
				Update existing
				*/
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$profile_table,
					$profile_data,
					array( 'user_id' => $user_id ),
					array_fill( 0, count( $profile_data ), '%s' ),
					array( '%d' )
				);
			} else {
				/*
				Insert new
				*/
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$profile_table,
					$profile_data,
					array_fill( 0, count( $profile_data ), '%s' )
				);
			}
		}

		/* Log to audit if enabled */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'users',
				'migration_imported',
				__( 'User data imported from Ultimate Member', 'nobloat-user-foundry' ),
				array(
					'source_plugin' => 'Ultimate Member',
					'verified'      => $is_verified,
					'fields_count'  => count( $profile_data ) - 1,
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize UM field value
	 *
	 * @param  mixed  $value     UM field value.
	 * @param  string $field_key UM field key.
	 * @return mixed
	 */
	private function sanitize_um_field( $value, $field_key ) {
		/* Determine field type based on key */
		if ( false !== strpos( $field_key, 'email' ) ) {
			return sanitize_email( $value );
		}

		if ( false !== strpos( $field_key, 'url' ) || in_array( $field_key, array( 'website', 'facebook', 'twitter', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok', 'telegram', 'twitch', 'reddit', 'soundcloud', 'vimeo', 'spotify', 'pinterest' ), true ) ) {
			return esc_url_raw( $value );
		}

		if ( false !== strpos( $field_key, 'date' ) || 'birth_date' === $field_key ) {
			return $this->sanitize_field( $value, 'date' );
		}

		if ( 'description' === $field_key || 'bio' === $field_key ) {
			return sanitize_textarea_field( $value );
		}

		/* Default to text sanitization */
		return sanitize_text_field( $value );
	}

	/**
	 * Get user IDs for batch import
	 *
	 * @param  int $limit  Batch size.
	 * @param  int $offset Batch offset.
	 * @return array
	 */
	protected function get_user_ids_for_batch( $limit, $offset ) {
		global $wpdb;

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.ID
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE um.meta_key LIKE %s OR um.meta_key LIKE %s OR um.meta_key = 'account_status'
				LIMIT %d OFFSET %d",
				$wpdb->esc_like( 'um_' ) . '%',
				$wpdb->esc_like( '_um_' ) . '%',
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $user_ids );
	}
}
