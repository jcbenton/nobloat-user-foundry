<?php
/**
 * NoBloat User Foundry - Email Handler
 *
 * Handles preparing and sending verification emails using
 * stored templates and placeholder replacement.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Email
 *
 * Handles email sending functionality.
 */
class NBUF_Email {


	/**
	 * Send email with custom sender settings.
	 *
	 * Central method that applies configured sender address and name
	 * before sending via wp_mail. Uses settings from System > Email tab.
	 *
	 * @param  string $to          Recipient email address.
	 * @param  string $subject     Email subject.
	 * @param  string $message     Email body.
	 * @param  string $content_type Optional. 'html' or 'text'. Default 'text'.
	 * @return bool True on success, false on failure.
	 */
	public static function send( $to, $subject, $message, $content_type = 'text' ) {
		/* Get sender settings with WordPress defaults as fallback */
		$sender_address = NBUF_Options::get( 'nbuf_email_sender_address', '' );
		$sender_name    = NBUF_Options::get( 'nbuf_email_sender_name', '' );

		/* Fall back to WordPress defaults if not set */
		if ( empty( $sender_address ) ) {
			$sender_address = get_option( 'admin_email' );
		}
		if ( empty( $sender_name ) ) {
			$sender_name = get_bloginfo( 'name' );
		}

		/*
		 * Create filter callbacks.
		 *
		 * Anonymous functions are used here to temporarily override wp_mail settings
		 * for this specific email, then removed after sending.
		 */

		/**
		 * Returns the sender email address for wp_mail_from filter.
		 *
		 * @return string Sender email address.
		 */
		$from_callback = function () use ( $sender_address ): string {
			return $sender_address;
		};

		/**
		 * Returns the sender name for wp_mail_from_name filter.
		 *
		 * @return string Sender display name.
		 */
		$name_callback = function () use ( $sender_name ): string {
			return $sender_name;
		};

		/**
		 * Returns the content type for wp_mail_content_type filter.
		 *
		 * @return string Content type (text/html or text/plain).
		 */
		$content_type_callback = function () use ( $content_type ): string {
			return 'html' === $content_type ? 'text/html' : 'text/plain';
		};

		/* Apply filters */
		add_filter( 'wp_mail_from', $from_callback );
		add_filter( 'wp_mail_from_name', $name_callback );
		add_filter( 'wp_mail_content_type', $content_type_callback );

		/* Send email */
		$sent = wp_mail( $to, $subject, $message );

		/* Remove filters to prevent affecting other emails */
		remove_filter( 'wp_mail_from', $from_callback );
		remove_filter( 'wp_mail_from_name', $name_callback );
		remove_filter( 'wp_mail_content_type', $content_type_callback );

		return $sent;
	}

	/**
	 * Send verification email.
	 *
	 * Builds and sends the verification email message.
	 *
	 * @param  string       $user_email User email address.
	 * @param  string       $token      Verification token.
	 * @param  WP_User|null $user       Optional. User object to avoid redundant query.
	 * @return bool True on success, false on failure.
	 */
	public static function send_verification_email( $user_email, $token, $user = null ) {

		// Load plugin settings.
		$settings = NBUF_Options::get( 'nbuf_settings', array() );
		$mode     = isset( $settings['email_mode'] ) && 'text' === $settings['email_mode'] ? 'text' : 'html';

		// Get verification page URL using helper method (supports Universal Router).
		$verification_base = '';
		if ( class_exists( 'NBUF_Shortcodes' ) && method_exists( 'NBUF_Shortcodes', 'get_verification_url' ) ) {
			$verification_base = NBUF_Shortcodes::get_verification_url();
		} else {
			$verification_page_id = NBUF_Options::get( 'nbuf_page_verification', 0 );
			$verification_base    = $verification_page_id ? get_permalink( $verification_page_id ) : home_url( '/nobloat-verify' );
		}

		// Construct verification URL.
		$verification_url = add_query_arg( 'token', rawurlencode( $token ), $verification_base );

		// Load email template.
		$template = self::get_template_content( $mode );

		/* PERFORMANCE: Only query user if not provided by caller */
		if ( ! $user ) {
			$user = get_user_by( 'email', $user_email );
		}

		// Gather user info (if available).
		$display_name = $user ? $user->display_name : $user_email;
		$username     = $user ? $user->user_login : $user_email;

		// Define replacements.
		$replacements = array(
			'{site_name}'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'         => home_url(),
			'{user_email}'       => sanitize_email( $user_email ),
			'{display_name}'     => sanitize_text_field( $display_name ),
			'{username}'         => sanitize_text_field( $username ),
			'{verify_link}'      => esc_url( $verification_url ),
			'{verification_url}' => esc_url( $verification_url ),
		);

		// Replace placeholders.
		$message = strtr( $template, $replacements );

		// Subject.
		$subject = ! empty( $settings['email_subject'] )
		? sanitize_text_field( $settings['email_subject'] )
		: __( 'Verify your email address', 'nobloat-user-foundry' );

		// Send email using central method with sender settings.
		$sent = self::send( $user_email, $subject, $message, $mode );

		/*
		* CRITICAL: Always log email failures in production
		*
		* Silent email failures are a major user experience issue. Without logging,
		* users never receive verification emails and there's no debugging info.
		*
		* This logs to both error_log (for server admins) and audit log (for WP admins).
		*/
		if ( ! $sent ) {
			/* ALWAYS log email failures, even in production */
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical production logging for email failures.
				sprintf(
					'[NoBloat User Foundry] CRITICAL: Failed to send verification email to %s',
					$user_email
				)
			);

			/* Log to audit log for admin visibility */
			if ( class_exists( 'NBUF_Audit_Log' ) && $user ) {
				NBUF_Audit_Log::log(
					$user->ID,
					'email_verification_failed',
					'failure',
					'Failed to send verification email',
					array( 'email' => $user_email )
				);
			}
		}

