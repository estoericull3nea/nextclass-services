<?php
/**
 * Native booking emails.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Helpers;
use NextClassServices\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer and admin emails for native bookings.
 */
class Booking_Email {

	/**
	 * Placeholder names.
	 *
	 * @return array
	 */
	public static function placeholders() {
		return array( 'customer_name', 'customer_email', 'customer_phone', 'service_name', 'appointment_date', 'appointment_time', 'timezone', 'booking_status', 'company', 'notes', 'manage_link', 'previous_date', 'previous_time' );
	}

	/**
	 * Build placeholder values.
	 *
	 * @param array  $appt  Appointment row.
	 * @param array  $cfg   Service config.
	 * @param string $token Raw management token if known.
	 * @param array  $prev  Previous appointment row for reschedules.
	 * @return array
	 */
	public static function vars( array $appt, array $cfg, $token = '', array $prev = array() ) {
		$tz       = $appt['timezone'];
		$statuses = Appointment::statuses();
		$date_fmt = get_option( 'date_format' );
		$time_fmt = get_option( 'time_format' );
		$link     = '' !== $token ? Appointment::manage_url( $appt, $token ) : '';
		return array(
			'customer_name'    => $appt['customer_name'],
			'customer_email'   => $appt['customer_email'],
			'customer_phone'   => $appt['customer_phone'],
			'service_name'     => '' !== $cfg['title'] ? $cfg['title'] : $appt['service_title'],
			'appointment_date' => Helpers::format_utc( $appt['start_utc'], $date_fmt, $tz ),
			'appointment_time' => Helpers::format_utc( $appt['start_utc'], $time_fmt, $tz ),
			'timezone'         => Appointment::tz_label( $tz, $appt['start_utc'] ),
			'booking_status'   => isset( $statuses[ $appt['status'] ] ) ? $statuses[ $appt['status'] ] : $appt['status'],
			'company'          => $appt['company'],
			'notes'            => (string) $appt['notes'],
			'manage_link'      => $link,
			'previous_date'    => ! empty( $prev ) ? Helpers::format_utc( $prev['start_utc'], $date_fmt, $tz ) : '',
			'previous_time'    => ! empty( $prev ) ? Helpers::format_utc( $prev['start_utc'], $time_fmt, $tz ) : '',
		);
	}

	/**
	 * Common send options.
	 *
	 * @param array $cfg Config.
	 * @return array
	 */
	private static function options( array $cfg ) {
		return array(
			'from_name' => $cfg['notify']['from_name'],
			'reply_to'  => $cfg['notify']['reply_to'],
		);
	}

	/**
	 * Admin recipient list.
	 *
	 * @param array $cfg Config.
	 * @return array
	 */
	private static function admin_recipients( array $cfg ) {
		$to = Helpers::parse_emails( $cfg['notify']['admin']['recipients'] );
		return empty( $to ) ? array( Helpers::notification_email() ) : $to;
	}

	/**
	 * Send a template.
	 *
	 * @param array  $tpl     Template with subject/body.
	 * @param array  $vars    Values.
	 * @param mixed  $to      Recipient(s).
	 * @param array  $cfg     Config.
	 * @param string $heading Heading.
	 * @param array  $opts    Extra options.
	 * @return bool
	 */
	private static function dispatch( array $tpl, array $vars, $to, array $cfg, $heading, array $opts = array() ) {
		$args = array( 'heading' => $heading );
		if ( '' !== $vars['manage_link'] ) {
			$args['cta_url']   = $vars['manage_link'];
			$args['cta_label'] = __( 'Manage your booking', 'next-class-services' );
		}
		$message = Mailer::compose( $tpl['subject'], $tpl['body'], $vars, $args );
		return Mailer::send( $to, $message, array_merge( self::options( $cfg ), $opts ) );
	}

	/**
	 * Customer confirmation.
	 *
	 * @param array  $appt  Appointment.
	 * @param array  $cfg   Config.
	 * @param string $token Raw token.
	 * @return bool
	 */
	public static function send_confirmation( array $appt, array $cfg, $token ) {
		$tpl = $cfg['notify']['customer'];
		if ( empty( $tpl['enabled'] ) ) {
			return false;
		}
		return self::dispatch( $tpl, self::vars( $appt, $cfg, $token ), $appt['customer_email'], $cfg, __( 'Booking received', 'next-class-services' ) );
	}

