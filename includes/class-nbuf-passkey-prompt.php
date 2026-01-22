<?php
/**
 * Passkey Enrollment Prompt
 *
 * Prompts users without passkeys to set one up after login.
 * Uses per-device tracking via cookie-based device IDs.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Passkey_Prompt class.
 *
 * Handles passkey enrollment prompts after login for users without passkeys.
 *
 * @since 1.5.1
 */
class NBUF_Passkey_Prompt {


	/**
	 * Device ID cookie name.
	 *
	 * @var string
	 */
	const DEVICE_COOKIE = 'nbuf_device_id';

	/**
	 * Device ID cookie expiry in seconds (1 year).
	 *
	 * @var int
	 */
	const DEVICE_COOKIE_EXPIRY = 31536000;

	/**
	 * User meta key for dismissed devices.
	 *
	 * @var string
	 */
	const DISMISSED_META_KEY = 'nbuf_passkey_prompt_dismissed_devices';

	/**
	 * Transient prefix for login trigger.
	 *
	 * @var string
	 */
	const TRIGGER_TRANSIENT_PREFIX = 'nbuf_show_passkey_prompt_';

	/**
	 * Transient prefix for session dismissal.
	 *
	 * @var string
	 */
	const SESSION_TRANSIENT_PREFIX = 'nbuf_passkey_prompt_session_';

	/**
	 * Whether modal should be rendered this request.
	 *
	 * @var bool
	 */
	private static $render_modal = false;

	/**
	 * Initialize passkey prompt functionality.
	 *
	 * @since 1.5.1
	 * @return void
	 */
	public static function init(): void {
		/* Hook after login completion */
		add_action( 'wp_login', array( __CLASS__, 'maybe_trigger_prompt' ), 20, 2 );

		/* Hook to clear session dismissal on logout */
		add_action( 'wp_logout', array( __CLASS__, 'clear_session_dismissal' ), 10, 1 );

		/* Hook to render modal on frontend */
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_modal' ) );

		/* AJAX handlers */
		add_action( 'wp_ajax_nbuf_passkey_prompt_dismiss', array( __CLASS__, 'ajax_dismiss' ) );
		add_action( 'wp_ajax_nbuf_passkey_prompt_remind_later', array( __CLASS__, 'ajax_remind_later' ) );
	}

