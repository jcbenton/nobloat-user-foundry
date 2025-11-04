<?php
/**
 * NoBloat User Foundry - Transient Key Helper
 *
 * Provides consistent naming for transient keys across the plugin.
 * Prevents typos and makes cleanup easier.
 *
 * @package    NoBloat_User_Foundry.
 * @subpackage NoBloat_User_Foundry/includes.
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Transients
 *
 * Manages transient caching for plugin data.
 */
class NBUF_Transients {


	/**
	 * Plugin prefix for all transients
	 *
	 * @var string
	 */
	const PREFIX = 'nbuf_';

	/**
	 * Maximum transient key length
	 *
	 * WordPress option_name limit is 191 characters.
	 * Minus _transient_ prefix (11 chars) and _timeout_ prefix (19 chars) = 172 chars max.
	 *
	 * @var int
	 */
	const MAX_KEY_LENGTH = 172;

	/**
	 * Get transient key with consistent naming
	 *
	 * Ensures all plugin transients have consistent naming pattern:
	 * nbuf_{category}_{identifier}
	 *
	 * Examples:
	 * - get_key('2fa_email_code', 123) => 'nbuf_2fa_email_code_123'
	 * - get_key('bulk_import', 'batch_456') => 'nbuf_bulk_import_batch_456'
	 * - get_key('password_reset', 123) => 'nbuf_password_reset_123'
	 *
	 * @param  string $category   Category/type of transient (e.g., '2fa_email_code', 'rate_limit').
	 * @param  string $identifier Unique identifier (usually user ID or batch ID).
	 * @return string Properly formatted transient key.
	 */
	public static function get_key( string $category, $identifier = '' ): string {
		$key = self::PREFIX . $category;

		if ( '' !== $identifier ) {
			$key .= '_' . $identifier;
		}

		/* Sanitize key to prevent issues */
		$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );

		/* Check if key exceeds maximum length */
		if ( strlen( $key ) > self::MAX_KEY_LENGTH ) {
			/* Hash long keys to keep them under limit */
			$hash = md5( $key );
			$key  = self::PREFIX . substr( $category, 0, 50 ) . '_' . $hash;
		}

		return $key;
	}

	/**
	 * Set a transient with automatic key formatting
	 *
	 * @param  string $category   Category/type of transient.
	 * @param  string $identifier Unique identifier.
	 * @param  mixed  $value      Value to store.
	 * @param  int    $expiration Expiration in seconds (default 0 = no expiration).
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $category, $identifier, $value, int $expiration = 0 ): bool {
		$key = self::get_key( $category, $identifier );
		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Get a transient with automatic key formatting
	 *
	 * @param  string $category   Category/type of transient.
	 * @param  string $identifier Unique identifier.
	 * @return mixed Transient value or false if not found/expired.
	 */
	public static function get( string $category, $identifier = '' ) {
		$key = self::get_key( $category, $identifier );
		return get_transient( $key );
	}

	/**
	 * Delete a transient with automatic key formatting
	 *
	 * @param  string $category   Category/type of transient.
	 * @param  string $identifier Unique identifier.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $category, $identifier = '' ): bool {
		$key = self::get_key( $category, $identifier );
		return delete_transient( $key );
	}

	/**
	 * Atomically increment a transient counter
	 *
	 * Uses database-level UPDATE with WHERE clause to prevent race conditions.
	 * If transient doesn't exist, creates it with initial value.
	 *
	 * @param  string $category   Category/type of transient.
	 * @param  string $identifier Unique identifier.
	 * @param  int    $increment  Amount to increment by (default 1).
	 * @param  int    $expiration Expiration in seconds (default 0 = no expiration).
	 * @return int|false New counter value on success, false on failure.
	 */
	public static function increment( string $category, $identifier, int $increment = 1, int $expiration = 0 ) {
		global $wpdb;

		$key         = self::get_key( $category, $identifier );
		$option_name = '_transient_' . $key;
		$timeout_key = '_transient_timeout_' . $key;

		/* Try to get current value */
		$current = get_transient( $key );

		if ( false === $current ) {
			/* Transient doesn't exist - try to create it atomically */
			$success = add_option( $option_name, $increment, '', 'no' );

			if ( $success && $expiration > 0 ) {
				add_option( $timeout_key, time() + $expiration, '', 'no' );
			}

			return $success ? $increment : false;
		}

		/* Transient exists - increment atomically using database UPDATE */
		$new_value = intval( $current ) + $increment;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment operation requires direct query.
		$updated = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => $new_value ),
			array(
				'option_name'  => $option_name,
				'option_value' => $current, // WHERE clause ensures we only update if value hasn't changed.
			),
			array( '%d' ),
			array( '%s', '%d' )
		);

		if ( $updated ) {
			/* Update timeout if expiration specified */
			if ( $expiration > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic operation for transient timeout.
				$wpdb->replace(
					$wpdb->options,
					array(
						'option_name'  => $timeout_key,
						'option_value' => time() + $expiration,
						'autoload'     => 'no',
					),
					array( '%s', '%d', '%s' )
				);
			}

			return $new_value;
		}

		/* Update failed (value changed by another process) - retry once */
		return self::increment( $category, $identifier, $increment, $expiration );
	}

	/**
	 * Delete all plugin transients (useful for cleanup/debugging)
	 *
	 * @return int Number of transients deleted.
	 */
	public static function delete_all(): int {
		global $wpdb;

		/*
		 * Delete all transient timeouts
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom transient cleanup for nbuf_ prefixed transients.
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_timeout_nbuf_%'"
		);

		/*
		 * Delete all transient values
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom transient value cleanup for nbuf_ prefixed transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_nbuf_%'"
		);

		return (int) $deleted;
	}

	/**
	 * Get list of all active plugin transients (for debugging)
	 *
	 * @return array List of transient keys currently in database.
	 */
	public static function get_all_keys(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		$keys = $wpdb->get_col(
			"SELECT option_name
			 FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_nbuf_%'
			 AND option_name NOT LIKE '_transient_timeout_%'"
		);

		/* Strip '_transient_' prefix */
		return array_map(
			function ( $key ) {
				return str_replace( '_transient_', '', $key );
			},
			$keys
		);
	}
}
