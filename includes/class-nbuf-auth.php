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
	 * unverified / pending approval); the forced/expired/weak PASSWORD gates are
	 * handled separately by NBUF_Password_Expiration::maybe_get_change_redirect().
	 *
	 * @param  int $user_id User ID.
	 * @return true|WP_Error True if the user may proceed, WP_Error otherwise.
	 */
	public static function enforce_login_status( int $user_id ) {
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
}
