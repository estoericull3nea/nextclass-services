<?php
/**
 * Dashboard page.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Leads;
use NextClassServices\Booking\Appointment;
use NextClassServices\Booking\Booking_Service;
use NextClassServices\Database;
use NextClassServices\Helpers;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summary of plugin activity.
 */
class Dashboard {

	/**
	 * Render.
	 */
	public static function render() {
		global $wpdb;
		Admin::header( 'ncs-dashboard', __( 'Dashboard', 'next-class-services' ) );

		$sub_table   = Database::table( 'submissions' );
		$counts      = Assessment::counts();
		$svc_counts  = Booking_Service::counts();
		$range       = Helpers::date_range( '30d' );
		$events      = Tracker::counts( 'assessment', $range['start'], $range['end'] );
		$starts      = isset( $events['assessment_start'] ) ? $events['assessment_start'] : 0;
		$completions = isset( $events['assessment_complete'] ) ? $events['assessment_complete'] : 0;
		$rate        = $starts > 0 ? min( 100, round( $completions / $starts * 100 ) ) . '%' : __( 'No data yet', 'next-class-services' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_subs  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sub_table}" );
		$total_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sub_table} WHERE email <> ''" );
		// phpcs:enable

		$cards = array(
			array( __( 'Assessments', 'next-class-services' ), (string) ( $counts['publish'] + $counts['draft'] ), sprintf( /* translators: %d: count */ __( '%d published', 'next-class-services' ), $counts['publish'] ) ),
			array( __( 'Assessment submissions', 'next-class-services' ), (string) $total_subs, __( 'All time', 'next-class-services' ) ),
			array( __( 'Assessment leads', 'next-class-services' ), (string) $total_leads, __( 'Submissions with an email', 'next-class-services' ) ),
			array( __( 'Completion rate', 'next-class-services' ), $rate, __( 'Last 30 days, completions per start', 'next-class-services' ) ),
			array( __( 'Booking services', 'next-class-services' ), (string) ( $svc_counts['publish'] + $svc_counts['draft'] ), sprintf( /* translators: %d: count */ __( '%d published', 'next-class-services' ), $svc_counts['publish'] ) ),
			array( __( 'Upcoming appointments', 'next-class-services' ), (string) Appointment::upcoming_count(), __( 'Native bookings, pending or confirmed', 'next-class-services' ) ),
		);
		echo '<div class="ncs-cards">';
		foreach ( $cards as $card ) {
			printf(
				'<div class="ncs-card ncs-stat"><span class="ncs-stat-label">%1$s</span><span class="ncs-stat-value">%2$s</span><span class="ncs-stat-note">%3$s</span></div>',
				esc_html( $card[0] ),
				esc_html( $card[1] ),
				esc_html( $card[2] )
			);
		}
		echo '</div>';

