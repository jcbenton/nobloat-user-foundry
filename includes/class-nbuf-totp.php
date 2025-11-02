<?php
/**
 * TOTP (Time-based One-Time Password) Implementation
 *
 * RFC 6238 compliant TOTP generator and verifier with zero external dependencies.
 * Works with Google Authenticator, Authy, 1Password, and other TOTP apps.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_TOTP class.
 *
 * Handles TOTP generation, verification, and Base32 encoding/decoding.
 */
class NBUF_TOTP {

	/**
	 * Generate cryptographically secure random secret
	 *
	 * Creates a random secret suitable for TOTP authentication.
	 * The secret is Base32 encoded for compatibility with authenticator apps.
	 *
	 * @param int $length Number of bytes for the secret (default 20 for 160-bit).
	 * @return string Base32 encoded secret.
	 */
	public static function generate_secret( $length = 20 ) {
		/* Generate random bytes using cryptographically secure method */
		$random_bytes = random_bytes( $length );

		/* Encode to Base32 for TOTP compatibility */
		return self::base32_encode( $random_bytes );
	}

	/**
	 * Generate TOTP code for current time window
	 *
	 * Generates a 6 or 8 digit TOTP code based on the secret and timestamp.
	 * Follows RFC 6238 specification.
	 *
	 * @param string   $secret Base32 encoded secret.
	 * @param int|null $timestamp Unix timestamp (null = current time).
	 * @param int      $code_length Length of code (6 or 8 digits).
	 * @param int      $time_step Time window in seconds (default 30).
	 * @return string TOTP code padded with zeros.
	 */
	public static function get_code( $secret, $timestamp = null, $code_length = 6, $time_step = 30 ) {
		/* Use current time if not specified */
		if ( $timestamp === null ) {
			$timestamp = time();
		}

		/* Get time counter */
		$time_counter = self::get_time_counter( $timestamp, $time_step );

		/* Decode Base32 secret */
		$secret_bytes = self::base32_decode( $secret );

		/* Convert counter to 8-byte big-endian */
		$time_bytes = pack( 'N*', 0, $time_counter );

		/* Generate HMAC-SHA1 hash */
		$hash = hash_hmac( 'sha1', $time_bytes, $secret_bytes, true );

		/* Extract dynamic offset from last nibble */
		$offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0F;

		/* Extract 4 bytes from offset */
		$truncated = substr( $hash, $offset, 4 );

		/* Convert to integer and mask to 31 bits */
		$code = unpack( 'N', $truncated )[1] & 0x7FFFFFFF;

		/* Get modulo based on code length */
		$modulo = pow( 10, $code_length );
		$code   = $code % $modulo;

		/* Pad with zeros */
		return str_pad( (string) $code, $code_length, '0', STR_PAD_LEFT );
	}

