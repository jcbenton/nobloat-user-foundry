<?php
/**
 * NoBloat User Foundry - Abstract Restriction Base Class
 *
 * Provides common functionality for all restriction types (menu and content).
 * Handles access checking logic that is shared across restriction implementations.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class Abstract_NBUF_Restriction
 *
 * Base class for all restriction types.
 */
abstract class Abstract_NBUF_Restriction {


	/**
	 * Check if current user has access based on visibility settings
	 *
	 * @param  string $visibility    Visibility setting: 'everyone', 'logged_in', 'logged_out', 'role_based'.
	 * @param  array  $allowed_roles Array of allowed role slugs (only used if visibility = 'role_based').
	 * @param  int    $user_id       Optional user ID (defaults to current user).
	 * @return bool True if user has access, false otherwise.
	 */
	protected static function check_access( $visibility, $allowed_roles = array(), $user_id = null ) {
		/* Get user */
		if ( null === $user_id ) {
			$user = wp_get_current_user();
		} else {
			$user = get_userdata( $user_id );
		}

		/* Check based on visibility type */
		switch ( $visibility ) {
			case 'everyone':
				return true;

			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'role_based':
				/* Must be logged in for role-based access */
				if ( ! is_user_logged_in() || ! $user ) {
					return false;
				}

				/* Check if user has one of the allowed roles */
				if ( empty( $allowed_roles ) ) {
					return false; // No roles specified = no access.
				}

				/* Parse allowed roles if JSON string */
				if ( is_string( $allowed_roles ) ) {
					$allowed_roles = json_decode( $allowed_roles, true );
					if ( ! is_array( $allowed_roles ) ) {
						return false;
					}
				}

				/* Check for role intersection */
				return ! empty( array_intersect( $user->roles, $allowed_roles ) );

			default:
				/* Unknown visibility type = deny access */
				return false;
		}
	}

	/**
	 * Sanitize visibility value
	 *
	 * @param  string $visibility Raw visibility value.
	 * @return string Sanitized visibility value.
	 */
	protected static function sanitize_visibility( $visibility ) {
		$allowed = array( 'everyone', 'logged_in', 'logged_out', 'role_based' );

		$visibility = sanitize_text_field( $visibility );

		return in_array( $visibility, $allowed, true ) ? $visibility : 'everyone';
	}

	/**
	 * Sanitize allowed roles array
	 *
	 * @param  array $roles Raw roles array.
	 * @return array Sanitized roles array.
	 */
	protected static function sanitize_roles( $roles ) {
		if ( ! is_array( $roles ) ) {
			return array();
		}

		/* Get valid WordPress roles */
		$wp_roles   = wp_roles()->get_names();
		$role_slugs = array_keys( $wp_roles );

		/* Filter to only valid roles */
		$sanitized = array();
		foreach ( $roles as $role ) {
			$role = sanitize_text_field( $role );
			if ( in_array( $role, $role_slugs, true ) ) {
				$sanitized[] = $role;
			}
		}

		return $sanitized;
	}

	/**
	 * Get current timestamp for database
	 *
	 * @return string MySQL formatted datetime
	 */
	protected static function get_current_timestamp() {
		return current_time( 'mysql' );
	}

	/**
	 * Initialize the restriction type
	 * Must be implemented by child classes
	 */
	abstract public static function init();
}
