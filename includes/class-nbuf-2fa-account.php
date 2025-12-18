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
	 * Generates HTML for security section with sub-tabs on account page.
	 * Sub-tabs: Password, 2FA Email, Authenticator, Backup Codes, App Passwords, Privacy.
	 *
	 * @param  int    $user_id               User ID.
	 * @param  string $password_requirements Password requirements text.
	 * @return string HTML output.
	 */
	public static function build_security_tab_html( $user_id, $password_requirements = '' ) {
		/* Check if any 2FA method is available */
		$email_method = NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
		$totp_method  = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );

		/* Accept both old and new option values for backwards compatibility */
		$email_available = in_array( $email_method, array( 'optional_all', 'required_all', 'required_admin', 'user_configurable', 'required' ), true );
		$totp_available  = in_array( $totp_method, array( 'optional_all', 'required_all', 'required_admin', 'user_configurable', 'required' ), true );

		/* Check if methods are user-configurable (optional) vs required/forced */
		$email_user_can_toggle = in_array( $email_method, array( 'optional_all', 'user_configurable' ), true );

		/* Check if backup codes and application passwords are enabled */
		$backup_enabled        = NBUF_Options::get( 'nbuf_2fa_backup_enabled', true );
		$app_passwords_enabled = NBUF_Options::get( 'nbuf_app_passwords_enabled', false );

		/* Get user's current 2FA status */
		$current_method = NBUF_2FA::get_user_method( $user_id );
		$has_totp       = ( 'totp' === $current_method || 'both' === $current_method );
		$has_email      = ( 'email' === $current_method || 'both' === $current_method );

		/* Build sub-tabs list */
		$subtabs = array();

		/* Password sub-tab - always shown */
		$subtabs['password'] = __( 'Password', 'nobloat-user-foundry' );

		/* 2FA Email sub-tab - only show if user can toggle it (optional mode) */
		if ( $email_available && $email_user_can_toggle ) {
			$subtabs['2fa-email'] = __( '2FA Email', 'nobloat-user-foundry' );
		}

		/* Authenticator sub-tab - show if TOTP is available */
		if ( $totp_available ) {
			$subtabs['authenticator'] = __( 'Authenticator', 'nobloat-user-foundry' );
		}

		/* Backup Codes sub-tab - show if backup codes enabled AND any 2FA is available */
		if ( $backup_enabled && ( $email_available || $totp_available ) ) {
			$subtabs['backup-codes'] = __( 'Backup Codes', 'nobloat-user-foundry' );
		}

		/* App Passwords sub-tab - show if enabled */
		if ( $app_passwords_enabled ) {
			$subtabs['app-passwords'] = __( 'App Passwords', 'nobloat-user-foundry' );
		}

		/* Passkeys sub-tab - show if enabled */
		$passkeys_enabled = NBUF_Options::get( 'nbuf_passkeys_enabled', false );
		if ( $passkeys_enabled ) {
			$subtabs['passkeys'] = __( 'Passkeys', 'nobloat-user-foundry' );
		}

		/* Personal Data sub-tab - always shown */
		$subtabs['privacy'] = __( 'Personal Data', 'nobloat-user-foundry' );

		/* Build sub-tab navigation */
		$html = '<div class="nbuf-account-section">';

		/* Sub-tab links */
		$html         .= '<div class="nbuf-subtabs">';
		$subtab_count  = 0;
		foreach ( $subtabs as $key => $label ) {
			$is_first  = ( 0 === $subtab_count );
			$html     .= '<button type="button" class="nbuf-subtab-link' . ( $is_first ? ' active' : '' ) . '" data-subtab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
			++$subtab_count;
		}
		$html .= '</div>';

		/* Password sub-tab content */
		$html .= '<div class="nbuf-subtab-content active" data-subtab="password">';
		$html .= self::build_password_subtab_html( $user_id, $password_requirements );
		$html .= '</div>';

		/* 2FA Email sub-tab content */
		if ( isset( $subtabs['2fa-email'] ) ) {
			$html .= '<div class="nbuf-subtab-content" data-subtab="2fa-email">';
			$html .= self::build_email_2fa_subtab_html( $user_id, $has_email );
			$html .= '</div>';
		}

		/* Authenticator sub-tab content */
		if ( isset( $subtabs['authenticator'] ) ) {
			$html .= '<div class="nbuf-subtab-content" data-subtab="authenticator">';
			$html .= self::build_authenticator_subtab_html( $user_id, $has_totp );
			$html .= '</div>';
		}

		/* Backup Codes sub-tab content */
		if ( isset( $subtabs['backup-codes'] ) ) {
			$html .= '<div class="nbuf-subtab-content" data-subtab="backup-codes">';
			$html .= self::build_backup_codes_subtab_html( $user_id );
			$html .= '</div>';
		}

		/* App Passwords sub-tab content */
		if ( isset( $subtabs['app-passwords'] ) ) {
			$html .= '<div class="nbuf-subtab-content" data-subtab="app-passwords">';
			$html .= self::build_app_passwords_html( $user_id );
			$html .= '</div>';
		}

		/* Passkeys sub-tab content */
		if ( isset( $subtabs['passkeys'] ) ) {
			$html .= '<div class="nbuf-subtab-content" data-subtab="passkeys">';
			$html .= self::build_passkeys_subtab_html( $user_id );
			$html .= '</div>';
		}

		/* Privacy sub-tab content */
		$html .= '<div class="nbuf-subtab-content" data-subtab="privacy">';
		$html .= self::build_privacy_subtab_html( $user_id );
		$html .= '</div>';

		$html .= '</div>'; /* Close nbuf-account-section */

		return $html;
	}

	/**
	 * Build Password sub-tab HTML.
	 *
	 * @param  int    $user_id               User ID.
	 * @param  string $password_requirements Password requirements text.
	 * @return string HTML output.
	 */
	private static function build_password_subtab_html( $user_id, $password_requirements ) {
		ob_start();
		wp_nonce_field( 'nbuf_account_password', 'nbuf_password_nonce', false );
		$nonce_field = ob_get_clean();

		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Change Password', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Update your account password to maintain security. Choose a strong, unique password.', 'nobloat-user-foundry' ) . '</p>';

		$html .= '<div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 25px;">';
		$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '" class="nbuf-account-form">';
		$html .= $nonce_field;
		$html .= '<input type="hidden" name="nbuf_account_action" value="change_password">';
		$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
		$html .= '<input type="hidden" name="nbuf_active_subtab" value="password">';

		$html .= '<div class="nbuf-form-group" style="margin-bottom: 20px;">';
		$html .= '<label for="current_password" class="nbuf-form-label" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #495057;">' . esc_html__( 'Current Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="current_password" name="current_password" class="nbuf-form-input" required style="width: 100%; max-width: 400px; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 4px; background: #fff;">';
		$html .= '</div>';

		$html .= '<div class="nbuf-form-group" style="margin-bottom: 20px;">';
		$html .= '<label for="new_password" class="nbuf-form-label" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #495057;">' . esc_html__( 'New Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="new_password" name="new_password" class="nbuf-form-input" required style="width: 100%; max-width: 400px; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 4px; background: #fff;">';
		if ( ! empty( $password_requirements ) ) {
			$html .= '<div class="nbuf-form-help" style="margin-top: 8px; font-size: 14px; color: #6c757d;">' . esc_html( $password_requirements ) . '</div>';
		}
		$html .= '</div>';

		$html .= '<div class="nbuf-form-group" style="margin-bottom: 25px;">';
		$html .= '<label for="confirm_password" class="nbuf-form-label" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #495057;">' . esc_html__( 'Confirm New Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="confirm_password" name="confirm_password" class="nbuf-form-input" required style="width: 100%; max-width: 400px; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 4px; background: #fff;">';
		$html .= '</div>';

		$html .= '<button type="submit" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px;">' . esc_html__( 'Change Password', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</form>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build Email 2FA sub-tab HTML.
	 *
	 * @param  int  $user_id   User ID.
	 * @param  bool $has_email Whether user has email 2FA active.
	 * @return string HTML output.
	 */
	private static function build_email_2fa_subtab_html( $user_id, $has_email ) {
		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Email-Based Two-Factor Authentication', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Receive a verification code via email each time you log in.', 'nobloat-user-foundry' ) . '</p>';

		if ( $has_email ) {
			/* Active status - styled as success notice */
			$html .= '<div class="nbuf-method-status nbuf-status-active" style="background: #d4edda; border: 1px solid #c3e6cb; border-left: 4px solid #28a745; padding: 15px 18px; margin: 0 0 25px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #28a745; font-size: 20px; line-height: 1;">✓</span>';
			$html .= '<span style="color: #155724; font-size: 15px; font-weight: 500;">' . esc_html__( 'Email 2FA is currently active on your account.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '" style="margin-top: 25px;">';
			$html .= wp_nonce_field( 'nbuf_2fa_disable_email', 'nbuf_2fa_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_email">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="2fa-email">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary" style="padding: 10px 20px; font-size: 15px;">' . esc_html__( 'Disable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			/* Inactive status - styled as info notice */
			$html .= '<div class="nbuf-method-status nbuf-status-inactive" style="background: #f8f9fa; border: 1px solid #e0e0e0; border-left: 4px solid #6c757d; padding: 15px 18px; margin: 0 0 25px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #6c757d; font-size: 18px; line-height: 1;">&#9675;</span>';
			$html .= '<span style="color: #495057; font-size: 15px; font-weight: 500;">' . esc_html__( 'Email 2FA is not active.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '" style="margin-top: 25px;">';
			$html .= wp_nonce_field( 'nbuf_2fa_enable_email', 'nbuf_2fa_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="enable_email">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="2fa-email">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px;">' . esc_html__( 'Enable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Build Authenticator sub-tab HTML.
	 *
	 * @param  int  $user_id  User ID.
	 * @param  bool $has_totp Whether user has TOTP active.
	 * @return string HTML output.
	 */
	private static function build_authenticator_subtab_html( $user_id, $has_totp ) {
		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Authenticator App', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Use an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator to generate verification codes.', 'nobloat-user-foundry' ) . '</p>';

		if ( $has_totp ) {
			/* Active status - styled as success notice */
			$html .= '<div class="nbuf-method-status nbuf-status-active" style="background: #d4edda; border: 1px solid #c3e6cb; border-left: 4px solid #28a745; padding: 15px 18px; margin: 0 0 20px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #28a745; font-size: 20px; line-height: 1;">✓</span>';
			$html .= '<span style="color: #155724; font-size: 15px; font-weight: 500;">' . esc_html__( 'Authenticator app is currently active on your account.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';

			/* Disable form */
			$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '" style="margin-top: 25px;">';
			$html .= wp_nonce_field( 'nbuf_2fa_disable_totp', 'nbuf_2fa_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_totp">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="authenticator">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary" style="padding: 10px 20px; font-size: 15px;">' . esc_html__( 'Disable Authenticator', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			/* Inactive status - styled as info notice */
			$html .= '<div class="nbuf-method-status nbuf-status-inactive" style="background: #f8f9fa; border: 1px solid #dee2e6; border-left: 4px solid #6c757d; padding: 15px 18px; margin: 0 0 20px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #6c757d; font-size: 18px; line-height: 1;">○</span>';
			$html .= '<span style="color: #495057; font-size: 15px; font-weight: 500;">' . esc_html__( 'Authenticator app is not configured.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';

			/* Link to TOTP setup page - prefer Universal Router */
			$setup_url = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$setup_url = NBUF_Universal_Router::get_url( '2fa-setup' );
			} else {
				$setup_page_id = NBUF_Options::get( 'nbuf_page_totp_setup', 0 );
				$setup_url     = $setup_page_id ? get_permalink( $setup_page_id ) : '';
			}

			if ( $setup_url ) {
				$html .= '<div style="margin-top: 25px;">';
				$html .= '<a href="' . esc_url( $setup_url ) . '" class="nbuf-button nbuf-button-primary" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: 600;">' . esc_html__( 'Setup Authenticator', 'nobloat-user-foundry' ) . '</a>';
				$html .= '</div>';
			} else {
				$html .= '<p class="nbuf-setup-note" style="margin-top: 15px; padding: 12px 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; font-size: 14px;">' . esc_html__( 'Contact administrator to set up authenticator app.', 'nobloat-user-foundry' ) . '</p>';
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Build Backup Codes sub-tab HTML.
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output.
	 */
	private static function build_backup_codes_subtab_html( $user_id ) {
		$backup_codes    = NBUF_User_2FA_Data::get_backup_codes( $user_id );
		$used_indexes    = NBUF_User_2FA_Data::get_backup_codes_used( $user_id );
		$codes_remaining = is_array( $backup_codes ) ? count( $backup_codes ) - count( (array) $used_indexes ) : 0;

		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Backup Codes', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'One-time use codes for emergency access if you lose your authenticator device or cannot receive email codes.', 'nobloat-user-foundry' ) . '</p>';

		/* Status box */
		if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
			$html .= '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-left: 4px solid #28a745; padding: 15px 18px; margin: 0 0 25px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #155724; font-size: 20px; line-height: 1;">&#10003;</span>';
			$html .= '<span style="color: #155724; font-size: 16px; font-weight: 600;">' . sprintf(
				/* translators: %d: number of backup codes remaining */
				esc_html__( '%d backup codes remaining', 'nobloat-user-foundry' ),
				$codes_remaining
			) . '</span>';
			$html .= '</div>';
			$html .= '</div>';
		} else {
			$html .= '<div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-left: 4px solid #6c757d; padding: 15px 18px; margin: 0 0 25px 0; border-radius: 4px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 10px;">';
			$html .= '<span style="color: #6c757d; font-size: 18px; line-height: 1;">&#9675;</span>';
			$html .= '<span style="color: #495057; font-size: 15px;">' . esc_html__( 'No backup codes generated yet.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
		$html .= wp_nonce_field( 'nbuf_2fa_generate_backup', 'nbuf_2fa_nonce', true, false );
		$html .= '<input type="hidden" name="nbuf_2fa_action" value="generate_backup_codes">';
		$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
		$html .= '<input type="hidden" name="nbuf_active_subtab" value="backup-codes">';

		if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
			$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary" style="padding: 10px 20px; font-size: 15px;" onclick="return confirm(\'' . esc_js( __( 'This will replace your existing backup codes. Continue?', 'nobloat-user-foundry' ) ) . '\')">';
			$html .= esc_html__( 'Regenerate Codes', 'nobloat-user-foundry' );
		} else {
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px;">';
			$html .= esc_html__( 'Generate Backup Codes', 'nobloat-user-foundry' );
		}
		$html .= '</button></form>';

		$html .= '</div>';
		return $html;
	}

	/**
	 * Build Privacy sub-tab HTML.
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output.
	 */
	private static function build_privacy_subtab_html( $user_id ) {
		$html = '<div class="nbuf-security-subtab-content">';

		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Personal Data', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Manage your personal data and privacy settings.', 'nobloat-user-foundry' ) . '</p>';

		/* Data Export section */
		$html .= '<div class="nbuf-data-export-section" style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 25px; margin-bottom: 25px;">';
		$html .= '<h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Export Your Data', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Download all your personal data in JSON format, including profile information, account settings, and activity history for your account.', 'nobloat-user-foundry' ) . '</p>';

		/* Check if GDPR export class exists */
		if ( class_exists( 'NBUF_GDPR_Export' ) ) {
			$html .= '<form method="post" action="' . esc_url( get_permalink() ) . '">';
			$html .= wp_nonce_field( 'nbuf_export_data', 'nbuf_export_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_account_action" value="export_data">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px;">' . esc_html__( 'Download My Data', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 12px 15px; border-radius: 4px;">';
			$html .= '<span style="color: #856404; font-size: 14px;">' . esc_html__( 'Data export is not currently available.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build Application Passwords HTML section.
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output.
	 */
	private static function build_app_passwords_html( $user_id ) {
		/* Check if WordPress supports application passwords (WP 5.6+) */
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		/* Get existing application passwords */
		$app_passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );

		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Application Passwords', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Application passwords allow external apps to connect to your account via the REST API without using your main password.', 'nobloat-user-foundry' ) . '</p>';

		/* Warning about 2FA bypass - styled as warning notice */
		$html .= '<div class="nbuf-app-password-warning" style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 15px 18px; margin: 0 0 30px 0; border-radius: 4px;">';
		$html .= '<div style="display: flex; align-items: flex-start; gap: 10px;">';
		$html .= '<span style="color: #856404; font-size: 18px; line-height: 1; margin-top: 1px;">⚠</span>';
		$html .= '<div>';
		$html .= '<strong style="color: #856404; font-size: 15px; font-weight: 600;">' . esc_html__( 'Security Note:', 'nobloat-user-foundry' ) . '</strong> ';
		$html .= '<span style="color: #856404; font-size: 14px;">' . esc_html__( 'Application passwords bypass two-factor authentication. Only create passwords for apps you trust.', 'nobloat-user-foundry' ) . '</span>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		/* List existing passwords */
		if ( ! empty( $app_passwords ) ) {
			$html .= '<div class="nbuf-app-passwords-list" style="margin-bottom: 35px;">';
			$html .= '<h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Active Application Passwords', 'nobloat-user-foundry' ) . '</h4>';

			/* Styled table with border wrapper */
			$html .= '<div style="overflow-x: auto; border: 1px solid #e0e0e0; border-radius: 6px; background: #fff;">';
			$html .= '<table class="nbuf-app-passwords-table" style="width: 100%; border-collapse: collapse; margin: 0;">';
			$html .= '<thead>';
			$html .= '<tr style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Name', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Created', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Last Used', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: right; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Actions', 'nobloat-user-foundry' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $app_passwords as $password ) {
				$created   = isset( $password['created'] ) ? date_i18n( get_option( 'date_format' ), $password['created'] ) : '—';
				$last_used = isset( $password['last_used'] ) && $password['last_used'] ? date_i18n( get_option( 'date_format' ), $password['last_used'] ) : esc_html__( 'Never', 'nobloat-user-foundry' );
				$uuid      = isset( $password['uuid'] ) ? $password['uuid'] : '';

				$html .= '<tr style="border-bottom: 1px solid #f0f0f1; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor=\'#f8f9fa\'" onmouseout="this.style.backgroundColor=\'transparent\'">';
				$html .= '<td style="padding: 14px 16px;"><span style="font-size: 15px; font-weight: 500; color: #1a202c;">' . esc_html( $password['name'] ) . '</span></td>';
				$html .= '<td style="padding: 14px 16px; font-size: 14px; color: #6c757d;">' . esc_html( $created ) . '</td>';
				$html .= '<td style="padding: 14px 16px; font-size: 14px; color: #6c757d;">' . esc_html( $last_used ) . '</td>';
				$html .= '<td style="padding: 14px 16px; text-align: right;">';
				$html .= '<button type="button" class="nbuf-revoke-app-password nbuf-button nbuf-button-small nbuf-button-danger" data-uuid="' . esc_attr( $uuid ) . '" style="padding: 6px 12px; font-size: 13px; background: #dc3545; border-color: #dc3545; color: #fff; border-radius: 3px; cursor: pointer; border: 1px solid transparent; font-weight: 500; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor=\'#c82333\'" onmouseout="this.style.backgroundColor=\'#dc3545\'">';
				$html .= esc_html__( 'Revoke', 'nobloat-user-foundry' );
				$html .= '</button></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>'; /* Close table wrapper */
			$html .= '</div>'; /* Close nbuf-app-passwords-list */
		} else {
			/* Empty state */
			$html .= '<div class="nbuf-app-passwords-empty" style="padding: 25px 20px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; text-align: center; margin-bottom: 30px;">';
			$html .= '<p style="margin: 0; font-size: 15px; color: #6c757d; font-style: italic;">' . esc_html__( 'No application passwords created yet.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';
		}

		/* Create new password form */
		$html .= '<div class="nbuf-create-app-password" style="padding: 25px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; margin-top: 30px;">';
		$html .= '<h4 style="margin: 0 0 18px 0; font-size: 16px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Create New Application Password', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<div class="nbuf-app-password-form">';
		$html .= '<div class="nbuf-form-group" style="margin-bottom: 15px;">';
		$html .= '<label for="nbuf-app-password-name" class="nbuf-form-label" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #495057;">' . esc_html__( 'Application Name', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="text" id="nbuf-app-password-name" class="nbuf-form-input" placeholder="' . esc_attr__( 'e.g., My Mobile App', 'nobloat-user-foundry' ) . '" style="width: 100%; max-width: 400px; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 4px; background: #fff;">';
		$html .= '</div>';
		$html .= '<button type="button" id="nbuf-create-app-password" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px; font-weight: 600; margin-top: 5px;">';
		$html .= esc_html__( 'Create Password', 'nobloat-user-foundry' );
		$html .= '</button>';
		$html .= '</div>';
		$html .= '</div>';

		/* New password display area (hidden by default) - styled as success notice */
		$html .= '<div id="nbuf-new-app-password-display" style="display: none; margin-top: 25px; padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-left: 4px solid #28a745; border-radius: 4px;">';
		$html .= '<p style="margin: 0 0 15px 0; font-size: 15px;"><strong style="color: #155724;">' . esc_html__( 'New Application Password Created:', 'nobloat-user-foundry' ) . '</strong></p>';
		$html .= '<div style="display: flex; gap: 10px; align-items: stretch; margin-bottom: 15px;">';
		$html .= '<code id="nbuf-new-app-password-value" style="flex: 1; padding: 12px 14px; background: #fff; border: 1px solid #c3e6cb; font-size: 16px; letter-spacing: 1px; word-break: break-all; display: flex; align-items: center; border-radius: 3px; font-family: Consolas, Monaco, monospace; color: #155724;"></code>';
		$html .= '<button type="button" id="nbuf-copy-app-password" class="nbuf-button nbuf-button-secondary" style="white-space: nowrap; padding: 8px 16px; font-size: 14px; font-weight: 500;">' . esc_html__( 'Copy', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</div>';
		$html .= '<p style="margin: 0 0 15px 0; padding: 12px 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; font-size: 14px;">';
		$html .= '<strong>' . esc_html__( 'Important:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html__( 'Copy this password now. You won\'t be able to see it again!', 'nobloat-user-foundry' );
		$html .= '</p>';
		$html .= '<button type="button" id="nbuf-done-app-password" class="nbuf-button nbuf-button-primary" style="padding: 10px 20px; font-size: 15px; font-weight: 500;">' . esc_html__( 'Done', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</div>';

		/* Add nonce for AJAX */
		$html .= '<input type="hidden" id="nbuf-app-password-nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_app_passwords' ) ) . '">';

		$html .= '</div>'; /* Close nbuf-security-subtab-content */

		/* Inline JavaScript for app password management */
		$html .= self::get_app_passwords_js();

		return $html;
	}

	/**
	 * Get JavaScript for Application Passwords management.
	 *
	 * @return string JavaScript code wrapped in script tags.
	 */
	private static function get_app_passwords_js() {
		ob_start();
		?>
		<script>
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				var createBtn = document.getElementById('nbuf-create-app-password');
				var nameInput = document.getElementById('nbuf-app-password-name');
				var nonce = document.getElementById('nbuf-app-password-nonce');
				var displayArea = document.getElementById('nbuf-new-app-password-display');
				var passwordValue = document.getElementById('nbuf-new-app-password-value');
				var copyBtn = document.getElementById('nbuf-copy-app-password');
				var doneBtn = document.getElementById('nbuf-done-app-password');
				var createForm = document.querySelector('.nbuf-create-app-password');

				/* Helper to reload with security tab active */
				function reloadWithTab() {
					var url = new URL(window.location.href);
					url.searchParams.set('tab', 'security');
					window.location.href = url.toString();
				}

				if (!createBtn || !nameInput || !nonce) return;

				/* Copy button */
				if (copyBtn) {
					copyBtn.addEventListener('click', function() {
						var text = passwordValue.textContent;
						navigator.clipboard.writeText(text).then(function() {
							copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'nobloat-user-foundry' ) ); ?>';
							setTimeout(function() {
								copyBtn.textContent = '<?php echo esc_js( __( 'Copy', 'nobloat-user-foundry' ) ); ?>';
							}, 2000);
						}).catch(function() {
							/* Fallback for older browsers */
							var textarea = document.createElement('textarea');
							textarea.value = text;
							document.body.appendChild(textarea);
							textarea.select();
							document.execCommand('copy');
							document.body.removeChild(textarea);
							copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'nobloat-user-foundry' ) ); ?>';
							setTimeout(function() {
								copyBtn.textContent = '<?php echo esc_js( __( 'Copy', 'nobloat-user-foundry' ) ); ?>';
							}, 2000);
						});
					});
				}

				/* Done button - reload to show new password in list */
				if (doneBtn) {
					doneBtn.addEventListener('click', function() {
						reloadWithTab();
					});
				}

				/* Create new password */
				createBtn.addEventListener('click', function() {
					var name = nameInput.value.trim();
					if (!name) {
						alert('<?php echo esc_js( __( 'Please enter an application name.', 'nobloat-user-foundry' ) ); ?>');
						return;
					}

					createBtn.disabled = true;
					createBtn.textContent = '<?php echo esc_js( __( 'Creating...', 'nobloat-user-foundry' ) ); ?>';

					var formData = new FormData();
					formData.append('action', 'nbuf_create_app_password');
					formData.append('nonce', nonce.value);
					formData.append('name', name);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(function(response) { return response.json(); })
					.then(function(data) {
						if (data.success) {
							passwordValue.textContent = data.data.password;
							displayArea.style.display = 'block';
							createForm.style.display = 'none';
							nameInput.value = '';
						} else {
							alert(data.data.message || '<?php echo esc_js( __( 'Error creating password.', 'nobloat-user-foundry' ) ); ?>');
							createBtn.disabled = false;
							createBtn.textContent = '<?php echo esc_js( __( 'Create Password', 'nobloat-user-foundry' ) ); ?>';
						}
					})
					.catch(function() {
						alert('<?php echo esc_js( __( 'Error creating password.', 'nobloat-user-foundry' ) ); ?>');
						createBtn.disabled = false;
						createBtn.textContent = '<?php echo esc_js( __( 'Create Password', 'nobloat-user-foundry' ) ); ?>';
					});
				});

				/* Revoke password */
				document.querySelectorAll('.nbuf-revoke-app-password').forEach(function(btn) {
					btn.addEventListener('click', function() {
						if (!confirm('<?php echo esc_js( __( 'Are you sure you want to revoke this application password? Any app using it will no longer be able to connect.', 'nobloat-user-foundry' ) ); ?>')) {
							return;
						}

						var uuid = this.getAttribute('data-uuid');
						var row = this.closest('tr');
						this.disabled = true;
						this.textContent = '<?php echo esc_js( __( 'Revoking...', 'nobloat-user-foundry' ) ); ?>';

						var formData = new FormData();
						formData.append('action', 'nbuf_revoke_app_password');
						formData.append('nonce', nonce.value);
						formData.append('uuid', uuid);

						fetch(ajaxUrl, {
							method: 'POST',
							body: formData,
							credentials: 'same-origin'
						})
						.then(function(response) { return response.json(); })
						.then(function(data) {
							if (data.success) {
								row.remove();
								/* Check if table is empty */
								var tbody = document.querySelector('.nbuf-app-passwords-table tbody');
								if (tbody && tbody.children.length === 0) {
									reloadWithTab();
								}
							} else {
								alert(data.data.message || '<?php echo esc_js( __( 'Error revoking password.', 'nobloat-user-foundry' ) ); ?>');
								reloadWithTab();
							}
						})
						.catch(function() {
							alert('<?php echo esc_js( __( 'Error revoking password.', 'nobloat-user-foundry' ) ); ?>');
							reloadWithTab();
						});
					});
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
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

		/* Get redirect URL with fallback to account page */
		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$account_page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
			$redirect_url    = $account_page_id ? get_permalink( $account_page_id ) : home_url();
		}
		/* Append security tab parameter */
		$redirect_url = add_query_arg( 'tab', 'security', $redirect_url );

		/* Append subtab parameter if provided */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified per action below.
		$subtab = isset( $_POST['nbuf_active_subtab'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_active_subtab'] ) ) : '';
		if ( ! empty( $subtab ) ) {
			$redirect_url = add_query_arg( 'subtab', $subtab, $redirect_url );
		}

		/* Handle each 2FA action */
		switch ( $action ) {
			case 'enable_email':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_enable_email' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				NBUF_2FA::enable_for_user( $user_id, 'email' );
				NBUF_Shortcodes::set_flash_message( $user_id, __( 'Two-factor authentication enabled!', 'nobloat-user-foundry' ), 'success' );
				wp_safe_redirect( $redirect_url );
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
				NBUF_Shortcodes::set_flash_message( $user_id, __( 'Two-factor authentication disabled.', 'nobloat-user-foundry' ), 'success' );
				wp_safe_redirect( $redirect_url );
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
				NBUF_Shortcodes::set_flash_message( $user_id, __( 'Two-factor authentication disabled.', 'nobloat-user-foundry' ), 'success' );
				wp_safe_redirect( $redirect_url );
				exit;

			case 'generate_backup_codes':
				if ( ! isset( $_POST['nbuf_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_2fa_nonce'] ) ), 'nbuf_2fa_generate_backup' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'nobloat-user-foundry' ) );
				}
				/* Generate new backup codes */
				$codes = NBUF_2FA::generate_backup_codes( $user_id );
				/* Store in transient to display once (account page will retrieve and delete) */
				set_transient( 'nbuf_backup_codes_' . $user_id, $codes, 300 );
				wp_safe_redirect( $redirect_url );
				exit;
		}
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * Register AJAX hooks for application password management.
	 */
	public static function init() {
		/* Application password AJAX handlers (logged-in users only) */
		add_action( 'wp_ajax_nbuf_create_app_password', array( __CLASS__, 'ajax_create_app_password' ) );
		add_action( 'wp_ajax_nbuf_revoke_app_password', array( __CLASS__, 'ajax_revoke_app_password' ) );
	}

	/**
	 * AJAX handler for creating an application password.
	 */
	public static function ajax_create_app_password() {
		/* Verify nonce */
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nbuf_app_passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'nobloat-user-foundry' ) ) );
		}

		/* Check user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		/* Check application passwords are enabled */
		if ( ! NBUF_Options::get( 'nbuf_app_passwords_enabled', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords are not enabled.', 'nobloat-user-foundry' ) ) );
		}

		/* Check WordPress supports application passwords */
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords require WordPress 5.6 or later.', 'nobloat-user-foundry' ) ) );
		}

		/* Get and validate name */
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide an application name.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/* Create the application password */
		$result = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $name ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		/* Return the new password (shown only once) */
		wp_send_json_success(
			array(
				'password' => $result[0],
				'message'  => __( 'Application password created.', 'nobloat-user-foundry' ),
			)
		);
	}

	/**
	 * AJAX handler for revoking an application password.
	 */
	public static function ajax_revoke_app_password() {
		/* Verify nonce */
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nbuf_app_passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'nobloat-user-foundry' ) ) );
		}

		/* Check user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		/* Check application passwords are enabled */
		if ( ! NBUF_Options::get( 'nbuf_app_passwords_enabled', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords are not enabled.', 'nobloat-user-foundry' ) ) );
		}

		/* Check WordPress supports application passwords */
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords require WordPress 5.6 or later.', 'nobloat-user-foundry' ) ) );
		}

		/* Get and validate UUID */
		$uuid = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';
		if ( empty( $uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid password identifier.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		/* Verify this password belongs to the current user */
		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
		$found     = false;
		foreach ( $passwords as $password ) {
			if ( isset( $password['uuid'] ) && $password['uuid'] === $uuid ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'Password not found.', 'nobloat-user-foundry' ) ) );
		}

		/* Delete the application password */
		$result = WP_Application_Passwords::delete_application_password( $user_id, $uuid );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Application password revoked.', 'nobloat-user-foundry' ) ) );
	}

	/**
	 * Build Passkeys sub-tab HTML.
	 *
	 * Shows user's registered passkeys and allows registration of new ones.
	 *
	 * @param  int $user_id User ID.
	 * @return string HTML output.
	 */
	private static function build_passkeys_subtab_html( $user_id ) {
		$passkeys     = NBUF_User_Passkeys_Data::get_all( $user_id );
		$max_passkeys = (int) NBUF_Options::get( 'nbuf_passkeys_max_per_user', 10 );
		$can_add_more = count( $passkeys ) < $max_passkeys;

		$html  = '<div class="nbuf-security-subtab-content">';
		$html .= '<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Passkeys', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description" style="margin: 0 0 25px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">' . esc_html__( 'Passkeys allow you to sign in using your device\'s biometrics (fingerprint, face recognition) or a security key. They are more secure than passwords and easier to use.', 'nobloat-user-foundry' ) . '</p>';

		/* Registered passkeys list */
		if ( ! empty( $passkeys ) ) {
			$html .= '<div class="nbuf-passkeys-list" style="margin-bottom: 35px;">';
			$html .= '<h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Your Passkeys', 'nobloat-user-foundry' ) . '</h4>';

			/* Passkeys table with improved styling */
			$html .= '<div style="overflow-x: auto; border: 1px solid #e0e0e0; border-radius: 6px; background: #fff;">';
			$html .= '<table class="nbuf-passkeys-table" style="width: 100%; border-collapse: collapse; margin: 0;">';
			$html .= '<thead>';
			$html .= '<tr style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Device', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Added', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: left; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Last Used', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th style="padding: 14px 16px; text-align: right; font-size: 14px; font-weight: 600; color: #495057;">' . esc_html__( 'Actions', 'nobloat-user-foundry' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $passkeys as $passkey ) {
				$device_name = ! empty( $passkey->device_name ) ? $passkey->device_name : __( 'Unknown Device', 'nobloat-user-foundry' );
				$created_at  = ! empty( $passkey->created_at ) ? wp_date( get_option( 'date_format' ), strtotime( $passkey->created_at ) ) : '—';
				$last_used   = ! empty( $passkey->last_used ) ? wp_date( get_option( 'date_format' ), strtotime( $passkey->last_used ) ) : __( 'Never', 'nobloat-user-foundry' );

				$html .= '<tr data-passkey-id="' . esc_attr( $passkey->id ) . '" style="border-bottom: 1px solid #f0f0f1; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor=\'#f8f9fa\'" onmouseout="this.style.backgroundColor=\'transparent\'">';

				/* Device name column - removed icon, clean typography */
				$html .= '<td class="nbuf-passkey-name" style="padding: 14px 16px;">';
				$html .= '<span class="nbuf-passkey-device-name" style="font-size: 15px; font-weight: 500; color: #1a202c;">' . esc_html( $device_name ) . '</span>';
				$html .= '</td>';

				/* Created at column */
				$html .= '<td style="padding: 14px 16px; font-size: 14px; color: #6c757d;">' . esc_html( $created_at ) . '</td>';

				/* Last used column */
				$html .= '<td style="padding: 14px 16px; font-size: 14px; color: #6c757d;">' . esc_html( $last_used ) . '</td>';

				/* Actions column */
				$html .= '<td class="nbuf-passkey-actions" style="padding: 14px 16px; text-align: right; white-space: nowrap;">';
				$html .= '<button type="button" class="nbuf-button nbuf-button-small nbuf-passkey-rename" data-passkey-id="' . esc_attr( $passkey->id ) . '" style="padding: 6px 12px; font-size: 13px; margin-right: 6px; background: #6c757d; border-color: #6c757d; color: #fff; border-radius: 3px; cursor: pointer; border: 1px solid transparent; font-weight: 500; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor=\'#5a6268\'" onmouseout="this.style.backgroundColor=\'#6c757d\'">' . esc_html__( 'Rename', 'nobloat-user-foundry' ) . '</button>';
				$html .= '<button type="button" class="nbuf-button nbuf-button-small nbuf-button-danger nbuf-passkey-delete" data-passkey-id="' . esc_attr( $passkey->id ) . '" style="padding: 6px 12px; font-size: 13px; background: #dc3545; border-color: #dc3545; color: #fff; border-radius: 3px; cursor: pointer; border: 1px solid transparent; font-weight: 500; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor=\'#c82333\'" onmouseout="this.style.backgroundColor=\'#dc3545\'">' . esc_html__( 'Delete', 'nobloat-user-foundry' ) . '</button>';
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>'; /* Close table wrapper */
			$html .= '</div>'; /* Close nbuf-passkeys-list */
		} else {
			/* Empty state */
			$html .= '<div class="nbuf-passkeys-empty" style="padding: 25px 20px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; text-align: center; margin-bottom: 30px;">';
			$html .= '<p style="margin: 0; font-size: 15px; color: #6c757d; font-style: italic;">' . esc_html__( 'You haven\'t registered any passkeys yet.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';
		}

		/* Register new passkey */
		if ( $can_add_more ) {
			$html .= '<div class="nbuf-passkeys-register" style="padding: 25px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; margin-top: 30px;">';
			$html .= '<h4 style="margin: 0 0 18px 0; font-size: 16px; font-weight: 600; color: #1a202c;">' . esc_html__( 'Register New Passkey', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<div class="nbuf-passkey-register-form">';
			$html .= '<div class="nbuf-form-group" style="margin-bottom: 15px;">';
			$html .= '<label for="nbuf-passkey-name" class="nbuf-form-label" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #495057;">' . esc_html__( 'Device Name (optional)', 'nobloat-user-foundry' ) . '</label>';
			$html .= '<input type="text" id="nbuf-passkey-name" class="nbuf-form-input" placeholder="' . esc_attr__( 'e.g., My iPhone, Work Laptop', 'nobloat-user-foundry' ) . '" style="width: 100%; max-width: 400px; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 4px; background: #fff;">';
			$html .= '</div>';
			$html .= '<button type="button" id="nbuf-register-passkey" class="nbuf-button nbuf-button-primary" style="padding: 12px 24px; font-size: 15px; font-weight: 600; margin-top: 5px;">';
			$html .= esc_html__( 'Register Passkey', 'nobloat-user-foundry' );
			$html .= '</button>';
			$html .= '</div>';
			$html .= '<p class="nbuf-form-help" style="margin: 15px 0 0 0; font-size: 13px; color: #6c757d;">' . sprintf(
				/* translators: 1: maximum number of passkeys, 2: current number of passkeys */
				esc_html__( 'You can register up to %1$d passkeys. You have %2$d registered.', 'nobloat-user-foundry' ),
				$max_passkeys,
				count( $passkeys )
			) . '</p>';
			$html .= '</div>';
		} else {
			/* Limit reached notice */
			$html .= '<div class="nbuf-passkeys-limit" style="margin-top: 30px;">';
			$html .= '<p class="nbuf-notice nbuf-notice-warning" style="padding: 15px 18px; background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 4px; margin: 0; font-size: 14px; color: #856404;">' . sprintf(
				/* translators: %d: maximum number of passkeys */
				esc_html__( 'You have reached the maximum of %d passkeys. Delete an existing passkey to register a new one.', 'nobloat-user-foundry' ),
				$max_passkeys
			) . '</p>';
			$html .= '</div>';
		}

		/* Browser support notice */
		$html .= '<div class="nbuf-passkeys-browser-check" style="display:none; margin-top: 20px;">';
		$html .= '<p class="nbuf-notice nbuf-notice-error" style="padding: 15px 18px; background: #f8d7da; border: 1px solid #f5c6cb; border-left: 4px solid #dc3545; border-radius: 4px; margin: 0; font-size: 14px; color: #721c24;">' . esc_html__( 'Your browser does not support passkeys. Please use a modern browser like Chrome, Safari, Firefox, or Edge.', 'nobloat-user-foundry' ) . '</p>';
		$html .= '</div>';

		/* Hidden data for JavaScript */
		$html .= '<script type="text/javascript">';
		$html .= 'var nbufPasskeyData = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'nbuf_passkey_nonce' ),
			)
		) . ';';
		$html .= '</script>';

		$html .= '</div>';

		return $html;
	}
}

/* Initialize AJAX handlers */
NBUF_2FA_Account::init();
