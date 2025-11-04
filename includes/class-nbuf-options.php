<?php
/**
 * Custom Options Management
 *
 * Isolates plugin options from WordPress wp_options table.
 * Only loads data when plugin is actively being used.
 *
 * PERFORMANCE: Loads ALL options in single query on first access.
 * Caches in Redis/Memcached for 1 hour (0 DB queries on subsequent pageloads).
 * Falls back gracefully if no persistent cache installed.
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
	 * Object cache group for Redis/Memcached
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'nbuf_options';

	/**
	 * Cache expiration time (1 hour)
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * In-memory cache for loaded options
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Flag to track if all options have been loaded
	 *
	 * @var bool
	 */
	private static $all_options_loaded = false;

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
	 * Load all options in ONE query with Redis/Memcached support
	 *
	 * PERFORMANCE OPTIMIZATION:
	 * - Try Redis/Memcached first (0 DB queries if cached)
	 * - Fall back to single SELECT query for ALL options (~100 options in ~10KB)
	 * - Cache in Redis for 1 hour (subsequent pageloads = 0 queries)
	 * - More efficient than 20+ individual queries
	 *
	 * Redis Integration:
	 * - wp_cache_get() automatically uses Redis if Redis Object Cache plugin installed
	 * - Falls back to in-memory cache if no persistent cache available
	 * - Zero configuration needed - WordPress handles everything
	 *
	 * @return void
	 */
	private static function load_all_options(): void {
		global $wpdb;
		self::init();

		/*
		 * PERFORMANCE: Try object cache first (Redis/Memcached if installed)
		 * wp_cache_get() automatically uses Redis if Redis Object Cache plugin is active
		 * Falls back to in-memory cache if no persistent cache installed
		 */
		$cached = wp_cache_get( 'all_options', self::CACHE_GROUP );

		if ( false !== $cached && is_array( $cached ) ) {
			/* Cache HIT - load from Redis/Memcached (0 DB queries) */
			self::$cache              = $cached;
			self::$all_options_loaded = true;
			return;
		}

		/*
		 * Cache MISS - query database for ALL options
		 * Single query loads entire options table (~100 options in ~10KB)
		 * More efficient than 20+ individual queries per pageload
		 */
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT option_name, option_value FROM %i', self::$table_name ),
			OBJECT
		);

		/* Build cache array */
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				self::$cache[ $row->option_name ] = maybe_unserialize( $row->option_value );
			}
		}

		/*
		 * Store in object cache for next pageload
		 * wp_cache_set() automatically uses Redis if plugin active
		 * If no Redis: data stored in PHP memory (discarded after pageload)
		 * With Redis: data persists for 1 hour across all pageloads
		 */
		wp_cache_set( 'all_options', self::$cache, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		self::$all_options_loaded = true;
	}

	/**
	 * Get option value
	 *
	 * PERFORMANCE: Loads ALL options on first access using single query + Redis cache.
	 * Subsequent calls return from in-memory cache (0 queries).
	 *
	 * @param  string $key     Option name.
	 * @param  mixed  $default Default value if not found.
	 * @return mixed Option value or default.
	 */
	public static function get( string $key, $default = false ) {  // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Matches WordPress get_option() convention
		/* Load ALL options on first access */
		if ( ! self::$all_options_loaded ) {
			self::load_all_options();
		}

		/* Return from cache */
		return self::$cache[ $key ] ?? $default;
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

		/*
		 * Invalidate cache on update
		 * wp_cache_delete() removes from Redis if installed
		 * Next pageload will reload fresh data from database
		 */
		if ( false !== $result ) {
			/* Clear object cache (Redis/Memcached) */
			wp_cache_delete( 'all_options', self::CACHE_GROUP );

			/* Clear in-memory cache for current pageload */
			self::$all_options_loaded = false;
			self::$cache              = array();
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

		/*
		 * Invalidate cache on delete
		 * wp_cache_delete() removes from Redis if installed
		 */
		if ( false !== $result ) {
			/* Clear object cache (Redis/Memcached) */
			wp_cache_delete( 'all_options', self::CACHE_GROUP );

			/* Clear in-memory cache for current pageload */
			self::$all_options_loaded = false;
			self::$cache              = array();
		}

		return false !== $result;
	}

	/**
	 * Check if option exists
	 *
	 * @param  string $key Option name.
	 * @return bool True if exists.
	 */
	public static function exists( string $key ): bool {
		/* Load all options if not loaded */
		if ( ! self::$all_options_loaded ) {
			self::load_all_options();
		}

		return isset( self::$cache[ $key ] );
	}

	/**
	 * Get all options
	 *
	 * @return array All options.
	 */
	public static function get_all(): array {
		/* Load all options if not loaded */
		if ( ! self::$all_options_loaded ) {
			self::load_all_options();
		}

		return self::$cache;
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
	 * Clear the in-memory cache and Redis cache
	 * Useful for testing or after bulk operations
	 */
	public static function clear_cache(): void {
		/* Clear object cache (Redis/Memcached) */
		wp_cache_delete( 'all_options', self::CACHE_GROUP );

		/* Clear in-memory cache */
		self::$all_options_loaded = false;
		self::$cache              = array();
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
