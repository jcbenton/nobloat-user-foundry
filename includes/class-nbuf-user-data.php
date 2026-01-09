<?php
/**
 * User Data Management
 *
 * Handles all user verification and expiration data stored in custom table.
 * Replaces usermeta approach with performant table structure.
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
 * Direct database access is architectural for user data management.
 * Custom nbuf_user_data table stores verification/expiration data and cannot use
 * WordPress's standard meta APIs. Caching is not implemented as data changes
 * frequently and caching would introduce stale data issues.
 */

/**
 * User data management class.
 *
 * Provides interface for reading/writing user verification and expiration data.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @author     NoBloat
 */
class NBUF_User_Data {


	/**
	 * Get user data from custom table.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return object|null         User data object or null if not found.
	 */
	public static function get( int $user_id ): ?object {
		global $wpdb;
		$table_name = NBUF_Database::get_table_name( 'user_data' );

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
	 * Check if user is verified.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if verified, false otherwise.
	 */
	public static function is_verified( int $user_id ): bool {
		$data = self::get( $user_id );
		/* SECURITY: Use strict comparison to prevent type juggling attacks. */
		return $data && 1 === (int) $data->is_verified;
	}

	/**
	 * Check if user is disabled.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if disabled, false otherwise.
	 */
	public static function is_disabled( int $user_id ): bool {
		$data = self::get( $user_id );
		/* SECURITY: Use strict comparison to prevent type juggling attacks. */
		return $data && 1 === (int) $data->is_disabled;
	}

	/**
	 * Get user's expiration date.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Expiration datetime or null.
	 */
	public static function get_expiration( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->expires_at : null;
	}

	/**
	 * Check if user account is expired.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if expired, false otherwise.
	 */
	public static function is_expired( int $user_id ): bool {
		$expires_at = self::get_expiration( $user_id );
		if ( ! $expires_at ) {
			return false;
		}
		/* FIXED: Use consistent UTC time for comparison (database stores GMT/UTC). */
		return strtotime( $expires_at ) <= time();
	}

	/**
	 * Set user as verified.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function set_verified( int $user_id ): bool {
		return self::update(
			$user_id,
			array(
				'is_verified'   => 1,
				'verified_date' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Set user as unverified.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function set_unverified( int $user_id ): bool {
		return self::update(
			$user_id,
			array(
				'is_verified'   => 0,
				'verified_date' => null,
			)
		);
	}

	/**
	 * Set user as disabled.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $reason  Reason for disable (manual, expired, etc.).
	 * @return bool                   True on success, false on failure.
	 */
	public static function set_disabled( int $user_id, string $reason = 'manual' ): bool {
		/* Log account disabled. */
		NBUF_Audit_Log::log(
			$user_id,
			'account_disabled',
			'success',
			'User account disabled',
			array( 'reason' => $reason )
		);

		$result = self::update(
			$user_id,
			array(
				'is_disabled'     => 1,
				'disabled_reason' => $reason,
			)
		);

		if ( $result ) {
			/**
			 * Fires when a user account is disabled.
			 *
			 * @param int    $user_id User ID.
			 * @param string $reason  Disable reason.
			 */
			do_action( 'nbuf_user_disabled', $user_id, $reason );
		}

		return $result;
	}

	/**
	 * Set user as enabled.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function set_enabled( int $user_id ): bool {
		/* Log account enabled. */
		NBUF_Audit_Log::log(
			$user_id,
			'account_enabled',
			'success',
			'User account enabled'
		);

		return self::update(
			$user_id,
			array(
				'is_disabled'     => 0,
				'disabled_reason' => null,
			)
		);
	}

	/**
	 * Set user expiration date.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id    User ID.
	 * @param  string $expires_at Expiration datetime (MySQL format) or null to remove.
	 * @return bool                     True on success, false on failure.
	 */
	public static function set_expiration( int $user_id, ?string $expires_at ): bool {
		$data = array( 'expires_at' => $expires_at );

		// If removing expiration and user is expired, auto-enable.
		if ( null === $expires_at && self::is_expired( $user_id ) ) {
			$user_data = self::get( $user_id );
			if ( $user_data && 'expired' === $user_data->disabled_reason ) {
				$data['is_disabled']     = 0;
				$data['disabled_reason'] = null;
			}
		}

		// Reset warning flag when expiration changes.
		$data['expiration_warned_at'] = null;

		return self::update( $user_id, $data );
	}

