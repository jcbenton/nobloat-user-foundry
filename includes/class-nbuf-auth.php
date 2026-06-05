<?php
/**
 * Shared authentication helpers.
 *
 * Consolidates the login-status gating that was previously duplicated across
 * the magic-link, 2FA, and passkey login-completion paths. A single source of
 * truth prevents one path from silently omitting a check — the root cause of
 * the out-of-band session-minting bypasses fixed in the v1.7.x audit rounds.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Auth
 *
 * Static authentication helpers shared by every login path.
 */
class NBUF_Auth {

	/**
	 * Enforce account login-status gates for a user.
	 *
	 * Mirrors NBUF_Hooks::enforce_verification_before_login (the priority-25
	 * `authenticate` filter) for code paths that authenticate out-of-band and
	 * never run that filter (magic links, passkeys, 2FA completion). Returns the
	 * same error codes those paths already emit so downstream handling is
	 * unchanged. NOTE: this covers account-state gates (disabled / expired /
	 * unverified / pending approval) AND the IP-restriction gate (mirroring the
	 * authenticate priority-1 filter); the forced/expired/weak PASSWORD gates are
	 * handled separately by NBUF_Password_Expiration::maybe_get_change_redirect().
	 *
	 * @param  int $user_id User ID.
	 * @return true|WP_Error True if the user may proceed, WP_Error otherwise.
	 */
	public static function enforce_login_status( int $user_id ) {
		/*
		 * IP-restriction gate. The authenticate priority-1 filter
		 * (NBUF_IP_Restrictions::check_ip_restrictions) only runs on the password
		 * front door; out-of-band paths (magic link, passkey, 2FA completion)
		 * never invoke it, so enforce it here too. Checked BEFORE the admin
		 * exemption below so an IP block is honored for admins as well UNLESS
		 * admin_bypass is enabled, exactly matching the front-door semantics.
		 * No-op on default installs (feature off or empty list -> is_ip_allowed
		 * returns true), so no legitimate user is ever newly blocked.
		 */
		if ( class_exists( 'NBUF_IP_Restrictions' ) && NBUF_IP_Restrictions::is_enabled() ) {
			$nbuf_client_ip = NBUF_IP_Restrictions::get_client_ip();
			if ( ! NBUF_IP_Restrictions::is_ip_allowed( $nbuf_client_ip ) ) {
				$ip_admin_bypass = NBUF_IP_Restrictions::admin_bypass_enabled()
					&& user_can( $user_id, 'manage_options' );
				if ( ! $ip_admin_bypass ) {
					if ( class_exists( 'NBUF_Security_Log' ) ) {
						NBUF_Security_Log::log_or_update(
							'ip_blocked',
							'critical',
							'Login blocked (out-of-band): IP address not authorized',
							array(
								'ip_address' => $nbuf_client_ip,
								'user_id'    => $user_id,
							)
						);
					}
					return new WP_Error(
						'ip_blocked',
						__( 'Access denied. Your IP address is not authorized to log in.', 'nobloat-user-foundry' )
					);
				}
			}
		}

		/* Admins bypass all restrictions. */
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( class_exists( 'NBUF_User_Data' ) ) {
			if ( NBUF_User_Data::is_disabled( $user_id ) ) {
				return new WP_Error(
					'user_disabled',
					__( 'Your account has been disabled. Please contact the site administrator.', 'nobloat-user-foundry' )
				);
			}
			if ( NBUF_User_Data::is_expired( $user_id ) ) {
				return new WP_Error(
					'account_expired',
					__( 'Your account has expired. Please contact the site administrator.', 'nobloat-user-foundry' )
				);
			}
			$require_verification = NBUF_Options::get( 'nbuf_require_verification', true );
			if ( $require_verification && ! NBUF_User_Data::is_verified( $user_id ) ) {
				return new WP_Error(
					'nbuf_unverified',
					__( 'Your email address has not been verified. Please check your inbox for a verification link.', 'nobloat-user-foundry' )
				);
			}
			if ( NBUF_User_Data::requires_approval( $user_id ) && ! NBUF_User_Data::is_approved( $user_id ) ) {
				return new WP_Error(
					'awaiting_approval',
					__( 'Your account is pending administrator approval.', 'nobloat-user-foundry' )
				);
			}
		}

		return true;
	}

	/**
	 * Verify the current request carries the user's correct password (re-auth).
	 *
	 * Shared helper for sensitive self-service actions (2FA changes, passkey
	 * delete/rename, app-password create/revoke). Reads $_POST['current_password']
	 * and checks it against the user's hash, with a per-user online-guess rate
	 * limit (10 attempts / 15 min, window anchored to the first failure) so a
	 * hijacked session cannot brute-force the password against these endpoints.
	 * The CALLER must verify its own nonce + capability before calling this.
	 *
	 * @param  int $user_id User whose password (the actor's) must be confirmed.
	 * @return bool True if the submitted password is correct and not rate-limited.
	 */
	public static function verify_reauth( int $user_id ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Raw password passed to wp_check_password(); sanitizing would corrupt it. Nonce is verified by the calling action handler before this helper is invoked.
		$password = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : '';

		if ( '' === $password ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$state_key = 'nbuf_reauth_state_' . $user_id;
		$state     = get_transient( $state_key );
		if ( ! is_array( $state ) || empty( $state['first_at'] ) ) {
			$state = array(
				'count'    => 0,
				'first_at' => time(),
			);
		}
		$window = 15 * MINUTE_IN_SECONDS;
		if ( time() - (int) $state['first_at'] > $window ) {
			$state = array(
				'count'    => 0,
				'first_at' => time(),
			);
		}
		if ( (int) $state['count'] >= 10 ) {
			return false;
		}

		$ok = wp_check_password( $password, $user->user_pass, $user_id );

		if ( $ok ) {
			delete_transient( $state_key );
			return true;
		}

		++$state['count'];
		$ttl_remaining = max( 60, $window - ( time() - (int) $state['first_at'] ) );
		set_transient( $state_key, $state, $ttl_remaining );

		return false;
	}
}
