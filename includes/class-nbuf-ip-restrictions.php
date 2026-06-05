<?php
/**
 * IP Restrictions
 *
 * Restricts login to specific IP addresses via whitelist or blacklist.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_IP_Restrictions class.
 *
 * Handles IP-based login restrictions via whitelist/blacklist.
 *
 * @since 1.5.2
 */
class NBUF_IP_Restrictions {


	/**
	 * Initialize IP restriction hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/*
		 * Hook into authenticate filter for wp-login.php access.
		 * Priority 1: Run BEFORE WordPress authentication (priority 20) so that
		 * blacklisted IPs are blocked regardless of whether credentials are valid.
		 * This prevents brute-force attacks from restricted IPs and matches the
		 * behavior of the custom login form which checks IP before wp_signon().
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_ip_restrictions' ), 1, 3 );
	}

	/**
	 * Check if IP restrictions are enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_ip_restriction_enabled', false );
	}

	/**
	 * Get restriction mode.
	 *
	 * @since  1.5.2
	 * @return string Mode: 'whitelist' or 'blacklist'.
	 */
	public static function get_mode(): string {
		return NBUF_Options::get( 'nbuf_ip_restriction_mode', 'whitelist' );
	}

	/**
	 * Get configured IP list.
	 *
	 * @since  1.5.2
	 * @return array<int, string> Array of IP addresses/ranges.
	 */
	public static function get_ip_list(): array {
		$list_raw = NBUF_Options::get( 'nbuf_ip_restriction_list', '' );

		if ( empty( $list_raw ) ) {
			return array();
		}

		/* Parse IPs - one per line or comma-separated */
		$list_raw = str_replace( ',', "\n", $list_raw );
		$lines    = explode( "\n", $list_raw );
		$ips      = array();

		foreach ( $lines as $line ) {
			$ip = strtolower( trim( $line ) );

			/* Skip empty lines and comments */
			if ( empty( $ip ) || 0 === strpos( $ip, '#' ) ) {
				continue;
			}

			$ips[] = $ip;
		}

		return $ips;
	}

	/**
	 * Check if admin bypass is enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if admins can bypass restrictions.
	 */
	public static function admin_bypass_enabled(): bool {
		/*
		 * SECURITY: default OFF. When enabled, the response from a blocked
		 * IP differs depending on whether the supplied username resolves to
		 * an admin (passes through to password check) vs a non-admin
		 * (returns ip_blocked WP_Error). That variance leaks role
		 * membership and lets an attacker enumerate admin accounts. Keep
		 * the toggle for emergency lockout recovery, but require explicit
		 * opt-in and surface the trade-off in the settings UI.
		 */
		return (bool) NBUF_Options::get( 'nbuf_ip_restriction_admin_bypass', false );
	}

	/**
	 * Get client IP address.
	 *
	 * Reuses the same IP detection logic as login limiting for consistency.
	 *
	 * @since  1.5.2
	 * @return string Client IP address.
	 */
	public static function get_client_ip(): string {
		return NBUF_IP::get_client_ip( true );
	}

	/**
	 * Check if an IP matches a pattern.
	 *
	 * Supports:
	 * - Exact match: 192.168.1.1
	 * - CIDR notation: 192.168.1.0/24
	 * - Wildcard: 192.168.1.*
	 *
	 * @since  1.5.2
	 * @param  string $ip      IP address to check.
	 * @param  string $pattern Pattern to match against.
	 * @return bool True if IP matches pattern.
	 */
	public static function ip_matches_pattern( string $ip, string $pattern ): bool {
		$ip      = strtolower( trim( $ip ) );
		$pattern = strtolower( trim( $pattern ) );

		/* Exact match */
		if ( $ip === $pattern ) {
			return true;
		}

		/*
		 * Canonicalized exact match. The client IP arrives already canonical
		 * (NBUF_IP::get_client_ip), but an admin-entered IPv6 may be expanded /
		 * zero-padded / mixed-case. Without canonicalizing the pattern, a
		 * blacklist entry fails open and a whitelist entry locks the admin out.
		 * Skip wildcard/CIDR patterns (handled below).
		 */
		if ( false === strpos( $pattern, '/' ) && false === strpos( $pattern, '*' )
			&& class_exists( 'NBUF_IP' ) && method_exists( 'NBUF_IP', 'canonicalize_ip' )
			&& NBUF_IP::canonicalize_ip( $ip ) === NBUF_IP::canonicalize_ip( $pattern ) ) {
			return true;
		}

		/* CIDR notation: 192.168.1.0/24 */
		if ( strpos( $pattern, '/' ) !== false ) {
			return self::ip_in_cidr( $ip, $pattern );
		}

		/* Wildcard: 192.168.1.* or 192.168.*.* */
		if ( strpos( $pattern, '*' ) !== false ) {
			return self::ip_matches_wildcard( $ip, $pattern );
		}

		return false;
	}

