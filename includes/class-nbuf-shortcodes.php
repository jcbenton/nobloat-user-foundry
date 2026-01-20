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
	 * @return void
	 */
	private static function enqueue_frontend_css( string $page_type ): void {
		/* Check if CSS loading is enabled */
		$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
		if ( ! $css_load_on_pages ) {
			return;
		}

		/* Load individual CSS files */
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
			return '<div class="nbuf-message nbuf-message-error">'
				. '<strong>' . esc_html__( 'NoBloat User Foundry', 'nobloat-user-foundry' ) . ':</strong> '
				. esc_html__( 'The user management system is currently disabled. Enable it under NoBloat Foundry → User Settings → System → Status.', 'nobloat-user-foundry' )
				. '</div>';
		}

		// Non-admins see a clear message explaining the situation.
		return '<div class="nbuf-message nbuf-message-error">'
			. esc_html__( 'The NoBloat User Management system is not enabled. Please contact the site administrator.', 'nobloat-user-foundry' )
			. '</div>';
	}

	/**
	 * Initialize shortcodes and form handlers.
	 *
	 * @return void
	 */
	public static function init(): void {
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public static function sc_verify_page( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
			$html  = '<div class="nobloat-verify-wrapper">';
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public static function sc_reset_form( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
				$redirect_url = add_query_arg(
					array(
						'tab'    => 'security',
						'subtab' => 'password',
					),
					$redirect_url
				);
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/*
		 * Get and validate reset key from URL.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL params for reset key validation.
		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

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
			$forgot_url    = self::get_forgot_password_url();
			$error_code    = $user->get_error_code();
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
		$error_param   = self::get_query_param( 'error' );
		if ( $error_param ) {
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-reset-error">' . esc_html( urldecode( $error_param ) ) . '</div>';
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_request_reset_form( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
				$redirect_url = add_query_arg(
					array(
						'tab'    => 'security',
						'subtab' => 'password',
					),
					$redirect_url
				);
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'request-reset-form' );

		/* Get success/error messages from query parameters */
		$success_message = '';
		$error_message   = '';

		if ( 'sent' === self::get_query_param( 'reset' ) ) {
			$success_message = '<div class="nbuf-message nbuf-message-success nbuf-reset-success">' . esc_html__( 'Password reset link has been sent to your email address.', 'nobloat-user-foundry' ) . '</div>';
		}

		$error_param = self::get_query_param( 'error' );
		if ( $error_param ) {
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-reset-error">' . esc_html( urldecode( $error_param ) ) . '</div>';
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
	 *
	 * @return void
	 */
	public static function maybe_handle_request_reset(): void {
		if ( is_admin() ) {
			return;
		}

		/* Check if on reset request page - either WordPress page or Universal Router */
		$is_reset_page = false;

		/* Check WordPress page */
		$page_id = NBUF_Options::get( 'nbuf_page_request_reset' );
		if ( $page_id && is_page( $page_id ) ) {
			$is_reset_page = true;
		}

		/* Check Universal Router */
		if ( ! $is_reset_page && class_exists( 'NBUF_Universal_Router' ) ) {
			$current_view = NBUF_Universal_Router::get_current_view();
			if ( 'forgot-password' === $current_view ) {
				$is_reset_page = true;
			}
		}

		if ( ! $is_reset_page ) {
			return;
		}

		if ( empty( $_POST['nbuf_request_reset_action'] ) ) {
			return;
		}

		/* Get the redirect URL for this page (Universal Router or WordPress page) */
		$redirect_base_url = '';
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$redirect_base_url = NBUF_Universal_Router::get_url( 'forgot-password' );
		}
		if ( empty( $redirect_base_url ) && $page_id ) {
			$redirect_base_url = get_permalink( $page_id );
		}
		if ( empty( $redirect_base_url ) ) {
			$redirect_base_url = home_url( '/forgot-password/' );
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
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Please enter your email address.', 'nobloat-user-foundry' ) ), $redirect_base_url ) );
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
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Too many password reset attempts. Please try again later.', 'nobloat-user-foundry' ) ), $redirect_base_url ) );
			exit;
		}

		/* Get user by email */
		$user = get_user_by( 'email', $user_login );

		if ( ! $user ) {
			/* For security, show success message even if user not found */
			wp_safe_redirect( add_query_arg( 'reset', 'sent', $redirect_base_url ) );
			exit;
		}

		/* Generate password reset key */
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $key->get_error_message() ), $redirect_base_url ) );
			exit;
		}

		/* Build reset link - prefer Universal Router, then page, then wp-login */
		$reset_url = '';
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			$reset_url = NBUF_Universal_Router::get_url( 'reset-password' );
		}
		if ( empty( $reset_url ) ) {
			$reset_page_id = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
			if ( $reset_page_id ) {
				$reset_url = get_permalink( $reset_page_id );
			}
		}
		if ( ! empty( $reset_url ) ) {
			$reset_url = add_query_arg(
				array(
					'action' => 'rp',
					'key'    => $key,
					'login'  => rawurlencode( $user->user_login ),
				),
				$reset_url
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

		/* Send email using central sender */
		$sent = NBUF_Email::send( $user->user_email, $subject, $message, $mode );

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
		wp_safe_redirect( add_query_arg( 'reset', 'sent', $redirect_base_url ) );
		exit;
	}

	/**
	 * ==========================================================
	 * maybe_handle_password_reset()
	 * ----------------------------------------------------------
	 * Process POST from our reset form on the configured page.
	 * ==========================================================
	 *
	 * @return void
	 */
	public static function maybe_handle_password_reset(): void {
		if ( is_admin() ) {
			return;
		}

		/* Check if on password reset page - either WordPress page or Universal Router */
		$is_reset_page = false;

		/* Check WordPress page */
		$page_id = NBUF_Options::get( 'nbuf_page_password_reset' );
		if ( $page_id && is_page( $page_id ) ) {
			$is_reset_page = true;
		}

		/* Check Universal Router */
		if ( ! $is_reset_page && class_exists( 'NBUF_Universal_Router' ) ) {
			$current_view = NBUF_Universal_Router::get_current_view();
			if ( 'reset-password' === $current_view ) {
				$is_reset_page = true;
			}
		}

		if ( ! $is_reset_page ) {
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_login_form( array $atts = array() ): string {
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

			/*
			 * Check for redirect_to parameter.
			 */
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Just reading redirect URL, no data modification.
			if ( isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] ) ) {
				$redirect_url = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

		// Build magic link option if enabled.
		$magic_link = '';
		if ( class_exists( 'NBUF_Magic_Links' ) && NBUF_Magic_Links::is_enabled() ) {
			$magic_link_url = NBUF_Universal_Router::get_url( 'magic-link' );
			if ( $magic_link_url ) {
				$magic_link = '<div class="nbuf-magic-link-divider">'
					. '<span>' . esc_html__( 'or', 'nobloat-user-foundry' ) . '</span>'
					. '</div>'
					. '<button type="button" class="nbuf-magic-link-button" onclick="window.location.href=\'' . esc_url( $magic_link_url ) . '\'">'
					. esc_html__( 'Sign in with Magic Link', 'nobloat-user-foundry' )
					. '</button>';
			}
		}

		// Get error message if present.
		$error_message = '';
		$login_status  = self::get_query_param( 'login' );
		if ( $login_status ) {
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
				case 'ip_blocked':
					$error_message = '<div class="nbuf-message nbuf-message-error nbuf-login-error">' . esc_html__( 'Access denied. Your IP address is not authorized to log in.', 'nobloat-user-foundry' ) . '</div>';
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
				'{magic_link}',
			),
			array(
				$action_url,
				$nonce_field,
				esc_attr( $atts['redirect'] ),
				$reset_link,
				$register_link,
				$error_message,
				$magic_link,
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
	 *
	 * @return void
	 */
	public static function maybe_handle_login(): void {
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

		// Check IP restrictions before authentication.
		if ( class_exists( 'NBUF_IP_Restrictions' ) && NBUF_IP_Restrictions::is_enabled() ) {
			$client_ip = NBUF_IP_Restrictions::get_client_ip();

			if ( ! NBUF_IP_Restrictions::is_ip_allowed( $client_ip ) ) {
				// Check admin bypass - need to look up user first.
				$bypass = false;
				if ( NBUF_IP_Restrictions::admin_bypass_enabled() ) {
					$check_user = get_user_by( 'login', $username );
					if ( ! $check_user ) {
						$check_user = get_user_by( 'email', $username );
					}
					if ( $check_user && user_can( $check_user, 'manage_options' ) ) {
						$bypass = true;
					}
				}

				if ( ! $bypass ) {
					// Log the blocked attempt.
					if ( class_exists( 'NBUF_Security_Log' ) ) {
						NBUF_Security_Log::log_or_update(
							'ip_blocked',
							'critical',
							'Login blocked: IP not in allowed list',
							array(
								'ip_address' => $client_ip,
								'username'   => $username,
								'mode'       => NBUF_IP_Restrictions::get_mode(),
							)
						);
					}

					$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
					$current_url = home_url( $request_uri );
					wp_safe_redirect( add_query_arg( 'login', 'ip_blocked', $current_url ) );
					exit;
				}
			}
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
			$error_code   = $user->get_error_code();
			$login_status = 'failed'; // Default.

			if ( 'too_many_attempts' === $error_code ) {
				$login_status = 'blocked';
			} elseif ( 'nbuf_unverified' === $error_code || 'email_not_verified' === $error_code ) {
				$login_status = 'unverified';
			} elseif ( 'nbuf_disabled' === $error_code || 'user_disabled' === $error_code ) {
				$login_status = 'disabled';
			} elseif ( 'nbuf_expired' === $error_code || 'account_expired' === $error_code ) {
				$login_status = 'expired';
			} elseif ( 'awaiting_approval' === $error_code ) {
				$login_status = 'pending';
			}

			wp_safe_redirect( add_query_arg( 'login', $login_status, $current_url ) );
			exit;
		}

		// User authenticated - now check verification/expiration status.
		// This integrates with existing NBUF_Hooks::check_login_verification.
		// which is already hooked into 'authenticate' filter.

		// If we got here, user is logged in and passed all checks.
		// Note: Login success is logged via wp_login hook in main plugin file.

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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function sc_registration_form( array $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
		$disabled_notice = self::get_system_disabled_notice();
		if ( $disabled_notice ) {
			return $disabled_notice;
		}

		/* Enqueue CSS for registration page */
		self::enqueue_frontend_css( 'registration' );

		/* Check if registration is enabled */
		$enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
		if ( ! $enable_registration ) {
			return '<div class="nbuf-message nbuf-message-info">' . esc_html__( 'User registration is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
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

		/* Handle successful registration - hide form and show appropriate message */
		if ( 'success' === self::get_query_param( 'registration' ) ) {
			$require_verification = NBUF_Options::get( 'nbuf_require_verification', true );

			if ( $require_verification ) {
				/* Email verification required - show message to check email */
				return '<div class="nbuf-registration-success-container">
					<div class="nbuf-message nbuf-message-success nbuf-registration-success">
						<h3>' . esc_html__( 'Registration Successful!', 'nobloat-user-foundry' ) . '</h3>
						<p>' . esc_html__( 'Please check your email to verify your account. You will need to click the verification link before you can log in.', 'nobloat-user-foundry' ) . '</p>
					</div>
					<p class="nbuf-registration-success-login">
						' . sprintf(
							/* translators: %1$s: opening link tag, %2$s: closing link tag */
							esc_html__( 'Already have an account? %1$sLog in%2$s.', 'nobloat-user-foundry' ),
							'<a href="' . esc_url( $login_url ) . '">',
							'</a>'
						) . '
					</p>
				</div>';
			} else {
				/* No verification required - show success and prompt to log in */
				return '<div class="nbuf-registration-success-container">
					<div class="nbuf-message nbuf-message-success nbuf-registration-success">
						<h3>' . esc_html__( 'Registration Successful!', 'nobloat-user-foundry' ) . '</h3>
						<p>' . esc_html__( 'Your account has been created. You can now log in with your credentials.', 'nobloat-user-foundry' ) . '</p>
					</div>
					<p class="nbuf-registration-success-login">
						<button type="button" class="nbuf-button" onclick="window.location.href=\'' . esc_url( $login_url ) . '\'">' . esc_html__( 'Login Now', 'nobloat-user-foundry' ) . '</button>
					</p>
				</div>';
			}
		}

		$error_param = self::get_query_param( 'error' );
		if ( $error_param ) {
			$error_message = '<div class="nbuf-message nbuf-message-error nbuf-registration-error">' . esc_html( urldecode( $error_param ) ) . '</div>';
		}

		/* Retrieve preserved form data from transient (if redirected back with error) */
		$preserved_data = array();
		$form_key       = self::get_query_param( 'form_key' );
		if ( $form_key ) {
			$preserved_data = get_transient( $form_key );
			if ( $preserved_data ) {
				delete_transient( $form_key ); /* One-time use */
			} else {
				$preserved_data = array();
			}
		}

		/* Build username field HTML if needed */
		$username_field_html = '';
		if ( NBUF_Registration::should_show_username_field() ) {
			$username_value      = isset( $preserved_data['username'] ) ? esc_attr( $preserved_data['username'] ) : '';
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
		$field_items     = array();

		/* Textarea fields that should always span full width */
		$full_width_fields = array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' );

		foreach ( $enabled_fields as $field_key => $field_data ) {
			$field_value = isset( $preserved_data[ $field_key ] ) ? esc_attr( $preserved_data[ $field_key ] ) : '';
			$required    = $field_data['required'] ? ' <span class="required">*</span>' : '';
			$req_attr    = $field_data['required'] ? ' required' : '';

			/* Determine input type based on field characteristics */
			$input_type    = self::get_field_input_type( $field_key );
			$is_full_width = in_array( $field_key, $full_width_fields, true );

			/* Special handling for timezone field - searchable select */
			if ( 'timezone' === $field_key ) {
				$field_html = self::render_timezone_select(
					$field_key,
					$field_value,
					$field_data['label'],
					$field_data['required'],
					'registration'
				);
			} elseif ( $is_full_width ) {
				/* Text areas for long-form content */
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
		$email_value = isset( $preserved_data['email'] ) ? esc_attr( $preserved_data['email'] ) : '';

		/* Build dynamic password requirements text */
		$password_min_length        = absint( NBUF_Options::get( 'nbuf_password_min_length', 12 ) );
		$password_requirements_text = NBUF_Password_Validator::get_requirements_text();

		$replacements = array(
			'{action_url}'            => esc_url( self::get_current_page_url() ),
			'{nonce_field}'           => wp_nonce_field( 'nbuf_registration', 'nbuf_registration_nonce', true, false ),
			'{antibot_fields}'        => class_exists( 'NBUF_Antibot' ) ? NBUF_Antibot::render_fields() : '',
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
	 *
	 * @return void
	 */
	public static function maybe_handle_registration(): void {
		if ( ! isset( $_POST['nbuf_register'] ) ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_registration_nonce'] ) ), 'nbuf_registration' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Anti-bot validation - must run BEFORE any other validation */
		if ( class_exists( 'NBUF_Antibot' ) ) {
			$antibot_result = NBUF_Antibot::validate( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
			if ( is_wp_error( $antibot_result ) ) {
				$redirect_url = add_query_arg( 'error', rawurlencode( $antibot_result->get_error_message() ), self::get_current_page_url() );
				wp_safe_redirect( $redirect_url );
				exit;
			}
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
			/* Store form data (except passwords) in transient for repopulating form */
			$preserved_data = $data;
			unset( $preserved_data['password'], $preserved_data['password_confirm'] );
			$transient_key = 'nbuf_reg_form_' . wp_hash( session_id() . wp_get_session_token() );
			set_transient( $transient_key, $preserved_data, 5 * MINUTE_IN_SECONDS );

			/* Redirect back with error */
			$error_message = $user_id->get_error_message();
			$redirect_url  = add_query_arg(
				array(
					'error'    => rawurlencode( $error_message ),
					'form_key' => $transient_key,
				),
				self::get_current_page_url()
			);
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Page HTML.
	 */
	public static function sc_account_page( array $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-profile-photos', 'assets/js/frontend/profile-photos.js', array( 'jquery' ) );
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
		NBUF_Asset_Minifier::enqueue_script( 'nbuf-account-page', 'assets/js/frontend/account-page.js', array() );

		/* Enqueue passkeys JavaScript if enabled */
		$passkeys_enabled = NBUF_Options::get( 'nbuf_passkeys_enabled', false );
		if ( $passkeys_enabled ) {
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-passkeys-account', 'assets/js/frontend/passkeys-account.js', array() );
		}

		/* Get template using Template Manager (custom table + caching) */
		$template = NBUF_Template_Manager::load_template( 'account-page' );

		/* Get flash message from transient (one-time display) */
		$messages = '';
		$flash    = self::get_flash_message( $user_id );
		if ( $flash ) {
			$class    = 'error' === $flash['type'] ? 'nbuf-message-error nbuf-account-error' : 'nbuf-message-success nbuf-account-success';
			$messages = '<div class="nbuf-message ' . esc_attr( $class ) . '">' . esc_html( $flash['message'] ) . '</div>';
		}

		/* Check for weak password warning (grace period) */
		$weak_password_warning = get_transient( 'nbuf_weak_password_warning_' . $user_id );
		if ( $weak_password_warning && is_array( $weak_password_warning ) ) {
			delete_transient( 'nbuf_weak_password_warning_' . $user_id );
			$days_remaining = isset( $weak_password_warning['days_remaining'] ) ? (int) $weak_password_warning['days_remaining'] : 0;
			$requirements   = isset( $weak_password_warning['requirements'] ) ? $weak_password_warning['requirements'] : array();

			$messages .= '<div class="nbuf-message nbuf-message-warning nbuf-weak-password-warning">';
			$messages .= '<strong>' . esc_html__( 'Password Update Required', 'nobloat-user-foundry' ) . '</strong><br>';
			/* translators: %d: number of days remaining */
			$messages .= '<p>' . sprintf( esc_html__( 'Your password does not meet the current security requirements. Please update it within %d days to avoid being locked out.', 'nobloat-user-foundry' ), $days_remaining ) . '</p>';
			if ( ! empty( $requirements ) ) {
				$messages .= '<p><strong>' . esc_html__( 'Requirements:', 'nobloat-user-foundry' ) . '</strong></p>';
				$messages .= '<ul style="margin-left: 20px; list-style: disc;">';
				foreach ( $requirements as $req ) {
					$messages .= '<li>' . esc_html( $req ) . '</li>';
				}
				$messages .= '</ul>';
			}
			$messages .= '</div>';
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
		$profiles_enabled         = NBUF_Options::get( 'nbuf_enable_profiles', false );
		$gravatar_enabled         = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
		$cover_enabled            = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );
		$public_profiles_enabled  = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
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
				$is_first      = ( 0 === $subtab_count );
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
				$is_first              = ( 'profile-settings' === $first_subtab );
				$subtab_contents      .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="profile-settings">';
				$subtab_contents      .= '<form method="post" action="' . esc_url( self::get_current_page_url() ) . '" class="nbuf-account-form nbuf-profile-tab-form">';
				$subtab_contents      .= $profile_tab_nonce;
				$subtab_contents      .= '<input type="hidden" name="nbuf_account_action" value="update_profile_tab">';
				$subtab_contents      .= '<input type="hidden" name="nbuf_active_tab" value="profile">';
				$subtab_contents      .= '<input type="hidden" name="nbuf_active_subtab" value="profile-settings">';
				$subtab_contents      .= '<input type="hidden" name="nbuf_directory_submitted" value="1">';
				$subtab_contents      .= '<div class="nbuf-profile-subtab-section">' . $profile_settings_html . '</div>';
				$subtab_contents      .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Save Settings', 'nobloat-user-foundry' ) . '</button>';
				$subtab_contents      .= '</form>';
				$subtab_contents      .= '</div>';
			}

			/* Profile Photo sub-tab */
			if ( $show_profile_photo ) {
				ob_start();
				do_action( 'nbuf_account_profile_photo_subtab', $user_id );
				$profile_photo_html = ob_get_clean();
				$is_first           = ( 'profile-photo' === $first_subtab );
				$subtab_contents   .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="profile-photo">';
				$subtab_contents   .= '<div class="nbuf-profile-subtab-section">' . $profile_photo_html . '</div>';
				$subtab_contents   .= '</div>';
			}

			/* Cover Photo sub-tab */
			if ( $show_cover_photo ) {
				ob_start();
				do_action( 'nbuf_account_cover_photo_subtab', $user_id );
				$cover_photo_html = ob_get_clean();
				$is_first         = ( 'cover-photo' === $first_subtab );
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
		$history_tab_button           = '';
		$history_tab_content          = '';
		$version_history_enabled      = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		$version_history_user_visible = NBUF_Options::get( 'nbuf_version_history_user_visible', false );
		if ( $version_history_enabled && $version_history_user_visible ) {
			/* Build history tab content directly */
			$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );

			$info_message = '';
			if ( ! $allow_user_revert ) {
				$info_message = '<div class="nbuf-message nbuf-message-info nbuf-section-spacing">' . esc_html__( 'View your profile change history below. Only administrators can restore previous versions.', 'nobloat-user-foundry' ) . '</div>';
			}

			$version_history_html = '<div class="nbuf-account-section nbuf-vh-account">' . $info_message . NBUF_Version_History::render_viewer( $user_id, 'account', $allow_user_revert ) . '</div>';

			/* Enqueue version history CSS via CSS Manager */
			NBUF_CSS_Manager::enqueue_css(
				'nbuf-version-history',
				'version-history',
				'nbuf_version_history_custom_css',
				'nbuf_css_write_failed_version_history'
			);
			wp_enqueue_script(
				'nbuf-version-history',
				plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js',
				array( 'jquery' ),
				'1.4.0',
				true
			);
			wp_localize_script( 'nbuf-version-history', 'NBUF_VersionHistory', NBUF_Version_History::get_script_data( $allow_user_revert ) );

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
		$password_requirements_text = NBUF_Password_Validator::get_requirements_text();

		/* Build security tab content (always shown - contains Password and optional 2FA) */
		$security_tab_html    = NBUF_2FA_Account::build_security_tab_html( $user_id, $password_requirements_text );
		$security_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="security">' . esc_html__( 'Security', 'nobloat-user-foundry' ) . '</button>';
		$security_tab_content = '<div class="nbuf-tab-content" data-tab="security">' . $security_tab_html . '</div>';

		/* Build sessions tab content if enabled */
		$sessions_tab_button  = '';
		$sessions_tab_content = '';
		$sessions_enabled     = NBUF_Options::get( 'nbuf_session_management_enabled', true );
		if ( $sessions_enabled && class_exists( 'NBUF_Sessions' ) ) {
			$sessions_tab_html    = NBUF_Sessions::build_sessions_tab_html( $user_id );
			$sessions_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="sessions">' . esc_html__( 'Sessions', 'nobloat-user-foundry' ) . '</button>';
			$sessions_tab_content = '<div class="nbuf-tab-content" data-tab="sessions">' . $sessions_tab_html . '</div>';

			/* Enqueue sessions JavaScript */
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-sessions', 'assets/js/frontend/sessions.js', array() );
			wp_localize_script(
				'nbuf-sessions',
				'NBUF_Sessions',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'i18n'     => array(
						'revoking'           => __( 'Revoking...', 'nobloat-user-foundry' ),
						'revoke'             => __( 'Revoke', 'nobloat-user-foundry' ),
						'session_revoked'    => __( 'Session revoked.', 'nobloat-user-foundry' ),
						'all_revoked'        => __( 'All other sessions have been logged out.', 'nobloat-user-foundry' ),
						'error'              => __( 'An error occurred.', 'nobloat-user-foundry' ),
						'confirm_revoke_all' => __( 'Are you sure you want to log out all other sessions?', 'nobloat-user-foundry' ),
					),
				)
			);
		}

		/* Build activity tab content if enabled */
		$activity_tab_button  = '';
		$activity_tab_content = '';
		$activity_enabled     = NBUF_Options::get( 'nbuf_activity_dashboard_enabled', true );
		if ( $activity_enabled && class_exists( 'NBUF_Activity_Dashboard' ) ) {
			$activity_tab_html    = NBUF_Activity_Dashboard::build_activity_tab_html( $user_id );
			$activity_tab_button  = '<button type="button" class="nbuf-tab-button" data-tab="activity">' . esc_html__( 'Activity', 'nobloat-user-foundry' ) . '</button>';
			$activity_tab_content = '<div class="nbuf-tab-content" data-tab="activity">' . $activity_tab_html . '</div>';

			/* Enqueue activity JavaScript */
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-activity', 'assets/js/frontend/activity.js', array() );
			wp_localize_script(
				'nbuf-activity',
				'nbuf_activity',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'loading_text' => __( 'Loading...', 'nobloat-user-foundry' ),
					/* translators: 1: items shown, 2: total items */
					'count_text'   => __( 'Showing %1$d of %2$d activities', 'nobloat-user-foundry' ),
				)
			);

			/* Add activity CSS inline */
			wp_add_inline_style( 'nbuf-account', NBUF_Activity_Dashboard::get_css() );
		}

		/* Build policies tab content if enabled */
		$policies_tab_button    = '';
		$policies_tab_content   = '';
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
					<p class="description nbuf-description-warning nbuf-section-spacing">
						<strong>' . esc_html__( 'Verification Required:', 'nobloat-user-foundry' ) . '</strong> ' .
						esc_html__( 'A verification link will be sent to your new email address. The change will not take effect until you click the link to confirm.', 'nobloat-user-foundry' ) . '
					</p>';
			}

			/* Check for pending email change */
			$pending_email        = get_user_meta( $user_id, 'nbuf_pending_email', true );
			$pending_email_notice = '';
			if ( $pending_email ) {
				$pending_email_notice = '
					<div class="nbuf-message nbuf-message-info nbuf-section-spacing">
						<strong>' . esc_html__( 'Pending Email Change:', 'nobloat-user-foundry' ) . '</strong> ' .
						sprintf(
							/* translators: %s: pending email address */
							esc_html__( 'A verification email has been sent to %s. Click the link in that email to complete the change.', 'nobloat-user-foundry' ),
							'<strong>' . esc_html( $pending_email ) . '</strong>'
						) . '
					</div>';
			}

			$email_tab_button = '<button type="button" class="nbuf-tab-button" data-tab="email">' . esc_html__( 'Email', 'nobloat-user-foundry' ) . '</button>';

			$email_tab_content = '
			<div class="nbuf-tab-content" data-tab="email">
				<div class="nbuf-account-section">
					<h3>' . esc_html__( 'Change Email Address', 'nobloat-user-foundry' ) . '</h3>
					<p class="nbuf-method-description">' . esc_html__( 'Update your account email address. This email is used for account notifications and password recovery.', 'nobloat-user-foundry' ) . '</p>
					' . $pending_email_notice . '
					' . $verification_notice . '
					<div class="nbuf-content-box">
						<form method="post" action="' . esc_url( self::get_current_page_url() ) . '" class="nbuf-account-form nbuf-email-change-form">
							' . $nonce_field_email . '
							<input type="hidden" name="nbuf_account_action" value="change_email">
							<input type="hidden" name="nbuf_active_tab" value="email">
							<div class="nbuf-form-group">
								<label for="current_email" class="nbuf-form-label">' . esc_html__( 'Current Email', 'nobloat-user-foundry' ) . '</label>
								<input type="email" id="current_email" class="nbuf-form-input nbuf-form-input-disabled" value="' . esc_attr( $current_user->user_email ) . '" disabled>
							</div>
							<div class="nbuf-form-group">
								<label for="new_email" class="nbuf-form-label">' . esc_html__( 'New Email Address', 'nobloat-user-foundry' ) . '</label>
								<input type="email" id="new_email" name="new_email" class="nbuf-form-input" required>
							</div>
							<div class="nbuf-form-group">
								<label for="email_confirm_password" class="nbuf-form-label">' . esc_html__( 'Confirm Your Password', 'nobloat-user-foundry' ) . '</label>
								<input type="password" id="email_confirm_password" name="email_confirm_password" class="nbuf-form-input" required>
								<div class="nbuf-form-help">' . esc_html__( 'Enter your current password to confirm this change.', 'nobloat-user-foundry' ) . '</div>
							</div>
							<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Update Email', 'nobloat-user-foundry' ) . '</button>
						</form>
					</div>
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

		/* Build custom tabs if any are configured */
		$custom_tabs_buttons = '';
		$custom_tabs_content = '';
		if ( class_exists( 'NBUF_Custom_Tabs' ) ) {
			$user_custom_tabs = NBUF_Custom_Tabs::get_for_user( $user_id );
			$has_tab_icons    = false;

			foreach ( $user_custom_tabs as $custom_tab ) {
				$tab_slug = esc_attr( $custom_tab['slug'] );
				$tab_name = esc_html( $custom_tab['name'] );

				/* Build icon HTML if set */
				$icon_html = '';
				if ( ! empty( $custom_tab['icon'] ) ) {
					$icon_html     = '<span class="dashicons ' . esc_attr( $custom_tab['icon'] ) . '" style="margin-right: 6px; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>';
					$has_tab_icons = true;
				}

				/* Tab button */
				$custom_tabs_buttons .= '<button type="button" class="nbuf-tab-button" data-tab="' . $tab_slug . '">' . $icon_html . $tab_name . '</button>';

				/* Tab content - process shortcodes */
				$tab_content          = do_shortcode( $custom_tab['content'] );
				$custom_tabs_content .= '<div class="nbuf-tab-content" data-tab="' . $tab_slug . '"><div class="nbuf-account-section">' . $tab_content . '</div></div>';
			}

			/* Enqueue dashicons if any custom tab has an icon */
			if ( $has_tab_icons ) {
				wp_enqueue_style( 'dashicons' );
			}
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
			'{sessions_tab_button}'          => $sessions_tab_button,
			'{sessions_tab_content}'         => $sessions_tab_content,
			'{activity_tab_button}'          => $activity_tab_button,
			'{activity_tab_content}'         => $activity_tab_content,
			'{policies_tab_button}'          => $policies_tab_button,
			'{policies_tab_content}'         => $policies_tab_content,
			'{custom_tabs_buttons}'          => $custom_tabs_buttons,
			'{custom_tabs_content}'          => $custom_tabs_content,
		);

		foreach ( $replacements as $placeholder => $value ) {
			$template = str_replace( $placeholder, $value, $template );
		}

		/*
		 * Fallback injection for custom tabs if template doesn't have dedicated placeholders.
		 * This handles templates stored in database before custom tabs feature was added.
		 * Uses preg_replace_callback() to avoid regex backreference interpretation of $ and \ in content.
		 */
		if ( ! empty( $custom_tabs_buttons ) && strpos( $template, $custom_tabs_buttons ) === false ) {
			/* Inject buttons before closing </div> of tab navigation */
			$template = preg_replace_callback(
				'/(<div[^>]*class="[^"]*nbuf-account-tabs[^"]*"[^>]*>.*?)(<\/div>)/s',
				function ( $matches ) use ( $custom_tabs_buttons ) {
					return $matches[1] . $custom_tabs_buttons . $matches[2];
				},
				$template,
				1
			);
			/* Inject content before closing </div> of main wrapper */
			$template = preg_replace_callback(
				'/(.*)(<\/div>\s*)$/s',
				function ( $matches ) use ( $custom_tabs_content ) {
					return $matches[1] . $custom_tabs_content . $matches[2];
				},
				$template,
				1
			);
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
	 * @param  WP_User     $user         User object.
	 * @param  object|null $profile_data Profile data object.
	 * @return string HTML output.
	 */
	private static function build_profile_fields_html( WP_User $user, ?object $profile_data ): string {
		/* Get enabled account profile fields */
		$enabled_fields   = NBUF_Profile_Data::get_account_fields();
		$field_registry   = NBUF_Profile_Data::get_field_registry();
		$custom_labels    = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
		$show_description = NBUF_Options::get( 'nbuf_show_description_field', false );

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
		$first_name         = get_user_meta( $user->ID, 'first_name', true );
		$last_name          = get_user_meta( $user->ID, 'last_name', true );
		$description        = get_user_meta( $user->ID, 'description', true );
		$first_name_label   = ! empty( $custom_labels['first_name'] ) ? $custom_labels['first_name'] : $default_labels['first_name'];
		$last_name_label    = ! empty( $custom_labels['last_name'] ) ? $custom_labels['last_name'] : $default_labels['last_name'];
		$display_name_label = ! empty( $custom_labels['display_name'] ) ? $custom_labels['display_name'] : $default_labels['display_name'];
		$user_url_label     = ! empty( $custom_labels['user_url'] ) ? $custom_labels['user_url'] : $default_labels['user_url'];
		$description_label  = ! empty( $custom_labels['description'] ) ? $custom_labels['description'] : $default_labels['description'];

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="first_name" class="nbuf-form-label">' . esc_html( $first_name_label ) . '</label>
				<input type="text" id="first_name" name="first_name" class="nbuf-form-input nbuf-form-input-full" value="' . esc_attr( $first_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="last_name" class="nbuf-form-label">' . esc_html( $last_name_label ) . '</label>
				<input type="text" id="last_name" name="last_name" class="nbuf-form-input nbuf-form-input-full" value="' . esc_attr( $last_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="display_name" class="nbuf-form-label">' . esc_html( $display_name_label ) . '</label>
				<input type="text" id="display_name" name="display_name" class="nbuf-form-input nbuf-form-input-full" value="' . esc_attr( $user->display_name ) . '">
			</div>',
			'full_width' => false,
		);

		$field_items[] = array(
			'html'       => '<div class="nbuf-form-group">
				<label for="user_url" class="nbuf-form-label">' . esc_html( $user_url_label ) . '</label>
				<input type="url" id="user_url" name="user_url" class="nbuf-form-input nbuf-form-input-full" value="' . esc_attr( $user->user_url ) . '">
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
			$label         = ! empty( $custom_labels[ $field_key ] ) ? $custom_labels[ $field_key ] : ( isset( $default_labels[ $field_key ] ) ? $default_labels[ $field_key ] : ucwords( str_replace( '_', ' ', $field_key ) ) );
			$value         = isset( $profile_data->$field_key ) ? $profile_data->$field_key : '';
			$input_type    = self::get_field_input_type( $field_key );
			$is_full_width = in_array( $field_key, $full_width_fields, true );

			/* Special handling for timezone field - searchable select */
			if ( 'timezone' === $field_key ) {
				$field_html = self::render_timezone_select( $field_key, $value, $label, false, 'account' );
			} elseif ( $is_full_width ) {
				$field_html = '<div class="nbuf-form-group nbuf-form-group-full">
					<label for="' . esc_attr( $field_key ) . '" class="nbuf-form-label">' . esc_html( $label ) . '</label>
					<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" class="nbuf-form-input nbuf-form-textarea">' . esc_textarea( $value ) . '</textarea>
				</div>';
			} else {
				$field_html = '<div class="nbuf-form-group">
					<label for="' . esc_attr( $field_key ) . '" class="nbuf-form-label">' . esc_html( $label ) . '</label>
					<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" class="nbuf-form-input nbuf-form-input-full" value="' . esc_attr( $value ) . '">
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
	 *
	 * @return void
	 */
	public static function maybe_handle_account_actions(): void {
		if ( is_admin() ) {
			return;
		}

		/*
		 * Handle standard account actions.
		 */
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

		/*
		 * Handle 2FA actions.
		 */
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
	 * @param  array<string, mixed> $args Query args to add to URL.
	 * @return string Redirect URL with tab parameters.
	 */
	private static function build_account_redirect( array $args = array() ): string {
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
	 * @return void
	 */
	public static function set_flash_message( int $user_id, string $message, string $type = 'success' ): void {
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
	 * @return array{message: string, type: string}|false Message array with 'message' and 'type' keys, or false if none.
	 */
	private static function get_flash_message( int $user_id ) {
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
	 *
	 * @return void
	 */
	private static function handle_profile_update(): void {
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

		/* Collect NoBloat profile fields - use ALL available fields from registry */
		$all_profile_fields = NBUF_Profile_Data::get_all_field_keys();

		/* Textarea fields that need special handling */
		$textarea_fields = array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' );

		/* URL fields that need URL sanitization */
		$url_fields = array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok', 'twitch', 'reddit', 'snapchat', 'soundcloud', 'vimeo', 'spotify', 'pinterest' );

		/* Email fields that need email sanitization */
		$email_fields = array( 'secondary_email', 'work_email', 'supervisor_email' );

		$profile_data = array();

		foreach ( $all_profile_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( in_array( $field, $textarea_fields, true ) ) {
					/* SECURITY: Enforce maximum length for textarea fields (5000 chars) */
					$value                  = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
					$profile_data[ $field ] = mb_substr( $value, 0, 5000 );
				} elseif ( in_array( $field, $url_fields, true ) ) {
					/* SECURITY: Enforce maximum length for URL (500 chars) */
					$value                  = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
					$profile_data[ $field ] = mb_substr( $value, 0, 500 );
				} elseif ( in_array( $field, $email_fields, true ) ) {
					/* Email fields */
					$profile_data[ $field ] = sanitize_email( wp_unslash( $_POST[ $field ] ) );
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
	 *
	 * @return void
	 */
	private static function handle_password_change(): void {
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
		} elseif ( mb_strlen( $new_password, 'UTF-8' ) < 8 ) {
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

		/* Re-authenticate user - must clear old cookie, reset user, then set new cookie */
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

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
	 *
	 * @return void
	 */
	private static function handle_email_change(): void {
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

		/* Check if verification is required for email changes */
		$verify_email_change = NBUF_Options::get( 'nbuf_verify_email_change', true );

		if ( $verify_email_change ) {
			/*
			 * PENDING EMAIL FLOW
			 * Store new email as pending, send verification to new email.
			 * Actual email change happens only after verification.
			 */
			update_user_meta( $user_id, 'nbuf_pending_email', $new_email );

			/* Generate verification token */
			$token   = bin2hex( random_bytes( 32 ) );
			$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

			/* Store token with pending email */
			NBUF_Database::insert_token( $user_id, $new_email, $token, $expires, 0 );

			/* Send verification to the NEW email */
			NBUF_Email::send_verification_email( $new_email, $token, $current_user );

			/* Log pending email change */
			NBUF_Audit_Log::log(
				$user_id,
				'email_change_pending',
				'info',
				'User requested email change, verification pending',
				array(
					'old_email' => $old_email,
					'new_email' => $new_email,
				)
			);

			self::set_flash_message(
				$user_id,
				__( 'A verification link has been sent to your new email address. Your email will be updated once you click the link to confirm.', 'nobloat-user-foundry' ),
				'success'
			);
		} else {
			/*
			 * IMMEDIATE EMAIL CHANGE
			 * No verification required, change email immediately.
			 */

			/* Disable WordPress's built-in email change notification - we send our own */
			add_filter( 'send_email_change_email', '__return_false' );

			$result = wp_update_user(
				array(
					'ID'         => $user_id,
					'user_email' => $new_email,
				)
			);

			/* Re-enable WordPress email change notification */
			remove_filter( 'send_email_change_email', '__return_false' );

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
	 * @return void
	 */
	public static function send_email_change_notification( int $user_id, string $old_email, string $new_email ): void {
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
		NBUF_Email::send( $old_email, $subject, $message );
	}

	/**
	 * ==========================================================
	 * HANDLE VISIBILITY UPDATE
	 * ----------------------------------------------------------
	 * Process profile visibility form submission.
	 * ==========================================================
	 *
	 * @return void
	 */
	private static function handle_visibility_update(): void {
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
		$privacy       = isset( $_POST['nbuf_profile_privacy'] ) ? sanitize_key( wp_unslash( $_POST['nbuf_profile_privacy'] ) ) : 'private';
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
	 *
	 * @return void
	 */
	private static function handle_gravatar_update(): void {
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
	 *
	 * @return void
	 */
	private static function handle_profile_tab_update(): void {
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
	 *
	 * @return void
	 */
	private static function handle_data_export(): void {
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

		/* Set HTTP 200 OK status (WordPress may have set 404 for virtual URL) */
		status_header( 200 );
		nocache_headers();

		/*
		 * Verify the file is a valid ZIP before sending.
		 */
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading ZIP signature to verify file integrity.
		$fp = fopen( $export_file, 'rb' );
		if ( $fp ) {
			$signature = fread( $fp, 4 );
			fclose( $fp );
			// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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

		/*
		 * Clean up file after sending.
		 */
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Logout HTML or redirect.
	 */
	public static function sc_logout( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
					<button type="button" class="nbuf-button nbuf-button-secondary" onclick="window.location.href='<?php echo esc_url( home_url( '/' ) ); ?>'">
		<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
					</button>
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string 2FA verification form HTML.
	 */
	public static function sc_2fa_verify( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string TOTP setup page HTML.
	 */
	public static function sc_totp_setup( array $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts required by WordPress shortcode API
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
			return '<p class="nbuf-centered-message">' . esc_html__( 'Authenticator app 2FA is not enabled on this site.', 'nobloat-user-foundry' ) . '</p>';
		}

		/*
		 * Handle TOTP setup form submission.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( isset( $_POST['nbuf_2fa_setup_totp'] ) ) {
			return self::handle_totp_setup_submission( $user_id );
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
					<p><button type="button" class="nbuf-button nbuf-button-primary" onclick="window.location.href='<?php echo esc_url( get_permalink( $account_page_id ) ); ?>'"><?php esc_html_e( 'Go to Account', 'nobloat-user-foundry' ); ?></button></p>
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
							printf( esc_html__( 'You have %d day(s) remaining to complete setup.', 'nobloat-user-foundry' ), (int) $days_left );
							?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				/* Generate new secret for setup using settings from Security > Authenticator */
				$secret      = NBUF_TOTP::generate_secret();
				$username    = wp_get_current_user()->user_email;
				$issuer      = get_bloginfo( 'name' );
				$code_length = (int) NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
				$time_window = (int) NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
				$uri         = NBUF_TOTP::get_provisioning_uri( $secret, $username, $issuer, $code_length, $time_window );
				$qr_code     = NBUF_QR_Code::generate( $uri, NBUF_Options::get( 'nbuf_2fa_totp_qr_size', 200 ) );

				/* Load template and replace placeholders directly (avoids shortcode quote issues) */
				$template = NBUF_Template_Manager::load_template( '2fa-setup-totp' );

				/* Get action URL for Universal Router */
				$action_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
					? NBUF_Universal_Router::get_url( '2fa-setup' )
					: get_permalink();

				/* Get cancel URL - back to account security > authenticator subtab */
				$cancel_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
					? add_query_arg( 'subtab', 'authenticator', NBUF_Universal_Router::get_url( 'account', 'security' ) )
					: add_query_arg(
						array(
							'tab'    => 'security',
							'subtab' => 'authenticator',
						),
						self::get_account_url()
					);

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
					'{cancel_url}'      => esc_url( $cancel_url ),
					'{nonce_field}'     => $nonce_field,
					'{success_message}' => '',
					'{error_message}'   => '',
				);
				$template     = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

				echo $template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template already escaped
				?>
			<?php endif; ?>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * ==========================================================
	 * HANDLE TOTP SETUP SUBMISSION
	 * ----------------------------------------------------------
	 * Process the TOTP setup form submission.
	 * Verifies the code against the submitted secret and enables TOTP.
	 * ==========================================================
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output (success message or form with error).
	 */
	private static function handle_totp_setup_submission( int $user_id ): string {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_setup_totp' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Get submitted data */
		$secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
		$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		/* Validate inputs */
		if ( empty( $secret ) || empty( $code ) ) {
			return self::render_totp_setup_with_error(
				$user_id,
				__( 'Please enter the verification code from your authenticator app.', 'nobloat-user-foundry' )
			);
		}

		/* Get settings from Security > Authenticator tab */
		$code_length = (int) NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
		$time_window = (int) NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
		$tolerance   = (int) NBUF_Options::get( 'nbuf_2fa_totp_tolerance', 1 );

		/* Validate code format */
		$code = preg_replace( '/\s+/', '', $code ); // Remove any whitespace.
		if ( ! preg_match( '/^\d{' . $code_length . '}$/', $code ) ) {
			return self::render_totp_setup_with_error(
				$user_id,
				/* translators: %d: number of digits required */
				sprintf( __( 'Please enter a valid %d-digit code.', 'nobloat-user-foundry' ), $code_length )
			);
		}

		/* Verify the code against the submitted secret (not stored yet) */
		$valid = NBUF_TOTP::verify_code( $secret, $code, $tolerance, $code_length, $time_window );

		if ( ! $valid ) {
			return self::render_totp_setup_with_error(
				$user_id,
				__( 'Invalid verification code. Please check your authenticator app and try again.', 'nobloat-user-foundry' )
			);
		}

		/* Code is valid - enable TOTP for user */
		$current_method = NBUF_2FA::get_user_method( $user_id );

		/* Determine new method (preserve email if already enabled) */
		$new_method = ( 'email' === $current_method ) ? 'both' : 'totp';

		$result = NBUF_2FA::enable_for_user( $user_id, $new_method, $secret );

		if ( is_wp_error( $result ) ) {
			return self::render_totp_setup_with_error( $user_id, $result->get_error_message() );
		}

		/* Clear grace period start if set */
		delete_user_meta( $user_id, 'nbuf_totp_grace_start' );

		/* Generate backup codes automatically */
		$backup_codes = NBUF_2FA::generate_backup_codes( $user_id );

		/* Log the setup */
		NBUF_Audit_Log::log(
			$user_id,
			'2fa_totp_enabled',
			'success',
			'User enabled authenticator app 2FA',
			array( 'method' => $new_method )
		);

		/* Enqueue CSS for 2FA pages */
		self::enqueue_frontend_css( '2fa' );

		/* Build success page with backup codes */
		$account_url = '';
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			$account_url = NBUF_Universal_Router::get_url( 'account' );
		} else {
			$account_page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
			$account_url     = $account_page_id ? get_permalink( $account_page_id ) : home_url();
		}

		ob_start();
		?>
		<div class="nbuf-totp-setup-page">
			<div class="nbuf-2fa-setup-wrapper">
				<div class="nbuf-2fa-setup-header">
					<h2><?php esc_html_e( 'Authenticator App Enabled!', 'nobloat-user-foundry' ); ?></h2>
					<p class="nbuf-setup-subtitle"><?php esc_html_e( 'Your account is now protected with two-factor authentication.', 'nobloat-user-foundry' ); ?></p>
				</div>

				<div class="nbuf-2fa-setup-success">
					<p><strong><?php esc_html_e( 'Success!', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Your authenticator app has been configured.', 'nobloat-user-foundry' ); ?></p>
				</div>

				<?php if ( ! empty( $backup_codes ) ) : ?>
				<div class="nbuf-backup-codes-section">
					<h3><?php esc_html_e( 'Save Your Backup Codes', 'nobloat-user-foundry' ); ?></h3>
					<p class="nbuf-backup-codes-description"><?php esc_html_e( 'These codes can be used to access your account if you lose your authenticator device. Each code can only be used once.', 'nobloat-user-foundry' ); ?></p>
					<p class="nbuf-important"><strong><?php esc_html_e( 'Important: Save these codes in a secure location. You will not see them again!', 'nobloat-user-foundry' ); ?></strong></p>

					<div class="nbuf-backup-codes-list">
						<?php foreach ( $backup_codes as $bc ) : ?>
							<div class="nbuf-backup-code"><?php echo esc_html( $bc ); ?></div>
						<?php endforeach; ?>
					</div>

					<div class="nbuf-backup-codes-actions">
						<button type="button" class="nbuf-button nbuf-button-secondary" onclick="navigator.clipboard.writeText('<?php echo esc_js( implode( "\n", $backup_codes ) ); ?>').then(function() { alert('<?php echo esc_js( __( 'Backup codes copied to clipboard!', 'nobloat-user-foundry' ) ); ?>'); });">
							<?php esc_html_e( 'Copy Codes', 'nobloat-user-foundry' ); ?>
						</button>
						<button type="button" class="nbuf-button nbuf-button-primary" onclick="window.location.href='<?php echo esc_url( $account_url ); ?>'"><?php esc_html_e( 'Go to Account', 'nobloat-user-foundry' ); ?></button>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render TOTP setup form with error message.
	 *
	 * @param  int    $user_id       User ID.
	 * @param  string $error_message Error message to display.
	 * @return string HTML output.
	 */
	private static function render_totp_setup_with_error( $user_id, $error_message ) {
		/* Enqueue CSS for 2FA pages */
		self::enqueue_frontend_css( '2fa' );

		/* Generate new secret for retry using settings from Security > Authenticator */
		$secret      = NBUF_TOTP::generate_secret();
		$username    = wp_get_current_user()->user_email;
		$issuer      = get_bloginfo( 'name' );
		$code_length = (int) NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
		$time_window = (int) NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
		$uri         = NBUF_TOTP::get_provisioning_uri( $secret, $username, $issuer, $code_length, $time_window );
		$qr_code     = NBUF_QR_Code::generate( $uri, NBUF_Options::get( 'nbuf_2fa_totp_qr_size', 200 ) );

		/* Load template */
		$template = NBUF_Template_Manager::load_template( '2fa-setup-totp' );

		/* Get action URL */
		$action_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
			? NBUF_Universal_Router::get_url( '2fa-setup' )
			: get_permalink();

		/* Get cancel URL - back to account security > authenticator subtab */
		$cancel_url = ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() )
			? add_query_arg( 'subtab', 'authenticator', NBUF_Universal_Router::get_url( 'account', 'security' ) )
			: add_query_arg(
				array(
					'tab'    => 'security',
					'subtab' => 'authenticator',
				),
				self::get_account_url()
			);

		/* Generate nonce field */
		ob_start();
		wp_nonce_field( 'nbuf_2fa_setup_totp', 'nbuf_2fa_nonce' );
		$nonce_field = ob_get_clean();

		/* Build error message HTML */
		$error_html  = '<div class="nbuf-error">';
		$error_html .= '<p><strong>' . esc_html__( 'Error:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( $error_message ) . '</p>';
		$error_html .= '</div>';

		/* Replace placeholders */
		$replacements = array(
			'{qr_code}'         => $qr_code,
			'{secret_key}'      => esc_attr( $secret ),
			'{account_name}'    => esc_html( $username ),
			'{action_url}'      => esc_url( $action_url ),
			'{cancel_url}'      => esc_url( $cancel_url ),
			'{nonce_field}'     => $nonce_field,
			'{success_message}' => '',
			'{error_message}'   => $error_html,
		);
		$template     = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		return '<div class="nbuf-totp-setup-page">' . $template . '</div>';
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
	 * @param  array<string, mixed> $atts    Shortcode attributes.
	 * @param  string               $content Content to restrict.
	 * @return string Restricted or allowed content.
	 */
	public static function sc_restrict( array $atts, string $content = '' ): string {
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
				'message'   => '',
			),
			$atts,
			'nbuf_restrict'
		);

		/* Build restriction output - empty string hides content silently */
		$restriction_output = '';
		if ( '' !== $atts['message'] ) {
			$restriction_output = '<div class="nbuf-restricted-content"><p>' . esc_html( $atts['message'] ) . '</p></div>';
		}

		/* Get current user */
		$user         = wp_get_current_user();
		$user_id      = $user->ID;
		$is_logged_in = is_user_logged_in();

		/*
		 * If role, verified, or expired attributes are specified,
		 * user must be logged in (these only apply to authenticated users).
		 */
		$requires_login = ! empty( $atts['role'] ) || ! empty( $atts['verified'] ) || ! empty( $atts['expired'] );
		if ( $requires_login && ! $is_logged_in ) {
			return $restriction_output;
		}

		/* Check logged_in requirement */
		if ( ! empty( $atts['logged_in'] ) ) {
			$required_logged_in = filter_var( $atts['logged_in'], FILTER_VALIDATE_BOOLEAN );
			if ( $required_logged_in && ! $is_logged_in ) {
				return $restriction_output;
			}
			if ( ! $required_logged_in && $is_logged_in ) {
				return $restriction_output;
			}
		}

		/* Check role requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['role'] ) ) {
			$required_roles = array_map( 'trim', explode( ',', $atts['role'] ) );
			$user_roles     = $user->roles;

			/* Check if user has any of the required roles */
			$has_role = ! empty( array_intersect( $user_roles, $required_roles ) );
			if ( ! $has_role ) {
				return $restriction_output;
			}
		}

		/* Check verified requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['verified'] ) ) {
			$required_verified = filter_var( $atts['verified'], FILTER_VALIDATE_BOOLEAN );
			$user_data         = NBUF_User_Data::get( $user_id );

			/* SECURITY: Check if user_data exists */
			if ( ! $user_data ) {
				return $restriction_output;
			}

			$is_verified = 1 === (int) $user_data->is_verified;

			if ( $required_verified && ! $is_verified ) {
				return $restriction_output;
			}
			if ( ! $required_verified && $is_verified ) {
				return $restriction_output;
			}
		}

		/* Check expired requirement (only if user is logged in) */
		if ( $is_logged_in && ! empty( $atts['expired'] ) ) {
			$required_not_expired = ! filter_var( $atts['expired'], FILTER_VALIDATE_BOOLEAN );
			$user_data            = NBUF_User_Data::get( $user_id );

			/* SECURITY: Check if user_data exists */
			if ( ! $user_data ) {
				return $restriction_output;
			}

			$is_expired = NBUF_User_Data::is_expired( $user_id );

			if ( $required_not_expired && $is_expired ) {
				return $restriction_output;
			}
			if ( ! $required_not_expired && ! $is_expired ) {
				return $restriction_output;
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Profile HTML.
	 */
	public static function sc_profile( array $atts ): string {
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
			$profile_data   = NBUF_Profile_Data::get( $user->ID );
			$field_registry = NBUF_Profile_Data::get_field_registry();
			$custom_labels  = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
			$enabled_fields = NBUF_Profile_Data::get_account_fields();

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

		/* Build cover photo HTML */
		if ( $allow_cover && ! empty( $cover_photo ) ) {
			$cover_photo_html = '<div class="nbuf-profile-cover" style="background-image: url(\'' . esc_url( $cover_photo ) . '\');">'
				. '<div class="nbuf-profile-cover-overlay"></div>'
				. '</div>';
		} else {
			$cover_photo_html = '<div class="nbuf-profile-cover nbuf-profile-cover-default">'
				. '<div class="nbuf-profile-cover-overlay"></div>'
				. '</div>';
		}

		/* Build profile photo URL (handle data URIs vs regular URLs) */
		$photo_src = 0 === strpos( $profile_photo, 'data:' ) ? esc_attr( $profile_photo ) : esc_url( $profile_photo );

		/*
		 * Build joined text.
		 */
		/* translators: %s: User registration date */
		$joined_text = sprintf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( $registered_date ) );

		/* Build profile fields HTML */
		$profile_fields_html = '';
		if ( ! empty( $display_fields ) ) {
			$full_width_fields    = array( 'user_url', 'description', 'website', 'bio' );
			$profile_fields_html .= '<div class="nbuf-profile-fields">';
			$profile_fields_html .= '<h2 class="nbuf-profile-fields-title">' . esc_html__( 'Profile Information', 'nobloat-user-foundry' ) . '</h2>';
			$profile_fields_html .= '<div class="nbuf-profile-fields-grid">';

			foreach ( $display_fields as $key => $field_data ) {
				$field_class = 'nbuf-profile-field';
				if ( in_array( $key, $full_width_fields, true ) ) {
					$field_class .= ' nbuf-profile-field-full';
				}

				$field_type  = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
				$field_value = $field_data['value'];

				/* Format field value based on type */
				if ( 'url' === $field_type && ! empty( $field_value ) ) {
					$formatted_value = '<a href="' . esc_url( $field_value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'email' === $field_type && ! empty( $field_value ) ) {
					$formatted_value = '<a href="mailto:' . esc_attr( $field_value ) . '">' . esc_html( $field_value ) . '</a>';
				} elseif ( 'textarea' === $field_type && ! empty( $field_value ) ) {
					$formatted_value = wpautop( wp_kses_post( $field_value ) );
				} else {
					$formatted_value = esc_html( $field_value );
				}

				$profile_fields_html .= '<div class="' . esc_attr( $field_class ) . '">';
				$profile_fields_html .= '<div class="nbuf-profile-field-label">' . esc_html( $field_data['label'] ) . '</div>';
				$profile_fields_html .= '<div class="nbuf-profile-field-value">' . $formatted_value . '</div>';
				$profile_fields_html .= '</div>';
			}

			$profile_fields_html .= '</div></div>';
		}

		/* Build custom content via action hook */
		ob_start();
		/**
		 * Hook for adding custom content to profile page
		 *
		 * @param WP_User $user User object.
		 * @param object  $user_data User data from custom table.
		 */
		do_action( 'nbuf_public_profile_content', $user, $user_data );
		$custom_content = ob_get_clean();

		/* Build edit profile button (only for own profile) */
		$edit_profile_button = '';
		if ( is_user_logged_in() && get_current_user_id() === $user->ID ) {
			$account_page_id = NBUF_Options::get( 'nbuf_page_account' );
			if ( $account_page_id ) {
				$edit_profile_button = '<div class="nbuf-profile-actions">'
					. '<button type="button" class="nbuf-button nbuf-button-primary" onclick="window.location.href=\'' . esc_url( get_permalink( $account_page_id ) ) . '\'">'
					. esc_html__( 'Edit Profile', 'nobloat-user-foundry' )
					. '</button></div>';
			}
		}

		/* Load and render template */
		$template = NBUF_Template_Manager::load_template( 'public-profile-html' );

		/* Replace placeholders */
		$replacements = array(
			'{cover_photo_html}'    => $cover_photo_html,
			'{profile_photo}'       => $photo_src,
			'{display_name}'        => esc_html( $display_name ),
			'{username}'            => esc_html( $user->user_login ),
			'{joined_text}'         => $joined_text,
			'{profile_fields_html}' => $profile_fields_html,
			'{custom_content}'      => $custom_content,
			'{edit_profile_button}' => $edit_profile_button,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * ==========================================================
	 * [nbuf_members]
	 * ----------------------------------------------------------
	 * Displays the member directory.
	 * Delegates to NBUF_Member_Directory::render_directory().
	 * ==========================================================
	 *
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string Member directory HTML.
	 */
	public static function sc_members( array $atts = array() ): string {
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
	 * Get a query parameter from the current request.
	 *
	 * On virtual pages handled by Universal Router, $_GET may not be populated
	 * by WordPress/server, so we manually parse the query string from REQUEST_URI.
	 *
	 * @param  string $param   Parameter name to retrieve.
	 * @param  mixed  $default Default value if parameter not found.
	 * @return mixed Parameter value or default.
	 */
	private static function get_query_param( $param, $default = '' ) {
		/* First try $_GET */
		if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		/* Fallback: manually parse query string for virtual pages */
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$query_string = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( $query_string ) {
				parse_str( $query_string, $query_params );
				if ( isset( $query_params[ $param ] ) ) {
					return sanitize_text_field( $query_params[ $param ] );
				}
			}
		}

		return $default;
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
	 * Get verification page URL (Universal Router or legacy).
	 *
	 * @return string Verification URL.
	 */
	public static function get_verification_url() {
		/* Use Universal Router if available */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'verify' );
		}

		/* Legacy: use page ID */
		$page_id = NBUF_Options::get( 'nbuf_page_verification', 0 );
		return $page_id ? get_permalink( $page_id ) : home_url( '/nobloat-verify' );
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
	 * Flag to track if timezone JS has been output.
	 *
	 * @var bool
	 */
	private static $timezone_js_output = false;

	/**
	 * Render a searchable timezone select field.
	 *
	 * Uses native HTML select with vanilla JS filtering for search.
	 * No third-party libraries required.
	 *
	 * @since  1.5.1
	 * @param  string $field_key   Field key/name.
	 * @param  string $value       Current selected value.
	 * @param  string $label       Field label.
	 * @param  bool   $required    Whether field is required.
	 * @param  string $context     Context: 'account' or 'registration'.
	 * @return string              HTML for the timezone select.
	 */
	private static function render_timezone_select( string $field_key, string $value, string $label, bool $required = false, string $context = 'account' ): string {
		$field_id      = 'account' === $context ? $field_key : 'nbuf_reg_' . $field_key;
		$required_html = $required ? ' <span class="required">*</span>' : '';
		$req_attr      = $required ? ' required' : '';

		/* Get all timezone identifiers grouped by region */
		$timezones         = DateTimeZone::listIdentifiers();
		$grouped_timezones = array();

		foreach ( $timezones as $tz ) {
			$parts  = explode( '/', $tz, 2 );
			$region = $parts[0];
			$city   = isset( $parts[1] ) ? str_replace( '_', ' ', $parts[1] ) : $tz;

			if ( ! isset( $grouped_timezones[ $region ] ) ) {
				$grouped_timezones[ $region ] = array();
			}
			$grouped_timezones[ $region ][ $tz ] = $city;
		}

		/* Build select options */
		$options_html = '<option value="">' . esc_html__( 'Select timezone...', 'nobloat-user-foundry' ) . '</option>';

		foreach ( $grouped_timezones as $region => $cities ) {
			$options_html .= '<optgroup label="' . esc_attr( $region ) . '">';
			foreach ( $cities as $tz_value => $tz_label ) {
				$selected      = selected( $value, $tz_value, false );
				$options_html .= '<option value="' . esc_attr( $tz_value ) . '"' . $selected . '>' . esc_html( $tz_label ) . '</option>';
			}
			$options_html .= '</optgroup>';
		}

		/* Build the searchable select component */
		$html  = '<div class="nbuf-form-group nbuf-timezone-select-wrap">';
		$html .= '<label for="' . esc_attr( $field_id ) . '" class="nbuf-form-label">' . esc_html( $label ) . $required_html . '</label>';
		$html .= '<div class="nbuf-searchable-select">';
		$html .= '<input type="text" class="nbuf-form-input nbuf-timezone-search" placeholder="' . esc_attr__( 'Search', 'nobloat-user-foundry' ) . '" autocomplete="off">';
		$html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_key ) . '" class="nbuf-form-input nbuf-timezone-select"' . $req_attr . '>';
		$html .= $options_html;
		$html .= '</select>';
		$html .= '</div>';
		$html .= '</div>';

		/* Add inline JavaScript for search filtering (only once per page) */
		if ( ! self::$timezone_js_output ) {
			self::$timezone_js_output = true;
			$html                    .= self::get_timezone_search_js();
		}

		return $html;
	}

	/**
	 * Get inline JavaScript for timezone search filtering.
	 *
	 * @since  1.5.1
	 * @return string Inline script tag.
	 */
	private static function get_timezone_search_js(): string {
		$js = <<<'JS'
<script>
(function() {
    'use strict';

    function initTimezoneSearch() {
        var wraps = document.querySelectorAll('.nbuf-searchable-select');

        wraps.forEach(function(wrap) {
            var search = wrap.querySelector('.nbuf-timezone-search');
            var select = wrap.querySelector('.nbuf-timezone-select');

            if (!search || !select || search.dataset.initialized) return;
            search.dataset.initialized = 'true';

            /* Store original options */
            var optgroups = select.querySelectorAll('optgroup');
            var originalData = [];

            optgroups.forEach(function(og) {
                var groupData = {
                    label: og.label,
                    options: []
                };
                og.querySelectorAll('option').forEach(function(opt) {
                    groupData.options.push({
                        value: opt.value,
                        text: opt.textContent,
                        selected: opt.selected
                    });
                });
                originalData.push(groupData);
            });

            /* Also store the placeholder option */
            var placeholder = select.querySelector('option:first-child');
            var placeholderText = placeholder ? placeholder.textContent : '';

            search.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var currentValue = select.value;

                /* Clear select except placeholder */
                while (select.options.length > 1) {
                    select.remove(1);
                }
                while (select.querySelectorAll('optgroup').length > 0) {
                    select.querySelector('optgroup').remove();
                }

                /* Rebuild with filtered options */
                originalData.forEach(function(group) {
                    var matchingOpts = group.options.filter(function(opt) {
                        return opt.text.toLowerCase().indexOf(query) !== -1 ||
                               opt.value.toLowerCase().indexOf(query) !== -1 ||
                               group.label.toLowerCase().indexOf(query) !== -1;
                    });

                    if (matchingOpts.length > 0 || query === '') {
                        var optgroup = document.createElement('optgroup');
                        optgroup.label = group.label;

                        var optsToShow = query === '' ? group.options : matchingOpts;
                        optsToShow.forEach(function(opt) {
                            var option = document.createElement('option');
                            option.value = opt.value;
                            option.textContent = opt.text;
                            if (opt.value === currentValue) {
                                option.selected = true;
                            }
                            optgroup.appendChild(option);
                        });

                        select.appendChild(optgroup);
                    }
                });

                /* Restore selection if still available */
                if (currentValue) {
                    select.value = currentValue;
                }
            });
        });
    }

    /* Initialize on DOM ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimezoneSearch);
    } else {
        initTimezoneSearch();
    }
})();
</script>
JS;
		return $js;
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
		NBUF_Asset_Minifier::enqueue_script(
			'nbuf-policy-tabs',
			'assets/js/frontend/policy-tabs.js',
			array()
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
	 * @param  array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML content.
	 */
	public static function sc_universal( array $atts = array() ): string {
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
	 * @return void
	 */
	private static function enqueue_universal_view_css( string $view ): void {
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
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-account-page', 'assets/js/frontend/account-page.js', array() );

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