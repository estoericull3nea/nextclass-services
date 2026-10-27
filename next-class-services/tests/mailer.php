<?php
/** Optional CC regression checks; wp_mail is mocked and no email is sent. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function get_option( $key, $default = false ) { return $default; }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function sanitize_email( $email ) { return filter_var( $email, FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function add_action( ...$args ) {}
function remove_action( ...$args ) {}
function wp_mail( $to, $subject, $html, $headers ) {
	$GLOBALS['sent'][] = compact( 'to', 'subject', 'html', 'headers' );
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
echo "Optional CC mailer checks passed. No email was sent.\n";
