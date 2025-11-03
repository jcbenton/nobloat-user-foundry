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

		/* Generate secure token and store user data in transient */
		$token = wp_generate_password( 32, false );
		set_transient(
			'nbuf_2fa_pending_' . $token,
			array(
				'user_id'   => $user->ID,
				'timestamp' => time(),
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

		/* Send email code if user has email 2FA */
		$method = NBUF_2FA::get_user_method( $user->ID );
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
		/* Check if this is a 2FA verification request */
		if ( ! isset( $_POST['nbuf_2fa_verify'] ) ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_verify' ) ) {
			return;
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

		/*
		 * Get submitted code
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification on line 144
		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( empty( $code ) ) {
			return;
		}

		$verified = false;
		$method   = NBUF_2FA::get_user_method( $user_id );
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification on line 144
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

			wp_safe_redirect( add_query_arg( 'error', '1', wp_get_referer() ) );
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
		do_action( 'wp_login', $user->user_login, $user );

		/* Redirect to admin dashboard or intended page */
		$redirect_to = admin_url();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Protected by nonce verification on line 145
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Protected by nonce verification on line 145
			$redirect_to = sanitize_text_field( wp_unslash( $_REQUEST['redirect_to'] ) );
		}

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
	 * Used by the shortcode.
	 *
	 * @return string HTML output.
	 */
	public static function get_verification_form() {
		/* Get token from cookie */
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '<p>' . esc_html__( 'No pending verification. Please log in first.', 'nobloat-user-foundry' ) . '</p>';
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		/* Get user data from transient */
		$pending_data = get_transient( 'nbuf_2fa_pending_' . $token );

		if ( false === $pending_data ) {
			return '<p>' . esc_html__( 'Session expired. Please log in again.', 'nobloat-user-foundry' ) . '</p>';
		}

		$user_id = absint( $pending_data['user_id'] );
		$method  = NBUF_2FA::get_user_method( $user_id );

		/* Check for setup required */
		if ( ! $method && NBUF_2FA::is_required( $user_id ) ) {
			$grace_days = NBUF_2FA::get_grace_period_remaining( $user_id );

			if ( $grace_days <= 0 ) {
				return '<div class="nbuf-2fa-setup-required">' .
				'<p><strong>' . esc_html__( '2FA Setup Required', 'nobloat-user-foundry' ) . '</strong></p>' .
				'<p>' . esc_html__( 'Two-factor authentication is required for your account. Please set it up to continue.', 'nobloat-user-foundry' ) . '</p>' .
				'</div>';
			}
		}

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

		/* Build form HTML */
		$html  = '<div class="nbuf-2fa-verify-wrapper">';
		$html .= '<h2>' . esc_html__( 'Two-Factor Authentication', 'nobloat-user-foundry' ) . '</h2>';
		$html .= $error_message;
		$html .= '<p class="nbuf-2fa-instructions">' . esc_html( $instructions ) . '</p>';
		$html .= '<form method="post" class="nbuf-2fa-verify-form">';
		$html .= wp_nonce_field( 'nbuf_2fa_verify', 'nbuf_2fa_nonce', true, false );
		$html .= '<input type="hidden" name="nbuf_2fa_verify" value="1">';
		$html .= '<div class="nbuf-form-group">';
		$html .= '<label for="nbuf_2fa_code">' . esc_html__( 'Verification Code', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="text" name="code" id="nbuf_2fa_code" class="nbuf-2fa-input" ';
		$html .= 'placeholder="000000" maxlength="8" pattern="[0-9A-Z]*" inputmode="numeric" ';
		$html .= 'autocomplete="one-time-code" required autofocus>';
		$html .= '</div>';

		/* Device trust checkbox */
		if ( NBUF_Options::get( 'nbuf_2fa_device_trust', true ) ) {
			$html .= '<div class="nbuf-form-group">';
			$html .= '<label><input type="checkbox" name="trust_device" value="1"> ';
			$html .= esc_html__( 'Trust this device for 30 days', 'nobloat-user-foundry' ) . '</label>';
			$html .= '</div>';
		}

		$html .= '<button type="submit" class="nbuf-2fa-button">' . esc_html__( 'Verify', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</form>';

		/* Resend email link if applicable */
		if ( 'email' === $method || 'both' === $method ) {
			$html .= '<p class="nbuf-2fa-resend"><a href="?resend=1">' . esc_html__( 'Resend code', 'nobloat-user-foundry' ) . '</a></p>';
		}

		$html .= '</div>';

		return $html;
	}
}
