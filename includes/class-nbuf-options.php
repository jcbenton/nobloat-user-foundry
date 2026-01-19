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

		/* Get old value for audit logging before update */
		$old_value = self::get( $key );

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

			/* Log critical setting changes to admin audit log */
			self::maybe_log_setting_change( $key, $old_value, $value );

			/**
			 * Fire action hook when option is updated.
			 *
			 * Mimics WordPress's update_option_{$option} hook but for custom options table.
			 * Only fires if value actually changed.
			 *
			 * @param mixed  $old_value Previous option value.
			 * @param mixed  $value     New option value.
			 * @param string $key       Option name.
			 */
			if ( $old_value !== $value ) {
				do_action( 'nbuf_update_option_' . $key, $old_value, $value, $key );
			}
		}

		return false !== $result;
	}

	/**
	 * Log critical setting changes to admin audit log.
	 *
	 * @param string $key       Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	private static function maybe_log_setting_change( string $key, $old_value, $new_value ): void {
		/* Skip if values are the same */
		if ( $old_value === $new_value ) {
			return;
		}

		/* Skip if not in admin or no user logged in */
		$admin_id = get_current_user_id();
		if ( ! $admin_id || ! is_admin() ) {
			return;
		}

		/* Skip if admin audit log class doesn't exist */
		if ( ! class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			return;
		}

		/* Define critical settings to log */
		$critical_settings = array(
			'nbuf_user_manager_enabled'        => 'Master Toggle',
			'nbuf_require_verification'        => 'Email Verification Required',
			'nbuf_enable_login_limiting'       => 'Login Limiting',
			'nbuf_login_max_attempts'          => 'Login Max Attempts',
			'nbuf_login_lockout_duration'      => 'Login Lockout Duration',
			'nbuf_2fa_email_method'            => '2FA Email Method',
			'nbuf_2fa_totp_method'             => '2FA TOTP Method',
			'nbuf_2fa_backup_enabled'          => '2FA Backup Codes',
			'nbuf_enable_custom_roles'         => 'Custom Roles',
			'nbuf_audit_log_enabled'           => 'User Audit Logging',
			'nbuf_logging_admin_audit_enabled' => 'Admin Audit Logging',
			'nbuf_security_log_enabled'        => 'Security Logging',
			'nbuf_password_min_length'         => 'Password Minimum Length',
			'nbuf_password_require_uppercase'  => 'Password Require Uppercase',
			'nbuf_password_require_lowercase'  => 'Password Require Lowercase',
			'nbuf_password_require_numbers'    => 'Password Require Numbers',
			'nbuf_password_require_special'    => 'Password Require Special Chars',
		);

		/* Check if this is a critical setting */
		if ( ! isset( $critical_settings[ $key ] ) ) {
			return;
		}

		/* Format values for logging */
		$old_str = is_bool( $old_value ) ? ( $old_value ? 'enabled' : 'disabled' ) : (string) $old_value;
		$new_str = is_bool( $new_value ) ? ( $new_value ? 'enabled' : 'disabled' ) : (string) $new_value;

		/* Log the setting change */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_SETTINGS_CHANGED,
			'success',
			sprintf(
				'Setting "%s" changed from %s to %s',
				$critical_settings[ $key ],
				$old_str,
				$new_str
			),
			null,
			array(
				'setting'   => $key,
				'label'     => $critical_settings[ $key ],
				'old_value' => $old_value,
				'new_value' => $new_value,
			)
		);
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic IN clause with spread operator.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM %i WHERE option_name IN ($placeholders)",
				self::$table_name,
				...$keys
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name, option_value FROM %i WHERE option_group = %s',
				self::$table_name,
				$group
			)
		);

		$options = array();
		foreach ( $results as $row ) {
			$value                            = maybe_unserialize( $row->option_value );
			$options[ $row->option_name ]     = $value;
			self::$cache[ $row->option_name ] = $value;
		}

		return $options;
	}

	/**
	 * Preload options into cache.
	 *
	 * Call this when plugin initializes (only if plugin is being used).
	 * Delegates to load_all_options() to load everything in a single query.
	 * More efficient than loading autoload options separately since
	 * non-autoload options are often needed shortly after.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Optimized to use load_all_options() instead of separate query.
	 */
	public static function preload_autoload(): void {
		/* Load all options in single query (eliminates duplicate query). */
		if ( ! self::$all_options_loaded ) {
			self::load_all_options();
		}
	}

	/**
	 * Batch insert multiple options in a single query.
	 *
	 * Optimized for Galera clusters and high-latency databases.
	 * Uses single INSERT ... ON DUPLICATE KEY UPDATE to minimize
	 * round-trips and synchronization overhead.
	 *
	 * @since  1.5.0
	 * @param  array  $options  Associative array of options (key => value).
	 * @param  bool   $autoload Whether to autoload (default: false).
	 * @param  string $group    Option group (default: 'settings').
	 * @return int              Number of options inserted/updated.
	 */
	public static function batch_insert( array $options, bool $autoload = false, string $group = 'settings' ): int {
		if ( empty( $options ) ) {
			return 0;
		}

		global $wpdb;
		self::init();

		$values       = array();
		$placeholders = array();
		$autoload_int = $autoload ? 1 : 0;

		foreach ( $options as $key => $value ) {
			$serialized_value = maybe_serialize( $value );
			$placeholders[]   = '(%s, %s, %d, %s)';
			$values[]         = $key;
			$values[]         = $serialized_value;
			$values[]         = $autoload_int;
			$values[]         = $group;
		}

		/*
		 * Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
		 * This is a single query regardless of how many options.
		 * Galera only needs to sync once instead of N times.
		 *
		 * Security: self::$table_name is set in init() from $wpdb->prefix (internal, trusted).
		 * $placeholders are hardcoded format strings '(%s, %s, %d, %s)'.
		 * All user values go through $wpdb->prepare().
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source, placeholders are hardcoded format strings.
		$sql = 'INSERT INTO `' . self::$table_name . '` (option_name, option_value, autoload, option_group)
				VALUES ' . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE
					option_value = VALUES(option_value),
					autoload = VALUES(autoload),
					option_group = VALUES(option_group)';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Batch insert to custom options table. Table name from trusted internal source. All user values go through $wpdb->prepare().
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		/* Invalidate cache after batch insert */
		if ( false !== $result ) {
			wp_cache_delete( 'all_options', self::CACHE_GROUP );
			self::$all_options_loaded = false;
			self::$cache              = array();
		}

		return count( $options );
	}

	/**
	 * Check which options from a list already exist.
	 *
	 * Returns only the option names that exist in the database.
	 * Useful for determining which options need to be inserted.
	 *
	 * @since  1.5.0
	 * @param  array $keys Array of option names to check.
	 * @return array       Array of option names that exist.
	 */
	public static function get_existing_keys( array $keys ): array {
		if ( empty( $keys ) ) {
			return array();
		}

		global $wpdb;
		self::init();

		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic IN clause for checking existing options. Table name uses %i identifier placeholder (WP 6.2+). Read-only query, result used immediately.
		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM %i WHERE option_name IN ($placeholders)",
				self::$table_name,
				...$keys
			)
		);
		// phpcs:enable

		return $existing ? $existing : array();
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

	/**
	 * Convert UTC timestamp to user's browser timezone
	 *
	 * Reads timezone from nbuf_browser_tz cookie (set by JavaScript).
	 * Falls back to WordPress site timezone if cookie not available.
	 * Uses WordPress date/time format settings.
	 *
	 * @since 1.4.0
	 *
	 * @param string $utc_timestamp MySQL datetime string in UTC (e.g., "2024-12-14 10:30:00").
	 * @param string $format        Format type: 'full' (date+time+tz), 'date', 'time', or custom PHP format.
	 * @return string Formatted date/time in user's local timezone.
	 */
	public static function format_local_time( string $utc_timestamp, string $format = 'full' ): string {
		if ( empty( $utc_timestamp ) ) {
			return '';
		}

		try {
			/* Create DateTime object in UTC */
			$date = new DateTime( $utc_timestamp, new DateTimeZone( 'UTC' ) );

			/* Get user's timezone from cookie (set by JavaScript) */
			$user_tz = null;
			if ( isset( $_COOKIE['nbuf_browser_tz'] ) ) {
				$cookie_tz = sanitize_text_field( wp_unslash( $_COOKIE['nbuf_browser_tz'] ) );
				/* Validate timezone identifier against known list */
				if ( in_array( $cookie_tz, DateTimeZone::listIdentifiers(), true ) ) {
					$user_tz = $cookie_tz;
				}
			}

			/* Fall back to WordPress site timezone */
			if ( ! $user_tz ) {
				$user_tz = wp_timezone_string();
			}

			/* Convert to user's timezone */
			$date->setTimezone( new DateTimeZone( $user_tz ) );

			/* Get WordPress date/time format settings */
			$wp_date_format = get_option( 'date_format', 'Y-m-d' );
			$wp_time_format = get_option( 'time_format', 'g:i a' );

			/* Determine format to use */
			switch ( $format ) {
				case 'date':
					$php_format = $wp_date_format;
					break;
				case 'time':
					$php_format = $wp_time_format . ' T';
					break;
				case 'full':
					$php_format = $wp_date_format . ' ' . $wp_time_format . ' T';
					break;
				default:
					/* Custom format passed directly */
					$php_format = $format;
					break;
			}

			return $date->format( $php_format );

		} catch ( Exception $e ) {
			/* Return original timestamp if conversion fails */
			return $utc_timestamp;
		}
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
