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
		$html        .= '<div class="nbuf-subtabs">';
		$subtab_count = 0;
		foreach ( $subtabs as $key => $label ) {
			$is_first = ( 0 === $subtab_count );
			$html    .= '<button type="button" class="nbuf-subtab-link' . ( $is_first ? ' active' : '' ) . '" data-subtab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
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
		$user = get_userdata( $user_id );
		$html = '<div class="nbuf-security-subtab-content">';

		/* Status box */
		if ( $has_email ) {
			$html .= '<div class="nbuf-method-status nbuf-status-active">';
			$html .= '<div class="nbuf-status-content">';
			$html .= '<span class="nbuf-status-icon">&#10003;</span>';
			$html .= '<div class="nbuf-status-text">';
			$html .= '<strong>' . esc_html__( 'Email 2FA is Enabled', 'nobloat-user-foundry' ) . '</strong>';
			$html .= '<span>' . esc_html__( 'A verification code will be sent to your email each time you log in.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div></div></div>';
		} else {
			$html .= '<div class="nbuf-method-status nbuf-status-inactive">';
			$html .= '<div class="nbuf-status-content">';
			$html .= '<span class="nbuf-status-icon">&#9675;</span>';
			$html .= '<div class="nbuf-status-text">';
			$html .= '<strong>' . esc_html__( 'Email 2FA is Not Enabled', 'nobloat-user-foundry' ) . '</strong>';
			$html .= '<span>' . esc_html__( 'Enable it below to add an extra layer of security to your account.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div></div></div>';
		}

		$html .= '<h4>' . esc_html__( 'What is Email 2FA?', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Email two-factor authentication adds an extra security step when you log in. After entering your password, you\'ll receive a 6-digit verification code at your email address that you must enter to complete the login.', 'nobloat-user-foundry' ) . '</p>';

		$html .= '<h4>' . esc_html__( 'How It Works', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<ul class="nbuf-benefits-list">';
		$html .= '<li>' . esc_html__( 'Enter your username and password as usual', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Check your email for a 6-digit verification code', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Enter the code to complete your login', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Codes expire after a few minutes for security', 'nobloat-user-foundry' ) . '</li>';
		$html .= '</ul>';

		$html .= '<h4>' . esc_html__( 'Benefits', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<ul class="nbuf-benefits-list">';
		$html .= '<li>' . esc_html__( 'No app installation required—works with any email', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Protects your account even if your password is compromised', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Easy to use on any device with email access', 'nobloat-user-foundry' ) . '</li>';
		$html .= '</ul>';

		if ( $user ) {
			$html .= '<div class="nbuf-info-box nbuf-info-box-muted">';
			$html .= '<p><strong>' . esc_html__( 'Your email:', 'nobloat-user-foundry' ) . '</strong> ';
			$html .= esc_html( $user->user_email ) . '<br>';
			$html .= esc_html__( 'Verification codes will be sent to this address. Make sure you have access to it before enabling.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';
		}

		$html .= '<div class="nbuf-section-spacing-large"></div>';

		if ( $has_email ) {
			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_disable_email' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_email">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="2fa-email">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-danger" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to disable Email 2FA? This will reduce your account security.', 'nobloat-user-foundry' ) ) . '\')">' . esc_html__( 'Disable Email 2FA', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
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
			/* Status box for enabled state */
			$html .= '<div class="nbuf-method-status nbuf-status-active">';
			$html .= '<div class="nbuf-status-content">';
			$html .= '<span class="nbuf-status-icon">&#10003;</span>';
			$html .= '<div class="nbuf-status-text">';
			$html .= '<strong>' . esc_html__( 'Authenticator App Enabled', 'nobloat-user-foundry' ) . '</strong>';
			$html .= '<span>' . esc_html__( 'Your account is protected with two-factor authentication.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div></div></div>';

			$html .= '<h4>' . esc_html__( 'How It Works', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'When you log in, open your authenticator app and enter the 6-digit code shown. Codes refresh every 30 seconds, so make sure to enter the current one.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<div class="nbuf-info-box nbuf-info-box-muted">';
			$html .= '<p><strong>' . esc_html__( 'Lost your device?', 'nobloat-user-foundry' ) . '</strong> ';
			$html .= esc_html__( 'Use a backup code to log in, then set up a new authenticator. Keep your backup codes in a safe place.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';

			$html .= '<h4>' . esc_html__( 'Disable Authenticator', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Disabling the authenticator will remove this layer of security from your account. You can set it up again anytime.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= '<input type="hidden" name="nbuf_2fa_nonce" value="' . esc_attr( wp_create_nonce( 'nbuf_2fa_disable_totp' ) ) . '">';
			$html .= '<input type="hidden" name="nbuf_2fa_action" value="disable_totp">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="authenticator">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-danger">' . esc_html__( 'Disable Authenticator', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<h4>' . esc_html__( 'What is an Authenticator App?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'An authenticator app generates time-based security codes on your phone or device. When you log in, you\'ll enter both your password and a 6-digit code from the app. This means even if someone learns your password, they can\'t access your account without your device.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<h4>' . esc_html__( 'Recommended Apps', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Download any of these free authenticator apps to get started:', 'nobloat-user-foundry' ) . '</p>';
			$html .= '<ul class="nbuf-app-list">';
			$html .= '<li><strong>Google Authenticator</strong> &mdash; ' . esc_html__( 'Simple and reliable. Available for iOS and Android.', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>Microsoft Authenticator</strong> &mdash; ' . esc_html__( 'Includes cloud backup. Available for iOS and Android.', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>Authy</strong> &mdash; ' . esc_html__( 'Syncs across multiple devices. Available for iOS, Android, and desktop.', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>1Password</strong> &mdash; ' . esc_html__( 'Built into the password manager. Requires subscription.', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			$html .= '<h4>' . esc_html__( 'Why Use It?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-benefits-list">';
			$html .= '<li>' . esc_html__( 'Stronger protection than password alone', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Works offline &mdash; no internet or cell service needed', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Codes change every 30 seconds, so they can\'t be reused', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Free to use with any compatible app', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			/* Link to TOTP setup page */
			$setup_url = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$setup_url = NBUF_Universal_Router::get_url( '2fa-setup' );
			} else {
				$setup_page_id = NBUF_Options::get( 'nbuf_page_totp_setup', 0 );
				$setup_url     = $setup_page_id ? get_permalink( $setup_page_id ) : '';
			}

			if ( $setup_url ) {
				$html .= '<div class="nbuf-section-spacing-large"></div>';
				$html .= '<button type="button" class="nbuf-button nbuf-button-primary" onclick="window.location.href=\'' . esc_url( $setup_url ) . '\'">' . esc_html__( 'Set Up Authenticator', 'nobloat-user-foundry' ) . '</button>';
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
			/* Status box for codes remaining */
			if ( $codes_remaining > 0 ) {
				$html .= '<div class="nbuf-method-status nbuf-status-active">';
				$html .= '<div class="nbuf-status-content">';
				$html .= '<span class="nbuf-status-icon">&#10003;</span>';
				$html .= '<div class="nbuf-status-text">';
				$html .= '<strong>' . sprintf(
					/* translators: %1$d: remaining codes, %2$d: total codes */
					esc_html__( '%1$d of %2$d Backup Codes Remaining', 'nobloat-user-foundry' ),
					$codes_remaining,
					$total_codes
				) . '</strong>';
				$html .= '<span>' . esc_html__( 'Store these codes in a safe place. You\'ll need them if you lose access to your authenticator.', 'nobloat-user-foundry' ) . '</span>';
				$html .= '</div></div></div>';
			} else {
				$html .= '<div class="nbuf-method-status nbuf-status-warning">';
				$html .= '<div class="nbuf-status-content">';
				$html .= '<span class="nbuf-status-icon">&#9888;</span>';
				$html .= '<div class="nbuf-status-text">';
				$html .= '<strong>' . esc_html__( 'All Backup Codes Used', 'nobloat-user-foundry' ) . '</strong>';
				$html .= '<span>' . esc_html__( 'Generate new codes now to maintain emergency access to your account.', 'nobloat-user-foundry' ) . '</span>';
				$html .= '</div></div></div>';
			}

			$html .= '<h4>' . esc_html__( 'How Backup Codes Work', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Backup codes are one-time passwords you can use instead of your authenticator app. Each code works only once, then it\'s marked as used. When you run low on codes, generate a new set.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<h4>' . esc_html__( 'Storage Tips', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-benefits-list">';
			$html .= '<li>' . esc_html__( 'Save them in a password manager', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Print and store in a secure location', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Keep them separate from your devices', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Never share them with anyone', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			$html .= '<div class="nbuf-info-box nbuf-info-box-muted">';
			$html .= '<p><strong>' . esc_html__( 'Need new codes?', 'nobloat-user-foundry' ) . '</strong> ';
			$html .= esc_html__( 'Generating new codes will invalidate all existing ones. Make sure to save the new codes before leaving the page.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';

			$html .= '<div class="nbuf-section-spacing-large"></div>';
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
			$html .= '<h4>' . esc_html__( 'What Are Backup Codes?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Backup codes are emergency passwords you can use to log in when you don\'t have access to your authenticator app or email. Each code is a one-time use password that bypasses two-factor authentication.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<h4>' . esc_html__( 'When You\'ll Need Them', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-benefits-list">';
			$html .= '<li>' . esc_html__( 'Lost or broken phone with authenticator app', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Traveling without access to your usual devices', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Email temporarily unavailable for verification', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Switching to a new phone before transferring your authenticator', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			$html .= '<h4>' . esc_html__( 'How It Works', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'When you generate backup codes, you\'ll receive a set of unique codes. Save these in a secure place. At the login screen, use any unused code instead of your regular verification method.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<div class="nbuf-section-spacing-large"></div>';
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

		$html .= '<h4>' . esc_html__( 'Your Privacy Rights', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'You have the right to know what personal data we store about you and to receive a copy of that data. This includes information you\'ve provided and data generated through your use of the site.', 'nobloat-user-foundry' ) . '</p>';

		/* Check if GDPR export class exists */
		if ( class_exists( 'NBUF_GDPR_Export' ) ) {
			$html .= '<h4>' . esc_html__( 'Download Your Data', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Export a complete copy of your personal data in JSON format. This file can be opened in any text editor or imported into other services.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<h4>' . esc_html__( 'What\'s Included', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-benefits-list">';
			$html .= '<li>' . esc_html__( 'Profile information (name, email, bio, etc.)', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Account details (registration date, role)', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Login history and security events', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li>' . esc_html__( 'Custom profile fields and preferences', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			$html .= '<div class="nbuf-info-box nbuf-info-box-muted">';
			$html .= '<p><strong>' . esc_html__( 'Processing time:', 'nobloat-user-foundry' ) . '</strong> ';
			$html .= esc_html__( 'Your download will start immediately. For large accounts, this may take a few moments.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';

			$html .= '<div class="nbuf-section-spacing-large"></div>';
			$html .= '<form method="post" action="' . esc_url( self::get_form_action_url() ) . '" class="nbuf-simple-form">';
			$html .= wp_nonce_field( 'nbuf_export_data', 'nbuf_export_nonce', true, false );
			$html .= '<input type="hidden" name="nbuf_account_action" value="export_data">';
			$html .= '<input type="hidden" name="nbuf_active_tab" value="security">';
			$html .= '<input type="hidden" name="nbuf_active_subtab" value="privacy">';
			$html .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Download My Data', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</form>';

			$html .= '<h4 class="nbuf-margin-top-large">' . esc_html__( 'Need to Delete Your Account?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'If you wish to delete your account and all associated data, please contact the site administrator. Account deletion is permanent and cannot be undone.', 'nobloat-user-foundry' ) . '</p>';
		} else {
			$html .= '<div class="nbuf-info-box nbuf-info-box-muted">';
			$html .= '<p>' . esc_html__( 'Data export is not currently available. Please contact the site administrator to request a copy of your personal data or for any privacy-related inquiries.', 'nobloat-user-foundry' ) . '</p>';
			$html .= '</div>';
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
		$app_passwords  = WP_Application_Passwords::get_user_application_passwords( $user_id );
		$password_count = count( $app_passwords );

		$html = '<div class="nbuf-security-subtab-content">';

		/* Status box showing current app passwords count */
		if ( $password_count > 0 ) {
			$html .= '<div class="nbuf-method-status nbuf-status-active">';
			$html .= '<div class="nbuf-status-content">';
			$html .= '<span class="nbuf-status-icon">&#128274;</span>';
			$html .= '<div class="nbuf-status-text">';
			$html .= '<strong>' . sprintf(
				/* translators: %d: number of active app passwords */
				esc_html( _n( '%d Active App Password', '%d Active App Passwords', $password_count, 'nobloat-user-foundry' ) ),
				$password_count
			) . '</strong>';
			$html .= '<span>' . esc_html__( 'Review periodically and revoke any you no longer use.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div></div></div>';
		}

		$html .= '<h4>' . esc_html__( 'What Are App Passwords?', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Application passwords are unique passwords that let third-party apps access your account without sharing your main password. Each app password is specific to one application and can be revoked independently.', 'nobloat-user-foundry' ) . '</p>';

		$html .= '<h4>' . esc_html__( 'Common Uses', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<ul class="nbuf-benefits-list">';
		$html .= '<li>' . esc_html__( 'Mobile apps that connect to your site', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Desktop publishing tools (e.g., blog editors)', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Automation services and integrations', 'nobloat-user-foundry' ) . '</li>';
		$html .= '<li>' . esc_html__( 'REST API access for development', 'nobloat-user-foundry' ) . '</li>';
		$html .= '</ul>';

		$html .= '<div class="nbuf-info-box">';
		$html .= '<p><strong>' . esc_html__( 'Security Note:', 'nobloat-user-foundry' ) . '</strong> ';
		$html .= esc_html__( 'App passwords bypass two-factor authentication. Only create them for apps you trust, and revoke them immediately if a device is lost or compromised.', 'nobloat-user-foundry' ) . '</p>';
		$html .= '</div>';

		/* List existing passwords */
		if ( ! empty( $app_passwords ) ) {
			$html .= '<h4>' . esc_html__( 'Your App Passwords', 'nobloat-user-foundry' ) . '</h4>';
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
		$html .= '<h4>' . esc_html__( 'Create New App Password', 'nobloat-user-foundry' ) . '</h4>';
		$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Enter a name to identify where you\'ll use this password (e.g., "iPhone WordPress App" or "Home Desktop").', 'nobloat-user-foundry' ) . '</p>';
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

				/* Helper to reload with app-passwords subtab active */
				function reloadWithTab() {
					var url = new URL(window.location.href);
					url.searchParams.set('tab', 'security');
					url.searchParams.set('subtab', 'app-passwords');
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
	 * Build redirect URL for 2FA actions.
	 *
	 * Properly handles both Universal Router virtual pages and regular pages.
	 *
	 * @return string Redirect URL with tab and subtab parameters.
	 */
	private static function build_action_redirect_url() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Tab/subtab values only used for redirect URL.
		$subtab = isset( $_POST['nbuf_active_subtab'] ) ? sanitize_key( wp_unslash( $_POST['nbuf_active_subtab'] ) ) : 'backup-codes';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$args = array();

		/* For Universal Router virtual pages, use path-based URLs */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			$url = NBUF_Universal_Router::get_url( 'account', 'security' );
			if ( $subtab ) {
				$args['subtab'] = $subtab;
			}
		} else {
			/* For regular pages, try form action URL first, then referer, then account page */
			$url = self::get_form_action_url();

			/* Add tab and subtab as query params */
			$args['tab'] = 'security';
			if ( $subtab ) {
				$args['subtab'] = $subtab;
			}
		}

		return add_query_arg( $args, $url );
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

		/* Build redirect URL properly handling Universal Router virtual pages */
		$redirect_url = self::build_action_redirect_url();

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

		/* Show different content based on whether user has passkeys */
		if ( ! empty( $passkeys ) ) {
			$html .= '<div class="nbuf-method-status nbuf-status-active">';
			$html .= '<div class="nbuf-status-content">';
			$html .= '<span class="nbuf-status-icon">&#10003;</span>';
			$html .= '<div class="nbuf-status-text">';
			$html .= '<strong>' . sprintf(
				/* translators: %d: number of registered passkeys */
				esc_html( _n( '%d Passkey Registered', '%d Passkeys Registered', $passkey_count, 'nobloat-user-foundry' ) ),
				$passkey_count
			) . '</strong>';
			$html .= '<span>' . esc_html__( 'You can sign in quickly using biometrics or a security key.', 'nobloat-user-foundry' ) . '</span>';
			$html .= '</div></div></div>';

			$html .= '<h4>' . esc_html__( 'How to Sign In', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'On the login page, click "Sign in with Passkey" and follow your device\'s prompts. You\'ll use your fingerprint, face, or security key instead of typing a password.', 'nobloat-user-foundry' ) . '</p>';
		} else {
			$html .= '<h4>' . esc_html__( 'What Are Passkeys?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<p class="nbuf-simple-description">' . esc_html__( 'Passkeys replace passwords with biometric authentication (fingerprint or face recognition) or hardware security keys. They\'re built into your devices and work across platforms.', 'nobloat-user-foundry' ) . '</p>';

			$html .= '<h4>' . esc_html__( 'Why Use Passkeys?', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-benefits-list">';
			$html .= '<li><strong>' . esc_html__( 'More secure', 'nobloat-user-foundry' ) . '</strong> &mdash; ' . esc_html__( 'Can\'t be phished, guessed, or stolen in data breaches', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>' . esc_html__( 'Faster login', 'nobloat-user-foundry' ) . '</strong> &mdash; ' . esc_html__( 'Touch your fingerprint sensor or glance at your camera', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>' . esc_html__( 'No memorization', 'nobloat-user-foundry' ) . '</strong> &mdash; ' . esc_html__( 'Your device handles everything securely', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>' . esc_html__( 'Works everywhere', 'nobloat-user-foundry' ) . '</strong> &mdash; ' . esc_html__( 'Syncs across your phones, tablets, and computers', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';

			$html .= '<h4>' . esc_html__( 'Supported Devices', 'nobloat-user-foundry' ) . '</h4>';
			$html .= '<ul class="nbuf-app-list">';
			$html .= '<li><strong>iPhone/iPad</strong> &mdash; ' . esc_html__( 'iOS 16+ with Face ID or Touch ID', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>Mac</strong> &mdash; ' . esc_html__( 'macOS Ventura+ with Touch ID or iPhone nearby', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>Android</strong> &mdash; ' . esc_html__( 'Android 9+ with fingerprint or screen lock', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>Windows</strong> &mdash; ' . esc_html__( 'Windows 10/11 with Windows Hello', 'nobloat-user-foundry' ) . '</li>';
			$html .= '<li><strong>' . esc_html__( 'Security Keys', 'nobloat-user-foundry' ) . '</strong> &mdash; ' . esc_html__( 'YubiKey, Titan, and other FIDO2 keys', 'nobloat-user-foundry' ) . '</li>';
			$html .= '</ul>';
		}

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
