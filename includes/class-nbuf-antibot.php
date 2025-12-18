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
	 * @var array
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
	 */
	public static function init() {
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
	 */
	public static function enqueue_scripts() {
		self::debug_log( 'enqueue_scripts() called' );

		if ( ! self::is_registration_page() ) {
			self::debug_log( 'Not a registration page - skipping script enqueue' );
			return;
		}

		self::debug_log( 'Registration page detected - enqueueing antibot.js' );

		wp_enqueue_script(
			'nbuf-antibot',
			NBUF_PLUGIN_URL . 'assets/js/frontend/antibot.js',
			array(),
			NBUF_VERSION,
			true
		);

		$config = self::get_client_config();
		self::debug_log( 'Client config: ' . wp_json_encode( $config ) );
		wp_localize_script( 'nbuf-antibot', 'nbufAntibot', $config );
	}

	/**
	 * Get client-side configuration for JavaScript.
	 *
	 * @since  1.5.0
	 * @return array Configuration data.
	 */
	private static function get_client_config() {
		$session_id  = self::get_or_create_session_id();
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
			self::debug_log( 'Universal Router current_view: ' . ( $current_view ?: '(empty)' ) );

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

	/* =========================================================
	   SESSION MANAGEMENT
	   ========================================================= */

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

		if ( empty( $session_id ) || strlen( $session_id ) !== 32 ) {
			$session_id = bin2hex( random_bytes( 16 ) );
		}

		/* Cache for this request */
		self::$request_session_id = $session_id;

		return $session_id;
	}

	/* =========================================================
	   HONEYPOT FIELDS
	   ========================================================= */

	/**
	 * Generate honeypot field names (rotates hourly).
	 *
	 * @since  1.5.0
	 * @return array Associative array of field keys to names.
	 */
	public static function get_honeypot_fields() {
		$rotation_key = floor( time() / self::HONEYPOT_ROTATION );
		$site_salt    = wp_salt( 'auth' );

		$base_hash = hash( 'sha256', $site_salt . $rotation_key );

		return array(
			'field1' => 'contact_' . substr( $base_hash, 0, 8 ),
			'field2' => 'website_' . substr( $base_hash, 8, 8 ),
			'field3' => 'company_' . substr( $base_hash, 16, 8 ),
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
	 * @param  array $data POST data.
	 * @return bool True if valid (honeypots empty).
	 */
	public static function validate_honeypot( $data ) {
		if ( ! NBUF_Options::get( 'nbuf_antibot_honeypot', true ) ) {
			return true;
		}

		$fields = self::get_honeypot_fields();

		/* Also check previous rotation period (handles edge case at rotation boundary) */
		$prev_rotation_key = floor( ( time() - self::HONEYPOT_ROTATION ) / self::HONEYPOT_ROTATION );
		$prev_base_hash    = hash( 'sha256', wp_salt( 'auth' ) . $prev_rotation_key );
		$prev_fields       = array(
			'contact_' . substr( $prev_base_hash, 0, 8 ),
			'website_' . substr( $prev_base_hash, 8, 8 ),
			'company_' . substr( $prev_base_hash, 16, 8 ),
		);

		$all_fields = array_merge( array_values( $fields ), $prev_fields );

		foreach ( $all_fields as $field_name ) {
			if ( isset( $data[ $field_name ] ) && '' !== $data[ $field_name ] ) {
				return false;
			}
		}

		return true;
	}

	/* =========================================================
	   TIME CHECK
	   ========================================================= */

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

		/* Reject forms older than 1 hour (stale tokens) */
		if ( $elapsed > 3600 ) {
			return false;
		}

		return true;
	}

	/* =========================================================
	   JAVASCRIPT TOKEN
	   ========================================================= */

	/**
	 * Generate seed for JS token computation.
	 *
	 * @since  1.5.0
	 * @param  string $session_id Session ID.
	 * @return array Array with 'seed' and 'timestamp' keys.
	 */
	public static function generate_js_seed( $session_id ) {
		if ( ! NBUF_Options::get( 'nbuf_antibot_js_token', true ) ) {
			return array(
				'seed'      => '',
				'timestamp' => '',
			);
		}

		$seed      = bin2hex( random_bytes( 8 ) );
		$timestamp = time();

		/* Store seed and timestamp for validation */
		set_transient(
			self::SESSION_PREFIX . 'js_' . $session_id,
			array(
				'seed'      => $seed,
				'timestamp' => $timestamp,
			),
			HOUR_IN_SECONDS
		);

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
		$challenge     = get_transient( $transient_key );
		self::debug_log( 'Looking for transient: ' . $transient_key );
		self::debug_log( 'Transient value: ' . wp_json_encode( $challenge ) );

		if ( ! $challenge || ! isset( $challenge['seed'], $challenge['timestamp'] ) ) {
			self::debug_log( 'JS token FAIL: no transient or missing seed/timestamp' );
			return false;
		}

		/* Compute expected token */
		$expected = hash( 'sha256', $challenge['seed'] . $challenge['timestamp'] . $session_id );
		self::debug_log( 'Expected token: ' . $expected );
		self::debug_log( 'Received token: ' . $token );
		self::debug_log( 'Tokens match: ' . ( hash_equals( $expected, $token ) ? 'YES' : 'NO' ) );

		/* Constant-time comparison */
		return hash_equals( $expected, $token );
	}

	/* =========================================================
	   INTERACTION DETECTION
	   ========================================================= */

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

		/* Require at least some keyboard interaction (typing) */
		if ( $key_events < 1 ) {
			self::debug_log( 'Interaction FAIL: no keyboard events' );
			return false;
		}

		self::debug_log( 'Interaction PASS' );
		return true;
	}

	/* =========================================================
	   PROOF OF WORK
	   ========================================================= */

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

		set_transient(
			self::SESSION_PREFIX . 'pow_' . $session_id,
			$challenge,
			HOUR_IN_SECONDS
		);

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
		$challenge     = get_transient( $transient_key );
		self::debug_log( 'Looking for PoW transient: ' . $transient_key );
		self::debug_log( 'PoW challenge: ' . ( $challenge ?: '(not found)' ) );

		if ( ! $challenge ) {
			self::debug_log( 'PoW FAIL: no transient found' );
			return false;
		}

		$difficulty = self::get_pow_difficulty();
		$hash       = hash( 'sha256', $challenge . $nonce );

		/* Check for N leading zeros (hex digits) */
		$prefix = str_repeat( '0', $difficulty );

		self::debug_log( "PoW difficulty=$difficulty, prefix=$prefix, hash=$hash" );

		$result = 0 === strpos( $hash, $prefix );
		self::debug_log( 'PoW ' . ( $result ? 'PASS' : 'FAIL' ) );

		return $result;
	}

	/* =========================================================
	   MAIN VALIDATION
	   ========================================================= */

	/**
	 * Validate all anti-bot checks.
	 *
	 * @since  1.5.0
	 * @param  array $post_data POST data from form submission.
	 * @return true|WP_Error True if valid, WP_Error if blocked.
	 */
	public static function validate( $post_data ) {
		self::debug_log( '========== ANTIBOT VALIDATION START ==========' );

		if ( ! self::is_enabled() ) {
			self::debug_log( 'Antibot disabled - skipping validation' );
			return true;
		}

		$failed_checks = array();
		$ip_address    = self::get_client_ip();
		$session_id    = isset( $post_data['nbuf_session'] )
			? sanitize_text_field( $post_data['nbuf_session'] )
			: '';

		self::debug_log( 'Session ID from POST: ' . ( $session_id ?: '(empty)' ) );
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
		$time_token = isset( $post_data['nbuf_form_token'] )
			? sanitize_text_field( $post_data['nbuf_form_token'] )
			: '';
		$time_result = self::validate_time_check( $time_token );
		self::debug_log( '2. Time check: ' . ( $time_result ? 'PASS' : 'FAIL' ) );
		if ( ! $time_result ) {
			$failed_checks[] = 'time_check';
		}

		/* 3. JavaScript token validation */
		$js_token = isset( $post_data['nbuf_js_token'] )
			? sanitize_text_field( $post_data['nbuf_js_token'] )
			: '';
		$js_result = self::validate_js_token( $js_token, $session_id );
		self::debug_log( '3. JS token check: ' . ( $js_result ? 'PASS' : 'FAIL' ) );
		if ( ! $js_result ) {
			$failed_checks[] = 'js_token';
		}

		/* 4. Interaction detection */
		$interaction_data = isset( $post_data['nbuf_interaction'] )
			? sanitize_text_field( $post_data['nbuf_interaction'] )
			: '';
		$interaction_result = self::validate_interaction( $interaction_data );
		self::debug_log( '4. Interaction check: ' . ( $interaction_result ? 'PASS' : 'FAIL' ) );
		if ( ! $interaction_result ) {
			$failed_checks[] = 'interaction';
		}

		/* 5. Proof of work */
		$pow_nonce = isset( $post_data['nbuf_pow_nonce'] )
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

		self::debug_log( 'ALL CHECKS PASSED - Registration allowed' );
		self::debug_log( '========== ANTIBOT VALIDATION END ==========' );

		return true;
	}

	/**
	 * Log blocked bot attempt to security log.
	 *
	 * @since 1.5.0
	 * @param string $ip_address    Client IP address.
	 * @param array  $failed_checks Array of failed check names.
	 */
	private static function log_blocked_attempt( $ip_address, $failed_checks ) {
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
	private static function get_client_ip() {
		$trusted_proxies = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return $remote_addr;
	}

	/**
	 * Render all anti-bot fields for form.
	 *
	 * @since  1.5.0
	 * @return string HTML for all anti-bot fields.
	 */
	public static function render_fields() {
		self::debug_log( 'render_fields() called' );

		if ( ! self::is_enabled() ) {
			self::debug_log( 'Antibot disabled - returning empty' );
			return '';
		}

		$session_id = self::get_or_create_session_id();
		self::debug_log( 'render_fields() session_id: ' . $session_id );

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
	 */
	private static function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[NBUF Antibot] ' . $message );
		}
	}
}
