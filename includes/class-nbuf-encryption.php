<?php
/**
 * Encryption Utility
 *
 * Provides AES-256-GCM authenticated encryption for sensitive data at rest.
 * Used to encrypt TOTP secrets, webhook secrets, and other sensitive values.
 *
 * KEY MANAGEMENT
 * --------------
 * v2 (current): the data-encryption key is a dedicated 256-bit random value
 * stored in the `nbuf_encryption_key` option (autoload off). It is independent
 * of the WordPress salts, so rotating AUTH_KEY/SECURE_AUTH_KEY does NOT destroy
 * stored secrets. New ciphertext carries the `$nbuf_enc2$` prefix.
 *
 * v1 (legacy): older ciphertext (`$nbuf_enc$` prefix) was encrypted with a key
 * derived from AUTH_KEY via HKDF. decrypt() still transparently reads it with
 * the legacy key, and migrate_to_dedicated_key() re-encrypts it to v2 so it is
 * no longer salt-coupled. The legacy path is read-only; nothing writes v1.
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
	 * Dedicated-key (v2) cache.
	 *
	 * @var string|null
	 */
	private static $key_cache = null;

	/**
	 * Legacy (v1, AUTH_KEY-derived) key cache.
	 *
	 * @var string|null
	 */
	private static $legacy_key_cache = null;

	/**
	 * Option name holding the dedicated 256-bit data key (base64).
	 *
	 * @var string
	 */
	const KEY_OPTION = 'nbuf_encryption_key';

	/**
	 * Prefix for legacy (v1) encrypted values — AUTH_KEY-derived key.
	 *
	 * @var string
	 */
	const ENCRYPTED_PREFIX = '$nbuf_enc$';

	/**
	 * Prefix for current (v2) encrypted values — dedicated stored key.
	 *
	 * @var string
	 */
	const ENCRYPTED_PREFIX_V2 = '$nbuf_enc2$';

	/**
	 * Get the dedicated data-encryption key (v2).
	 *
	 * Reads a random 256-bit key from the `nbuf_encryption_key` option,
	 * generating and persisting one on first use. The stored value is run
	 * through HKDF for domain separation and consistent length. This key is
	 * decoupled from the WordPress salts so salt rotation cannot destroy
	 * encrypted data.
	 *
	 * @return string 32-byte encryption key.
	 */
	private static function get_dedicated_key(): string {
		if ( null !== self::$key_cache ) {
			return self::$key_cache;
		}

		$stored = get_option( self::KEY_OPTION );
		$raw    = '';

		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = base64_decode( $stored, true );
			if ( false !== $decoded && '' !== $decoded ) {
				$raw = $decoded;
			}
		}

		/* First use (or corrupt option): mint and persist a fresh random key. */
		if ( '' === $raw ) {
			$raw = random_bytes( 32 );
			update_option( self::KEY_OPTION, base64_encode( $raw ), false );
		}

		self::$key_cache = hash_hkdf( 'sha256', $raw, 32, 'nbuf_encryption_v2' );

		return self::$key_cache;
	}

	/**
	 * Get the legacy (v1) encryption key derived from WordPress AUTH_KEY.
	 *
	 * Read-only path used to decrypt data written before the dedicated-key
	 * migration. Falls back to SECURE_AUTH_KEY, then to a stored random
	 * fallback key if the salts are unconfigured.
	 *
	 * @return string 32-byte encryption key.
	 */
	private static function get_legacy_key(): string {
		if ( null !== self::$legacy_key_cache ) {
			return self::$legacy_key_cache;
		}

		/* Use AUTH_KEY as the base key material */
		$base_key = defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : '';

		/* Fall back to SECURE_AUTH_KEY if AUTH_KEY is default/empty */
		if ( empty( $base_key ) || 'put your unique phrase here' === $base_key ) {
			$base_key = defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ? SECURE_AUTH_KEY : '';
		}

		/* Generate and persist a random fallback key if WP salts are unconfigured */
		if ( empty( $base_key ) || 'put your unique phrase here' === $base_key ) {
			$stored = get_option( 'nbuf_encryption_fallback_key' );
			if ( ! empty( $stored ) ) {
				$base_key = $stored;
			} else {
				$base_key = bin2hex( random_bytes( 32 ) );
				add_option( 'nbuf_encryption_fallback_key', $base_key, '', false );
			}
		}

		/*
		 * Derive a proper 256-bit key using HKDF.
		 * This ensures consistent key length regardless of input length.
		 */
		self::$legacy_key_cache = hash_hkdf( 'sha256', $base_key, 32, 'nbuf_encryption_v1' );

		return self::$legacy_key_cache;
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
	 * Uses AES-256-GCM with the dedicated v2 key.
	 * Returns prefixed base64 string: $nbuf_enc2$base64(iv + ciphertext + tag)
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string|false Encrypted value with prefix, or false on failure.
	 */
	public static function encrypt( string $plaintext ): string|false {
		/* Return empty string as-is */
		if ( '' === $plaintext ) {
			return '';
		}

		/* If already encrypted, return as-is */
		if ( self::is_encrypted( $plaintext ) ) {
			return $plaintext;
		}

		if ( ! self::is_available() ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log( 'encryption_unavailable', 'critical', 'OpenSSL with AES-256-GCM is not available; refusing to store sensitive data in plaintext.' );
			}
			return false;
		}

		$key = self::get_dedicated_key();

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
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log( 'encryption_failed', 'critical', 'openssl_encrypt returned false; refusing to store sensitive data in plaintext.' );
			}
			return false;
		}

		/* Combine IV + ciphertext + tag and encode */
		$combined = $iv . $ciphertext . $tag;

		return self::ENCRYPTED_PREFIX_V2 . base64_encode( $combined );
	}

	/**
	 * Decrypt an encrypted string value.
	 *
	 * Detects the key version from the prefix: $nbuf_enc2$ uses the dedicated
	 * v2 key; $nbuf_enc$ uses the legacy AUTH_KEY-derived key.
	 *
	 * @param string $encrypted The encrypted value.
	 * @return string|false Decrypted value, original if not encrypted, or false on failure.
	 */
	public static function decrypt( string $encrypted ): string|false {
		/* Return empty string as-is */
		if ( '' === $encrypted ) {
			return '';
		}

		/* Select key + strip the matching prefix based on the version marker. */
		if ( str_starts_with( $encrypted, self::ENCRYPTED_PREFIX_V2 ) ) {
			$key     = self::get_dedicated_key();
			$encoded = substr( $encrypted, strlen( self::ENCRYPTED_PREFIX_V2 ) );
		} elseif ( str_starts_with( $encrypted, self::ENCRYPTED_PREFIX ) ) {
			$key     = self::get_legacy_key();
			$encoded = substr( $encrypted, strlen( self::ENCRYPTED_PREFIX ) );
		} else {
			/* Not encrypted — return as-is (legacy plaintext data) */
			return $encrypted;
		}

		if ( ! self::is_available() ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log( 'decryption_unavailable', 'critical', 'OpenSSL unavailable; cannot decrypt stored data.' );
			}
			return false;
		}

		$combined = base64_decode( $encoded, true );

		if ( false === $combined ) {
			return false;
		}

		/* IV is 12 bytes, tag is 16 bytes, rest is ciphertext */
		$iv_length  = 12;
		$tag_length = 16;

		if ( strlen( $combined ) < $iv_length + $tag_length + 1 ) {
			return false;
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
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log( 'decryption_failed', 'error', 'Decryption failed — possible data corruption or key mismatch.' );
			}
			return false;
		}

		return $plaintext;
	}

	/**
	 * Check if a value is encrypted (either key version).
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::ENCRYPTED_PREFIX_V2 )
			|| str_starts_with( $value, self::ENCRYPTED_PREFIX );
	}

	/**
	 * Re-encrypt a value.
	 *
	 * Decrypts with whichever key version the value carries and re-encrypts
	 * with the current (v2) dedicated key. If value is not encrypted, just
	 * encrypts it. Returns false (and the caller must NOT overwrite storage)
	 * when the source cannot be decrypted, so a key mismatch never destroys data.
	 *
	 * @param string $value The value to re-encrypt.
	 * @return string|false Re-encrypted value, or false on failure.
	 */
	public static function reencrypt( string $value ): string|false {
		$decrypted = self::decrypt( $value );
		if ( false === $decrypted ) {
			return false;
		}
		return self::encrypt( $decrypted );
	}

	/**
	 * One-shot migration: re-encrypt all legacy (v1) secrets under the v2 key.
	 *
	 * Walks the two tables that store encrypted secrets (nbuf_user_2fa.totp_secret
	 * and nbuf_webhooks.secret) and re-encrypts any value still carrying the
	 * legacy $nbuf_enc$ prefix. Rows that fail to decrypt (e.g. salts already
	 * rotated and the legacy key is gone) are LEFT UNTOUCHED and counted as
	 * failures — the migration never overwrites a row it could not read.
	 *
	 * Safe to run repeatedly; already-v2 / plaintext / empty values are skipped.
	 *
	 * @return array{totp:int, webhooks:int, failed:int} Counts of migrated rows and failures.
	 */
	public static function migrate_to_dedicated_key(): array {
		global $wpdb;

		$result = array(
			'totp'     => 0,
			'webhooks' => 0,
			'failed'   => 0,
		);

		if ( ! self::is_available() ) {
			return $result;
		}

		$legacy_prefix = self::ENCRYPTED_PREFIX;
		$like          = $wpdb->esc_like( $legacy_prefix ) . '%';

		/* --- nbuf_user_2fa.totp_secret --- */
		$twofa_table = $wpdb->prefix . 'nbuf_user_2fa';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, totp_secret FROM {$twofa_table} WHERE totp_secret LIKE %s", $like ) );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$reencrypted = self::reencrypt( (string) $row->totp_secret );
				if ( false === $reencrypted ) {
					++$result['failed'];
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = $wpdb->update( $twofa_table, array( 'totp_secret' => $reencrypted ), array( 'user_id' => (int) $row->user_id ), array( '%s' ), array( '%d' ) );
				if ( false === $updated ) {
					++$result['failed'];
				} else {
					++$result['totp'];
				}
			}
		}

		/* --- nbuf_webhooks.secret --- */
		$webhooks_table = $wpdb->prefix . 'nbuf_webhooks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wrows = $wpdb->get_results( $wpdb->prepare( "SELECT id, secret FROM {$webhooks_table} WHERE secret LIKE %s", $like ) );
		if ( is_array( $wrows ) ) {
			foreach ( $wrows as $row ) {
				$reencrypted = self::reencrypt( (string) $row->secret );
				if ( false === $reencrypted ) {
					++$result['failed'];
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = $wpdb->update( $webhooks_table, array( 'secret' => $reencrypted ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
				if ( false === $updated ) {
					++$result['failed'];
				} else {
					++$result['webhooks'];
				}
			}
		}

		if ( ( $result['totp'] || $result['webhooks'] || $result['failed'] ) && class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log(
				'encryption_key_migration',
				$result['failed'] > 0 ? 'warning' : 'info',
				'Re-encrypted legacy secrets under the dedicated encryption key.',
				$result
			);
		}

		return $result;
	}
}
