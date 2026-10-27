<?php
/** Render Settings without WordPress to check category layout and HTML form ownership. */
namespace {
	define( 'ABSPATH', __DIR__ );
	function __( $text, $domain = '' ) { return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_textarea( $text ) { return esc_attr( $text ); }
	function esc_url( $text ) { return esc_attr( $text ); }
	function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
	function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
	function current_user_can( $cap ) { return true; }
	function get_option( $key, $default = false ) { return $GLOBALS['render_options'][$key] ?? ( $key === 'admin_email' ? 'admin@example.test' : $default ); }
	function wp_parse_args( $in, $defaults ) { return array_merge( $defaults, $in ); }
	function wp_timezone() { return new \DateTimeZone( $GLOBALS['render_timezone'] ?? 'UTC' ); }
	function wp_date( $format, $timestamp, $timezone ) { return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format ); }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
	function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">'; }
	function wp_create_nonce( $action ) { return 'test-nonce'; }
	function wp_kses( $text, $allowed ) { return $text; }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function checked( $value, $expected, $echo = true ) { return $value === $expected ? ' checked' : ''; }
	function selected( $value, $expected, $echo = true ) { return $value === $expected ? ' selected' : ''; }
	function disabled( $value, $expected, $echo = true ) { return $value === $expected ? ' disabled' : ''; }
}
namespace NextClassServices {
	class Capabilities { public static function all() { return array( 'ncs_manage_settings' => 'Manage settings' ); } }
}
namespace NextClassServices\Admin {
	class Admin {
		public static function get( $key, $default = '' ) { return $GLOBALS['page_args'][$key] ?? $default; }
		public static function header( ...$args ) { echo '<div class="ncs-admin">'; }
		public static function footer() { echo '</div>'; }
	}
}
namespace {
	require __DIR__ . '/../includes/class-helpers.php';
	require __DIR__ . '/../admin/class-form.php';
	require __DIR__ . '/../admin/class-settings.php';
	require __DIR__ . '/../modules/booking/class-google-calendar.php';
	require __DIR__ . '/../admin/class-google-calendar-settings.php';
	require __DIR__ . '/../modules/booking/class-booking-service.php';
	function check( $value, $message ) { if ( ! $value ) { throw new \RuntimeException( $message ); } }
	function render_page( $tab = null ) {
		$GLOBALS['page_args'] = null === $tab ? array() : array( 'tab' => $tab );
		ob_start();
		\NextClassServices\Admin\Settings::render();
		$html = ob_get_clean();
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
		return array( new \DOMXPath( $doc ), $html );
	}
	list( $xpath, $html ) = render_page();
	check( 7 === $xpath->query( '//*[@role="tab"]' )->length, 'Seven settings categories' );
	check( 7 === $xpath->query( '//*[@role="tabpanel"]' )->length, 'One panel per category' );
	check( 10 === $xpath->query( '//dialog[contains(@class,"ncs-guide-dialog")]' )->length, 'Every remaining settings card has its own guide, including Google Calendar' );
	foreach ( $xpath->query( '//*[@data-ncs-dialog-open]' ) as $trigger ) {
		$id = $trigger->getAttribute( 'data-ncs-dialog-open' );
		check( 1 === $xpath->query( '//dialog[@id="' . $id . '"]' )->length, 'Every guide icon opens an existing modal' );
	}
	check( 1 === $xpath->query( '//*[@role="tabpanel" and not(@hidden)]' )->length, 'Only one panel is initially visible' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking" and not(@hidden)]' )->length, 'Booking is the default panel' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking"]//*[@id="ncs-google-calendar"]' )->length, 'Google Calendar is grouped with booking' );
	$controls = $xpath->query( '//input[starts-with(@name,"s[")] | //select[starts-with(@name,"s[")] | //textarea[starts-with(@name,"s[")]' );
	check( 24 === $controls->length, 'All configuration fields and optional CC remain present' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-notifications"]//input[@name="s[notification_cc]" and not(@required)]' )->length, 'CC is optional and grouped with notifications' );
	foreach ( $controls as $control ) {
		check( 'ncs-settings-form' === $control->getAttribute( 'form' ), 'Shared save form owns ' . $control->getAttribute( 'name' ) );
	}
	check( 0 === $xpath->query( '//*[contains(concat(" ",normalize-space(@class)," ")," ncs-field ")]//p[contains(@class,"ncs-help") and not(ancestor::*[@id="ncs-google-calendar"])]' )->length, 'Detailed field descriptions moved from settings fields into guides' );
	check( 1 === $xpath->query( '//dialog[@id="ncs-settings-guide-notifications"]//*[text()="CC (optional)"]' )->length, 'The email guide explains optional CC' );
	check( 1 === $xpath->query( '//dialog[@id="ncs-settings-guide-security"]//*[text()="Trust proxy headers"]' )->length, 'The security guide retains proxy guidance' );
	check( 0 === $xpath->query( '//dialog[@id="ncs-settings-guide-roles" or @id="ncs-settings-guide-stored-data"]' )->length, 'Removed sections have no remaining guide modals' );
	check( 0 === $xpath->query( '//form//form' )->length, 'Independent AJAX forms are never nested' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy"]//*[@data-ncs-cleanup]' )->length, 'Cleanup is grouped with privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy"]//input[@name="s[delete_on_uninstall]"]' )->length, 'Uninstall data setting is grouped with privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-advanced"]//input[@name="s[debug_log]"]' )->length, 'Debugging is grouped with advanced settings' );
	check( 1 === $xpath->query( '//button[@form="ncs-settings-form"]' )->length, 'Sticky save button submits the shared form' );
	list( $xpath ) = render_page( 'privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy" and not(@hidden)]' )->length, 'A category can be opened directly by URL' );
	list( $xpath ) = render_page( 'unknown' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking" and not(@hidden)]' )->length, 'Invalid category falls back to Booking' );
	$GLOBALS['render_timezone'] = 'Asia/Shanghai';
	$GLOBALS['render_options']['ncs_google_test_result'] = array( 'date' => '2026-10-27 14:27:30', 'message' => 'Passed' );
	$test = \NextClassServices\Admin\Google_Calendar_Settings::test_result();
	check( 'Oct 27, 2026 2:27 pm' === $test['dateLabel'], 'Format legacy local timestamps without a timezone shift' );
	$GLOBALS['render_options']['ncs_google_test_result']['timestamp'] = ( new \DateTimeImmutable( '2026-10-27 14:27:30', wp_timezone() ) )->getTimestamp();
	$GLOBALS['render_options']['date_format'] = 'd/m/Y';
	$GLOBALS['render_options']['time_format'] = 'H:i';
	$test = \NextClassServices\Admin\Google_Calendar_Settings::test_result();
	check( '27/10/2026 14:27' === $test['dateLabel'], 'Respect WordPress date/time format for stored timestamps' );
	$method = new \ReflectionMethod( \NextClassServices\Booking\Booking_Service::class, 'sanitize_fields' );
	if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
	$fields = $method->invoke( null, array( 'name' => array( 'placeholder' => '<b>Your name</b>' ), 'email' => array( 'placeholder' => '' ) ), array( array( 'key' => 'custom_team', 'label' => 'Team', 'type' => 'select', 'placeholder' => '<b>Choose a team</b>', 'options' => "People\nOperations" ) ) );
	check( 'Your name' === $fields[0]['placeholder'], 'Sanitize configurable built-in placeholders' );
	check( '' === $fields[1]['placeholder'], 'Allow an explicitly blank placeholder' );
	check( 'Choose a team' === $fields[6]['placeholder'], 'Custom dropdown placeholders are sanitized and saved' );
	$config = \NextClassServices\Booking\Booking_Service::defaults();
	$config['native']['fields'] = $fields;
	$public = \NextClassServices\Booking\Booking_Service::public_config( 1, $config );
	check( 'Your name' === $public['fields'][0]['placeholder'], 'Public field configuration exposes the saved placeholder' );
	$config['native']['fields'] = \NextClassServices\Booking\Booking_Service::default_fields();
	$public = \NextClassServices\Booking\Booking_Service::public_config( 1, $config );
	check( 'Your full name' === $public['fields'][0]['placeholder'], 'Legacy configurations receive a useful default placeholder' );
	echo "Settings rendering passed: categories, field ownership, independent forms and direct URLs.\n";
}
