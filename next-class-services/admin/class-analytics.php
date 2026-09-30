<?php
/**
 * Analytics admin screen.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Booking\Booking_Service;
use NextClassServices\Database;
use NextClassServices\Helpers;
use NextClassServices\Post_Types;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-party analytics report with date filtering and CSV export.
 */
class Analytics {

	const CAP = 'ncs_view_analytics';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_export_analytics', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Selected range from the query string.
	 *
	 * @return array
	 */
	private static function range() {
		$range = sanitize_key( Admin::get( 'range', '30d' ) );
		if ( ! in_array( $range, array( 'today', '7d', '30d', 'quarter', 'custom' ), true ) ) {
			$range = '30d';
		}
		return Helpers::date_range( $range, Admin::get( 'from' ), Admin::get( 'to' ) );
	}

	/**
	 * Percentage helper.
	 *
	 * @param int $num Numerator.
	 * @param int $den Denominator.
	 * @return string
	 */
	private static function pct( $num, $den ) {
		if ( $den <= 0 ) {
			return __( 'No data', 'next-class-services' );
		}
		return min( 100, round( $num / $den * 100, 1 ) ) . '%';
	}

	/**
	 * Post title or a fallback for deleted items.
	 *
	 * @param int $id Post id.
	 * @return string
	 */
	private static function title( $id ) {
		$t = get_the_title( $id );
		/* translators: %d: id */
		return '' !== $t ? $t : sprintf( __( 'Deleted item (%d)', 'next-class-services' ), $id );
	}

