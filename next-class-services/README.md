# Next Class Services

WordPress plugin with two services for Next Class: an HR Diagnostic Assessment Tool and an Online Booking System.

- Version: 1.0.0
- Requires WordPress 6.0 or newer, PHP 7.4 or newer
- Tested up to WordPress 6.8
- Text domain: `next-class-services`, PHP prefix `ncs_`, namespace `NextClassServices`
- No CRM integration, no Google Analytics installation, no external calendar sync

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

- Actions: `ncs_appointment_created`, `ncs_appointment_updated`, `ncs_appointment_cancelled` (use these for a future calendar sync).
- Filter: `ncs_capability_slugs`.

## Design rules enforced in code

Public pages use the Next Class palette (#ff954f, #393939, #ffffff, #f1efe7, #ffe972). Admin screens use black (#000000) and white (#ffffff) only. Only solid fills are used. There are no icons, no emoji and no em dashes anywhere in the plugin. Poppins is loaded only on Next Class Services admin screens (from `assets/fonts`, no third party request) and on the public site only if enabled in Settings.
