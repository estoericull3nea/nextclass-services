# Next Class Services

WordPress plugin with two services for Next Class: an HR Diagnostic Assessment Tool and an Online Booking System.

- Version: 1.2.1
- Requires WordPress 6.0 or newer, PHP 7.4 or newer
- Tested up to WordPress 6.8
- Text domain: `next-class-services`, PHP prefix `ncs_`, namespace `NextClassServices`
- Optional outbound Google Calendar sync with Google Meet for native bookings. No CRM integration or Google Analytics installation.

See `readme.txt` for installation, configuration, the testing checklist, troubleshooting and the changelog.

## Architecture

Definitions (assessments and booking services) are low volume, need publish and draft states and are easy to duplicate, so they are stored in two private custom post types (`ncs_assessment`, `ncs_service`). The full configuration is one JSON document in the `_ncs_config` post meta, sanitized field by field on save. The shortcode ID is the post ID.

High volume operational data uses dedicated tables with indexes, created with `dbDelta` and versioned by the `ncs_db_version` option. Schema work only runs when the stored version differs from `NCS_DB_VERSION`, never on every request. Add new migrations to `Database::run_migrations()`.

```
next-class-services/
  next-class-services.php      Plugin header, constants, environment check
  uninstall.php                Opt-in data removal
  readme.txt / README.md
  includes/                    Loader (autoloader), Activator, Deactivator, Database,
                               Capabilities, Assets, Privacy, Retention, Post_Types,
                               Helpers, Mailer, Tracker
  admin/                       Admin (menu, notices), Dashboard, Assessments,
                               Assessment_Builder, Assessment_Leads, Booking_Services,
                               Appointments, Analytics, Settings, Form
    views/                     assessment-editor.php, service-editor.php
    css/admin.css              Scoped under .ncs-admin, Poppins bundled locally
    js/                        admin.js, assessment-builder.js, booking-admin.js, analytics.js
  public/                      Front (shortcodes, REST routes, guards),
                               Assessment_Shortcode, Booking_Shortcode,
                               Assessment_Handler, Booking_Handler
    css/public.css             Scoped under .ncs-root
    js/                        analytics.js (REST client, GA4), assessment.js, booking.js
  modules/assessments/         Assessment, Question, Conditional_Logic, Scoring,
                               Results, Leads, Assessment_Email
  modules/booking/             Booking_Service, Availability, Appointment, Booking_Email,
                               Calendly, Tidycal, Custom_Booking, Native_Booking
  templates/                   assessment/container, booking/container, emails/layout
  assets/fonts/                Poppins 400, 500, 600 (woff2)
  languages/                   next-class-services.pot
```

Classes are autoloaded from their namespace: `NextClassServices\Assessments\Conditional_Logic` maps to `modules/assessments/class-conditional-logic.php`.

## Database schema

All tables use the WordPress prefix.

| Table | Purpose | Key indexes |
| --- | --- | --- |
| `ncs_submissions` | One row per completed assessment: lead fields, score, result, programs, consent flags and wording | `(assessment_id, created_at)`, `email`, `result_id`, `created_at` |
| `ncs_answers` | One row per answered visible question with label, score and maximum | `submission_id` |
| `ncs_appointments` | Native bookings, times stored in UTC plus the service timezone, token hash, buffered window, `active_slot` | unique `token_hash`, unique `active_slot`, `(service_id, start_utc)`, `(block_start_utc, block_end_utc)`, `(status, start_utc)` |
| `ncs_events` | Anonymous analytics counters (type, object, short meta, date) | `(event_type, created_at)`, `(object_type, object_id, event_type)` |

## Key design decisions

- **Server side authority.** The browser never sends a score. The server normalizes every answer, evaluates conditional logic with the same rules as `assessment.js`, validates required fields for visible questions only, scores, matches a result and only then returns it.
- **Scoring.** Raw score and maximum are summed over visible questions. Optional scored questions left unanswered are treated as skipped (not counted in either). Percentage is clamped to 0 to 100. Result ranges can be based on percentage (default) or raw score.
- **Double booking.** Availability is recalculated inside a MySQL `GET_LOCK` critical section immediately before insert. A unique index on `active_slot` (`all_<timestamp>` or `<service>_<timestamp>`) is a database level backstop for identical start times. Buffers are enforced by comparing buffered windows.
- **Timezones.** Times are stored in UTC and in the service timezone name. Calculations use `DateTimeImmutable` with the service timezone, never the server default.
- **Customer links.** Cancel and reschedule links contain a random 160 bit token. Only its SHA-256 hash is stored. Notice windows are enforced server side.
- **Public endpoints.** REST namespace `ncs/v1`. POST routes check a nonce header, a honeypot and a per-visitor rate limit, and validate every parameter. `/nonce` returns a fresh nonce for cached pages. Draft previews additionally require a logged in user with the right capability and a preview nonce.
- **Capabilities.** `ncs_manage_assessments`, `ncs_manage_submissions`, `ncs_manage_booking_services`, `ncs_manage_appointments`, `ncs_view_analytics`, `ncs_manage_settings`. Granted to administrators on activation. Filter: `ncs_capability_slugs`.

## Extension points

