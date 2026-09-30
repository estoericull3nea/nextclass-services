<?php
/**
 * Calendly integration.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates Calendly URLs and builds the responsive inline embed URL.
 *
 * The plugin embeds Calendly in an iframe and listens for Calendly postMessage
 * events in the browser. Those messages are client side signals only, so they
 * are recorded as interactions and never as confirmed appointments.
 */
class Calendly {

	/**
	 * Validate a Calendly URL.
	 *
	 * @param string $raw Raw URL.
	 * @return array [ url, error ]
	 */
	public static function clean_url( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array( '', __( 'Enter your Calendly booking URL.', 'next-class-services' ) );
		}
		$url = Helpers::safe_url( $raw, true );
		if ( '' === $url ) {
			return array( '', __( 'The Calendly URL must be a valid https address.', 'next-class-services' ) );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( 'calendly.com' !== $host && '.calendly.com' !== substr( $host, -13 ) ) {
			return array( '', __( 'The URL must be on calendly.com.', 'next-class-services' ) );
		}
		return array( $url, '' );
	}

	/**
	 * Inline embed URL.
	 *
	 * @param string $url Booking URL.
	 * @return string
	 */
	public static function embed_url( $url ) {
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return add_query_arg(
			array(
				'embed_domain' => (string) $host,
				'embed_type'   => 'Inline',
			),
			$url
		);
	}
}
