<?php
/**
 * NoBloat User Foundry - Test Utility
 *
 * Sends a test verification email from the settings page.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test utility class.
 *
 * Handles test email functionality.
 */
class NBUF_Test {


	/**
	 * Initialize test utility.
	 */
	public static function init() {
		add_action( 'admin_post_nbuf_send_test_email', array( __CLASS__, 'handle_test_email' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'wp_ajax_nbuf_test_change_notification', array( __CLASS__, 'ajax_test_change_notification' ) );
		add_action( 'wp_ajax_nbuf_test_webhook', array( __CLASS__, 'ajax_test_webhook' ) );
	}

	/**
	 * Handle test email submission.
	 */
	public static function handle_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_send_test_email' );

		$sender     = isset( $_POST['nbuf_sender'] ) ? sanitize_email( wp_unslash( $_POST['nbuf_sender'] ) ) : '';
		$recipient  = isset( $_POST['nbuf_recipient'] ) ? sanitize_email( wp_unslash( $_POST['nbuf_recipient'] ) ) : '';
		$email_type = isset( $_POST['nbuf_email_type'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_email_type'] ) ) : '';

		if ( ! $sender || ! $recipient || ! $email_type ) {
			self::redirect_with( 'missing' );
			exit;
		}

		/* Store callbacks for proper removal */
		$from_callback = function () use ( $sender ) {
			return $sender;
		};
		$name_callback = function () {
			return get_bloginfo( 'name' ) . ' (Test)';
		};

		add_filter( 'wp_mail_from', $from_callback );
		add_filter( 'wp_mail_from_name', $name_callback );

		/* Send appropriate email type */
		$sent = false;
		switch ( $email_type ) {
			case 'email-verification':
				$sent = self::send_test_verification( $recipient );
				break;
			case 'welcome-email':
				$sent = self::send_test_welcome( $recipient );
				break;
			case 'account-approved':
				$sent = self::send_test_account_approved( $recipient );
				break;
			case 'account-rejected':
				$sent = self::send_test_account_rejected( $recipient );
				break;
			case 'expiration-warning':
				$sent = self::send_test_expiration( $recipient );
				break;
			case 'expiration-notice':
				$sent = self::send_test_expiration_notice( $recipient );
				break;
			case '2fa-email-code':
				$sent = self::send_test_2fa_code( $recipient );
				break;
			case 'password-reset':
				$sent = self::send_test_password_reset( $recipient );
				break;
			case 'magic-link':
				$sent = self::send_test_magic_link( $recipient );
				break;
			case 'admin-new-user':
				$sent = self::send_test_admin_notification( $recipient );
				break;
			case 'security-alert':
				$sent = self::send_test_security_alert( $recipient );
				break;
			case 'profile-change':
				$sent = self::send_test_profile_change( $recipient );
				break;
		}

		remove_filter( 'wp_mail_from', $from_callback );
		remove_filter( 'wp_mail_from_name', $name_callback );

		self::redirect_with( $sent ? 'success' : 'failed' );
	}

	/**
	 * Send test verification email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_verification( $recipient ) {
		/* Create test token */
		$token   = 'test-' . wp_generate_password( 20, false );
		$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) );
		NBUF_Database::insert_token( 0, $recipient, $token, $expires, 1 );

