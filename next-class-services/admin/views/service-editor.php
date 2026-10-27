<?php
/**
 * Booking service editor view.
 *
 * @var int    $id
 * @var array  $cfg
 * @var string $status
 * @var array  $errors
 * @var string $tab
 *
 * @package NextClassServices
 */

use NextClassServices\Admin\Admin;
use NextClassServices\Admin\Form;
use NextClassServices\Booking\Booking_Email;
use NextClassServices\Booking\Booking_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general'  => __( 'General', 'next-class-services' ),
	'method'   => __( 'Booking method', 'next-class-services' ),
	'form'     => __( 'Booking form', 'next-class-services' ),
	'emails'   => __( 'Emails', 'next-class-services' ),
	'publish'  => __( 'Publish', 'next-class-services' ),
);
if ( ! isset( $tabs[ $tab ] ) ) {
	$tab = 'general';
}
$published = 'publish' === $status;
$n         = $cfg['native'];
$ex        = $cfg['external'];
$nt        = $cfg['notify'];
$is_admin  = current_user_can( 'manage_options' );
$weekdays  = array(
	1 => __( 'Monday', 'next-class-services' ),
	2 => __( 'Tuesday', 'next-class-services' ),
	3 => __( 'Wednesday', 'next-class-services' ),
	4 => __( 'Thursday', 'next-class-services' ),
	5 => __( 'Friday', 'next-class-services' ),
	6 => __( 'Saturday', 'next-class-services' ),
	7 => __( 'Sunday', 'next-class-services' ),
);
$tz_options = array(
	'' => sprintf(
		/* translators: %s: timezone name */
		__( 'Use the site timezone (%s)', 'next-class-services' ),
		\NextClassServices\Helpers::timezone()->getName()
	),
);
foreach ( timezone_identifiers_list() as $tz_name ) {
	$tz_options[ $tz_name ] = $tz_name;
}
$embed_value = 'embed' === $ex['custom_mode'] ? $ex['url'] : '';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ncs-service-form" class="ncs-form" data-ncs-warn-unsaved>
	<input type="hidden" name="action" value="ncs_save_service">
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
	<input type="hidden" name="tab" id="ncs-current-tab" value="<?php echo esc_attr( $tab ); ?>">
	<?php wp_nonce_field( 'ncs_save_service_' . $id, '_ncs_nonce' ); ?>

	<div class="ncs-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Booking service sections', 'next-class-services' ); ?>">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<button type="button" role="tab" class="ncs-tab<?php echo $key === $tab ? ' is-active' : ''; ?>" id="ncs-tab-<?php echo esc_attr( $key ); ?>" data-tab="<?php echo esc_attr( $key ); ?>" aria-controls="ncs-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo $key === $tab ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>

	<!-- General -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-general" aria-labelledby="ncs-tab-general"<?php echo 'general' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Service details', 'next-class-services' ); ?></h2>
			<div class="ncs-cols">
				<?php
				Form::text( 'cfg[admin_title]', __( 'Internal title', 'next-class-services' ), $cfg['admin_title'], array( 'help' => __( 'Only shown in the admin area.', 'next-class-services' ) ) );
				Form::text( 'cfg[title]', __( 'Public service name', 'next-class-services' ), $cfg['title'], array( 'required' => true ) );
				?>
			</div>
			<?php
			Form::textarea( 'cfg[description]', __( 'Description', 'next-class-services' ), $cfg['description'], array( 'rows' => 3 ) );
			Form::number( 'cfg[duration]', __( 'Duration (minutes)', 'next-class-services' ), $cfg['duration'], array( 'min' => 5, 'max' => 480, 'step' => 5 ) );
			Form::select( 'cfg[method]', __( 'Booking method', 'next-class-services' ), Booking_Service::methods(), $cfg['method'], array( 'help' => __( 'Each service can use a different method. External providers manage their own calendars, confirmations and reminders.', 'next-class-services' ) ) );
			?>
			<div data-show-method="native"<?php echo 'native' === $cfg['method'] ? '' : ' hidden'; ?>>
				<?php Form::checkbox( 'cfg[native][google_sync]', __( 'Sync to Google Calendar and create a Google Meet', 'next-class-services' ), $n['google_sync'], array( 'class' => 'ncs-calendar-toggle', 'help' => __( 'Connect Google Calendar in Settings first. New bookings create an event with a Meet link. Cancellations remove the event; rescheduling updates the same event. Existing synced bookings continue syncing when this is turned off.', 'next-class-services' ) ) ); ?>
				<div class="ncs-booking-calendar-status">
					<?php $google_connected = \NextClassServices\Booking\Google_Calendar::connected(); ?>
					<span class="ncs-connection-badge <?php echo $google_connected ? 'is-connected' : 'is-disconnected'; ?>" role="status"><?php echo esc_html( $google_connected ? __( 'Connected', 'next-class-services' ) : __( 'Not connected', 'next-class-services' ) ); ?></span>
					<?php if ( current_user_can( 'ncs_manage_settings' ) && current_user_can( 'manage_options' ) ) : ?>
						<a class="ncs-btn ncs-btn-small" href="<?php echo esc_url( Admin::url( 'ncs-settings', array( 'tab' => 'booking' ) ) . '#ncs-google-calendar' ); ?>"><?php esc_html_e( 'Google Calendar settings', 'next-class-services' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Display', 'next-class-services' ); ?></h2>
			<?php
			Form::checkbox( 'cfg[display][show_description]', __( 'Show the description', 'next-class-services' ), $cfg['display']['show_description'] );
			Form::checkbox( 'cfg[display][show_duration]', __( 'Show the duration', 'next-class-services' ), $cfg['display']['show_duration'] );
			Form::checkbox( 'cfg[display][show_timezone]', __( 'Show the timezone (native bookings)', 'next-class-services' ), $cfg['display']['show_timezone'] );
			Form::text( 'cfg[display][button_text]', __( 'Booking button text', 'next-class-services' ), $cfg['display']['button_text'], array( 'help' => __( 'Used for the external booking link and the confirm button.', 'next-class-services' ) ) );
			?>
		</div>
	</section>

	<!-- Method -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-method" aria-labelledby="ncs-tab-method"<?php echo 'method' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card" data-show-method="calendly tidycal custom">
			<h2 class="ncs-card-title"><?php esc_html_e( 'External booking', 'next-class-services' ); ?></h2>
			<p class="ncs-help" data-show-method="calendly tidycal custom"><?php esc_html_e( 'Appointments made on an external provider are not confirmed by this plugin. Analytics count views and interactions, never completed appointments.', 'next-class-services' ); ?></p>
			<div data-show-method="custom">
				<?php
				Form::select(
					'cfg[external][custom_mode]',
					__( 'Custom booking type', 'next-class-services' ),
					array(
						'url'   => __( 'External booking URL', 'next-class-services' ),
						'embed' => __( 'Approved iframe embed', 'next-class-services' ),
					),
					$ex['custom_mode']
				);
				?>
			</div>
			<div data-show-custom-mode="url">
				<?php Form::text( 'cfg[external][url]', __( 'Booking URL', 'next-class-services' ), 'embed' === $ex['custom_mode'] && 'custom' === $cfg['method'] ? '' : $ex['url'], array( 'type' => 'url', 'placeholder' => 'https://', 'help' => __( 'Must start with https. Calendly URLs must be on calendly.com and TidyCal URLs on tidycal.com.', 'next-class-services' ) ) ); ?>
			</div>
			<div data-show-method="custom" data-show-custom-mode="embed">
				<?php
				if ( $is_admin ) {
					Form::textarea( 'cfg[external][embed_source]', __( 'Embed code or embed address', 'next-class-services' ), $embed_value, array( 'rows' => 3, 'help' => __( 'Paste an iframe snippet or its address. Only the https address is kept and the plugin outputs its own sandboxed iframe. Scripts are never stored. Optionally restrict hosts in Settings.', 'next-class-services' ) ) );
				} else {
					echo '<p class="ncs-muted">' . esc_html__( 'Only site administrators can configure a custom embed.', 'next-class-services' ) . '</p>';
				}
				?>
			</div>
			<div class="ncs-cols">
				<?php
				Form::select(
					'cfg[external][mode]',
					__( 'Display', 'next-class-services' ),
					array(
						'embed' => __( 'Embed inline on the page', 'next-class-services' ),
						'link'  => __( 'Show a button that opens the booking page', 'next-class-services' ),
					),
					$ex['mode'],
					array( 'help' => __( 'A link to the booking page is always shown as a fallback if embedding fails.', 'next-class-services' ) )
				);
				Form::number( 'cfg[external][height]', __( 'Embed height (pixels)', 'next-class-services' ), $ex['height'], array( 'min' => 300, 'max' => 1600, 'step' => 10 ) );
				?>
			</div>
		</div>

		<div data-show-method="native">
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Availability', 'next-class-services' ); ?></h2>
				<p class="ncs-help"><?php esc_html_e( 'WordPress controls booking availability. Enable Google Calendar sync in General to send bookings to Google Calendar with Meet links. Events created directly in Google Calendar do not block availability here.', 'next-class-services' ); ?></p>
				<fieldset class="ncs-fieldset" id="ncs-native-days">
					<legend><?php esc_html_e( 'Available working days', 'next-class-services' ); ?></legend>
					<div class="ncs-inline-checks">
						<?php foreach ( $weekdays as $num => $day_label ) : ?>
							<div class="ncs-field ncs-field-check"><label><input type="checkbox" name="cfg[native][days][]" value="<?php echo esc_attr( $num ); ?>"<?php checked( in_array( $num, array_map( 'intval', $n['days'] ), true ) ); ?>> <?php echo esc_html( $day_label ); ?></label></div>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<div class="ncs-cols ncs-cols-3">
					<?php
					Form::text( 'cfg[native][start]', __( 'Start time', 'next-class-services' ), $n['start'], array( 'type' => 'time' ) );
					Form::text( 'cfg[native][end]', __( 'End time', 'next-class-services' ), $n['end'], array( 'type' => 'time' ) );
					Form::number( 'cfg[native][slot_step]', __( 'Slot interval (minutes)', 'next-class-services' ), $n['slot_step'], array( 'min' => 0, 'max' => 480, 'step' => 5, 'help' => __( '0 uses the appointment duration.', 'next-class-services' ) ) );
					?>
				</div>
				<div class="ncs-cols ncs-cols-3">
					<?php
					Form::number( 'cfg[native][buffer_before]', __( 'Buffer before (minutes)', 'next-class-services' ), $n['buffer_before'], array( 'min' => 0, 'max' => 240, 'step' => 5 ) );
					Form::number( 'cfg[native][buffer_after]', __( 'Buffer after (minutes)', 'next-class-services' ), $n['buffer_after'], array( 'min' => 0, 'max' => 240, 'step' => 5 ) );
					Form::number( 'cfg[native][max_per_day]', __( 'Maximum bookings per day', 'next-class-services' ), $n['max_per_day'], array( 'min' => 0, 'max' => 500, 'help' => __( '0 means no limit.', 'next-class-services' ) ) );
					?>
				</div>
				<div class="ncs-cols">
					<?php
					Form::number( 'cfg[native][min_notice_hours]', __( 'Minimum booking notice (hours)', 'next-class-services' ), $n['min_notice_hours'], array( 'min' => 0, 'max' => 8760 ) );
					Form::number( 'cfg[native][max_advance_days]', __( 'Maximum advance booking (days)', 'next-class-services' ), $n['max_advance_days'], array( 'min' => 1, 'max' => 730, 'help' => __( 'Cannot be shorter than the minimum notice.', 'next-class-services' ) ) );
					?>
				</div>
				<?php
				Form::select( 'cfg[native][timezone]', __( 'Timezone', 'next-class-services' ), $tz_options, $n['timezone'], array( 'help' => __( 'Working hours are interpreted in this timezone and shown to visitors.', 'next-class-services' ) ) );
				Form::textarea(
					'cfg[native][blocked_dates]',
					__( 'Blocked dates and holidays', 'next-class-services' ),
					$n['blocked_dates'],
					array(
						'rows' => 5,
						'help' => __( 'One per line. Use <code>2026-12-25 Holiday</code> for a day or <code>2026-12-24 to 2026-12-31 Closed</code> for a range.', 'next-class-services' ),
					)
				);
				Form::select( 'cfg[native][new_status]', __( 'Status of new bookings', 'next-class-services' ), array(
					'pending'   => __( 'Pending staff approval', 'next-class-services' ),
				), 'pending', array( 'help' => __( 'Every new request must be reviewed under Appointments. Change its status to Confirmed to approve it and email the client.', 'next-class-services' ) ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Cancellation and rescheduling', 'next-class-services' ); ?></h2>
				<p class="ncs-help"><?php esc_html_e( 'Customers use a private link from their confirmation email. Links are random, are not based on database IDs, and stop working for changes once the appointment has started or is cancelled.', 'next-class-services' ); ?></p>
				<?php
				Form::checkbox( 'cfg[native][allow_cancel]', __( 'Allow customers to cancel', 'next-class-services' ), $n['allow_cancel'] );
				Form::number( 'cfg[native][cancel_notice_hours]', __( 'Minimum notice to cancel (hours)', 'next-class-services' ), $n['cancel_notice_hours'], array( 'min' => 0, 'max' => 8760 ) );
				Form::checkbox( 'cfg[native][allow_reschedule]', __( 'Allow customers to reschedule', 'next-class-services' ), $n['allow_reschedule'] );
				Form::number( 'cfg[native][reschedule_notice_hours]', __( 'Minimum notice to reschedule (hours)', 'next-class-services' ), $n['reschedule_notice_hours'], array( 'min' => 0, 'max' => 8760 ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Confirmation', 'next-class-services' ); ?></h2>
				<?php
				Form::textarea( 'cfg[confirmation][message]', __( 'Confirmation message', 'next-class-services' ), $cfg['confirmation']['message'], array( 'rows' => 3, 'help' => __( 'Shown after booking. Placeholders such as {customer_name} and {appointment_date} are supported.', 'next-class-services' ) ) );
				Form::text( 'cfg[confirmation][redirect_url]', __( 'Redirect URL after booking (optional)', 'next-class-services' ), $cfg['confirmation']['redirect_url'], array( 'type' => 'url', 'placeholder' => 'https://' ) );
				?>
			</div>
		</div>
	</section>

	<!-- Form -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-form" aria-labelledby="ncs-tab-form"<?php echo 'form' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card" data-show-method="native">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Customer form', 'next-class-services' ); ?></h2>
			<p class="ncs-help"><?php esc_html_e( 'Choose the fields for native bookings. Name and email are always required.', 'next-class-services' ); ?></p>
			<table class="ncs-table ncs-table-fields">
				<thead><tr><th scope="col"><?php esc_html_e( 'Field', 'next-class-services' ); ?></th><th scope="col"><?php esc_html_e( 'Label', 'next-class-services' ); ?></th><th scope="col"><?php esc_html_e( 'Placeholder', 'next-class-services' ); ?></th><th scope="col"><?php esc_html_e( 'Enabled', 'next-class-services' ); ?></th><th scope="col"><?php esc_html_e( 'Required', 'next-class-services' ); ?></th></tr></thead>
				<tbody>
				<?php
				$builtin_names = array(
					'name'      => __( 'Name', 'next-class-services' ),
					'email'     => __( 'Email', 'next-class-services' ),
					'phone'     => __( 'Phone', 'next-class-services' ),
					'company'   => __( 'Company', 'next-class-services' ),
					'job_title' => __( 'Job title', 'next-class-services' ),
					'notes'     => __( 'Notes', 'next-class-services' ),
				);
				$custom_rows = array();
				foreach ( $n['fields'] as $f ) {
					if ( ! empty( $f['custom'] ) ) {
						$custom_rows[] = $f;
						continue;
					}
					$locked = in_array( $f['key'], array( 'name', 'email' ), true );
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $builtin_names[ $f['key'] ] ); ?></th>
						<td><label class="screen-reader-text" for="ncs-fl-<?php echo esc_attr( $f['key'] ); ?>"><?php esc_html_e( 'Label', 'next-class-services' ); ?></label><input type="text" id="ncs-fl-<?php echo esc_attr( $f['key'] ); ?>" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>"></td>
						<td><label class="screen-reader-text" for="ncs-fp-<?php echo esc_attr( $f['key'] ); ?>"><?php esc_html_e( 'Placeholder', 'next-class-services' ); ?></label><input type="text" maxlength="200" id="ncs-fp-<?php echo esc_attr( $f['key'] ); ?>" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][placeholder]" value="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : Booking_Service::field_placeholder( $f['key'] ) ); ?>"></td>
						<td><label><input type="checkbox" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][enabled]" value="1"<?php checked( $f['enabled'] ); ?><?php disabled( $locked ); ?>> <span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'next-class-services' ); ?></span></label><?php if ( $locked ) : ?><input type="hidden" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][enabled]" value="1"><?php endif; ?></td>
						<td><label><input type="checkbox" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][required]" value="1"<?php checked( $f['required'] ); ?><?php disabled( $locked ); ?>> <span class="screen-reader-text"><?php esc_html_e( 'Required', 'next-class-services' ); ?></span></label><?php if ( $locked ) : ?><input type="hidden" name="cfg[native][fields][<?php echo esc_attr( $f['key'] ); ?>][required]" value="1"><?php endif; ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>

			<h3 class="ncs-subtitle"><?php esc_html_e( 'Custom fields', 'next-class-services' ); ?></h3>
			<div id="ncs-custom-fields">
				<?php foreach ( $custom_rows as $i => $f ) : ?>
					<div class="ncs-custom-row">
						<input type="hidden" name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $f['key'] ); ?>">
						<div class="ncs-field"><label><?php esc_html_e( 'Label', 'next-class-services' ); ?><input type="text" name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>"></label></div>
						<div class="ncs-field"><label><?php esc_html_e( 'Placeholder', 'next-class-services' ); ?><input type="text" maxlength="200" name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][placeholder]" value="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ); ?>"></label></div>
						<div class="ncs-field"><label><?php esc_html_e( 'Type', 'next-class-services' ); ?>
							<select name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][type]">
								<option value="text"<?php selected( $f['type'], 'text' ); ?>><?php esc_html_e( 'Text', 'next-class-services' ); ?></option>
								<option value="textarea"<?php selected( $f['type'], 'textarea' ); ?>><?php esc_html_e( 'Long text', 'next-class-services' ); ?></option>
								<option value="select"<?php selected( $f['type'], 'select' ); ?>><?php esc_html_e( 'Dropdown', 'next-class-services' ); ?></option>
							</select></label></div>
						<div class="ncs-field"><label><?php esc_html_e( 'Choices (one per line, dropdown only)', 'next-class-services' ); ?><textarea rows="3" name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][options]"><?php echo esc_textarea( implode( "\n", $f['options'] ) ); ?></textarea></label></div>
						<div class="ncs-field ncs-field-check"><label><input type="checkbox" name="cfg[native][custom][<?php echo esc_attr( $i ); ?>][required]" value="1"<?php checked( $f['required'] ); ?>> <?php esc_html_e( 'Required', 'next-class-services' ); ?></label></div>
						<button type="button" class="ncs-btn ncs-btn-small ncs-btn-danger" data-ncs-remove-row><?php esc_html_e( 'Remove', 'next-class-services' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="ncs-btn ncs-btn-small" id="ncs-add-custom-field"><?php esc_html_e( 'Add custom field', 'next-class-services' ); ?></button></p>
			<template id="ncs-custom-template">
				<div class="ncs-custom-row">
					<input type="hidden" name="cfg[native][custom][__i__][key]" value="">
					<div class="ncs-field"><label><?php esc_html_e( 'Label', 'next-class-services' ); ?><input type="text" name="cfg[native][custom][__i__][label]" value="" placeholder="e.g. Team size"></label></div>
					<div class="ncs-field"><label><?php esc_html_e( 'Placeholder', 'next-class-services' ); ?><input type="text" maxlength="200" name="cfg[native][custom][__i__][placeholder]" value="" placeholder="e.g. Enter your team size"></label></div>
					<div class="ncs-field"><label><?php esc_html_e( 'Type', 'next-class-services' ); ?>
						<select name="cfg[native][custom][__i__][type]">
							<option value="text"><?php esc_html_e( 'Text', 'next-class-services' ); ?></option>
							<option value="textarea"><?php esc_html_e( 'Long text', 'next-class-services' ); ?></option>
							<option value="select"><?php esc_html_e( 'Dropdown', 'next-class-services' ); ?></option>
						</select></label></div>
					<div class="ncs-field"><label><?php esc_html_e( 'Choices (one per line, dropdown only)', 'next-class-services' ); ?><textarea rows="3" name="cfg[native][custom][__i__][options]"></textarea></label></div>
					<div class="ncs-field ncs-field-check"><label><input type="checkbox" name="cfg[native][custom][__i__][required]" value="1"> <?php esc_html_e( 'Required', 'next-class-services' ); ?></label></div>
					<button type="button" class="ncs-btn ncs-btn-small ncs-btn-danger" data-ncs-remove-row><?php esc_html_e( 'Remove', 'next-class-services' ); ?></button>
				</div>
			</template>
		</div>
		<div class="ncs-card" data-show-method="calendly tidycal custom">
			<p class="ncs-muted"><?php esc_html_e( 'External providers collect customer details on their own booking page. The form settings apply to native bookings only.', 'next-class-services' ); ?></p>
		</div>
	</section>

	<!-- Emails -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-emails" aria-labelledby="ncs-tab-emails"<?php echo 'emails' === $tab ? '' : ' hidden'; ?>>
		<div data-show-method="native">
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Sender', 'next-class-services' ); ?></h2>
				<div class="ncs-cols">
					<?php
					Form::text( 'cfg[notify][from_name]', __( 'Sender name', 'next-class-services' ), $nt['from_name'] );
					Form::text( 'cfg[notify][reply_to]', __( 'Reply-to address', 'next-class-services' ), $nt['reply_to'], array( 'type' => 'email' ) );
					?>
				</div>
				<p class="ncs-help"><?php esc_html_e( 'Available placeholders:', 'next-class-services' ); ?> <code><?php echo esc_html( '{' . implode( '} {', Booking_Email::placeholders() ) . '}' ); ?></code></p>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Customer booking confirmation', 'next-class-services' ); ?></h2>
				<?php
				Form::checkbox( 'cfg[notify][customer][enabled]', __( 'Send the customer a confirmation', 'next-class-services' ), $nt['customer']['enabled'] );
				Form::text( 'cfg[notify][customer][subject]', __( 'Subject', 'next-class-services' ), $nt['customer']['subject'] );
				Form::textarea( 'cfg[notify][customer][body]', __( 'Message', 'next-class-services' ), $nt['customer']['body'], array( 'rows' => 8 ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Admin new booking notification', 'next-class-services' ); ?></h2>
				<?php
				Form::checkbox( 'cfg[notify][admin][enabled]', __( 'Notify staff of new bookings', 'next-class-services' ), $nt['admin']['enabled'] );
				Form::text( 'cfg[notify][admin][recipients]', __( 'Recipient email addresses', 'next-class-services' ), $nt['admin']['recipients'], array( 'help' => __( 'Separate several with commas. If empty, the default notification email from Settings is used.', 'next-class-services' ) ) );
				Form::text( 'cfg[notify][admin][subject]', __( 'Subject', 'next-class-services' ), $nt['admin']['subject'] );
				Form::textarea( 'cfg[notify][admin][body]', __( 'Message', 'next-class-services' ), $nt['admin']['body'], array( 'rows' => 8 ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Cancellation notification', 'next-class-services' ); ?></h2>
				<?php
				Form::checkbox( 'cfg[notify][cancel][enabled]', __( 'Send cancellation emails', 'next-class-services' ), $nt['cancel']['enabled'] );
				Form::checkbox( 'cfg[notify][cancel][notify_admin]', __( 'Also notify staff', 'next-class-services' ), $nt['cancel']['notify_admin'] );
				Form::text( 'cfg[notify][cancel][subject]', __( 'Subject', 'next-class-services' ), $nt['cancel']['subject'] );
				Form::textarea( 'cfg[notify][cancel][body]', __( 'Message', 'next-class-services' ), $nt['cancel']['body'], array( 'rows' => 6 ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Reschedule notification', 'next-class-services' ); ?></h2>
				<?php
				Form::checkbox( 'cfg[notify][reschedule][enabled]', __( 'Send reschedule emails', 'next-class-services' ), $nt['reschedule']['enabled'] );
				Form::checkbox( 'cfg[notify][reschedule][notify_admin]', __( 'Also notify staff', 'next-class-services' ), $nt['reschedule']['notify_admin'] );
				Form::text( 'cfg[notify][reschedule][subject]', __( 'Subject', 'next-class-services' ), $nt['reschedule']['subject'] );
				Form::textarea( 'cfg[notify][reschedule][body]', __( 'Message', 'next-class-services' ), $nt['reschedule']['body'], array( 'rows' => 6 ) );
				?>
			</div>
			<div class="ncs-card">
				<h2 class="ncs-card-title"><?php esc_html_e( 'Send a test email', 'next-class-services' ); ?></h2>
				<?php if ( $id ) : ?>
					<p class="ncs-help"><?php esc_html_e( 'Tests use the last saved settings and sample data. Save your changes first.', 'next-class-services' ); ?></p>
					<div class="ncs-field">
						<label for="ncs-test-to"><?php esc_html_e( 'Send test to', 'next-class-services' ); ?></label>
						<input type="email" id="ncs-test-to" name="test_to" form="ncs-test-form" placeholder="you@yourdomain.com" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
					</div>
					<button type="submit" class="ncs-btn" name="kind" value="customer" form="ncs-test-form"><?php esc_html_e( 'Customer confirmation', 'next-class-services' ); ?></button>
					<button type="submit" class="ncs-btn" name="kind" value="admin" form="ncs-test-form"><?php esc_html_e( 'Admin notification', 'next-class-services' ); ?></button>
					<button type="submit" class="ncs-btn" name="kind" value="cancel" form="ncs-test-form"><?php esc_html_e( 'Cancellation', 'next-class-services' ); ?></button>
					<button type="submit" class="ncs-btn" name="kind" value="reschedule" form="ncs-test-form"><?php esc_html_e( 'Reschedule', 'next-class-services' ); ?></button>
				<?php else : ?>
					<p class="ncs-muted"><?php esc_html_e( 'Save the service first to enable test emails.', 'next-class-services' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="ncs-card" data-show-method="calendly tidycal custom">
			<p class="ncs-muted"><?php esc_html_e( 'Confirmation and reminder emails for external bookings are sent by the booking provider.', 'next-class-services' ); ?></p>
		</div>
	</section>

	<!-- Publish -->
	<section class="ncs-panel" role="tabpanel" id="ncs-panel-publish" aria-labelledby="ncs-tab-publish"<?php echo 'publish' === $tab ? '' : ' hidden'; ?>>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Status and shortcode', 'next-class-services' ); ?></h2>
			<p><?php esc_html_e( 'Status:', 'next-class-services' ); ?> <?php echo Admin::badge( $published ? __( 'Published', 'next-class-services' ) : __( 'Draft', 'next-class-services' ), $published ? 'ok' : 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php if ( $id ) : ?>
				<p><?php esc_html_e( 'Paste this shortcode into any page or post:', 'next-class-services' ); ?></p>
				<p><?php Admin::shortcode_box( Booking_Service::shortcode( $id ) ); ?></p>
				<p class="ncs-help"><?php esc_html_e( 'To let visitors choose between services, list several IDs, for example [next_class_booking id="12,15"].', 'next-class-services' ); ?></p>
				<p><a class="ncs-btn" href="<?php echo esc_url( Admin::url( 'ncs-booking', array( 'action' => 'preview', 'id' => $id ) ) ); ?>"><?php esc_html_e( 'Preview booking experience', 'next-class-services' ); ?></a></p>
			<?php else : ?>
				<p class="ncs-muted"><?php esc_html_e( 'The shortcode is created when you save.', 'next-class-services' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ncs-card">
			<h2 class="ncs-card-title"><?php esc_html_e( 'Publishing checklist', 'next-class-services' ); ?></h2>
			<?php if ( ! $id ) : ?>
				<p class="ncs-muted"><?php esc_html_e( 'Save a draft to see which checks still need attention.', 'next-class-services' ); ?></p>
			<?php elseif ( empty( $errors ) ) : ?>
				<p class="ncs-notice ncs-notice-success"><strong class="ncs-notice-label"><?php esc_html_e( 'Done.', 'next-class-services' ); ?></strong> <?php esc_html_e( 'This service passes all publishing checks.', 'next-class-services' ); ?></p>
			<?php else : ?>
				<ul class="ncs-list ncs-check-list">
					<?php foreach ( $errors as $err ) : ?>
						<li class="ncs-notice ncs-notice-error"><strong class="ncs-notice-label"><?php esc_html_e( 'Error.', 'next-class-services' ); ?></strong> <?php echo Admin::error_list( array( $err ), 'ncs-booking', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
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
	<input type="hidden" name="action" value="ncs_service_test_email">
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
	<?php wp_nonce_field( 'ncs_service_test_email_' . $id, '_ncs_test_nonce' ); ?>
</form>
<?php endif; ?>
