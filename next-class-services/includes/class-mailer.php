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
	 * @param array  $args        cta_label, cta_url, footer, heading.
	 * @return array subject, html, text
	 */
	public static function compose( $subject_tpl, $body_tpl, array $vars, array $args = array() ) {
		$subject = Helpers::single_line( self::replace( $subject_tpl, $vars, 'subject' ) );
		if ( '' === $subject ) {
			$subject = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}

		$body_safe = wp_kses_post( (string) $body_tpl );
		$body_html = wpautop( self::replace( $body_safe, $vars, 'html' ) );

		$cta_url   = ! empty( $args['cta_url'] ) ? Helpers::safe_url( self::replace( $args['cta_url'], $vars, 'subject' ) ) : '';
		$cta_label = ! empty( $args['cta_label'] ) ? Helpers::single_line( self::replace( $args['cta_label'], $vars, 'subject' ) ) : '';
		$footer    = ! empty( $args['footer'] ) ? wpautop( self::replace( wp_kses_post( $args['footer'] ), $vars, 'html' ) ) : '';
		$heading   = ! empty( $args['heading'] ) ? Helpers::single_line( self::replace( $args['heading'], $vars, 'subject' ) ) : '';

		$html = Helpers::render(
			'emails/layout',
			array(
				'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'heading'   => $heading,
				'body'      => $body_html,
				'cta_url'   => $cta_url,
				'cta_label' => $cta_label,
				'footer'    => $footer,
			)
		);

		$text = wp_strip_all_tags( self::replace( $body_safe, $vars, 'text' ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		if ( $cta_url ) {
			$text .= "\n\n" . ( $cta_label ? $cta_label . ': ' : '' ) . $cta_url;
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
	 * @param array        $options  reply_to, from_name.
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
		$alt_hook = function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		};
		add_action( 'phpmailer_init', $alt_hook );
		$sent = wp_mail( $recipients, Helpers::single_line( $message['subject'] ), $message['html'], $headers );
		remove_action( 'phpmailer_init', $alt_hook );

		if ( ! $sent ) {
			Helpers::log( 'wp_mail failed', array( 'subject' => $message['subject'] ) );
		}
		return (bool) $sent;
	}
}
