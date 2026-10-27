<?php
/** Direct administrative calendar mail, independent of Google's guest notifications. @package NextClassServices */
namespace NextClassServices\Booking;

use NextClassServices\Helpers;
use NextClassServices\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Calendar_Invite {
	/** Whitelist calendar identity needed for a matching cancellation after remote deletion. */
	public static function metadata( array $event ) {
		return array(
			'uid' => isset( $event['iCalUID'] ) ? Helpers::single_line( $event['iCalUID'] ) : '',
			'organizer' => isset( $event['organizer']['email'] ) ? sanitize_email( $event['organizer']['email'] ) : '',
			'sequence' => isset( $event['sequence'] ) ? max( 0, (int) $event['sequence'] ) : 0,
			'url' => isset( $event['htmlLink'] ) ? Helpers::safe_url( $event['htmlLink'] ) : '',
		);
	}

	public static function send( array $appt, array $meta, $cancelled = false ) {
		$to = Helpers::notification_email();
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'admin_invite', __( 'Admin calendar email was not sent: configure a valid Notification email or WordPress admin email.', 'next-class-services' ) );
		}
		if ( empty( $meta['uid'] ) || empty( $meta['organizer'] ) ) {
			return new \WP_Error( 'admin_invite', __( 'Admin calendar email is waiting for the Google event identity. Retry calendar sync.', 'next-class-services' ) );
		}
		$method = $cancelled ? 'CANCEL' : 'REQUEST';
		$signature = hash( 'sha256', wp_json_encode( array( $to, $meta['uid'], $method, $appt['start_utc'], $appt['end_utc'], $appt['status'], $cancelled ? '' : $appt['google_meet_url'] ) ) );
		if ( ! empty( $appt['google_admin_invite_signature'] ) && hash_equals( $appt['google_admin_invite_signature'], $signature ) ) {
			return true;
		}
		$subject = ( $cancelled ? __( 'Cancelled: ', 'next-class-services' ) : __( 'Calendar invitation: ', 'next-class-services' ) ) . $appt['service_title'];
		$vars = array(
			'service' => $appt['service_title'], 'customer' => $appt['customer_name'], 'email' => $appt['customer_email'],
			'when' => Helpers::format_utc( $appt['start_utc'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $appt['timezone'] ),
			'timezone' => $appt['timezone'], 'meet' => $cancelled ? '' : $appt['google_meet_url'],
		);
		$body = $cancelled ? __( "Please note that the following appointment has been cancelled. The attached calendar file reflects this cancellation.\n\n", 'next-class-services' ) : __( "Please find the calendar invitation for the following client appointment. Review the details below and use the attached calendar file to update your schedule.\n\n", 'next-class-services' );
		$body .= __( "Service: {service}\nClient: {customer}\nEmail: {email}\nDate and time: {when}\nTimezone: {timezone}\n\nPlease retain this email for your records.", 'next-class-services' );
		$message = Mailer::compose( $subject, $body, $vars, array( 'heading' => $subject, 'greeting' => __( 'Dear Team,', 'next-class-services' ), 'cta_label' => __( 'View Google Calendar event', 'next-class-services' ), 'cta_url' => $cancelled ? '' : $meta['url'], 'secondary_label' => __( 'Join Google Meet', 'next-class-services' ), 'secondary_url' => $vars['meet'] ) );
		$sent = Mailer::send( $to, $message, array( 'calendar' => self::ics( $appt, $meta, $to, $cancelled ), 'calendar_method' => $method, 'reply_to' => $appt['customer_email'] ) );
		if ( ! $sent ) {
			return new \WP_Error( 'admin_invite', __( 'The calendar event is synced, but WordPress could not send the admin invitation email. Check mail/SMTP configuration, then retry sync.', 'next-class-services' ) );
		}
		if ( ! Appointment::update( $appt['id'], array( 'google_admin_invite_signature' => $signature ) ) ) {
			return new \WP_Error( 'admin_invite', __( 'Admin calendar email was accepted, but its delivery state could not be saved.', 'next-class-services' ) );
		}
		return true;
	}

	/** Escape iCalendar text and fold lines at 75 UTF-8 octets (RFC 5545). */
	private static function text( $value ) {
		return str_replace( array( '\\', "\r\n", "\r", "\n", ';', ',' ), array( '\\\\', '\\n', '\\n', '\\n', '\\;', '\\,' ), (string) $value );
	}

	public static function ics( array $appt, array $meta, $to, $cancelled = false ) {
		$lines = array(
			'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Next Class Services//Booking//EN', 'CALSCALE:GREGORIAN', 'METHOD:' . ( $cancelled ? 'CANCEL' : 'REQUEST' ),
			'BEGIN:VEVENT', 'UID:' . self::text( $meta['uid'] ), 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', strtotime( $appt['start_utc'] . ' UTC' ) ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', strtotime( $appt['end_utc'] . ' UTC' ) ),
			'SEQUENCE:' . ( (int) $meta['sequence'] + ( $cancelled ? 1 : 0 ) ),
			'SUMMARY:' . self::text( $appt['service_title'] ), 'ORGANIZER:mailto:' . sanitize_email( $meta['organizer'] ),
			'ATTENDEE;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:' . sanitize_email( $to ),
			'STATUS:' . ( $cancelled ? 'CANCELLED' : 'CONFIRMED' ),
		);
		if ( ! $cancelled && ! empty( $appt['google_meet_url'] ) ) {
			$lines[] = 'DESCRIPTION:' . self::text( $appt['google_meet_url'] );
			$lines[] = 'LOCATION:' . self::text( $appt['google_meet_url'] );
		}
		$lines[] = 'END:VEVENT'; $lines[] = 'END:VCALENDAR';
		$folded = array();
		foreach ( $lines as $line ) {
			while ( strlen( $line ) > 75 ) {
				$cut = 75;
				while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xc0 ) === 0x80 ) { --$cut; }
				$folded[] = substr( $line, 0, $cut );
				$line = ' ' . substr( $line, $cut );
			}
			$folded[] = $line;
		}
		return implode( "\r\n", $folded ) . "\r\n";
	}
}
