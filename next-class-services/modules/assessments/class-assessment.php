<?php
/**
 * Assessment model.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Database;
use NextClassServices\Helpers;
use NextClassServices\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads, sanitizes, validates and stores assessment definitions.
 */
class Assessment {

	const META = '_ncs_config';

	/**
	 * Default configuration.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title'                => '',
			'admin_title'          => '',
			'description'          => '',
			'intro'                => '',
			'start_text'           => __( 'Start the assessment', 'next-class-services' ),
			'prev_text'            => __( 'Previous', 'next-class-services' ),
			'next_text'            => __( 'Next', 'next-class-services' ),
			'submit_text'          => __( 'See my results', 'next-class-services' ),
			'lead_submit_text'     => __( 'Show my results', 'next-class-services' ),
			'show_intro'           => true,
			'show_progress'        => true,
			'show_percentage'      => true,
			'layout'               => 'single',
			'completion_message'   => __( 'Thank you for completing the assessment.', 'next-class-services' ),
			'range_basis'          => 'percentage',
			'result_heading'       => __( 'Your result', 'next-class-services' ),
			'show_score'           => false,
			'show_percentage_score' => false,
			'show_recommendation'  => true,
			'show_programs'        => true,
			'questions'            => array(),
			'results'              => array(),
			'programs'             => array(),
			'lead'                 => array(
				'enabled'     => true,
				'heading'     => __( 'Where should we send your results?', 'next-class-services' ),
				'intro'       => __( 'Enter your details to see your personalized result.', 'next-class-services' ),
				'fields'      => Leads::default_fields(),
				'privacy'     => array(
					'enabled'  => true,
					'required' => true,
					'text'     => __( 'I agree to the privacy policy and to being contacted about my results.', 'next-class-services' ),
				),
				'marketing'   => array(
					'enabled'  => false,
					'required' => false,
					'text'     => __( 'I would like to receive news and updates.', 'next-class-services' ),
				),
				'privacy_url' => '',
			),
			'email'                => array(
				'enabled'   => false,
				'from_name' => '',
				'reply_to'  => '',
				'subject'   => __( 'Your {assessment_name} results', 'next-class-services' ),
				'heading'   => __( 'Your results are ready', 'next-class-services' ),
				'intro'     => __( "Dear {first_name},\n\nThank you for completing the {assessment_name}. We have reviewed your responses and prepared your personalized results below.", 'next-class-services' ),
				'body'      => __( "Your result: {result_title}\n\n{result_description}\n\nOur recommendation:\n{recommendation}\n\nRecommended programs: {programs}\n\nIf you would like to talk through your results, simply reply to this email and a member of our team will be glad to help.\n\nKind regards,\nThe Next Class Team", 'next-class-services' ),
				'cta_label' => '',
				'cta_url'   => '',
				'footer'    => __( "You are receiving this email because you completed an assessment on our website.", 'next-class-services' ),
			),
			'admin_notify'         => array(
				'enabled'    => false,
				'recipients' => '',
				'subject'    => __( 'New assessment completed: {assessment_name}', 'next-class-services' ),
				'message'    => __( "Hello,\n\nA new assessment has just been completed on your website.\n\nAssessment: {assessment_name}\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nCompany: {company}\nJob title: {job_title}\nScore: {score} ({percentage}%)\nResult: {result_title}\nRecommended programs: {programs}\n\nThe full submission, including every answer, is available in the WordPress admin under Next Class Services, Assessment Leads.", 'next-class-services' ),
			),
		);
	}

	/**
	 * Load an assessment.
	 *
	 * @param int $id Post id.
	 * @return array|null [id, status, config]
	 */
	public static function get( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || Post_Types::ASSESSMENT !== $post->post_type ) {
			return null;
		}
		$saved  = json_decode( (string) get_post_meta( $post->ID, self::META, true ), true );
		$config = self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		if ( '' === $config['admin_title'] ) {
			$config['admin_title'] = $post->post_title;
		}
		return array(
			'id'      => (int) $post->ID,
			'status'  => 'publish' === $post->post_status ? 'publish' : 'draft',
			'config'  => $config,
			'created' => $post->post_date_gmt,
		);
	}

	/**
	 * Merge saved config over defaults (one level deep for grouped settings).
	 *
	 * @param array $defaults Defaults.
	 * @param array $saved    Saved.
	 * @return array
	 */
	private static function merge( array $defaults, array $saved ) {
		$out = $defaults;
		foreach ( $saved as $key => $value ) {
			if ( in_array( $key, array( 'lead', 'email', 'admin_notify' ), true ) && is_array( $value ) ) {
				$out[ $key ] = array_merge( $defaults[ $key ], $value );
				if ( 'lead' === $key ) {
					foreach ( array( 'privacy', 'marketing' ) as $sub ) {
						$out['lead'][ $sub ] = array_merge( $defaults['lead'][ $sub ], isset( $value[ $sub ] ) && is_array( $value[ $sub ] ) ? $value[ $sub ] : array() );
					}
				}
			} elseif ( array_key_exists( $key, $defaults ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Build a sanitized config from a posted form.
	 *
	 * @param array $post     Unslashed POST data.
	 * @param array $warnings Filled with non-fatal notices.
	 * @return array
	 */
	public static function from_request( array $post, array &$warnings ) {
		$d   = self::defaults();
		$cfg = isset( $post['cfg'] ) && is_array( $post['cfg'] ) ? $post['cfg'] : array();
		$g   = function ( $key, $default = '' ) use ( $cfg ) {
			return isset( $cfg[ $key ] ) && is_scalar( $cfg[ $key ] ) ? (string) $cfg[ $key ] : $default;
		};

		$c = array(
			'title'                 => sanitize_text_field( $g( 'title' ) ),
			'admin_title'           => sanitize_text_field( $g( 'admin_title' ) ),
			'description'           => wp_kses_post( $g( 'description' ) ),
			'intro'                 => wp_kses_post( $g( 'intro' ) ),
			'start_text'            => sanitize_text_field( $g( 'start_text', $d['start_text'] ) ),
			'prev_text'             => sanitize_text_field( $g( 'prev_text', $d['prev_text'] ) ),
			'next_text'             => sanitize_text_field( $g( 'next_text', $d['next_text'] ) ),
			'submit_text'           => sanitize_text_field( $g( 'submit_text', $d['submit_text'] ) ),
			'lead_submit_text'      => sanitize_text_field( $g( 'lead_submit_text', $d['lead_submit_text'] ) ),
			'show_intro'            => ! empty( $cfg['show_intro'] ),
			'show_progress'         => ! empty( $cfg['show_progress'] ),
			'show_percentage'       => ! empty( $cfg['show_percentage'] ),
			'layout'                => 'grouped' === $g( 'layout' ) ? 'grouped' : 'single',
			'completion_message'    => wp_kses_post( $g( 'completion_message' ) ),
			'range_basis'           => 'raw' === $g( 'range_basis' ) ? 'raw' : 'percentage',
			'result_heading'        => sanitize_text_field( $g( 'result_heading', $d['result_heading'] ) ),
			'show_score'            => ! empty( $cfg['show_score'] ),
			'show_percentage_score' => ! empty( $cfg['show_percentage_score'] ),
			'show_recommendation'   => ! empty( $cfg['show_recommendation'] ),
			'show_programs'         => ! empty( $cfg['show_programs'] ),
		);

		// Questions.
		$questions = array();
		$ids       = array();
		foreach ( Helpers::decode_json_list( isset( $post['questions_json'] ) ? $post['questions_json'] : '' ) as $rawq ) {
			$q = Question::sanitize( $rawq );
			if ( isset( $ids[ $q['id'] ] ) ) {
				$q['id'] = Helpers::uid( 'q' );
			}
			$ids[ $q['id'] ] = true;
			$questions[]     = $q;
		}
		// Logic may only reference earlier questions that exist.
		$before = array();
		foreach ( $questions as &$q ) {
			$kept = array();
			foreach ( $q['logic']['conditions'] as $cond ) {
				if ( isset( $before[ $cond['question'] ] ) ) {
					$kept[] = $cond;
				}
			}
			$q['logic']['conditions'] = $kept;
			if ( empty( $kept ) ) {
				$q['logic']['enabled'] = false;
			}
			$before[ $q['id'] ] = true;
		}
		unset( $q );
		$c['questions'] = $questions;

		$c['programs'] = Results::sanitize_programs( Helpers::decode_json_list( isset( $post['programs_json'] ) ? $post['programs_json'] : '' ) );
		$c['results']  = Results::sanitize( Helpers::decode_json_list( isset( $post['results_json'] ) ? $post['results_json'] : '' ) );
		$valid_prog    = wp_list_pluck( $c['programs'], 'id' );
		foreach ( $c['results'] as &$r ) {
			$r['programs'] = array_values( array_intersect( $r['programs'], $valid_prog ) );
		}
		unset( $r );

		// Lead capture.
		$lead_in = isset( $cfg['lead'] ) && is_array( $cfg['lead'] ) ? $cfg['lead'] : array();
		$cons    = function ( $key ) use ( $lead_in, $d ) {
			$in = isset( $lead_in[ $key ] ) && is_array( $lead_in[ $key ] ) ? $lead_in[ $key ] : array();
			return array(
				'enabled'  => ! empty( $in['enabled'] ),
				'required' => ! empty( $in['required'] ),
				'text'     => wp_kses( isset( $in['text'] ) ? (string) $in['text'] : $d['lead'][ $key ]['text'], array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array() ) ),
			);
		};
		$c['lead'] = array(
			'enabled'     => ! empty( $lead_in['enabled'] ),
			'heading'     => sanitize_text_field( isset( $lead_in['heading'] ) ? $lead_in['heading'] : '' ),
			'intro'       => sanitize_textarea_field( isset( $lead_in['intro'] ) ? $lead_in['intro'] : '' ),
			'fields'      => Leads::sanitize_fields( Helpers::decode_json_list( isset( $post['lead_fields_json'] ) ? $post['lead_fields_json'] : '' ) ),
			'privacy'     => $cons( 'privacy' ),
			'marketing'   => $cons( 'marketing' ),
			'privacy_url' => Helpers::safe_url( isset( $lead_in['privacy_url'] ) ? $lead_in['privacy_url'] : '' ),
		);
		if ( ! empty( $lead_in['privacy_url'] ) && '' === $c['lead']['privacy_url'] ) {
			$warnings[] = __( 'The privacy policy URL was not valid and was removed.', 'next-class-services' );
		}

		// Visitor email.
		$em_in      = isset( $cfg['email'] ) && is_array( $cfg['email'] ) ? $cfg['email'] : array();
		$reply      = sanitize_email( isset( $em_in['reply_to'] ) ? $em_in['reply_to'] : '' );
		$reply_bad  = ! empty( $em_in['reply_to'] ) && ( ! $reply || ! is_email( $reply ) );
		$c['email'] = array(
			'enabled'   => ! empty( $em_in['enabled'] ),
			'from_name' => Helpers::single_line( isset( $em_in['from_name'] ) ? $em_in['from_name'] : '' ),
			'reply_to'  => $reply_bad ? '' : $reply,
			'subject'   => Helpers::single_line( isset( $em_in['subject'] ) ? $em_in['subject'] : '' ),
			'heading'   => Helpers::single_line( isset( $em_in['heading'] ) ? $em_in['heading'] : '' ),
			'intro'     => wp_kses_post( isset( $em_in['intro'] ) ? $em_in['intro'] : '' ),
			'body'      => wp_kses_post( isset( $em_in['body'] ) ? $em_in['body'] : '' ),
			'cta_label' => sanitize_text_field( isset( $em_in['cta_label'] ) ? $em_in['cta_label'] : '' ),
			'cta_url'   => Helpers::safe_url( isset( $em_in['cta_url'] ) ? $em_in['cta_url'] : '' ),
			'footer'    => wp_kses_post( isset( $em_in['footer'] ) ? $em_in['footer'] : '' ),
		);
		if ( $reply_bad ) {
			$warnings[] = __( 'The reply-to address was not a valid email and was removed.', 'next-class-services' );
		}

		// Admin notification.
		$an_in           = isset( $cfg['admin_notify'] ) && is_array( $cfg['admin_notify'] ) ? $cfg['admin_notify'] : array();
		$recipients      = Helpers::parse_emails( isset( $an_in['recipients'] ) ? $an_in['recipients'] : '' );
		$c['admin_notify'] = array(
			'enabled'    => ! empty( $an_in['enabled'] ),
			'recipients' => implode( ', ', $recipients ),
			'subject'    => Helpers::single_line( isset( $an_in['subject'] ) ? $an_in['subject'] : '' ),
			'message'    => sanitize_textarea_field( isset( $an_in['message'] ) ? $an_in['message'] : '' ),
		);
		return $c;
	}

	/**
	 * Validate a config for publishing.
	 *
	 * @param array $c Config.
	 * @return array List of error strings.
	 */
	public static function validate( array $c ) {
		$errors  = array();
		$enabled = array_filter(
			$c['questions'],
			function ( $q ) {
				return ! empty( $q['enabled'] );
			}
		);
		if ( 0 === count( $enabled ) ) {
			$errors[] = array(
				'message' => __( 'Add at least one enabled question before publishing.', 'next-class-services' ),
				'tab'     => 'questions',
				'field'   => 'ncs-questions-root',
			);
		}
		foreach ( $enabled as $i => $q ) {
			if ( '' === $q['text'] ) {
				/* translators: %d: question number */
				$errors[] = array(
				'message' => sprintf( __( 'Question %d needs question text.', 'next-class-services' ), $i + 1 ),
				'tab'     => 'questions',
				'field'   => 'ncs-questions-root',
			);
			}
			if ( in_array( $q['type'], array( 'single', 'multiple', 'dropdown' ), true ) && count( $q['options'] ) < 2 ) {
				/* translators: %d: question number */
				$errors[] = array(
				'message' => sprintf( __( 'Question %d needs at least two answer options.', 'next-class-services' ), $i + 1 ),
				'tab'     => 'questions',
				'field'   => 'ncs-questions-root',
			);
			}
		}
		if ( '' === $c['title'] ) {
			$errors[] = array(
				'message' => __( 'Enter a public assessment title.', 'next-class-services' ),
				'tab'     => 'general',
				'field'   => 'ncs-cfg-title',
			);
		}
		if ( Scoring::has_scoring( $c['questions'] ) || ! empty( $c['results'] ) ) {
			foreach ( Results::validate( $c ) as $range_error ) {
				$errors[] = array(
					'message' => $range_error,
					'tab'     => 'results',
					'field'   => 'ncs-results-root',
				);
			}
		}
		if ( $c['email']['enabled'] && '' === trim( $c['email']['subject'] ) ) {
			$errors[] = array(
				'message' => __( 'The visitor result email needs a subject.', 'next-class-services' ),
				'tab'     => 'emails',
				'field'   => 'ncs-cfg-email-subject',
			);
		}
		if ( $c['admin_notify']['enabled'] && '' === $c['admin_notify']['recipients'] ) {
			$errors[] = array(
				'message' => __( 'Admin notifications are enabled but no valid recipient email is set.', 'next-class-services' ),
				'tab'     => 'emails',
				'field'   => 'ncs-cfg-admin-notify-recipients',
			);
		}
		if ( $c['lead']['enabled'] ) {
			$has_email = false;
			foreach ( $c['lead']['fields'] as $f ) {
				if ( 'email' === $f['key'] && $f['enabled'] ) {
					$has_email = true;
				}
			}
			if ( ! $has_email ) {
				$errors[] = array(
				'message' => __( 'Lead capture requires the email field.', 'next-class-services' ),
				'tab'     => 'lead',
				'field'   => 'ncs-leadfields-root',
			);
			}
		}
		return $errors;
	}

	/**
	 * Save a config and status.
	 *
	 * @param int    $id     Post id, 0 to create.
	 * @param array  $config Sanitized config.
	 * @param string $status publish|draft.
	 * @return int Post id or 0.
	 */
	public static function save( $id, array $config, $status ) {
		$title = '' !== $config['admin_title'] ? $config['admin_title'] : ( '' !== $config['title'] ? $config['title'] : __( 'Untitled assessment', 'next-class-services' ) );
		$args  = array(
			'post_type'   => Post_Types::ASSESSMENT,
			'post_title'  => $title,
			'post_status' => 'publish' === $status ? 'publish' : 'draft',
		);
		if ( $id ) {
			$args['ID'] = absint( $id );
			$id         = wp_update_post( wp_slash( $args ), true );
		} else {
			$id = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, self::META, wp_slash( wp_json_encode( $config ) ) );
		return (int) $id;
	}

	/**
	 * Set status only.
	 *
	 * @param int    $id     Id.
	 * @param string $status publish|draft.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		$post = get_post( $id );
		if ( ! $post || Post_Types::ASSESSMENT !== $post->post_type ) {
			return false;
		}
		return (bool) wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish' === $status ? 'publish' : 'draft',
			)
		);
	}

	/**
	 * Duplicate as a draft.
	 *
	 * @param int $id Source id.
	 * @return int New id or 0.
	 */
	public static function duplicate( $id ) {
		$src = self::get( $id );
		if ( ! $src ) {
			return 0;
		}
		$cfg                = $src['config'];
		/* translators: %s: assessment name */
		$cfg['admin_title'] = sprintf( __( '%s (copy)', 'next-class-services' ), $cfg['admin_title'] );
		return self::save( 0, $cfg, 'draft' );
	}

	/**
	 * Delete an assessment. Stored submissions are kept.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$post = get_post( $id );
		if ( ! $post || Post_Types::ASSESSMENT !== $post->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $post->ID, true );
	}

	/**
	 * Paginated list for the admin.
	 *
	 * @param int    $paged    Page.
	 * @param int    $per_page Per page.
	 * @param string $search   Search.
	 * @param string $status   Status filter.
	 * @return \WP_Query
	 */
	public static function query( $paged = 1, $per_page = 20, $search = '', $status = '' ) {
		$args = array(
			'post_type'      => Post_Types::ASSESSMENT,
			'post_status'    => in_array( $status, array( 'publish', 'draft' ), true ) ? $status : array( 'publish', 'draft' ),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => false,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		return new \WP_Query( $args );
	}

	/**
	 * Count by status.
	 *
	 * @return array publish, draft.
	 */
	public static function counts() {
		$c = wp_count_posts( Post_Types::ASSESSMENT );
		return array(
			'publish' => isset( $c->publish ) ? (int) $c->publish : 0,
			'draft'   => isset( $c->draft ) ? (int) $c->draft : 0,
		);
	}

	/**
	 * Submission counts per assessment.
	 *
	 * @return array id => count
	 */
	public static function submission_counts() {
		global $wpdb;
		$table = Database::table( 'submissions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT assessment_id, COUNT(*) c FROM {$table} GROUP BY assessment_id", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['assessment_id'] ] = (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Shortcode text.
	 *
	 * @param int $id Id.
	 * @return string
	 */
	public static function shortcode( $id ) {
		return '[next_class_assessment id="' . absint( $id ) . '"]';
	}

	/**
	 * Configuration safe to expose to browsers (no scores, no result ranges).
	 *
	 * @param int   $id Id.
	 * @param array $c  Config.
	 * @return array
	 */
	public static function public_config( $id, array $c ) {
		$questions = array();
		foreach ( $c['questions'] as $q ) {
			if ( empty( $q['enabled'] ) ) {
				continue;
			}
			$options = array();
			foreach ( $q['options'] as $o ) {
				$options[] = array(
					'id'    => $o['id'],
					'label' => $o['label'],
				);
			}
			$questions[] = array(
				'id'          => $q['id'],
				'type'        => $q['type'],
				'text'        => $q['text'],
				'help'        => $q['help'],
				'section'     => $q['section'],
				'required'    => (bool) $q['required'],
				'placeholder' => $q['placeholder'],
				'options'     => $options,
				'scale_min'   => $q['scale_min'],
				'scale_max'   => $q['scale_max'],
				'min_label'   => $q['min_label'],
				'max_label'   => $q['max_label'],
				'num_min'     => $q['num_min'],
				'num_max'     => $q['num_max'],
				'logic'       => $q['logic'],
			);
		}

		$lead   = $c['lead'];
		$fields = array();
		foreach ( $lead['fields'] as $f ) {
			if ( $f['enabled'] ) {
				$fields[] = $f;
			}
		}

		return array(
			'id'         => (int) $id,
			'title'      => $c['title'],
			'description' => $c['description'],
			'intro'      => $c['intro'],
			'showIntro'  => (bool) $c['show_intro'],
			'layout'     => $c['layout'],
			'progress'   => (bool) $c['show_progress'],
			'percentage' => (bool) $c['show_percentage'],
			'text'       => array(
				'start'      => $c['start_text'],
				'prev'       => $c['prev_text'],
				'next'       => $c['next_text'],
				'submit'     => $c['submit_text'],
				'leadSubmit' => $c['lead_submit_text'],
			),
			'questions'  => $questions,
			'lead'       => array(
				'enabled'    => (bool) $lead['enabled'],
				'heading'    => $lead['heading'],
				'intro'      => $lead['intro'],
				'fields'     => $fields,
				'privacy'    => $lead['privacy'],
				'marketing'  => $lead['marketing'],
				'privacyUrl' => $lead['privacy_url'],
			),
			'completion' => $c['completion_message'],
		);
	}
}
