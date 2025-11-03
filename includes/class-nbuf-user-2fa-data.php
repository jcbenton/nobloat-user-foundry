<?php
/**
 * User 2FA Data Management
 *
 * Handles all two-factor authentication data stored in custom table.
 * Replaces wp_usermeta approach with performant table structure.
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
 * Direct database access is architectural for 2FA data management.
 * Custom nbuf_user_2fa table stores security-sensitive authentication data and cannot use
 * WordPress's standard meta APIs. Caching is not implemented for security reasons.
 */

/**
 * User 2FA data management class.
 *
 * Provides interface for reading/writing user 2FA settings and data.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @author     NoBloat
 */
class NBUF_User_2FA_Data {


	/**
	 * Get user 2FA data from custom table.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return object|null         User 2FA data object or null if not found
	 */
	public static function get( int $user_id ): ?object {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_2fa';

     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $data;
	}

	/**
	 * Check if 2FA is enabled for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if enabled, false otherwise
	 */
	public static function is_enabled( int $user_id ): bool {
		$data = self::get( $user_id );
		return $data && 1 === $data->enabled;
	}

	/**
	 * Get 2FA method for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Method ('email', 'totp') or null
	 */
	public static function get_method( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->method : null;
	}

	/**
	 * Get TOTP secret for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                TOTP secret or null
	 */
	public static function get_totp_secret( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->totp_secret : null;
	}

	/**
	 * Get backup codes for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return array                Array of backup codes (empty if none)
	 */
	public static function get_backup_codes( int $user_id ): array {
		$data = self::get( $user_id );
		if ( ! $data || empty( $data->backup_codes ) ) {
			return array();
		}

		$codes = json_decode( $data->backup_codes, true );
		return is_array( $codes ) ? $codes : array();
	}

	/**
	 * Get used backup code indexes for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return array                Array of used indexes (empty if none)
	 */
	public static function get_backup_codes_used( int $user_id ): array {
		$data = self::get( $user_id );
		if ( ! $data || empty( $data->backup_codes_used ) ) {
			return array();
		}

		$used = json_decode( $data->backup_codes_used, true );
		return is_array( $used ) ? $used : array();
	}

	/**
	 * Get trusted devices for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return array                Array of trusted devices (empty if none)
	 */
	public static function get_trusted_devices( int $user_id ): array {
		$data = self::get( $user_id );
		if ( ! $data || empty( $data->trusted_devices ) ) {
			return array();
		}

		$devices = json_decode( $data->trusted_devices, true );
		return is_array( $devices ) ? $devices : array();
	}

	/**
	 * Get last used timestamp for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Last used datetime or null
	 */
	public static function get_last_used( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->last_used : null;
	}

	/**
	 * Get forced_at timestamp for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string|null                Forced at datetime or null
	 */
	public static function get_forced_at( int $user_id ): ?string {
		$data = self::get( $user_id );
		return $data ? $data->forced_at : null;
	}

	/**
	 * Check if 2FA setup is completed.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if completed, false otherwise
	 */
	public static function is_setup_completed( int $user_id ): bool {
		$data = self::get( $user_id );
		return $data && 1 === $data->setup_completed;
	}

	/**
	 * Enable 2FA for user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id     User ID.
	 * @param  string $method      Method ('email' or 'totp').
	 * @param  string $totp_secret TOTP secret (required if method is 'totp').
	 * @return bool                      True on success, false on failure
	 */
	public static function enable( int $user_id, string $method, ?string $totp_secret = null ): bool {
		$data = array(
			'enabled'         => 1,
			'method'          => $method,
			'setup_completed' => 1,
		);

		if ( 'totp' === $method && $totp_secret ) {
			$data['totp_secret'] = $totp_secret;
		}

		return self::update( $user_id, $data );
	}

	/**
	 * Disable 2FA for user (deletes all 2FA data).
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure
	 */
	public static function disable( int $user_id ): bool {
		return self::delete( $user_id );
	}

	/**
	 * Set TOTP secret for user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $secret  TOTP secret.
	 * @return bool                   True on success, false on failure
	 */
	public static function set_totp_secret( int $user_id, string $secret ): bool {
		return self::update(
			$user_id,
			array(
				'totp_secret' => $secret,
			)
		);
	}