	/**
	 * Gather all report data.
	 *
	 * @param array $r Range.
	 * @return array
	 */
	private static function data( array $r ) {
		global $wpdb;
		$sub = Database::table( 'submissions' );

		$a_totals = Tracker::counts( 'assessment', $r['start'], $r['end'] );
		$a_by_obj = Tracker::counts_by_object( 'assessment', $r['start'], $r['end'] );
		$b_totals = Tracker::counts( 'booking', $r['start'], $r['end'] );
		$b_by_obj = Tracker::counts_by_object( 'booking', $r['start'], $r['end'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dist = $wpdb->get_results(
			$wpdb->prepare( "SELECT assessment_title, result_title, COUNT(*) c FROM {$sub} WHERE created_at BETWEEN %s AND %s GROUP BY assessment_title, result_title ORDER BY assessment_title, c DESC", $r['start'], $r['end'] ),
			ARRAY_A
		);
		$prog_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT recommended_programs, COUNT(*) c FROM {$sub} WHERE created_at BETWEEN %s AND %s AND recommended_programs <> '' GROUP BY recommended_programs LIMIT 500", $r['start'], $r['end'] ),
			ARRAY_A
		);
		// phpcs:enable

		$programs = array();
		foreach ( (array) $prog_rows as $row ) {
			$names = json_decode( (string) $row['recommended_programs'], true );
			if ( ! is_array( $names ) ) {
				continue;
			}
			foreach ( $names as $name ) {
				$programs[ $name ] = ( isset( $programs[ $name ] ) ? $programs[ $name ] : 0 ) + (int) $row['c'];
			}
		}
		arsort( $programs );

		// Booking services by method.
		$services = array();
		foreach ( Booking_Service::options() as $sid => $stitle ) {
			$svc = Booking_Service::get( $sid );
			if ( $svc ) {
				$services[ $sid ] = array(
					'title'  => $stitle,
					'method' => $svc['config']['method'],
				);
			}
		}

		$native_starts = 0;
		$native_done   = 0;
		foreach ( $services as $sid => $svc ) {
			if ( 'native' === $svc['method'] && isset( $b_by_obj[ $sid ] ) ) {
				$native_starts += isset( $b_by_obj[ $sid ]['booking_start'] ) ? $b_by_obj[ $sid ]['booking_start'] : 0;
				$native_done   += isset( $b_by_obj[ $sid ]['booking_complete'] ) ? $b_by_obj[ $sid ]['booking_complete'] : 0;
			}
		}

		return compact( 'a_totals', 'a_by_obj', 'b_totals', 'b_by_obj', 'dist', 'programs', 'services', 'native_starts', 'native_done' );
	}

	/**
	 * Read a count.
	 *
	 * @param array  $arr Array.
	 * @param string $key Key.
	 * @return int
	 */
	private static function n( $arr, $key ) {
		return isset( $arr[ $key ] ) ? (int) $arr[ $key ] : 0;
	}

	/**
	 * Render.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		$r    = self::range();
		$d    = self::data( $r );
		$at   = $d['a_totals'];
		$bt   = $d['b_totals'];
		$args = array(
			'action' => 'ncs_export_analytics',
			'range'  => $r['range'],
		);
		if ( 'custom' === $r['range'] ) {
			$args['from'] = $r['from'];
			$args['to']   = $r['to'];
		}
		$export = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'ncs_export_analytics' );

		Admin::header( 'ncs-analytics', __( 'Analytics', 'next-class-services' ), '<a class="ncs-btn" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'next-class-services' ) . '</a>' );

		echo '<form method="get" class="ncs-filters" id="ncs-range-form"><input type="hidden" name="page" value="ncs-analytics">';
		echo '<div class="ncs-filter"><label for="ncs-range">' . esc_html__( 'Date range', 'next-class-services' ) . '</label><select id="ncs-range" name="range">';
		foreach ( array(
			'today'   => __( 'Today', 'next-class-services' ),
			'7d'      => __( 'Last 7 days', 'next-class-services' ),
			'30d'     => __( 'Last 30 days', 'next-class-services' ),
			'quarter' => __( 'This quarter', 'next-class-services' ),
			'custom'  => __( 'Custom range', 'next-class-services' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $r['range'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="ncs-filter" data-custom-range' . ( 'custom' === $r['range'] ? '' : ' hidden' ) . '><label for="ncs-from">' . esc_html__( 'From', 'next-class-services' ) . '</label><input type="date" id="ncs-from" name="from" value="' . esc_attr( $r['from'] ) . '"></div>';
		echo '<div class="ncs-filter" data-custom-range' . ( 'custom' === $r['range'] ? '' : ' hidden' ) . '><label for="ncs-to">' . esc_html__( 'To', 'next-class-services' ) . '</label><input type="date" id="ncs-to" name="to" value="' . esc_attr( $r['to'] ) . '"></div>';
		echo '<div class="ncs-filter ncs-filter-actions"><button class="ncs-btn ncs-btn-primary">' . esc_html__( 'Apply', 'next-class-services' ) . '</button></div></form>';
		/* translators: 1: start date, 2: end date */
		echo '<p class="ncs-help">' . esc_html( sprintf( __( 'Showing %1$s to %2$s (site timezone).', 'next-class-services' ), $r['from'], $r['to'] ) ) . '</p>';

		if ( ! Helpers::setting( 'analytics_enabled' ) ) {
			echo '<div class="ncs-notice ncs-notice-warning"><strong class="ncs-notice-label">' . esc_html__( 'Attention.', 'next-class-services' ) . '</strong> ' . esc_html__( 'First-party analytics are turned off in Settings. New events are not being recorded.', 'next-class-services' ) . '</div>';
		}

		$no_data = empty( $at ) && empty( $bt ) && empty( $d['dist'] );
		if ( $no_data ) {
			Admin::empty_state( __( 'No analytics data has been recorded for this period.', 'next-class-services' ), Admin::url( 'ncs-assessments' ), __( 'View assessments', 'next-class-services' ) );
			Admin::footer();
			return;
		}

		// Assessment cards.
		echo '<h2 class="ncs-section-title">' . esc_html__( 'Assessments', 'next-class-services' ) . '</h2><div class="ncs-cards">';
		$starts = self::n( $at, 'assessment_start' );
		$done   = self::n( $at, 'assessment_complete' );
		self::stat( __( 'Views', 'next-class-services' ), (string) self::n( $at, 'assessment_view' ) );
		self::stat( __( 'Starts', 'next-class-services' ), (string) $starts );
		self::stat( __( 'Completions', 'next-class-services' ), (string) $done );
		self::stat( __( 'Leads captured', 'next-class-services' ), (string) self::n( $at, 'lead_captured' ) );
		self::stat( __( 'Completion rate', 'next-class-services' ), self::pct( $done, $starts ), __( 'Completions per start', 'next-class-services' ) );
		self::stat( __( 'Results viewed', 'next-class-services' ), (string) self::n( $at, 'result_view' ) );
		echo '</div>';

		// Per assessment.
		if ( ! empty( $d['a_by_obj'] ) ) {
			echo '<div class="ncs-card"><h3 class="ncs-card-title">' . esc_html__( 'By assessment', 'next-class-services' ) . '</h3><div class="ncs-table-wrap"><table class="ncs-table"><thead><tr>';
			foreach ( array( __( 'Assessment', 'next-class-services' ), __( 'Views', 'next-class-services' ), __( 'Starts', 'next-class-services' ), __( 'Completions', 'next-class-services' ), __( 'Leads', 'next-class-services' ), __( 'Completion rate', 'next-class-services' ) ) as $h ) {
				echo '<th scope="col">' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $d['a_by_obj'] as $oid => $c ) {
				echo '<tr><td>' . esc_html( self::title( $oid ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'assessment_view' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'assessment_start' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'assessment_complete' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'lead_captured' ) ) . '</td><td>' . esc_html( self::pct( self::n( $c, 'assessment_complete' ), self::n( $c, 'assessment_start' ) ) ) . '</td></tr>';
			}
			echo '</tbody></table></div></div>';
		}

		echo '<div class="ncs-grid-2">';
		// Result distribution.
		echo '<div class="ncs-card"><h3 class="ncs-card-title">' . esc_html__( 'Result distribution', 'next-class-services' ) . '</h3>';
		if ( empty( $d['dist'] ) ) {
			echo '<p class="ncs-muted">' . esc_html__( 'No completed assessments in this period.', 'next-class-services' ) . '</p>';
		} else {
			$total = 0;
			foreach ( $d['dist'] as $row ) {
				$total += (int) $row['c'];
			}
			echo '<table class="ncs-table"><thead><tr><th scope="col">' . esc_html__( 'Assessment', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Result', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Count', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Share', 'next-class-services' ) . '</th></tr></thead><tbody>';
			foreach ( $d['dist'] as $row ) {
				$share = $total ? round( $row['c'] / $total * 100 ) : 0;
				echo '<tr><td>' . esc_html( $row['assessment_title'] ) . '</td><td>' . esc_html( '' !== $row['result_title'] ? $row['result_title'] : __( 'No result category', 'next-class-services' ) ) . '</td><td>' . esc_html( $row['c'] ) . '</td><td><span class="ncs-bar" role="img" aria-label="' . esc_attr( $share . '%' ) . '"><span style="width:' . esc_attr( $share ) . '%"></span></span> ' . esc_html( $share . '%' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '<div class="ncs-card"><h3 class="ncs-card-title">' . esc_html__( 'Recommended programs', 'next-class-services' ) . '</h3>';
		if ( empty( $d['programs'] ) ) {
			echo '<p class="ncs-muted">' . esc_html__( 'No program recommendations in this period.', 'next-class-services' ) . '</p>';
		} else {
			echo '<table class="ncs-table"><thead><tr><th scope="col">' . esc_html__( 'Program', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Times recommended', 'next-class-services' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $d['programs'], 0, 20, true ) as $name => $count ) {
				echo '<tr><td>' . esc_html( $name ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';

		// Booking.
		echo '<h2 class="ncs-section-title">' . esc_html__( 'Online booking', 'next-class-services' ) . '</h2><div class="ncs-cards">';
		self::stat( __( 'Service views', 'next-class-services' ), (string) self::n( $bt, 'booking_view' ) );
		self::stat( __( 'Booking starts', 'next-class-services' ), (string) self::n( $bt, 'booking_start' ), __( 'Native flow only', 'next-class-services' ) );
		self::stat( __( 'External booking clicks', 'next-class-services' ), (string) self::n( $bt, 'booking_external_click' ), __( 'Interactions with Calendly, TidyCal or custom pages', 'next-class-services' ) );
		self::stat( __( 'Native bookings completed', 'next-class-services' ), (string) self::n( $bt, 'booking_complete' ), __( 'Confirmed by this plugin', 'next-class-services' ) );
		self::stat( __( 'Cancellations', 'next-class-services' ), (string) self::n( $bt, 'booking_cancel' ) );
		self::stat( __( 'Reschedules', 'next-class-services' ), (string) self::n( $bt, 'booking_reschedule' ) );
		self::stat( __( 'Native conversion rate', 'next-class-services' ), self::pct( $d['native_done'], $d['native_starts'] ), __( 'Completed per start', 'next-class-services' ) );
		self::stat( __( 'Scheduling signals', 'next-class-services' ), (string) self::n( $bt, 'booking_external_signal' ), __( 'Reported by the provider in the visitor browser. Not verified, not counted as bookings.', 'next-class-services' ) );
		echo '</div>';
		echo '<p class="ncs-help">' . esc_html__( 'External bookings on Calendly or TidyCal are never counted as completed appointments because those providers do not send this site a reliable confirmation.', 'next-class-services' ) . '</p>';

		if ( ! empty( $d['b_by_obj'] ) ) {
			$methods = Booking_Service::methods();
			echo '<div class="ncs-card"><h3 class="ncs-card-title">' . esc_html__( 'By booking service', 'next-class-services' ) . '</h3><div class="ncs-table-wrap"><table class="ncs-table"><thead><tr>';
			foreach ( array( __( 'Service', 'next-class-services' ), __( 'Method', 'next-class-services' ), __( 'Views', 'next-class-services' ), __( 'Starts', 'next-class-services' ), __( 'External clicks', 'next-class-services' ), __( 'Completed (native)', 'next-class-services' ), __( 'Cancellations', 'next-class-services' ), __( 'Reschedules', 'next-class-services' ), __( 'Conversion', 'next-class-services' ) ) as $h ) {
				echo '<th scope="col">' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $d['b_by_obj'] as $oid => $c ) {
				$method = isset( $d['services'][ $oid ] ) ? $d['services'][ $oid ]['method'] : '';
				$native = 'native' === $method;
				echo '<tr><td>' . esc_html( self::title( $oid ) ) . '</td><td>' . esc_html( isset( $methods[ $method ] ) ? $methods[ $method ] : '' ) . '</td>';
				echo '<td>' . esc_html( (string) self::n( $c, 'booking_view' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'booking_start' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'booking_external_click' ) ) . '</td>';
				echo '<td>' . esc_html( $native ? (string) self::n( $c, 'booking_complete' ) : '-' ) . '</td><td>' . esc_html( (string) self::n( $c, 'booking_cancel' ) ) . '</td><td>' . esc_html( (string) self::n( $c, 'booking_reschedule' ) ) . '</td>';
				echo '<td>' . esc_html( $native ? self::pct( self::n( $c, 'booking_complete' ), self::n( $c, 'booking_start' ) ) : __( 'Not available', 'next-class-services' ) ) . '</td></tr>';
			}
			echo '</tbody></table></div></div>';
		}
		Admin::footer();
	}

	/**
	 * Print a stat card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $note  Note.
	 */
	private static function stat( $label, $value, $note = '' ) {
		echo '<div class="ncs-card ncs-stat"><span class="ncs-stat-label">' . esc_html( $label ) . '</span><span class="ncs-stat-value">' . esc_html( $value ) . '</span>';
		if ( '' !== $note ) {
			echo '<span class="ncs-stat-note">' . esc_html( $note ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * CSV export.
	 */
	public static function handle_export() {
		Admin::authorize( self::CAP, 'ncs_export_analytics' );
		$r   = self::range();
		$d   = self::data( $r );
		$rows = array();
		$labels = array(
			'assessment_view'         => 'Assessment views',
			'assessment_start'        => 'Assessment starts',
			'assessment_complete'     => 'Assessment completions',
			'lead_captured'           => 'Leads captured',
			'result_view'             => 'Results viewed',
			'booking_view'            => 'Booking service views',
			'booking_start'           => 'Booking starts',
			'booking_external_click'  => 'External booking clicks',
			'booking_external_signal' => 'External scheduling signals (unverified)',
			'booking_complete'        => 'Native bookings completed',
			'booking_cancel'          => 'Cancellations',
			'booking_reschedule'      => 'Reschedules',
		);
		foreach ( $d['a_by_obj'] as $oid => $c ) {
			foreach ( $c as $type => $count ) {
				$rows[] = array( $r['from'] . ' to ' . $r['to'], 'Assessment', self::title( $oid ), isset( $labels[ $type ] ) ? $labels[ $type ] : $type, $count );
			}
		}
		foreach ( $d['b_by_obj'] as $oid => $c ) {
			foreach ( $c as $type => $count ) {
				$rows[] = array( $r['from'] . ' to ' . $r['to'], 'Booking service', self::title( $oid ), isset( $labels[ $type ] ) ? $labels[ $type ] : $type, $count );
			}
		}
		foreach ( $d['dist'] as $row ) {
			$rows[] = array( $r['from'] . ' to ' . $r['to'], 'Result distribution', $row['assessment_title'], $row['result_title'], $row['c'] );
		}
		foreach ( $d['programs'] as $name => $count ) {
			$rows[] = array( $r['from'] . ' to ' . $r['to'], 'Recommended program', $name, 'Times recommended', $count );
		}
		Helpers::send_csv( 'analytics', array( 'Period', 'Section', 'Item', 'Metric', 'Value' ), $rows );
	}
}
