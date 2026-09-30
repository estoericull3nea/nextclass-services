<?php
/**
 * Custom capabilities.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin capabilities and grants them to administrators.
 */
class Capabilities {

	const OPTION_CAPS_VERSION = 'ncs_caps_version';
	const CAPS_VERSION        = '1';

	/**
	 * Capability slugs. No translated strings here, so this is safe to call before init.
	 *
	 * @return array
	 */
	public static function slugs() {
		return apply_filters(
			'ncs_capability_slugs',
			array(
				'ncs_manage_assessments',
				'ncs_manage_submissions',
				'ncs_manage_booking_services',
				'ncs_manage_appointments',
				'ncs_view_analytics',
				'ncs_manage_settings',
			)
		);
	}

	/**
	 * Capabilities with human readable descriptions. Call after init only.
	 *
	 * @return array slug => description
	 */
	public static function all() {
		$labels = array(
			'ncs_manage_assessments'      => __( 'Manage assessments', 'next-class-services' ),
			'ncs_manage_submissions'      => __( 'View and export assessment submissions', 'next-class-services' ),
			'ncs_manage_booking_services' => __( 'Manage booking services', 'next-class-services' ),
			'ncs_manage_appointments'     => __( 'Manage appointments', 'next-class-services' ),
			'ncs_view_analytics'          => __( 'View analytics', 'next-class-services' ),
			'ncs_manage_settings'         => __( 'Manage plugin settings', 'next-class-services' ),
		);
		$out    = array();
		foreach ( self::slugs() as $slug ) {
			$out[ $slug ] = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
		}
		return $out;
	}

	/**
	 * Grant every capability to administrators.
	 */
	public static function grant_defaults() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( self::slugs() as $cap ) {
			$role->add_cap( $cap );
		}
		update_option( self::OPTION_CAPS_VERSION, self::CAPS_VERSION, true );
	}

	/**
	 * Re-grant when the capability set version changes.
	 */
	public static function maybe_sync() {
		if ( get_option( self::OPTION_CAPS_VERSION ) !== self::CAPS_VERSION ) {
			self::grant_defaults();
		}
	}

	/**
	 * Remove capabilities from every role.
	 */
	public static function remove_all() {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::slugs() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		delete_option( self::OPTION_CAPS_VERSION );
	}

	/**
	 * Capability that gives access to the top level menu for the current user.
	 *
	 * @return string
	 */
	public static function menu_cap() {
		foreach ( self::slugs() as $cap ) {
			if ( current_user_can( $cap ) ) {
				return $cap;
			}
		}
		return 'ncs_manage_settings';
	}
}
