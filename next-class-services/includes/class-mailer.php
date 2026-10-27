<?php
/**
 * HTML email composition and sending.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds safe HTML plus plain text emails and sends them with wp_mail().
 */
class Mailer {

	/** Present plain-text field blocks as a readable email-safe details table. */
	private static function format_details( $html ) {
		return preg_replace_callback( '#<p>(.*?)</p>#is', function ( $match ) {
			$lines = preg_split( '#<br\s*/?>\s*#i', $match[1] );
			if ( count( $lines ) < 2 ) { return $match[0]; }
			$rows = array();
			foreach ( $lines as $line ) {
				if ( '' === trim( $line ) ) { continue; }
				if ( ! preg_match( '/^\s*([^<>:\r\n]{1,60}):\s*(.*)$/us', trim( $line ), $parts ) ) { return $match[0]; }
				if ( '' === trim( $parts[2] ) ) { continue; }
				$rows[] = '<tr><th scope="row" style="padding:10px 14px;text-align:left;vertical-align:top;width:34%;font-size:14px;font-weight:600;border-bottom:1px solid #e6e4de;">' . trim( $parts[1] ) . '</th><td style="padding:10px 14px;vertical-align:top;font-size:14px;border-bottom:1px solid #e6e4de;overflow-wrap:anywhere;">' . $parts[2] . '</td></tr>';
			}
			if ( ! $rows ) { return ''; }
			return '<table cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0 24px;border:1px solid #e6e4de;background-color:#faf9f6;line-height:1.6;color:#393939;"><tbody>' . implode( '', $rows ) . '</tbody></table>';
		}, $html );
	}

	/** Keep custom copy while giving plain-text templates a consistent letter structure. */
	private static function letter_parts( $body, array $vars, array $args, $site_name ) {
		$name = '';
		foreach ( array( 'customer_name', 'first_name', 'full_name' ) as $key ) {
			if ( ! empty( $vars[ $key ] ) ) { $name = Helpers::single_line( $vars[ $key ] ); break; }
		}
		/* translators: %s: recipient name */
		$greeting = isset( $args['greeting'] ) ? $args['greeting'] : ( $name ? sprintf( __( 'Dear %s,', 'next-class-services' ), $name ) : __( 'Dear Team,', 'next-class-services' ) );
		$closing = __( 'Kind regards,', 'next-class-services' ) . "\n" . $site_name;
		$body = trim( $body );
		$has_html = (bool) preg_match( '/<[a-z][^>]*>/i', $body );
		if ( ! $has_html ) {
			foreach ( array( __( 'Dear {customer_name},', 'next-class-services' ), __( 'Dear {first_name},', 'next-class-services' ), __( 'Dear Team,', 'next-class-services' ), __( 'Hello,', 'next-class-services' ) ) as $prefix ) {
				if ( 0 === strpos( $body, $prefix . "\n" ) || 0 === strpos( $body, $prefix . "\r\n" ) ) {
					$body = ltrim( substr( $body, strlen( $prefix ) ) ); break;
				}
			}
			$body = preg_replace( '/^(?:Dear\b[^\r\n]*|Hello\b[^\r\n]*|Hi\b[^\r\n]*|Greetings\b[^\r\n]*)\r?\n+\s*/iu', '', $body, 1 );
			$closing_words = 'Kind regards|Best regards|Warm regards|Regards|Sincerely|Yours sincerely|Yours faithfully|' . preg_quote( rtrim( __( 'Kind regards,', 'next-class-services' ), ',!.' ), '/' );
			if ( preg_match( '/(?:^|\r?\n)\s*((?:' . $closing_words . ')[,!\.]?[\s\S]*)$/iu', $body, $match, PREG_OFFSET_CAPTURE ) ) {
				$closing = trim( $match[1][0] );
				$body = rtrim( substr( $body, 0, $match[0][1] ) );
			}
		} else {
			// Authored HTML letters keep their salutation and signature in place.
			$plain = self::to_text( $body );
			if ( preg_match( '/^(Dear|Hello|Hi|Greetings)\b/iu', $plain ) ) { $greeting = ''; }
			if ( preg_match( '/(?:^|\r?\n)\s*(Kind regards|Best regards|Warm regards|Regards|Sincerely|Yours sincerely|Yours faithfully)\b/iu', $plain ) ) { $closing = ''; }
		}
		return array( 'greeting' => $greeting, 'body' => $body, 'closing' => $closing );
	}

