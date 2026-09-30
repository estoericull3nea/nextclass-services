<?php
/**
 * [next_class_assessment] shortcode.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Front;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assets;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the assessment container. The interactive UI is built by assessment.js.
 */
class Assessment_Shortcode {

	/**
	 * Instance counter for unique DOM ids.
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'next_class_assessment' );
		return self::render_assessment( absint( $atts['id'] ), false );
	}

	/**
	 * Render an assessment by id.
	 *
	 * @param int  $id            Assessment id.
	 * @param bool $force_preview Force preview mode (admin preview screen).
	 * @return string
	 */
	public static function render_assessment( $id, $force_preview = false ) {
		$can_manage = current_user_can( 'ncs_manage_assessments' );
		$data       = $id ? Assessment::get( $id ) : null;

		if ( ! $data ) {
			return $can_manage ? self::notice( __( 'Next Class Services: this assessment could not be found. Check the shortcode ID.', 'next-class-services' ) ) : '';
		}
		$published = 'publish' === $data['status'];
		if ( ! $published && ! $can_manage ) {
			return '';
		}
		$preview = $force_preview || ! $published;
		if ( $preview && ! $can_manage ) {
			return '';
		}

		$config = $data['config'];
		$public = Assessment::public_config( $id, $config );
		if ( empty( $public['questions'] ) ) {
			return $can_manage ? self::notice( __( 'Next Class Services: this assessment has no enabled questions yet.', 'next-class-services' ) ) : '';
		}

		$public['preview'] = $preview;
		if ( $preview ) {
			$public['previewNonce'] = wp_create_nonce( 'ncs_preview_' . $id );
			$public['restNonce']    = wp_create_nonce( 'wp_rest' );
		}

		Assets::enqueue_public( 'assessment', $preview );
		self::$instances++;

		return Helpers::render(
			'assessment/container',
			array(
				'dom_id'  => 'ncs-assessment-' . $id . '-' . self::$instances,
				'config'  => $public,
				'preview' => $preview,
				'style'   => Front::style_vars(),
				'font'    => Front::font_class(),
			)
		);
	}

	/**
	 * Notice shown to editors only.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function notice( $message ) {
		return '<div class="ncs-root ncs-notice">' . esc_html( $message ) . '</div>';
	}
}
