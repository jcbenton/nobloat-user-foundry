<?php
/**
 * NoBloat User Foundry - Hooks Handler
 *
 * Handles registration, profile update, password reset link
 * rewriting, and login blocking for unverified users.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Hooks
 *
 * Manages WordPress hooks and filters for the plugin.
 */
class NBUF_Hooks {


	/**
	 * Initialize hooks handler.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_hooks' ) );
		add_filter( 'authenticate', array( __CLASS__, 'enforce_verification_before_login' ), 10, 3 );
		add_filter( 'retrieve_password_message', array( __CLASS__, 'rewrite_password_reset_link' ), 10, 4 );
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'customize_welcome_email' ), 10, 3 );
		add_action( 'user_register', array( __CLASS__, 'maybe_notify_admin_registration' ), 20, 1 );
		add_action( 'login_init', array( __CLASS__, 'handle_default_wordpress_redirects' ) );

		/* Password strength validation hooks */
		add_action( 'user_profile_update_errors', array( __CLASS__, 'validate_profile_password' ), 10, 3 );
		add_filter( 'registration_errors', array( __CLASS__, 'validate_registration_password' ), 10, 3 );
		add_action( 'validate_password_reset', array( __CLASS__, 'validate_reset_password' ), 10, 2 );
	}

	/**
	 * Check if security restrictions should be enforced for user
	 *
	 * Centralizes admin bypass logic to avoid duplicate checks.
	 * Admins with manage_options capability bypass all restrictions.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if restrictions should be enforced, false if user bypasses.
	 */
	private static function should_enforce_restrictions( $user_id ) {
		/* Never enforce restrictions on admins */
		if ( user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register hooks dynamically based on settings.
	 *
	 * Dynamically attach verification triggers based on settings.
	 */
	public static function register_hooks() {
		$settings = NBUF_Options::get( 'nbuf_settings', array() );
		$hooks    = isset( $settings['hooks'] ) ? (array) $settings['hooks'] : array( 'user_register' );

		foreach ( $hooks as $hook_name ) {
			add_action( $hook_name, array( __CLASS__, 'trigger_verification_email' ), 10, 1 );
		}

		// Optional re-verification on email change.
		if ( ! empty( $settings['reverify_on_email_change'] ) ) {
			add_action( 'profile_update', array( __CLASS__, 'handle_email_change' ), 10, 2 );
		}
	}

	/**
	 * TRIGGER VERIFICATION EMAIL.
	 *
	 * Called when a new user registers or other configured hook fires.
	 * Never sends emails for admin-created users.
	 *
	 * @param int $user_id User ID.
	 */
	public static function trigger_verification_email( $user_id ) {
		if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Skip if user created via admin panel (is_admin check) */
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Skip admins or already verified users */
		if ( user_can( $user, 'manage_options' ) || NBUF_User_Data::is_verified( $user_id ) ) {
			return;
		}

		$user_email = $user->user_email;

		/* Generate cryptographically secure token and expiration */
		$token   = bin2hex( random_bytes( 16 ) ); // 32 hex characters
		$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

		/* Store and send */
		NBUF_Database::insert_token( $user_id, $user_email, $token, $expires, 0 );
		NBUF_Email::send_verification_email( $user_email, $token );

		/* Log user registration */
		NBUF_Audit_Log::log(
			$user_id,
			'user_registered',
			'success',
			'New user registered',
			array( 'email' => $user_email )
		);

		/* Log verification email sent */
		NBUF_Audit_Log::log(
			$user_id,
			'email_verification_sent',
			'success',
			'Verification email sent to ' . $user_email,
			array( 'email' => $user_email )
		);
	}

	/**
	 * HANDLE EMAIL CHANGE.
	 *
	 * If user changes their email, reset verification and resend.
	 *
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Old user data object.
	 */
	public static function handle_email_change( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $old_user_data->user_email ) ) {
			return;
		}

		if ( strcasecmp( $user->user_email, $old_user_data->user_email ) !== 0 ) {
			NBUF_User_Data::set_unverified( $user_id );

			/*
			 * SECURITY: Generate cryptographically secure verification token.
			 * Use random_bytes() instead of wp_generate_password() for security tokens.
			 */
			$token   = bin2hex( random_bytes( 32 ) ); /* 64 hex characters, cryptographically secure */
			$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

			NBUF_Database::insert_token( $user_id, $user->user_email, $token, $expires, 0 );
			NBUF_Email::send_verification_email( $user->user_email, $token );

			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf -- Intentional placeholder for future debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Future: Add debug logging here.
			}
		}
	}

	/**
	 * REWRITE PASSWORD RESET LINK.
	 *
	 * Replaces wp-login.php reset URLs with our custom page.
	 *
	 * @param  string $message    Message content.
	 * @param  string $key        Reset key.
	 * @param  string $user_login User login.
	 * @param  object $user_data  User data object.
	 * @return string Modified message.
	 */
	public static function rewrite_password_reset_link( $message, $key, $user_login, $user_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$settings    = NBUF_Options::get( 'nbuf_settings', array() );
		$custom_path = $settings['password_reset_page'] ?? '/nbuf-reset';
		$custom_path = '/' . ltrim( $custom_path, '/' );

		$reset_url = home_url( $custom_path );
		$reset_url = add_query_arg(
			array(
				'key'    => rawurlencode( $key ),
				'login'  => rawurlencode( $user_login ),
				'action' => 'rp',
			),
			$reset_url
		);

		$encoded = strpos( $message, '<' ) !== false ? esc_url( $reset_url ) : $reset_url;

		return preg_replace(
			'#https?://[^\s<>"]+/wp-login\.php\?[^ \r\n<>"]+#i',
			$encoded,
			$message
		);
	}

	/**
	 * ENFORCE VERIFICATION BEFORE LOGIN.
	 *
	 * Blocks disabled users and unverified users from logging in.
	 *
	 * @param  WP_User|WP_Error $user     User object or error.
	 * @param  string           $username Username for login.
	 * @param  string           $password Password for login.
	 * @return WP_User|WP_Error User object or error.
	 */
	public static function enforce_verification_before_login( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return $user;
		}

		$user_id = $user->ID;

		/* Admins bypass all restrictions */
		if ( ! self::should_enforce_restrictions( $user_id ) ) {
			return $user;
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
				'email_not_verified',
				__( 'Your email address has not been verified yet. Please check your inbox for a verification link.', 'nobloat-user-foundry' )
			);
		}

		/* Check admin approval (if user requires it) */
		if ( NBUF_User_Data::requires_approval( $user_id ) && ! NBUF_User_Data::is_approved( $user_id ) ) {
			return new WP_Error(
				'awaiting_approval',
				__( 'Your account is pending administrator approval. You will receive an email once your account has been reviewed.', 'nobloat-user-foundry' )
			);
		}

		/* Check if user has weak password and grace period expired */
		$force_weak_change = NBUF_Options::get( 'nbuf_password_force_weak_change', false );
		if ( $force_weak_change && class_exists( 'NBUF_Password_Validator' ) ) {
			$weak_password_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user_id );

			if ( $weak_password_flagged ) {
				/* Check if grace period expired */
				$grace_period_days   = NBUF_Options::get( 'nbuf_password_grace_period', 7 );
				$flagged_timestamp   = strtotime( $weak_password_flagged );
				$grace_end_timestamp = $flagged_timestamp + ( $grace_period_days * DAY_IN_SECONDS );

				if ( time() > $grace_end_timestamp ) {
					/* Grace period expired - prevent login */
					return new WP_Error(
						'weak_password_expired',
						sprintf(
						/* translators: %d: number of days in grace period */
							__( 'Your password does not meet the current security requirements. The %d-day grace period has expired. Please reset your password to continue.', 'nobloat-user-foundry' ),
							$grace_period_days
						)
					);
				}
			}
		}

		return $user;
	}

	/**
	 * CUSTOMIZE WELCOME EMAIL.
	 *
	 * Intercepts WordPress new user notification email and
	 * replaces it with our custom template. Also replaces the
	 * password reset link with our custom page URL.
	 *
	 * @param  array   $wp_new_user_notification_email Email notification array.
	 * @param  WP_User $user                           User object.
	 * @param  string  $blogname                       Blog name.
	 * @return array Modified email notification array.
	 */
	public static function customize_welcome_email( $wp_new_user_notification_email, $user, $blogname ) {
		/* Get custom templates */
		$html_template = NBUF_Options::get( 'nbuf_welcome_email_html', '' );
		$text_template = NBUF_Options::get( 'nbuf_welcome_email_text', '' );

		/* If no custom templates, return default */
		if ( empty( $html_template ) && empty( $text_template ) ) {
			return $wp_new_user_notification_email;
		}

		/* Get user data */
		$user_login   = stripslashes( $user->user_login );
		$user_email   = $user->user_email;
		$display_name = $user->display_name ? $user->display_name : $user_login;

		/* Generate password reset link */
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			/* If key generation fails, use default email */
			return $wp_new_user_notification_email;
		}

		/* Get custom password reset page URL */
		$settings            = NBUF_Options::get( 'nbuf_settings', array() );
		$reset_path          = isset( $settings['password_reset_page'] ) ? $settings['password_reset_page'] : '/nbuf-reset';
		$password_reset_link = site_url( $reset_path . '?key=' . $key . '&login=' . rawurlencode( $user_login ) );

		/* Prepare placeholders */
		$placeholders = array(
			'{site_name}'           => $blogname,
			'{display_name}'        => $display_name,
			'{user_email}'          => $user_email,
			'{username}'            => $user_login,
			'{site_url}'            => site_url(),
			'{password_reset_link}' => $password_reset_link,
		);

		/* Use HTML template if available, otherwise text */
		$use_html = ! empty( $html_template );
		$template = $use_html ? $html_template : $text_template;

		/* Replace placeholders */
		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );

		/* Determine content type */
		$content_type = $use_html ? 'text/html' : 'text/plain';

		/*
		* Update the email array
		*/
		/* translators: %s: Blog name */
		$wp_new_user_notification_email['subject'] = sprintf( __( '[%s] Your account', 'nobloat-user-foundry' ), $blogname );
		$wp_new_user_notification_email['message'] = $message;
		$wp_new_user_notification_email['headers'] = array(
			'Content-Type: ' . $content_type . '; charset=UTF-8',
		);

		return $wp_new_user_notification_email;
	}

	/**
	 * MAYBE NOTIFY ADMIN OF NEW REGISTRATION.
	 *
	 * Sends email notification to site admin when new user
	 * registers, if the setting is enabled.
	 *
	 * @param int $user_id User ID.
	 */
	public static function maybe_notify_admin_registration( $user_id ) {
		/* Check if admin notification is enabled */
		$notify_enabled = NBUF_Options::get( 'nbuf_notify_admin_registration', false );
		if ( ! $notify_enabled ) {
			return;
		}

		/* Get user data */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Don't notify for admin-created users */
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Prepare email content using template */
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] New User Registration', 'nobloat-user-foundry' ), $site_name );

		/* Load template */
		$mode          = 'html'; // Default to HTML, can be made configurable later.
		$template_name = ( 'html' === $mode ) ? 'admin-new-user-html' : 'admin-new-user-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		/* Prepare replacements */
		$replacements = array(
			'{site_name}'         => wp_specialchars_decode( $site_name, ENT_QUOTES ),
			'{site_url}'          => home_url(),
			'{username}'          => sanitize_text_field( $user->user_login ),
			'{user_email}'        => sanitize_email( $user->user_email ),
			'{registration_date}' => current_time( 'mysql', true ),
			'{user_profile_link}' => esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ),
		);

		/* Replace placeholders */
		$message = strtr( $template, $replacements );

		/* Set content type */
		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		/* Send email */
		wp_mail( $admin_email, $subject, $message );

		/* Remove filter */
		remove_filter( 'wp_mail_content_type', $content_type_callback );
	}

	/**
	 * HANDLE DEFAULT WordPress REDIRECTS.
	 *
	 * Redirects default WordPress login/registration/logout
	 * pages to custom NoBloat pages when enabled.
	 */
	public static function handle_default_wordpress_redirects() {
		/* Only run on wp-login.php */
		if ( ! isset( $GLOBALS['pagenow'] ) || 'wp-login.php' !== $GLOBALS['pagenow'] ) {
			return;
		}

		/* Get redirect settings */
		$redirect_login        = NBUF_Options::get( 'nbuf_redirect_default_login', false );
		$redirect_register     = NBUF_Options::get( 'nbuf_redirect_default_register', false );
		$redirect_logout       = NBUF_Options::get( 'nbuf_redirect_default_logout', false );
		$redirect_lostpassword = NBUF_Options::get( 'nbuf_redirect_default_lostpassword', false );
		$redirect_resetpass    = NBUF_Options::get( 'nbuf_redirect_default_resetpass', false );

		/*
		 * Get current action
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only login redirect navigation
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		/* Handle lost password redirect (request reset form) */
		if ( 'lostpassword' === $action && $redirect_lostpassword ) {
			$page_id = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
			if ( $page_id ) {
				wp_safe_redirect( get_permalink( $page_id ) );
				exit;
			}
		}

		/* Handle password reset redirect (actual reset form with key) */
		if ( 'rp' === $action && $redirect_resetpass ) {
			$page_id = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
			if ( $page_id ) {
             // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress core password reset parameters
				$login = isset( $_REQUEST['login'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['login'] ) ) : '';
             // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress core password reset parameters
				$key = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';

				if ( $login && $key ) {
					wp_safe_redirect(
						add_query_arg(
							array(
								'login' => $login,
								'key'   => $key,
							),
							get_permalink( $page_id )
						)
					);
						exit;
				}
			}
		}

		/* Handle registration redirect */
		if ( 'register' === $action && $redirect_register ) {
			$page_id = NBUF_Options::get( 'nbuf_page_registration', 0 );
			if ( $page_id ) {
				wp_safe_redirect( get_permalink( $page_id ) );
				exit;
			}
		}

		/* Handle logout redirect */
		if ( 'logout' === $action && $redirect_logout ) {
			/*
			 * Let WordPress process the logout normally, then redirect
			 */
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking if nonce exists, WordPress core handles verification
			if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
				return; // Nonce required for logout.
			}

			$page_id = NBUF_Options::get( 'nbuf_page_login', 0 );
			if ( $page_id ) {
				add_filter(
					'logout_redirect',
					function () use ( $page_id ) {
						return get_permalink( $page_id );
					},
					10,
					3
				);
			}
			return; // Let WordPress handle the logout.
		}

		/* Handle login redirect (default action) */
		if ( 'login' === $action && $redirect_login ) {
			/*
			* Don't redirect if already processing a login attempt
			*/
         // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core login form.
			if ( isset( $_POST['log'] ) && isset( $_POST['pwd'] ) ) {
				return;
			}

			$page_id = NBUF_Options::get( 'nbuf_page_login', 0 );
			if ( $page_id ) {
				wp_safe_redirect( get_permalink( $page_id ) );
				exit;
			}
		}
	}

	/**
	 * Validate password on profile update
	 *
	 * Checks password strength when user changes password in their profile.
	 * Hooks into user_profile_update_errors action.
	 *
	 * @param WP_Error $errors WP_Error object for validation errors.
	 * @param bool     $update Whether this is a user update.
	 * @param WP_User  $user   User object being updated.
	 */
	public static function validate_profile_password( $errors, $update, $user ) {
		if ( ! NBUF_Password_Validator::should_enforce( 'profile_change' ) ) {
			return;
		}

		/*
		 * Verify nonce before accessing $_POST
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checking if nonce exists before verification
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce retrieved for verification on next line
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'update-user_' . $user->ID ) ) {
			return;
		}

		/*
		 * Check if password is being changed
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Protected by nonce verification on line 529
		if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) ) {
			return; // Not changing password.
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Protected by nonce on line 529, password validated not sanitized
		$password = wp_unslash( $_POST['pass1'] );
		$user_id  = $update ? $user->ID : 0;

		$validation = NBUF_Password_Validator::validate( $password, $user_id );

		if ( is_wp_error( $validation ) ) {
			$errors->add( 'weak_password', $validation->get_error_message() );
		} elseif ( $user_id ) {
			/* Clear weak password flag on successful password change */
			NBUF_Password_Validator::clear_weak_password_flag( $user_id );
		}
	}

	/**
	 * Validate password on registration
	 *
	 * Checks password strength during WordPress user registration.
	 * Hooks into registration_errors filter.
	 *
	 * @param  WP_Error $errors               WP_Error object.
	 * @param  string   $sanitized_user_login Sanitized username.
	 * @param  string   $user_email           User email address.
	 * @return WP_Error Modified errors object.
	 */
	public static function validate_registration_password( $errors, $sanitized_user_login, $user_email ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! NBUF_Password_Validator::should_enforce( 'registration' ) ) {
			return $errors;
		}

		/*
		* Check if password field exists
		*/
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core registration hook, nonce verified by core.
		if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) ) {
			return $errors;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress core registration hook, validating password strength.
		$password = wp_unslash( $_POST['pass1'] );

		$validation = NBUF_Password_Validator::validate( $password, 0 );

		if ( is_wp_error( $validation ) ) {
			$errors->add( 'weak_password', $validation->get_error_message() );
		}

		return $errors;
	}

	/**
	 * Validate password on password reset
	 *
	 * Checks password strength when user resets their password.
	 * Hooks into validate_password_reset action.
	 *
	 * @param WP_Error $errors WP_Error object.
	 * @param WP_User  $user   User object.
	 */
	public static function validate_reset_password( $errors, $user ) {
		if ( ! NBUF_Password_Validator::should_enforce( 'reset' ) ) {
			return;
		}

		/*
		* Check if password field exists
		*/
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core password reset hook, nonce verified by core.
		if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) ) {
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress core password reset hook, validating password strength.
		$password = wp_unslash( $_POST['pass1'] );
		$user_id  = $user->ID;

		$validation = NBUF_Password_Validator::validate( $password, $user_id );

		if ( is_wp_error( $validation ) ) {
			$errors->add( 'weak_password', $validation->get_error_message() );
		} else {
			/* Clear weak password flag on successful password change */
			NBUF_Password_Validator::clear_weak_password_flag( $user_id );
		}
	}
}

// Initialize.
NBUF_Hooks::init();
