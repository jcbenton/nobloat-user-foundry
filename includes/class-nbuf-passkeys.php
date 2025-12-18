<?php
/**
 * WebAuthn Passkeys Implementation
 *
 * Pure PHP WebAuthn implementation for passwordless authentication.
 * Handles passkey registration and authentication flows.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Passkeys class.
 *
 * Core WebAuthn passkey functionality including registration options,
 * attestation verification, authentication options, and assertion verification.
 */
class NBUF_Passkeys {


	/**
	 * Challenge transient prefix.
	 *
	 * @var string
	 */
	const CHALLENGE_PREFIX = 'nbuf_passkey_challenge_';

	/**
	 * Challenge expiration in seconds (2 minutes).
	 *
	 * @var int
	 */
	const CHALLENGE_EXPIRATION = 120;

	/**
	 * Initialize passkeys functionality.
	 *
	 * @since 1.5.0
	 */
	public static function init() {
		/* AJAX handlers for registration */
		add_action( 'wp_ajax_nbuf_passkey_registration_options', array( __CLASS__, 'ajax_registration_options' ) );
		add_action( 'wp_ajax_nbuf_passkey_register', array( __CLASS__, 'ajax_register' ) );
		add_action( 'wp_ajax_nbuf_passkey_delete', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_nbuf_passkey_rename', array( __CLASS__, 'ajax_rename' ) );

		/* AJAX handlers for authentication (available to non-logged in users) */
		add_action( 'wp_ajax_nopriv_nbuf_passkey_auth_options', array( __CLASS__, 'ajax_auth_options' ) );
		add_action( 'wp_ajax_nopriv_nbuf_passkey_authenticate', array( __CLASS__, 'ajax_authenticate' ) );
		add_action( 'wp_ajax_nopriv_nbuf_passkey_check_user', array( __CLASS__, 'ajax_check_user_passkeys' ) );
		add_action( 'wp_ajax_nbuf_passkey_auth_options', array( __CLASS__, 'ajax_auth_options' ) );
		add_action( 'wp_ajax_nbuf_passkey_authenticate', array( __CLASS__, 'ajax_authenticate' ) );
		add_action( 'wp_ajax_nbuf_passkey_check_user', array( __CLASS__, 'ajax_check_user_passkeys' ) );

		/* Delete passkeys when user is deleted */
		add_action( 'delete_user', array( __CLASS__, 'on_user_delete' ) );
	}

	/**
	 * Check if passkeys feature is enabled.
	 *
	 * @since  1.5.0
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_passkeys_enabled', false );
	}

	/**
	 * Get relying party ID (domain).
	 *
	 * @since  1.5.0
	 * @return string RP ID (domain name).
	 */
	public static function get_rp_id(): string {
		$site_url = wp_parse_url( home_url() );
		return $site_url['host'];
	}

	/**
	 * Get relying party name.
	 *
	 * @since  1.5.0
	 * @return string RP name (site title).
	 */
	public static function get_rp_name(): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * Get origin for verification.
	 *
	 * @since  1.5.0
	 * @return string Origin URL.
	 */
	public static function get_origin(): string {
		return home_url();
	}

	/**
	 * Generate registration options for WebAuthn.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return array|WP_Error Registration options or error.
	 */
	public static function generate_registration_options( int $user_id ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'passkeys_disabled', __( 'Passkeys are not enabled.', 'nobloat-user-foundry' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', __( 'User not found.', 'nobloat-user-foundry' ) );
		}

		/* Check max passkeys limit */
		$max_passkeys = (int) NBUF_Options::get( 'nbuf_passkeys_max_per_user', 10 );
		if ( NBUF_User_Passkeys_Data::get_count( $user_id ) >= $max_passkeys ) {
			return new WP_Error(
				'max_passkeys',
				sprintf(
					/* translators: %d: maximum number of passkeys */
					__( 'You have reached the maximum of %d passkeys.', 'nobloat-user-foundry' ),
					$max_passkeys
				)
			);
		}

		/* Generate challenge */
		$challenge = self::generate_challenge();

		/* Store challenge in transient (base64 encoded to avoid binary storage issues) */
		$transient_key = self::CHALLENGE_PREFIX . 'reg_' . $user_id;
		$set_result    = set_transient(
			$transient_key,
			base64_encode( $challenge ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			self::CHALLENGE_EXPIRATION
		);

		/* Get existing credentials to exclude */
		$existing_credentials = NBUF_User_Passkeys_Data::get_credential_ids( $user_id );
		$exclude_credentials  = array();
		foreach ( $existing_credentials as $cred_id ) {
			$exclude_credentials[] = array(
				'type' => 'public-key',
				'id'   => self::base64url_encode( $cred_id ),
			);
		}

		/* User verification preference */
		$user_verification = NBUF_Options::get( 'nbuf_passkeys_user_verification', 'preferred' );

		/* Build registration options */
		$options = array(
			'challenge'        => self::base64url_encode( $challenge ),
			'rp'               => array(
				'name' => self::get_rp_name(),
				'id'   => self::get_rp_id(),
			),
			'user'             => array(
				'id'          => self::base64url_encode( (string) $user_id ),
				'name'        => $user->user_login,
				'displayName' => $user->display_name,
			),
			'pubKeyCredParams' => array(
				array(
					'type' => 'public-key',
					'alg'  => -7,
				), /* ES256 */
				array(
					'type' => 'public-key',
					'alg'  => -257,
				), /* RS256 */
			),
			'timeout'          => (int) NBUF_Options::get( 'nbuf_passkeys_timeout', 60000 ),
			'attestation'      => NBUF_Options::get( 'nbuf_passkeys_attestation', 'none' ),
			'authenticatorSelection' => array(
				'residentKey'      => 'preferred',
				'userVerification' => $user_verification,
			),
		);

		if ( ! empty( $exclude_credentials ) ) {
			$options['excludeCredentials'] = $exclude_credentials;
		}

		return $options;
	}

	/**
	 * Verify registration response and store credential.
	 *
	 * @since  1.5.0
	 * @param  int    $user_id     User ID.
	 * @param  array  $response    WebAuthn response from browser.
	 * @param  string $device_name Optional device name.
	 * @return int|WP_Error Passkey ID or error.
	 */
	public static function verify_registration( int $user_id, array $response, string $device_name = '' ) {
		$transient_key = self::CHALLENGE_PREFIX . 'reg_' . $user_id;

		/* Get stored challenge (base64 encoded) */
		$stored_challenge = get_transient( $transient_key );

		if ( ! $stored_challenge ) {
			return new WP_Error( 'challenge_expired', __( 'Registration session expired. Please try again.', 'nobloat-user-foundry' ) );
		}

		/* Decode the stored challenge */
		$expected_challenge = base64_decode( $stored_challenge ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		/* Delete challenge (one-time use) */
		delete_transient( self::CHALLENGE_PREFIX . 'reg_' . $user_id );

		/* Extract response components */
		$client_data_json = self::base64url_decode( $response['clientDataJSON'] ?? '' );
		$attestation_obj  = self::base64url_decode( $response['attestationObject'] ?? '' );

		if ( empty( $client_data_json ) || empty( $attestation_obj ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid registration response.', 'nobloat-user-foundry' ) );
		}

		/* Verify client data */
		$client_data = json_decode( $client_data_json, true );
		if ( ! $client_data ) {
			return new WP_Error( 'invalid_client_data', __( 'Invalid client data.', 'nobloat-user-foundry' ) );
		}

		/* Verify type */
		if ( ( $client_data['type'] ?? '' ) !== 'webauthn.create' ) {
			return new WP_Error( 'invalid_type', __( 'Invalid ceremony type.', 'nobloat-user-foundry' ) );
		}

		/* Verify challenge */
		$received_challenge = self::base64url_decode( $client_data['challenge'] ?? '' );
		if ( ! hash_equals( $expected_challenge, $received_challenge ) ) {
			return new WP_Error( 'challenge_mismatch', __( 'Challenge verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify origin */
		$expected_origin = self::get_origin();
		if ( ( $client_data['origin'] ?? '' ) !== $expected_origin ) {
			return new WP_Error( 'origin_mismatch', __( 'Origin verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Parse attestation object (CBOR) */
		$attestation = self::parse_cbor( $attestation_obj );
		if ( ! $attestation || ! isset( $attestation['authData'] ) ) {
			return new WP_Error( 'invalid_attestation', __( 'Invalid attestation object.', 'nobloat-user-foundry' ) );
		}

		/* Parse authenticator data */
		$auth_data = self::parse_authenticator_data( $attestation['authData'] );
		if ( ! $auth_data ) {
			return new WP_Error( 'invalid_auth_data', __( 'Invalid authenticator data.', 'nobloat-user-foundry' ) );
		}

		/* Verify RP ID hash */
		$expected_rp_id_hash = hash( 'sha256', self::get_rp_id(), true );
		if ( ! hash_equals( $expected_rp_id_hash, $auth_data['rpIdHash'] ) ) {
			return new WP_Error( 'rp_id_mismatch', __( 'Relying party verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify user present flag */
		if ( ! ( $auth_data['flags'] & 0x01 ) ) {
			return new WP_Error( 'user_not_present', __( 'User presence verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Extract credential data */
		if ( empty( $auth_data['credentialId'] ) || empty( $auth_data['publicKey'] ) ) {
			return new WP_Error( 'missing_credential', __( 'Credential data missing.', 'nobloat-user-foundry' ) );
		}

		/* Extract transports if available */
		$transports = null;
		if ( ! empty( $response['transports'] ) && is_array( $response['transports'] ) ) {
			$transports = wp_json_encode( $response['transports'] );
		}

		/* Generate device name if not provided */
		if ( empty( $device_name ) ) {
			$device_name = self::generate_device_name();
		}

		/* Store credential */
		$passkey_id = NBUF_User_Passkeys_Data::create(
			$user_id,
			array(
				'credential_id' => $auth_data['credentialId'],
				'public_key'    => $auth_data['publicKey'],
				'sign_count'    => $auth_data['signCount'],
				'transports'    => $transports,
				'aaguid'        => $auth_data['aaguid'],
				'device_name'   => $device_name,
			)
		);

		if ( ! $passkey_id ) {
			return new WP_Error( 'storage_failed', __( 'Failed to store passkey.', 'nobloat-user-foundry' ) );
		}

		return $passkey_id;
	}

	/**
	 * Generate authentication options for WebAuthn.
	 *
	 * @since  1.5.0
	 * @param  string $username Optional username to pre-fill allowed credentials.
	 * @return array|WP_Error Authentication options or error.
	 */
	public static function generate_authentication_options( string $username = '' ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'passkeys_disabled', __( 'Passkeys are not enabled.', 'nobloat-user-foundry' ) );
		}

		/* Generate challenge */
		$challenge = self::generate_challenge();

		/* Generate session ID for tracking this auth attempt */
		$session_id = wp_generate_password( 32, false );

		/* Store challenge with session ID (base64 encoded to avoid binary storage issues) */
		set_transient(
			self::CHALLENGE_PREFIX . 'auth_' . $session_id,
			base64_encode( $challenge ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			self::CHALLENGE_EXPIRATION
		);

		/* User verification preference */
		$user_verification = NBUF_Options::get( 'nbuf_passkeys_user_verification', 'preferred' );

		/* Build authentication options */
		$options = array(
			'challenge'        => self::base64url_encode( $challenge ),
			'rpId'             => self::get_rp_id(),
			'timeout'          => (int) NBUF_Options::get( 'nbuf_passkeys_timeout', 60000 ),
			'userVerification' => $user_verification,
			'sessionId'        => $session_id,
		);

		/* If username provided, get their allowed credentials */
		if ( $username ) {
			$user = get_user_by( 'login', $username );
			if ( ! $user ) {
				$user = get_user_by( 'email', $username );
			}

			if ( $user ) {
				$credentials         = NBUF_User_Passkeys_Data::get_all( $user->ID );
				$allowed_credentials = array();

				foreach ( $credentials as $cred ) {
					$cred_data = array(
						'type' => 'public-key',
						'id'   => self::base64url_encode( $cred->credential_id ),
					);

					if ( $cred->transports ) {
						$transports = json_decode( $cred->transports, true );
						if ( is_array( $transports ) ) {
							$cred_data['transports'] = $transports;
						}
					}

					$allowed_credentials[] = $cred_data;
				}

				if ( ! empty( $allowed_credentials ) ) {
					$options['allowCredentials'] = $allowed_credentials;
				}

				/* Store user ID with session for verification */
				set_transient(
					self::CHALLENGE_PREFIX . 'user_' . $session_id,
					$user->ID,
					self::CHALLENGE_EXPIRATION
				);
			}
		}

		return $options;
	}

	/**
	 * Verify authentication response.
	 *
	 * @since  1.5.0
	 * @param  array $response WebAuthn response from browser.
	 * @return int|WP_Error User ID or error.
	 */
	public static function verify_authentication( array $response ) {
		$session_id = $response['sessionId'] ?? '';
		if ( empty( $session_id ) ) {
			return new WP_Error( 'invalid_session', __( 'Invalid authentication session.', 'nobloat-user-foundry' ) );
		}

		/* Get stored challenge (base64 encoded) */
		$stored_challenge = get_transient( self::CHALLENGE_PREFIX . 'auth_' . $session_id );
		if ( ! $stored_challenge ) {
			return new WP_Error( 'challenge_expired', __( 'Authentication session expired. Please try again.', 'nobloat-user-foundry' ) );
		}

		/* Decode the stored challenge */
		$expected_challenge = base64_decode( $stored_challenge ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		/* Delete challenge (one-time use) */
		delete_transient( self::CHALLENGE_PREFIX . 'auth_' . $session_id );
		delete_transient( self::CHALLENGE_PREFIX . 'user_' . $session_id );

		/* Extract response components */
		$credential_id    = self::base64url_decode( $response['id'] ?? '' );
		$client_data_json = self::base64url_decode( $response['clientDataJSON'] ?? '' );
		$authenticator_data = self::base64url_decode( $response['authenticatorData'] ?? '' );
		$signature        = self::base64url_decode( $response['signature'] ?? '' );

		if ( empty( $credential_id ) || empty( $client_data_json ) || empty( $authenticator_data ) || empty( $signature ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid authentication response.', 'nobloat-user-foundry' ) );
		}

		/* Find passkey by credential ID */
		$passkey = NBUF_User_Passkeys_Data::get_by_credential_id( $credential_id );
		if ( ! $passkey ) {
			return new WP_Error( 'credential_not_found', __( 'Passkey not recognized.', 'nobloat-user-foundry' ) );
		}

		/* Verify client data */
		$client_data = json_decode( $client_data_json, true );
		if ( ! $client_data ) {
			return new WP_Error( 'invalid_client_data', __( 'Invalid client data.', 'nobloat-user-foundry' ) );
		}

		/* Verify type */
		if ( ( $client_data['type'] ?? '' ) !== 'webauthn.get' ) {
			return new WP_Error( 'invalid_type', __( 'Invalid ceremony type.', 'nobloat-user-foundry' ) );
		}

		/* Verify challenge */
		$received_challenge = self::base64url_decode( $client_data['challenge'] ?? '' );
		if ( ! hash_equals( $expected_challenge, $received_challenge ) ) {
			return new WP_Error( 'challenge_mismatch', __( 'Challenge verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify origin */
		$expected_origin = self::get_origin();
		if ( ( $client_data['origin'] ?? '' ) !== $expected_origin ) {
			return new WP_Error( 'origin_mismatch', __( 'Origin verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Parse authenticator data */
		$auth_data = self::parse_authenticator_data_minimal( $authenticator_data );
		if ( ! $auth_data ) {
			return new WP_Error( 'invalid_auth_data', __( 'Invalid authenticator data.', 'nobloat-user-foundry' ) );
		}

		/* Verify RP ID hash */
		$expected_rp_id_hash = hash( 'sha256', self::get_rp_id(), true );
		if ( ! hash_equals( $expected_rp_id_hash, $auth_data['rpIdHash'] ) ) {
			return new WP_Error( 'rp_id_mismatch', __( 'Relying party verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify user present flag */
		if ( ! ( $auth_data['flags'] & 0x01 ) ) {
			return new WP_Error( 'user_not_present', __( 'User presence verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify signature */
		$signed_data     = $authenticator_data . hash( 'sha256', $client_data_json, true );
		$signature_valid = self::verify_signature( $passkey->public_key, $signed_data, $signature );

		if ( ! $signature_valid ) {
			return new WP_Error( 'signature_invalid', __( 'Signature verification failed.', 'nobloat-user-foundry' ) );
		}

		/* Verify sign count (detect cloned authenticator) */
		$new_sign_count = $auth_data['signCount'];
		if ( $new_sign_count > 0 && $new_sign_count <= (int) $passkey->sign_count ) {
			/* Possible cloned authenticator - log security event */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'passkey_clone_detected',
					sprintf( 'Possible cloned authenticator for user %d', $passkey->user_id ),
					'warning',
					array(
						'passkey_id'     => $passkey->id,
						'old_sign_count' => $passkey->sign_count,
						'new_sign_count' => $new_sign_count,
					)
				);
			}
			return new WP_Error( 'sign_count_invalid', __( 'Authenticator verification failed. Possible cloned device detected.', 'nobloat-user-foundry' ) );
		}

		/* Update sign count and last used */
		NBUF_User_Passkeys_Data::update_sign_count( $passkey->id, $new_sign_count );

		/* Log successful passkey authentication */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$passkey->user_id,
				'passkey_auth',
				'success',
				'Authenticated with passkey',
				array(
					'passkey_id'  => $passkey->id,
					'device_name' => $passkey->device_name,
				)
			);
		}

		return (int) $passkey->user_id;
	}

	/**
	 * Generate cryptographic challenge.
	 *
	 * @since  1.5.0
	 * @return string Random 32-byte challenge.
	 */
	private static function generate_challenge(): string {
		return random_bytes( 32 );
	}

	/**
	 * Generate device name from user agent.
	 *
	 * @since  1.5.0
	 * @return string Device name.
	 */
	private static function generate_device_name(): string {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( empty( $user_agent ) ) {
			return __( 'Unknown Device', 'nobloat-user-foundry' );
		}

		/* Simple device detection */
		if ( strpos( $user_agent, 'iPhone' ) !== false ) {
			return 'iPhone';
		} elseif ( strpos( $user_agent, 'iPad' ) !== false ) {
			return 'iPad';
		} elseif ( strpos( $user_agent, 'Android' ) !== false ) {
			if ( strpos( $user_agent, 'Mobile' ) !== false ) {
				return __( 'Android Phone', 'nobloat-user-foundry' );
			}
			return __( 'Android Tablet', 'nobloat-user-foundry' );
		} elseif ( strpos( $user_agent, 'Windows' ) !== false ) {
			return __( 'Windows PC', 'nobloat-user-foundry' );
		} elseif ( strpos( $user_agent, 'Macintosh' ) !== false ) {
			return 'Mac';
		} elseif ( strpos( $user_agent, 'Linux' ) !== false ) {
			return 'Linux';
		}

		return __( 'Unknown Device', 'nobloat-user-foundry' );
	}

	/**
	 * Base64URL encode.
	 *
	 * @since  1.5.0
	 * @param  string $data Raw data.
	 * @return string Base64URL encoded string.
	 */
	public static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Base64URL decode.
	 *
	 * @since  1.5.0
	 * @param  string $data Base64URL encoded string.
	 * @return string Raw data.
	 */
	public static function base64url_decode( string $data ): string {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		return base64_decode( strtr( $data, '-_', '+/' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Parse CBOR data (minimal implementation for attestation objects).
	 *
	 * @since  1.5.0
	 * @param  string $data CBOR encoded data.
	 * @return array|null Parsed data or null on error.
	 */
	private static function parse_cbor( string $data ): ?array {
		$offset = 0;
		return self::parse_cbor_item( $data, $offset );
	}

	/**
	 * Parse a single CBOR item.
	 *
	 * @since  1.5.0
	 * @param  string $data   CBOR data.
	 * @param  int    $offset Current offset (modified by reference).
	 * @return mixed Parsed value or null on error.
	 */
	private static function parse_cbor_item( string $data, int &$offset ) {
		if ( $offset >= strlen( $data ) ) {
			return null;
		}

		$initial_byte = ord( $data[ $offset ] );
		$major_type   = $initial_byte >> 5;
		$additional   = $initial_byte & 0x1f;
		++$offset;

		/* Get argument value */
		$argument = self::get_cbor_argument( $data, $offset, $additional );
		if ( null === $argument ) {
			return null;
		}

		switch ( $major_type ) {
			case 0: /* Unsigned integer */
				return $argument;

			case 1: /* Negative integer */
				return -1 - $argument;

			case 2: /* Byte string */
				if ( $offset + $argument > strlen( $data ) ) {
					return null;
				}
				$value   = substr( $data, $offset, $argument );
				$offset += $argument;
				return $value;

			case 3: /* Text string */
				if ( $offset + $argument > strlen( $data ) ) {
					return null;
				}
				$value   = substr( $data, $offset, $argument );
				$offset += $argument;
				return $value;

			case 4: /* Array */
				$array = array();
				for ( $i = 0; $i < $argument; $i++ ) {
					$array[] = self::parse_cbor_item( $data, $offset );
				}
				return $array;

			case 5: /* Map */
				$map = array();
				for ( $i = 0; $i < $argument; $i++ ) {
					$key         = self::parse_cbor_item( $data, $offset );
					$value       = self::parse_cbor_item( $data, $offset );
					$map[ $key ] = $value;
				}
				return $map;

			default:
				return null;
		}
	}

	/**
	 * Get CBOR argument value.
	 *
	 * @since  1.5.0
	 * @param  string $data       CBOR data.
	 * @param  int    $offset     Current offset (modified by reference).
	 * @param  int    $additional Additional info from initial byte.
	 * @return int|null Argument value or null on error.
	 */
	private static function get_cbor_argument( string $data, int &$offset, int $additional ): ?int {
		if ( $additional < 24 ) {
			return $additional;
		} elseif ( 24 === $additional ) {
			if ( $offset >= strlen( $data ) ) {
				return null;
			}
			return ord( $data[ $offset++ ] );
		} elseif ( 25 === $additional ) {
			if ( $offset + 2 > strlen( $data ) ) {
				return null;
			}
			$value   = unpack( 'n', substr( $data, $offset, 2 ) )[1];
			$offset += 2;
			return $value;
		} elseif ( 26 === $additional ) {
			if ( $offset + 4 > strlen( $data ) ) {
				return null;
			}
			$value   = unpack( 'N', substr( $data, $offset, 4 ) )[1];
			$offset += 4;
			return $value;
		}
		return null;
	}

	/**
	 * Parse authenticator data from attestation (includes credential data).
	 *
	 * @since  1.5.0
	 * @param  string $auth_data Raw authenticator data.
	 * @return array|null Parsed data or null on error.
	 */
	private static function parse_authenticator_data( string $auth_data ): ?array {
		if ( strlen( $auth_data ) < 37 ) {
			return null;
		}

		$result = array(
			'rpIdHash'  => substr( $auth_data, 0, 32 ),
			'flags'     => ord( $auth_data[32] ),
			'signCount' => unpack( 'N', substr( $auth_data, 33, 4 ) )[1],
		);

		/* Check if attested credential data is present (bit 6) */
		if ( $result['flags'] & 0x40 ) {
			if ( strlen( $auth_data ) < 55 ) {
				return null;
			}

			$result['aaguid'] = substr( $auth_data, 37, 16 );

			/* Credential ID length (2 bytes, big-endian) */
			$cred_id_length = unpack( 'n', substr( $auth_data, 53, 2 ) )[1];

			if ( strlen( $auth_data ) < 55 + $cred_id_length ) {
				return null;
			}

			$result['credentialId'] = substr( $auth_data, 55, $cred_id_length );

			/* COSE public key follows credential ID - parse to get exact length */
			$public_key_start = 55 + $cred_id_length;
			$public_key_data  = substr( $auth_data, $public_key_start );

			/* Parse the CBOR to determine exact key length (exclude extensions if present) */
			$cbor_offset = 0;
			$parsed_key  = self::parse_cbor_item( $public_key_data, $cbor_offset );

			if ( ! is_array( $parsed_key ) ) {
				return null;
			}

			/* Store only the exact CBOR bytes for the public key */
			$result['publicKey'] = substr( $public_key_data, 0, $cbor_offset );
		}

		return $result;
	}

	/**
	 * Parse authenticator data for authentication (no credential data).
	 *
	 * @since  1.5.0
	 * @param  string $auth_data Raw authenticator data.
	 * @return array|null Parsed data or null on error.
	 */
	private static function parse_authenticator_data_minimal( string $auth_data ): ?array {
		if ( strlen( $auth_data ) < 37 ) {
			return null;
		}

		return array(
			'rpIdHash'  => substr( $auth_data, 0, 32 ),
			'flags'     => ord( $auth_data[32] ),
			'signCount' => unpack( 'N', substr( $auth_data, 33, 4 ) )[1],
		);
	}

	/**
	 * Verify signature using COSE public key.
	 *
	 * @since  1.5.0
	 * @param  string $public_key_cbor COSE public key (CBOR encoded).
	 * @param  string $data            Data that was signed.
	 * @param  string $signature       Signature to verify.
	 * @return bool True if signature is valid.
	 */
	private static function verify_signature( string $public_key_cbor, string $data, string $signature ): bool {
		/* Parse COSE key */
		$offset   = 0;
		$cose_key = self::parse_cbor_item( $public_key_cbor, $offset );

		if ( ! is_array( $cose_key ) ) {
			return false;
		}

		/* Get key type and algorithm */
		$kty = $cose_key[1] ?? null; /* Key type */
		$alg = $cose_key[3] ?? null; /* Algorithm */

		/* EC2 key (kty = 2) with ES256 (alg = -7) */
		if ( 2 === $kty && -7 === $alg ) {
			return self::verify_es256( $cose_key, $data, $signature );
		}

		/* RSA key (kty = 3) with RS256 (alg = -257) */
		if ( 3 === $kty && -257 === $alg ) {
			return self::verify_rs256( $cose_key, $data, $signature );
		}

		return false;
	}

	/**
	 * Verify ES256 (ECDSA with P-256 and SHA-256) signature.
	 *
	 * @since  1.5.0
	 * @param  array  $cose_key  Parsed COSE key.
	 * @param  string $data      Data that was signed.
	 * @param  string $signature Raw signature.
	 * @return bool True if signature is valid.
	 */
	private static function verify_es256( array $cose_key, string $data, string $signature ): bool {
		/* Extract x and y coordinates */
		$x = $cose_key[-2] ?? null;
		$y = $cose_key[-3] ?? null;

		if ( ! $x || ! $y || strlen( $x ) !== 32 || strlen( $y ) !== 32 ) {
			return false;
		}

		/* Build PEM public key */
		/* EC public key for P-256: 0x04 || x || y */
		$ec_point = "\x04" . $x . $y;

		/* ASN.1 structure for EC public key */
		$ec_key_der = "\x30\x59" .                           /* SEQUENCE */
			"\x30\x13" .                                     /* SEQUENCE */
			"\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .         /* OID ecPublicKey */
			"\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" .     /* OID prime256v1 */
			"\x03\x42\x00" . $ec_point;                      /* BIT STRING */

		$pem = "-----BEGIN PUBLIC KEY-----\n" .
			chunk_split( base64_encode( $ec_key_der ), 64, "\n" ) . // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'-----END PUBLIC KEY-----';

		/* Convert signature to DER format if needed */
		$signature_der = self::ensure_der_signature( $signature );
		if ( ! $signature_der ) {
			return false;
		}

		/* Verify signature */
		$key = openssl_pkey_get_public( $pem );
		if ( ! $key ) {
			return false;
		}

		$result = openssl_verify( $data, $signature_der, $key, OPENSSL_ALGO_SHA256 );

		return 1 === $result;
	}

	/**
	 * Verify RS256 (RSASSA-PKCS1-v1_5 with SHA-256) signature.
	 *
	 * @since  1.5.0
	 * @param  array  $cose_key  Parsed COSE key.
	 * @param  string $data      Data that was signed.
	 * @param  string $signature Raw signature.
	 * @return bool True if signature is valid.
	 */
	private static function verify_rs256( array $cose_key, string $data, string $signature ): bool {
		/* Extract modulus and exponent */
		$n = $cose_key[-1] ?? null; /* Modulus */
		$e = $cose_key[-2] ?? null; /* Exponent */

		if ( ! $n || ! $e ) {
			return false;
		}

		/* Build PEM public key */
		$modulus  = self::encode_asn1_integer( $n );
		$exponent = self::encode_asn1_integer( $e );

		$rsa_public_key = "\x30" . self::encode_asn1_length( strlen( $modulus ) + strlen( $exponent ) ) .
			$modulus . $exponent;

		/* RSA OID */
		$algorithm_identifier = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

		/* Wrap in SubjectPublicKeyInfo */
		$bit_string = "\x03" . self::encode_asn1_length( strlen( $rsa_public_key ) + 1 ) .
			"\x00" . $rsa_public_key;

		$public_key_info = "\x30" . self::encode_asn1_length(
			strlen( $algorithm_identifier ) + strlen( $bit_string )
		) . $algorithm_identifier . $bit_string;

		$pem = "-----BEGIN PUBLIC KEY-----\n" .
			chunk_split( base64_encode( $public_key_info ), 64, "\n" ) . // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'-----END PUBLIC KEY-----';

		/* Verify signature */
		$key = openssl_pkey_get_public( $pem );
		if ( ! $key ) {
			return false;
		}

		$result = openssl_verify( $data, $signature, $key, OPENSSL_ALGO_SHA256 );

		return 1 === $result;
	}

	/**
	 * Ensure signature is in DER format.
	 *
	 * WebAuthn signatures can be in either raw (r||s) or DER format.
	 * This function detects the format and converts to DER if needed.
	 *
	 * @since  1.5.0
	 * @param  string $signature Signature (raw or DER format).
	 * @return string|false DER encoded signature or false on error.
	 */
	private static function ensure_der_signature( string $signature ) {
		$sig_len = strlen( $signature );

		/* Check if already in DER format (starts with SEQUENCE tag 0x30) */
		if ( $sig_len > 2 && "\x30" === $signature[0] ) {
			/* Validate DER structure: SEQUENCE containing two INTEGERs */
			$seq_len = ord( $signature[1] );
			if ( $seq_len + 2 === $sig_len || ( "\x81" === $signature[1] && ord( $signature[2] ) + 3 === $sig_len ) ) {
				return $signature;
			}
		}

		/* Raw format: exactly 64 bytes (32 for r, 32 for s) */
		if ( 64 === $sig_len ) {
			return self::raw_to_der_signature( $signature );
		}

		return false;
	}

	/**
	 * Convert raw ECDSA signature (r||s) to DER format.
	 *
	 * @since  1.5.0
	 * @param  string $signature Raw signature (64 bytes for ES256).
	 * @return string|false DER encoded signature or false on error.
	 */
	private static function raw_to_der_signature( string $signature ) {
		if ( strlen( $signature ) !== 64 ) {
			return false;
		}

		$r = substr( $signature, 0, 32 );
		$s = substr( $signature, 32, 32 );

		/* Encode r and s as ASN.1 integers */
		$r_der = self::encode_asn1_integer( $r );
		$s_der = self::encode_asn1_integer( $s );

		/* Wrap in SEQUENCE */
		return "\x30" . self::encode_asn1_length( strlen( $r_der ) + strlen( $s_der ) ) . $r_der . $s_der;
	}

	/**
	 * Encode a value as ASN.1 INTEGER.
	 *
	 * @since  1.5.0
	 * @param  string $value Raw integer bytes.
	 * @return string ASN.1 encoded integer.
	 */
	private static function encode_asn1_integer( string $value ): string {
		/* Remove leading zeros but keep at least one byte */
		$value = ltrim( $value, "\x00" );
		if ( '' === $value ) {
			$value = "\x00";
		}

		/* Add leading zero if high bit is set (to indicate positive) */
		if ( ord( $value[0] ) & 0x80 ) {
			$value = "\x00" . $value;
		}

		return "\x02" . self::encode_asn1_length( strlen( $value ) ) . $value;
	}

	/**
	 * Encode ASN.1 length.
	 *
	 * @since  1.5.0
	 * @param  int $length Length value.
	 * @return string Encoded length.
	 */
	private static function encode_asn1_length( int $length ): string {
		if ( $length < 128 ) {
			return chr( $length );
		} elseif ( $length < 256 ) {
			return "\x81" . chr( $length );
		} else {
			return "\x82" . pack( 'n', $length );
		}
	}

	/**
	 * AJAX handler for registration options.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_registration_options() {
		check_ajax_referer( 'nbuf_passkey_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		$options = self::generate_registration_options( $user_id );

		if ( is_wp_error( $options ) ) {
			wp_send_json_error( array( 'message' => $options->get_error_message() ) );
		}

		wp_send_json_success( $options );
	}

	/**
	 * AJAX handler for registration.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_register() {
		check_ajax_referer( 'nbuf_passkey_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nobloat-user-foundry' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON contains base64url data that must not be modified
		$response    = isset( $_POST['response'] ) ? json_decode( wp_unslash( $_POST['response'] ), true ) : null;
		$device_name = isset( $_POST['device_name'] ) ? sanitize_text_field( wp_unslash( $_POST['device_name'] ) ) : '';

		if ( ! $response ) {
			wp_send_json_error( array( 'message' => __( 'Invalid response.', 'nobloat-user-foundry' ) ) );
		}

		$result = self::verify_registration( $user_id, $response, $device_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Passkey registered successfully.', 'nobloat-user-foundry' ),
				'passkey_id' => $result,
			)
		);
	}

	/**
	 * AJAX handler for deleting a passkey.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_delete() {
		check_ajax_referer( 'nbuf_passkey_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$passkey_id = isset( $_POST['passkey_id'] ) ? (int) $_POST['passkey_id'] : 0;

		if ( ! $user_id || ! $passkey_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'nobloat-user-foundry' ) ) );
		}

		/* Verify passkey belongs to user */
		$passkey = NBUF_User_Passkeys_Data::get_by_id( $passkey_id );
		if ( ! $passkey || (int) $passkey->user_id !== $user_id ) {
			/* Check if admin with capability */
			if ( ! current_user_can( 'edit_users' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this passkey.', 'nobloat-user-foundry' ) ) );
			}
		}

		$result = NBUF_User_Passkeys_Data::delete( $passkey_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Passkey deleted.', 'nobloat-user-foundry' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete passkey.', 'nobloat-user-foundry' ) ) );
		}
	}

	/**
	 * AJAX handler for renaming a passkey.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_rename() {
		check_ajax_referer( 'nbuf_passkey_nonce', 'nonce' );

		$user_id     = get_current_user_id();
		$passkey_id  = isset( $_POST['passkey_id'] ) ? (int) $_POST['passkey_id'] : 0;
		$device_name = isset( $_POST['device_name'] ) ? sanitize_text_field( wp_unslash( $_POST['device_name'] ) ) : '';

		if ( ! $user_id || ! $passkey_id || empty( $device_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'nobloat-user-foundry' ) ) );
		}

		/* Verify passkey belongs to user */
		$passkey = NBUF_User_Passkeys_Data::get_by_id( $passkey_id );
		if ( ! $passkey || (int) $passkey->user_id !== $user_id ) {
			if ( ! current_user_can( 'edit_users' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to rename this passkey.', 'nobloat-user-foundry' ) ) );
			}
		}

		$result = NBUF_User_Passkeys_Data::update_device_name( $passkey_id, $device_name );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Passkey renamed.', 'nobloat-user-foundry' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to rename passkey.', 'nobloat-user-foundry' ) ) );
		}
	}

	/**
	 * AJAX handler for authentication options.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_auth_options() {
		/* No nonce check for this endpoint - it's pre-login */
		$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';

		$options = self::generate_authentication_options( $username );

		if ( is_wp_error( $options ) ) {
			wp_send_json_error( array( 'message' => $options->get_error_message() ) );
		}

		wp_send_json_success( $options );
	}

	/**
	 * AJAX handler for authentication.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_authenticate() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON contains base64url data that must not be modified
		$response = isset( $_POST['response'] ) ? json_decode( wp_unslash( $_POST['response'] ), true ) : null;

		if ( ! $response ) {
			wp_send_json_error( array( 'message' => __( 'Invalid response.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = self::verify_authentication( $response );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		/* Check if 2FA is enabled for this user */
		$twofa_required = false;
		if ( class_exists( 'NBUF_2FA' ) && NBUF_2FA::is_enabled( $user_id ) ) {
			$twofa_required = true;
		}

		if ( $twofa_required ) {
			/* Generate 2FA token */
			$twofa_token = wp_generate_password( 32, false );

			/* Get user's 2FA method */
			$method = NBUF_2FA::get_user_method( $user_id );

			/* Store pending 2FA data in same format as regular login */
			set_transient(
				'nbuf_2fa_pending_' . $twofa_token,
				array(
					'user_id'   => $user_id,
					'timestamp' => time(),
					'method'    => $method,
				),
				300
			);

			/* Set the 2FA cookie */
			setcookie(
				'nbuf_2fa_token',
				$twofa_token,
				time() + 300,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);

			/* Send email code if using email 2FA */
			if ( 'email' === $method || 'both' === $method ) {
				NBUF_2FA::send_email_code( $user_id );
			}

			/* Get 2FA verification page URL */
			$twofa_url = '';
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				$twofa_url = NBUF_Universal_Router::get_url( '2fa' );
			} else {
				$page_id = NBUF_Options::get( 'nbuf_page_2fa_verify', 0 );
				if ( $page_id ) {
					$twofa_url = get_permalink( $page_id );
				} else {
					$twofa_url = home_url( '/2fa-verify/' );
				}
			}

			wp_send_json_success(
				array(
					'requires_2fa'   => true,
					'twofa_redirect' => $twofa_url,
					'message'        => __( 'Passkey verified. Please complete 2FA.', 'nobloat-user-foundry' ),
				)
			);
		}

		/* Log user in */
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		/* Get redirect URL */
		$redirect_url = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		if ( empty( $redirect_url ) ) {
			$redirect_url = admin_url();
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Login successful.', 'nobloat-user-foundry' ),
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * AJAX handler to check if user has passkeys.
	 *
	 * Used by two-step login flow to determine if passkey option should be shown.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_check_user_passkeys() {
		/* No nonce check for this endpoint - it's pre-login */
		$login = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';

		if ( empty( $login ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a username or email.', 'nobloat-user-foundry' ) ) );
		}

		/* Find user by email or username */
		$user = null;
		if ( is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
		}
		if ( ! $user ) {
			$user = get_user_by( 'login', $login );
		}

		if ( ! $user ) {
			/* Don't reveal if user exists - just say no passkeys (security) */
			wp_send_json_success(
				array(
					'has_passkeys'  => false,
					'show_password' => true,
				)
			);
		}

		/* Check if user has passkeys */
		$has_passkeys = NBUF_User_Passkeys_Data::has_passkeys( $user->ID );

		wp_send_json_success(
			array(
				'has_passkeys'  => $has_passkeys,
				'show_password' => true, /* Always allow password fallback */
				'user_id'       => $user->ID,
			)
		);
	}

	/**
	 * Handle user deletion.
	 *
	 * @since 1.5.0
	 * @param int $user_id User ID being deleted.
	 */
	public static function on_user_delete( int $user_id ) {
		NBUF_User_Passkeys_Data::delete_all( $user_id );
	}
}