		return NBUF_Email::send_verification_email( $recipient, $token );
	}

	/**
	 * Send test welcome email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_welcome( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? 'welcome-email-html' : 'welcome-email-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$replacements = array(
			'{site_name}'           => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'            => home_url(),
			'{display_name}'        => 'Test User',
			'{username}'            => 'testuser',
			'{user_email}'          => $recipient,
			'{password_reset_link}' => home_url( '/reset-password' ),
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( 'Welcome to %s!', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test account approved email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_account_approved( $recipient ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Your account has been approved on %s', 'nobloat-user-foundry' ),
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: username */
			__( 'Hello %s,', 'nobloat-user-foundry' ),
			'testuser'
		) . "\n\n";
		$message .= sprintf(
			/* translators: %s: site name */
			__( 'Your account on %s has been approved by an administrator.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( 'You can now log in to your account:', 'nobloat-user-foundry' ) . "\n";
		$message .= wp_login_url() . "\n\n";
		$message .= __( 'Thank you!', 'nobloat-user-foundry' ) . "\n\n";
		$message .= __( '(This is a test email - no actual account was approved)', 'nobloat-user-foundry' );

		return wp_mail( $recipient, $subject, $message );
	}

	/**
	 * Send test account rejected email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_account_rejected( $recipient ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Account registration update from %s', 'nobloat-user-foundry' ),
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: Site name */
			__( 'Your account registration on %s has been reviewed.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( 'Unfortunately, your account was not approved at this time.', 'nobloat-user-foundry' ) . "\n\n";
		$message .= __( 'Reason:', 'nobloat-user-foundry' ) . "\n";
		$message .= __( 'Sample rejection reason for testing purposes.', 'nobloat-user-foundry' ) . "\n\n";
		$message .= sprintf(
			/* translators: %s: Site contact URL */
			__( 'If you have questions, please contact us: %s', 'nobloat-user-foundry' ),
			home_url( '/contact' )
		) . "\n\n";
		$message .= __( '(This is a test email - no actual account was rejected)', 'nobloat-user-foundry' );

		return wp_mail( $recipient, $subject, $message );
	}

	/**
	 * Send test expiration warning email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_expiration( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? 'expiration-warning-html' : 'expiration-warning-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$replacements = array(
			'{site_name}'             => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'              => home_url(),
			'{display_name}'          => 'Test User',
			'{username}'              => 'testuser',
			'{days_until_expiration}' => '7',
			'{expires_date}'          => gmdate( 'F j, Y', strtotime( '+7 days' ) ),
			'{expiration_date}'       => gmdate( 'F j, Y', strtotime( '+7 days' ) ),
			'{login_url}'             => wp_login_url(),
			'{contact_url}'           => home_url( '/contact' ),
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Your account is expiring soon', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test expiration notice email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_expiration_notice( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? 'expiration-notice-html' : 'expiration-notice-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$replacements = array(
			'{site_name}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'        => home_url(),
			'{display_name}'    => 'Test User',
			'{username}'        => 'testuser',
			'{expires_date}'    => gmdate( 'F j, Y' ),
			'{expiration_date}' => gmdate( 'F j, Y' ),
			'{contact_url}'     => home_url( '/contact' ),
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Your account has expired', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test 2FA email code.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_2fa_code( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? '2fa-email-code-html' : '2fa-email-code-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$test_code = wp_rand( 100000, 999999 );

		$replacements = array(
			'{site_name}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'           => home_url(),
			'{display_name}'       => 'Test User',
			'{verification_code}'  => $test_code,
			'{code}'               => $test_code,
			'{expiration_minutes}' => '10',
			'{user_email}'         => $recipient,
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( 'Your verification code for %s', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test password reset email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_password_reset( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? 'password-reset-html' : 'password-reset-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$replacements = array(
			'{site_name}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'     => home_url(),
			'{display_name}' => 'Test User',
			'{username}'     => 'testuser',
			'{reset_link}'   => home_url( '/reset-password?key=test123' ),
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Password Reset', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test magic link email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_magic_link( $recipient ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Magic Link for %s', 'nobloat-user-foundry' ),
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: user display name */
			__( 'Hi %s,', 'nobloat-user-foundry' ),
			'Test User'
		) . "\n\n";
		$message .= sprintf(
			/* translators: %s: site name */
			__( 'You requested a magic link to log into %s.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( 'Click the link below to log in:', 'nobloat-user-foundry' ) . "\n\n";
		$message .= home_url( '/user-foundry/magic-link/?token=test-magic-link-token-12345' ) . "\n\n";
		$message .= sprintf(
			/* translators: %d: expiration time in minutes */
			__( 'This link expires in %d minutes and can only be used once.', 'nobloat-user-foundry' ),
			15
		) . "\n\n";
		$message .= __( 'If you did not request this link, you can safely ignore this email.', 'nobloat-user-foundry' ) . "\n\n";
		$message .= sprintf(
			/* translators: %s: site name */
			__( '- The %s Team', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( '(This is a test email - the link above will not work)', 'nobloat-user-foundry' );

		return wp_mail( $recipient, $subject, $message );
	}

	/**
	 * Send test admin new user notification email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_admin_notification( $recipient ) {
		$mode          = 'html';
		$template_name = ( 'html' === $mode ) ? 'admin-new-user-html' : 'admin-new-user-text';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		$replacements = array(
			'{site_name}'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'          => home_url(),
			'{username}'          => 'testuser',
			'{user_email}'        => 'testuser@example.com',
			'{registration_date}' => current_time( 'mysql' ),
			'{user_profile_link}' => admin_url( 'user-edit.php?user_id=1' ),
		);

		$message = strtr( $template, $replacements );
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] New User Registration', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		$sent = wp_mail( $recipient, $subject, $message );

		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send test security alert email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_security_alert( $recipient ) {
		/*
		 * Use the real security alert system to test the actual template and flow.
		 * This tests the HTML template and NBUF_Email::send() integration.
		 *
		 * Note: The recipient parameter is ignored - the email goes to the
		 * configured security alert recipient (admin or custom email).
		 * This tests the actual production flow.
		 */
		if ( ! class_exists( 'NBUF_Security_Log' ) ) {
			return false;
		}

		/* Check if security alerts are enabled */
		$alerts_enabled = NBUF_Options::get( 'nbuf_security_log_alerts_enabled', false );
		if ( ! $alerts_enabled ) {
			/*
			 * Alerts are disabled - send a simple notification to the specified
			 * recipient explaining that alerts need to be enabled.
			 */
			$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$subject   = sprintf(
				/* translators: %s: site name */
				__( '[%s] Security Alerts Not Enabled', 'nobloat-user-foundry' ),
				$site_name
			);
			$message = __( 'Security alert emails are currently disabled.', 'nobloat-user-foundry' ) . "\n\n";
			$message .= __( 'To receive security alerts for critical events (brute force attacks, privilege escalation, etc.), enable them in:', 'nobloat-user-foundry' ) . "\n";
			$message .= __( 'User Foundry > GDPR & Privacy > Logging > Enable Critical Alert Emails', 'nobloat-user-foundry' ) . "\n\n";
			$message .= __( 'Once enabled, security alerts will be sent using the HTML template to your configured recipient.', 'nobloat-user-foundry' );

			return wp_mail( $recipient, $subject, $message );
		}

		/* Use the real security log test method which tests the full flow */
		$result = NBUF_Security_Log::send_test_email();

		/* send_test_email returns true on success, WP_Error on failure */
		return true === $result;
	}

	/**
	 * Send test profile change notification email.
	 *
	 * @param  string $recipient Email recipient.
	 * @return bool True if sent successfully.
	 */
	private static function send_test_profile_change( $recipient ) {
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$current_user = wp_get_current_user();

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Test Profile Change Notification', 'nobloat-user-foundry' ),
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: site name */
			__( 'This is a test profile change notification from %s.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( 'If you received this email, your profile change notifications are configured correctly.', 'nobloat-user-foundry' ) . "\n\n";
		$message .= __( '--- Sample Notification ---', 'nobloat-user-foundry' ) . "\n\n";
		$message .= sprintf(
			/* translators: %s: username */
			__( 'User: %s', 'nobloat-user-foundry' ),
			$current_user->user_login
		) . "\n";
		$message .= sprintf(
			/* translators: %s: display name */
			__( 'Display Name: %s', 'nobloat-user-foundry' ),
			$current_user->display_name
		) . "\n";
		$message .= sprintf(
			/* translators: %s: email */
			__( 'Email: %s', 'nobloat-user-foundry' ),
			$current_user->user_email
		) . "\n\n";
		$message .= __( 'Changes:', 'nobloat-user-foundry' ) . "\n";
		$message .= __( '• Display Name: "Old Name" → "New Name"', 'nobloat-user-foundry' ) . "\n";
		$message .= __( '• Email: "old@example.com" → "new@example.com"', 'nobloat-user-foundry' ) . "\n\n";
		$message .= sprintf(
			/* translators: %s: date/time */
			__( 'Changed at: %s', 'nobloat-user-foundry' ),
			current_time( 'F j, Y g:i a' )
		) . "\n\n";
		$message .= __( 'This is a test notification. No actual profile change occurred.', 'nobloat-user-foundry' );

		return wp_mail( $recipient, $subject, $message );
	}

	/**
	 * Handle AJAX test for profile change notification.
	 */
	public static function ajax_test_change_notification() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'nobloat-user-foundry' ) ) );
		}

		if ( ! wp_verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'nbuf_test_notification' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'nobloat-user-foundry' ) ) );
		}

		$recipient = NBUF_Options::get( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ) );

		/* Handle array or string */
		if ( is_array( $recipient ) ) {
			$recipient = implode( ', ', $recipient );
		}

		/* Get first email if multiple */
		$emails = array_map( 'trim', explode( ',', $recipient ) );
		$to     = ! empty( $emails[0] ) ? $emails[0] : get_option( 'admin_email' );

		/* Build test notification email */
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject   = sprintf(
			/* translators: %s: site name */
			__( '[%s] Test Profile Change Notification', 'nobloat-user-foundry' ),
			$site_name
		);

		$current_user = wp_get_current_user();

		$message = sprintf(
			/* translators: 1: site name */
			__( 'This is a test notification from %s.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";

		$message .= __( 'If you received this email, your profile change notifications are configured correctly.', 'nobloat-user-foundry' ) . "\n\n";

		$message .= __( '--- Sample Notification ---', 'nobloat-user-foundry' ) . "\n\n";

		$message .= sprintf(
			/* translators: %s: username */
			__( 'User: %s', 'nobloat-user-foundry' ),
			$current_user->user_login
		) . "\n";

		$message .= sprintf(
			/* translators: %s: display name */
			__( 'Display Name: %s', 'nobloat-user-foundry' ),
			$current_user->display_name
		) . "\n";

		$message .= sprintf(
			/* translators: %s: email */
			__( 'Email: %s', 'nobloat-user-foundry' ),
			$current_user->user_email
		) . "\n\n";

		$message .= __( 'Changes:', 'nobloat-user-foundry' ) . "\n";
		$message .= __( '• Display Name: "Old Name" → "New Name"', 'nobloat-user-foundry' ) . "\n";
		$message .= __( '• Email: "old@example.com" → "new@example.com"', 'nobloat-user-foundry' ) . "\n\n";

		$message .= sprintf(
			/* translators: %s: date/time */
			__( 'Changed at: %s', 'nobloat-user-foundry' ),
			current_time( 'F j, Y g:i a' )
		) . "\n";

		$sent = wp_mail( $to, $subject, $message );

		if ( $sent ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: email address */
						__( 'Test notification sent to %s', 'nobloat-user-foundry' ),
						$to
					),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test notification. Check your email configuration.', 'nobloat-user-foundry' ) ) );
		}
	}

	/**
	 * AJAX handler for testing a webhook.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_test_webhook() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'nobloat-user-foundry' ) ) );
		}

		if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'nbuf_test_webhook' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'nobloat-user-foundry' ) ) );
		}

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'nobloat-user-foundry' ) ) );
		}

		if ( ! class_exists( 'NBUF_Webhooks' ) ) {
			wp_send_json_error( array( 'message' => __( 'Webhooks not available.', 'nobloat-user-foundry' ) ) );
		}

		$result = NBUF_Webhooks::test( $webhook_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'code'    => $result['code'],
					'message' => $result['message'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'code'    => $result['code'],
					'message' => $result['message'],
				)
			);
		}
	}

	/**
	 * Redirect with status code.
	 *
	 * @param string $code Status code (success, failed, missing).
	 */
	private static function redirect_with( $code ) {
		$url = add_query_arg(
			array(
				'page'      => 'nobloat-foundry-users',
				'tab'       => 'tools',
				'subtab'    => 'email-tests',
				'nbuf_test' => $code,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Display admin notice.
	 */
	public static function admin_notice() {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter for admin notice
		if ( ! isset( $_GET['nbuf_test'] ) ) {
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : '';

		if ( 'tools' !== $tab || 'email-tests' !== $subtab ) {
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter
		$code = sanitize_text_field( wp_unslash( $_GET['nbuf_test'] ) );
		$map  = array(
			'success' => array( 'notice-success', __( 'Test email sent successfully! Check your inbox.', 'nobloat-user-foundry' ) ),
			'failed'  => array( 'notice-error', __( 'Failed to send test email. Please check your email server configuration.', 'nobloat-user-foundry' ) ),
			'missing' => array( 'notice-warning', __( 'Please select an email type and enter both sender and recipient emails.', 'nobloat-user-foundry' ) ),
		);

		if ( isset( $map[ $code ] ) ) {
			list( $cls, $msg ) = $map[ $code ];
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p><strong>' . esc_html( $msg ) . '</strong></p></div>';
		}
	}
}
