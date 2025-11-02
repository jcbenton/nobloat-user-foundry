<?php
/**
 * NoBloat User Foundry - Unified User Data Access
 *
 * Provides a single, cached interface for accessing all user data across
 * multiple custom tables. Replaces multiple queries with optimized JOINs
 * and implements WordPress object caching for performance.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_User {

	/**
	 * Cache group for WordPress object cache
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'nbuf_users';

	/**
	 * Cache groups for granular caching (BuddyPress pattern)
	 *
	 * @var array
	 */
	const CACHE_GROUPS = array(
		'user_data' => 'nbuf_user_data',
		'profile'   => 'nbuf_profile',
		'2fa'       => 'nbuf_2fa',
	);

	/**
	 * Cache expiration time (1 hour)
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * User data object
	 *
	 * @var object
	 */
	public $data;

	/**
	 * User ID
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * WordPress user object
	 *
	 * @var WP_User
	 */
	public $wp_user;

	/**
	 * Constructor
	 *
	 * @param object $data User data object from database.
	 */
	private function __construct( $data ) {
		$this->data = $data;
		$this->ID = (int) $data->ID;
		$this->wp_user = get_userdata( $this->ID );
	}

	/**
	 * Get user by ID with all data from custom tables
	 *
	 * Performs a single JOIN query across all custom tables and caches the result.
	 * This is the primary method for retrieving user data.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Optional arguments.
	 *                       - 'refresh' => Force refresh cache (default: false)
	 *                       - 'fields'  => Array of field groups to include (default: all)
	 *                                      Options: 'user_data', 'profile', '2fa', 'notes_count'
	 * @return NBUF_User|null User object or null if not found.
	 */
	public static function get( $user_id, $args = array() ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return null;
		}

		$defaults = array(
			'refresh' => false,
			'fields'  => array( 'user_data', 'profile', '2fa' ),
		);

		$args = wp_parse_args( $args, $defaults );

		/* Check cache first unless refresh requested */
		if ( ! $args['refresh'] ) {
			$cache_key = self::get_cache_key( $user_id );
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

			if ( false !== $cached ) {
				return new self( $cached );
			}
		}

		/* Fetch from database */
		$data = self::fetch_user_data( $user_id, $args['fields'] );

		if ( ! $data ) {
			return null;
		}

		/* Cache the result */
		self::cache_user_data( $user_id, $data );

		return new self( $data );
	}

	/**
	 * Get multiple users in a single batch query
	 *
	 * Optimized for admin lists and bulk operations. Significantly faster
	 * than calling get() in a loop.
	 *
	 * @param array $user_ids Array of user IDs.
	 * @param array $args     Optional arguments (same as get()).
	 * @return array Array of NBUF_User objects keyed by user ID.
	 */
	public static function get_many( $user_ids, $args = array() ) {
		$user_ids = array_map( 'absint', $user_ids );
		$user_ids = array_filter( $user_ids );

		if ( empty( $user_ids ) ) {
			return array();
		}

		$defaults = array(
			'refresh' => false,
			'fields'  => array( 'user_data', 'profile', '2fa' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$users = array();
		$uncached_ids = array();

		/* Check cache first for each user */
		if ( ! $args['refresh'] ) {
			foreach ( $user_ids as $user_id ) {
				$cache_key = self::get_cache_key( $user_id );
				$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

				if ( false !== $cached ) {
					$users[ $user_id ] = new self( $cached );
				} else {
					$uncached_ids[] = $user_id;
				}
			}
		} else {
			$uncached_ids = $user_ids;
		}

		/* Fetch uncached users in batch */
		if ( ! empty( $uncached_ids ) ) {
			$batch_data = self::fetch_users_batch( $uncached_ids, $args['fields'] );

			/*
			 * PERFORMANCE: Prime WP_User cache to prevent N+1 queries
			 *
			 * The constructor calls get_userdata() for each user. Without priming
			 * the cache, this would execute individual queries for each user.
			 * WP_User_Query batch loads and caches all users in a single query.
			 */
			$wp_user_query = new WP_User_Query(
				array(
					'include' => $uncached_ids,
					'fields'  => 'all',
				)
			);

			foreach ( $batch_data as $data ) {
				$user_id = (int) $data->ID;

				/* Cache each user */
				self::cache_user_data( $user_id, $data );

				$users[ $user_id ] = new self( $data );
			}
		}

		return $users;
	}

	/**
	 * Build SELECT clause for user queries
	 *
	 * @param array $fields Field groups to include.
	 * @return string SELECT clause.
	 */
	private static function build_select_clause( $fields ) {
		$select_parts = array( 'u.ID', 'u.user_login', 'u.user_email', 'u.display_name', 'u.user_registered' );

		/* Add user_data fields */
		if ( in_array( 'user_data', $fields, true ) ) {
			$select_parts[] = 'ud.is_verified';
			$select_parts[] = 'ud.verified_date';
			$select_parts[] = 'ud.is_disabled';
			$select_parts[] = 'ud.disabled_reason';
			$select_parts[] = 'ud.expires_at';
			$select_parts[] = 'ud.expiration_warned_at';
			$select_parts[] = 'ud.weak_password_flagged_at';
			$select_parts[] = 'ud.password_changed_at';
		}

		/* Add profile fields - all 53 custom profile columns */
		if ( in_array( 'profile', $fields, true ) ) {
			$select_parts[] = 'up.phone';
			$select_parts[] = 'up.mobile_phone';
			$select_parts[] = 'up.work_phone';
			$select_parts[] = 'up.fax';
			$select_parts[] = 'up.preferred_name';
			$select_parts[] = 'up.nickname';
			$select_parts[] = 'up.pronouns';
			$select_parts[] = 'up.gender';
			$select_parts[] = 'up.date_of_birth';
			$select_parts[] = 'up.timezone';
			$select_parts[] = 'up.secondary_email';
			$select_parts[] = 'up.address';
			$select_parts[] = 'up.address_line1';
			$select_parts[] = 'up.address_line2';
			$select_parts[] = 'up.city';
			$select_parts[] = 'up.state';
			$select_parts[] = 'up.postal_code';
			$select_parts[] = 'up.country';
			$select_parts[] = 'up.company';
			$select_parts[] = 'up.job_title';
			$select_parts[] = 'up.department';
			$select_parts[] = 'up.division';
			$select_parts[] = 'up.employee_id';
			$select_parts[] = 'up.badge_number';
			$select_parts[] = 'up.manager_name';
			$select_parts[] = 'up.supervisor_email';
			$select_parts[] = 'up.office_location';
			$select_parts[] = 'up.hire_date';
			$select_parts[] = 'up.termination_date';
			$select_parts[] = 'up.work_email';
			$select_parts[] = 'up.employment_type';
			$select_parts[] = 'up.license_number';
			$select_parts[] = 'up.professional_memberships';
			$select_parts[] = 'up.security_clearance';
			$select_parts[] = 'up.shift';
			$select_parts[] = 'up.remote_status';
			$select_parts[] = 'up.student_id';
			$select_parts[] = 'up.school_name';
			$select_parts[] = 'up.degree';
			$select_parts[] = 'up.major';
			$select_parts[] = 'up.graduation_year';
			$select_parts[] = 'up.gpa';
			$select_parts[] = 'up.certifications';
			$select_parts[] = 'up.twitter';
			$select_parts[] = 'up.facebook';
			$select_parts[] = 'up.linkedin';
			$select_parts[] = 'up.instagram';
			$select_parts[] = 'up.github';
			$select_parts[] = 'up.youtube';
			$select_parts[] = 'up.tiktok';
			$select_parts[] = 'up.discord_username';
			$select_parts[] = 'up.whatsapp';
			$select_parts[] = 'up.telegram';
			$select_parts[] = 'up.viber';
			$select_parts[] = 'up.twitch';
			$select_parts[] = 'up.reddit';
			$select_parts[] = 'up.snapchat';
			$select_parts[] = 'up.soundcloud';
			$select_parts[] = 'up.vimeo';
			$select_parts[] = 'up.spotify';
			$select_parts[] = 'up.pinterest';
			$select_parts[] = 'up.bio';
			$select_parts[] = 'up.website';
			$select_parts[] = 'up.nationality';
			$select_parts[] = 'up.languages';
			$select_parts[] = 'up.emergency_contact';
		}

		/* Add 2FA fields */
		if ( in_array( '2fa', $fields, true ) ) {
			$select_parts[] = 'tfa.enabled AS tfa_enabled';
			$select_parts[] = 'tfa.method AS tfa_method';
			$select_parts[] = 'tfa.totp_enabled';
			$select_parts[] = 'tfa.email_enabled';
			$select_parts[] = 'tfa.backup_codes_remaining';
			$select_parts[] = 'tfa.remember_device';
			$select_parts[] = 'tfa.last_verified_at AS tfa_last_verified_at';
		}

		/* Add notes count */
		if ( in_array( 'notes_count', $fields, true ) ) {
			$select_parts[] = 'COUNT(DISTINCT un.id) AS notes_count';
		}

		return implode( ', ', $select_parts );
	}

	/**
	 * Build FROM/JOIN clause for user queries
	 *
	 * @param array $fields Field groups to include.
	 * @return string FROM clause with JOINs.
	 */
	private static function build_from_clause( $fields ) {
		global $wpdb;

		$from = "{$wpdb->users} u";

		if ( in_array( 'user_data', $fields, true ) ) {
			$from .= " LEFT JOIN {$wpdb->prefix}nbuf_user_data ud ON u.ID = ud.user_id";
		}

		if ( in_array( 'profile', $fields, true ) ) {
			$from .= " LEFT JOIN {$wpdb->prefix}nbuf_user_profile up ON u.ID = up.user_id";
		}

		if ( in_array( '2fa', $fields, true ) ) {
			$from .= " LEFT JOIN {$wpdb->prefix}nbuf_user_2fa tfa ON u.ID = tfa.user_id";
		}

		if ( in_array( 'notes_count', $fields, true ) ) {
			$from .= " LEFT JOIN {$wpdb->prefix}nbuf_user_notes un ON u.ID = un.user_id";
		}

		return $from;
	}

	/**
	 * Fetch user data from database with JOINs
	 *
	 * @param int   $user_id User ID.
	 * @param array $fields  Field groups to include.
	 * @return object|null User data object.
	 */
	private static function fetch_user_data( $user_id, $fields = array() ) {
		global $wpdb;

		$fields = empty( $fields ) ? array( 'user_data', 'profile', '2fa' ) : $fields;

		/* Build query components using helper methods */
		$select = self::build_select_clause( $fields );
		$from   = self::build_from_clause( $fields );

		/* Build query */
		$sql = $wpdb->prepare(
			"SELECT {$select} FROM {$from} WHERE u.ID = %d",
			$user_id
		);

		if ( in_array( 'notes_count', $fields, true ) ) {
			$sql .= " GROUP BY u.ID";
		}

		return $wpdb->get_row( $sql );
	}

	/**
	 * Fetch multiple users in batch
	 *
	 * @param array $user_ids Array of user IDs.
	 * @param array $fields   Field groups to include.
	 * @return array Array of user data objects.
	 */
	private static function fetch_users_batch( $user_ids, $fields = array() ) {
		global $wpdb;

		if ( empty( $user_ids ) ) {
			return array();
		}

		$fields = empty( $fields ) ? array( 'user_data', 'profile', '2fa' ) : $fields;

		/* Build query components using helper methods */
		$select = self::build_select_clause( $fields );
		$from   = self::build_from_clause( $fields );

		/* Build WHERE clause with placeholders */
		$placeholders = implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) );

		/* Build query */
		$sql = $wpdb->prepare(
			"SELECT {$select} FROM {$from} WHERE u.ID IN ({$placeholders})",
			...$user_ids
		);

		if ( in_array( 'notes_count', $fields, true ) ) {
			$sql .= " GROUP BY u.ID";
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Magic getter for user properties
	 *
	 * Provides clean property access: $user->is_verified, $user->phone, etc.
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 */
	public function __get( $name ) {
		/*
		 * FIXED: Use property_exists() instead of isset()
		 *
		 * isset() returns false for NULL values, making it impossible to distinguish
		 * between a property that exists with NULL value vs. a missing property.
		 *
		 * property_exists() correctly identifies properties even when they are NULL.
		 */

		/* Check user data object first - including NULL values */
		if ( property_exists( $this->data, $name ) ) {
			return $this->data->$name;
		}

		/* Check WordPress user object */
		if ( $this->wp_user && property_exists( $this->wp_user, $name ) ) {
			return $this->wp_user->$name;
		}

		/* Trigger notice for truly undefined properties (development mode) */
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			trigger_error(
				sprintf( 'Undefined property: NBUF_User::$%s', $name ),
				E_USER_NOTICE
			);
		}

		return null;
	}

	/**
	 * Check if property exists
	 *
	 * @param string $name Property name.
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->data->$name ) || ( $this->wp_user && isset( $this->wp_user->$name ) );
	}

	/**
	 * Get cache key for user
	 *
	 * @param int $user_id User ID.
	 * @return string Cache key.
	 */
	private static function get_cache_key( $user_id ) {
		return 'user_' . $user_id;
	}

	/**
	 * Get composite cache key for specific data type (BuddyPress pattern)
	 *
	 * Creates a composite key like "{user_id}:{data_type}" for granular caching.
	 * This allows invalidating just user_data, profile, or 2fa without clearing
	 * the entire user cache.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $data_type Data type ('user_data', 'profile', '2fa').
	 * @return string Composite cache key.
	 */
	private static function get_composite_cache_key( $user_id, $data_type ) {
		return $user_id . ':' . $data_type;
	}

	/**
	 * Cache user data
	 *
	 * @param int    $user_id User ID.
	 * @param object $data    User data object.
	 */
	private static function cache_user_data( $user_id, $data ) {
		$cache_key = self::get_cache_key( $user_id );
		wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
	}

	/**
	 * Invalidate user cache
	 *
	 * Call this whenever user data is updated in any table.
	 *
	 * @param int         $user_id   User ID.
	 * @param string|null $data_type Optional. Specific data type to invalidate ('user_data', 'profile', '2fa').
	 *                                If null, invalidates entire user cache.
	 */
	public static function invalidate_cache( $user_id, $data_type = null ) {
		/* Invalidate entire user cache */
		$cache_key = self::get_cache_key( $user_id );
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		/* Also invalidate granular cache if data type specified (BuddyPress pattern) */
		if ( $data_type && isset( self::CACHE_GROUPS[ $data_type ] ) ) {
			$composite_key = self::get_composite_cache_key( $user_id, $data_type );
			wp_cache_delete( $composite_key, self::CACHE_GROUPS[ $data_type ] );
		}
	}

	/**
	 * Invalidate cache for multiple users
	 *
	 * @param array $user_ids Array of user IDs.
	 */
	public static function invalidate_cache_many( $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			self::invalidate_cache( $user_id );
		}
	}

	/**
	 * Get user display name or fallback
	 *
	 * @return string
	 */
	public function get_display_name() {
		if ( ! empty( $this->data->display_name ) ) {
			return $this->data->display_name;
		}

		if ( $this->wp_user ) {
			return $this->wp_user->display_name;
		}

		return $this->data->user_login ?? __( 'Unknown User', 'nobloat-user-foundry' );
	}

	/**
	 * Check if user is verified
	 *
	 * @return bool
	 */
	public function is_verified() {
		return ! empty( $this->data->is_verified );
	}

	/**
	 * Check if user is disabled
	 *
	 * @return bool
	 */
	public function is_disabled() {
		return ! empty( $this->data->is_disabled );
	}

	/**
	 * Check if user has 2FA enabled
	 *
	 * @return bool
	 */
	public function has_2fa() {
		return ! empty( $this->data->tfa_enabled );
	}

	/**
	 * Check if user account is expired
	 *
	 * @return bool
	 */
	public function is_expired() {
		if ( empty( $this->data->expires_at ) ) {
			return false;
		}

		return strtotime( $this->data->expires_at ) < time();
	}

	/**
	 * Get user's full name
	 *
	 * @return string
	 */
	public function get_full_name() {
		$first = $this->wp_user ? $this->wp_user->first_name : '';
		$last = $this->wp_user ? $this->wp_user->last_name : '';

		$full_name = trim( $first . ' ' . $last );

		return ! empty( $full_name ) ? $full_name : $this->get_display_name();
	}

	/**
	 * Convert to array
	 *
	 * Useful for JSON responses or debugging.
	 *
	 * @return array
	 */
	public function to_array() {
		return (array) $this->data;
	}

	/**
	 * Get user data as JSON
	 *
	 * @return string
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}
}
