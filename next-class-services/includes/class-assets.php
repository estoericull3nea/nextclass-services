<?php
/**
 * Asset registration and conditional loading.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers assets. Admin assets load only on plugin pages, public assets only
 * when a plugin shortcode is rendered.
 */
class Assets {

	/**
	 * Whether public handles were registered this request.
	 *
	 * @var bool
	 */
	private static $public_registered = false;

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
	}

	/**
	 * Is this one of our admin screens.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'ncs-' );
	}

	/**
	 * Register public assets on first use. Nothing is registered or localized on pages without a shortcode.
	 */
	public static function register_public() {
		if ( self::$public_registered ) {
			return;
		}
		self::$public_registered = true;

		wp_register_style( 'ncs-public', NCS_URL . 'public/css/public.css', array(), NCS_VERSION . '.' . filemtime( NCS_PATH . 'public/css/public.css' ) );

		wp_register_script( 'ncs-analytics', NCS_URL . 'public/js/analytics.js', array(), NCS_VERSION, true );
		wp_localize_script(
			'ncs-analytics',
			'ncsPublic',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'ncs/v1/' ) ),
				'nonce'     => wp_create_nonce( 'ncs_public' ),
				'ga4'       => (bool) Helpers::setting( 'ga4_enabled' ),
				'analytics' => (bool) Helpers::setting( 'analytics_enabled' ),
				'preview'   => false,
				'i18n'      => self::public_strings(),
			)
		);

		wp_register_script( 'ncs-assessment', NCS_URL . 'public/js/assessment.js', array( 'ncs-analytics' ), NCS_VERSION, true );
		wp_register_script( 'ncs-booking', NCS_URL . 'public/js/booking.js', array( 'ncs-analytics' ), NCS_VERSION . '.' . filemtime( NCS_PATH . 'public/js/booking.js' ), true );
	}

	/**
	 * Strings used by the public scripts.
	 *
	 * @return array
	 */
	private static function public_strings() {
		return array(
			'step'          => __( 'Step %1 of %2', 'next-class-services' ),
			'fix'           => __( 'Please answer the highlighted question.', 'next-class-services' ),
			'fix2'          => __( 'Please fix the highlighted fields.', 'next-class-services' ),
			'required'      => __( 'This question is required.', 'next-class-services' ),
			'requiredMark'  => __( '(required)', 'next-class-services' ),
			'choose'        => __( 'Choose an option', 'next-class-services' ),
			'privacy'       => __( 'Read our privacy policy', 'next-class-services' ),
			'tick'          => __( 'Please tick this box to continue.', 'next-class-services' ),
			'working'       => __( 'Working...', 'next-class-services' ),
			'network'       => __( 'Something went wrong. Please check your connection and try again.', 'next-class-services' ),
			'retry'         => __( 'Try again', 'next-class-services' ),
			'score'         => __( 'Score', 'next-class-services' ),
			'percent'       => __( 'Percentage', 'next-class-services' ),
			'rec'           => __( 'Our recommendation', 'next-class-services' ),
			'programs'      => __( 'Recommended programs', 'next-class-services' ),
			'chooseService' => __( 'Choose a service', 'next-class-services' ),
			'minutes'       => __( '%d minutes', 'next-class-services' ),
			'timesShown'    => __( 'Times are shown in %s.', 'next-class-services' ),
			'chooseDate'    => __( 'Choose a date', 'next-class-services' ),
			'chooseTime'    => __( 'Choose a time', 'next-class-services' ),
			'selectDateFirst' => __( 'Select date first', 'next-class-services' ),
			'yourDetails'   => __( 'Your details', 'next-class-services' ),
			'confirmTitle'  => __( 'Review and confirm', 'next-class-services' ),
			'confirmBooking' => __( 'Confirm booking', 'next-class-services' ),
			'back'          => __( 'Back', 'next-class-services' ),
			'continue'      => __( 'Continue', 'next-class-services' ),
			'prevMonth'     => __( 'Previous month', 'next-class-services' ),
			'nextMonth'     => __( 'Next month', 'next-class-services' ),
			'noSlots'       => __( 'No times are available on this date. Please choose another date.', 'next-class-services' ),
			'noDates'       => __( 'No dates are available this month.', 'next-class-services' ),
			'loading'       => __( 'Loading...', 'next-class-services' ),
			'available'     => __( 'available', 'next-class-services' ),
			'unavailable'   => __( 'unavailable', 'next-class-services' ),
			'openBooking'   => __( 'Open the booking page in a new tab', 'next-class-services' ),
			'embedTitle'    => __( 'Booking calendar', 'next-class-services' ),
			'embedHelp'     => __( 'Having trouble loading the calendar?', 'next-class-services' ),
			'bookingDone'   => __( 'Booking received', 'next-class-services' ),
			'previewDone'   => __( 'This is a preview, so nothing was saved.', 'next-class-services' ),
			'manageLink'    => __( 'Save this private link to cancel or reschedule your booking:', 'next-class-services' ),
			'when'          => __( 'When', 'next-class-services' ),
			'status'        => __( 'Status', 'next-class-services' ),
			'service'       => __( 'Service', 'next-class-services' ),
			'manageTitle'   => __( 'Manage your booking', 'next-class-services' ),
			'joinMeet'      => __( 'Join Google Meet', 'next-class-services' ),
			'appointmentDetails' => __( 'Appointment details', 'next-class-services' ),
			'yourAppointment' => __( 'Your appointment', 'next-class-services' ),
			'calendarPendingTitle' => __( 'Calendar invitation is being prepared', 'next-class-services' ),
			'calendarPending' => __( 'Your booking is saved. Your calendar invitation and Meet link are still being prepared. Check your private booking page shortly.', 'next-class-services' ),
			'calendarReadyTitle' => __( 'Your calendar invitation', 'next-class-services' ),
			'calendarReady' => __( 'Check your inbox for the Google Calendar invitation. You may need to accept it to add the event to your calendar.', 'next-class-services' ),
			'reviewHelp' => __( 'Check your appointment and contact details before you confirm.', 'next-class-services' ),
			'bookingProgress' => __( 'Booking progress', 'next-class-services' ),
			'editTime' => __( 'Edit date and time', 'next-class-services' ),
			'editDetails' => __( 'Edit details', 'next-class-services' ),
			'invitationReview' => __( 'Your name and email will be used to send a Google Calendar invitation with a Meet link.', 'next-class-services' ),
			'bookingSaved' => __( 'Booking saved', 'next-class-services' ),
			'nextSteps' => __( 'What happens next', 'next-class-services' ),
			'pendingBooking' => __( 'Your booking is awaiting approval. Keep your private link to check its status.', 'next-class-services' ),
			'keepBookingLink' => __( 'Keep your private booking link so you can view your appointment and available changes.', 'next-class-services' ),
			'copyLink' => __( 'Copy link', 'next-class-services' ),
			'copyManually' => __( 'Select and copy this link to keep it.', 'next-class-services' ),
			'linkCopied' => __( 'Link copied.', 'next-class-services' ),
			'privateLink' => __( 'Your private booking link', 'next-class-services' ),
			'privateLinkHelp' => __( 'Save this link and keep it private. Anyone with the link can manage this booking.', 'next-class-services' ),
			'manageHelp' => __( 'Your appointment details and available changes, all in one place.', 'next-class-services' ),
			'name' => __( 'Name', 'next-class-services' ),
			'email' => __( 'Email', 'next-class-services' ),
			'refreshBooking' => __( 'Refresh booking details', 'next-class-services' ),
			'needDifferentTime' => __( 'Need a different time?', 'next-class-services' ),
			'rescheduleHelp' => __( 'Choose another available slot. Your booking stays in place until the new time is confirmed.', 'next-class-services' ),
			'cantMakeIt' => __( "Can't make it?", 'next-class-services' ),
			'cancelHelp' => __( 'Cancel your appointment and release this time. You will review the cancellation first.', 'next-class-services' ),
			'cancelBooking' => __( 'Cancel booking', 'next-class-services' ),
			'rescheduleBooking' => __( 'Reschedule', 'next-class-services' ),
			'confirmCancel' => __( 'Are you sure you want to cancel this booking?', 'next-class-services' ),
			'yesCancel'     => __( 'Yes, cancel booking', 'next-class-services' ),
			'keepBooking'   => __( 'Keep booking', 'next-class-services' ),
			'newTime'       => __( 'Choose a new time', 'next-class-services' ),
			'confirmMove'   => __( 'Confirm new time', 'next-class-services' ),
			'bookingNotFound' => __( 'We could not find that booking.', 'next-class-services' ),
			'selected'      => __( 'selected', 'next-class-services' ),
			'weekdays'      => array_values( array( __( 'Mon', 'next-class-services' ), __( 'Tue', 'next-class-services' ), __( 'Wed', 'next-class-services' ), __( 'Thu', 'next-class-services' ), __( 'Fri', 'next-class-services' ), __( 'Sat', 'next-class-services' ), __( 'Sun', 'next-class-services' ) ) ),
			'startOfWeek'   => (int) get_option( 'start_of_week', 1 ),
		);
	}

	/**
	 * Enqueue for a feature. Called by shortcodes.
	 *
	 * @param string $feature assessment|booking.
	 * @param bool   $preview Whether this is an authorized admin preview.
	 */
	public static function enqueue_public( $feature, $preview = false ) {
		self::register_public();
		wp_enqueue_style( 'ncs-public' );
		wp_enqueue_script( 'ncs-analytics' );
		if ( 'assessment' === $feature ) {
			wp_enqueue_script( 'ncs-assessment' );
		} elseif ( 'booking' === $feature ) {
			wp_enqueue_script( 'ncs-booking' );
		}
		unset( $preview );
	}

	/**
	 * Admin assets for plugin pages only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function admin( $hook ) {
		unset( $hook );
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		wp_enqueue_style( 'ncs-admin', NCS_URL . 'admin/css/admin.css', array(), NCS_VERSION );
		wp_enqueue_script( 'ncs-admin', NCS_URL . 'admin/js/admin.js', array(), NCS_VERSION . '.' . filemtime( NCS_PATH . 'admin/js/admin.js' ), true );
		wp_localize_script(
			'ncs-admin',
			'ncsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n' => array(
					'confirmDelete' => __( 'This cannot be undone. Continue?', 'next-class-services' ),
					'working' => __( 'Working...', 'next-class-services' ),
					'ajaxError' => __( 'The request could not be completed. Check your connection and try again.', 'next-class-services' ),
					'popupBlocked' => __( 'Allow popups for this site, then click Connect again.', 'next-class-services' ),
					'connected' => __( 'Connected', 'next-class-services' ),
					'notConnected' => __( 'Not connected', 'next-class-services' ),
					'secretSaved' => __( 'Saved. Leave blank to keep.', 'next-class-services' ),
					'secretEmpty' => __( 'Not saved.', 'next-class-services' ),
					'oauthWaiting' => __( 'Complete authorization in the Google window.', 'next-class-services' ),
					'oauthClosed' => __( 'The Google window was closed. Connection status has been refreshed.', 'next-class-services' ),
					'copied'        => __( 'Copied', 'next-class-services' ),
					'copy'          => __( 'Copy', 'next-class-services' ),
				),
			)
		);
		if ( 'ncs-settings' === $page ) {
			wp_enqueue_script( 'ncs-settings-admin', NCS_URL . 'admin/js/settings.js', array( 'ncs-admin' ), NCS_VERSION, true );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'ncs-assessments' === $page && in_array( $action, array( 'edit', 'new' ), true ) ) {
			wp_enqueue_script( 'ncs-assessment-builder', NCS_URL . 'admin/js/assessment-builder.js', array( 'ncs-admin' ), NCS_VERSION, true );
		}
		if ( 'ncs-booking' === $page && in_array( $action, array( 'edit', 'new' ), true ) ) {
			wp_enqueue_script( 'ncs-booking-admin', NCS_URL . 'admin/js/booking-admin.js', array( 'ncs-admin' ), NCS_VERSION, true );
		}
		if ( 'ncs-analytics' === $page ) {
			wp_enqueue_script( 'ncs-analytics-admin', NCS_URL . 'admin/js/analytics.js', array( 'ncs-admin' ), NCS_VERSION, true );
		}
		if ( 'preview' === $action ) {
			self::enqueue_public( 'ncs-assessments' === $page ? 'assessment' : 'booking', true );
		}
	}
}
