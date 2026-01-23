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
	 * @var array<string, string>
	 */
	private static $cache = array();

	/**
	 * Template types and their database option keys.
	 *
	 * @var array<string, string>
	 */
	private static $template_map = array(
		// Email templates - option names must match UI form field names.
		'html'                        => 'nbuf_email_template_html',
		'text'                        => 'nbuf_email_template_text',
		'email-verification-html'     => 'nbuf_email_template_html',
		'email-verification-text'     => 'nbuf_email_template_text',
		'welcome-html'                => 'nbuf_welcome_email_html',
		'welcome-text'                => 'nbuf_welcome_email_text',
		'welcome-email-html'          => 'nbuf_welcome_email_html',
		'welcome-email-text'          => 'nbuf_welcome_email_text',
		'expiration-warning-html'     => 'nbuf_expiration_warning_email_html',
		'expiration-warning-text'     => 'nbuf_expiration_warning_email_text',
		'expiration-notice-html'      => 'nbuf_expiration_notice_email_html',
		'expiration-notice-text'      => 'nbuf_expiration_notice_email_text',
		'2fa-html'                    => 'nbuf_2fa_email_html',
		'2fa-text'                    => 'nbuf_2fa_email_text',
		'2fa-email-code-html'         => 'nbuf_2fa_email_html',
		'2fa-email-code-text'         => 'nbuf_2fa_email_text',
		'password-reset-html'         => 'nbuf_password_reset_email_html',
		'password-reset-text'         => 'nbuf_password_reset_email_text',
		'admin-new-user-html'         => 'nbuf_admin_new_user_html',
		'admin-new-user-text'         => 'nbuf_admin_new_user_text',
		'security-alert-email-html'   => 'nbuf_security_alert_email_html',

		// Form templates.
		'login-form'                  => 'nbuf_login_form_template',
		'registration-form'           => 'nbuf_registration_form_template',
		'account-page'                => 'nbuf_account_page_template',
		'request-reset-form'          => 'nbuf_request_reset_form_template',
		'reset-form'                  => 'nbuf_reset_form_template',

		// 2FA page templates.
		'2fa-verify'                  => 'nbuf_2fa_verify_template',
		'2fa-setup-totp'              => 'nbuf_2fa_setup_totp_template',
		'2fa-backup-codes'            => 'nbuf_2fa_backup_codes_template',
		'2fa-backup-verify'           => 'nbuf_2fa_backup_verify_template',

		// Policy templates.
		'policy-privacy-html'         => 'nbuf_policy_privacy_html',
		'policy-terms-html'           => 'nbuf_policy_terms_html',

		// Page templates.
		'public-profile-html'         => 'nbuf_public_profile_template',
		'member-directory-html'       => 'nbuf_member_directory_template',
		'member-directory-list-html'  => 'nbuf_member_directory_list_template',
		'account-data-export-html'    => 'nbuf_account_data_export_template',
		'version-history-viewer-html' => 'nbuf_version_history_viewer_template',
	);

	/**
	 * File name mappings for default templates.
	 *
	 * @var array<string, string>
	 */
	private static $file_map = array(
		'email-verification-html'     => 'email-verification.html',
		'email-verification-text'     => 'email-verification.txt',
		'welcome-email-html'          => 'welcome-email.html',
		'welcome-email-text'          => 'welcome-email.txt',
		'expiration-warning-html'     => 'expiration-warning.html',
		'expiration-warning-text'     => 'expiration-warning.txt',
		'expiration-notice-html'      => 'expiration-notice.html',
		'expiration-notice-text'      => 'expiration-notice.txt',
		'2fa-email-code-html'         => '2fa-email-code.html',
		'2fa-email-code-text'         => '2fa-email-code.txt',
		'password-reset-html'         => 'password-reset.html',
		'password-reset-text'         => 'password-reset.txt',
		'admin-new-user-html'         => 'admin-new-user.html',
		'admin-new-user-text'         => 'admin-new-user.txt',
		'security-alert-email-html'   => 'security-alert-email.html',
		'login-form'                  => 'login-form.html',
		'registration-form'           => 'registration-form.html',
		'account-page'                => 'account-page.html',
		'request-reset-form'          => 'request-reset-form.html',
		'reset-form'                  => 'reset-form.html',
		'2fa-verify'                  => '2fa-verify.html',
		'2fa-setup-totp'              => '2fa-setup-totp.html',
		'2fa-backup-codes'            => '2fa-backup-codes.html',
		'2fa-backup-verify'           => '2fa-backup-verify.html',
		'policy-privacy-html'         => 'policy-privacy.html',
		'policy-terms-html'           => 'policy-terms.html',
		'public-profile-html'         => 'public-profile.html',
		'member-directory-html'       => 'member-directory.html',
		'member-directory-list-html'  => 'member-directory-list.html',
		'account-data-export-html'    => 'account-data-export.html',
		'version-history-viewer-html' => 'version-history-viewer.html',
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
	 * Email templates: preserve full HTML (admin-only editing)
	 * Form templates: allow forms + safe HTML
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

		// Email templates need full HTML preserved (DOCTYPE, html, head, body, inline styles).
		// These are only editable by admins with manage_options capability.
		$is_email_template = (
			strpos( $template_name, 'email' ) !== false ||
			strpos( $template_name, 'welcome' ) !== false ||
			strpos( $template_name, 'expiration' ) !== false ||
			strpos( $template_name, 'password-reset' ) !== false ||
			strpos( $template_name, 'admin-new-user' ) !== false ||
			strpos( $template_name, '2fa-email' ) !== false ||
			strpos( $template_name, 'security-alert' ) !== false
		);

		if ( $is_email_template ) {
			// Email templates: use permissive kses that preserves email HTML structure.
			return self::sanitize_email_template( $content );
		}

		// Page templates need SVG, data attributes, and flexible HTML.
		// These are only editable by admins with manage_options capability.
		$is_page_template = (
			strpos( $template_name, 'public-profile' ) !== false ||
			strpos( $template_name, 'member-directory' ) !== false ||
			strpos( $template_name, 'account-data-export' ) !== false ||
			strpos( $template_name, 'version-history-viewer' ) !== false
		);

		if ( $is_page_template ) {
			// Page templates: use permissive kses that preserves SVG and data attributes.
			return self::sanitize_page_template( $content );
		}

		// Form/page templates - allow forms and safe HTML.
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
				'type'        => true,
				'class'       => true,
				'id'          => true,
				'name'        => true,
				'value'       => true,
				'data-tab'    => true,
				'data-subtab' => true,
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
				'class'       => true,
				'id'          => true,
				'style'       => true,
				'data-tab'    => true,
				'data-subtab' => true,
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
	 * SANITIZE EMAIL TEMPLATE.
	 *
	 * Permissive sanitization for email templates that preserves
	 * full HTML structure including DOCTYPE, inline styles, and
	 * table-based layouts required for email clients.
	 *
	 * Only admins with manage_options can edit these templates.
	 *
	 * @param  string $content Email template content.
	 * @return string Sanitized content with HTML structure preserved.
	 */
	private static function sanitize_email_template( $content ) {
		// Common style attribute for all elements.
		$style_attr = array( 'style' => true );

		$allowed_html = array(
			// Document structure.
			'html'       => array( 'lang' => true ),
			'head'       => array(),
			'body'       => $style_attr,
			'meta'       => array(
				'charset'    => true,
				'name'       => true,
				'content'    => true,
				'http-equiv' => true,
			),
			'title'      => array(),
			'style'      => array( 'type' => true ),

			// Table layout (essential for email).
			'table'      => array(
				'width'       => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'border'      => true,
				'role'        => true,
				'align'       => true,
				'bgcolor'     => true,
				'style'       => true,
				'class'       => true,
			),
			'tr'         => array(
				'style'   => true,
				'class'   => true,
				'bgcolor' => true,
				'align'   => true,
				'valign'  => true,
			),
			'td'         => array(
				'width'   => true,
				'height'  => true,
				'colspan' => true,
				'rowspan' => true,
				'align'   => true,
				'valign'  => true,
				'bgcolor' => true,
				'style'   => true,
				'class'   => true,
			),
			'th'         => array(
				'width'   => true,
				'colspan' => true,
				'rowspan' => true,
				'align'   => true,
				'valign'  => true,
				'style'   => true,
				'class'   => true,
			),
			'thead'      => $style_attr,
			'tbody'      => $style_attr,

			// Text elements.
			'p'          => $style_attr + array( 'class' => true ),
			'h1'         => $style_attr + array( 'class' => true ),
			'h2'         => $style_attr + array( 'class' => true ),
			'h3'         => $style_attr + array( 'class' => true ),
			'h4'         => $style_attr + array( 'class' => true ),
			'span'       => $style_attr + array( 'class' => true ),
			'div'        => $style_attr + array(
				'class' => true,
				'id'    => true,
			),
			'strong'     => $style_attr,
			'b'          => $style_attr,
			'em'         => $style_attr,
			'i'          => $style_attr,
			'u'          => $style_attr,
			'br'         => array(),
			'hr'         => $style_attr,

			// Links and images.
			'a'          => array(
				'href'   => true,
				'style'  => true,
				'class'  => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
				'style'  => true,
				'class'  => true,
				'border' => true,
			),

			// Lists.
			'ul'         => $style_attr + array( 'class' => true ),
			'ol'         => $style_attr + array( 'class' => true ),
			'li'         => $style_attr + array( 'class' => true ),

			// Other common elements.
			'blockquote' => $style_attr,
			'pre'        => $style_attr,
			'code'       => $style_attr,
			'center'     => array(),
		);

		// Use wp_kses but preserve DOCTYPE by handling it separately.
		$has_doctype = ( stripos( $content, '<!DOCTYPE' ) !== false );
		$doctype     = '';

		if ( $has_doctype ) {
			// Extract and preserve DOCTYPE.
			if ( preg_match( '/<!DOCTYPE[^>]*>/i', $content, $matches ) ) {
				$doctype = $matches[0] . "\n";
				$content = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $content, 1 );
			}
		}

		$sanitized = wp_kses( $content, $allowed_html );

		return $has_doctype ? $doctype . $sanitized : $sanitized;
	}

	/**
	 * SANITIZE PAGE TEMPLATE.
	 *
	 * Permissive sanitization for page templates (public profile, member directory,
	 * version history, data export) that preserves SVG elements, data attributes,
	 * and flexible HTML structure.
	 *
	 * Only admins with manage_options can edit these templates.
	 *
	 * @param  string $content Page template content.
	 * @return string Sanitized content with HTML structure preserved.
	 */
	private static function sanitize_page_template( $content ) {
		// Common attributes for most elements.
		$common_attrs = array(
			'class' => true,
			'id'    => true,
			'style' => true,
		);

		// Data attributes commonly used in templates.
		$data_attrs = array(
			'data-tab'         => true,
			'data-subtab'      => true,
			'data-view'        => true,
			'data-user-id'     => true,
			'data-context'     => true,
			'data-version-id'  => true,
			'data-previous-id' => true,
			'data-codes'       => true,
			'data-secret'      => true,
			'data-template'    => true,
			'data-target'      => true,
		);

		$allowed_html = array(
			// Structural elements.
			'div'        => $common_attrs + $data_attrs,
			'span'       => $common_attrs + $data_attrs,
			'section'    => $common_attrs,
			'article'    => $common_attrs,
			'header'     => $common_attrs,
			'footer'     => $common_attrs,
			'main'       => $common_attrs,
			'aside'      => $common_attrs,
			'nav'        => $common_attrs,

			// Headings.
			'h1'         => $common_attrs,
			'h2'         => $common_attrs,
			'h3'         => $common_attrs,
			'h4'         => $common_attrs,
			'h5'         => $common_attrs,
			'h6'         => $common_attrs,

			// Text elements.
			'p'          => $common_attrs,
			'strong'     => $common_attrs,
			'em'         => $common_attrs,
			'b'          => array(),
			'i'          => $common_attrs,
			'u'          => array(),
			'small'      => $common_attrs,
			'mark'       => $common_attrs,
			'del'        => $common_attrs,
			'ins'        => $common_attrs,
			'sub'        => array(),
			'sup'        => array(),
			'br'         => array(),
			'hr'         => $common_attrs,
			'pre'        => $common_attrs,
			'code'       => $common_attrs,
			'blockquote' => $common_attrs,
			'time'       => $common_attrs + array( 'datetime' => true ),

			// Links and images.
			'a'          => $common_attrs + array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'img'        => $common_attrs + array(
				'src'     => true,
				'alt'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
				'srcset'  => true,
				'sizes'   => true,
			),

			// Lists.
			'ul'         => $common_attrs,
			'ol'         => $common_attrs + array(
				'start' => true,
				'type'  => true,
			),
			'li'         => $common_attrs,
			'dl'         => $common_attrs,
			'dt'         => $common_attrs,
			'dd'         => $common_attrs,

			// Tables.
			'table'      => $common_attrs + array( 'role' => true ),
			'thead'      => $common_attrs,
			'tbody'      => $common_attrs,
			'tfoot'      => $common_attrs,
			'tr'         => $common_attrs,
			'th'         => $common_attrs + array(
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			),
			'td'         => $common_attrs + array(
				'colspan' => true,
				'rowspan' => true,
			),
			'caption'    => $common_attrs,

			// Forms.
			'form'       => $common_attrs + array(
				'method'  => true,
				'action'  => true,
				'enctype' => true,
			),
			'input'      => $common_attrs + $data_attrs + array(
				'type'         => true,
				'name'         => true,
				'value'        => true,
				'placeholder'  => true,
				'required'     => true,
				'disabled'     => true,
				'readonly'     => true,
				'checked'      => true,
				'maxlength'    => true,
				'minlength'    => true,
				'min'          => true,
				'max'          => true,
				'step'         => true,
				'pattern'      => true,
				'autocomplete' => true,
				'autofocus'    => true,
			),
			'button'     => $common_attrs + $data_attrs + array(
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'disabled' => true,
				'title'    => true,
			),
			'select'     => $common_attrs + array(
				'name'     => true,
				'required' => true,
				'disabled' => true,
				'multiple' => true,
			),
			'option'     => array(
				'value'    => true,
				'selected' => true,
				'disabled' => true,
			),
			'optgroup'   => array( 'label' => true ),
			'textarea'   => $common_attrs + array(
				'name'        => true,
				'rows'        => true,
				'cols'        => true,
				'placeholder' => true,
				'required'    => true,
				'disabled'    => true,
				'readonly'    => true,
				'maxlength'   => true,
			),
			'label'      => $common_attrs + array( 'for' => true ),
			'fieldset'   => $common_attrs,
			'legend'     => $common_attrs,

			// SVG elements (essential for icons).
			'svg'        => array(
				'class'           => true,
				'id'              => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'xmlns'           => true,
				'aria-hidden'     => true,
				'aria-label'      => true,
				'aria-labelledby' => true,
				'role'            => true,
				'focusable'       => true,
			),
			'path'       => array(
				'd'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'circle'     => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'rect'       => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'line'       => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'polyline'   => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'polygon'    => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'g'          => array(
				'fill'      => true,
				'stroke'    => true,
				'transform' => true,
				'class'     => true,
			),
			'use'        => array(
				'href'       => true,
				'xlink:href' => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
				'class'      => true,
			),
			'defs'       => array(),
			'clippath'   => array( 'id' => true ),
			'title'      => array(),
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
	 * @return void
	 */
	public static function clear_cache( ?string $template_name = null ): void {
		if ( $template_name ) {
			unset( self::$cache[ $template_name ] );
		} else {
			self::$cache = array();
		}
	}

	/**
	 * GET TEMPLATE LIST.
	 *
	 * Get all available templates (for admin UI).
	 *
	 * @return array<int, string> List of template names.
	 */
	public static function get_template_list(): array {
		return array_keys( self::$template_map );
	}

	/**
	 * GET PLACEHOLDERS.
	 *
	 * Get available placeholders for each template type.
	 *
	 * @param  string $template_name Template identifier.
	 * @return string Comma-separated list of placeholder descriptions.
	 */
	public static function get_placeholders( string $template_name ): string {
		$placeholders = array(
			// Email verification.
			'email-verification-html'     => '{site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}',
			'email-verification-text'     => '{site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}',

			// Welcome email.
			'welcome-email-html'          => '{site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}',
			'welcome-email-text'          => '{site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}',

			// Expiration warning.
			'expiration-warning-html'     => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}, {days_until_expiration}, {login_url}',
			'expiration-warning-text'     => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}, {days_until_expiration}, {login_url}',

			// Expiration notice (account expired).
			'expiration-notice-html'      => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}',
			'expiration-notice-text'      => '{site_name}, {site_url}, {display_name}, {username}, {expires_date}, {expiration_date}',

			// 2FA email code.
			'2fa-email-code-html'         => '{site_name}, {display_name}, {code}, {user_email}',
			'2fa-email-code-text'         => '{site_name}, {display_name}, {code}, {user_email}',

			// Password reset.
			'password-reset-html'         => '{site_name}, {display_name}, {username}, {reset_link}, {site_url}',
			'password-reset-text'         => '{site_name}, {display_name}, {username}, {reset_link}, {site_url}',

			// Admin new user notification.
			'admin-new-user-html'         => '{site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}',
			'admin-new-user-text'         => '{site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}',

			// Security alert.
			'security-alert-email-html'   => '{site_name}, {site_url}, {event_type}, {message}, {username}, {user_email}, {user_id}, {ip_address}, {timestamp}, {log_url}, {context}',

			// Login form.
			'login-form'                  => '{action_url}, {nonce_field}, {redirect_to}, {reset_link}, {register_link}, {error_message}',

			// Registration form.
			'registration-form'           => '{action_url}, {nonce_field}, {registration_fields}, {error_message}, {login_link}',

			// Account page.
			'account-page'                => '{messages}, {status_badges}, {username}, {email}, {display_name}, {registered_date}, {expiration_info}, {action_url}, {nonce_field}, {nonce_field_password}, {profile_fields}, {logout_url}',

			// Reset forms.
			'request-reset-form'          => '{action_url}, {nonce_field}, {error_message}, {success_message}, {login_url}, {register_link}',
			'reset-form'                  => '{action_url}, {nonce_field}, {error_message}, {password_requirements}, {login_url}',

			// 2FA pages.
			'2fa-verify'                  => '{action_url}, {nonce_field}, {error_message}, {method}, {resend_link}',
			'2fa-setup-totp'              => '{qr_code}, {secret_key}, {action_url}, {cancel_url}, {nonce_field}, {error_message}',
			'2fa-backup-codes'            => '{backup_codes}, {action_url}, {nonce_field}, {error_message}',
			'2fa-backup-verify'           => '{action_url}, {nonce_field}, {error_message}, {success_message}, {device_trust_checkbox}, {regular_verify_link}, {help_text}',

			// Policy templates.
			'policy-privacy-html'         => '{site_name}, {site_url}',
			'policy-terms-html'           => '{site_name}, {site_url}',

			// Page templates.
			'public-profile-html'         => '{display_name}, {username}, {profile_photo}, {cover_photo_html}, {joined_text}, {profile_fields_html}, {custom_content}, {edit_profile_button}',
			'member-directory-html'       => '{directory_controls}, {members_content}, {pagination}',
			'member-directory-list-html'  => '{directory_controls}, {members_content}, {pagination}',
			'account-data-export-html'    => '{section_title}, {gdpr_description}, {export_includes_title}, {export_includes_list}, {format_label}, {format_value}, {estimated_size_label}, {estimated_size}, {export_button}, {cancel_button}, {history_title}, {export_history}, {modal_title}, {modal_description}, {password_label}, {confirm_button}',
			'version-history-viewer-html' => '{header_title}, {header_description}, {context}, {user_id}, {page_info}, {prev_button}, {next_button}, {empty_title}, {empty_description}, {close_button}, {compare_button}, {revert_button}, {view_snapshot_button}, {diff_modal_title}, {comparing_text}, {loading_text}, {fields_changed_label}, {ip_address_label}',
		);

		return isset( $placeholders[ $template_name ] ) ? $placeholders[ $template_name ] : '';
	}
}
