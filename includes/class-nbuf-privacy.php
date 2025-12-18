<?php
/**
 * NoBloat User Foundry - WordPress Privacy Integration
 *
 * Integrates with WordPress privacy tools for GDPR compliance.
 * Handles personal data export and erasure requests.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Privacy
 *
 * WordPress privacy tools integration for GDPR compliance.
 */
class NBUF_Privacy {


	/**
	 * Initialize privacy hooks
	 */
	public static function init() {
		/* Register exporters */
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporters' ) );

		/* Register erasers */
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_erasers' ) );

		/* Add privacy policy content */
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );

		/* Hook into user deletion to handle audit logs */
		add_action( 'delete_user', array( __CLASS__, 'handle_user_deletion' ), 5 );

		/* Customize privacy confirmation page */
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_privacy_confirmation_styles' ) );
		add_filter( 'user_request_action_confirmed_message', array( __CLASS__, 'customize_confirmation_message' ), 10, 2 );
	}

	/**
	 * Register personal data exporters
	 *
	 * @param  array $exporters Existing exporters.
	 * @return array Modified exporters array.
	 */
	public static function register_exporters( $exporters ) {
		$exporters['nobloat-user-foundry-user-data'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - User Data', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_user_data' ),
		);

		$exporters['nobloat-user-foundry-audit-logs'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - User Activity Logs', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_audit_logs' ),
		);

		$exporters['nobloat-user-foundry-admin-audit-logs'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - Admin Actions on Your Account', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_admin_audit_logs' ),
		);

		$exporters['nobloat-user-foundry-security-logs'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - Security Events', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_security_logs' ),
		);

		$exporters['nobloat-user-foundry-2fa-data'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - 2FA Settings', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_2fa_data' ),
		);

		$exporters['nobloat-user-foundry-profile-photos'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - Profile Photos', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_profile_photos' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data erasers
	 *
	 * @param  array $erasers Existing erasers.
	 * @return array Modified erasers array.
	 */
	public static function register_erasers( $erasers ) {
		$erasers['nobloat-user-foundry-user-data'] = array(
			'eraser_friendly_name' => __( 'NoBloat User Foundry - User Data', 'nobloat-user-foundry' ),
			'callback'             => array( __CLASS__, 'erase_user_data' ),
		);

		$erasers['nobloat-user-foundry-enterprise-logs'] = array(
			'eraser_friendly_name' => __( 'NoBloat User Foundry - All Logs (User Activity, Admin Actions, Security)', 'nobloat-user-foundry' ),
			'callback'             => array( __CLASS__, 'erase_enterprise_logs' ),
		);

		return $erasers;
	}

	/**
	 * Export user data
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_user_data( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy exporter signature
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();

		/* Get user data */
		$user_data = NBUF_User_Data::get( $user->ID );

		if ( $user_data ) {
			$data_to_export[] = array(
				'group_id'    => 'nobloat-user-foundry-user-data',
				'group_label' => __( 'User Verification & Status', 'nobloat-user-foundry' ),
				'item_id'     => 'user-' . $user->ID,
				'data'        => array(
					array(
						'name'  => __( 'Email Verified', 'nobloat-user-foundry' ),
						'value' => $user_data->is_verified ? __( 'Yes', 'nobloat-user-foundry' ) : __( 'No', 'nobloat-user-foundry' ),
					),
					array(
						'name'  => __( 'Verification Date', 'nobloat-user-foundry' ),
						'value' => $user_data->verified_date ? $user_data->verified_date : __( 'Not verified', 'nobloat-user-foundry' ),
					),
					array(
						'name'  => __( 'Account Status', 'nobloat-user-foundry' ),
						'value' => $user_data->is_disabled ? __( 'Disabled', 'nobloat-user-foundry' ) : __( 'Active', 'nobloat-user-foundry' ),
					),
					array(
						'name'  => __( 'Account Expiration', 'nobloat-user-foundry' ),
						'value' => $user_data->expires_at ? $user_data->expires_at : __( 'No expiration', 'nobloat-user-foundry' ),
					),
				),
			);
		}

		/* Get profile data */
		$profile_data = NBUF_Profile_Data::get( $user->ID );

		if ( $profile_data ) {
			$profile_items = array();

			if ( ! empty( $profile_data->phone ) ) {
				$profile_items[] = array(
					'name'  => __( 'Phone', 'nobloat-user-foundry' ),
					'value' => $profile_data->phone,
				);
			}

			if ( ! empty( $profile_data->company ) ) {
				$profile_items[] = array(
					'name'  => __( 'Company', 'nobloat-user-foundry' ),
					'value' => $profile_data->company,
				);
			}

			if ( ! empty( $profile_data->job_title ) ) {
				$profile_items[] = array(
					'name'  => __( 'Job Title', 'nobloat-user-foundry' ),
					'value' => $profile_data->job_title,
				);
			}

			if ( ! empty( $profile_data->address ) ) {
				$profile_items[] = array(
					'name'  => __( 'Address', 'nobloat-user-foundry' ),
					'value' => $profile_data->address,
				);
			}

			if ( ! empty( $profile_items ) ) {
				$data_to_export[] = array(
					'group_id'    => 'nobloat-user-foundry-profile-data',
					'group_label' => __( 'Extended Profile Data', 'nobloat-user-foundry' ),
					'item_id'     => 'profile-' . $user->ID,
					'data'        => $profile_items,
				);
			}
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Export audit logs
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_audit_logs( $email_address, $page = 1 ) {
		/* Check if audit logs should be included */
		if ( ! NBUF_Options::get( 'nbuf_gdpr_include_audit_logs', true ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();
		$per_page       = 500;
		$offset         = ( $page - 1 ) * $per_page;

		/* Get audit logs for this user */
		$logs = NBUF_Audit_Log::get_logs(
			array( 'user_id' => $user->ID ),
			$per_page,
			$offset,
			'created_at',
			'DESC'
		);

		foreach ( $logs as $log ) {
			$data_to_export[] = array(
				'group_id'    => 'nobloat-user-foundry-audit-logs',
				'group_label' => __( 'Activity Logs', 'nobloat-user-foundry' ),
				'item_id'     => 'log-' . $log->id,
				'data'        => array(
					array(
						'name'  => __( 'Date/Time', 'nobloat-user-foundry' ),
						'value' => $log->created_at,
					),
					array(
						'name'  => __( 'Event', 'nobloat-user-foundry' ),
						'value' => $log->event_type,
					),
					array(
						'name'  => __( 'Status', 'nobloat-user-foundry' ),
						'value' => $log->event_status,
					),
					array(
						'name'  => __( 'Details', 'nobloat-user-foundry' ),
						'value' => $log->event_message,
					),
					array(
						'name'  => __( 'IP Address', 'nobloat-user-foundry' ),
						'value' => $log->ip_address ? $log->ip_address : 'N/A',
					),
				),
			);
		}

		$done = count( $logs ) < $per_page;

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Export admin audit logs
	 *
	 * Exports admin actions performed on this user's account.
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_admin_audit_logs( $email_address, $page = 1 ) {
		/* Check if admin audit logs should be included */
		if ( ! NBUF_Options::get( 'nbuf_gdpr_include_admin_audit_logs', true ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();
		$per_page       = 500;
		$offset         = ( $page - 1 ) * $per_page;

		/* Get admin actions performed on this user */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			$logs = NBUF_Admin_Audit_Log::get_user_modifications(
				$user->ID,
				$per_page,
				$offset
			);

			foreach ( $logs as $log ) {
				$admin_user = get_userdata( $log->admin_id );
				$admin_name = $admin_user ? $admin_user->user_login : 'Unknown';

				$data_to_export[] = array(
					'group_id'    => 'nobloat-user-foundry-admin-audit-logs',
					'group_label' => __( 'Admin Actions on Your Account', 'nobloat-user-foundry' ),
					'item_id'     => 'admin-log-' . $log->id,
					'data'        => array(
						array(
							'name'  => __( 'Date/Time', 'nobloat-user-foundry' ),
							'value' => $log->created_at,
						),
						array(
							'name'  => __( 'Admin User', 'nobloat-user-foundry' ),
							'value' => $admin_name,
						),
						array(
							'name'  => __( 'Action', 'nobloat-user-foundry' ),
							'value' => $log->action_type,
						),
						array(
							'name'  => __( 'Status', 'nobloat-user-foundry' ),
							'value' => $log->status,
						),
						array(
							'name'  => __( 'Details', 'nobloat-user-foundry' ),
							'value' => $log->description,
						),
					),
				);
			}

			$done = count( $logs ) < $per_page;
		} else {
			$done = true;
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Export security logs
	 *
	 * Exports security events related to this user.
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_security_logs( $email_address, $page = 1 ) {
		/* Check if security logs should be included */
		if ( ! NBUF_Options::get( 'nbuf_gdpr_include_security_logs', true ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();
		$per_page       = 500;
		$offset         = ( $page - 1 ) * $per_page;

		/* Get security logs for this user */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			$logs = NBUF_Security_Log::get_logs(
				array(
					'user_id' => $user->ID,
					'limit'   => $per_page,
					'offset'  => $offset,
					'orderby' => 'timestamp',
					'order'   => 'DESC',
				)
			);

			foreach ( $logs as $log ) {
				$data_to_export[] = array(
					'group_id'    => 'nobloat-user-foundry-security-logs',
					'group_label' => __( 'Security Events', 'nobloat-user-foundry' ),
					'item_id'     => 'security-log-' . $log->id,
					'data'        => array(
						array(
							'name'  => __( 'Date/Time', 'nobloat-user-foundry' ),
							'value' => $log->timestamp,
						),
						array(
							'name'  => __( 'Event Type', 'nobloat-user-foundry' ),
							'value' => $log->event_type,
						),
						array(
							'name'  => __( 'Severity', 'nobloat-user-foundry' ),
							'value' => $log->severity,
						),
						array(
							'name'  => __( 'Details', 'nobloat-user-foundry' ),
							'value' => $log->message,
						),
						array(
							'name'  => __( 'IP Address', 'nobloat-user-foundry' ),
							'value' => $log->ip_address ? $log->ip_address : 'N/A',
						),
					),
				);
			}

			$done = count( $logs ) < $per_page;
		} else {
			$done = true;
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Export 2FA data
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_2fa_data( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy exporter signature
		/* Check if 2FA data should be included */
		if ( ! NBUF_Options::get( 'nbuf_gdpr_include_2fa_data', true ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();
		$twofa_data     = NBUF_User_2FA_Data::get( $user->ID );

		if ( $twofa_data && $twofa_data->enabled ) {
			$data_to_export[] = array(
				'group_id'    => 'nobloat-user-foundry-2fa-data',
				'group_label' => __( 'Two-Factor Authentication', 'nobloat-user-foundry' ),
				'item_id'     => '2fa-' . $user->ID,
				'data'        => array(
					array(
						'name'  => __( '2FA Enabled', 'nobloat-user-foundry' ),
						'value' => __( 'Yes', 'nobloat-user-foundry' ),
					),
					array(
						'name'  => __( 'Method', 'nobloat-user-foundry' ),
						'value' => 'email' === $twofa_data->method ? __( 'Email', 'nobloat-user-foundry' ) : __( 'Authenticator App', 'nobloat-user-foundry' ),
					),
					array(
						'name'  => __( 'Setup Date', 'nobloat-user-foundry' ),
						'value' => $twofa_data->created_at,
					),
					array(
						'name'  => __( 'Last Used', 'nobloat-user-foundry' ),
						'value' => $twofa_data->last_used ? $twofa_data->last_used : __( 'Never', 'nobloat-user-foundry' ),
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Export profile photos
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Export data.
	 */
	public static function export_profile_photos( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy exporter signature
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data_to_export = array();
		$files          = array();

		/* Scan user's upload directory for all files */
		$upload_dir = wp_upload_dir();
		$user_dir   = trailingslashit( $upload_dir['basedir'] ) . 'nobloat/users/' . $user->ID;

		if ( is_dir( $user_dir ) ) {
			$base_url = trailingslashit( $upload_dir['baseurl'] ) . 'nobloat/users/' . $user->ID . '/';

			/* Get all files in the directory (non-recursive for security) */
			$dir_files = scandir( $user_dir );
			if ( $dir_files ) {
				foreach ( $dir_files as $file ) {
					/* Skip . and .. and hidden files */
					if ( '.' === $file[0] ) {
						continue;
					}

					$file_path = $user_dir . '/' . $file;

					/* Only include actual files, not directories */
					if ( is_file( $file_path ) ) {
						/* Determine file type for label */
						$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
						$is_image  = in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ), true );

						$files[] = array(
							'name'  => $is_image ? __( 'Image', 'nobloat-user-foundry' ) : __( 'File', 'nobloat-user-foundry' ),
							'value' => $base_url . $file,
						);
					}
				}
			}
		}

		if ( ! empty( $files ) ) {
			$data_to_export[] = array(
				'group_id'    => 'nobloat-user-foundry-profile-photos',
				'group_label' => __( 'User Uploaded Files', 'nobloat-user-foundry' ),
				'item_id'     => 'files-' . $user->ID,
				'data'        => $files,
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Erase user data
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Erasure result.
	 */
	public static function erase_user_data( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy eraser signature
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed = false;
		$messages      = array();

		/* Anonymize user data */
		$user_data = NBUF_User_Data::get( $user->ID );
		if ( $user_data ) {
			NBUF_User_Data::update(
				$user->ID,
				array(
					'verified_date'            => null,
					'expiration_warned_at'     => null,
					'weak_password_flagged_at' => null,
				)
			);
			$items_removed = true;
			$messages[]    = __( 'User verification and expiration data anonymized.', 'nobloat-user-foundry' );
		}

		/* Anonymize profile data - Set all fields to NULL */
		$profile_data = NBUF_Profile_Data::get( $user->ID );
		if ( $profile_data ) {
			$all_fields     = NBUF_Profile_Data::get_all_field_keys();
			$anonymize_data = array();
			foreach ( $all_fields as $field ) {
				$anonymize_data[ $field ] = null;
			}
			NBUF_Profile_Data::update( $user->ID, $anonymize_data );
			$items_removed = true;
			$messages[]    = __( 'Extended profile data erased.', 'nobloat-user-foundry' );
		}

		/* Disable 2FA */
		if ( NBUF_User_2FA_Data::is_enabled( $user->ID ) ) {
			NBUF_User_2FA_Data::update( $user->ID, array( 'enabled' => 0 ) );
			$items_removed = true;
			$messages[]    = __( '2FA disabled.', 'nobloat-user-foundry' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Erase enterprise logs (all 3 tables)
	 *
	 * Handles erasure for User Activity Log, Admin Actions Log, and Security Log
	 * based on GDPR settings.
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Erasure result.
	 */
	public static function erase_enterprise_logs( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy eraser signature
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		/* Get GDPR deletion mode (delete, anonymize, keep) */
		$deletion_mode = NBUF_Options::get( 'nbuf_logging_user_deletion_action', 'anonymize' );

		if ( 'delete' === $deletion_mode ) {
			/* Permanently delete logs from all 3 tables */

			/* Delete user activity logs */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::delete_user_logs( $user->ID );
				$items_removed = true;
			}

			/* Delete admin action logs (where this user is the target) */
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::delete_target_user_logs( $user->ID );
				$items_removed = true;
			}

			/* Delete security logs */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::delete_user_logs( $user->ID );
				$items_removed = true;
			}

			$messages[] = __( 'All logs permanently deleted (user activity, admin actions, security events).', 'nobloat-user-foundry' );

		} elseif ( 'anonymize' === $deletion_mode ) {
			/* Anonymize logs in all 3 tables */

			/* Anonymize user activity logs */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::anonymize_user_logs( $user->ID );
				$items_removed = true;
			}

			/* Anonymize admin action logs (where this user is the target) */
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::anonymize_target_user_logs( $user->ID );
				$items_removed = true;
			}

			/* Anonymize security logs */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::anonymize_user_logs( $user->ID );
				$items_removed = true;
			}

			$messages[] = __( 'All logs anonymized (personal data removed, logs retained for security and compliance).', 'nobloat-user-foundry' );

		} else {
			/* Keep logs unchanged */
			$items_retained = true;
			$messages[]     = __( 'All logs retained per GDPR settings (compliance with legal obligations).', 'nobloat-user-foundry' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Handle user deletion
	 *
	 * Called when a user is deleted to:
	 * 1. Clean up all user data from plugin tables (always)
	 * 2. Handle logs per GDPR settings (delete, anonymize, or keep)
	 *
	 * @param int $user_id User ID being deleted.
	 */
	public static function handle_user_deletion( $user_id ) {
		/*
		 * ALWAYS clean up user data from plugin tables.
		 * This is not affected by log deletion settings - user data must be removed.
		 */

		/* Delete verification/password reset tokens */
		if ( class_exists( 'NBUF_Database' ) ) {
			NBUF_Database::delete_user_tokens( $user_id );
		}

		/* Delete user data record */
		if ( class_exists( 'NBUF_User_Data' ) ) {
			NBUF_User_Data::delete( $user_id );
		}

		/* Delete profile data */
		if ( class_exists( 'NBUF_Profile_Data' ) ) {
			NBUF_Profile_Data::delete( $user_id );
		}

		/* Delete 2FA data */
		if ( class_exists( 'NBUF_User_2FA_Data' ) ) {
			NBUF_User_2FA_Data::delete( $user_id );
		}

		/* Delete user notes */
		if ( class_exists( 'NBUF_User_Notes' ) ) {
			NBUF_User_Notes::delete_user_notes( $user_id );
		}

		/* Delete version history */
		if ( class_exists( 'NBUF_Version_History' ) ) {
			$version_history = new NBUF_Version_History();
			$version_history->delete_user_versions( $user_id );
		}

		/* Passkeys are handled separately via delete_user hook in NBUF_Passkeys */

		/*
		 * Handle logs per GDPR settings.
		 * These are audit/security logs, not user data.
		 */
		$deletion_mode = NBUF_Options::get( 'nbuf_logging_user_deletion_action', 'anonymize' );

		if ( 'delete' === $deletion_mode ) {
			/* Permanently delete logs from all 3 tables */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::delete_user_logs( $user_id );
			}
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::delete_target_user_logs( $user_id );
			}
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::delete_user_logs( $user_id );
			}
		} elseif ( 'anonymize' === $deletion_mode ) {
			/* Anonymize logs in all 3 tables */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::anonymize_user_logs( $user_id );
			}
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::anonymize_target_user_logs( $user_id );
			}
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::anonymize_user_logs( $user_id );
			}
		}
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Intentional documentation comment for 'keep' mode, not commented code.
		/* If 'keep', do nothing - logs retained for compliance */
	}

	/**
	 * Add privacy policy content
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%s</h2><p>%s</p>' .
			'<h3>%s</h3><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>' .
			'<h3>%s</h3><p>%s</p>' .
			'<h3>%s</h3><p>%s</p>',
			__( 'NoBloat User Foundry', 'nobloat-user-foundry' ),
			__( 'This site uses NoBloat User Foundry to manage user accounts, verification, authentication, and security features with enterprise-grade logging for compliance and security.', 'nobloat-user-foundry' ),
			__( 'What Data We Collect', 'nobloat-user-foundry' ),
			__( 'Email verification status and verification dates', 'nobloat-user-foundry' ),
			__( 'Account expiration dates (if applicable)', 'nobloat-user-foundry' ),
			__( 'Two-factor authentication settings and usage logs', 'nobloat-user-foundry' ),
			__( 'User Activity Logs: Your login attempts, password changes, and account modifications with IP addresses (retained 1 year)', 'nobloat-user-foundry' ),
			__( 'Admin Actions Logs: Administrative actions performed on your account including edits, role changes, and security operations (retained permanently for compliance)', 'nobloat-user-foundry' ),
			__( 'Security Event Logs: System security events related to your account including failed logins and security warnings (retained 90 days)', 'nobloat-user-foundry' ),
			__( 'Extended profile information such as phone number, company, address (if provided)', 'nobloat-user-foundry' ),
			__( 'How Long We Retain Data', 'nobloat-user-foundry' ),
			__( 'Logs are automatically deleted based on configured retention periods: User Activity (1 year), Admin Actions (permanent for compliance), Security Events (90 days). User verification and profile data is retained for the life of the account unless deleted. All retention periods comply with GDPR Article 5(1)(e) storage limitation principle.', 'nobloat-user-foundry' ),
			__( 'Your Rights', 'nobloat-user-foundry' ),
			__( 'You can request a data export or deletion of your personal data at any time through the WordPress privacy tools. All logs (User Activity, Admin Actions, Security Events) will be anonymized or deleted per our GDPR settings. Note: Admin Actions logs may be retained in anonymized form to comply with legal obligations under GDPR Article 6(1)(c).', 'nobloat-user-foundry' )
		);

		wp_add_privacy_policy_content(
			'NoBloat User Foundry',
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Enqueue custom styles for privacy confirmation page
	 *
	 * Improves the default WordPress privacy confirmation page appearance.
	 */
	public static function enqueue_privacy_confirmation_styles() {
		/* Only load on privacy confirmation pages */
		if ( ! isset( $_GET['action'] ) || 'confirmaction' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for page identification.
			return;
		}

		/* Custom CSS for the confirmation page */
		$css = '
			/* Override WordPress login page styles for privacy confirmation */
			body.login {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				min-height: 100vh;
			}

			#login {
				width: 420px;
				padding: 8% 0 0;
			}

			#login h1 a {
				background-image: none;
				width: auto;
				height: auto;
				text-indent: -9999px;
				margin: 0 auto 40px;
				display: block;
				position: relative;
			}

			#login h1 a::before {
				content: "' . esc_js( get_bloginfo( 'name' ) ) . '";
				display: block;
				font-size: 28px;
				font-weight: 600;
				color: #fff;
				text-align: center;
				text-shadow: 0 2px 4px rgba(0,0,0,0.2);
				text-indent: 0;
				position: absolute;
				left: 0;
				right: 0;
			}

			.login form {
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 10px 40px rgba(0,0,0,0.2);
				padding: 30px;
				margin-top: 0;
			}

			.login .message {
				background: #fff;
				border: none;
				border-radius: 8px;
				box-shadow: 0 10px 40px rgba(0,0,0,0.2);
				padding: 30px;
				margin: 0 0 20px;
				text-align: center;
			}

			.login .message h2 {
				color: #2e7d32;
				font-size: 22px;
				margin: 0 0 15px;
				padding: 0;
			}

			.login .message .nbuf-privacy-icon {
				font-size: 48px;
				margin-bottom: 15px;
			}

			.login .message p {
				color: #555;
				font-size: 15px;
				line-height: 1.6;
				margin: 10px 0;
			}

			.login .message .nbuf-next-steps {
				background: #f8f9fa;
				border-radius: 6px;
				padding: 15px;
				margin-top: 20px;
				text-align: left;
			}

			.login .message .nbuf-next-steps h4 {
				color: #333;
				font-size: 14px;
				font-weight: 600;
				margin: 0 0 10px;
			}

			.login .message .nbuf-next-steps p {
				font-size: 13px;
				margin: 5px 0;
				color: #666;
			}

			#backtoblog {
				text-align: center;
			}

			#backtoblog a {
				color: rgba(255,255,255,0.8);
				text-decoration: none;
				font-size: 14px;
				transition: color 0.2s;
			}

			#backtoblog a:hover {
				color: #fff;
			}

			.login #nav {
				text-align: center;
			}

			.login #nav a {
				color: rgba(255,255,255,0.8);
			}

			.login #nav a:hover {
				color: #fff;
			}
		';

		/* phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline styles, no external file */
		wp_add_inline_style( 'login', $css );
	}

	/**
	 * Customize the privacy confirmation message
	 *
	 * @param string $message     The default confirmation message.
	 * @param string $action_type The type of request (export_personal_data, remove_personal_data).
	 * @return string Modified confirmation message.
	 */
	public static function customize_confirmation_message( $message, $action_type ) {
		$site_name = get_bloginfo( 'name' );

		if ( 'export_personal_data' === $action_type ) {
			$icon  = '&#128230;'; /* Package/box emoji */
			$title = __( 'Export Request Confirmed', 'nobloat-user-foundry' );
			$body  = sprintf(
				/* translators: %s: site name */
				__( 'Thank you for confirming your data export request. The site administrator at %s has been notified and will process your request.', 'nobloat-user-foundry' ),
				'<strong>' . esc_html( $site_name ) . '</strong>'
			);
			$next_steps = sprintf(
				'<div class="nbuf-next-steps"><h4>%s</h4><p>%s</p><p>%s</p></div>',
				__( 'What happens next?', 'nobloat-user-foundry' ),
				__( 'You will receive an email with a download link once your data export is ready. This typically takes 24-48 hours.', 'nobloat-user-foundry' ),
				__( 'The download link will be valid for 3 days. Make sure to save the file in a secure location.', 'nobloat-user-foundry' )
			);
		} elseif ( 'remove_personal_data' === $action_type ) {
			$icon  = '&#128465;'; /* Wastebasket emoji */
			$title = __( 'Erasure Request Confirmed', 'nobloat-user-foundry' );
			$body  = sprintf(
				/* translators: %s: site name */
				__( 'Thank you for confirming your data erasure request. The site administrator at %s has been notified and will process your request.', 'nobloat-user-foundry' ),
				'<strong>' . esc_html( $site_name ) . '</strong>'
			);
			$next_steps = sprintf(
				'<div class="nbuf-next-steps"><h4>%s</h4><p>%s</p><p>%s</p></div>',
				__( 'What happens next?', 'nobloat-user-foundry' ),
				__( 'The administrator will review and process your request. You will receive a confirmation email once your data has been erased.', 'nobloat-user-foundry' ),
				__( 'Note: Some data may be retained for legal compliance as outlined in our privacy policy.', 'nobloat-user-foundry' )
			);
		} else {
			return $message;
		}

		return sprintf(
			'<div class="nbuf-privacy-icon">%s</div><h2>%s</h2><p>%s</p>%s',
			$icon,
			$title,
			$body,
			$next_steps
		);
	}
}