	/**
	 * Set backup codes for user.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id User ID.
	 * @param  array $codes   Array of backup codes.
	 * @return bool                 True on success, false on failure
	 */
	public static function set_backup_codes( int $user_id, array $codes ): bool {
		return self::update(
			$user_id,
			array(
				'backup_codes'      => wp_json_encode( $codes ),
				'backup_codes_used' => wp_json_encode( array() ), // Reset used indexes.
			)
		);
	}

	/**
	 * Mark backup code as used.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @param  int $index   Code index to mark as used.
	 * @return bool                True on success, false on failure
	 */
	public static function mark_backup_code_used( int $user_id, int $index ): bool {
		$used   = self::get_backup_codes_used( $user_id );
		$used[] = $index;

		return self::update(
			$user_id,
			array(
				'backup_codes_used' => wp_json_encode( $used ),
			)
		);
	}

	/**
	 * Add trusted device for user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $token   Device token.
	 * @param  array  $device  Device data.
	 * @return bool                   True on success, false on failure
	 */
	public static function add_trusted_device( int $user_id, string $token, array $device ): bool {
		$devices           = self::get_trusted_devices( $user_id );
		$devices[ $token ] = $device;

		return self::update(
			$user_id,
			array(
				'trusted_devices' => wp_json_encode( $devices ),
			)
		);
	}

	/**
	 * Remove trusted device for user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $token   Device token to remove.
	 * @return bool                   True on success, false on failure
	 */
	public static function remove_trusted_device( int $user_id, string $token ): bool {
		$devices = self::get_trusted_devices( $user_id );
		if ( isset( $devices[ $token ] ) ) {
			unset( $devices[ $token ] );
		}

		return self::update(
			$user_id,
			array(
				'trusted_devices' => wp_json_encode( $devices ),
			)
		);
	}

	/**
	 * Set last used timestamp.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure
	 */
	public static function set_last_used( int $user_id ): bool {
		return self::update(
			$user_id,
			array(
				'last_used' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Set forced_at timestamp.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id   User ID.
	 * @param  string $forced_at Datetime or null to clear.
	 * @return bool                      True on success, false on failure
	 */
	public static function set_forced_at( int $user_id, ?string $forced_at ): bool {
		return self::update(
			$user_id,
			array(
				'forced_at' => $forced_at,
			)
		);
	}

	/**
	 * Update user 2FA data in table.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id User ID.
	 * @param  array $data    Data to update (column => value).
	 * @return bool                 True on success, false on failure
	 */
	public static function update( int $user_id, array $data ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_2fa';

		// Check if record exists.
		$exists = self::get( $user_id );

		/* Detect 2FA enable/disable events for audit logging. */
		if ( isset( $data['enabled'] ) ) {
			$was_enabled = $exists && 1 === $exists->enabled;
			$now_enabled = 1 === $data['enabled'];

			if ( ! $was_enabled && $now_enabled ) {
				/* 2FA being enabled */
				$method = isset( $data['method'] ) ? $data['method'] : ( $exists ? $exists->method : 'unknown' );
				NBUF_Audit_Log::log(
					$user_id,
					'2fa_enabled',
					'success',
					'Two-factor authentication enabled',
					array( 'method' => $method )
				);
			} elseif ( $was_enabled && ! $now_enabled ) {
				/* 2FA being disabled */
				$method = $exists ? $exists->method : 'unknown';
				NBUF_Audit_Log::log(
					$user_id,
					'2fa_disabled',
					'success',
					'Two-factor authentication disabled',
					array( 'method' => $method )
				);
			}
		}

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
			NBUF_User::invalidate_cache( $user_id, '2fa' );
		}

		return false !== $result;
	}

	/**
	 * Delete user 2FA data from table.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure
	 */
	public static function delete( int $user_id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_2fa';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get count of users with 2FA enabled.
	 *
	 * @since  1.0.0
	 * @param  string $method Optional method filter ('email', 'totp').
	 * @return int                  User count
	 */
	public static function get_count( $method = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_2fa';

     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $method ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE enabled = 1 AND method = %s",
					$method
				)
			);
		} else {
			$count = $wpdb->get_var(
				"SELECT COUNT(*) FROM $table_name WHERE enabled = 1"
			);
		}
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
