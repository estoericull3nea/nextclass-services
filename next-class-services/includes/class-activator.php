<?php
/**
 * Activation.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation.
 */
class Activator {

	/**
	 * Activate.
	 */
	public static function activate() {
		Database::install();
		Capabilities::grant_defaults();

		if ( false === get_option( Helpers::OPTION_SETTINGS, false ) ) {
			add_option( Helpers::OPTION_SETTINGS, Helpers::default_settings(), '', true );
		}
		if ( false === get_option( 'ncs_installed_at', false ) ) {
			add_option( 'ncs_installed_at', time(), '', false );
		}

		Post_Types::register();
		Retention::schedule();
		flush_rewrite_rules( false );
	}
}
