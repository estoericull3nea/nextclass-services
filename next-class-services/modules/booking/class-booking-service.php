<?php
/**
 * Booking service model.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Booking;

use NextClassServices\Database;
use NextClassServices\Helpers;
use NextClassServices\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads, sanitizes, validates and stores booking services.
 */
class Booking_Service {

	const META = '_ncs_config';

	/**
	 * Booking methods.
	 *
	 * @return array
	 */
	public static function methods() {
		return array(
			'calendly' => __( 'Calendly', 'next-class-services' ),
			'tidycal'  => __( 'TidyCal', 'next-class-services' ),
			'custom'   => __( 'Custom embed or URL', 'next-class-services' ),
			'native'   => __( 'Native WordPress booking', 'next-class-services' ),
		);
	}

	/**
	 * Default form fields for native bookings.
	 *
	 * @return array
	 */
	public static function default_fields() {
		return array(
			array( 'key' => 'name', 'label' => __( 'Name', 'next-class-services' ), 'type' => 'text', 'enabled' => true, 'required' => true, 'options' => array(), 'custom' => false ),
			array( 'key' => 'email', 'label' => __( 'Email', 'next-class-services' ), 'type' => 'email', 'enabled' => true, 'required' => true, 'options' => array(), 'custom' => false ),
			array( 'key' => 'phone', 'label' => __( 'Phone', 'next-class-services' ), 'type' => 'tel', 'enabled' => true, 'required' => false, 'options' => array(), 'custom' => false ),
			array( 'key' => 'company', 'label' => __( 'Company', 'next-class-services' ), 'type' => 'text', 'enabled' => true, 'required' => false, 'options' => array(), 'custom' => false ),
			array( 'key' => 'job_title', 'label' => __( 'Job title', 'next-class-services' ), 'type' => 'text', 'enabled' => false, 'required' => false, 'options' => array(), 'custom' => false ),
			array( 'key' => 'notes', 'label' => __( 'Notes', 'next-class-services' ), 'type' => 'textarea', 'enabled' => true, 'required' => false, 'options' => array(), 'custom' => false ),
		);
	}

