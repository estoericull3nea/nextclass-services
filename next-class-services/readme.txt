=== Next Class Services ===
Contributors: nextclass
Tags: assessment, lead generation, booking, appointments, calendly, tidycal
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

HR Diagnostic Assessment Tool and Online Booking System for Next Class. Two services in one plugin, configured entirely from the WordPress admin.

== Description ==

Next Class Services adds two services to your site.

**1. HR Diagnostic Assessment Tool**

* Build unlimited assessments without touching code.
* Eight question types: single choice, multiple choice, dropdown, rating scale, yes / no, number, short text, long text.
* Drag or button reordering, duplicate, enable / disable, required / optional.
* Conditional logic (equals, does not equal, contains, does not contain, greater than, less than) to show or skip questions. Skipped questions never affect scoring or validation.
* Configurable scoring: raw score, maximum applicable score and percentage.
* Unlimited result categories with your own ranges, descriptions, recommendations, program recommendations and buttons. Ranges are validated for gaps and overlaps.
* Flow: questions, then lead capture, then a personalized result. The result is only calculated and returned by the server after the lead form is accepted.
* Configurable lead fields (including custom fields) and consent checkboxes that are never pre-checked.
* Optional result email to the visitor and admin notification, with placeholders and test emails.
* Lead list with search, filters, detail view with answers, delete and CSV export.

**2. Online Booking System**

Choose a booking method for each service:

* Calendly (responsive embed with fallback link)
* TidyCal (responsive embed with fallback link)
* Custom URL or approved iframe embed
* Native WordPress booking with working days, hours, buffers, minimum notice, maximum advance, blocked dates, timezone, daily limits, double booking protection, confirmation emails, cancellation and rescheduling through private links, and an appointment manager with CSV export.

WordPress controls native booking availability. Optional Google Calendar sync creates an event with Google Meet for new bookings, updates the same event on rescheduling, and deletes the event on cancellation. Configure OAuth credentials, connect and test under Settings, then enable sync per service in General. API keys alone are insufficient. Sync sends service titles, booking times, and client names and emails for attendee invitations. Rescheduling and cancellation notify attendees. Google Calendar busy events are not imported. See README.md for setup and operational details.

External bookings made on Calendly, TidyCal or another provider are never reported as completed appointments, because those providers do not send this site a reliable confirmation. Analytics count views and interactions only.

**Analytics**

First-party, cookie free analytics with date ranges (today, 7 days, 30 days, this quarter, custom) and CSV export. Optional Google Analytics 4 events are sent only if `gtag` already exists on the page. The plugin never installs Google Analytics.

== Installation ==

1. In WordPress go to Plugins, Add Plugin, Upload Plugin.
2. Choose `next-class-services.zip` and click Install Now.
3. Click Activate.
4. Open Next Class Services in the admin menu.

Activation creates the database tables, the plugin capabilities (granted to administrators) and default settings. No public pages are created.

== Configuration ==

**Create an assessment**

1. Next Class Services, HR Assessments, Create assessment.
2. General tab: enter the public title, description and button text.
3. Questions tab: add questions, choose types, assign scores to answers, add conditional logic.
4. Scoring and results tab: add programs, then result categories. Ranges must cover every score without overlap (for example 0 to 25, 26 to 50, 51 to 75, 76 to 100).
5. Lead capture tab: choose fields and consent wording.
6. Emails tab: configure the visitor email and the admin notification, then send a test.
7. Publish tab: click Publish and copy the shortcode `[next_class_assessment id="123"]` onto any page.

Use Preview at any time. Previews never save submissions or send email.

**Create a booking service**

1. Next Class Services, Online Booking, Create booking service.
2. General tab: name, duration, description and booking method.
3. Booking method tab: enter the Calendly or TidyCal URL, or configure native availability.
4. Booking form and Emails tabs apply to native bookings.
5. Publish and copy the shortcode `[next_class_booking id="123"]`. Several IDs separated by commas let visitors choose a service: `[next_class_booking id="12,15"]`.

Customers receive a private link in their confirmation email. It returns to the page where they booked, which already contains the shortcode. You can set a fallback page in Settings.

**Settings**

Default notification email, sender name and address, default timezone, shared calendar, embed host allowlist, frontend appearance (primary button color, corner radius, optional Poppins), analytics, GA4, data retention and uninstall behavior.

== Data stored ==

* Assessment submissions: the lead fields you enable, answers, score, result, recommended programs, consent status and wording, page URL, date.
* Appointments: name, email, phone, company, job title, notes and custom fields you enable, time, timezone, status, page URL. Management tokens are stored as SHA-256 hashes.
* Analytics events: event type, assessment or service ID, a short context value and date. No IP address, cookie or device data.
* Short-lived rate limit counters use a keyed hash of the visitor IP for a few minutes.

