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

		/* Cannot impersonate users with higher capabilities */
		if ( user_can( $target_user, 'manage_options' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
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
		 * SECURITY: Validate IP address binding to prevent session hijacking.
		 * If the IP address doesn't match, invalidate the impersonation session.
		 */
		if ( isset( $data['ip_address'] ) && ! empty( $data['ip_address'] ) ) {
			$current_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			if ( $current_ip !== $data['ip_address'] ) {
				/* IP mismatch - log security event and invalidate session */
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
				/* Delete the transient to end the impersonation */
				delete_transient( $transient_key );
				return false;
			}
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
		 * SECURITY: Bind to IP address and user agent to prevent session hijacking.
		 */
		$client_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$expiration = 2 * HOUR_IN_SECONDS; /* Shorter expiration for security (2 hours instead of 1 day) */

		$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $session_token );
		set_transient(
			$transient_key,
			array(
				'original_user_id'  => $current_user->ID,
				'original_username' => $current_user->user_login,
				'target_user_id'    => $target_user_id,
				'target_username'   => $target_user->user_login,
				'started_at'        => time(),
				'ip_address'        => $client_ip,
				'user_agent'        => $user_agent,
			),
			$expiration
		);

		/* Redirect to frontend (home page) */
		wp_safe_redirect( home_url() );
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

		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'nbuf_end_impersonation' ) ) {
			return;
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

		/* Clear impersonation transient */
		$session_token = wp_get_session_token();
		$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $session_token );
		delete_transient( $transient_key );

		/* Clear current session */
		wp_clear_auth_cookie();

		/* Restore original user session */
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
