<?php
/**
 * Anti-Bot Protection System
 *
 * Multi-layered bot detection for registration forms using:
 * - Dynamic honeypot fields with rotating names
 * - Minimum time validation
 * - JavaScript token validation
 * - User interaction detection
 * - Proof of work challenges
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Antibot
 *
 * Handles anti-bot protection for registration forms.
 */
class NBUF_Antibot {

	/**
	 * Session key prefix for transients.
	 *
	 * @var string
	 */
	const SESSION_PREFIX = 'nbuf_antibot_';

	/**
	 * Max challenges retained per session (ring buffer). Concurrent renders
	 * (two tabs / refresh / Back button) each append their own JS seed and PoW
	 * challenge; validation accepts a match against ANY unexpired entry so an
	 * earlier-rendered form is not clobbered by a later render and wrongly
	 * blocked on submit.
	 */
	const CHALLENGE_RING_MAX = 12;

	/**
	 * Cached session ID for current request.
	 *
	 * @var string|null
	 */
	private static $request_session_id = null;

	/**
	 * Honeypot field name rotation period in seconds (1 hour).
	 *
	 * @var int
	 */
	const HONEYPOT_ROTATION = 3600;

	/**
	 * PoW difficulty levels (number of leading hex zeros required).
	 *
	 * @var array<string, int>
	 */
	const POW_DIFFICULTIES = array(
		'low'    => 2,
		'medium' => 3,
		'high'   => 4,
	);

	/**
	 * Initialize anti-bot hooks.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Check if anti-bot protection is enabled.
	 *
	 * @since  1.5.0
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		return (bool) NBUF_Options::get( 'nbuf_antibot_enabled', true );
	}

	/**
	 * Enqueue frontend JavaScript on registration pages.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function enqueue_scripts(): void {
		self::debug_log( 'enqueue_scripts() called' );

		if ( ! self::is_registration_page() ) {
			self::debug_log( 'Not a registration page - skipping script enqueue' );
			return;
		}

		self::debug_log( 'Registration page detected - enqueueing antibot.js' );

		NBUF_Asset_Minifier::enqueue_script(
			'nbuf-antibot',
			'assets/js/frontend/antibot.js',
			array()
		);

		$config = self::get_client_config();
		self::debug_log( 'Client config: ' . wp_json_encode( $config ) );
		wp_localize_script( 'nbuf-antibot', 'nbufAntibot', $config );
	}

	/**
	 * Get client-side configuration for JavaScript.
	 *
	 * @since  1.5.0
	 * @return array<string, mixed> Configuration data.
	 */
	private static function get_client_config(): array {
		$session_id   = self::get_or_create_session_id();
		$js_seed_data = self::generate_js_seed( $session_id );

		return array(
			'sessionId'          => $session_id,
			'jsTokenEnabled'     => (bool) NBUF_Options::get( 'nbuf_antibot_js_token', true ),
			'interactionEnabled' => (bool) NBUF_Options::get( 'nbuf_antibot_interaction', true ),
			'powEnabled'         => (bool) NBUF_Options::get( 'nbuf_antibot_pow', true ),
			'powChallenge'       => self::generate_pow_challenge( $session_id ),
			'powDifficulty'      => self::get_pow_difficulty(),
			'minInteractions'    => absint( NBUF_Options::get( 'nbuf_antibot_min_interactions', 3 ) ),
			'formSelector'       => '.nbuf-registration-form',
			'jsSeed'             => $js_seed_data['seed'],
			'jsTimestamp'        => $js_seed_data['timestamp'],
		);
	}

