<?php
/**
 * NoBloat User Foundry - Shortcodes
 *
 * Provides the single reset shortcode and a simple verify
 * page shortcode. Also handles POST for password resets.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes handler class.
 *
 * Manages all plugin shortcodes and form submissions.
 */
class NBUF_Shortcodes {

	/**
	 * Enqueue frontend CSS for a specific page type.
	 *
	 * Called directly from shortcodes to ensure CSS loads
	 * regardless of page ID settings.
	 *
	 * @param string $page_type Page type: 'login', 'registration', 'reset', 'account'.
	 */
	private static function enqueue_frontend_css( $page_type ) {
		/* Check if CSS loading is enabled */
		$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
		if ( ! $css_load_on_pages ) {
			return;
		}

		$css_files = array(
			'login'        => array( 'nbuf-login', 'login-page', 'nbuf_login_page_css', 'nbuf_css_write_failed_login' ),
			'registration' => array( 'nbuf-registration', 'registration-page', 'nbuf_registration_page_css', 'nbuf_css_write_failed_registration' ),
			'reset'        => array( 'nbuf-reset', 'reset-page', 'nbuf_reset_page_css', 'nbuf_css_write_failed_reset' ),
			'account'      => array( 'nbuf-account', 'account-page', 'nbuf_account_page_css', 'nbuf_css_write_failed_account' ),
			'2fa'          => array( 'nbuf-2fa', '2fa-setup', 'nbuf_2fa_page_css', 'nbuf_css_write_failed_2fa' ),
		);

		if ( ! isset( $css_files[ $page_type ] ) ) {
			return;
		}

		list( $handle, $filename, $db_option, $token_key ) = $css_files[ $page_type ];

		/* Only enqueue once per page type */
		if ( wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		NBUF_CSS_Manager::enqueue_css( $handle, $filename, $db_option, $token_key );
	}

	/**
	 * Check if user management system is enabled and return notice if not.
	 *
	 * @return string|false HTML notice if system disabled, false if enabled.
	 */
	private static function get_system_disabled_notice() {
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		if ( $system_enabled ) {
			return false;
		}

		// Only show detailed message to admins.
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="nbuf-message nbuf-message-error" style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px; margin: 20px auto; max-width: 500px; text-align: center; font-size: 16px;">'
				. '<strong>' . esc_html__( 'NoBloat User Foundry', 'nobloat-user-foundry' ) . ':</strong> '
				. esc_html__( 'The user management system is currently disabled. Enable it under NoBloat Foundry → User Settings → System → Status.', 'nobloat-user-foundry' )
				. '</div>';
		}

		// Non-admins see a clear message explaining the situation.
		return '<div class="nbuf-message nbuf-message-error" style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px; margin: 20px auto; max-width: 500px; text-align: center; font-size: 16px;">'
			. esc_html__( 'The NoBloat User Management system is not enabled. Please contact the site administrator.', 'nobloat-user-foundry' )
			. '</div>';
	}

