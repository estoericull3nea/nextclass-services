<?php
/**
 * Assessment emails.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Helpers;
use NextClassServices\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor result email and admin notification.
 */
class Assessment_Email {

	/**
	 * Placeholder documentation.
	 *
	 * @return array
	 */
	public static function placeholders() {
		return array( 'first_name', 'last_name', 'full_name', 'email', 'phone', 'company', 'job_title', 'assessment_name', 'score', 'percentage', 'result_title', 'result_description', 'recommendation', 'programs' );
	}

	/**
	 * Convert stored HTML into readable plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function to_text( $html ) {
		$html = preg_replace( '#</p>|<br\s*/?>|</li>#i', "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/**
	 * Build placeholder values.
	 *
	 * @param array      $config   Config.
	 * @param array      $lead     Lead values keyed by field key.
	 * @param array      $score    Score data.
	 * @param array|null $result   Matched result.
	 * @param array      $programs Program list.
	 * @return array
	 */
	public static function vars( array $config, array $lead, array $score, $result, array $programs ) {
		$first = isset( $lead['first_name'] ) ? $lead['first_name'] : '';
		$last  = isset( $lead['last_name'] ) ? $lead['last_name'] : '';
		$full  = isset( $lead['full_name'] ) && '' !== $lead['full_name'] ? $lead['full_name'] : trim( $first . ' ' . $last );
		if ( '' === $first && '' !== $full ) {
			$parts = explode( ' ', $full, 2 );
			$first = $parts[0];
		}
		return array(
			'first_name'         => $first,
			'last_name'          => $last,
			'full_name'          => $full,
			'email'              => isset( $lead['email'] ) ? $lead['email'] : '',
			'phone'              => isset( $lead['phone'] ) ? $lead['phone'] : '',
			'company'            => isset( $lead['company'] ) ? $lead['company'] : '',
			'job_title'          => isset( $lead['job_title'] ) ? $lead['job_title'] : '',
			'assessment_name'    => '' !== $config['title'] ? $config['title'] : $config['admin_title'],
			'score'              => rtrim( rtrim( number_format( (float) $score['raw'], 2, '.', '' ), '0' ), '.' ),
			'percentage'         => rtrim( rtrim( number_format( (float) $score['percentage'], 1, '.', '' ), '0' ), '.' ),
			'result_title'       => $result ? $result['title'] : '',
			'result_description' => $result ? self::to_text( $result['description'] ) : '',
			'recommendation'     => $result ? self::to_text( $result['recommendation'] ) : '',
			'programs'           => implode( ', ', wp_list_pluck( $programs, 'name' ) ),
		);
	}

	/**
	 * Send the visitor result email.
	 *
	 * @param array      $config   Config.
	 * @param array      $vars     Placeholder values.
	 * @param array|null $result   Matched result (for CTA fallback).
	 * @param string     $to       Recipient.
	 * @return bool
	 */
	public static function send_visitor( array $config, array $vars, $result, $to ) {
		$e = $config['email'];
		if ( empty( $e['enabled'] ) ) {
			return false;
		}
		$cta_label = $e['cta_label'];
		$cta_url   = $e['cta_url'];
		if ( ( '' === $cta_label || '' === $cta_url ) && $result && ! empty( $result['cta_url'] ) ) {
			$cta_label = '' !== $result['cta_label'] ? $result['cta_label'] : __( 'Learn more', 'next-class-services' );
			$cta_url   = $result['cta_url'];
		}
		$message = Mailer::compose(
			$e['subject'],
			trim( $e['intro'] . "\n\n" . $e['body'] ),
			$vars,
			array(
				'heading'   => $e['heading'],
				'cta_label' => $cta_label,
				'cta_url'   => $cta_url,
				'footer'    => $e['footer'],
			)
		);
		return Mailer::send(
			$to,
			$message,
			array(
				'reply_to'  => $e['reply_to'],
				'from_name' => $e['from_name'],
			)
		);
	}

	/**
	 * Send the admin notification.
	 *
	 * @param array $config Config.
	 * @param array $vars   Placeholder values.
	 * @return bool
	 */
	public static function send_admin( array $config, array $vars ) {
		$n = $config['admin_notify'];
		if ( empty( $n['enabled'] ) ) {
			return false;
		}
		$to = Helpers::parse_emails( $n['recipients'] );
		if ( empty( $to ) ) {
			$to = array( Helpers::notification_email() );
		}
		$message = Mailer::compose(
			'' !== $n['subject'] ? $n['subject'] : __( 'New assessment completed', 'next-class-services' ),
			$n['message'],
			$vars,
			array( 'heading' => __( 'New assessment submission', 'next-class-services' ) )
		);
		return Mailer::send( $to, $message, array( 'reply_to' => isset( $vars['email'] ) ? $vars['email'] : '', 'cc' => Helpers::setting( 'notification_cc', '' ) ) );
	}

	/**
	 * Sample values for test emails.
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function sample_vars( array $config ) {
		$results = $config['results'];
		$result  = ! empty( $results ) ? $results[0] : null;
		$programs = $result ? Results::programs_for( $config, $result ) : array();
		return self::vars(
			$config,
			array(
				'first_name' => 'Alex',
				'last_name'  => 'Sample',
				'email'      => 'alex.sample@example.com',
				'phone'      => '555 0100',
				'company'    => 'Sample Company',
				'job_title'  => 'HR Manager',
			),
			array(
				'raw'        => 42,
				'percentage' => 60,
			),
			$result,
			$programs
		);
	}

	/**
	 * Send a test email.
	 *
	 * @param string $kind visitor|admin.
	 * @param array  $config Config.
	 * @param string $to Recipient.
	 * @return bool
	 */
	public static function send_test( $kind, array $config, $to ) {
		$vars   = self::sample_vars( $config );
		$result = ! empty( $config['results'] ) ? $config['results'][0] : null;
		if ( 'admin' === $kind ) {
			$copy                             = $config;
			$copy['admin_notify']['enabled']    = true;
			$copy['admin_notify']['recipients'] = $to;
			return self::send_admin( $copy, $vars );
		}
		$copy                    = $config;
		$copy['email']['enabled'] = true;
		return self::send_visitor( $copy, $vars, $result, $to );
	}
}
