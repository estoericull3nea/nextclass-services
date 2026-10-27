<?php
/** Optional CC regression checks; wp_mail is mocked and no email is sent. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function get_option( $key, $default = false ) { return $default; }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function sanitize_email( $email ) { return filter_var( $email, FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function add_action( $name, $callback ) { $GLOBALS['mail_hooks'][$name] = $callback; }
function remove_action( $name, $callback ) { unset( $GLOBALS['mail_hooks'][$name] ); }
function wp_mail( $to, $subject, $html, $headers ) {
	$mailer = new class {
		public $AltBody = '';
		public $attachments = array();
		public function addStringAttachment( ...$args ) { $this->attachments[] = $args; }
	};
	if ( isset( $GLOBALS['mail_hooks']['phpmailer_init'] ) ) { $GLOBALS['mail_hooks']['phpmailer_init']( $mailer ); }
	$GLOBALS['sent'][] = array_merge( compact( 'to', 'subject', 'html', 'headers' ), array( 'attachments' => $mailer->attachments ) );
	return true;
}
require __DIR__ . '/../includes/class-helpers.php';
require __DIR__ . '/../includes/class-mailer.php';
use NextClassServices\Mailer;
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$message = array( 'subject' => 'Test', 'html' => '<p>Test</p>', 'text' => 'Test' );
Mailer::send( 'admin@example.test', $message );
check( 1 === count( $GLOBALS['sent'][0]['headers'] ), 'No CC when left blank' );
Mailer::send( 'admin@example.test', $message, array( 'cc' => 'team@example.test, manager@example.test; invalid-address', 'reply_to' => 'customer@example.test' ) );
$headers = $GLOBALS['sent'][1]['headers'];
check( in_array( 'Cc: team@example.test', $headers, true ) && in_array( 'Cc: manager@example.test', $headers, true ), 'Valid optional CC addresses are included' );
check( in_array( 'Reply-To: customer@example.test', $headers, true ), 'Existing reply-to is preserved' );
check( 2 === count( array_filter( $headers, function ( $header ) { return 0 === strpos( $header, 'Cc:' ); } ) ), 'Invalid addresses are excluded' );
Mailer::send( 'admin@example.test', $message, array( 'cc' => 'ADMIN@example.test, team@example.test, TEAM@example.test' ) );
$cc = array_values( array_filter( $GLOBALS['sent'][2]['headers'], function ( $header ) { return 0 === strpos( $header, 'Cc:' ); } ) );
check( array( 'Cc: team@example.test' ) === $cc, 'Deduplicate CC and primary recipients without regard to case' );
check( false === Mailer::send( 'invalid', $message, array( 'cc' => 'team@example.test' ) ), 'CC does not bypass missing primary recipients' );
check( 3 === count( $GLOBALS['sent'] ), 'Invalid primary recipient never triggers a send' );
	Mailer::send( 'admin@example.test', $message, array( 'calendar' => "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nEND:VCALENDAR\r\n", 'calendar_method' => 'REQUEST' ) );
	check( 'booking.ics' === $GLOBALS['sent'][3]['attachments'][0][1] && false !== strpos( $GLOBALS['sent'][3]['attachments'][0][3], 'method=REQUEST' ), 'Admin calendar invitation is attached through PHPMailer' );
	check( empty( $GLOBALS['mail_hooks'] ), 'Calendar mail hook is removed after sending' );
	Mailer::send( 'admin@example.test', $message );
	check( empty( $GLOBALS['sent'][4]['attachments'] ), 'Calendar attachments do not leak into unrelated messages' );
echo "Optional CC mailer checks passed. No email was sent.\n";
