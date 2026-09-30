<?php
/**
 * Assessment submission endpoint.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Front;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Assessment_Email;
use NextClassServices\Assessments\Conditional_Logic;
use NextClassServices\Assessments\Leads;
use NextClassServices\Assessments\Question;
use NextClassServices\Assessments\Results;
use NextClassServices\Assessments\Scoring;
use NextClassServices\Helpers;
use NextClassServices\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates a submitted assessment on the server and produces the result.
 * Nothing the browser calculates is trusted.
 */
class Assessment_Handler {

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function submit( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		$request->set_param( 'website', isset( $body['website'] ) ? $body['website'] : '' );

		$guard = Front::guard( $request, 'assessment_submit', 40, 600 );
		if ( $guard ) {
			return $guard;
		}

		$id   = isset( $body['id'] ) ? absint( $body['id'] ) : 0;
		$data = $id ? Assessment::get( $id ) : null;
		if ( ! $data ) {
			return Front::error( 'not_found', __( 'This assessment is not available.', 'next-class-services' ), 404 );
		}

		$preview = false;
		if ( 'publish' !== $data['status'] || ! empty( $body['preview'] ) ) {
			$pn = isset( $body['preview_nonce'] ) ? sanitize_text_field( (string) $body['preview_nonce'] ) : '';
			if ( ! Front::can_preview( 'ncs_manage_assessments' ) || ! wp_verify_nonce( $pn, 'ncs_preview_' . $id ) ) {
				return Front::error( 'not_found', __( 'This assessment is not available.', 'next-class-services' ), 404 );
			}
			$preview = true;
		}

		$elapsed = isset( $body['elapsed'] ) ? (int) $body['elapsed'] : 0;
		if ( ! $preview && $elapsed < 2000 ) {
			return Front::error( 'too_fast', __( 'Please take a moment to review your answers and try again.', 'next-class-services' ), 400 );
		}

		$config    = $data['config'];
		$questions = array_values(
			array_filter(
				$config['questions'],
				function ( $q ) {
					return ! empty( $q['enabled'] );
				}
			)
		);

		// Normalize every submitted answer first, then decide visibility.
		$raw_answers = isset( $body['answers'] ) && is_array( $body['answers'] ) ? $body['answers'] : array();
		$answers     = array();
		$errors      = array();
		foreach ( $questions as $q ) {
			$raw                 = isset( $raw_answers[ $q['id'] ] ) ? $raw_answers[ $q['id'] ] : '';
			list( $val, $error ) = Question::normalize_answer( $q, $raw );
			$answers[ $q['id'] ] = $val;
			if ( '' !== $error ) {
				$errors['answers'][ $q['id'] ] = $error;
			}
		}
		$visible = Conditional_Logic::visible_map( $questions, $answers );

		foreach ( $questions as $q ) {
			if ( empty( $visible[ $q['id'] ] ) ) {
				unset( $errors['answers'][ $q['id'] ] );
				continue;
			}
			if ( ! empty( $q['required'] ) && Question::is_empty( $answers[ $q['id'] ] ) && empty( $errors['answers'][ $q['id'] ] ) ) {
				$errors['answers'][ $q['id'] ] = __( 'This question is required.', 'next-class-services' );
			}
		}

		// Lead capture.
		$lead    = array();
		$consent = array(
			'privacy'   => 0,
			'marketing' => 0,
		);
		$lead_cfg = $config['lead'];
		if ( $lead_cfg['enabled'] ) {
			list( $lead, $lead_errors ) = Leads::validate( $lead_cfg['fields'], isset( $body['lead'] ) ? $body['lead'] : array() );
			if ( ! empty( $lead_errors ) ) {
				$errors['lead'] = $lead_errors;
			}
			$c_in = isset( $body['consent'] ) && is_array( $body['consent'] ) ? $body['consent'] : array();
			foreach ( array( 'privacy', 'marketing' ) as $type ) {
				$cc = $lead_cfg[ $type ];
				if ( empty( $cc['enabled'] ) ) {
					continue;
				}
				$consent[ $type ] = ! empty( $c_in[ $type ] ) && filter_var( $c_in[ $type ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
				if ( ! empty( $cc['required'] ) && ! $consent[ $type ] ) {
					$errors['consent'][ $type ] = __( 'Please tick this box to continue.', 'next-class-services' );
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return Front::error( 'validation', __( 'Please review the highlighted fields and try again.', 'next-class-services' ), 422, array( 'errors' => $errors ) );
		}

		// Score and result.
		$score   = Scoring::calculate( $questions, $answers, $visible );
		$result  = Results::match( $config, $score );
		$programs = $result ? Results::programs_for( $config, $result ) : array();

		$submission_id = 0;
		if ( ! $preview ) {
			$submission_id = self::store( $id, $config, $questions, $answers, $visible, $score, $result, $programs, $lead, $consent, isset( $body['page_url'] ) ? (string) $body['page_url'] : '' );
			if ( ! $submission_id ) {
				return Front::error( 'store_failed', __( 'We could not save your submission. Please try again shortly.', 'next-class-services' ), 500 );
			}
			Tracker::record( 'assessment_complete', 'assessment', $id, $result ? $result['id'] : '' );
			if ( ! empty( $lead['email'] ) ) {
				Tracker::record( 'lead_captured', 'assessment', $id, '' );
			}
			self::notify( $submission_id, $config, $lead, $score, $result, $programs );
		}

		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'result' => self::public_result( $config, $score, $result, $programs ),
				'id'     => $submission_id,
			),
			200
		);
	}

	/**
	 * Persist the submission.
	 *
	 * @param int        $id        Assessment id.
	 * @param array      $config    Config.
	 * @param array      $questions Enabled questions.
	 * @param array      $answers   Normalized answers.
	 * @param array      $visible   Visible map.
	 * @param array      $score     Score.
	 * @param array|null $result    Result.
	 * @param array      $programs  Programs.
	 * @param array      $lead      Lead values.
	 * @param array      $consent   Consent flags.
	 * @param string     $page_url  Page URL.
	 * @return int
	 */
	private static function store( $id, array $config, array $questions, array $answers, array $visible, array $score, $result, array $programs, array $lead, array $consent, $page_url ) {
		$builtin = array( 'first_name', 'last_name', 'full_name', 'email', 'phone', 'company', 'job_title', 'company_size' );
		$row     = array(
			'assessment_id'        => (int) $id,
			'assessment_title'     => Helpers::truncate( '' !== $config['admin_title'] ? $config['admin_title'] : $config['title'], 250 ),
			'raw_score'            => $score['raw'],
			'max_score'            => $score['max'],
			'percentage'           => $score['percentage'],
			'result_id'            => $result ? $result['id'] : '',
			'result_title'         => $result ? Helpers::truncate( $result['title'], 250 ) : '',
			'recommended_programs' => wp_json_encode( array_values( wp_list_pluck( $programs, 'name' ) ) ),
			'consent_privacy'      => (int) $consent['privacy'],
			'consent_marketing'    => (int) $consent['marketing'],
			'page_url'             => Helpers::truncate( esc_url_raw( $page_url ), 480 ),
		);
		foreach ( $builtin as $key ) {
			$row[ $key ] = isset( $lead[ $key ] ) ? $lead[ $key ] : '';
		}
		$custom = array();
		foreach ( $config['lead']['fields'] as $f ) {
			if ( ! empty( $f['custom'] ) && ! empty( $f['enabled'] ) && isset( $lead[ $f['key'] ] ) && '' !== $lead[ $f['key'] ] ) {
				$custom[] = array(
					'label' => $f['label'],
					'value' => $lead[ $f['key'] ],
				);
			}
		}
		$row['custom_fields'] = empty( $custom ) ? null : wp_json_encode( $custom );

		if ( $consent['privacy'] || $consent['marketing'] ) {
			$row['consent_at']       = Helpers::now_utc();
			$row['consent_snapshot'] = wp_json_encode(
				array(
					'privacy'     => $consent['privacy'] ? wp_strip_all_tags( $config['lead']['privacy']['text'] ) : '',
					'marketing'   => $consent['marketing'] ? wp_strip_all_tags( $config['lead']['marketing']['text'] ) : '',
					'privacy_url' => $config['lead']['privacy_url'],
				)
			);
		}

		$answer_rows = array();
		foreach ( $questions as $q ) {
			if ( empty( $visible[ $q['id'] ] ) ) {
				continue;
			}
			$val = $answers[ $q['id'] ];
			if ( Question::is_empty( $val ) ) {
				continue;
			}
			list( $s, $m ) = isset( $score['per_question'][ $q['id'] ] ) ? $score['per_question'][ $q['id'] ] : array( 0, 0 );
			$answer_rows[] = array(
				'question_id'   => $q['id'],
				'question_text' => $q['text'],
				'question_type' => $q['type'],
				'answer_value'  => is_array( $val ) ? implode( ',', $val ) : (string) $val,
				'answer_label'  => Question::answer_label( $q, $val ),
				'score'         => $s,
				'max_score'     => $m,
			);
		}
		return Leads::create( $row, $answer_rows );
	}

	/**
	 * Send emails.
	 *
	 * @param int        $submission_id Submission id.
	 * @param array      $config        Config.
	 * @param array      $lead          Lead values.
	 * @param array      $score         Score.
	 * @param array|null $result        Result.
	 * @param array      $programs      Programs.
	 */
	private static function notify( $submission_id, array $config, array $lead, array $score, $result, array $programs ) {
		global $wpdb;
		$vars = Assessment_Email::vars( $config, $lead, $score, $result, $programs );
		$sent = false;
		if ( ! empty( $lead['email'] ) && $config['email']['enabled'] ) {
			$sent = Assessment_Email::send_visitor( $config, $vars, $result, $lead['email'] );
		}
		Assessment_Email::send_admin( $config, $vars );
		if ( $sent ) {
			$wpdb->update( \NextClassServices\Database::table( 'submissions' ), array( 'email_sent' => 1 ), array( 'id' => $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Build the payload shown to the visitor.
	 *
	 * @param array      $config   Config.
	 * @param array      $score    Score.
	 * @param array|null $result   Result.
	 * @param array      $programs Programs.
	 * @return array
	 */
	private static function public_result( array $config, array $score, $result, array $programs ) {
		$out = array(
			'heading'    => $config['result_heading'],
			'title'      => '',
			'description' => '',
			'recommendation' => '',
			'programs'   => array(),
			'cta'        => null,
			'cta2'       => null,
			'score'      => null,
			'percentage' => null,
			'completion' => $config['completion_message'],
			'assessment' => '' !== $config['title'] ? $config['title'] : $config['admin_title'],
		);
		if ( $config['show_score'] ) {
			$out['score'] = array(
				'raw' => (float) $score['raw'],
				'max' => (float) $score['max'],
			);
		}
		if ( $config['show_percentage_score'] ) {
			$out['percentage'] = round( (float) $score['percentage'] );
		}
		if ( ! $result ) {
			return $out;
		}
		$out['title']       = $result['title'];
		$out['description'] = $result['description'];
		if ( $config['show_recommendation'] ) {
			$out['recommendation'] = $result['recommendation'];
		}
		if ( $config['show_programs'] ) {
			foreach ( $programs as $p ) {
				$out['programs'][] = array(
					'name'        => $p['name'],
					'description' => $p['description'],
					'url'         => $p['url'],
					'cta_label'   => '' !== $p['cta_label'] ? $p['cta_label'] : __( 'Learn more', 'next-class-services' ),
				);
			}
		}
		if ( $result['cta_url'] && $result['cta_label'] ) {
			$out['cta'] = array(
				'label' => $result['cta_label'],
				'url'   => $result['cta_url'],
			);
		}
		if ( $result['cta2_url'] && $result['cta2_label'] ) {
			$out['cta2'] = array(
				'label' => $result['cta2_label'],
				'url'   => $result['cta2_url'],
			);
		}
		return $out;
	}
}
