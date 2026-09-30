<?php
/**
 * [next_class_booking] shortcode.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Front;

use NextClassServices\Assets;
use NextClassServices\Booking\Booking_Service;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the booking container. booking.js builds native flows and embeds.
 */
class Booking_Shortcode {

	/**
	 * Instance counter.
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), (array) $atts, 'next_class_booking' );
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $atts['id'] ) ) ) ) );
		return self::render_services( array_slice( $ids, 0, 10 ), false );
	}

	/**
	 * Render one or more services.
	 *
	 * @param array $ids           Service ids.
	 * @param bool  $force_preview Force preview mode.
	 * @return string
	 */
	public static function render_services( array $ids, $force_preview = false ) {
		$can_manage = current_user_can( 'ncs_manage_booking_services' );
		if ( empty( $ids ) ) {
			return $can_manage ? '<div class="ncs-root ncs-notice">' . esc_html__( 'Next Class Services: add a service ID to the booking shortcode.', 'next-class-services' ) . '</div>' : '';
		}

		$services = array();
		$preview  = false;
		foreach ( $ids as $id ) {
			$data = Booking_Service::get( $id );
			if ( ! $data ) {
				continue;
			}
			$published = 'publish' === $data['status'];
			if ( ! $published && ! $can_manage ) {
				continue;
			}
			$item = Booking_Service::public_config( $id, $data['config'] );
			if ( ! $published || $force_preview ) {
				$item['preview']      = true;
				$item['previewNonce'] = wp_create_nonce( 'ncs_preview_svc_' . $id );
				$preview              = true;
			}
			$services[] = $item;
		}

		if ( empty( $services ) ) {
			return $can_manage ? '<div class="ncs-root ncs-notice">' . esc_html__( 'Next Class Services: no matching booking service was found or it is not published.', 'next-class-services' ) . '</div>' : '';
		}

		Assets::enqueue_public( 'booking', $preview );
		self::$instances++;

		$config = array(
			'services' => $services,
			'preview'  => $preview,
		);
		if ( $preview ) {
			$config['restNonce'] = wp_create_nonce( 'wp_rest' );
		}

		return Helpers::render(
			'booking/container',
			array(
				'dom_id'  => 'ncs-booking-' . self::$instances,
				'config'  => $config,
				'preview' => $preview,
				'style'   => Front::style_vars(),
				'font'    => Front::font_class(),
			)
		);
	}
}
