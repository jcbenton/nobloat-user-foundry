<?php
/**
 * NoBloat User Foundry - URL Helper
 *
 * Centralized URL generation for all user-facing pages.
 * All pages are virtual - no WordPress pages needed.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_URL
 *
 * Provides URL generation methods for all plugin pages.
 */
class NBUF_URL {

	/**
	 * Get URL for a specific view.
	 *
	 * @param  string $view View key (login, register, account, etc.).
	 * @param  array  $args Optional query args to append.
	 * @return string Full URL.
	 */
	public static function get( $view, $args = array() ) {
		$base_slug = self::get_base_slug();
		$url       = home_url( '/' . $base_slug . '/' . $view . '/' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get URL for account page with specific tab/subtab.
	 *
	 * @param  string $tab    Tab name (profile, security, email, etc.).
	 * @param  string $subtab Optional subtab name.
	 * @param  array  $args   Additional query args.
	 * @return string Full URL.
	 */
	public static function get_account( $tab = '', $subtab = '', $args = array() ) {
		$base_slug = self::get_base_slug();
		$path      = '/' . $base_slug . '/account/';

		if ( $tab ) {
			$path .= $tab . '/';
			if ( $subtab ) {
				$path .= $subtab . '/';
			}
		}

		$url = home_url( $path );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get URL for public profile page.
	 *
	 * @param  string $username Username or user slug.
	 * @param  array  $args     Additional query args.
	 * @return string Full URL.
	 */
	public static function get_profile( $username, $args = array() ) {
		$base_slug = self::get_base_slug();
		$url       = home_url( '/' . $base_slug . '/profile/' . sanitize_title( $username ) . '/' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get the base slug for URLs.
	 *
	 * @return string Base slug (default: 'user-foundry').
	 */
	public static function get_base_slug() {
		return sanitize_title( NBUF_Options::get( 'nbuf_universal_base_slug', 'user-foundry' ) );
	}

	/**
	 * Get the default view for the base URL.
	 *
	 * @return string Default view key (default: 'account').
	 */
	public static function get_default_view() {
		return sanitize_key( NBUF_Options::get( 'nbuf_universal_default_view', 'account' ) );
	}

	/**
	 * Get all available views.
	 *
	 * @return array Array of view key => label.
	 */
	public static function get_available_views() {
		return array(
			'login'           => __( 'Login', 'nobloat-user-foundry' ),
			'register'        => __( 'Registration', 'nobloat-user-foundry' ),
			'account'         => __( 'Account', 'nobloat-user-foundry' ),
			'profile'         => __( 'Public Profile', 'nobloat-user-foundry' ),
			'verify'          => __( 'Email Verification', 'nobloat-user-foundry' ),
			'forgot-password' => __( 'Forgot Password', 'nobloat-user-foundry' ),
			'reset-password'  => __( 'Reset Password', 'nobloat-user-foundry' ),
			'logout'          => __( 'Logout', 'nobloat-user-foundry' ),
			'2fa'             => __( '2FA Verification', 'nobloat-user-foundry' ),
			'2fa-setup'       => __( 'TOTP Setup', 'nobloat-user-foundry' ),
			'members'         => __( 'Member Directory', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Check if currently on a plugin page.
	 *
	 * @return bool True if on a plugin virtual page.
	 */
	public static function is_plugin_page() {
		return class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request();
	}

	/**
	 * Get current view key.
	 *
	 * @return string View key or empty string.
	 */
	public static function get_current_view() {
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_current_view();
		}
		return '';
	}
}