- Actions: `ncs_appointment_created`, `ncs_appointment_updated`, `ncs_appointment_cancelled`.

## Google Calendar setup

1. Enable Google Calendar API in a Google Cloud project and configure its OAuth consent screen. For External apps in Testing, add the connecting Google account as a test user; testing refresh tokens can expire after seven days. Use a production OAuth app for ongoing bookings.
2. Create an OAuth client of type **Web application**. Copy the exact redirect URI shown under Next Class Services > Settings > Google Calendar into the client's authorized redirect URIs.
3. Save the OAuth client ID, client secret and calendar ID in that section. `primary` uses the connecting account's primary calendar. An API key alone cannot authorize event creation. PHP OpenSSL is required for encrypted credential storage; changing WordPress authentication salts requires re-entering the secret and reconnecting.
4. Click **Connect / reconnect Google**, grant Calendar event and calendar metadata permissions, then click **Test Calendar and Meet API**. The test creates a temporary event with a Meet request, changes its time, and deletes it. Pending Meet generation is reported as unverified. Failed cleanup retains the test event ID and the next test retries deletion first.
5. Edit a booking service, select Native WordPress booking in General, and enable **Sync to Google Calendar and create a Google Meet**. This applies to new bookings, including pending bookings. Existing synced appointments continue syncing after the toggle is turned off.

Sync saves the service title and booking times on the connected Google calendar, adds the client name and email as an attendee, and uses `sendUpdates=all` for invitations, rescheduling and cancellation. Client calendar appearance depends on their Google invitation settings and may require an RSVP. Phone numbers, notes and custom answers are not exported. Retries skip unchanged events with the correct attendee to avoid repeated invitation notifications, and preserve existing RSVP responses. Ready Meet links appear in plugin booking emails, the confirmation screen, private booking management and the appointment detail. Use `{google_meet_link}` to position the link within an email template. If Google generates the link asynchronously, it appears on the private management page after the scheduled retry; the original confirmation email is not resent. The API connection test remains attendee-free.

Booking service editors include configurable placeholders for built-in and custom customer fields. Existing fields fall back to sensible examples until saved. The frontend uses placeholders for text and textarea fields, and as the initial prompt for dropdowns. Review shows separate appointment and customer cards with edit actions. Confirmation provides calendar status, Meet and management actions, and a private-link copy control. Management refreshes current server state after cancellation and rescheduling. Run `node tests/booking-ui.js` for the mocked frontend flow. `tests/booking-preview.html` is an optional local-only visual fixture; all its booking API calls are mocked.

Rescheduling patches the same event and retains its Meet conference. Cancellation deletes the event and removes the locally displayed Meet link. Deleting a Calendar event does not guarantee immediate invalidation of a previously shared Meet URL; Google's conference expiry rules apply. WordPress owns availability; busy events created in Google Calendar are not imported.

Each appointment pins its real calendar ID and remote event ID before requests. Retries reuse event IDs after timeouts and read the latest appointment state. Errors appear in appointment details with a retry button. A working WP-Cron is required for background retries. A booking remains valid when Google is temporarily unavailable. Disconnecting keeps remote events; reconnect the same account to finish pending changes. Appointment deletion waits for remote cancellation to succeed; retention preserves rows with unresolved sync errors. Retention of old successful appointments does not delete historical Google events. Uninstalling does not delete remote Google events.

Run mocked API regression checks with `php tests/google-calendar.php` (OpenSSL enabled). No live Google credentials are used by these checks.

Settings actions use authenticated, nonce-protected AJAX and show results inline. Google authorization opens in a popup so the Settings page stays open. The green/red connection badge and action buttons update after configuration changes, authorization and disconnect. Connect is disabled while connected; Test and Disconnect are disabled while disconnected. Run `node tests/settings.js` for the Settings UI regressions.

Settings are organized into Booking (including Google Calendar), Notifications, Appearance, Analytics, Security, Privacy & data, and Advanced tabs. The sticky Save settings button saves configuration across all tabs; Google configuration retains its own save and connection actions. Run `php tests/settings-render.php` to verify panel grouping and form ownership.

Each Settings card has a question-mark guide icon. Short field labels keep configuration compact; the guides contain field explanations, privacy details and the permissions reference. All guides support keyboard navigation, Escape, outside-click closing and focus return. Cleanup retains a visible deletion warning.

Notifications includes an optional Email CC field for admin booking and assessment notices, including admin cancellation and reschedule notices. Use comma-separated email addresses; leave it blank to disable. Duplicate recipients are omitted. Customer emails do not use this setting. Run `php tests/mailer.php` for CC checks without sending real email. Google API test timestamps follow the site's WordPress date/time format and timezone, and booking editors show the Google connection state with a Settings shortcut.
- Filter: `ncs_capability_slugs`.

## Design rules enforced in code

Public pages use the Next Class palette (#ff954f, #393939, #ffffff, #f1efe7, #ffe972). Admin screens use black (#000000) and white (#ffffff) only. Only solid fills are used. The review screen uses decorative inline SVG icons alongside visible text labels. There are no emoji or em dashes anywhere in the plugin. Poppins is loaded only on Next Class Services admin screens (from `assets/fonts`, no third party request) and on the public site only if enabled in Settings.
