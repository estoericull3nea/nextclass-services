<?php
/** Standalone regression tests with mocked WordPress HTTP/database. Run: php tests/google-calendar.php */
namespace {
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		fwrite( STDERR, "Enable the PHP OpenSSL extension before running Google Calendar tests.\n" );
		exit( 2 );
	}
	define( 'ABSPATH', __DIR__ );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'ARRAY_A', 'ARRAY_A' );
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
	function __( $text, $domain = '' ) { return $text; }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_parse_args( $in, $defaults ) { return array_merge( $defaults, $in ); }
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
	function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
	function wp_salt( $scheme ) { return 'test-only-salt'; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
	function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
	function esc_url_raw( $url ) { return $url; }
	function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
	function wp_remote_retrieve_body( $r ) { return json_encode( $r['data'] ); }
	function wp_remote_request( $url, $args ) {
		$GLOBALS['requests'][] = array( $url, $args );
		return ( $GLOBALS['http'] )( $url, $args );
	}
	function wp_remote_post( $url, $args ) { return wp_remote_request( $url, $args ); }
	function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['jobs'][ $hook . json_encode( $args ) ] ?? false; }
	function wp_schedule_single_event( $time, $hook, $args ) { $GLOBALS['jobs'][ $hook . json_encode( $args ) ] = $time; }
	function wp_clear_scheduled_hook( $hook, $args ) { unset( $GLOBALS['jobs'][ $hook . json_encode( $args ) ] ); }
	class JsonResponse extends RuntimeException {
		public $success;
		public $data;
		public $status;
		public function __construct( $success, $data, $status ) { $this->success = $success; $this->data = $data; $this->status = $status; }
	}
	function wp_send_json_success( $data, $status = 200 ) { throw new JsonResponse( true, $data, $status ); }
	function wp_send_json_error( $data, $status = 200 ) { throw new JsonResponse( false, $data, $status ); }
	function current_user_can( $cap ) { return ! isset( $GLOBALS['denied_cap'] ) || $GLOBALS['denied_cap'] !== $cap; }
	function check_ajax_referer( $action, $field, $die ) { return $GLOBALS['nonce_valid'] ?? true; }
	function wp_doing_ajax() { return true; }
	function wp_unslash( $value ) { return $value; }
	function sanitize_text_field( $value ) { return strip_tags( $value ); }
	function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
	function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
	class MockDB {
		public $prefix = 'wp_';
		public $rows = array();
		public $busy = false;
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function get_var( $query ) { return $this->busy && false !== strpos( $query[0], 'GET_LOCK' ) ? '0' : '1'; }
		public function get_row( $query, $type ) { return $this->rows[ $query[1][0] ] ?? null; }
		public function suppress_errors( $value ) { return false; }
		public function update( $table, $data, $where ) { $this->rows[ $where['id'] ] = array_merge( $this->rows[ $where['id'] ], $data ); return 1; }
		public function delete( $table, $where, $format ) { unset( $this->rows[ $where['id'] ] ); return 1; }
	}
	$GLOBALS['wpdb'] = new MockDB();
}
namespace NextClassServices {
	class Helpers {
		public static function now_utc() { return gmdate( 'Y-m-d H:i:s' ); }
		public static function setting( $key ) { return false; }
		public static function settings() { return array( 'retention_mode' => 'indefinite' ); }
		public static function retention_days() { return 0; }
	}
}
namespace {
	require __DIR__ . '/../includes/class-database.php';
	require __DIR__ . '/../modules/booking/class-appointment.php';
	require __DIR__ . '/../modules/booking/class-google-calendar.php';
	require __DIR__ . '/../admin/class-google-calendar-settings.php';
	require __DIR__ . '/../admin/class-admin.php';
	require __DIR__ . '/../admin/class-settings.php';
	use NextClassServices\Booking\Google_Calendar as Calendar;
	use NextClassServices\Booking\Appointment;
	use NextClassServices\Admin\Google_Calendar_Settings as Settings;
	use NextClassServices\Admin\Admin;
	function check( $condition, $message ) {
		if ( ! $condition ) { throw new RuntimeException( $message ); }
		$GLOBALS['checks']++;
	}
	function response( $code, $data = array() ) { return array( 'code' => $code, 'data' => $data ); }
	function reset_test() {
		$GLOBALS['options'] = array(); $GLOBALS['requests'] = array(); $GLOBALS['jobs'] = array();
		$GLOBALS['wpdb']->busy = false;
		$GLOBALS['wpdb']->rows = array( 1 => array(
			'id' => 1, 'service_id' => 9, 'status' => 'confirmed', 'start_utc' => '2026-11-01 09:00:00', 'end_utc' => '2026-11-01 09:30:00', 'timezone' => 'Asia/Shanghai',
			'google_sync_enabled' => 1, 'google_calendar_id' => '', 'google_event_id' => '', 'google_meet_url' => '', 'google_sync_error' => '', 'google_sync_attempts' => 0,
		) );
		Calendar::save_config( array( 'client_id' => 'test-client', 'secret' => Calendar::encrypt( 'test-secret' ), 'calendar_id' => 'primary', 'tokens' => Calendar::encrypt( json_encode( array( 'refresh_token' => 'refresh-test', 'access_token' => 'access-test', 'expires_at' => time() + 3600 ) ) ) ) );
	}
	function calendar_metadata() { return response( 200, array( 'id' => 'calendar@example.test', 'conferenceProperties' => array( 'allowedConferenceSolutionTypes' => array( 'hangoutsMeet' ) ) ) ); }
	function event_data() { return array( 'id' => 'event-test', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij', 'start' => array( 'dateTime' => '2026-11-01T09:00:00Z' ), 'end' => array( 'dateTime' => '2026-11-01T09:30:00Z' ), 'conferenceData' => array( 'createRequest' => array( 'status' => array( 'statusCode' => 'success' ) ) ) ); }
	$GLOBALS['checks'] = 0;
	reset_test();
	$encrypted = Calendar::encrypt( 'private-token' );
	check( 'private-token' !== $encrypted && 'private-token' === Calendar::decrypt( $encrypted ), 'Credentials must round-trip encrypted' );
	$tampered = base64_decode( $encrypted ); $tampered[28] = chr( ord( $tampered[28] ) ^ 1 );
	check( '' === Calendar::decrypt( base64_encode( $tampered ) ), 'Tampering must be rejected' );
	check( '' === Calendar::meet_url( array( 'hangoutLink' => 'https://evil.example/' ) ), 'Reject non-Meet URLs' );
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( false === strpos( $url, '/events' ) ) { return calendar_metadata(); }
		check( ! isset( json_decode( $args['body'] ?? '{}', true )['attendees'] ), 'No attendee export' );
		check( 0 === $args['redirection'], 'Bearer requests must not redirect' );
		return response( 200, event_data() );
	};
	Calendar::sync( 1 );
	$a = Appointment::get( 1 );
	check( 'calendar@example.test' === $a['google_calendar_id'] && '' !== $a['google_event_id'], 'Pin identifiers before remote creation' );
	check( 'https://meet.google.com/abc-defg-hij' === $a['google_meet_url'] && '' === $a['google_sync_error'], 'Save created Meet URL' );
	$creation = json_decode( $GLOBALS['requests'][1][1]['body'], true );
	check( 'hangoutsMeet' === $creation['conferenceData']['createRequest']['conferenceSolutionKey']['type'], 'Request Meet conference' );
	check( 'Next Class booking' === $creation['summary'] && ! isset( $creation['description'] ), 'Use generic event data' );
	$event_id = $a['google_event_id'];
	Appointment::update( 1, array( 'start_utc' => '2026-11-02 09:00:00', 'end_utc' => '2026-11-02 09:30:00' ) );
	$GLOBALS['requests'] = array();
	Calendar::sync( 1 );
	check( 'PATCH' === $GLOBALS['requests'][1][1]['method'] && false !== strpos( $GLOBALS['requests'][1][0], $event_id ), 'Reschedule the same event' );
	check( ! isset( json_decode( $GLOBALS['requests'][1][1]['body'], true )['conferenceData'] ), 'Retain existing Meet on reschedule' );
	Appointment::update( 1, array( 'status' => 'cancelled' ) );
	$GLOBALS['requests'] = array();
	Calendar::sync( 1 );
	check( 'DELETE' === $GLOBALS['requests'][0][1]['method'] && '' === Appointment::get( 1 )['google_meet_url'], 'Cancellation removes remote event and local Meet URL' );

	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		return false === strpos( $url, '/events' ) ? calendar_metadata() : new WP_Error( 'timeout', 'mock timeout' );
	};
	Calendar::sync( 1 );
	$event_id = Appointment::get( 1 )['google_event_id'];
	check( '' !== $event_id && ! empty( $GLOBALS['jobs'] ), 'Persist ID and schedule retry after timeout' );
	$GLOBALS['http'] = function ( $url, $args ) { return response( 200, event_data() ); };
	$GLOBALS['requests'] = array();
	Calendar::sync( 1 );
	check( 1 === count( $GLOBALS['requests'] ) && 'GET' === $GLOBALS['requests'][0][1]['method'], 'Recover timed-out successful POST without creating a duplicate' );
	check( $event_id === Appointment::get( 1 )['google_event_id'], 'Retry keeps event identity' );