	/**
	 * Check if current page contains registration form.
	 *
	 * @since  1.5.0
	 * @return bool True if registration page.
	 */
	private static function is_registration_page() {
		global $post;

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		self::debug_log( 'is_registration_page() checking URI: ' . $request_uri );

		/*
		 * Check Universal Router first - parse URL directly since wp_enqueue_scripts
		 * runs before template_redirect where the router normally processes.
		 */
		if ( class_exists( 'NBUF_Universal_Router' ) ) {
			/* If router has already processed, use its state */
			$current_view = NBUF_Universal_Router::get_current_view();
			self::debug_log( 'Universal Router current_view: ' . ( $current_view ? $current_view : '(empty)' ) );

			if ( 'register' === $current_view ) {
				self::debug_log( 'Matched via router current_view' );
				return true;
			}

			/* Otherwise, parse URL directly to detect registration page early */
			if ( method_exists( 'NBUF_Universal_Router', 'parse_url' ) ) {
				$parsed = NBUF_Universal_Router::parse_url();
				self::debug_log( 'Universal Router parse_url result: ' . wp_json_encode( $parsed ) );

				if ( $parsed && 'register' === $parsed['view'] ) {
					self::debug_log( 'Matched via router parse_url' );
					return true;
				}
			}
		} else {
			self::debug_log( 'NBUF_Universal_Router class not found' );
		}

		/* Check for registration shortcode on regular pages */
		if ( $post && has_shortcode( $post->post_content, 'nbuf_registration_form' ) ) {
			self::debug_log( 'Matched via shortcode in post content' );
			return true;
		}

		/* Check if this is the designated registration page */
		if ( $post ) {
			$reg_page_id = NBUF_Options::get( 'nbuf_page_registration', 0 );
			self::debug_log( 'Checking reg_page_id: ' . $reg_page_id . ' vs post ID: ' . $post->ID );
			if ( $reg_page_id && $post->ID === (int) $reg_page_id ) {
				self::debug_log( 'Matched via page ID' );
				return true;
			}
		} else {
			self::debug_log( 'No $post global available' );
		}

		self::debug_log( 'No registration page match found' );
		return false;
	}

	/*
	 * =========================================================
	 * SESSION MANAGEMENT
	 * =========================================================
	 */

	/**
	 * Get or create a unique session ID.
	 *
	 * Uses a static cache to ensure the same session ID is used throughout
	 * a single request (important because enqueue_scripts and render_fields
	 * are called at different times).
	 *
	 * @since  1.5.0
	 * @return string 32-character session ID.
	 */
	public static function get_or_create_session_id() {
		/* Return cached session ID if available (same request) */
		if ( null !== self::$request_session_id ) {
			return self::$request_session_id;
		}

		$session_key = self::SESSION_PREFIX . 'session';
		$session_id  = isset( $_COOKIE[ $session_key ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ $session_key ] ) )
			: '';