	/**
	 * Default configuration.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title'        => '',
			'admin_title'  => '',
			'description'  => '',
			'duration'     => 30,
			'method'       => 'native',
			'display'      => array(
				'show_duration'    => true,
				'show_description' => true,
				'show_timezone'    => true,
				'button_text'      => __( 'Book a time', 'next-class-services' ),
			),
			'external'     => array(
				'url'          => '',
				'mode'         => 'embed',
				'height'       => 700,
				'custom_mode'  => 'url',
				'embed_source' => '',
			),
			'confirmation' => array(
				'message'      => __( 'Thank you, {customer_name}. Your booking for {service_name} on {appointment_date} at {appointment_time} ({timezone}) has been received. A confirmation email is on its way.', 'next-class-services' ),
				'redirect_url' => '',
			),
			'native'       => array(
				'google_sync'             => false,
				'days'                    => array( 1, 2, 3, 4, 5 ),
				'start'                   => '09:00',
				'end'                     => '17:00',
				'slot_step'               => 0,
				'buffer_before'           => 0,
				'buffer_after'            => 0,
				'min_notice_hours'        => 24,
				'max_advance_days'        => 60,
				'blocked_dates'           => '',
				'timezone'                => '',
				'max_per_day'             => 0,
				'new_status'              => 'confirmed',
				'allow_cancel'            => true,
				'allow_reschedule'        => true,
				'cancel_notice_hours'     => 24,
				'reschedule_notice_hours' => 24,
				'fields'                  => self::default_fields(),
			),
			'notify'       => array(
				'from_name'  => '',
				'reply_to'   => '',
				'customer'   => array(
					'enabled' => true,
					'subject' => __( "Your booking is confirmed: {service_name}", 'next-class-services' ),
					'body'    => __( "Dear {customer_name},\n\nThank you for booking with us. Your appointment details are below.\n\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time} ({timezone})\nStatus: {booking_status}\n\nIf your plans change, you can cancel or reschedule using this secure link:\n{manage_link}\n\nWe look forward to speaking with you.\n\nKind regards,\nThe Next Class Team", 'next-class-services' ),
				),
				'admin'      => array(
					'enabled'    => true,
					'recipients' => '',
					'subject'    => __( 'New booking: {service_name} with {customer_name}', 'next-class-services' ),
					'body'       => __( "Hello,\n\nA new booking has been made on your website.\n\nService: {service_name}\nCustomer: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\nCompany: {company}\nDate: {appointment_date}\nTime: {appointment_time} ({timezone})\nStatus: {booking_status}\nNotes: {notes}\n\nYou can manage this appointment in the WordPress admin under Next Class Services, Appointments.", 'next-class-services' ),
				),
				'cancel'     => array(
					'enabled'      => true,
					'notify_admin' => true,
					'subject'      => __( "Your booking has been cancelled: {service_name}", 'next-class-services' ),
					'body'         => __( "Dear {customer_name},\n\nThis is to confirm that your booking has been cancelled.\n\nService: {service_name}\nWas scheduled for: {appointment_date} at {appointment_time} ({timezone})\n\nIf this was a mistake, or you would like to book a new time, please visit our website or reply to this email and we will be happy to help.\n\nKind regards,\nThe Next Class Team", 'next-class-services' ),
				),
				'reschedule' => array(
					'enabled'      => true,
					'notify_admin' => true,
					'subject'      => __( "Your booking has been rescheduled: {service_name}", 'next-class-services' ),
					'body'         => __( "Dear {customer_name},\n\nYour booking for {service_name} has been moved to a new time.\n\nPrevious time: {previous_date} at {previous_time}\nNew time: {appointment_date} at {appointment_time} ({timezone})\n\nYou can cancel or reschedule again using this secure link:\n{manage_link}\n\nKind regards,\nThe Next Class Team", 'next-class-services' ),
				),
			),
		);
	}

	/**
	 * Recursive merge of saved values over defaults, keeping list values as saved.
	 *
	 * @param array $defaults Defaults.
	 * @param array $saved    Saved.
	 * @return array
	 */
	private static function merge( array $defaults, array $saved ) {
		$out = $defaults;
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				continue;
			}
			if ( is_array( $default ) && ! empty( $default ) && array_keys( $default ) !== range( 0, count( $default ) - 1 ) && is_array( $saved[ $key ] ) ) {
				$out[ $key ] = self::merge( $default, $saved[ $key ] );
			} else {
				$out[ $key ] = $saved[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Load a service.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || Post_Types::SERVICE !== $post->post_type ) {
			return null;
		}
		$saved  = json_decode( (string) get_post_meta( $post->ID, self::META, true ), true );
		$config = self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		if ( '' === $config['admin_title'] ) {
			$config['admin_title'] = $post->post_title;
		}
		return array(
			'id'     => (int) $post->ID,
			'status' => 'publish' === $post->post_status ? 'publish' : 'draft',
			'config' => $config,
		);
	}

