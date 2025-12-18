<?php
/**
 * Passkeys Login Integration
 *
 * Adds passkey authentication option to login forms.
 * Works with both wp-login.php and custom frontend login.
 * Implements two-step login flow for better UX.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Passkeys_Login class.
 *
 * Handles passkey integration with WordPress login forms.
 */
class NBUF_Passkeys_Login {


	/**
	 * Initialize passkeys login functionality.
	 *
	 * @since 1.5.0
	 */
	public static function init() {
		if ( ! NBUF_Passkeys::is_enabled() ) {
			return;
		}

		/* Add scripts to wp-login.php */
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login_scripts' ) );

		/* Enqueue scripts on frontend pages */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Get common localization data for JavaScript.
	 *
	 * @since  1.5.0
	 * @param  string $redirect_url Default redirect URL after login.
	 * @return array Localization data.
	 */
	private static function get_localize_data( $redirect_url ) {
		return array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'redirectUrl'     => $redirect_url,
			'passkeysEnabled' => true,
			'twoStepEnabled'  => true, /* Enable two-step login flow */
			'strings'         => array(
				'continue'         => __( 'Continue', 'nobloat-user-foundry' ),
				'checking'         => __( 'Checking...', 'nobloat-user-foundry' ),
				'enterUsername'    => __( 'Please enter a username or email.', 'nobloat-user-foundry' ),
				'passkeyAvailable' => __( 'A passkey is available for this account.', 'nobloat-user-foundry' ),
				'signInPasskey'    => __( 'Sign in with Passkey', 'nobloat-user-foundry' ),
				'usePassword'      => __( 'Use password instead', 'nobloat-user-foundry' ),
				'authenticating'   => __( 'Authenticating...', 'nobloat-user-foundry' ),
				'browserError'     => __( 'Your browser does not support passkeys.', 'nobloat-user-foundry' ),
				'canceled'         => __( 'Authentication canceled.', 'nobloat-user-foundry' ),
				'error'            => __( 'Authentication failed. Please try again.', 'nobloat-user-foundry' ),
				'changeUser'       => __( 'Change', 'nobloat-user-foundry' ),
			),
		);
	}

	/**
	 * Enqueue login scripts for wp-login.php.
	 *
	 * @since 1.5.0
	 */
	public static function enqueue_login_scripts() {
		if ( ! NBUF_Passkeys::is_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'nbuf-passkeys-login',
			NBUF_PLUGIN_URL . 'assets/js/frontend/passkeys-login.js',
			array(),
			NBUF_VERSION,
			true
		);

		wp_localize_script(
			'nbuf-passkeys-login',
			'nbufPasskeyLogin',
			self::get_localize_data( admin_url() )
		);

		/* Add inline styles for two-step flow */
		wp_add_inline_style(
			'login',
			'#nbuf-continue-btn { margin-top: 16px; }
			#nbuf-passkey-section { margin: 20px 0; }
			#nbuf-passkey-login-btn { margin-bottom: 10px; }
			#nbuf-passkey-login-btn:hover { background: #135e96; }
			#nbuf-passkey-login-btn:disabled { opacity: 0.6; cursor: not-allowed; }
			#nbuf-use-password-link:hover { text-decoration: underline; }'
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.5.0
	 */
	public static function enqueue_frontend_scripts() {
		if ( ! NBUF_Passkeys::is_enabled() ) {
			return;
		}

		/* Check if on login page - either by page ID or Universal Router */
		$is_login_page = false;

		/* Check page ID */
		$login_page_id = NBUF_Options::get( 'nbuf_login_page', 0 );
		if ( $login_page_id && is_page( $login_page_id ) ) {
			$is_login_page = true;
		}

		/* Check Universal Router */
		if ( ! $is_login_page && class_exists( 'NBUF_Universal_Router' ) ) {
			$current_view = NBUF_Universal_Router::get_current_view();
			if ( 'login' === $current_view ) {
				$is_login_page = true;
			}
		}

		if ( ! $is_login_page ) {
			return;
		}

		wp_enqueue_script(
			'nbuf-passkeys-login',
			NBUF_PLUGIN_URL . 'assets/js/frontend/passkeys-login.js',
			array(),
			NBUF_VERSION,
			true
		);

		/* Determine redirect URL */
		$redirect_url = home_url();
		$account_page = NBUF_Options::get( 'nbuf_account_page', 0 );
		if ( $account_page ) {
			$redirect_url = get_permalink( $account_page );
		}

		wp_localize_script(
			'nbuf-passkeys-login',
			'nbufPasskeyLogin',
			self::get_localize_data( $redirect_url )
		);
	}
}
