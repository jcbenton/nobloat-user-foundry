<?php
/**
 * NoBloat User Foundry - 2FA Account Management
 *
 * Handles 2FA configuration UI and actions on the account page.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 2FA Account Management class.
 *
 * Provides security tab HTML and 2FA action handlers for account page.
 */
class NBUF_2FA_Account {

	/**
	 * Build Security Tab HTML for account page.
	 *
	 * Generates HTML for security/2FA section on account page.
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output (empty if 2FA not available).
	 */
	public static function build_security_tab_html( $user_id ) {
		/* Check if any 2FA method is available */
		$email_method = NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
		$totp_method  = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );

		/* Accept both old and new option values for backwards compatibility */
		$email_available = in_array( $email_method, array( 'optional_all', 'required_all', 'required_admin', 'user_configurable', 'required' ), true );
		$totp_available  = in_array( $totp_method, array( 'optional_all', 'required_all', 'required_admin', 'user_configurable', 'required' ), true );

		/* If no 2FA methods are available, return empty */
		if ( ! $email_available && ! $totp_available ) {
			return '';
		}

		/* Get user's current 2FA status */
		$current_method = NBUF_2FA::get_user_method( $user_id );
		$has_totp       = ( 'totp' === $current_method || 'both' === $current_method );
		$has_email      = ( 'email' === $current_method || 'both' === $current_method );

		$html  = '<div class="nbuf-account-section">';
		$html .= '<h2 class="nbuf-section-title">' . esc_html__( 'Two-Factor Authentication', 'nobloat-user-foundry' ) . '</h2>';

		/* 2FA Status overview */
		$html .= '<div class="nbuf-2fa-status">';
		if ( $current_method ) {
			$html .= '<p class="nbuf-2fa-enabled"><span class="nbuf-icon">&#10003;</span> ' . esc_html__( 'Two-factor authentication is enabled on your account.', 'nobloat-user-foundry' ) . '</p>';
		} else {
			$html .= '<p class="nbuf-2fa-disabled"><span class="nbuf-icon">&#9888;</span> ' . esc_html__( 'Two-factor authentication is not enabled. Enable it below for extra security.', 'nobloat-user-foundry' ) . '</p>';
		}
		$html .= '</div>';