		if ( empty( $session_id ) || strlen( $session_id ) !== 32 || ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) ) {
			$session_id = bin2hex( random_bytes( 16 ) );
		}

		/* Cache for this request */
		self::$request_session_id = $session_id;

		return $session_id;
	}

	/*
	 * =========================================================
	 * HONEYPOT FIELDS
	 * =========================================================
	 */

	/**
	 * Generate honeypot field names (rotates hourly).
	 *
	 * @since  1.5.0
	 * @return array<string, string> Associative array of field keys to names.
	 */
	public static function get_honeypot_fields(): array {
		$rotation_key = floor( time() / self::HONEYPOT_ROTATION );
		$site_salt    = wp_salt( 'auth' );

		$base_hash = hash( 'sha256', $site_salt . $rotation_key );

		/*
		 * Use plugin-specific prefixes to avoid collision with legitimate
		 * form fields from themes or other plugins (e.g., contact forms).
		 */
		return array(
			'field1' => 'nbuf_hp_a_' . substr( $base_hash, 0, 8 ),
			'field2' => 'nbuf_hp_b_' . substr( $base_hash, 8, 8 ),
			'field3' => 'nbuf_hp_c_' . substr( $base_hash, 16, 8 ),
		);
	}

	/**
	 * Render honeypot fields HTML.
	 *
	 * @since  1.5.0
	 * @return string HTML for honeypot fields.
	 */
	public static function render_honeypot_fields() {
		if ( ! NBUF_Options::get( 'nbuf_antibot_honeypot', true ) ) {
			return '';
		}

		$fields = self::get_honeypot_fields();
		$html   = '';

		/* CSS positions fields off-screen (not display:none which bots detect) */
		$html .= '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:1px;width:1px;overflow:hidden;">';

		foreach ( $fields as $key => $name ) {
			$html .= sprintf(
				'<label for="%1$s">Leave empty</label>' .
				'<input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off">',
				esc_attr( $name )
			);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Validate honeypot fields are empty.
	 *
	 * @since  1.5.0
	 * @param  array<string, mixed> $data POST data.
	 * @return bool True if valid (honeypots empty).
	 */
	public static function validate_honeypot( array $data ): bool {
		if ( ! NBUF_Options::get( 'nbuf_antibot_honeypot', true ) ) {
			return true;
		}

		$fields = self::get_honeypot_fields();

		/* Also check previous rotation period (handles edge case at rotation boundary) */
		$prev_rotation_key = floor( ( time() - self::HONEYPOT_ROTATION ) / self::HONEYPOT_ROTATION );
		$prev_base_hash    = hash( 'sha256', wp_salt( 'auth' ) . $prev_rotation_key );
		$prev_fields       = array(
			'nbuf_hp_a_' . substr( $prev_base_hash, 0, 8 ),
			'nbuf_hp_b_' . substr( $prev_base_hash, 8, 8 ),
			'nbuf_hp_c_' . substr( $prev_base_hash, 16, 8 ),
		);

		$all_fields = array_merge( array_values( $fields ), $prev_fields );

		foreach ( $all_fields as $field_name ) {
			if ( isset( $data[ $field_name ] ) && '' !== $data[ $field_name ] ) {
				return false;
			}
		}

		return true;
	}

	/*
	 * =========================================================
	 * TIME CHECK
	 * =========================================================
	 */

	/**
	 * Generate form render timestamp token.
	 *
	 * @since  1.5.0
	 * @return string Base64-encoded token.
	 */
	public static function generate_time_token() {
		if ( ! NBUF_Options::get( 'nbuf_antibot_time_check', true ) ) {
			return '';
		}

		$timestamp = time();
		$nonce     = wp_create_nonce( 'nbuf_antibot_time_' . $timestamp );

		return base64_encode( $timestamp . '|' . $nonce ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Render time check hidden field.
	 *
	 * @since  1.5.0
	 * @return string HTML for time field.
	 */
	public static function render_time_field() {
		if ( ! NBUF_Options::get( 'nbuf_antibot_time_check', true ) ) {
			return '';
		}

		$token = self::generate_time_token();

		return sprintf(
			'<input type="hidden" name="nbuf_form_token" value="%s">',
			esc_attr( $token )
		);
	}

	/**
	 * Validate minimum time elapsed since form render.
	 *
	 * @since  1.5.0
	 * @param  string $token Time token from form.
	 * @return bool True if valid (enough time elapsed).
	 */
	public static function validate_time_check( $token ) {
		if ( ! NBUF_Options::get( 'nbuf_antibot_time_check', true ) ) {
			return true;
		}

		if ( empty( $token ) ) {
			return false;
		}

		$decoded = base64_decode( $token, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return false;
		}

		$parts = explode( '|', $decoded );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$timestamp = absint( $parts[0] );
		$nonce     = $parts[1];

		/* Verify nonce */
		if ( ! wp_verify_nonce( $nonce, 'nbuf_antibot_time_' . $timestamp ) ) {
			return false;
		}

		$min_seconds = absint( NBUF_Options::get( 'nbuf_antibot_min_time', 3 ) );
		$elapsed     = time() - $timestamp;

		if ( $elapsed < $min_seconds ) {
			return false;
		}

		/* Reject forms older than 2 hours (stale tokens). Raised from 1h so a
		 * user who leaves the tab open is not blocked before the challenge TTL. */
		if ( $elapsed > 7200 ) {
			return false;
		}

		return true;
	}

	/*
	 * =========================================================
	 * JAVASCRIPT TOKEN
	 * =========================================================
	 */

	/**
	 * Generate seed for JS token computation.
	 *
	 * @since  1.5.0
	 * @param  string $session_id Session ID.
	 * @return array{seed: string, timestamp: string} Array with 'seed' and 'timestamp' keys.
	 */
	public static function generate_js_seed( string $session_id ): array {
		if ( ! NBUF_Options::get( 'nbuf_antibot_js_token', true ) ) {
			return array(
				'seed'      => '',
				'timestamp' => '',
			);
		}

		$seed      = bin2hex( random_bytes( 8 ) );
		$timestamp = time();

		/*
		 * Append to a small per-session ring instead of overwriting, so a later
		 * render does not invalidate an earlier-rendered (but not yet submitted)
		 * form. Validation matches against any unexpired entry.
		 */
		$js_key  = self::SESSION_PREFIX . 'js_' . $session_id;
		$js_ring = self::normalize_js_ring( get_transient( $js_key ) );
		$js_ring[] = array(
			'seed'      => $seed,
			'timestamp' => $timestamp,
		);
		if ( count( $js_ring ) > self::CHALLENGE_RING_MAX ) {
			$js_ring = array_slice( $js_ring, -self::CHALLENGE_RING_MAX );
		}
		set_transient( $js_key, $js_ring, 2 * HOUR_IN_SECONDS );

		return array(
			'seed'      => $seed,
			'timestamp' => (string) $timestamp,
		);
	}

	/**
	 * Validate JS-generated token.
	 *
	 * Expected: SHA256(seed + timestamp + session_id)
	 *
	 * @since  1.5.0
	 * @param  string $token      Token from form.
	 * @param  string $session_id Session ID.
	 * @return bool True if valid.
	 */
	public static function validate_js_token( $token, $session_id ) {
		self::debug_log( 'validate_js_token() - token length: ' . strlen( $token ) . ', session_id: ' . $session_id );

		if ( ! NBUF_Options::get( 'nbuf_antibot_js_token', true ) ) {
			self::debug_log( 'JS token check disabled' );
			return true;
		}

		if ( empty( $token ) || empty( $session_id ) ) {
			self::debug_log( 'JS token FAIL: empty token or session_id' );
			return false;
		}

		$transient_key = self::SESSION_PREFIX . 'js_' . $session_id;
		$js_ring       = self::normalize_js_ring( get_transient( $transient_key ) );
		self::debug_log( 'Looking for transient: ' . $transient_key . ' (ring size ' . count( $js_ring ) . ')' );

		if ( empty( $js_ring ) ) {
			self::debug_log( 'JS token FAIL: no transient or empty ring' );
			return false;
		}

		/* Accept a constant-time match against ANY unexpired ring entry. */
		foreach ( $js_ring as $challenge ) {
			if ( ! isset( $challenge['seed'], $challenge['timestamp'] ) ) {
				continue;
			}
			$expected = hash( 'sha256', $challenge['seed'] . $challenge['timestamp'] . $session_id );
			if ( hash_equals( $expected, (string) $token ) ) {
				self::debug_log( 'JS token PASS' );
				return true;
			}
		}

		self::debug_log( 'JS token FAIL: no ring entry matched' );
		return false;
	}

	/*
	 * =========================================================
	 * INTERACTION DETECTION
	 * =========================================================
	 */

	/**
	 * Validate interaction data from client.
	 *
	 * @since  1.5.0
	 * @param  string $data Base64-encoded JSON interaction data.
	 * @return bool True if valid.
	 */
	public static function validate_interaction( $data ) {
		self::debug_log( 'validate_interaction() - data length: ' . strlen( $data ) );

		if ( ! NBUF_Options::get( 'nbuf_antibot_interaction', true ) ) {
			self::debug_log( 'Interaction check disabled' );
			return true;
		}

		if ( empty( $data ) ) {
			self::debug_log( 'Interaction FAIL: empty data' );
			return false;
		}

		$decoded_b64 = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded_b64 ) {
			self::debug_log( 'Interaction FAIL: base64 decode failed' );
			return false;
		}

		$decoded = json_decode( $decoded_b64, true );
		if ( ! is_array( $decoded ) ) {
			self::debug_log( 'Interaction FAIL: JSON decode failed' );
			return false;
		}

		self::debug_log( 'Interaction data: ' . wp_json_encode( $decoded ) );

		$min_interactions = absint( NBUF_Options::get( 'nbuf_antibot_min_interactions', 3 ) );

		$mouse_events  = isset( $decoded['mouse'] ) ? absint( $decoded['mouse'] ) : 0;
		$key_events    = isset( $decoded['keyboard'] ) ? absint( $decoded['keyboard'] ) : 0;
		$focus_events  = isset( $decoded['focus'] ) ? absint( $decoded['focus'] ) : 0;
		$scroll_events = isset( $decoded['scroll'] ) ? absint( $decoded['scroll'] ) : 0;

		$total = $mouse_events + $key_events + $focus_events + $scroll_events;

		self::debug_log( "Interactions: mouse=$mouse_events, keyboard=$key_events, focus=$focus_events, scroll=$scroll_events, total=$total (min=$min_interactions)" );

		if ( $total < $min_interactions ) {
			self::debug_log( 'Interaction FAIL: insufficient total' );
			return false;
		}

		/*
		 * Do NOT hard-require a keyboard event: password-manager autofill and
		 * paste-only flows produce zero keydowns and were wrongly blocked. The
		 * total-interactions threshold above is the gate. (The client also now
		 * counts input/paste/change toward interaction.)
		 */
		self::debug_log( 'Interaction PASS' );
		return true;
	}

	/*
	 * =========================================================
	 * PROOF OF WORK
	 * =========================================================
	 */

	/**
	 * Generate PoW challenge.
	 *
	 * @since  1.5.0
	 * @param  string $session_id Session ID.
	 * @return string|null Challenge string or null if disabled.
	 */
	public static function generate_pow_challenge( $session_id ) {
		if ( ! NBUF_Options::get( 'nbuf_antibot_pow', true ) ) {
			return null;
		}

		$challenge = bin2hex( random_bytes( 16 ) );

		/* Append to the per-session ring (see generate_js_seed). */
		$pow_key  = self::SESSION_PREFIX . 'pow_' . $session_id;
		$pow_ring = self::normalize_pow_ring( get_transient( $pow_key ) );
		$pow_ring[] = $challenge;
		if ( count( $pow_ring ) > self::CHALLENGE_RING_MAX ) {
			$pow_ring = array_slice( $pow_ring, -self::CHALLENGE_RING_MAX );
		}
		set_transient( $pow_key, $pow_ring, 2 * HOUR_IN_SECONDS );

		return $challenge;
	}

	/**
	 * Get PoW difficulty level.
	 *
	 * @since  1.5.0
	 * @return int Number of leading zeros required.
	 */
	public static function get_pow_difficulty() {
		$level = NBUF_Options::get( 'nbuf_antibot_pow_difficulty', 'medium' );
		return isset( self::POW_DIFFICULTIES[ $level ] )
			? self::POW_DIFFICULTIES[ $level ]
			: self::POW_DIFFICULTIES['medium'];
	}

	/**
	 * Validate PoW solution.
	 *
	 * Client finds nonce where SHA256(challenge + nonce) has N leading zeros.
	 *
	 * @since  1.5.0
	 * @param  string $nonce      Nonce from client.
	 * @param  string $session_id Session ID.
	 * @return bool True if valid.
	 */
	public static function validate_pow( $nonce, $session_id ) {
		self::debug_log( 'validate_pow() - nonce: ' . $nonce . ', session_id: ' . $session_id );

		if ( ! NBUF_Options::get( 'nbuf_antibot_pow', true ) ) {
			self::debug_log( 'PoW check disabled' );
			return true;
		}

		if ( '' === $nonce || empty( $session_id ) ) {
			self::debug_log( 'PoW FAIL: empty nonce or session_id' );
			return false;
		}

		$transient_key = self::SESSION_PREFIX . 'pow_' . $session_id;
		$pow_ring      = self::normalize_pow_ring( get_transient( $transient_key ) );
		self::debug_log( 'Looking for PoW transient: ' . $transient_key . ' (ring size ' . count( $pow_ring ) . ')' );

		if ( empty( $pow_ring ) ) {
			self::debug_log( 'PoW FAIL: no transient found' );
			return false;
		}

		$difficulty = self::get_pow_difficulty();
		$prefix     = str_repeat( '0', $difficulty );

		/* Accept a solution against ANY unexpired ring entry. */
		foreach ( $pow_ring as $challenge ) {
			if ( ! is_string( $challenge ) || '' === $challenge ) {
				continue;
			}
			$hash = hash( 'sha256', $challenge . $nonce );
			if ( 0 === strpos( $hash, $prefix ) ) {
				self::debug_log( 'PoW PASS' );
				return true;
			}
		}

		self::debug_log( 'PoW FAIL: no ring entry matched' );
		return false;
	}

	/**
	 * In-request flag: set true once a real antibot validation pass succeeds,
	 * so the legitimate internal second validation in the same request
	 * short-circuits without re-consuming challenge transients. Server-side
	 * only — it cannot be influenced by client input.
	 *
	 * @var bool
	 */
	private static $request_validated = false;

	/*
	 * =========================================================
	 * MAIN VALIDATION
	 * =========================================================
	 */

	/**
	 * Validate all anti-bot checks.
	 *
	 * @since  1.5.0
	 * @param  array<string, mixed> $post_data POST data from form submission.
	 * @return true|WP_Error True if valid, WP_Error if blocked.
	 */
	/**
	 * Whether at least one antibot sub-check is enabled.
	 *
	 * @return bool True if any of honeypot/time/js/interaction/pow is active.
	 */
	private static function any_check_active(): bool {
		return (bool) NBUF_Options::get( 'nbuf_antibot_honeypot', true )
			|| (bool) NBUF_Options::get( 'nbuf_antibot_time_check', true )
			|| (bool) NBUF_Options::get( 'nbuf_antibot_js_token', true )
			|| (bool) NBUF_Options::get( 'nbuf_antibot_interaction', true )
			|| (bool) NBUF_Options::get( 'nbuf_antibot_pow', true );
	}

	public static function validate( array $post_data ) {
		self::debug_log( '========== ANTIBOT VALIDATION START ==========' );

		if ( ! self::is_enabled() ) {
			self::debug_log( 'Antibot disabled - skipping validation' );
			return true;
		}

		/*
		 * Misconfiguration signal: antibot is ON but every sub-check is disabled,
		 * so validate() would pass any POST. Log it (rate-limited) so the operator
		 * sees that no detection is active. The per-IP registration throttle still
		 * applies as a flood backstop.
		 */
		if ( ! self::any_check_active() && false === get_transient( 'nbuf_antibot_misconfig_logged' ) ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log( 'antibot_no_checks_active', 'warning', 'Anti-bot is enabled but all detection methods are disabled; registration bot protection is inactive.' );
			}
			set_transient( 'nbuf_antibot_misconfig_logged', 1, HOUR_IN_SECONDS );
		}

		/*
		 * Short-circuit when an upstream caller signals the antibot challenge
		 * has already been validated for this request. Without this, the
		 * shortcode handler validates once at form-handle time (which deletes
		 * the per-session js_token / pow transients), then register_user()
		 * calls validate_registration_data() which calls NBUF_Antibot::validate
		 * AGAIN — and the second call fails because the transients it needs
		 * have already been consumed. Default-config registration was
		 * therefore broken with antibot enabled.
		 */
		/*
		 * SECURITY: the "already validated this request" short-circuit must be
		 * driven by SERVER-side in-request state, never by a field in the
		 * inbound POST array. Previously this read
		 * $post_data['_nbuf_antibot_already_validated'], so any client could add
		 * that one field to skip every antibot check (honeypot, timing, JS
		 * token, proof-of-work) and mass-register. The flag below is set only
		 * after a real validation pass succeeds, so the legitimate internal
		 * second validation (register_user -> validate_registration_data) is
		 * recognised while client input cannot forge it.
		 */
		if ( self::$request_validated ) {
			self::debug_log( 'Antibot already validated this request - skipping' );
			return true;
		}

		$failed_checks = array();
		$ip_address    = self::get_client_ip();
		$session_id    = isset( $post_data['nbuf_session'] )
			? sanitize_text_field( $post_data['nbuf_session'] )
			: '';

		/* Validate session ID format matches what get_or_create_session_id() generates */
		if ( ! empty( $session_id ) && ( strlen( $session_id ) !== 32 || ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) ) ) {
			$failed_checks[] = 'invalid_session';
			$session_id      = '';
		}

		self::debug_log( 'Session ID from POST: ' . ( $session_id ? $session_id : '(empty)' ) );
		self::debug_log( 'POST keys: ' . implode( ', ', array_keys( $post_data ) ) );

		/* Log antibot-related POST values */
		$antibot_fields = array( 'nbuf_session', 'nbuf_form_token', 'nbuf_js_token', 'nbuf_interaction', 'nbuf_pow_nonce' );
		foreach ( $antibot_fields as $field ) {
			$value = isset( $post_data[ $field ] ) ? $post_data[ $field ] : '(not set)';
			if ( strlen( $value ) > 100 ) {
				$value = substr( $value, 0, 100 ) . '...';
			}
			self::debug_log( "POST[$field]: $value" );
		}

		/* 1. Honeypot validation */
		$honeypot_result = self::validate_honeypot( $post_data );
		self::debug_log( '1. Honeypot check: ' . ( $honeypot_result ? 'PASS' : 'FAIL' ) );
		if ( ! $honeypot_result ) {
			$failed_checks[] = 'honeypot';
		}

		/* 2. Time check validation */
		$time_token  = isset( $post_data['nbuf_form_token'] )
			? sanitize_text_field( $post_data['nbuf_form_token'] )
			: '';
		$time_result = self::validate_time_check( $time_token );
		self::debug_log( '2. Time check: ' . ( $time_result ? 'PASS' : 'FAIL' ) );
		if ( ! $time_result ) {
			$failed_checks[] = 'time_check';
		}

		/* 3. JavaScript token validation */
		$js_token  = isset( $post_data['nbuf_js_token'] )
			? sanitize_text_field( $post_data['nbuf_js_token'] )
			: '';
		$js_result = self::validate_js_token( $js_token, $session_id );
		self::debug_log( '3. JS token check: ' . ( $js_result ? 'PASS' : 'FAIL' ) );
		if ( ! $js_result ) {
			$failed_checks[] = 'js_token';
		}

		/* 4. Interaction detection */
		$interaction_data   = isset( $post_data['nbuf_interaction'] )
			? sanitize_text_field( $post_data['nbuf_interaction'] )
			: '';
		$interaction_result = self::validate_interaction( $interaction_data );
		self::debug_log( '4. Interaction check: ' . ( $interaction_result ? 'PASS' : 'FAIL' ) );
		if ( ! $interaction_result ) {
			$failed_checks[] = 'interaction';
		}

		/* 5. Proof of work */
		$pow_nonce  = isset( $post_data['nbuf_pow_nonce'] )
			? sanitize_text_field( $post_data['nbuf_pow_nonce'] )
			: '';
		$pow_result = self::validate_pow( $pow_nonce, $session_id );
		self::debug_log( '5. PoW check: ' . ( $pow_result ? 'PASS' : 'FAIL' ) );
		if ( ! $pow_result ) {
			$failed_checks[] = 'pow';
		}

		/* Log and block if any checks failed */
		if ( ! empty( $failed_checks ) ) {
			self::debug_log( 'BLOCKED - Failed checks: ' . implode( ', ', $failed_checks ) );
			self::log_blocked_attempt( $ip_address, $failed_checks );

			return new WP_Error(
				'antibot_blocked',
				__( 'Registration blocked due to suspicious activity. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Consume challenge transients so they cannot be replayed */
		if ( ! empty( $session_id ) ) {
			delete_transient( self::SESSION_PREFIX . 'js_' . $session_id );
			delete_transient( self::SESSION_PREFIX . 'pow_' . $session_id );
		}

		self::debug_log( 'ALL CHECKS PASSED - Registration allowed' );
		self::debug_log( '========== ANTIBOT VALIDATION END ==========' );

		/* Mark this request validated so the internal re-validation short-circuits. */
		self::$request_validated = true;

		return true;
	}

	/**
	 * Log blocked bot attempt to security log.
	 *
	 * @since 1.5.0
	 * @param string   $ip_address    Client IP address.
	 * @param string[] $failed_checks Array of failed check names.
	 * @return void
	 */
	private static function log_blocked_attempt( string $ip_address, array $failed_checks ): void {
		self::debug_log( 'log_blocked_attempt() called' );

		if ( ! class_exists( 'NBUF_Security_Log' ) ) {
			self::debug_log( 'NBUF_Security_Log class NOT found!' );
			return;
		}

		self::debug_log( 'Calling NBUF_Security_Log::log_or_update()...' );

		$result = NBUF_Security_Log::log_or_update(
			'registration_bot_blocked',
			'warning',
			'Bot registration attempt blocked',
			array(
				'ip_address'    => $ip_address,
				'failed_checks' => implode( ', ', $failed_checks ),
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] )
					? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
					: '',
			),
			0
		);

		self::debug_log( 'Security log result: ' . ( $result ? 'success' : 'failed' ) );
	}

	/**
	 * Get client IP address.
	 *
	 * @since  1.5.0
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		return NBUF_IP::get_client_ip( false );
	}

	/**
	 * Render all anti-bot fields for form.
	 *
	 * @since  1.5.0
	 * @return string HTML for all anti-bot fields.
	 */
	/**
	 * Mark the current page as non-cacheable.
	 *
	 * Sets the constants honored by the major page-cache plugins (WP Super
	 * Cache, W3TC, WP Rocket, LiteSpeed, etc.) so a page carrying a nonce or a
	 * one-time antibot challenge is never written to a shared cache. Also emits
	 * no-cache headers when output has not started.
	 *
	 * @return void
	 */
	public static function prevent_page_caching(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		if ( ! headers_sent() && function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	public static function render_fields() {
		self::debug_log( 'render_fields() called' );

		/*
		 * The registration form embeds a per-request WordPress nonce AND (when
		 * enabled) a one-time antibot session/challenge. Serving it from a
		 * full-page cache shares those across visitors: the first submit consumes
		 * the one-time tokens and every later visitor is blocked, and a cached
		 * nonce fails verification. Mark the page non-cacheable. Done before the
		 * is_enabled() check so the nonce is protected even with antibot off.
		 */
		self::prevent_page_caching();

		if ( ! self::is_enabled() ) {
			self::debug_log( 'Antibot disabled - returning empty' );
			return '';
		}

		$session_id = self::get_or_create_session_id();
		self::debug_log( 'render_fields() session_id: ' . $session_id );

		/*
		 * Self-heal the enqueue/render gate divergence: the challenge transient
		 * and antibot.js are normally minted/enqueued by enqueue_scripts() on
		 * wp_enqueue_scripts, gated by is_registration_page(). If the form is
		 * placed where that gate cannot see it (reusable block / synced pattern /
		 * widget / nested shortcode / null $post), enqueue_scripts() bailed: no JS
		 * loaded and no challenge minted, so a real submit would be hard-blocked.
		 * If the script was not enqueued for this request, enqueue + localize now
		 * (localize mints the js/pow challenge for THIS session). Runs from the
		 * render path so the two gates cannot diverge.
		 */
		if ( ! wp_script_is( 'nbuf-antibot', 'enqueued' ) ) {
			self::debug_log( 'render_fields() self-heal: antibot.js not enqueued, enqueuing + minting now' );
			NBUF_Asset_Minifier::enqueue_script(
				'nbuf-antibot',
				'assets/js/frontend/antibot.js',
				array()
			);
			wp_localize_script( 'nbuf-antibot', 'nbufAntibot', self::get_client_config() );
		}

		$html  = self::render_honeypot_fields();
		$html .= self::render_time_field();
		$html .= sprintf(
			'<input type="hidden" name="nbuf_session" value="%s">',
			esc_attr( $session_id )
		);
		$html .= '<input type="hidden" name="nbuf_js_token" value="">';
		$html .= '<input type="hidden" name="nbuf_interaction" value="">';
		$html .= '<input type="hidden" name="nbuf_pow_nonce" value="">';

		self::debug_log( 'render_fields() completed - HTML length: ' . strlen( $html ) );

		return $html;
	}

	/**
	 * Debug logging helper.
	 *
	 * Logs to debug.log when WP_DEBUG_LOG is enabled.
	 *
	 * @since 1.5.0
	 * @param string $message Message to log.
	 * @return void
	 */
	private static function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[NBUF Antibot] ' . $message );
		}
	}

	/**
	 * Normalize the stored JS-seed challenge into a ring (list of entries).
	 *
	 * Back-compat: a pre-ring value was a single assoc array
	 * array('seed'=>.., 'timestamp'=>..); wrap it as a one-element ring so
	 * in-flight challenges minted before the upgrade still validate.
	 *
	 * @param  mixed $value Stored transient value.
	 * @return array<int, array<string, mixed>> Ring of {seed,timestamp} entries.
	 */
	private static function normalize_js_ring( $value ): array {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}
		if ( isset( $value['seed'], $value['timestamp'] ) ) {
			return array(
				array(
					'seed'      => $value['seed'],
					'timestamp' => $value['timestamp'],
				),
			);
		}
		return array_values( $value );
	}

	/**
	 * Normalize the stored PoW challenge into a ring (list of strings).
	 *
	 * Back-compat: a pre-ring value was a single challenge string; wrap it.
	 *
	 * @param  mixed $value Stored transient value.
	 * @return array<int, string> Ring of challenge strings.
	 */
	private static function normalize_pow_ring( $value ): array {
		if ( empty( $value ) ) {
			return array();
		}
		if ( is_string( $value ) ) {
			return array( $value );
		}
		if ( is_array( $value ) ) {
			return array_values( array_filter( $value, 'is_string' ) );
		}
		return array();
	}
}