	reset_test();
	Appointment::update( 1, array( 'google_calendar_id' => 'fixed-calendar', 'google_event_id' => 'fixed-event', 'status' => 'cancelled' ) );
	$GLOBALS['http'] = function () { return response( 503 ); };
	Calendar::sync( 1 );
	check( 'fixed-event' === Appointment::get( 1 )['google_event_id'] && ! empty( $GLOBALS['jobs'] ), 'Failed cancellation retains identifier for retry' );
	check( ! Appointment::delete( 1 ) && null !== Appointment::get( 1 ), 'Retain row when remote cleanup fails' );
	$GLOBALS['http'] = function () { return response( 404 ); };
	Calendar::sync( 1 );
	check( '' === Appointment::get( 1 )['google_event_id'], 'Cancellation is idempotent when remote event is missing' );

	reset_test();
	$GLOBALS['wpdb']->busy = true;
	Calendar::sync( 1 );
	check( empty( $GLOBALS['requests'] ) && ! empty( $GLOBALS['jobs'] ), 'Concurrent sync is queued' );
	check( ! Calendar::deletion_ready( 1 ), 'Do not delete during in-flight creation' );
	$GLOBALS['wpdb']->busy = false;
	Appointment::update( 1, array( 'google_sync_enabled' => 0 ) );
	Calendar::sync( 1 );
	check( empty( $GLOBALS['requests'] ), 'Disabled bookings make no HTTP requests' );

