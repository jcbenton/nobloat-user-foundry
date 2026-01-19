<?php
/**
 * NoBloat User Foundry - Token Verifier (Shortcode-first)
 *
 * Validates tokens and returns minimal markup for use inside
 * a normal WordPress page via [nbuf_verify_page].
 * No template hijacking, no get_header/footer, no exits.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Verifier
 *
 * Handles email verification token validation.
 */
class NBUF_Verifier {


	/**
	 * Initialize verifier.
	 *
	 * Kept for future extension; no front-end hijacking here.
	 */
	public static function init() {
		// Intentionally empty: verification is invoked by shortcode.
	}

	/**
	 * Render for shortcode.
	 *
	 * Entry point used by [nbuf_verify_page].
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based verification, not form submission.
	 * Returns HTML (never echoes) so WP can place it in content.
	 *
	 * @return string HTML output.
	 */
	public static function render_for_shortcode() {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based verification via GET parameter, not form submission.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( '' === $token ) {
			// No token present; show a minimal helpful stub.
			return self::wrap_notice(
				__( 'No verification token found in the URL.', 'nobloat-user-foundry' ),
				false
			);
		}

		/* SECURITY: Validate token format (64 alphanumeric characters) */
		if ( strlen( $token ) !== 64 || ! ctype_alnum( $token ) ) {
			return self::wrap_notice(
				__( 'Invalid token format.', 'nobloat-user-foundry' ),
				false
			);
		}

		return self::handle_token( $token );
	}

	/**
	 * ==========================================================
	 * handle_token()
	 * ----------------------------------------------------------
	 * Core verification logic. Returns HTML snippet.
	 *
	 * @param  string $token Verification token.
	 * @return string HTML output.
	 * ==========================================================
	 */
	private static function handle_token( string $token ): string {
		global $wpdb;

		$table = $wpdb->prefix . NBUF_DB_TABLE;

		/*
		 * SECURITY: Use transaction to make FOR UPDATE lock effective.
		 * This prevents race conditions where the same token could be
		 * verified simultaneously by multiple requests.
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$entry = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE token = %s FOR UPDATE', $table, $token )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $entry ) {
			$wpdb->query( 'ROLLBACK' );
			/* Log failed verification - invalid token (cannot log user_id since entry doesn't exist) */
			return self::wrap_notice(
				__( 'This verification link is invalid or has already been used.', 'nobloat-user-foundry' ),
				false
			);
		}

		$now = current_time( 'mysql', true );

