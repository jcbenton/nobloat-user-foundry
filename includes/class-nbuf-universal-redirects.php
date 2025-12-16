<?php
/**
 * NoBloat User Foundry - Universal Redirects
 *
 * Handles 301 redirects from legacy individual pages to Universal Page URLs.
 * Ensures SEO continuity when migrating to Universal Page Mode.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Universal_Redirects
 *
 * Redirect handler for legacy page URLs.
 */
class NBUF_Universal_Redirects {

	/**
	 * Legacy page option to view mapping.
	 *
	 * Maps legacy page option keys to universal view keys.
	 *
	 * @var array
	 */
	private static $page_to_view = array(
		'nbuf_page_login'            => 'login',
		'nbuf_page_registration'     => 'register',
		'nbuf_page_account'          => 'account',
		'nbuf_page_profile'          => 'profile',
		'nbuf_page_verification'     => 'verify',
		'nbuf_page_request_reset'    => 'forgot-password',
		'nbuf_page_password_reset'   => 'reset-password',
		'nbuf_page_logout'           => 'logout',
		'nbuf_page_2fa_verify'       => '2fa',
		'nbuf_page_totp_setup'       => '2fa-setup',
		'nbuf_page_member_directory' => 'members',
	);

	/**
	 * Initialize the redirect handler.
	 */
	public static function init() {
		/* Only initialize if Universal Mode and legacy redirects are enabled */
		if ( ! NBUF_URL::is_universal_mode() ) {
			return;
		}

		if ( ! NBUF_URL::is_legacy_redirects_enabled() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'handle_legacy_redirects' ), 5 );
	}

	/**
	 * Handle redirects from legacy pages to universal URLs.
	 *
	 * Checks if the current page is a legacy plugin page and redirects
	 * to the corresponding universal URL with 301 status.
	 */
	public static function handle_legacy_redirects() {
		/* Skip if not a singular page */
		if ( ! is_page() ) {
			return;
		}

		$current_page_id = get_queried_object_id();
		if ( ! $current_page_id ) {
			return;
		}

		/* Check if this page matches any legacy page option */
		foreach ( self::$page_to_view as $option_key => $view ) {
			$legacy_page_id = NBUF_Options::get( $option_key, 0 );

			if ( $legacy_page_id && (int) $legacy_page_id === (int) $current_page_id ) {
				/* Check if this view has an override - if so, don't redirect */
				if ( NBUF_URL::get_override_page( $view ) ) {
					return;
				}

				/* Build redirect URL */
				$redirect_url = NBUF_URL::get( $view );

				/* Preserve query parameters */
				$query_string = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
				if ( $query_string ) {
					$redirect_url = add_query_arg( wp_parse_args( $query_string ), $redirect_url );
				}

				/* Perform 301 redirect */
				wp_safe_redirect( $redirect_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Get all legacy page IDs mapped to their views.
	 *
	 * @return array Array of page_id => view_key.
	 */
	public static function get_legacy_page_map() {
		$map = array();

		foreach ( self::$page_to_view as $option_key => $view ) {
			$page_id = NBUF_Options::get( $option_key, 0 );
			if ( $page_id ) {
				$map[ $page_id ] = $view;
			}
		}

		return $map;
	}

	/**
	 * Check if a page ID is a legacy plugin page.
	 *
	 * @param  int $page_id Page ID to check.
	 * @return string|false View key if legacy page, false otherwise.
	 */
	public static function is_legacy_page( $page_id ) {
		foreach ( self::$page_to_view as $option_key => $view ) {
			$legacy_page_id = NBUF_Options::get( $option_key, 0 );
			if ( $legacy_page_id && (int) $legacy_page_id === (int) $page_id ) {
				return $view;
			}
		}

		return false;
	}
}