	/**
	 * Admin new booking notice.
	 *
	 * @param array $appt Appointment.
	 * @param array $cfg  Config.
	 * @return bool
	 */
	public static function send_admin_new( array $appt, array $cfg ) {
		$tpl = $cfg['notify']['admin'];
		if ( empty( $tpl['enabled'] ) ) {
			return false;
		}
		return self::dispatch( $tpl, self::vars( $appt, $cfg ), self::admin_recipients( $cfg ), $cfg, __( 'New booking', 'next-class-services' ), array( 'reply_to' => $appt['customer_email'] ) );
	}

	/**
	 * Cancellation email(s).
	 *
	 * @param array $appt Appointment.
	 * @param array $cfg  Config.
	 * @return bool
	 */
	public static function send_cancellation( array $appt, array $cfg ) {
		$tpl = $cfg['notify']['cancel'];
		if ( empty( $tpl['enabled'] ) ) {
			return false;
		}
		$vars = self::vars( $appt, $cfg );
		$ok   = self::dispatch( $tpl, $vars, $appt['customer_email'], $cfg, __( 'Booking cancelled', 'next-class-services' ) );
		if ( ! empty( $tpl['notify_admin'] ) ) {
			self::dispatch( $tpl, $vars, self::admin_recipients( $cfg ), $cfg, __( 'Booking cancelled', 'next-class-services' ), array( 'reply_to' => $appt['customer_email'] ) );
		}
		return $ok;
	}

	/**
	 * Reschedule email(s).
	 *
	 * @param array  $appt  Updated appointment.
	 * @param array  $prev  Previous appointment.
	 * @param array  $cfg   Config.
	 * @param string $token Raw token.
	 * @return bool
	 */
	public static function send_reschedule( array $appt, array $prev, array $cfg, $token ) {
		$tpl = $cfg['notify']['reschedule'];
		if ( empty( $tpl['enabled'] ) ) {
			return false;
		}
		$ok = self::dispatch( $tpl, self::vars( $appt, $cfg, $token, $prev ), $appt['customer_email'], $cfg, __( 'Booking rescheduled', 'next-class-services' ) );
		if ( ! empty( $tpl['notify_admin'] ) ) {
			self::dispatch( $tpl, self::vars( $appt, $cfg, '', $prev ), self::admin_recipients( $cfg ), $cfg, __( 'Booking rescheduled', 'next-class-services' ), array( 'reply_to' => $appt['customer_email'] ) );
		}
		return $ok;
	}

	/**
	 * Sample appointment for tests.
	 *
	 * @param array $cfg Config.
	 * @return array
	 */
	private static function sample( array $cfg ) {
		$tz    = Availability::tz( $cfg )->getName();
		$start = gmdate( 'Y-m-d H:i:s', time() + ( 3 * DAY_IN_SECONDS ) );
		return array(
			'id'             => 0,
			'service_id'     => 0,
			'service_title'  => $cfg['title'],
			'customer_name'  => 'Alex Sample',
			'customer_email' => 'alex.sample@example.com',
			'customer_phone' => '555 0100',
			'company'        => 'Sample Company',
			'job_title'      => '',
			'notes'          => 'Sample note.',
			'start_utc'      => $start,
			'timezone'       => $tz,
			'status'         => 'confirmed',
			'page_url'       => '',
		);
	}

	/**
	 * Send a test email using the saved template.
	 *
	 * @param string $kind customer|admin|cancel|reschedule.
	 * @param array  $cfg  Config.
	 * @param string $to   Recipient.
	 * @return bool
	 */
	public static function send_test( $kind, array $cfg, $to ) {
		$appt = self::sample( $cfg );
		$prev = $appt;
		$prev['start_utc'] = gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) );
		$tok  = 'preview';
		switch ( $kind ) {
			case 'admin':
				$tpl = $cfg['notify']['admin'];
				return self::dispatch( $tpl, self::vars( $appt, $cfg ), array( $to ), $cfg, __( 'New booking', 'next-class-services' ) );
			case 'cancel':
				$tpl = $cfg['notify']['cancel'];
				return self::dispatch( $tpl, self::vars( $appt, $cfg ), array( $to ), $cfg, __( 'Booking cancelled', 'next-class-services' ) );
			case 'reschedule':
				$tpl = $cfg['notify']['reschedule'];
				return self::dispatch( $tpl, self::vars( $appt, $cfg, $tok, $prev ), array( $to ), $cfg, __( 'Booking rescheduled', 'next-class-services' ) );
			case 'customer':
			default:
				$tpl = $cfg['notify']['customer'];
				return self::dispatch( $tpl, self::vars( $appt, $cfg, $tok ), array( $to ), $cfg, __( 'Booking received', 'next-class-services' ) );
		}
	}
}