		// Expired token.
		if ( (string) $entry->expires_at < $now ) {
			/* Log failed verification - expired token */
			if ( ! empty( $entry->user_id ) ) {
				NBUF_Audit_Log::log(
					$entry->user_id,
					'email_verification_failed',
					'failure',
					'Verification link expired'
				);
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Architectural token update.
			$wpdb->delete( $table, array( 'id' => (int) $entry->id ) );
			$wpdb->query( 'COMMIT' );
			return self::wrap_notice(
				__( 'This verification link has expired. Please request a new one.', 'nobloat-user-foundry' ),
				false
			);
		}

		// Test tokens: report success but do not mark verified.
		if ( ! empty( $entry->is_test ) && 1 === (int) $entry->is_test ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Architectural token deletion.
			$wpdb->delete( $table, array( 'id' => (int) $entry->id ) );
			$wpdb->query( 'COMMIT' );
			return self::wrap_notice(
				__( 'Test verification successful. The plugin is functioning correctly.', 'nobloat-user-foundry' ),
				true
			);
		}

		// Mark user verified (if a user_id exists).
		$email_was_changed = false;
		if ( ! empty( $entry->user_id ) ) {
			$user_id = (int) $entry->user_id;

			/*
			 * Check for pending email change.
			 * If this verification is for a pending email, apply the change.
			 */
			$pending_email = get_user_meta( $user_id, 'nbuf_pending_email', true );
			if ( $pending_email && strtolower( $pending_email ) === strtolower( $entry->user_email ) ) {
				/* Get old email for notification */
				$user      = get_userdata( $user_id );
				$old_email = $user ? $user->user_email : '';

				/* Set flag to prevent hooks from triggering re-verification */
				if ( class_exists( 'NBUF_Hooks' ) ) {
					NBUF_Hooks::set_applying_pending_email( true );
				}

				/* Disable WordPress's built-in email change notification - we send our own */
				add_filter( 'send_email_change_email', '__return_false' );

				/* Apply the email change */
				$result = wp_update_user(
					array(
						'ID'         => $user_id,
						'user_email' => $pending_email,
					)
				);

				/* Re-enable WordPress email change notification */
				remove_filter( 'send_email_change_email', '__return_false' );

				/* Reset flag */
				if ( class_exists( 'NBUF_Hooks' ) ) {
					NBUF_Hooks::set_applying_pending_email( false );
				}

				if ( ! is_wp_error( $result ) ) {
					$email_was_changed = true;

					/* Clear pending email */
					delete_user_meta( $user_id, 'nbuf_pending_email' );

					/* Send notification to old email */
					if ( $old_email && class_exists( 'NBUF_Shortcodes' ) ) {
						NBUF_Shortcodes::send_email_change_notification( $user_id, $old_email, $pending_email );
					}

					/* Log email change completion */
					NBUF_Audit_Log::log(
						$user_id,
						'email_changed',
						'success',
						'Email address changed after verification',
						array(
							'old_email' => $old_email,
							'new_email' => $pending_email,
						)
					);
				}
			}

			NBUF_User_Data::set_verified( $user_id );

			/* Log successful email verification */
			NBUF_Audit_Log::log(
				$entry->user_id,
				'email_verified',
				'success',
				'Email address verified successfully',
				array( 'email' => $entry->user_email )
			);
		}

		// One-time token: delete after use.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Architectural auto-login update.
		$wpdb->delete( $table, array( 'id' => (int) $entry->id ) );
		$wpdb->query( 'COMMIT' );

		/**
		 * Fire hook for integrations.
		 *
		 * @param string $email User email.
		 * @param int    $user_id User ID.
		 */
		do_action( 'nbuf_user_verified', (string) $entry->user_email, (int) $entry->user_id );

		// Optional post-verify redirect: since we're in shortcode context.
		// avoid sending headers. Show a clean success message with a link.
		$settings     = NBUF_Options::get( 'nbuf_settings', array() );
		$redirect_url = ! empty( $settings['verified_redirect'] ) ? esc_url_raw( $settings['verified_redirect'] ) : '';

		if ( $email_was_changed ) {
			$msg = __( 'Your new email address has been verified and is now active on your account.', 'nobloat-user-foundry' );
		} else {
			$msg = __( 'Your email address has been successfully verified. You may now log in.', 'nobloat-user-foundry' );
		}
		$html = self::wrap_notice( $msg, true );

		/* Get login URL from settings (respects Universal Router) */
		$login_url = '';
		if ( class_exists( 'NBUF_Shortcodes' ) && method_exists( 'NBUF_Shortcodes', 'get_login_url' ) ) {
			$login_url = NBUF_Shortcodes::get_login_url();
		}
		if ( empty( $login_url ) ) {
			$login_url = wp_login_url();
		}

		/* Add "Go to Login" button */
		$html .= '<p class="nobloat-verify-next">'
			. '<button type="button" class="nobloat-verify-login-btn" onclick="window.location.href=\'' . esc_url( $login_url ) . '\'">'
			. esc_html__( 'Go to Login', 'nobloat-user-foundry' )
			. '</button></p>';

		return $html;
	}

	/**
	 * ==========================================================
	 * wrap_notice()
	 * ----------------------------------------------------------
	 * Tiny helper to return a minimal, styled message block.
	 * Keep it theme-friendly; no external CSS required.
	 *
	 * @param  string $message Notice message text.
	 * @param  bool   $success Whether this is a success or failure notice.
	 * @return string HTML output.
	 * ==========================================================
	 */
	private static function wrap_notice( string $message, bool $success ): string {
		$title = $success
		? __( 'Verification Successful', 'nobloat-user-foundry' )
		: __( 'Verification Failed', 'nobloat-user-foundry' );

		$color = $success ? '#2d8a34' : '#c0392b';

		// Using CSS classes from account-page.css; color is dynamic for success/failure.
		$out  = '<div class="nobloat-verify-wrapper">';
		$out .= '<h1 class="nobloat-verify-title" style="color:' . esc_attr( $color ) . ';">' . esc_html( $title ) . '</h1>';
		$out .= '<p class="nobloat-verify-message">' . esc_html( $message ) . '</p>';
		$out .= '</div>';
		return $out;
	}
}

// Initialize (kept for parity; does not hook front-end output).
NBUF_Verifier::init();
