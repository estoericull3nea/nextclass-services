<?php
/**
 * Assessment question model helpers.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Question types, sanitization and answer normalization.
 */
class Question {

	/**
	 * Question types.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'single'     => __( 'Single choice', 'next-class-services' ),
			'multiple'   => __( 'Multiple choice', 'next-class-services' ),
			'dropdown'   => __( 'Dropdown', 'next-class-services' ),
			'rating'     => __( 'Rating scale', 'next-class-services' ),
			'yesno'      => __( 'Yes / No', 'next-class-services' ),
			'number'     => __( 'Number', 'next-class-services' ),
			'short_text' => __( 'Short text', 'next-class-services' ),
			'long_text'  => __( 'Long text', 'next-class-services' ),
		);
	}

	/**
	 * Choice based types (answers are option ids).
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_choice( $type ) {
		return in_array( $type, array( 'single', 'multiple', 'dropdown', 'yesno' ), true );
	}

	/**
	 * Types that can contribute to a numerical score.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_scorable( $type ) {
		return in_array( $type, array( 'single', 'multiple', 'dropdown', 'yesno', 'rating' ), true );
	}

	/**
	 * Sanitize one question definition.
	 *
	 * @param array $raw Raw question.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw  = is_array( $raw ) ? $raw : array();
		$type = isset( $raw['type'] ) && isset( self::types()[ $raw['type'] ] ) ? $raw['type'] : 'single';

		$q = array(
			'id'          => Helpers::sanitize_id( isset( $raw['id'] ) ? $raw['id'] : '' ),
			'type'        => $type,
			'text'        => sanitize_text_field( isset( $raw['text'] ) ? $raw['text'] : '' ),
			'help'        => sanitize_textarea_field( isset( $raw['help'] ) ? $raw['help'] : '' ),
			'section'     => sanitize_text_field( isset( $raw['section'] ) ? $raw['section'] : '' ),
			'required'    => ! empty( $raw['required'] ),
			'enabled'     => ! isset( $raw['enabled'] ) || ! empty( $raw['enabled'] ),
			'placeholder' => sanitize_text_field( isset( $raw['placeholder'] ) ? $raw['placeholder'] : '' ),
			'options'     => array(),
			'scored'      => ! empty( $raw['scored'] ),
			'weight'      => isset( $raw['weight'] ) ? max( 0, min( 100, (float) $raw['weight'] ) ) : 1,
			'scale_min'   => isset( $raw['scale_min'] ) ? (int) $raw['scale_min'] : 1,
			'scale_max'   => isset( $raw['scale_max'] ) ? (int) $raw['scale_max'] : 5,
			'min_label'   => sanitize_text_field( isset( $raw['min_label'] ) ? $raw['min_label'] : '' ),
			'max_label'   => sanitize_text_field( isset( $raw['max_label'] ) ? $raw['max_label'] : '' ),
			'reverse'     => ! empty( $raw['reverse'] ),
			'num_min'     => isset( $raw['num_min'] ) && '' !== $raw['num_min'] && is_numeric( $raw['num_min'] ) ? (float) $raw['num_min'] : '',
			'num_max'     => isset( $raw['num_max'] ) && '' !== $raw['num_max'] && is_numeric( $raw['num_max'] ) ? (float) $raw['num_max'] : '',
			'logic'       => Conditional_Logic::sanitize( isset( $raw['logic'] ) ? $raw['logic'] : array() ),
		);
		if ( '' === $q['id'] ) {
			$q['id'] = Helpers::uid( 'q' );
		}

		if ( $q['scale_min'] < 0 ) {
			$q['scale_min'] = 0;
		}
		if ( $q['scale_max'] > 20 ) {
			$q['scale_max'] = 20;
		}
		if ( $q['scale_max'] <= $q['scale_min'] ) {
			$q['scale_max'] = $q['scale_min'] + 1;
		}

		if ( 'yesno' === $type ) {
			$opts = isset( $raw['options'] ) && is_array( $raw['options'] ) ? $raw['options'] : array();
			$map  = array();
			foreach ( $opts as $o ) {
				if ( is_array( $o ) && isset( $o['id'] ) ) {
					$map[ $o['id'] ] = isset( $o['score'] ) && is_numeric( $o['score'] ) ? (float) $o['score'] : 0;
				}
			}
			$q['options'] = array(
				array(
					'id'    => 'yes',
					'label' => __( 'Yes', 'next-class-services' ),
					'score' => isset( $map['yes'] ) ? $map['yes'] : 0,
				),
				array(
					'id'    => 'no',
					'label' => __( 'No', 'next-class-services' ),
					'score' => isset( $map['no'] ) ? $map['no'] : 0,
				),
			);
		} elseif ( self::is_choice( $type ) && isset( $raw['options'] ) && is_array( $raw['options'] ) ) {
			$seen = array();
			foreach ( $raw['options'] as $o ) {
				if ( ! is_array( $o ) ) {
					continue;
				}
				$label = sanitize_text_field( isset( $o['label'] ) ? $o['label'] : '' );
				if ( '' === $label ) {
					continue;
				}
				$oid = Helpers::sanitize_id( isset( $o['id'] ) ? $o['id'] : '' );
				if ( '' === $oid || isset( $seen[ $oid ] ) ) {
					$oid = Helpers::uid( 'o' );
				}
				$seen[ $oid ]   = true;
				$q['options'][] = array(
					'id'    => $oid,
					'label' => $label,
					'score' => isset( $o['score'] ) && is_numeric( $o['score'] ) ? (float) $o['score'] : 0,
				);
			}
		}

		if ( ! self::is_scorable( $type ) ) {
			$q['scored'] = false;
		}
		if ( self::is_choice( $type ) ) {
			// Choice questions are scored when any option carries a non zero score.
			$q['scored'] = false;
			foreach ( $q['options'] as $o ) {
				if ( 0.0 !== (float) $o['score'] ) {
					$q['scored'] = true;
					break;
				}
			}
		}

		return $q;
	}

	/**
	 * Find an option by id.
	 *
	 * @param array  $q  Question.
	 * @param string $id Option id.
	 * @return array|null
	 */
	public static function option( array $q, $id ) {
		foreach ( $q['options'] as $o ) {
			if ( (string) $o['id'] === (string) $id ) {
				return $o;
			}
		}
		return null;
	}

