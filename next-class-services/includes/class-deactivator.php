<?php
/**
 * Deactivation.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on deactivation. Never deletes customer data.
 */
class Deactivator {

	/**
	 * Deactivate.
	 */
	public static function deactivate() {
		Retention::unschedule();
		flush_rewrite_rules( false );
	}
}
