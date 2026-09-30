<?php
/**
 * Assessments list, actions and preview.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Assessment_Email;
use NextClassServices\Front\Assessment_Shortcode;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assessment management screens.
 */
class Assessments {

	const CAP = 'ncs_manage_assessments';

	/**
	 * Init.
	 */
	public static function init() {
		Assessment_Builder::init();
		add_action( 'admin_post_ncs_assessment_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_ncs_assessment_test_email', array( __CLASS__, 'handle_test_email' ) );
	}

	/**
	 * Nonce protected row action URL.
	 *
	 * @param string $op Operation.
	 * @param int    $id Id.
	 * @return string
	 */
	public static function action_url( $op, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'ncs_assessment_action',
					'op'     => $op,
					'id'     => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			'ncs_assessment_action_' . (int) $id
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
			Assessment_Builder::render( 0 );
		} elseif ( 'edit' === $action && $id ) {
			Assessment_Builder::render( $id );
		} elseif ( 'preview' === $action && $id ) {
			self::render_preview( $id );
		} else {
			self::render_list();
		}
	}

	/**
	 * List screen.
	 */
	private static function render_list() {
		$paged  = max( 1, absint( Admin::get( 'paged', '1' ) ) );
		$search = Admin::get( 's' );
		$status = Admin::get( 'status' );
		$query  = Assessment::query( $paged, 20, $search, $status );
		$counts = Assessment::counts();
		$subs   = Assessment::submission_counts();

		Admin::header(
			'ncs-assessments',
			__( 'HR Assessments', 'next-class-services' ),
			'<a class="ncs-btn ncs-btn-primary" href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create assessment', 'next-class-services' ) . '</a>'
		);

		if ( ! $query->have_posts() && '' === $search && '' === $status ) {
			Admin::empty_state( __( 'No assessments have been created yet.', 'next-class-services' ), Admin::url( 'ncs-assessments', array( 'action' => 'new' ) ), __( 'Create assessment', 'next-class-services' ) );
			Admin::footer();
			return;
		}

		echo '<div class="ncs-toolbar"><ul class="ncs-filter-links">';
		$links = array(
			''        => sprintf( /* translators: %d: count */ __( 'All (%d)', 'next-class-services' ), $counts['publish'] + $counts['draft'] ),
			'publish' => sprintf( /* translators: %d: count */ __( 'Published (%d)', 'next-class-services' ), $counts['publish'] ),
			'draft'   => sprintf( /* translators: %d: count */ __( 'Draft (%d)', 'next-class-services' ), $counts['draft'] ),
		);
		foreach ( $links as $key => $label ) {
			printf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url( Admin::url( 'ncs-assessments', '' === $key ? array() : array( 'status' => $key ) ) ),
				$status === $key ? ' class="is-current"' : '',
				esc_html( $label )
			);
		}
		echo '</ul><form method="get" class="ncs-search"><input type="hidden" name="page" value="ncs-assessments">';
		echo '<label class="screen-reader-text" for="ncs-s">' . esc_html__( 'Search assessments', 'next-class-services' ) . '</label>';
		echo '<input type="search" id="ncs-s" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search assessments', 'next-class-services' ) . '"> <button class="ncs-btn">' . esc_html__( 'Search', 'next-class-services' ) . '</button></form></div>';

		if ( ! $query->have_posts() ) {
			Admin::empty_state( __( 'No assessments match your search.', 'next-class-services' ) );
			Admin::footer();
			return;
		}

		echo '<div class="ncs-table-wrap"><table class="ncs-table"><thead><tr>';
		foreach ( array( __( 'Assessment', 'next-class-services' ), __( 'Status', 'next-class-services' ), __( 'Questions', 'next-class-services' ), __( 'Submissions', 'next-class-services' ), __( 'Shortcode', 'next-class-services' ), __( 'Actions', 'next-class-services' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $query->posts as $post ) {
			$data = Assessment::get( $post->ID );
			if ( ! $data ) {
				continue;
			}
			$cfg       = $data['config'];
			$published = 'publish' === $data['status'];
			$enabled   = count( array_filter( $cfg['questions'], function ( $q ) {
				return ! empty( $q['enabled'] );
			} ) );
			echo '<tr>';
			echo '<td><a class="ncs-row-title" href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $post->ID ) ) ) . '">' . esc_html( $cfg['admin_title'] ) . '</a>';
			if ( '' !== $cfg['title'] && $cfg['title'] !== $cfg['admin_title'] ) {
				echo '<br><span class="ncs-muted">' . esc_html( $cfg['title'] ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . Admin::badge( $published ? __( 'Published', 'next-class-services' ) : __( 'Draft', 'next-class-services' ), $published ? 'ok' : 'muted' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . esc_html( (string) $enabled ) . '</td>';
			echo '<td>' . esc_html( (string) ( isset( $subs[ $post->ID ] ) ? $subs[ $post->ID ] : 0 ) ) . '</td>';
			echo '<td>';
			Admin::shortcode_box( Assessment::shortcode( $post->ID ) );
			echo '</td><td class="ncs-actions">';
			echo '<a href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $post->ID ) ) ) . '">' . esc_html__( 'Edit', 'next-class-services' ) . '</a>';
			echo '<a href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'preview', 'id' => $post->ID ) ) ) . '">' . esc_html__( 'Preview', 'next-class-services' ) . '</a>';
			echo '<a href="' . esc_url( self::action_url( 'duplicate', $post->ID ) ) . '">' . esc_html__( 'Duplicate', 'next-class-services' ) . '</a>';
			echo '<a href="' . esc_url( self::action_url( $published ? 'unpublish' : 'publish', $post->ID ) ) . '">' . esc_html( $published ? __( 'Unpublish', 'next-class-services' ) : __( 'Publish', 'next-class-services' ) ) . '</a>';
			echo '<a class="ncs-danger" data-ncs-confirm="' . esc_attr__( 'Delete this assessment? Stored submissions are kept, but the assessment can no longer be edited or shown.', 'next-class-services' ) . '" href="' . esc_url( self::action_url( 'delete', $post->ID ) ) . '">' . esc_html__( 'Delete', 'next-class-services' ) . '</a>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		Admin::pagination( (int) $query->found_posts, 20, $paged, array_filter( array( 'page' => 'ncs-assessments', 's' => $search, 'status' => $status ) ) );
		Admin::footer();
	}

	/**
	 * Preview screen. Renders the real shortcode in forced preview mode.
	 *
	 * @param int $id Id.
	 */
	private static function render_preview( $id ) {
		$data = Assessment::get( $id );
		if ( ! $data ) {
			Admin::flash( 'error', __( 'That assessment could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-assessments' ) );
		}
		Admin::header(
			'ncs-assessments',
			/* translators: %s: assessment name */
			sprintf( __( 'Preview: %s', 'next-class-services' ), $data['config']['admin_title'] ),
			'<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $id ) ) ) . '">' . esc_html__( 'Back to editor', 'next-class-services' ) . '</a>'
		);
		echo '<div class="ncs-card ncs-preview-frame">';
		echo Assessment_Shortcode::render_assessment( $id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		Admin::footer();
	}

	/**
	 * Row actions.
	 */
	public static function handle_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_assessment_action_' . $id, '_wpnonce' );
		$op   = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';
		$back = Admin::url( 'ncs-assessments' );

		switch ( $op ) {
			case 'duplicate':
				$new = Assessment::duplicate( $id );
				if ( $new ) {
					Admin::flash( 'success', __( 'Assessment duplicated. The copy is saved as a draft.', 'next-class-services' ) );
					Admin::redirect( Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $new ) ) );
				}
				Admin::flash( 'error', __( 'The assessment could not be duplicated.', 'next-class-services' ) );
				break;
			case 'delete':
				$deleted = Assessment::delete( $id );
				Admin::flash( $deleted ? 'success' : 'error', $deleted ? __( 'Assessment deleted.', 'next-class-services' ) : __( 'The assessment could not be deleted.', 'next-class-services' ) );
				break;
			case 'unpublish':
				Assessment::set_status( $id, 'draft' );
				Admin::flash( 'success', __( 'Assessment unpublished. It is now a draft.', 'next-class-services' ) );
				break;
			case 'publish':
				$data = Assessment::get( $id );
				if ( $data ) {
					$errors = Assessment::validate( $data['config'] );
					if ( empty( $errors ) ) {
						Assessment::set_status( $id, 'publish' );
						Admin::flash( 'success', __( 'Assessment published.', 'next-class-services' ) );
					} else {
						Admin::flash( 'error', __( 'It cannot be published yet:', 'next-class-services' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $errors ) ) );
						$back = Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $id ) );
					}
				}
				break;
		}
		Admin::redirect( $back );
	}

	/**
	 * Send a test email from saved settings.
	 */
	public static function handle_test_email() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_assessment_test_email_' . $id, '_ncs_test_nonce' );
		$kind = isset( $_POST['kind'] ) && 'admin' === $_POST['kind'] ? 'admin' : 'visitor';
		$to   = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';
		$back = Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $id ) );
		if ( ! $to || ! is_email( $to ) ) {
			Admin::flash( 'error', __( 'Enter a valid email address to send the test to.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		$data = Assessment::get( $id );
		if ( ! $data ) {
			Admin::flash( 'error', __( 'Save the assessment before sending a test email.', 'next-class-services' ) );
			Admin::redirect( $back );
		}
		if ( Assessment_Email::send_test( $kind, $data['config'], $to ) ) {
			Admin::flash( 'success', sprintf( /* translators: %s: email */ __( 'Test email sent to %s. It uses the last saved settings and sample data.', 'next-class-services' ), esc_html( $to ) ) );
		} else {
			Admin::flash( 'error', __( 'The test email could not be sent. Check your WordPress mail configuration.', 'next-class-services' ) );
		}
		Admin::redirect( $back );
	}
}
