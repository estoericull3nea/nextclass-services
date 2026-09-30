<?php
/**
 * Lead fields and stored submissions.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Assessments;

use NextClassServices\Database;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead capture configuration, validation and persistence.
 */
class Leads {

	/**
	 * Built-in field keys mapped to their column.
	 *
	 * @return array key => label
	 */
	public static function builtin_fields() {
		return array(
			'first_name'   => __( 'First name', 'next-class-services' ),
			'last_name'    => __( 'Last name', 'next-class-services' ),
			'full_name'    => __( 'Full name', 'next-class-services' ),
			'email'        => __( 'Email', 'next-class-services' ),
			'phone'        => __( 'Phone', 'next-class-services' ),
			'company'      => __( 'Company', 'next-class-services' ),
			'job_title'    => __( 'Job title', 'next-class-services' ),
			'company_size' => __( 'Company size', 'next-class-services' ),
		);
	}

	/**
	 * Default company size choices.
	 *
	 * @return array
	 */
	public static function default_sizes() {
		return array( '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+' );
	}

	/**
	 * Default lead field set.
	 *
	 * @return array
	 */
	public static function default_fields() {
		$fields = array();
		$on     = array( 'first_name', 'last_name', 'email', 'company' );
		$req    = array( 'first_name', 'email' );
		foreach ( self::builtin_fields() as $key => $label ) {
			$fields[] = array(
				'key'         => $key,
				'label'       => $label,
				'placeholder' => '',
				'type'        => self::builtin_type( $key ),
				'options'     => 'company_size' === $key ? self::default_sizes() : array(),
				'required'    => in_array( $key, $req, true ),
				'enabled'     => in_array( $key, $on, true ),
				'custom'      => false,
			);
		}
		return $fields;
	}

	/**
	 * Input type of a built-in field.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function builtin_type( $key ) {
		switch ( $key ) {
			case 'email':
				return 'email';
			case 'phone':
				return 'tel';
			case 'company_size':
				return 'select';
		}
		return 'text';
	}

	/**
	 * Sanitize the field list from the builder.
	 *
	 * @param mixed $raw Raw list.
	 * @return array
	 */
	public static function sanitize_fields( $raw ) {
		$builtin = self::builtin_fields();
		$out     = array();
		$seen    = array();

		if ( is_array( $raw ) ) {
			foreach ( array_slice( $raw, 0, 30 ) as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$key = Helpers::sanitize_id( isset( $f['key'] ) ? $f['key'] : '' );
				if ( '' === $key || isset( $seen[ $key ] ) ) {
					continue;
				}
				$custom = ! isset( $builtin[ $key ] );
				if ( $custom && 0 !== strpos( $key, 'custom_' ) ) {
					$key = 'custom_' . $key;
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
				}
				$label = sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' );
				if ( '' === $label ) {
					$label = $custom ? __( 'Custom field', 'next-class-services' ) : $builtin[ $key ];
				}
				$type = self::builtin_type( $key );
				if ( $custom ) {
					$type = isset( $f['type'] ) && in_array( $f['type'], array( 'text', 'textarea', 'select' ), true ) ? $f['type'] : 'text';
				}
				$options = array();
				if ( 'select' === $type && isset( $f['options'] ) ) {
					$list = is_array( $f['options'] ) ? $f['options'] : preg_split( '/\r\n|\r|\n|,/', (string) $f['options'] );
					foreach ( $list as $opt ) {
						$opt = sanitize_text_field( (string) $opt );
						if ( '' !== $opt ) {
							$options[] = $opt;
						}
					}
					if ( 'company_size' === $key && empty( $options ) ) {
						$options = self::default_sizes();
					}
				}
				$seen[ $key ] = true;
				$out[]        = array(
					'key'         => $key,
					'label'       => $label,
					'placeholder' => sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ),
					'type'        => $type,
					'options'     => array_slice( $options, 0, 50 ),
					'required'    => ! empty( $f['required'] ),
					'enabled'     => ! empty( $f['enabled'] ),
					'custom'      => $custom,
				);
			}
		}

