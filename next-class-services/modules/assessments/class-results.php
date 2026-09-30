<?php
/**
 * Result categories and program recommendations.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result category handling.
 */
class Results {

	/**
	 * Sanitize the result list.
	 *
	 * @param array $raw Raw list.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( array_slice( $raw, 0, 50 ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$programs = array();
			if ( isset( $r['programs'] ) && is_array( $r['programs'] ) ) {
				foreach ( $r['programs'] as $pid ) {
					$pid = Helpers::sanitize_id( is_scalar( $pid ) ? $pid : '' );
					if ( $pid ) {
						$programs[] = $pid;
					}
				}
			}
			$out[] = array(
				'id'             => Helpers::sanitize_id( isset( $r['id'] ) ? $r['id'] : '' ) ?: Helpers::uid( 'r' ),
				'title'          => sanitize_text_field( isset( $r['title'] ) ? $r['title'] : '' ),
				'min'            => isset( $r['min'] ) && is_numeric( $r['min'] ) ? (float) $r['min'] : 0,
				'max'            => isset( $r['max'] ) && is_numeric( $r['max'] ) ? (float) $r['max'] : 0,
				'description'    => wp_kses_post( isset( $r['description'] ) ? $r['description'] : '' ),
				'recommendation' => wp_kses_post( isset( $r['recommendation'] ) ? $r['recommendation'] : '' ),
				'programs'       => array_values( array_unique( $programs ) ),
				'cta_label'      => sanitize_text_field( isset( $r['cta_label'] ) ? $r['cta_label'] : '' ),
				'cta_url'        => Helpers::safe_url( isset( $r['cta_url'] ) ? $r['cta_url'] : '' ),
				'cta2_label'     => sanitize_text_field( isset( $r['cta2_label'] ) ? $r['cta2_label'] : '' ),
				'cta2_url'       => Helpers::safe_url( isset( $r['cta2_url'] ) ? $r['cta2_url'] : '' ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize programs.
	 *
	 * @param array $raw Raw list.
	 * @return array
	 */
	public static function sanitize_programs( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( array_slice( $raw, 0, 100 ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$name = sanitize_text_field( isset( $p['name'] ) ? $p['name'] : '' );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'          => Helpers::sanitize_id( isset( $p['id'] ) ? $p['id'] : '' ) ?: Helpers::uid( 'p' ),
				'name'        => $name,
				'description' => wp_kses_post( isset( $p['description'] ) ? $p['description'] : '' ),
				'url'         => Helpers::safe_url( isset( $p['url'] ) ? $p['url'] : '' ),
				'cta_label'   => sanitize_text_field( isset( $p['cta_label'] ) ? $p['cta_label'] : '' ),
			);
		}
		return $out;
	}

	/**
	 * Find the result that matches a score.
	 *
	 * @param array $config Assessment config.
	 * @param array $score  Output of Scoring::calculate().
	 * @return array|null
	 */
	public static function match( array $config, array $score ) {
		$results = $config['results'];
		if ( empty( $results ) ) {
			return null;
		}
		$value = 'raw' === $config['range_basis'] ? (float) $score['raw'] : round( (float) $score['percentage'] );

		usort(
			$results,
			function ( $a, $b ) {
				return $a['min'] <=> $b['min'];
			}
		);
		foreach ( $results as $r ) {
			if ( $value >= $r['min'] && $value <= $r['max'] ) {
				return $r;
			}
		}
		// Fall back to the nearest range so visitors never see an empty result.
		$best = null;
		$dist = null;
		foreach ( $results as $r ) {
			$d = $value < $r['min'] ? $r['min'] - $value : $value - $r['max'];
			if ( null === $dist || $d < $dist ) {
				$dist = $d;
				$best = $r;
			}
		}
		return $best;
	}

	/**
	 * Validate result ranges.
	 *
	 * @param array $config Config.
	 * @return array error messages
	 */
	public static function validate( array $config ) {
		$errors  = array();
		$results = $config['results'];
		if ( empty( $results ) ) {
			return array( __( 'A scored assessment needs at least one result category.', 'next-class-services' ) );
		}
		$top = 'raw' === $config['range_basis'] ? Scoring::max_possible( $config['questions'] ) : 100;

		foreach ( $results as $r ) {
			if ( '' === $r['title'] ) {
				$errors[] = __( 'Every result category needs a title.', 'next-class-services' );
				break;
			}
		}
		foreach ( $results as $r ) {
			if ( $r['min'] > $r['max'] ) {
				/* translators: %s: result title */
				$errors[] = sprintf( __( 'Result "%s": the minimum score is greater than the maximum.', 'next-class-services' ), $r['title'] );
			}
		}
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		usort(
			$results,
			function ( $a, $b ) {
				return $a['min'] <=> $b['min'];
			}
		);
		if ( $results[0]['min'] > 0 ) {
			/* translators: %s: lowest score covered */
			$errors[] = sprintf( __( 'Scores below %s are not covered by any result category.', 'next-class-services' ), $results[0]['min'] );
		}
		$count = count( $results );
		for ( $i = 1; $i < $count; $i++ ) {
			$prev = $results[ $i - 1 ];
			$cur  = $results[ $i ];
			if ( $cur['min'] <= $prev['max'] ) {
				/* translators: 1: result title, 2: result title */
				$errors[] = sprintf( __( 'Result ranges for "%1$s" and "%2$s" overlap.', 'next-class-services' ), $prev['title'], $cur['title'] );
			} elseif ( $cur['min'] - $prev['max'] > 1 ) {
				/* translators: 1: from score, 2: to score */
				$errors[] = sprintf( __( 'Scores from %1$s to %2$s are not covered by any result category.', 'next-class-services' ), $prev['max'], $cur['min'] );
			}
		}
		$last = $results[ $count - 1 ];
		if ( $last['max'] < $top ) {
			/* translators: %s: highest possible score */
			$errors[] = sprintf( __( 'Scores above %s are not covered by any result category.', 'next-class-services' ), $last['max'] );
		}
		return $errors;
	}

	/**
	 * Programs attached to a result.
	 *
	 * @param array $config Config.
	 * @param array $result Result.
	 * @return array
	 */
	public static function programs_for( array $config, array $result ) {
		$out = array();
		foreach ( $result['programs'] as $pid ) {
			foreach ( $config['programs'] as $p ) {
				if ( $p['id'] === $pid ) {
					$out[] = $p;
				}
			}
		}
		return $out;
	}
}
