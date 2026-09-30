<?php
/**
 * Conditional question logic.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates show/skip rules. The same rules are mirrored in assessment.js for
 * the visitor experience; this class is authoritative for scoring and validation.
 */
class Conditional_Logic {

	/**
	 * Supported operators.
	 *
	 * @return array
	 */
	public static function operators() {
		return array(
			'equals'       => __( 'equals', 'next-class-services' ),
			'not_equals'   => __( 'does not equal', 'next-class-services' ),
			'contains'     => __( 'contains', 'next-class-services' ),
			'not_contains' => __( 'does not contain', 'next-class-services' ),
			'gt'           => __( 'is greater than', 'next-class-services' ),
			'lt'           => __( 'is less than', 'next-class-services' ),
		);
	}

	/**
	 * Sanitize a logic block.
	 *
	 * @param mixed $raw Raw.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$logic = array(
			'enabled'    => ! empty( $raw['enabled'] ),
			'action'     => isset( $raw['action'] ) && 'hide' === $raw['action'] ? 'hide' : 'show',
			'match'      => isset( $raw['match'] ) && 'any' === $raw['match'] ? 'any' : 'all',
			'conditions' => array(),
		);
		$ops   = self::operators();
		if ( isset( $raw['conditions'] ) && is_array( $raw['conditions'] ) ) {
			foreach ( array_slice( $raw['conditions'], 0, 10 ) as $c ) {
				if ( ! is_array( $c ) || empty( $c['question'] ) ) {
					continue;
				}
				$op                  = isset( $c['operator'] ) && isset( $ops[ $c['operator'] ] ) ? $c['operator'] : 'equals';
				$logic['conditions'][] = array(
					'question' => Helpers::sanitize_id( $c['question'] ),
					'operator' => $op,
					'value'    => Helpers::truncate( sanitize_text_field( isset( $c['value'] ) ? (string) $c['value'] : '' ), 255 ),
				);
			}
		}
		if ( empty( $logic['conditions'] ) ) {
			$logic['enabled'] = false;
		}
		return $logic;
	}

	/**
	 * Evaluate a single condition against an answer.
	 *
	 * @param string $operator Operator.
	 * @param mixed  $answer   Normalized answer (string|array|null).
	 * @param string $target   Target value.
	 * @return bool
	 */
	public static function compare( $operator, $answer, $target ) {
		$target = (string) $target;
		switch ( $operator ) {
			case 'equals':
				return self::equals( $answer, $target );
			case 'not_equals':
				return ! self::equals( $answer, $target );
			case 'contains':
				return self::contains( $answer, $target );
			case 'not_contains':
				return ! self::contains( $answer, $target );
			case 'gt':
				return ! is_array( $answer ) && is_numeric( $answer ) && is_numeric( $target ) && (float) $answer > (float) $target;
			case 'lt':
				return ! is_array( $answer ) && is_numeric( $answer ) && is_numeric( $target ) && (float) $answer < (float) $target;
		}
		return false;
	}

	/**
	 * Equality.
	 *
	 * @param mixed  $answer Answer.
	 * @param string $target Target.
	 * @return bool
	 */
	private static function equals( $answer, $target ) {
		if ( is_array( $answer ) ) {
			return 1 === count( $answer ) && (string) reset( $answer ) === $target;
		}
		if ( null === $answer || '' === $answer ) {
			return false;
		}
		if ( is_numeric( $answer ) && is_numeric( $target ) ) {
			return (float) $answer === (float) $target;
		}
		return 0 === strcasecmp( (string) $answer, $target );
	}

	/**
	 * Containment.
	 *
	 * @param mixed  $answer Answer.
	 * @param string $target Target.
	 * @return bool
	 */
	private static function contains( $answer, $target ) {
		if ( is_array( $answer ) ) {
			return in_array( $target, array_map( 'strval', $answer ), true );
		}
		if ( null === $answer || '' === $answer || '' === $target ) {
			return false;
		}
		return false !== stripos( (string) $answer, $target );
	}

	/**
	 * Determine which questions are visible for a set of normalized answers.
	 *
	 * A condition that references a hidden or unanswered question sees an empty
	 * answer, so skipped questions never cause later questions to show by accident.
	 *
	 * @param array $questions Enabled or disabled question list, in order.
	 * @param array $answers   question id => normalized answer.
	 * @return array question id => true for visible questions
	 */
	public static function visible_map( array $questions, array $answers ) {
		$visible = array();
		foreach ( $questions as $q ) {
			if ( empty( $q['enabled'] ) ) {
				continue;
			}
			$logic = isset( $q['logic'] ) ? $q['logic'] : array();
			if ( empty( $logic['enabled'] ) || empty( $logic['conditions'] ) ) {
				$visible[ $q['id'] ] = true;
				continue;
			}
			$results = array();
			foreach ( $logic['conditions'] as $c ) {
				$ref    = $c['question'];
				$answer = ( isset( $visible[ $ref ] ) && isset( $answers[ $ref ] ) ) ? $answers[ $ref ] : null;
				if ( is_array( $answer ) && empty( $answer ) ) {
					$answer = null;
				}
				$results[] = self::compare( $c['operator'], $answer, $c['value'] );
			}
			$met = 'any' === $logic['match'] ? in_array( true, $results, true ) : ! in_array( false, $results, true );
			if ( 'hide' === $logic['action'] ) {
				$met = ! $met;
			}
			if ( $met ) {
				$visible[ $q['id'] ] = true;
			}
		}
		return $visible;
	}
}
