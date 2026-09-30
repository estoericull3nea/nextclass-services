<?php
/**
 * First-party analytics event recording.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores lightweight events. No visitor identifiers are stored.
 */
class Tracker {

	/**
	 * Events a browser may report itself.
	 *
	 * @return array event => object type
	 */
	public static function client_events() {
		return array(
			'assessment_view'         => 'assessment',
			'assessment_start'        => 'assessment',
			'result_view'             => 'assessment',
			'booking_view'            => 'booking',
			'booking_start'           => 'booking',
			'booking_external_click'  => 'booking',
			'booking_external_signal' => 'booking',
		);
	}

	/**
	 * Record an event.
	 *
	 * @param string $type        Event type.
	 * @param string $object_type assessment|booking.
	 * @param int    $object_id   Post id.
	 * @param string $meta        Short context value (result id, method).
	 * @return bool
	 */
	public static function record( $type, $object_type, $object_id, $meta = '' ) {
		global $wpdb;
		if ( ! Helpers::setting( 'analytics_enabled' ) ) {
			return false;
		}
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Database::table( 'events' ),
			array(
				'event_type'  => substr( sanitize_key( $type ), 0, 40 ),
				'object_type' => substr( sanitize_key( $object_type ), 0, 20 ),
				'object_id'   => absint( $object_id ),
				'meta'        => Helpers::truncate( sanitize_text_field( (string) $meta ), 250 ),
				'created_at'  => Helpers::now_utc(),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		return (bool) $ok;
	}

	/**
	 * Count events by type in a range.
	 *
	 * @param string $object_type Object type.
	 * @param string $start       UTC start.
	 * @param string $end         UTC end.
	 * @param int    $object_id   Optional object filter.
	 * @return array type => count
	 */
	public static function counts( $object_type, $start, $end, $object_id = 0 ) {
		global $wpdb;
		$table = Database::table( 'events' );
		$sql   = "SELECT event_type, COUNT(*) AS c FROM {$table} WHERE object_type = %s AND created_at BETWEEN %s AND %s";
		$args  = array( $object_type, $start, $end );
		if ( $object_id ) {
			$sql   .= ' AND object_id = %d';
			$args[] = $object_id;
		}
		$sql .= ' GROUP BY event_type';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['event_type'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Counts grouped by object and type.
	 *
	 * @param string $object_type Object type.
	 * @param string $start       UTC start.
	 * @param string $end         UTC end.
	 * @return array object_id => [type => count]
	 */
	public static function counts_by_object( $object_type, $start, $end ) {
		global $wpdb;
		$table = Database::table( 'events' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, event_type, COUNT(*) AS c FROM {$table} WHERE object_type = %s AND created_at BETWEEN %s AND %s GROUP BY object_id, event_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$object_type,
				$start,
				$end
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['object_id'] ][ $row['event_type'] ] = (int) $row['c'];
		}
		return $out;
	}
}