Personal data is included in the WordPress personal data export and erase tools. Upcoming active appointments are kept by the eraser and by retention cleanup so scheduling is not broken.

== Testing checklist ==

Plugin
* Activate with no PHP errors. Deactivate. Reactivate and confirm data is still there.
* Uninstall with "Delete all data" off keeps data. With it on, tables, options, posts and capabilities are removed.

Assessments
* Create, edit, duplicate, delete, publish, unpublish.
* Add questions of every type, reorder, disable, mark required.
* Conditional logic shows and hides questions live; hidden questions are ignored by the server.
* Scoring: raw, maximum applicable and percentage; skipped questions do not corrupt the score.
* Result ranges: overlaps and gaps are reported; unpublished assessments cannot be published while invalid.
* Lead form validation, consent, honeypot, rate limit.
* Results, program recommendations and buttons render.
* Visitor email, admin notification and test emails.
* Lead list filters, detail view, CSV export, delete.

Booking
* Calendly, TidyCal, custom URL and custom embed configuration and fallback link.
* Native: working days, blocked dates, buffers, minimum notice, maximum advance, maximum per day.
* Double booking and buffer overlap are rejected server side.
* Confirmation, admin notification, cancellation and reschedule emails.
* Cancel and reschedule with notice windows; wrong or malformed tokens are rejected.
* Status changes release or reclaim time slots. CSV export.

Analytics and design
* Views, starts, completions, leads, native bookings, cancellations and reschedules are counted. External bookings are not counted as completed.
* Date filtering and CSV export. GA4 events fail silently without gtag.
* Scoped typography and palette, solid fills, decorative review icons with visible text labels, no em dashes.
* Keyboard navigation, visible focus, mobile layout, reduced motion.

== Troubleshooting ==

* **The assessment or calendar does not appear.** Make sure the service is published and the shortcode ID is correct. Administrators see a short notice when an ID is invalid; visitors see nothing.
* **"Your session has expired" errors on cached pages.** The plugin refreshes its nonce automatically. If a caching plugin also caches REST responses, exclude `/wp-json/ncs/` from caching.
* **Emails are not arriving.** Use the test email buttons. WordPress mail often needs an SMTP plugin. Set a sender address on your own domain in Settings. Enable "Write technical errors to the PHP error log" while debugging.
* **Calendly or TidyCal shows blank.** Some ad blockers and privacy settings block embeds. The "open in a new tab" link is always shown as a fallback.
* **Times look wrong.** Check the service timezone and the WordPress timezone in Settings, General.
* **Cancel links open the home page.** The link returns to the page where the customer booked. Set a fallback booking page URL in Settings, and make sure that page contains the booking shortcode.
* **Slots disappear after changing Settings.** Sharing one calendar across services blocks the same time for every native service. Turn it off if services use different staff.

== Changelog ==

= 1.4.0 =
* New native booking requests require staff approval. Clients receive an awaiting-review receipt and a separate approval email when staff confirms the appointment.
* Google Calendar events, Meet links and invitations wait until approval.

= 1.3.0 =
* Standardized plugin email as professional letters with branded letterhead, greetings, detail tables, action links and closing signatures in HTML and plain text.
* Added recipient-appropriate admin booking update messages while preserving custom customer templates and calendar attachments.

= 1.2.3 =
* Send admin calendar emails directly through WordPress with matching .ics files, independent of Google guest notifications. Retry failed mail and show its handoff status.

= 1.2.2 =
* Calendar invitations include the Notification email, falling back to the WordPress admin email.
* Added matching icons to the Booking received screen.

= 1.2.1 =
* Refined the booking review screen with inline icons, progress indicators and clearer edit and confirm actions.

= 1.2.0 =
* Native Google Calendar bookings now invite clients and notify them of reschedules and cancellations.
* Added editable placeholders for built-in and custom booking fields.
* Improved frontend review, booking confirmation and private management screens.

= 1.1.0 =
* Added optional Google Calendar and Google Meet sync for native bookings, OAuth configuration, API lifecycle testing, encrypted credentials and background retries.
* Booking services now use a three-dot actions menu.

= 1.0.0 =
* Initial release: assessments, lead capture, results, emails, lead management, booking (Calendly, TidyCal, custom, native), appointments, analytics, optional GA4 events, privacy tools, retention.

== Upgrade Notice ==

= 1.1.0 =
Adds appointment sync fields automatically. Connect Google Calendar in Settings and enable sync per service. Existing appointments are not automatically exported.

= 1.0.0 =
First release.