		// Guarantee every built-in field exists so it can be enabled later.
		foreach ( self::default_fields() as $def ) {
			if ( ! isset( $seen[ $def['key'] ] ) ) {
				$def['enabled']  = false;
				$def['required'] = false;
				$out[]           = $def;
			}
		}
		// Email is the one field a lead cannot exist without.
		foreach ( $out as &$field ) {
			if ( 'email' === $field['key'] ) {
				$field['enabled']  = true;
				$field['required'] = true;
			}
		}
		unset( $field );
		return $out;
	}

	/**
	 * Validate and clean submitted lead values.
	 *
	 * @param array $fields Field definitions.
	 * @param array $input  Raw submitted values.
	 * @return array [ clean values, errors key => message ]
	 */
	public static function validate( array $fields, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$clean  = array();
		$errors = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['enabled'] ) ) {
				continue;
			}
			$key = $f['key'];
			$raw = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( 'textarea' === $f['type'] ) {
				$val = Helpers::truncate( sanitize_textarea_field( $raw ), 2000 );
			} elseif ( 'email' === $f['type'] ) {
				$val = sanitize_email( $raw );
			} else {
				$val = Helpers::truncate( sanitize_text_field( $raw ), 190 );
			}

			if ( '' === $val ) {
				if ( ! empty( $f['required'] ) ) {
					/* translators: %s: field label */
					$errors[ $key ] = sprintf( __( '%s is required.', 'next-class-services' ), $f['label'] );
				}
				$clean[ $key ] = '';
				continue;
			}
			if ( 'email' === $f['type'] && ! is_email( $val ) ) {
				$errors[ $key ] = __( 'Please enter a valid email address.', 'next-class-services' );
			} elseif ( 'tel' === $f['type'] && ! preg_match( '/^[0-9+\-().\s]{5,40}$/', $val ) ) {
				$errors[ $key ] = __( 'Please enter a valid phone number.', 'next-class-services' );
			} elseif ( 'select' === $f['type'] && ! in_array( $val, $f['options'], true ) ) {
				$errors[ $key ] = __( 'Please choose one of the available options.', 'next-class-services' );
			}
			$clean[ $key ] = $val;
		}
		return array( $clean, $errors );
	}

	/**
	 * Persist a submission with its answers.
	 *
	 * @param array $data Row data (columns of the submissions table).
	 * @param array $answers List of answer rows.
	 * @return int Submission id or 0.
	 */
	public static function create( array $data, array $answers ) {
		global $wpdb;
		$data['created_at'] = Helpers::now_utc();
		$ok                 = $wpdb->insert( Database::table( 'submissions' ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ok ) {
			Helpers::log( 'Submission insert failed', $wpdb->last_error );
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		foreach ( $answers as $i => $a ) {
			$a['submission_id'] = $id;
			$a['sort_order']    = $i;
			$wpdb->insert( Database::table( 'answers' ), $a ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $id;
	}

	/**
	 * Build WHERE clause and args from filters.
	 *
	 * @param array $f Filters.
	 * @return array [ sql, args ]
	 */
	private static function where( array $f ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $f['assessment_id'] ) ) {
			$where[] = 'assessment_id = %d';
			$args[]  = (int) $f['assessment_id'];
		}
		if ( ! empty( $f['result_title'] ) ) {
			$where[] = 'result_title = %s';
			$args[]  = $f['result_title'];
		}
		if ( ! empty( $f['from'] ) ) {
			$dt = Helpers::parse_local_date( $f['from'], wp_timezone() );
			if ( $dt ) {
				$where[] = 'created_at >= %s';
				$args[]  = $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		if ( ! empty( $f['to'] ) ) {
			$dt = Helpers::parse_local_date( $f['to'], wp_timezone() );
			if ( $dt ) {
				$where[] = 'created_at <= %s';
				$args[]  = $dt->setTime( 23, 59, 59 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		if ( ! empty( $f['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $f['search'] ) . '%';
			$where[] = '(full_name LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR company LIKE %s OR phone LIKE %s)';
			array_push( $args, $like, $like, $like, $like, $like, $like );
		}
		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * Query submissions with pagination.
	 *
	 * @param array $filters  Filters.
	 * @param int   $page     Page number.
	 * @param int   $per_page Per page, 0 for all (export).
	 * @return array [ rows, total ]
	 */
	public static function query( array $filters, $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table                 = Database::table( 'submissions' );
		list( $where, $args ) = self::where( $filters );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC";
		if ( $per_page > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $per_page;
			$args[] = max( 0, ( $page - 1 ) * $per_page );
		}
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable
		return array( (array) $rows, $total );
	}

	/**
	 * Distinct result titles for a filter dropdown.
	 *
	 * @param int $assessment_id Optional assessment.
	 * @return array
	 */
	public static function result_titles( $assessment_id = 0 ) {
		global $wpdb;
		$table = Database::table( 'submissions' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		if ( $assessment_id ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT result_title FROM {$table} WHERE result_title <> '' AND assessment_id = %d ORDER BY result_title", $assessment_id ) );
		} else {
			$rows = $wpdb->get_col( "SELECT DISTINCT result_title FROM {$table} WHERE result_title <> '' ORDER BY result_title LIMIT 200" );
		}
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Get one submission.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'submissions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Answers of a submission.
	 *
	 * @param int $id Submission id.
	 * @return array
	 */
	public static function answers( $id ) {
		global $wpdb;
		$table = Database::table( 'answers' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE submission_id = %d ORDER BY sort_order ASC", $id ), ARRAY_A );
	}

	/**
	 * Delete one submission.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		$wpdb->delete( Database::table( 'answers' ), array( 'submission_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( Database::table( 'submissions' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Display name of a submission row.
	 *
	 * @param array $row Row.
	 * @return string
	 */
	public static function display_name( array $row ) {
		if ( ! empty( $row['full_name'] ) ) {
			return $row['full_name'];
		}
		return trim( $row['first_name'] . ' ' . $row['last_name'] );
	}
}
