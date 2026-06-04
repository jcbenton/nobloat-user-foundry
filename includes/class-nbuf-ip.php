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

		/*
		 * SECURITY: canonicalize all addresses BEFORE comparing against the
		 * trusted-proxy allowlist. Previously the strict in_array() compared raw
		 * strings, so a trusted proxy configured as `2001:db8::1` would fail to
		 * match a REMOTE_ADDR of `2001:0db8:0000::1` (or upper-case). The XFF
		 * chain would then not be trusted, collapsing every client behind that
		 * proxy onto a single IP for rate-limiting and IP-restriction checks.
		 */
		$trusted_proxies   = array_filter( array_map( array( __CLASS__, 'canonicalize_ip' ), (array) $trusted_proxies ) );
		$remote_addr_canon = self::canonicalize_ip( $remote_addr );

		/* Only trust X-Forwarded-For if request comes from trusted proxy */
		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr_canon, $trusted_proxies, true ) ) {
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$xff = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );

				/*
				 * Walk the chain right-to-left (closest proxy → farthest).
				 * Stop at the first IP that is NOT a trusted proxy — that is
				 * the real client. The leftmost entry is attacker-controlled
				 * and must never be trusted.
				 */
				$parts = array_map( 'trim', explode( ',', $xff ) );
				$parts = array_reverse( $parts );
				foreach ( $parts as $candidate ) {
					if ( '' === $candidate ) {
						continue;
					}
					$candidate_canon = self::canonicalize_ip( $candidate );
					if ( ! in_array( $candidate_canon, $trusted_proxies, true ) ) {
						$ip = $candidate_canon;
						break;
					}
				}
			} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			}
		}

		/* Fallback to REMOTE_ADDR (cannot be spoofed) */
		if ( empty( $ip ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
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
	 * Canonicalize an IP string to a comparable form.
	 *
	 * Round-trips through inet_pton/inet_ntop so equivalent IPv6 representations
	 * (zero-compression, leading zeros, letter case) collapse to one value, and
	 * lowercases the result. Non-IP input is returned lowercased unchanged.
	 *
	 * @param  string $ip Raw IP string.
	 * @return string Canonical lowercase IP, or '' for empty input.
	 */
	private static function canonicalize_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( '' === $ip ) {
			return '';
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning on non-IP input; we handle false below.
		if ( false === $packed ) {
			return strtolower( $ip );
		}
		$normal = inet_ntop( $packed );
		return false === $normal ? strtolower( $ip ) : strtolower( $normal );
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
