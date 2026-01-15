<?php
/**
 * Magic Links
 *
 * Passwordless login via email magic links.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Magic_Links class.
 *
 * Handles passwordless login via magic links sent to user email.
 *
 * @since 1.5.2
 */
class NBUF_Magic_Links {

	/**
	 * Token type identifier for database storage.
	 */
	const TOKEN_TYPE = 'magic_link';

	/**
	 * Initialize magic links hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/* Register shortcode */
		add_shortcode( 'nbuf_magic_link_form', array( __CLASS__, 'shortcode_form' ) );
	}

	/**
	 * Check if magic links are enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_magic_links_enabled', false );
	}

	/**
	 * Get token expiration time in minutes.
	 *
	 * @since  1.5.2
	 * @return int Expiration in minutes.
	 */
	public static function get_expiration(): int {
		return absint( NBUF_Options::get( 'nbuf_magic_links_expiration', 15 ) );
	}

	/**
	 * Get rate limit per hour.
	 *
	 * @since  1.5.2
	 * @return int Maximum requests per hour.
	 */
	public static function get_rate_limit(): int {
		return absint( NBUF_Options::get( 'nbuf_magic_links_rate_limit', 3 ) );
	}

	/**
	 * Check if IP is rate limited (prevents DoS/enumeration).
	 *
	 * @since  1.5.2
	 * @return bool True if IP is rate limited.
	 */
	public static function is_ip_rate_limited(): bool {
		$ip            = self::get_client_ip();
		$ip_hash       = md5( $ip );
		$transient_key = 'nbuf_magic_link_ip_' . $ip_hash;
		$data          = get_transient( $transient_key );

		/* Allow 10 requests per IP per hour (regardless of email) */
		$ip_limit = 10;

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		return (int) $data['count'] >= $ip_limit;
	}

	/**
	 * Increment IP rate limit counter.
	 *
	 * @since 1.5.2
	 */
	public static function increment_ip_rate_limit(): void {
		$ip            = self::get_client_ip();
		$ip_hash       = md5( $ip );
		$transient_key = 'nbuf_magic_link_ip_' . $ip_hash;
		$data          = get_transient( $transient_key );

		if ( false === $data || ! is_array( $data ) ) {
			$data = array(
				'count'   => 0,
				'expires' => time() + HOUR_IN_SECONDS,
			);
		}

		$data['count'] = (int) $data['count'] + 1;

		$ttl = $data['expires'] - time();
		if ( $ttl > 0 ) {
			set_transient( $transient_key, $data, $ttl );
		}
	}

	/**
	 * Get remaining minutes until IP rate limit resets.
	 *
	 * @since  1.5.2
	 * @return int Minutes remaining.
	 */
	public static function get_ip_rate_limit_remaining_minutes(): int {
		$ip            = self::get_client_ip();
		$ip_hash       = md5( $ip );
		$transient_key = 'nbuf_magic_link_ip_' . $ip_hash;
		$data          = get_transient( $transient_key );

		if ( false === $data || ! is_array( $data ) || ! isset( $data['expires'] ) ) {
			return 0;
		}

		$remaining_seconds = $data['expires'] - time();
		return $remaining_seconds > 0 ? (int) ceil( $remaining_seconds / 60 ) : 0;
	}

	/**
	 * Get client IP address.
	 *
	 * @since  1.5.2
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		if ( class_exists( 'NBUF_Login_Limiting' ) && method_exists( 'NBUF_Login_Limiting', 'get_client_ip' ) ) {
			return NBUF_Login_Limiting::get_client_ip();
		}

		/* Fallback */
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return $ip;
	}

	/**
	 * Check if email is rate limited.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address.
	 * @return bool True if rate limited.
	 */
	public static function is_rate_limited( string $email ): bool {
		$email_hash    = md5( strtolower( $email ) );
		$transient_key = 'nbuf_magic_link_rate_' . $email_hash;
		$data          = get_transient( $transient_key );
		$rate_limit    = self::get_rate_limit();

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		return (int) $data['count'] >= $rate_limit;
	}

	/**
	 * Get remaining minutes until rate limit resets.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address.
	 * @return int Minutes remaining, or 0 if not rate limited.
	 */
	public static function get_rate_limit_remaining_minutes( string $email ): int {
		$email_hash    = md5( strtolower( $email ) );
		$transient_key = 'nbuf_magic_link_rate_' . $email_hash;
		$data          = get_transient( $transient_key );

		if ( false === $data || ! is_array( $data ) || ! isset( $data['expires'] ) ) {
			return 0;
		}

		$remaining_seconds = $data['expires'] - time();
		if ( $remaining_seconds <= 0 ) {
			return 0;
		}

		return (int) ceil( $remaining_seconds / 60 );
	}

	/**
	 * Increment rate limit counter.
	 *
	 * @since 1.5.2
	 * @param string $email Email address.
	 */
	public static function increment_rate_limit( string $email ): void {
		$email_hash    = md5( strtolower( $email ) );
		$transient_key = 'nbuf_magic_link_rate_' . $email_hash;
		$data          = get_transient( $transient_key );

		if ( false === $data || ! is_array( $data ) ) {
			$data = array(
				'count'   => 0,
				'expires' => time() + HOUR_IN_SECONDS,
			);
		}

		$data['count'] = (int) $data['count'] + 1;

		/* Calculate remaining TTL to preserve original expiration */
		$ttl = $data['expires'] - time();
		if ( $ttl > 0 ) {
			set_transient( $transient_key, $data, $ttl );
		}
	}

	/**
	 * Generate magic link token and send email.
	 *
	 * @since  1.5.2
	 * @param  string $email Email address.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_magic_link( string $email ) {
		/* Always increment rate limit - prevents timing attacks */
		self::increment_rate_limit( $email );

		/* Find user by email */
		$user = get_user_by( 'email', $email );

		/*
		 * SECURITY: Always perform the same operations regardless of whether user exists.
		 * This prevents email enumeration via timing attacks.
		 */

		/* Generate token regardless of user existence */
		$token = bin2hex( random_bytes( 32 ) );

		if ( $user ) {
			/* Store token in database */
			$expiration = gmdate( 'Y-m-d H:i:s', time() + ( self::get_expiration() * MINUTE_IN_SECONDS ) );

			global $wpdb;
			$table_name = $wpdb->prefix . NBUF_DB_TABLE;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom tokens table.
			$result = $wpdb->insert(
				$table_name,
				array(
					'user_id'    => $user->ID,
					'user_email' => $email,
					'token'      => $token,
					'expires_at' => $expiration,
					'verified'   => 0,
					'type'       => self::TOKEN_TYPE,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			/* Log insert failure for debugging */
			if ( false === $result ) {
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'magic_link_insert_failed',
						'error',
						'Failed to insert magic link token: ' . $wpdb->last_error,
						array(
							'email'      => $email,
							'table_name' => $table_name,
						)
					);
				}
				return new WP_Error( 'db_error', __( 'Failed to generate magic link. Please try again.', 'nobloat-user-foundry' ) );
			}

			/* Build magic link URL */
			$magic_link_url = self::get_magic_link_url( $token );

			/* Send email */
			self::send_magic_link_email( $user, $magic_link_url );

			/* Log the request */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'magic_link_sent',
					'info',
					'Magic link requested',
					array(
						'email'   => $email,
						'user_id' => $user->ID,
					),
					$user->ID
				);
			}
		} else {
			/*
			 * SECURITY: Perform dummy operations to match timing.
			 * Hash the token even though we won't store it.
			 */
			wp_hash_password( $token . microtime() );
		}

		/* Always return success to prevent email enumeration */
		return true;
	}

	/**
	 * Get magic link URL.
	 *
	 * @since  1.5.2
	 * @param  string $token Token.
	 * @return string Magic link URL.
	 */
	public static function get_magic_link_url( string $token ): string {
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			return NBUF_Universal_Router::get_url( 'magic-link' ) . '?token=' . $token;
		}

		/* Fallback using NBUF_URL helper with configurable base slug */
		return NBUF_URL::get( 'magic-link', array( 'token' => $token ) );
	}

	/**
	 * Send magic link email.
	 *
	 * @since 1.5.2
	 * @param WP_User $user           User object.
	 * @param string  $magic_link_url Magic link URL.
	 */
	private static function send_magic_link_email( WP_User $user, string $magic_link_url ): void {
		$expiration = self::get_expiration();
		$site_name  = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Magic Link for %s', 'nobloat-user-foundry' ),
			$site_name
		);

		$message = sprintf(
			/* translators: 1: user display name, 2: site name */
			__( 'Hi %1$s,', 'nobloat-user-foundry' ),
			$user->display_name ? $user->display_name : $user->user_login
		) . "\n\n";

		$message .= sprintf(
			/* translators: %s: site name */
			__( 'You requested a magic link to log into %s.', 'nobloat-user-foundry' ),
			$site_name
		) . "\n\n";

		$message .= __( 'Click the link below to log in:', 'nobloat-user-foundry' ) . "\n\n";
		$message .= $magic_link_url . "\n\n";

		$message .= sprintf(
			/* translators: %d: expiration time in minutes */
			__( 'This link expires in %d minutes and can only be used once.', 'nobloat-user-foundry' ),
			$expiration
		) . "\n\n";

		$message .= __( 'If you did not request this link, you can safely ignore this email.', 'nobloat-user-foundry' ) . "\n\n";

		$message .= sprintf(
			/* translators: %s: site name */
			__( '- The %s Team', 'nobloat-user-foundry' ),
			$site_name
		);

		/* Use plugin email system if available */
		if ( class_exists( 'NBUF_Email' ) && method_exists( 'NBUF_Email', 'send' ) ) {
			NBUF_Email::send( $user->user_email, $subject, $message );
		} else {
			wp_mail( $user->user_email, $subject, $message );
		}
	}

	/**
	 * Verify magic link token and log user in.
	 *
	 * @since  1.5.2
	 * @param  string $token Token to verify.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	public static function verify_magic_link( string $token ) {
		if ( empty( $token ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid magic link.', 'nobloat-user-foundry' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . NBUF_DB_TABLE;

		/* Find token */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tokens table.
		$token_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token = %s AND type = %s AND verified = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted constant.
				$token,
				self::TOKEN_TYPE
			)
		);

		if ( ! $token_data ) {
			return new WP_Error( 'invalid_token', __( 'This magic link is invalid or has already been used.', 'nobloat-user-foundry' ) );
		}

		/* Check expiration */
		if ( strtotime( $token_data->expires_at ) < time() ) {
			/* Delete expired token */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tokens table.
			$wpdb->delete( $table_name, array( 'id' => $token_data->id ), array( '%d' ) );

			return new WP_Error( 'expired_token', __( 'This magic link has expired. Please request a new one.', 'nobloat-user-foundry' ) );
		}

		/* Delete token after use (one-time use) */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tokens table.
		$wpdb->delete( $table_name, array( 'id' => $token_data->id ), array( '%d' ) );

		/* Get user */
		$user = get_user_by( 'id', $token_data->user_id );

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User account not found.', 'nobloat-user-foundry' ) );
		}

		/* Log the successful login */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log(
				'magic_link_used',
				'info',
				'User logged in via magic link',
				array(
					'user_id' => $user->ID,
					'email'   => $user->user_email,
				),
				$user->ID
			);
		}

		/* Log to audit log */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user->ID,
				'login_magic_link',
				'success',
				'User logged in via magic link',
				array( 'username' => $user->user_login )
			);
		}

		return $user->ID;
	}

	/**
	 * Handle magic link verification via URL.
	 *
	 * @since 1.5.2
	 */
	public static function maybe_handle_magic_link(): void {
		/* Only process on magic-link route */
		if ( ! class_exists( 'NBUF_Universal_Router' ) ) {
			return;
		}

		$current_view = NBUF_Universal_Router::get_current_view();
		if ( 'magic-link' !== $current_view ) {
			return;
		}

		/* Check if magic links are enabled */
		if ( ! self::is_enabled() ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token is verified separately via verify_magic_link().
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( empty( $token ) ) {
			/* Show request form instead */
			return;
		}

		/* Verify the token */
		$result = self::verify_magic_link( $token );

		if ( is_wp_error( $result ) ) {
			/* Store error for display */
			set_transient( 'nbuf_magic_link_error', $result->get_error_message(), 60 );

			/* Redirect to magic link page without token to show form */
			wp_safe_redirect( NBUF_Universal_Router::get_url( 'magic-link' ) );
			exit;
		}

		/* Log the user in */
		wp_set_auth_cookie( $result, true );
		wp_set_current_user( $result );

		/* Trigger wp_login action for other plugins */
		$user = get_user_by( 'id', $result );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a WordPress core hook.
		do_action( 'wp_login', $user->user_login, $user );

		/* Redirect based on login redirect settings */
		$redirect_url = self::get_redirect_url();
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get redirect URL based on login redirect settings.
	 *
	 * @since  1.5.2
	 * @return string Redirect URL.
	 */
	private static function get_redirect_url(): string {
		$login_redirect_setting = NBUF_Options::get( 'nbuf_login_redirect', 'account' );

		switch ( $login_redirect_setting ) {
			case 'account':
				$redirect_url = NBUF_Universal_Router::get_url( 'account' );
				break;
			case 'admin':
				$redirect_url = admin_url();
				break;
			case 'home':
				$redirect_url = home_url( '/' );
				break;
			case 'custom':
				$custom_url   = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );
				$redirect_url = $custom_url ? home_url( $custom_url ) : NBUF_Universal_Router::get_url( 'account' );
				break;
			default:
				$redirect_url = NBUF_Universal_Router::get_url( 'account' );
				break;
		}

		return $redirect_url;
	}

	/**
	 * Shortcode for magic link request form.
	 *
	 * @since  1.5.2
	 * @param  array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function shortcode_form( $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Standard shortcode signature.
		/* Check if enabled */
		if ( ! self::is_enabled() ) {
			return '<div class="nbuf-message nbuf-message-info">' .
				esc_html__( 'Magic links are not enabled.', 'nobloat-user-foundry' ) .
				'</div>';
		}

		/* If user is logged in, show message */
		if ( is_user_logged_in() ) {
			$account_url = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$account_url = NBUF_Universal_Router::get_url( 'account' );
			}

			return '<div class="nbuf-message nbuf-message-info">' .
				esc_html__( 'You are already logged in.', 'nobloat-user-foundry' ) .
				( $account_url ? ' <a href="' . esc_url( $account_url ) . '">' . esc_html__( 'Go to your account', 'nobloat-user-foundry' ) . '</a>' : '' ) .
				'</div>';
		}

		/* Check for error from failed verification */
		$error = get_transient( 'nbuf_magic_link_error' );
		if ( $error ) {
			delete_transient( 'nbuf_magic_link_error' );
		}

		/* Check for success message */
		$success = get_transient( 'nbuf_magic_link_success' );
		if ( $success ) {
			delete_transient( 'nbuf_magic_link_success' );
		}

		/* Handle form submission */
		if ( isset( $_POST['nbuf_magic_link_email'] ) && isset( $_POST['nbuf_magic_link_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_magic_link_nonce'] ) ), 'nbuf_magic_link_request' ) ) {
				$email = sanitize_email( wp_unslash( $_POST['nbuf_magic_link_email'] ) );

				if ( ! is_email( $email ) ) {
					$error = __( 'Please enter a valid email address.', 'nobloat-user-foundry' );
				} elseif ( self::is_ip_rate_limited() ) {
					/* IP rate limit - prevents DoS/enumeration attacks */
					$remaining = self::get_ip_rate_limit_remaining_minutes();
					$error     = sprintf(
						/* translators: %d: number of minutes to wait */
						__( 'Too many requests. Please wait %d minutes before trying again.', 'nobloat-user-foundry' ),
						max( 1, $remaining )
					);
				} elseif ( self::is_rate_limited( $email ) ) {
					/* Per-email rate limit */
					$remaining = self::get_rate_limit_remaining_minutes( $email );
					$error     = sprintf(
						/* translators: %d: number of minutes to wait */
						__( 'Too many requests for this email. Please wait %d minutes before trying again.', 'nobloat-user-foundry' ),
						max( 1, $remaining )
					);
				} else {
					/* Increment IP counter before processing (prevents enumeration via timing) */
					self::increment_ip_rate_limit();
					self::send_magic_link( $email );
					$success = __( 'If an account exists with that email, a magic link has been sent. Check your inbox!', 'nobloat-user-foundry' );
				}
			} else {
				$error = __( 'Security check failed. Please try again.', 'nobloat-user-foundry' );
			}
		}

		/* Build form */
		ob_start();
		?>
		<div class="nbuf-magic-link-form-wrapper">
			<?php if ( $error ) : ?>
				<div class="nbuf-message nbuf-message-error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<?php if ( $success ) : ?>
				<div class="nbuf-message nbuf-message-success"><?php echo esc_html( $success ); ?></div>
			<?php else : ?>
				<form method="post" class="nbuf-magic-link-form">
					<?php wp_nonce_field( 'nbuf_magic_link_request', 'nbuf_magic_link_nonce' ); ?>

					<h3><?php esc_html_e( 'Login with Magic Link', 'nobloat-user-foundry' ); ?></h3>
					<p class="nbuf-form-description">
						<?php esc_html_e( 'Enter your email address and we\'ll send you a secure link to log in instantly.', 'nobloat-user-foundry' ); ?>
					</p>

					<div class="nbuf-form-group">
						<label for="nbuf_magic_link_email" class="nbuf-form-label">
							<?php esc_html_e( 'Email Address', 'nobloat-user-foundry' ); ?>
						</label>
						<input type="email"
							   id="nbuf_magic_link_email"
							   name="nbuf_magic_link_email"
							   class="nbuf-form-input"
							   required
							   placeholder="<?php esc_attr_e( 'your@email.com', 'nobloat-user-foundry' ); ?>">
					</div>

					<button type="submit" class="nbuf-login-button">
						<?php esc_html_e( 'Send Magic Link', 'nobloat-user-foundry' ); ?>
					</button>
				</form>

				<?php
				/* Show login link */
				$login_url = '';
				if ( class_exists( 'NBUF_Universal_Router' ) ) {
					$login_url = NBUF_Universal_Router::get_url( 'login' );
				}
				if ( $login_url ) :
					?>
					<p class="nbuf-form-footer">
						<?php
						printf(
							/* translators: %s: login link */
							esc_html__( 'Prefer to use your password? %s', 'nobloat-user-foundry' ),
							'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log in here', 'nobloat-user-foundry' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Cleanup expired magic link tokens.
	 *
	 * Note: This is called by NBUF_Database::cleanup_expired() via daily cron,
	 * which cleans all expired tokens regardless of type.
	 *
	 * @since 1.5.2
	 */
	public static function cleanup_expired_tokens(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted constant.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}nbuf_tokens WHERE type = %s AND (expires_at < %s OR verified = 1)",
				self::TOKEN_TYPE,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}
}
