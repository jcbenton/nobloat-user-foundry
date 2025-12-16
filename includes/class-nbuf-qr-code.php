<?php
/**
 * QR Code Generator
 *
 * Generates QR codes for TOTP provisioning URIs using api.qrserver.com.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_QR_Code class.
 *
 * Generates QR codes for TOTP setup using external API.
 */
class NBUF_QR_Code {

	/**
	 * Generate QR code using api.qrserver.com
	 *
	 * Uses the free and reliable api.qrserver.com service for QR code generation.
	 *
	 * @param  string $data Data to encode.
	 * @param  int    $size Size in pixels.
	 * @return string IMG tag with QR code.
	 */
	public static function generate_external( $data, $size = 200 ) {
		$api_url = 'https://api.qrserver.com/v1/create-qr-code/';
		$params  = array(
			'size' => $size . 'x' . $size,
			'data' => $data,
		);

		$qr_url = $api_url . '?' . http_build_query( $params );

		return '<img src="' . esc_url( $qr_url ) . '" alt="QR Code" width="' . esc_attr( $size ) . '" height="' . esc_attr( $size ) . '" />';
	}

	/**
	 * Generate QR code
	 *
	 * Uses api.qrserver.com for reliable QR code generation.
	 *
	 * @param  string $data Data to encode.
	 * @param  int    $size Size in pixels.
	 * @return string HTML IMG tag with QR code.
	 */
	public static function generate( $data, $size = 200 ) {
		return self::generate_external( $data, $size );
	}
}