	/**
	 * Check if an IP is within a CIDR range.
	 *
	 * @since  1.5.2
	 * @param  string $ip   IP address to check.
	 * @param  string $cidr CIDR notation (e.g., 192.168.1.0/24).
	 * @return bool True if IP is in range.
	 */
	private static function ip_in_cidr( string $ip, string $cidr ): bool {
		list( $subnet, $mask ) = array_pad( explode( '/', $cidr ), 2, null );

		if ( null === $mask ) {
			return $ip === $subnet;
		}

		/* Handle IPv4 */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) &&
			filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {

			$mask_int = (int) $mask;
			if ( $mask_int < 0 || $mask_int > 32 ) {
				return false;
			}

			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_long   = $mask_int > 0 ? ( -1 << ( 32 - $mask_int ) ) : 0;

			return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
		}

		/* Handle IPv6 */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) &&
			filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {

			$ip_bin     = inet_pton( $ip );
			$subnet_bin = inet_pton( $subnet );

			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}

			/* Build mask — validate IPv6 CIDR range */
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
	 * Check if an IP matches a wildcard pattern.
	 *
	 * @since  1.5.2
	 * @param  string $ip      IP address to check.
	 * @param  string $pattern Wildcard pattern (e.g., 192.168.1.*).
	 * @return bool True if IP matches pattern.
	 */
	private static function ip_matches_wildcard( string $ip, string $pattern ): bool {
		$pattern_is_ipv6 = false !== strpos( $pattern, ':' );

		/* IPv6 client matches only an IPv6 wildcard pattern. */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $pattern_is_ipv6 ? self::ipv6_matches_wildcard( $ip, $pattern ) : false;
		}

		/* IPv4 client: reject non-IPv4 client or an IPv6 pattern outright. */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || $pattern_is_ipv6 ) {
			return false;
		}

		/* IPv4 octet wildcard: 192.168.1.* or 192.168.*.* */
		$escaped = preg_quote( $pattern, '/' );
		$regex   = '/^' . str_replace( '\\*', '(\\d{1,3})', $escaped ) . '$/';

		if ( ! preg_match( $regex, $ip, $matches ) ) {
			return false;
		}

		/* Validate that wildcard-matched octets are 0-255 */
		foreach ( array_slice( $matches, 1 ) as $octet ) {
			if ( (int) $octet > 255 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Match an IPv6 address against an IPv6 wildcard pattern.
	 *
	 * Supports a trailing-prefix wildcard ("2001:db8:*" / "2001:db8::*" — the
	 * trailing * matches any remaining hextets) and a full per-hextet form
	 * ("2001:db8:0:0:0:0:0:*" — exactly 8 groups, each * matches one hextet).
	 * Both the client IP and the pattern's literal hextets are normalized (the
	 * client is expanded from its packed form) so :: compression / zero-padding
	 * cannot cause a mismatch. CIDR ranges (2001:db8::/32) are handled by
	 * ip_in_cidr, exact addresses by the canonical exact-match branch.
	 *
	 * @param  string $ip      Canonical IPv6 client address.
	 * @param  string $pattern Lowercased IPv6 wildcard pattern.
	 * @return bool True if the address matches the pattern.
	 */
	private static function ipv6_matches_wildcard( string $ip, string $pattern ): bool {
		$ip_groups = self::expand_ipv6( $ip );
		if ( null === $ip_groups ) {
			return false;
		}

		/* Prefix form: a trailing * matches any remaining hextets. */
		if ( '*' === substr( $pattern, -1 ) ) {
			$prefix = rtrim( substr( $pattern, 0, -1 ), ':' );
			if ( '' !== $prefix && false === strpos( $prefix, '*' ) && false === strpos( $prefix, '::' ) ) {
				$pattern_groups = explode( ':', $prefix );
				if ( count( $pattern_groups ) > 8 ) {
					return false;
				}
				foreach ( $pattern_groups as $i => $pg ) {
					$normalized = self::normalize_hextet( $pg );
					if ( null === $normalized || $normalized !== $ip_groups[ $i ] ) {
						return false;
					}
				}
				return true;
			}
		}

		/* Full per-hextet form: exactly 8 groups, '*' matches one hextet. */
		if ( false !== strpos( $pattern, '::' ) ) {
			return false;
		}
		$pattern_groups = explode( ':', $pattern );
		if ( count( $pattern_groups ) !== 8 ) {
			return false;
		}
		foreach ( $pattern_groups as $i => $pg ) {
			if ( '*' === $pg ) {
				continue;
			}
			$normalized = self::normalize_hextet( $pg );
			if ( null === $normalized || $normalized !== $ip_groups[ $i ] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Expand an IPv6 address to its eight normalized (lowercase,
	 * leading-zero-stripped) hextets, or null if not a valid IPv6 address.
	 *
	 * @param  string $ip IPv6 address.
	 * @return array<int,string>|null Eight hextets, or null.
	 */
	private static function expand_ipv6( string $ip ): ?array {
		$packed = @inet_pton( $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return null;
		}
		$groups = str_split( bin2hex( $packed ), 4 );
		return array_map(
			static function ( $g ) {
				$g = ltrim( $g, '0' );
				return '' === $g ? '0' : $g;
			},
			$groups
		);
	}

	/**
	 * Normalize a single hextet literal (lowercase, leading-zero-stripped), or
	 * null if it is not a valid 1-4 digit hex group.
	 *
	 * @param  string $hextet Hextet literal.
	 * @return string|null Normalized hextet, or null.
	 */
	private static function normalize_hextet( string $hextet ): ?string {
		if ( ! preg_match( '/^[0-9a-f]{1,4}$/', $hextet ) ) {
			return null;
		}
		$hextet = ltrim( $hextet, '0' );
		return '' === $hextet ? '0' : $hextet;
	}

	/**
	 * Check if an IP is allowed based on restriction settings.
	 *
	 * @since  1.5.2
	 * @param  string $ip IP address to check.
	 * @return bool True if IP is allowed.
	 */
	public static function is_ip_allowed( string $ip ): bool {
		/* If restrictions disabled, allow all */
		if ( ! self::is_enabled() ) {
			return true;
		}

		$mode    = self::get_mode();
		$ip_list = self::get_ip_list();

		/* If no IPs configured, allow all (prevents lockout) */
		if ( empty( $ip_list ) ) {
			return true;
		}

		/* Check if IP matches any pattern */
		$matches_pattern = false;
		foreach ( $ip_list as $pattern ) {
			if ( self::ip_matches_pattern( $ip, $pattern ) ) {
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
	 * Check IP restrictions during authentication.
	 *
	 * Blocks restricted IPs regardless of whether credentials are valid,
	 * matching the behavior of the custom login form. This prevents
	 * blacklisted IPs from brute-forcing passwords on wp-login.php.
	 *
	 * @since  1.5.2
	 * @param  WP_User|WP_Error|null $user     User object or error.
	 * @param  string                $username Username.
	 * @param  string                $password Password.
	 * @return WP_User|WP_Error|null Modified user object or error.
	 */
	public static function check_ip_restrictions( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress authenticate filter signature
		/* Skip if no login attempt (empty username means no form submission) */
		if ( empty( $username ) ) {
			return $user;
		}

		/* Check if IP restrictions are enabled */
		if ( ! self::is_enabled() ) {
			return $user;
		}

		/* Get client IP */
		$client_ip = self::get_client_ip();

		/* Check if IP is allowed */
		if ( self::is_ip_allowed( $client_ip ) ) {
			return $user;
		}

		/*
		 * Check admin bypass — look up user if not already authenticated.
		 *
		 * SECURITY trade-off: a non-admin from a blocked IP still gets
		 * blocked (returns WP_Error) whereas an admin is allowed through.
		 * That visible difference is unavoidable while admin_bypass exists,
		 * but we balance the work done so the timing of the two branches
		 * is similar — the attacker's only signal is the response code,
		 * not response latency.
		 */
		if ( self::admin_bypass_enabled() ) {
			$bypass_user = ( $user instanceof \WP_User ) ? $user : null;

			if ( ! $bypass_user ) {
				$bypass_user = get_user_by( 'login', $username );
				if ( ! $bypass_user ) {
					$bypass_user = get_user_by( 'email', $username );
				}
			}

			if ( $bypass_user && user_can( $bypass_user, 'manage_options' ) ) {
				return $user;
			}

			/*
			 * Time-balance: spend roughly the same wall-clock time the
			 * admin path would have, so the only attacker-visible signal
			 * for username-is-admin is the response code, not the latency.
			 */
			if ( $bypass_user ) {
				user_can( $bypass_user, 'manage_options' );
			}
		}

		/* Build log message based on mode */
		$mode        = self::get_mode();
		$log_message = ( 'blacklist' === $mode )
			? 'Login blocked: IP address is blacklisted'
			: 'Login blocked: IP address is not in whitelist';

		/* Log blocked attempt */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log_or_update(
				'ip_blocked',
				'critical',
				$log_message,
				array(
					'ip_address' => $client_ip,
					'username'   => $username,
					'mode'       => $mode,
				)
			);
		}

		return new WP_Error(
			'ip_blocked',
			__( 'Access denied. Your IP address is not authorized to log in.', 'nobloat-user-foundry' )
		);
	}
}
