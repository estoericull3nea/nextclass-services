<?php
/**
 * WordPress privacy tools integration.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

use NextClassServices\Assessments\Leads;
use NextClassServices\Booking\Appointment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Personal data exporters, erasers and suggested policy text.
 */
class Privacy {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_content' ) );
	}

	/**
	 * Register exporters.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporters( $exporters ) {
		$exporters['next-class-assessments'] = array(
			'exporter_friendly_name' => __( 'Next Class Services assessment submissions', 'next-class-services' ),
			'callback'               => array( __CLASS__, 'export_assessments' ),
		);
		$exporters['next-class-appointments'] = array(
			'exporter_friendly_name' => __( 'Next Class Services appointments', 'next-class-services' ),
			'callback'               => array( __CLASS__, 'export_appointments' ),
		);
		return $exporters;
	}

	/**
	 * Register erasers.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_erasers( $erasers ) {
		$erasers['next-class-assessments'] = array(
			'eraser_friendly_name' => __( 'Next Class Services assessment submissions', 'next-class-services' ),
			'callback'             => array( __CLASS__, 'erase_assessments' ),
		);
		$erasers['next-class-appointments'] = array(
			'eraser_friendly_name' => __( 'Next Class Services appointments', 'next-class-services' ),
			'callback'             => array( __CLASS__, 'erase_appointments' ),
		);
		return $erasers;
	}

	/**
	 * Export submissions for an email address.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export_assessments( $email ) {
		global $wpdb;
		$table = Database::table( 'submissions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT 500", $email ), ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $r ) {
			$data = array(
				array( 'name' => __( 'Assessment', 'next-class-services' ), 'value' => $r['assessment_title'] ),
				array( 'name' => __( 'Name', 'next-class-services' ), 'value' => Leads::display_name( $r ) ),
				array( 'name' => __( 'Email', 'next-class-services' ), 'value' => $r['email'] ),
				array( 'name' => __( 'Phone', 'next-class-services' ), 'value' => $r['phone'] ),
				array( 'name' => __( 'Company', 'next-class-services' ), 'value' => $r['company'] ),
				array( 'name' => __( 'Job title', 'next-class-services' ), 'value' => $r['job_title'] ),
				array( 'name' => __( 'Score', 'next-class-services' ), 'value' => $r['raw_score'] . ' / ' . $r['max_score'] . ' (' . $r['percentage'] . '%)' ),
				array( 'name' => __( 'Result', 'next-class-services' ), 'value' => $r['result_title'] ),
				array( 'name' => __( 'Privacy consent', 'next-class-services' ), 'value' => $r['consent_privacy'] ? __( 'Yes', 'next-class-services' ) : __( 'No', 'next-class-services' ) ),
				array( 'name' => __( 'Marketing consent', 'next-class-services' ), 'value' => $r['consent_marketing'] ? __( 'Yes', 'next-class-services' ) : __( 'No', 'next-class-services' ) ),
				array( 'name' => __( 'Submitted', 'next-class-services' ), 'value' => $r['created_at'] . ' UTC' ),
			);
			foreach ( Leads::answers( $r['id'] ) as $a ) {
				$data[] = array(
					'name'  => $a['question_text'],
					'value' => $a['answer_label'],
				);
			}
			$items[] = array(
				'group_id'    => 'ncs-assessment-submissions',
				'group_label' => __( 'Assessment submissions', 'next-class-services' ),
				'item_id'     => 'ncs-submission-' . $r['id'],
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase submissions for an email address.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase_assessments( $email ) {
		global $wpdb;
		$table = Database::table( 'submissions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );
		$removed = 0;
		foreach ( (array) $ids as $id ) {
			if ( Leads::delete( $id ) ) {
				$removed++;
			}
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Export appointments.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export_appointments( $email ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE customer_email = %s ORDER BY id ASC LIMIT 500", $email ), ARRAY_A );
		$statuses = Appointment::statuses();
		$items    = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'group_id'    => 'ncs-appointments',
				'group_label' => __( 'Appointments', 'next-class-services' ),
				'item_id'     => 'ncs-appointment-' . $r['id'],
				'data'        => array(
					array( 'name' => __( 'Service', 'next-class-services' ), 'value' => $r['service_title'] ),
					array( 'name' => __( 'Name', 'next-class-services' ), 'value' => $r['customer_name'] ),
					array( 'name' => __( 'Email', 'next-class-services' ), 'value' => $r['customer_email'] ),
					array( 'name' => __( 'Phone', 'next-class-services' ), 'value' => $r['customer_phone'] ),
					array( 'name' => __( 'Company', 'next-class-services' ), 'value' => $r['company'] ),
					array( 'name' => __( 'Notes', 'next-class-services' ), 'value' => (string) $r['notes'] ),
					array( 'name' => __( 'Appointment time (UTC)', 'next-class-services' ), 'value' => $r['start_utc'] ),
					array( 'name' => __( 'Timezone', 'next-class-services' ), 'value' => $r['timezone'] ),
					array( 'name' => __( 'Status', 'next-class-services' ), 'value' => isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'] ),
					array( 'name' => __( 'Google Calendar ID', 'next-class-services' ), 'value' => isset( $r['google_calendar_id'] ) ? $r['google_calendar_id'] : '' ),
					array( 'name' => __( 'Google event ID', 'next-class-services' ), 'value' => isset( $r['google_event_id'] ) ? $r['google_event_id'] : '' ),
					array( 'name' => __( 'Google Meet link', 'next-class-services' ), 'value' => isset( $r['google_meet_url'] ) ? $r['google_meet_url'] : '' ),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase appointments. Future active bookings are retained.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase_appointments( $email ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT id, status, end_utc FROM {$table} WHERE customer_email = %s", $email ), ARRAY_A );
		$removed  = 0;
		$retained = false;
		$now      = Helpers::now_utc();
		foreach ( (array) $rows as $r ) {
			if ( Appointment::is_active( $r['status'] ) && $r['end_utc'] > $now ) {
				$retained = true;
				continue;
			}
			if ( Appointment::delete( $r['id'] ) ) {
				$removed++;
			} else {
				$retained = true;
			}
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Some appointments were kept because they are upcoming and active, or Google Calendar cleanup is pending. Cancel upcoming bookings or resolve calendar sync, then run the eraser again.', 'next-class-services' ) ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<h3>' . esc_html__( 'Assessments and bookings (Next Class Services)', 'next-class-services' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'When you complete a diagnostic assessment we store your answers, your calculated score and result, and the contact details you enter in the lead form (which may include name, email, phone, company, job title and company size). We also store whether you agreed to the privacy and marketing consent statements and when. We do not store your IP address with your submission.', 'next-class-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'When you book an appointment using the built-in scheduler we store your name, email, phone, company, job title and notes if provided, the chosen time and timezone, and the booking status. If you book with an external provider such as Calendly or TidyCal, that provider handles your data under its own privacy policy.', 'next-class-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If Google Calendar sync is enabled for a booking service, we send the service title, booking times, and your name and email to Google to create a calendar event, Google Meet link and attendee invitation. Rescheduling and cancellation send updates to attendees. Notes, phone numbers and custom answers are not sent by this integration. Calendar event identifiers and Meet links are stored with the appointment. Google processes calendar and conferencing data under its own policies.', 'next-class-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'We record anonymous interaction counts (for example views, starts and completions) without cookies, fingerprinting or visitor identifiers. If Google Analytics 4 is already installed on this site and the option is enabled, matching events are also sent to it.', 'next-class-services' ) . '</p>';
		wp_add_privacy_policy_content( 'Next Class Services', wp_kses_post( $content ) );
	}
}
