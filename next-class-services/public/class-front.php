<?php
/**
 * Public side bootstrap: shortcodes and REST routes.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Front;

use NextClassServices\Helpers;
use NextClassServices\Post_Types;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers public shortcodes and REST endpoints.
 */
class Front {

	const REST_NS = 'ncs/v1';

	/**
	 * Init.
	 */
	public static function init() {
		add_shortcode( 'next_class_assessment', array( 'NextClassServices\Front\Assessment_Shortcode', 'render' ) );
		add_shortcode( 'next_class_booking', array( 'NextClassServices\Front\Booking_Shortcode', 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes. Public routes verify a nonce, apply rate limits and
	 * validate every parameter inside their handlers.
	 */
	public static function register_routes() {
		$public = '__return_true';

		register_rest_route(
			self::REST_NS,
			'/assessment/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'NextClassServices\Front\Assessment_Handler', 'submit' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'fresh_nonce' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'event' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/booking/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'NextClassServices\Front\Booking_Handler', 'availability' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/booking/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'NextClassServices\Front\Booking_Handler', 'slots' ),
				'permission_callback' => $public,
			)
		);
		foreach ( array( 'create', 'manage', 'cancel', 'reschedule' ) as $action ) {
			register_rest_route(
				self::REST_NS,
				'/booking/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( 'NextClassServices\Front\Booking_Handler', $action ),
					'permission_callback' => $public,
				)
			);
		}
	}

	/**
	 * Fresh nonce for pages served from a full page cache.
	 *
	 * @return \WP_REST_Response
	 */
	public static function fresh_nonce() {
		if ( ! Helpers::rate_limit( 'nonce', 30, 60 ) ) {
			return self::error( 'rate_limited', __( 'Too many requests.', 'next-class-services' ), 429 );
		}
		$response = new \WP_REST_Response( array( 'nonce' => wp_create_nonce( 'ncs_public' ) ), 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Verify the public nonce header.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function verify_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-NCS-Nonce' );
		return $nonce && wp_verify_nonce( sanitize_text_field( $nonce ), 'ncs_public' );
	}

	/**
	 * Build an error response.
	 *
	 * @param string $code    Machine code.
	 * @param string $message Visitor friendly message.
	 * @param int    $status  HTTP status.
	 * @param array  $extra   Extra data.
	 * @return \WP_REST_Response
	 */
	public static function error( $code, $message, $status = 400, array $extra = array() ) {
		return new \WP_REST_Response(
			array_merge(
				array(
					'ok'      => false,
					'code'    => $code,
					'message' => $message,
				),
				$extra
			),
			$status
		);
	}

	/**
	 * Standard guard for POST endpoints: nonce, honeypot and rate limit.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $bucket  Rate limit bucket.
	 * @param int              $limit   Limit per window.
	 * @param int              $window  Window seconds.
	 * @return \WP_REST_Response|null Error response or null when allowed.
	 */
	public static function guard( \WP_REST_Request $request, $bucket, $limit, $window ) {
		if ( ! Helpers::rate_limit( $bucket, $limit, $window ) ) {
			return self::error( 'rate_limited', __( 'Too many requests. Please wait a moment and try again.', 'next-class-services' ), 429 );
		}
		if ( ! self::verify_nonce( $request ) ) {
			return self::error( 'bad_nonce', __( 'Your session has expired. Please refresh the page and try again.', 'next-class-services' ), 403 );
		}
		$hp = $request->get_param( 'website' );
		if ( is_string( $hp ) && '' !== trim( $hp ) ) {
			// Pretend success to bots without doing any work.
			return self::error( 'spam', __( 'We could not process this request.', 'next-class-services' ), 400 );
		}
		return null;
	}

	/**
	 * Is the current REST user allowed to preview.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	public static function can_preview( $cap ) {
		return is_user_logged_in() && current_user_can( $cap );
	}

	/**
	 * Analytics event endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function event( \WP_REST_Request $request ) {
		$guard = self::guard( $request, 'event', 90, 60 );
		if ( $guard ) {
			return new \WP_REST_Response( array( 'ok' => false ), 202 );
		}
		$event   = sanitize_key( (string) $request->get_param( 'event' ) );
		$allowed = Tracker::client_events();
		$id      = absint( $request->get_param( 'id' ) );
		if ( ! isset( $allowed[ $event ] ) || ! $id ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$expected = 'assessment' === $allowed[ $event ] ? Post_Types::ASSESSMENT : Post_Types::SERVICE;
		$post     = get_post( $id );
		if ( ! $post || $expected !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_REST_Response( array( 'ok' => false ), 404 );
		}
		$meta = sanitize_text_field( (string) $request->get_param( 'meta' ) );
		Tracker::record( $event, $allowed[ $event ], $id, $meta );
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Inline style for scoped appearance variables.
	 *
	 * @return string
	 */
	public static function style_vars() {
		$color  = '#393939';
		$radius = max( 0, min( 16, (int) Helpers::setting( 'frontend_radius', 4 ) ) );
		return '--ncs-primary:' . $color . ';--ncs-radius:' . $radius . 'px;';
	}

	/**
	 * Extra CSS class for the frontend font choice.
	 *
	 * @return string
	 */
	public static function font_class() {
		return Helpers::setting( 'frontend_poppins' ) ? ' ncs-font-poppins' : '';
	}
}
