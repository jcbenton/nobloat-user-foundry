<?php
/**
 * Terms of Service Tracking
 *
 * Manages ToS versions, user acceptances, and enforcement.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * NBUF_ToS class.
 *
 * Handles Terms of Service version management and acceptance tracking.
 *
 * @since 1.5.2
 */
class NBUF_ToS {

	/**
	 * Initialize ToS hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/* Check ToS acceptance on login */
		add_action( 'wp_login', array( __CLASS__, 'check_tos_on_login' ), 20, 2 );

		/* Redirect to acceptance page if needed - must run before Universal Router (priority 0) */
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_to_acceptance' ), -10 );

		/* Handle ToS acceptance form submission */
		add_action( 'admin_post_nbuf_accept_tos', array( __CLASS__, 'handle_acceptance' ) );
		add_action( 'admin_post_nopriv_nbuf_accept_tos', array( __CLASS__, 'handle_acceptance' ) );
	}

	/**
	 * Check if ToS tracking is enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_tos_enabled', false );
	}

	/**
	 * Check if ToS is required on login.
	 *
	 * @since  1.5.2
	 * @return bool True if required.
	 */
	public static function is_required_on_login(): bool {
		return (bool) NBUF_Options::get( 'nbuf_tos_require_on_login', true );
	}

	/**
	 * Get grace period in hours.
	 *
	 * @since  1.5.2
	 * @return int Grace period hours.
	 */
	public static function get_grace_period_hours(): int {
		return (int) NBUF_Options::get( 'nbuf_tos_grace_period_hours', 24 );
	}

	/**
	 * Get the currently active ToS version.
	 *
	 * @since  1.5.2
	 * @return object|null ToS version row or null.
	 */
	public static function get_active_version() {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';
		$now   = current_time( 'mysql', false ); /* Use local time to match stored effective_date */

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE is_active = 1
				AND effective_date <= %s
				ORDER BY effective_date DESC
				LIMIT 1",
				$table,
				$now
			)
		);
	}

	/**
	 * Get a specific ToS version by ID.
	 *
	 * @since  1.5.2
	 * @param  int $version_id Version ID.
	 * @return object|null ToS version row or null.
	 */
	public static function get_version( int $version_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$version_id
			)
		);
	}

	/**
	 * Get all ToS versions.
	 *
	 * @since  1.5.2
	 * @return array Array of version rows.
	 */
	public static function get_all_versions(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC',
				$table
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Create a new ToS version.
	 *
	 * @since  1.5.2
	 * @param  array $data Version data (version, title, content, effective_date, is_active).
	 * @return int|false Insert ID or false on failure.
	 */
	public static function create_version( array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';

		/* Convert datetime-local format (2024-01-14T10:30) to MySQL format (2024-01-14 10:30:00) */
		$effective_date = $data['effective_date'] ?? '';
		if ( $effective_date ) {
			$effective_date = str_replace( 'T', ' ', $effective_date );
			if ( strlen( $effective_date ) === 16 ) {
				$effective_date .= ':00'; /* Add seconds if missing */
			}
		} else {
			$effective_date = current_time( 'mysql', false );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'version'        => sanitize_text_field( $data['version'] ?? '' ),
				'title'          => sanitize_text_field( $data['title'] ?? '' ),
				'content'        => wp_kses_post( $data['content'] ?? '' ),
				'effective_date' => $effective_date,
				'created_by'     => get_current_user_id(),
				'is_active'      => ! empty( $data['is_active'] ) ? 1 : 0,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( $result ) {
			$insert_id = $wpdb->insert_id;

			/* If this version is active, deactivate others */
			if ( ! empty( $data['is_active'] ) ) {
				self::set_active_version( $insert_id );
			}

			return $insert_id;
		}

		return false;
	}

	/**
	 * Update a ToS version.
	 *
	 * @since  1.5.2
	 * @param  int   $version_id Version ID.
	 * @param  array $data       Version data.
	 * @return bool True on success.
	 */
	public static function update_version( int $version_id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';

		$update_data = array();
		$formats     = array();

		if ( isset( $data['version'] ) ) {
			$update_data['version'] = sanitize_text_field( $data['version'] );
			$formats[]              = '%s';
		}
		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$formats[]            = '%s';
		}
		if ( isset( $data['content'] ) ) {
			$update_data['content'] = wp_kses_post( $data['content'] );
			$formats[]              = '%s';
		}
		if ( isset( $data['effective_date'] ) ) {
			/* Convert datetime-local format to MySQL format */
			$effective_date = str_replace( 'T', ' ', $data['effective_date'] );
			if ( strlen( $effective_date ) === 16 ) {
				$effective_date .= ':00';
			}
			$update_data['effective_date'] = $effective_date;
			$formats[]                     = '%s';
		}
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = ! empty( $data['is_active'] ) ? 1 : 0;
			$formats[]                = '%d';

			/* If activating, deactivate others */
			if ( ! empty( $data['is_active'] ) ) {
				self::set_active_version( $version_id );
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $version_id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Set a version as active (deactivates all others).
	 *
	 * @since  1.5.2
	 * @param  int $version_id Version ID to activate.
	 * @return bool True on success.
	 */
	public static function set_active_version( int $version_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_versions';

		/* Deactivate all versions */
		$wpdb->update(
			$table,
			array( 'is_active' => 0 ),
			array( 'is_active' => 1 ),
			array( '%d' ),
			array( '%d' )
		);

		/* Activate the specified version */
		$result = $wpdb->update(
			$table,
			array( 'is_active' => 1 ),
			array( 'id' => $version_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a ToS version.
	 *
	 * @since  1.5.2
	 * @param  int $version_id Version ID.
	 * @return bool True on success.
	 */
	public static function delete_version( int $version_id ): bool {
		global $wpdb;

		$versions_table    = $wpdb->prefix . 'nbuf_tos_versions';
		$acceptances_table = $wpdb->prefix . 'nbuf_tos_acceptances';

		/* Delete acceptances for this version first */
		$wpdb->delete(
			$acceptances_table,
			array( 'tos_version_id' => $version_id ),
			array( '%d' )
		);

		/* Delete the version */
		$result = $wpdb->delete(
			$versions_table,
			array( 'id' => $version_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if user has accepted the current ToS.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return bool True if accepted.
	 */
	public static function has_user_accepted_current( int $user_id ): bool {
		$active_version = self::get_active_version();
		if ( ! $active_version ) {
			/* No active ToS, so nothing to accept */
			return true;
		}

		return self::has_user_accepted_version( $user_id, $active_version->id );
	}

	/**
	 * Check if user has accepted a specific ToS version.
	 *
	 * @since  1.5.2
	 * @param  int $user_id    User ID.
	 * @param  int $version_id Version ID.
	 * @return bool True if accepted.
	 */
	public static function has_user_accepted_version( int $user_id, int $version_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_acceptances';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d AND tos_version_id = %d',
				$table,
				$user_id,
				$version_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get user's acceptance record for a version.
	 *
	 * @since  1.5.2
	 * @param  int $user_id    User ID.
	 * @param  int $version_id Version ID.
	 * @return object|null Acceptance record or null.
	 */
	public static function get_user_acceptance( int $user_id, int $version_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_acceptances';

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d AND tos_version_id = %d',
				$table,
				$user_id,
				$version_id
			)
		);
	}

	/**
	 * Get all acceptances for a user.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return array Array of acceptance records.
	 */
	public static function get_user_acceptances( int $user_id ): array {
		global $wpdb;

		$acceptances_table = $wpdb->prefix . 'nbuf_tos_acceptances';
		$versions_table    = $wpdb->prefix . 'nbuf_tos_versions';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, v.version, v.title
				FROM %i a
				LEFT JOIN %i v ON a.tos_version_id = v.id
				WHERE a.user_id = %d
				ORDER BY a.accepted_at DESC",
				$acceptances_table,
				$versions_table,
				$user_id
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Record user's acceptance of ToS.
	 *
	 * @since  1.5.2
	 * @param  int $user_id    User ID.
	 * @param  int $version_id Version ID.
	 * @return bool True on success.
	 */
	public static function record_acceptance( int $user_id, int $version_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_acceptances';

		/* Check if already accepted */
		if ( self::has_user_accepted_version( $user_id, $version_id ) ) {
			return true;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'tos_version_id' => $version_id,
				'ip_address'     => self::get_client_ip(),
				'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '',
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			/* Log acceptance */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				$version = self::get_version( $version_id );
				NBUF_Audit_Log::log(
					$user_id,
					'tos_accepted',
					'success',
					sprintf(
						/* translators: %s: ToS version number */
						__( 'Accepted Terms of Service version %s', 'nobloat-user-foundry' ),
						$version ? $version->version : $version_id
					),
					array( 'version_id' => $version_id )
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Get acceptance count for a version.
	 *
	 * @since  1.5.2
	 * @param  int $version_id Version ID.
	 * @return int Acceptance count.
	 */
	public static function get_acceptance_count( int $version_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_tos_acceptances';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE tos_version_id = %d',
				$table,
				$version_id
			)
		);

		return (int) $count;
	}

	/**
	 * Get users who haven't accepted the current ToS.
	 *
	 * @since  1.5.2
	 * @param  int $limit Limit results.
	 * @return array Array of user IDs.
	 */
	public static function get_users_pending_acceptance( int $limit = 100 ): array {
		global $wpdb;

		$active_version = self::get_active_version();
		if ( ! $active_version ) {
			return array();
		}

		$acceptances_table = $wpdb->prefix . 'nbuf_tos_acceptances';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT u.ID
				FROM {$wpdb->users} u
				LEFT JOIN %i a ON u.ID = a.user_id AND a.tos_version_id = %d
				WHERE a.id IS NULL
				LIMIT %d",
				$acceptances_table,
				$active_version->id,
				$limit
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Check ToS acceptance on login.
	 *
	 * @since 1.5.2
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public static function check_tos_on_login( string $user_login, WP_User $user ): void {
		if ( ! self::is_enabled() || ! self::is_required_on_login() ) {
			return;
		}

		/* Skip for admins if configured */
		if ( user_can( $user, 'manage_options' ) ) {
			return;
		}

		/* Check if user needs to accept ToS */
		if ( ! self::has_user_accepted_current( $user->ID ) ) {
			/* Set a flag in user session to redirect after login completes */
			set_transient( 'nbuf_tos_pending_' . $user->ID, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Redirect to ToS acceptance page if needed.
	 *
	 * @since 1.5.2
	 */
	public static function maybe_redirect_to_acceptance(): void {
		if ( ! self::is_enabled() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		/* Skip for admins */
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Check if we're already on the acceptance page */
		if ( self::is_acceptance_page() ) {
			return;
		}

		/* Allow logout */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking WP logout action, no form processing.
		if ( isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			return;
		}

		/* Check if user has pending ToS or hasn't accepted current */
		$has_pending = get_transient( 'nbuf_tos_pending_' . $user_id );
		if ( $has_pending || ! self::has_user_accepted_current( $user_id ) ) {
			/* Check grace period */
			$active_version = self::get_active_version();
			if ( $active_version ) {
				$grace_hours = self::get_grace_period_hours();
				/*
				 * Both effective_date and current time need to be in the same timezone.
				 * effective_date is stored in local time, so compare with current local time.
				 * Convert both to timestamps using the same reference point.
				 */
				$effective_time = strtotime( $active_version->effective_date );
				$current_time   = strtotime( current_time( 'mysql', false ) );
				$grace_end      = $effective_time + ( $grace_hours * HOUR_IN_SECONDS );

				/* If still in grace period, don't force redirect */
				if ( $current_time < $grace_end ) {
					return;
				}
			}

			/* Redirect to acceptance page */
			$acceptance_url = NBUF_URL::get( 'accept-tos' );
			wp_safe_redirect( $acceptance_url );
			exit;
		}
	}

	/**
	 * Check if current page is the ToS acceptance page.
	 *
	 * @since  1.5.2
	 * @return bool True if on acceptance page.
	 */
	public static function is_acceptance_page(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$base_slug   = NBUF_URL::get_base_slug();
		return strpos( $request_uri, '/' . $base_slug . '/accept-tos' ) !== false;
	}

	/**
	 * Handle ToS acceptance form submission.
	 *
	 * @since 1.5.2
	 */
	public static function handle_acceptance(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to accept the Terms of Service.', 'nobloat-user-foundry' ) );
		}

		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'nbuf_accept_tos' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		$user_id    = get_current_user_id();
		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;

		if ( ! $version_id ) {
			wp_die( esc_html__( 'Invalid Terms of Service version.', 'nobloat-user-foundry' ) );
		}

		/* Record acceptance */
		$result = self::record_acceptance( $user_id, $version_id );

		if ( $result ) {
			/* Clear pending flag */
			delete_transient( 'nbuf_tos_pending_' . $user_id );

			/* Redirect using configured login redirect settings */
			$redirect_url = self::get_redirect_url();

			wp_safe_redirect( $redirect_url );
			exit;
		}

		wp_die( esc_html__( 'Failed to record acceptance. Please try again.', 'nobloat-user-foundry' ) );
	}

	/**
	 * Render the ToS acceptance page content.
	 *
	 * @since  1.5.2
	 * @return string HTML content.
	 */
	public static function render_acceptance_page(): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="nbuf-tos-wrapper"><div class="nbuf-tos-message nbuf-tos-message-info">' .
				esc_html__( 'You must be logged in to view this page.', 'nobloat-user-foundry' ) .
				'</div></div>';
		}

		$active_version = self::get_active_version();
		if ( ! $active_version ) {
			return '<div class="nbuf-tos-wrapper"><div class="nbuf-tos-message nbuf-tos-message-info">' .
				esc_html__( 'No Terms of Service currently available.', 'nobloat-user-foundry' ) .
				'</div></div>';
		}

		$user_id = get_current_user_id();

		/* Check if already accepted */
		if ( self::has_user_accepted_version( $user_id, $active_version->id ) ) {
			$redirect_url = self::get_redirect_url();
			return '<div class="nbuf-tos-wrapper"><div class="nbuf-tos-message nbuf-tos-message-success">' .
				esc_html__( 'You have already accepted the current Terms of Service.', 'nobloat-user-foundry' ) .
				' <a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Continue', 'nobloat-user-foundry' ) . '</a>' .
				'</div></div>';
		}

		ob_start();
		/* Convert local datetime to proper timestamp for display */
		$effective_dt = new DateTime( $active_version->effective_date, wp_timezone() );
		?>
		<div class="nbuf-tos-wrapper">
			<div class="nbuf-tos-header">
				<h1 class="nbuf-tos-title"><?php echo esc_html( $active_version->title ); ?></h1>
				<p class="nbuf-tos-version-info">
					<?php
					printf(
						/* translators: 1: version number, 2: effective date */
						esc_html__( 'Version %1$s - Effective %2$s', 'nobloat-user-foundry' ),
						esc_html( $active_version->version ),
						esc_html( wp_date( get_option( 'date_format' ), $effective_dt->getTimestamp() ) )
					);
					?>
				</p>
			</div>

			<div class="nbuf-tos-content-area">
				<?php echo wp_kses_post( $active_version->content ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nbuf-tos-form">
				<?php wp_nonce_field( 'nbuf_accept_tos' ); ?>
				<input type="hidden" name="action" value="nbuf_accept_tos">
				<input type="hidden" name="version_id" value="<?php echo esc_attr( $active_version->id ); ?>">

				<label class="nbuf-tos-checkbox-group">
					<input type="checkbox" name="accept_tos" value="1" required>
					<span class="nbuf-tos-checkbox-label">
						<?php esc_html_e( 'I have read and agree to the Terms of Service', 'nobloat-user-foundry' ); ?>
					</span>
				</label>

				<button type="submit" class="nbuf-tos-submit">
					<?php esc_html_e( 'Accept Terms of Service', 'nobloat-user-foundry' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get client IP address.
	 *
	 * @since  1.5.2
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				/* Handle comma-separated IPs (X-Forwarded-For) */
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
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
				$redirect_url = NBUF_URL::get( 'account' );
				break;
			case 'admin':
				$redirect_url = admin_url();
				break;
			case 'home':
				$redirect_url = home_url( '/' );
				break;
			case 'custom':
				$custom_url   = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );
				$redirect_url = $custom_url ? home_url( $custom_url ) : NBUF_URL::get( 'account' );
				break;
			default:
				$redirect_url = NBUF_URL::get( 'account' );
				break;
		}

		return $redirect_url;
	}

	/**
	 * Get acceptances for export.
	 *
	 * @since  1.5.2
	 * @param  int|null $version_id Optional version ID to filter.
	 * @return array Array of acceptance records with user data.
	 */
	public static function get_acceptances_for_export( ?int $version_id = null ): array {
		global $wpdb;

		$acceptances_table = $wpdb->prefix . 'nbuf_tos_acceptances';
		$versions_table    = $wpdb->prefix . 'nbuf_tos_versions';

		/*
		 * Build query with conditional WHERE clause.
		 * Uses separate prepare calls to avoid mixing prepared and interpolated segments.
		 */
		if ( $version_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, v.version, v.title, u.user_login, u.user_email, u.display_name
					FROM %i a
					LEFT JOIN %i v ON a.tos_version_id = v.id
					LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
					WHERE a.tos_version_id = %d
					ORDER BY a.accepted_at DESC",
					$acceptances_table,
					$versions_table,
					$version_id
				)
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, v.version, v.title, u.user_login, u.user_email, u.display_name
					FROM %i a
					LEFT JOIN %i v ON a.tos_version_id = v.id
					LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
					ORDER BY a.accepted_at DESC",
					$acceptances_table,
					$versions_table
				)
			);
		}

		return $results ? $results : array();
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
