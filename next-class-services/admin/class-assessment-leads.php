<?php
/**
 * Assessment leads and submissions admin.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Leads;
use NextClassServices\Helpers;
use NextClassServices\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead list, detail view, deletion and CSV export.
 */
class Assessment_Leads {

	const CAP = 'ncs_manage_submissions';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_export_leads', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_ncs_delete_lead', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Current filters from the query string.
	 *
	 * @param array|null $source Source (defaults to GET).
	 * @return array
	 */
	private static function filters( $source = null ) {
		$src = null === $source ? $_GET : $source; // phpcs:ignore WordPress.Security.NonceVerification
		$get = function ( $key ) use ( $src ) {
			return isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ? sanitize_text_field( wp_unslash( $src[ $key ] ) ) : '';
		};
		return array(
			'assessment_id' => absint( $get( 'assessment_id' ) ),
			'result_title'  => $get( 'result_title' ),
			'from'          => $get( 'from' ),
			'to'            => $get( 'to' ),
			'search'        => $get( 's' ),
		);
	}

	/**
	 * Assessments for the filter dropdown.
	 *
	 * @return array
	 */
	private static function assessment_options() {
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::ASSESSMENT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $posts as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}

	/**
	 * Router.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'next-class-services' ), '', array( 'response' => 403 ) );
		}
		if ( 'view' === Admin::get( 'action' ) && absint( Admin::get( 'id' ) ) ) {
			self::render_detail( absint( Admin::get( 'id' ) ) );
			return;
		}
		self::render_list();
	}

	/**
	 * List.
	 */
	private static function render_list() {
		$filters = self::filters();
		$paged   = max( 1, absint( Admin::get( 'paged', '1' ) ) );
		$per     = 20;
		list( $rows, $total ) = Leads::query( $filters, $paged, $per );
		$has_filters = array_filter( $filters );

		$export_url = wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => 'ncs_export_leads' ), array_filter( $filters ) ), admin_url( 'admin-post.php' ) ),
			'ncs_export_leads'
		);
		Admin::header(
			'ncs-leads',
			__( 'Assessment Results and Leads', 'next-class-services' ),
			$total ? '<a class="ncs-btn" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'next-class-services' ) . '</a>' : ''
		);

		$options = self::assessment_options();
		$glance = Leads::glance();
		echo '<div class="ncs-cards">';
		foreach ( array(
			array( __( 'Total submissions', 'next-class-services' ), $glance['total'], __( 'All time', 'next-class-services' ) ),
			array( __( 'Last 7 days', 'next-class-services' ), $glance['week'], __( 'New submissions', 'next-class-services' ) ),
			array( __( 'Last 30 days', 'next-class-services' ), $glance['month'], __( 'New submissions', 'next-class-services' ) ),
			array( __( 'Marketing opt-ins', 'next-class-services' ), $glance['optin'], __( 'Agreed to marketing emails', 'next-class-services' ) ),
		) as $card ) {
			echo '<div class="ncs-card ncs-stat"><span class="ncs-stat-label">' . esc_html( $card[0] ) . '</span><span class="ncs-stat-value">' . esc_html( (string) $card[1] ) . '</span><span class="ncs-stat-note">' . esc_html( $card[2] ) . '</span></div>';
		}
		echo '</div>';

		echo '<form method="get" class="ncs-filters"><input type="hidden" name="page" value="ncs-leads">';
		echo '<div class="ncs-filter"><label for="ncs-f-a">' . esc_html__( 'Assessment', 'next-class-services' ) . '</label><select id="ncs-f-a" name="assessment_id"><option value="">' . esc_html__( 'All assessments', 'next-class-services' ) . '</option>';
		foreach ( $options as $oid => $otitle ) {
			echo '<option value="' . esc_attr( $oid ) . '"' . selected( $filters['assessment_id'], $oid, false ) . '>' . esc_html( $otitle ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-r">' . esc_html__( 'Result category', 'next-class-services' ) . '</label><select id="ncs-f-r" name="result_title"><option value="">' . esc_html__( 'All results', 'next-class-services' ) . '</option>';
		foreach ( Leads::result_titles( $filters['assessment_id'] ) as $title ) {
			echo '<option value="' . esc_attr( $title ) . '"' . selected( $filters['result_title'], $title, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-from">' . esc_html__( 'From', 'next-class-services' ) . '</label><input type="date" id="ncs-f-from" name="from" value="' . esc_attr( $filters['from'] ) . '"></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-to">' . esc_html__( 'To', 'next-class-services' ) . '</label><input type="date" id="ncs-f-to" name="to" value="' . esc_attr( $filters['to'] ) . '"></div>';
		echo '<div class="ncs-filter"><label for="ncs-f-s">' . esc_html__( 'Search', 'next-class-services' ) . '</label><input type="search" id="ncs-f-s" name="s" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Name, email, company', 'next-class-services' ) . '"></div>';
		echo '<div class="ncs-filter ncs-filter-actions"><button class="ncs-btn ncs-btn-primary">' . esc_html__( 'Filter', 'next-class-services' ) . '</button>';
		if ( $has_filters ) {
			echo ' <a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-leads' ) ) . '">' . esc_html__( 'Reset', 'next-class-services' ) . '</a>';
		}
		echo '</div></form>';

		if ( empty( $rows ) ) {
			if ( $has_filters ) {
				Admin::empty_state( __( 'No submissions match these filters.', 'next-class-services' ) );
			} else {
				Admin::empty_state( __( 'No assessment submissions have been received yet.', 'next-class-services' ), Admin::url( 'ncs-assessments' ), __( 'View assessments', 'next-class-services' ) );
			}
			Admin::footer();
			return;
		}

		echo '<div class="ncs-table-wrap"><table class="ncs-table"><thead><tr>';
		$heads = array( __( 'Lead', 'next-class-services' ), __( 'Assessment', 'next-class-services' ), __( 'Result', 'next-class-services' ), __( 'Submitted', 'next-class-services' ), __( 'Action', 'next-class-services' ) );
		foreach ( $heads as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$name = Leads::display_name( $r );
			$view = Admin::url( 'ncs-leads', array( 'action' => 'view', 'id' => $r['id'] ) );
			$shown = '' !== $name ? $name : __( 'Anonymous', 'next-class-services' );
			echo '<tr>';
			echo '<td><span class="ncs-row-title">' . esc_html( $shown ) . '</span><br><span class="ncs-muted">' . esc_html( $r['email'] ) . '</span></td>';
			echo '<td>' . esc_html( $r['assessment_title'] ) . '</td>';
			echo '<td>' . esc_html( $r['result_title'] ) . '<br><span class="ncs-muted">' . esc_html( round( (float) $r['percentage'] ) . '%' ) . '</span></td>';
			echo '<td>' . esc_html( Helpers::format_utc( $r['created_at'] ) ) . '</td>';
			echo '<td><a class="ncs-btn ncs-btn-small" href="' . esc_url( $view ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: lead name */ __( 'View submission from %s', 'next-class-services' ), $shown ) ) . '">' . esc_html__( 'View', 'next-class-services' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		Admin::pagination( $total, $per, $paged, array_merge( array( 'page' => 'ncs-leads' ), array_filter( $filters ) ) );
		Admin::footer();
	}

	/**
	 * Detail view.
	 *
	 * @param int $id Submission id.
	 */
	private static function render_detail( $id ) {
		$r = Leads::get( $id );
		if ( ! $r ) {
			Admin::flash( 'error', __( 'That submission could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-leads' ) );
		}
		$name    = Leads::display_name( $r );
		$answers = Leads::answers( $id );
		$delete  = wp_nonce_url( add_query_arg( array( 'action' => 'ncs_delete_lead', 'id' => $id ), admin_url( 'admin-post.php' ) ), 'ncs_delete_lead_' . $id );

		Admin::header(
			'ncs-leads',
			'' !== $name ? $name : __( 'Assessment submission', 'next-class-services' ),
			'<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-leads' ) ) . '">' . esc_html__( 'All submissions', 'next-class-services' ) . '</a> <a class="ncs-btn ncs-btn-danger" data-ncs-confirm="' . esc_attr__( 'Permanently delete this submission and its answers?', 'next-class-services' ) . '" href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete submission', 'next-class-services' ) . '</a>'
		);

		$programs = json_decode( (string) $r['recommended_programs'], true );
		$consent  = json_decode( (string) $r['consent_snapshot'], true );
		$custom   = json_decode( (string) $r['custom_fields'], true );

		$summary = array(
			__( 'Assessment', 'next-class-services' )          => $r['assessment_title'],
			__( 'Submitted', 'next-class-services' )           => Helpers::format_utc( $r['created_at'] ),
			__( 'Name', 'next-class-services' )                => $name,
			__( 'Email', 'next-class-services' )               => $r['email'],
			__( 'Phone', 'next-class-services' )               => $r['phone'],
			__( 'Company', 'next-class-services' )             => $r['company'],
			__( 'Job title', 'next-class-services' )           => $r['job_title'],
			__( 'Company size', 'next-class-services' )        => $r['company_size'],
			__( 'Raw score', 'next-class-services' )           => $r['raw_score'] . ' / ' . $r['max_score'],
			__( 'Percentage', 'next-class-services' )          => round( (float) $r['percentage'], 1 ) . '%',
			__( 'Result', 'next-class-services' )              => $r['result_title'],
			__( 'Recommended programs', 'next-class-services' ) => is_array( $programs ) ? implode( ', ', $programs ) : '',
			__( 'Privacy consent', 'next-class-services' )     => $r['consent_privacy'] ? __( 'Yes', 'next-class-services' ) : __( 'No', 'next-class-services' ),
			__( 'Marketing consent', 'next-class-services' )   => $r['consent_marketing'] ? __( 'Yes', 'next-class-services' ) : __( 'No', 'next-class-services' ),
			__( 'Consent recorded', 'next-class-services' )    => $r['consent_at'] ? Helpers::format_utc( $r['consent_at'] ) : '',
			__( 'Result email sent', 'next-class-services' )   => $r['email_sent'] ? __( 'Yes', 'next-class-services' ) : __( 'No', 'next-class-services' ),
			__( 'Page', 'next-class-services' )                => $r['page_url'],
		);
		if ( is_array( $custom ) ) {
			foreach ( $custom as $c ) {
				$summary[ (string) $c['label'] ] = (string) $c['value'];
			}
		}
		if ( is_array( $consent ) && ! empty( $consent['privacy'] ) ) {
			$summary[ __( 'Privacy wording shown', 'next-class-services' ) ] = $consent['privacy'];
		}
		if ( is_array( $consent ) && ! empty( $consent['marketing'] ) ) {
			$summary[ __( 'Marketing wording shown', 'next-class-services' ) ] = $consent['marketing'];
		}

		echo '<div class="ncs-grid-2"><div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Lead and result', 'next-class-services' ) . '</h2><dl class="ncs-dl">';
		foreach ( $summary as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd>';
		}
		echo '</dl></div>';

		echo '<div class="ncs-card"><h2 class="ncs-card-title">' . esc_html__( 'Answers', 'next-class-services' ) . '</h2>';
		if ( empty( $answers ) ) {
			echo '<p class="ncs-muted">' . esc_html__( 'No answers were stored for this submission.', 'next-class-services' ) . '</p>';
		} else {
			echo '<table class="ncs-table"><thead><tr><th scope="col">' . esc_html__( 'Question', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Answer', 'next-class-services' ) . '</th><th scope="col">' . esc_html__( 'Score', 'next-class-services' ) . '</th></tr></thead><tbody>';
			foreach ( $answers as $a ) {
				$score = (float) $a['max_score'] > 0 || (float) $a['score'] !== 0.0 ? rtrim( rtrim( number_format( (float) $a['score'], 2, '.', '' ), '0' ), '.' ) . ' / ' . rtrim( rtrim( number_format( (float) $a['max_score'], 2, '.', '' ), '0' ), '.' ) : '';
				echo '<tr><td>' . esc_html( $a['question_text'] ) . '</td><td>' . nl2br( esc_html( $a['answer_label'] ) ) . '</td><td>' . esc_html( $score ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
		Admin::footer();
	}

	/**
	 * CSV export.
	 */
	public static function handle_export() {
		Admin::authorize( self::CAP, 'ncs_export_leads' );
		$filters = self::filters();
		list( $rows ) = Leads::query( $filters, 1, 0 );

		$headers = array( 'ID', 'Submitted (UTC)', 'Assessment', 'First name', 'Last name', 'Full name', 'Email', 'Phone', 'Company', 'Job title', 'Company size', 'Custom fields', 'Raw score', 'Max score', 'Percentage', 'Result', 'Recommended programs', 'Privacy consent', 'Marketing consent', 'Consent recorded (UTC)', 'Result email sent', 'Answers' );
		$out     = array();
		foreach ( $rows as $r ) {
			$programs = json_decode( (string) $r['recommended_programs'], true );
			$custom   = json_decode( (string) $r['custom_fields'], true );
			$custom_t = array();
			if ( is_array( $custom ) ) {
				foreach ( $custom as $c ) {
					$custom_t[] = $c['label'] . ': ' . $c['value'];
				}
			}
			$answer_t = array();
			foreach ( Leads::answers( $r['id'] ) as $a ) {
				$answer_t[] = $a['question_text'] . ': ' . $a['answer_label'];
			}
			$out[] = array(
				$r['id'],
				$r['created_at'],
				$r['assessment_title'],
				$r['first_name'],
				$r['last_name'],
				$r['full_name'],
				$r['email'],
				$r['phone'],
				$r['company'],
				$r['job_title'],
				$r['company_size'],
				implode( ' | ', $custom_t ),
				$r['raw_score'],
				$r['max_score'],
				$r['percentage'],
				$r['result_title'],
				is_array( $programs ) ? implode( ', ', $programs ) : '',
				$r['consent_privacy'] ? 'Yes' : 'No',
				$r['consent_marketing'] ? 'Yes' : 'No',
				(string) $r['consent_at'],
				$r['email_sent'] ? 'Yes' : 'No',
				implode( ' | ', $answer_t ),
			);
		}
		Helpers::send_csv( 'assessment-leads', $headers, $out );
	}

	/**
	 * Delete one submission.
	 */
	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_delete_lead_' . $id );
		Leads::delete( $id );
		Admin::flash( 'success', __( 'Submission deleted.', 'next-class-services' ) );
		Admin::redirect( Admin::url( 'ncs-leads' ) );
	}
}
