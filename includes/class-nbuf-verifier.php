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
	 * Returns HTML (never echoes) so WP can place it in content.
	 *
	 * @return string HTML output.
	 */
	public static function render_for_shortcode() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( '' === $token ) {
			// No token present; show a minimal helpful stub.
			return self::wrap_notice(
				__( 'No verification token found in the URL.', 'nobloat-user-foundry' ),
				false
			);
		}

		return self::handle_token( $token );
	}

    /* ==========================================================
       handle_token()
       ----------------------------------------------------------
       Core verification logic. Returns HTML snippet.
       ========================================================== */
    private static function handle_token(string $token): string {
        global $wpdb;

        $table = $wpdb->prefix . NBUF_DB_TABLE;
        $entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token)
        );

        if (!$entry) {
            /* Log failed verification - invalid token (cannot log user_id since entry doesn't exist) */
            return self::wrap_notice(
                __('This verification link is invalid or has already been used.', 'nobloat-user-foundry'),
                false
            );
        }

        $now = current_time('mysql');

        // Expired token
        if ((string) $entry->expires_at < $now) {
            /* Log failed verification - expired token */
            if (!empty($entry->user_id)) {
                NBUF_Audit_Log::log(
                    $entry->user_id,
                    'email_verification_failed',
                    'failure',
                    'Verification link expired'
                );
            }
            $wpdb->delete($table, ['id' => (int) $entry->id]);
            return self::wrap_notice(
                __('This verification link has expired. Please request a new one.', 'nobloat-user-foundry'),
                false
            );
        }

        // Test tokens: report success but do not mark verified
        if (!empty($entry->is_test) && (int) $entry->is_test === 1) {
            $wpdb->delete($table, ['id' => (int) $entry->id]);
            return self::wrap_notice(
                __('Test verification successful. The plugin is functioning correctly.', 'nobloat-user-foundry'),
                true
            );
        }

        // Mark user verified (if a user_id exists)
        if (!empty($entry->user_id)) {
            NBUF_User_Data::set_verified((int) $entry->user_id);

            /* Log successful email verification */
            NBUF_Audit_Log::log(
                $entry->user_id,
                'email_verified',
                'success',
                'Email address verified successfully',
                array( 'email' => $entry->user_email )
            );
        }

        // One-time token: delete after use
        $wpdb->delete($table, ['id' => (int) $entry->id]);

        /**
         * Fire hook for integrations.
         *
         * @param string $email
         * @param int    $user_id
         */
        do_action('nbuf_user_verified', (string) $entry->user_email, (int) $entry->user_id);

        // Optional post-verify redirect: since we're in shortcode context,
        // avoid sending headers. Show a clean success message with a link.
        $settings     = NBUF_Options::get('nbuf_settings', []);
        $redirect_url = !empty($settings['verified_redirect']) ? esc_url_raw($settings['verified_redirect']) : '';

        $msg  = __('Your email address has been successfully verified. You may now log in.', 'nobloat-user-foundry');
        $html = self::wrap_notice($msg, true);

        if ($redirect_url) {
            // Add a small follow-up line with a link (no auto-redirect to keep it simple & reliable).
            $html .= '<p class="nobloat-verify-next" style="text-align:center;margin-top:12px;">'
                   . '<a class="nobloat-verify-return" href="' . esc_url($redirect_url) . '">'
                   . esc_html__('Continue', 'nobloat-user-foundry')
                   . '</a></p>';
        } else {
            // Fallback link to home
            $html .= '<p class="nobloat-verify-next" style="text-align:center;margin-top:12px;">'
                   . '<a class="nobloat-verify-return" href="' . esc_url(home_url('/')) . '">'
                   . esc_html__('Return to site', 'nobloat-user-foundry')
                   . '</a></p>';
        }

        return $html;
    }

    /* ==========================================================
       wrap_notice()
       ----------------------------------------------------------
       Tiny helper to return a minimal, styled message block.
       Keep it theme-friendly; no external CSS required.
       ========================================================== */
    private static function wrap_notice(string $message, bool $success): string {
        $title = $success
               ? __('Verification Successful', 'nobloat-user-foundry')
               : __('Verification Failed', 'nobloat-user-foundry');

        $color = $success ? '#2d8a34' : '#c0392b';

        // Minimal inline styles to avoid dependency on theme CSS.
        // Admin can override via theme CSS if desired.
        $out  = '<div class="nobloat-verify-wrapper" style="max-width:640px;margin:64px auto 32px auto;text-align:center;">';
        $out .= '<h1 class="nobloat-verify-title" style="margin:0 0 10px 0;color:' . esc_attr($color) . ';">' . esc_html($title) . '</h1>';
        $out .= '<p class="nobloat-verify-message" style="margin:0 0 8px 0;">' . esc_html($message) . '</p>';
        $out .= '</div>';
        return $out;
    }
}

// Initialize (kept for parity; does not hook front-end output).
NBUF_Verifier::init();