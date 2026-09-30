<?php
/**
 * Small form field renderer for admin screens.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints labelled, escaped form controls.
 */
class Form {

	/**
	 * Build an id from a field name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function id( $name ) {
		return 'ncs-' . trim( preg_replace( '/[^a-z0-9]+/i', '-', $name ), '-' );
	}

	/**
	 * Example placeholders used when a field does not set its own.
	 *
	 * @return array
	 */
	private static function examples() {
		return array(
			'cfg[admin_title]'                     => __( 'e.g. Workplace Communication Diagnostic (internal)', 'next-class-services' ),
			'cfg[title]'                           => __( 'e.g. Workplace Communication Diagnostic', 'next-class-services' ),
			'cfg[description]'                     => __( 'e.g. Find out how well your team communicates in about 5 minutes.', 'next-class-services' ),
			'cfg[intro]'                           => __( 'e.g. Answer 10 short questions. There are no right or wrong answers.', 'next-class-services' ),
			'cfg[start_text]'                      => __( 'e.g. Start the assessment', 'next-class-services' ),
			'cfg[prev_text]'                       => __( 'e.g. Previous', 'next-class-services' ),
			'cfg[next_text]'                       => __( 'e.g. Next', 'next-class-services' ),
			'cfg[submit_text]'                     => __( 'e.g. See my results', 'next-class-services' ),
			'cfg[lead_submit_text]'                => __( 'e.g. Show my results', 'next-class-services' ),
			'cfg[completion_message]'              => __( 'e.g. Thank you for completing the assessment.', 'next-class-services' ),
			'cfg[result_heading]'                  => __( 'e.g. Your result', 'next-class-services' ),
			'cfg[lead][heading]'                   => __( 'e.g. Where should we send your results?', 'next-class-services' ),
			'cfg[lead][intro]'                     => __( 'e.g. Enter your details to see your personalized result.', 'next-class-services' ),
			'cfg[lead][privacy][text]'             => __( 'e.g. I agree to the privacy policy.', 'next-class-services' ),
			'cfg[lead][marketing][text]'           => __( 'e.g. I would like to receive news and updates.', 'next-class-services' ),
			'cfg[email][from_name]'                => __( 'e.g. Next Class', 'next-class-services' ),
			'cfg[email][reply_to]'                 => 'hello@yourdomain.com',
			'cfg[email][subject]'                  => __( 'e.g. Your {assessment_name} results', 'next-class-services' ),
			'cfg[email][heading]'                  => __( 'e.g. Your results are ready', 'next-class-services' ),
			'cfg[email][intro]'                    => __( 'e.g. Hi {first_name}, thank you for completing the assessment.', 'next-class-services' ),
			'cfg[email][body]'                     => __( 'e.g. Your result: {result_title}', 'next-class-services' ),
			'cfg[email][cta_label]'                => __( 'e.g. Book a consultation', 'next-class-services' ),
			'cfg[email][footer]'                   => __( 'e.g. You received this email because you took our assessment.', 'next-class-services' ),
			'cfg[admin_notify][recipients]'        => 'team@yourdomain.com, sales@yourdomain.com',
			'cfg[admin_notify][subject]'           => __( 'e.g. New assessment completed: {assessment_name}', 'next-class-services' ),
			'cfg[admin_notify][message]'           => __( 'e.g. {full_name} ({email}) scored {percentage}%.', 'next-class-services' ),
			'cfg[duration]'                        => '30',
			'cfg[display][button_text]'            => __( 'e.g. Book a time', 'next-class-services' ),
			'cfg[external][embed_source]'          => '<iframe src="https://booking.example.com/embed"></iframe>',
			'cfg[external][height]'                => '700',
			'cfg[native][start]'                   => '09:00',
			'cfg[native][end]'                     => '17:00',
			'cfg[native][slot_step]'               => '30',
			'cfg[native][buffer_before]'           => '10',
			'cfg[native][buffer_after]'            => '10',
			'cfg[native][max_per_day]'             => '8',
			'cfg[native][min_notice_hours]'        => '24',
			'cfg[native][max_advance_days]'        => '60',
			'cfg[native][blocked_dates]'           => "2026-12-25 Holiday\n2026-12-24 to 2026-12-31 Closed",
			'cfg[native][cancel_notice_hours]'     => '24',
			'cfg[native][reschedule_notice_hours]' => '24',
			'cfg[confirmation][message]'           => __( 'e.g. Thank you, {customer_name}. Your booking is received.', 'next-class-services' ),
			'cfg[notify][from_name]'               => __( 'e.g. Next Class', 'next-class-services' ),
			'cfg[notify][reply_to]'                => 'hello@yourdomain.com',
			'cfg[notify][customer][subject]'       => __( 'e.g. Your booking is confirmed: {service_name}', 'next-class-services' ),
			'cfg[notify][customer][body]'          => __( 'e.g. Hi {customer_name}, see you on {appointment_date} at {appointment_time}.', 'next-class-services' ),
			'cfg[notify][admin][recipients]'       => 'team@yourdomain.com',
			'cfg[notify][admin][subject]'          => __( 'e.g. New booking: {service_name} with {customer_name}', 'next-class-services' ),
			'cfg[notify][admin][body]'             => __( 'e.g. Customer: {customer_name} ({customer_email})', 'next-class-services' ),
			'cfg[notify][cancel][subject]'         => __( 'e.g. Booking cancelled: {service_name}', 'next-class-services' ),
			'cfg[notify][cancel][body]'            => __( 'e.g. Hi {customer_name}, your booking has been cancelled.', 'next-class-services' ),
			'cfg[notify][reschedule][subject]'     => __( 'e.g. Booking rescheduled: {service_name}', 'next-class-services' ),
			'cfg[notify][reschedule][body]'        => __( 'e.g. Your booking moved to {appointment_date} at {appointment_time}.', 'next-class-services' ),
			's[default_notification_email]'        => 'notifications@yourdomain.com',
			's[from_name]'                         => __( 'e.g. Next Class', 'next-class-services' ),
			's[from_email]'                        => 'hello@yourdomain.com',
			's[manage_page_url]'                   => 'https://yourdomain.com/book/',
			's[embed_host_allowlist]'              => 'booking.example.com, calendar.example.org',
			's[retention_custom_days]'             => '730',
			's[frontend_radius]'                   => '4',
			'test_to'                              => 'you@yourdomain.com',
		);
	}

