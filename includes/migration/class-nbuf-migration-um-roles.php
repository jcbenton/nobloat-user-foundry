<?php
/**
 * NoBloat User Foundry - Ultimate Member Roles Migration
 *
 * Migrates custom roles from Ultimate Member to NoBloat User Foundry.
 * Handles conversion of UM's role metadata to NBUF role format.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Migration_UM_Roles
 *
 * Migrates custom roles from Ultimate Member to NBUF.
 */
class NBUF_Migration_UM_Roles {


	/**
	 * Migrate Ultimate Member roles to NBUF
	 *
	 * @return array Migration results with counts and errors
	 */
	public static function migrate_roles() {
		$results = array(
			'total'    => 0,
			'migrated' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		/* Check if Ultimate Member is active */
		if ( ! class_exists( 'UM' ) ) {
			$results['errors'][] = __( 'Ultimate Member plugin is not active.', 'nobloat-user-foundry' );
			return $results;
		}

		/* Get all UM roles */
		$um_role_keys = get_option( 'um_roles', array() );

		if ( empty( $um_role_keys ) || ! is_array( $um_role_keys ) ) {
			$results['errors'][] = __( 'No Ultimate Member roles found.', 'nobloat-user-foundry' );
			return $results;
		}

		$results['total'] = count( $um_role_keys );

		/* WordPress native roles - don't migrate these */
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		foreach ( $um_role_keys as $role_key ) {
			try {
				/* Skip WordPress native roles */
				if ( in_array( $role_key, $native_roles, true ) ) {
					++$results['skipped'];
					continue;
				}

				/* Get UM role metadata */
				$um_meta = get_option( "um_role_{$role_key}_meta", array() );

				if ( empty( $um_meta ) ) {
					++$results['skipped'];
					continue;
				}

				/* Map UM role to NBUF format */
				$nbuf_role = self::map_um_to_nbuf( $role_key, $um_meta );

				/* Check if role already exists in NBUF */
				if ( NBUF_Role_Manager::role_exists( $role_key ) ) {
					/* Update existing role */
					$result = NBUF_Role_Manager::update_role(
						$role_key,
						array(
							'role_name'    => $nbuf_role['role_name'],
							'capabilities' => $nbuf_role['capabilities'],
							'priority'     => $nbuf_role['priority'],
						)
					);
				} else {
					/* Create new role */
					$result = NBUF_Role_Manager::create_role(
						$role_key,
						$nbuf_role['role_name'],
						$nbuf_role['capabilities'],
						null, // No parent role for UM migrations.
						$nbuf_role['priority']
					);
				}

				if ( is_wp_error( $result ) ) {
					$results['errors'][] = sprintf(
					/* translators: 1: Role key, 2: Error message */
						__( 'Role %1$s: %2$s', 'nobloat-user-foundry' ),
						$role_key,
						$result->get_error_message()
					);
				} else {
					++$results['migrated'];
				}
			} catch ( Exception $e ) {
				$results['errors'][] = sprintf(
				/* translators: 1: Role key, 2: Error message */
					__( 'Role %1$s: %2$s', 'nobloat-user-foundry' ),
					$role_key,
					$e->getMessage()
				);
			}
		}

		/* Log migration to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'roles',
				'um_roles_migrated',
				sprintf(
				/* translators: %d: Number of roles migrated */
					__( 'Migrated %d roles from Ultimate Member', 'nobloat-user-foundry' ),
					$results['migrated']
				),
				$results
			);
		}

		return $results;
	}

	/**
	 * Map Ultimate Member role data to NBUF format
	 *
	 * @param  string $role_key Role key.
	 * @param  array  $um_meta  UM role metadata.
	 * @return array NBUF role data.
	 */
	private static function map_um_to_nbuf( $role_key, $um_meta ) {
		$nbuf = array(
			'role_name'    => '',
			'capabilities' => array(),
			'priority'     => 0,
		);

		/* Get role name */
		if ( isset( $um_meta['_um_name'] ) ) {
			$nbuf['role_name'] = $um_meta['_um_name'];
		} else {
			/* Fallback: make readable name from key */
			$nbuf['role_name'] = ucwords( str_replace( '_', ' ', $role_key ) );
		}

		/* Get WordPress capabilities */
		if ( isset( $um_meta['wp_capabilities'] ) && is_array( $um_meta['wp_capabilities'] ) ) {
			$nbuf['capabilities'] = $um_meta['wp_capabilities'];
		}

		/* Get priority */
		if ( isset( $um_meta['_um_priority'] ) ) {
			$nbuf['priority'] = absint( $um_meta['_um_priority'] );
		}

		return $nbuf;
	}

	/**
	 * Get migration preview (first N roles)
	 *
	 * @param  int $limit Number of roles to preview.
	 * @return array Preview data.
	 */
	public static function get_migration_preview( $limit = 10 ) {
		$um_role_keys = get_option( 'um_roles', array() );

		if ( empty( $um_role_keys ) || ! is_array( $um_role_keys ) ) {
			return array();
		}

		/* WordPress native roles - don't include in preview */
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$custom_roles = array_diff( $um_role_keys, $native_roles );

		$preview = array();
		$count   = 0;

		foreach ( $custom_roles as $role_key ) {
			if ( $count >= $limit ) {
				break;
			}

			$um_meta = get_option( "um_role_{$role_key}_meta", array() );

			if ( empty( $um_meta ) ) {
				continue;
			}

			$nbuf_data = self::map_um_to_nbuf( $role_key, $um_meta );

			/* Get user count for this role */
			$user_count = NBUF_Role_Manager::get_user_count( $role_key );

			$preview[] = array(
				'role_key'     => $role_key,
				'role_name'    => $nbuf_data['role_name'],
				'capabilities' => count( $nbuf_data['capabilities'] ),
				'priority'     => $nbuf_data['priority'],
				'users'        => $user_count,
			);

			++$count;
		}

		return $preview;
	}

	/**
	 * Get count of custom UM roles
	 *
	 * @return int Number of custom roles (excluding WordPress native)
	 */
	public static function get_role_count() {
		$um_role_keys = get_option( 'um_roles', array() );

		if ( empty( $um_role_keys ) || ! is_array( $um_role_keys ) ) {
			return 0;
		}

		/* Exclude WordPress native roles */
		$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$custom_roles = array_diff( $um_role_keys, $native_roles );

		return count( $custom_roles );
	}

	/**
	 * Rollback migration (delete migrated roles)
	 *
	 * @return array Results with count of deleted roles
	 */
	public static function rollback_migration() {
		$um_role_keys = get_option( 'um_roles', array() );
		$deleted      = 0;

		if ( ! empty( $um_role_keys ) && is_array( $um_role_keys ) ) {
			/* Exclude WordPress native roles */
			$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

			foreach ( $um_role_keys as $role_key ) {
				if ( in_array( $role_key, $native_roles, true ) ) {
					continue;
				}

				/* Delete from NBUF if it exists */
				if ( NBUF_Role_Manager::role_exists( $role_key ) ) {
					$result = NBUF_Role_Manager::delete_role( $role_key );
					if ( ! is_wp_error( $result ) ) {
						++$deleted;
					}
				}
			}
		}

		/* Log rollback */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'roles',
				'um_roles_rollback',
				sprintf(
				/* translators: %d: Number of roles deleted */
					__( 'Rolled back migration - deleted %d custom roles', 'nobloat-user-foundry' ),
					$deleted
				),
				array( 'deleted_count' => $deleted )
			);
		}

		return array(
			'success' => true,
			'deleted' => $deleted,
		);
	}
}
