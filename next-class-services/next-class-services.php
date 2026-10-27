<?php
/**
 * Plugin Name:       Next Class Services
 * Plugin URI:        https://nextclass.com/
 * Description:       HR Diagnostic Assessment Tool and Online Booking System for Next Class. Build multi-step assessments with scoring, lead capture and personalized results, and offer bookings through Calendly, TidyCal, a custom embed, or native WordPress scheduling.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DEV
 * Text Domain:       next-class-services
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NextClassServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NCS_VERSION', '1.4.0' );
define( 'NCS_DB_VERSION', '1.2.0' );
define( 'NCS_MIN_PHP', '7.4' );
define( 'NCS_MIN_WP', '6.0' );
define( 'NCS_TESTED_WP', '6.8' );
define( 'NCS_FILE', __FILE__ );
define( 'NCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NCS_URL', plugin_dir_url( __FILE__ ) );
define( 'NCS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Show a notice and stop when the environment is too old.
 */
function ncs_environment_ok() {
	global $wp_version;
	return version_compare( PHP_VERSION, NCS_MIN_PHP, '>=' ) && version_compare( $wp_version, NCS_MIN_WP, '>=' );
}

if ( ! ncs_environment_ok() ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: 1: PHP version, 2: WordPress version */
					__( 'Next Class Services requires PHP %1$s or newer and WordPress %2$s or newer. The plugin has not been loaded.', 'next-class-services' ),
					NCS_MIN_PHP,
					NCS_MIN_WP
				)
			) . '</p></div>';
		}
	);
	return;
}

require_once NCS_PATH . 'includes/class-loader.php';

\NextClassServices\Loader::register_autoloader();

register_activation_hook( __FILE__, array( '\NextClassServices\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\NextClassServices\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		\NextClassServices\Loader::instance()->boot();
	}
);