		/* TOTP (Authenticator App) section */
		if ( $totp_available ) {
			$html .= '<div class="nbuf-2fa-method-card">';
			$html .= '<h3>' . esc_html__( 'Authenticator App', 'nobloat-user-foundry' ) . '</h3>';
			$html .= '<p class="nbuf-method-description">' . esc_html__( 'Use an authenticator app like Google Authenticator, Authy, or 1Password to generate verification codes.', 'nobloat-user-foundry' ) . '</p>';

			if ( $has_totp ) {
				$html .= '<p class="nbuf-method-status nbuf-status-active"><span class="nbuf-icon">&#10003;</span> ' . esc_html__( 'Active', 'nobloat-user-foundry' ) . '</p>';
				$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
				$html .= wp_nonce_field( 'nbuf_2fa_disable_totp', 'nbuf_2fa_nonce', true, false );
				$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_totp">';
				$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary">' . esc_html__( 'Disable Authenticator', 'nobloat-user-foundry' ) . '</button>';
				$html .= '</form>';
			} else {
				/* Link to 2FA setup page */
				$setup_page_id = NBUF_Options::get( 'nbuf_page_2fa_setup', 0 );
				$setup_url     = $setup_page_id ? get_permalink( $setup_page_id ) : '';

				if ( $setup_url ) {
					$html .= '<a href="' . esc_url( $setup_url ) . '" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Set Up Authenticator', 'nobloat-user-foundry' ) . '</a>';
				} else {
					$html .= '<p class="nbuf-setup-note">' . esc_html__( 'Contact administrator to set up authenticator app.', 'nobloat-user-foundry' ) . '</p>';
				}
			}
			$html .= '</div>';
		}

		/* Email 2FA section */
		if ( $email_available ) {
			$html .= '<div class="nbuf-2fa-method-card">';
			$html .= '<h3>' . esc_html__( 'Email Verification', 'nobloat-user-foundry' ) . '</h3>';
			$html .= '<p class="nbuf-method-description">' . esc_html__( 'Receive a verification code via email each time you log in.', 'nobloat-user-foundry' ) . '</p>';

			if ( $has_email ) {
				$html .= '<p class="nbuf-method-status nbuf-status-active"><span class="nbuf-icon">&#10003;</span> ' . esc_html__( 'Active', 'nobloat-user-foundry' ) . '</p>';
				$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
				$html .= wp_nonce_field( 'nbuf_2fa_disable_email', 'nbuf_2fa_nonce', true, false );
				$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_email">';
				$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary">' . esc_html__( 'Disable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
				$html .= '</form>';
			} else {
				$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
				$html .= wp_nonce_field( 'nbuf_2fa_enable_email', 'nbuf_2fa_nonce', true, false );
				$html .= '<input type="hidden" name="nbuf_2fa_action" value="enable_email">';
				$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Enable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
				$html .= '</form>';
			}
			$html .= '</div>';
		}

		/* Backup codes section - only show if 2FA is enabled */
		if ( $current_method && NBUF_Options::get( 'nbuf_2fa_backup_enabled', true ) ) {
			$backup_codes    = NBUF_User_2FA_Data::get_backup_codes( $user_id );
			$used_indexes    = NBUF_User_2FA_Data::get_backup_codes_used( $user_id );
			$codes_remaining = is_array( $backup_codes ) ? count( $backup_codes ) - count( (array) $used_indexes ) : 0;

			$html .= '<div class="nbuf-2fa-method-card nbuf-backup-codes-card">';
			$html .= '<h3>' . esc_html__( 'Backup Codes', 'nobloat-user-foundry' ) . '</h3>';
			$html .= '<p class="nbuf-method-description">' . esc_html__( 'One-time use codes for emergency access if you lose your authenticator device.', 'nobloat-user-foundry' ) . '</p>';

			if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
				$html .= '<p class="nbuf-codes-remaining">';
				$html .= sprintf(
					/* translators: %d: number of backup codes remaining */
					esc_html__( '%d backup codes remaining', 'nobloat-user-foundry' ),
					$codes_remaining
				);
				$html .= '</p>';
			}

			$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
			$html .= wp_nonce_field( 'nbuf_2fa_generate_backup', 'nbuf_2fa_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="generate_backup_codes">';

			if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
				$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary" onclick="return confirm(\'' . esc_js( __( 'This will replace your existing backup codes. Continue?', 'nobloat-user-foundry' ) ) . '\')">';
				$html .= esc_html__( 'Regenerate Backup Codes', 'nobloat-user-foundry' );
			} else {
				$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">';
				$html .= esc_html__( 'Generate Backup Codes', 'nobloat-user-foundry' );
			}
			$html .= '</button></form>';
			$html .= '</div>';
		}

		$html .= '</div>'; /* Close nbuf-account-section */

		return $html;
	}

	/**
	 * Handle 2FA account actions.
	 *
	 * Process 2FA enable/disable and backup code generation.
	 */
	public static function handle_actions() {
		/* Require logged in user */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below per action.
		$action = isset( $_POST['nbuf_2fa_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_action'] ) ) : '';

		/* Handle each 2FA action */
		switch ( $action ) {
			case 'enable_email':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_enable_email' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				NBUF_2FA::enable_for_user( $user_id, 'email' );
				wp_safe_redirect( add_query_arg( '2fa_updated', 'enabled', wp_get_referer() ) );
				exit;

			case 'disable_email':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_disable_email' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				/* Check if user has TOTP as well */
				$current_method = NBUF_2FA::get_user_method( $user_id );
				if ( 'both' === $current_method ) {
					/* Keep TOTP, remove email - update method to totp only */
					NBUF_User_2FA_Data::update( $user_id, array( 'method' => 'totp' ) );
				} else {
					NBUF_2FA::disable_for_user( $user_id );
				}
				wp_safe_redirect( add_query_arg( '2fa_updated', 'disabled', wp_get_referer() ) );
				exit;

			case 'disable_totp':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_disable_totp' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				/* Check if user has email as well */
				$current_method = NBUF_2FA::get_user_method( $user_id );
				if ( 'both' === $current_method ) {
					/* Keep email, remove TOTP - update method to email and clear secret */
					NBUF_User_2FA_Data::update(
						$user_id,
						array(
							'method'      => 'email',
							'totp_secret' => null,
						)
					);
				} else {
					NBUF_2FA::disable_for_user( $user_id );
				}
				wp_safe_redirect( add_query_arg( '2fa_updated', 'disabled', wp_get_referer() ) );
				exit;

			case 'generate_backup_codes':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_generate_backup' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				/* Generate new backup codes */
				$codes = NBUF_2FA::generate_backup_codes( $user_id );
				/* Store in transient to display once */
				set_transient( 'nbuf_backup_codes_' . $user_id, $codes, 300 );
				wp_safe_redirect( add_query_arg( 'backup_codes', 'generated', wp_get_referer() ) );
				exit;
		}
	}
}
