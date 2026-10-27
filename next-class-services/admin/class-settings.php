<?php
/**
 * Settings page.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Capabilities;
use NextClassServices\Helpers;
use NextClassServices\Retention;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global plugin settings.
 */
class Settings {

	const CAP = 'ncs_manage_settings';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ncs_run_cleanup', array( __CLASS__, 'handle_cleanup' ) );
		foreach ( array( 'ncs_save_settings' => 'handle_save', 'ncs_run_cleanup' => 'handle_cleanup' ) as $action => $handler ) {
			add_action( 'wp_ajax_' . $action, function () use ( $handler ) {
				Admin::begin_ajax( array( __CLASS__, 'ajax_state' ) );
				call_user_func( array( __CLASS__, $handler ) );
			} );
		}
	}

	public static function ajax_state() {
		return array(
			'cleanupEnabled' => Helpers::retention_days() > 0,
			'settings' => Helpers::settings(),
			'google' => current_user_can( 'manage_options' ) ? Google_Calendar_Settings::state() : null,
		);
	}

	/** Associate settings fields with the shared form across independent tab panels. */
	private static function field( $type, ...$args ) {
		$index = 'select' === $type ? 4 : 3;
		$options = isset( $args[ $index ] ) ? $args[ $index ] : array();
		$options['attrs']['form'] = 'ncs-settings-form';
		$args[ $index ] = $options;
		call_user_func_array( array( Form::class, $type ), $args );
	}

	private static function panel_start( $key, $active ) {
		echo '<section class="ncs-settings-panel" role="tabpanel" id="ncs-settings-panel-' . esc_attr( $key ) . '" aria-labelledby="ncs-settings-tab-' . esc_attr( $key ) . '"' . ( $key === $active ? '' : ' hidden' ) . '>';
	}

	/**
	 * Render.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		$s = Helpers::settings();
		Admin::header( 'ncs-settings', __( 'Settings', 'next-class-services' ) );
		echo '<div id="ncs-settings-feedback" aria-live="polite" aria-atomic="true"></div>';

		$tz_options = array(
			'' => sprintf(
				/* translators: %s: timezone name */
				__( 'WordPress site timezone (%s)', 'next-class-services' ),
				wp_timezone()->getName()
			),
		);
		foreach ( timezone_identifiers_list() as $tz ) {
			$tz_options[ $tz ] = $tz;
		}

		echo '<form id="ncs-settings-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ncs-form" data-ncs-settings-ajax data-ncs-warn-unsaved>';
		echo '<input type="hidden" name="action" value="ncs_save_settings">';
		wp_nonce_field( 'ncs_save_settings', '_ncs_nonce' );
		echo '</form>';

		$tabs = array(
			'booking' => __( 'Booking', 'next-class-services' ),
			'notifications' => __( 'Notifications', 'next-class-services' ),
			'appearance' => __( 'Appearance', 'next-class-services' ),
			'analytics' => __( 'Analytics', 'next-class-services' ),
			'security' => __( 'Security', 'next-class-services' ),
			'privacy' => __( 'Privacy & data', 'next-class-services' ),
			'advanced' => __( 'Advanced', 'next-class-services' ),
		);
		$active = Admin::get( 'tab', 'booking' );
		$active = isset( $tabs[ $active ] ) ? $active : 'booking';
		echo '<div class="ncs-tabs ncs-settings-tabs" role="tablist" aria-label="' . esc_attr__( 'Settings categories', 'next-class-services' ) . '">';
		foreach ( $tabs as $key => $label ) {
			echo '<button type="button" role="tab" class="ncs-tab' . ( $key === $active ? ' is-active' : '' ) . '" id="ncs-settings-tab-' . esc_attr( $key ) . '" data-tab="' . esc_attr( $key ) . '" aria-controls="ncs-settings-panel-' . esc_attr( $key ) . '" aria-selected="' . ( $key === $active ? 'true' : 'false' ) . '" tabindex="' . ( $key === $active ? '0' : '-1' ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		self::panel_start( 'notifications', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Notifications and email', 'next-class-services' ) . '</h2>';
		self::field( 'text', 's[default_notification_email]', __( 'Default notification email', 'next-class-services' ), $s['default_notification_email'], array( 'type' => 'email', 'help' => sprintf( /* translators: %s: email */ __( 'Used when an assessment or service has no recipient. Leave empty to use the site admin email (%s).', 'next-class-services' ), esc_html( get_option( 'admin_email' ) ) ) ) );
		echo '<div class="ncs-cols">';
		self::field( 'text', 's[from_name]', __( 'Default sender name', 'next-class-services' ), $s['from_name'], array( 'help' => __( 'Optional. Overrides the WordPress default sender name on plugin emails.', 'next-class-services' ) ) );
		self::field( 'text', 's[from_email]', __( 'Sender email address', 'next-class-services' ), $s['from_email'], array( 'type' => 'email', 'help' => __( 'Optional. Use an address on your own domain so mail is not marked as spam. Many mail plugins override this.', 'next-class-services' ) ) );
		echo '</div></div>';
		echo '</section>';

		self::panel_start( 'booking', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Booking defaults', 'next-class-services' ) . '</h2>';
		self::field( 'select', 's[default_timezone]', __( 'Default booking timezone', 'next-class-services' ), $tz_options, $s['default_timezone'], array( 'help' => __( 'Used by native services that do not choose their own timezone.', 'next-class-services' ) ) );
		self::field( 'checkbox', 's[shared_calendar]', __( 'Share one calendar across all native booking services', 'next-class-services' ), $s['shared_calendar'], array( 'help' => __( 'When on, a booking for one service blocks the same time for every other native service. Turn off if each service has separate staff. Change this before taking bookings.', 'next-class-services' ) ) );
		self::field( 'text', 's[manage_page_url]', __( 'Booking management page URL', 'next-class-services' ), $s['manage_page_url'], array( 'type' => 'url', 'placeholder' => 'https://', 'help' => __( 'Optional fallback for cancel and reschedule links. By default the link returns to the page where the customer booked, which already contains the booking shortcode.', 'next-class-services' ) ) );
		self::field( 'textarea', 's[embed_host_allowlist]', __( 'Allowed embed hosts', 'next-class-services' ), $s['embed_host_allowlist'], array( 'rows' => 2, 'help' => __( 'Optional. Comma or line separated hostnames (for example booking.example.com). When set, custom iframe embeds must use one of these hosts. Empty allows any https host.', 'next-class-services' ) ) );
		echo '</div>';
		Google_Calendar_Settings::render();
		echo '</section>';

		self::panel_start( 'security', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Security and spam protection', 'next-class-services' ) . '</h2>';
		echo '<p class="ncs-help">' . esc_html__( 'Public forms always verify a security token and validate every field on the server. These options tune the extra protections.', 'next-class-services' ) . '</p>';
		self::field( 'checkbox', 's[spam_trap_enabled]', __( 'Enable the hidden spam trap field', 'next-class-services' ), $s['spam_trap_enabled'], array( 'help' => __( 'A hidden field real visitors never fill. Submissions that fill it are rejected as spam. Turn this off if a browser or password manager autofills the field and blocks real people.', 'next-class-services' ) ) );
		self::field( 'number', 's[min_seconds]', __( 'Minimum seconds to complete an assessment', 'next-class-services' ), $s['min_seconds'], array( 'min' => 0, 'max' => 60, 'help' => __( 'Submissions faster than this are treated as bots. Use 0 to turn the check off.', 'next-class-services' ) ) );
		self::field( 'checkbox', 's[rate_limit_enabled]', __( 'Limit how often one visitor can submit', 'next-class-services' ), $s['rate_limit_enabled'], array( 'help' => __( 'Limits are counted per visitor address and reset automatically. Turn this off while testing.', 'next-class-services' ) ) );
		echo '<div class="ncs-cols">';
		self::field( 'number', 's[rate_window_minutes]', __( 'Limit window (minutes)', 'next-class-services' ), $s['rate_window_minutes'], array( 'min' => 1, 'max' => 1440 ) );
		self::field( 'number', 's[rate_assessment]', __( 'Assessment submissions per window', 'next-class-services' ), $s['rate_assessment'], array( 'min' => 1, 'max' => 1000 ) );
		echo '</div><div class="ncs-cols">';
		self::field( 'number', 's[rate_booking_create]', __( 'New bookings per window', 'next-class-services' ), $s['rate_booking_create'], array( 'min' => 1, 'max' => 1000 ) );
		self::field( 'number', 's[rate_booking_manage]', __( 'Cancel, reschedule and lookup requests per window', 'next-class-services' ), $s['rate_booking_manage'], array( 'min' => 1, 'max' => 1000 ) );
		echo '</div>';
		self::field( 'number', 's[rate_lookup_per_minute]', __( 'Calendar availability lookups per minute', 'next-class-services' ), $s['rate_lookup_per_minute'], array( 'min' => 10, 'max' => 1000 ) );
		self::field( 'checkbox', 's[trust_proxy_headers]', __( 'Read the visitor address from proxy headers', 'next-class-services' ), $s['trust_proxy_headers'], array( 'help' => __( 'Turn on only if the site is behind a proxy or CDN such as Cloudflare or a load balancer. Otherwise all visitors can appear to share one address and hit the limits together. Do not enable it on a site that is directly reachable, because the header can be faked.', 'next-class-services' ) ) );
		echo '<p class="ncs-help">' . esc_html__( 'Custom embeds can also be restricted to approved hosts under Booking, Allowed embed hosts.', 'next-class-services' ) . '</p></div>';
		echo '</section>';

		self::panel_start( 'appearance', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Frontend appearance', 'next-class-services' ) . '</h2>';
		echo '<p class="ncs-help">' . esc_html__( 'Public assessments and booking forms inherit typography from your theme by default.', 'next-class-services' ) . '</p>';
		echo '<div class="ncs-cols">';
		self::field( 'number', 's[frontend_radius]', __( 'Corner radius (pixels)', 'next-class-services' ), $s['frontend_radius'], array( 'min' => 0, 'max' => 16 ) );
		echo '</div></div>';
		echo '</section>';

		self::panel_start( 'analytics', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Analytics', 'next-class-services' ) . '</h2>';
		self::field( 'checkbox', 's[analytics_enabled]', __( 'Record first-party analytics', 'next-class-services' ), $s['analytics_enabled'], array( 'help' => __( 'Stores anonymous counts such as views, starts and completions. No cookies, IP addresses or visitor identifiers are stored.', 'next-class-services' ) ) );
		self::field( 'checkbox', 's[ga4_enabled]', __( 'Send events to Google Analytics 4 when it is already installed', 'next-class-services' ), $s['ga4_enabled'], array( 'help' => __( 'This plugin never installs Google Analytics. Events are sent only if gtag is available on the page. Event names: nc_assessment_viewed, nc_assessment_started, nc_assessment_completed, nc_lead_captured, nc_result_viewed, nc_booking_viewed, nc_booking_started, nc_booking_completed, nc_booking_external_click.', 'next-class-services' ) ) );
		echo '</div>';
		echo '</section>';

		self::panel_start( 'privacy', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Data retention', 'next-class-services' ) . '</h2>';
		self::field( 'select',
			's[retention_mode]',
			__( 'Keep personal data', 'next-class-services' ),
			array(
				'indefinite' => __( 'Indefinitely', 'next-class-services' ),
				'90'         => __( '90 days', 'next-class-services' ),
				'180'        => __( '180 days', 'next-class-services' ),
				'365'        => __( '365 days', 'next-class-services' ),
				'custom'     => __( 'Custom number of days', 'next-class-services' ),
			),
			(string) $s['retention_mode'],
			array( 'help' => __( 'A daily task removes older assessment submissions and past appointments. Upcoming and active appointments are never removed. Anonymous analytics counts are kept.', 'next-class-services' ) )
		);
		self::field( 'number', 's[retention_custom_days]', __( 'Custom retention (days)', 'next-class-services' ), $s['retention_custom_days'], array( 'min' => 1, 'max' => 3650 ) );
		echo '</div>';

		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Run retention cleanup now', 'next-class-services' ) . '</h2>';
		echo '<p class="ncs-help">' . esc_html__( 'Applies the saved retention period immediately. This permanently deletes data older than the period.', 'next-class-services' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ncs-settings-ajax data-ncs-confirm-submit="' . esc_attr__( 'Permanently delete personal data older than the retention period?', 'next-class-services' ) . '"><input type="hidden" name="action" value="ncs_run_cleanup">';
		wp_nonce_field( 'ncs_run_cleanup', '_ncs_nonce' );
		echo '<button class="ncs-btn ncs-btn-danger" type="submit" data-ncs-cleanup' . disabled( Helpers::retention_days() < 1, true, false ) . '>' . esc_html__( 'Run cleanup', 'next-class-services' ) . '</button></form></div>';

		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Uninstall data', 'next-class-services' ) . '</h2>';
		self::field( 'checkbox', 's[delete_on_uninstall]', __( 'Delete all Next Class Services data when the plugin is uninstalled', 'next-class-services' ), $s['delete_on_uninstall'], array( 'help' => __( 'Off by default. Deactivating never deletes data. When on, deleting the plugin removes tables, options, scheduled tasks, assessments, booking services and capabilities.', 'next-class-services' ) ) );
		echo '</div>';

		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Data stored by this plugin', 'next-class-services' ) . '</h2><ul class="ncs-list">';
		foreach ( array(
			__( 'Assessment submissions: lead fields you enable (name, email, phone, company, job title, company size, custom fields), answers, score, result, recommended programs, consent status and wording, page URL, date.', 'next-class-services' ),
			__( 'Appointments: name, email, phone, company, job title, notes and custom fields you enable, time, timezone, status, page URL, date. Management links are stored as one-way hashes.', 'next-class-services' ),
			__( 'Analytics events: event type, assessment or service ID, a short context value and date. No IP address, cookie or device data.', 'next-class-services' ),
			__( 'Temporary rate limit counters use a one-way hash of the visitor IP for a few minutes and then expire.', 'next-class-services' ),
			__( 'Personal data is included in WordPress export and erase tools (Tools, Export Personal Data and Erase Personal Data).', 'next-class-services' ),
		) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
		echo '</section>';

		self::panel_start( 'advanced', $active );
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Troubleshooting', 'next-class-services' ) . '</h2>';
		self::field( 'checkbox', 's[debug_log]', __( 'Write technical errors to the PHP error log', 'next-class-services' ), $s['debug_log'], array( 'help' => __( 'Only enable while troubleshooting. Visitors never see technical details.', 'next-class-services' ) ) );
		echo '</div>';
		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Roles and capabilities', 'next-class-services' ) . '</h2>';
		echo '<p class="ncs-help">' . esc_html__( 'Administrators receive every capability on activation. Use a role editor plugin to grant them to other roles.', 'next-class-services' ) . '</p>';
		echo '<table class="ncs-table"><thead><tr><th scope="col">' . esc_html__( 'Capability', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Allows', 'next-class-services' ) . '</th></tr></thead><tbody>';
		foreach ( Capabilities::all() as $cap => $desc ) {
			echo '<tr><td><code>' . esc_html( $cap ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';

		echo '<div class="ncs-savebar ncs-settings-savebar"><span class="ncs-savebar-status" id="ncs-dirty" role="status" hidden>' . esc_html__( 'You have unsaved changes.', 'next-class-services' ) . '</span><button type="submit" form="ncs-settings-form" class="ncs-btn ncs-btn-primary">' . esc_html__( 'Save settings', 'next-class-services' ) . '</button></div>';

		Admin::footer();
	}

	/**
	 * Save.
	 */
	public static function handle_save() {
		Admin::authorize( self::CAP, 'ncs_save_settings', '_ncs_nonce' );
		$in   = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$prev = Helpers::settings();
		$get  = function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? (string) $in[ $key ] : '';
		};

		$email = sanitize_email( $get( 'default_notification_email' ) );
		$from  = sanitize_email( $get( 'from_email' ) );
		$mode  = $get( 'retention_mode' );
		$new   = array(
			'default_notification_email' => $email && is_email( $email ) ? $email : '',
			'from_name'                  => Helpers::single_line( $get( 'from_name' ) ),
			'from_email'                 => $from && is_email( $from ) ? $from : '',
			'default_timezone'           => Helpers::sanitize_timezone( $get( 'default_timezone' ) ),
			'retention_mode'             => in_array( $mode, array( 'indefinite', '90', '180', '365', 'custom' ), true ) ? $mode : 'indefinite',
			'retention_custom_days'      => max( 1, min( 3650, (int) $get( 'retention_custom_days' ) ) ),
			'ga4_enabled'                => ! empty( $in['ga4_enabled'] ) ? 1 : 0,
			'analytics_enabled'          => ! empty( $in['analytics_enabled'] ) ? 1 : 0,
			'frontend_primary_color'     => '#393939',
			'frontend_radius'            => max( 0, min( 16, (int) $get( 'frontend_radius' ) ) ),
			'shared_calendar'            => ! empty( $in['shared_calendar'] ) ? 1 : 0,
			'manage_page_url'            => Helpers::safe_url( $get( 'manage_page_url' ) ),
			'embed_host_allowlist'       => implode( ', ', array_filter( array_map( 'sanitize_text_field', preg_split( '/[\s,]+/', strtolower( $get( 'embed_host_allowlist' ) ) ) ) ) ),
			'delete_on_uninstall'        => ! empty( $in['delete_on_uninstall'] ) ? 1 : 0,
			'debug_log'                  => ! empty( $in['debug_log'] ) ? 1 : 0,
			'spam_trap_enabled'          => ! empty( $in['spam_trap_enabled'] ) ? 1 : 0,
			'min_seconds'                => max( 0, min( 60, (int) $get( 'min_seconds' ) ) ),
			'rate_limit_enabled'         => ! empty( $in['rate_limit_enabled'] ) ? 1 : 0,
			'rate_window_minutes'        => max( 1, min( 1440, (int) $get( 'rate_window_minutes' ) ) ),
			'rate_assessment'            => max( 1, min( 1000, (int) $get( 'rate_assessment' ) ) ),
			'rate_booking_create'        => max( 1, min( 1000, (int) $get( 'rate_booking_create' ) ) ),
			'rate_booking_manage'        => max( 1, min( 1000, (int) $get( 'rate_booking_manage' ) ) ),
			'rate_lookup_per_minute'     => max( 10, min( 1000, (int) $get( 'rate_lookup_per_minute' ) ) ),
			'trust_proxy_headers'        => ! empty( $in['trust_proxy_headers'] ) ? 1 : 0,
		);
		Helpers::save_settings( $new );

		if ( '' !== $get( 'default_notification_email' ) && '' === $new['default_notification_email'] ) {
			Admin::flash( 'warning', __( 'The default notification email was not valid and was not saved.', 'next-class-services' ) );
		}
		if ( '' !== $get( 'from_email' ) && '' === $new['from_email'] ) {
			Admin::flash( 'warning', __( 'The sender email address was not valid and was not saved.', 'next-class-services' ) );
		}
		if ( (int) $prev['shared_calendar'] !== (int) $new['shared_calendar'] ) {
			Admin::flash( 'info', __( 'Calendar sharing changed. Existing bookings keep their original slot keys, so review upcoming appointments for overlaps.', 'next-class-services' ) );
		}
		Retention::schedule();
		Admin::flash( 'success', __( 'Settings saved.', 'next-class-services' ) );
		Admin::redirect( Admin::url( 'ncs-settings' ) );
	}

	/**
	 * Manual cleanup.
	 */
	public static function handle_cleanup() {
		Admin::authorize( self::CAP, 'ncs_run_cleanup', '_ncs_nonce' );
		$result = Retention::run();
		Admin::flash(
			'success',
			sprintf(
				/* translators: 1: submissions removed, 2: appointments removed */
				__( 'Cleanup finished. Removed %1$d submissions and %2$d appointments.', 'next-class-services' ),
				$result['submissions'],
				$result['appointments']
			)
		);
		Admin::redirect( Admin::url( 'ncs-settings' ) );
	}
}
