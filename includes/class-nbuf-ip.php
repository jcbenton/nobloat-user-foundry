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
		 * SECURITY: Prevent IP spoofing via proxy headers.
		 *
		 * Only trust forwarded-client headers if the request originates from a
		 * configured trusted proxy. Trusted-proxy entries may be exact IPs OR
		 * CIDR ranges (e.g. 173.245.48.0/20) — CDNs/load balancers present many
		 * rotating edge IPs, so a single-IP allowlist is unusable for them. An
		 * empty list = trust nothing (use REMOTE_ADDR for everyone).
		 */
		$trusted_proxies = (array) NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( ! empty( $trusted_proxies ) && self::is_trusted_proxy( $remote_addr, $trusted_proxies ) ) {
			/*
			 * Request arrived via a trusted proxy. Prefer purpose-built
			 * single-client headers (Cloudflare CF-Connecting-IP / Akamai-style
			 * True-Client-IP), then walk X-Forwarded-For right-to-left stopping
			 * at the first hop that is NOT itself a trusted proxy (the real
			 * client). The leftmost XFF entry is attacker-controlled and must
			 * never be trusted.
			 */
			$cf_ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
			$tc_ip = isset( $_SERVER['HTTP_TRUE_CLIENT_IP'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_TRUE_CLIENT_IP'] ) ) : '';

			if ( '' !== $cf_ip && filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				$ip = $cf_ip;
			} elseif ( '' !== $tc_ip && filter_var( $tc_ip, FILTER_VALIDATE_IP ) ) {
				$ip = $tc_ip;
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$xff   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$parts = array_reverse( array_map( 'trim', explode( ',', $xff ) ) );
				foreach ( $parts as $candidate ) {
					if ( '' === $candidate || ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
						continue;
					}
					if ( ! self::is_trusted_proxy( $candidate, $trusted_proxies ) ) {
						$ip = $candidate;
						break;
					}
				}
			} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$client_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
				if ( filter_var( $client_ip, FILTER_VALIDATE_IP ) ) {
					$ip = $client_ip;
				}
			}
		}

		/* Fallback to REMOTE_ADDR (cannot be spoofed) */
		if ( empty( $ip ) && '' !== $remote_addr ) {
			$ip = $remote_addr;
		}

		/* Validate IP address */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			/* Invalid IP - use REMOTE_ADDR as fallback, then a last-resort sentinel. */
			$ip = ( '' !== $remote_addr && filter_var( $remote_addr, FILTER_VALIDATE_IP ) )
				? $remote_addr
				: '0.0.0.0';
		}

		/* Normalize IPv6 addresses to canonical form if requested */
		/* Collapse IPv4-mapped IPv6 (::ffff:a.b.c.d) to dotted IPv4 so a client
		 * arriving over a dual-stack socket keys/matches identically to native
		 * IPv4 (otherwise blacklists are evaded and IPv4 whitelists lock the
		 * client out). */
		$ip = self::fold_mapped_ipv4( $ip );

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
	 * Determine whether an address is a configured trusted proxy.
	 *
	 * Each trusted-proxy entry may be an exact IP (matched canonically so
	 * representation variants collapse) or a CIDR range. This is what makes the
	 * plugin usable behind CDNs/load balancers whose edges are published as
	 * ranges rather than a fixed list of IPs.
	 *
	 * @param  string             $address         Candidate address (REMOTE_ADDR or an XFF hop).
	 * @param  array<int, string> $trusted_proxies Configured trusted-proxy entries (IPs and/or CIDRs).
	 * @return bool True if the address is (or is within) a trusted proxy.
	 */
	public static function is_trusted_proxy( string $address, array $trusted_proxies ): bool {
		$address = trim( $address );
		if ( '' === $address || ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$address_canon = self::canonicalize_ip( $address );

		foreach ( $trusted_proxies as $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( false !== strpos( $entry, '/' ) ) {
				if ( self::ip_in_cidr( $address, $entry ) ) {
					return true;
				}
			} elseif ( self::canonicalize_ip( $entry ) === $address_canon ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether an IP falls within a CIDR range (IPv4 or IPv6).
	 *
	 * Bit-masks the packed address against the subnet. Returns false for a
	 * malformed CIDR or an address-family mismatch. A CIDR with no "/" is
	 * treated as an exact-IP comparison.
	 *
	 * @param  string $ip   IP address to test.
	 * @param  string $cidr CIDR range, e.g. "192.168.0.0/16" or "2001:db8::/32".
	 * @return bool True if $ip is within $cidr.
	 */
	public static function ip_in_cidr( string $ip, string $cidr ): bool {
		list( $subnet, $mask ) = array_pad( explode( '/', $cidr ), 2, null );

		if ( null === $mask ) {
			return self::canonicalize_ip( $ip ) === self::canonicalize_ip( (string) $subnet );
		}

		/* IPv4 */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
			&& filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$mask_int = (int) $mask;
			if ( $mask_int < 0 || $mask_int > 32 ) {
				return false;
			}
			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_long   = $mask_int > 0 ? ( -1 << ( 32 - $mask_int ) ) : 0;
			return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
		}

		/* IPv6 */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
			&& filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$ip_bin     = inet_pton( $ip );
			$subnet_bin = inet_pton( $subnet );
			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}
			$mask_int = (int) $mask;
			if ( $mask_int < 0 || $mask_int > 128 ) {
				return false;
			}
			$mask_bin = str_repeat( "\xff", intdiv( $mask_int, 8 ) );
			if ( $mask_int % 8 ) {
				$mask_bin .= chr( ( 0xff << ( 8 - ( $mask_int % 8 ) ) ) & 0xff );
			}
			$mask_bin = str_pad( $mask_bin, 16, "\x00" );
			return ( $ip_bin & $mask_bin ) === ( $subnet_bin & $mask_bin );
		}

		return false;
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
	/**
	 * Collapse an IPv4-mapped IPv6 address (::ffff:a.b.c.d) to dotted IPv4.
	 *
	 * Returns the input unchanged if it is not a mapped address. Lets a client
	 * presented over a dual-stack socket as ::ffff:x compare equal to native
	 * IPv4 x in rate-limit keys and IP-restriction matching.
	 *
	 * @param  string $ip IP address.
	 * @return string IPv4 dotted form if mapped, else $ip unchanged.
	 */
	private static function fold_mapped_ipv4( string $ip ): string {
		if ( false === strpos( $ip, ':' ) ) {
			return $ip;
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- non-IP handled below.
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $packed, 0, 12 ) ) {
			$v4 = inet_ntop( substr( $packed, 12, 4 ) );
			if ( false !== $v4 ) {
				return $v4;
			}
		}
		return $ip;
	}

	public static function canonicalize_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( '' === $ip ) {
			return '';
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning on non-IP input; we handle false below.
		if ( false === $packed ) {
			return strtolower( $ip );
		}
		$normal = inet_ntop( $packed );
		if ( false === $normal ) {
			return strtolower( $ip );
		}
		return strtolower( self::fold_mapped_ipv4( $normal ) );
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
