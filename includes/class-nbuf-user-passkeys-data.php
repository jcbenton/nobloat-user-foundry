<?php
/**
 * User Passkeys Data Management
 *
 * Handles all WebAuthn passkey data stored in custom table.
 * Provides CRUD operations for passkey credentials.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for passkey data management.
 * Custom nbuf_user_passkeys table stores security-sensitive WebAuthn credentials
 * and cannot use WordPress's standard meta APIs. Caching is not implemented
 * for security reasons.
 */

/**
 * User Passkeys data management class.
 *
 * Provides interface for reading/writing user passkey credentials.
 *
 * @since      1.5.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */
class NBUF_User_Passkeys_Data {


	/**
	 * Get all passkeys for a user.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return array Array of passkey objects (empty if none).
	 */
	public static function get_all( int $user_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$passkeys = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC',
				$table_name,
				$user_id
			)
		);

		return is_array( $passkeys ) ? $passkeys : array();
	}

	/**
	 * Get a specific passkey by ID.
	 *
	 * @since  1.5.0
	 * @param  int $id Passkey ID.
	 * @return object|null Passkey object or null if not found.
	 */
	public static function get_by_id( int $id ): ?object {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$id
			)
		);
	}

	/**
	 * Get passkey by credential ID.
	 *
	 * Credential IDs are stored as binary for efficient lookup.
	 *
	 * @since  1.5.0
	 * @param  string $credential_id Raw binary credential ID.
	 * @return object|null Passkey object or null if not found.
	 */
	public static function get_by_credential_id( string $credential_id ): ?object {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE credential_id = %s',
				$table_name,
				$credential_id
			)
		);
	}

	/**
	 * Get count of passkeys for a user.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return int Passkey count.
	 */
	public static function get_count( int $user_id ): int {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		return (int) $count;
	}

	/**
	 * Check if user has any passkeys.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return bool True if user has passkeys, false otherwise.
	 */
	public static function has_passkeys( int $user_id ): bool {
		return self::get_count( $user_id ) > 0;
	}

	/**
	 * Create a new passkey record.
	 *
	 * @since  1.5.0
	 * @param  int   $user_id User ID.
	 * @param  array $data    Passkey data with keys:
	 *                        - credential_id: Raw binary credential ID (required).
	 *                        - public_key: COSE public key blob (required).
	 *                        - sign_count: Initial sign count (optional, default 0).
	 *                        - transports: JSON string of transports (optional).
	 *                        - aaguid: 16-byte AAGUID (optional).
	 *                        - device_name: User-friendly device name (optional).
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( int $user_id, array $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		/* Validate required fields */
		if ( empty( $data['credential_id'] ) || empty( $data['public_key'] ) ) {
			return false;
		}

		/* Check max passkeys limit */
		$max_passkeys = (int) NBUF_Options::get( 'nbuf_passkeys_max_per_user', 10 );
		if ( self::get_count( $user_id ) >= $max_passkeys ) {
			return false;
		}

		/* Check for duplicate credential_id */
		if ( self::get_by_credential_id( $data['credential_id'] ) ) {
			return false;
		}

		$insert_data = array(
			'user_id'       => $user_id,
			'credential_id' => $data['credential_id'],
			'public_key'    => $data['public_key'],
			'sign_count'    => isset( $data['sign_count'] ) ? (int) $data['sign_count'] : 0,
			'transports'    => isset( $data['transports'] ) ? $data['transports'] : null,
			'aaguid'        => isset( $data['aaguid'] ) ? $data['aaguid'] : null,
			'device_name'   => isset( $data['device_name'] ) ? sanitize_text_field( $data['device_name'] ) : null,
			'created_at'    => current_time( 'mysql', true ),
		);

		$result = $wpdb->insert(
			$table_name,
			$insert_data,
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$passkey_id = $wpdb->insert_id;

		/* Log passkey registration */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'passkey_registered',
				'success',
				'Passkey registered',
				array(
					'passkey_id'  => $passkey_id,
					'device_name' => $insert_data['device_name'],
				)
			);
		}

		/* Invalidate unified user cache */
		if ( class_exists( 'NBUF_User' ) ) {
			NBUF_User::invalidate_cache( $user_id, 'passkeys' );
		}

		return $passkey_id;
	}

	/**
	 * Update sign count after successful authentication.
	 *
	 * Sign count is used to detect cloned authenticators.
	 *
	 * @since  1.5.0
	 * @param  int $id         Passkey ID.
	 * @param  int $sign_count New sign count from authenticator.
	 * @return bool True on success, false on failure.
	 */
	public static function update_sign_count( int $id, int $sign_count ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$result = $wpdb->update(
			$table_name,
			array(
				'sign_count' => $sign_count,
				'last_used'  => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update last used timestamp.
	 *
	 * @since  1.5.0
	 * @param  int $id Passkey ID.
	 * @return bool True on success, false on failure.
	 */
	public static function update_last_used( int $id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$result = $wpdb->update(
			$table_name,
			array( 'last_used' => current_time( 'mysql', true ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update device name.
	 *
	 * @since  1.5.0
	 * @param  int    $id          Passkey ID.
	 * @param  string $device_name New device name.
	 * @return bool True on success, false on failure.
	 */
	public static function update_device_name( int $id, string $device_name ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$result = $wpdb->update(
			$table_name,
			array( 'device_name' => sanitize_text_field( $device_name ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a passkey.
	 *
	 * @since  1.5.0
	 * @param  int $id Passkey ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		/* Get passkey data for logging */
		$passkey = self::get_by_id( $id );
		if ( ! $passkey ) {
			return false;
		}

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			/* Log passkey deletion */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::log(
					$passkey->user_id,
					'passkey_deleted',
					'success',
					'Passkey deleted',
					array(
						'passkey_id'  => $id,
						'device_name' => $passkey->device_name,
					)
				);
			}

			/* Invalidate unified user cache */
			if ( class_exists( 'NBUF_User' ) ) {
				NBUF_User::invalidate_cache( $passkey->user_id, 'passkeys' );
			}
		}

		return false !== $result;
	}

	/**
	 * Delete all passkeys for a user.
	 *
	 * Used during user deletion or account cleanup.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_all( int $user_id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$count = self::get_count( $user_id );

		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		if ( false !== $result && $count > 0 ) {
			/* Log bulk passkey deletion */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::log(
					$user_id,
					'passkeys_deleted_all',
					'success',
					'All passkeys deleted',
					array( 'count' => $count )
				);
			}

			/* Invalidate unified user cache */
			if ( class_exists( 'NBUF_User' ) ) {
				NBUF_User::invalidate_cache( $user_id, 'passkeys' );
			}
		}

		return false !== $result;
	}

	/**
	 * Get credential IDs for a user.
	 *
	 * Used during authentication to provide allowed credentials.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return array Array of credential IDs (raw binary).
	 */
	public static function get_credential_ids( int $user_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT credential_id FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get total count of all passkeys in system.
	 *
	 * For admin statistics.
	 *
	 * @since  1.5.0
	 * @return int Total passkey count.
	 */
	public static function get_total_count(): int {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$table_name
			)
		);

		return (int) $count;
	}

	/**
	 * Get count of users with passkeys.
	 *
	 * For admin statistics.
	 *
	 * @since  1.5.0
	 * @return int User count.
	 */
	public static function get_users_with_passkeys_count(): int {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_passkeys';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id) FROM %i',
				$table_name
			)
		);

		return (int) $count;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