	/**
	 * Verify TOTP code with clock drift tolerance
	 *
	 * Checks if the provided code matches the expected TOTP code within
	 * a specified tolerance window to account for clock drift.
	 *
	 * @param string $secret Base32 encoded secret.
	 * @param string $code User-entered code.
	 * @param int    $tolerance Number of time windows to check (±N).
	 * @param int    $code_length Length of code (6 or 8 digits).
	 * @param int    $time_step Time window in seconds (default 30).
	 * @return bool True if code matches within tolerance.
	 */
	public static function verify_code( $secret, $code, $tolerance = 1, $code_length = 6, $time_step = 30 ) {
		/* Sanitize code - remove spaces and non-numeric characters */
		$code = preg_replace( '/[^0-9]/', '', $code );

		/* Validate code length */
		if ( strlen( $code ) !== $code_length ) {
			return false;
		}

		/* Get current timestamp */
		$timestamp = time();

		/* Check current window and tolerance windows */
		for ( $i = -$tolerance; $i <= $tolerance; $i++ ) {
			$check_time = $timestamp + ( $i * $time_step );
			$valid_code = self::get_code( $secret, $check_time, $code_length, $time_step );

			/* Use constant-time comparison to prevent timing attacks */
			if ( hash_equals( $valid_code, $code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get provisioning URI for QR code
	 *
	 * Generates the otpauth:// URI used by authenticator apps to set up TOTP.
	 * This URI is typically encoded in a QR code for easy scanning.
	 *
	 * @param string $secret Base32 encoded secret.
	 * @param string $username User's username or email.
	 * @param string $issuer Site name.
	 * @param int    $code_length Length of code (6 or 8 digits).
	 * @param int    $time_step Time window in seconds (default 30).
	 * @return string otpauth:// URI.
	 */
	public static function get_provisioning_uri( $secret, $username, $issuer, $code_length = 6, $time_step = 30 ) {
		/* Build the label: issuer:username */
		$label = rawurlencode( $issuer . ':' . $username );

		/* Build query parameters */
		$params = array(
			'secret'    => $secret,
			'issuer'    => rawurlencode( $issuer ),
			'digits'    => $code_length,
			'period'    => $time_step,
			'algorithm' => 'SHA1',
		);

		/* Build URI */
		$uri = 'otpauth://totp/' . $label . '?' . http_build_query( $params );

		return $uri;
	}

	/**
	 * Base32 encode (RFC 4648)
	 *
	 * Encodes binary data to Base32 string for TOTP compatibility.
	 *
	 * @param string $data Raw bytes.
	 * @return string Base32 encoded string.
	 */
	private static function base32_encode( $data ) {
		/* Base32 alphabet (RFC 4648) */
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$encoded  = '';
		$bits     = '';

		/* Convert each byte to binary string */
		for ( $i = 0; $i < strlen( $data ); $i++ ) {
			$bits .= str_pad( decbin( ord( $data[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		/* Split into 5-bit chunks and encode */
		$chunks = str_split( $bits, 5 );
		foreach ( $chunks as $chunk ) {
			/* Pad last chunk if needed */
			$chunk    = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
			$encoded .= $alphabet[ bindec( $chunk ) ];
		}

		/* Add padding */
		$padding = strlen( $encoded ) % 8;
		if ( $padding > 0 ) {
			$encoded .= str_repeat( '=', 8 - $padding );
		}

		return $encoded;
	}

	/**
	 * Base32 decode (RFC 4648)
	 *
	 * Decodes Base32 string back to binary data.
	 *
	 * @param string $data Base32 encoded string.
	 * @return string Raw bytes.
	 */
	private static function base32_decode( $data ) {
		/* Base32 alphabet (RFC 4648) */
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

		/* Remove padding and convert to uppercase */
		$data = strtoupper( rtrim( $data, '=' ) );

		/* Build reverse lookup array */
		$lookup = array_flip( str_split( $alphabet ) );

		$bits   = '';
		$output = '';

		/* Convert each character to 5-bit binary */
		for ( $i = 0; $i < strlen( $data ); $i++ ) {
			if ( ! isset( $lookup[ $data[ $i ] ] ) ) {
				/* Invalid character, skip */
				continue;
			}
			$bits .= str_pad( decbin( $lookup[ $data[ $i ] ] ), 5, '0', STR_PAD_LEFT );
		}

		/* Split into 8-bit chunks and convert to bytes */
		$chunks = str_split( $bits, 8 );
		foreach ( $chunks as $chunk ) {
			if ( strlen( $chunk ) === 8 ) {
				$output .= chr( bindec( $chunk ) );
			}
		}

		return $output;
	}

	/**
	 * Generate time counter for TOTP
	 *
	 * Calculates the time counter based on Unix timestamp and time step.
	 * This counter is used as the moving factor in TOTP generation.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @param int $time_step Time window in seconds (30 or 60).
	 * @return int Time counter.
	 */
	private static function get_time_counter( $timestamp, $time_step = 30 ) {
		return (int) floor( $timestamp / $time_step );
	}
}