	/**
	 * Mark that expiration warning was sent.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function set_expiration_warned( int $user_id ): bool {
		return self::update(
			$user_id,
			array(
				'expiration_warned_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Update user's last login timestamp.
	 *
	 * Called on successful login to track when user last accessed the site.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return bool         True on success, false on failure.
	 */
	public static function update_last_login( int $user_id ): bool {
		return self::update(
			$user_id,
			array(
				'last_login_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Get user's last login timestamp.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return string|null  Last login datetime or null if never logged in.
	 */
	public static function get_last_login( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->last_login_at : null;
	}

	/**
	 * Check if user requires admin approval.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool True if requires approval.
	 */
	public static function requires_approval( int $user_id ): bool {
		$data = self::get( $user_id );
		return $data && 1 === (int) $data->requires_approval;
	}

	/**
	 * Check if user is approved.
	 *
	 * Returns true if user is approved OR doesn't require approval.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool True if approved or approval not required.
	 */
	public static function is_approved( int $user_id ): bool {
		$data = self::get( $user_id );

		/* If doesn't require approval, consider approved */
		if ( ! $data || ! $data->requires_approval ) {
			return true;
		}

		return 1 === (int) $data->is_approved;
	}

	/**
	 * Set user to require approval.
	 *
	 * @since  1.0.0
	 * @param  int  $user_id User ID.
	 * @param  bool $requires Whether approval is required.
	 * @return bool True on success.
	 */
	public static function set_requires_approval( int $user_id, bool $requires = true ): bool {
		return self::update(
			$user_id,
			array(
				'requires_approval' => $requires ? 1 : 0,
				'is_approved'       => $requires ? 0 : 1,  /* If no longer requires, auto-approve */
			)
		);
	}

	/**
	 * Approve user account.
	 *
	 * Manual approval - works regardless of global settings.
	 * Admin can manually approve any user at any time.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID to approve.
	 * @param  int    $admin_id Admin who approved.
	 * @param  string $notes Optional approval notes.
	 * @return bool True on success.
	 */
	public static function approve_user( int $user_id, int $admin_id, string $notes = '' ): bool {
		/* Log approval */
		NBUF_Audit_Log::log(
			$user_id,
			'users',
			'account_approved',
			array(
				'approved_by' => $admin_id,
				'notes'       => $notes,
			)
		);

		$result = self::update(
			$user_id,
			array(
				'is_approved'    => 1,
				'approved_by'    => $admin_id,
				'approved_date'  => current_time( 'mysql', true ),
				'approval_notes' => sanitize_textarea_field( $notes ),
			)
		);

		/* Send approval notification email */
		if ( $result ) {
			$user = get_userdata( $user_id );
			if ( $user && class_exists( 'NBUF_Email' ) ) {
				NBUF_Email::send_account_approved_email( $user->user_email, $user->user_login );
			}

			/**
			 * Fires when a user account is approved.
			 *
			 * @param int $user_id User ID.
			 */
			do_action( 'nbuf_user_approved', $user_id );
		}

		return $result;
	}

	/**
	 * Reject user account.
	 *
	 * Manual rejection - works regardless of global settings.
	 * Sets account as disabled with 'rejected' reason.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID to reject.
	 * @param  int    $admin_id Admin who rejected.
	 * @param  string $reason Rejection reason.
	 * @return bool True on success.
	 */
	public static function reject_user( int $user_id, int $admin_id, string $reason = '' ): bool {
		/* Log rejection */
		NBUF_Audit_Log::log(
			$user_id,
			'users',
			'account_rejected',
			array(
				'rejected_by' => $admin_id,
				'reason'      => $reason,
			)
		);

		/* Disable account with 'rejected' reason */
		$result = self::set_disabled( $user_id, 'rejected' );

		/* Send rejection email */
		if ( $result ) {
			$user = get_userdata( $user_id );
			if ( $user && class_exists( 'NBUF_Email' ) ) {
				NBUF_Email::send_account_rejected_email( $user->user_email, $reason );
			}
		}

		return $result;
	}

	/**
	 * Manually verify user.
	 *
	 * Admin can manually verify any user regardless of whether
	 * email verification is enabled in settings.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID to verify.
	 * @param  int $admin_id Admin who verified.
	 * @return bool True on success.
	 */
	public static function manually_verify( int $user_id, int $admin_id ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameter preserved for API consistency, admin ID retrieved internally by logging method.
		/* Log manual verification to admin audit log */
		NBUF_Admin_Action_Hooks::log_manual_verification( $user_id );

		return self::set_verified( $user_id );
	}

	/**
	 * Manually unverify user.
	 *
	 * Admin can manually unverify any user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID to unverify.
	 * @param  int $admin_id Admin who unverified.
	 * @return bool True on success.
	 */
	public static function manually_unverify( int $user_id, int $admin_id ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameter preserved for API consistency, admin ID retrieved internally by logging method.
		/* Log manual unverification to admin audit log */
		NBUF_Admin_Action_Hooks::log_manual_unverification( $user_id );

		return self::set_unverified( $user_id );
	}

	/**
	 * Update user data in table.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id User ID.
	 * @param  array $data    Data to update (column => value).
	 * @return bool                 True on success, false on failure.
	 */
	public static function update( int $user_id, array $data ): bool {
		global $wpdb;
		$table_name = NBUF_Database::get_table_name( 'user_data' );

		// Check if record exists.
		$exists = self::get( $user_id );

		if ( $exists ) {
			// Update existing record.
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'user_id' => $user_id ),
				null,
				array( '%d' )
			);
		} else {
			// Insert new record.
			$data['user_id'] = $user_id;
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $table_name, $data );
		}

		/* Invalidate unified user cache. */
		if ( false !== $result && class_exists( 'NBUF_User' ) ) {
			NBUF_User::invalidate_cache( $user_id, 'user_data' );
		}

		return false !== $result;
	}