	private static function to_text( $html ) {
		$html = preg_replace( '#</p>|</div>|</tr>|</li>#i', "\n\n", (string) $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Replace {placeholders}. Values are escaped for HTML mode.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Key => plain value.
	 * @param string $mode     html|text|subject.
	 * @return string
	 */
	public static function replace( $template, array $vars, $mode = 'html' ) {
		$search  = array();
		$replace = array();
		foreach ( $vars as $key => $value ) {
			$value    = is_scalar( $value ) ? (string) $value : '';
			$search[] = '{' . $key . '}';
			if ( 'html' === $mode ) {
				$replace[] = nl2br( esc_html( $value ) );
			} elseif ( 'text' === $mode ) {
				$replace[] = wp_strip_all_tags( $value );
			} else {
				$replace[] = Helpers::single_line( $value );
			}
		}
		return str_replace( $search, $replace, (string) $template );
	}

	/**
	 * Compose a full message.
	 *
	 * @param string $subject_tpl Subject template.
	 * @param string $body_tpl    Body template (limited HTML allowed).
	 * @param array  $vars        Placeholder values.
	 * @param array  $args        cta_label, cta_url, secondary_label, secondary_url, footer, heading, greeting.
	 * @return array subject, html, text
	 */
	public static function compose( $subject_tpl, $body_tpl, array $vars, array $args = array() ) {
		$subject = Helpers::single_line( self::replace( $subject_tpl, $vars, 'subject' ) );
		if ( '' === $subject ) {
			$subject = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$parts = self::letter_parts( wp_kses_post( (string) $body_tpl ), $vars, $args, $site_name );
		$body_html = self::format_details( wpautop( self::replace( $parts['body'], $vars, 'html' ) ) );
		$body_html = preg_replace( '#<p>([^<>:\r\n]{1,80}):<br\s*/?>\s*#iu', '<p><strong>$1</strong><br>', $body_html );
		$greeting = $parts['greeting'] ? esc_html( self::replace( $parts['greeting'], $vars, 'text' ) ) : '';
		$closing = $parts['closing'] ? wpautop( esc_html( self::replace( $parts['closing'], $vars, 'text' ) ) ) : '';
		$body_html = preg_replace( '/<p>/', '<p style="margin:0 0 18px;line-height:1.75;">', $body_html );
		$body_html = preg_replace( '/<a\s+(?![^>]*\bstyle=)/i', '<a style="color:#393939;text-decoration:underline;" ', $body_html );

		$cta_url   = ! empty( $args['cta_url'] ) ? Helpers::safe_url( self::replace( $args['cta_url'], $vars, 'subject' ) ) : '';
		$cta_label = ! empty( $args['cta_label'] ) ? Helpers::single_line( self::replace( $args['cta_label'], $vars, 'subject' ) ) : '';
		$footer    = ! empty( $args['footer'] ) ? wpautop( self::replace( wp_kses_post( $args['footer'] ), $vars, 'html' ) ) : '';
		$heading   = ! empty( $args['heading'] ) ? Helpers::single_line( self::replace( $args['heading'], $vars, 'subject' ) ) : '';
		$secondary_url = ! empty( $args['secondary_url'] ) ? Helpers::safe_url( self::replace( $args['secondary_url'], $vars, 'subject' ) ) : '';
		$secondary_label = ! empty( $args['secondary_label'] ) ? Helpers::single_line( self::replace( $args['secondary_label'], $vars, 'subject' ) ) : '';

		$html = Helpers::render(
			'emails/layout',
			array(
				'site_name' => $site_name,
				'subject' => $subject,
				'greeting' => $greeting,
				'closing' => $closing,
				'heading'   => $heading,
				'body'      => $body_html,
				'cta_url'   => $cta_url,
				'cta_label' => $cta_label,
				'secondary_url' => $secondary_url,
				'secondary_label' => $secondary_label,
				'footer'    => $footer,
			)
		);

		$text = ( $parts['greeting'] ? self::replace( $parts['greeting'], $vars, 'text' ) . "\n\n" : '' ) . self::to_text( self::replace( $parts['body'], $vars, 'text' ) );
		if ( $cta_url ) {
			$text .= "\n\n" . ( $cta_label ? $cta_label . ': ' : '' ) . $cta_url;
		}
		if ( $secondary_url ) {
			$text .= "\n\n" . ( $secondary_label ? $secondary_label . ': ' : '' ) . $secondary_url;
		}
		if ( $parts['closing'] ) {
			$text .= "\n\n" . self::to_text( self::replace( $parts['closing'], $vars, 'text' ) );
		}
		if ( $footer ) {
			$text .= "\n\n" . html_entity_decode( wp_strip_all_tags( $footer ), ENT_QUOTES, 'UTF-8' );
		}

		return array(
			'subject' => $subject,
			'html'    => $html,
			'text'    => trim( $text ),
		);
	}

	/**
	 * Send a composed message.
	 *
	 * @param string|array $to       Recipient(s), validated.
	 * @param array        $message  Output of compose().
	 * @param array        $options  reply_to, from_name, cc, calendar, calendar_method.
	 * @return bool
	 */
	public static function send( $to, array $message, array $options = array() ) {
		$recipients = array();
		foreach ( (array) $to as $address ) {
			$address = sanitize_email( (string) $address );
			if ( $address && is_email( $address ) ) {
				$recipients[] = $address;
			}
		}
		if ( empty( $recipients ) ) {
			return false;
		}

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$seen = array_map( 'strtolower', $recipients );
		foreach ( Helpers::parse_emails( isset( $options['cc'] ) ? $options['cc'] : '' ) as $cc ) {
			if ( ! in_array( strtolower( $cc ), $seen, true ) ) {
				$headers[] = 'Cc: ' . $cc;
				$seen[] = strtolower( $cc );
			}
		}
		$from_name = Helpers::single_line( ! empty( $options['from_name'] ) ? $options['from_name'] : Helpers::setting( 'from_name', '' ) );
		$from_mail = sanitize_email( (string) Helpers::setting( 'from_email', '' ) );
		if ( '' !== $from_name ) {
			if ( ! $from_mail || ! is_email( $from_mail ) ) {
				$host      = wp_parse_url( home_url(), PHP_URL_HOST );
				$host      = preg_replace( '/^www\./i', '', (string) $host );
				$from_mail = 'wordpress@' . $host;
			}
			$from_name = str_replace( array( '"', '<', '>' ), '', $from_name );
			$headers[] = 'From: "' . $from_name . '" <' . $from_mail . '>';
		} elseif ( $from_mail && is_email( $from_mail ) ) {
			$headers[] = 'From: ' . $from_mail;
		}
		if ( ! empty( $options['reply_to'] ) ) {
			$reply = sanitize_email( (string) $options['reply_to'] );
			if ( $reply && is_email( $reply ) ) {
				$headers[] = 'Reply-To: ' . $reply;
			}
		}

		$text     = $message['text'];
		$calendar = isset( $options['calendar'] ) ? (string) $options['calendar'] : '';
		$method = isset( $options['calendar_method'] ) && 'CANCEL' === $options['calendar_method'] ? 'CANCEL' : 'REQUEST';
		$alt_hook = function ( $phpmailer ) use ( $text, $calendar, $method ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			if ( '' !== $calendar ) {
				$phpmailer->addStringAttachment( $calendar, 'booking.ics', 'base64', 'text/calendar; charset=UTF-8; method=' . $method );
			}
		};
		add_action( 'phpmailer_init', $alt_hook );
		try {
			$sent = wp_mail( $recipients, Helpers::single_line( $message['subject'] ), $message['html'], $headers );
		} finally {
			remove_action( 'phpmailer_init', $alt_hook );
		}

		if ( ! $sent ) {
			Helpers::log( 'wp_mail failed', array( 'subject' => $message['subject'] ) );
		}
		return (bool) $sent;
	}
}
