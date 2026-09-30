<?php
/**
 * TidyCal integration.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates TidyCal URLs. TidyCal pages are embedded in an iframe with a
 * fallback link. Interactions are tracked, completions are not claimed.
 */
class Tidycal {

	/**
	 * Validate a TidyCal URL.
	 *
	 * @param string $raw Raw URL.
	 * @return array [ url, error ]
	 */
	public static function clean_url( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array( '', __( 'Enter your TidyCal booking URL.', 'next-class-services' ) );
		}
		$url = Helpers::safe_url( $raw, true );
		if ( '' === $url ) {
			return array( '', __( 'The TidyCal URL must be a valid https address.', 'next-class-services' ) );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( 'tidycal.com' !== $host && '.tidycal.com' !== substr( $host, -12 ) ) {
			return array( '', __( 'The URL must be on tidycal.com.', 'next-class-services' ) );
		}
		return array( $url, '' );
	}

	/**
	 * Embed URL.
	 *
	 * @param string $url Booking URL.
	 * @return string
	 */
	public static function embed_url( $url ) {
		return $url;
	}
}
