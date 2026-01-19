<?php
/**
 * Two-Factor Authentication Login Integration
 *
 * Hooks into WordPress authentication to enforce 2FA verification
 * after successful password validation.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_2FA_Login class.
 *
 * Handles 2FA integration with WordPress login flow.
 */
class NBUF_2FA_Login {


	/**
	 * Cookie name for 2FA token
	 */
	const COOKIE_NAME = 'nbuf_2fa_token';

	/**
	 * Transient expiration (5 minutes)
	 */
	const TRANSIENT_EXPIRATION = 300;

	/**
	 * Initialize hooks
	 */
	public static function init() {
		/* Hook into authentication after password validation */
		add_filter( 'authenticate', array( __CLASS__, 'intercept_login' ), 30, 3 );

		/* Handle 2FA verification on dedicated page */
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_2fa_verification' ) );

		/* Clear transient data on logout */
		add_action( 'wp_logout', array( __CLASS__, 'clear_2fa_transient' ) );

		/* Check TOTP setup requirement on every page load for logged-in users */
		add_action( 'template_redirect', array( __CLASS__, 'check_totp_setup_required' ) );
	}

	/**
	 * Check if logged-in user needs to set up TOTP on every page load.
	 *
	 * This catches users who logged in without going through 2FA flow
	 * (e.g., trusted devices, no 2FA configured yet).
	 */
	public static function check_totp_setup_required() {
		/* Only for logged-in users */
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		/* Check if we're on Universal Router pages (2fa-setup or account) */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$current_view = NBUF_Universal_Router::get_current_view();
			if ( in_array( $current_view, array( '2fa-setup', 'account' ), true ) ) {
				return;
			}
		}

		/* Check if we're already on the TOTP setup page (legacy) */
		$setup_page_id = NBUF_Options::get( 'nbuf_page_totp_setup', 0 );
		if ( $setup_page_id && is_page( $setup_page_id ) ) {
			return;
		}

		/* Check if we're on the account page (legacy - allow access to security settings) */
		$account_page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
		if ( $account_page_id && is_page( $account_page_id ) ) {
			return;
		}

		/* Skip for admin pages - let admin users access their dashboard */
		if ( is_admin() ) {
			return;
		}

		/* Check if TOTP setup redirect is needed */
		$redirect_url = self::maybe_redirect_to_totp_setup( $user_id );
		if ( $redirect_url ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Intercept login after password validation
	 *
	 * Runs after WordPress validates the password. If 2FA is required,
	 * we prevent immediate login and redirect to 2FA verification page.
	 *
	 * @param  WP_User|WP_Error|null $user     WP_User if authenticated, WP_Error if failed.
	 * @param  string                $username Username or email address.
	 * @param  string                $password User password.
	 * @return WP_User|WP_Error Modified authentication result.
	 */
	public static function intercept_login( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $username, $password required by WordPress authenticate filter signature
		/* Only proceed if password authentication succeeded */
		if ( ! $user instanceof WP_User ) {
			return $user;
		}

		/* Check if admin bypass is enabled and user is admin */
		$admin_bypass = NBUF_Options::get( 'nbuf_2fa_admin_bypass', false );
		if ( $admin_bypass && user_can( $user->ID, 'manage_options' ) ) {
			return $user;
		}

		/* Check if 2FA challenge is needed */
		if ( ! NBUF_2FA::should_challenge( $user->ID ) ) {
			return $user;
		}

		/*
		 * SECURITY: Generate cryptographically secure 2FA session token.
		 * Use random_bytes() instead of wp_generate_password() for security tokens.
		 */
		$token = bin2hex( random_bytes( 32 ) ); /* 64 hex characters, cryptographically secure */

		/* Get user's 2FA method, or required method if user hasn't set up 2FA */
		$method = NBUF_2FA::get_user_method( $user->ID );
		if ( ! $method && NBUF_2FA::is_required( $user->ID ) ) {
			$method = NBUF_2FA::get_required_method();
		}

		set_transient(
			'nbuf_2fa_pending_' . $token,
			array(
				'user_id'   => $user->ID,
				'timestamp' => time(),
				'method'    => $method, /* Store method for verification page */
			),
			self::TRANSIENT_EXPIRATION
		);

		/* Store token in secure cookie */
		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + self::TRANSIENT_EXPIRATION,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);

		/* Send email code if using email 2FA */
		if ( 'email' === $method || 'both' === $method ) {
			NBUF_2FA::send_email_code( $user->ID );
		}

		/* Redirect to 2FA verification page */
		self::redirect_to_2fa_page( $user->ID );

		/* Return error to prevent automatic login */
		return new WP_Error(
			'2fa_required',
			__( 'Redirecting to two-factor authentication...', 'nobloat-user-foundry' )
		);
	}

