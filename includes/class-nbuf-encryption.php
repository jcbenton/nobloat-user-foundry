<?php
/**
 * Encryption Utility
 *
 * Provides AES-256-GCM authenticated encryption for sensitive data at rest.
 * Used to encrypt TOTP secrets, webhook secrets, and other sensitive values.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Encryption class.
 *
 * Handles encryption and decryption of sensitive data using AES-256-GCM.
 */
class NBUF_Encryption {

	/**
	 * Encryption cipher algorithm.
	 *
	 * @var string
	 */
	const CIPHER = 'aes-256-gcm';

	/**
	 * Encryption key cache.
	 *
	 * @var string|null
	 */
	private static $key_cache = null;

	/**
	 * Prefix for encrypted values to identify them.
	 *
	 * @var string
	 */
	const ENCRYPTED_PREFIX = '$nbuf_enc$';

	/**
	 * Get the encryption key.
	 *
	 * Derives a 256-bit key from WordPress AUTH_KEY using HKDF.
	 * Falls back to SECURE_AUTH_KEY if AUTH_KEY is not available.
	 *
	 * @return string 32-byte encryption key.
	 */
	private static function get_key(): string {
		if ( null !== self::$key_cache ) {
			return self::$key_cache;
		}

		/* Use AUTH_KEY as the base key material */
		$base_key = defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : '';

		/* Fall back to SECURE_AUTH_KEY if AUTH_KEY is default/empty */
		if ( empty( $base_key ) || 'put your unique phrase here' === $base_key ) {
			$base_key = defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ? SECURE_AUTH_KEY : '';
		}

		/* If still no key, use a site-specific fallback (not ideal but prevents errors) */
		if ( empty( $base_key ) || 'put your unique phrase here' === $base_key ) {
			$base_key = get_option( 'siteurl' ) . get_option( 'admin_email' );
		}

		/*
		 * Derive a proper 256-bit key using HKDF.
		 * This ensures consistent key length regardless of input length.
		 */
		self::$key_cache = hash_hkdf( 'sha256', $base_key, 32, 'nbuf_encryption_v1' );

		return self::$key_cache;
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool True if OpenSSL is available with required cipher.
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		return in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt a string value.
	 *
	 * Uses AES-256-GCM for authenticated encryption.
	 * Returns prefixed base64 string: $nbuf_enc$base64(iv + ciphertext + tag)
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Encrypted value with prefix, or original if encryption unavailable.
	 */
	public static function encrypt( string $plaintext ): string {
		/* Return empty string as-is */
		if ( '' === $plaintext ) {
			return '';
		}

		/* If already encrypted, return as-is */
		if ( self::is_encrypted( $plaintext ) ) {
			return $plaintext;
		}

		/* Check if encryption is available */
		if ( ! self::is_available() ) {
			return $plaintext;
		}

		$key = self::get_key();

		/* Generate random IV (12 bytes for GCM) */
		$iv = random_bytes( 12 );

		/* Encrypt with authentication tag */
		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'', /* Additional authenticated data (AAD) - not used */
			16  /* Tag length */
		);

		if ( false === $ciphertext ) {
			/* Encryption failed - return original (logged for debugging) */
			error_log( 'NBUF Encryption: openssl_encrypt failed' );
			return $plaintext;
		}

		/* Combine IV + ciphertext + tag and encode */
		$combined = $iv . $ciphertext . $tag;

		return self::ENCRYPTED_PREFIX . base64_encode( $combined );
	}

	/**
	 * Decrypt an encrypted string value.
	 *
	 * Expects format: $nbuf_enc$base64(iv + ciphertext + tag)
	 *
	 * @param string $encrypted The encrypted value.
	 * @return string Decrypted value, or original if not encrypted/decryption fails.
	 */
	public static function decrypt( string $encrypted ): string {
		/* Return empty string as-is */
		if ( '' === $encrypted ) {
			return '';
		}

		/* Check if this is an encrypted value */
		if ( ! self::is_encrypted( $encrypted ) ) {
			return $encrypted;
		}

		/* Check if decryption is available */
		if ( ! self::is_available() ) {
			error_log( 'NBUF Encryption: OpenSSL not available for decryption' );
			return '';
		}

		$key = self::get_key();

		/* Remove prefix and decode */
		$encoded  = substr( $encrypted, strlen( self::ENCRYPTED_PREFIX ) );
		$combined = base64_decode( $encoded, true );

		if ( false === $combined ) {
			error_log( 'NBUF Encryption: base64_decode failed' );
			return '';
		}

		/* IV is 12 bytes, tag is 16 bytes, rest is ciphertext */
		$iv_length  = 12;
		$tag_length = 16;

		if ( strlen( $combined ) < $iv_length + $tag_length + 1 ) {
			error_log( 'NBUF Encryption: Invalid encrypted data length' );
			return '';
		}

		$iv         = substr( $combined, 0, $iv_length );
		$tag        = substr( $combined, -$tag_length );
		$ciphertext = substr( $combined, $iv_length, -$tag_length );

		/* Decrypt with tag verification */
		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $plaintext ) {
			error_log( 'NBUF Encryption: Decryption failed (authentication check failed or corrupted data)' );
			return '';
		}

		return $plaintext;
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::ENCRYPTED_PREFIX );
	}

	/**
	 * Re-encrypt a value (useful for key rotation).
	 *
	 * Decrypts with current key and re-encrypts.
	 * If value is not encrypted, just encrypts it.
	 *
	 * @param string $value The value to re-encrypt.
	 * @return string Re-encrypted value.
	 */
	public static function reencrypt( string $value ): string {
		$decrypted = self::decrypt( $value );
		return self::encrypt( $decrypted );
	}
}
