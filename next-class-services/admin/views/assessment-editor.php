<?php
/**
 * Assessment editor view.
 *
 * @var int    $id
 * @var array  $cfg
 * @var string $status
 * @var array  $errors
 * @var string $tab
 * @var array  $builder
 *
 * @package NextClassServices
 */

use NextClassServices\Admin\Admin;
use NextClassServices\Admin\Form;
use NextClassServices\Assessments\Assessment;
use NextClassServices\Assessments\Assessment_Email;
use NextClassServices\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general'   => __( 'General', 'next-class-services' ),
	'questions' => __( 'Questions', 'next-class-services' ),
	'results'   => __( 'Scoring and results', 'next-class-services' ),
	'lead'      => __( 'Lead capture', 'next-class-services' ),
	'emails'    => __( 'Emails', 'next-class-services' ),
	'publish'   => __( 'Publish', 'next-class-services' ),
);
if ( ! isset( $tabs[ $tab ] ) ) {
	$tab = 'general';
}
$published = 'publish' === $status;
$lead      = $cfg['lead'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ncs-assessment-form" class="ncs-form" data-ncs-warn-unsaved>
	<input type="hidden" name="action" value="ncs_save_assessment">
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
	<input type="hidden" name="tab" id="ncs-current-tab" value="<?php echo esc_attr( $tab ); ?>">
	<?php wp_nonce_field( 'ncs_save_assessment_' . $id, '_ncs_nonce' ); ?>
	<input type="hidden" name="questions_json" id="ncs-questions-json" value="">
	<input type="hidden" name="results_json" id="ncs-results-json" value="">
	<input type="hidden" name="programs_json" id="ncs-programs-json" value="">
	<input type="hidden" name="lead_fields_json" id="ncs-leadfields-json" value="">

	<div class="ncs-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Assessment sections', 'next-class-services' ); ?>">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<button type="button" role="tab" class="ncs-tab<?php echo $key === $tab ? ' is-active' : ''; ?>" id="ncs-tab-<?php echo esc_attr( $key ); ?>" data-tab="<?php echo esc_attr( $key ); ?>" aria-controls="ncs-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo $key === $tab ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>

	<!-- General -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-general" aria-labelledby="ncs-tab-general"<?php echo 'general' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Titles and text', 'next-class-services' ); ?></h2>
			<div class="ncs-cols">
				<?php
				Form::text( 'cfg[admin_title]', __( 'Internal admin title', 'next-class-services' ), $cfg['admin_title'], array( 'help' => __( 'Only shown in the admin area.', 'next-class-services' ) ) );
				Form::text( 'cfg[title]', __( 'Assessment title', 'next-class-services' ), $cfg['title'], array( 'help' => __( 'Shown to visitors.', 'next-class-services' ), 'required' => true ) );
				?>
			</div>
			<?php
			Form::textarea( 'cfg[description]', __( 'Public description', 'next-class-services' ), $cfg['description'], array( 'rows' => 3, 'help' => __( 'A short summary shown under the title. Basic formatting is allowed.', 'next-class-services' ) ) );
			Form::textarea( 'cfg[intro]', __( 'Introduction and instructions', 'next-class-services' ), $cfg['intro'], array( 'rows' => 4, 'help' => __( 'Shown on the start screen, for example how long it takes.', 'next-class-services' ) ) );
			Form::checkbox( 'cfg[show_intro]', __( 'Show a start screen before the first question', 'next-class-services' ), $cfg['show_intro'] );
			?>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Buttons', 'next-class-services' ); ?></h2>
			<div class="ncs-cols">
				<?php
				Form::text( 'cfg[start_text]', __( 'Start button text', 'next-class-services' ), $cfg['start_text'] );
				Form::text( 'cfg[prev_text]', __( 'Previous button text', 'next-class-services' ), $cfg['prev_text'] );
				Form::text( 'cfg[next_text]', __( 'Next button text', 'next-class-services' ), $cfg['next_text'] );
				Form::text( 'cfg[submit_text]', __( 'Submit button text (last question)', 'next-class-services' ), $cfg['submit_text'], array( 'help' => __( 'Used on the final question when lead capture is off.', 'next-class-services' ) ) );
				Form::text( 'cfg[lead_submit_text]', __( 'Lead form submit button text', 'next-class-services' ), $cfg['lead_submit_text'] );
				?>
			</div>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Steps and progress', 'next-class-services' ); ?></h2>
			<?php
			Form::select(
				'cfg[layout]',
				__( 'Question layout', 'next-class-services' ),
				array(
					'single'  => __( 'One question per step', 'next-class-services' ),
					'grouped' => __( 'Group questions by section name', 'next-class-services' ),
				),
				$cfg['layout'],
				array( 'help' => __( 'Grouped layout shows consecutive questions with the same section name on one step.', 'next-class-services' ) )
			);
			Form::checkbox( 'cfg[show_progress]', __( 'Show progress indicator', 'next-class-services' ), $cfg['show_progress'] );
			Form::checkbox( 'cfg[show_percentage]', __( 'Show progress percentage', 'next-class-services' ), $cfg['show_percentage'] );
			Form::textarea( 'cfg[completion_message]', __( 'Completion message', 'next-class-services' ), $cfg['completion_message'], array( 'rows' => 2, 'help' => __( 'Shown on the result screen, and used when no result categories are defined.', 'next-class-services' ) ) );
			?>
		</div>
	</section>

	<!-- Questions -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-questions" aria-labelledby="ncs-tab-questions"<?php echo 'questions' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<div class="ncs-card-head">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Questions', 'next-class-services' ); ?></h2>
				<span class="ncs-muted" id="ncs-score-summary"></span>
			</div>
			<p class="ncs-help"><?php esc_html_e( 'Assign scores to answers to include a question in the score. Text and number answers are stored but not scored. Hidden or skipped questions never affect the score.', 'next-class-services' ); ?></p>
			<div id="ncs-questions-root"></div>
		</div>
	</section>

	<!-- Scoring and results -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-results" aria-labelledby="ncs-tab-results"<?php echo 'results' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Recommended programs', 'next-class-services' ); ?></h2>
			<p class="ncs-help"><?php esc_html_e( 'Create programs once, then attach one or more to each result category.', 'next-class-services' ); ?></p>
			<div id="ncs-programs-root"></div>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Result categories', 'next-class-services' ); ?></h2>
			<?php
			Form::select(
				'cfg[range_basis]',
				__( 'Result ranges are based on', 'next-class-services' ),
				array(
					'percentage' => __( 'Percentage score (0 to 100)', 'next-class-services' ),
					'raw'        => __( 'Raw score', 'next-class-services' ),
				),
				$cfg['range_basis'],
				array( 'help' => __( 'Percentage ranges stay valid when conditional logic skips questions. Ranges must cover every score without overlap, for example 0 to 25 then 26 to 50.', 'next-class-services' ) )
			);
			?>
			<div id="ncs-range-check" class="ncs-range-check" aria-live="polite"></div>
			<div id="ncs-results-root"></div>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Result display', 'next-class-services' ); ?></h2>
			<?php
			Form::text( 'cfg[result_heading]', __( 'Result heading', 'next-class-services' ), $cfg['result_heading'] );
			Form::checkbox( 'cfg[show_score]', __( 'Show the numerical score to visitors', 'next-class-services' ), $cfg['show_score'], array( 'help' => __( 'Off by default. When off, visitors only see the result category.', 'next-class-services' ) ) );
			Form::checkbox( 'cfg[show_percentage_score]', __( 'Show the percentage to visitors', 'next-class-services' ), $cfg['show_percentage_score'] );
			Form::checkbox( 'cfg[show_recommendation]', __( 'Show the personalized recommendation', 'next-class-services' ), $cfg['show_recommendation'] );
			Form::checkbox( 'cfg[show_programs]', __( 'Show recommended programs', 'next-class-services' ), $cfg['show_programs'] );
			?>
		</div>
	</section>

	<!-- Lead capture -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-lead" aria-labelledby="ncs-tab-lead"<?php echo 'lead' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Lead form', 'next-class-services' ); ?></h2>
			<?php
			Form::checkbox( 'cfg[lead][enabled]', __( 'Require the lead form before showing results', 'next-class-services' ), $lead['enabled'], array( 'help' => __( 'The visitor flow is questions, then lead capture, then results. Turn this off to show results straight after the last question.', 'next-class-services' ) ) );
			Form::text( 'cfg[lead][heading]', __( 'Form heading', 'next-class-services' ), $lead['heading'] );
			Form::textarea( 'cfg[lead][intro]', __( 'Form introduction', 'next-class-services' ), $lead['intro'], array( 'rows' => 2 ) );
			?>
		</div>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Lead fields', 'next-class-services' ); ?></h2>
			<p class="ncs-help"><?php esc_html_e( 'Choose which fields appear, their order, labels and whether they are required. Only collect what you need.', 'next-class-services' ); ?></p>
			<div id="ncs-leadfields-root"></div>
		</div>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Consent', 'next-class-services' ); ?></h2>
			<p class="ncs-help"><?php esc_html_e( 'Consent checkboxes are never pre-checked. Consent status and the wording shown are stored with each submission.', 'next-class-services' ); ?></p>
			<?php
			Form::text( 'cfg[lead][privacy_url]', __( 'Privacy policy URL', 'next-class-services' ), $lead['privacy_url'], array( 'type' => 'url', 'placeholder' => 'https://' ) );
			Form::checkbox( 'cfg[lead][privacy][enabled]', __( 'Show a privacy consent checkbox', 'next-class-services' ), $lead['privacy']['enabled'] );
			Form::checkbox( 'cfg[lead][privacy][required]', __( 'Privacy consent is required', 'next-class-services' ), $lead['privacy']['required'] );
			Form::textarea( 'cfg[lead][privacy][text]', __( 'Privacy consent text', 'next-class-services' ), $lead['privacy']['text'], array( 'rows' => 2, 'help' => __( 'Links are allowed, for example a link to your privacy policy.', 'next-class-services' ) ) );
			Form::checkbox( 'cfg[lead][marketing][enabled]', __( 'Show a marketing consent checkbox', 'next-class-services' ), $lead['marketing']['enabled'] );
			Form::checkbox( 'cfg[lead][marketing][required]', __( 'Marketing consent is required', 'next-class-services' ), $lead['marketing']['required'] );
			Form::textarea( 'cfg[lead][marketing][text]', __( 'Marketing consent text', 'next-class-services' ), $lead['marketing']['text'], array( 'rows' => 2 ) );
			?>
		</div>
	</section>

	<!-- Emails -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-emails" aria-labelledby="ncs-tab-emails"<?php echo 'emails' === $tab ? '' : ' hidden'; ?>>
		<?php $em = $cfg['email']; ?>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Visitor result email', 'next-class-services' ); ?></h2>
			<?php
			Form::checkbox( 'cfg[email][enabled]', __( 'Email each visitor their result', 'next-class-services' ), $em['enabled'], array( 'help' => __( 'Sent to the email address entered in the lead form.', 'next-class-services' ) ) );
			?>
			<div class="ncs-cols">
				<?php
				Form::text( 'cfg[email][from_name]', __( 'Sender name', 'next-class-services' ), $em['from_name'], array( 'help' => __( 'The sending address is set by WordPress or in Settings. Leave empty to use the default.', 'next-class-services' ) ) );
				Form::text( 'cfg[email][reply_to]', __( 'Reply-to address', 'next-class-services' ), $em['reply_to'], array( 'type' => 'email' ) );
				?>
			</div>
			<?php
			Form::text( 'cfg[email][subject]', __( 'Subject', 'next-class-services' ), $em['subject'] );
			Form::text( 'cfg[email][heading]', __( 'Heading inside the email', 'next-class-services' ), $em['heading'] );
			Form::textarea( 'cfg[email][intro]', __( 'Introduction', 'next-class-services' ), $em['intro'], array( 'rows' => 3 ) );
			Form::textarea( 'cfg[email][body]', __( 'Result content', 'next-class-services' ), $em['body'], array( 'rows' => 7 ) );
			?>
			<div class="ncs-cols">
				<?php
				Form::text( 'cfg[email][cta_label]', __( 'Button label', 'next-class-services' ), $em['cta_label'], array( 'help' => __( 'If empty, the button from the visitor\'s result category is used.', 'next-class-services' ) ) );
				Form::text( 'cfg[email][cta_url]', __( 'Button URL', 'next-class-services' ), $em['cta_url'], array( 'type' => 'url', 'placeholder' => 'https://' ) );
				?>
			</div>
			<?php Form::textarea( 'cfg[email][footer]', __( 'Footer', 'next-class-services' ), $em['footer'], array( 'rows' => 2 ) ); ?>
			<p class="ncs-help"><?php esc_html_e( 'Available placeholders:', 'next-class-services' ); ?> <code><?php echo esc_html( '{' . implode( '} {', Assessment_Email::placeholders() ) . '}' ); ?></code></p>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Admin notification', 'next-class-services' ); ?></h2>
			<?php
			$an = $cfg['admin_notify'];
			Form::checkbox( 'cfg[admin_notify][enabled]', __( 'Notify me when an assessment is completed', 'next-class-services' ), $an['enabled'] );
			Form::text( 'cfg[admin_notify][recipients]', __( 'Recipient email addresses', 'next-class-services' ), $an['recipients'], array( 'help' => __( 'Separate several addresses with commas. If empty, the default notification email from Settings is used.', 'next-class-services' ) ) );
			Form::text( 'cfg[admin_notify][subject]', __( 'Subject', 'next-class-services' ), $an['subject'] );
			Form::textarea( 'cfg[admin_notify][message]', __( 'Message', 'next-class-services' ), $an['message'], array( 'rows' => 8 ) );
			?>
		</div>

		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Send a test email', 'next-class-services' ); ?></h2>
			<?php if ( $id ) : ?>
				<p class="ncs-help"><?php esc_html_e( 'Tests use the last saved settings and sample data. Save your changes first.', 'next-class-services' ); ?></p>
				<div class="ncs-field">
					<label for="ncs-test-to"><?php esc_html_e( 'Send test to', 'next-class-services' ); ?></label>
					<input type="email" id="ncs-test-to" name="test_to" form="ncs-test-form" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
				</div>
				<button type="submit" class="ncs-btn" name="kind" value="visitor" form="ncs-test-form"><?php esc_html_e( 'Send visitor email test', 'next-class-services' ); ?></button>
				<button type="submit" class="ncs-btn" name="kind" value="admin" form="ncs-test-form"><?php esc_html_e( 'Send admin notification test', 'next-class-services' ); ?></button>
			<?php else : ?>
				<p class="ncs-muted"><?php esc_html_e( 'Save the assessment first to enable test emails.', 'next-class-services' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Publish -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-publish" aria-labelledby="ncs-tab-publish"<?php echo 'publish' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Status and shortcode', 'next-class-services' ); ?></h2>
			<p><?php esc_html_e( 'Status:', 'next-class-services' ); ?> <?php echo Admin::badge( $published ? __( 'Published', 'next-class-services' ) : __( 'Draft', 'next-class-services' ), $published ? 'ok' : 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php if ( $id ) : ?>
				<p><?php esc_html_e( 'Paste this shortcode into any page or post, or a page builder shortcode block:', 'next-class-services' ); ?></p>
				<p><?php Admin::shortcode_box( Assessment::shortcode( $id ) ); ?></p>
				<p><a class="ncs-btn" href="<?php echo esc_url( Admin::url( 'ncs-assessments', array( 'action' => 'preview', 'id' => $id ) ) ); ?>"><?php esc_html_e( 'Preview assessment', 'next-class-services' ); ?></a></p>
			<?php else : ?>
				<p class="ncs-muted"><?php esc_html_e( 'The shortcode is created when you save.', 'next-class-services' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Publishing checklist', 'next-class-services' ); ?></h2>
			<?php if ( empty( $errors ) && $id ) : ?>
				<p><?php echo Admin::badge( __( 'Ready', 'next-class-services' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'This assessment passes all publishing checks.', 'next-class-services' ); ?></p>
			<?php elseif ( ! $id ) : ?>
				<p class="ncs-muted"><?php esc_html_e( 'Save a draft to see which checks still need attention.', 'next-class-services' ); ?></p>
			<?php else : ?>
				<ul class="ncs-list">
					<?php foreach ( $errors as $err ) : ?>
						<li><?php echo Admin::badge( __( 'Fix', 'next-class-services' ), 'bad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<div class="ncs-savebar">
		<span class="ncs-savebar-status" id="ncs-dirty" role="status" hidden><?php esc_html_e( 'You have unsaved changes.', 'next-class-services' ); ?></span>
		<?php if ( $published ) : ?>
			<button type="submit" class="ncs-btn ncs-btn-primary" name="save_action" value="update"><?php esc_html_e( 'Update', 'next-class-services' ); ?></button>
			<button type="submit" class="ncs-btn" name="save_action" value="draft"><?php esc_html_e( 'Unpublish and save as draft', 'next-class-services' ); ?></button>
		<?php else : ?>
			<button type="submit" class="ncs-btn" name="save_action" value="draft"><?php esc_html_e( 'Save draft', 'next-class-services' ); ?></button>
			<button type="submit" class="ncs-btn ncs-btn-primary" name="save_action" value="publish"><?php esc_html_e( 'Publish', 'next-class-services' ); ?></button>
		<?php endif; ?>
	</div>
</form>

<?php if ( $id ) : ?>
<form id="ncs-test-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="ncs_assessment_test_email">
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
	<?php wp_nonce_field( 'ncs_assessment_test_email_' . $id, '_ncs_test_nonce' ); ?>
</form>
<?php endif; ?>

<script type="application/json" id="ncs-builder-data"><?php echo Helpers::json_for_html( $builder ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
