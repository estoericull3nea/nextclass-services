<?php
/**
 * Booking services admin.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Booking\Booking_Email;
use NextClassServices\Booking\Booking_Service;
use NextClassServices\Front\Booking_Shortcode;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List, editor, preview and actions for booking services.
 */
class Booking_Services {

	const CAP = 'ncs_manage_booking_services';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_save_service', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ncs_service_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_ncs_service_test_email', array( __CLASS__, 'handle_test_email' ) );
	}

	/**
	 * Row action URL.
	 *
	 * @param string $op Operation.
	 * @param int    $id Id.
	 * @return string
	 */
	public static function action_url( $op, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'ncs_service_action',
					'op'     => $op,
					'id'     => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			'ncs_service_action_' . (int) $id
		);
	}

	/**
	 * Router.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		$action = Admin::get( 'action' );
		$id     = absint( Admin::get( 'id' ) );
		if ( 'new' === $action ) {
			self::render_editor( 0 );
		} elseif ( 'edit' === $action && $id ) {
			self::render_editor( $id );
		} elseif ( 'preview' === $action && $id ) {
			self::render_preview( $id );
		} else {
			self::render_list();
		}
	}

	/**
	 * List.
	 */
	private static function render_list() {
		$paged  = max( 1, absint( Admin::get( 'paged', '1' ) ) );
		$search = Admin::get( 's' );
		$query  = Booking_Service::query( $paged, 20, $search );
		$counts = Booking_Service::upcoming_counts();
		$method = Booking_Service::methods();

		Admin::header(
			'ncs-booking',
			__( 'Online Booking', 'next-class-services' ),
			'<a class="ncs-btn ncs-btn-primary" href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create booking service', 'next-class-services' ) . '</a>'
		);

		if ( ! $query->have_posts() ) {
			if ( '' === $search ) {
				Admin::empty_state( __( 'No booking services have been created yet.', 'next-class-services' ), Admin::url( 'ncs-booking', array( 'action' => 'new' ) ), __( 'Create booking service', 'next-class-services' ) );
			} else {
				Admin::empty_state( __( 'No booking services match your search.', 'next-class-services' ) );
			}
			Admin::footer();
			return;
		}

		echo '<div class="ncs-table-wrap ncs-overflow-visible"><table class="ncs-table"><thead><tr>';
		foreach ( array( __( 'Service', 'next-class-services' ), __( 'Method', 'next-class-services' ), __( 'Duration', 'next-class-services' ), __( 'Status', 'next-class-services' ), __( 'Upcoming', 'next-class-services' ), __( 'Shortcode', 'next-class-services' ), __( 'Actions', 'next-class-services' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $query->posts as $post ) {
			$data = Booking_Service::get( $post->ID );
			if ( ! $data ) {
				continue;
			}
			$cfg       = $data['config'];
			$published = 'publish' === $data['status'];
			echo '<tr><td><a class="ncs-row-title" href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $post->ID ) ) ) . '">' . esc_html( $cfg['admin_title'] ) . '</a>';
			if ( '' !== $cfg['title'] && $cfg['title'] !== $cfg['admin_title'] ) {
				echo '<br><span class="ncs-muted">' . esc_html( $cfg['title'] ) . '</span>';
			}
			echo '</td><td>' . esc_html( $method[ $cfg['method'] ] ) . '</td>';
			/* translators: %d: minutes */
			echo '<td>' . esc_html( sprintf( __( '%d min', 'next-class-services' ), $cfg['duration'] ) ) . '</td>';
			echo '<td>' . Admin::badge( $published ? __( 'Published', 'next-class-services' ) : __( 'Draft', 'next-class-services' ), $published ? 'ok' : 'muted' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . ( 'native' === $cfg['method'] ? esc_html( (string) ( isset( $counts[ $post->ID ] ) ? $counts[ $post->ID ] : 0 ) ) : '<span class="ncs-muted">' . esc_html__( 'Tracked by provider', 'next-class-services' ) . '</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>';
			Admin::shortcode_box( Booking_Service::shortcode( $post->ID ) );
			echo '</td><td><details class="ncs-menu">';
			/* translators: %s: service name */
			echo '<summary class="ncs-menu-btn" aria-label="' . esc_attr( sprintf( __( 'Actions for %s', 'next-class-services' ), $cfg['admin_title'] ) ) . '"><span class="ncs-dots" aria-hidden="true"><i></i><i></i><i></i></span></summary>';
			echo '<ul class="ncs-menu-list"><li>';
			echo '<a href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $post->ID ) ) ) . '">' . esc_html__( 'Edit', 'next-class-services' ) . '</a>';
			echo '</li><li>';
			echo '<a href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'preview', 'id' => $post->ID ) ) ) . '">' . esc_html__( 'Preview', 'next-class-services' ) . '</a>';
			echo '</li><li>';
			echo '<a href="' . esc_url( self::action_url( 'duplicate', $post->ID ) ) . '">' . esc_html__( 'Duplicate', 'next-class-services' ) . '</a>';
			echo '</li><li>';
			echo '<a href="' . esc_url( self::action_url( $published ? 'unpublish' : 'publish', $post->ID ) ) . '">' . esc_html( $published ? __( 'Unpublish', 'next-class-services' ) : __( 'Publish', 'next-class-services' ) ) . '</a>';
			echo '</li><li>';
			echo '<a class="ncs-danger" data-ncs-confirm="' . esc_attr__( 'Delete this booking service? Existing appointments are kept.', 'next-class-services' ) . '" href="' . esc_url( self::action_url( 'delete', $post->ID ) ) . '">' . esc_html__( 'Delete', 'next-class-services' ) . '</a>';
			echo '</li></ul></details></td></tr>';
		}
		echo '</tbody></table></div>';
		Admin::pagination( (int) $query->found_posts, 20, $paged, array_filter( array( 'page' => 'ncs-booking', 's' => $search ) ) );
		Admin::footer();
	}

	/**
	 * Editor.
	 *
	 * @param int $id Service id or 0.
	 */
	private static function render_editor( $id ) {
		$data = $id ? Booking_Service::get( $id ) : null;
		if ( $id && ! $data ) {
			Admin::flash( 'error', __( 'That booking service could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-booking' ) );
		}
		if ( ! $data ) {
			$data = array(
				'id'     => 0,
				'status' => 'draft',
				'config' => Booking_Service::defaults(),
			);
		}
		$cfg    = $data['config'];
		$status = $data['status'];
		$errors = $id ? Booking_Service::validate( $cfg ) : array();
		$tab    = sanitize_key( Admin::get( 'tab', 'general' ) );

		$actions = '<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-booking' ) ) . '">' . esc_html__( 'All services', 'next-class-services' ) . '</a>';
		if ( $id ) {
			$actions .= ' <a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'preview', 'id' => $id ) ) ) . '">' . esc_html__( 'Preview', 'next-class-services' ) . '</a>';
		}
		Admin::header( 'ncs-booking', $id ? __( 'Edit booking service', 'next-class-services' ) : __( 'Create booking service', 'next-class-services' ), $actions );
		include NCS_PATH . 'admin/views/service-editor.php';
		Admin::footer();
	}

	/**
	 * Preview.
	 *
	 * @param int $id Id.
	 */
	private static function render_preview( $id ) {
		$data = Booking_Service::get( $id );
		if ( ! $data ) {
			Admin::flash( 'error', __( 'That booking service could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-booking' ) );
		}
		Admin::header(
			'ncs-booking',
			/* translators: %s: service name */
			sprintf( __( 'Preview: %s', 'next-class-services' ), $data['config']['admin_title'] ),
			'<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $id ) ) ) . '">' . esc_html__( 'Back to editor', 'next-class-services' ) . '</a>'
		);
		echo '<div class="ncs-card ncs-preview-frame">';
		echo Booking_Shortcode::render_services( array( $id ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		Admin::footer();
	}

	/**
	 * Save.
	 */
	public static function handle_save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_save_service_' . $id, '_ncs_nonce' );

		$existing = $id ? Booking_Service::get( $id ) : null;
		if ( $id && ! $existing ) {
			Admin::flash( 'error', __( 'That booking service no longer exists.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-booking' ) );
		}

		$post     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Booking_Service::from_request().
		$warnings = array();
		$config   = Booking_Service::from_request( $post, $warnings );
		if ( ! empty( $config['native']['google_sync'] ) && ! \NextClassServices\Booking\Google_Calendar::connected() ) {
			$warnings[] = __( 'Google Calendar sync is enabled, but no Google account is connected. Connect and test Google Calendar in Settings. Bookings will be saved locally and sync will retry.', 'next-class-services' );
		}
		if ( '' === $config['admin_title'] ) {
			$config['admin_title'] = '' !== $config['title'] ? $config['title'] : __( 'Untitled service', 'next-class-services' );
		}

		$button = isset( $post['save_action'] ) ? sanitize_key( $post['save_action'] ) : 'draft';
		$errors = Booking_Service::validate( $config );
		$was    = $existing ? $existing['status'] : 'draft';
		if ( 'publish' === $button || ( 'update' === $button && 'publish' === $was ) ) {
			$status = empty( $errors ) ? 'publish' : 'draft';
		} else {
			$status = 'draft';
		}

		$new_id = Booking_Service::save( $id, $config, $status );
		if ( ! $new_id ) {
			Admin::flash( 'error', __( 'The booking service could not be saved. Please try again.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-booking', $id ? array( 'action' => 'edit', 'id' => $id ) : array( 'action' => 'new' ) ) );
		}
		foreach ( $warnings as $w ) {
			Admin::flash( 'warning', esc_html( $w ) );
		}
		if ( 'publish' === $status ) {
			Admin::flash( 'success', 'publish' === $was ? __( 'Booking service updated.', 'next-class-services' ) : __( 'Booking service published. Copy the shortcode to place it on a page.', 'next-class-services' ) );
		} elseif ( ( 'publish' === $button || 'update' === $button ) && ! empty( $errors ) ) {
			Admin::flash( 'error', __( 'Saved as a draft. Fix these issues before publishing:', 'next-class-services' ) . '<br>' . Admin::error_list( $errors, 'ncs-booking', $new_id ) );
		} else {
			Admin::flash( 'success', __( 'Draft saved.', 'next-class-services' ) );
			if ( ! empty( $errors ) ) {
				Admin::flash( 'info', __( 'Issues to fix before publishing:', 'next-class-services' ) . '<br>' . Admin::error_list( $errors, 'ncs-booking', $new_id ) );
			}
		}
		$tab = isset( $post['tab'] ) ? sanitize_key( $post['tab'] ) : 'general';
		Admin::redirect( Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $new_id, 'tab' => $tab ) ) );
	}

	/**
	 * Row actions.
	 */
	public static function handle_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_service_action_' . $id );
		$op   = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';
		$back = Admin::url( 'ncs-booking' );
		switch ( $op ) {
			case 'duplicate':
				$new = Booking_Service::duplicate( $id );
				if ( $new ) {
					Admin::flash( 'success', __( 'Booking service duplicated. The copy is saved as a draft.', 'next-class-services' ) );
					Admin::redirect( Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $new ) ) );
				}
				Admin::flash( 'error', __( 'The booking service could not be duplicated.', 'next-class-services' ) );
				break;
			case 'delete':
				$deleted = Booking_Service::delete( $id );
				Admin::flash( $deleted ? 'success' : 'error', $deleted ? __( 'Booking service deleted.', 'next-class-services' ) : __( 'The booking service could not be deleted.', 'next-class-services' ) );
				break;
			case 'unpublish':
				Booking_Service::set_status( $id, 'draft' );
				Admin::flash( 'success', __( 'Booking service unpublished. It is now a draft.', 'next-class-services' ) );
				break;
			case 'publish':
				$data = Booking_Service::get( $id );
				if ( $data ) {
					$errors = Booking_Service::validate( $data['config'] );
					if ( empty( $errors ) ) {
						Booking_Service::set_status( $id, 'publish' );
						Admin::flash( 'success', __( 'Booking service published.', 'next-class-services' ) );
					} else {
						Admin::flash( 'error', __( 'It cannot be published yet:', 'next-class-services' ) . '<br>' . Admin::error_list( $errors, 'ncs-booking', $id ) );
						$back = Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $id ) );
					}
				}
				break;
		}
		Admin::redirect( $back );
	}

	/**
	 * Test email.
	 */
	public static function handle_test_email() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_service_test_email_' . $id, '_ncs_test_nonce' );
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'customer';
		if ( ! in_array( $kind, array( 'customer', 'admin', 'cancel', 'reschedule' ), true ) ) {
			$kind = 'customer';
		}
		$to   = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';
		$back = Admin::url( 'ncs-booking', array( 'action' => 'edit', 'id' => $id, 'tab' => 'emails' ) );
		if ( ! $to || ! is_email( $to ) ) {
			Admin::flash( 'error', __( 'Enter a valid email address to send the test to.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		$data = Booking_Service::get( $id );
		if ( ! $data ) {
			Admin::flash( 'error', __( 'Save the service before sending a test email.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		if ( Booking_Email::send_test( $kind, $data['config'], $to ) ) {
			Admin::flash( 'success', sprintf( /* translators: %s: email */ __( 'Test email sent to %s. It uses the last saved settings and sample data.', 'next-class-services' ), esc_html( $to ) ) );
		} else {
			Admin::flash( 'error', __( 'The test email could not be sent. Check your WordPress mail configuration.', 'next-class-services' ) );
		}
		Admin::redirect( $back );
	}
}
