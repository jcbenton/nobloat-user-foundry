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
			case 'expiration-warning':
				$sent = self::send_test_expiration( $recipient );
				break;
			case '2fa-email-code':
				$sent = self::send_test_2fa_code( $recipient );
				break;
			case 'password-reset':
				$sent = self::send_test_password_reset( $recipient );
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
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$current_user = wp_get_current_user();

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Test Security Alert', 'nobloat-user-foundry' ),
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: site name */
			__( 'This is a test security alert from %s.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";
		$message .= __( 'If you received this email, your security alert notifications are configured correctly.', 'nobloat-user-foundry' ) . "\n\n";
		$message .= __( '--- Sample Alert ---', 'nobloat-user-foundry' ) . "\n\n";
		$message .= __( 'Event Type: Test Alert', 'nobloat-user-foundry' ) . "\n";
		$message .= __( 'Severity: Critical', 'nobloat-user-foundry' ) . "\n";
		$message .= sprintf(
			/* translators: %s: username */
			__( 'User: %s', 'nobloat-user-foundry' ),
			$current_user->user_login
		) . "\n";
		$message .= __( 'IP Address: 127.0.0.1', 'nobloat-user-foundry' ) . "\n";
		$message .= sprintf(
			/* translators: %s: date/time */
			__( 'Time: %s', 'nobloat-user-foundry' ),
			current_time( 'F j, Y g:i a' )
		) . "\n\n";
		$message .= __( 'This is a test notification. No actual security event occurred.', 'nobloat-user-foundry' );

		return wp_mail( $recipient, $subject, $message );
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
	 * Redirect with status code.
	 *
	 * @param string $code Status code (success, failed, missing).
	 */
	private static function redirect_with( $code ) {
		$url = add_query_arg(
			array(
				'page'      => 'nobloat-foundry-users',
				'tab'       => 'tools',
				'subtab'    => 'tests',
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

		if ( 'tools' !== $tab || 'tests' !== $subtab ) {
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
