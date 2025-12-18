<?php
/**
 * Anti-Bot Protection Test Script
 *
 * Run from command line: php tests/test-antibot.php
 * Or via WP-CLI: wp eval-file tests/test-antibot.php
 *
 * Tests each anti-bot mechanism by simulating bot behavior.
 * Each test should FAIL validation (which means the protection is working).
 *
 * @package NoBloat_User_Foundry
 */

/* Load WordPress if running standalone */
if ( ! defined( 'ABSPATH' ) ) {
	/* Find wp-load.php */
	$wp_load_paths = array(
		dirname( __DIR__, 4 ) . '/wp-load.php',      /* Standard plugin location */
		dirname( __DIR__, 3 ) . '/wp-load.php',      /* Alternate location */
		'/var/www/html/wp-load.php',                  /* Common server path */
	);

	$loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$loaded = true;
			break;
		}
	}

	if ( ! $loaded ) {
		echo "Error: Could not find wp-load.php\n";
		echo "Run this script from WordPress root or use: wp eval-file tests/test-antibot.php\n";
		exit( 1 );
	}
}

/* Ensure we have the antibot class */
if ( ! class_exists( 'NBUF_Antibot' ) ) {
	echo "Error: NBUF_Antibot class not found. Is the plugin active?\n";
	exit( 1 );
}

echo "\n";
echo "=======================================================\n";
echo "  NoBloat User Foundry - Anti-Bot Protection Tests\n";
echo "=======================================================\n\n";

/* Check if anti-bot is enabled */
$enabled = NBUF_Options::get( 'nbuf_antibot_enabled', true );
echo "Anti-bot protection enabled: " . ( $enabled ? 'YES' : 'NO' ) . "\n\n";

if ( ! $enabled ) {
	echo "Warning: Anti-bot protection is disabled. Enable it first to test.\n";
	echo "Go to: Backend > Security > Registration\n\n";
}

/* Show current settings */
echo "Current Settings:\n";
echo "-----------------\n";
echo "Honeypot:        " . ( NBUF_Options::get( 'nbuf_antibot_honeypot', true ) ? 'ON' : 'OFF' ) . "\n";
echo "Time Check:      " . ( NBUF_Options::get( 'nbuf_antibot_time_check', true ) ? 'ON' : 'OFF' );
echo " (min " . NBUF_Options::get( 'nbuf_antibot_min_time', 3 ) . "s)\n";
echo "JS Token:        " . ( NBUF_Options::get( 'nbuf_antibot_js_token', true ) ? 'ON' : 'OFF' ) . "\n";
echo "Interaction:     " . ( NBUF_Options::get( 'nbuf_antibot_interaction', true ) ? 'ON' : 'OFF' );
echo " (min " . NBUF_Options::get( 'nbuf_antibot_min_interactions', 3 ) . ")\n";
echo "Proof of Work:   " . ( NBUF_Options::get( 'nbuf_antibot_pow', true ) ? 'ON' : 'OFF' );
echo " (" . NBUF_Options::get( 'nbuf_antibot_pow_difficulty', 'medium' ) . ")\n";
echo "\n";

$tests_run    = 0;
$tests_passed = 0;

/**
 * Run a single anti-bot validation test.
 *
 * @param string $name        Test name.
 * @param array  $post_data   POST data to validate.
 * @param string $expect_fail Expected failure check name (or empty for pass).
 */
function run_antibot_test( $name, $post_data, $expect_fail = '' ) {
	global $tests_run, $tests_passed;
	++$tests_run;

	echo "Test: $name\n";

	$result = NBUF_Antibot::validate( $post_data );

	if ( is_wp_error( $result ) ) {
		$error_code = $result->get_error_code();
		if ( 'antibot_blocked' === $error_code ) {
			if ( ! empty( $expect_fail ) ) {
				echo "  ✓ PASS - Blocked as expected (protection working)\n";
				++$tests_passed;
			} else {
				echo "  ✗ FAIL - Blocked but should have passed\n";
			}
		} else {
			echo "  ? UNEXPECTED ERROR: $error_code\n";
		}
	} else {
		if ( empty( $expect_fail ) ) {
			echo "  ✓ PASS - Allowed as expected\n";
			++$tests_passed;
		} else {
			echo "  ✗ FAIL - Should have been blocked by: $expect_fail\n";
		}
	}
	echo "\n";
}

echo "=======================================================\n";
echo "  Running Tests (each should BLOCK = protection works)\n";
echo "=======================================================\n\n";

/* Generate a valid session for testing */
$test_session = bin2hex( random_bytes( 16 ) );

/* Test 1: Completely empty submission (bot behavior) */
run_antibot_test(
	'Empty submission (no tokens)',
	array(),
	'all'
);

/* Test 2: Honeypot filled (bot fills all fields) */
$honeypot_fields = NBUF_Antibot::get_honeypot_fields();
$honeypot_data   = array(
	'nbuf_session' => $test_session,
);
foreach ( $honeypot_fields as $field ) {
	$honeypot_data[ $field ] = 'bot filled this';
}
run_antibot_test(
	'Honeypot field filled',
	$honeypot_data,
	'honeypot'
);