	/**
	 * Initialize shortcodes and form handlers.
	 */
	public static function init() {
		/* Shortcodes */
		add_shortcode( 'nbuf_reset_form', array( __CLASS__, 'sc_reset_form' ) );
		add_shortcode( 'nbuf_request_reset_form', array( __CLASS__, 'sc_request_reset_form' ) );
		add_shortcode( 'nbuf_verify_page', array( __CLASS__, 'sc_verify_page' ) );
		add_shortcode( 'nbuf_login_form', array( __CLASS__, 'sc_login_form' ) );
		add_shortcode( 'nbuf_registration_form', array( __CLASS__, 'sc_registration_form' ) );
		add_shortcode( 'nbuf_account_page', array( __CLASS__, 'sc_account_page' ) );
		add_shortcode( 'nbuf_logout', array( __CLASS__, 'sc_logout' ) );
		add_shortcode( 'nbuf_2fa_verify', array( __CLASS__, 'sc_2fa_verify' ) );
		add_shortcode( 'nbuf_totp_setup', array( __CLASS__, 'sc_totp_setup' ) );

		/* Restriction shortcode (only if enabled) */
		$shortcode_enabled = NBUF_Options::get( 'nbuf_restrict_content_shortcode_enabled', false );
		if ( $shortcode_enabled ) {
			add_shortcode( 'nbuf_restrict', array( __CLASS__, 'sc_restrict' ) );
		}

		/* Profile shortcode (always register, checks setting internally) */
		add_shortcode( 'nbuf_profile', array( __CLASS__, 'sc_profile' ) );

		/* Universal page shortcode */
		add_shortcode( 'nbuf_universal', array( __CLASS__, 'sc_universal' ) );

		/* Handle POST submissions */
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_request_reset' ), 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_password_reset' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_login' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_registration' ), 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_account_actions' ), 3 );
	}

	/**
	 * Verification page shortcode [nbuf_verify_page].
	 *
	 * Handles both display and processing of email verification.
	 * If ?token= is present, delegates to verifier class.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public static function sc_verify_page( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for reset page (shares styles with verify) */
		self::enqueue_frontend_css( 'reset' );

		/*
		* If token is present, delegate to verifier for processing
		*/

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token verification handled by verifier class
		if ( ! empty( $_GET['token'] ) ) {
			$html = NBUF_Verifier::render_for_shortcode();
		} else {
			/* No token: show instructions */
			$html  = '<div class="nobloat-verify-wrapper" style="max-width:600px;margin:80px auto;text-align:center;">';
			$html .= '<h2>' . esc_html__( 'Email Verification', 'nobloat-user-foundry' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'Follow the verification link sent to your email to complete verification.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';
		}

		/* Check if policy panel should be displayed */
		$policy_enabled = NBUF_Options::get( 'nbuf_policy_verify_enabled', false );
		if ( $policy_enabled ) {
			$policy_position = NBUF_Options::get( 'nbuf_policy_verify_position', 'right' );
			$html            = self::wrap_with_policy_panel( $html, $policy_position, 'reset' );
		}

		return $html;
	}

	/**
	 * Password reset form shortcode [nbuf_reset_form].
	 *
	 * Render password reset form using template.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public static function sc_reset_form( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for reset page */
		self::enqueue_frontend_css( 'reset' );

		/* Check if password reset is enabled */
		$enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
		if ( ! $enable_password_reset ) {
			return '<div class="nbuf-info-message">' . esc_html__( 'Password reset is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* If user is already logged in, redirect to account security > password */
		if ( is_user_logged_in() ) {
			$redirect_url = self::get_account_url();
			/* Add tab/subtab parameters for security > password */
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$redirect_url = NBUF_Universal_Router::get_url( 'account', 'security' );
				$redirect_url = add_query_arg( 'subtab', 'password', $redirect_url );
			} else {
				$redirect_url = add_query_arg( array( 'tab' => 'security', 'subtab' => 'password' ), $redirect_url );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/* Get and validate reset key from URL */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL params for reset key validation.
		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		/* Require login and key parameters */
		if ( empty( $login ) || empty( $key ) ) {
			$forgot_url = self::get_forgot_password_url();
			return '<div class="nbuf-message nbuf-message-error">' .
				esc_html__( 'Invalid password reset link. Please request a new one.', 'nobloat-user-foundry' ) .
				( $forgot_url ? ' <a href="' . esc_url( $forgot_url ) . '">' . esc_html__( 'Request Password Reset', 'nobloat-user-foundry' ) . '</a>' : '' ) .
				'</div>';
		}

		/* Validate the reset key using WordPress function */
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			$forgot_url   = self::get_forgot_password_url();
			$error_code   = $user->get_error_code();
			$error_message = '';

			if ( 'expired_key' === $error_code ) {
				$error_message = esc_html__( 'This password reset link has expired. Please request a new one.', 'nobloat-user-foundry' );
			} elseif ( 'invalid_key' === $error_code ) {
				$error_message = esc_html__( 'This password reset link is invalid. Please request a new one.', 'nobloat-user-foundry' );
			} else {
				$error_message = esc_html__( 'This password reset link is no longer valid. Please request a new one.', 'nobloat-user-foundry' );
			}

			return '<div class="nbuf-message nbuf-message-error">' .
				$error_message .
				( $forgot_url ? ' <a href="' . esc_url( $forgot_url ) . '">' . esc_html__( 'Request Password Reset', 'nobloat-user-foundry' ) . '</a>' : '' ) .
				'</div>';
		}

		/* Enqueue WordPress password strength meter */
		if ( NBUF_Password_Validator::should_enforce( 'reset' ) ) {
			wp_enqueue_script( 'password-strength-meter' );
			wp_localize_script(
				'password-strength-meter',
				'pwsL10n',
				array(
					'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
					'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
					'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
					'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
					'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
					'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
				)
			);
		}

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'reset-form' );

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$action_url  = remove_query_arg(
			'_wpnonce',
			home_url(
				add_query_arg(
					array(
						'login'  => $login,
						'key'    => $key,
						'action' => 'rp',
					),
					wp_parse_url( $request_uri, PHP_URL_PATH )
				)
			)
		);

		/* Get error message from query string */
		$error_message = '';
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error         = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-reset-error">' . esc_html( urldecode( $error ) ) . '</div>';
		}

		/* Build password requirements list if enabled */
		$password_requirements = '';
		if ( NBUF_Password_Validator::should_enforce( 'reset' ) ) {
			$requirements = NBUF_Password_Validator::get_requirements_list();
			if ( ! empty( $requirements ) ) {
				$password_requirements  = '<div class="nbuf-password-requirements">';
				$password_requirements .= '<p class="nbuf-requirements-title">' . esc_html__( 'Password must include:', 'nobloat-user-foundry' ) . '</p>';
				$password_requirements .= '<ul class="nbuf-requirements-list">';
				foreach ( $requirements as $requirement ) {
					$password_requirements .= '<li>' . esc_html( $requirement ) . '</li>';
				}
				$password_requirements .= '</ul></div>';
			}
		}

		/* Get login URL */
		$login_url = self::get_login_url();

		/* Replace template placeholders */
		$replacements = array(
			'{action_url}'            => esc_url( $action_url ),
			'{nonce_field}'           => wp_nonce_field( 'nbuf_reset', 'nbuf_reset_nonce', true, false ),
			'{error_message}'         => $error_message,
			'{password_requirements}' => $password_requirements,
			'{login_url}'             => esc_url( $login_url ),
		);

		$html = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		/* Check if policy panel should be displayed */
		$policy_enabled = NBUF_Options::get( 'nbuf_policy_reset_enabled', false );
		if ( $policy_enabled ) {
			$policy_position = NBUF_Options::get( 'nbuf_policy_reset_position', 'right' );
			$html            = self::wrap_with_policy_panel( $html, $policy_position, 'reset' );
		}

		return $html;
	}

	/**
	 * ==========================================================
	 * [nbuf_request_reset_form]
	 * ----------------------------------------------------------
	 * Render form to request a password reset link.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_request_reset_form( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for reset page (shares same CSS) */
		self::enqueue_frontend_css( 'reset' );

		/* Check if password reset is enabled */
		$enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
		if ( ! $enable_password_reset ) {
			return '<div class="nbuf-info-message">' . esc_html__( 'Password reset is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* If user is already logged in, redirect to account security > password */
		if ( is_user_logged_in() ) {
			$redirect_url = self::get_account_url();
			/* Add tab/subtab parameters for security > password */
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$redirect_url = NBUF_Universal_Router::get_url( 'account', 'security' );
				$redirect_url = add_query_arg( 'subtab', 'password', $redirect_url );
			} else {
				$redirect_url = add_query_arg( array( 'tab' => 'security', 'subtab' => 'password' ), $redirect_url );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'request-reset-form' );

		/* Get success/error messages from query parameters */
		$success_message = '';
		$error_message   = '';

		if ( isset( $_GET['reset'] ) && 'sent' === $_GET['reset'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$success_message = '<div class="nbuf-message nbuf-message-success nbuf-reset-success">' . esc_html__( 'Password reset link has been sent to your email address.', 'nobloat-user-foundry' ) . '</div>';
		}

		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error         = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-reset-error">' . esc_html( urldecode( $error ) ) . '</div>';
		}

		/* Get login and registration URLs */
		$login_url = self::get_login_url();

		$register_link       = '';
		$enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
		if ( $enable_registration ) {
			$register_url = self::get_register_url();
			if ( $register_url ) {
				$register_link = '<a href="' . esc_url( $register_url ) . '" class="nbuf-request-reset-link">' . esc_html__( 'Create an Account', 'nobloat-user-foundry' ) . '</a>';
			}
		}

		/* Build action URL */
		$action_url = self::get_current_page_url();

		/* Replace template placeholders */
		$replacements = array(
			'{action_url}'      => esc_url( $action_url ),
			'{nonce_field}'     => wp_nonce_field( 'nbuf_request_reset', 'nbuf_request_reset_nonce', true, false ),
			'{error_message}'   => $error_message,
			'{success_message}' => $success_message,
			'{login_url}'       => esc_url( $login_url ),
			'{register_link}'   => $register_link,
		);

		$html = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		/* Check if policy panel should be displayed */
		$policy_enabled = NBUF_Options::get( 'nbuf_policy_request_reset_enabled', false );
		if ( $policy_enabled ) {
			$policy_position = NBUF_Options::get( 'nbuf_policy_request_reset_position', 'right' );
			$html            = self::wrap_with_policy_panel( $html, $policy_position, 'reset' );
		}

		return $html;
	}

	/**
	 * ==========================================================
	 * maybe_handle_request_reset()
	 * ----------------------------------------------------------
	 * Process POST from request reset form (forgot password).
	 * ==========================================================
	 */
	public static function maybe_handle_request_reset() {
		if ( is_admin() ) {
			return;
		}

		$page_id = NBUF_Options::get( 'nbuf_page_request_reset' );
		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		if ( empty( $_POST['nbuf_request_reset_action'] ) ) {
			return;
		}

		/*
		* Nonce verification
		*/
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified, not sanitized.
		if ( ! isset( $_POST['nbuf_request_reset_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nbuf_request_reset_nonce'] ), 'nbuf_request_reset' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( empty( $user_login ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Please enter your email address.', 'nobloat-user-foundry' ) ), get_permalink( $page_id ) ) );
			exit;
		}

		/*
		Rate limiting: 3 requests per 15 minutes per email.
		*/

		/*
		 * SECURITY: Use SHA-256 instead of MD5 for rate limiting identifier.
		 * MD5 is cryptographically broken and vulnerable to collision attacks.
		 * SHA-256 prevents attackers from finding collisions to bypass rate limits.
		 */
		$rate_identifier = hash( 'sha256', strtolower( $user_login ) );

		/* Atomically increment attempt counter */
		$attempts = NBUF_Transients::increment( 'password_reset_rate', $rate_identifier, 1, 15 * MINUTE_IN_SECONDS );

		if ( $attempts >= 3 ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Too many password reset attempts. Please try again later.', 'nobloat-user-foundry' ) ), get_permalink( $page_id ) ) );
			exit;
		}

		/* Get user by email */
		$user = get_user_by( 'email', $user_login );

		if ( ! $user ) {
			/* For security, show success message even if user not found */
			wp_safe_redirect( add_query_arg( 'reset', 'sent', get_permalink( $page_id ) ) );
			exit;
		}

		/* Generate password reset key */
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $key->get_error_message() ), get_permalink( $page_id ) ) );
			exit;
		}

		/* Build reset link */
		$reset_page_id = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
		if ( $reset_page_id ) {
			$reset_url = add_query_arg(
				array(
					'action' => 'rp',
					'key'    => $key,
					'login'  => rawurlencode( $user->user_login ),
				),
				get_permalink( $reset_page_id )
			);
		} else {
			$reset_url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );
		}

		/* Send email using template */
		$mode          = 'html'; // Default to HTML, can be made configurable later.
		$template_name = ( 'html' === $mode ) ? 'password-reset-html' : 'password-reset-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		/* Prepare replacements */
		$replacements = array(
			'{site_name}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'     => home_url(),
			'{display_name}' => sanitize_text_field( $user->display_name ),
			'{username}'     => sanitize_text_field( $user->user_login ),
			'{reset_link}'   => esc_url( $reset_url ),
		);

		/* Replace placeholders */
		$message = strtr( $template, $replacements );

		/*
		* Subject
		*/
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Password Reset', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		/* Set content type */
		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		/*
		Send email.
		*/
		/* SECURITY: Check if email sending succeeded */
		$sent = wp_mail( $user->user_email, $subject, $message );

		/* Remove filter */
		remove_filter( 'wp_mail_content_type', $content_type_callback );

		/*
		* Log email failures for admin visibility.
		*/
     // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf -- Intentional placeholder for future logging
		if ( ! $sent ) {
			/* Still continue - user may have other ways to reset */
		}

		/* Log password reset request */
		NBUF_Audit_Log::log(
			$user->ID,
			'password_reset_requested',
			'success',
			'Password reset link requested',
			array(
				'email'    => $user->user_email,
				'username' => $user->user_login,
			)
		);

		/* Redirect with success message */
		wp_safe_redirect( add_query_arg( 'reset', 'sent', get_permalink( $page_id ) ) );
		exit;
	}

	/**
	 * ==========================================================
	 * maybe_handle_password_reset()
	 * ----------------------------------------------------------
	 * Process POST from our reset form on the configured page.
	 * ==========================================================
	 */
	public static function maybe_handle_password_reset() {
		if ( is_admin() ) {
			return;
		}

		$page_id = NBUF_Options::get( 'nbuf_page_password_reset' );
		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		if ( empty( $_POST['nbuf_reset_action'] ) ) {
			return;
		}

		/*
		* Nonce
		*/
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified, not sanitized.
		if ( ! isset( $_POST['nbuf_reset_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nbuf_reset_nonce'] ), 'nbuf_reset' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( empty( $login ) || empty( $key ) ) {
			wp_die( esc_html__( 'Invalid reset link.', 'nobloat-user-foundry' ) );
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			wp_die( esc_html__( 'Invalid or expired reset key.', 'nobloat-user-foundry' ) );
		}

     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are not sanitized.
		$pass1 = isset( $_POST['pass1'] ) ? wp_unslash( $_POST['pass1'] ) : '';
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are not sanitized.
		$pass2 = isset( $_POST['pass2'] ) ? wp_unslash( $_POST['pass2'] ) : '';

		if ( '' === $pass1 || '' === $pass2 ) {
			wp_die( esc_html__( 'Password fields cannot be empty.', 'nobloat-user-foundry' ) );
		}
		if ( $pass1 !== $pass2 ) {
			wp_die( esc_html__( 'Passwords do not match.', 'nobloat-user-foundry' ) );
		}

		reset_password( $user, $pass1 );

		/* Log password reset completion */
		NBUF_Audit_Log::log(
			$user->ID,
			'password_reset_completed',
			'success',
			'Password reset completed successfully',
			array(
				'username' => $user->user_login,
				'method'   => 'reset_link',
			)
		);

		/* Success: redirect to login */
		wp_safe_redirect( self::get_login_url() );
		exit;
	}

	/**
	 * ==========================================================
	 * [nbuf_login_form]
	 * ----------------------------------------------------------
	 * Renders a customizable login form from database template.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_login_form( $atts = array() ) {
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for login page */
		self::enqueue_frontend_css( 'login' );

		/* If user is already logged in, redirect to the configured destination */
		if ( is_user_logged_in() ) {
			/* Determine redirect URL from settings */
			$login_redirect_setting = NBUF_Options::get( 'nbuf_login_redirect', 'account' );
			switch ( $login_redirect_setting ) {
				case 'account':
					$redirect_url = self::get_account_url();
					break;
				case 'admin':
					$redirect_url = admin_url();
					break;
				case 'home':
					$redirect_url = home_url( '/' );
					break;
				case 'custom':
					$custom_url   = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );
					$redirect_url = $custom_url ? home_url( $custom_url ) : self::get_account_url();
					break;
				default:
					$redirect_url = self::get_account_url();
					break;
			}

			/* Check for redirect_to parameter */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading redirect URL, no data modification.
			if ( isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] ) ) {
				$redirect_url = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Get template using Template Manager (custom table + caching).
		$template = NBUF_Template_Manager::load_template( 'login-form' );

		// Get current URL for form action.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$current_url = home_url( $request_uri );

		// Determine default redirect URL from settings.
		$login_redirect_setting = NBUF_Options::get( 'nbuf_login_redirect', 'account' );
		switch ( $login_redirect_setting ) {
			case 'account':
				$default_redirect = self::get_account_url();
				break;
			case 'admin':
				$default_redirect = admin_url();
				break;
			case 'home':
				$default_redirect = home_url( '/' );
				break;
			case 'custom':
				$custom_url       = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );
				$default_redirect = $custom_url ? home_url( $custom_url ) : self::get_account_url();
				break;
			default:
				$default_redirect = self::get_account_url();
				break;
		}

		// Get redirect URL from shortcode attribute or use settings default.
		$atts = shortcode_atts(
			array(
				'redirect' => $default_redirect,
			),
			$atts,
			'nbuf_login_form'
		);

		// Build action URL.
		$action_url = esc_url( $current_url );

		// Get password reset link (only if enabled).
		$reset_link            = '';
		$enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
		if ( $enable_password_reset ) {
			/* Use Universal Router URL if available */
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$reset_url = NBUF_Universal_Router::get_url( 'forgot-password' );
			} else {
				$reset_page_id = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
				$reset_url     = $reset_page_id ? get_permalink( $reset_page_id ) : '';
			}
			if ( $reset_url ) {
				$reset_link = '<a href="' . esc_url( $reset_url ) . '" class="nbuf-login-link">' . esc_html__( 'Forgot Password?', 'nobloat-user-foundry' ) . '</a>';
			}
		}

		// Build register link if registration is enabled.
		$register_link       = '';
		$enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
		if ( $enable_registration ) {
			/* Use Universal Router URL if available */
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$register_url = NBUF_Universal_Router::get_url( 'register' );
			} else {
				$registration_page_id = NBUF_Options::get( 'nbuf_page_registration', 0 );
				$register_url         = $registration_page_id ? get_permalink( $registration_page_id ) : '';
			}
			if ( $register_url ) {
				$register_link = '<a href="' . esc_url( $register_url ) . '" class="nbuf-login-link">' . esc_html__( 'Register', 'nobloat-user-foundry' ) . '</a>';
			}
		}

		// Get error message if present.
		$error_message = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for display purposes only
		if ( isset( $_GET['login'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for display purposes only
			$login_status = sanitize_text_field( wp_unslash( $_GET['login'] ) );
			switch ( $login_status ) {
				case 'failed':
					$error_message = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' . esc_html__( 'Invalid username or password.', 'nobloat-user-foundry' ) . '</div>';
					break;
				case 'blocked':
					$lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );
					$error_message    = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' .
						sprintf(
							/* translators: %d: number of minutes until lockout expires */
							esc_html__( 'Too many failed login attempts from this IP address. For security reasons, please wait %d minutes before trying again.', 'nobloat-user-foundry' ),
							$lockout_duration
						) . '</div>';
					break;
				case 'unverified':
					$error_message = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' . esc_html__( 'Please verify your email address before logging in.', 'nobloat-user-foundry' ) . '</div>';
					break;
				case 'disabled':
					$error_message = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' . esc_html__( 'Your account has been disabled. Please contact support.', 'nobloat-user-foundry' ) . '</div>';
					break;
				case 'expired':
					$error_message = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' . esc_html__( 'Your account has expired. Please contact support.', 'nobloat-user-foundry' ) . '</div>';
					break;
			}
		}

		// Generate nonce field.
		ob_start();
		wp_nonce_field( 'nbuf_login', 'nbuf_login_nonce', false );
		$nonce_field = ob_get_clean();

		// Replace placeholders in template.
		$html = str_replace(
			array(
				'{action_url}',
				'{nonce_field}',
				'{redirect_to}',
				'{reset_link}',
				'{register_link}',
				'{error_message}',
			),
			array(
				$action_url,
				$nonce_field,
				esc_attr( $atts['redirect'] ),
				$reset_link,
				$register_link,
				$error_message,
			),
			$template
		);

		/* Check if policy panel should be displayed */
		$policy_enabled = NBUF_Options::get( 'nbuf_policy_login_enabled', true );
		if ( $policy_enabled ) {
			$policy_position = NBUF_Options::get( 'nbuf_policy_login_position', 'right' );
			$html            = self::wrap_with_policy_panel( $html, $policy_position, 'login' );
		}

		return $html;
	}

	/**
	 * ==========================================================
	 * maybe_handle_login()
	 * ----------------------------------------------------------
	 * Process POST from login form.
	 * Checks verification status before allowing login.
	 * ==========================================================
	 */
	public static function maybe_handle_login() {
		if ( is_admin() ) {
			return;
		}

		// Check if this is a login form submission.
		if ( empty( $_POST['nbuf_login_action'] ) ) {
			return;
		}

		// Verify nonce.
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified, not sanitized.
		if ( ! isset( $_POST['nbuf_login_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nbuf_login_nonce'] ), 'nbuf_login' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		// Get credentials.
		$username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are not sanitized.
		$password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '';
		$remember = isset( $_POST['rememberme'] ) && 'forever' === $_POST['rememberme'];

		/*
		 * SECURITY: Prevent open redirect vulnerability with whitelist-based validation.
		 * Only allow redirects to internal URLs within the same domain.
		 * wp_validate_redirect() alone has known bypass vectors (protocol-relative URLs, punycode).
		 */
		$redirect = home_url();
		if ( isset( $_POST['redirect_to'] ) ) {
			$redirect_candidate = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );

			/* Parse URL components to validate host */
			$parsed       = wp_parse_url( $redirect_candidate );
			$current_host = wp_parse_url( home_url(), PHP_URL_HOST );

			/*
			 * Only allow internal redirects:
			 * - No host specified (relative URLs like /my-account)
			 * - Host matches current site domain
			 */
			if ( empty( $parsed['host'] ) || $parsed['host'] === $current_host ) {
				/* Additional validation with WordPress function */
				$redirect = wp_validate_redirect( esc_url_raw( $redirect_candidate ), home_url() );
			} else {
				/* External redirect attempted - log security event and block */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'open_redirect_blocked',
						'warning',
						'Blocked external redirect attempt during login',
						array(
							'attempted_url' => $redirect_candidate,
							'parsed_host'   => $parsed['host'] ?? 'none',
							'current_host'  => $current_host,
							'username'      => $username,
						)
					);
				}
				/* Fallback to home_url() for external redirects */
				$redirect = home_url();
			}
		}

		// Validate inputs.
		if ( empty( $username ) || empty( $password ) ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = home_url( $request_uri );
			wp_safe_redirect( add_query_arg( 'login', 'failed', $current_url ) );
			exit;
		}

		// Attempt authentication.
		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $creds, is_ssl() );

		// Check for authentication errors.
		if ( is_wp_error( $user ) ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = home_url( $request_uri );

			/* Check for specific error codes to provide better feedback */
			$error_code = $user->get_error_code();
			$login_status = 'failed'; // Default

			if ( 'too_many_attempts' === $error_code ) {
				$login_status = 'blocked';
			} elseif ( 'nbuf_unverified' === $error_code ) {
				$login_status = 'unverified';
			} elseif ( 'nbuf_disabled' === $error_code ) {
				$login_status = 'disabled';
			} elseif ( 'nbuf_expired' === $error_code ) {
				$login_status = 'expired';
			}

			wp_safe_redirect( add_query_arg( 'login', $login_status, $current_url ) );
			exit;
		}

		// User authenticated - now check verification/expiration status.
		// This integrates with existing NBUF_Hooks::check_login_verification.
		// which is already hooked into 'authenticate' filter.

		// If we got here, user is logged in and passed all checks.
		// Log successful login to audit log (only for non-2FA logins; 2FA logins are logged after verification).
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user->ID,
				'login_success',
				'success',
				'User logged in successfully',
				array( 'username' => $user->user_login )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * ==========================================================
	 * [nbuf_registration_form]
	 * ----------------------------------------------------------
	 * Renders a customizable registration form with dynamic fields.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_registration_form( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for registration page */
		self::enqueue_frontend_css( 'registration' );

		/* Check if registration is enabled */
		$enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
		if ( ! $enable_registration ) {
			return '<div class="nbuf-info-message">' . esc_html__( 'User registration is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* If user is already logged in, redirect to account page */
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_account_url() );
			exit;
		}

		/* Enqueue WordPress password strength meter */
		if ( NBUF_Password_Validator::should_enforce( 'registration' ) ) {
			wp_enqueue_script( 'password-strength-meter' );
			wp_localize_script(
				'password-strength-meter',
				'pwsL10n',
				array(
					'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
					'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
					'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
					'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
					'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
					'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
				)
			);
		}

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'registration-form' );

		/* Get settings */
		$login_url = self::get_login_url();

		/* Get success/error messages from query parameters */
		$success_message = '';
		$error_message   = '';

		if ( isset( $_GET['registration'] ) && 'success' === $_GET['registration'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$success_message = '<div class="nbuf-message nbuf-message-success nbuf-registration-success">' . esc_html__( 'Registration successful! Please check your email to verify your account.', 'nobloat-user-foundry' ) . '</div>';
		}

		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error         = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-registration-error">' . esc_html( urldecode( $error ) ) . '</div>';
		}

		/* Build username field HTML if needed */
		$username_field_html = '';
		if ( NBUF_Registration::should_show_username_field() ) {
			$username_value      = isset( $_POST['username'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['username'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$username_field_html = '
                <div class="nbuf-form-group">
                    <label class="nbuf-form-label nbuf-registration-label" for="nbuf_reg_username">' . esc_html__( 'Username', 'nobloat-user-foundry' ) . ' <span class="required">*</span></label>
                    <input type="text" id="nbuf_reg_username" name="username" class="nbuf-form-input nbuf-registration-input" required value="' . $username_value . '" autocomplete="username">
                </div>';
		}

		/* Build profile fields HTML based on enabled fields */
		$profile_fields_html = '';
		$enabled_fields      = NBUF_Registration::get_enabled_fields();
		$field_count         = count( $enabled_fields );

		/* Check if policy panel will be shown - if so, disable multi-column layout */
		$policy_enabled  = NBUF_Options::get( 'nbuf_policy_registration_enabled', true );
		$use_two_columns = $field_count > 5 && ! $policy_enabled;
		$field_items         = array();

		/* Textarea fields that should always span full width */
		$full_width_fields = array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' );

		foreach ( $enabled_fields as $field_key => $field_data ) {
			$field_value = isset( $_POST[ $field_key ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$required    = $field_data['required'] ? ' <span class="required">*</span>' : '';
			$req_attr    = $field_data['required'] ? ' required' : '';

			/* Determine input type based on field characteristics */
			$input_type    = self::get_field_input_type( $field_key );
			$is_full_width = in_array( $field_key, $full_width_fields, true );

			/* Text areas for long-form content */
			if ( $is_full_width ) {
				$field_html = sprintf(
					'<div class="nbuf-form-group nbuf-form-group-full">
                        <label class="nbuf-form-label nbuf-registration-label" for="nbuf_reg_%1$s">%2$s%3$s</label>
                        <textarea id="nbuf_reg_%1$s" name="%1$s" class="nbuf-form-input nbuf-form-textarea nbuf-registration-input"%4$s autocomplete="%1$s">%5$s</textarea>
                    </div>',
					esc_attr( $field_key ),
					esc_html( $field_data['label'] ),
					$required,
					$req_attr,
					esc_textarea( $field_value )
				);
			} else {
				$field_html = sprintf(
					'<div class="nbuf-form-group">
                        <label class="nbuf-form-label nbuf-registration-label" for="nbuf_reg_%1$s">%2$s%3$s</label>
                        <input type="%4$s" id="nbuf_reg_%1$s" name="%1$s" class="nbuf-form-input nbuf-registration-input"%5$s value="%6$s" autocomplete="%1$s">
                    </div>',
					esc_attr( $field_key ),
					esc_html( $field_data['label'] ),
					$required,
					esc_attr( $input_type ),
					$req_attr,
					$field_value
				);
			}

			$field_items[] = array(
				'html'       => $field_html,
				'full_width' => $is_full_width,
			);
		}

		/* Build final HTML - wrap in grid container if using two columns */
		if ( $use_two_columns && ! empty( $field_items ) ) {
			$profile_fields_html = '<div class="nbuf-form-grid">';
			foreach ( $field_items as $item ) {
				$profile_fields_html .= $item['html'];
			}
			$profile_fields_html .= '</div>';
		} else {
			foreach ( $field_items as $item ) {
				$profile_fields_html .= $item['html'];
			}
		}

		/* Replace placeholders */
		$email_value = isset( $_POST['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		/* Build dynamic password requirements text */
		$password_min_length      = absint( NBUF_Options::get( 'nbuf_password_min_length', 12 ) );
		$password_requirements    = array();
		$password_requirements[]  = sprintf(
			/* translators: %d: minimum password length */
			__( 'Minimum %d characters', 'nobloat-user-foundry' ),
			$password_min_length
		);

		/* Add character type requirements if password strength is enabled */
		if ( NBUF_Options::get( 'nbuf_password_requirements_enabled', true ) ) {
			if ( NBUF_Options::get( 'nbuf_password_require_uppercase', false ) ) {
				$password_requirements[] = __( 'uppercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_lowercase', false ) ) {
				$password_requirements[] = __( 'lowercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_numbers', false ) ) {
				$password_requirements[] = __( 'number', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_special', false ) ) {
				$password_requirements[] = __( 'special character', 'nobloat-user-foundry' );
			}
		}

		/* Format requirements text */
		if ( count( $password_requirements ) > 1 ) {
			$first_req               = array_shift( $password_requirements );
			$password_requirements_text = $first_req . '. ' . __( 'Must include:', 'nobloat-user-foundry' ) . ' ' . implode( ', ', $password_requirements );
		} else {
			$password_requirements_text = $password_requirements[0];
		}

		$replacements = array(
			'{action_url}'            => esc_url( self::get_current_page_url() ),
			'{nonce_field}'           => wp_nonce_field( 'nbuf_registration', 'nbuf_registration_nonce', true, false ),
			'{username_field}'        => $username_field_html,
			'{profile_fields}'        => $profile_fields_html,
			'{email_value}'           => $email_value,
			'{login_url}'             => esc_url( $login_url ),
			'{success_message}'       => $success_message,
			'{error_message}'         => $error_message,
			'{logged_in_message}'     => '',
			'{password_min_length}'   => esc_attr( $password_min_length ),
			'{password_requirements}' => esc_html( $password_requirements_text ),
		);

		foreach ( $replacements as $placeholder => $value ) {
			$template = str_replace( $placeholder, $value, $template );
		}

		/* Check if policy panel should be displayed */
		if ( $policy_enabled ) {
			$policy_position = NBUF_Options::get( 'nbuf_policy_registration_position', 'right' );
			$template        = self::wrap_with_policy_panel( $template, $policy_position, 'registration' );
		}

		return $template;
	}

	/**
	 * ==========================================================
	 * HANDLE REGISTRATION FORM SUBMISSION
	 * ----------------------------------------------------------
	 * Process registration form and create new user.
	 * ==========================================================
	 */
	public static function maybe_handle_registration() {
		if ( ! isset( $_POST['nbuf_register'] ) ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_registration_nonce'] ) ), 'nbuf_registration' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Don't allow registration if user is already logged in */
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_current_page_url() );
			exit;
		}

		/* Collect form data */
		$data = array();

		/* Always required */
		$data['email']            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$data['password']         = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data['password_confirm'] = isset( $_POST['password_confirm'] ) ? wp_unslash( $_POST['password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/* Username if user-entered mode */
		if ( NBUF_Registration::should_show_username_field() ) {
			$data['username'] = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		}

		/* Collect all profile fields */
		$all_fields = array(
			'first_name',
			'last_name',
			'phone',
			'company',
			'job_title',
			'address',
			'address_line1',
			'address_line2',
			'city',
			'state',
			'postal_code',
			'country',
			'bio',
			'website',
		);

		foreach ( $all_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( 'bio' === $field ) {
					$data[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
				} elseif ( 'website' === $field ) {
					$data[ $field ] = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
				} else {
					$data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}
			}
		}

		/* Attempt registration */
		$user_id = NBUF_Registration::register_user( $data );

		if ( is_wp_error( $user_id ) ) {
			/* Redirect back with error */
			$error_message = $user_id->get_error_message();
			$redirect_url  = add_query_arg( 'error', rawurlencode( $error_message ), self::get_current_page_url() );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/* Registration successful - redirect with success message */
		$redirect_url = add_query_arg( 'registration', 'success', self::get_current_page_url() );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * ==========================================================
	 * [nbuf_account_page]
	 * ----------------------------------------------------------
	 * Renders user account management page with profile editing
	 * and password change functionality.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Page HTML.
	 */
	public static function sc_account_page( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for account page */
		self::enqueue_frontend_css( 'account' );

		/* Require user to be logged in */
		if ( ! is_user_logged_in() ) {
			$login_url = self::get_login_url( self::get_current_page_url() );
			return '<div class="nbuf-message nbuf-message-info">' .
			sprintf(
			/* translators: 1: Opening link tag, 2: Closing link tag */
				esc_html__( 'Please %1$slog in%2$s to view your account.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( $login_url ) . '">',
				'</a>'
			) .
			'</div>';
		}

		/* Get current user data */
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;

		/* Enqueue WordPress password strength meter for password change */
		if ( NBUF_Password_Validator::should_enforce( 'profile_change' ) ) {
			wp_enqueue_script( 'password-strength-meter' );
			wp_localize_script(
				'password-strength-meter',
				'pwsL10n',
				array(
					'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
					'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
					'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
					'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
					'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
					'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
				)
			);
		}

		/* Enqueue profile photos script if profiles enabled */
		if ( NBUF_Options::get( 'nbuf_enable_profiles', false ) ) {
			wp_enqueue_script( 'nbuf-profile-photos', NBUF_PLUGIN_URL . 'assets/js/frontend/profile-photos.js', array( 'jquery' ), NBUF_VERSION, true );
			wp_localize_script(
				'nbuf-profile-photos',
				'NBUF_ProfilePhotos',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonces'   => array(
						'upload_profile' => wp_create_nonce( 'nbuf_upload_profile_photo' ),
						'upload_cover'   => wp_create_nonce( 'nbuf_upload_cover_photo' ),
						'delete_profile' => wp_create_nonce( 'nbuf_delete_profile_photo' ),
						'delete_cover'   => wp_create_nonce( 'nbuf_delete_cover_photo' ),
					),
					'i18n'     => array(
						'profile_uploaded'       => __( 'Profile photo uploaded successfully!', 'nobloat-user-foundry' ),
						'cover_uploaded'         => __( 'Cover photo uploaded successfully!', 'nobloat-user-foundry' ),
						'upload_failed'          => __( 'Upload failed.', 'nobloat-user-foundry' ),
						'upload_error'           => __( 'An error occurred during upload.', 'nobloat-user-foundry' ),
						'profile_deleted'        => __( 'Profile photo deleted.', 'nobloat-user-foundry' ),
						'cover_deleted'          => __( 'Cover photo deleted.', 'nobloat-user-foundry' ),
						'delete_failed'          => __( 'Delete failed.', 'nobloat-user-foundry' ),
						'confirm_delete_profile' => __( 'Are you sure you want to delete your profile photo?', 'nobloat-user-foundry' ),
						'confirm_delete_cover'   => __( 'Are you sure you want to delete your cover photo?', 'nobloat-user-foundry' ),
					),
				)
			);
		}

		/* Enqueue account page JavaScript (tabs, toast auto-dismiss) */
		wp_enqueue_script( 'nbuf-account-page', NBUF_PLUGIN_URL . 'assets/js/frontend/account-page.js', array(), NBUF_VERSION, true );

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'account-page' );

		/* Get flash message from transient (one-time display) */
		$messages = '';
		$flash    = self::get_flash_message( $user_id );
		if ( $flash ) {
			$class    = 'error' === $flash['type'] ? 'nbuf-message-error nbuf-account-error' : 'nbuf-message-success nbuf-account-success';
			$messages = '<div class="nbuf-message ' . esc_attr( $class ) . '">' . esc_html( $flash['message'] ) . '</div>';
		}

		/* Check for newly generated backup codes (special case - needs to display codes) */
		$backup_codes = get_transient( 'nbuf_backup_codes_' . $user_id );
		if ( $backup_codes && is_array( $backup_codes ) ) {
			delete_transient( 'nbuf_backup_codes_' . $user_id );
			$codes_text = implode( "\n", $backup_codes );
			$codes_id   = 'nbuf-backup-codes-' . wp_rand( 1000, 9999 );

			$messages .= '<div class="nbuf-backup-codes-display">';
			$messages .= '<h3>' . esc_html__( 'Your New Backup Codes', 'nobloat-user-foundry' ) . '</h3>';
			$messages .= '<p class="nbuf-backup-codes-warning">' . esc_html__( 'Save these codes in a safe place. They will only be shown once!', 'nobloat-user-foundry' ) . '</p>';
			$messages .= '<textarea id="' . esc_attr( $codes_id ) . '" class="nbuf-backup-codes-textarea" readonly rows="' . count( $backup_codes ) . '">' . esc_textarea( $codes_text ) . '</textarea>';
			$messages .= '<div class="nbuf-backup-codes-actions">';
			$messages .= '<button type="button" class="nbuf-button nbuf-button-secondary" onclick="navigator.clipboard.writeText(document.getElementById(\'' . esc_js( $codes_id ) . '\').value);this.textContent=\'' . esc_js( __( 'Copied!', 'nobloat-user-foundry' ) ) . '\';setTimeout(function(){document.querySelector(\'.nbuf-backup-codes-actions button:first-child\').textContent=\'' . esc_js( __( 'Copy All Codes', 'nobloat-user-foundry' ) ) . '\';},2000);">' . esc_html__( 'Copy All Codes', 'nobloat-user-foundry' ) . '</button>';
			$messages .= '<button type="button" class="nbuf-button nbuf-button-primary" onclick="document.querySelector(\'.nbuf-backup-codes-display\').remove();">' . esc_html__( 'Done', 'nobloat-user-foundry' ) . '</button>';
			$messages .= '</div>';
			$messages .= '<p><small>' . esc_html__( 'Each code can only be used once. Use them if you lose access to your primary 2FA method.', 'nobloat-user-foundry' ) . '</small></p>';
			$messages .= '</div>';
		}

		/* Get user verification and expiration status */
		$is_verified = NBUF_User_Data::is_verified( $user_id );
		$is_expired  = NBUF_User_Data::is_expired( $user_id );
		$expires_at  = NBUF_User_Data::get_expiration( $user_id );

		/* Build status badges */
		$status_badges = '';
		if ( $is_verified ) {
			$status_badges .= '<span class="nbuf-account-status nbuf-status-verified">' . esc_html__( 'Verified', 'nobloat-user-foundry' ) . '</span>';
		} else {
			$status_badges .= '<span class="nbuf-account-status nbuf-status-unverified">' . esc_html__( 'Unverified', 'nobloat-user-foundry' ) . '</span>';
		}

		if ( $is_expired ) {
			$status_badges .= '<span class="nbuf-account-status nbuf-status-expired">' . esc_html__( 'Expired', 'nobloat-user-foundry' ) . '</span>';
		}

		/* Build expiration info */
		$expiration_info = '';
		if ( $expires_at ) {
			$expiration_info  = '<div class="nbuf-info-label">' . esc_html__( 'Account Expires:', 'nobloat-user-foundry' ) . '</div>';
			$expiration_info .= '<div class="nbuf-info-value">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $expires_at ) ) ) . '</div>';
		}

		/* Get profile data */
		$profile_data = NBUF_Profile_Data::get( $user_id );

		/* Build profile fields HTML */
		$profile_fields_html = self::build_profile_fields_html( $current_user, $profile_data );

		/* Build Profile tab with sub-tabs */
		$profiles_enabled        = NBUF_Options::get( 'nbuf_enable_profiles', false );
		$gravatar_enabled        = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
		$cover_enabled           = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );
		$public_profiles_enabled = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
		$member_directory_enabled = NBUF_Options::get( 'nbuf_enable_member_directory', false );

		/* Determine what sub-tabs to show */
		$show_visibility    = $profiles_enabled && $public_profiles_enabled;
		$show_profile_photo = $profiles_enabled || $gravatar_enabled;
		$show_cover_photo   = $profiles_enabled && $cover_enabled;
		$show_directory     = $member_directory_enabled;

		/* Build Profile tab (primary tab with sub-tabs) */
		$profile_tab_button  = '';
		$profile_tab_content = '';

		/* Clear old visibility variables (no longer used in Account tab - kept for backward compatibility) */
		$visibility_section_inline = '';
		$visibility_section        = '';

		if ( $show_visibility || $show_profile_photo || $show_cover_photo || $show_directory ) {
			$profile_tab_button = '<button type="button" class="nbuf-tab-button" data-tab="profile">' . esc_html__( 'Profile', 'nobloat-user-foundry' ) . '</button>';

			/* Build sub-tab navigation */
			$subtabs      = array();
			$subtab_links = '';
			$first_subtab = '';

			/* Consolidate Visibility + Directory into Profile Settings */
			if ( $show_visibility || $show_directory ) {
				$subtabs['profile-settings'] = __( 'Profile Settings', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'profile-settings';
				}
			}
			if ( $show_profile_photo ) {
				$subtabs['profile-photo'] = __( 'Profile Photo', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'profile-photo';
				}
			}
			if ( $show_cover_photo ) {
				$subtabs['cover-photo'] = __( 'Cover Photo', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'cover-photo';
				}
			}

			/* Build sub-tab links */
			$subtab_count = 0;
			foreach ( $subtabs as $key => $label ) {
				$is_first     = ( $subtab_count === 0 );
				$subtab_links .= '<button type="button" class="nbuf-subtab-link' . ( $is_first ? ' active' : '' ) . '" data-subtab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
				++$subtab_count;
			}

			/* Generate nonce for profile tab */
			ob_start();
			wp_nonce_field( 'nbuf_account_profile_tab', 'nbuf_profile_tab_nonce', false );
			$profile_tab_nonce = ob_get_clean();

			/* Build sub-tab contents */
			$subtab_contents = '';

			/* Profile Settings sub-tab (consolidated Visibility + Directory) */
			if ( $show_visibility || $show_directory ) {
				ob_start();
				do_action( 'nbuf_account_profile_settings_subtab', $user_id );
				$profile_settings_html = ob_get_clean();
				$is_first         = ( $first_subtab === 'profile-settings' );
				$subtab_contents .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="profile-settings">';
				$subtab_contents .= '<form method="post" action="' . esc_url( self::get_current_page_url() ) . '" class="nbuf-account-form nbuf-profile-tab-form">';
				$subtab_contents .= $profile_tab_nonce;
				$subtab_contents .= '<input type="hidden" name="nbuf_account_action" value="update_profile_tab">';
				$subtab_contents .= '<input type="hidden" name="nbuf_active_tab" value="profile">';
				$subtab_contents .= '<input type="hidden" name="nbuf_active_subtab" value="profile-settings">';
				$subtab_contents .= '<input type="hidden" name="nbuf_directory_submitted" value="1">';
				$subtab_contents .= '<div class="nbuf-profile-subtab-section">' . $profile_settings_html . '</div>';
				$subtab_contents .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Save Settings', 'nobloat-user-foundry' ) . '</button>';
				$subtab_contents .= '</form>';
				$subtab_contents .= '</div>';
			}

			/* Profile Photo sub-tab */
			if ( $show_profile_photo ) {
				ob_start();
				do_action( 'nbuf_account_profile_photo_subtab', $user_id );
				$profile_photo_html = ob_get_clean();
				$is_first           = ( $first_subtab === 'profile-photo' );
				$subtab_contents   .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="profile-photo">';
				$subtab_contents   .= '<div class="nbuf-profile-subtab-section">' . $profile_photo_html . '</div>';
				$subtab_contents   .= '</div>';
			}

			/* Cover Photo sub-tab */
			if ( $show_cover_photo ) {
				ob_start();
				do_action( 'nbuf_account_cover_photo_subtab', $user_id );
				$cover_photo_html = ob_get_clean();
				$is_first         = ( $first_subtab === 'cover-photo' );
				$subtab_contents .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="cover-photo">';
				$subtab_contents .= '<div class="nbuf-profile-subtab-section">' . $cover_photo_html . '</div>';
				$subtab_contents .= '</div>';
			}

			/* Assemble Profile tab content */
			$profile_tab_content  = '<div class="nbuf-tab-content" data-tab="profile">';
			$profile_tab_content .= '<div class="nbuf-account-section">';
			$profile_tab_content .= '<div class="nbuf-subtabs">' . $subtab_links . '</div>';
			$profile_tab_content .= $subtab_contents;
			$profile_tab_content .= '</div>';
			$profile_tab_content .= '</div>';
		}

		/* Backward compatibility placeholders */
		$photos_tab_button  = $profile_tab_button;
		$photos_tab_content = $profile_tab_content;

		/* Build history tab content if version history is enabled AND user access is enabled */
		$history_tab_button  = '';
		$history_tab_content = '';
		$version_history_enabled      = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		$version_history_user_visible = NBUF_Options::get( 'nbuf_version_history_user_visible', false );
		if ( $version_history_enabled && $version_history_user_visible ) {
			/* Build history tab content directly */
			$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );

			ob_start();
			?>
			<div class="nbuf-account-section nbuf-vh-account">
				<?php if ( ! $allow_user_revert ) : ?>
					<div class="nbuf-message nbuf-message-info" style="margin-bottom: 20px;">
						<?php esc_html_e( 'View your profile change history below. Only administrators can restore previous versions.', 'nobloat-user-foundry' ); ?>
					</div>
				<?php endif; ?>
				<?php
				/* Include the version history viewer template - $user_id already defined at line 1153 */
				$context    = 'account';
				$can_revert = $allow_user_revert;
				include plugin_dir_path( __DIR__ ) . 'templates/version-history-viewer.php';
				?>
			</div>
			<?php
			$version_history_html = ob_get_clean();

			/* Enqueue version history assets */
			wp_enqueue_style(
				'nbuf-version-history',
				plugin_dir_url( __DIR__ ) . 'assets/css/admin/version-history.css',
				array(),
				'1.4.0'
			);
			wp_enqueue_script(
				'nbuf-version-history',
				plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js',
				array( 'jquery' ),
				'1.4.0',
				true
			);
			wp_localize_script(
				'nbuf-version-history',
				'NBUF_VersionHistory',
				array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'nbuf_version_history' ),
					'can_revert' => $allow_user_revert,
					'i18n'       => array(
						'registration'   => __( 'Registration', 'nobloat-user-foundry' ),
						'profile_update' => __( 'Profile Update', 'nobloat-user-foundry' ),
						'admin_update'   => __( 'Admin Update', 'nobloat-user-foundry' ),
						'import'         => __( 'Import', 'nobloat-user-foundry' ),
						'revert'         => __( 'Reverted', 'nobloat-user-foundry' ),
						'self'           => __( 'Self', 'nobloat-user-foundry' ),
						'admin'          => __( 'Admin', 'nobloat-user-foundry' ),
						'confirm_revert' => __( 'Are you sure you want to revert to this version? This will create a new version entry.', 'nobloat-user-foundry' ),
						'revert_success' => __( 'Profile reverted successfully.', 'nobloat-user-foundry' ),
						'revert_failed'  => __( 'Revert failed.', 'nobloat-user-foundry' ),
						'error'          => __( 'An error occurred.', 'nobloat-user-foundry' ),
						'before'         => __( 'Before:', 'nobloat-user-foundry' ),
						'after'          => __( 'After:', 'nobloat-user-foundry' ),
						'field'          => __( 'Field', 'nobloat-user-foundry' ),
						'before_value'   => __( 'Before', 'nobloat-user-foundry' ),
						'after_value'    => __( 'After', 'nobloat-user-foundry' ),
					),
				)
			);

			$history_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="history">' . esc_html__( 'History', 'nobloat-user-foundry' ) . '</button>';
			$history_tab_content = '<div class="nbuf-tab-content" data-tab="history">' . $version_history_html . '</div>';
		}

		/* Generate nonce fields */
		ob_start();
		wp_nonce_field( 'nbuf_account_profile', 'nbuf_account_nonce', false );
		$nonce_field = ob_get_clean();

		ob_start();
		wp_nonce_field( 'nbuf_account_password', 'nbuf_password_nonce', false );
		$nonce_field_password = ob_get_clean();

		ob_start();
		wp_nonce_field( 'nbuf_account_visibility', 'nbuf_visibility_nonce', false );
		$nonce_field_visibility = ob_get_clean();

		/* Build dynamic password requirements text */
		$password_min_length     = absint( NBUF_Options::get( 'nbuf_password_min_length', 12 ) );
		$password_requirements   = array();
		$password_requirements[] = sprintf(
			/* translators: %d: minimum password length */
			__( 'Minimum %d characters', 'nobloat-user-foundry' ),
			$password_min_length
		);

		/* Add character type requirements if password strength is enabled */
		if ( NBUF_Options::get( 'nbuf_password_requirements_enabled', true ) ) {
			if ( NBUF_Options::get( 'nbuf_password_require_uppercase', false ) ) {
				$password_requirements[] = __( 'uppercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_lowercase', false ) ) {
				$password_requirements[] = __( 'lowercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_numbers', false ) ) {
				$password_requirements[] = __( 'number', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_special', false ) ) {
				$password_requirements[] = __( 'special character', 'nobloat-user-foundry' );
			}
		}

		/* Format requirements text */
		if ( count( $password_requirements ) > 1 ) {
			$first_req                  = array_shift( $password_requirements );
			$password_requirements_text = $first_req . '. ' . __( 'Must include:', 'nobloat-user-foundry' ) . ' ' . implode( ', ', $password_requirements );
		} else {
			$password_requirements_text = $password_requirements[0];
		}

		/* Build security tab content (always shown - contains Password and optional 2FA) */
		$security_tab_html    = NBUF_2FA_Account::build_security_tab_html( $user_id, $password_requirements_text );
		$security_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="security">' . esc_html__( 'Security', 'nobloat-user-foundry' ) . '</button>';
		$security_tab_content = '<div class="nbuf-tab-content" data-tab="security">' . $security_tab_html . '</div>';

		/* Build policies tab content if enabled */
		$policies_tab_button  = '';
		$policies_tab_content = '';
		$policy_account_enabled = NBUF_Options::get( 'nbuf_policy_account_tab_enabled', false );
		if ( $policy_account_enabled ) {
			$policies_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="policies">' . esc_html__( 'Policies', 'nobloat-user-foundry' ) . '</button>';
			$policies_tab_content = '<div class="nbuf-tab-content" data-tab="policies"><div class="nbuf-account-section">' . self::get_policy_tab_content() . '</div></div>';
		}

		/* Build email tab if email changes are enabled */
		$email_tab_button   = '';
		$email_tab_content  = '';
		$allow_email_change = NBUF_Options::get( 'nbuf_allow_email_change', 'disabled' );
		if ( 'enabled' === $allow_email_change ) {
			ob_start();
			wp_nonce_field( 'nbuf_change_email', 'nbuf_email_nonce', false );
			$nonce_field_email = ob_get_clean();

			/* Check if email verification is required */
			$verify_email_change = NBUF_Options::get( 'nbuf_verify_email_change', true );
			$verification_notice = '';
			if ( $verify_email_change ) {
				$verification_notice = '
					<p class="description" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 20px;">
						<strong>' . esc_html__( 'Verification Required:', 'nobloat-user-foundry' ) . '</strong> ' .
						esc_html__( 'A verification link will be sent to your new email address. The change will not take effect until you click the link to confirm.', 'nobloat-user-foundry' ) . '
					</p>';
			}

			$email_tab_button = '<button type="button" class="nbuf-tab-button" data-tab="email">' . esc_html__( 'Email', 'nobloat-user-foundry' ) . '</button>';

			$email_tab_content = '
				<div class="nbuf-tab-content" data-tab="email">
					<div class="nbuf-account-section">
						' . $verification_notice . '
						<form method="post" action="' . esc_url( self::get_current_page_url() ) . '" class="nbuf-account-form nbuf-email-change-form">
							' . $nonce_field_email . '
							<input type="hidden" name="nbuf_account_action" value="change_email">
							<input type="hidden" name="nbuf_active_tab" value="email">
							<div class="nbuf-form-group">
								<label for="current_email" class="nbuf-form-label">' . esc_html__( 'Current Email', 'nobloat-user-foundry' ) . '</label>
								<input type="email" id="current_email" class="nbuf-form-input" value="' . esc_attr( $current_user->user_email ) . '" disabled>
							</div>
							<div class="nbuf-form-group">
								<label for="new_email" class="nbuf-form-label">' . esc_html__( 'New Email Address', 'nobloat-user-foundry' ) . '</label>
								<input type="email" id="new_email" name="new_email" class="nbuf-form-input" required>
							</div>
							<div class="nbuf-form-group">
								<label for="email_confirm_password" class="nbuf-form-label">' . esc_html__( 'Confirm Your Password', 'nobloat-user-foundry' ) . '</label>
								<input type="password" id="email_confirm_password" name="email_confirm_password" class="nbuf-form-input" required>
								<small class="nbuf-form-help">' . esc_html__( 'Enter your current password to confirm this change.', 'nobloat-user-foundry' ) . '</small>
							</div>
							<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Update Email', 'nobloat-user-foundry' ) . '</button>
						</form>
					</div>
				</div>';
		}

		/* Build profile photo section HTML - only show if user has a CUSTOM uploaded photo */
		$user_data             = NBUF_User_Data::get( $user_id );
		$has_custom_photo      = $user_data && ! empty( $user_data->profile_photo_url );
		$profile_photo_section = '';
		if ( $has_custom_photo ) {
			$profile_photo_url     = NBUF_Profile_Photos::get_profile_photo( $user_id, 200 );
			$profile_photo_section = '<div class="nbuf-account-photo"><img src="' . esc_url( $profile_photo_url ) . '" alt="' . esc_attr( $current_user->display_name ) . '"></div>';
		}

		/* Replace placeholders */
		$replacements = array(
			'{messages}'                     => $messages,
			'{profile_photo_section}'        => $profile_photo_section,
			'{status_badges}'                => $status_badges,
			'{username}'                     => esc_html( $current_user->user_login ),
			'{email}'                        => esc_html( $current_user->user_email ),
			'{registered_date}'              => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $current_user->user_registered ) ) ),
			'{expiration_info}'              => $expiration_info,
			'{action_url}'                   => esc_url( self::get_current_page_url() ),
			'{nonce_field}'                  => $nonce_field,
			'{nonce_field_password}'         => $nonce_field_password,
			'{nonce_field_visibility}'       => $nonce_field_visibility,
			'{profile_fields}'               => $profile_fields_html,
			'{visibility_section_inline}'    => $visibility_section_inline,
			'{photos_tab_button}'            => $photos_tab_button,
			'{photos_tab_content}'           => $photos_tab_content,
			'{email_tab_button}'             => $email_tab_button,
			'{email_tab_content}'            => $email_tab_content,
			/* Backward compatibility - old placeholders */
			'{visibility_section}'           => $visibility_section,
			'{visibility_section_wrapper}'   => '',
			'{photos_subtab_button}'         => '',
			'{photos_subtab_content}'        => '',
			'{profile_photo_subtab_button}'  => '',
			'{cover_photo_subtab_button}'    => '',
			'{profile_photo_subtab_content}' => '',
			'{cover_photo_subtab_content}'   => '',
			'{display_name}'                 => esc_html( $current_user->display_name ? $current_user->display_name : $current_user->user_login ),
			'{version_history_section}'      => '', /* Deprecated - moved to History tab */
			'{history_tab_button}'           => $history_tab_button,
			'{history_tab_content}'          => $history_tab_content,
			'{logout_url}'                   => esc_url( self::get_logout_url() ),
			'{password_requirements}'        => esc_html( $password_requirements_text ),
			'{security_tab_button}'          => $security_tab_button,
			'{security_tab_content}'         => $security_tab_content,
			'{policies_tab_button}'          => $policies_tab_button,
			'{policies_tab_content}'         => $policies_tab_content,
		);

		foreach ( $replacements as $placeholder => $value ) {
			$template = str_replace( $placeholder, $value, $template );
		}

		return $template;
	}

	/**
	 * ==========================================================
	 * BUILD PROFILE FIELDS HTML
	 * ----------------------------------------------------------
	 * Generates HTML for profile edit fields based on settings.
	 * ==========================================================
	 *
	 * @param  WP_User $user         User object.
	 * @param  array   $profile_data Profile data.
	 * @return string HTML output.
	 */
	private static function build_profile_fields_html( $user, $profile_data ) {
		/* Get enabled account profile fields */
		$enabled_fields    = NBUF_Profile_Data::get_account_fields();
		$field_registry    = NBUF_Profile_Data::get_field_registry();
		$custom_labels     = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
		$show_description  = NBUF_Options::get( 'nbuf_show_description_field', false );

		/* Build flat field labels array (defaults from registry) */
		$default_labels = array(
			'first_name'   => __( 'First Name', 'nobloat-user-foundry' ),
			'last_name'    => __( 'Last Name', 'nobloat-user-foundry' ),
			'display_name' => __( 'Public Display Name', 'nobloat-user-foundry' ),
			'user_url'     => __( 'Website', 'nobloat-user-foundry' ),
			'description'  => __( 'Biography', 'nobloat-user-foundry' ),
		);
		foreach ( $field_registry as $category_data ) {
			$default_labels = array_merge( $default_labels, $category_data['fields'] );
		}

		/* Count total fields (native WP fields + enabled fields) */
		$native_field_count = $show_description ? 5 : 4; /* first, last, display, url, (bio if enabled) */
		$field_count        = $native_field_count + count( $enabled_fields );

		/* Use two columns if more than 5 fields */
		$use_two_columns = $field_count > 5;

		/* Textarea fields that should always span full width */
		$full_width_fields = array( 'description', 'bio', 'professional_memberships', 'certifications', 'emergency_contact' );

		/* Collect field items */
		$field_items = array();

		/* Native WordPress fields */
		$first_name       = get_user_meta( $user->ID, 'first_name', true );
		$last_name        = get_user_meta( $user->ID, 'last_name', true );
		$description      = get_user_meta( $user->ID, 'description', true );
		$first_name_label = ! empty( $custom_labels['first_name'] ) ? $custom_labels['first_name'] : $default_labels['first_name'];
		$last_name_label  = ! empty( $custom_labels['last_name'] ) ? $custom_labels['last_name'] : $default_labels['last_name'];
		$display_name_label = ! empty( $custom_labels['display_name'] ) ? $custom_labels['display_name'] : $default_labels['display_name'];
		$user_url_label   = ! empty( $custom_labels['user_url'] ) ? $custom_labels['user_url'] : $default_labels['user_url'];
		$description_label = ! empty( $custom_labels['description'] ) ? $custom_labels['description'] : $default_labels['description'];

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="first_name" class="nbuf-form-label">' . esc_html( $first_name_label ) . '</label>
				<input type="text" id="first_name" name="first_name" class="nbuf-form-input" value="' . esc_attr( $first_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="last_name" class="nbuf-form-label">' . esc_html( $last_name_label ) . '</label>
				<input type="text" id="last_name" name="last_name" class="nbuf-form-input" value="' . esc_attr( $last_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="display_name" class="nbuf-form-label">' . esc_html( $display_name_label ) . '</label>
				<input type="text" id="display_name" name="display_name" class="nbuf-form-input" value="' . esc_attr( $user->display_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="user_url" class="nbuf-form-label">' . esc_html( $user_url_label ) . '</label>
				<input type="url" id="user_url" name="user_url" class="nbuf-form-input" value="' . esc_attr( $user->user_url ) . '">
			</div>',
			'full_width' => false,
		);

		/* Only show description/biography field if enabled in settings */
		if ( $show_description ) {
			$field_items[] = array(
				'html'       => '<div class="nbuf-form-group nbuf-form-group-full">
					<label for="description" class="nbuf-form-label">' . esc_html( $description_label ) . '</label>
					<textarea id="description" name="description" class="nbuf-form-input nbuf-form-textarea" rows="4">' . esc_textarea( $description ) . '</textarea>
				</div>',
				'full_width' => true,
			);
		}

		/* Add profile fields based on enabled settings */
		foreach ( $enabled_fields as $field_key ) {
			/* Use custom label if set, otherwise fall back to default */
			$label        = ! empty( $custom_labels[ $field_key ] ) ? $custom_labels[ $field_key ] : ( isset( $default_labels[ $field_key ] ) ? $default_labels[ $field_key ] : ucwords( str_replace( '_', ' ', $field_key ) ) );
			$value        = isset( $profile_data->$field_key ) ? $profile_data->$field_key : '';
			$input_type   = self::get_field_input_type( $field_key );
			$is_full_width = in_array( $field_key, $full_width_fields, true );

			if ( $is_full_width ) {
				$field_html = '<div class="nbuf-form-group nbuf-form-group-full">
					<label for="' . esc_attr( $field_key ) . '" class="nbuf-form-label">' . esc_html( $label ) . '</label>
					<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" class="nbuf-form-input nbuf-form-textarea">' . esc_textarea( $value ) . '</textarea>
				</div>';
			} else {
				$field_html = '<div class="nbuf-form-group">
					<label for="' . esc_attr( $field_key ) . '" class="nbuf-form-label">' . esc_html( $label ) . '</label>
					<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" class="nbuf-form-input" value="' . esc_attr( $value ) . '">
				</div>';
			}

			$field_items[] = array(
				'html'       => $field_html,
				'full_width' => $is_full_width,
			);
		}

		/* Build final HTML - wrap in grid container if using two columns */
		$html = '';
		if ( $use_two_columns && ! empty( $field_items ) ) {
			$html = '<div class="nbuf-form-grid">';
			foreach ( $field_items as $item ) {
				$html .= $item['html'];
			}
			$html .= '</div>';
		} else {
			foreach ( $field_items as $item ) {
				$html .= $item['html'];
			}
		}

		return $html;
	}

	/**
	 * ==========================================================
	 * HANDLE ACCOUNT PAGE ACTIONS
	 * ----------------------------------------------------------
	 * Process profile updates and password changes.
	 * ==========================================================
	 */
	public static function maybe_handle_account_actions() {
		if ( is_admin() ) {
			return;
		}

		/* Handle standard account actions */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in individual action handlers.
		if ( isset( $_POST['nbuf_account_action'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in individual action handlers.
			$action = sanitize_text_field( wp_unslash( $_POST['nbuf_account_action'] ) );

			if ( 'update_profile' === $action ) {
				self::handle_profile_update();
			} elseif ( 'change_password' === $action ) {
				self::handle_password_change();
			} elseif ( 'change_email' === $action ) {
				self::handle_email_change();
			} elseif ( 'update_visibility' === $action ) {
				self::handle_visibility_update();
			} elseif ( 'update_gravatar' === $action ) {
				self::handle_gravatar_update();
			} elseif ( 'update_profile_tab' === $action ) {
				self::handle_profile_tab_update();
			} elseif ( 'export_data' === $action ) {
				self::handle_data_export();
			}
		}

		/* Handle 2FA actions */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in individual action handlers.
		if ( isset( $_POST['nbuf_2fa_action'] ) ) {
			NBUF_2FA_Account::handle_actions();
		}
	}

	/**
	 * ==========================================================
	 * BUILD ACCOUNT REDIRECT URL
	 * ----------------------------------------------------------
	 * Build redirect URL preserving active tab/subtab state.
	 * ==========================================================
	 *
	 * @param  array $args Query args to add to URL.
	 * @return string Redirect URL with tab parameters.
	 */
	private static function build_account_redirect( $args = array() ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Tab/subtab values are only used for redirect URL, nonce verified by form handler.
		$tab    = '';
		$subtab = '';

		/* Get active tab from POST */
		if ( isset( $_POST['nbuf_active_tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_POST['nbuf_active_tab'] ) );
		}

		/* Get active subtab from POST */
		if ( isset( $_POST['nbuf_active_subtab'] ) ) {
			$subtab = sanitize_key( wp_unslash( $_POST['nbuf_active_subtab'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		/* For virtual pages, use path-based URLs */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$url = NBUF_Universal_Router::get_url( 'account', $tab );
			/* Subtab goes as query param since URL structure is /account/{tab}/ */
			if ( $subtab ) {
				$args['subtab'] = $subtab;
			}
		} else {
			/* For regular pages, use query params */
			$url = get_permalink();
			if ( $tab ) {
				$args['tab'] = $tab;
			}
			if ( $subtab ) {
				$args['subtab'] = $subtab;
			}
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * ==========================================================
	 * SET FLASH MESSAGE
	 * ----------------------------------------------------------
	 * Store a one-time message in transients for display on next page load.
	 * ==========================================================
	 *
	 * @param int    $user_id User ID.
	 * @param string $message Message text.
	 * @param string $type    Message type: 'success' or 'error'.
	 */
	public static function set_flash_message( $user_id, $message, $type = 'success' ) {
		set_transient(
			'nbuf_flash_' . $user_id,
			array(
				'message' => $message,
				'type'    => $type,
			),
			60 // Expire after 60 seconds (plenty of time for redirect).
		);
	}

	/**
	 * ==========================================================
	 * GET FLASH MESSAGE
	 * ----------------------------------------------------------
	 * Retrieve and delete a one-time flash message.
	 * ==========================================================
	 *
	 * @param  int $user_id User ID.
	 * @return array|false Message array with 'message' and 'type' keys, or false if none.
	 */
	private static function get_flash_message( $user_id ) {
		$flash = get_transient( 'nbuf_flash_' . $user_id );
		if ( $flash ) {
			delete_transient( 'nbuf_flash_' . $user_id );
			return $flash;
		}
		return false;
	}

	/**
	 * ==========================================================
	 * HANDLE PROFILE UPDATE
	 * ----------------------------------------------------------
	 * Process profile form submission.
	 * ==========================================================
	 */
	private static function handle_profile_update() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_account_nonce'] ) ), 'nbuf_account_profile' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id = get_current_user_id();

		/* Fire action before profile update (for version history, etc.) */
		do_action( 'nbuf_before_profile_update', $user_id );

		/* Update native WordPress user meta fields */
		if ( isset( $_POST['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) );
		}
		if ( isset( $_POST['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) );
		}
		if ( isset( $_POST['description'] ) ) {
			/* SECURITY: Enforce maximum length for biography (5000 chars) */
			$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );
			update_user_meta( $user_id, 'description', mb_substr( $description, 0, 5000 ) );
		}

		/* Update native WordPress user table fields (display_name, user_url) */
		$user_data = array( 'ID' => $user_id );
		if ( isset( $_POST['display_name'] ) ) {
			$user_data['display_name'] = sanitize_text_field( wp_unslash( $_POST['display_name'] ) );
		}
		if ( isset( $_POST['user_url'] ) ) {
			$user_data['user_url'] = esc_url_raw( wp_unslash( $_POST['user_url'] ) );
		}
		if ( count( $user_data ) > 1 ) {
			wp_update_user( $user_data );
		}

		/* Collect NoBloat profile fields */
		$profile_fields = array(
			'phone',
			'company',
			'job_title',
			'address',
			'address_line1',
			'address_line2',
			'city',
			'state',
			'postal_code',
			'country',
			'bio',
			'website',
		);

		$profile_data = array();

		foreach ( $profile_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( 'bio' === $field ) {
					/* SECURITY: Enforce maximum length for bio field (5000 chars) */
					$value                  = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
					$profile_data[ $field ] = mb_substr( $value, 0, 5000 );
				} elseif ( 'website' === $field ) {
					/* SECURITY: Enforce maximum length for URL (500 chars) */
					$value                  = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
					$profile_data[ $field ] = mb_substr( $value, 0, 500 );
				} else {
					/* SECURITY: Enforce maximum length for text fields (500 chars) */
					$value                  = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
					$profile_data[ $field ] = mb_substr( $value, 0, 500 );
				}
			}
		}

		/* Update profile data */
		if ( ! empty( $profile_data ) ) {
			NBUF_Profile_Data::update( $user_id, $profile_data );
		}

		/* Update profile visibility if submitted */
		if ( isset( $_POST['nbuf_profile_privacy'] ) ) {
			$privacy       = sanitize_key( wp_unslash( $_POST['nbuf_profile_privacy'] ) );
			$valid_options = array( 'public', 'members_only', 'private' );
			if ( ! in_array( $privacy, $valid_options, true ) ) {
				$privacy = 'private';
			}
			NBUF_User_Data::update( $user_id, array( 'profile_privacy' => $privacy ) );
		}

		/* PERFORMANCE: Invalidate user cache after profile update */
		if ( class_exists( 'NBUF_User' ) && method_exists( 'NBUF_User', 'invalidate_cache' ) ) {
			NBUF_User::invalidate_cache( $user_id );
		}

		/* Fire action for extensions (privacy settings, etc.) */
		do_action( 'nbuf_after_profile_update', $user_id, $_POST );

		/* Set flash message and redirect (preserving tab state) */
		self::set_flash_message( $user_id, __( 'Profile updated successfully!', 'nobloat-user-foundry' ), 'success' );
		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * ==========================================================
	 * HANDLE PASSWORD CHANGE
	 * ----------------------------------------------------------
	 * Process password change form submission.
	 * ==========================================================
	 */
	private static function handle_password_change() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_password_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_password_nonce'] ) ), 'nbuf_account_password' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id      = get_current_user_id();
		$current_user = get_userdata( $user_id );

		/* Get form data */
		$current_password = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_password     = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/* Validate current password */
		if ( ! wp_check_password( $current_password, $current_user->user_pass, $user_id ) ) {
			self::set_flash_message( $user_id, __( 'Current password is incorrect.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Validate password match */
		if ( $new_password !== $confirm_password ) {
			self::set_flash_message( $user_id, __( 'New passwords do not match.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* SECURITY: Validate password strength if enforced */
		if ( class_exists( 'NBUF_Password_Validator' ) && NBUF_Password_Validator::should_enforce( 'profile_change' ) ) {
			$validation = NBUF_Password_Validator::validate( $new_password, $user_id );
			if ( is_wp_error( $validation ) ) {
				self::set_flash_message( $user_id, $validation->get_error_message(), 'error' );
				wp_safe_redirect( self::build_account_redirect() );
				exit;
			}
		} elseif ( strlen( $new_password ) < 8 ) {
			/* Fallback: Basic length check if validator not available */
			self::set_flash_message( $user_id, __( 'New password must be at least 8 characters.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Update password */
		wp_set_password( $new_password, $user_id );

		/* Log password change */
		NBUF_Audit_Log::log(
			$user_id,
			'password_changed',
			'success',
			'User changed password via account page',
			array( 'method' => 'profile_update' )
		);

		/* Re-authenticate user */
		wp_set_auth_cookie( $user_id );

		/* Set flash message and redirect (preserving tab state) */
		self::set_flash_message( $user_id, __( 'Password changed successfully!', 'nobloat-user-foundry' ), 'success' );
		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * ==========================================================
	 * HANDLE EMAIL CHANGE
	 * ----------------------------------------------------------
	 * Process email change form submission.
	 * ==========================================================
	 */
	private static function handle_email_change() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_email_nonce'] ) ), 'nbuf_change_email' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		/* Check if email change is enabled */
		$allow_email_change = NBUF_Options::get( 'nbuf_allow_email_change', 'disabled' );
		if ( 'enabled' !== $allow_email_change ) {
			wp_die( esc_html__( 'Email changes are not allowed.', 'nobloat-user-foundry' ) );
		}

		$user_id      = get_current_user_id();
		$current_user = get_userdata( $user_id );

		/* Get form data */
		$new_email        = isset( $_POST['new_email'] ) ? sanitize_email( wp_unslash( $_POST['new_email'] ) ) : '';
		$confirm_password = isset( $_POST['email_confirm_password'] ) ? wp_unslash( $_POST['email_confirm_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/* Validate password */
		if ( ! wp_check_password( $confirm_password, $current_user->user_pass, $user_id ) ) {
			self::set_flash_message( $user_id, __( 'Password is incorrect.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Validate new email */
		if ( ! is_email( $new_email ) ) {
			self::set_flash_message( $user_id, __( 'Please enter a valid email address.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Check if email is the same */
		if ( strtolower( $new_email ) === strtolower( $current_user->user_email ) ) {
			self::set_flash_message( $user_id, __( 'New email must be different from your current email.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Check if email is already in use */
		if ( email_exists( $new_email ) ) {
			self::set_flash_message( $user_id, __( 'This email address is already registered to another account.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Store old email for notification */
		$old_email = $current_user->user_email;

		/* Update email */
		$result = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $new_email,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::set_flash_message( $user_id, $result->get_error_message(), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Send notification to old email */
		self::send_email_change_notification( $user_id, $old_email, $new_email );

		/* Log email change */
		NBUF_Audit_Log::log(
			$user_id,
			'email_changed',
			'success',
			'User changed email address via account page',
			array(
				'old_email' => $old_email,
				'new_email' => $new_email,
			)
		);

		/* Check if re-verification is required */
		$reverify_on_change = NBUF_Options::get( 'nbuf_reverify_on_email_change', false );
		if ( $reverify_on_change ) {
			/* Mark user as unverified */
			update_user_meta( $user_id, 'nbuf_verified', 0 );
			delete_user_meta( $user_id, 'nbuf_verified_date' );

			/* Generate and send new verification email */
			if ( class_exists( 'NBUF_Verifier' ) ) {
				NBUF_Verifier::send_verification_email( $user_id );
			}

			self::set_flash_message( $user_id, __( 'Email updated! Please check your new email to verify your account.', 'nobloat-user-foundry' ), 'success' );
		} else {
			self::set_flash_message( $user_id, __( 'Email address updated successfully!', 'nobloat-user-foundry' ), 'success' );
		}

		/* PERFORMANCE: Invalidate user cache */
		if ( class_exists( 'NBUF_User' ) && method_exists( 'NBUF_User', 'invalidate_cache' ) ) {
			NBUF_User::invalidate_cache( $user_id );
		}

		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * ==========================================================
	 * SEND EMAIL CHANGE NOTIFICATION
	 * ----------------------------------------------------------
	 * Send notification to old email when email is changed.
	 * ==========================================================
	 *
	 * @param int    $user_id   User ID.
	 * @param string $old_email Old email address.
	 * @param string $new_email New email address.
	 */
	private static function send_email_change_notification( $user_id, $old_email, $new_email ) {
		$user      = get_userdata( $user_id );
		$site_name = get_bloginfo( 'name' );

		/* Build email */
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Email Address Changed', 'nobloat-user-foundry' ),
			$site_name
		);

		$message = sprintf(
			/* translators: 1: display name, 2: site name, 3: new email address, 4: admin email */
			__(
				'Hi %1$s,

This is a notification that the email address associated with your account on %2$s has been changed.

Your new email address is: %3$s

If you did not make this change, please contact the site administrator immediately at %4$s.

Best regards,
%2$s',
				'nobloat-user-foundry'
			),
			$user->display_name ? $user->display_name : $user->user_login,
			$site_name,
			$new_email,
			get_option( 'admin_email' )
		);

		/* Send to old email */
		wp_mail( $old_email, $subject, $message );
	}

	/**
	 * ==========================================================
	 * HANDLE VISIBILITY UPDATE
	 * ----------------------------------------------------------
	 * Process profile visibility form submission.
	 * ==========================================================
	 */
	private static function handle_visibility_update() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_visibility_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_visibility_nonce'] ) ), 'nbuf_account_visibility' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id = get_current_user_id();

		/* Get and validate privacy setting */
		$privacy = isset( $_POST['nbuf_profile_privacy'] ) ? sanitize_key( wp_unslash( $_POST['nbuf_profile_privacy'] ) ) : 'private';
		$valid_options = array( 'public', 'members_only', 'private' );
		if ( ! in_array( $privacy, $valid_options, true ) ) {
			$privacy = 'private';
		}

		/* Update profile privacy */
		NBUF_User_Data::update( $user_id, array( 'profile_privacy' => $privacy ) );

		/* PERFORMANCE: Invalidate user cache after visibility update */
		if ( class_exists( 'NBUF_User' ) && method_exists( 'NBUF_User', 'invalidate_cache' ) ) {
			NBUF_User::invalidate_cache( $user_id );
		}

		/* Set flash message and redirect (preserving tab state) */
		self::set_flash_message( $user_id, __( 'Profile visibility updated!', 'nobloat-user-foundry' ), 'success' );
		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * ==========================================================
	 * HANDLE GRAVATAR UPDATE
	 * ----------------------------------------------------------
	 * Process gravatar preference form submission.
	 * ==========================================================
	 */
	private static function handle_gravatar_update() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_gravatar_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_gravatar_nonce'] ) ), 'nbuf_account_gravatar' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id = get_current_user_id();

		/* Get gravatar preference (checkbox: present = enabled, absent = disabled) */
		$use_gravatar = isset( $_POST['nbuf_use_gravatar'] ) ? 1 : 0;

		/* Update user data */
		NBUF_User_Data::update( $user_id, array( 'use_gravatar' => $use_gravatar ) );

		/* PERFORMANCE: Invalidate user cache after gravatar update */
		if ( class_exists( 'NBUF_User' ) && method_exists( 'NBUF_User', 'invalidate_cache' ) ) {
			NBUF_User::invalidate_cache( $user_id );
		}

		/* Set flash message and redirect (preserving tab state) */
		self::set_flash_message( $user_id, __( 'Gravatar setting updated!', 'nobloat-user-foundry' ), 'success' );
		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * ==========================================================
	 * HANDLE PROFILE TAB UPDATE
	 * ----------------------------------------------------------
	 * Process Profile tab form submission (visibility + photos).
	 * ==========================================================
	 */
	private static function handle_profile_tab_update() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_profile_tab_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_profile_tab_nonce'] ) ), 'nbuf_account_profile_tab' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id = get_current_user_id();

		/* Update profile visibility if submitted */
		if ( isset( $_POST['nbuf_profile_privacy'] ) ) {
			$privacy       = sanitize_key( wp_unslash( $_POST['nbuf_profile_privacy'] ) );
			$valid_options = array( 'public', 'members_only', 'private' );
			if ( ! in_array( $privacy, $valid_options, true ) ) {
				$privacy = 'private';
			}
			NBUF_User_Data::update( $user_id, array( 'profile_privacy' => $privacy ) );
		}

		/* Update visible fields preference (only if form section was submitted) */
		if ( isset( $_POST['nbuf_visible_fields_submitted'] ) ) {
			$visible_fields = array();
			if ( isset( $_POST['nbuf_visible_fields'] ) && is_array( $_POST['nbuf_visible_fields'] ) ) {
				$visible_fields = array_map( 'sanitize_key', wp_unslash( $_POST['nbuf_visible_fields'] ) );
			}
			NBUF_User_Data::update( $user_id, array( 'visible_fields' => maybe_serialize( $visible_fields ) ) );
		}

		/* Update gravatar preference if submitted */
		if ( isset( $_POST['nbuf_use_gravatar'] ) ) {
			$use_gravatar = absint( $_POST['nbuf_use_gravatar'] );
			NBUF_User_Data::update( $user_id, array( 'use_gravatar' => $use_gravatar ) );
		}

		/* Update directory listing preference if submitted */
		if ( isset( $_POST['nbuf_directory_submitted'] ) ) {
			$show_in_directory = isset( $_POST['nbuf_show_in_directory'] ) ? 1 : 0;
			NBUF_User_Data::update( $user_id, array( 'show_in_directory' => $show_in_directory ) );
		}

		/* PERFORMANCE: Invalidate user cache after profile tab update */
		if ( class_exists( 'NBUF_User' ) && method_exists( 'NBUF_User', 'invalidate_cache' ) ) {
			NBUF_User::invalidate_cache( $user_id );
		}

		/* Set flash message and redirect (preserving tab state) */
		self::set_flash_message( $user_id, __( 'Profile settings updated!', 'nobloat-user-foundry' ), 'success' );
		wp_safe_redirect( self::build_account_redirect() );
		exit;
	}

	/**
	 * Handle data export request from Privacy tab.
	 */
	private static function handle_data_export() {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_export_nonce'] ) ), 'nbuf_export_data' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_url() );
			exit;
		}

		$user_id = get_current_user_id();

		/* Check if GDPR export is available */
		if ( ! class_exists( 'NBUF_GDPR_Export' ) ) {
			self::set_flash_message( $user_id, __( 'Data export is not available.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Generate export file */
		$export_file = NBUF_GDPR_Export::generate_export( $user_id );

		if ( ! $export_file || ! file_exists( $export_file ) ) {
			self::set_flash_message( $user_id, __( 'Failed to generate export. Please try again.', 'nobloat-user-foundry' ), 'error' );
			wp_safe_redirect( self::build_account_redirect() );
			exit;
		}

		/* Log export */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'data_exported',
				'success',
				'User exported personal data',
				array( 'file_size' => size_format( filesize( $export_file ) ) )
			);
		}

		/* Send file for download */
		$filename = 'personal-data-export-' . gmdate( 'Y-m-d' ) . '.zip';

		/* Clean any output buffers that might corrupt the file */
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		/* Verify the file is a valid ZIP before sending */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading ZIP signature to verify file integrity.
		$fp = fopen( $export_file, 'rb' );
		if ( $fp ) {
			$signature = fread( $fp, 4 );
			fclose( $fp );
			/* Check for ZIP magic number (PK\x03\x04) */
			if ( 'PK' !== substr( $signature, 0, 2 ) ) {
				self::set_flash_message( $user_id, __( 'Export file generation failed. ZIP library may not be available.', 'nobloat-user-foundry' ), 'error' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup invalid export file.
				unlink( $export_file );
				wp_safe_redirect( self::build_account_redirect() );
				exit;
			}
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $export_file ) );
		header( 'Pragma: public' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming file to browser for download.
		readfile( $export_file );

		/* Clean up file after sending */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup temporary export file.
		unlink( $export_file );

		exit;
	}

	/**
	 * ==========================================================
	 * [nbuf_logout]
	 * ----------------------------------------------------------
	 * Handles user logout with configurable behavior
	 * (immediate or confirmation) and redirect options.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Logout HTML or redirect.
	 */
	public static function sc_logout( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Get settings */
		$logout_behavior = NBUF_Options::get( 'nbuf_logout_behavior', 'immediate' );
		$logout_redirect = NBUF_Options::get( 'nbuf_logout_redirect', 'home' );
		$custom_url      = NBUF_Options::get( 'nbuf_logout_redirect_custom', '' );

		/* Determine redirect URL */
		$redirect_url = home_url( '/' );
		if ( 'login' === $logout_redirect ) {
			$redirect_url = self::get_login_url();
		} elseif ( 'custom' === $logout_redirect && ! empty( $custom_url ) ) {
			/* Support both FQDNs and relative paths */
			if ( filter_var( $custom_url, FILTER_VALIDATE_URL ) ) {
				$redirect_url = esc_url_raw( $custom_url );
			} else {
				/* Relative path - make sure it starts with / */
				$custom_url   = ltrim( $custom_url, '/' );
				$redirect_url = home_url( '/' . $custom_url );
			}
		}

		/* If not logged in, just redirect to the configured destination */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/*
		* Handle logout action.
		* SECURITY: Sanitize GET parameters before nonce verification.
		*/
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( 'logout' === $action && $nonce && wp_verify_nonce( $nonce, 'nbuf-logout' ) ) {
			wp_logout();
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/* Generate logout URL with nonce */
		$logout_url = add_query_arg(
			array(
				'action'   => 'logout',
				'_wpnonce' => wp_create_nonce( 'nbuf-logout' ),
			),
			self::get_current_page_url()
		);

		/* Immediate logout - auto-submit form or redirect */
		if ( 'immediate' === $logout_behavior ) {
			/* Auto-redirect approach */
			wp_safe_redirect( $logout_url );
			exit;
		}

		/* Confirmation logout - show confirmation form */
		$current_user = wp_get_current_user();

		ob_start();
		?>
		<div class="nbuf-logout-wrapper">
			<div class="nbuf-logout-confirmation">
				<h2 class="nbuf-logout-title"><?php esc_html_e( 'Confirm Logout', 'nobloat-user-foundry' ); ?></h2>
				<p class="nbuf-logout-message">
		<?php
		printf(
		/* translators: %s: user display name */
			esc_html__( 'You are currently logged in as %s.', 'nobloat-user-foundry' ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>'
		);
		?>
				</p>
				<p><?php esc_html_e( 'Are you sure you want to log out?', 'nobloat-user-foundry' ); ?></p>

				<form method="get" class="nbuf-logout-form">
					<input type="hidden" name="action" value="logout">
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'nbuf-logout' ) ); ?>">
					<button type="submit" class="nbuf-button nbuf-button-primary">
		<?php esc_html_e( 'Yes, Log Me Out', 'nobloat-user-foundry' ); ?>
					</button>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nbuf-button nbuf-button-secondary">
		<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
					</a>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * ==========================================================
	 * [nbuf_2fa_verify]
	 * ----------------------------------------------------------
	 * Two-Factor Authentication verification page.
	 * Displays form for entering 2FA code during login.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string 2FA verification form HTML.
	 */
	public static function sc_2fa_verify( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for 2FA pages */
		self::enqueue_frontend_css( '2fa' );

		/* Check if 2FA_Login class exists */
		if ( ! class_exists( 'NBUF_2FA_Login' ) ) {
			return '<p>' . esc_html__( '2FA system is not available.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Let the login class handle the verification form */
		return NBUF_2FA_Login::get_verification_form();
	}

	/**
	 * ==========================================================
	 * [nbuf_totp_setup]
	 * ----------------------------------------------------------
	 * Authenticator App (TOTP) setup page.
	 * Dedicated page for setting up TOTP authentication.
	 * Used when Authenticator 2FA is required.
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string TOTP setup page HTML.
	 */
	public static function sc_totp_setup( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for 2FA pages */
		self::enqueue_frontend_css( '2fa' );

		/* Check if user is logged in */
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to set up authenticator app.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Check if 2FA classes exist */
		if ( ! class_exists( 'NBUF_2FA' ) || ! class_exists( 'NBUF_TOTP' ) || ! class_exists( 'NBUF_QR_Code' ) ) {
			return '<p>' . esc_html__( 'Authenticator system is not available.', 'nobloat-user-foundry' ) . '</p>';
		}

		$user_id        = get_current_user_id();
		$current_method = NBUF_2FA::get_user_method( $user_id );

		/* Check if TOTP is available */
		$totp_method    = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );
		$totp_available = in_array( $totp_method, array( 'optional_all', 'required_all', 'required_admin', 'user_configurable', 'required' ), true );

		if ( ! $totp_available ) {
			return '<p style="text-align: center; margin: 80px auto;">' . esc_html__( 'Authenticator app 2FA is not enabled on this site.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Check if user already has TOTP configured */
		$totp_active = in_array( $current_method, array( 'totp', 'both' ), true );

		ob_start();
		?>
		<div class="nbuf-totp-setup-page">
			<?php if ( $totp_active ) : ?>
				<div class="nbuf-success">
					<p><?php esc_html_e( 'Your authenticator app is already configured and active.', 'nobloat-user-foundry' ); ?></p>
				</div>
				<p><?php esc_html_e( 'You can manage your 2FA settings from your account page.', 'nobloat-user-foundry' ); ?></p>
				<?php
				$account_page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
				if ( $account_page_id ) :
					?>
					<p><a href="<?php echo esc_url( get_permalink( $account_page_id ) ); ?>" class="nbuf-button nbuf-button-primary"><?php esc_html_e( 'Go to Account', 'nobloat-user-foundry' ); ?></a></p>
				<?php endif; ?>
			<?php else : ?>
				<?php
				/* Check if this is a required setup (redirect scenario) */
				$totp_required = in_array( $totp_method, array( 'required_all', 'required_admin', 'required' ), true );
				$is_admin      = current_user_can( 'manage_options' );
				$must_setup    = 'required_all' === $totp_method || ( 'required_admin' === $totp_method && $is_admin );

				if ( $must_setup ) :
					/* Check grace period */
					$grace_days  = absint( NBUF_Options::get( 'nbuf_2fa_totp_grace_period', 7 ) );
					$grace_start = get_user_meta( $user_id, 'nbuf_totp_grace_start', true );

					if ( ! $grace_start ) {
						/* First time on setup page - start grace period */
						update_user_meta( $user_id, 'nbuf_totp_grace_start', time() );
						$grace_start = time();
					}

					$grace_end   = $grace_start + ( $grace_days * DAY_IN_SECONDS );
					$days_left   = max( 0, ceil( ( $grace_end - time() ) / DAY_IN_SECONDS ) );
					$grace_ended = time() > $grace_end;
					?>
					<div class="nbuf-warning">
						<?php if ( $grace_ended ) : ?>
							<p><strong><?php esc_html_e( 'Authenticator app setup is required.', 'nobloat-user-foundry' ); ?></strong></p>
							<p><?php esc_html_e( 'Your grace period has expired. Please complete setup to continue using your account.', 'nobloat-user-foundry' ); ?></p>
						<?php else : ?>
							<p><strong><?php esc_html_e( 'Authenticator app setup is required.', 'nobloat-user-foundry' ); ?></strong></p>
							<p>
							<?php
							/* translators: %d: number of days remaining */
							printf( esc_html__( 'You have %d day(s) remaining to complete setup.', 'nobloat-user-foundry' ), $days_left );
							?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				/* Generate new secret for setup */
				$secret   = NBUF_TOTP::generate_secret();
				$username = wp_get_current_user()->user_email;
				$issuer   = get_bloginfo( 'name' );
				$uri      = NBUF_TOTP::get_provisioning_uri( $secret, $username, $issuer );
				$qr_code  = NBUF_QR_Code::generate( $uri, NBUF_Options::get( 'nbuf_2fa_totp_qr_size', 200 ) );

				/* Load template and replace placeholders directly (avoids shortcode quote issues) */
				$template = NBUF_Template_Manager::load_template( '2fa-setup-totp' );

				/* Get action URL for Universal Router */
				$action_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
					? NBUF_Universal_Router::get_url( '2fa-setup' )
					: get_permalink();

				/* Generate nonce field */
				ob_start();
				wp_nonce_field( 'nbuf_2fa_setup_totp', 'nbuf_2fa_nonce' );
				$nonce_field = ob_get_clean();

				/* Replace placeholders */
				$replacements = array(
					'{qr_code}'         => $qr_code,
					'{secret_key}'      => esc_attr( $secret ),
					'{account_name}'    => esc_html( $username ),
					'{action_url}'      => esc_url( $action_url ),
					'{nonce_field}'     => $nonce_field,
					'{success_message}' => '',
					'{error_message}'   => '',
				);
				$template = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

				echo $template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template already escaped
				?>
			<?php endif; ?>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * ==========================================================
	 * [nbuf_restrict]
	 * ----------------------------------------------------------
	 * Restrict content sections based on login status, roles,
	 * verification status, and expiration status.
	 *
	 * Attributes:
	 * - role: Comma-separated list of roles (e.g., "administrator,editor")
	 * - logged_in: "yes" or "no"
	 * - verified: "yes" or "no" (email verification)
	 * - expired: "yes" or "no" (account expiration)
	 * - message: Custom message for unauthorized users
	 *
	 * Examples:
	 * [nbuf_restrict logged_in="yes"]Content for logged-in users[/nbuf_restrict]
	 * [nbuf_restrict role="subscriber,customer"]VIP Content[/nbuf_restrict]
	 * [nbuf_restrict verified="yes" message="Please verify your email"]...[/nbuf_restrict]
	 * ==========================================================
	 *
	 * @param  array  $atts    Shortcode attributes.
	 * @param  string $content Content to restrict.
	 * @return string Restricted or allowed content.
	 */
	public static function sc_restrict( $atts, $content = '' ) {
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Parse attributes */
		$atts = shortcode_atts(
			array(
				'role'      => '',
				'logged_in' => '',
				'verified'  => '',
				'expired'   => '',
				'message'   => __( 'This content is restricted.', 'nobloat-user-foundry' ),
			),
			$atts,
			'nbuf_restrict'
		);

		/* Get current user */
		$user         = wp_get_current_user();
		$user_id      = $user->ID;
		$is_logged_in = is_user_logged_in();

		/* Check logged_in requirement */
		if ( ! empty( $atts['logged_in'] ) ) {
			$required_logged_in = filter_var( $atts['logged_in'], FILTER_VALIDATE_BOOLEAN );
			if ( $required_logged_in && ! $is_logged_in ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
			if ( ! $required_logged_in && $is_logged_in ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
		}

		/* Check role requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['role'] ) ) {
			$required_roles = array_map( 'trim', explode( ',', $atts['role'] ) );
			$user_roles     = $user->roles;

			/* Check if user has any of the required roles */
			$has_role = ! empty( array_intersect( $user_roles, $required_roles ) );
			if ( ! $has_role ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
		}

		/* Check verified requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['verified'] ) ) {
			$required_verified = filter_var( $atts['verified'], FILTER_VALIDATE_BOOLEAN );
			$user_data         = NBUF_User_Data::get( $user_id );

			/* SECURITY: Check if user_data exists */
			if ( ! $user_data ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html__( 'User data not available.', 'nobloat-user-foundry' ) . '</p></div>';
			}

			$is_verified = 1 === (int) $user_data->is_verified;

			if ( $required_verified && ! $is_verified ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
			if ( ! $required_verified && $is_verified ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
		}

		/* Check expired requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['expired'] ) ) {
			$required_not_expired = ! filter_var( $atts['expired'], FILTER_VALIDATE_BOOLEAN );
			$user_data            = NBUF_User_Data::get( $user_id );

			/* SECURITY: Check if user_data exists */
			if ( ! $user_data ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html__( 'User data not available.', 'nobloat-user-foundry' ) . '</p></div>';
			}

			$is_expired = NBUF_User_Data::is_expired( $user_id );

			if ( $required_not_expired && $is_expired ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
			if ( ! $required_not_expired && ! $is_expired ) {
				return '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
			}
		}

		/* User has access - return content */
		return do_shortcode( $content );
	}

	/**
	 * ==========================================================
	 * [nbuf_profile]
	 * ----------------------------------------------------------
	 * Displays a user profile page (alternative to custom URLs).
	 * Attributes:
	 * - user: Username or user ID (required)
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Profile HTML.
	 */
	public static function sc_profile( $atts ) {
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Check if public profiles feature is enabled */
		$public_profiles_enabled = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
		if ( ! $public_profiles_enabled ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="nbuf-message nbuf-message-info">' .
					esc_html__( 'Public profiles are not enabled.', 'nobloat-user-foundry' ) . ' ' .
					'<a href="' . esc_url( admin_url( 'admin.php?page=nbuf-settings&tab=users&subtab=profiles' ) ) . '">' .
					esc_html__( 'Enable in Settings → Users → Profiles', 'nobloat-user-foundry' ) . '</a>' .
					'</div>';
			}
			return '<div class="nbuf-message nbuf-message-info">' . esc_html__( 'Public profiles are not available.', 'nobloat-user-foundry' ) . '</div>';
		}

		$atts = shortcode_atts(
			array(
				'user' => '',
			),
			$atts,
			'nbuf_profile'
		);

		/* Get user from attribute, URL query param, or rewrite query var */
		$user_param = $atts['user'];

		if ( empty( $user_param ) ) {
			/* Check for query var set by rewrite rules */
			$user_param = get_query_var( 'nbuf_profile_user', '' );
		}

		if ( empty( $user_param ) && isset( $_GET['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_param = sanitize_user( wp_unslash( $_GET['user'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		/* Require user parameter */
		if ( empty( $user_param ) ) {
			return '<div class="nbuf-message nbuf-message-info">' . esc_html__( 'Please specify a user to view their profile.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* Get user by ID or username */
		if ( is_numeric( $user_param ) ) {
			$user = get_userdata( (int) $user_param );
		} else {
			$user = get_user_by( 'login', $user_param );
			if ( ! $user ) {
				$user = get_user_by( 'slug', $user_param );
			}
		}

		/* User not found */
		if ( ! $user ) {
			return '<div class="nbuf-error">' . esc_html__( 'User not found.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* Check privacy settings */
		if ( ! NBUF_Public_Profiles::can_view_profile( $user->ID ) ) {
			return '<div class="nbuf-restricted-content">' . esc_html__( 'This profile is private.', 'nobloat-user-foundry' ) . '</div>';
		}

		/* Get user data */
		$user_data = NBUF_User_Data::get( $user->ID );

		/* Get profile photo */
		$profile_photo = NBUF_Profile_Photos::get_profile_photo( $user->ID, 150 );

		/* Get cover photo */
		$cover_photo = ( $user_data && ! empty( $user_data->cover_photo_url ) ) ? $user_data->cover_photo_url : '';
		$allow_cover = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );

		/* Get display name */
		$display_name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;

		/* Get user registration date */
		$registered      = $user->user_registered;
		$registered_date = mysql2date( get_option( 'date_format' ), $registered );

		/* Get user's visible fields preference */
		$visible_fields = array();
		if ( $user_data && ! empty( $user_data->visible_fields ) ) {
			$visible_fields = maybe_unserialize( $user_data->visible_fields );
			if ( ! is_array( $visible_fields ) ) {
				$visible_fields = array();
			}
		}

		/* Prepare profile fields to display */
		$profile_fields = array();

		/* Native WordPress fields */
		$native_fields = array(
			'display_name' => array(
				'label' => __( 'Display Name', 'nobloat-user-foundry' ),
				'value' => $user->display_name,
			),
			'first_name'   => array(
				'label' => __( 'First Name', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'first_name', true ),
			),
			'last_name'    => array(
				'label' => __( 'Last Name', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'last_name', true ),
			),
			'user_url'     => array(
				'label' => __( 'Website', 'nobloat-user-foundry' ),
				'value' => $user->user_url,
				'type'  => 'url',
			),
			'description'  => array(
				'label' => __( 'Biography', 'nobloat-user-foundry' ),
				'value' => get_user_meta( $user->ID, 'description', true ),
				'type'  => 'textarea',
			),
		);

		/* Get custom profile data */
		$profile_data = null;
		if ( class_exists( 'NBUF_Profile_Data' ) ) {
			$profile_data     = NBUF_Profile_Data::get( $user->ID );
			$field_registry   = NBUF_Profile_Data::get_field_registry();
			$custom_labels    = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
			$enabled_fields   = NBUF_Profile_Data::get_account_fields();

			/* Build custom fields array */
			foreach ( $field_registry as $category ) {
				if ( isset( $category['fields'] ) && is_array( $category['fields'] ) ) {
					foreach ( $category['fields'] as $key => $default_label ) {
						if ( in_array( $key, $enabled_fields, true ) ) {
							$label = ! empty( $custom_labels[ $key ] ) ? $custom_labels[ $key ] : $default_label;
							$value = $profile_data && isset( $profile_data->$key ) ? $profile_data->$key : '';

							/* Determine field type */
							$field_type = 'text';
							if ( in_array( $key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
								$field_type = 'url';
							} elseif ( in_array( $key, array( 'work_email', 'supervisor_email', 'secondary_email' ), true ) ) {
								$field_type = 'email';
							}

							$profile_fields[ $key ] = array(
								'label' => $label,
								'value' => $value,
								'type'  => $field_type,
							);
						}
					}
				}
			}
		}

		/* Merge native and custom fields */
		$all_fields = array_merge( $native_fields, $profile_fields );

		/* Filter to only visible fields */
		$display_fields = array();
		foreach ( $all_fields as $key => $field_data ) {
			if ( in_array( $key, $visible_fields, true ) && ! empty( $field_data['value'] ) ) {
				$display_fields[ $key ] = $field_data;
			}
		}

		/* Start output buffer */
		ob_start();

		/* Inline CSS - minimal, theme-neutral styling */
		?>
		<style>
		/* NoBloat User Foundry - Profile Page */
		.nbuf-profile-page {
			max-width: 900px;
			margin: 0 auto;
			background: #fff;
		}

		/* Header with cover photo */
		.nbuf-profile-header {
			position: relative;
			margin-bottom: 80px;
		}

		.nbuf-profile-cover {
			height: 250px;
			background-size: cover;
			background-position: center;
			background-repeat: no-repeat;
			position: relative;
		}

		.nbuf-profile-cover-default {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		}

		.nbuf-profile-cover-overlay {
			position: absolute;
			inset: 0;
			background: rgba(0, 0, 0, 0.05);
		}

		/* Avatar positioning - overlaps cover photo */
		.nbuf-profile-avatar-wrap {
			position: absolute;
			bottom: -60px;
			left: 50%;
			transform: translateX(-50%);
			width: 120px;
			height: 120px;
			border-radius: 50%;
			border: 4px solid #fff;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
			overflow: hidden;
			background: #fff;
		}

		.nbuf-profile-avatar {
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}

		/* Content area */
		.nbuf-profile-content {
			padding: 0 20px 30px;
		}

		.nbuf-profile-info {
			text-align: center;
			margin-bottom: 30px;
		}

		.nbuf-profile-name {
			font-size: 1.75rem;
			font-weight: 600;
			margin: 0 0 5px 0;
			line-height: 1.2;
		}

		.nbuf-profile-username {
			font-size: 0.95rem;
			color: #666;
			margin: 0 0 20px 0;
		}

		.nbuf-profile-meta {
			display: flex;
			justify-content: center;
			gap: 15px;
			flex-wrap: wrap;
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #e5e5e5;
		}

		.nbuf-profile-meta-item {
			display: flex;
			align-items: center;
			gap: 5px;
			color: #666;
			font-size: 0.9rem;
		}

		.nbuf-icon {
			opacity: 0.7;
		}

		/* Profile fields section */
		.nbuf-profile-fields {
			margin-top: 30px;
			background: #f9f9f9;
			border-radius: 6px;
			padding: 25px;
		}

		.nbuf-profile-fields-title {
			font-size: 1.1rem;
			font-weight: 600;
			margin: 0 0 20px 0;
			padding-bottom: 10px;
			border-bottom: 2px solid #e5e5e5;
		}

		.nbuf-profile-fields-grid {
			display: grid;
			gap: 15px;
		}

		.nbuf-profile-field {
			display: grid;
			grid-template-columns: 140px 1fr;
			gap: 12px;
			align-items: start;
		}

		.nbuf-profile-field-label {
			font-weight: 600;
			color: #444;
			font-size: 0.9rem;
		}

		.nbuf-profile-field-value {
			color: #333;
			word-wrap: break-word;
			overflow-wrap: break-word;
		}

		.nbuf-profile-field-value a {
			color: #0073aa;
			text-decoration: none;
		}

		.nbuf-profile-field-value a:hover {
			text-decoration: underline;
		}

		/* Actions */
		.nbuf-profile-actions {
			text-align: center;
			margin-top: 25px;
		}

		.nbuf-button {
			display: inline-block;
			padding: 10px 20px;
			border-radius: 4px;
			text-decoration: none;
			font-weight: 600;
			transition: background 0.2s;
			border: none;
			cursor: pointer;
			font-size: 0.95rem;
		}

		.nbuf-button-primary {
			background: #0073aa;
			color: #fff;
		}

		.nbuf-button-primary:hover {
			background: #005a87;
			color: #fff;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.nbuf-profile-cover {
				height: 180px;
			}

			.nbuf-profile-avatar-wrap {
				width: 100px;
				height: 100px;
				bottom: -50px;
			}

			.nbuf-profile-header {
				margin-bottom: 65px;
			}

			.nbuf-profile-name {
				font-size: 1.5rem;
			}

			.nbuf-profile-field {
				grid-template-columns: 1fr;
				gap: 6px;
			}

			.nbuf-profile-field-label {
				font-size: 0.85rem;
			}

			.nbuf-profile-fields {
				padding: 20px;
			}
		}

		@media (max-width: 480px) {
			.nbuf-profile-cover {
				height: 140px;
			}

			.nbuf-profile-avatar-wrap {
				width: 90px;
				height: 90px;
				bottom: -45px;
			}

			.nbuf-profile-header {
				margin-bottom: 55px;
			}

			.nbuf-profile-name {
				font-size: 1.3rem;
			}

			.nbuf-profile-fields {
				padding: 15px;
			}

			.nbuf-profile-meta {
				flex-direction: column;
				gap: 8px;
			}
		}
		</style>

		<div class="nbuf-profile-page">
			<!-- Profile Header with Cover Photo -->
			<div class="nbuf-profile-header">
		<?php if ( $allow_cover && ! empty( $cover_photo ) ) : ?>
					<div class="nbuf-profile-cover" style="background-image: url('<?php echo esc_url( $cover_photo ); ?>');">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php else : ?>
					<div class="nbuf-profile-cover nbuf-profile-cover-default">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php endif; ?>

				<div class="nbuf-profile-avatar-wrap">
					<?php
					/* Data URIs (SVG avatars) need esc_attr, regular URLs use esc_url */
					$photo_src = 0 === strpos( $profile_photo, 'data:' ) ? esc_attr( $profile_photo ) : esc_url( $profile_photo );
					?>
					<img src="<?php echo $photo_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above based on type. ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="nbuf-profile-avatar" width="150" height="150" loading="lazy">
				</div>
			</div>

			<!-- Profile Info -->
			<div class="nbuf-profile-content">
				<div class="nbuf-profile-info">
					<h1 class="nbuf-profile-name"><?php echo esc_html( $display_name ); ?></h1>
					<p class="nbuf-profile-username">@<?php echo esc_html( $user->user_login ); ?></p>

					<div class="nbuf-profile-meta">
						<span class="nbuf-profile-meta-item">
							<svg class="nbuf-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M8 8a3 3 0 100-6 3 3 0 000 6zm0 1.5c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
							</svg>
			<?php
			/* translators: %s: User registration date */
			printf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( $registered_date ) );
			?>
						</span>
					</div>
				</div>

		<?php if ( ! empty( $display_fields ) ) : ?>
				<div class="nbuf-profile-fields">
					<h2 class="nbuf-profile-fields-title"><?php esc_html_e( 'Profile Information', 'nobloat-user-foundry' ); ?></h2>
					<div class="nbuf-profile-fields-grid">
			<?php foreach ( $display_fields as $key => $field_data ) : ?>
						<div class="nbuf-profile-field">
							<div class="nbuf-profile-field-label"><?php echo esc_html( $field_data['label'] ); ?></div>
							<div class="nbuf-profile-field-value">
				<?php
				$field_type  = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
				$field_value = $field_data['value'];

				if ( 'url' === $field_type && ! empty( $field_value ) ) {
					/* Display as clickable link */
					echo '<a href="' . esc_url( $field_value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'email' === $field_type && ! empty( $field_value ) ) {
					/* Display as mailto link */
					echo '<a href="mailto:' . esc_attr( $field_value ) . '">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'textarea' === $field_type && ! empty( $field_value ) ) {
					/* Display as formatted text with paragraphs */
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() handles escaping.
					echo wpautop( wp_kses_post( $field_value ) );
				} else {
					/* Display as plain text */
					echo esc_html( $field_value );
				}
				?>
							</div>
						</div>
			<?php endforeach; ?>
					</div>
				</div>
		<?php endif; ?>

		<?php
		/**
		 * Hook for adding custom content to profile page
		 *
		 * @param WP_User $user User object.
		 * @param array   $user_data User data from custom table.
		 */
		do_action( 'nbuf_public_profile_content', $user, $user_data );
		?>

		<?php if ( is_user_logged_in() && get_current_user_id() === $user->ID ) : ?>
					<div class="nbuf-profile-actions">
			<?php
			$account_page_id = NBUF_Options::get( 'nbuf_page_account' );
			if ( $account_page_id ) :
				?>
							<a href="<?php echo esc_url( get_permalink( $account_page_id ) ); ?>" class="nbuf-button nbuf-button-primary">
				<?php esc_html_e( 'Edit Profile', 'nobloat-user-foundry' ); ?>
							</a>
			<?php endif; ?>
					</div>
		<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * ==========================================================
	 * [nbuf_members]
	 * ----------------------------------------------------------
	 * Displays the member directory.
	 * Delegates to NBUF_Member_Directory::render_directory().
	 * ==========================================================
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string Member directory HTML.
	 */
	public static function sc_members( $atts = array() ) {
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Delegate to Member Directory class */
		if ( class_exists( 'NBUF_Member_Directory' ) ) {
			return NBUF_Member_Directory::render_directory( $atts );
		}

		return '<div class="nbuf-message nbuf-message-info">' . esc_html__( 'Member directory is not available.', 'nobloat-user-foundry' ) . '</div>';
	}

	/**
	 * ==========================================================
	 * HELPER: GET CURRENT PAGE URL
	 * ----------------------------------------------------------
	 * Returns the current page URL, handling virtual pages correctly.
	 * On virtual pages, get_permalink() returns wrong URL, so we
	 * use the REQUEST_URI instead.
	 * ==========================================================
	 *
	 * @return string Current page URL.
	 */
	private static function get_current_page_url() {
		/* Check if we're on a virtual page */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$view    = NBUF_Universal_Router::get_current_view();
			$subview = NBUF_Universal_Router::get_current_subview();
			return NBUF_Universal_Router::get_url( $view, $subview );
		}

		/* Fall back to get_permalink for real WordPress pages */
		return get_permalink();
	}

	/**
	 * Get login page URL (Universal Router or legacy).
	 *
	 * @param  string $redirect_to Optional redirect URL after login.
	 * @return string Login URL.
	 */
	public static function get_login_url( $redirect_to = '' ) {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			$url = NBUF_Universal_Router::get_url( 'login' );
			if ( $redirect_to ) {
				$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
			}
			return $url;
		}

		/* Legacy: use page ID or WordPress login */
		$login_page_id = NBUF_Options::get( 'nbuf_page_login', 0 );
		if ( $login_page_id ) {
			$url = get_permalink( $login_page_id );
			if ( $redirect_to ) {
				$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
			}
			return $url;
		}

		return wp_login_url( $redirect_to );
	}

	/**
	 * Get registration page URL (Universal Router or legacy).
	 *
	 * @return string Registration URL.
	 */
	public static function get_register_url() {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'register' );
		}

		/* Legacy: use page ID */
		$page_id = NBUF_Options::get( 'nbuf_page_registration', 0 );
		return $page_id ? get_permalink( $page_id ) : '';
	}

	/**
	 * Get account page URL (Universal Router or legacy).
	 *
	 * @param  string $tab Optional tab name.
	 * @return string Account URL.
	 */
	public static function get_account_url( $tab = '' ) {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'account', $tab );
		}

		/* Legacy: use page ID */
		$page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
		$url     = $page_id ? get_permalink( $page_id ) : '';
		if ( $url && $tab ) {
			$url = add_query_arg( 'tab', $tab, $url );
		}
		return $url;
	}

	/**
	 * Get forgot password page URL (Universal Router or legacy).
	 *
	 * @return string Forgot password URL.
	 */
	public static function get_forgot_password_url() {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'forgot-password' );
		}

		/* Legacy: use page ID */
		$page_id = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
		return $page_id ? get_permalink( $page_id ) : '';
	}

	/**
	 * Get logout page URL (Universal Router or legacy).
	 *
	 * @return string Logout URL.
	 */
	public static function get_logout_url() {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'logout' );
		}

		/* Legacy: use page ID or WordPress logout */
		$page_id = NBUF_Options::get( 'nbuf_page_logout', 0 );
		return $page_id ? get_permalink( $page_id ) : wp_logout_url();
	}

	/**
	 * Get reset password page URL (Universal Router or legacy).
	 *
	 * @return string Reset password URL.
	 */
	public static function get_reset_password_url() {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'reset-password' );
		}

		/* Legacy: use page ID */
		$page_id = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
		return $page_id ? get_permalink( $page_id ) : home_url( '/nobloat-reset' );
	}

	/**
	 * ==========================================================
	 * HELPER: GET FIELD INPUT TYPE
	 * ----------------------------------------------------------
	 * Returns the appropriate HTML input type for a given field.
	 * Reduces code duplication in registration and account forms.
	 * ==========================================================
	 *
	 * @param  string $field_key Field key.
	 * @return string HTML input type.
	 */
	private static function get_field_input_type( string $field_key ): string {
		/* Phone number fields */
		if ( in_array( $field_key, array( 'phone', 'mobile_phone', 'work_phone', 'fax' ), true ) ) {
			return 'tel';
		}

		/* URL/social media fields */
		if ( in_array( $field_key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
			return 'url';
		}

		/* Email fields (excluding primary email) */
		if ( in_array( $field_key, array( 'work_email', 'supervisor_email' ), true ) ) {
			return 'email';
		}

		/* Date fields */
		if ( in_array( $field_key, array( 'date_of_birth', 'hire_date', 'termination_date' ), true ) ) {
			return 'date';
		}

		/* Default to text for all other fields */
		return 'text';
	}

	/**
	 * ==========================================================
	 * GET POLICY PANEL (TABBED)
	 * ----------------------------------------------------------
	 * Returns the tabbed Privacy Policy / Terms of Use panel
	 * for display alongside login and registration forms.
	 * ==========================================================
	 *
	 * @return string HTML for tabbed policy panel.
	 */
	private static function get_policy_panel() {
		$privacy = NBUF_Template_Manager::load_template( 'policy-privacy-html' );
		$terms   = NBUF_Template_Manager::load_template( 'policy-terms-html' );

		/* Replace placeholders */
		$replacements = array(
			'{site_name}' => get_bloginfo( 'name' ),
			'{site_url}'  => home_url(),
		);

		$privacy = str_replace( array_keys( $replacements ), array_values( $replacements ), $privacy );
		$terms   = str_replace( array_keys( $replacements ), array_values( $replacements ), $terms );

		/* Build tabbed HTML */
		return '<div class="nbuf-policy-panel">
			<div class="nbuf-policy-tabs">
				<button type="button" class="nbuf-policy-tab-link active" data-tab="privacy">' . esc_html__( 'Privacy Policy', 'nobloat-user-foundry' ) . '</button>
				<button type="button" class="nbuf-policy-tab-link" data-tab="terms">' . esc_html__( 'Terms of Use', 'nobloat-user-foundry' ) . '</button>
			</div>
			<div class="nbuf-policy-tab-content active" data-tab="privacy">' . wp_kses_post( $privacy ) . '</div>
			<div class="nbuf-policy-tab-content" data-tab="terms">' . wp_kses_post( $terms ) . '</div>
		</div>';
	}

	/**
	 * ==========================================================
	 * GET POLICY TAB CONTENT (SIDE-BY-SIDE)
	 * ----------------------------------------------------------
	 * Returns 50/50 side-by-side layout of Privacy Policy and
	 * Terms of Use for the account page Policies tab.
	 * ==========================================================
	 *
	 * @return string HTML for side-by-side policy display.
	 */
	private static function get_policy_tab_content() {
		$privacy = NBUF_Template_Manager::load_template( 'policy-privacy-html' );
		$terms   = NBUF_Template_Manager::load_template( 'policy-terms-html' );

		/* Replace placeholders */
		$replacements = array(
			'{site_name}' => get_bloginfo( 'name' ),
			'{site_url}'  => home_url(),
		);

		$privacy = str_replace( array_keys( $replacements ), array_values( $replacements ), $privacy );
		$terms   = str_replace( array_keys( $replacements ), array_values( $replacements ), $terms );

		/* Build side-by-side HTML */
		return '<div class="nbuf-policies-side-by-side">
			<div class="nbuf-policy-column">
				<h3>' . esc_html__( 'Privacy Policy', 'nobloat-user-foundry' ) . '</h3>
				' . wp_kses_post( $privacy ) . '
			</div>
			<div class="nbuf-policy-column">
				<h3>' . esc_html__( 'Terms of Use', 'nobloat-user-foundry' ) . '</h3>
				' . wp_kses_post( $terms ) . '
			</div>
		</div>';
	}

	/**
	 * ==========================================================
	 * WRAP CONTENT WITH POLICY PANEL
	 * ----------------------------------------------------------
	 * Wraps form content with a two-column container and policy
	 * panel based on position setting.
	 * ==========================================================
	 *
	 * @param string $content  The form content to wrap.
	 * @param string $position Position of policy panel ('left' or 'right').
	 * @param string $form_type Form type for CSS class prefix.
	 * @return string Wrapped HTML content.
	 */
	private static function wrap_with_policy_panel( $content, $position, $form_type ) {
		/* Enqueue policy tabs JavaScript */
		wp_enqueue_script(
			'nbuf-policy-tabs',
			NBUF_PLUGIN_URL . 'assets/js/frontend/policy-tabs.js',
			array(),
			NBUF_VERSION,
			true
		);

		$policy_panel = self::get_policy_panel();

		/* Determine container class based on position */
		$position_class = 'right' === $position ? 'nbuf-policies-right' : 'nbuf-policies-left';

		return '<div class="nbuf-' . esc_attr( $form_type ) . '-container nbuf-with-policies ' . esc_attr( $position_class ) . '">
			<div class="nbuf-' . esc_attr( $form_type ) . '-main">' . $content . '</div>
			' . $policy_panel . '
		</div>';
	}

	/**
	 * Universal page shortcode [nbuf_universal].
	 *
	 * Renders the appropriate view based on URL or shortcode attributes.
	 * Used on the Universal Page when Universal Mode is enabled.
	 *
	 * Usage:
	 * - [nbuf_universal] - Renders view from URL (e.g., /user-foundry/login/)
	 * - [nbuf_universal view="login"] - Force specific view
	 * - [nbuf_universal default="account"] - Set default view for base URL
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML content.
	 */
	public static function sc_universal( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'view'    => '',       /* Force specific view */
				'default' => 'account', /* Default view when none specified */
			),
			$atts,
			'nbuf_universal'
		);

		/* Check if Universal Mode is enabled */
		if ( ! class_exists( 'NBUF_URL' ) || ! NBUF_URL::is_universal_mode() ) {
			return '<p>' . esc_html__( 'Universal Page Mode is not enabled.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Determine which view to render */
		$view = $atts['view'];

		if ( empty( $view ) ) {
			/* Get view from URL via router */
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$view = NBUF_Universal_Router::get_current_view();
			}
		}

		/* Use default if still empty */
		if ( empty( $view ) || 'default' === $view ) {
			$view = $atts['default'];
		}

		/* Set document title for this view */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			$view_title = NBUF_Universal_Router::get_view_title( $view );
			if ( $view_title ) {
				add_filter(
					'document_title_parts',
					function ( $parts ) use ( $view_title ) {
						$parts['title'] = $view_title;
						return $parts;
					},
					100
				);
			}
		}

		/* Enqueue CSS for this view */
		self::enqueue_universal_view_css( $view );

		/* Render the view */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::render_view( $view );
		}

		/* Fallback: try to call shortcode method directly */
		$view_methods = array(
			'login'           => 'sc_login_form',
			'register'        => 'sc_registration_form',
			'account'         => 'sc_account_page',
			'verify'          => 'sc_verify_page',
			'forgot-password' => 'sc_request_reset_form',
			'reset-password'  => 'sc_reset_form',
			'logout'          => 'sc_logout',
			'2fa'             => 'sc_2fa_verify',
			'2fa-setup'       => 'sc_totp_setup',
			'members'         => 'sc_members',
			'profile'         => 'sc_profile',
		);

		if ( isset( $view_methods[ $view ] ) && method_exists( __CLASS__, $view_methods[ $view ] ) ) {
			return call_user_func( array( __CLASS__, $view_methods[ $view ] ), array() );
		}

		return '<p>' . esc_html__( 'Invalid view specified.', 'nobloat-user-foundry' ) . '</p>';
	}

	/**
	 * Enqueue CSS for Universal Page view.
	 *
	 * @param string $view View key.
	 */
	private static function enqueue_universal_view_css( $view ) {
		/* Map views to CSS page types */
		$css_map = array(
			'login'           => 'login',
			'register'        => 'registration',
			'account'         => 'account',
			'forgot-password' => 'reset',
			'reset-password'  => 'reset',
			'2fa'             => '2fa',
			'2fa-setup'       => '2fa',
		);

		if ( ! isset( $css_map[ $view ] ) || ! class_exists( 'NBUF_CSS_Manager' ) ) {
			return;
		}

		$page_type = $css_map[ $view ];

		/* CSS configuration */
		$css_files = array(
			'login'        => array( 'nbuf-login', 'login-page', 'nbuf_login_page_css', 'nbuf_css_write_failed_login' ),
			'registration' => array( 'nbuf-registration', 'registration-page', 'nbuf_registration_page_css', 'nbuf_css_write_failed_registration' ),
			'reset'        => array( 'nbuf-reset', 'reset-page', 'nbuf_reset_page_css', 'nbuf_css_write_failed_reset' ),
			'account'      => array( 'nbuf-account', 'account-page', 'nbuf_account_page_css', 'nbuf_css_write_failed_account' ),
			'2fa'          => array( 'nbuf-2fa', '2fa-setup', 'nbuf_2fa_page_css', 'nbuf_css_write_failed_2fa' ),
		);

		if ( isset( $css_files[ $page_type ] ) ) {
			list( $handle, $filename, $db_option, $token_key ) = $css_files[ $page_type ];

			$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
			if ( $css_load_on_pages ) {
				NBUF_CSS_Manager::enqueue_css( $handle, $filename, $db_option, $token_key );
			}
		}

		/* Enqueue account page JavaScript */
		if ( 'account' === $view ) {
			wp_enqueue_script( 'nbuf-account-page', NBUF_PLUGIN_URL . 'assets/js/frontend/account-page.js', array(), NBUF_VERSION, true );

			/* Pass subview (tab) to JavaScript */
			$subview = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$subview = NBUF_Universal_Router::get_current_subview();
			}

			wp_localize_script(
				'nbuf-account-page',
				'nbufAccountData',
				array(
					'activeTab'    => $subview ? $subview : '',
					'activeSubtab' => '',
					'baseSlug'     => class_exists( 'NBUF_URL' ) ? NBUF_URL::get_base_slug() : '',
				)
			);
		}
	}
}

/* Note: Init is called from main plugin file via plugins_loaded hook */