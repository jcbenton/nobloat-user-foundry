<?php
/**
 * User Impersonation
 *
 * Allows administrators to log in as other users for support purposes.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Impersonation class.
 *
 * Handles admin impersonation of users with full audit trail.
 *
 * @since 1.5.2
 */
class NBUF_Impersonation {

	/**
	 * Transient prefix for storing impersonation data.
	 */
	const TRANSIENT_PREFIX = 'nbuf_impersonation_';

	/**
	 * Initialize impersonation hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/* Add "Login as User" link to Users list */
		add_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_action' ), 10, 2 );

		/* Handle impersonation start */
		add_action( 'admin_init', array( __CLASS__, 'handle_impersonation_start' ) );

		/* Handle impersonation end */
		add_action( 'init', array( __CLASS__, 'handle_impersonation_end' ) );

		/* Display impersonation banner */
		add_action( 'wp_footer', array( __CLASS__, 'display_impersonation_banner' ) );
		add_action( 'admin_footer', array( __CLASS__, 'display_impersonation_banner' ) );

		/* Add banner styles */
		add_action( 'wp_head', array( __CLASS__, 'output_banner_styles' ) );
		add_action( 'admin_head', array( __CLASS__, 'output_banner_styles' ) );

		/* Clear sudo grant on logout. */
		add_action( 'wp_logout', array( __CLASS__, 'clear_sudo_on_logout' ), 10, 1 );
	}

	/**
	 * Clear the sudo-until user meta when an admin logs out.
	 *
	 * @since  1.6.6
	 * @param  int $user_id User logging out.
	 * @return void
	 */
	public static function clear_sudo_on_logout( $user_id ): void {
		if ( ! $user_id ) {
			return;
		}
		delete_user_meta( (int) $user_id, '_nbuf_impersonation_sudo_until' );
	}

	/**
	 * Check if impersonation is enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_impersonation_enabled', false );
	}

	/**
	 * Get the required capability for impersonation.
	 *
	 * @since  1.5.2
	 * @return string Capability name.
	 */
	public static function get_required_capability(): string {
		return NBUF_Options::get( 'nbuf_impersonation_capability', 'edit_users' );
	}

	/**
	 * Check if current user can impersonate.
	 *
	 * @since  1.5.2
	 * @return bool True if user can impersonate.
	 */
	public static function current_user_can_impersonate(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		return current_user_can( self::get_required_capability() );
	}

	/**
	 * Check if current user can impersonate a specific user.
	 *
	 * @since  1.5.2
	 * @param  int $target_user_id User ID to impersonate.
	 * @return bool True if user can impersonate the target.
	 */
	public static function can_impersonate_user( int $target_user_id ): bool {
		if ( ! self::current_user_can_impersonate() ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$target_user  = get_userdata( $target_user_id );

		if ( ! $target_user ) {
			return false;
		}

		/* Cannot impersonate yourself */
		if ( $current_user->ID === $target_user_id ) {
			return false;
		}

		/* Cannot impersonate super admins (multisite) */
		if ( is_multisite() && is_super_admin( $target_user_id ) ) {
			return false;
		}

		/*
		 * Cannot impersonate users who have any capability the impersonator
		 * lacks. Use user_can() rather than ->allcaps array_diff so dynamic
		 * caps granted at runtime by the user_has_cap filter (membership
		 * plugins, multi-role plugins, BuddyPress group caps, etc.) are
		 * honored. ->allcaps is the static role-derived map and silently
		 * drops anything granted by a filter, which would let the
		 * impersonator escalate when they assume the target's identity.
		 */
		foreach ( (array) $target_user->allcaps as $cap => $granted ) {
			if ( ! $granted ) {
				continue;
			}
			if ( ! user_can( $current_user, $cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get current impersonation data.
	 *
	 * @since  1.5.2
	 * @return array<string, mixed>|false Impersonation data or false if not impersonating.
	 */
	public static function get_impersonation_data() {
		$session_token = wp_get_session_token();
		if ( empty( $session_token ) ) {
			return false;
		}

		$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $session_token );
		$data          = get_transient( $transient_key );

		if ( ! $data || ! is_array( $data ) ) {
			return false;
		}

		/*
		 * SECURITY: require BOTH ip_address and user_agent to have been bound
		 * at start. The previous "only enforce when set" behaviour silently
		 * skipped the check whenever the bound value was missing (because the
		 * admin's original request lacked the header), letting an attacker
		 * with the cookie pass without a binding match.
		 */
		if ( empty( $data['ip_address'] ) || empty( $data['user_agent'] ) ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'impersonation_binding_missing',
					'warning',
					'Impersonation session invalidated — IP or User-Agent binding was empty',
					array(
						'admin_id'  => $data['original_user_id'] ?? 0,
						'target_id' => $data['target_user_id'] ?? 0,
					)
				);
			}
			delete_transient( $transient_key );
			return false;
		}

		/*
		 * Validate IP address binding. Use NBUF_IP::get_client_ip() to apply
		 * the same trusted-proxy / IPv6-canonicalisation logic that wrote the
		 * stored value at start, so a dual-stack client doesn't get logged
		 * out by representation drift (`2001:db8::1` vs the expanded form).
		 */
		$current_ip = class_exists( 'NBUF_IP' )
			? NBUF_IP::get_client_ip( true )
			: ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( $current_ip !== $data['ip_address'] ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'impersonation_ip_mismatch',
					'warning',
					'Impersonation session invalidated due to IP change',
					array(
						'original_ip' => $data['ip_address'],
						'current_ip'  => $current_ip,
						'admin_id'    => $data['original_user_id'] ?? 0,
						'target_id'   => $data['target_user_id'] ?? 0,
					)
				);
			}
			delete_transient( $transient_key );
			return false;
		}

		/*
		 * Validate User-Agent binding. hash_equals() avoids timing-based
		 * leaks on the comparison, though the value is not secret.
		 */
		$current_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! hash_equals( (string) $data['user_agent'], $current_ua ) ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'impersonation_ua_mismatch',
					'warning',
					'Impersonation session invalidated due to User-Agent change',
					array(
						'admin_id'  => $data['original_user_id'] ?? 0,
						'target_id' => $data['target_user_id'] ?? 0,
					)
				);
			}
			delete_transient( $transient_key );
			return false;
		}

		return $data;
	}

	/**
	 * Check if currently impersonating.
	 *
	 * @since  1.5.2
	 * @return bool True if impersonating.
	 */
	public static function is_impersonating(): bool {
		return false !== self::get_impersonation_data();
	}

	/**
	 * Add "Login as User" link to Users list row actions.
	 *
	 * @since  1.5.2
	 * @param  array<string, string> $actions Row actions.
	 * @param  WP_User               $user    User object.
	 * @return array<string, string> Modified actions.
	 */
	public static function add_user_row_action( array $actions, WP_User $user ): array {
		if ( ! self::can_impersonate_user( $user->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'nbuf_impersonate',
					'user_id' => $user->ID,
				),
				admin_url( 'users.php' )
			),
			'nbuf_impersonate_' . $user->ID
		);

		$actions['nbuf_impersonate'] = sprintf(
			'<a href="%s" style="color: #0073aa;">%s</a>',
			esc_url( $url ),
			esc_html__( 'Login as User', 'nobloat-user-foundry' )
		);

		return $actions;
	}

	/**
	 * Handle impersonation start request.
	 *
	 * @since 1.5.2
	 */
	public static function handle_impersonation_start(): void {
		if ( ! isset( $_GET['action'] ) || 'nbuf_impersonate' !== $_GET['action'] ) {
			return;
		}

		if ( ! isset( $_GET['user_id'] ) ) {
			return;
		}

		/* Prevent nested impersonation — would lose the return-to-original session */
		if ( self::is_impersonating() ) {
			wp_die( esc_html__( 'Cannot impersonate while already impersonating another user. End the current impersonation first.', 'nobloat-user-foundry' ) );
		}

		$target_user_id = absint( $_GET['user_id'] );

		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'nbuf_impersonate_' . $target_user_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Check permission */
		if ( ! self::can_impersonate_user( $target_user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to impersonate this user.', 'nobloat-user-foundry' ) );
		}

		$current_user = wp_get_current_user();
		$target_user  = get_userdata( $target_user_id );

		if ( ! $target_user ) {
			wp_die( esc_html__( 'User not found.', 'nobloat-user-foundry' ) );
		}

		/*
		 * SECURITY: sudo-step. Require fresh password re-entry within
		 * a short window before allowing impersonation to start. Without
		 * this, a stolen admin session cookie immediately grants the
		 * attacker the ability to pivot to any user the admin can
		 * impersonate. The sudo timestamp is stored in user meta and
		 * cleared on logout.
		 */
		$sudo_window = (int) apply_filters( 'nbuf_impersonation_sudo_seconds', 10 * MINUTE_IN_SECONDS );
		$sudo_until  = (int) get_user_meta( $current_user->ID, '_nbuf_impersonation_sudo_until', true );
		if ( $sudo_until < time() ) {
			/* Render the sudo form (renders + exits). */
			self::render_sudo_form( $target_user_id, $sudo_window );
		}
		/* Sudo gate passed — invalidate the token (single-use within window). */
		delete_user_meta( $current_user->ID, '_nbuf_impersonation_sudo_until' );

		/* Log impersonation start to admin audit log */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				$current_user->ID,
				'impersonation',
				'start',
				sprintf(
					/* translators: 1: admin username, 2: target username */
					__( 'Admin %1$s started impersonating user %2$s', 'nobloat-user-foundry' ),
					$current_user->user_login,
					$target_user->user_login
				),
				$target_user_id,
				array(
					'admin_id'        => $current_user->ID,
					'admin_username'  => $current_user->user_login,
					'target_id'       => $target_user_id,
					'target_username' => $target_user->user_login,
				)
			);
		}

		/* Log to security log */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log(
				'impersonation_start',
				'warning',
				sprintf(
					/* translators: 1: admin username, 2: target username */
					__( 'Admin %1$s started impersonating %2$s', 'nobloat-user-foundry' ),
					$current_user->user_login,
					$target_user->user_login
				),
				array(
					'admin_id'        => $current_user->ID,
					'admin_username'  => $current_user->user_login,
					'target_id'       => $target_user_id,
					'target_username' => $target_user->user_login,
				),
				$target_user_id
			);
		}

		/*
		 * Capture the original admin's session token BEFORE clearing the
		 * cookie. We hash and store it in the transient so handle_impersonation_end
		 * can verify the resume-target really matches an active admin session.
		 * Without this binding, an attacker who can write a transient (e.g.,
		 * via object-cache compromise) could call handle_impersonation_end
		 * with a forged transient and have wp_set_auth_cookie() executed for
		 * an arbitrary user_id holding the impersonation capability.
		 */
		$original_session_token      = wp_get_session_token();
		$original_session_token_hash = $original_session_token ? hash( 'sha256', $original_session_token ) : '';

		/* Clear current session and create new one for target user */
		wp_clear_auth_cookie();

		/* Generate a new session token for the target user */
		$manager       = WP_Session_Tokens::get_instance( $target_user_id );
		$session_token = $manager->create( time() + DAY_IN_SECONDS );

		/* Set auth cookie for target user with our token */
		wp_set_auth_cookie( $target_user_id, false, '', $session_token );
		wp_set_current_user( $target_user_id );

		/*
		 * Store impersonation data using the token we created.
		 * SECURITY: Bind to IP address (via NBUF_IP for normalised IPv6 and
		 * trusted-proxy resolution), user agent, and the original admin's
		 * session-token hash.
		 */
		$client_ip  = class_exists( 'NBUF_IP' )
			? NBUF_IP::get_client_ip( true )
			: ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$expiration = 2 * HOUR_IN_SECONDS; /* Shorter expiration for security (2 hours instead of 1 day) */

		$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $session_token );
		set_transient(
			$transient_key,
			array(
				'original_user_id'            => $current_user->ID,
				'original_username'           => $current_user->user_login,
				'original_session_token_hash' => $original_session_token_hash,
				'target_user_id'              => $target_user_id,
				'target_username'             => $target_user->user_login,
				'started_at'                  => time(),
				'ip_address'                  => $client_ip,
				'user_agent'                  => $user_agent,
			),
			$expiration
		);

		/* Redirect to frontend (home page) */
		wp_safe_redirect( home_url() );
		exit;
	}

	/**
	 * Render the sudo (password re-entry) form before impersonation starts.
	 *
	 * Handles its own POST verification — on successful re-auth, sets the
	 * sudo-until user meta and reloads the original impersonation start URL,
	 * which then walks past the sudo gate the second time around.
	 *
	 * @since  1.6.6
	 * @param  int $target_user_id Target user ID being impersonated.
	 * @param  int $sudo_window    Seconds the sudo grant should remain valid.
	 * @return void
	 */
	private static function render_sudo_form( int $target_user_id, int $sudo_window ): void {
		$current_user = wp_get_current_user();
		$error        = '';

		// Process re-auth submission.
		if ( isset( $_POST['nbuf_impersonation_sudo'] ) && isset( $_POST['nbuf_impersonation_sudo_nonce'] ) ) {
			$nonce_ok = wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['nbuf_impersonation_sudo_nonce'] ) ),
				'nbuf_impersonation_sudo_' . $target_user_id
			);
			if ( ! $nonce_ok ) {
				$error = __( 'Security check failed. Please try again.', 'nobloat-user-foundry' );
			} else {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are not sanitized; verified below.
				$pwd = isset( $_POST['nbuf_impersonation_sudo_pwd'] ) ? wp_unslash( $_POST['nbuf_impersonation_sudo_pwd'] ) : '';
				if ( '' === $pwd || ! wp_check_password( $pwd, $current_user->user_pass, $current_user->ID ) ) {
					$error = __( 'Password is incorrect.', 'nobloat-user-foundry' );
					if ( class_exists( 'NBUF_Security_Log' ) ) {
						NBUF_Security_Log::log(
							'impersonation_sudo_failed',
							'warning',
							'Failed sudo re-auth before impersonation start',
							array(
								'admin_id'  => $current_user->ID,
								'target_id' => $target_user_id,
							)
						);
					}
				} else {
					update_user_meta( $current_user->ID, '_nbuf_impersonation_sudo_until', time() + $sudo_window );
					$reload = wp_nonce_url(
						add_query_arg(
							array(
								'action'  => 'nbuf_impersonate',
								'user_id' => $target_user_id,
							),
							admin_url( 'users.php' )
						),
						'nbuf_impersonate_' . $target_user_id
					);
					wp_safe_redirect( $reload );
					exit;
				}
			}
		}

		$target_user = get_userdata( $target_user_id );
		$target_name = $target_user ? $target_user->display_name : '';

		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Confirm Impersonation', 'nobloat-user-foundry' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: target user display name */
					esc_html__( 'You are about to log in as %s. Re-enter your administrator password to continue.', 'nobloat-user-foundry' ),
					'<strong>' . esc_html( $target_name ) . '</strong>'  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML fragment.
				);
				?>
			</p>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'nbuf_impersonation_sudo_' . $target_user_id, 'nbuf_impersonation_sudo_nonce' ); ?>
				<input type="hidden" name="nbuf_impersonation_sudo" value="1">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="nbuf_impersonation_sudo_pwd"><?php esc_html_e( 'Your password', 'nobloat-user-foundry' ); ?></label>
						</th>
						<td>
							<input type="password" name="nbuf_impersonation_sudo_pwd" id="nbuf_impersonation_sudo_pwd" class="regular-text" autocomplete="current-password" required>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm and Continue', 'nobloat-user-foundry' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?></a>
				</p>
			</form>
		</div>
		<?php
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	/**
	 * Handle impersonation end request.
	 *
	 * @since 1.5.2
	 */
	public static function handle_impersonation_end(): void {
		if ( ! isset( $_GET['action'] ) || 'nbuf_end_impersonation' !== $_GET['action'] ) {
			return;
		}

		/* Verify nonce — show error instead of silently trapping the admin in impersonation */
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'nbuf_end_impersonation' ) ) {
			wp_die( esc_html__( 'Security check failed. Please use the End Impersonation link again.', 'nobloat-user-foundry' ) );
		}

		$impersonation_data = self::get_impersonation_data();
		if ( ! $impersonation_data ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		$original_user_id = $impersonation_data['original_user_id'];
		$original_user    = get_userdata( $original_user_id );
		$target_user      = get_userdata( $impersonation_data['target_user_id'] );

		/* Log impersonation end */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) && $original_user && $target_user ) {
			NBUF_Admin_Audit_Log::log(
				$original_user_id,
				'impersonation',
				'end',
				sprintf(
					/* translators: 1: admin username, 2: target username */
					__( 'Admin %1$s ended impersonation of user %2$s', 'nobloat-user-foundry' ),
					$original_user->user_login,
					$target_user->user_login
				),
				$impersonation_data['target_user_id'],
				array(
					'admin_id'        => $original_user_id,
					'admin_username'  => $original_user->user_login,
					'target_id'       => $impersonation_data['target_user_id'],
					'target_username' => $target_user->user_login,
					'duration'        => time() - $impersonation_data['started_at'],
				)
			);
		}

		/*
		 * SECURITY: verify the original admin still has the impersonation
		 * capability BEFORE we destroy any session — otherwise a permission
		 * change mid-impersonation locks the admin out entirely. Also verify
		 * the bound original-session-token hash matches a still-active
		 * session for that user, defeating transient-injection attacks that
		 * would otherwise let an attacker call wp_set_auth_cookie() for any
		 * user_id holding the impersonation cap.
		 */
		$original_user = get_userdata( $original_user_id );
		if ( ! $original_user || ! user_can( $original_user, self::get_required_capability() ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$bound_token_hash = isset( $impersonation_data['original_session_token_hash'] )
			? (string) $impersonation_data['original_session_token_hash']
			: '';
		$binding_ok       = false;
		if ( '' !== $bound_token_hash ) {
			/*
			 * The stored hash is sha256( raw_token ) — captured at start
			 * via hash('sha256', wp_get_session_token()). WordPress's
			 * WP_User_Meta_Session_Tokens::get_sessions() returns the
			 * sessions array keyed by `hash_token($token)`, which is
			 * itself sha256(raw). So the foreach key already IS sha256(raw).
			 * The previous comparison ran sha256() on it again, producing
			 * sha256(sha256(raw)) and thus mismatching every legitimate
			 * end-impersonation. Compare against the stored verifier
			 * directly.
			 */
			$original_manager = WP_Session_Tokens::get_instance( $original_user_id );
			$all_tokens       = $original_manager->get_all();
			foreach ( (array) $all_tokens as $stored_token => $session ) {
				if ( hash_equals( $bound_token_hash, (string) $stored_token ) ) {
					$binding_ok = true;
					break;
				}
			}
		}
		if ( ! $binding_ok ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'impersonation_end_binding_invalid',
					'critical',
					'Impersonation end refused — bound original-session token does not match any active session',
					array(
						'admin_id'  => $original_user_id,
						'target_id' => $impersonation_data['target_user_id'] ?? 0,
					)
				);
			}
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		/* Destroy the target user's session token created during impersonation */
		$session_token = wp_get_session_token();
		if ( $session_token && isset( $impersonation_data['target_user_id'] ) ) {
			$target_manager = WP_Session_Tokens::get_instance( $impersonation_data['target_user_id'] );
			$target_manager->destroy( $session_token );

			/* Clear impersonation transient — only when we have a real session token. */
			$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $session_token );
			delete_transient( $transient_key );
		}

		/* Clear current session and restore original user. */
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $original_user_id, false );
		wp_set_current_user( $original_user_id );

		/* Redirect to Users list */
		wp_safe_redirect( admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Display impersonation banner.
	 *
	 * @since 1.5.2
	 */
	public static function display_impersonation_banner(): void {
		$impersonation_data = self::get_impersonation_data();
		if ( ! $impersonation_data ) {
			return;
		}

		$end_url = wp_nonce_url(
			add_query_arg( 'action', 'nbuf_end_impersonation', home_url() ),
			'nbuf_end_impersonation'
		);

		$original_user = get_userdata( $impersonation_data['original_user_id'] );
		?>
		<div id="nbuf-impersonation-banner">
			<span class="nbuf-impersonation-icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
					<circle cx="12" cy="7" r="4"></circle>
				</svg>
			</span>
			<span class="nbuf-impersonation-text">
				<?php
				printf(
					/* translators: %s: username being impersonated */
					esc_html__( 'You are logged in as %s', 'nobloat-user-foundry' ),
					'<strong>' . esc_html( $impersonation_data['target_username'] ) . '</strong>'
				);
				if ( $original_user ) {
					echo ' <span class="nbuf-impersonation-note">';
					printf(
						/* translators: %s: original admin username */
						esc_html__( '(impersonating from %s)', 'nobloat-user-foundry' ),
						esc_html( $original_user->user_login )
					);
					echo '</span>';
				}
				?>
			</span>
			<a href="<?php echo esc_url( $end_url ); ?>" class="nbuf-impersonation-end">
				<?php esc_html_e( 'End Impersonation', 'nobloat-user-foundry' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Output banner styles.
	 *
	 * @since 1.5.2
	 */
	public static function output_banner_styles(): void {
		if ( ! self::is_impersonating() ) {
			return;
		}
		?>
		<style>
			#nbuf-impersonation-banner {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				z-index: 999999;
				background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%);
				color: #fff;
				padding: 10px 20px;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 12px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				font-size: 14px;
				box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
			}

			.admin-bar #nbuf-impersonation-banner {
				top: 32px;
			}

			@media screen and (max-width: 782px) {
				.admin-bar #nbuf-impersonation-banner {
					top: 46px;
				}
			}

			#nbuf-impersonation-banner .nbuf-impersonation-icon {
				display: flex;
				align-items: center;
			}

			#nbuf-impersonation-banner .nbuf-impersonation-text {
				flex: 1;
				text-align: center;
			}

			#nbuf-impersonation-banner .nbuf-impersonation-note {
				opacity: 0.8;
				font-size: 12px;
			}

			#nbuf-impersonation-banner .nbuf-impersonation-end {
				background: rgba(255, 255, 255, 0.2);
				color: #fff;
				text-decoration: none;
				padding: 6px 16px;
				border-radius: 4px;
				font-weight: 500;
				transition: background 0.2s;
			}

			#nbuf-impersonation-banner .nbuf-impersonation-end:hover {
				background: rgba(255, 255, 255, 0.3);
			}

			/* Push down content */
			body.nbuf-impersonating {
				margin-top: 44px !important;
			}

			.admin-bar body.nbuf-impersonating {
				margin-top: 44px !important;
			}
		</style>
		<script>
			document.body.classList.add('nbuf-impersonating');
		</script>
		<?php
	}
}