		return $sent;
	}

	/**
	 * Send account approved email.
	 *
	 * Notifies user their account has been approved by an administrator.
	 *
	 * @param  string $user_email User email address.
	 * @param  string $username   Username.
	 * @return bool True on success, false on failure.
	 */
	public static function send_account_approved_email( $user_email, $username ) {
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Your account has been approved on %s', 'nobloat-user-foundry' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		/* translators: 1: username, 2: site name, 3: login URL */
		$message_template = __(
			'Hello %1$s,

Your account on %2$s has been approved by an administrator.

You can now log in to your account:
%3$s

Thank you!',
			'nobloat-user-foundry'
		);

		$message = sprintf(
			$message_template,
			sanitize_text_field( $username ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			wp_login_url()
		);

		$sent = self::send( $user_email, $subject, $message );

		if ( ! $sent ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical production logging for email failures.
				sprintf(
					'[NoBloat User Foundry] CRITICAL: Failed to send approval email to %s',
					$user_email
				)
			);
		}

		return $sent;
	}

	/**
	 * Send account rejected email.
	 *
	 * Notifies user their account has been rejected by an administrator.
	 *
	 * @param  string $user_email User email address.
	 * @param  string $reason     Rejection reason (optional).
	 * @return bool True on success, false on failure.
	 */
	public static function send_account_rejected_email( $user_email, $reason = '' ) {
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Account registration update from %s', 'nobloat-user-foundry' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message_parts = array(
			sprintf(
				/* translators: %s: Site name */
				__( 'Your account registration on %s has been reviewed.', 'nobloat-user-foundry' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
			'',
			__( 'Unfortunately, your account was not approved at this time.', 'nobloat-user-foundry' ),
		);

		if ( ! empty( $reason ) && 'Bulk rejection by administrator' !== $reason && 'Rejected by administrator' !== $reason ) {
			$message_parts[] = '';
			$message_parts[] = __( 'Reason:', 'nobloat-user-foundry' );
			$message_parts[] = sanitize_text_field( $reason );
		}

		$message_parts[] = '';
		$message_parts[] = sprintf(
			/* translators: %s: Site contact URL */
			__( 'If you have questions, please contact us: %s', 'nobloat-user-foundry' ),
			admin_url( 'admin.php?page=nobloat-foundry-users' )
		);

		$message = implode( "\n", $message_parts );

		$sent = self::send( $user_email, $subject, $message );

		if ( ! $sent ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical production logging for email failures.
				sprintf(
					'[NoBloat User Foundry] CRITICAL: Failed to send rejection email to %s',
					$user_email
				)
			);
		}

		return $sent;
	}

	/**
	 * Get template content.
	 *
	 * Fetches stored or fallback email template using Template Manager.
	 *
	 * @param  string $type Template type (html or text).
	 * @return string Template content.
	 */
	private static function get_template_content( $type = 'html' ) {
		$template_name = ( 'html' === $type )
		? 'email-verification-html'
		: 'email-verification-text';

		// Use Template Manager for loading (uses custom table + caching).
		return NBUF_Template_Manager::load_template( $template_name );
	}
}
