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
class NBUF_Migration_Ultimate_Member extends Abstract_NBUF_Migration_Plugin {


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
				'target'    => 'audit_log',
				'transform' => 'um_last_login_to_audit',
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

		/* Get account status */
		$account_status         = get_user_meta( $user_id, 'account_status', true );
		$data['account_status'] = $account_status ? $account_status : 'unknown';
		$data['will_verify']    = ( 'approved' === $account_status ) ? 'Yes' : 'No';

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

		/* Get account status and import to user_data */
		$account_status = get_user_meta( $user_id, 'account_status', true );
		$is_verified    = ( 'approved' === $account_status ) ? 1 : 0;

		$user_data_table = $wpdb->prefix . 'nbuf_user_data';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->replace(
			$user_data_table,
			array(
				'user_id'       => $user_id,
				'is_verified'   => $is_verified,
				'verified_date' => $is_verified ? current_time( 'mysql' ) : null,
				'is_disabled'   => ( 'inactive' === $account_status || 'rejected' === $account_status ) ? 1 : 0,
			),
			array( '%d', '%d', '%s', '%d' )
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
					"SELECT user_id FROM {$wpdb->prefix}nbuf_user_profile WHERE user_id = %d",
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
