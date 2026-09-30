<?php
/**
 * Autoloader and plugin bootstrap.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the class autoloader and wires up every component.
 */
class Loader {

	/**
	 * Singleton instance.
	 *
	 * @var Loader|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Namespace to directory map.
	 *
	 * @var array
	 */
	private static $map = array(
		'NextClassServices\\Admin\\'       => 'admin/',
		'NextClassServices\\Front\\'       => 'public/',
		'NextClassServices\\Assessments\\' => 'modules/assessments/',
		'NextClassServices\\Booking\\'     => 'modules/booking/',
		'NextClassServices\\'              => 'includes/',
	);

	/**
	 * Get instance.
	 *
	 * @return Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register the autoloader.
	 */
	public static function register_autoloader() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload plugin classes.
	 *
	 * NextClassServices\Assessments\Conditional_Logic maps to
	 * modules/assessments/class-conditional-logic.php.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'NextClassServices\\' ) ) {
			return;
		}
		foreach ( self::$map as $prefix => $dir ) {
			if ( 0 !== strpos( $class, $prefix ) ) {
				continue;
			}
			$relative = substr( $class, strlen( $prefix ) );
			if ( false !== strpos( $relative, '\\' ) ) {
				continue;
			}
			$file = NCS_PATH . $dir . 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}
	}

	/**
	 * Boot all components once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'next-class-services', false, dirname( NCS_BASENAME ) . '/languages' );

		Database::maybe_upgrade();
		Capabilities::maybe_sync();

		add_action( 'init', array( 'NextClassServices\Post_Types', 'register' ) );

		Retention::init();
		Privacy::init();
		Assets::init();

		Front\Front::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		}
	}
}
