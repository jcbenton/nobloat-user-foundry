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

class NBUF_Shortcodes {

    public static function init() {
        /* Shortcodes */
        add_shortcode('nbuf_reset_form', [__CLASS__, 'sc_reset_form']);
        add_shortcode('nbuf_request_reset_form', [__CLASS__, 'sc_request_reset_form']);
        add_shortcode('nbuf_verify_page', [__CLASS__, 'sc_verify_page']);
        add_shortcode('nbuf_login_form', [__CLASS__, 'sc_login_form']);
        add_shortcode('nbuf_registration_form', [__CLASS__, 'sc_registration_form']);
        add_shortcode('nbuf_account_page', [__CLASS__, 'sc_account_page']);
        add_shortcode('nbuf_logout', [__CLASS__, 'sc_logout']);
        add_shortcode('nbuf_2fa_verify', [__CLASS__, 'sc_2fa_verify']);
        add_shortcode('nbuf_2fa_setup', [__CLASS__, 'sc_2fa_setup']);

        /* Restriction shortcode (only if enabled) */
        $shortcode_enabled = NBUF_Options::get('nbuf_restrict_content_shortcode_enabled', false);
        if ($shortcode_enabled) {
            add_shortcode('nbuf_restrict', [__CLASS__, 'sc_restrict']);
        }

        /* Profile shortcode (only if public profiles enabled) */
        $public_profiles_enabled = NBUF_Options::get('nbuf_enable_public_profiles', false);
        if ($public_profiles_enabled) {
            add_shortcode('nbuf_profile', [__CLASS__, 'sc_profile']);
        }

        /* Handle POST submissions */
        add_action('template_redirect', [__CLASS__, 'maybe_handle_request_reset'], 2);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_password_reset']);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_login'], 5);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_registration'], 3);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_account_actions'], 3);
    }

    /* ==========================================================
       [nbuf_verify_page]
       ----------------------------------------------------------
       Handles both display and processing of email verification.
       If ?token= is present, delegates to verifier class.
       ========================================================== */
    public static function sc_verify_page($atts = []) {
        /* If token is present, delegate to verifier for processing */
        if ( ! empty( $_GET['token'] ) ) {
            return NBUF_Verifier::render_for_shortcode();
        }

        /* No token: show instructions */
        $html  = '<div class="nobloat-verify-wrapper" style="max-width:600px;margin:80px auto;text-align:center;">';
        $html .= '<h2>' . esc_html__('Email Verification', 'nobloat-user-foundry') . '</h2>';
        $html .= '<p>' . esc_html__('Follow the verification link sent to your email to complete verification.', 'nobloat-user-foundry') . '</p>';
        $html .= '</div>';
        return $html;
    }

    /* ==========================================================
       [nbuf_reset_form]
       ----------------------------------------------------------
       Render password reset form using template.
       ========================================================== */
    public static function sc_reset_form($atts = []) {
        /* Check if password reset is enabled */
        $enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
        if ( ! $enable_password_reset ) {
            return '<div class="nbuf-info-message">' . esc_html__( 'Password reset is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
        }

        /* Enqueue WordPress password strength meter */
        if ( NBUF_Password_Validator::should_enforce( 'reset' ) ) {
            wp_enqueue_script( 'password-strength-meter' );
            wp_localize_script( 'password-strength-meter', 'pwsL10n', array(
                'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
                'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
                'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
                'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
                'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
                'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
            ) );
        }

        /* Get template using Template Manager (custom table + caching) */
        $template = NBUF_Template_Manager::load_template( 'reset-form' );

        /* Build action URL */
        $login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $action_url = remove_query_arg('_wpnonce', home_url(add_query_arg([
            'login' => $login,
            'key'   => $key,
            'action'=> 'rp',
        ], parse_url( $request_uri, PHP_URL_PATH ))));

        /* Get error message from query string */
        $error_message = '';
        if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error_message = '<div class="nbuf-error-message">' . esc_html( urldecode( $error ) ) . '</div>';
        }

        /* Build password requirements list if enabled */
        $password_requirements = '';
        if ( NBUF_Password_Validator::should_enforce( 'reset' ) ) {
            $requirements = NBUF_Password_Validator::get_requirements_list();
            if ( ! empty( $requirements ) ) {
                $password_requirements = '<div class="nbuf-password-requirements">';
                $password_requirements .= '<p class="nbuf-requirements-title">' . esc_html__( 'Password must include:', 'nobloat-user-foundry' ) . '</p>';
                $password_requirements .= '<ul class="nbuf-requirements-list">';
                foreach ( $requirements as $requirement ) {
                    $password_requirements .= '<li>' . esc_html( $requirement ) . '</li>';
                }
                $password_requirements .= '</ul></div>';
            }
        }

        /* Get login URL */
        $login_page_id = NBUF_Options::get( 'nbuf_page_login', 0 );
        $login_url = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();

        /* Replace template placeholders */
        $replacements = array(
            '{action_url}'           => esc_url( $action_url ),
            '{nonce_field}'          => wp_nonce_field( 'nbuf_reset', 'nbuf_reset_nonce', true, false ),
            '{error_message}'        => $error_message,
            '{password_requirements}' => $password_requirements,
            '{login_url}'            => esc_url( $login_url ),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /* ==========================================================
       [nbuf_request_reset_form]
       ----------------------------------------------------------
       Render form to request a password reset link.
       ========================================================== */
    public static function sc_request_reset_form($atts = []) {
        /* Check if password reset is enabled */
        $enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
        if ( ! $enable_password_reset ) {
            return '<div class="nbuf-info-message">' . esc_html__( 'Password reset is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
        }

        /* Get template using Template Manager (custom table + caching) */
        $template = NBUF_Template_Manager::load_template( 'request-reset-form' );

        /* Get success/error messages from query parameters */
        $success_message = '';
        $error_message   = '';

        if ( isset( $_GET['reset'] ) && 'sent' === $_GET['reset'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $success_message = '<div class="nbuf-success-message">' . esc_html__( 'Password reset link has been sent to your email address.', 'nobloat-user-foundry' ) . '</div>';
        }

        if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error_message = '<div class="nbuf-error-message">' . esc_html( urldecode( $error ) ) . '</div>';
        }

        /* Get login and registration URLs */
        $login_page_id = NBUF_Options::get( 'nbuf_page_login', 0 );
        $login_url = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();

        $registration_page_id = NBUF_Options::get( 'nbuf_page_registration', 0 );
        $register_link = '';
        $enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
        if ( $enable_registration && $registration_page_id ) {
            $register_link = '<a href="' . esc_url( get_permalink( $registration_page_id ) ) . '" class="nbuf-request-reset-link">' . esc_html__( 'Create an Account', 'nobloat-user-foundry' ) . '</a>';
        }

        /* Build action URL */
        $action_url = get_permalink();

        /* Replace template placeholders */
        $replacements = array(
            '{action_url}'      => esc_url( $action_url ),
            '{nonce_field}'     => wp_nonce_field( 'nbuf_request_reset', 'nbuf_request_reset_nonce', true, false ),
            '{error_message}'   => $error_message,
            '{success_message}' => $success_message,
            '{login_url}'       => esc_url( $login_url ),
            '{register_link}'   => $register_link,
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /* ==========================================================
       maybe_handle_request_reset()
       ----------------------------------------------------------
       Process POST from request reset form (forgot password).
       ========================================================== */
    public static function maybe_handle_request_reset() {
        if ( is_admin() ) {
            return;
        }

        $page_id = NBUF_Options::get('nbuf_page_request_reset');
        if ( ! $page_id || ! is_page( $page_id ) ) {
            return;
        }

        if ( empty( $_POST['nbuf_request_reset_action'] ) ) {
            return;
        }

        /* Nonce verification */
        if ( ! isset( $_POST['nbuf_request_reset_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nbuf_request_reset_nonce'] ), 'nbuf_request_reset' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
        }

        $user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

        if ( empty( $user_login ) ) {
            wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Please enter your email address.', 'nobloat-user-foundry' ) ), get_permalink( $page_id ) ) );
            exit;
        }

        /* Rate limiting: 3 requests per 15 minutes per email */
        $rate_key = 'nbuf_password_reset_rate_' . md5( strtolower( $user_login ) );
        $attempts = (int) get_transient( $rate_key );

        if ( $attempts >= 3 ) {
            wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Too many password reset attempts. Please try again later.', 'nobloat-user-foundry' ) ), get_permalink( $page_id ) ) );
            exit;
        }

        /* Increment rate limit counter */
        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

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
            $reset_url = add_query_arg( array(
                'action' => 'rp',
                'key'    => $key,
                'login'  => rawurlencode( $user->user_login ),
            ), get_permalink( $reset_page_id ) );
        } else {
            $reset_url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );
        }

        /* Send email using template */
        $mode = 'html'; // Default to HTML, can be made configurable later
        $template_name = ( 'html' === $mode ) ? 'password-reset-html' : 'password-reset-text';
        $template = NBUF_Template_Manager::load_template( $template_name );

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

        /* Subject */
        $subject = sprintf( __( '[%s] Password Reset', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

        /* Set content type */
        $content_type_callback = function () use ( $mode ) {
            return 'html' === $mode ? 'text/html' : 'text/plain';
        };
        add_filter( 'wp_mail_content_type', $content_type_callback );

        /* Send email */
        wp_mail( $user->user_email, $subject, $message );

        /* Remove filter */
        remove_filter( 'wp_mail_content_type', $content_type_callback );

        /* Log password reset request */
        NBUF_Audit_Log::log(
            $user->ID,
            'password_reset_requested',
            'success',
            'Password reset link requested',
            array( 'email' => $user->user_email, 'username' => $user->user_login )
        );

        /* Redirect with success message */
        wp_safe_redirect( add_query_arg( 'reset', 'sent', get_permalink( $page_id ) ) );
        exit;
    }

    /* ==========================================================
       maybe_handle_password_reset()
       ----------------------------------------------------------
       Process POST from our reset form on the configured page.
       ========================================================== */
    public static function maybe_handle_password_reset() {
        if ( is_admin() ) {
            return;
        }

        $page_id = NBUF_Options::get('nbuf_page_password_reset');
        if ( ! $page_id || ! is_page( $page_id ) ) {
            return;
        }

        if ( empty( $_POST['nbuf_reset_action'] ) ) {
            return;
        }

        /* Nonce */
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

        $pass1 = isset( $_POST['pass1'] ) ? wp_unslash( $_POST['pass1'] ) : '';
        $pass2 = isset( $_POST['pass2'] ) ? wp_unslash( $_POST['pass2'] ) : '';

        if ( $pass1 === '' || $pass2 === '' ) {
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
            array( 'username' => $user->user_login, 'method' => 'reset_link' )
        );

        # Success: redirect to login
        wp_safe_redirect( wp_login_url() );
        exit;
    }

    /* ==========================================================
       [nbuf_login_form]
       ----------------------------------------------------------
       Renders a customizable login form from database template.
       ========================================================== */
    public static function sc_login_form( $atts = array() ) {
        # Check if custom login is enabled
        $enable_login = NBUF_Options::get( 'nbuf_enable_login', true );
        if ( ! $enable_login ) {
            return '<div class="nbuf-info-message">' . esc_html__( 'Custom login is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
        }

        # Don't show login form if user is already logged in
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $message = sprintf(
                esc_html__( 'You are already logged in as %s.', 'nobloat-user-foundry' ),
                '<strong>' . esc_html( $current_user->display_name ) . '</strong>'
            );
            return '<div class="nbuf-login-notice">' . $message . '</div>';
        }

        # Get template using Template Manager (custom table + caching)
        $template = NBUF_Template_Manager::load_template( 'login-form' );

        # Get current URL for redirect
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $current_url = home_url( $request_uri );

        # Get redirect URL from shortcode attribute or use current page
        $atts = shortcode_atts( array(
            'redirect' => $current_url,
        ), $atts, 'nbuf_login_form' );

        # Build action URL
        $action_url = esc_url( $current_url );

        # Get password reset page URL (only if enabled)
        $reset_url = '';
        $enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
        if ( $enable_password_reset ) {
            $reset_page_id = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
            if ( $reset_page_id ) {
                $reset_url = get_permalink( $reset_page_id );
            }
        }

        # Build register link if registration is enabled
        $register_link = '';
        if ( get_option( 'users_can_register' ) ) {
            $register_url = wp_registration_url();
            $register_link = '<a href="' . esc_url( $register_url ) . '" class="nbuf-login-link">' . esc_html__( 'Register', 'nobloat-user-foundry' ) . '</a>';
        }

        # Get error message if present
        $error_message = '';
        if ( isset( $_GET['login'] ) ) {
            $login_status = sanitize_text_field( wp_unslash( $_GET['login'] ) );
            switch ( $login_status ) {
                case 'failed':
                    $error_message = '<div class="nbuf-login-error">' . esc_html__( 'Invalid username or password.', 'nobloat-user-foundry' ) . '</div>';
                    break;
                case 'unverified':
                    $error_message = '<div class="nbuf-login-error">' . esc_html__( 'Please verify your email address before logging in.', 'nobloat-user-foundry' ) . '</div>';
                    break;
                case 'disabled':
                    $error_message = '<div class="nbuf-login-error">' . esc_html__( 'Your account has been disabled. Please contact support.', 'nobloat-user-foundry' ) . '</div>';
                    break;
                case 'expired':
                    $error_message = '<div class="nbuf-login-error">' . esc_html__( 'Your account has expired. Please contact support.', 'nobloat-user-foundry' ) . '</div>';
                    break;
            }
        }

        # Generate nonce field
        ob_start();
        wp_nonce_field( 'nbuf_login', 'nbuf_login_nonce', false );
        $nonce_field = ob_get_clean();

        # Replace placeholders in template
        $html = str_replace(
            array(
                '{action_url}',
                '{nonce_field}',
                '{redirect_to}',
                '{reset_url}',
                '{register_link}',
                '{error_message}',
            ),
            array(
                $action_url,
                $nonce_field,
                esc_attr( $atts['redirect'] ),
                esc_url( $reset_url ),
                $register_link,
                $error_message,
            ),
            $template
        );

        return $html;
    }

    /* ==========================================================
       maybe_handle_login()
       ----------------------------------------------------------
       Process POST from login form.
       Checks verification status before allowing login.
       ========================================================== */
    public static function maybe_handle_login() {
        if ( is_admin() ) {
            return;
        }

        # Check if this is a login form submission
        if ( empty( $_POST['nbuf_login_action'] ) ) {
            return;
        }

        # Verify nonce
        if ( ! isset( $_POST['nbuf_login_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nbuf_login_nonce'] ), 'nbuf_login' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
        }

        # Get credentials
        $username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
        $password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '';
        $remember = isset( $_POST['rememberme'] ) && 'forever' === $_POST['rememberme'];

        /* Validate redirect_to - prevent open redirect vulnerability */
        $redirect = home_url();
        if ( isset( $_POST['redirect_to'] ) ) {
            $redirect_candidate = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
            /* Only allow internal redirects */
            $validated_redirect = wp_validate_redirect( $redirect_candidate, false );
            if ( $validated_redirect ) {
                $redirect = $validated_redirect;
            }
        }

        # Validate inputs
        if ( empty( $username ) || empty( $password ) ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            $current_url = home_url( $request_uri );
            wp_safe_redirect( add_query_arg( 'login', 'failed', $current_url ) );
            exit;
        }

        # Attempt authentication
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon( $creds, is_ssl() );

        # Check for authentication errors
        if ( is_wp_error( $user ) ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            $current_url = home_url( $request_uri );
            wp_safe_redirect( add_query_arg( 'login', 'failed', $current_url ) );
            exit;
        }

        # User authenticated - now check verification/expiration status
        # This integrates with existing NBUF_Hooks::check_login_verification
        # which is already hooked into 'authenticate' filter

        # If we got here, user is logged in and passed all checks
        wp_safe_redirect( $redirect );
        exit;
    }

    /* ==========================================================
       [nbuf_registration_form]
       ----------------------------------------------------------
       Renders a customizable registration form with dynamic fields.
       ========================================================== */
    public static function sc_registration_form( $atts ) {
        /* Check if registration is enabled */
        $enable_registration = NBUF_Options::get( 'nbuf_enable_registration', true );
        if ( ! $enable_registration ) {
            return '<div class="nbuf-info-message">' . esc_html__( 'User registration is currently disabled.', 'nobloat-user-foundry' ) . '</div>';
        }

        /* Don't show form if user is already logged in */
        if ( is_user_logged_in() ) {
            return '<div class="nbuf-info-message">' . esc_html__( 'You are already logged in.', 'nobloat-user-foundry' ) . '</div>';
        }

        /* Enqueue WordPress password strength meter */
        if ( NBUF_Password_Validator::should_enforce( 'registration' ) ) {
            wp_enqueue_script( 'password-strength-meter' );
            wp_localize_script( 'password-strength-meter', 'pwsL10n', array(
                'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
                'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
                'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
                'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
                'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
                'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
            ) );
        }

        /* Get template using Template Manager (custom table + caching) */
        $template = NBUF_Template_Manager::load_template( 'registration-form' );

        /* Get settings */
        $login_url = wp_login_url();

        /* Get success/error messages from query parameters */
        $success_message = '';
        $error_message   = '';

        if ( isset( $_GET['registration'] ) && 'success' === $_GET['registration'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $success_message = '<div class="nbuf-success-message">' . esc_html__( 'Registration successful! Please check your email to verify your account.', 'nobloat-user-foundry' ) . '</div>';
        }

        if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error_message = '<div class="nbuf-error-message">' . esc_html( urldecode( $error ) ) . '</div>';
        }

        /* Build username field HTML if needed */
        $username_field_html = '';
        if ( NBUF_Registration::should_show_username_field() ) {
            $username_value = isset( $_POST['username'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['username'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $username_field_html = '
                <div class="nbuf-form-group">
                    <label for="nbuf_reg_username">' . esc_html__( 'Username', 'nobloat-user-foundry' ) . ' <span class="required">*</span></label>
                    <input type="text" id="nbuf_reg_username" name="username" required value="' . $username_value . '" autocomplete="username">
                </div>';
        }

        /* Build profile fields HTML based on enabled fields */
        $profile_fields_html = '';
        $enabled_fields      = NBUF_Registration::get_enabled_fields();

        foreach ( $enabled_fields as $field_key => $field_data ) {
            $field_value = isset( $_POST[ $field_key ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $required    = $field_data['required'] ? ' <span class="required">*</span>' : '';
            $req_attr    = $field_data['required'] ? ' required' : '';

            /* Determine input type based on field characteristics */
            $input_type = 'text';
            if ( in_array( $field_key, array( 'phone', 'mobile_phone', 'work_phone', 'fax' ), true ) ) {
                $input_type = 'tel';
            } elseif ( in_array( $field_key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
                $input_type = 'url';
            } elseif ( in_array( $field_key, array( 'work_email', 'supervisor_email' ), true ) ) {
                $input_type = 'email';
            } elseif ( in_array( $field_key, array( 'date_of_birth', 'hire_date', 'termination_date' ), true ) ) {
                $input_type = 'date';
            }

            /* Text areas for long-form content */
            if ( in_array( $field_key, array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' ), true ) ) {
                $profile_fields_html .= sprintf(
                    '<div class="nbuf-form-group">
                        <label for="nbuf_reg_%1$s">%2$s%3$s</label>
                        <textarea id="nbuf_reg_%1$s" name="%1$s"%4$s autocomplete="%1$s">%5$s</textarea>
                    </div>',
                    esc_attr( $field_key ),
                    esc_html( $field_data['label'] ),
                    $required,
                    $req_attr,
                    esc_textarea( $field_value )
                );
            } else {
                $profile_fields_html .= sprintf(
                    '<div class="nbuf-form-group">
                        <label for="nbuf_reg_%1$s">%2$s%3$s</label>
                        <input type="%4$s" id="nbuf_reg_%1$s" name="%1$s"%5$s value="%6$s" autocomplete="%1$s">
                    </div>',
                    esc_attr( $field_key ),
                    esc_html( $field_data['label'] ),
                    $required,
                    esc_attr( $input_type ),
                    $req_attr,
                    $field_value
                );
            }
        }

        /* Replace placeholders */
        $email_value = isset( $_POST['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $replacements = array(
            '{action_url}'         => esc_url( get_permalink() ),
            '{nonce_field}'        => wp_nonce_field( 'nbuf_registration', 'nbuf_registration_nonce', true, false ),
            '{username_field}'     => $username_field_html,
            '{profile_fields}'     => $profile_fields_html,
            '{email_value}'        => $email_value,
            '{login_url}'          => esc_url( $login_url ),
            '{success_message}'    => $success_message,
            '{error_message}'      => $error_message,
            '{logged_in_message}'  => '',
        );

        foreach ( $replacements as $placeholder => $value ) {
            $template = str_replace( $placeholder, $value, $template );
        }

        return $template;
    }

    /* ==========================================================
       HANDLE REGISTRATION FORM SUBMISSION
       ----------------------------------------------------------
       Process registration form and create new user.
       ========================================================== */
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
            wp_safe_redirect( get_permalink() );
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
            'first_name', 'last_name', 'phone', 'company', 'job_title',
            'address', 'address_line1', 'address_line2', 'city', 'state',
            'postal_code', 'country', 'bio', 'website'
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
            $redirect_url  = add_query_arg( 'error', rawurlencode( $error_message ), get_permalink() );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        /* Registration successful - redirect with success message */
        $redirect_url = add_query_arg( 'registration', 'success', get_permalink() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /* ==========================================================
       [nbuf_account_page]
       ----------------------------------------------------------
       Renders user account management page with profile editing
       and password change functionality.
       ========================================================== */
    public static function sc_account_page( $atts ) {
        /* Require user to be logged in */
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );
            return '<div class="nbuf-message nbuf-message-info">' .
                   sprintf(
                       esc_html__( 'Please %slog in%s to view your account.', 'nobloat-user-foundry' ),
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
            wp_localize_script( 'password-strength-meter', 'pwsL10n', array(
                'empty'    => __( 'Strength indicator', 'nobloat-user-foundry' ),
                'short'    => __( 'Very weak', 'nobloat-user-foundry' ),
                'bad'      => __( 'Weak', 'nobloat-user-foundry' ),
                'good'     => _x( 'Medium', 'password strength', 'nobloat-user-foundry' ),
                'strong'   => __( 'Strong', 'nobloat-user-foundry' ),
                'mismatch' => __( 'Mismatch', 'nobloat-user-foundry' ),
            ) );
        }

        /* Enqueue profile photos script if profiles enabled */
        if ( NBUF_Options::get( 'nbuf_enable_profiles', false ) ) {
            wp_enqueue_script( 'nbuf-profile-photos', NBUF_PLUGIN_URL . 'assets/js/frontend/profile-photos.js', array( 'jquery' ), NBUF_VERSION, true );
            wp_localize_script( 'nbuf-profile-photos', 'NBUF_ProfilePhotos', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonces'   => array(
                    'upload_profile' => wp_create_nonce( 'nbuf_upload_profile_photo' ),
                    'upload_cover'   => wp_create_nonce( 'nbuf_upload_cover_photo' ),
                    'delete_profile' => wp_create_nonce( 'nbuf_delete_profile_photo' ),
                    'delete_cover'   => wp_create_nonce( 'nbuf_delete_cover_photo' ),
                ),
                'i18n'     => array(
                    'profile_uploaded'        => __( 'Profile photo uploaded successfully!', 'nobloat-user-foundry' ),
                    'cover_uploaded'          => __( 'Cover photo uploaded successfully!', 'nobloat-user-foundry' ),
                    'upload_failed'           => __( 'Upload failed.', 'nobloat-user-foundry' ),
                    'upload_error'            => __( 'An error occurred during upload.', 'nobloat-user-foundry' ),
                    'profile_deleted'         => __( 'Profile photo deleted.', 'nobloat-user-foundry' ),
                    'cover_deleted'           => __( 'Cover photo deleted.', 'nobloat-user-foundry' ),
                    'delete_failed'           => __( 'Delete failed.', 'nobloat-user-foundry' ),
                    'confirm_delete_profile'  => __( 'Are you sure you want to delete your profile photo?', 'nobloat-user-foundry' ),
                    'confirm_delete_cover'    => __( 'Are you sure you want to delete your cover photo?', 'nobloat-user-foundry' ),
                ),
            ) );
        }

        /* Get template using Template Manager (custom table + caching) */
        $template = NBUF_Template_Manager::load_template( 'account-page' );

        /* Get messages from URL parameters */
        $messages = '';
        if ( isset( $_GET['account_updated'] ) && 'success' === $_GET['account_updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $messages .= '<div class="nbuf-message nbuf-message-success">' . esc_html__( 'Profile updated successfully!', 'nobloat-user-foundry' ) . '</div>';
        }
        if ( isset( $_GET['password_changed'] ) && 'success' === $_GET['password_changed'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $messages .= '<div class="nbuf-message nbuf-message-success">' . esc_html__( 'Password changed successfully!', 'nobloat-user-foundry' ) . '</div>';
        }
        if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $messages .= '<div class="nbuf-message nbuf-message-error">' . esc_html( urldecode( $error ) ) . '</div>';
        }

        /* Get user verification and expiration status */
        $is_verified = NBUF_User_Data::is_verified( $user_id );
        $is_expired  = NBUF_User_Data::is_expired( $user_id );
        $expires_at  = NBUF_User_Data::get_expires_at( $user_id );

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
            $expiration_info = '<div class="nbuf-info-label">' . esc_html__( 'Account Expires:', 'nobloat-user-foundry' ) . '</div>';
            $expiration_info .= '<div class="nbuf-info-value">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $expires_at ) ) ) . '</div>';
        }

        /* Get profile data */
        $profile_data = NBUF_Profile_Data::get( $user_id );

        /* Build profile fields HTML */
        $profile_fields_html = self::build_profile_fields_html( $current_user, $profile_data );

        /* Build privacy section HTML */
        ob_start();
        do_action( 'nbuf_profile_privacy_section', $user_id );
        $privacy_section_html = ob_get_clean();

        /* Build profile photos section HTML */
        ob_start();
        do_action( 'nbuf_account_profile_photos_section', $user_id );
        $photos_section_html = ob_get_clean();

        /* Build version history section HTML */
        ob_start();
        do_action( 'nbuf_account_version_history_section', $user_id );
        $version_history_section_html = ob_get_clean();

        /* Generate nonce fields */
        ob_start();
        wp_nonce_field( 'nbuf_account_profile', 'nbuf_account_nonce', false );
        $nonce_field = ob_get_clean();

        ob_start();
        wp_nonce_field( 'nbuf_account_password', 'nbuf_password_nonce', false );
        $nonce_field_password = ob_get_clean();

        /* Replace placeholders */
        $replacements = array(
            '{messages}'            => $messages,
            '{status_badges}'       => $status_badges,
            '{username}'            => esc_html( $current_user->user_login ),
            '{email}'               => esc_html( $current_user->user_email ),
            '{display_name}'        => esc_html( $current_user->display_name ? $current_user->display_name : $current_user->user_login ),
            '{registered_date}'     => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $current_user->user_registered ) ) ),
            '{expiration_info}'     => $expiration_info,
            '{action_url}'          => esc_url( get_permalink() ),
            '{nonce_field}'         => $nonce_field,
            '{nonce_field_password}' => $nonce_field_password,
            '{profile_fields}'      => $profile_fields_html,
            '{privacy_section}'     => $privacy_section_html,
            '{photos_section}'      => $photos_section_html,
            '{version_history_section}' => $version_history_section_html,
            '{logout_url}'          => esc_url( wp_logout_url( home_url() ) ),
        );

        foreach ( $replacements as $placeholder => $value ) {
            $template = str_replace( $placeholder, $value, $template );
        }

        return $template;
    }

    /* ==========================================================
       BUILD PROFILE FIELDS HTML
       ----------------------------------------------------------
       Generates HTML for profile edit fields based on settings.
       ========================================================== */
    private static function build_profile_fields_html( $user, $profile_data ) {
        $html = '';

        /* Get enabled profile fields */
        $enabled_fields = NBUF_Profile_Data::get_enabled_fields();
        $field_registry = NBUF_Profile_Data::get_field_registry();

        /* Build flat field labels array */
        $field_labels = array();
        foreach ( $field_registry as $category_data ) {
            $field_labels = array_merge( $field_labels, $category_data['fields'] );
        }

        /* Add first name and last name (always available) */
        $first_name = get_user_meta( $user->ID, 'first_name', true );
        $last_name  = get_user_meta( $user->ID, 'last_name', true );

        $html .= '<div class="nbuf-form-group">';
        $html .= '<label for="first_name">' . esc_html__( 'First Name', 'nobloat-user-foundry' ) . '</label>';
        $html .= '<input type="text" id="first_name" name="first_name" value="' . esc_attr( $first_name ) . '">';
        $html .= '</div>';

        $html .= '<div class="nbuf-form-group">';
        $html .= '<label for="last_name">' . esc_html__( 'Last Name', 'nobloat-user-foundry' ) . '</label>';
        $html .= '<input type="text" id="last_name" name="last_name" value="' . esc_attr( $last_name ) . '">';
        $html .= '</div>';

        /* Add profile fields based on enabled settings */
        foreach ( $enabled_fields as $field_key ) {
            $label = isset( $field_labels[ $field_key ] ) ? $field_labels[ $field_key ] : ucwords( str_replace( '_', ' ', $field_key ) );
            $value = isset( $profile_data->$field_key ) ? $profile_data->$field_key : '';

            /* Determine input type based on field characteristics */
            $input_type = 'text';
            if ( in_array( $field_key, array( 'phone', 'mobile_phone', 'work_phone', 'fax' ), true ) ) {
                $input_type = 'tel';
            } elseif ( in_array( $field_key, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
                $input_type = 'url';
            } elseif ( in_array( $field_key, array( 'work_email', 'supervisor_email' ), true ) ) {
                $input_type = 'email';
            } elseif ( in_array( $field_key, array( 'date_of_birth', 'hire_date', 'termination_date' ), true ) ) {
                $input_type = 'date';
            }

            $html .= '<div class="nbuf-form-group">';
            $html .= '<label for="' . esc_attr( $field_key ) . '">' . esc_html( $label ) . '</label>';

            if ( in_array( $field_key, array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' ), true ) ) {
                $html .= '<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '">' . esc_textarea( $value ) . '</textarea>';
            } else {
                $html .= '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '">';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /* ==========================================================
       HANDLE ACCOUNT PAGE ACTIONS
       ----------------------------------------------------------
       Process profile updates and password changes.
       ========================================================== */
    public static function maybe_handle_account_actions() {
        if ( is_admin() || ! isset( $_POST['nbuf_account_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['nbuf_account_action'] ) );

        if ( 'update_profile' === $action ) {
            self::handle_profile_update();
        } elseif ( 'change_password' === $action ) {
            self::handle_password_change();
        }
    }

    /* ==========================================================
       HANDLE PROFILE UPDATE
       ----------------------------------------------------------
       Process profile form submission.
       ========================================================== */
    private static function handle_profile_update() {
        /* Verify nonce */
        if ( ! isset( $_POST['nbuf_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_account_nonce'] ) ), 'nbuf_account_profile' ) ) {
            wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
        }

        /* Require logged in user */
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $user_id = get_current_user_id();

        /* Update first name and last name */
        if ( isset( $_POST['first_name'] ) ) {
            update_user_meta( $user_id, 'first_name', sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) );
        }
        if ( isset( $_POST['last_name'] ) ) {
            update_user_meta( $user_id, 'last_name', sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) );
        }

        /* Collect profile fields */
        $profile_fields = array(
            'phone', 'company', 'job_title', 'address',
            'address_line1', 'address_line2', 'city', 'state',
            'postal_code', 'country', 'bio', 'website'
        );

        $profile_data = array();

        foreach ( $profile_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                if ( 'bio' === $field ) {
                    $profile_data[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
                } elseif ( 'website' === $field ) {
                    $profile_data[ $field ] = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
                } else {
                    $profile_data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                }
            }
        }

        /* Update profile data */
        if ( ! empty( $profile_data ) ) {
            NBUF_Profile_Data::update( $user_id, $profile_data );
        }

        /* Fire action for extensions (privacy settings, etc.) */
        do_action( 'nbuf_after_profile_update', $user_id, $_POST );

        /* Redirect with success message */
        wp_safe_redirect( add_query_arg( 'account_updated', 'success', get_permalink() ) );
        exit;
    }

    /* ==========================================================
       HANDLE PASSWORD CHANGE
       ----------------------------------------------------------
       Process password change form submission.
       ========================================================== */
    private static function handle_password_change() {
        /* Verify nonce */
        if ( ! isset( $_POST['nbuf_password_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_password_nonce'] ) ), 'nbuf_account_password' ) ) {
            wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
        }

        /* Require logged in user */
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
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
            $error = rawurlencode( __( 'Current password is incorrect.', 'nobloat-user-foundry' ) );
            wp_safe_redirect( add_query_arg( 'error', $error, get_permalink() ) );
            exit;
        }

        /* Validate new password */
        if ( strlen( $new_password ) < 8 ) {
            $error = rawurlencode( __( 'New password must be at least 8 characters.', 'nobloat-user-foundry' ) );
            wp_safe_redirect( add_query_arg( 'error', $error, get_permalink() ) );
            exit;
        }

        if ( $new_password !== $confirm_password ) {
            $error = rawurlencode( __( 'New passwords do not match.', 'nobloat-user-foundry' ) );
            wp_safe_redirect( add_query_arg( 'error', $error, get_permalink() ) );
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

        /* Redirect with success message */
        wp_safe_redirect( add_query_arg( 'password_changed', 'success', get_permalink() ) );
        exit;
    }

    /* ==========================================================
       [nbuf_logout]
       ----------------------------------------------------------
       Handles user logout with configurable behavior
       (immediate or confirmation) and redirect options.
       ========================================================== */
    public static function sc_logout( $atts = array() ) {
        /* Get settings */
        $logout_behavior = NBUF_Options::get( 'nbuf_logout_behavior', 'immediate' );
        $logout_redirect = NBUF_Options::get( 'nbuf_logout_redirect', 'home' );
        $custom_url      = NBUF_Options::get( 'nbuf_logout_redirect_custom', '' );

        /* Determine redirect URL */
        $redirect_url = home_url( '/' );
        if ( 'login' === $logout_redirect ) {
            $login_page_id = NBUF_Options::get( 'nbuf_page_login' );
            if ( $login_page_id ) {
                $redirect_url = get_permalink( $login_page_id );
            }
        } elseif ( 'custom' === $logout_redirect && ! empty( $custom_url ) ) {
            /* Support both FQDNs and relative paths */
            if ( filter_var( $custom_url, FILTER_VALIDATE_URL ) ) {
                $redirect_url = esc_url_raw( $custom_url );
            } else {
                /* Relative path - make sure it starts with / */
                $custom_url = ltrim( $custom_url, '/' );
                $redirect_url = home_url( '/' . $custom_url );
            }
        }

        /* Check if user is already logged out */
        if ( ! is_user_logged_in() ) {
            $login_page_id = NBUF_Options::get( 'nbuf_page_login' );
            $login_url = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();

            return '<div class="nbuf-logout-wrapper">
                <div class="nbuf-message nbuf-message-info">
                    <p>' . esc_html__( 'You are not currently logged in.', 'nobloat-user-foundry' ) . '</p>
                    <p><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Login', 'nobloat-user-foundry' ) . '</a></p>
                </div>
            </div>';
        }

        /* Handle logout action */
        if ( isset( $_GET['action'] ) && 'logout' === $_GET['action'] && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'nbuf-logout' ) ) {
                wp_logout();
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }

        /* Generate logout URL with nonce */
        $logout_url = add_query_arg(
            array(
                'action'   => 'logout',
                '_wpnonce' => wp_create_nonce( 'nbuf-logout' ),
            ),
            get_permalink()
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

    /* ==========================================================
       [nbuf_2fa_verify]
       ----------------------------------------------------------
       Two-Factor Authentication verification page.
       Displays form for entering 2FA code during login.
       ========================================================== */
    public static function sc_2fa_verify($atts = []) {
        /* Check if 2FA_Login class exists */
        if (!class_exists('NBUF_2FA_Login')) {
            return '<p>' . esc_html__('2FA system is not available.', 'nobloat-user-foundry') . '</p>';
        }

        /* Let the login class handle the verification form */
        return NBUF_2FA_Login::get_verification_form();
    }

    /* ==========================================================
       [nbuf_2fa_setup]
       ----------------------------------------------------------
       Two-Factor Authentication setup page.
       Allows users to configure TOTP, email 2FA, and backup codes.
       ========================================================== */
    public static function sc_2fa_setup($atts = []) {
        /* Check if user is logged in */
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to set up two-factor authentication.', 'nobloat-user-foundry') . '</p>';
        }

        /* Check if 2FA classes exist */
        if (!class_exists('NBUF_2FA') || !class_exists('NBUF_TOTP') || !class_exists('NBUF_QR_Code')) {
            return '<p>' . esc_html__('2FA system is not available.', 'nobloat-user-foundry') . '</p>';
        }

        $user_id = get_current_user_id();
        $current_method = NBUF_2FA::get_user_method($user_id);

        /* Get 2FA method availability */
        $email_method = NBUF_Options::get('nbuf_2fa_email_method', 'disabled');
        $totp_method = NBUF_Options::get('nbuf_2fa_totp_method', 'disabled');

        $email_available = in_array($email_method, ['optional_all', 'required_all', 'required_admin'], true);
        $totp_available = in_array($totp_method, ['optional', 'required_all', 'required_admin'], true);

        /* Check if current user can access 2FA */
        $is_admin = current_user_can('manage_options');
        if (!$email_available && !$totp_available) {
            return '<p>' . esc_html__('Two-factor authentication is not enabled on this site.', 'nobloat-user-foundry') . '</p>';
        }

        ob_start();
        ?>
        <div class="nbuf-2fa-setup-page">
            <h2><?php esc_html_e('Two-Factor Authentication Setup', 'nobloat-user-foundry'); ?></h2>

            <?php if ($current_method): ?>
                <div class="nbuf-success">
                    <?php
                    /* translators: %s: current 2FA method */
                    printf(esc_html__('2FA is currently enabled using: %s', 'nobloat-user-foundry'), esc_html($current_method));
                    ?>
                </div>
            <?php endif; ?>

            <div class="nbuf-2fa-options">
                <?php if ($totp_available): ?>
                    <div class="nbuf-2fa-option-card">
                        <h3><?php esc_html_e('Authenticator App (TOTP)', 'nobloat-user-foundry'); ?></h3>
                        <p><?php esc_html_e('Use an authenticator app like Google Authenticator or Authy to generate verification codes.', 'nobloat-user-foundry'); ?></p>

                        <?php if ($current_method === 'totp' || $current_method === 'both'): ?>
                            <p class="nbuf-status-active"><?php esc_html_e('✓ Active', 'nobloat-user-foundry'); ?></p>
                            <form method="post">
                                <?php wp_nonce_field('nbuf_2fa_disable_totp', 'nbuf_2fa_nonce'); ?>
                                <input type="hidden" name="nbuf_2fa_action" value="disable_totp">
                                <button type="submit" class="nbuf-button nbuf-button-secondary">
                                    <?php esc_html_e('Disable TOTP', 'nobloat-user-foundry'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <?php
                            /* Generate new secret for setup */
                            $secret = NBUF_TOTP::generate_secret();
                            $username = wp_get_current_user()->user_email;
                            $issuer = get_bloginfo('name');
                            $uri = NBUF_TOTP::get_provisioning_uri($secret, $username, $issuer);
                            $qr_code = NBUF_QR_Code::generate($uri, NBUF_Options::get('nbuf_2fa_totp_qr_size', 200));
                            ?>

                            <!-- Show TOTP setup template -->
                            <?php echo do_shortcode('[nbuf_template name="2fa_setup_totp" secret="' . esc_attr($secret) . '" qr_code="' . esc_attr($qr_code) . '"]'); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($email_available): ?>
                    <div class="nbuf-2fa-option-card">
                        <h3><?php esc_html_e('Email-Based 2FA', 'nobloat-user-foundry'); ?></h3>
                        <p><?php esc_html_e('Receive verification codes via email each time you log in.', 'nobloat-user-foundry'); ?></p>

                        <?php if ($current_method === 'email' || $current_method === 'both'): ?>
                            <p class="nbuf-status-active"><?php esc_html_e('✓ Active', 'nobloat-user-foundry'); ?></p>
                            <form method="post">
                                <?php wp_nonce_field('nbuf_2fa_disable_email', 'nbuf_2fa_nonce'); ?>
                                <input type="hidden" name="nbuf_2fa_action" value="disable_email">
                                <button type="submit" class="nbuf-button nbuf-button-secondary">
                                    <?php esc_html_e('Disable Email 2FA', 'nobloat-user-foundry'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <?php wp_nonce_field('nbuf_2fa_enable_email', 'nbuf_2fa_nonce'); ?>
                                <input type="hidden" name="nbuf_2fa_action" value="enable_email">
                                <button type="submit" class="nbuf-button nbuf-button-primary">
                                    <?php esc_html_e('Enable Email 2FA', 'nobloat-user-foundry'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (NBUF_Options::get('nbuf_2fa_backup_enabled', true) && $current_method): ?>
                    <div class="nbuf-2fa-option-card">
                        <h3><?php esc_html_e('Backup Codes', 'nobloat-user-foundry'); ?></h3>
                        <p><?php esc_html_e('Generate one-time use backup codes for emergency access.', 'nobloat-user-foundry'); ?></p>

                        <form method="post">
                            <?php wp_nonce_field('nbuf_2fa_generate_backup', 'nbuf_2fa_nonce'); ?>
                            <input type="hidden" name="nbuf_2fa_action" value="generate_backup_codes">
                            <button type="submit" class="nbuf-button nbuf-button-primary">
                                <?php esc_html_e('Generate Backup Codes', 'nobloat-user-foundry'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .nbuf-2fa-setup-page {
            max-width: 900px;
            margin: 40px auto;
        }
        .nbuf-2fa-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .nbuf-2fa-option-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .nbuf-2fa-option-card h3 {
            margin-top: 0;
            color: #333;
        }
        .nbuf-status-active {
            color: #2e7d32;
            font-weight: 600;
            margin: 15px 0;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /* ==========================================================
       [nbuf_restrict]
       ----------------------------------------------------------
       Restrict content sections based on login status, roles,
       verification status, and expiration status.

       Attributes:
       - role: Comma-separated list of roles (e.g., "administrator,editor")
       - logged_in: "yes" or "no"
       - verified: "yes" or "no" (email verification)
       - expired: "yes" or "no" (account expiration)
       - message: Custom message for unauthorized users

       Examples:
       [nbuf_restrict logged_in="yes"]Content for logged-in users[/nbuf_restrict]
       [nbuf_restrict role="subscriber,customer"]VIP Content[/nbuf_restrict]
       [nbuf_restrict verified="yes" message="Please verify your email"]...[/nbuf_restrict]
       ========================================================== */
    public static function sc_restrict($atts, $content = '') {
        /* Parse attributes */
        $atts = shortcode_atts([
            'role'       => '',
            'logged_in'  => '',
            'verified'   => '',
            'expired'    => '',
            'message'    => __('This content is restricted.', 'nobloat-user-foundry'),
        ], $atts, 'nbuf_restrict');

        /* Get current user */
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $is_logged_in = is_user_logged_in();

        /* Check logged_in requirement */
        if (!empty($atts['logged_in'])) {
            $required_logged_in = filter_var($atts['logged_in'], FILTER_VALIDATE_BOOLEAN);
            if ($required_logged_in && !$is_logged_in) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
            if (!$required_logged_in && $is_logged_in) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
        }

        /* Check role requirement (only if user is logged in) */
        if ($is_logged_in && !empty($atts['role'])) {
            $required_roles = array_map('trim', explode(',', $atts['role']));
            $user_roles = $user->roles;

            /* Check if user has any of the required roles */
            $has_role = !empty(array_intersect($user_roles, $required_roles));
            if (!$has_role) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
        }

        /* Check verified requirement (only if user is logged in) */
        if ($is_logged_in && !empty($atts['verified'])) {
            $required_verified = filter_var($atts['verified'], FILTER_VALIDATE_BOOLEAN);
            $user_data = NBUF_User_Data::get($user_id);
            $is_verified = !empty($user_data['verification_status']) && $user_data['verification_status'] === 'verified';

            if ($required_verified && !$is_verified) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
            if (!$required_verified && $is_verified) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
        }

        /* Check expired requirement (only if user is logged in) */
        if ($is_logged_in && !empty($atts['expired'])) {
            $required_not_expired = !filter_var($atts['expired'], FILTER_VALIDATE_BOOLEAN);
            $user_data = NBUF_User_Data::get($user_id);
            $is_expired = !empty($user_data['expiration_date']) && strtotime($user_data['expiration_date']) < time();

            if ($required_not_expired && $is_expired) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
            if (!$required_not_expired && !$is_expired) {
                return '<div class="nbuf-restricted-content">' . wpautop(wp_kses_post($atts['message'])) . '</div>';
            }
        }

        /* User has access - return content */
        return do_shortcode($content);
    }

    /* ==========================================================
       [nbuf_profile]
       ----------------------------------------------------------
       Displays a user profile page (alternative to custom URLs).
       Attributes:
         - user: Username or user ID (required)
       ========================================================== */
    public static function sc_profile($atts) {
        $atts = shortcode_atts([
            'user' => '',
        ], $atts, 'nbuf_profile');

        /* Require user parameter */
        if (empty($atts['user'])) {
            return '<div class="nbuf-error">' . esc_html__('Error: user parameter is required.', 'nobloat-user-foundry') . '</div>';
        }

        /* Get user by ID or username */
        if (is_numeric($atts['user'])) {
            $user = get_userdata((int) $atts['user']);
        } else {
            $user = get_user_by('login', $atts['user']);
            if (!$user) {
                $user = get_user_by('slug', $atts['user']);
            }
        }

        /* User not found */
        if (!$user) {
            return '<div class="nbuf-error">' . esc_html__('User not found.', 'nobloat-user-foundry') . '</div>';
        }

        /* Check privacy settings */
        if (!NBUF_Public_Profiles::can_view_profile($user->ID)) {
            return '<div class="nbuf-restricted-content">' . esc_html__('This profile is private.', 'nobloat-user-foundry') . '</div>';
        }

        /* Get user data */
        $user_data = NBUF_User_Data::get($user->ID);

        /* Get profile photo */
        $profile_photo = NBUF_Profile_Photos::get_profile_photo($user->ID, 150);

        /* Get cover photo */
        $cover_photo = !empty($user_data['cover_photo_url']) ? $user_data['cover_photo_url'] : '';
        $allow_cover = NBUF_Options::get('nbuf_profile_allow_cover_photos', true);

        /* Get bio and other info */
        $bio = get_user_meta($user->ID, 'description', true);
        $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;

        /* Get user registration date */
        $registered = $user->user_registered;
        $registered_date = mysql2date(get_option('date_format'), $registered);

        /* Start output buffer */
        ob_start();
        ?>
        <div class="nbuf-profile-page">
            <!-- Profile Header with Cover Photo -->
            <div class="nbuf-profile-header">
                <?php if ($allow_cover && !empty($cover_photo)) : ?>
                    <div class="nbuf-profile-cover" style="background-image: url('<?php echo esc_url($cover_photo); ?>');">
                        <div class="nbuf-profile-cover-overlay"></div>
                    </div>
                <?php else : ?>
                    <div class="nbuf-profile-cover nbuf-profile-cover-default">
                        <div class="nbuf-profile-cover-overlay"></div>
                    </div>
                <?php endif; ?>

                <div class="nbuf-profile-avatar-wrap">
                    <img src="<?php echo esc_url($profile_photo); ?>" alt="<?php echo esc_attr($display_name); ?>" class="nbuf-profile-avatar" width="150" height="150">
                </div>
            </div>

            <!-- Profile Info -->
            <div class="nbuf-profile-content">
                <div class="nbuf-profile-info">
                    <h1 class="nbuf-profile-name"><?php echo esc_html($display_name); ?></h1>
                    <p class="nbuf-profile-username">@<?php echo esc_html($user->user_login); ?></p>

                    <?php if (!empty($bio)) : ?>
                        <div class="nbuf-profile-bio">
                            <?php echo wpautop(wp_kses_post($bio)); ?>
                        </div>
                    <?php endif; ?>

                    <div class="nbuf-profile-meta">
                        <span class="nbuf-profile-meta-item">
                            <svg class="nbuf-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 8a3 3 0 100-6 3 3 0 000 6zm0 1.5c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
                            </svg>
                            <?php
                            /* translators: %s: User registration date */
                            printf(esc_html__('Joined %s', 'nobloat-user-foundry'), esc_html($registered_date));
                            ?>
                        </span>
                    </div>
                </div>

                <?php
                /**
                 * Hook for adding custom content to profile page
                 *
                 * @param WP_User $user User object.
                 * @param array   $user_data User data from custom table.
                 */
                do_action('nbuf_public_profile_content', $user, $user_data);
                ?>

                <?php if (is_user_logged_in() && get_current_user_id() === $user->ID) : ?>
                    <div class="nbuf-profile-actions">
                        <?php
                        $account_page_id = NBUF_Options::get('nbuf_page_account');
                        if ($account_page_id) :
                            ?>
                            <a href="<?php echo esc_url(get_permalink($account_page_id)); ?>" class="nbuf-button nbuf-button-primary">
                                <?php esc_html_e('Edit Profile', 'nobloat-user-foundry'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/* Note: Init is called from main plugin file via plugins_loaded hook */