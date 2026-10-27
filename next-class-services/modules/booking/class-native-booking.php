<?php
/**
 * Native booking workflow.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Helpers;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, cancels and reschedules native appointments.
 *
 * WordPress owns availability. Services can opt into outbound Google Calendar
 * synchronization; lifecycle actions remain available for other integrations.
 */
class Native_Booking {

	/**
	 * Validate customer fields.
	 *
	 * @param array $cfg   Service config.
	 * @param mixed $input Raw input.
	 * @return array [ clean, errors ]
	 */
	public static function validate_fields( array $cfg, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$clean  = array();
		$errors = array();
		foreach ( $cfg['native']['fields'] as $f ) {
			if ( empty( $f['enabled'] ) ) {
				continue;
			}
			$key = $f['key'];
			$raw = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( 'textarea' === $f['type'] ) {
				$val = Helpers::truncate( sanitize_textarea_field( $raw ), 2000 );
			} elseif ( 'email' === $f['type'] ) {
				$val = sanitize_email( $raw );
			} else {
				$val = Helpers::truncate( sanitize_text_field( $raw ), 190 );
			}
			if ( '' === $val ) {
				if ( ! empty( $f['required'] ) ) {
					/* translators: %s: field label */
					$errors[ $key ] = sprintf( __( '%s is required.', 'next-class-services' ), $f['label'] );
				}
				$clean[ $key ] = '';
				continue;
			}
			if ( 'email' === $f['type'] && ! is_email( $val ) ) {
				$errors[ $key ] = __( 'Please enter a valid email address.', 'next-class-services' );
			} elseif ( 'tel' === $f['type'] && ! preg_match( '/^[0-9+\-().\s]{5,40}$/', $val ) ) {
				$errors[ $key ] = __( 'Please enter a valid phone number.', 'next-class-services' );
			} elseif ( 'select' === $f['type'] && ! in_array( $val, $f['options'], true ) ) {
				$errors[ $key ] = __( 'Please choose one of the available options.', 'next-class-services' );
			}
			$clean[ $key ] = $val;
		}
		return array( $clean, $errors );
	}

