<?php
/**
 * Availability calculation for native bookings.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Database;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates bookable slots from working hours, blocked dates, notice rules,
 * buffers and existing active appointments. All comparisons use UTC timestamps.
 */
class Availability {

	/**
	 * Timezone used by a service.
	 *
	 * @param array $cfg Service config.
	 * @return \DateTimeZone
	 */
	public static function tz( array $cfg ) {
		return Helpers::timezone( isset( $cfg['native']['timezone'] ) ? $cfg['native']['timezone'] : '' );
	}

	/**
	 * Is the date blocked by the blocked list.
	 *
	 * @param array  $cfg  Config.
	 * @param string $date Y-m-d.
	 * @return bool
	 */
	public static function is_blocked( array $cfg, $date ) {
		list( $list ) = Booking_Service::parse_blocked( $cfg['native']['blocked_dates'] );
		foreach ( $list as $b ) {
			if ( $date >= $b['from'] && $date <= $b['to'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Load active appointments overlapping a time window.
	 *
	 * @param array $cfg        Config.
	 * @param int   $service_id Service id.
	 * @param int   $from_ts    Window start.
	 * @param int   $to_ts      Window end.
	 * @param int   $exclude    Appointment id to ignore.
	 * @return array
	 */
	public static function busy( array $cfg, $service_id, $from_ts, $to_ts, $exclude = 0 ) {
		global $wpdb;
		unset( $cfg );
		$table = Database::table( 'appointments' );
		$sql   = "SELECT id, service_id, local_date, block_start_utc, block_end_utc FROM {$table} WHERE status IN ('pending','confirmed') AND block_end_utc > %s AND block_start_utc < %s AND id <> %d";
		$args  = array( gmdate( 'Y-m-d H:i:s', $from_ts ), gmdate( 'Y-m-d H:i:s', $to_ts ), (int) $exclude );
		if ( ! Helpers::setting( 'shared_calendar' ) ) {
			$sql   .= ' AND service_id = %d';
			$args[] = (int) $service_id;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'bs'         => strtotime( $r['block_start_utc'] . ' UTC' ),
				'be'         => strtotime( $r['block_end_utc'] . ' UTC' ),
				'service_id' => (int) $r['service_id'],
				'date'       => $r['local_date'],
			);
		}
		return $out;
	}

	/**
	 * Compute slots for one date against a preloaded busy list.
	 *
	 * @param array  $cfg        Service config.
	 * @param int    $service_id Service id.
	 * @param string $date       Y-m-d in the service timezone.
	 * @param array  $busy       Busy windows.
	 * @param int    $now        Current timestamp.
	 * @return array
	 */
	public static function day_slots( array $cfg, $service_id, $date, array $busy, $now ) {
		$n  = $cfg['native'];
		$tz = self::tz( $cfg );
		$d  = Helpers::parse_local_date( $date, $tz );
		if ( ! $d ) {
			return array();
		}
		if ( ! in_array( (int) $d->format( 'N' ), array_map( 'intval', $n['days'] ), true ) ) {
			return array();
		}
		if ( self::is_blocked( $cfg, $date ) ) {
			return array();
		}
		if ( $n['max_per_day'] > 0 ) {
			$count = 0;
			foreach ( $busy as $b ) {
				if ( $b['date'] === $date && $b['service_id'] === (int) $service_id ) {
					$count++;
				}
			}
			if ( $count >= $n['max_per_day'] ) {
				return array();
			}
		}

		list( $sh, $sm ) = array_map( 'intval', explode( ':', $n['start'] ) );
		list( $eh, $em ) = array_map( 'intval', explode( ':', $n['end'] ) );
		$start_min       = $sh * 60 + $sm;
		$end_min         = $eh * 60 + $em;
		$duration        = (int) $cfg['duration'];
		$step            = $n['slot_step'] > 0 ? (int) $n['slot_step'] : $duration;
		$earliest        = $now + ( (int) $n['min_notice_hours'] * HOUR_IN_SECONDS );
		$latest          = $now + ( (int) $n['max_advance_days'] * DAY_IN_SECONDS );
		$time_format     = get_option( 'time_format' );

		$slots = array();
		for ( $m = $start_min; $m + $duration <= $end_min; $m += $step ) {
			$start = $d->setTime( intdiv( $m, 60 ), $m % 60 );
			$ts    = $start->getTimestamp();
			if ( $ts < $earliest || $ts > $latest ) {
				continue;
			}
			$end_ts = $ts + ( $duration * MINUTE_IN_SECONDS );
			$bs     = $ts - ( (int) $n['buffer_before'] * MINUTE_IN_SECONDS );
			$be     = $end_ts + ( (int) $n['buffer_after'] * MINUTE_IN_SECONDS );

			$clash = false;
			foreach ( $busy as $b ) {
				if ( $bs < $b['be'] && $be > $b['bs'] ) {
					$clash = true;
					break;
				}
			}
			if ( $clash ) {
				continue;
			}
			$slots[] = array(
				'time'            => sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 ),
				'label'           => wp_date( $time_format, $ts, $tz ),
				'start_ts'        => $ts,
				'end_ts'          => $end_ts,
				'start_utc'       => gmdate( 'Y-m-d H:i:s', $ts ),
				'end_utc'         => gmdate( 'Y-m-d H:i:s', $end_ts ),
				'block_start_utc' => gmdate( 'Y-m-d H:i:s', $bs ),
				'block_end_utc'   => gmdate( 'Y-m-d H:i:s', $be ),
				'local_date'      => $date,
			);
		}
		return $slots;
	}

	/**
	 * Slots for a date (loads busy list itself).
	 *
	 * @param array  $cfg        Config.
	 * @param int    $service_id Service id.
	 * @param string $date       Y-m-d.
	 * @param int    $exclude    Appointment id to ignore (rescheduling).
	 * @return array
	 */
	public static function slots( array $cfg, $service_id, $date, $exclude = 0 ) {
		$tz = self::tz( $cfg );
		$d  = Helpers::parse_local_date( $date, $tz );
		if ( ! $d ) {
			return array();
		}
		$from = $d->modify( '-1 day' )->getTimestamp();
		$to   = $d->modify( '+2 days' )->getTimestamp();
		$busy = self::busy( $cfg, $service_id, $from, $to, $exclude );
		return self::day_slots( $cfg, $service_id, $date, $busy, time() );
	}

	/**
	 * Slot count per day for a month.
	 *
	 * @param array $cfg        Config.
	 * @param int   $service_id Service id.
	 * @param int   $year       Year.
	 * @param int   $month      Month 1-12.
	 * @param int   $exclude    Appointment id to ignore.
	 * @return array date => count
	 */
	public static function month( array $cfg, $service_id, $year, $month, $exclude = 0 ) {
		$tz    = self::tz( $cfg );
		$first = Helpers::parse_local_date( sprintf( '%04d-%02d-01', $year, $month ), $tz );
		if ( ! $first ) {
			return array();
		}
		$days = (int) $first->format( 't' );
		$busy = self::busy( $cfg, $service_id, $first->modify( '-1 day' )->getTimestamp(), $first->modify( '+' . ( $days + 1 ) . ' days' )->getTimestamp(), $exclude );
		$now  = time();
		$out  = array();
		for ( $i = 1; $i <= $days; $i++ ) {
			$date         = sprintf( '%04d-%02d-%02d', $year, $month, $i );
			$out[ $date ] = count( self::day_slots( $cfg, $service_id, $date, $busy, $now ) );
		}
		return $out;
	}

	/**
	 * Find a specific slot if it is still available.
	 *
	 * @param array  $cfg        Config.
	 * @param int    $service_id Service id.
	 * @param string $date       Y-m-d.
	 * @param string $time       HH:MM.
	 * @param int    $exclude    Appointment id to ignore.
	 * @return array|null
	 */
	public static function find_slot( array $cfg, $service_id, $date, $time, $exclude = 0 ) {
		if ( ! preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ) {
			return null;
		}
		foreach ( self::slots( $cfg, $service_id, $date, $exclude ) as $slot ) {
			if ( $slot['time'] === $time ) {
				return $slot;
			}
		}
		return null;
	}
}
