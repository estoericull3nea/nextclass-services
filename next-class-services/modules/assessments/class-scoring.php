<?php
/**
 * Scoring engine.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates raw score, applicable maximum and percentage.
 */
class Scoring {

	/**
	 * Calculate the score for a submission.
	 *
	 * Only visible questions contribute. An optional scored question that was
	 * left unanswered is treated as skipped: it adds nothing to the raw score
	 * and nothing to the maximum applicable score.
	 *
	 * @param array $questions Question list.
	 * @param array $answers   question id => normalized answer.
	 * @param array $visible   question id => true.
	 * @return array raw, max, percentage, per_question
	 */
	public static function calculate( array $questions, array $answers, array $visible ) {
		$raw = 0.0;
		$max = 0.0;
		$per = array();

		foreach ( $questions as $q ) {
			if ( empty( $visible[ $q['id'] ] ) ) {
				continue;
			}
			$value = isset( $answers[ $q['id'] ] ) ? $answers[ $q['id'] ] : '';
			list( $score, $qmax ) = Question::score( $q, $value );

			if ( $q['scored'] && Question::is_empty( $value ) ) {
				if ( empty( $q['required'] ) ) {
					// Skipped optional question: not applicable.
					$per[ $q['id'] ] = array( 0.0, 0.0 );
					continue;
				}
			}
			$raw += $score;
			$max += $qmax;

			$per[ $q['id'] ] = array( $score, $qmax );
		}

		$pct = $max > 0 ? ( $raw / $max ) * 100 : 0;
		$pct = max( 0, min( 100, $pct ) );

		return array(
			'raw'          => round( $raw, 2 ),
			'max'          => round( $max, 2 ),
			'percentage'   => round( $pct, 2 ),
			'per_question' => $per,
		);
	}

	/**
	 * Maximum possible score ignoring conditional logic (used for range validation).
	 *
	 * @param array $questions Questions.
	 * @return float
	 */
	public static function max_possible( array $questions ) {
		$max = 0.0;
		foreach ( $questions as $q ) {
			if ( empty( $q['enabled'] ) ) {
				continue;
			}
			list( , $qmax ) = Question::score( $q, '' );
			$max           += $qmax;
		}
		return round( $max, 2 );
	}

	/**
	 * Does the assessment contain any scored question.
	 *
	 * @param array $questions Questions.
	 * @return bool
	 */
	public static function has_scoring( array $questions ) {
		foreach ( $questions as $q ) {
			if ( ! empty( $q['enabled'] ) && ! empty( $q['scored'] ) ) {
				return true;
			}
		}
		return false;
	}
}
