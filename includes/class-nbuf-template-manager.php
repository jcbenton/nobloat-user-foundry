<?php
/**
 * NoBloat User Foundry - Template Manager
 *
 * Centralized template loading, caching, and sanitization.
 * Similar architecture to NBUF_CSS_Manager for consistency.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Template_Manager
 *
 * Manages email and form templates with caching and customization.
 */
class NBUF_Template_Manager {


	/**
	 * Runtime cache for loaded templates.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Template types and their database option keys.
	 *
	 * @var array
	 */
	private static $template_map = array(
		// Email templates.
		'email-verification-html'   => 'nbuf_email_template_html',
		'email-verification-text'   => 'nbuf_email_template_text',
		'welcome-email-html'        => 'nbuf_welcome_email_html',
		'welcome-email-text'        => 'nbuf_welcome_email_text',
		'expiration-warning-html'   => 'nbuf_expiration_warning_html',
		'expiration-warning-text'   => 'nbuf_expiration_warning_text',
		'2fa-email-code-html'       => 'nbuf_2fa_email_code_html',
		'2fa-email-code-text'       => 'nbuf_2fa_email_code_text',
		'password-reset-html'       => 'nbuf_password_reset_html',
		'password-reset-text'       => 'nbuf_password_reset_text',
		'admin-new-user-html'       => 'nbuf_admin_new_user_html',
		'admin-new-user-text'       => 'nbuf_admin_new_user_text',
		'security-alert-email-html' => 'nbuf_security_alert_email_html',

		// Form templates.
		'login-form'                => 'nbuf_login_form_template',
		'registration-form'         => 'nbuf_registration_form_template',
		'account-page'              => 'nbuf_account_page_template',
		'request-reset-form'        => 'nbuf_request_reset_form_template',
		'reset-form'                => 'nbuf_reset_form_template',

		// 2FA page templates.
		'2fa-verify'                => 'nbuf_2fa_verify_template',
		'2fa-setup-totp'            => 'nbuf_2fa_setup_totp_template',
		'2fa-backup-codes'          => 'nbuf_2fa_backup_codes_template',
	);

	/**
	 * File name mappings for default templates.
	 *
	 * @var array
	 */
	private static $file_map = array(
		'email-verification-html'   => 'email-verification.html',
		'email-verification-text'   => 'email-verification.txt',
		'welcome-email-html'        => 'welcome-email.html',
		'welcome-email-text'        => 'welcome-email.txt',
		'expiration-warning-html'   => 'expiration-warning.html',
		'expiration-warning-text'   => 'expiration-warning.txt',
		'2fa-email-code-html'       => '2fa-email-code.html',
		'2fa-email-code-text'       => '2fa-email-code.txt',
		'password-reset-html'       => 'password-reset.html',
		'password-reset-text'       => 'password-reset.txt',
		'admin-new-user-html'       => 'admin-new-user.html',
		'admin-new-user-text'       => 'admin-new-user.txt',
		'security-alert-email-html' => 'security-alert-email.html',
		'login-form'                => 'login-form.html',
		'registration-form'         => 'registration-form.html',
		'account-page'              => 'account-page.html',
		'request-reset-form'        => 'request-reset-form.html',
		'reset-form'                => 'reset-form.html',
		'2fa-verify'                => '2fa-verify.html',
		'2fa-setup-totp'            => '2fa-setup-totp.html',
		'2fa-backup-codes'          => '2fa-backup-codes.html',
	);

	/**
	 * LOAD TEMPLATE.
	 *
	 * Load template with runtime caching.
	 * Priority: runtime cache > custom table > file > fallback.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string Template content.
	 */
	public static function load_template( $template_name ) {
		// Check runtime cache first.
		if ( isset( self::$cache[ $template_name ] ) ) {
			return self::$cache[ $template_name ];
		}

		// Get option key.
		$option_key = self::get_option_key( $template_name );
		if ( ! $option_key ) {
			return self::load_fallback( $template_name );
		}

		// Try loading from custom table.
		$template = NBUF_Options::get( $option_key );

		// If empty, load from default file.
		if ( empty( $template ) ) {
			$template = self::load_default_file( $template_name );
		}

		// Cache and return.
		self::$cache[ $template_name ] = $template;
		return $template;
	}