	/**
	 * Acquire the booking lock.
	 *
	 * @param int $service_id Service.
	 * @return string|false Lock name, or false when it could not be obtained.
	 */
	private static function lock( $service_id ) {
		global $wpdb;
		$name = 'ncs_' . substr( md5( $wpdb->prefix . ( Helpers::setting( 'shared_calendar' ) ? 'all' : (int) $service_id ) ), 0, 20 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) );
		if ( '0' === (string) $got ) {
			return false;
		}
		return $name;
	}

	/**
	 * Release the lock.
	 *
	 * @param string $name Lock name.
	 */
	private static function unlock( $name ) {
		global $wpdb;
		if ( $name ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Only accept a page URL on this site.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function clean_page_url( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host || strtolower( $host ) !== strtolower( (string) $home ) ) {
			return '';
		}
		return Helpers::truncate( remove_query_arg( array( 'ncs_manage' ), $url ), 480 );
	}

	/**
	 * Create an appointment.
	 *
	 * @param int    $service_id Service id.
	 * @param array  $cfg        Service config.
	 * @param string $date       Y-m-d.
	 * @param string $time       HH:MM.
	 * @param array  $clean      Validated field values.
	 * @param string $page_url   Originating page.
	 * @return array ok, code, appointment, token
	 */
	public static function create( $service_id, array $cfg, $date, $time, array $clean, $page_url ) {
		$lock = self::lock( $service_id );
		if ( false === $lock ) {
			return array( 'ok' => false, 'code' => 'busy' );
		}

		$slot = Availability::find_slot( $cfg, $service_id, $date, $time );
		if ( ! $slot ) {
			self::unlock( $lock );
			return array( 'ok' => false, 'code' => 'slot_taken' );
		}

		$custom = array();
		foreach ( $cfg['native']['fields'] as $f ) {
			if ( ! empty( $f['custom'] ) && ! empty( $f['enabled'] ) && isset( $clean[ $f['key'] ] ) && '' !== $clean[ $f['key'] ] ) {
				$custom[] = array(
					'label' => $f['label'],
					'value' => $clean[ $f['key'] ],
				);
			}
		}

		$token  = Appointment::new_token();
		$status = 'pending' === $cfg['native']['new_status'] ? 'pending' : 'confirmed';
		$row    = array(
			'service_id'      => (int) $service_id,
			'service_title'   => Helpers::truncate( '' !== $cfg['title'] ? $cfg['title'] : $cfg['admin_title'], 250 ),
			'token_hash'      => Appointment::hash_token( $token ),
			'customer_name'   => isset( $clean['name'] ) ? $clean['name'] : '',
			'customer_email'  => isset( $clean['email'] ) ? $clean['email'] : '',
			'customer_phone'  => isset( $clean['phone'] ) ? $clean['phone'] : '',
			'company'         => isset( $clean['company'] ) ? $clean['company'] : '',
			'job_title'       => isset( $clean['job_title'] ) ? $clean['job_title'] : '',
			'notes'           => isset( $clean['notes'] ) ? $clean['notes'] : '',
			'custom_fields'   => empty( $custom ) ? null : wp_json_encode( $custom ),
			'start_utc'       => $slot['start_utc'],
			'end_utc'         => $slot['end_utc'],
			'block_start_utc' => $slot['block_start_utc'],
			'block_end_utc'   => $slot['block_end_utc'],
			'local_date'      => $slot['local_date'],
			'timezone'        => Availability::tz( $cfg )->getName(),
			'status'          => $status,
			'active_slot'     => Appointment::slot_key( $service_id, $slot['start_ts'] ),
			'google_sync_enabled' => ! empty( $cfg['native']['google_sync'] ) ? 1 : 0,
			'page_url'        => self::clean_page_url( $page_url ),
		);
		$id = Appointment::insert( $row );
		self::unlock( $lock );

		if ( ! $id ) {
			return array( 'ok' => false, 'code' => 'slot_taken' );
		}

		$appt = Appointment::get( $id );
		Google_Calendar::sync_now( $id );
		$appt = Appointment::get( $id );
		Tracker::record( 'booking_complete', 'booking', $service_id, 'native' );

		Booking_Email::send_confirmation( $appt, $cfg, $token );
		Booking_Email::send_admin_new( $appt, $cfg );

		/**
		 * Fires after a native appointment was created.
		 *
		 * @param array $appt Appointment row.
		 * @param array $cfg  Service config.
		 */
		do_action( 'ncs_appointment_created', $appt, $cfg );

		return array(
			'ok'          => true,
			'appointment' => $appt,
			'token'       => $token,
		);
	}

	/**
	 * Hours until an appointment starts.
	 *
	 * @param array $appt Appointment.
	 * @return float
	 */
	private static function hours_until( array $appt ) {
		return ( strtotime( $appt['start_utc'] . ' UTC' ) - time() ) / HOUR_IN_SECONDS;
	}

	/**
	 * Can the customer cancel.
	 *
	 * @param array $appt Appointment.
	 * @param array $cfg  Config.
	 * @return array [ allowed, reason ]
	 */
	public static function can_cancel( array $appt, array $cfg ) {
		if ( ! Appointment::is_active( $appt['status'] ) ) {
			return array( false, __( 'This booking is no longer active.', 'next-class-services' ) );
		}
		if ( empty( $cfg['native']['allow_cancel'] ) ) {
			return array( false, __( 'Online cancellation is not available for this service. Please contact us.', 'next-class-services' ) );
		}
		$hours = self::hours_until( $appt );
		if ( $hours <= 0 || $hours < $cfg['native']['cancel_notice_hours'] ) {
			/* translators: %d: hours */
			return array( false, sprintf( __( 'Cancellations need at least %d hours notice. Please contact us.', 'next-class-services' ), $cfg['native']['cancel_notice_hours'] ) );
		}
		return array( true, '' );
	}

	/**
	 * Can the customer reschedule.
	 *
	 * @param array $appt Appointment.
	 * @param array $cfg  Config.
	 * @return array [ allowed, reason ]
	 */
	public static function can_reschedule( array $appt, array $cfg ) {
		if ( ! Appointment::is_active( $appt['status'] ) ) {
			return array( false, __( 'This booking is no longer active.', 'next-class-services' ) );
		}
		if ( empty( $cfg['native']['allow_reschedule'] ) ) {
			return array( false, __( 'Online rescheduling is not available for this service. Please contact us.', 'next-class-services' ) );
		}
		$hours = self::hours_until( $appt );
		if ( $hours <= 0 || $hours < $cfg['native']['reschedule_notice_hours'] ) {
			/* translators: %d: hours */
			return array( false, sprintf( __( 'Rescheduling needs at least %d hours notice. Please contact us.', 'next-class-services' ), $cfg['native']['reschedule_notice_hours'] ) );
		}
		return array( true, '' );
	}

	/**
	 * Cancel an appointment.
	 *
	 * @param array $appt   Appointment.
	 * @param array $cfg    Config.
	 * @param bool  $notify Send emails.
	 * @return bool
	 */
	public static function cancel( array $appt, array $cfg, $notify = true ) {
		$result = Appointment::set_status( $appt, 'cancelled' );
		if ( true !== $result ) {
			return false;
		}
		$appt = Appointment::get( $appt['id'] );
		Tracker::record( 'booking_cancel', 'booking', $appt['service_id'], 'native' );
		if ( $notify ) {
			Booking_Email::send_cancellation( $appt, $cfg );
		}
		do_action( 'ncs_appointment_cancelled', $appt, $cfg );
		return true;
	}

	/**
	 * Reschedule to a new slot.
	 *
	 * @param array  $appt  Appointment.
	 * @param array  $cfg   Config.
	 * @param string $date  Y-m-d.
	 * @param string $time  HH:MM.
	 * @param string $token Raw token (for the email link).
	 * @return array ok, code, appointment
	 */
	public static function reschedule( array $appt, array $cfg, $date, $time, $token ) {
		$lock = self::lock( $appt['service_id'] );
		if ( false === $lock ) {
			return array( 'ok' => false, 'code' => 'busy' );
		}
		$slot = Availability::find_slot( $cfg, (int) $appt['service_id'], $date, $time, (int) $appt['id'] );
		if ( ! $slot ) {
			self::unlock( $lock );
			return array( 'ok' => false, 'code' => 'slot_taken' );
		}
		$prev = $appt;
		$ok   = Appointment::update(
			$appt['id'],
			array(
				'start_utc'        => $slot['start_utc'],
				'end_utc'          => $slot['end_utc'],
				'block_start_utc'  => $slot['block_start_utc'],
				'block_end_utc'    => $slot['block_end_utc'],
				'local_date'       => $slot['local_date'],
				'active_slot'      => Appointment::slot_key( $appt['service_id'], $slot['start_ts'] ),
				'reschedule_count' => (int) $appt['reschedule_count'] + 1,
			)
		);
		self::unlock( $lock );
		if ( ! $ok ) {
			return array( 'ok' => false, 'code' => 'slot_taken' );
		}
		$updated = Appointment::get( $appt['id'] );
		Google_Calendar::sync_now( $appt['id'] );
		$updated = Appointment::get( $appt['id'] );
		Tracker::record( 'booking_reschedule', 'booking', $appt['service_id'], 'native' );
		Booking_Email::send_reschedule( $updated, $prev, $cfg, $token );
		do_action( 'ncs_appointment_updated', $updated, $prev, $cfg );
		return array(
			'ok'          => true,
			'appointment' => $updated,
		);
	}
}
