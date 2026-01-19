<?php
/**
 * NoBloat User Foundry - Role Manager
 *
 * Lightweight role management with three-tier caching and WordPress integration.
 * Provides efficient role storage and retrieval with minimal database queries.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Role_Manager
 *
 * Lightweight role management with three-tier caching.
 */
class NBUF_Role_Manager {


	/**
	 * In-memory cache for current request
	 *
	 * @var array|null
	 */
	private static $roles_cache = null;

	/**
	 * Cache group for WordPress object cache
	 *
	 * @var string
	 */
	private static $cache_group = 'nbuf_roles';

	/**
	 * Cache expiration time
	 *
	 * @var int
	 */
	private static $cache_expiration = 12 * HOUR_IN_SECONDS;

	/**
	 * Initialize role manager
	 */
	public static function init() {
		/* Register with WordPress roles system (early priority for better performance) */
		add_action( 'wp_roles_init', array( __CLASS__, 'register_roles' ), 10 );
	}

	/**
	 * Get all custom roles with smart caching
	 *
	 * @return array Custom roles data.
	 */
	public static function get_all_roles() {
		/* Level 1: In-memory cache (zero cost) */
		if ( null !== self::$roles_cache ) {
			return self::$roles_cache;
		}

		/* Level 2: WordPress object cache (Redis, Memcached, etc.) */
		$cached = wp_cache_get( 'all_roles', self::$cache_group );
		if ( false !== $cached ) {
			self::$roles_cache = $cached;
			return $cached;
		}

		/* Level 3: Database (only on cache miss) */
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_user_roles';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$roles = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY priority DESC',
				$table
			),
			ARRAY_A
		);

		$formatted = array();
		foreach ( $roles as $role ) {
			$formatted[ $role['role_key'] ] = array(
				'name'         => $role['role_name'],
				'capabilities' => json_decode( $role['capabilities'], true ),
				'parent_role'  => $role['parent_role'],
				'priority'     => (int) $role['priority'],
				'created_at'   => $role['created_at'],
				'updated_at'   => $role['updated_at'],
			);
		}

		/* Cache for future requests */
		wp_cache_set( 'all_roles', $formatted, self::$cache_group, self::$cache_expiration );
		self::$roles_cache = $formatted;

		return $formatted;
	}

	/**
	 * Get single role - even more efficient
	 *
	 * @param  string $role_key Role key/slug.
	 * @return array|null Role data or null if not found.
	 */
	public static function get_role( $role_key ) {
		/* Check object cache first */
		$cached = wp_cache_get( "role_{$role_key}", self::$cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		/* Fetch all roles (which are cached) and extract */
		$all_roles = self::get_all_roles();
		$role      = isset( $all_roles[ $role_key ] ) ? $all_roles[ $role_key ] : null;

		/* Cache individual role too */
		if ( $role ) {
			wp_cache_set( "role_{$role_key}", $role, self::$cache_group, self::$cache_expiration );
		}

		return $role;
	}

	/**
	 * Create a new custom role
	 *
	 * @param  string $role_key     Role key/slug (e.g., 'team_manager').
	 * @param  string $role_name    Display name (e.g., 'Team Manager').
	 * @param  array  $capabilities Array of capabilities.
	 * @param  string $parent_role  Optional parent role for inheritance.
	 * @param  int    $priority     Priority for multiple roles (higher = more important).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_role( $role_key, $role_name, $capabilities = array(), $parent_role = null, $priority = 0 ) {
		global $wpdb;

		/* Validate role key */
		if ( empty( $role_key ) || ! preg_match( '/^[a-z0-9_]+$/', $role_key ) ) {
			return new WP_Error( 'invalid_role_key', __( 'Role key must contain only lowercase letters, numbers, and underscores.', 'nobloat-user-foundry' ) );
		}

		/* Check if role already exists */
		if ( self::role_exists( $role_key ) ) {
			return new WP_Error( 'role_exists', __( 'A role with this key already exists.', 'nobloat-user-foundry' ) );
		}

		/* Validate role name */
		if ( empty( $role_name ) ) {
			return new WP_Error( 'invalid_role_name', __( 'Role name is required.', 'nobloat-user-foundry' ) );
		}

		/* Resolve capabilities (handle inheritance) */
		$final_capabilities = self::resolve_capabilities(
			array(
				'capabilities' => $capabilities,
				'parent_role'  => $parent_role,
			)
		);

		/* Prepare data */
		$table = $wpdb->prefix . 'nbuf_user_roles';
		$data  = array(
			'role_key'     => $role_key,
			'role_name'    => sanitize_text_field( $role_name ),
			'capabilities' => wp_json_encode( $final_capabilities ),
			'parent_role'  => $parent_role ? sanitize_text_field( $parent_role ) : null,
			'priority'     => absint( $priority ),
			'created_at'   => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		);

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create role in database.', 'nobloat-user-foundry' ) );
		}

		/* Add to WordPress roles system */
		add_role( $role_key, $role_name, $final_capabilities );

		/* Clear caches */
		self::clear_cache();

		/* Log to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'roles',
				'role_created',
				sprintf(
				/* translators: %s: Role name */
					__( 'Created role "%s"', 'nobloat-user-foundry' ),
					$role_name
				),
				array(
					'role_key'     => $role_key,
					'capabilities' => count( $final_capabilities ),
					'parent_role'  => $parent_role,
				)
			);
		}

		return true;
	}

	/**
	 * Update an existing role
	 *
	 * @param  string $role_key Role key.
	 * @param  array  $updates  Array of fields to update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function update_role( $role_key, $updates ) {
		global $wpdb;

		/* Check if role exists */
		if ( ! self::role_exists( $role_key ) ) {
			return new WP_Error( 'role_not_found', __( 'Role not found.', 'nobloat-user-foundry' ) );
		}

		/* Prepare update data */
		$data = array(
			'updated_at' => current_time( 'mysql', true ),
		);

		if ( isset( $updates['role_name'] ) ) {
			$data['role_name'] = sanitize_text_field( $updates['role_name'] );
		}

		if ( isset( $updates['capabilities'] ) ) {
			/* Resolve capabilities with inheritance */
			$final_capabilities   = self::resolve_capabilities(
				array(
					'capabilities' => $updates['capabilities'],
					'parent_role'  => isset( $updates['parent_role'] ) ? $updates['parent_role'] : null,
				)
			);
			$data['capabilities'] = wp_json_encode( $final_capabilities );
		}

		if ( isset( $updates['parent_role'] ) ) {
			$data['parent_role'] = $updates['parent_role'] ? sanitize_text_field( $updates['parent_role'] ) : null;
		}

		if ( isset( $updates['priority'] ) ) {
			$data['priority'] = absint( $updates['priority'] );
		}

		/* Update database */
		$table = $wpdb->prefix . 'nbuf_user_roles';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$data,
			array( 'role_key' => $role_key ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update role in database.', 'nobloat-user-foundry' ) );
		}

		/* Update WordPress role */
		remove_role( $role_key );
		add_role( $role_key, $data['role_name'], $final_capabilities ?? null );

		/* Clear caches */
		self::clear_cache();

		/* Log to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'roles',
				'role_updated',
				sprintf(
				/* translators: %s: Role key */
					__( 'Updated role "%s"', 'nobloat-user-foundry' ),
					$role_key
				),
				array(
					'role_key' => $role_key,
					'updates'  => array_keys( $updates ),
				)
			);
		}

		return true;
	}

	/**
	 * Delete a custom role
	 *
	 * @param  string $role_key      Role key.
	 * @param  string $reassign_role Role to reassign users to (default: subscriber).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_role( $role_key, $reassign_role = 'subscriber' ) {
		global $wpdb;

		/* Don't allow deletion of WordPress native roles */
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $role_key, $native_roles, true ) ) {
			return new WP_Error( 'cannot_delete_native', __( 'Cannot delete WordPress native roles.', 'nobloat-user-foundry' ) );
		}

		/* Check if role exists */
		if ( ! self::role_exists( $role_key ) ) {
			return new WP_Error( 'role_not_found', __( 'Role not found.', 'nobloat-user-foundry' ) );
		}

		/* Reassign users to new role */
		$users = get_users( array( 'role' => $role_key ) );
		foreach ( $users as $user ) {
			$user_obj = new WP_User( $user->ID );
			$user_obj->remove_role( $role_key );
			$user_obj->add_role( $reassign_role );
		}

		/* Delete from database */
		$table = $wpdb->prefix . 'nbuf_user_roles';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array( 'role_key' => $role_key ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to delete role from database.', 'nobloat-user-foundry' ) );
		}

		/* Remove from WordPress */
		remove_role( $role_key );

		/* Clear caches */
		self::clear_cache();

		/* Log to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'roles',
				'role_deleted',
				sprintf(
				/* translators: 1: Role key, 2: Number of users reassigned */
					__( 'Deleted role "%1$s" and reassigned %2$d users', 'nobloat-user-foundry' ),
					$role_key,
					count( $users )
				),
				array(
					'role_key'      => $role_key,
					'users_count'   => count( $users ),
					'reassign_role' => $reassign_role,
				)
			);
		}

		return true;
	}

	/**
	 * Check if a role exists (in database or WordPress)
	 *
	 * @param  string $role_key Role key.
	 * @return bool True if exists.
	 */
	public static function role_exists( $role_key ) {
		/* Check custom roles */
		$custom_roles = self::get_all_roles();
		if ( isset( $custom_roles[ $role_key ] ) ) {
			return true;
		}

		/* Check WordPress native roles */
		$wp_roles = wp_roles()->roles;
		return isset( $wp_roles[ $role_key ] );
	}

	/**
	 * Resolve capabilities with inheritance
	 *
	 * @param  array $role_data Role data with capabilities and parent_role.
	 * @return array Final capabilities array.
	 */
	public static function resolve_capabilities( $role_data ) {
		$capabilities = isset( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();
		$parent_role  = isset( $role_data['parent_role'] ) ? $role_data['parent_role'] : null;

		/* If no parent, return capabilities as-is */
		if ( empty( $parent_role ) ) {
			return $capabilities;
		}

		/* Get parent capabilities */
		$parent_caps = array();
		$wp_role     = get_role( $parent_role );
		if ( $wp_role ) {
			$parent_caps = $wp_role->capabilities;
		}

		/* Merge parent capabilities with custom ones (custom overrides parent) */
		return array_merge( $parent_caps, $capabilities );
	}

	/**
	 * Register custom roles with WordPress
	 *
	 * @param WP_Roles $roles WordPress roles object.
	 */
	public static function register_roles( $roles ) {
		$custom_roles = self::get_all_roles(); // Cached!

		foreach ( $custom_roles as $role_key => $role_data ) {
			/* Resolve capabilities (handle inheritance) */
			$caps = self::resolve_capabilities( $role_data );

			/* Add to WordPress if not exists */
			if ( ! isset( $roles->roles[ $role_key ] ) ) {
				add_role( $role_key, $role_data['name'], $caps );
			}
		}
	}

	/**
	 * Get user count for a role
	 *
	 * @param  string $role_key Role key.
	 * @return int Number of users with this role.
	 */
	public static function get_user_count( $role_key ) {
		$users = count_users();
		return isset( $users['avail_roles'][ $role_key ] ) ? $users['avail_roles'][ $role_key ] : 0;
	}

	/**
	 * Get all WordPress capabilities (for UI)
	 *
	 * @return array Capabilities grouped by category.
	 */
	public static function get_all_capabilities() {
		$wp_roles = wp_roles()->roles;
		$all_caps = array();

		/* Collect all unique capabilities from all roles */
		foreach ( $wp_roles as $role_data ) {
			if ( isset( $role_data['capabilities'] ) ) {
				$all_caps = array_merge( $all_caps, array_keys( $role_data['capabilities'] ) );
			}
		}

		$all_caps = array_unique( $all_caps );
		sort( $all_caps );

		/* Organize by category */
		$categorized = array(
			'content'  => array(),
			'users'    => array(),
			'plugins'  => array(),
			'themes'   => array(),
			'settings' => array(),
			'other'    => array(),
		);

		foreach ( $all_caps as $cap ) {
			/* Content capabilities */
			if ( preg_match( '/(post|page|attachment|edit|delete|publish|read)/', $cap ) ) {
				$categorized['content'][] = $cap;
				/* User capabilities */
			} elseif ( preg_match( '/(user|profile|list_users|promote_users|delete_users|create_users|edit_users)/', $cap ) ) {
				$categorized['users'][] = $cap;
				/* Plugin capabilities */
			} elseif ( preg_match( '/(plugin|activate_plugin|edit_plugins)/', $cap ) ) {
				$categorized['plugins'][] = $cap;
				/* Theme capabilities */
			} elseif ( preg_match( '/(theme|edit_themes|switch_themes)/', $cap ) ) {
				$categorized['themes'][] = $cap;
				/* Settings capabilities */
			} elseif ( preg_match( '/(manage_options|manage_categories|manage_links|moderate_comments)/', $cap ) ) {
				$categorized['settings'][] = $cap;
				/* Everything else */
			} else {
				$categorized['other'][] = $cap;
			}
		}

		return $categorized;
	}

	/**
	 * Clear all role caches
	 */
	public static function clear_cache() {
		/* Clear in-memory cache */
		self::$roles_cache = null;

		/* Clear object cache */
		wp_cache_delete( 'all_roles', self::$cache_group );

		/* Clear WordPress role cache */
		wp_cache_delete( 'user_roles', 'options' );
	}

	/**
	 * Export role configuration as JSON
	 *
	 * @param  string $role_key Role key.
	 * @return string|WP_Error JSON string or error.
	 */
	public static function export_role( $role_key ) {
		$role = self::get_role( $role_key );

		if ( ! $role ) {
			return new WP_Error( 'role_not_found', __( 'Role not found.', 'nobloat-user-foundry' ) );
		}

		$export = array(
			'role_key'     => $role_key,
			'role_name'    => $role['name'],
			'capabilities' => $role['capabilities'],
			'parent_role'  => $role['parent_role'],
			'priority'     => $role['priority'],
			'exported_at'  => current_time( 'mysql', true ),
			'exported_by'  => get_current_user_id(),
		);

		return wp_json_encode( $export, JSON_PRETTY_PRINT );
	}

	/**
	 * Import role from JSON
	 *
	 * @param  string $json      JSON string.
	 * @param  bool   $overwrite Whether to overwrite existing role.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function import_role( $json, $overwrite = false ) {
		$data = json_decode( $json, true );

		if ( ! $data || ! isset( $data['role_key'] ) || ! isset( $data['role_name'] ) ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'nobloat-user-foundry' ) );
		}

		$exists = self::role_exists( $data['role_key'] );

		if ( $exists && ! $overwrite ) {
			return new WP_Error( 'role_exists', __( 'Role already exists. Enable overwrite to replace.', 'nobloat-user-foundry' ) );
		}

		if ( $exists && $overwrite ) {
			return self::update_role(
				$data['role_key'],
				array(
					'role_name'    => $data['role_name'],
					'capabilities' => $data['capabilities'],
					'parent_role'  => $data['parent_role'] ?? null,
					'priority'     => $data['priority'] ?? 0,
				)
			);
		} else {
			return self::create_role(
				$data['role_key'],
				$data['role_name'],
				$data['capabilities'] ?? array(),
				$data['parent_role'] ?? null,
				$data['priority'] ?? 0
			);
		}
	}

	/**
	 * Adopt all orphaned roles into NoBloat management
	 *
	 * Finds all WordPress roles that are not native and not already
	 * managed by NoBloat, and imports them into the database.
	 *
	 * @return array Results with counts and adopted roles list.
	 */
	public static function adopt_all_orphaned_roles() {
		global $wpdb;

		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$custom_roles = self::get_all_roles();
		$wp_roles     = wp_roles();
		$table        = $wpdb->prefix . 'nbuf_user_roles';

		$results = array(
			'total'   => 0,
			'adopted' => 0,
			'skipped' => 0,
			'errors'  => array(),
			'roles'   => array(),
		);

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			/* Skip native WordPress roles */
			if ( in_array( $role_key, $native_roles, true ) ) {
				continue;
			}

			/* Skip roles already managed by NoBloat */
			if ( isset( $custom_roles[ $role_key ] ) ) {
				continue;
			}

			++$results['total'];

			/* Get the WordPress role object */
			$wp_role = get_role( $role_key );
			if ( ! $wp_role ) {
				$results['errors'][] = sprintf(
					/* translators: %s: Role key */
					__( 'Role "%s" not found in WordPress.', 'nobloat-user-foundry' ),
					$role_key
				);
				continue;
			}

			/*
			 * Insert into NBUF database.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$table,
				array(
					'role_key'     => $role_key,
					'role_name'    => $role_data['name'],
					'capabilities' => wp_json_encode( $wp_role->capabilities ),
					'parent_role'  => null,
					'priority'     => 0,
					'created_at'   => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				)
			);

			if ( false === $inserted ) {
				$results['errors'][] = sprintf(
					/* translators: %s: Role key */
					__( 'Failed to adopt role "%s". Database error.', 'nobloat-user-foundry' ),
					$role_key
				);
				continue;
			}

			++$results['adopted'];
			$results['roles'][] = array(
				'role_key'  => $role_key,
				'role_name' => $role_data['name'],
			);
		}

		/* Clear caches if any roles were adopted */
		if ( $results['adopted'] > 0 ) {
			self::clear_cache();

			/* Log to audit */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::log(
					get_current_user_id(),
					'roles',
					'roles_adopted',
					sprintf(
						/* translators: %d: Number of roles adopted */
						__( 'Adopted %d orphaned roles during migration', 'nobloat-user-foundry' ),
						$results['adopted']
					),
					$results
				);
			}
		}

		return $results;
	}

	/**
	 * Get count of orphaned roles (not native and not in NBUF database)
	 *
	 * @return int Number of orphaned roles.
	 */
	public static function get_orphaned_role_count() {
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$custom_roles = self::get_all_roles();
		$wp_roles     = wp_roles();
		$count        = 0;

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			if ( ! in_array( $role_key, $native_roles, true ) && ! isset( $custom_roles[ $role_key ] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get list of orphaned roles for preview
	 *
	 * @return array List of orphaned roles with their details.
	 */
	public static function get_orphaned_roles_preview() {
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$custom_roles = self::get_all_roles();
		$wp_roles     = wp_roles();
		$orphaned     = array();

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			if ( ! in_array( $role_key, $native_roles, true ) && ! isset( $custom_roles[ $role_key ] ) ) {
				$orphaned[] = array(
					'role_key'   => $role_key,
					'role_name'  => $role_data['name'],
					'user_count' => self::get_user_count( $role_key ),
					'cap_count'  => count( $role_data['capabilities'] ),
				);
			}
		}

		return $orphaned;
	}
}

// Initialize Role Manager.
NBUF_Role_Manager::init();