	/**
	 * SAVE TEMPLATE.
	 *
	 * Save template to custom table (NBUF_Options).
	 * Automatically sanitizes based on template type.
	 *
	 * @param  string $template_name Template identifier.
	 * @param  string $content       Template content.
	 * @return bool True on success.
	 */
	public static function save_template( $template_name, $content ) {
		$option_key = self::get_option_key( $template_name );
		if ( ! $option_key ) {
			return false;
		}

		// Sanitize based on type.
		$sanitized = self::sanitize_template( $content, $template_name );

		// Save to custom table (autoload = false for templates).
		NBUF_Options::update( $option_key, $sanitized, false, 'templates' );

		// Update runtime cache.
		self::$cache[ $template_name ] = $sanitized;

		return true;
	}

	/**
	 * SANITIZE TEMPLATE.
	 *
	 * Sanitize template content based on type.
	 * HTML templates: allow forms + safe HTML
	 * Text templates: sanitize_textarea_field.
	 *
	 * @param  string $content       Template content.
	 * @param  string $template_name Template identifier.
	 * @return string Sanitized content.
	 */
	public static function sanitize_template( $content, $template_name ) {
		// Text templates - simple sanitization.
		if ( strpos( $template_name, '-text' ) !== false ) {
			return sanitize_textarea_field( $content );
		}

		// HTML templates - allow forms and safe HTML.
		$allowed_html = array(
			'form'     => array(
				'method' => true,
				'action' => true,
				'class'  => true,
				'id'     => true,
			),
			'input'    => array(
				'type'         => true,
				'name'         => true,
				'id'           => true,
				'class'        => true,
				'placeholder'  => true,
				'required'     => true,
				'value'        => true,
				'checked'      => true,
				'disabled'     => true,
				'readonly'     => true,
				'maxlength'    => true,
				'minlength'    => true,
				'pattern'      => true,
				'autocomplete' => true,
			),
			'button'   => array(
				'type'  => true,
				'class' => true,
				'id'    => true,
				'name'  => true,
				'value' => true,
			),
			'select'   => array(
				'name'     => true,
				'id'       => true,
				'class'    => true,
				'required' => true,
				'multiple' => true,
			),
			'option'   => array(
				'value'    => true,
				'selected' => true,
			),
			'textarea' => array(
				'name'        => true,
				'id'          => true,
				'class'       => true,
				'rows'        => true,
				'cols'        => true,
				'placeholder' => true,
				'required'    => true,
				'maxlength'   => true,
			),
			'label'    => array(
				'for'   => true,
				'class' => true,
			),
			'div'      => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'span'     => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'p'        => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'h1'       => array(
				'class' => true,
				'id'    => true,
			),
			'h2'       => array(
				'class' => true,
				'id'    => true,
			),
			'h3'       => array(
				'class' => true,
				'id'    => true,
			),
			'h4'       => array(
				'class' => true,
				'id'    => true,
			),
			'a'        => array(
				'href'   => true,
				'class'  => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'strong'   => array(),
			'em'       => array(),
			'b'        => array(),
			'i'        => array(),
			'u'        => array(),
			'br'       => array(),
			'hr'       => array(),
			'ul'       => array(
				'class' => true,
			),
			'ol'       => array(
				'class' => true,
			),
			'li'       => array(
				'class' => true,
			),
			'table'    => array(
				'class' => true,
			),
			'thead'    => array(),
			'tbody'    => array(),
			'tr'       => array(
				'class' => true,
			),
			'th'       => array(
				'class' => true,
			),
			'td'       => array(
				'class' => true,
			),
			'img'      => array(
				'src'    => true,
				'alt'    => true,
				'class'  => true,
				'width'  => true,
				'height' => true,
			),
		);

		return wp_kses( $content, $allowed_html );
	}

	/**
	 * LOAD DEFAULT FILE.
	 *
	 * Load template from /templates/ directory.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string Template content or empty string.
	 */
	public static function load_default_file( $template_name ) {
		$filename = self::get_filename( $template_name );
		if ( ! $filename ) {
			return '';
		}

		$file_path = NBUF_TEMPLATES_DIR . $filename;

		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return '';
	}

	/**
	 * GET OPTION KEY.
	 *
	 * Get database option key for a template name.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string|false Option key or false.
	 */
	private static function get_option_key( $template_name ) {
		return isset( self::$template_map[ $template_name ] ) ? self::$template_map[ $template_name ] : false;
	}

	/**
	 * GET FILENAME.
	 *
	 * Get default filename for a template name.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string|false Filename or false.
	 */
	private static function get_filename( $template_name ) {
		return isset( self::$file_map[ $template_name ] ) ? self::$file_map[ $template_name ] : false;
	}

	/**
	 * LOAD FALLBACK.
	 *
	 * Return minimal fallback content if template not found.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string Fallback content.
	 */
	private static function load_fallback( $template_name ) {
		if ( strpos( $template_name, 'email' ) !== false ) {
			return __( 'Email content unavailable.', 'nobloat-user-foundry' );
		}

		return '<p>' . __( 'Template not found.', 'nobloat-user-foundry' ) . '</p>';
	}

	/**
	 * CLEAR CACHE.
	 *
	 * Clear runtime template cache (useful for testing).
	 *
	 * @param string|null $template_name Optional template to clear, or null for all.
	 */
	public static function clear_cache( $template_name = null ) {
		if ( $template_name ) {
			unset( self::$cache[ $template_name ] );
		} else {
			self::$cache = array();
		}
	}

	/**
	==========================================================
	GET TEMPLATE LIST
	----------------------------------------------------------
	Get all available templates (for admin UI).
	==========================================================
	 */
	public static function get_template_list() {
		return array_keys( self::$template_map );
	}

	/**
	 * GET PLACEHOLDERS.
	 *
	 * Get available placeholders for each template type.
	 *
	 * @param  string $template_name Template identifier.
	 * @return array Array of placeholder descriptions.
	 */
	public static function get_placeholders( $template_name ) {
		$placeholders = array(
			// Email verification.
			'email-verification-html'   => '{site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}',
			'email-verification-text'   => '{site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}',

			// Welcome email.
			'welcome-email-html'        => '{site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}',
			'welcome-email-text'        => '{site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}',

			// Expiration warning.
			'expiration-warning-html'   => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}, {days_until_expiration}, {login_url}, {contact_url}',
			'expiration-warning-text'   => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}, {days_until_expiration}, {login_url}, {contact_url}',

			// 2FA email code.
			'2fa-email-code-html'       => '{site_name}, {display_name}, {code}, {user_email}',
			'2fa-email-code-text'       => '{site_name}, {display_name}, {code}, {user_email}',

			// Password reset.
			'password-reset-html'       => '{site_name}, {display_name}, {username}, {reset_link}, {site_url}',
			'password-reset-text'       => '{site_name}, {display_name}, {username}, {reset_link}, {site_url}',

			// Admin new user notification.
			'admin-new-user-html'       => '{site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}',
			'admin-new-user-text'       => '{site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}',

			// Security alert.
			'security-alert-email-html' => '{site_name}, {site_url}, {event_type}, {message}, {username}, {user_email}, {user_id}, {ip_address}, {timestamp}, {log_url}, {context}',

			// Login form.
			'login-form'                => '{action_url}, {nonce_field}, {redirect_to}, {reset_link}, {register_link}, {error_message}',

			// Registration form.
			'registration-form'         => '{action_url}, {nonce_field}, {registration_fields}, {error_message}, {login_link}',

			// Account page.
			'account-page'              => '{messages}, {status_badges}, {username}, {email}, {display_name}, {registered_date}, {expiration_info}, {action_url}, {nonce_field}, {nonce_field_password}, {profile_fields}, {logout_url}',

			// Reset forms.
			'request-reset-form'        => '{action_url}, {nonce_field}, {error_message}, {success_message}, {login_url}, {register_link}',
			'reset-form'                => '{action_url}, {nonce_field}, {error_message}, {password_requirements}, {login_url}',

			// 2FA pages.
			'2fa-verify'                => '{action_url}, {nonce_field}, {error_message}, {method}, {resend_link}',
			'2fa-setup-totp'            => '{qr_code}, {secret_key}, {action_url}, {nonce_field}, {error_message}',
			'2fa-backup-codes'          => '{backup_codes}, {action_url}, {nonce_field}, {error_message}',
		);

		return isset( $placeholders[ $template_name ] ) ? $placeholders[ $template_name ] : '';
	}
}