	/**
	 * Generate a UUID v4 for device identification.
	 *
	 * @since  1.5.1
	 * @return string UUID v4 string.
	 */
	private static function generate_uuid(): string {
		$data    = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); /* Set version to 0100 */
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); /* Set bits 6-7 to 10 */

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Validate UUID format.
	 *
	 * @since  1.5.1
	 * @param  string $uuid UUID to validate.
	 * @return bool True if valid UUID format.
	 */
	private static function is_valid_uuid( string $uuid ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid );
	}

	/**
	 * Ensure device ID cookie exists.
	 *
	 * Creates a new device ID if one doesn't exist.
	 *
	 * @since  1.5.1
	 * @return string Device ID (existing or newly created).
	 */
	public static function ensure_device_id(): string {
		$device_id = self::get_device_id();

		if ( $device_id ) {
			return $device_id;
		}

		/* Generate new device ID */
		$device_id = self::generate_uuid();

		/* Set cookie */
		setcookie(
			self::DEVICE_COOKIE,
			$device_id,
			array(
				'expires'  => time() + self::DEVICE_COOKIE_EXPIRY,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false, /* Needs JS access for AJAX */
				'samesite' => 'Lax',
			)
		);

		/* Also set in $_COOKIE for same-request access */
		$_COOKIE[ self::DEVICE_COOKIE ] = $device_id;

		return $device_id;
	}

	/**
	 * Get device ID from cookie.
	 *
	 * @since  1.5.1
	 * @return string|null Device ID or null if not set/invalid.
	 */
	public static function get_device_id(): ?string {
		if ( ! isset( $_COOKIE[ self::DEVICE_COOKIE ] ) ) {
			return null;
		}

		$device_id = sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_COOKIE ] ) );

		/* Validate UUID format */
		if ( ! self::is_valid_uuid( $device_id ) ) {
			return null;
		}

		return $device_id;
	}

	/**
	 * Check if prompt should show for user/device.
	 *
	 * @since  1.5.1
	 * @param  int $user_id User ID.
	 * @return bool True if prompt should show.
	 */
	public static function should_show_prompt( int $user_id ): bool {
		/* Check passkeys globally enabled */
		if ( ! NBUF_Passkeys::is_enabled() ) {
			return false;
		}

		/* Check prompt setting enabled */
		if ( ! NBUF_Options::get( 'nbuf_passkey_prompt_enabled', true ) ) {
			return false;
		}

		/* Check user has no passkeys */
		if ( NBUF_User_Passkeys_Data::has_passkeys( $user_id ) ) {
			return false;
		}

		/* Get device ID */
		$device_id = self::get_device_id();
		if ( ! $device_id ) {
			return false;
		}

		/* Check device not in dismissed list */
		$dismissed_devices = self::get_dismissed_devices( $user_id );
		if ( in_array( $device_id, $dismissed_devices, true ) ) {
			return false;
		}

		/* Check session not dismissed */
		$session_key = self::SESSION_TRANSIENT_PREFIX . $user_id . '_' . $device_id;
		if ( get_transient( $session_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set trigger transient after login.
	 *
	 * @since 1.5.1
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public static function maybe_trigger_prompt( string $user_login, WP_User $user ): void {
		/* Ensure device ID cookie exists */
		self::ensure_device_id();

		/* Check if prompt should show */
		if ( ! self::should_show_prompt( $user->ID ) ) {
			return;
		}

		/* Set trigger transient (60 seconds - enough to render next page) */
		set_transient(
			self::TRIGGER_TRANSIENT_PREFIX . $user->ID,
			1,
			60
		);
	}

	/**
	 * Render modal HTML in footer.
	 *
	 * @since 1.5.1
	 */
	public static function maybe_render_modal(): void {
		/* Must be logged in */
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		/* Check trigger transient exists */
		$trigger_key = self::TRIGGER_TRANSIENT_PREFIX . $user_id;
		if ( ! get_transient( $trigger_key ) ) {
			return;
		}

		/* Delete transient (one-time show) */
		delete_transient( $trigger_key );

		/* Verify should_show_prompt still true (in case of race) */
		if ( ! self::should_show_prompt( $user_id ) ) {
			return;
		}

		/* Check HTTPS (passkeys require secure context) */
		if ( ! is_ssl() ) {
			return;
		}

		/* Mark for rendering and enqueue assets */
		self::$render_modal = true;
		self::render_modal_html();
	}

	/**
	 * Render the modal HTML.
	 *
	 * @since 1.5.1
	 */
	private static function render_modal_html(): void {
		$device_id = self::get_device_id();
		$nonce     = wp_create_nonce( 'nbuf_passkey_prompt' );

		/* Get passkey nonce for registration flow */
		$passkey_nonce = wp_create_nonce( 'nbuf_passkey_nonce' );

		?>
		<div id="nbuf-passkey-prompt-modal" class="nbuf-modal-overlay" style="display: flex;">
			<div class="nbuf-modal-content nbuf-passkey-prompt">
				<div class="nbuf-passkey-prompt-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
					</svg>
				</div>
				<h2><?php esc_html_e( 'Secure Your Account with a Passkey', 'nobloat-user-foundry' ); ?></h2>
				<p><?php esc_html_e( 'Passkeys are faster and more secure than passwords. Use your fingerprint, face, or device PIN to sign in.', 'nobloat-user-foundry' ); ?></p>

				<div class="nbuf-passkey-prompt-actions">
					<button type="button" class="nbuf-button nbuf-button-primary" id="nbuf-passkey-prompt-setup">
						<?php esc_html_e( 'Set Up Passkey', 'nobloat-user-foundry' ); ?>
					</button>
				</div>

				<div class="nbuf-passkey-prompt-secondary">
					<button type="button" class="nbuf-link-button" id="nbuf-passkey-prompt-later">
						<?php esc_html_e( 'Remind Me Later', 'nobloat-user-foundry' ); ?>
					</button>
					<span class="nbuf-separator">|</span>
					<button type="button" class="nbuf-link-button" id="nbuf-passkey-prompt-never">
						<?php esc_html_e( "Don't Ask Again", 'nobloat-user-foundry' ); ?>
					</button>
				</div>

				<div id="nbuf-passkey-prompt-status" class="nbuf-passkey-prompt-status" style="display: none;"></div>
			</div>
		</div>

		<style>
			.nbuf-modal-overlay {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.6);
				z-index: 999999;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 20px;
			}
			.nbuf-modal-content.nbuf-passkey-prompt {
				background: #fff;
				border-radius: 12px;
				max-width: 420px;
				width: 100%;
				padding: 32px;
				text-align: center;
				box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
				animation: nbuf-modal-fade-in 0.2s ease-out;
			}
			@keyframes nbuf-modal-fade-in {
				from { opacity: 0; transform: scale(0.95); }
				to { opacity: 1; transform: scale(1); }
			}
			.nbuf-passkey-prompt-icon {
				color: #2271b1;
				margin-bottom: 16px;
			}
			.nbuf-passkey-prompt h2 {
				margin: 0 0 12px;
				font-size: 20px;
				font-weight: 600;
				color: #1d2327;
			}
			.nbuf-passkey-prompt p {
				margin: 0 0 24px;
				color: #50575e;
				font-size: 14px;
				line-height: 1.5;
			}
			.nbuf-passkey-prompt-actions {
				margin-bottom: 16px;
			}
			.nbuf-passkey-prompt .nbuf-button-primary {
				display: inline-block;
				padding: 12px 24px;
				background: #2271b1;
				color: #fff;
				border: none;
				border-radius: 6px;
				font-size: 14px;
				font-weight: 500;
				cursor: pointer;
				transition: background 0.15s ease;
			}
			.nbuf-passkey-prompt .nbuf-button-primary:hover {
				background: #135e96;
			}
			.nbuf-passkey-prompt .nbuf-button-primary:disabled {
				background: #a0a5aa;
				cursor: not-allowed;
			}
			.nbuf-passkey-prompt-secondary {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 12px;
			}
			.nbuf-passkey-prompt .nbuf-link-button {
				background: none;
				border: none;
				color: #50575e;
				font-size: 13px;
				cursor: pointer;
				padding: 4px 0;
				text-decoration: underline;
			}
			.nbuf-passkey-prompt .nbuf-link-button:hover {
				color: #2271b1;
			}
			.nbuf-passkey-prompt .nbuf-separator {
				color: #dcdcde;
			}
			.nbuf-passkey-prompt-status {
				margin-top: 16px;
				padding: 12px;
				border-radius: 4px;
				font-size: 13px;
			}
			.nbuf-passkey-prompt-status.success {
				background: #d4edda;
				color: #155724;
			}
			.nbuf-passkey-prompt-status.error {
				background: #f8d7da;
				color: #721c24;
			}
			@media (max-width: 480px) {
				.nbuf-modal-content.nbuf-passkey-prompt {
					padding: 24px 20px;
				}
				.nbuf-passkey-prompt h2 {
					font-size: 18px;
				}
			}
		</style>

		<script>
		(function() {
			'use strict';

			var modal = document.getElementById('nbuf-passkey-prompt-modal');
			if (!modal) return;

			var setupBtn = document.getElementById('nbuf-passkey-prompt-setup');
			var laterBtn = document.getElementById('nbuf-passkey-prompt-later');
			var neverBtn = document.getElementById('nbuf-passkey-prompt-never');
			var statusEl = document.getElementById('nbuf-passkey-prompt-status');
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var passkeyNonce = <?php echo wp_json_encode( $passkey_nonce ); ?>;
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var deviceId = <?php echo wp_json_encode( $device_id ); ?>;

			function closeModal() {
				modal.style.display = 'none';
				modal.remove();
			}

			function showStatus(message, type) {
				statusEl.textContent = message;
				statusEl.className = 'nbuf-passkey-prompt-status ' + type;
				statusEl.style.display = 'block';
			}

			/* Check WebAuthn support */
			if (!window.PublicKeyCredential) {
				closeModal();
				return;
			}

			/* Setup Passkey button */
			setupBtn.addEventListener('click', function() {
				setupBtn.disabled = true;
				setupBtn.textContent = <?php echo wp_json_encode( __( 'Setting up...', 'nobloat-user-foundry' ) ); ?>;

				/* Get registration options from server */
				fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=nbuf_passkey_registration_options&nonce=' + encodeURIComponent(passkeyNonce)
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (!data.success) {
						throw new Error(data.data.message || <?php echo wp_json_encode( __( 'Failed to get registration options.', 'nobloat-user-foundry' ) ); ?>);
					}

					var options = data.data;

					/* Convert base64url to ArrayBuffer */
					function base64urlToBuffer(base64url) {
						var base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
						var padding = '='.repeat((4 - base64.length % 4) % 4);
						var binary = atob(base64 + padding);
						var bytes = new Uint8Array(binary.length);
						for (var i = 0; i < binary.length; i++) {
							bytes[i] = binary.charCodeAt(i);
						}
						return bytes.buffer;
					}

					/* Prepare credentials options */
					var publicKey = {
						challenge: base64urlToBuffer(options.challenge),
						rp: options.rp,
						user: {
							id: base64urlToBuffer(options.user.id),
							name: options.user.name,
							displayName: options.user.displayName
						},
						pubKeyCredParams: options.pubKeyCredParams,
						timeout: options.timeout,
						attestation: options.attestation,
						authenticatorSelection: options.authenticatorSelection
					};

					if (options.excludeCredentials) {
						publicKey.excludeCredentials = options.excludeCredentials.map(function(cred) {
							return {
								type: cred.type,
								id: base64urlToBuffer(cred.id)
							};
						});
					}

					return navigator.credentials.create({ publicKey: publicKey });
				})
				.then(function(credential) {
					/* Convert response to base64url */
					function bufferToBase64url(buffer) {
						var bytes = new Uint8Array(buffer);
						var binary = '';
						for (var i = 0; i < bytes.length; i++) {
							binary += String.fromCharCode(bytes[i]);
						}
						return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
					}

					var response = {
						id: credential.id,
						rawId: bufferToBase64url(credential.rawId),
						type: credential.type,
						clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
						attestationObject: bufferToBase64url(credential.response.attestationObject)
					};

					if (credential.response.getTransports) {
						response.transports = credential.response.getTransports();
					}

					/* Send to server */
					return fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=nbuf_passkey_register&nonce=' + encodeURIComponent(passkeyNonce) +
							'&response=' + encodeURIComponent(JSON.stringify(response)) +
							'&device_name=' + encodeURIComponent(<?php echo wp_json_encode( __( 'My Passkey', 'nobloat-user-foundry' ) ); ?>)
					});
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (!data.success) {
						throw new Error(data.data.message || <?php echo wp_json_encode( __( 'Failed to register passkey.', 'nobloat-user-foundry' ) ); ?>);
					}

					showStatus(<?php echo wp_json_encode( __( 'Passkey registered successfully! Refreshing...', 'nobloat-user-foundry' ) ); ?>, 'success');
					setupBtn.style.display = 'none';
					laterBtn.style.display = 'none';
					neverBtn.style.display = 'none';
					document.querySelector('.nbuf-passkey-prompt .nbuf-separator').style.display = 'none';

					/* Reload page to show the new passkey in account page */
					setTimeout(function() {
						window.location.reload();
					}, 1500);
				})
				.catch(function(err) {
					console.error('Passkey registration error:', err);
					setupBtn.disabled = false;
					setupBtn.textContent = <?php echo wp_json_encode( __( 'Set Up Passkey', 'nobloat-user-foundry' ) ); ?>;

					var message = err.name === 'NotAllowedError'
						? <?php echo wp_json_encode( __( 'Passkey registration was cancelled.', 'nobloat-user-foundry' ) ); ?>
						: (err.message || <?php echo wp_json_encode( __( 'Failed to register passkey.', 'nobloat-user-foundry' ) ); ?>);

					showStatus(message, 'error');
				});
			});

			/* Remind Me Later button */
			laterBtn.addEventListener('click', function() {
				fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=nbuf_passkey_prompt_remind_later&nonce=' + encodeURIComponent(nonce) + '&device_id=' + encodeURIComponent(deviceId)
				});
				closeModal();
			});

			/* Don't Ask Again button */
			neverBtn.addEventListener('click', function() {
				fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=nbuf_passkey_prompt_dismiss&nonce=' + encodeURIComponent(nonce) + '&device_id=' + encodeURIComponent(deviceId)
				});
				closeModal();
			});

			/* Close on overlay click */
			modal.addEventListener('click', function(e) {
				if (e.target === modal) {
					/* Treat as "remind me later" */
					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=nbuf_passkey_prompt_remind_later&nonce=' + encodeURIComponent(nonce) + '&device_id=' + encodeURIComponent(deviceId)
					});
					closeModal();
				}
			});

			/* Close on Escape key */
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && modal.style.display !== 'none') {
					/* Treat as "remind me later" */
					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=nbuf_passkey_prompt_remind_later&nonce=' + encodeURIComponent(nonce) + '&device_id=' + encodeURIComponent(deviceId)
					});
					closeModal();
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX: Permanently dismiss for this device.
	 *
	 * @since 1.5.1
	 */
	public static function ajax_dismiss(): void {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_passkey_prompt', 'nonce' );

		/* Must be logged in */
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'nobloat-user-foundry' ) ) );
		}

		/* Get and validate device ID */
		$device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( wp_unslash( $_POST['device_id'] ) ) : '';
		if ( ! self::is_valid_uuid( $device_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'nobloat-user-foundry' ) ) );
		}

		/* Add device to dismissed list */
		self::add_dismissed_device( $user_id, $device_id );

		wp_send_json_success( array( 'message' => __( 'Dismissed.', 'nobloat-user-foundry' ) ) );
	}

	/**
	 * AJAX: Dismiss for this session.
	 *
	 * @since 1.5.1
	 */
	public static function ajax_remind_later(): void {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_passkey_prompt', 'nonce' );

		/* Must be logged in */
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'nobloat-user-foundry' ) ) );
		}

		/* Get and validate device ID */
		$device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( wp_unslash( $_POST['device_id'] ) ) : '';
		if ( ! self::is_valid_uuid( $device_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'nobloat-user-foundry' ) ) );
		}

		/* Set session transient (12 hours as fallback for session) */
		$session_key = self::SESSION_TRANSIENT_PREFIX . $user_id . '_' . $device_id;
		set_transient( $session_key, 1, 12 * HOUR_IN_SECONDS );

		wp_send_json_success( array( 'message' => __( 'Reminder set.', 'nobloat-user-foundry' ) ) );
	}

	/**
	 * Clear session dismissal on logout.
	 *
	 * Ensures "Remind Me Later" means "remind me next login session".
	 *
	 * @since 1.5.2
	 * @param int $user_id User ID being logged out.
	 */
	public static function clear_session_dismissal( int $user_id ): void {
		/* Get device ID from cookie */
		$device_id = self::get_device_id();
		if ( ! $device_id ) {
			return;
		}

		/* Delete the session transient */
		$session_key = self::SESSION_TRANSIENT_PREFIX . $user_id . '_' . $device_id;
		delete_transient( $session_key );
	}

	/**
	 * Get list of dismissed device IDs for user.
	 *
	 * @since  1.5.1
	 * @param  int $user_id User ID.
	 * @return array<string, mixed> Array of device IDs.
	 */
	private static function get_dismissed_devices( int $user_id ): array {
		return NBUF_User_Data::get_passkey_prompt_dismissed( $user_id );
	}

	/**
	 * Add device ID to dismissed list.
	 *
	 * @since 1.5.1
	 * @param int    $user_id   User ID.
	 * @param string $device_id Device ID to add.
	 */
	private static function add_dismissed_device( int $user_id, string $device_id ): void {
		$dismissed = self::get_dismissed_devices( $user_id );

		/* Don't add duplicates */
		if ( in_array( $device_id, $dismissed, true ) ) {
			return;
		}

		$dismissed[] = $device_id;

		/* Limit to 50 devices to prevent unbounded growth */
		if ( count( $dismissed ) > 50 ) {
			$dismissed = array_slice( $dismissed, -50 );
		}

		NBUF_User_Data::set_passkey_prompt_dismissed( $user_id, $dismissed );
	}
}
