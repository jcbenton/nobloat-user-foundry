<?php
/**
 * Custom Options Management
 *
 * Isolates plugin options from WordPress wp_options table.
 * Only loads data when plugin is actively being used.
 *
 * @package NoBloat_User_Foundry
 * @since   1.0.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for custom options storage.
 * Custom nbuf_options table eliminates wp_options bloat and cannot use
 * WordPress's standard options API. Caching is implemented at application
 * level with wp_cache for optimal performance.
 */

/**
 * Class NBUF_Options
 *
 * Manages plugin options in custom table.
 */
class NBUF_Options {


	/**
	 * In-memory cache for loaded options
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Table name
	 *
	 * @var string
	 */
	private static $table_name = null;

	/**
	 * Initialize table name
	 * Called automatically on first use
	 */
	private static function init(): void {
		if ( null === self::$table_name ) {
			global $wpdb;
			self::$table_name = $wpdb->prefix . 'nbuf_options';
		}
	}

	/**
	 * Get option value
	 *
	 * @param  string $key     Option name.
	 * @param  mixed  $default Default value if not found.
	 * @return mixed Option value or default.
	 */
	public static function get( string $key, $default = false ) {  // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Matches WordPress get_option() convention
		/* Check cache first */
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		global $wpdb;
		self::init();

     // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM ' . self::$table_name . ' WHERE option_name = %s',
				$key
			)
		);
     // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $value ) {
			return $default;
		}

		/* Unserialize if needed and cache */
		$value               = maybe_unserialize( $value );
		self::$cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Update or insert option value
	 *
	 * @param  string $key      Option name.
	 * @param  mixed  $value    Option value.
	 * @param  bool   $autoload Whether to autoload (default: false).
	 * @param  string $group    Option group: 'settings', 'templates', 'css', 'system' (default: 'settings').
	 * @return bool Success.
	 */
	public static function update( string $key, $value, bool $autoload = false, string $group = 'settings' ): bool {
		global $wpdb;
		self::init();

		$serialized_value = maybe_serialize( $value );

		$result = $wpdb->replace(
			self::$table_name,
			array(
				'option_name'  => $key,
				'option_value' => $serialized_value,
				'autoload'     => $autoload ? 1 : 0,
				'option_group' => $group,
			),
			array( '%s', '%s', '%d', '%s' )
		);

		/* Update cache */
		if ( false !== $result ) {
			self::$cache[ $key ] = $value;
		}

		return false !== $result;
	}

	/**
	 * Delete option
	 *
	 * @param  string $key Option name.
	 * @return bool Success.
	 */
	public static function delete( string $key ): bool {
		global $wpdb;
		self::init();

		$result = $wpdb->delete(
			self::$table_name,
			array( 'option_name' => $key ),
			array( '%s' )
		);

		/* Clear from cache */
		unset( self::$cache[ $key ] );

		return false !== $result;
	}

	/**
	 * Get multiple options at once (batch query)
	 * More efficient than calling get() multiple times
	 *
	 * @param  array $keys Array of option names.
	 * @return array Associative array of option values (key => value).
	 */
	public static function get_multiple( array $keys ): array {
		if ( empty( $keys ) ) {
			return array();
		}

		global $wpdb;
		self::init();

		/* Build placeholders for IN clause */
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

     // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name, option_value FROM ' . self::$table_name . " WHERE option_name IN ($placeholders)",
				...$keys
			)
		);
     // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$options = array();
		foreach ( $results as $row ) {
			$value                            = maybe_unserialize( $row->option_value );
			$options[ $row->option_name ]     = $value;
			self::$cache[ $row->option_name ] = $value;
		}

		return $options;
	}

	/**
	 * Get all options in a specific group
	 * Useful for loading all settings or all templates at once
	 *
	 * @param  string $group Option group: 'settings', 'templates', 'css', 'system'.
	 * @return array Associative array of option values (key => value).
	 */
	public static function get_group( string $group ): array {
		global $wpdb;
		self::init();

     // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name, option_value FROM ' . self::$table_name . ' WHERE option_group = %s',
				$group
			)
		);
     // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$options = array();
		foreach ( $results as $row ) {
			$value                            = maybe_unserialize( $row->option_value );
			$options[ $row->option_name ]     = $value;
			self::$cache[ $row->option_name ] = $value;
		}

		return $options;
	}

	/**
	 * Preload autoload options into cache
	 * Call this when plugin initializes (only if plugin is being used)
	 *
	 * This mimics WordPress autoload behavior but only for our plugin
	 * and only when the plugin is actually being used on the page.
	 */
	public static function preload_autoload(): void {
		global $wpdb;
		self::init();

     // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		// Table name from class property, no user input in query.
		$results = $wpdb->get_results(
			'SELECT option_name, option_value FROM ' . self::$table_name . ' WHERE autoload = 1'
		);
     // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $results as $row ) {
			self::$cache[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}
	}

	/**
	 * Clear the in-memory cache
	 * Useful for testing or after bulk operations
	 */
	public static function clear_cache(): void {
		self::$cache = array();
	}

	/**
	 * Get cache statistics (for debugging)
	 *
	 * @return array Cache stats.
	 */
	public static function get_cache_stats(): array {
		return array(
			'cached_keys'      => array_keys( self::$cache ),
			'cache_count'      => count( self::$cache ),
			'cache_size_bytes' => strlen( serialize( self::$cache ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Debugging function only, safe usage.
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
