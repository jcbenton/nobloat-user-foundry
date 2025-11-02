<?php
/**
 * NoBloat User Foundry - Transient Key Helper
 *
 * Provides consistent naming for transient keys across the plugin.
 * Prevents typos and makes cleanup easier.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Transients {

	/**
	 * Plugin prefix for all transients
	 *
	 * @var string
	 */
	const PREFIX = 'nbuf_';

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
	 * @param string $category   Category/type of transient (e.g., '2fa_email_code', 'rate_limit')
	 * @param string $identifier Unique identifier (usually user ID or batch ID)
	 * @return string Properly formatted transient key
	 */
	public static function get_key( $category, $identifier = '' ) {
		$key = self::PREFIX . $category;

		if ( '' !== $identifier ) {
			$key .= '_' . $identifier;
		}

		/* Sanitize key to prevent issues */
		$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );

		/* WordPress transient keys max length is 172 characters (option_name limit is 191, minus prefix) */
		if ( strlen( $key ) > 172 ) {
			/* Hash long keys to keep them under limit */
			$hash = md5( $key );
			$key = self::PREFIX . substr( $category, 0, 50 ) . '_' . $hash;
		}

		return $key;
	}

	/**
	 * Set a transient with automatic key formatting
	 *
	 * @param string $category   Category/type of transient
	 * @param string $identifier Unique identifier
	 * @param mixed  $value      Value to store
	 * @param int    $expiration Expiration in seconds (default 0 = no expiration)
	 * @return bool True on success, false on failure
	 */
	public static function set( $category, $identifier, $value, $expiration = 0 ) {
		$key = self::get_key( $category, $identifier );
		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Get a transient with automatic key formatting
	 *
	 * @param string $category   Category/type of transient
	 * @param string $identifier Unique identifier
	 * @return mixed Transient value or false if not found/expired
	 */
	public static function get( $category, $identifier = '' ) {
		$key = self::get_key( $category, $identifier );
		return get_transient( $key );
	}

	/**
	 * Delete a transient with automatic key formatting
	 *
	 * @param string $category   Category/type of transient
	 * @param string $identifier Unique identifier
	 * @return bool True on success, false on failure
	 */
	public static function delete( $category, $identifier = '' ) {
		$key = self::get_key( $category, $identifier );
		return delete_transient( $key );
	}

	/**
	 * Delete all plugin transients (useful for cleanup/debugging)
	 *
	 * @return int Number of transients deleted
	 */
	public static function delete_all() {
		global $wpdb;

		/* Delete all transient timeouts */
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_timeout_nbuf_%'"
		);

		/* Delete all transient values */
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_nbuf_%'"
		);

		return (int) $deleted;
	}

	/**
	 * Get list of all active plugin transients (for debugging)
	 *
	 * @return array List of transient keys currently in database
	 */
	public static function get_all_keys() {
		global $wpdb;

		$keys = $wpdb->get_col(
			"SELECT option_name
			 FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_nbuf_%'
			 AND option_name NOT LIKE '_transient_timeout_%'"
		);

		/* Strip '_transient_' prefix */
		return array_map( function( $key ) {
			return str_replace( '_transient_', '', $key );
		}, $keys );
	}
}
