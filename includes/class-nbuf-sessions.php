<?php
/**
 * Session Management
 *
 * Allows users to view and revoke their active login sessions.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Sessions class.
 *
 * Handles session management for users.
 *
 * @since 1.5.2
 */
class NBUF_Sessions {


	/**
	 * Initialize session management hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/* AJAX handlers for session management */
		add_action( 'wp_ajax_nbuf_revoke_session', array( __CLASS__, 'ajax_revoke_session' ) );
		add_action( 'wp_ajax_nbuf_revoke_other_sessions', array( __CLASS__, 'ajax_revoke_other_sessions' ) );
	}

	/**
	 * Check if session management is enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_session_management_enabled', true );
	}

	/**
	 * Get all sessions for a user.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return array Array of session data.
	 */
	public static function get_user_sessions( int $user_id ): array {
		/*
		 * Get session tokens directly from user meta.
		 * WP_Session_Tokens::get_all() can sometimes re-index the array,
		 * losing the token hash keys. Using raw meta preserves them.
		 */
		$tokens = get_user_meta( $user_id, 'session_tokens', true );

		if ( empty( $tokens ) || ! is_array( $tokens ) ) {
			return array();
		}

		$sessions           = array();
		$current_token      = wp_get_session_token();
		$current_token_hash = $current_token ? hash( 'sha256', $current_token ) : '';

		foreach ( $tokens as $token_hash => $session ) {
			$token_hash_str = (string) $token_hash;
			$is_current     = $current_token_hash && hash_equals( $current_token_hash, $token_hash_str );
			$sessions[]     = array(
				'token_hash'  => $token_hash_str,
				'is_current'  => $is_current,
				'login_time'  => isset( $session['login'] ) ? $session['login'] : 0,
				'expiration'  => isset( $session['expiration'] ) ? $session['expiration'] : 0,
				'ip'          => isset( $session['ip'] ) ? $session['ip'] : '',
				'user_agent'  => isset( $session['ua'] ) ? $session['ua'] : '',
				'device_info' => self::parse_user_agent( isset( $session['ua'] ) ? $session['ua'] : '' ),
			);
		}

		/* Sort by login time descending (most recent first) */
		usort(
			$sessions,
			function ( $a, $b ) {
				return $b['login_time'] - $a['login_time'];
			}
		);

		return $sessions;
	}

	/**
	 * Parse user agent string to extract device/browser info.
	 *
	 * @since  1.5.2
	 * @param  string $ua User agent string.
	 * @return array Device info.
	 */
	public static function parse_user_agent( string $ua ): array {
		$browser  = __( 'Unknown Browser', 'nobloat-user-foundry' );
		$platform = __( 'Unknown Device', 'nobloat-user-foundry' );

		if ( empty( $ua ) ) {
			return array(
				'browser'  => $browser,
				'platform' => $platform,
				'full'     => __( 'Unknown', 'nobloat-user-foundry' ),
			);
		}

		/* Detect browser */
		if ( preg_match( '/Edg(?:e|A|iOS)?\/[\d.]+/i', $ua ) ) {
			$browser = 'Edge';
		} elseif ( preg_match( '/OPR\/|Opera/i', $ua ) ) {
			$browser = 'Opera';
		} elseif ( preg_match( '/Firefox\/[\d.]+/i', $ua ) ) {
			$browser = 'Firefox';
		} elseif ( preg_match( '/Chrome\/[\d.]+/i', $ua ) && ! preg_match( '/Chromium/i', $ua ) ) {
			$browser = 'Chrome';
		} elseif ( preg_match( '/Safari\/[\d.]+/i', $ua ) && ! preg_match( '/Chrome/i', $ua ) ) {
			$browser = 'Safari';
		} elseif ( preg_match( '/MSIE|Trident/i', $ua ) ) {
			$browser = 'Internet Explorer';
		}

		/* Detect platform/device */
		if ( preg_match( '/iPhone/i', $ua ) ) {
			$platform = 'iPhone';
		} elseif ( preg_match( '/iPad/i', $ua ) ) {
			$platform = 'iPad';
		} elseif ( preg_match( '/Android/i', $ua ) ) {
			if ( preg_match( '/Mobile/i', $ua ) ) {
				$platform = 'Android Phone';
			} else {
				$platform = 'Android Tablet';
			}
		} elseif ( preg_match( '/Windows/i', $ua ) ) {
			$platform = 'Windows';
		} elseif ( preg_match( '/Macintosh|Mac OS X/i', $ua ) ) {
			$platform = 'Mac';
		} elseif ( preg_match( '/Linux/i', $ua ) ) {
			$platform = 'Linux';
		}

		return array(
			'browser'  => $browser,
			'platform' => $platform,
			'full'     => sprintf(
				/* translators: 1: browser name, 2: platform/device name */
				__( '%1$s on %2$s', 'nobloat-user-foundry' ),
				$browser,
				$platform
			),
		);
	}

	/**
	 * Revoke a specific session.
	 *
	 * @since  1.5.2
	 * @param  int    $user_id    User ID.
	 * @param  string $token_hash Session token hash.
	 * @return bool True if revoked.
	 */
	public static function revoke_session( int $user_id, string $token_hash ): bool {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$tokens  = $manager->get_all();

		if ( ! isset( $tokens[ $token_hash ] ) ) {
			return false;
		}

		$manager->destroy( $token_hash );

		/* Log revocation */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'session_revoked',
				'success',
				'User revoked a login session',
				array( 'token_hash' => substr( $token_hash, 0, 16 ) . '...' )
			);
		}

		return true;
	}

	/**
	 * Revoke all sessions except the current one.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return int Number of sessions revoked.
	 */
	public static function revoke_other_sessions( int $user_id ): int {
		$manager       = WP_Session_Tokens::get_instance( $user_id );
		$current_token = wp_get_session_token();

		if ( empty( $current_token ) ) {
			return 0;
		}

		$manager->destroy_others( $current_token );

		/* Log revocation */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'sessions_revoked_all',
				'success',
				'User revoked all other login sessions'
			);
		}

		return 1; /* WordPress doesn't return count */
	}

	/**
	 * AJAX handler for revoking a single session.
	 *
	 * @since 1.5.2
	 */
	public static function ajax_revoke_session(): void {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_sessions', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		/* Check user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		$user_id    = get_current_user_id();
		$token_hash = isset( $_POST['token_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['token_hash'] ) ) : '';

		if ( empty( $token_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'nobloat-user-foundry' ) ) );
		}

		/* Prevent revoking current session */
		$current_token      = wp_get_session_token();
		$current_token_hash = hash( 'sha256', $current_token );

		if ( hash_equals( $current_token_hash, $token_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot revoke your current session.', 'nobloat-user-foundry' ) ) );
		}

		/* Revoke the session */
		if ( self::revoke_session( $user_id, $token_hash ) ) {
			wp_send_json_success( array( 'message' => __( 'Session revoked.', 'nobloat-user-foundry' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'nobloat-user-foundry' ) ) );
		}
	}

	/**
	 * AJAX handler for revoking all other sessions.
	 *
	 * @since 1.5.2
	 */
	public static function ajax_revoke_other_sessions(): void {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_sessions', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		/* Check user is logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();

		self::revoke_other_sessions( $user_id );

		wp_send_json_success( array( 'message' => __( 'All other sessions have been logged out.', 'nobloat-user-foundry' ) ) );
	}

	/**
	 * Build sessions tab HTML for account page.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return string HTML output.
	 */
	public static function build_sessions_tab_html( int $user_id ): string {
		$sessions = self::get_user_sessions( $user_id );

		$html  = '<div class="nbuf-account-section">';
		$html .= '<h3>' . esc_html__( 'Active Sessions', 'nobloat-user-foundry' ) . '</h3>';
		$html .= '<p class="nbuf-method-description">' . esc_html__( 'These are the devices currently logged into your account. Revoke any sessions you do not recognize.', 'nobloat-user-foundry' ) . '</p>';

		if ( empty( $sessions ) ) {
			$html .= '<p class="nbuf-no-sessions">' . esc_html__( 'No active sessions found.', 'nobloat-user-foundry' ) . '</p>';
		} else {
			/* Add "Revoke All Other Sessions" button if more than one session */
			if ( count( $sessions ) > 1 ) {
				$html .= '<div class="nbuf-sessions-actions nbuf-section-spacing">';
				$html .= '<button type="button" class="nbuf-button nbuf-button-secondary nbuf-revoke-all-sessions" data-nonce="' . esc_attr( wp_create_nonce( 'nbuf_sessions' ) ) . '">';
				$html .= esc_html__( 'Log Out All Other Sessions', 'nobloat-user-foundry' );
				$html .= '</button>';
				$html .= '</div>';
			}

			$html .= '<div class="nbuf-sessions-list">';

			foreach ( $sessions as $session ) {
				$is_current = $session['is_current'];
				$device     = $session['device_info'];

				$html .= '<div class="nbuf-session-item' . ( $is_current ? ' nbuf-session-current' : '' ) . '">';
				$html .= '<div class="nbuf-session-icon">';
				$html .= self::get_device_icon( $device['platform'] );
				$html .= '</div>';
				$html .= '<div class="nbuf-session-details">';
				$html .= '<div class="nbuf-session-device">' . esc_html( $device['full'] ) . '</div>';

				/* IP address */
				if ( ! empty( $session['ip'] ) ) {
					$html .= '<div class="nbuf-session-ip">' . esc_html( $session['ip'] ) . '</div>';
				}

				/* Login time */
				if ( $session['login_time'] ) {
					$html .= '<div class="nbuf-session-time">';
					$html .= sprintf(
						/* translators: %s: relative time (e.g., "2 hours ago") */
						esc_html__( 'Logged in %s', 'nobloat-user-foundry' ),
						esc_html( human_time_diff( $session['login_time'], time() ) . ' ' . __( 'ago', 'nobloat-user-foundry' ) )
					);
					$html .= '</div>';
				}

				$html .= '</div>'; /* .nbuf-session-details */
				$html .= '<div class="nbuf-session-actions">';

				if ( $is_current ) {
					$html .= '<span class="nbuf-session-current-badge">' . esc_html__( 'Current Session', 'nobloat-user-foundry' ) . '</span>';
				} else {
					$html .= '<button type="button" class="nbuf-button nbuf-button-small nbuf-button-danger nbuf-revoke-session" ';
					$html .= 'data-token="' . esc_attr( $session['token_hash'] ) . '" ';
					$html .= 'data-nonce="' . esc_attr( wp_create_nonce( 'nbuf_sessions' ) ) . '">';
					$html .= esc_html__( 'Revoke', 'nobloat-user-foundry' );
					$html .= '</button>';
				}

				$html .= '</div>'; /* .nbuf-session-actions */
				$html .= '</div>'; /* .nbuf-session-item */
			}

			$html .= '</div>'; /* .nbuf-sessions-list */
		}

		$html .= '</div>'; /* .nbuf-account-section */

		return $html;
	}

	/**
	 * Get SVG icon for device type.
	 *
	 * @since  1.5.2
	 * @param  string $platform Platform name.
	 * @return string SVG HTML.
	 */
	private static function get_device_icon( string $platform ): string {
		$platform_lower = strtolower( $platform );

		/* Mobile devices */
		if ( in_array( $platform_lower, array( 'iphone', 'android phone' ), true ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>';
		}

		/* Tablets */
		if ( in_array( $platform_lower, array( 'ipad', 'android tablet' ), true ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>';
		}

		/* Desktop (default) */
		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
	}
}

/* Initialize AJAX handlers */
NBUF_Sessions::init();
