<?php
/**
 * Custom URL or embed booking.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles external booking URLs and an approved iframe embed.
 *
 * Raw HTML is never stored or printed. When an administrator pastes an embed
 * snippet, only the iframe address is extracted, validated as https and checked
 * against the optional host allowlist. The plugin then renders its own iframe.
 */
class Custom_Booking {

	/**
	 * Validate a plain booking URL.
	 *
	 * @param string $raw Raw URL.
	 * @return array [ url, error ]
	 */
	public static function clean_url( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array( '', __( 'Enter the booking URL.', 'next-class-services' ) );
		}
		$url = Helpers::safe_url( $raw, true );
		if ( '' === $url ) {
			return array( '', __( 'The booking URL must be a valid https address.', 'next-class-services' ) );
		}
		return array( $url, '' );
	}

	/**
	 * Extract and validate an iframe source from pasted embed code or a URL.
	 *
	 * @param string $source Pasted value.
	 * @return array [ url, error ]
	 */
	public static function clean_embed( $source ) {
		$source = trim( (string) $source );
		if ( '' === $source ) {
			return array( '', __( 'Paste the embed code or the embed address.', 'next-class-services' ) );
		}
		$candidate = $source;
		if ( false !== stripos( $source, '<' ) ) {
			if ( ! preg_match( '/<iframe\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1/is', $source, $m ) ) {
				return array( '', __( 'Only iframe embeds are supported. Scripts and other markup are not accepted.', 'next-class-services' ) );
			}
			$candidate = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
		}
		$url = Helpers::safe_url( $candidate, true );
		if ( '' === $url ) {
			return array( '', __( 'The embed address must be a valid https URL.', 'next-class-services' ) );
		}
		if ( ! self::host_allowed( $url ) ) {
			return array( '', __( 'That host is not on the embed allowlist in Settings.', 'next-class-services' ) );
		}
		return array( $url, '' );
	}

	/**
	 * Check the host allowlist (empty allowlist allows any https host).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function host_allowed( $url ) {
		$list = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', strtolower( (string) Helpers::setting( 'embed_host_allowlist', '' ) ) ) ) );
		if ( empty( $list ) ) {
			return true;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		foreach ( $list as $allowed ) {
			if ( $host === $allowed || '.' . $allowed === substr( $host, -( strlen( $allowed ) + 1 ) ) ) {
				return true;
			}
		}
		return false;
	}
}
