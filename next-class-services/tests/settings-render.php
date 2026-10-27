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
	function get_option( $key, $default = false ) { return $key === 'admin_email' ? 'admin@example.test' : $default; }
	function wp_parse_args( $in, $defaults ) { return array_merge( $defaults, $in ); }
	function wp_timezone() { return new \DateTimeZone( 'UTC' ); }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
	function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">'; }
	function wp_create_nonce( $action ) { return 'test-nonce'; }
	function wp_kses( $text, $allowed ) { return $text; }
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
	check( 1 === $xpath->query( '//*[@role="tabpanel" and not(@hidden)]' )->length, 'Only one panel is initially visible' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking" and not(@hidden)]' )->length, 'Booking is the default panel' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking"]//*[@id="ncs-google-calendar"]' )->length, 'Google Calendar is grouped with booking' );
	$controls = $xpath->query( '//input[starts-with(@name,"s[")] | //select[starts-with(@name,"s[")] | //textarea[starts-with(@name,"s[")]' );
	check( 23 === $controls->length, 'All existing configuration fields remain present' );
	foreach ( $controls as $control ) {
		check( 'ncs-settings-form' === $control->getAttribute( 'form' ), 'Shared save form owns ' . $control->getAttribute( 'name' ) );
	}
	check( 0 === $xpath->query( '//form//form' )->length, 'Independent AJAX forms are never nested' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy"]//*[@data-ncs-cleanup]' )->length, 'Cleanup is grouped with privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy"]//input[@name="s[delete_on_uninstall]"]' )->length, 'Uninstall data setting is grouped with privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-advanced"]//input[@name="s[debug_log]"]' )->length, 'Debugging is grouped with advanced settings' );
	check( 1 === $xpath->query( '//button[@form="ncs-settings-form"]' )->length, 'Sticky save button submits the shared form' );
	list( $xpath ) = render_page( 'privacy' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-privacy" and not(@hidden)]' )->length, 'A category can be opened directly by URL' );
	list( $xpath ) = render_page( 'unknown' );
	check( 1 === $xpath->query( '//*[@id="ncs-settings-panel-booking" and not(@hidden)]' )->length, 'Invalid category falls back to Booking' );
	echo "Settings rendering passed: categories, field ownership, independent forms and direct URLs.\n";
}