	/**
	 * Wrap a control.
	 *
	 * @param string $label Label.
	 * @param string $id    Control id.
	 * @param string $html  Control HTML (already escaped).
	 * @param array  $args  help, class, required.
	 */
	private static function wrap( $label, $id, $html, array $args ) {
		$class = 'ncs-field' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
		echo '<div class="' . esc_attr( $class ) . '">';
		if ( '' !== $label ) {
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label );
			if ( ! empty( $args['required'] ) ) {
				echo ' <span class="ncs-req">' . esc_html__( '(required)', 'next-class-services' ) . '</span>';
			}
			echo '</label>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $args['help'] ) ) {
			echo '<p class="ncs-help" id="' . esc_attr( $id ) . '-help">' . wp_kses( $args['help'], array( 'code' => array(), 'strong' => array(), 'br' => array() ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Attribute string for extra attributes.
	 *
	 * @param array  $args Args.
	 * @param string $id   Id.
	 * @param string $name Field name, used to look up an example placeholder.
	 * @return string
	 */
	private static function attrs( array $args, $id, $name = '' ) {
		$out = '';
		if ( empty( $args['placeholder'] ) && '' !== $name ) {
			$examples = self::examples();
			if ( isset( $examples[ $name ] ) ) {
				$args['placeholder'] = $examples[ $name ];
			}
		}
		if ( ! empty( $args['help'] ) ) {
			$out .= ' aria-describedby="' . esc_attr( $id ) . '-help"';
		}
		if ( ! empty( $args['placeholder'] ) ) {
			$out .= ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
		}
		if ( ! empty( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
			foreach ( $args['attrs'] as $k => $v ) {
				$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		return $out;
	}

	/**
	 * Text-like input.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param array  $args  type, help, placeholder, class, attrs, required.
	 */
	public static function text( $name, $label, $value, array $args = array() ) {
		$id   = self::id( $name );
		$type = isset( $args['type'] ) ? $args['type'] : 'text';
		if ( in_array( $type, array( 'time', 'color', 'date' ), true ) ) {
			$args['placeholder'] = '';
		}
		$html = '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . self::attrs( $args, $id, $name ) . '>';
		self::wrap( $label, $id, $html, $args );
	}

	/**
	 * Number input.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param array  $args  min, max, step plus text args.
	 */
	public static function number( $name, $label, $value, array $args = array() ) {
		$attrs = isset( $args['attrs'] ) ? $args['attrs'] : array();
		foreach ( array( 'min', 'max', 'step' ) as $k ) {
			if ( isset( $args[ $k ] ) ) {
				$attrs[ $k ] = $args[ $k ];
			}
		}
		$args['attrs'] = $attrs;
		$args['type']  = 'number';
		$args['class'] = ( isset( $args['class'] ) ? $args['class'] . ' ' : '' ) . 'ncs-field-narrow';
		self::text( $name, $label, $value, $args );
	}

	/**
	 * Textarea.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param array  $args  rows plus text args.
	 */
	public static function textarea( $name, $label, $value, array $args = array() ) {
		$id   = self::id( $name );
		$rows = isset( $args['rows'] ) ? (int) $args['rows'] : 4;
		$html = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . $rows . '"' . self::attrs( $args, $id, $name ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
		self::wrap( $label, $id, $html, $args );
	}

	/**
	 * Select.
	 *
	 * @param string $name    Name.
	 * @param string $label   Label.
	 * @param array  $options value => label.
	 * @param string $current Current value.
	 * @param array  $args    Args.
	 */
	public static function select( $name, $label, array $options, $current, array $args = array() ) {
		$id   = self::id( $name );
		$html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . self::attrs( $args, $id ) . '>';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		$html .= '</select>';
		self::wrap( $label, $id, $html, $args );
	}

	/**
	 * Checkbox.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param bool   $on    Checked.
	 * @param array  $args  help, value, class.
	 */
	public static function checkbox( $name, $label, $on, array $args = array() ) {
		$id    = self::id( $name );
		$value = isset( $args['value'] ) ? $args['value'] : '1';
		$class = 'ncs-field ncs-field-check' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
		echo '<div class="' . esc_attr( $class ) . '"><label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . checked( (bool) $on, true, false ) . self::attrs( $args, $id ) . '> ' . esc_html( $label ) . '</label>';
		if ( ! empty( $args['help'] ) ) {
			echo '<p class="ncs-help" id="' . esc_attr( $id ) . '-help">' . wp_kses( $args['help'], array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</div>';
	}
}