	/**
	 * Redirect to 2FA verification page
	 *
	 * @param int $user_id User ID.
	 */
	private static function redirect_to_2fa_page( $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $user_id parameter kept for potential future use in redirect customization
		/* Prefer Universal Router URL (respects base slug setting) */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			$redirect_url = NBUF_Universal_Router::get_url( '2fa' );
		} else {
			/* Fallback to legacy page */
			$page_id = NBUF_Options::get( 'nbuf_page_2fa_verify', 0 );
			if ( $page_id ) {
				$redirect_url = get_permalink( $page_id );
			} else {
				$redirect_url = home_url( '/2fa-verify/' );
			}
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle 2FA code verification POST
	 *
	 * Processes the verification form submission on the 2FA page.
	 */
	public static function maybe_handle_2fa_verification() {
		/* Early exit for non-POST requests - no need to check anything else */
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		/* Check if this is a 2FA verification request */
		if ( ! isset( $_POST['nbuf_2fa_verify'] ) ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_verify' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'nobloat-user-foundry' ) );
		}

		/* Get token from cookie */
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			wp_die( esc_html__( 'Invalid session. Please log in again.', 'nobloat-user-foundry' ) );
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		/* Get user data from transient */
		$pending_data = get_transient( 'nbuf_2fa_pending_' . $token );

		if ( false === $pending_data ) {
			wp_die( esc_html__( 'Session expired. Please log in again.', 'nobloat-user-foundry' ) );
		}

		$user_id = absint( $pending_data['user_id'] );

		/* Get method from transient (includes auto-required method) */
		$method = isset( $pending_data['method'] ) ? $pending_data['method'] : NBUF_2FA::get_user_method( $user_id );

		/*
		 * Get submitted code and code type.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification above.
		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification above.
		$code_type = isset( $_POST['code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['code_type'] ) ) : 'auto';

		if ( empty( $code ) ) {
			/* Redirect back with error for empty code */
			$current_url   = self::get_2fa_page_url();
			$redirect_args = array( 'error' => 'invalid' );

			/* Preserve backup mode if that's what was used */
			if ( 'backup' === $code_type ) {
				$redirect_args['backup'] = '1';
			}

			wp_safe_redirect( add_query_arg( $redirect_args, $current_url ) );
			exit;
		}

		$verified = false;
		$result   = null;

		/* Try backup code first if explicitly selected */
		if ( 'backup' === $code_type ) {
			$result   = NBUF_2FA::verify_backup_code( $user_id, $code );
			$verified = ! is_wp_error( $result );
		} elseif ( 'email' === $method ) {
			$result   = NBUF_2FA::verify_email_code( $user_id, $code );
			$verified = ! is_wp_error( $result );
		} elseif ( 'totp' === $method ) {
			$result   = NBUF_2FA::verify_totp_code( $user_id, $code );
			$verified = ! is_wp_error( $result );
		} elseif ( 'both' === $method ) {
			/* Try TOTP first (more common), then email */
			$result = NBUF_2FA::verify_totp_code( $user_id, $code );
			if ( ! is_wp_error( $result ) ) {
				$verified = true;
			} else {
				$result   = NBUF_2FA::verify_email_code( $user_id, $code );
				$verified = ! is_wp_error( $result );
			}
		}

		/* If verification failed, reload with error */
		if ( ! $verified ) {
			/* Determine error code for display */
			$error_code = 'invalid';
			if ( is_wp_error( $result ) ) {
				$wp_error_code = $result->get_error_code();
				if ( '2fa_locked_out' === $wp_error_code ) {
					$error_code = 'locked';
				} elseif ( '2fa_code_expired' === $wp_error_code ) {
					$error_code = 'expired';
				}
			}

			/* Log 2FA verification failure */
			NBUF_Audit_Log::log(
				$user_id,
				'2fa_failed',
				'failure',
				'2FA verification failed - ' . $error_code,
				array(
					'method'    => $method,
					'code_type' => $code_type,
				)
			);

			/* Redirect to 2FA page with specific error */
			$current_url   = self::get_2fa_page_url();
			$redirect_args = array( 'error' => $error_code );

			/* Preserve backup mode if that's what was used */
			if ( 'backup' === $code_type ) {
				$redirect_args['backup'] = '1';
			}

			wp_safe_redirect( add_query_arg( $redirect_args, $current_url ) );
			exit;
		}

		/* Log successful 2FA verification */
		NBUF_Audit_Log::log(
			$user_id,
			'2fa_verified',
			'success',
			'2FA verification successful',
			array(
				'method'    => $method,
				'code_type' => $code_type,
			)
		);

		/*
		 * Check if user wants to trust this device
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification on line 144
		$trust_device = isset( $_POST['trust_device'] ) && '1' === $_POST['trust_device'];
		if ( $trust_device ) {
			NBUF_2FA::trust_device( $user_id );

			/* Log device trust */
			NBUF_Audit_Log::log(
				$user_id,
				'2fa_device_trusted',
				'success',
				'Device marked as trusted for 2FA'
			);
		}

