<?php
/** Google Calendar settings and OAuth handlers. @package NextClassServices */
namespace NextClassServices\Admin;

use NextClassServices\Booking\Google_Calendar;
use NextClassServices\Booking\Appointment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Calendar_Settings {
	private static $popup_callback = false;

	public static function init() {
		foreach ( array( 'save', 'connect', 'callback', 'disconnect', 'test', 'retry' ) as $action ) {
			add_action( 'admin_post_ncs_google_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
		foreach ( array( 'save', 'connect', 'disconnect', 'test', 'status' ) as $action ) {
			add_action( 'wp_ajax_ncs_google_' . $action, function () use ( $action ) {
				Admin::begin_ajax( array( Settings::class, 'ajax_state' ) );
				call_user_func( array( __CLASS__, 'handle_' . $action ) );
			} );
		}
	}

	/** Only return public connection metadata, never secrets or tokens. */
	public static function state() {
		$c = Google_Calendar::config();
		return array( 'connected' => Google_Calendar::connected(), 'secretSaved' => '' !== $c['secret'], 'clientId' => $c['client_id'], 'calendarId' => $c['calendar_id'], 'test' => get_option( 'ncs_google_test_result', array() ) );
	}

	public static function handle_status() {
		self::authorize( 'status' );
		wp_send_json_success( array( 'state' => Settings::ajax_state() ) );
	}

	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => __( 'Only administrators can manage Google Calendar credentials.', 'next-class-services' ) ), 403 );
			}
			wp_die( esc_html__( 'Only administrators can manage Google Calendar credentials.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		Admin::authorize( Settings::CAP, 'ncs_google_' . $action, '_ncs_nonce' );
	}

	private static function input( $key, $source ) {
		return isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? trim( wp_unslash( $source[ $key ] ) ) : '';
	}

	private static function finish( $message, $type = 'success' ) {
		if ( self::$popup_callback ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			$parts = wp_parse_url( admin_url() );
			$origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
			$data = array( 'type' => 'ncs-google-oauth', 'success' => 'success' === $type, 'message' => $message );
			echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Google Calendar connection', 'next-class-services' ) . '</title></head><body><p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( Admin::url( 'ncs-settings' ) ) . '">' . esc_html__( 'Return to Settings', 'next-class-services' ) . '</a></p><script>if(window.opener){window.opener.postMessage(' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ',' . wp_json_encode( $origin ) . ');window.close();}</script></body></html>';
			exit;
		}
		Admin::flash( $type, $message );
		Admin::redirect( Admin::url( 'ncs-settings' ) . '#ncs-google-calendar' );
	}

	private static function form_start( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ncs-form" data-ncs-settings-ajax><input type="hidden" name="action" value="ncs_google_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'ncs_google_' . $action, '_ncs_nonce' );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = Google_Calendar::config();
		$connected = Google_Calendar::connected();
		echo '<div class="ncs-card" id="ncs-google-calendar" data-connected="' . ( $connected ? '1' : '0' ) . '" data-status-nonce="' . esc_attr( wp_create_nonce( 'ncs_google_status' ) ) . '"><div class="ncs-guide-heading"><h2 class="ncs-card-title">' . esc_html__( 'Google Calendar and Google Meet', 'next-class-services' ) . '</h2><button type="button" class="ncs-guide-trigger" data-ncs-dialog-open="ncs-google-guide" aria-haspopup="dialog" aria-controls="ncs-google-guide" aria-label="' . esc_attr__( 'Open Google Calendar setup guide', 'next-class-services' ) . '" title="' . esc_attr__( 'Setup guide', 'next-class-services' ) . '"><span aria-hidden="true">?</span></button><span class="ncs-connection-badge ' . ( $connected ? 'is-connected' : 'is-disconnected' ) . '" data-ncs-google-badge role="status">' . esc_html( $connected ? __( 'Connected', 'next-class-services' ) : __( 'Not connected', 'next-class-services' ) ) . '</span></div>';
		echo '<p class="ncs-help">' . esc_html__( 'Enable the Google Calendar API in Google Cloud, configure the OAuth consent screen, and create a Web application OAuth client. An API key alone cannot create calendar events. Add this exact authorized redirect URI:', 'next-class-services' ) . '</p><p><code>' . esc_html( Google_Calendar::redirect_uri() ) . '</code></p>';
		echo '<p class="ncs-help">' . esc_html__( 'For an External app in Testing, add your Google account as a test user. Google may expire testing refresh tokens after seven days. Use a production OAuth app for ongoing bookings. The connected calendar must support Google Meet.', 'next-class-services' ) . '</p>';
		self::form_start( 'save' );
		Form::text( 'client_id', __( 'Google OAuth client ID', 'next-class-services' ), $c['client_id'] );
		Form::text( 'client_secret', __( 'Google OAuth client secret', 'next-class-services' ), '', array( 'type' => 'password', 'attrs' => array( 'autocomplete' => 'new-password' ), 'help' => $c['secret'] ? __( 'A secret is saved. Leave blank to keep it. Secrets are encrypted and never displayed.', 'next-class-services' ) : __( 'Enter the client secret from Google Cloud.', 'next-class-services' ) ) );
		Form::text( 'calendar_id', __( 'Google calendar ID', 'next-class-services' ), $c['calendar_id'], array( 'help' => __( 'Use primary for the connected account, or copy a calendar ID from Google Calendar settings. Changes apply to new bookings; existing events keep their calendar.', 'next-class-services' ) ) );
		echo '<button type="submit" class="ncs-btn ncs-btn-primary">' . esc_html__( 'Save Google configuration', 'next-class-services' ) . '</button></form>';
		echo '<p class="ncs-help">' . esc_html__( 'Save configuration before connecting or testing. Enable sync per service in Booking, General. Sync sends generic event titles and booking times to Google; customer names, email addresses and notes are not sent. Meet links appear in appointment details, customer booking management and plugin emails. Google attendee invitations are not sent. Google Calendar busy times are not imported.', 'next-class-services' ) . '</p>';
		echo '<div class="ncs-item-actions">';
		foreach ( array( 'connect' => __( 'Connect / reconnect Google', 'next-class-services' ), 'test' => __( 'Test Calendar and Meet API', 'next-class-services' ), 'disconnect' => __( 'Disconnect Google', 'next-class-services' ) ) as $action => $label ) {
			self::form_start( $action );
			echo '<button type="submit" class="ncs-btn" data-ncs-google-action="' . esc_attr( $action ) . '"' . disabled( 'connect' === $action ? $connected : ! $connected, true, false ) . '>' . esc_html( $label ) . '</button></form>';
		}
		echo '</div><p class="ncs-help">' . esc_html__( 'The test creates a temporary event with Meet, updates its time, and deletes it. If Meet generation is still pending, the result says so. Failed booking syncs retry automatically through WordPress scheduled tasks; a working WP-Cron is required.', 'next-class-services' ) . '</p>';
		$test = get_option( 'ncs_google_test_result', array() );
		echo '<p role="status" data-ncs-google-test-result' . ( empty( $test['message'] ) ? ' hidden' : '' ) . '>' . ( ! empty( $test['message'] ) ? esc_html( $test['date'] . ': ' . $test['message'] ) : '' ) . '</p>';
		echo '</div>';
		self::render_guide();
	}

	/** Step-by-step instructions live in a dialog to keep the settings compact. */
	private static function render_guide() {
		echo '<dialog id="ncs-google-guide" class="ncs-guide-dialog" aria-labelledby="ncs-google-guide-title"><div class="ncs-guide-dialog-header"><h2 id="ncs-google-guide-title" tabindex="-1" autofocus>' . esc_html__( 'Set up Google Calendar and Google Meet', 'next-class-services' ) . '</h2><button type="button" class="ncs-btn ncs-btn-small" data-ncs-dialog-close>' . esc_html__( 'Close', 'next-class-services' ) . '</button></div>';
		echo '<p>' . esc_html__( 'Follow these steps to get your Google OAuth client ID and client secret, then connect them to this website.', 'next-class-services' ) . '</p><ol class="ncs-guide-steps">';
		$steps = array(
			array( __( 'Choose a Google Cloud project', 'next-class-services' ), array(
				__( 'Open Google Cloud Console.', 'next-class-services' ),
				__( 'Sign in with the Google account that will host your bookings.', 'next-class-services' ),
				__( 'Use the project selector at the top to select a project or create a new one.', 'next-class-services' ),
			) ),
			array( __( 'Enable the Google Calendar API', 'next-class-services' ), array(
				__( 'In your selected project, open APIs & Services > Library.', 'next-class-services' ),
				__( 'Search for Google Calendar API.', 'next-class-services' ),
				__( 'Open the API and click Enable.', 'next-class-services' ),
			) ),
			array( __( 'Configure the consent screen', 'next-class-services' ), array(
				__( 'Open Google Auth platform.', 'next-class-services' ),
				__( 'If prompted, click Get started.', 'next-class-services' ),
				__( 'Open Branding and enter an app name.', 'next-class-services' ),
				__( 'Enter a support email and developer contact details, then save.', 'next-class-services' ),
				__( 'Open Audience.', 'next-class-services' ),
				__( 'Choose Internal only for an eligible Workspace organization; otherwise choose External.', 'next-class-services' ),
				__( 'If the app is in Testing, open Test users and add the Google account you will connect.', 'next-class-services' ),
			) ),
			array( __( 'Add the Calendar permissions', 'next-class-services' ), array(
				__( 'Open Google Auth platform > Data Access.', 'next-class-services' ),
				__( 'Click Add or remove scopes.', 'next-class-services' ),
				__( 'Add both Calendar scopes shown below.', 'next-class-services' ),
				__( 'Save your changes. The plugin requests these permissions when you connect.', 'next-class-services' ),
			) ),
			array( __( 'Create a Web application client', 'next-class-services' ), array(
				__( 'Open Google Auth platform > Clients.', 'next-class-services' ),
				__( 'Click Create client.', 'next-class-services' ),
				__( 'Select Web application.', 'next-class-services' ),
				__( 'Enter a name such as Next Class Bookings.', 'next-class-services' ),
				__( 'Under Authorized redirect URIs, click Add URI.', 'next-class-services' ),
				__( 'Copy the exact redirect URI below and paste it into Google.', 'next-class-services' ),
				__( 'Click Create.', 'next-class-services' ),
			) ),
			array( __( 'Copy your client ID and client secret', 'next-class-services' ), array(
				__( 'Copy the client ID from the new client dialog.', 'next-class-services' ),
				__( 'Copy the client secret and keep it private. Google may show the full secret only at creation.', 'next-class-services' ),
				__( 'If a JSON download is offered, save it securely. It contains client_id and client_secret.', 'next-class-services' ),
				__( 'If you no longer have the secret, create a replacement secret in that client.', 'next-class-services' ),
			) ),
			array( __( 'Save and connect in WordPress', 'next-class-services' ), array(
				__( 'Close this guide.', 'next-class-services' ),
				__( 'Paste your client ID into Google OAuth client ID.', 'next-class-services' ),
				__( 'Paste your client secret into Google OAuth client secret.', 'next-class-services' ),
				__( 'Leave Google calendar ID as primary to use your main calendar.', 'next-class-services' ),
				__( 'Click Save Google configuration.', 'next-class-services' ),
				__( 'Click Connect / reconnect Google.', 'next-class-services' ),
				__( 'Sign in with the intended Google account and grant the requested permissions.', 'next-class-services' ),
			) ),
			array( __( 'Test and enable booking sync', 'next-class-services' ), array(
				__( 'Click Test Calendar and Meet API. The test creates, reschedules and deletes a temporary event.', 'next-class-services' ),
				__( 'Once the test succeeds, open Booking and edit a booking service.', 'next-class-services' ),
				__( 'In General, select Native WordPress booking.', 'next-class-services' ),
				__( 'Enable Sync to Google Calendar and create a Google Meet.', 'next-class-services' ),
				__( 'Save the booking service.', 'next-class-services' ),
			) ),
		);
		foreach ( $steps as $index => $step ) {
			echo '<li><h3>' . esc_html( $step[0] ) . '</h3><ol class="ncs-guide-substeps">';
			foreach ( $step[1] as $instruction ) {
				echo '<li>' . esc_html( $instruction ) . '</li>';
			}
			echo '</ol>';
			if ( 0 === $index ) {
				echo '<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Google Cloud Console', 'next-class-services' ) . '</a>';
			} elseif ( 3 === $index ) {
				echo '<p><code>https://www.googleapis.com/auth/calendar.events</code><br><code>https://www.googleapis.com/auth/calendar.calendars.readonly</code></p>';
			} elseif ( 4 === $index ) {
				echo '<div class="ncs-guide-redirect"><code>' . esc_html( Google_Calendar::redirect_uri() ) . '</code><button type="button" class="ncs-btn ncs-btn-small" data-ncs-copy="' . esc_attr( Google_Calendar::redirect_uri() ) . '">' . esc_html__( 'Copy redirect URI', 'next-class-services' ) . '</button><input class="screen-reader-text" type="text" readonly tabindex="-1" aria-label="' . esc_attr__( 'Authorized redirect URI', 'next-class-services' ) . '" value="' . esc_attr( Google_Calendar::redirect_uri() ) . '"></div>';
			}
			echo '</li>';
		}
		echo '</ol><div class="ncs-guide-notes"><h3>' . esc_html__( 'If something does not work', 'next-class-services' ) . '</h3><ul>';
		foreach ( array(
			__( 'Redirect URI mismatch: make sure the URI in Google exactly matches the address above, including https, the domain and the action query parameter.', 'next-class-services' ),
			__( 'Access blocked while testing: add the connecting account as a test user in Audience. External apps in Testing can have refresh tokens expire after seven days; publish the app and complete any Google verification required for ongoing use.', 'next-class-services' ),
			__( 'Calendar access or Meet failure: verify the Calendar API is enabled, the connected account can edit the selected calendar, and that calendar supports Google Meet.', 'next-class-services' ),
		) as $note ) {
			echo '<li>' . esc_html( $note ) . '</li>';
		}
		echo '</ul><p><a href="https://developers.google.com/workspace/guides/create-credentials" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google credential setup documentation', 'next-class-services' ) . '</a> | <a href="https://developers.google.com/workspace/guides/configure-oauth-consent" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google consent screen documentation', 'next-class-services' ) . '</a></p></div></dialog>';
	}

	public static function handle_save() {
		self::authorize( 'save' );
		$c = Google_Calendar::config();
		$id = sanitize_text_field( self::input( 'client_id', $_POST ) );
		$secret = self::input( 'client_secret', $_POST );
		$changed = $id !== $c['client_id'] || ( '' !== $secret && $secret !== Google_Calendar::decrypt( $c['secret'] ) );
		if ( '' !== $secret ) {
			$encrypted = Google_Calendar::encrypt( $secret );
			if ( is_wp_error( $encrypted ) ) {
				self::finish( $encrypted->get_error_message(), 'error' );
			}
			$c['secret'] = $encrypted;
		}
		$c['client_id'] = $id;
		$c['calendar_id'] = sanitize_text_field( self::input( 'calendar_id', $_POST ) );
		if ( '' === $c['calendar_id'] ) {
			$c['calendar_id'] = 'primary';
		}
		if ( $changed ) {
			$c['tokens'] = '';
		}
		Google_Calendar::save_config( $c );
		self::finish( __( 'Google configuration saved. Connect or reconnect if credentials changed, then run the API test.', 'next-class-services' ) );
	}

	public static function handle_connect() {
		self::authorize( 'connect' );
		if ( Google_Calendar::connected() ) {
			self::finish( __( 'Google is already connected. Disconnect first to connect another account.', 'next-class-services' ), 'error' );
		}
		$c = Google_Calendar::config();
		if ( ! $c['client_id'] || ! Google_Calendar::decrypt( $c['secret'] ) ) {
			self::finish( __( 'Save an OAuth client ID and secret first.', 'next-class-services' ), 'error' );
		}
		$state = bin2hex( random_bytes( 32 ) );
		set_transient( 'ncs_google_state_' . hash( 'sha256', $state ), array( 'user' => get_current_user_id(), 'session' => hash( 'sha256', wp_get_session_token() ), 'client' => hash( 'sha256', $c['client_id'] . $c['secret'] ), 'popup' => wp_doing_ajax() ), 10 * MINUTE_IN_SECONDS );
		$url = add_query_arg( array(
			'client_id' => $c['client_id'], 'redirect_uri' => Google_Calendar::redirect_uri(), 'response_type' => 'code',
			'scope' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendars.readonly',
			'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state,
		), 'https://accounts.google.com/o/oauth2/v2/auth' );
		if ( wp_doing_ajax() ) {
			wp_send_json_success( array( 'authUrl' => $url ) );
		}
		wp_redirect( $url ); // Fixed Google authorization host, not user-provided.
		exit;
	}

	public static function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( Settings::CAP ) ) {
			wp_die( esc_html__( 'You cannot connect Google Calendar.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		$state = self::input( 'state', $_GET );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $state ) ) {
			self::finish( __( 'Invalid Google authorization state. Start the connection again.', 'next-class-services' ), 'error' );
		}
		$key = 'ncs_google_state_' . hash( 'sha256', $state );
		$saved = get_transient( $key );
		$c = Google_Calendar::config();
		if ( ! is_array( $saved ) || (int) $saved['user'] !== get_current_user_id() || ! hash_equals( $saved['session'], hash( 'sha256', wp_get_session_token() ) ) || ! hash_equals( $saved['client'], hash( 'sha256', $c['client_id'] . $c['secret'] ) ) ) {
			self::finish( __( 'Google authorization expired or belongs to another session. Connect again.', 'next-class-services' ), 'error' );
		}
		delete_transient( $key );
		self::$popup_callback = ! empty( $saved['popup'] );
		$code = self::input( 'code', $_GET );
		if ( '' === $code || '' !== self::input( 'error', $_GET ) ) {
			self::finish( __( 'Google authorization was declined or incomplete.', 'next-class-services' ), 'error' );
		}
		$result = Google_Calendar::token_request( array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => Google_Calendar::redirect_uri() ) );
		self::finish( is_wp_error( $result ) ? $result->get_error_message() : __( 'Google account connected. Run the Calendar and Meet API test.', 'next-class-services' ), is_wp_error( $result ) ? 'error' : 'success' );
	}

	public static function handle_disconnect() {
		self::authorize( 'disconnect' );
		if ( ! Google_Calendar::connected() ) {
			self::finish( __( 'Google is not connected.', 'next-class-services' ), 'error' );
		}
		$c = Google_Calendar::config();
		$c['tokens'] = '';
		Google_Calendar::save_config( $c );
		self::finish( __( 'Google disconnected locally. Existing events remain in Google Calendar. Reconnect to resume pending changes. You can revoke app access in your Google account.', 'next-class-services' ) );
	}

	public static function handle_test() {
		self::authorize( 'test' );
		if ( ! Google_Calendar::connected() ) {
			self::finish( __( 'Connect Google before testing the API.', 'next-class-services' ), 'error' );
		}
		$result = self::test_connection();
		$message = is_wp_error( $result ) ? $result->get_error_message() : $result;
		update_option( 'ncs_google_test_result', array( 'date' => current_time( 'mysql' ), 'message' => $message ), false );
		self::finish( $message, is_wp_error( $result ) ? 'error' : 'success' );
	}

	/** Persist a test event's ID before POST so cleanup also works after a timeout. */
	public static function test_connection() {
		global $wpdb;
		$lock = 'ncs_google_test_' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return new \WP_Error( 'busy', __( 'A Google API test is already running. Wait for it to finish.', 'next-class-services' ) );
		}
		try {
			return self::run_test();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	private static function run_test() {
		$pending = get_option( 'ncs_google_test_event', array() );
		if ( $pending ) {
			$cleanup = Google_Calendar::request( 'DELETE', Google_Calendar::events_path( $pending['calendar'], $pending['id'] ) . '?sendUpdates=none' );
			if ( is_wp_error( $cleanup ) && ! Google_Calendar::missing( $cleanup ) ) {
				return new \WP_Error( 'cleanup', __( 'The previous test event could not be deleted. Reconnect the original account and run the test again.', 'next-class-services' ) );
			}
			delete_option( 'ncs_google_test_event' );
		}
		$c = Google_Calendar::config();
		$cal = Google_Calendar::request( 'GET', 'calendars/' . rawurlencode( $c['calendar_id'] ) );
		if ( is_wp_error( $cal ) ) {
			return $cal;
		}
		if ( ! in_array( 'hangoutsMeet', isset( $cal['conferenceProperties']['allowedConferenceSolutionTypes'] ) ? $cal['conferenceProperties']['allowedConferenceSolutionTypes'] : array(), true ) ) {
			return new \WP_Error( 'meet', __( 'Calendar access works, but this calendar does not support Google Meet.', 'next-class-services' ) );
		}
		$id = bin2hex( random_bytes( 16 ) );
		$path = Google_Calendar::events_path( $cal['id'], $id );
		$start = time() + DAY_IN_SECONDS;
		$body = array( 'id' => $id, 'summary' => 'Next Class API connection test', 'start' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start ) ), 'end' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start + 900 ) ), 'conferenceData' => Google_Calendar::conference() );
		update_option( 'ncs_google_test_event', array( 'calendar' => $cal['id'], 'id' => $id ), false );
		$result = Google_Calendar::request( 'POST', Google_Calendar::events_path( $cal['id'] ) . '?conferenceDataVersion=1&sendUpdates=none', $body );
		if ( ! is_wp_error( $result ) ) {
			$result = Google_Calendar::request( 'PATCH', $path . '?conferenceDataVersion=1&sendUpdates=none', array( 'start' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start + 3600 ) ), 'end' => array( 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start + 4500 ) ) ) );
		}
		$cleanup = Google_Calendar::request( 'DELETE', $path . '?sendUpdates=none' );
		if ( is_wp_error( $cleanup ) && ! Google_Calendar::missing( $cleanup ) ) {
			/* translators: %s: Google event ID */
			return new \WP_Error( 'cleanup', sprintf( __( 'Test cleanup failed. A temporary event may remain (ID: %s). Run the test again to retry cleanup.', 'next-class-services' ), $id ) );
		}
		delete_option( 'ncs_google_test_event' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$status = isset( $result['conferenceData']['createRequest']['status']['statusCode'] ) ? $result['conferenceData']['createRequest']['status']['statusCode'] : '';
		if ( 'failure' === $status ) {
			return new \WP_Error( 'meet', __( 'Event creation, update and deletion passed, but Google Meet generation failed.', 'next-class-services' ) );
		}
		return Google_Calendar::meet_url( $result ) ? __( 'Passed: event created, Google Meet link generated, event rescheduled and test event deleted.', 'next-class-services' ) : __( 'Event creation, rescheduling and deletion passed. Meet creation was requested but was still pending; Meet generation is not yet verified.', 'next-class-services' );
	}

	public static function handle_retry() {
		Admin::authorize( Appointments::CAP, 'ncs_google_retry', '_ncs_nonce' );
		$id = absint( self::input( 'id', $_POST ) );
		Google_Calendar::sync_now( $id );
		$a = Appointment::get( $id );
		Admin::flash( $a && empty( $a['google_sync_error'] ) ? 'success' : 'error', $a && ! empty( $a['google_sync_error'] ) ? $a['google_sync_error'] : __( 'Calendar sync processed.', 'next-class-services' ) );
		Admin::redirect( Admin::url( 'ncs-appointments', array( 'action' => 'view', 'id' => $id ) ) );
	}
}
