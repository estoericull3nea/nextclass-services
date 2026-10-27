<?php
/** Google Calendar OAuth and appointment synchronization. @package NextClassServices */
namespace NextClassServices\Booking;

use NextClassServices\Database;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Calendar {
	const OPTION = 'ncs_google_calendar';
	const RETRY = 'ncs_google_calendar_retry';

	public static function init() {
		add_action( self::RETRY, array( __CLASS__, 'sync' ) );
		if ( is_admin() ) {
			\NextClassServices\Admin\Google_Calendar_Settings::init();
		}
	}

	public static function config() {
		return wp_parse_args( get_option( self::OPTION, array() ), array( 'client_id' => '', 'secret' => '', 'tokens' => '', 'calendar_id' => 'primary' ) );
	}

	public static function save_config( array $config ) {
		update_option( self::OPTION, $config, false );
	}

	/** Store credentials encrypted with authenticated encryption and the site's salts. */
	public static function encrypt( $value ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error( 'crypto', __( 'PHP OpenSSL is required to store Google credentials.', 'next-class-services' ) );
		}
		$iv = random_bytes( 12 );
		$tag = '';
		$data = openssl_encrypt( $value, 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $data ? new \WP_Error( 'crypto', __( 'Could not encrypt Google credentials.', 'next-class-services' ) ) : base64_encode( $iv . $tag . $data );
	}

	public static function decrypt( $value ) {
		$data = base64_decode( $value, true );
		if ( ! function_exists( 'openssl_decrypt' ) || false === $data || strlen( $data ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $data, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $data, 0, 12 ), substr( $data, 12, 16 ) );
		return false === $plain ? '' : $plain;
	}

	public static function connected() {
		$c = self::config();
		$t = json_decode( self::decrypt( $c['tokens'] ), true );
		return ! empty( $t['refresh_token'] );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=ncs_google_callback' );
	}

	/** Token errors deliberately exclude Google's response body and all credentials. */
	public static function token_request( array $body ) {
		$c = self::config();
		$secret = self::decrypt( $c['secret'] );
		if ( ! $c['client_id'] || ! $secret ) {
			return new \WP_Error( 'credentials', __( 'Save a Google OAuth client ID and client secret first.', 'next-class-services' ) );
		}
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'redirection' => 0, 'body' => array_merge( $body, array( 'client_id' => $c['client_id'], 'client_secret' => $secret ) ) ) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'network', __( 'Could not reach Google. Check connectivity and retry.', 'next-class-services' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['access_token'] ) ) {
			return new \WP_Error( 'oauth', __( 'Google authorization failed. Check the OAuth credentials and reconnect the account.', 'next-class-services' ) );
		}
		$old = 'refresh_token' === $body['grant_type'] ? json_decode( self::decrypt( $c['tokens'] ), true ) : array();
		$tokens = array(
			'access_token' => $data['access_token'],
			'refresh_token' => isset( $data['refresh_token'] ) ? $data['refresh_token'] : ( isset( $old['refresh_token'] ) ? $old['refresh_token'] : '' ),
			'expires_at' => time() + max( 60, (int) $data['expires_in'] ) - 60,
		);
		if ( ! $tokens['refresh_token'] ) {
			return new \WP_Error( 'oauth', __( 'Google did not return offline authorization. Reconnect and grant access.', 'next-class-services' ) );
		}
		$encrypted = self::encrypt( wp_json_encode( $tokens ) );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		$c['tokens'] = $encrypted;
		self::save_config( $c );
		return $tokens['access_token'];
	}

	private static function access_token( $force = false ) {
		$c = self::config();
		$t = json_decode( self::decrypt( $c['tokens'] ), true );
		if ( empty( $t['refresh_token'] ) ) {
			return new \WP_Error( 'disconnected', __( 'Connect Google Calendar in Settings.', 'next-class-services' ) );
		}
		if ( ! $force && ! empty( $t['access_token'] ) && $t['expires_at'] > time() ) {
			return $t['access_token'];
		}
		return self::token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'] ) );
	}

	/** Credentials go only to the fixed Google host, never through redirects. */
	public static function request( $method, $path, $body = null, $retry_auth = true ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array( 'method' => $method, 'timeout' => 15, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ) );
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( 'https://www.googleapis.com/calendar/v3/' . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'network', __( 'Google Calendar could not be reached. Sync will retry.', 'next-class-services' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code && $retry_auth ) {
			$refreshed = self::access_token( true );
			return is_wp_error( $refreshed ) ? $refreshed : self::request( $method, $path, $body, false );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP response code */
			return new \WP_Error( 'google_' . $code, sprintf( __( 'Google Calendar returned HTTP %d. Check the Calendar API, account permissions, calendar ID and quota, then retry.', 'next-class-services' ), $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	public static function events_path( $calendar, $event = '' ) {
		return 'calendars/' . rawurlencode( $calendar ) . '/events' . ( '' !== $event ? '/' . rawurlencode( $event ) : '' );
	}

	public static function missing( $result ) {
		return is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'google_404', 'google_410' ), true );
	}

	public static function conference() {
		return array( 'createRequest' => array( 'requestId' => wp_generate_uuid4(), 'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ) ) );
	}

	public static function meet_url( array $event ) {
		$url = isset( $event['hangoutLink'] ) ? $event['hangoutLink'] : '';
		foreach ( isset( $event['conferenceData']['entryPoints'] ) ? $event['conferenceData']['entryPoints'] : array() as $entry ) {
			if ( 'video' === $entry['entryPointType'] ) {
				$url = $entry['uri'];
			}
		}
		return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && 'meet.google.com' === wp_parse_url( $url, PHP_URL_HOST ) ? esc_url_raw( $url ) : '';
	}

	/** Serialize workers and reload current state so stale jobs cannot undo cancellation. */
	public static function sync( $id ) {
		global $wpdb;
		$id = absint( $id );
		$a = Appointment::get( $id );
		if ( ! $a || empty( $a['google_sync_enabled'] ) ) {
			return;
		}
		$lock = 'ncs_google_' . md5( Database::table( 'appointments' ) . $id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			self::schedule( $id );
			return;
		}
		try {
			$a = Appointment::get( $id );
			if ( ! $a || empty( $a['google_sync_enabled'] ) ) {
				return;
			}
			$result = self::sync_row( $a );
			if ( is_wp_error( $result ) ) {
				$attempts = (int) $a['google_sync_attempts'] + 1;
				Appointment::update( $id, array( 'google_sync_error' => $result->get_error_message(), 'google_sync_attempts' => min( 65535, $attempts ) ) );
				self::schedule( $id, min( DAY_IN_SECONDS, 60 * pow( 2, min( 10, $attempts ) ) ) );
			} else {
				Appointment::update( $id, array( 'google_sync_error' => '', 'google_sync_attempts' => 0 ) );
				// Keep any queued retry: it may represent a concurrent cancellation or reschedule.
			}
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Never delete a local row while a remote creation request is in flight. */
	public static function deletion_ready( $id ) {
		global $wpdb;
		$lock = 'ncs_google_' . md5( Database::table( 'appointments' ) . absint( $id ) );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return false;
		}
		try {
			$a = Appointment::get( $id );
			return $a && 'cancelled' === $a['status'] && '' === $a['google_event_id'];
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	private static function schedule( $id, $delay = 60 ) {
		if ( ! wp_next_scheduled( self::RETRY, array( $id ) ) ) {
			wp_schedule_single_event( time() + (int) $delay, self::RETRY, array( $id ) );
		}
	}

	/** Queue a safety retry before synchronous HTTP work, including unexpected process termination. */
	public static function sync_now( $id ) {
		$a = Appointment::get( $id );
		if ( $a && ! empty( $a['google_sync_enabled'] ) ) {
			self::schedule( absint( $id ) );
			self::sync( $id );
		}
	}

	private static function sync_row( array $a ) {
		$id = (int) $a['id'];
		$event_id = $a['google_event_id'];
		$calendar = $a['google_calendar_id'];
		if ( 'pending' === $a['status'] ) {
			return true;
		}
		if ( 'cancelled' === $a['status'] ) {
			$meta = ! empty( $a['google_calendar_meta'] ) ? json_decode( $a['google_calendar_meta'], true ) : array();
			$meta = is_array( $meta ) ? $meta : array();
			if ( '' !== $event_id ) {
				if ( empty( $meta['uid'] ) ) {
					$existing = self::request( 'GET', self::events_path( $calendar, $event_id ) );
					if ( is_wp_error( $existing ) && ! self::missing( $existing ) ) { return $existing; }
					if ( is_array( $existing ) ) {
						$meta = Admin_Calendar_Invite::metadata( $existing );
						if ( ! Appointment::update( $id, array( 'google_calendar_meta' => wp_json_encode( $meta ) ) ) ) {
							return new \WP_Error( 'storage', __( 'Could not save calendar cancellation metadata.', 'next-class-services' ) );
						}
					}
				}
				$result = self::request( 'DELETE', self::events_path( $calendar, $event_id ) . '?sendUpdates=all' );
				if ( is_wp_error( $result ) && ! self::missing( $result ) ) {
					return $result;
				}
			}
			if ( ! empty( $meta['uid'] ) ) {
				$mail = Admin_Calendar_Invite::send( $a, $meta, true );
				if ( is_wp_error( $mail ) ) { return $mail; }
			}
			Appointment::update( $id, array( 'google_event_id' => '', 'google_meet_url' => '' ) );
			return true;
		}
		if ( ! Appointment::is_active( $a['status'] ) ) {
			return true;
		}
		if ( '' === $calendar ) {
			$c = self::config();
			$cal = self::request( 'GET', 'calendars/' . rawurlencode( $c['calendar_id'] ) );
			if ( is_wp_error( $cal ) ) {
				return $cal;
			}
			if ( ! in_array( 'hangoutsMeet', isset( $cal['conferenceProperties']['allowedConferenceSolutionTypes'] ) ? $cal['conferenceProperties']['allowedConferenceSolutionTypes'] : array(), true ) ) {
				return new \WP_Error( 'meet', __( 'This Google calendar does not support Google Meet conferences.', 'next-class-services' ) );
			}
			// Pin the real calendar ID so changing the default cannot move existing events.
			$calendar = $cal['id'];
			if ( ! Appointment::update( $id, array( 'google_calendar_id' => $calendar ) ) ) {
				return new \WP_Error( 'storage', __( 'Could not save the Google calendar identifier.', 'next-class-services' ) );
			}
		}
		$existing = '' !== $event_id ? self::request( 'GET', self::events_path( $calendar, $event_id ) ) : null;
		if ( is_wp_error( $existing ) && ! self::missing( $existing ) ) {
			return $existing;
		}
		if ( ( is_wp_error( $existing ) && 'google_410' === $existing->get_error_code() ) || ( is_array( $existing ) && isset( $existing['status'] ) && 'cancelled' === $existing['status'] ) ) {
			$event_id = '';
			$existing = null;
		} elseif ( self::missing( $existing ) ) {
			// Reuse the saved ID after 404: an earlier POST may have timed out.
			$existing = null;
		}
		$body = array(
			'summary' => ! empty( $a['service_title'] ) ? $a['service_title'] : __( 'Next Class booking', 'next-class-services' ),
			'start' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $a['start_utc'] . ' UTC' ) ), 'timeZone' => $a['timezone'] ),
			'end' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $a['end_utc'] . ' UTC' ) ), 'timeZone' => $a['timezone'] ),
		);
		$required_attendees = array();
		if ( ! empty( $a['customer_email'] ) && is_email( $a['customer_email'] ) ) {
			$required_attendees[] = array( 'email' => $a['customer_email'], 'displayName' => isset( $a['customer_name'] ) ? $a['customer_name'] : '' );
		}
		$notification_email = Helpers::notification_email();
		if ( is_email( $notification_email ) ) {
			$required_attendees[] = array( 'email' => $notification_email );
		}
		$attendees = is_array( $existing ) && ! empty( $existing['attendees'] ) ? $existing['attendees'] : array();
		$has_attendee = true;
		foreach ( $required_attendees as $required ) {
			$found = false;
			foreach ( $attendees as $attendee ) {
				if ( isset( $attendee['email'] ) && 0 === strcasecmp( $attendee['email'], $required['email'] ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$attendees[] = $required;
				$has_attendee = false;
			}
		}
		if ( $attendees ) {
			$body['attendees'] = $attendees;
		}
		if ( ! is_array( $existing ) ) {
			if ( '' === $event_id ) {
				$event_id = bin2hex( random_bytes( 16 ) );
				if ( ! Appointment::update( $id, array( 'google_event_id' => $event_id ) ) ) {
					return new \WP_Error( 'storage', __( 'Could not save the Google event identifier.', 'next-class-services' ) );
				}
			}
			$body['id'] = $event_id;
			$body['conferenceData'] = self::conference();
			$event = self::request( 'POST', self::events_path( $calendar ) . '?conferenceDataVersion=1&sendUpdates=all', $body );
			if ( is_wp_error( $event ) && 'google_409' === $event->get_error_code() ) {
				unset( $body['id'], $body['conferenceData'] );
				$event = self::request( 'PATCH', self::events_path( $calendar, $event_id ) . '?conferenceDataVersion=1&sendUpdates=all', $body );
			}
		} else {
			$status = isset( $existing['conferenceData']['createRequest']['status']['statusCode'] ) ? $existing['conferenceData']['createRequest']['status']['statusCode'] : '';
			if ( empty( $existing['conferenceData'] ) || 'failure' === $status ) {
				$body['conferenceData'] = self::conference();
			}
			if ( self::same_time( $existing, $a ) && $has_attendee && empty( $body['conferenceData'] ) ) {
				$event = $existing;
			} else {
				$event = self::request( 'PATCH', self::events_path( $calendar, $event_id ) . '?conferenceDataVersion=1&sendUpdates=all', $body );
			}
		}
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$url = self::meet_url( $event );
		$meta = Admin_Calendar_Invite::metadata( $event );
		if ( ! Appointment::update( $id, array( 'google_meet_url' => $url, 'google_calendar_meta' => wp_json_encode( $meta ) ) ) ) {
			return new \WP_Error( 'storage', __( 'Could not save Google calendar details.', 'next-class-services' ) );
		}
		if ( '' === $url ) {
			return new \WP_Error( 'meet_pending', __( 'The event exists, but the Google Meet link is pending or failed. Sync will retry.', 'next-class-services' ) );
		}
		$a['google_meet_url'] = $url;
		return Admin_Calendar_Invite::send( $a, $meta );
	}

	private static function same_time( array $event, array $a ) {
		return isset( $event['start']['dateTime'], $event['end']['dateTime'] )
			&& strtotime( $event['start']['dateTime'] ) === strtotime( $a['start_utc'] . ' UTC' )
			&& strtotime( $event['end']['dateTime'] ) === strtotime( $a['end_utc'] . ' UTC' );
	}
}
