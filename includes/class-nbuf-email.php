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

		// Determine verification page path.
		$relative_path = ! empty( $settings['verification_page'] )
		? '/' . ltrim( $settings['verification_page'], '/' )
		: '/nbuf-verify';

		// Construct verification URL.
		$verification_url = add_query_arg( 'token', rawurlencode( $token ), home_url( $relative_path ) );

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

		// Set content type via filter instead of headers array.
		$content_type_callback = function () use ( $mode ) {
			return 'html' === $mode ? 'text/html' : 'text/plain';
		};
		add_filter( 'wp_mail_content_type', $content_type_callback );

		// Send email.
		$sent = wp_mail( $user_email, $subject, $message );

		// Remove filter to prevent affecting other emails.
		remove_filter( 'wp_mail_content_type', $content_type_callback );

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
