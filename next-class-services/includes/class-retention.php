<?php
/**
 * Data retention cleanup.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes old personal data according to the configured retention period.
 */
class Retention {

	const HOOK = 'ncs_daily_cleanup';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedule daily cleanup.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the scheduled event.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Run cleanup.
	 *
	 * @return array counts of removed rows
	 */
	public static function run() {
		global $wpdb;
		$days = Helpers::retention_days();
		if ( $days < 1 ) {
			return array(
				'submissions'  => 0,
				'appointments' => 0,
			);
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$sub    = Database::table( 'submissions' );
		$ans    = Database::table( 'answers' );
		$apt    = Database::table( 'appointments' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE a FROM {$ans} a INNER JOIN {$sub} s ON s.id = a.submission_id WHERE s.created_at < %s", $cutoff ) );
		$submissions = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$sub} WHERE created_at < %s", $cutoff ) );

		// Only past appointments are removed. Future active bookings are never touched.
		$appointments = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$apt} WHERE created_at < %s AND end_utc < %s AND (google_sync_error IS NULL OR google_sync_error = '') AND NOT (status = 'cancelled' AND google_event_id <> '')",
				$cutoff,
				Helpers::now_utc()
			)
		);
		// phpcs:enable

		return array(
			'submissions'  => $submissions,
			'appointments' => $appointments,
		);
	}
}
