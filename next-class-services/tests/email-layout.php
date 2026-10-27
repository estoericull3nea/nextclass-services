<?php
/** Exercise actual email send paths with WordPress and delivery mocked. No email is sent. */
namespace {
	define( 'ABSPATH', __DIR__ ); define( 'NCS_PATH', str_replace( '\\', '/', dirname( __DIR__ ) ) . '/' );
	function __( $text, $domain = '' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return esc_html( $text ); }
	function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
	function esc_url( $url ) { return esc_html( $url ); }
	function esc_url_raw( $url ) { return $url; }
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
	function wp_kses_post( $text ) { return strip_tags( $text, '<p><br><strong><em><a><ul><ol><li><h2><h3><table><tbody><tr><th><td><div>' ); }
	function wpautop( $text ) {
		if ( preg_match( '/<(p|table|div)\b/i', $text ) ) { return $text; }
		$blocks = preg_split( '/\r?\n\s*\r?\n/', trim( $text ) );
		return implode( "\n", array_map( function ( $block ) { return '<p>' . preg_replace( '/\r?\n/', "<br />\n", $block ) . '</p>'; }, $blocks ) );
	}
	function wp_specialchars_decode( $text, $flags ) { return html_entity_decode( $text, $flags, 'UTF-8' ); }
	function get_bloginfo( $key ) { return 'language' === $key ? 'en-US' : 'Next Class'; }
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
	function untrailingslashit( $text ) { return rtrim( $text, '/' ); }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
	function get_option( $key, $default = false ) { return array( 'admin_email' => 'admin@example.test', 'date_format' => 'F j, Y', 'time_format' => 'g:i a' )[ $key ] ?? $default; }
	function wp_parse_args( $in, $defaults ) { return array_merge( $defaults, $in ); }
	function wp_timezone() { return new \DateTimeZone( 'UTC' ); }
	function wp_date( $format, $timestamp, $timezone ) { return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format ); }
	function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
	function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function add_action( $name, $callback ) { $GLOBALS['hooks'][ $name ] = $callback; }
	function remove_action( $name, $callback ) { unset( $GLOBALS['hooks'][ $name ] ); }
	function wp_mail( $to, $subject, $html, $headers ) {
		$mailer = new class { public $AltBody = ''; public $attachments = array(); public function addStringAttachment( ...$args ) { $this->attachments[] = $args; } };
		if ( isset( $GLOBALS['hooks']['phpmailer_init'] ) ) { $GLOBALS['hooks']['phpmailer_init']( $mailer ); }
		$GLOBALS['sent'][] = compact( 'to', 'subject', 'html', 'headers', 'mailer' ); return true;
	}
}
namespace NextClassServices {
	// PHP's Windows ACL probe reports false in this sandbox even though include can read the fixtures.
	function is_readable( $file ) { return \file_exists( $file ); }
}
namespace NextClassServices\Booking {
	class Appointment {
		public static function statuses() { return array( 'confirmed' => 'Confirmed', 'pending' => 'Pending', 'cancelled' => 'Cancelled' ); }
		public static function tz_label( $tz, $start ) { return $tz; }
		public static function manage_url( $appt, $token ) { return 'https://example.test/booking?ncs_manage=test-only'; }
		public static function update( $id, $data ) { return true; }
	}
}
namespace {
	require __DIR__ . '/../includes/class-helpers.php'; require __DIR__ . '/../includes/class-mailer.php';
	require __DIR__ . '/../modules/booking/class-booking-service.php'; require __DIR__ . '/../modules/booking/class-booking-email.php';
	require __DIR__ . '/../modules/booking/class-admin-calendar-invite.php';
	require __DIR__ . '/../modules/assessments/class-leads.php'; require __DIR__ . '/../modules/assessments/class-assessment.php'; require __DIR__ . '/../modules/assessments/class-assessment-email.php';
	use NextClassServices\Mailer;
	use NextClassServices\Booking\Booking_Service;
	use NextClassServices\Booking\Booking_Email;
	use NextClassServices\Booking\Admin_Calendar_Invite;
	use NextClassServices\Assessments\Assessment;
	use NextClassServices\Assessments\Assessment_Email;
	function check( $value, $message ) { if ( ! $value ) { throw new \RuntimeException( $message ); } }
	$cfg = Booking_Service::defaults(); $cfg['title'] = 'HR consultation';
	$appt = array( 'id' => 1, 'service_title' => 'HR consultation', 'customer_name' => 'Alex Morgan', 'customer_email' => 'alex@example.test', 'customer_phone' => '', 'company' => 'Example Co', 'notes' => '', 'timezone' => 'Asia/Shanghai', 'start_utc' => '2026-11-03 02:00:00', 'end_utc' => '2026-11-03 02:30:00', 'status' => 'confirmed', 'google_meet_url' => 'https://meet.google.com/abc-defg-hij' );
	$GLOBALS['sent'] = array();
	Booking_Email::send_confirmation( $appt, $cfg, 'test-only' );
	Booking_Email::send_admin_new( $appt, $cfg );
	$cancelled = array_merge( $appt, array( 'status' => 'cancelled' ) );
	Booking_Email::send_cancellation( $cancelled, $cfg );
	Booking_Email::send_reschedule( $appt, array_merge( $appt, array( 'start_utc' => '2026-11-02 02:00:00' ) ), $cfg, 'test-only' );
	$assessment = Assessment::defaults(); $assessment['admin_notify']['enabled'] = true; $assessment['email']['enabled'] = true;
	$vars = array( 'first_name' => 'Alex', 'full_name' => 'Alex Morgan', 'assessment_name' => 'HR Diagnostic', 'result_title' => 'Developing', 'result_description' => 'Your team has a foundation to build on.', 'recommendation' => 'Review the next steps with your leadership team.', 'programs' => 'Leadership Development', 'score' => '25', 'percentage' => '50', 'email' => 'alex@example.test', 'phone' => '', 'company' => 'Example Co', 'job_title' => '' );
	Assessment_Email::send_visitor( $assessment, $vars, null, 'alex@example.test' );
	Assessment_Email::send_admin( $assessment, $vars );
	$meta = array( 'uid' => 'test@google.com', 'organizer' => 'organizer@example.test', 'sequence' => 0, 'url' => 'https://calendar.google.com/test' );
	Admin_Calendar_Invite::send( $appt, $meta ); Admin_Calendar_Invite::send( $cancelled, $meta, true );
	check( 10 === count( $GLOBALS['sent'] ), 'Exercise every customer, admin, assessment and calendar email path' );
	foreach ( $GLOBALS['sent'] as $index => $mail ) {
		if ( '' === $mail['html'] ) { throw new \RuntimeException( 'Empty HTML: ' . NCS_PATH . ' readable=' . (int) is_readable( NCS_PATH . 'templates/emails/layout.php' ) ); }
		$plain = html_entity_decode( strip_tags( $mail['html'] ), ENT_QUOTES, 'UTF-8' );
		check( 1 === substr_count( $plain, 'Dear ' ), 'Exactly one greeting in email ' . $index . ': ' . substr( $plain, 0, 800 ) );
		check( 1 === substr_count( $plain, 'Kind regards,' ), 'Exactly one closing in email ' . $index );
		check( false !== strpos( $mail['mailer']->AltBody, 'Dear ' ) && false !== strpos( $mail['mailer']->AltBody, 'Kind regards,' ), 'Plain-text alternative retains the letter structure' );
		if ( 6 !== $index ) { check( false !== strpos( $mail['html'], 'scope="row"' ), 'Message details use a readable table in email ' . $index ); }
		check( false === strpos( $mail['html'], '{customer_name}' ), 'No unresolved recipient placeholder' );
		if ( array( 'admin@example.test' ) === $mail['to'] ) { check( false !== strpos( $plain, 'Dear Team,' ), 'Admin emails address the team' ); }
	}
	check( false !== strpos( $GLOBALS['sent'][0]['html'], 'Join Google Meet' ), 'Meet link has a dedicated action area' );
	check( false === strpos( $GLOBALS['sent'][2]['html'], 'meet.google.com' ), 'Cancellation email does not offer an active meeting link' );
	check( count( $GLOBALS['sent'][8]['mailer']->attachments ) === 1, 'Letter layout preserves the admin calendar attachment' );
	$custom = Mailer::compose( 'Custom', "Hello,\n\nPlease retain this custom instruction.\n\nBest regards,\nThe Operations Team", array(), array( 'greeting' => 'Dear Team,' ) );
	check( false !== strpos( $custom['html'], 'Please retain this custom instruction.' ) && false !== strpos( $custom['html'], 'The Operations Team' ), 'Keep custom message content and signature' );
	check( 1 === substr_count( $custom['text'], 'Best regards,' ) && false === strpos( $custom['text'], 'Kind regards,' ), 'Avoid adding a second signature' );
	$safe = Mailer::compose( 'Your details', "Name: {customer_name}\nEmail: {customer_email}", array( 'customer_name' => '<img src=x onerror=alert(1)>', 'customer_email' => 'alex@example.test' ) );
	check( false === strpos( $safe['html'], '<img' ), 'Untrusted placeholder values cannot inject HTML' );
	$authored = Mailer::compose( 'Authored letter', '<p>Dear Colleague,</p><p>Please keep this authored content.</p><p>Kind regards,<br>Operations</p>', array() );
	check( 1 === substr_count( $authored['text'], 'Dear ' ) && 1 === substr_count( $authored['text'], 'Kind regards,' ), 'Authored HTML letters keep their greeting and signature without duplicates' );
	$pending = array_merge( $appt, array( 'status' => 'pending' ) );
	Booking_Email::send_confirmation( $pending, $cfg, 'test-only' );
	check( false !== strpos( end( $GLOBALS['sent'] )['subject'], 'Booking request' ), 'Pending appointments are not described as confirmed in the default subject' );
	check( false !== strpos( end( $GLOBALS['sent'] )['mailer']->AltBody, 'not confirmed yet' ), 'Pending request email explains staff review' );
	Booking_Email::send_approval( $appt, $cfg );
	check( false !== strpos( end( $GLOBALS['sent'] )['subject'], 'Booking approved' ), 'Approval sends a dedicated client approval letter' );
	if ( ! empty( $argv[1] ) ) { file_put_contents( $argv[1], $GLOBALS['sent'][0]['html'] ); }
	echo "Professional email checks passed across 10 email paths. No email was sent.\n";
}
