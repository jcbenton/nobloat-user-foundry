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
	 * @since    1.0.0
	 * @param    int    $user_id    User ID
	 * @return   object|null         User data object or null if not found
	 */
	public static function get( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		return $data;
	}

	/**
	 * Check if user is verified.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True if verified, false otherwise
	 */
	public static function is_verified( $user_id ) {
		$data = self::get( $user_id );
		/* SECURITY: Use strict comparison to prevent type juggling attacks */
		return $data && (int) $data->is_verified === 1;
	}

	/**
	 * Check if user is disabled.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True if disabled, false otherwise
	 */
	public static function is_disabled( $user_id ) {
		$data = self::get( $user_id );
		/* SECURITY: Use strict comparison to prevent type juggling attacks */
		return $data && (int) $data->is_disabled === 1;
	}

	/**
	 * Get user's expiration date.
	 *
	 * @since    1.0.0
	 * @param    int            $user_id    User ID
	 * @return   string|null                Expiration datetime or null
	 */
	public static function get_expiration( $user_id ) {
		$data = self::get( $user_id );
		return $data ? $data->expires_at : null;
	}

	/**
	 * Check if user account is expired.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True if expired, false otherwise
	 */
	public static function is_expired( $user_id ) {
		$expires_at = self::get_expiration( $user_id );
		if ( ! $expires_at ) {
			return false;
		}
		/* FIXED: Use consistent UTC time for comparison (database stores GMT/UTC) */
		return strtotime( $expires_at ) <= time();
	}

	/**
	 * Set user as verified.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function set_verified( $user_id ) {
		return self::update( $user_id, array(
			'is_verified'   => 1,
			'verified_date' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Set user as unverified.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function set_unverified( $user_id ) {
		return self::update( $user_id, array(
			'is_verified'   => 0,
			'verified_date' => null,
		) );
	}

	/**
	 * Set user as disabled.
	 *
	 * @since    1.0.0
	 * @param    int        $user_id    User ID
	 * @param    string     $reason     Reason for disable (manual, expired, etc.)
	 * @return   bool                   True on success, false on failure
	 */
	public static function set_disabled( $user_id, $reason = 'manual' ) {
		/* Log account disabled */
		NBUF_Audit_Log::log(
			$user_id,
			'account_disabled',
			'success',
			'User account disabled',
			array( 'reason' => $reason )
		);

		return self::update( $user_id, array(
			'is_disabled'     => 1,
			'disabled_reason' => $reason,
		) );
	}

	/**
	 * Set user as enabled.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function set_enabled( $user_id ) {
		/* Log account enabled */
		NBUF_Audit_Log::log(
			$user_id,
			'account_enabled',
			'success',
			'User account enabled'
		);

		return self::update( $user_id, array(
			'is_disabled'     => 0,
			'disabled_reason' => null,
		) );
	}

	/**
	 * Set user expiration date.
	 *
	 * @since    1.0.0
	 * @param    int        $user_id      User ID
	 * @param    string     $expires_at   Expiration datetime (MySQL format) or null to remove
	 * @return   bool                     True on success, false on failure
	 */
	public static function set_expiration( $user_id, $expires_at ) {
		$data = array( 'expires_at' => $expires_at );

		// If removing expiration and user is expired, auto-enable
		if ( $expires_at === null && self::is_expired( $user_id ) ) {
			$user_data = self::get( $user_id );
			if ( $user_data && $user_data->disabled_reason === 'expired' ) {
				$data['is_disabled']     = 0;
				$data['disabled_reason'] = null;
			}
		}

		// Reset warning flag when expiration changes
		$data['expiration_warned_at'] = null;

		return self::update( $user_id, $data );
	}

	/**
	 * Mark that expiration warning was sent.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function set_expiration_warned( $user_id ) {
		return self::update( $user_id, array(
			'expiration_warned_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Update user data in table.
	 *
	 * @since    1.0.0
	 * @param    int      $user_id    User ID
	 * @param    array    $data       Data to update (column => value)
	 * @return   bool                 True on success, false on failure
	 */
	public static function update( $user_id, $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		// Check if record exists
		$exists = self::get( $user_id );

		if ( $exists ) {
			// Update existing record
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'user_id' => $user_id ),
				null,
				array( '%d' )
			);
		} else {
			// Insert new record
			$data['user_id'] = $user_id;
			$result = $wpdb->insert( $table_name, $data );
		}

		/* Invalidate unified user cache */
		if ( $result !== false && class_exists( 'NBUF_User' ) ) {
			NBUF_User::invalidate_cache( $user_id, 'user_data' );
		}

		return $result !== false;
	}

	/**
	 * Delete user data from table.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function delete( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get all users with expiration before given date.
	 *
	 * @since    1.0.0
	 * @param    string    $before_date    MySQL datetime
	 * @return   array                     Array of user IDs
	 */
	public static function get_expiring_before( $before_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM $table_name
				WHERE expires_at IS NOT NULL
				AND expires_at != '0000-00-00 00:00:00'
				AND expires_at <= %s
				AND is_disabled = 0",
				$before_date
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get users needing expiration warning.
	 *
	 * @since    1.0.0
	 * @param    string    $warning_date    MySQL datetime for warning threshold
	 * @return   array                      Array of user IDs
	 */
	public static function get_needing_warning( $warning_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM $table_name
				WHERE expires_at IS NOT NULL
				AND expires_at != '0000-00-00 00:00:00'
				AND expires_at <= %s
				AND (expiration_warned_at IS NULL OR expiration_warned_at = '0000-00-00 00:00:00')
				AND is_disabled = 0",
				$warning_date
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get user count by filters.
	 *
	 * @since    1.0.0
	 * @param    string    $filter    Filter type: 'verified', 'unverified', 'disabled', 'enabled', 'has_expiration', 'no_expiration', 'expired'
	 * @return   int                  User count
	 */
	public static function get_count( $filter = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';
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
				$current_time = current_time( 'mysql' );
				$where = $wpdb->prepare(
					"AND d.expires_at IS NOT NULL AND d.expires_at != '0000-00-00 00:00:00' AND d.expires_at <= %s",
					$current_time
				);
				break;
		}

		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT u.ID)
			FROM $users_table u
			LEFT JOIN $table_name d ON u.ID = d.user_id
			WHERE 1=1 $where"
		);

		return (int) $count;
	}

	/**
	 * Get weak password flagged timestamp.
	 *
	 * @since    1.0.0
	 * @param    int            $user_id    User ID
	 * @return   string|null                Flagged datetime or null
	 */
	public static function get_weak_password_flagged_at( $user_id ) {
		$data = self::get( $user_id );
		return $data ? $data->weak_password_flagged_at : null;
	}

	/**
	 * Get password changed timestamp.
	 *
	 * @since    1.0.0
	 * @param    int            $user_id    User ID
	 * @return   string|null                Changed datetime or null
	 */
	public static function get_password_changed_at( $user_id ) {
		$data = self::get( $user_id );
		return $data ? $data->password_changed_at : null;
	}

	/**
	 * Flag user password as weak.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function flag_weak_password( $user_id ) {
		return self::update( $user_id, array(
			'weak_password_flagged_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Clear weak password flag.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function clear_weak_password_flag( $user_id ) {
		return self::update( $user_id, array(
			'weak_password_flagged_at' => null,
		) );
	}

	/**
	 * Set password changed timestamp.
	 *
	 * @since    1.0.0
	 * @param    int     $user_id    User ID
	 * @return   bool                True on success, false on failure
	 */
	public static function set_password_changed( $user_id ) {
		return self::update( $user_id, array(
			'password_changed_at'      => current_time( 'mysql' ),
			'weak_password_flagged_at' => null, // Clear flag when password changes
		) );
	}
}
