<?php
/**
 * NoBloat User Foundry - Background Activation
 *
 * Handles long-running activation tasks in the background to prevent
 * PHP timeout issues on sites with many users.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background Activation class.
 *
 * Processes activation tasks (like user verification) in chunks
 * to avoid PHP timeout on large sites.
 */
class NBUF_Background_Activation {

	/**
	 * Transient key for activation state.
	 *
	 * @var string
	 */
	const STATE_TRANSIENT = 'nbuf_activation_state';

	/**
	 * Number of users to process per batch.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'show_activation_notice' ) );
		add_action( 'wp_ajax_nbuf_activation_process', array( __CLASS__, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_nbuf_activation_status', array( __CLASS__, 'ajax_get_status' ) );
		add_action( 'wp_ajax_nbuf_activation_dismiss', array( __CLASS__, 'ajax_dismiss_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Check if background activation is needed and start it.
	 *
	 * Called from activator after immediate tasks complete.
	 *
	 * @param array $tasks Array of deferred tasks to process.
	 * @return bool True if background processing started, false if not needed.
	 */
	public static function start_if_needed( $tasks = array() ) {
		/* Check if already processing */
		$state = self::get_state();
		if ( $state && 'processing' === $state['status'] ) {
			return true; /* Already running */
		}

		/* Default task: verify existing users if enabled */
		if ( empty( $tasks ) ) {
			$settings = NBUF_Options::get( 'nbuf_settings', array() );
			if ( ! empty( $settings['auto_verify_existing'] ) ) {
				$tasks[] = 'verify_users';
			}
		}

		/* No tasks needed */
		if ( empty( $tasks ) ) {
			return false;
		}

		/* Count total users for progress tracking */
		$total_users = 0;
		if ( in_array( 'verify_users', $tasks, true ) ) {
			$total_users = self::count_users_to_verify();
		}

		/* If small enough, just do it now */
		if ( $total_users <= self::BATCH_SIZE ) {
			self::process_all_immediately( $tasks );
			return false;
		}

		/* Start background processing */
		$state = array(
			'status'        => 'processing',
			'tasks'         => $tasks,
			'current_task'  => $tasks[0],
			'total_users'   => $total_users,
			'processed'     => 0,
			'offset'        => 0,
			'started_at'    => time(),
			'last_activity' => time(),
		);

		set_transient( self::STATE_TRANSIENT, $state, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Get current activation state.
	 *
	 * @return array|false State array or false if not processing.
	 */
	public static function get_state() {
		return get_transient( self::STATE_TRANSIENT );
	}

	/**
	 * Count users that need verification.
	 *
	 * @return int Number of users to verify.
	 */
	private static function count_users_to_verify() {
		global $wpdb;

		/* Count users not in nbuf_user_data or not verified */
		$user_data_table = $wpdb->prefix . 'nbuf_user_data';

		/* Check if table exists */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$user_data_table
			)
		);

		if ( ! $table_exists ) {
			/* Table doesn't exist, count all users */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
		}

		/* Count users needing verification (not in table or is_verified != 1) */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} u
				LEFT JOIN %i ud ON u.ID = ud.user_id
				WHERE ud.user_id IS NULL OR ud.is_verified != 1",
				$user_data_table
			)
		);

		return (int) $count;
	}

	/**
	 * Process all tasks immediately (for small user counts).
	 *
	 * @param array $tasks Tasks to process.
	 */
	private static function process_all_immediately( $tasks ) {
		foreach ( $tasks as $task ) {
			if ( 'verify_users' === $task ) {
				self::verify_users_batch( 0, PHP_INT_MAX );
			}
		}
	}

	/**
	 * Process a batch of user verifications.
	 *
	 * @param int $offset Starting offset.
	 * @param int $limit  Number of users to process.
	 * @return int Number of users processed.
	 */
	private static function verify_users_batch( $offset, $limit ) {
		global $wpdb;

		if ( ! class_exists( 'NBUF_User_Data' ) ) {
			return 0;
		}

		/* Get batch of user IDs */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch processing during activation.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		if ( empty( $user_ids ) ) {
			return 0;
		}

		/* Batch verify users */
		NBUF_User_Data::batch_verify_users( $user_ids );

		return count( $user_ids );
	}

	/**
	 * AJAX handler to process a batch.
	 */
	public static function ajax_process_batch() {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_activation_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = self::get_state();

		if ( ! $state || 'processing' !== $state['status'] ) {
			wp_send_json_error( array( 'message' => 'No activation in progress' ) );
		}

		/* Process batch based on current task */
		$processed = 0;

		if ( 'verify_users' === $state['current_task'] ) {
			$processed = self::verify_users_batch( $state['offset'], self::BATCH_SIZE );
		}

		/* Update state */
		$state['processed']     += $processed;
		$state['offset']        += self::BATCH_SIZE;
		$state['last_activity']  = time();

		/* Check if complete */
		if ( $processed < self::BATCH_SIZE || $state['processed'] >= $state['total_users'] ) {
			/* Move to next task or complete */
			$current_index = array_search( $state['current_task'], $state['tasks'], true );
			if ( false !== $current_index && isset( $state['tasks'][ $current_index + 1 ] ) ) {
				/* Move to next task */
				$state['current_task'] = $state['tasks'][ $current_index + 1 ];
				$state['offset']       = 0;
			} else {
				/* All tasks complete */
				$state['status']       = 'complete';
				$state['completed_at'] = time();
			}
		}

		set_transient( self::STATE_TRANSIENT, $state, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'status'      => $state['status'],
				'processed'   => $state['processed'],
				'total'       => $state['total_users'],
				'percent'     => $state['total_users'] > 0
					? round( ( $state['processed'] / $state['total_users'] ) * 100 )
					: 100,
				'current_task' => $state['current_task'] ?? '',
			)
		);
	}

	/**
	 * AJAX handler to get current status.
	 */
	public static function ajax_get_status() {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_activation_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = self::get_state();

		if ( ! $state ) {
			wp_send_json_success(
				array(
					'status'    => 'none',
					'processed' => 0,
					'total'     => 0,
					'percent'   => 100,
				)
			);
		}

		wp_send_json_success(
			array(
				'status'       => $state['status'],
				'processed'    => $state['processed'],
				'total'        => $state['total_users'],
				'percent'      => $state['total_users'] > 0
					? round( ( $state['processed'] / $state['total_users'] ) * 100 )
					: 100,
				'current_task' => $state['current_task'] ?? '',
			)
		);
	}

	/**
	 * AJAX handler to dismiss the completion notice.
	 */
	public static function ajax_dismiss_notice() {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_activation_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		/* Clear the state */
		delete_transient( self::STATE_TRANSIENT );

		wp_send_json_success();
	}

	/**
	 * Show admin notice for activation progress.
	 */
	public static function show_activation_notice() {
		/* Only show to admins */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = self::get_state();

		if ( ! $state ) {
			return;
		}

		$nonce = wp_create_nonce( 'nbuf_activation_nonce' );

		if ( 'processing' === $state['status'] ) {
			$percent = $state['total_users'] > 0
				? round( ( $state['processed'] / $state['total_users'] ) * 100 )
				: 0;

			?>
			<div class="notice notice-info nbuf-activation-notice" id="nbuf-activation-notice">
				<p>
					<strong>NoBloat User Foundry</strong> is completing setup...
					<span id="nbuf-activation-progress">
						(Processing users: <?php echo esc_html( number_format( $state['processed'] ) ); ?> of <?php echo esc_html( number_format( $state['total_users'] ) ); ?>)
					</span>
				</p>
				<div class="nbuf-progress-bar" style="background: #e0e0e0; border-radius: 3px; height: 20px; margin: 10px 0; overflow: hidden;">
					<div id="nbuf-progress-fill" style="background: #0073aa; height: 100%; width: <?php echo esc_attr( $percent ); ?>%; transition: width 0.3s ease;"></div>
				</div>
				<p class="description" id="nbuf-activation-status">
					Processing in the background. You can continue using WordPress.
				</p>
			</div>
			<script>
			(function() {
				var nonce = '<?php echo esc_js( $nonce ); ?>';
				var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				var isProcessing = false;
				var pollInterval;

				function processBatch() {
					if (isProcessing) return;
					isProcessing = true;

					fetch(ajaxurl + '?action=nbuf_activation_process&nonce=' + nonce, {
						method: 'POST',
						credentials: 'same-origin'
					})
					.then(function(response) { return response.json(); })
					.then(function(data) {
						isProcessing = false;
						if (data.success) {
							updateUI(data.data);
							if (data.data.status === 'processing') {
								/* Continue processing */
								setTimeout(processBatch, 100);
							} else if (data.data.status === 'complete') {
								showComplete();
							}
						}
					})
					.catch(function() {
						isProcessing = false;
						/* Retry on error */
						setTimeout(processBatch, 2000);
					});
				}

				function updateUI(data) {
					var progressText = document.getElementById('nbuf-activation-progress');
					var progressFill = document.getElementById('nbuf-progress-fill');
					if (progressText) {
						progressText.textContent = '(Processing users: ' +
							data.processed.toLocaleString() + ' of ' +
							data.total.toLocaleString() + ')';
					}
					if (progressFill) {
						progressFill.style.width = data.percent + '%';
					}
				}

				function showComplete() {
					var notice = document.getElementById('nbuf-activation-notice');
					if (notice) {
						notice.className = 'notice notice-success nbuf-activation-notice is-dismissible';
						notice.innerHTML = '<p><strong>NoBloat User Foundry</strong> setup complete!</p>' +
							'<button type="button" class="notice-dismiss" onclick="dismissNotice()"><span class="screen-reader-text">Dismiss this notice.</span></button>';
					}
				}

				window.dismissNotice = function() {
					fetch(ajaxurl + '?action=nbuf_activation_dismiss&nonce=' + nonce, {
						method: 'POST',
						credentials: 'same-origin'
					});
					var notice = document.getElementById('nbuf-activation-notice');
					if (notice) {
						notice.style.display = 'none';
					}
				};

				/* Start processing immediately */
				processBatch();
			})();
			</script>
			<?php
		} elseif ( 'complete' === $state['status'] ) {
			/* Show notice once, then clear the transient so it doesn't show again */
			delete_transient( self::STATE_TRANSIENT );
			?>
			<div class="notice notice-success is-dismissible nbuf-activation-notice" id="nbuf-activation-notice">
				<p><strong>NoBloat User Foundry</strong> setup complete!</p>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue admin scripts for activation notice.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		/* Only load if activation is in progress */
		$state = self::get_state();
		if ( ! $state ) {
			return;
		}

		/* Inline styles for progress bar */
		wp_add_inline_style(
			'wp-admin',
			'.nbuf-activation-notice .nbuf-progress-bar { margin: 10px 0; }'
		);
	}

	/**
	 * Check if activation is still processing (for external checks).
	 *
	 * @return bool True if processing, false otherwise.
	 */
	public static function is_processing() {
		$state = self::get_state();
		return $state && 'processing' === $state['status'];
	}

	/**
	 * Cancel background activation.
	 *
	 * @return bool True if cancelled, false if not running.
	 */
	public static function cancel() {
		$state = self::get_state();
		if ( ! $state || 'processing' !== $state['status'] ) {
			return false;
		}

		delete_transient( self::STATE_TRANSIENT );
		return true;
	}
}
