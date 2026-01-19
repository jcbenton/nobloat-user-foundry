<?php
/**
 * IP Address Utility Class
 *
 * Provides centralized IP address handling with security features
 * including proxy header validation and IPv6 normalization.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_IP class.
 *
 * Handles secure IP address retrieval and normalization.
 */
class NBUF_IP {

	/**
	 * Get client IP address securely.
	 *
	 * Retrieves the client's IP address with security measures to prevent
	 * IP spoofing via proxy headers. Only trusts X-Forwarded-For if the
	 * request originates from a configured trusted proxy.
	 *
	 * @since  1.5.5
	 * @param  bool $normalize_ipv6 Whether to normalize IPv6 addresses to canonical form.
	 * @return string Client IP address (lowercase).
	 */
	public static function get_client_ip( bool $normalize_ipv6 = true ): string {
		$ip = '';

		/*
		 * SECURITY: Prevent IP spoofing via X-Forwarded-For header.
		 *
		 * Only trust proxy headers if request originates from a trusted proxy.
		 * This prevents attackers from bypassing rate limiting by sending fake
		 * X-Forwarded-For headers.
		 *
		 * To configure trusted proxies, add them to plugin settings (empty = don't trust proxies).
		 */
		$trusted_proxies = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/* Only trust X-Forwarded-For if request comes from trusted proxy */
		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			}
		}

		/* Fallback to REMOTE_ADDR (cannot be spoofed) */
		if ( empty( $ip ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/* Handle multiple IPs from proxy (take first one - closest to client) */
		if ( strpos( $ip, ',' ) !== false ) {
			$ip_array = explode( ',', $ip );
			$ip       = trim( $ip_array[0] );
		}

		/* Validate IP address */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			/* Invalid IP - use REMOTE_ADDR as fallback */
			$ip = isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
				: '0.0.0.0';
		}

		/* Normalize IPv6 addresses to canonical form if requested */
		if ( $normalize_ipv6 && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			/*
			 * SECURITY: Normalize IPv6 to canonical form.
			 * Prevents rate limit bypass via IPv6 representation variations.
			 * Example: 2001:0db8::1 and 2001:db8::1 and 2001:DB8::1 are the same address.
			 */
			$normalized = inet_ntop( inet_pton( $ip ) );
			if ( false !== $normalized ) {
				$ip = $normalized;
			}
		}

		/* Lowercase for consistency */
		return strtolower( $ip );
	}

	/**
	 * Validate an IP address.
	 *
	 * @since  1.5.5
	 * @param  string $ip IP address to validate.
	 * @return bool True if valid IP address.
	 */
	public static function is_valid( string $ip ): bool {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Check if IP is IPv6.
	 *
	 * @since  1.5.5
	 * @param  string $ip IP address to check.
	 * @return bool True if IPv6 address.
	 */
	public static function is_ipv6( string $ip ): bool {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
	}

	/**
	 * Check if IP is IPv4.
	 *
	 * @since  1.5.5
	 * @param  string $ip IP address to check.
	 * @return bool True if IPv4 address.
	 */
	public static function is_ipv4( string $ip ): bool {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	}
}