	/**
	 * Is an answer empty.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_empty( $value ) {
		if ( is_array( $value ) ) {
			return 0 === count( $value );
		}
		return null === $value || '' === trim( (string) $value );
	}

	/**
	 * Normalize a submitted answer against the question definition.
	 *
	 * @param array $q   Question.
	 * @param mixed $raw Raw submitted value.
	 * @return array [ value, error message or '' ]
	 */
	public static function normalize_answer( array $q, $raw ) {
		$type = $q['type'];

		if ( 'multiple' === $type ) {
			$raw = is_array( $raw ) ? $raw : ( self::is_empty( $raw ) ? array() : array( $raw ) );
			$out = array();
			foreach ( $raw as $id ) {
				$id = Helpers::sanitize_id( is_scalar( $id ) ? $id : '' );
				if ( $id && self::option( $q, $id ) && ! in_array( $id, $out, true ) ) {
					$out[] = $id;
				}
			}
			return array( $out, '' );
		}

		if ( is_array( $raw ) ) {
			$raw = '';
		}
		$raw = is_scalar( $raw ) ? (string) $raw : '';

		switch ( $type ) {
			case 'single':
			case 'dropdown':
			case 'yesno':
				$id = Helpers::sanitize_id( $raw );
				if ( '' === $id ) {
					return array( '', '' );
				}
				if ( ! self::option( $q, $id ) ) {
					return array( '', __( 'Please choose one of the available options.', 'next-class-services' ) );
				}
				return array( $id, '' );

			case 'rating':
				if ( '' === trim( $raw ) ) {
					return array( '', '' );
				}
				if ( ! preg_match( '/^-?\d+$/', trim( $raw ) ) ) {
					return array( '', __( 'Please choose a rating.', 'next-class-services' ) );
				}
				$n = (int) $raw;
				if ( $n < $q['scale_min'] || $n > $q['scale_max'] ) {
					return array( '', __( 'Please choose a rating within the scale.', 'next-class-services' ) );
				}
				return array( (string) $n, '' );

			case 'number':
				$raw = trim( $raw );
				if ( '' === $raw ) {
					return array( '', '' );
				}
				if ( ! is_numeric( $raw ) ) {
					return array( '', __( 'Please enter a valid number.', 'next-class-services' ) );
				}
				$n = (float) $raw;
				if ( '' !== $q['num_min'] && $n < $q['num_min'] ) {
					/* translators: %s: minimum */
					return array( '', sprintf( __( 'Please enter a number of %s or more.', 'next-class-services' ), $q['num_min'] ) );
				}
				if ( '' !== $q['num_max'] && $n > $q['num_max'] ) {
					/* translators: %s: maximum */
					return array( '', sprintf( __( 'Please enter a number of %s or less.', 'next-class-services' ), $q['num_max'] ) );
				}
				return array( (string) $n, '' );

			case 'long_text':
				return array( Helpers::truncate( sanitize_textarea_field( $raw ), 5000 ), '' );

			case 'short_text':
			default:
				return array( Helpers::truncate( sanitize_text_field( $raw ), 255 ), '' );
		}
	}

	/**
	 * Human readable label for a stored answer.
	 *
	 * @param array $q     Question.
	 * @param mixed $value Normalized value.
	 * @return string
	 */
	public static function answer_label( array $q, $value ) {
		if ( self::is_choice( $q['type'] ) ) {
			$labels = array();
			foreach ( (array) $value as $id ) {
				$o = self::option( $q, $id );
				if ( $o ) {
					$labels[] = $o['label'];
				}
			}
			return implode( ', ', $labels );
		}
		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	/**
	 * Score and maximum for a normalized answer.
	 *
	 * @param array $q     Question.
	 * @param mixed $value Normalized value (may be empty).
	 * @return array [ score, max ]
	 */
	public static function score( array $q, $value ) {
		if ( ! $q['scored'] ) {
			return array( 0.0, 0.0 );
		}
		$weight = isset( $q['weight'] ) ? (float) $q['weight'] : 1.0;
		$score  = 0.0;
		$max    = 0.0;

		switch ( $q['type'] ) {
			case 'single':
			case 'dropdown':
			case 'yesno':
				foreach ( $q['options'] as $o ) {
					$max = max( $max, (float) $o['score'] );
				}
				$o = ! self::is_empty( $value ) ? self::option( $q, $value ) : null;
				if ( $o ) {
					$score = (float) $o['score'];
				}
				break;
			case 'multiple':
				foreach ( $q['options'] as $o ) {
					if ( $o['score'] > 0 ) {
						$max += (float) $o['score'];
					}
				}
				foreach ( (array) $value as $id ) {
					$o = self::option( $q, $id );
					if ( $o ) {
						$score += (float) $o['score'];
					}
				}
				break;
			case 'rating':
				$max = (float) $q['scale_max'];
				if ( ! self::is_empty( $value ) ) {
					$n     = (int) $value;
					$score = $q['reverse'] ? (float) ( $q['scale_max'] + $q['scale_min'] - $n ) : (float) $n;
				}
				break;
		}
		return array( $score * $weight, $max * $weight );
	}
}