	/**
	 * Parse the blocked dates textarea.
	 *
	 * @param string $text Text.
	 * @return array [ list of [from,to,label], invalid lines ]
	 */
	public static function parse_blocked( $text ) {
		$list    = array();
		$invalid = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:\s*(?:to|\.\.)\s*(\d{4}-\d{2}-\d{2}))?\s*(.*)$/i', $line, $m ) ) {
				$invalid[] = $line;
				continue;
			}
			$tz   = new \DateTimeZone( 'UTC' );
			$from = Helpers::parse_local_date( $m[1], $tz );
			$to   = '' !== $m[2] ? Helpers::parse_local_date( $m[2], $tz ) : $from;
			if ( ! $from || ! $to || $to < $from ) {
				$invalid[] = $line;
				continue;
			}
			$list[] = array(
				'from'  => $from->format( 'Y-m-d' ),
				'to'    => $to->format( 'Y-m-d' ),
				'label' => sanitize_text_field( $m[3] ),
			);
		}
		return array( $list, $invalid );
	}

	/**
	 * Build a sanitized config from POST.
	 *
	 * @param array $post     Unslashed POST.
	 * @param array $warnings Non fatal notices.
	 * @return array
	 */
	public static function from_request( array $post, array &$warnings ) {
		$d   = self::defaults();
		$cfg = isset( $post['cfg'] ) && is_array( $post['cfg'] ) ? $post['cfg'] : array();
		$sub = function ( $group ) use ( $cfg ) {
			return isset( $cfg[ $group ] ) && is_array( $cfg[ $group ] ) ? $cfg[ $group ] : array();
		};
		$str = function ( $arr, $key, $default = '' ) {
			return isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? (string) $arr[ $key ] : $default;
		};

		$method = isset( $cfg['method'] ) && isset( self::methods()[ $cfg['method'] ] ) ? $cfg['method'] : 'native';
		$c      = array(
			'title'       => sanitize_text_field( $str( $cfg, 'title' ) ),
			'admin_title' => sanitize_text_field( $str( $cfg, 'admin_title' ) ),
			'description' => wp_kses_post( $str( $cfg, 'description' ) ),
			'duration'    => max( 5, min( 480, (int) $str( $cfg, 'duration', '30' ) ) ),
			'method'      => $method,
		);

		$disp         = $sub( 'display' );
		$c['display'] = array(
			'show_duration'    => ! empty( $disp['show_duration'] ),
			'show_description' => ! empty( $disp['show_description'] ),
			'show_timezone'    => ! empty( $disp['show_timezone'] ),
			'button_text'      => sanitize_text_field( $str( $disp, 'button_text', $d['display']['button_text'] ) ),
		);

		// External configuration.
		$ex           = $sub( 'external' );
		$custom_mode  = 'embed' === $str( $ex, 'custom_mode' ) ? 'embed' : 'url';
		$mode         = 'link' === $str( $ex, 'mode' ) ? 'link' : 'embed';
		$source       = trim( $str( $ex, 'embed_source' ) );
		$url          = '';
		$url_error    = '';
		if ( 'calendly' === $method ) {
			list( $url, $url_error ) = Calendly::clean_url( $str( $ex, 'url' ) );
		} elseif ( 'tidycal' === $method ) {
			list( $url, $url_error ) = Tidycal::clean_url( $str( $ex, 'url' ) );
		} elseif ( 'custom' === $method ) {
			if ( 'embed' === $custom_mode ) {
				if ( current_user_can( 'manage_options' ) ) {
					list( $url, $url_error ) = Custom_Booking::clean_embed( $source );
				} else {
					$url_error = __( 'Only site administrators can configure a custom embed.', 'next-class-services' );
				}
			} else {
				list( $url, $url_error ) = Custom_Booking::clean_url( $str( $ex, 'url' ) );
			}
		}
		if ( '' !== $url_error && in_array( $method, array( 'calendly', 'tidycal', 'custom' ), true ) ) {
			$warnings[] = $url_error;
		}
		$c['external'] = array(
			'url'          => $url,
			'mode'         => $mode,
			'height'       => max( 300, min( 1600, (int) $str( $ex, 'height', '700' ) ) ),
			'custom_mode'  => $custom_mode,
			'embed_source' => '',
		);

		$conf         = $sub( 'confirmation' );
		$c['confirmation'] = array(
			'message'      => sanitize_textarea_field( $str( $conf, 'message', $d['confirmation']['message'] ) ),
			'redirect_url' => Helpers::safe_url( $str( $conf, 'redirect_url' ) ),
		);

		// Native scheduling.
		$n    = $sub( 'native' );
		$days = array();
		if ( isset( $n['days'] ) && is_array( $n['days'] ) ) {
			foreach ( $n['days'] as $day ) {
				$day = (int) $day;
				if ( $day >= 1 && $day <= 7 ) {
					$days[] = $day;
				}
			}
		}
		$days  = array_values( array_unique( $days ) );
		$tz    = Helpers::sanitize_timezone( $str( $n, 'timezone' ) );
		if ( '' !== $str( $n, 'timezone' ) && '' === $tz ) {
			$warnings[] = __( 'The timezone was not recognised and the site timezone will be used.', 'next-class-services' );
		}
		list( $blocked_list, $invalid ) = self::parse_blocked( $str( $n, 'blocked_dates' ) );
		if ( ! empty( $invalid ) ) {
			$warnings[] = sprintf(
				/* translators: %s: invalid lines */
				__( 'These blocked date lines were ignored because the format was not valid: %s', 'next-class-services' ),
				implode( '; ', array_map( 'sanitize_text_field', $invalid ) )
			);
		}
		$blocked_text = array();
		foreach ( $blocked_list as $b ) {
			$blocked_text[] = trim( $b['from'] . ( $b['to'] !== $b['from'] ? ' to ' . $b['to'] : '' ) . ' ' . $b['label'] );
		}

		$c['native'] = array(
			'google_sync'             => 'native' === $method && ! empty( $n['google_sync'] ),
			'days'                    => $days,
			'start'                   => self::clean_time( $str( $n, 'start' ), '09:00' ),
			'end'                     => self::clean_time( $str( $n, 'end' ), '17:00' ),
			'slot_step'               => max( 0, min( 480, (int) $str( $n, 'slot_step', '0' ) ) ),
			'buffer_before'           => max( 0, min( 240, (int) $str( $n, 'buffer_before', '0' ) ) ),
			'buffer_after'            => max( 0, min( 240, (int) $str( $n, 'buffer_after', '0' ) ) ),
			'min_notice_hours'        => max( 0, min( 8760, (int) $str( $n, 'min_notice_hours', '0' ) ) ),
			'max_advance_days'        => max( 1, min( 730, (int) $str( $n, 'max_advance_days', '60' ) ) ),
			'blocked_dates'           => implode( "\n", $blocked_text ),
			'timezone'                => $tz,
			'max_per_day'             => max( 0, min( 500, (int) $str( $n, 'max_per_day', '0' ) ) ),
			'new_status'              => 'pending' === $str( $n, 'new_status' ) ? 'pending' : 'confirmed',
			'allow_cancel'            => ! empty( $n['allow_cancel'] ),
			'allow_reschedule'        => ! empty( $n['allow_reschedule'] ),
			'cancel_notice_hours'     => max( 0, min( 8760, (int) $str( $n, 'cancel_notice_hours', '0' ) ) ),
			'reschedule_notice_hours' => max( 0, min( 8760, (int) $str( $n, 'reschedule_notice_hours', '0' ) ) ),
			'fields'                  => self::sanitize_fields( isset( $n['fields'] ) ? $n['fields'] : array(), isset( $n['custom'] ) ? $n['custom'] : array() ),
		);

		// Notifications.
		$nt      = $sub( 'notify' );
		$reply   = sanitize_email( $str( $nt, 'reply_to' ) );
		$bad_rep = '' !== $str( $nt, 'reply_to' ) && ( ! $reply || ! is_email( $reply ) );
		if ( $bad_rep ) {
			$warnings[] = __( 'The reply-to address was not a valid email and was removed.', 'next-class-services' );
		}
		$get   = function ( $key ) use ( $nt ) {
			return isset( $nt[ $key ] ) && is_array( $nt[ $key ] ) ? $nt[ $key ] : array();
		};
		$cu    = $get( 'customer' );
		$ad    = $get( 'admin' );
		$ca    = $get( 'cancel' );
		$re    = $get( 'reschedule' );
		$c['notify'] = array(
			'from_name'  => Helpers::single_line( $str( $nt, 'from_name' ) ),
			'reply_to'   => $bad_rep ? '' : $reply,
			'customer'   => array(
				'enabled' => ! empty( $cu['enabled'] ),
				'subject' => Helpers::single_line( $str( $cu, 'subject' ) ),
				'body'    => wp_kses_post( $str( $cu, 'body' ) ),
			),
			'admin'      => array(
				'enabled'    => ! empty( $ad['enabled'] ),
				'recipients' => implode( ', ', Helpers::parse_emails( $str( $ad, 'recipients' ) ) ),
				'subject'    => Helpers::single_line( $str( $ad, 'subject' ) ),
				'body'       => wp_kses_post( $str( $ad, 'body' ) ),
			),
			'cancel'     => array(
				'enabled'      => ! empty( $ca['enabled'] ),
				'notify_admin' => ! empty( $ca['notify_admin'] ),
				'subject'      => Helpers::single_line( $str( $ca, 'subject' ) ),
				'body'         => wp_kses_post( $str( $ca, 'body' ) ),
			),
			'reschedule' => array(
				'enabled'      => ! empty( $re['enabled'] ),
				'notify_admin' => ! empty( $re['notify_admin'] ),
				'subject'      => Helpers::single_line( $str( $re, 'subject' ) ),
				'body'         => wp_kses_post( $str( $re, 'body' ) ),
			),
		);
		return $c;
	}

	/**
	 * Clean an HH:MM time.
	 *
	 * @param string $value   Value.
	 * @param string $default Fallback.
	 * @return string
	 */
	public static function clean_time( $value, $default ) {
		if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) $value ), $m ) ) {
			return sprintf( '%02d:%s', (int) $m[1], $m[2] );
		}
		return $default;
	}

	/**
	 * Sanitize the form field configuration.
	 *
	 * @param array $builtin Built-in field settings keyed by key.
	 * @param array $custom  Custom field rows.
	 * @return array
	 */
	private static function sanitize_fields( $builtin, $custom ) {
		$out = array();
		foreach ( self::default_fields() as $def ) {
			$in  = is_array( $builtin ) && isset( $builtin[ $def['key'] ] ) && is_array( $builtin[ $def['key'] ] ) ? $builtin[ $def['key'] ] : array();
			$lab = sanitize_text_field( isset( $in['label'] ) ? $in['label'] : '' );
			if ( '' !== $lab ) {
				$def['label'] = $lab;
			}
			$def['enabled']  = ! empty( $in['enabled'] );
			$def['required'] = ! empty( $in['required'] );
			if ( in_array( $def['key'], array( 'name', 'email' ), true ) ) {
				$def['enabled']  = true;
				$def['required'] = true;
			}
			$out[] = $def;
		}
		if ( is_array( $custom ) ) {
			$i = 0;
			foreach ( array_slice( $custom, 0, 10 ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = sanitize_text_field( isset( $row['label'] ) ? $row['label'] : '' );
				if ( '' === $label ) {
					continue;
				}
				$type    = isset( $row['type'] ) && in_array( $row['type'], array( 'text', 'textarea', 'select' ), true ) ? $row['type'] : 'text';
				$options = array();
				if ( 'select' === $type ) {
					foreach ( preg_split( '/\r\n|\r|\n|,/', isset( $row['options'] ) ? (string) $row['options'] : '' ) as $opt ) {
						$opt = sanitize_text_field( $opt );
						if ( '' !== $opt ) {
							$options[] = $opt;
						}
					}
				}
				$key = Helpers::sanitize_id( isset( $row['key'] ) ? $row['key'] : '' );
				if ( 0 !== strpos( $key, 'custom_' ) || strlen( $key ) < 8 ) {
					$key = 'custom_' . strtolower( wp_generate_password( 6, false, false ) );
				}
				$out[] = array(
					'key'      => $key,
					'label'    => $label,
					'type'     => $type,
					'enabled'  => true,
					'required' => ! empty( $row['required'] ),
					'options'  => $options,
					'custom'   => true,
				);
				$i++;
			}
		}
		return $out;
	}

	/**
	 * Validate for publishing.
	 *
	 * @param array $c Config.
	 * @return array errors
	 */
	public static function validate( array $c ) {
		$errors = array();
		if ( '' === $c['title'] ) {
			$errors[] = array(
				'message' => __( 'Enter a public service name.', 'next-class-services' ),
				'tab'     => 'general',
				'field'   => 'ncs-cfg-title',
			);
		}
		if ( in_array( $c['method'], array( 'calendly', 'tidycal', 'custom' ), true ) && '' === $c['external']['url'] ) {
			$errors[] = array(
				'message' => __( 'External booking services need a valid booking URL.', 'next-class-services' ),
				'tab'     => 'method',
				'field'   => 'ncs-cfg-external-url',
			);
		}
		if ( 'native' === $c['method'] ) {
			$n = $c['native'];
			if ( empty( $n['days'] ) ) {
				$errors[] = array(
				'message' => __( 'Select at least one available working day.', 'next-class-services' ),
				'tab'     => 'method',
				'field'   => 'ncs-native-days',
			);
			}
			list( $sh, $sm ) = array_map( 'intval', explode( ':', $n['start'] ) );
			list( $eh, $em ) = array_map( 'intval', explode( ':', $n['end'] ) );
			$start_min       = $sh * 60 + $sm;
			$end_min         = $eh * 60 + $em;
			if ( $end_min <= $start_min ) {
				$errors[] = array(
				'message' => __( 'The end time must be later than the start time.', 'next-class-services' ),
				'tab'     => 'method',
				'field'   => 'ncs-cfg-native-end',
			);
			} elseif ( $end_min - $start_min < $c['duration'] ) {
				$errors[] = array(
				'message' => __( 'The available hours are shorter than the appointment duration.', 'next-class-services' ),
				'tab'     => 'method',
				'field'   => 'ncs-cfg-native-end',
			);
			}
			if ( $n['max_advance_days'] * 24 < $n['min_notice_hours'] ) {
				$errors[] = array(
				'message' => __( 'The maximum advance booking period cannot be shorter than the minimum notice.', 'next-class-services' ),
				'tab'     => 'method',
				'field'   => 'ncs-cfg-native-max-advance-days',
			);
			}
		}
		return $errors;
	}

	/**
	 * Save.
	 *
	 * @param int    $id     Post id or 0.
	 * @param array  $config Config.
	 * @param string $status publish|draft.
	 * @return int
	 */
	public static function save( $id, array $config, $status ) {
		$title = '' !== $config['admin_title'] ? $config['admin_title'] : ( '' !== $config['title'] ? $config['title'] : __( 'Untitled service', 'next-class-services' ) );
		$args  = array(
			'post_type'   => Post_Types::SERVICE,
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
	 * Set status.
	 *
	 * @param int    $id     Id.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		$post = get_post( $id );
		if ( ! $post || Post_Types::SERVICE !== $post->post_type ) {
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
	 * Duplicate as draft.
	 *
	 * @param int $id Source.
	 * @return int
	 */
	public static function duplicate( $id ) {
		$src = self::get( $id );
		if ( ! $src ) {
			return 0;
		}
		$cfg                = $src['config'];
		/* translators: %s: service name */
		$cfg['admin_title'] = sprintf( __( '%s (copy)', 'next-class-services' ), $cfg['admin_title'] );
		return self::save( 0, $cfg, 'draft' );
	}

	/**
	 * Delete a service. Existing appointments are kept with their snapshot title.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$post = get_post( $id );
		if ( ! $post || Post_Types::SERVICE !== $post->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $post->ID, true );
	}

	/**
	 * Admin list query.
	 *
	 * @param int    $paged    Page.
	 * @param int    $per_page Per page.
	 * @param string $search   Search.
	 * @return \WP_Query
	 */
	public static function query( $paged = 1, $per_page = 20, $search = '' ) {
		$args = array(
			'post_type'      => Post_Types::SERVICE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		return new \WP_Query( $args );
	}

	/**
	 * All services for dropdowns.
	 *
	 * @param bool $published_only Only published.
	 * @return array id => admin title
	 */
	public static function options( $published_only = false ) {
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::SERVICE,
				'post_status'    => $published_only ? 'publish' : array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $posts as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}

	/**
	 * Count by status.
	 *
	 * @return array
	 */
	public static function counts() {
		$c = wp_count_posts( Post_Types::SERVICE );
		return array(
			'publish' => isset( $c->publish ) ? (int) $c->publish : 0,
			'draft'   => isset( $c->draft ) ? (int) $c->draft : 0,
		);
	}

	/**
	 * Shortcode text.
	 *
	 * @param int $id Id.
	 * @return string
	 */
	public static function shortcode( $id ) {
		return '[next_class_booking id="' . absint( $id ) . '"]';
	}

	/**
	 * Public safe view of a service.
	 *
	 * @param int   $id Id.
	 * @param array $c  Config.
	 * @return array
	 */
	public static function public_config( $id, array $c ) {
		$out = array(
			'id'          => (int) $id,
			'name'        => $c['title'],
			'description' => $c['display']['show_description'] ? $c['description'] : '',
			'duration'    => $c['duration'],
			'showDuration' => (bool) $c['display']['show_duration'],
			'showTimezone' => (bool) $c['display']['show_timezone'],
			'method'      => $c['method'],
			'buttonText'  => $c['display']['button_text'],
		);
		if ( 'native' === $c['method'] ) {
			$fields = array();
			foreach ( $c['native']['fields'] as $f ) {
				if ( $f['enabled'] ) {
					$fields[] = $f;
				}
			}
			$tz              = Helpers::timezone( $c['native']['timezone'] );
			$out['timezone'] = $tz->getName();
			$out['tzLabel']  = $tz->getName();
			$out['fields']   = $fields;
			$out['allowCancel']     = (bool) $c['native']['allow_cancel'];
			$out['allowReschedule'] = (bool) $c['native']['allow_reschedule'];
			$out['redirect']        = $c['confirmation']['redirect_url'];
		} else {
			$out['url']    = $c['external']['url'];
			$out['mode']   = $c['external']['mode'];
			$out['height'] = $c['external']['height'];
			if ( 'custom' === $c['method'] ) {
				$out['mode'] = 'embed' === $c['external']['custom_mode'] ? 'embed' : $c['external']['mode'];
			}
			$out['embedUrl'] = 'calendly' === $c['method'] ? Calendly::embed_url( $c['external']['url'] ) : ( 'tidycal' === $c['method'] ? Tidycal::embed_url( $c['external']['url'] ) : $c['external']['url'] );
		}
		return $out;
	}

	/**
	 * Appointment counts per service.
	 *
	 * @return array
	 */
	public static function upcoming_counts() {
		global $wpdb;
		$table = Database::table( 'appointments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT service_id, COUNT(*) c FROM {$table} WHERE status IN ('pending','confirmed') AND start_utc >= %s GROUP BY service_id", Helpers::now_utc() ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['service_id'] ] = (int) $r['c'];
		}
		return $out;
	}
}