		/* Complete the login */
		self::complete_login( $user_id );
	}

	/**
	 * Complete login after successful 2FA
	 *
	 * Sets up the WordPress session and redirects to intended destination.
	 * Re-checks user status before completing login to prevent bypass.
	 *
	 * @param int $user_id User ID.
	 */
	private static function complete_login( $user_id ) {
		/*
		 * SECURITY: Re-verify user status before completing login.
		 * Status could have changed between initial auth and 2FA completion.
		 * Also prevents 2FA from being used to bypass verification requirements.
		 */
		$status_error = self::check_user_login_status( $user_id );
		if ( is_wp_error( $status_error ) ) {
			/* Clear 2FA transient since we're blocking login */
			self::clear_2fa_transient();

			/* Log the blocked attempt */
			NBUF_Audit_Log::log(
				$user_id,
				'login_blocked',
				'failure',
				'Login blocked after 2FA: ' . $status_error->get_error_code(),
				array( 'reason' => $status_error->get_error_message() )
			);

			/* Redirect to login page with error */
			$login_url = class_exists( 'NBUF_Universal_Router' )
				? NBUF_Universal_Router::get_url( 'login' )
				: wp_login_url();
			wp_safe_redirect( add_query_arg( 'nbuf_error', $status_error->get_error_code(), $login_url ) );
			exit;
		}

		/* Log the user in */
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		/* Clear 2FA transient and cookie */
		self::clear_2fa_transient();

		/* Log successful login */
		$user = get_userdata( $user_id );
		NBUF_Audit_Log::log(
			$user_id,
			'login_success',
			'success',
			'User logged in successfully with 2FA',
			array( 'username' => $user->user_login )
		);

		/*
		 * Fire login action.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- wp_login is a core WordPress hook.
		do_action( 'wp_login', $user->user_login, $user );

		/* Check if TOTP is required but user hasn't set it up */
		$redirect_to = self::maybe_redirect_to_totp_setup( $user_id );
		if ( $redirect_to ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		/* Determine redirect URL from plugin settings */
		$login_redirect_setting = NBUF_Options::get( 'nbuf_login_redirect', 'custom' );
		switch ( $login_redirect_setting ) {
			case 'admin':
				$redirect_to = admin_url();
				break;
			case 'home':
				$redirect_to = home_url( '/' );
				break;
			case 'custom':
			default:
				$custom_url  = NBUF_Options::get( 'nbuf_login_redirect_custom', '/nobloat-account' );
				$redirect_to = $custom_url ? home_url( $custom_url ) : home_url( '/nobloat-account' );
				break;
		}

		/*
		 * Allow override via redirect_to parameter.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Protected by nonce verification earlier.
		if ( isset( $_REQUEST['redirect_to'] ) && ! empty( $_REQUEST['redirect_to'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Protected by nonce verification earlier.
			$redirect_to = sanitize_text_field( wp_unslash( $_REQUEST['redirect_to'] ) );
		}

		/* Apply filter for customization */
		$redirect_to = apply_filters( 'nbuf_login_redirect', $redirect_to, $user );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Check if user needs to set up TOTP and redirect if so.
	 *
	 * @param  int $user_id User ID.
	 * @return string|false Redirect URL if TOTP setup required, false otherwise.
	 */
	private static function maybe_redirect_to_totp_setup( $user_id ) {
		/* Check if TOTP is required */
		$totp_method = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );
		$is_admin    = user_can( $user_id, 'manage_options' );

		/* Determine if TOTP is required for this user */
		$totp_required = false;
		if ( 'required_all' === $totp_method || 'required' === $totp_method ) {
			$totp_required = true;
		} elseif ( 'required_admin' === $totp_method && $is_admin ) {
			$totp_required = true;
		}

		if ( ! $totp_required ) {
			return false;
		}

		/* Check if user already has TOTP configured */
		$current_method = NBUF_2FA::get_user_method( $user_id );
		$has_totp       = ( 'totp' === $current_method || 'both' === $current_method );

		if ( $has_totp ) {
			return false; /* User already has TOTP set up */
		}

		/* Log that user is being redirected to TOTP setup */
		NBUF_Audit_Log::log(
			$user_id,
			'totp_setup_required',
			'info',
			'User redirected to TOTP setup - authenticator app is required'
		);

		/* Prefer Universal Router URL (respects base slug setting) */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( '2fa-setup' );
		}

		/* Fallback to legacy page */
		$setup_page_id = NBUF_Options::get( 'nbuf_page_totp_setup', 0 );
		if ( $setup_page_id ) {
			return get_permalink( $setup_page_id );
		}

		return false;
	}

	/**
	 * Get 2FA verification page URL.
	 *
	 * @return string URL of the 2FA verification page.
	 */
	private static function get_2fa_page_url() {
		/* Prefer Universal Router URL (respects base slug setting) */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( '2fa' );
		}

		/* Fallback to legacy page */
		$page_id = NBUF_Options::get( 'nbuf_page_2fa_verify', 0 );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}

		return home_url( '/2fa-verify/' );
	}

	/**
	 * Clear 2FA transient data
	 *
	 * Removes temporary transient and cookie used during 2FA process.
	 */
	public static function clear_2fa_transient() {
		/* Get token from cookie */
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

			/* Delete transient */
			delete_transient( 'nbuf_2fa_pending_' . $token );

			/* Delete cookie */
			setcookie(
				self::COOKIE_NAME,
				'',
				time() - 3600,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
		}
	}

	/**
	 * Get 2FA verification form HTML
	 *
	 * Returns the HTML for the 2FA verification form.
	 * Uses template from Templates tab for customization.
	 *
	 * @return string HTML output.
	 */
	public static function get_verification_form() {
		/* Get token from cookie */
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '<p class="nbuf-centered-message">' . esc_html__( 'No pending verification. Please log in first.', 'nobloat-user-foundry' ) . '</p>';
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		/* Get user data from transient */
		$pending_data = get_transient( 'nbuf_2fa_pending_' . $token );

		if ( false === $pending_data ) {
			return '<p class="nbuf-centered-message">' . esc_html__( 'Session expired. Please log in again.', 'nobloat-user-foundry' ) . '</p>';
		}

		$user_id = absint( $pending_data['user_id'] );

		/*
		 * Check if user wants to use backup code.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only mode parameter
		$backup_mode = isset( $_GET['backup'] ) && '1' === $_GET['backup'];

		/* If backup mode is requested, show backup code form */
		if ( $backup_mode ) {
			return self::get_backup_verification_form( $user_id );
		}

		/* Get method from transient (includes auto-required method) */
		$method = isset( $pending_data['method'] ) ? $pending_data['method'] : NBUF_2FA::get_user_method( $user_id );

		/* Check for TOTP setup required (email can be auto-applied, TOTP cannot) */
		if ( 'totp' === $method && ! NBUF_2FA::is_enabled( $user_id ) ) {
			$grace_days = NBUF_2FA::get_grace_period_remaining( $user_id );

			if ( $grace_days <= 0 ) {
				return '<div class="nbuf-2fa-setup-required">' .
				'<p><strong>' . esc_html__( '2FA Setup Required', 'nobloat-user-foundry' ) . '</strong></p>' .
				'<p>' . esc_html__( 'Two-factor authentication with an authenticator app is required for your account. Please set it up in your account settings to continue.', 'nobloat-user-foundry' ) . '</p>' .
				'</div>';
			}
		}

		/* Load template */
		$template = NBUF_Template_Manager::load_template( '2fa-verify' );

		/* Get instructions based on method */
		$instructions = '';
		if ( 'email' === $method ) {
			$instructions = __( 'A verification code has been sent to your email address. Enter it below to continue.', 'nobloat-user-foundry' );
		} elseif ( 'totp' === $method ) {
			$instructions = __( 'Enter the 6-digit code from your authenticator app.', 'nobloat-user-foundry' );
		} elseif ( 'both' === $method ) {
			$instructions = __( 'Enter the code from your authenticator app or email.', 'nobloat-user-foundry' );
		}

		/* Check for error */
		$error_message = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error message display
		$error_param = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		if ( ! empty( $error_param ) ) {
			$error_text = '';
			switch ( $error_param ) {
				case 'locked':
					$error_text = __( 'Too many failed attempts. Please try again later.', 'nobloat-user-foundry' );
					break;
				case 'expired':
					$error_text = __( 'Verification code has expired. Please request a new code.', 'nobloat-user-foundry' );
					break;
				case 'invalid':
				case '1':
				default:
					$error_text = __( 'Invalid code. Please try again.', 'nobloat-user-foundry' );
					break;
			}
			$error_message = '<div class="nbuf-error">' . esc_html( $error_text ) . '</div>';
		}

		/* Build device trust checkbox */
		$device_trust_checkbox = '';
		if ( NBUF_Options::get( 'nbuf_2fa_device_trust', true ) ) {
			$device_trust_checkbox = '<div class="nbuf-form-group nbuf-checkbox-group">' .
				'<label><input type="checkbox" name="trust_device" value="1"> ' .
				esc_html__( 'Trust this device for 30 days', 'nobloat-user-foundry' ) . '</label>' .
				'</div>';
		}

		/* Build resend email link */
		$resend_email_link = '';
		if ( 'email' === $method || 'both' === $method ) {
			$resend_email_link = '<a href="?resend=1" class="nbuf-resend-link">' . esc_html__( 'Resend code', 'nobloat-user-foundry' ) . '</a>';
		}

		/* Build backup code link */
		$backup_code_link = '';
		if ( NBUF_Options::get( 'nbuf_2fa_backup_enabled', true ) ) {
			$backup_codes = NBUF_User_2FA_Data::get_backup_codes( $user_id );
			if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
				$backup_url       = add_query_arg( 'backup', '1', self::get_2fa_page_url() );
				$backup_code_link = '<a href="' . esc_url( $backup_url ) . '" class="nbuf-backup-link">' . esc_html__( 'Use a backup code instead', 'nobloat-user-foundry' ) . '</a>';
			}
		}

		/* Get code length */
		$code_length = 'email' === $method
			? NBUF_Options::get( 'nbuf_2fa_email_code_length', 6 )
			: NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );

		/* Replace placeholders */
		$replacements = array(
			'{action_url}'            => '',
			'{nonce_field}'           => wp_nonce_field( 'nbuf_2fa_verify', 'nbuf_2fa_nonce', true, false ),
			'{error_message}'         => $error_message,
			'{success_message}'       => '',
			'{grace_period_notice}'   => '',
			'{locked_out_notice}'     => '',
			'{instructions_text}'     => esc_html( $instructions ),
			'{method}'                => esc_attr( $method ),
			'{code_length}'           => esc_attr( $code_length ),
			'{device_trust_checkbox}' => $device_trust_checkbox,
			'{resend_email_link}'     => $resend_email_link,
			'{backup_code_link}'      => $backup_code_link,
			'{help_text}'             => '',
		);

		$html = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		return $html;
	}

	/**
	 * Get backup code verification form HTML
	 *
	 * Returns the HTML for the backup code verification form.
	 * Uses template from Templates tab for customization.
	 *
	 * @param int $user_id User ID.
	 * @return string HTML output.
	 */
	private static function get_backup_verification_form( int $user_id ): string {
		/* Check if user has backup codes */
		if ( ! NBUF_Options::get( 'nbuf_2fa_backup_enabled', true ) ) {
			return '<p class="nbuf-centered-message">' . esc_html__( 'Backup codes are not enabled.', 'nobloat-user-foundry' ) . '</p>';
		}

		$backup_codes = NBUF_User_2FA_Data::get_backup_codes( $user_id );
		if ( ! is_array( $backup_codes ) || empty( $backup_codes ) ) {
			return '<p class="nbuf-centered-message">' . esc_html__( 'You do not have any backup codes available.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Load template */
		$template = NBUF_Template_Manager::load_template( '2fa-backup-verify' );

		/* Fallback template if loading fails */
		if ( empty( $template ) ) {
			$template = '<div class="nbuf-2fa-verify-wrapper nbuf-2fa-backup-verify-wrapper">
				<div class="nbuf-2fa-header"><h2>' . esc_html__( 'Use Backup Code', 'nobloat-user-foundry' ) . '</h2></div>
				{error_message}
				<div class="nbuf-2fa-instructions">' . esc_html__( 'Enter one of your backup codes to verify your identity.', 'nobloat-user-foundry' ) . '</div>
				<form method="post" class="nbuf-2fa-verify-form">
					{nonce_field}
					<input type="hidden" name="nbuf_2fa_verify" value="1">
					<input type="hidden" name="code_type" value="backup">
					<div class="nbuf-form-group">
						<label for="nbuf_backup_code">' . esc_html__( 'Backup Code', 'nobloat-user-foundry' ) . '</label>
						<input type="text" name="code" id="nbuf_backup_code" class="nbuf-2fa-input nbuf-backup-code-input" placeholder="' . esc_attr__( 'Enter backup code', 'nobloat-user-foundry' ) . '" maxlength="64" autocomplete="off" required autofocus>
					</div>
					{device_trust_checkbox}
					<div class="nbuf-form-actions">
						<button type="submit" class="nbuf-2fa-button nbuf-button-primary">' . esc_html__( 'Verify Backup Code', 'nobloat-user-foundry' ) . '</button>
					</div>
				</form>
				<div class="nbuf-2fa-help">{regular_verify_link}</div>
			</div>';
		}

		/* Check for error from URL parameter or REQUEST_URI */
		$error_message = '';
		$error_param   = '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only error message display
		if ( isset( $_GET['error'] ) ) {
			$error_param = sanitize_text_field( wp_unslash( $_GET['error'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		} else {
			/* Fallback: parse error from REQUEST_URI if $_GET is empty */
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( strpos( $request_uri, 'error=' ) !== false ) {
				$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );
				if ( $query_string ) {
					parse_str( $query_string, $query_params );
					if ( isset( $query_params['error'] ) ) {
						$error_param = sanitize_text_field( $query_params['error'] );
					}
				}
			}
		}

		if ( ! empty( $error_param ) ) {
			$error_text = __( 'Invalid backup code. Please try again.', 'nobloat-user-foundry' );

			if ( 'locked' === $error_param ) {
				$error_text = __( 'Too many failed attempts. Please try again later.', 'nobloat-user-foundry' );
			}

			$error_message = '<div class="nbuf-error">' . esc_html( $error_text ) . '</div>';
		}

		/* Build device trust checkbox */
		$device_trust_checkbox = '';
		if ( NBUF_Options::get( 'nbuf_2fa_device_trust', true ) ) {
			$device_trust_checkbox = '<div class="nbuf-form-group nbuf-checkbox-group">' .
				'<label><input type="checkbox" name="trust_device" value="1"> ' .
				esc_html__( 'Trust this device for 30 days', 'nobloat-user-foundry' ) . '</label>' .
				'</div>';
		}

		/* Build link back to regular verification */
		$regular_verify_url  = self::get_2fa_page_url();
		$regular_verify_link = '<a href="' . esc_url( $regular_verify_url ) . '" class="nbuf-regular-verify-link">' . esc_html__( 'Use regular verification instead', 'nobloat-user-foundry' ) . '</a>';

		/* Replace placeholders */
		$replacements = array(
			'{action_url}'            => '',
			'{nonce_field}'           => wp_nonce_field( 'nbuf_2fa_verify', 'nbuf_2fa_nonce', true, false ),
			'{error_message}'         => $error_message,
			'{success_message}'       => '',
			'{device_trust_checkbox}' => $device_trust_checkbox,
			'{regular_verify_link}'   => $regular_verify_link,
			'{help_text}'             => '',
		);

		$html = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		/*
		 * If error message exists but template doesn't have placeholder,
		 * inject it after the header div.
		 */
		if ( ! empty( $error_message ) && strpos( $html, 'nbuf-error' ) === false ) {
			$html = preg_replace(
				'/(<div class="nbuf-2fa-header">.*?<\/div>)/s',
				'$1' . "\n" . $error_message,
				$html,
				1
			);
		}

		return $html;
	}

	/**
	 * Check user login status before completing login.
	 *
	 * Verifies user is not disabled, expired, unverified, or pending approval.
	 * This prevents 2FA from being used to bypass these security checks.
	 *
	 * @param  int $user_id User ID.
	 * @return true|WP_Error True if user can login, WP_Error if blocked.
	 */
	private static function check_user_login_status( $user_id ) {
		/* Admins bypass all restrictions */
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		/* Check if user is disabled */
		if ( NBUF_User_Data::is_disabled( $user_id ) ) {
			return new WP_Error(
				'user_disabled',
				__( 'Your account has been disabled. Please contact the site administrator.', 'nobloat-user-foundry' )
			);
		}

		/* Check if user is expired */
		if ( NBUF_User_Data::is_expired( $user_id ) ) {
			return new WP_Error(
				'account_expired',
				__( 'Your account has expired. Please contact the site administrator.', 'nobloat-user-foundry' )
			);
		}

		/* Check if user is verified (only if verification is required) */
		$require_verification = NBUF_Options::get( 'nbuf_require_verification', true );
		if ( $require_verification && ! NBUF_User_Data::is_verified( $user_id ) ) {
			return new WP_Error(
				'nbuf_unverified',
				__( 'Your email address has not been verified. Please check your inbox for a verification link.', 'nobloat-user-foundry' )
			);
		}

		/* Check admin approval (if user requires it) */
		if ( NBUF_User_Data::requires_approval( $user_id ) && ! NBUF_User_Data::is_approved( $user_id ) ) {
			return new WP_Error(
				'awaiting_approval',
				__( 'Your account is pending administrator approval.', 'nobloat-user-foundry' )
			);
		}

		return true;
	}
}
