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
	 * Get the form action URL for the account page.
	 *
	 * Uses Universal Router URL only if request is being served by it.
	 * Falls back to configured account page, then current permalink.
	 *
	 * @return string The account page URL.
	 */
	private static function get_form_action_url() {
		/* Use Universal Router URL only if this request is being served by it */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$url = NBUF_Universal_Router::get_url( 'account' );
			if ( $url ) {
				return $url;
			}
		}

		/* Fall back to configured account page */
		$account_page_id = NBUF_Options::get( 'nbuf_page_account', 0 );
		if ( $account_page_id ) {
			$url = get_permalink( $account_page_id );
			if ( $url ) {
				return $url;
			}
		}

		/* Last resort: current permalink */
		return get_permalink();
	}

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

		$html = '<div class="nbuf-security-subtab-content">';

		$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-password-form">';
		$html .= $nonce_field;
		$html .= '<input type="hidden" name="nbuf_account_action" value="change_password">';
		$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
		$html .= '<input type="hidden" name="nbuf_active_subtab" value="password">';

		$html .= '<div class="nbuf-form-group">';
		$html .= '<label for="current_password" class="nbuf-form-label">' . esc_html__( 'Current Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="current_password" name="current_password" class="nbuf-form-input" required autocomplete="current-password">';
		$html .= '</div>';

		$html .= '<div class="nbuf-form-group">';
		$html .= '<label for="new_password" class="nbuf-form-label">' . esc_html__( 'New Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="new_password" name="new_password" class="nbuf-form-input" required autocomplete="new-password">';
		if ( ! empty( $password_requirements ) ) {
			$html .= '<p class="nbuf-form-help">' . esc_html( $password_requirements ) . '</p>';
		}
		$html .= '</div>';

		$html .= '<div class="nbuf-form-group">';
		$html .= '<label for="confirm_password" class="nbuf-form-label">' . esc_html__( 'Confirm New Password', 'nobloat-user-foundry' ) . '</label>';
		$html .= '<input type="password" id="confirm_password" name="confirm_password" class="nbuf-form-input" required autocomplete="new-password">';
		$html .= '</div>';

		$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Update Password', 'nobloat-user-foundry' ) . '</button>';

		$html .= '</form>';
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
		$html = '<div class="nbuf-security-subtab-content">';

		if ( $has_email ) {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Email 2FA is enabled. A verification code will be sent to your email each time you log in.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_disable_email' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_email">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="2fa-email">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-danger">' . esc_html__( 'Disable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Enable email verification to add an extra layer of security. You\'ll receive a 6-digit code each time you log in.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_enable_email' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="enable_email">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="2fa-email">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Enable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
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
		$html = '<div class="nbuf-security-subtab-content">';

		if ( $has_totp ) {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Authenticator app is configured. Open your app and enter the 6-digit code when you log in.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_disable_totp' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_totp">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="authenticator">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-danger">' . esc_html__( 'Disable Authenticator', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Use an authenticator app (Google Authenticator, Authy, 1Password) to generate time-based codes for login.', 'nobloat-user-foundry' ) . '</p>';

			/* Link to TOTP setup page */
			$setup_url = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$setup_url = NBUF_Universal_Router::get_url( '2fa-setup' );
			} else {
				$setup_page_id = NBUF_Options::get( 'nbuf_page_totp_setup', 0 );
				$setup_url     = $setup_page_id ? get_permalink( $setup_page_id ) : '';
			}

			if ( $setup_url ) {
				$html .= '<a href="' . esc_url( $setup_url ) . '" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Set Up Authenticator', 'nobloat-user-foundry' ) . '</a>';
			} else {
				$html .= '<p class="nbuf-muted-text">' . esc_html__( 'Authenticator setup is not currently available.', 'nobloat-user-foundry' ) . '</p>';
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
		$total_codes     = is_array( $backup_codes ) ? count( $backup_codes ) : 0;

		$html = '<div class="nbuf-security-subtab-content">';

		if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
			/* Show remaining count */
			if ( $codes_remaining > 0 ) {
				$html .= '<p class="nbuf-simple-description">' . sprintf(
					/* translators: %1$d: remaining codes, %2$d: total codes */
					esc_html__( 'You have %1$d of %2$d backup codes remaining. Store them securely and use them if you lose access to your authenticator.', 'nobloat-user-foundry' ),
					$codes_remaining,
					$total_codes
				) . '</p>';
			} else {
				$html .= '<p class="nbuf-simple-description">' . esc_html__( 'All backup codes have been used. Generate new codes to maintain emergency access to your account.', 'nobloat-user-foundry' ) . '</p>';
			}

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_generate_backup' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="generate_backup_codes">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="backup-codes">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-secondary" onclick="return confirm(\'' . esc_js( __( 'This will invalidate existing codes. Continue?', 'nobloat-user-foundry' ) ) . '\')">';
			$html .= esc_html__( 'Generate New Codes', 'nobloat-user-foundry' );
			$html .= '</button>';
			$html .= '</form>';
		} else {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Backup codes let you log in if you lose access to your authenticator app or email. Each code can only be used once.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_generate_backup' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="generate_backup_codes">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="backup-codes">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Generate Backup Codes', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		}

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

		/* Check if GDPR export class exists */
		if ( class_exists( 'NBUF_GDPR_Export' ) ) {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Download a copy of your personal data in JSON format, including profile details, registration date, and login history.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= wp_nonce_field( 'nbuf_export_data', 'nbuf_export_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_account_action" value="export_data">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="privacy">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Download My Data', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Data export is not currently available. Contact the site administrator for assistance.', 'nobloat-user-foundry' ) . '</p>';
		}

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

		$html = '<div class="nbuf-security-subtab-content">';

		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Application passwords let third-party apps connect to your account without sharing your main password. They bypass 2FA, so only create them for trusted apps.', 'nobloat-user-foundry' ) . '</p>';

		/* List existing passwords */
		if ( ! empty( $app_passwords ) ) {
			$html .= '<div class="nbuf-table-wrapper nbuf-spaced">';
			$html .= '<table class="nbuf-data-table nbuf-app-passwords-table">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th>' . esc_html__( 'Name', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Created', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Last Used', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th class="nbuf-th-actions"></th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $app_passwords as $password ) {
				$created   = isset( $password['created'] ) ? date_i18n( get_option( 'date_format' ), $password['created'] ) : '—';
				$last_used = isset( $password['last_used'] ) && $password['last_used'] ? date_i18n( get_option( 'date_format' ), $password['last_used'] ) : esc_html__( 'Never', 'nobloat-user-foundry' );
				$uuid      = isset( $password['uuid'] ) ? $password['uuid'] : '';

				$html .= '<tr>';
				$html .= '<td><span class="nbuf-table-cell-name">' . esc_html( $password['name'] ) . '</span></td>';
				$html .= '<td class="nbuf-table-cell-meta">' . esc_html( $created ) . '</td>';
				$html .= '<td class="nbuf-table-cell-meta">' . esc_html( $last_used ) . '</td>';
				$html .= '<td class="nbuf-table-cell-actions">';
				$html .= '<button type="button" class="nbuf-revoke-app-password nbuf-button nbuf-button-small nbuf-button-danger" data-uuid="' . esc_attr( $uuid ) . '">';
				$html .= esc_html__( 'Revoke', 'nobloat-user-foundry' );
				$html .= '</button></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>';
		}

		/* Create new password form */
		$html .= '<div class="nbuf-create-app-password nbuf-inline-form">';
		$html .= '<input type="text" id="nbuf-app-password-name" class="nbuf-form-input" placeholder="' . esc_attr__( 'App name (e.g., My Phone)', 'nobloat-user-foundry' ) . '">';
		$html .= '<button type="button" id="nbuf-create-app-password" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Create Password', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</div>';

		/* New password display area (hidden by default) */
		$html .= '<div id="nbuf-new-app-password-display" class="nbuf-generated-password-card">';
		$html .= '<div class="nbuf-generated-password-header">' . esc_html__( 'Your new application password:', 'nobloat-user-foundry' ) . '</div>';
		$html .= '<div class="nbuf-generated-password-value" id="nbuf-new-app-password-value"></div>';
		$html .= '<div class="nbuf-generated-password-warning">' . esc_html__( 'Copy this password now. You won\'t be able to see it again.', 'nobloat-user-foundry' ) . '</div>';
		$html .= '<div class="nbuf-generated-password-actions">';
		$html .= '<button type="button" id="nbuf-copy-app-password" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Copy Password', 'nobloat-user-foundry' ) . '</button>';
		$html .= '<button type="button" id="nbuf-done-app-password" class="nbuf-button nbuf-button-secondary">' . esc_html__( 'Done', 'nobloat-user-foundry' ) . '</button>';
		$html .= '</div>';
		$html .= '</div>';

		/* Add nonce for AJAX */
		$html .= '<input type="hidden" id="nbuf-app-password-nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_app_passwords' ) ) . '">';

		$html .= '</div>';

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
								copyBtn.textContent = '<?php echo esc_js( __( 'Copy Password', 'nobloat-user-foundry' ) ); ?>';
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
								copyBtn.textContent = '<?php echo esc_js( __( 'Copy Password', 'nobloat-user-foundry' ) ); ?>';
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
		$passkeys      = NBUF_User_Passkeys_Data::get_all( $user_id );
		$max_passkeys  = (int) NBUF_Options::get( 'nbuf_passkeys_max_per_user', 10 );
		$passkey_count = count( $passkeys );
		$can_add_more  = $passkey_count < $max_passkeys;

		$html = '<div class="nbuf-security-subtab-content">';

		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Sign in using fingerprint, face recognition, or a hardware security key. Passkeys are more secure and faster than passwords.', 'nobloat-user-foundry' ) . '</p>';

		/* Browser support notice (hidden by JS if supported) */
		$html .= '<div class="nbuf-passkeys-browser-check nbuf-browser-check">';
		$html .= '<p class="nbuf-muted-text">' . esc_html__( 'Your browser does not support passkeys.', 'nobloat-user-foundry' ) . '</p>';
		$html .= '</div>';

		/* Registered passkeys list */
		if ( ! empty( $passkeys ) ) {
			$html .= '<div class="nbuf-table-wrapper nbuf-spaced">';
			$html .= '<table class="nbuf-data-table nbuf-passkeys-table">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th>' . esc_html__( 'Device', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Added', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Last Used', 'nobloat-user-foundry' ) . '</th>';
			$html .= '<th class="nbuf-th-actions"></th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $passkeys as $passkey ) {
				$device_name = ! empty( $passkey->device_name ) ? $passkey->device_name : __( 'Unknown Device', 'nobloat-user-foundry' );
				$created_at  = ! empty( $passkey->created_at ) ? wp_date( get_option( 'date_format' ), strtotime( $passkey->created_at ) ) : '—';
				$last_used   = ! empty( $passkey->last_used ) ? wp_date( get_option( 'date_format' ), strtotime( $passkey->last_used ) ) : __( 'Never', 'nobloat-user-foundry' );

				$html .= '<tr data-passkey-id="' . esc_attr( $passkey->id ) . '">';
				$html .= '<td><span class="nbuf-table-cell-name">' . esc_html( $device_name ) . '</span></td>';
				$html .= '<td class="nbuf-table-cell-meta">' . esc_html( $created_at ) . '</td>';
				$html .= '<td class="nbuf-table-cell-meta">' . esc_html( $last_used ) . '</td>';
				$html .= '<td class="nbuf-table-cell-actions">';
				$html .= '<button type="button" class="nbuf-button nbuf-button-small nbuf-button-secondary nbuf-passkey-rename" data-passkey-id="' . esc_attr( $passkey->id ) . '">' . esc_html__( 'Rename', 'nobloat-user-foundry' ) . '</button>';
				$html .= '<button type="button" class="nbuf-button nbuf-button-small nbuf-button-danger nbuf-passkey-delete" data-passkey-id="' . esc_attr( $passkey->id ) . '">' . esc_html__( 'Delete', 'nobloat-user-foundry' ) . '</button>';
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>';
		}

		/* Register new passkey */
		if ( $can_add_more ) {
			$html .= '<div class="nbuf-passkeys-register nbuf-inline-form">';
			$html .= '<input type="text" id="nbuf-passkey-name" class="nbuf-form-input" placeholder="' . esc_attr__( 'Device name (optional)', 'nobloat-user-foundry' ) . '">';
			$html .= '<button type="button" id="nbuf-register-passkey" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Register Passkey', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</div>';
		} else {
			$html .= '<p class="nbuf-muted-text nbuf-margin-top">' . sprintf(
				/* translators: %d: maximum number of passkeys */
				esc_html__( 'Maximum of %d passkeys reached. Delete one to add another.', 'nobloat-user-foundry' ),
				$max_passkeys
			) . '</p>';
		}

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
