<?php
/**
 * Shared static helpers.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility functions used across the plugin.
 */
class Helpers {

	const OPTION_SETTINGS = 'ncs_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'default_notification_email' => '',
			'from_name'                  => '',
			'from_email'                 => '',
			'default_timezone'           => '',
			'retention_mode'             => 'indefinite',
			'retention_custom_days'      => 730,
			'ga4_enabled'                => 0,
			'analytics_enabled'          => 1,
			'frontend_primary_color'     => '#393939',
			'frontend_radius'            => 4,
			'shared_calendar'            => 1,
			'manage_page_url'            => '',
			'embed_host_allowlist'       => '',
			'delete_on_uninstall'        => 0,
			'debug_log'                  => 0,
			'spam_trap_enabled'          => 1,
			'min_seconds'                => 2,
			'rate_limit_enabled'         => 1,
			'rate_window_minutes'        => 10,
			'rate_assessment'            => 40,
			'rate_booking_create'        => 20,
			'rate_booking_manage'        => 30,
			'rate_lookup_per_minute'     => 90,
			'trust_proxy_headers'        => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION_SETTINGS, array() );
			self::$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
		}
		return self::$settings;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function setting( $key, $default = null ) {
		$all = self::settings();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Save settings and reset cache.
	 *
	 * @param array $settings Settings.
	 */
	public static function save_settings( array $settings ) {
		update_option( self::OPTION_SETTINGS, $settings, true );
		self::$settings = null;
	}

	/**
	 * Retention period in days, 0 for indefinite.
	 *
	 * @return int
	 */
	public static function retention_days() {
		$mode = self::setting( 'retention_mode', 'indefinite' );
		if ( 'indefinite' === $mode ) {
			return 0;
		}
		if ( 'custom' === $mode ) {
			return max( 1, (int) self::setting( 'retention_custom_days', 730 ) );
		}
		return max( 0, (int) $mode );
	}

	/**
	 * Default notification address.
	 *
	 * @return string
	 */
	public static function notification_email() {
		$email = (string) self::setting( 'default_notification_email', '' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Resolve a DateTimeZone, falling back to the WordPress timezone.
	 *
	 * @param string $name Timezone identifier or empty.
	 * @return \DateTimeZone
	 */
	public static function timezone( $name = '' ) {
		if ( '' === $name ) {
			$name = (string) self::setting( 'default_timezone', '' );
		}
		if ( '' !== $name ) {
			try {
				return new \DateTimeZone( $name );
			} catch ( \Exception $e ) {
				unset( $e );
			}
		}
		return wp_timezone();
	}

	/**
	 * Valid timezone identifier or empty string.
	 *
	 * @param string $name Identifier.
	 * @return string
	 */
	public static function sanitize_timezone( $name ) {
		$name = sanitize_text_field( (string) $name );
		if ( '' === $name ) {
			return '';
		}
		return in_array( $name, timezone_identifiers_list(), true ) ? $name : '';
	}

	/**
	 * Current UTC datetime in MySQL format.
	 *
	 * @return string
	 */
	public static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Format a UTC MySQL datetime into a site-timezone string.
	 *
	 * @param string $utc    UTC datetime.
	 * @param string $format PHP date format.
	 * @param string $tz     Timezone identifier.
	 * @return string
	 */
	public static function format_utc( $utc, $format = '', $tz = '' ) {
		if ( empty( $utc ) || '0000-00-00 00:00:00' === $utc ) {
			return '';
		}
		if ( '' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		try {
			$dt = new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) );
			$dt = $dt->setTimezone( '' === $tz ? wp_timezone() : self::timezone( $tz ) );
			return wp_date( $format, $dt->getTimestamp(), $dt->getTimezone() );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Sanitize a hex color, falling back to default.
	 *
	 * @param string $color   Color.
	 * @param string $default Fallback.
	 * @return string
	 */
	public static function hex( $color, $default = '#ff954f' ) {
		$color = sanitize_hex_color( (string) $color );
		return $color ? $color : $default;
	}

	/**
	 * Generate a short unique id for builder items.
	 *
	 * @param string $prefix Prefix.
	 * @return string
	 */
	public static function uid( $prefix = 'i' ) {
		return $prefix . strtolower( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Sanitize an id used as an array key by builders.
	 *
	 * @param string $id Raw id.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		$id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $id );
		return substr( (string) $id, 0, 40 );
	}

	/**
	 * Encode data so it is safe inside an HTML script tag or attribute.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function json_for_html( $data ) {
		return wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Decode JSON posted from the admin builders.
	 *
	 * @param mixed $raw Raw value.
	 * @return array
	 */
	public static function decode_json_list( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$data = json_decode( wp_unslash( $raw ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Keep a string free of line breaks (header injection safe).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function single_line( $value ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $value ) ) );
	}

	/**
	 * Parse a comma or newline separated list of emails, keeping valid ones only.
	 *
	 * @param string $raw Raw text.
	 * @return array
	 */
	public static function parse_emails( $raw ) {
		$parts = preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( $part );
			if ( $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Return an https URL or empty string.
	 *
	 * @param string $url           URL.
	 * @param bool   $require_https Require https.
	 * @return string
	 */
	public static function safe_url( $url, $require_https = false ) {
		$url = esc_url_raw( trim( (string) $url ), $require_https ? array( 'https' ) : array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		// Structural checks only. wp_http_validate_url() resolves DNS, which is slow and
		// unnecessary because these URLs are only ever sent to the visitor's browser.
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-.]*[a-z0-9])?$/i', $parts['host'] ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Hash of the client IP for short-lived rate limiting. Never stored long term.
	 *
	 * @return string
	 */
	public static function client_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( self::setting( 'trust_proxy_headers' ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					$parts     = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
					$candidate = trim( $parts[0] );
					if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
						$ip = $candidate;
						break;
					}
				}
			}
		}
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 24 );
	}

	/**
	 * Simple transient based rate limiter.
	 *
	 * @param string $bucket  Bucket name.
	 * @param int    $limit   Max hits in window.
	 * @param int    $window  Window seconds.
	 * @return bool True when the request is allowed.
	 */
	public static function rate_limit( $bucket, $limit = 10, $window = 60 ) {
		if ( ! self::setting( 'rate_limit_enabled' ) ) {
			return true;
		}
		$key   = 'ncs_rl_' . md5( $bucket . '|' . self::client_hash() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Write to the debug log when enabled.
	 *
	 * @param string $message Message.
	 * @param mixed  $context Optional context.
	 */
	public static function log( $message, $context = null ) {
		if ( ! self::setting( 'debug_log' ) && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}
		$line = '[Next Class Services] ' . $message;
		if ( null !== $context ) {
			$line .= ' ' . wp_json_encode( $context );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Escape a value for spreadsheet safety (CSV formula injection).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		$value = (string) $value;
		if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	/**
	 * Stream a CSV download and exit.
	 *
	 * @param string $filename Filename without extension.
	 * @param array  $headers  Header labels.
	 * @param array  $rows     Rows (arrays).
	 */
	public static function send_csv( $filename, array $headers, array $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $headers ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $row ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Render a template from the templates directory.
	 *
	 * @param string $template Relative path without extension.
	 * @param array  $vars     Variables.
	 * @return string
	 */
	public static function render( $template, array $vars = array() ) {
		$file = NCS_PATH . 'templates/' . ltrim( $template, '/' ) . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Truncate a string safely.
	 *
	 * @param string $value Value.
	 * @param int    $len   Max length.
	 * @return string
	 */
	public static function truncate( $value, $len ) {
		$value = (string) $value;
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $len ) : substr( $value, 0, $len );
	}

	/**
	 * Parse a date range request into UTC bounds.
	 *
	 * @param string $range Range key.
	 * @param string $from  Custom start (Y-m-d).
	 * @param string $to    Custom end (Y-m-d).
	 * @return array [start_utc, end_utc, label, from, to]
	 */
	public static function date_range( $range, $from = '', $to = '' ) {
		$tz    = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $tz );
		$end   = $today->setTime( 23, 59, 59 );
		switch ( $range ) {
			case 'today':
				$start = $today;
				break;
			case '7d':
				$start = $today->modify( '-6 days' );
				break;
			case 'quarter':
				$month = (int) $today->format( 'n' );
				$qm    = ( (int) floor( ( $month - 1 ) / 3 ) * 3 ) + 1;
				$start = $today->setDate( (int) $today->format( 'Y' ), $qm, 1 );
				break;
			case 'custom':
				$start = self::parse_local_date( $from, $tz ) ?: $today->modify( '-29 days' );
				$e     = self::parse_local_date( $to, $tz );
				if ( $e ) {
					$end = $e->setTime( 23, 59, 59 );
				}
				if ( $end < $start ) {
					$end = $start->setTime( 23, 59, 59 );
				}
				break;
			case '30d':
			default:
				$range = '30d';
				$start = $today->modify( '-29 days' );
				break;
		}
		$utc = new \DateTimeZone( 'UTC' );
		return array(
			'start' => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'end'   => $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'range' => $range,
			'from'  => $start->format( 'Y-m-d' ),
			'to'    => $end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Parse a Y-m-d date in a timezone.
	 *
	 * @param string        $date Date.
	 * @param \DateTimeZone $tz   Timezone.
	 * @return \DateTimeImmutable|null
	 */
	public static function parse_local_date( $date, \DateTimeZone $tz ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return null;
		}
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return null;
		}
		return $dt;
	}
}
