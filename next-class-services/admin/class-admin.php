<?php
/**
 * Admin framework: menu, notices, shared UI helpers.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu and provides helper methods to page classes.
 */
class Admin {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'plugin_action_links_' . NCS_BASENAME, array( __CLASS__, 'action_links' ) );

		Assessments::init();
		Assessment_Leads::init();
		Booking_Services::init();
		Appointments::init();
		Analytics::init();
		Settings::init();
	}

	/**
	 * Page definitions.
	 *
	 * @return array slug => [label, capability, callback]
	 */
	public static function pages() {
		return array(
			'ncs-dashboard'    => array( __( 'Dashboard', 'next-class-services' ), 'any', array( 'NextClassServices\Admin\Dashboard', 'render' ) ),
			'ncs-assessments'  => array( __( 'HR Assessments', 'next-class-services' ), 'ncs_manage_assessments', array( 'NextClassServices\Admin\Assessments', 'render' ) ),
			'ncs-leads'        => array( __( 'Assessment Leads', 'next-class-services' ), 'ncs_manage_submissions', array( 'NextClassServices\Admin\Assessment_Leads', 'render' ) ),
			'ncs-booking'      => array( __( 'Online Booking', 'next-class-services' ), 'ncs_manage_booking_services', array( 'NextClassServices\Admin\Booking_Services', 'render' ) ),
			'ncs-appointments' => array( __( 'Appointments', 'next-class-services' ), 'ncs_manage_appointments', array( 'NextClassServices\Admin\Appointments', 'render' ) ),
			'ncs-analytics'    => array( __( 'Analytics', 'next-class-services' ), 'ncs_view_analytics', array( 'NextClassServices\Admin\Analytics', 'render' ) ),
			'ncs-settings'     => array( __( 'Settings', 'next-class-services' ), 'ncs_manage_settings', array( 'NextClassServices\Admin\Settings', 'render' ) ),
		);
	}

	/**
	 * Register menu.
	 */
	public static function menu() {
		$menu_cap = Capabilities::menu_cap();
		add_menu_page(
			__( 'Next Class Services', 'next-class-services' ),
			__( 'Next Class Services', 'next-class-services' ),
			$menu_cap,
			'ncs-dashboard',
			array( 'NextClassServices\Admin\Dashboard', 'render' ),
			'none',
			58
		);
		foreach ( self::pages() as $slug => $def ) {
			$cap = 'any' === $def[1] ? $menu_cap : $def[1];
			add_submenu_page( 'ncs-dashboard', $def[0], $def[0], $cap, $slug, $def[2] );
		}
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( current_user_can( 'ncs_manage_settings' ) ) {
			array_unshift( $links, '<a href="' . esc_url( self::url( 'ncs-settings' ) ) . '">' . esc_html__( 'Settings', 'next-class-services' ) . '</a>' );
		}
		return $links;
	}

	/**
	 * Build an admin page URL.
	 *
	 * @param string $page Slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Read a GET value as sanitized text.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	/**
	 * Permission and nonce gate for admin-post handlers.
	 *
	 * @param string $cap    Capability.
	 * @param string $action Nonce action.
	 * @param string $field  Nonce field name.
	 */
	public static function authorize( $cap, $action, $field = '_wpnonce' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}

	/**
	 * Queue a notice for the next admin page load.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message.
	 */
	public static function flash( $type, $message ) {
		$key   = 'ncs_notice_' . get_current_user_id();
		$queue = get_transient( $key );
		$queue = is_array( $queue ) ? $queue : array();
		$queue[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( $key, array_slice( $queue, -10 ), 300 );
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url URL.
	 */
	public static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render and clear queued notices (scoped to plugin pages).
	 */
	public static function notices() {
		$key   = 'ncs_notice_' . get_current_user_id();
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $queue as $n ) {
			$type = in_array( $n['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $n['type'] : 'info';
			printf(
				'<div class="ncs-notice ncs-notice-%1$s" role="%2$s"><strong class="ncs-notice-label">%3$s</strong> <span>%4$s</span></div>',
				esc_attr( $type ),
				'error' === $type ? 'alert' : 'status',
				esc_html( self::notice_label( $type ) ),
				wp_kses( $n['message'], array( 'br' => array(), 'strong' => array(), 'code' => array() ) )
			);
		}
	}

	/**
	 * Text label so status is not conveyed by color alone.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function notice_label( $type ) {
		$labels = array(
			'success' => __( 'Done.', 'next-class-services' ),
			'error'   => __( 'Error.', 'next-class-services' ),
			'warning' => __( 'Attention.', 'next-class-services' ),
			'info'    => __( 'Note.', 'next-class-services' ),
		);
		return $labels[ $type ];
	}

	/**
	 * Page header with navigation.
	 *
	 * @param string $current Current page slug.
	 * @param string $title   Page title.
	 * @param string $actions Trusted HTML for action buttons.
	 */
	public static function header( $current, $title, $actions = '' ) {
		echo '<div class="wrap ncs-admin"><div class="ncs-shell">';
		echo '<div class="ncs-brand"><span class="ncs-brand-name">' . esc_html__( 'Next Class Services', 'next-class-services' ) . '</span><span class="ncs-brand-version">v' . esc_html( NCS_VERSION ) . '</span></div>';
		echo '<nav class="ncs-nav" aria-label="' . esc_attr__( 'Next Class Services', 'next-class-services' ) . '"><ul>';
		$menu_cap = Capabilities::menu_cap();
		foreach ( self::pages() as $slug => $def ) {
			$cap = 'any' === $def[1] ? $menu_cap : $def[1];
			if ( ! current_user_can( $cap ) ) {
				continue;
			}
			printf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url( self::url( $slug ) ),
				$slug === $current ? ' class="is-current" aria-current="page"' : '',
				esc_html( $def[0] )
			);
		}
		echo '</ul></nav>';
		echo '<div class="ncs-page-head"><h1 class="ncs-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $actions ) {
			echo '<div class="ncs-page-actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		// WordPress moves notices printed by other plugins above .wrap h1 only; keep ours scoped here.
		echo '<div class="ncs-notices">';
		self::notices();
		echo '</div>';
	}

	/**
	 * Page footer.
	 */
	public static function footer() {
		echo '</div></div>';
	}

	/**
	 * Empty state block.
	 *
	 * @param string $message Message.
	 * @param string $url     Action URL.
	 * @param string $label   Action label.
	 */
	public static function empty_state( $message, $url = '', $label = '' ) {
		echo '<div class="ncs-empty"><p>' . esc_html( $message ) . '</p>';
		if ( $url && $label ) {
			echo '<p><a class="ncs-btn ncs-btn-primary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Pagination links.
	 *
	 * @param int   $total    Total rows.
	 * @param int   $per_page Per page.
	 * @param int   $paged    Current page.
	 * @param array $args     Base query args (including page).
	 */
	public static function pagination( $total, $per_page, $paged, array $args ) {
		$pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $pages < 2 ) {
			return;
		}
		echo '<nav class="ncs-pagination" aria-label="' . esc_attr__( 'Pagination', 'next-class-services' ) . '">';
		/* translators: 1: current page, 2: total pages, 3: total items */
		echo '<span class="ncs-pagination-info">' . esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d items)', 'next-class-services' ), $paged, $pages, $total ) ) . '</span>';
		if ( $paged > 1 ) {
			echo '<a class="ncs-btn ncs-btn-small" href="' . esc_url( add_query_arg( 'paged', $paged - 1, admin_url( 'admin.php' ) . '?' . http_build_query( $args ) ) ) . '">' . esc_html__( 'Previous', 'next-class-services' ) . '</a>';
		}
		if ( $paged < $pages ) {
			echo '<a class="ncs-btn ncs-btn-small" href="' . esc_url( add_query_arg( 'paged', $paged + 1, admin_url( 'admin.php' ) . '?' . http_build_query( $args ) ) ) . '">' . esc_html__( 'Next', 'next-class-services' ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Small copy-to-clipboard shortcode display.
	 *
	 * @param string $shortcode Shortcode text.
	 */
	public static function shortcode_box( $shortcode ) {
		echo '<span class="ncs-shortcode"><input type="text" readonly value="' . esc_attr( $shortcode ) . '" aria-label="' . esc_attr__( 'Shortcode', 'next-class-services' ) . '" class="ncs-shortcode-input" onfocus="this.select();"> <button type="button" class="ncs-btn ncs-btn-small" data-ncs-copy="' . esc_attr( $shortcode ) . '">' . esc_html__( 'Copy', 'next-class-services' ) . '</button></span>';
	}

	/**
	 * Status badge with text label.
	 *
	 * @param string $label Label.
	 * @param string $tone  ok|warn|muted|bad.
	 * @return string
	 */
	public static function badge( $label, $tone = 'muted' ) {
		return '<span class="ncs-badge ncs-badge-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}
}