	reset_test();
	$c = Calendar::config(); $c['tokens'] = ''; Calendar::save_config( $c );
	Calendar::sync( 1 );
	check( ! empty( Appointment::get( 1 )['google_sync_error'] ) && empty( $GLOBALS['requests'] ), 'Disconnected account queues sync without HTTP' );

	reset_test();
	$counter = 0;
	$GLOBALS['http'] = function ( $url, $args ) use ( &$counter ) {
		if ( false !== strpos( $url, 'oauth2.googleapis.com/token' ) ) { return response( 200, array( 'access_token' => 'renewed', 'expires_in' => 3600 ) ); }
		return 0 === $counter++ ? response( 401 ) : response( 200 );
	};
	$result = Calendar::request( 'GET', 'calendars/primary' );
	check( ! is_wp_error( $result ) && 3 === count( $GLOBALS['requests'] ), 'Refresh on 401 then retry once' );
	check( 'Bearer renewed' === $GLOBALS['requests'][2][1]['headers']['Authorization'], 'Use refreshed access token' );

	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		return false === strpos( $url, '/events' ) ? calendar_metadata() : response( 200, event_data() );
	};
	$result = Settings::test_connection();
	check( ! is_wp_error( $result ) && 4 === count( $GLOBALS['requests'] ), 'API test checks calendar then creates, updates, deletes' );
	check( ! get_option( 'ncs_google_test_event' ), 'Successful test cleans tracking record' );
	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( false === strpos( $url, '/events' ) ) { return calendar_metadata(); }
		return 'PATCH' === $args['method'] ? response( 403 ) : response( 200, event_data() );
	};
	$result = Settings::test_connection();
	check( is_wp_error( $result ) && 'DELETE' === $GLOBALS['requests'][3][1]['method'], 'API test still deletes after update failure' );
	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( false === strpos( $url, '/events' ) ) { return calendar_metadata(); }
		return 'DELETE' === $args['method'] ? response( 503 ) : response( 200, event_data() );
	};
	$result = Settings::test_connection();
	check( is_wp_error( $result ) && get_option( 'ncs_google_test_event' ), 'Failed test cleanup preserves event ID for later cleanup' );
	reset_test();
	Appointment::update( 1, array( 'google_calendar_id' => 'fixed-calendar', 'google_event_id' => 'existing-event' ) );
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( 'GET' === $args['method'] ) { return response( 404 ); }
		if ( 'POST' === $args['method'] ) { return response( 409 ); }
		return response( 200, event_data() );
	};
	Calendar::sync( 1 );
	check( 'existing-event' === Appointment::get( 1 )['google_event_id'] && 'PATCH' === $GLOBALS['requests'][2][1]['method'], '409 after uncertain POST updates saved event identity' );
	reset_test();
	Appointment::update( 1, array( 'google_calendar_id' => 'fixed-calendar', 'google_event_id' => 'deleted-event' ) );
	$GLOBALS['http'] = function ( $url, $args ) { return 'GET' === $args['method'] ? response( 200, array( 'status' => 'cancelled' ) ) : response( 200, event_data() ); };
	Calendar::sync( 1 );
	check( 'deleted-event' !== Appointment::get( 1 )['google_event_id'], 'Reactivation replaces a known-deleted remote ID' );
	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( false === strpos( $url, '/events' ) ) { return calendar_metadata(); }
		return response( 200, array( 'conferenceData' => array( 'createRequest' => array( 'status' => array( 'statusCode' => 'pending' ) ) ) ) );
	};
	Calendar::sync( 1 );
	check( '' !== Appointment::get( 1 )['google_event_id'] && '' === Appointment::get( 1 )['google_meet_url'] && ! empty( $GLOBALS['jobs'] ), 'Async Meet generation queues a poll without losing the event' );
	$result = Settings::test_connection();
	check( ! is_wp_error( $result ) && false !== strpos( $result, 'not yet verified' ), 'Pending Meet test must not claim full success' );
	reset_test();
	$GLOBALS['http'] = function () { return response( 200, array( 'id' => 'no-meet-calendar' ) ); };
	Calendar::sync( 1 );
	check( '' === Appointment::get( 1 )['google_event_id'] && '' !== Appointment::get( 1 )['google_sync_error'], 'Reject calendars without Meet support before inserting an event' );
	reset_test();
	$GLOBALS['http'] = function ( $url, $args ) {
		if ( false === strpos( $url, '/events' ) ) { return calendar_metadata(); }
		return response( 200, event_data() );
	};
	Calendar::sync_now( 1 );
	check( ! empty( $GLOBALS['jobs'] ), 'Synchronous sync schedules a safety retry before HTTP' );
	// Settings AJAX must preserve authorization and never disclose credentials.
	reset_test();
	Admin::begin_ajax( array( \NextClassServices\Admin\Settings::class, 'ajax_state' ) );
	try {
		Settings::handle_status();
		throw new RuntimeException( 'Status must return JSON' );
	} catch ( JsonResponse $response ) {
		check( $response->success && $response->data['state']['google']['connected'], 'AJAX status returns connection state' );
		$json = json_encode( $response->data );
		check( false === strpos( $json, 'refresh-test' ) && false === strpos( $json, 'test-secret' ) && false === strpos( $json, 'access-test' ), 'AJAX responses do not expose credentials' );
	}
	$GLOBALS['nonce_valid'] = false;
	try { Settings::handle_disconnect(); } catch ( JsonResponse $response ) {
		check( ! $response->success && 403 === $response->status && Calendar::connected(), 'Invalid AJAX nonce blocks disconnect without modifying tokens' );
	}
	$GLOBALS['nonce_valid'] = true;
	$GLOBALS['denied_cap'] = 'manage_options';
	try { Settings::handle_disconnect(); } catch ( JsonResponse $response ) {
		check( ! $response->success && 403 === $response->status && Calendar::connected(), 'AJAX credential changes require administrator capability' );
	}
	unset( $GLOBALS['denied_cap'] );
	try { Settings::handle_connect(); } catch ( JsonResponse $response ) {
		check( ! $response->success && Calendar::connected(), 'Server blocks connect while already connected' );
	}
	Admin::begin_ajax( array( \NextClassServices\Admin\Settings::class, 'ajax_state' ) );
	try { Settings::handle_disconnect(); } catch ( JsonResponse $response ) {
		check( $response->success && ! $response->data['state']['google']['connected'], 'AJAX disconnect returns updated badge and button state' );
	}
	Admin::begin_ajax( array( \NextClassServices\Admin\Settings::class, 'ajax_state' ) );
	try { Settings::handle_test(); } catch ( JsonResponse $response ) {
		check( ! $response->success && empty( $GLOBALS['requests'] ), 'Server blocks test while disconnected without making HTTP requests' );
	}
	reset_test();
	Admin::begin_ajax( array( \NextClassServices\Admin\Settings::class, 'ajax_state' ) );
	$_POST = array( 'client_id' => 'test-client', 'client_secret' => '', 'calendar_id' => 'primary' );
	try { Settings::handle_save(); } catch ( JsonResponse $response ) {
		check( $response->success && Calendar::connected() && 'test-secret' === Calendar::decrypt( Calendar::config()['secret'] ), 'Saving a blank secret preserves credentials and connection' );
	}
	$_POST['client_secret'] = 'new-secret';
	Admin::begin_ajax( array( \NextClassServices\Admin\Settings::class, 'ajax_state' ) );
	try { Settings::handle_save(); } catch ( JsonResponse $response ) {
		check( $response->success && ! Calendar::connected() && $response->data['state']['google']['secretSaved'], 'Rotating credentials clears connection and returns safe UI state' );
	}
	\NextClassServices\Admin\Settings::init();
	Settings::init();
	foreach ( array( 'ncs_save_settings', 'ncs_run_cleanup', 'ncs_google_save', 'ncs_google_connect', 'ncs_google_disconnect', 'ncs_google_test', 'ncs_google_status' ) as $hook ) {
		check( isset( $GLOBALS['hooks'][ 'wp_ajax_' . $hook ] ), 'Register authorized AJAX handler: ' . $hook );
	}
	echo 'Passed ' . $GLOBALS['checks'] . " Google Calendar regression checks.\n";
}
