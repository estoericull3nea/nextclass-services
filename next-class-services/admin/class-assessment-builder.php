<?php
/**
 * Assessment editor and save handler.
 *
 * @package NextClassServices
 */

namespace NextClassServices\Admin;

use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Assessment_Email;
use NextClassServices\Assessments\Conditional_Logic;
use NextClassServices\Assessments\Question;
use NextClassServices\Assessments\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the tabbed assessment editor and processes saves.
 */
class Assessment_Builder {

	const CAP = 'ncs_manage_assessments';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_post_ncs_save_assessment', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Render the editor.
	 *
	 * @param int $id Assessment id, 0 for new.
	 */
	public static function render( $id ) {
		$data = $id ? Assessment::get( $id ) : null;
		if ( $id && ! $data ) {
			Admin::flash( 'error', __( 'That assessment could not be found.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-assessments' ) );
		}
		if ( ! $data ) {
			$defaults = Assessment::defaults();
			$data     = array(
				'id'     => 0,
				'status' => 'draft',
				'config' => $defaults,
			);
		}
		$cfg    = $data['config'];
		$status = $data['status'];
		$errors = $id ? Assessment::validate( $cfg ) : array();
		$tab    = sanitize_key( Admin::get( 'tab', 'general' ) );

		$builder = array(
			'questions'  => $cfg['questions'],
			'results'    => $cfg['results'],
			'programs'   => $cfg['programs'],
			'leadFields' => $cfg['lead']['fields'],
			'types'      => Question::types(),
			'operators'  => Conditional_Logic::operators(),
			'maxScore'   => Scoring::max_possible( $cfg['questions'] ),
			'i18n'       => self::i18n(),
		);

		$actions = '<a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-assessments' ) ) . '">' . esc_html__( 'All assessments', 'next-class-services' ) . '</a>';
		if ( $id ) {
			$actions .= ' <a class="ncs-btn" href="' . esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'preview', 'id' => $id ) ) ) . '">' . esc_html__( 'Preview', 'next-class-services' ) . '</a>';
		}
		Admin::header( 'ncs-assessments', $id ? __( 'Edit assessment', 'next-class-services' ) : __( 'Create assessment', 'next-class-services' ), $actions );

		include NCS_PATH . 'admin/views/assessment-editor.php';

		Admin::footer();
	}

	/**
	 * Strings used by the builder script.
	 *
	 * @return array
	 */
	private static function i18n() {
		return array(
			'addQuestion'      => __( 'Add question', 'next-class-services' ),
			'question'         => __( 'Question', 'next-class-services' ),
			'untitled'         => __( 'Untitled question', 'next-class-services' ),
			'edit'             => __( 'Edit', 'next-class-services' ),
			'close'            => __( 'Close', 'next-class-services' ),
			'duplicate'        => __( 'Duplicate', 'next-class-services' ),
			'delete'           => __( 'Delete', 'next-class-services' ),
			'moveUp'           => __( 'Move up', 'next-class-services' ),
			'moveDown'         => __( 'Move down', 'next-class-services' ),
			'dragHandle'       => __( 'Drag to reorder', 'next-class-services' ),
			'confirmDelete'    => __( 'Delete this item? This cannot be undone.', 'next-class-services' ),
			'type'             => __( 'Question type', 'next-class-services' ),
			'text'             => __( 'Question text', 'next-class-services' ),
			'help'             => __( 'Help text (optional)', 'next-class-services' ),
			'section'          => __( 'Section name (used when questions are grouped)', 'next-class-services' ),
			'required'         => __( 'Required', 'next-class-services' ),
			'optional'         => __( 'Optional', 'next-class-services' ),
			'enabled'          => __( 'Enabled', 'next-class-services' ),
			'disabled'         => __( 'Disabled', 'next-class-services' ),
			'options'          => __( 'Answer options', 'next-class-services' ),
			'optionLabel'      => __( 'Option label', 'next-class-services' ),
			'score'            => __( 'Score', 'next-class-services' ),
			'addOption'        => __( 'Add option', 'next-class-services' ),
			'removeOption'     => __( 'Remove', 'next-class-services' ),
			'weight'           => __( 'Score weight', 'next-class-services' ),
			'scaleMin'         => __( 'Scale minimum', 'next-class-services' ),
			'scaleMax'         => __( 'Scale maximum', 'next-class-services' ),
			'minLabel'         => __( 'Label for lowest value', 'next-class-services' ),
			'maxLabel'         => __( 'Label for highest value', 'next-class-services' ),
			'scored'           => __( 'Use the rating value as the score', 'next-class-services' ),
			'reverse'          => __( 'Reverse scoring (lower rating scores higher)', 'next-class-services' ),
			'numMin'           => __( 'Minimum value', 'next-class-services' ),
			'numMax'           => __( 'Maximum value', 'next-class-services' ),
			'placeholder'      => __( 'Placeholder', 'next-class-services' ),
			'logic'            => __( 'Conditional logic', 'next-class-services' ),
			'logicEnable'      => __( 'Use conditional logic for this question', 'next-class-services' ),
			'logicAction'      => __( 'Action', 'next-class-services' ),
			'logicMatch'       => __( 'Match', 'next-class-services' ),
			'operator'         => __( 'Condition', 'next-class-services' ),
			'logicShow'        => __( 'Show this question if', 'next-class-services' ),
			'logicHide'        => __( 'Skip this question if', 'next-class-services' ),
			'matchAll'         => __( 'all conditions match', 'next-class-services' ),
			'matchAny'         => __( 'any condition matches', 'next-class-services' ),
			'addCondition'     => __( 'Add condition', 'next-class-services' ),
			'chooseQuestion'   => __( 'Choose a question', 'next-class-services' ),
			'value'            => __( 'Value', 'next-class-services' ),
			'logicNone'        => __( 'Conditions can only use questions that come before this one.', 'next-class-services' ),
			'noQuestions'      => __( 'No questions yet. Add your first question to begin.', 'next-class-services' ),
			'yes'              => __( 'Yes', 'next-class-services' ),
			'no'               => __( 'No', 'next-class-services' ),
			'addResult'        => __( 'Add result category', 'next-class-services' ),
			'result'           => __( 'Result category', 'next-class-services' ),
			'resultTitle'      => __( 'Result title', 'next-class-services' ),
			'minScore'         => __( 'Minimum', 'next-class-services' ),
			'maxScoreLabel'    => __( 'Maximum', 'next-class-services' ),
			'resultDesc'       => __( 'Result description', 'next-class-services' ),
			'resultRec'        => __( 'Personalized recommendation', 'next-class-services' ),
			'resultPrograms'   => __( 'Recommended programs', 'next-class-services' ),
			'noPrograms'       => __( 'Add programs in the list above to attach them to a result.', 'next-class-services' ),
			'ctaLabel'         => __( 'Primary button label', 'next-class-services' ),
			'ctaUrl'           => __( 'Primary button URL', 'next-class-services' ),
			'cta2Label'        => __( 'Secondary button label (optional)', 'next-class-services' ),
			'cta2Url'          => __( 'Secondary button URL (optional)', 'next-class-services' ),
			'noResults'        => __( 'No result categories yet.', 'next-class-services' ),
			'addProgram'       => __( 'Add program', 'next-class-services' ),
			'program'          => __( 'Program', 'next-class-services' ),
			'programName'      => __( 'Program name', 'next-class-services' ),
			'programDesc'      => __( 'Description', 'next-class-services' ),
			'programUrl'       => __( 'URL', 'next-class-services' ),
			'programCta'       => __( 'Button label', 'next-class-services' ),
			'noProgramsYet'    => __( 'No programs yet.', 'next-class-services' ),
			'rangeCheck'       => __( 'Range check', 'next-class-services' ),
			'rangeOk'          => __( 'Result ranges cover every score without overlap.', 'next-class-services' ),
			'rangeEmpty'       => __( 'Add result categories to check coverage.', 'next-class-services' ),
			'rangeGap'         => __( 'Scores from %1$s to %2$s are not covered.', 'next-class-services' ),
			'rangeOverlap'     => __( 'Ranges "%1$s" and "%2$s" overlap.', 'next-class-services' ),
			'rangeStart'       => __( 'Scores below %s are not covered.', 'next-class-services' ),
			'rangeEnd'         => __( 'Scores above %s are not covered.', 'next-class-services' ),
			'rangeInvalid'     => __( 'The minimum is greater than the maximum for "%s".', 'next-class-services' ),
			'field'            => __( 'Field', 'next-class-services' ),
			'label'            => __( 'Label', 'next-class-services' ),
			'fieldPlaceholder' => __( 'Placeholder', 'next-class-services' ),
			'fieldOptions'     => __( 'Choices (one per line)', 'next-class-services' ),
			'addCustomField'   => __( 'Add custom field', 'next-class-services' ),
			'customField'      => __( 'Custom field', 'next-class-services' ),
			'fieldType'        => __( 'Field type', 'next-class-services' ),
			'typeText'         => __( 'Text', 'next-class-services' ),
			'typeTextarea'     => __( 'Long text', 'next-class-services' ),
			'typeSelect'       => __( 'Dropdown', 'next-class-services' ),
			'emailLocked'      => __( 'Email is always required so results can be delivered and leads identified.', 'next-class-services' ),
			'untitledResult'   => __( 'Untitled result', 'next-class-services' ),
			'untitledProgram'  => __( 'Untitled program', 'next-class-services' ),
			'scoreSummary'     => __( 'Maximum possible score: %s', 'next-class-services' ),
			'badgeScored'      => __( 'Scored', 'next-class-services' ),
			'badgeNotScored'   => __( 'Not scored', 'next-class-services' ),
			'logicBadge'       => __( 'Has logic', 'next-class-services' ),
		);
	}

	/**
	 * Save handler.
	 */
	public static function handle_save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		Admin::authorize( self::CAP, 'ncs_save_assessment_' . $id, '_ncs_nonce' );

		$existing = $id ? Assessment::get( $id ) : null;
		if ( $id && ! $existing ) {
			Admin::flash( 'error', __( 'That assessment no longer exists.', 'next-class-services' ) );
			Admin::redirect( Admin::url( 'ncs-assessments' ) );
		}

		$post     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in Assessment::from_request().
		$warnings = array();
		$config   = Assessment::from_request( $post, $warnings );
		if ( '' === $config['admin_title'] ) {
			$config['admin_title'] = '' !== $config['title'] ? $config['title'] : __( 'Untitled assessment', 'next-class-services' );
		}

		$button = isset( $post['save_action'] ) ? sanitize_key( $post['save_action'] ) : 'draft';
		$errors = Assessment::validate( $config );
		$was    = $existing ? $existing['status'] : 'draft';

		if ( 'publish' === $button || ( 'update' === $button && 'publish' === $was ) ) {
			$status = empty( $errors ) ? 'publish' : 'draft';
		} else {
			$status = 'draft';
		}

		$new_id = Assessment::save( $id, $config, $status );
		if ( ! $new_id ) {
			Admin::flash( 'error', __( 'The assessment could not be saved. Please try again.', 'next-class-services' ) );
			Admin::redirect( $id ? Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $id ) ) : Admin::url( 'ncs-assessments', array( 'action' => 'new' ) ) );
		}

		foreach ( $warnings as $w ) {
			Admin::flash( 'warning', esc_html( $w ) );
		}
		if ( 'publish' === $status ) {
			Admin::flash( 'success', 'publish' === $was ? __( 'Assessment updated.', 'next-class-services' ) : __( 'Assessment published. Copy the shortcode to place it on a page.', 'next-class-services' ) );
		} elseif ( ( 'publish' === $button || 'update' === $button ) && ! empty( $errors ) ) {
			Admin::flash( 'error', __( 'Saved as a draft. Fix these issues before publishing:', 'next-class-services' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $errors ) ) );
		} elseif ( ! empty( $errors ) ) {
			Admin::flash( 'success', __( 'Draft saved.', 'next-class-services' ) );
			Admin::flash( 'info', __( 'Issues to fix before publishing:', 'next-class-services' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $errors ) ) );
		} else {
			Admin::flash( 'success', __( 'Draft saved. It is ready to publish.', 'next-class-services' ) );
		}

		$tab = isset( $post['tab'] ) ? sanitize_key( $post['tab'] ) : 'general';
		Admin::redirect( Admin::url( 'ncs-assessments', array( 'action' => 'edit', 'id' => $new_id, 'tab' => $tab ) ) );
	}
}
