<?php
/**
 * Appointments admin.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Booking\Appointment;
use NextClassServices\Booking\Booking_Email;
use NextClassServices\Booking\Booking_Service;
use NextClassServices\Helpers;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appointment list, detail, status management and export.
 */
class Appointments {

	const CAP = 'ncs_manage_appointments';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_update_appointment', array( __CLASS__, 'handle_update' ) );
		add_action( 'admin_post_ncs_delete_appointment', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_ncs_export_appointments', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Filters from the query string.
	 *
	 * @return array
	 */
	private static function filters() {
		return array(
			'service_id' => absint( Admin::get( 'service_id' ) ),
			'status'     => sanitize_key( Admin::get( 'status' ) ),
			'from'       => Admin::get( 'from' ),
			'to'         => Admin::get( 'to' ),
			'search'     => Admin::get( 's' ),
		);
	}

	/**
	 * Router.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		if ( 'view' === Admin::get( 'action' ) && absint( Admin::get( 'id' ) ) ) {
			self::render_detail( absint( Admin::get( 'id' ) ) );
			return;
		}
		self::render_list();
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_badge( $status ) {
		$labels = Appointment::statuses();
		$tones  = array(
			'pending'   => 'warn',
			'confirmed' => 'ok',
			'completed' => 'ok',
			'cancelled' => 'bad',
			'no_show'   => 'bad',
		);
		return Admin::badge( isset( $labels[ $status ] ) ? $labels[ $status ] : $status, isset( $tones[ $status ] ) ? $tones[ $status ] : 'muted' );
	}

	/**
	 * List.
	 */
	private static function render_list() {
		$filters = self::filters();
		$paged   = max( 1, absint( Admin::get( 'paged', '1' ) ) );
		$per     = 20;
		$filters['order'] = 'asc' === Admin::get( 'order' ) ? 'asc' : 'desc';
		list( $rows, $total ) = Appointment::query( $filters, $paged, $per );
		$active_filters       = array_filter( array_diff_key( $filters, array( 'order' => 1 ) ) );

		$export_url = wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => 'ncs_export_appointments' ), $active_filters ), admin_url( 'admin-post.php' ) ),
			'ncs_export_appointments'
		);
		Admin::header(
			'ncs-appointments',
			__( 'Appointments', 'next-class-services' ),
			$total ? '<a class="ncs-btn" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'next-class-services' ) . '</a>' : ''
		);

		echo '<p class="ncs-help">' . esc_html__( 'This list shows native WordPress bookings. Appointments made through Calendly or TidyCal are managed in those tools.', 'next-class-services' ) . '</p>';

		$glance = Appointment::glance();
		echo '<div class="ncs-cards">';
		foreach ( array(
			array( __( 'Upcoming', 'next-class-services' ), $glance['upcoming'], __( 'Pending or confirmed', 'next-class-services' ) ),
			array( __( 'Today', 'next-class-services' ), $glance['today'], __( 'Active appointments today', 'next-class-services' ) ),
			array( __( 'Pending', 'next-class-services' ), $glance['pending'], __( 'Waiting for confirmation', 'next-class-services' ) ),
			array( __( 'Cancelled or no show', 'next-class-services' ), $glance['lost'], __( 'Last 30 days', 'next-class-services' ) ),
		) as $card ) {
			echo '<div class="ncs-card ncs-stat"><span class="ncs-stat-label">' . esc_html( $card[0] ) . '</span><span class="ncs-stat-value">' . esc_html( (string) $card[1] ) . '</span><span class="ncs-stat-note">' . esc_html( $card[2] ) . '</span></div>';
		}
		echo '</div>';

		echo '<form method="get" class="ncs-filters"><input type="hidden" name="page" value="ncs-appointments">';
		echo '<div class="ncs-filter"><label for="ncs-f-svc">' . esc_html__( 'Service', 'next-class-services' ) . '</label><select id="ncs-f-svc" name="service_id"><option value="">' . esc_html__( 'All services', 'next-class-services' ) . '</option>';
		foreach ( Booking_Service::options() as $sid => $stitle ) {
			echo '<option value="' . esc_attr( $sid ) . '"' . selected( $filters['service_id'], $sid, false ) . '>' . esc_html( $stitle ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-st">' . esc_html__( 'Status', 'next-class-services' ) . '</label><select id="ncs-f-st" name="status"><option value="">' . esc_html__( 'All statuses', 'next-class-services' ) . '</option>';
		foreach ( Appointment::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $filters['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-from">' . esc_html__( 'From date', 'next-class-services' ) . '</label><input type="date" id="ncs-f-from" name="from" value="' . esc_attr( $filters['from'] ) . '"></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-to">' . esc_html__( 'To date', 'next-class-services' ) . '</label><input type="date" id="ncs-f-to" name="to" value="' . esc_attr( $filters['to'] ) . '"></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-s">' . esc_html__( 'Search', 'next-class-services' ) . '</label><input type="search" id="ncs-f-s" name="s" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Name, email, company', 'next-class-services' ) . '"></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-o">' . esc_html__( 'Order', 'next-class-services' ) . '</label><select id="ncs-f-o" name="order"><option value="desc"' . selected( $filters['order'], 'desc', false ) . '>' . esc_html__( 'Latest first', 'next-class-services' ) . '</option><option value="asc"' . selected( $filters['order'], 'asc', false ) . '>' . esc_html__( 'Earliest first', 'next-class-services' ) . '</option></select></div>';
		echo '<div class="ncs-filter ncs-filter-actions"><button class="ncs-btn ncs-btn-primary">' . esc_html__( 'Filter', 'next-class-services' ) . '</button>';
		if ( $active_filters ) {
			echo ' <a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-appointments' ) ) . '">' . esc_html__( 'Reset', 'next-class-services' ) . '</a>';
		}
		echo '</div></form>';

		if ( empty( $rows ) ) {
			if ( $active_filters ) {
				Admin::empty_state( __( 'No appointments match these filters.', 'next-class-services' ) );
			} else {
				Admin::empty_state( __( 'No appointments have been made yet.', 'next-class-services' ), Admin::url( 'ncs-booking' ), __( 'View booking services', 'next-class-services' ) );
			}
			Admin::footer();
			return;
		}

		echo '<div class="ncs-table-wrap"><table class="ncs-table"><thead><tr>';
		foreach ( array( __( 'Customer', 'next-class-services' ), __( 'Service', 'next-class-services' ), __( 'Date and time', 'next-class-services' ), __( 'Status', 'next-class-services' ), __( 'Action', 'next-class-services' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$date_fmt = get_option( 'date_format' );
		$time_fmt = get_option( 'time_format' );
		foreach ( $rows as $a ) {
			$view = Admin::url( 'ncs-appointments', array( 'action' => 'view', 'id' => $a['id'] ) );
			echo '<tr>';
			echo '<td><span class="ncs-row-title">' . esc_html( $a['customer_name'] ) . '</span><br><span class="ncs-muted">' . esc_html( $a['customer_email'] ) . '</span></td>';
			echo '<td>' . esc_html( $a['service_title'] ) . '</td>';
			echo '<td>' . esc_html( Helpers::format_utc( $a['start_utc'], $date_fmt, $a['timezone'] ) ) . '<br><span class="ncs-muted">' . esc_html( Helpers::format_utc( $a['start_utc'], $time_fmt, $a['timezone'] ) . ' (' . $a['timezone'] . ')' ) . '</span></td>';
			echo '<td>' . self::status_badge( $a['status'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td><a class="ncs-btn ncs-btn-small" href="' . esc_url( $view ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: customer name */ __( 'View appointment for %s', 'next-class-services' ), $a['customer_name'] ) ) . '">' . esc_html__( 'View', 'next-class-services' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		Admin::pagination( $total, $per, $paged, array_merge( array( 'page' => 'ncs-appointments', 'order' => $filters['order'] ), $active_filters ) );
		Admin::footer();
	}

	/**
	 * Detail.
	 *
	 * @param int $id Appointment id.
	 */
	private static function render_detail( $id ) {
		$a = Appointment::get( $id );
		if ( ! $a ) {
			Admin::flash( 'error', __( 'That appointment could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-appointments' ) );
		}
		$delete = wp_nonce_url( add_query_arg( array( 'action' => 'ncs_delete_appointment', 'id' => $id ), admin_url( 'admin-post.php' ) ), 'ncs_delete_appointment_' . $id );
		Admin::header(
			'ncs-appointments',
			$a['customer_name'],
			'<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-appointments' ) ) . '">' . esc_html__( 'All appointments', 'next-class-services' ) . '</a> <a class="ncs-btn ncs-btn-danger" data-ncs-confirm="' . esc_attr__( 'Permanently delete this appointment record?', 'next-class-services' ) . '" href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete', 'next-class-services' ) . '</a>'
		);

		$custom = json_decode( (string) $a['custom_fields'], true );
		$rows   = array(
			__( 'Service', 'next-class-services' )        => $a['service_title'],
			__( 'Date', 'next-class-services' )           => Helpers::format_utc( $a['start_utc'], get_option( 'date_format' ), $a['timezone'] ),
			__( 'Time', 'next-class-services' )           => Helpers::format_utc( $a['start_utc'], get_option( 'time_format' ), $a['timezone'] ) . ' - ' . Helpers::format_utc( $a['end_utc'], get_option( 'time_format' ), $a['timezone'] ),
			__( 'Timezone', 'next-class-services' )       => Appointment::tz_label( $a['timezone'], $a['start_utc'] ),
			__( 'Customer', 'next-class-services' )       => $a['customer_name'],
			__( 'Email', 'next-class-services' )          => $a['customer_email'],
			__( 'Phone', 'next-class-services' )          => $a['customer_phone'],
			__( 'Company', 'next-class-services' )        => $a['company'],
			__( 'Job title', 'next-class-services' )      => $a['job_title'],
			__( 'Notes', 'next-class-services' )          => (string) $a['notes'],
			__( 'Created', 'next-class-services' )        => Helpers::format_utc( $a['created_at'] ),
			__( 'Status changed', 'next-class-services' ) => Helpers::format_utc( $a['status_changed_at'] ),
			__( 'Times rescheduled', 'next-class-services' ) => (string) $a['reschedule_count'],
			__( 'Booked from page', 'next-class-services' ) => $a['page_url'],
		);
		if ( is_array( $custom ) ) {
			foreach ( $custom as $c ) {
				$rows[ (string) $c['label'] ] = (string) $c['value'];
			}
		}

		echo '<div class="ncs-grid-2"><div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Appointment', 'next-class-services' ) . '</h2><dl class="ncs-dl">';
		echo '<dt>' . esc_html__( 'Status', 'next-class-services' ) . '</dt><dd>' . self::status_badge( $a['status'] ) . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( (string) $value ) ) . '</dd>';
		}
		echo '</dl></div>';

		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Change status', 'next-class-services' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ncs-form">';
		echo '<input type="hidden" name="action" value="ncs_update_appointment"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'ncs_update_appointment_' . $id, '_ncs_nonce' );
		Form::select( 'status', __( 'Status', 'next-class-services' ), Appointment::statuses(), $a['status'], array( 'help' => __( 'Pending and confirmed bookings hold their time slot. Other statuses release it.', 'next-class-services' ) ) );
		Form::checkbox( 'notify_customer', __( 'Email the customer if the booking is cancelled', 'next-class-services' ), true );
		echo '<p><button class="ncs-btn ncs-btn-primary" type="submit">' . esc_html__( 'Update status', 'next-class-services' ) . '</button></p></form></div></div>';
		Admin::footer();
	}

	/**
	 * Update status.
	 */
	public static function handle_update() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_update_appointment_' . $id, '_ncs_nonce' );
		$back   = Admin::url( 'ncs-appointments', array( 'action' => 'view', 'id' => $id ) );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$appt   = Appointment::get( $id );
		if ( ! $appt || ! isset( Appointment::statuses()[ $status ] ) ) {
			Admin::flash( 'error', __( 'Invalid appointment or status.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		if ( $status === $appt['status'] ) {
			Admin::flash( 'info', __( 'The status was not changed.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		$result = Appointment::set_status( $appt, $status );
		if ( true !== $result ) {
			Admin::flash( 'error', __( 'That status cannot be applied because another active booking now uses this time.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		if ( 'cancelled' === $status ) {
			Tracker::record( 'booking_cancel', 'booking', $appt['service_id'], 'admin' );
			if ( ! empty( $_POST['notify_customer'] ) ) {
				$svc = Booking_Service::get( $appt['service_id'] );
				if ( $svc ) {
					Booking_Email::send_cancellation( Appointment::get( $id ), $svc['config'] );
				}
			}
		}
		Admin::flash( 'success', __( 'Appointment status updated.', 'next-class-services' ) );
		Admin::redirect( $back );
	}

	/**
	 * Delete.
	 */
	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_delete_appointment_' . $id );
		Appointment::delete( $id );
		Admin::flash( 'success', __( 'Appointment deleted.', 'next-class-services' ) );
		Admin::redirect( Admin::url( 'ncs-appointments' ) );
	}

	/**
	 * CSV export.
	 */
	public static function handle_export() {
		Admin::authorize( self::CAP, 'ncs_export_appointments' );
		$filters = self::filters();
		list( $rows ) = Appointment::query( $filters, 1, 0 );
		$statuses     = Appointment::statuses();
		$headers      = array( 'ID', 'Customer', 'Email', 'Phone', 'Company', 'Job title', 'Service', 'Date', 'Start time', 'End time', 'Timezone', 'Status', 'Status changed (UTC)', 'Notes', 'Custom fields', 'Times rescheduled', 'Created (UTC)' );
		$out          = array();
		foreach ( $rows as $a ) {
			$custom   = json_decode( (string) $a['custom_fields'], true );
			$custom_t = array();
			if ( is_array( $custom ) ) {
				foreach ( $custom as $c ) {
					$custom_t[] = $c['label'] . ': ' . $c['value'];
				}
			}
			$out[] = array(
				$a['id'],
				$a['customer_name'],
				$a['customer_email'],
				$a['customer_phone'],
				$a['company'],
				$a['job_title'],
				$a['service_title'],
				$a['local_date'],
				Helpers::format_utc( $a['start_utc'], 'H:i', $a['timezone'] ),
				Helpers::format_utc( $a['end_utc'], 'H:i', $a['timezone'] ),
				$a['timezone'],
				isset( $statuses[ $a['status'] ] ) ? $statuses[ $a['status'] ] : $a['status'],
				(string) $a['status_changed_at'],
				(string) $a['notes'],
				implode( ' | ', $custom_t ),
				$a['reschedule_count'],
				$a['created_at'],
			);
		}
		Helpers::send_csv( 'appointments', $headers, $out );
	}
}
