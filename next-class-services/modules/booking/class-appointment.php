<?php
/**
 * Appointment data access.
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
 * Appointment CRUD, tokens and status handling.
 */
class Appointment {

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'pending'   => __( 'Pending', 'next-class-services' ),
			'confirmed' => __( 'Confirmed', 'next-class-services' ),
			'completed' => __( 'Completed', 'next-class-services' ),
			'cancelled' => __( 'Cancelled', 'next-class-services' ),
			'no_show'   => __( 'No show', 'next-class-services' ),
		);
	}

	/**
	 * Statuses that hold a time slot.
	 *
	 * @return array
	 */
	public static function active_statuses() {
		return array( 'pending', 'confirmed' );
	}

	/**
	 * Is the status one that blocks the calendar.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_active( $status ) {
		return in_array( $status, self::active_statuses(), true );
	}

	/**
	 * Generate a new management token.
	 *
	 * @return string
	 */
	public static function new_token() {
		return bin2hex( random_bytes( 20 ) );
	}

	/**
	 * Hash a token for storage.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function hash_token( $token ) {
		return hash( 'sha256', (string) $token );
	}

	/**
	 * Slot key used for the unique index (one active booking per start time).
	 *
	 * @param int $service_id Service id.
	 * @param int $start_ts   Start timestamp.
	 * @return string
	 */
	public static function slot_key( $service_id, $start_ts ) {
		$prefix = Helpers::setting( 'shared_calendar' ) ? 'all' : (string) (int) $service_id;
		return $prefix . '_' . (int) $start_ts;
	}

	/**
	 * Insert an appointment.
	 *
	 * @param array $data Column data.
	 * @return int|false Id or false when the slot is already taken.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now                 = Helpers::now_utc();
		$data['created_at']  = $now;
		$data['updated_at']  = $now;
		$data['status_changed_at'] = $now;
		$suppress            = $wpdb->suppress_errors( true );
		$ok                  = $wpdb->insert( Database::table( 'appointments' ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( $suppress );
		if ( ! $ok ) {
			Helpers::log( 'Appointment insert failed', $wpdb->last_error );
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update columns.
	 *
	 * @param int   $id   Id.
	 * @param array $data Columns.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = Helpers::now_utc();
		$suppress           = $wpdb->suppress_errors( true );
		$ok                 = $wpdb->update( Database::table( 'appointments' ), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( $suppress );
		return false !== $ok;
	}

	/**
	 * Get by id.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Get by public token.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
			return null;
		}
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", self::hash_token( $token ) ), ARRAY_A );
	}

	/**
	 * Change status, maintaining the active slot key.
	 *
	 * @param array  $appt   Appointment row.
	 * @param string $status New status.
	 * @return bool|string true, or error code slot_taken.
	 */
	public static function set_status( array $appt, $status ) {
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return false;
		}
		$data = array(
			'status'            => $status,
			'status_changed_at' => Helpers::now_utc(),
			'active_slot'       => null,
		);
		if ( self::is_active( $status ) ) {
			if ( ! self::is_active( $appt['status'] ) && self::has_conflict( $appt ) ) {
				return 'slot_taken';
			}
			$data['active_slot'] = self::slot_key( $appt['service_id'], strtotime( $appt['start_utc'] . ' UTC' ) );
		}
		if ( ! self::update( $appt['id'], $data ) ) {
			return 'slot_taken';
		}
		Google_Calendar::sync_now( $appt['id'] );
		return true;
	}

	/**
	 * Does another active appointment overlap this appointment's window.
	 *
	 * @param array $appt Appointment row.
	 * @return bool
	 */
	public static function has_conflict( array $appt ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE id <> %d AND status IN ('pending','confirmed') AND block_start_utc < %s AND block_end_utc > %s";
		$args  = array( $appt['id'], $appt['block_end_utc'], $appt['block_start_utc'] );
		if ( ! Helpers::setting( 'shared_calendar' ) ) {
			$sql   .= ' AND service_id = %d';
			$args[] = $appt['service_id'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) > 0;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$appt = self::get( $id );
		if ( $appt && ! empty( $appt['google_sync_enabled'] ) ) {
			// Keep the row and its remote identifiers until remote cleanup succeeds.
			self::set_status( $appt, 'cancelled' );
			$appt = self::get( $id );
			if ( ! Google_Calendar::deletion_ready( $id ) ) {
				return false;
			}
		}
		$deleted = (bool) $wpdb->delete( Database::table( 'appointments' ), array( 'id' => absint( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $deleted ) {
			wp_clear_scheduled_hook( Google_Calendar::RETRY, array( absint( $id ) ) );
		}
		return $deleted;
	}

	/**
	 * Build WHERE from filters.
	 *
	 * @param array $f Filters.
	 * @return array [ sql, args ]
	 */
	private static function where( array $f ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $f['service_id'] ) ) {
			$where[] = 'service_id = %d';
			$args[]  = (int) $f['service_id'];
		}
		if ( ! empty( $f['status'] ) && isset( self::statuses()[ $f['status'] ] ) ) {
			$where[] = 'status = %s';
			$args[]  = $f['status'];
		}
		if ( ! empty( $f['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['from'] ) ) {
			$where[] = 'local_date >= %s';
			$args[]  = $f['from'];
		}
		if ( ! empty( $f['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['to'] ) ) {
			$where[] = 'local_date <= %s';
			$args[]  = $f['to'];
		}
		if ( ! empty( $f['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $f['search'] ) . '%';
			$where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR company LIKE %s OR customer_phone LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}
		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * Paginated query.
	 *
	 * @param array $filters  Filters.
	 * @param int   $page     Page.
	 * @param int   $per_page Per page, 0 for all.
	 * @return array [ rows, total ]
	 */
	public static function query( array $filters, $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table                 = Database::table( 'appointments' );
		list( $where, $args ) = self::where( $filters );
		$order                 = ! empty( $filters['order'] ) && 'asc' === $filters['order'] ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY start_utc {$order}, id {$order}";
		if ( $per_page > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $per_page;
			$args[] = max( 0, ( $page - 1 ) * $per_page );
		}
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable
		return array( (array) $rows, $total );
	}

	/**
	 * Upcoming active appointments.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function upcoming( $limit = 5 ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('pending','confirmed') AND start_utc >= %s ORDER BY start_utc ASC LIMIT %d", Helpers::now_utc(), $limit ), ARRAY_A );
	}

	/**
	 * Count of upcoming active appointments.
	 *
	 * @return int
	 */
	public static function upcoming_count() {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','confirmed') AND start_utc >= %s", Helpers::now_utc() ) );
	}

	/**
	 * Recently created appointments.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function recent( $limit = 5 ) {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/**
	 * Counts for the at a glance cards.
	 *
	 * @return array upcoming, today, pending, lost
	 */
	public static function glance() {
		global $wpdb;
		$table = Database::table( 'appointments' );
		$now   = Helpers::now_utc();
		$today = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$upcoming = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','confirmed') AND start_utc >= %s", $now ) );
		$today_n  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','confirmed') AND local_date = %s", $today ) );
		$pending  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND start_utc >= %s", $now ) );
		$lost     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ('cancelled','no_show') AND status_changed_at >= %s", $since ) );
		// phpcs:enable
		return array(
			'upcoming' => $upcoming,
			'today'    => $today_n,
			'pending'  => $pending,
			'lost'     => $lost,
		);
	}

	/**
	 * Management link for a customer.
	 *
	 * @param array  $appt  Appointment row.
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function manage_url( array $appt, $token ) {
		$base = '';
		if ( ! empty( $appt['page_url'] ) ) {
			$base = $appt['page_url'];
		}
		if ( '' === $base ) {
			$base = (string) Helpers::setting( 'manage_page_url', '' );
		}
		if ( '' === $base ) {
			$base = home_url( '/' );
		}
		return add_query_arg( 'ncs_manage', rawurlencode( $token ), remove_query_arg( 'ncs_manage', $base ) );
	}

	/**
	 * Timezone abbreviation for display.
	 *
	 * @param string $tz_name Timezone.
	 * @param string $utc     UTC datetime for the abbreviation (DST aware).
	 * @return string
	 */
	public static function tz_label( $tz_name, $utc ) {
		$tz = Helpers::timezone( $tz_name );
		try {
			$dt = new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) );
			return $tz->getName() . ' (' . $dt->setTimezone( $tz )->format( 'T' ) . ')';
		} catch ( \Exception $e ) {
			return $tz->getName();
		}
	}
}
