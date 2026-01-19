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
		 * Priority 25: Run AFTER WordPress authentication (priority 20) completes,
		 * but BEFORE 2FA intercept (priority 30) which redirects and exits.
		 *
		 * Note: Custom login pages (Universal Router) check IP directly in
		 * NBUF_Shortcodes::process_login_form() before wp_signon() is called.
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_ip_restrictions' ), 25, 3 );
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
		return (bool) NBUF_Options::get( 'nbuf_ip_restriction_admin_bypass', true );
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
		$ip = '';

		/* Get trusted proxies configuration */
		$trusted_proxies = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

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

		/* Handle multiple IPs from proxy (take first one) */
		if ( strpos( $ip, ',' ) !== false ) {
			$ip_array = explode( ',', $ip );
			$ip       = trim( $ip_array[0] );
		}

		/* Validate and normalize IP address */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		}

		/* Normalize IPv6 addresses to canonical form */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$normalized = inet_ntop( inet_pton( $ip ) );
			if ( false !== $normalized ) {
				$ip = $normalized;
			}
		}

		return strtolower( $ip );
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

			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_long   = -1 << ( 32 - (int) $mask );

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

			/* Build mask */
			$mask_int = (int) $mask;
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
		/* Only support IPv4 wildcards for simplicity */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		/* Convert wildcard pattern to regex */
		$regex = '/^' . str_replace(
			array( '.', '*' ),
			array( '\\.', '\\d{1,3}' ),
			$pattern
		) . '$/';

		return (bool) preg_match( $regex, $ip );
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
	 * @since  1.5.2
	 * @param  WP_User|WP_Error|null $user     User object or error.
	 * @param  string                $username Username.
	 * @param  string                $password Password.
	 * @return WP_User|WP_Error Modified user object or error.
	 */
	public static function check_ip_restrictions( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress authenticate filter signature
		/* Only check if user successfully authenticated (is WP_User) */
		if ( ! $user instanceof \WP_User ) {
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

		/* Check admin bypass */
		if ( self::admin_bypass_enabled() && user_can( $user, 'manage_options' ) ) {
			return $user;
		}

		/* Log blocked attempt */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log_or_update(
				'ip_blocked',
				'critical',
				'Login blocked: IP not in allowed list',
				array(
					'ip_address' => $client_ip,
					'username'   => $user->user_login,
					'mode'       => self::get_mode(),
				)
			);
		}

		return new WP_Error(
			'ip_blocked',
			__( 'Access denied. Your IP address is not authorized to log in.', 'nobloat-user-foundry' )
		);
	}
}
