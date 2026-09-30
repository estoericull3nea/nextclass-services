<?php
/**
 * Native booking REST handlers.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Front;

use NextClassServices\Booking\Appointment;
use NextClassServices\Booking\Availability;
use NextClassServices\Booking\Booking_Email;
use NextClassServices\Booking\Booking_Service;
use NextClassServices\Booking\Native_Booking;
use NextClassServices\Helpers;
use NextClassServices\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Availability lookups, booking creation, cancellation and rescheduling.
 * Every handler re-validates on the server; browser availability is advisory.
 */
class Booking_Handler {

	/**
	 * Read JSON body parameters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private static function body( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		$request->set_param( 'website', isset( $body['website'] ) ? $body['website'] : '' );
		return $body;
	}

	/**
	 * Load a native service that the requester may use.
	 *
	 * @param int  $id      Service id.
	 * @param bool $preview Whether the request claims preview mode.
	 * @param string $pn    Preview nonce.
	 * @return array|null [ id, config, preview ]
	 */
	private static function service( $id, $preview = false, $pn = '' ) {
		$data = $id ? Booking_Service::get( $id ) : null;
		if ( ! $data || 'native' !== $data['config']['method'] ) {
			return null;
		}
		$is_preview = false;
		if ( 'publish' !== $data['status'] || $preview ) {
			if ( ! Front::can_preview( 'ncs_manage_booking_services' ) ) {
				return null;
			}
			if ( '' !== $pn && ! wp_verify_nonce( $pn, 'ncs_preview_svc_' . $id ) ) {
				return null;
			}
			if ( 'publish' !== $data['status'] || '' !== $pn ) {
				$is_preview = true;
			}
		}
		return array(
			'id'      => (int) $data['id'],
			'config'  => $data['config'],
			'preview' => $is_preview,
		);
	}

	/**
	 * Appointment id to exclude when a management token is supplied.
	 *
	 * @param string $token      Token.
	 * @param int    $service_id Service id.
	 * @return int
	 */
	private static function exclude_id( $token, $service_id ) {
		if ( '' === $token ) {
			return 0;
		}
		$appt = Appointment::get_by_token( $token );
		return ( $appt && (int) $appt['service_id'] === (int) $service_id ) ? (int) $appt['id'] : 0;
	}

	/**
	 * Month availability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function availability( \WP_REST_Request $request ) {
		if ( ! Helpers::rate_limit( 'availability', 90, 60 ) ) {
			return Front::error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'next-class-services' ), 429 );
		}
		$svc = self::service( absint( $request->get_param( 'service' ) ) );
		if ( ! $svc ) {
			return Front::error( 'not_found', __( 'This service is not available.', 'next-class-services' ), 404 );
		}
		$month = (string) $request->get_param( 'month' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $month, $m ) || (int) $m[2] < 1 || (int) $m[2] > 12 ) {
			return Front::error( 'bad_request', __( 'Invalid month.', 'next-class-services' ), 400 );
		}
		$cfg     = $svc['config'];
		$tz      = Availability::tz( $cfg );
		$now     = new \DateTimeImmutable( 'now', $tz );
		$current = (int) $now->format( 'Y' ) * 12 + (int) $now->format( 'n' );
		$asked   = (int) $m[1] * 12 + (int) $m[2];
		$limit   = $now->modify( '+' . (int) $cfg['native']['max_advance_days'] . ' days' );
		$last    = (int) $limit->format( 'Y' ) * 12 + (int) $limit->format( 'n' );
		if ( $asked < $current || $asked > $last ) {
			return new \WP_REST_Response(
				array(
					'ok'       => true,
					'days'     => new \stdClass(),
					'timezone' => $tz->getName(),
				),
				200
			);
		}
		$token = preg_match( '/^[a-f0-9]{40}$/', (string) $request->get_param( 'token' ) ) ? (string) $request->get_param( 'token' ) : '';
		$days  = Availability::month( $cfg, $svc['id'], (int) $m[1], (int) $m[2], self::exclude_id( $token, $svc['id'] ) );

		$response = new \WP_REST_Response(
			array(
				'ok'       => true,
				'days'     => $days,
				'timezone' => $tz->getName(),
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Slots for a date.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function slots( \WP_REST_Request $request ) {
		if ( ! Helpers::rate_limit( 'slots', 90, 60 ) ) {
			return Front::error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'next-class-services' ), 429 );
		}
		$svc = self::service( absint( $request->get_param( 'service' ) ) );
		if ( ! $svc ) {
			return Front::error( 'not_found', __( 'This service is not available.', 'next-class-services' ), 404 );
		}
		$date = (string) $request->get_param( 'date' );
		if ( ! Helpers::parse_local_date( $date, Availability::tz( $svc['config'] ) ) ) {
			return Front::error( 'bad_request', __( 'Invalid date.', 'next-class-services' ), 400 );
		}
		$token = preg_match( '/^[a-f0-9]{40}$/', (string) $request->get_param( 'token' ) ) ? (string) $request->get_param( 'token' ) : '';
		$slots = Availability::slots( $svc['config'], $svc['id'], $date, self::exclude_id( $token, $svc['id'] ) );

		$out = array();
		foreach ( $slots as $s ) {
			$out[] = array(
				'time'  => $s['time'],
				'label' => $s['label'],
			);
		}
		$response = new \WP_REST_Response(
			array(
				'ok'    => true,
				'slots' => $out,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Friendly error for an action failure code.
	 *
	 * @param string $code Code.
	 * @return \WP_REST_Response
	 */
	private static function failure( $code ) {
		if ( 'busy' === $code ) {
			return Front::error( 'busy', __( 'The booking system is busy. Please try again in a moment.', 'next-class-services' ), 503 );
		}
		return Front::error( 'slot_taken', __( 'Sorry, that time is no longer available. Please choose another time.', 'next-class-services' ), 409 );
	}

