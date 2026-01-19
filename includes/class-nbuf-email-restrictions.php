<?php
/**
 * Email Domain Restrictions
 *
 * Restricts registration to specific email domains via whitelist or blacklist.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Email_Restrictions class.
 *
 * Handles email domain whitelist/blacklist for registration.
 *
 * @since 1.5.2
 */
class NBUF_Email_Restrictions {


	/**
	 * Check if email domain restrictions are enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		$mode = NBUF_Options::get( 'nbuf_email_restriction_mode', 'none' );
		return 'none' !== $mode;
	}

	/**
	 * Get restriction mode.
	 *
	 * @since  1.5.2
	 * @return string Mode: 'none', 'whitelist', or 'blacklist'.
	 */
	public static function get_mode(): string {
		return NBUF_Options::get( 'nbuf_email_restriction_mode', 'none' );
	}

	/**
	 * Get configured domain list.
	 *
	 * @since  1.5.2
	 * @return array Array of domain patterns.
	 */
	public static function get_domains(): array {
		$domains_raw = NBUF_Options::get( 'nbuf_email_restriction_domains', '' );

		if ( empty( $domains_raw ) ) {
			return array();
		}

		/* Parse domains - one per line */
		$lines   = explode( "\n", $domains_raw );
		$domains = array();

		foreach ( $lines as $line ) {
			$domain = strtolower( trim( $line ) );

			/* Skip empty lines and comments */
			if ( empty( $domain ) || 0 === strpos( $domain, '#' ) ) {
				continue;
			}

			$domains[] = $domain;
		}

		return $domains;
	}

	/**
	 * Get custom error message.
	 *
	 * @since  1.5.2
	 * @return string Error message.
	 */
	public static function get_error_message(): string {
		$message = NBUF_Options::get( 'nbuf_email_restriction_message', '' );

		if ( empty( $message ) ) {
			$mode = self::get_mode();
			if ( 'whitelist' === $mode ) {
				return __( 'Registration is restricted to approved email domains.', 'nobloat-user-foundry' );
			}
			return __( 'This email domain is not allowed for registration.', 'nobloat-user-foundry' );
		}

		return $message;
	}

	/**
	 * Extract domain from email address.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address.
	 * @return string Domain portion of email.
	 */
	public static function get_email_domain( string $email ): string {
		$parts = explode( '@', strtolower( $email ) );
		return isset( $parts[1] ) ? $parts[1] : '';
	}

	/**
	 * Check if a domain matches a pattern.
	 *
	 * Supports:
	 * - Exact match: company.com
	 * - Wildcard subdomain: *.company.com (matches sub.company.com but not company.com)
	 *
	 * @since  1.5.2
	 * @param  string $domain  Domain to check.
	 * @param  string $pattern Pattern to match against.
	 * @return bool True if domain matches pattern.
	 */
	public static function domain_matches_pattern( string $domain, string $pattern ): bool {
		$domain  = strtolower( trim( $domain ) );
		$pattern = strtolower( trim( $pattern ) );

		/* Exact match */
		if ( $domain === $pattern ) {
			return true;
		}

		/* Wildcard subdomain match: *.company.com */
		if ( 0 === strpos( $pattern, '*.' ) ) {
			$base_domain = substr( $pattern, 2 ); /* Remove *. prefix */

			/* Check if domain ends with .base_domain */
			$suffix = '.' . $base_domain;
			if ( substr( $domain, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}

			/* Also match the base domain itself (*.company.com matches company.com) */
			if ( $domain === $base_domain ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an email is allowed based on restriction settings.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address to check.
	 * @return bool True if email is allowed.
	 */
	public static function is_email_allowed( string $email ): bool {
		/* If restrictions disabled, allow all */
		if ( ! self::is_enabled() ) {
			return true;
		}

		$mode    = self::get_mode();
		$domains = self::get_domains();

		/* If no domains configured, allow all */
		if ( empty( $domains ) ) {
			return true;
		}

		$email_domain = self::get_email_domain( $email );

		if ( empty( $email_domain ) ) {
			return false; /* Invalid email */
		}

		/* Check if domain matches any pattern */
		$matches_pattern = false;
		foreach ( $domains as $pattern ) {
			if ( self::domain_matches_pattern( $email_domain, $pattern ) ) {
				$matches_pattern = true;
				break;
			}
		}

		/* Whitelist mode: must match a pattern */
		if ( 'whitelist' === $mode ) {
			return $matches_pattern;
		}

		/* Blacklist mode: must NOT match any pattern */
		if ( 'blacklist' === $mode ) {
			return ! $matches_pattern;
		}

		return true;
	}

	/**
	 * Validate email for registration.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address.
	 * @return true|WP_Error True if valid, WP_Error if blocked.
	 */
	public static function validate( string $email ) {
		if ( self::is_email_allowed( $email ) ) {
			return true;
		}

		/* Log blocked attempt */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log_or_update(
				'email_domain_blocked',
				'warning',
				'Registration blocked: email domain not allowed',
				array(
					'email'  => $email,
					'domain' => self::get_email_domain( $email ),
					'mode'   => self::get_mode(),
				)
			);
		}

		return new WP_Error(
			'email_domain_blocked',
			self::get_error_message()
		);
	}
}
