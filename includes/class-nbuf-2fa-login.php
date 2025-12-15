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
			time() + self::TRANSIENT_EXPIRATION,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true // httponly.
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
		/* Get 2FA verification page */
		$page_id = NBUF_Options::get( 'nbuf_page_2fa_verify', 0 );

		if ( $page_id ) {
			$redirect_url = get_permalink( $page_id );
		} else {
			/* Fallback to custom endpoint */
			$redirect_url = home_url( '/2fa-verify/' );
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

		/* Get submitted code */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification above.
		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( empty( $code ) ) {
			/* Redirect back with error for empty code */
			$current_url = remove_query_arg( 'error' );
			wp_safe_redirect( add_query_arg( 'error', '1', $current_url ) );
			exit;
		}

		$verified = false;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification above.
		$code_type = isset( $_POST['code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['code_type'] ) ) : 'auto';

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
			/* Log 2FA verification failure */
			NBUF_Audit_Log::log(
				$user_id,
				'2fa_failed',
				'failure',
				'2FA verification failed - incorrect code',
				array(
					'method'    => $method,
					'code_type' => $code_type,
				)
			);

			/* Redirect to current page with error - don't rely on referer */
			$current_url = remove_query_arg( 'error' );
			wp_safe_redirect( add_query_arg( 'error', '1', $current_url ) );
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
	 *
	 * @param int $user_id User ID.
	 */
	private static function complete_login( $user_id ) {
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

		/* Fire login action */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- wp_login is a core WordPress hook.
		do_action( 'wp_login', $user->user_login, $user );

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

		/* Allow override via redirect_to parameter */
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
			return '<div class="nbuf-2fa-verify-wrapper"><p>' . esc_html__( 'No pending verification. Please log in first.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		/* Get user data from transient */
		$pending_data = get_transient( 'nbuf_2fa_pending_' . $token );

		if ( false === $pending_data ) {
			return '<div class="nbuf-2fa-verify-wrapper"><p>' . esc_html__( 'Session expired. Please log in again.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		$user_id = absint( $pending_data['user_id'] );

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
		if ( '1' === $error_param ) {
			$error_message = '<div class="nbuf-error">' . esc_html__( 'Invalid code. Please try again.', 'nobloat-user-foundry' ) . '</div>';
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
				$backup_code_link = '<a href="#" id="nbuf-toggle-backup" class="nbuf-backup-link">' . esc_html__( 'Use a backup code instead', 'nobloat-user-foundry' ) . '</a>';
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

		/* Add backup code form if user has backup codes */
		if ( ! empty( $backup_code_link ) ) {
			$backup_form  = '<div id="nbuf-backup-form" style="display:none; margin-top: 20px;">';
			$backup_form .= '<form method="post" class="nbuf-2fa-verify-form">';
			$backup_form .= wp_nonce_field( 'nbuf_2fa_verify', 'nbuf_2fa_nonce', true, false );
			$backup_form .= '<input type="hidden" name="nbuf_2fa_verify" value="1">';
			$backup_form .= '<input type="hidden" name="code_type" value="backup">';
			$backup_form .= '<div class="nbuf-form-group">';
			$backup_form .= '<label for="nbuf_backup_code">' . esc_html__( 'Backup Code', 'nobloat-user-foundry' ) . '</label>';
			$backup_form .= '<input type="text" name="code" id="nbuf_backup_code" class="nbuf-2fa-input" ';
			$backup_form .= 'placeholder="Enter backup code" maxlength="64" autocomplete="off" required>';
			$backup_form .= '</div>';
			$backup_form .= '<button type="submit" class="nbuf-2fa-button nbuf-button-primary">' . esc_html__( 'Verify Backup Code', 'nobloat-user-foundry' ) . '</button>';
			$backup_form .= '</form>';
			$backup_form .= '</div>';
			$backup_form .= '<script>document.getElementById("nbuf-toggle-backup").addEventListener("click",function(e){e.preventDefault();var f=document.getElementById("nbuf-backup-form");f.style.display=f.style.display==="none"?"block":"none";this.textContent=f.style.display==="none"?"' . esc_js( __( 'Use a backup code instead', 'nobloat-user-foundry' ) ) . '":"' . esc_js( __( 'Use regular verification', 'nobloat-user-foundry' ) ) . '";});</script>';

			/* Insert before closing wrapper div */
			$html = str_replace( '</div>' . "\n" . '</div>', $backup_form . '</div>' . "\n" . '</div>', $html );
		}

		return $html;
	}
}