	/**
	 * Delete user data from table.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function delete( int $user_id ): bool {
		global $wpdb;
		$table_name = NBUF_Database::get_table_name( 'user_data' );

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all users with expiration before given date.
	 *
	 * @since  1.0.0
	 * @param  string $before_date MySQL datetime.
	 * @return array                     Array of user IDs.
	 */
	public static function get_expiring_before( string $before_date ): array {
		global $wpdb;
		$table_name = NBUF_Database::get_table_name( 'user_data' );

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM %i
				WHERE expires_at IS NOT NULL
				AND expires_at != '0000-00-00 00:00:00'
				AND expires_at <= %s
				AND is_disabled = 0",
				$table_name,
				$before_date
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get users needing expiration warning.
	 *
	 * @since  1.0.0
	 * @param  string $warning_date MySQL datetime for warning threshold.
	 * @return array                      Array of user IDs.
	 */
	public static function get_needing_warning( $warning_date ) {
		global $wpdb;
		$table_name = NBUF_Database::get_table_name( 'user_data' );

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM %i
				WHERE expires_at IS NOT NULL
				AND expires_at != '0000-00-00 00:00:00'
				AND expires_at <= %s
				AND (expiration_warned_at IS NULL OR expiration_warned_at = '0000-00-00 00:00:00')
				AND is_disabled = 0",
				$table_name,
				$warning_date
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get user count by filters.
	 *
	 * @since  1.0.0
	 * @param  string $filter Filter type: 'verified', 'unverified', 'disabled', 'enabled', 'has_expiration', 'no_expiration', 'expired'.
	 * @return int                  User count.
	 */
	public static function get_count( $filter = '' ) {
		global $wpdb;
		$table_name  = NBUF_Database::get_table_name( 'user_data' );
		$users_table = $wpdb->prefix . 'users';

		$where = '';

		switch ( $filter ) {
			case 'verified':
				$where = 'AND d.is_verified = 1';
				break;
			case 'unverified':
				$where = 'AND (d.is_verified = 0 OR d.is_verified IS NULL)';
				break;
			case 'disabled':
				$where = 'AND d.is_disabled = 1';
				break;
			case 'enabled':
				$where = 'AND (d.is_disabled = 0 OR d.is_disabled IS NULL)';
				break;
			case 'has_expiration':
				$where = "AND d.expires_at IS NOT NULL AND d.expires_at != '0000-00-00 00:00:00'";
				break;
			case 'no_expiration':
				$where = "AND (d.expires_at IS NULL OR d.expires_at = '0000-00-00 00:00:00')";
				break;
			case 'expired':
				$current_time = current_time( 'mysql', true );
				$where        = $wpdb->prepare(
					"AND d.expires_at IS NOT NULL AND d.expires_at != '0000-00-00 00:00:00' AND d.expires_at <= %s",
					$current_time
				);
				break;
		}

		// Build base query with properly escaped table names.
		$base_query = $wpdb->prepare(
			'SELECT COUNT(DISTINCT u.ID) FROM %i u LEFT JOIN %i d ON u.ID = d.user_id WHERE 1=1',
			$users_table,
			$table_name
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is static switch case or already prepared above.
		$count = $wpdb->get_var( $base_query . ' ' . $where );

		return (int) $count;
	}

	/**
	 * Get weak password flagged timestamp.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Flagged datetime or null.
	 */
	public static function get_weak_password_flagged_at( $user_id ) {
		$data = self::get( $user_id );
		return $data ? $data->weak_password_flagged_at : null;
	}

	/**
	 * Get password changed timestamp.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Changed datetime or null.
	 */
	public static function get_password_changed_at( $user_id ) {
		$data = self::get( $user_id );
		return $data ? $data->password_changed_at : null;
	}

	/**
	 * Flag user password as weak.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function flag_weak_password( $user_id ) {
		return self::update(
			$user_id,
			array(
				'weak_password_flagged_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Clear weak password flag.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function clear_weak_password_flag( $user_id ) {
		return self::update(
			$user_id,
			array(
				'weak_password_flagged_at' => null,
			)
		);
	}

	/**
	 * Set password changed timestamp.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	public static function set_password_changed( $user_id ) {
		return self::update(
			$user_id,
			array(
				'password_changed_at'      => current_time( 'mysql', true ),
				'weak_password_flagged_at' => null, // Clear flag when password changes.
			)
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
