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
	 * @param array $args Args.
	 * @param string $id  Id.
	 * @return string
	 */
	private static function attrs( array $args, $id ) {
		$out = '';
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
		$html = '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . self::attrs( $args, $id ) . '>';
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
		$html = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . $rows . '"' . self::attrs( $args, $id ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
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