		// Setup checks: only shown when something needs attention.
		$checks = array();
		if ( 0 === $counts['publish'] && current_user_can( 'ncs_manage_assessments' ) ) {
			$checks[] = array( __( 'No assessment is published yet.', 'next-class-services' ), Admin::url( 'ncs-assessments' ), __( 'Open assessments', 'next-class-services' ) );
		}
		if ( 0 === $svc_counts['publish'] && current_user_can( 'ncs_manage_booking_services' ) ) {
			$checks[] = array( __( 'No booking service is published yet.', 'next-class-services' ), Admin::url( 'ncs-booking' ), __( 'Open booking services', 'next-class-services' ) );
		}
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && Helpers::retention_days() > 0 ) {
			$checks[] = array( __( 'WP-Cron is disabled on this site. Data retention cleanup only runs if a server cron job calls wp-cron.php.', 'next-class-services' ), '', '' );
		}
		if ( current_user_can( 'ncs_manage_settings' ) && ! is_email( (string) Helpers::setting( 'default_notification_email', '' ) ) ) {
			$checks[] = array( __( 'No default notification email is set, so the site admin email is used as the fallback recipient.', 'next-class-services' ), Admin::url( 'ncs-settings' ), __( 'Open settings', 'next-class-services' ) );
		}
		if ( ! empty( $checks ) ) {
			echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Setup checks', 'next-class-services' ) . '</h2><ul class="ncs-list ncs-check-list">';
			foreach ( $checks as $check ) {
				echo '<li class="ncs-notice ncs-notice-warning"><strong class="ncs-notice-label">' . esc_html__( 'Attention.', 'next-class-services' ) . '</strong> ' . esc_html( $check[0] );
				if ( $check[1] ) {
					echo ' <a href="' . esc_url( $check[1] ) . '">' . esc_html( $check[2] ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ul></div>';
		}

		echo '<div class="ncs-grid-2">';

		// Recent appointments.
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Recent appointments', 'next-class-services' ) . '</h2>';
		$recent = current_user_can( 'ncs_manage_appointments' ) ? Appointment::recent( 5 ) : array();
		if ( ! current_user_can( 'ncs_manage_appointments' ) ) {
			echo '<p class="ncs-muted">' . esc_html__( 'You do not have access to appointments.', 'next-class-services' ) . '</p>';
		} elseif ( empty( $recent ) ) {
			Admin::empty_state( __( 'No appointments have been made yet.', 'next-class-services' ), Admin::url( 'ncs-booking', array( 'action' => 'new' ) ), __( 'Create booking service', 'next-class-services' ) );
		} else {
			$statuses = Appointment::statuses();
			echo '<table class="ncs-table"><thead><tr><th>' . esc_html__( 'Customer', 'next-class-services' ) . '</th><th>' . esc_html__( 'Service', 'next-class-services' ) . '</th><th>' . esc_html__( 'When', 'next-class-services' ) . '</th><th>' . esc_html__( 'Status', 'next-class-services' ) . '</th></tr></thead><tbody>';
			foreach ( $recent as $a ) {
				printf(
					'<tr><td><a href="%1$s">%2$s</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
					esc_url( Admin::url( 'ncs-appointments', array( 'action' => 'view', 'id' => $a['id'] ) ) ),
					esc_html( $a['customer_name'] ),
					esc_html( $a['service_title'] ),
					esc_html( Helpers::format_utc( $a['start_utc'], '', $a['timezone'] ) ),
					esc_html( isset( $statuses[ $a['status'] ] ) ? $statuses[ $a['status'] ] : $a['status'] )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// Recent submissions.
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Recent assessment submissions', 'next-class-services' ) . '</h2>';
		if ( ! current_user_can( 'ncs_manage_submissions' ) ) {
			echo '<p class="ncs-muted">' . esc_html__( 'You do not have access to assessment submissions.', 'next-class-services' ) . '</p>';
		} else {
			list( $rows ) = Leads::query( array(), 1, 5 );
			if ( empty( $rows ) ) {
				Admin::empty_state( __( 'No assessment submissions yet.', 'next-class-services' ), Admin::url( 'ncs-assessments', array( 'action' => 'new' ) ), __( 'Create assessment', 'next-class-services' ) );
			} else {
				echo '<table class="ncs-table"><thead><tr><th>' . esc_html__( 'Name', 'next-class-services' ) . '</th><th>' . esc_html__( 'Assessment', 'next-class-services' ) . '</th><th>' . esc_html__( 'Result', 'next-class-services' ) . '</th><th>' . esc_html__( 'Date', 'next-class-services' ) . '</th></tr></thead><tbody>';
				foreach ( $rows as $r ) {
					$name = Leads::display_name( $r );
					printf(
						'<tr><td><a href="%1$s">%2$s</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
						esc_url( Admin::url( 'ncs-leads', array( 'action' => 'view', 'id' => $r['id'] ) ) ),
						esc_html( '' !== $name ? $name : ( '' !== $r['email'] ? $r['email'] : __( 'Anonymous', 'next-class-services' ) ) ),
						esc_html( $r['assessment_title'] ),
						esc_html( $r['result_title'] ),
						esc_html( Helpers::format_utc( $r['created_at'] ) )
					);
				}
				echo '</tbody></table>';
			}
		}
		echo '</div>';

		echo '</div>';
		Admin::footer();
	}
}