	/**
	 * Create a booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function create( \WP_REST_Request $request ) {
		$body  = self::body( $request );
		$guard = Front::guard( $request, 'booking_create', 20, 600 );
		if ( $guard ) {
			return $guard;
		}
		$id  = isset( $body['service'] ) ? absint( $body['service'] ) : 0;
		$pn  = isset( $body['preview_nonce'] ) ? sanitize_text_field( (string) $body['preview_nonce'] ) : '';
		$svc = self::service( $id, ! empty( $body['preview'] ), $pn );
		if ( ! $svc ) {
			return Front::error( 'not_found', __( 'This service is not available.', 'next-class-services' ), 404 );
		}
		$cfg  = $svc['config'];
		$date = isset( $body['date'] ) ? (string) $body['date'] : '';
		$time = isset( $body['time'] ) ? (string) $body['time'] : '';

		list( $clean, $errors ) = Native_Booking::validate_fields( $cfg, isset( $body['fields'] ) ? $body['fields'] : array() );
		if ( ! empty( $errors ) ) {
			return Front::error( 'validation', __( 'Please review the highlighted fields and try again.', 'next-class-services' ), 422, array( 'errors' => $errors ) );
		}
		if ( ! Helpers::parse_local_date( $date, Availability::tz( $cfg ) ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return Front::error( 'bad_request', __( 'Please choose a date and time.', 'next-class-services' ), 400 );
		}

		if ( $svc['preview'] ) {
			$slot = Availability::find_slot( $cfg, $id, $date, $time );
			if ( ! $slot ) {
				return self::failure( 'slot_taken' );
			}
			$appt = array(
				'customer_name'  => isset( $clean['name'] ) ? $clean['name'] : '',
				'customer_email' => isset( $clean['email'] ) ? $clean['email'] : '',
				'customer_phone' => isset( $clean['phone'] ) ? $clean['phone'] : '',
				'company'        => isset( $clean['company'] ) ? $clean['company'] : '',
				'notes'          => isset( $clean['notes'] ) ? $clean['notes'] : '',
				'service_title'  => $cfg['title'],
				'start_utc'      => $slot['start_utc'],
				'timezone'       => Availability::tz( $cfg )->getName(),
				'status'         => $cfg['native']['new_status'],
				'page_url'       => '',
			);
			return self::created_response( $appt, $cfg, '', true );
		}

		$result = Native_Booking::create( $id, $cfg, $date, $time, $clean, isset( $body['page_url'] ) ? (string) $body['page_url'] : '' );
		if ( empty( $result['ok'] ) ) {
			return self::failure( $result['code'] );
		}
		return self::created_response( $result['appointment'], $cfg, $result['token'], false );
	}

	/**
	 * Build the success payload for a created booking.
	 *
	 * @param array  $appt    Appointment.
	 * @param array  $cfg     Config.
	 * @param string $token   Raw token.
	 * @param bool   $preview Preview mode.
	 * @return \WP_REST_Response
	 */
	private static function created_response( array $appt, array $cfg, $token, $preview ) {
		$vars     = Booking_Email::vars( $appt, $cfg, $token );
		$statuses = Appointment::statuses();
		$message  = Mailer::replace( $cfg['confirmation']['message'], $vars, 'text' );
		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'preview'     => $preview,
				'message'     => $message,
				'appointment' => array(
					'service'     => $vars['service_name'],
					'date'        => $vars['appointment_date'],
					'time'        => $vars['appointment_time'],
					'timezone'    => $vars['timezone'],
					'status'      => $appt['status'],
					'statusLabel' => isset( $statuses[ $appt['status'] ] ) ? $statuses[ $appt['status'] ] : $appt['status'],
				),
				'manageUrl'   => ( '' !== $token && ( $cfg['native']['allow_cancel'] || $cfg['native']['allow_reschedule'] ) ) ? $vars['manage_link'] : '',
				'redirect'    => $cfg['confirmation']['redirect_url'],
			),
			200
		);
	}

	/**
	 * Resolve appointment and service from a token.
	 *
	 * @param string $token Token.
	 * @return array|null [ appt, cfg ]
	 */
	private static function by_token( $token ) {
		$appt = Appointment::get_by_token( $token );
		if ( ! $appt ) {
			return null;
		}
		$svc = Booking_Service::get( $appt['service_id'] );
		if ( ! $svc || 'native' !== $svc['config']['method'] ) {
			return null;
		}
		return array( $appt, $svc['config'] );
	}

	/**
	 * Token lookup for the manage view.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function manage( \WP_REST_Request $request ) {
		$body  = self::body( $request );
		$guard = Front::guard( $request, 'booking_manage', 30, 600 );
		if ( $guard ) {
			return $guard;
		}
		$token = isset( $body['token'] ) ? sanitize_text_field( (string) $body['token'] ) : '';
		$found = self::by_token( $token );
		if ( ! $found ) {
			return Front::error( 'not_found', __( 'We could not find that booking. The link may be incorrect.', 'next-class-services' ), 404 );
		}
		list( $appt, $cfg ) = $found;
		$vars               = Booking_Email::vars( $appt, $cfg );
		list( $can_cancel, $cancel_reason ) = Native_Booking::can_cancel( $appt, $cfg );
		list( $can_resched, $resched_reason ) = Native_Booking::can_reschedule( $appt, $cfg );
		$statuses = Appointment::statuses();

		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'service'     => Booking_Service::public_config( $appt['service_id'], $cfg ),
				'appointment' => array(
					'name'        => $appt['customer_name'],
					'service'     => $vars['service_name'],
					'date'        => $vars['appointment_date'],
					'time'        => $vars['appointment_time'],
					'timezone'    => $vars['timezone'],
					'status'      => $appt['status'],
					'statusLabel' => isset( $statuses[ $appt['status'] ] ) ? $statuses[ $appt['status'] ] : $appt['status'],
					'active'      => Appointment::is_active( $appt['status'] ),
				),
				'canCancel'        => $can_cancel,
				'cancelReason'     => $can_cancel ? '' : $cancel_reason,
				'canReschedule'    => $can_resched,
				'rescheduleReason' => $can_resched ? '' : $resched_reason,
			),
			200
		);
	}

	/**
	 * Cancel by token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function cancel( \WP_REST_Request $request ) {
		$body  = self::body( $request );
		$guard = Front::guard( $request, 'booking_cancel', 10, 600 );
		if ( $guard ) {
			return $guard;
		}
		$token = isset( $body['token'] ) ? sanitize_text_field( (string) $body['token'] ) : '';
		$found = self::by_token( $token );
		if ( ! $found ) {
			return Front::error( 'not_found', __( 'We could not find that booking.', 'next-class-services' ), 404 );
		}
		list( $appt, $cfg )       = $found;
		list( $allowed, $reason ) = Native_Booking::can_cancel( $appt, $cfg );
		if ( ! $allowed ) {
			return Front::error( 'not_allowed', $reason, 403 );
		}
		if ( ! Native_Booking::cancel( $appt, $cfg ) ) {
			return Front::error( 'failed', __( 'We could not cancel this booking. Please contact us.', 'next-class-services' ), 500 );
		}
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'message' => __( 'Your booking has been cancelled.', 'next-class-services' ),
			),
			200
		);
	}

	/**
	 * Reschedule by token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function reschedule( \WP_REST_Request $request ) {
		$body  = self::body( $request );
		$guard = Front::guard( $request, 'booking_reschedule', 10, 600 );
		if ( $guard ) {
			return $guard;
		}
		$token = isset( $body['token'] ) ? sanitize_text_field( (string) $body['token'] ) : '';
		$found = self::by_token( $token );
		if ( ! $found ) {
			return Front::error( 'not_found', __( 'We could not find that booking.', 'next-class-services' ), 404 );
		}
		list( $appt, $cfg )       = $found;
		list( $allowed, $reason ) = Native_Booking::can_reschedule( $appt, $cfg );
		if ( ! $allowed ) {
			return Front::error( 'not_allowed', $reason, 403 );
		}
		$date = isset( $body['date'] ) ? (string) $body['date'] : '';
		$time = isset( $body['time'] ) ? (string) $body['time'] : '';
		if ( ! Helpers::parse_local_date( $date, Availability::tz( $cfg ) ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return Front::error( 'bad_request', __( 'Please choose a date and time.', 'next-class-services' ), 400 );
		}
		$result = Native_Booking::reschedule( $appt, $cfg, $date, $time, $token );
		if ( empty( $result['ok'] ) ) {
			return self::failure( $result['code'] );
		}
		$vars = Booking_Email::vars( $result['appointment'], $cfg, $token );
		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'message'     => __( 'Your booking has been rescheduled.', 'next-class-services' ),
				'appointment' => array(
					'service'  => $vars['service_name'],
					'date'     => $vars['appointment_date'],
					'time'     => $vars['appointment_time'],
					'timezone' => $vars['timezone'],
				),
			),
			200
		);
	}
}
