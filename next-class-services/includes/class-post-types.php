<?php
/**
 * Private post types storing definitions.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers non-public post types for assessments and booking services.
 */
class Post_Types {

	const ASSESSMENT = 'ncs_assessment';
	const SERVICE    = 'ncs_service';

	/**
	 * Register.
	 */
	public static function register() {
		$args = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'can_export'          => true,
		);

		register_post_type(
			self::ASSESSMENT,
			array_merge( $args, array( 'label' => __( 'Assessments', 'next-class-services' ) ) )
		);
		register_post_type(
			self::SERVICE,
			array_merge( $args, array( 'label' => __( 'Booking services', 'next-class-services' ) ) )
		);
	}
}