/* Test 3: Instant submission (time check - too fast) */
/* Generate a token that's only 1 second old */
$instant_timestamp = time();
$instant_nonce     = wp_create_nonce( 'nbuf_antibot_time_' . $instant_timestamp );
$instant_token     = base64_encode( $instant_timestamp . '|' . $instant_nonce );

run_antibot_test(
	'Instant submission (0 seconds)',
	array(
		'nbuf_session'    => $test_session,
		'nbuf_form_token' => $instant_token,
	),
	'time_check'
);

/* Test 4: Missing JS token */
/* Create a valid time token (5 seconds ago) */
$valid_timestamp = time() - 5;
$valid_nonce     = wp_create_nonce( 'nbuf_antibot_time_' . $valid_timestamp );
$valid_time_token = base64_encode( $valid_timestamp . '|' . $valid_nonce );

run_antibot_test(
	'Missing JavaScript token',
	array(
		'nbuf_session'    => $test_session,
		'nbuf_form_token' => $valid_time_token,
		'nbuf_js_token'   => '',
	),
	'js_token'
);

/* Test 5: No interaction data */
run_antibot_test(
	'No interaction data',
	array(
		'nbuf_session'     => $test_session,
		'nbuf_form_token'  => $valid_time_token,
		'nbuf_js_token'    => 'fake_token',
		'nbuf_interaction' => '',
	),
	'interaction'
);

/* Test 6: Insufficient interaction (only 1 event, no keyboard) */
$low_interaction = base64_encode( json_encode( array(
	'mouse'    => 1,
	'keyboard' => 0,
	'focus'    => 0,
	'scroll'   => 0,
) ) );

run_antibot_test(
	'Insufficient interaction (no keyboard)',
	array(
		'nbuf_session'     => $test_session,
		'nbuf_form_token'  => $valid_time_token,
		'nbuf_js_token'    => 'fake_token',
		'nbuf_interaction' => $low_interaction,
	),
	'interaction'
);

/* Test 7: Missing Proof of Work */
$valid_interaction = base64_encode( json_encode( array(
	'mouse'    => 10,
	'keyboard' => 5,
	'focus'    => 3,
	'scroll'   => 2,
) ) );

run_antibot_test(
	'Missing Proof of Work nonce',
	array(
		'nbuf_session'     => $test_session,
		'nbuf_form_token'  => $valid_time_token,
		'nbuf_js_token'    => 'fake_token',
		'nbuf_interaction' => $valid_interaction,
		'nbuf_pow_nonce'   => '',
	),
	'pow'
);

/* Test 8: Invalid Proof of Work */
run_antibot_test(
	'Invalid Proof of Work nonce',
	array(
		'nbuf_session'     => $test_session,
		'nbuf_form_token'  => $valid_time_token,
		'nbuf_js_token'    => 'fake_token',
		'nbuf_interaction' => $valid_interaction,
		'nbuf_pow_nonce'   => '12345',
	),
	'pow'
);

echo "=======================================================\n";
echo "  Test Results\n";
echo "=======================================================\n\n";

echo "Tests run:    $tests_run\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: " . ( $tests_run - $tests_passed ) . "\n\n";

if ( $tests_passed === $tests_run ) {
	echo "✓ All anti-bot protections are working correctly!\n\n";
} else {
	echo "✗ Some tests failed. Check the settings above.\n\n";
}

/* Check security log for entries */
echo "=======================================================\n";
echo "  Security Log Entries (last 10)\n";
echo "=======================================================\n\n";

if ( class_exists( 'NBUF_Security_Log' ) ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'nbuf_security_log';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE event_type = %s ORDER BY timestamp DESC LIMIT 10",
				$table_name,
				'registration_bot_blocked'
			)
		);

		if ( empty( $entries ) ) {
			echo "No 'registration_bot_blocked' entries found yet.\n";
			echo "(Run this test a few times to generate log entries)\n\n";
		} else {
			echo "Found " . count( $entries ) . " entries:\n\n";
			foreach ( $entries as $entry ) {
				$context = json_decode( $entry->context, true );
				echo "ID: {$entry->id}\n";
				echo "  Time:        {$entry->timestamp}\n";
				echo "  Occurrences: {$entry->occurrence_count}\n";
				echo "  IP:          " . ( $context['ip_address'] ?? 'N/A' ) . "\n";
				echo "  Failed:      " . ( $context['failed_checks'] ?? 'N/A' ) . "\n";
				echo "\n";
			}
		}
	} else {
		echo "Security log table does not exist.\n\n";
	}
} else {
	echo "NBUF_Security_Log class not found.\n\n";
}

echo "=======================================================\n";
echo "  Manual Browser Testing\n";
echo "=======================================================\n\n";

echo "To test in a real browser:\n\n";

echo "1. DISABLE JavaScript and try to register:\n";
echo "   - Should fail (JS token missing)\n\n";

echo "2. Fill form VERY fast (under 3 seconds):\n";
echo "   - Should fail (time check)\n\n";

echo "3. Use browser dev tools to fill honeypot:\n";
echo "   - Find hidden fields with 'contact_', 'website_', 'company_' prefix\n";
echo "   - Fill them and submit - should fail\n\n";

echo "4. Normal registration should work fine.\n\n";

$reg_page = NBUF_Options::get( 'nbuf_page_registration', 0 );
if ( $reg_page ) {
	echo "Registration page: " . get_permalink( $reg_page ) . "\n\n";
}

echo "Done!\n\n";
