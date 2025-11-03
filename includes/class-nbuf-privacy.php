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
			'exporter_friendly_name' => __( 'NoBloat User Foundry - Audit Logs', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_audit_logs' ),
		);

		$exporters['nobloat-user-foundry-2fa-data'] = array(
			'exporter_friendly_name' => __( 'NoBloat User Foundry - 2FA Settings', 'nobloat-user-foundry' ),
			'callback'               => array( __CLASS__, 'export_2fa_data' ),
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

		$erasers['nobloat-user-foundry-audit-logs'] = array(
			'eraser_friendly_name' => __( 'NoBloat User Foundry - Audit Logs', 'nobloat-user-foundry' ),
			'callback'             => array( __CLASS__, 'erase_audit_logs' ),
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
	 * Erase audit logs
	 *
	 * @param  string $email_address User email address.
	 * @param  int    $page          Page number.
	 * @return array Erasure result.
	 */
	public static function erase_audit_logs( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $page required by WordPress privacy eraser signature
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$delete_mode    = NBUF_Options::get( 'nbuf_gdpr_delete_audit_logs', 'anonymize' );
		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		if ( 'delete' === $delete_mode ) {
			/* Permanently delete all audit logs */
			NBUF_Audit_Log::delete_user_logs( $user->ID );
			$items_removed = true;
			$messages[]    = __( 'All audit logs permanently deleted.', 'nobloat-user-foundry' );
		} elseif ( 'anonymize' === $delete_mode ) {
			/* Anonymize audit logs */
			NBUF_Audit_Log::anonymize_user_logs( $user->ID );
			$items_removed = true;
			$messages[]    = __( 'Audit logs anonymized (personal data removed, logs retained for security).', 'nobloat-user-foundry' );
		} else {
			/* Keep logs unchanged */
			$items_retained = true;
			$messages[]     = __( 'Audit logs retained per GDPR settings.', 'nobloat-user-foundry' );
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
	 * Called when a user is deleted to handle audit logs per GDPR settings.
	 *
	 * @param int $user_id User ID being deleted.
	 */
	public static function handle_user_deletion( $user_id ) {
		$delete_mode = NBUF_Options::get( 'nbuf_gdpr_delete_audit_logs', 'anonymize' );

		if ( 'delete' === $delete_mode ) {
			NBUF_Audit_Log::delete_user_logs( $user_id );
		} elseif ( 'anonymize' === $delete_mode ) {
			NBUF_Audit_Log::anonymize_user_logs( $user_id );
		}
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Intentional documentation comment for 'keep' mode, not commented code.
		/* If 'keep', do nothing */
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
			'<h3>%s</h3><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>' .
			'<h3>%s</h3><p>%s</p>' .
			'<h3>%s</h3><p>%s</p>',
			__( 'NoBloat User Foundry', 'nobloat-user-foundry' ),
			__( 'This site uses NoBloat User Foundry to manage user accounts, verification, authentication, and security features.', 'nobloat-user-foundry' ),
			__( 'What Data We Collect', 'nobloat-user-foundry' ),
			__( 'Email verification status and verification dates', 'nobloat-user-foundry' ),
			__( 'Account expiration dates (if applicable)', 'nobloat-user-foundry' ),
			__( 'Two-factor authentication settings and usage logs', 'nobloat-user-foundry' ),
			__( 'Activity logs including login attempts, password changes, and account modifications with IP addresses', 'nobloat-user-foundry' ),
			__( 'Extended profile information such as phone number, company, address (if provided)', 'nobloat-user-foundry' ),
			__( 'How Long We Retain Data', 'nobloat-user-foundry' ),
			__( 'Activity logs are automatically deleted based on configured retention period (default: 90 days). User verification and profile data is retained for the life of the account unless deleted.', 'nobloat-user-foundry' ),
			__( 'Your Rights', 'nobloat-user-foundry' ),
			__( 'You can request a data export or deletion of your personal data at any time through the WordPress privacy tools. Activity logs will be anonymized or deleted per our GDPR settings.', 'nobloat-user-foundry' )
		);

		wp_add_privacy_policy_content(
			'NoBloat User Foundry',
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}
