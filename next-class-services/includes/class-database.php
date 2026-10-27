<?php
/**
 * Database schema and migrations.
 *
 * @package NextClassServices
 */

namespace NextClassServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the custom tables.
 *
 * Assessment definitions and booking service configurations live in private
 * custom post types (see Post_Types) because they are low volume, need native
 * status handling and are easy to duplicate. High volume operational data lives
 * in dedicated tables with indexes.
 */
class Database {

	const OPTION_DB_VERSION = 'ncs_db_version';

	/**
	 * Table name helper.
	 *
	 * @param string $name submissions|answers|appointments|events.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ncs_' . $name;
	}

	/**
	 * Run install or upgrade only when the stored version differs.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::OPTION_DB_VERSION, '' );
		if ( NCS_DB_VERSION === $installed ) {
			return;
		}
		self::install();
	}

	/**
	 * Create or update tables and run migrations.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sub     = self::table( 'submissions' );
		$ans     = self::table( 'answers' );
		$apt     = self::table( 'appointments' );
		$evt     = self::table( 'events' );

		$sql   = array();
		$sql[] = "CREATE TABLE {$sub} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			assessment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			assessment_title varchar(255) NOT NULL DEFAULT '',
			first_name varchar(120) NOT NULL DEFAULT '',
			last_name varchar(120) NOT NULL DEFAULT '',
			full_name varchar(240) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(60) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			job_title varchar(190) NOT NULL DEFAULT '',
			company_size varchar(60) NOT NULL DEFAULT '',
			custom_fields longtext NULL,
			raw_score decimal(10,2) NOT NULL DEFAULT 0,
			max_score decimal(10,2) NOT NULL DEFAULT 0,
			percentage decimal(5,2) NOT NULL DEFAULT 0,
			result_id varchar(40) NOT NULL DEFAULT '',
			result_title varchar(255) NOT NULL DEFAULT '',
			recommended_programs text NULL,
			consent_privacy tinyint(1) NOT NULL DEFAULT 0,
			consent_marketing tinyint(1) NOT NULL DEFAULT 0,
			consent_snapshot text NULL,
			consent_at datetime NULL,
			page_url varchar(500) NOT NULL DEFAULT '',
			email_sent tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY assessment_created (assessment_id,created_at),
			KEY email (email),
			KEY result_id (result_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$ans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL,
			question_id varchar(40) NOT NULL DEFAULT '',
			question_text text NULL,
			question_type varchar(30) NOT NULL DEFAULT '',
			answer_value text NULL,
			answer_label text NULL,
			score decimal(10,2) NOT NULL DEFAULT 0,
			max_score decimal(10,2) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$apt} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL DEFAULT 0,
			service_title varchar(255) NOT NULL DEFAULT '',
			token_hash char(64) NOT NULL DEFAULT '',
			customer_name varchar(190) NOT NULL DEFAULT '',
			customer_email varchar(190) NOT NULL DEFAULT '',
			customer_phone varchar(60) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			job_title varchar(190) NOT NULL DEFAULT '',
			notes text NULL,
			custom_fields longtext NULL,
			start_utc datetime NOT NULL,
			end_utc datetime NOT NULL,
			block_start_utc datetime NOT NULL,
			block_end_utc datetime NOT NULL,
			local_date date NOT NULL,
			timezone varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			status_changed_at datetime NULL,
			active_slot varchar(64) NULL DEFAULT NULL,
			reschedule_count smallint(5) unsigned NOT NULL DEFAULT 0,
			google_sync_enabled tinyint(1) NOT NULL DEFAULT 0,
			google_calendar_id varchar(255) NOT NULL DEFAULT '',
			google_event_id varchar(128) NOT NULL DEFAULT '',
			google_meet_url varchar(500) NOT NULL DEFAULT '',
			google_sync_error text NULL,
			google_sync_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			google_calendar_meta text NULL,
			google_admin_invite_signature char(64) NOT NULL DEFAULT '',
			page_url varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			UNIQUE KEY active_slot (active_slot),
			KEY service_start (service_id,start_utc),
			KEY start_utc (start_utc),
			KEY block_range (block_start_utc,block_end_utc),
			KEY status_start (status,start_utc),
			KEY local_date (service_id,local_date),
			KEY customer_email (customer_email)
		) {$charset};";

		$sql[] = "CREATE TABLE {$evt} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(40) NOT NULL,
			object_type varchar(20) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type_created (event_type,created_at),
			KEY object_lookup (object_type,object_id,event_type),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::run_migrations( (string) get_option( self::OPTION_DB_VERSION, '' ) );

		update_option( self::OPTION_DB_VERSION, NCS_DB_VERSION, true );
	}

	/**
	 * Version keyed migrations. Add a new block for each schema change.
	 *
	 * @param string $from Previously installed version, empty on fresh install.
	 */
	private static function run_migrations( $from ) {
		if ( '' === $from ) {
			return;
		}
		// Example for later releases:
		// if ( version_compare( $from, '1.1.0', '<' ) ) { ... }.
	}

	/**
	 * Drop all custom tables (uninstall only).
	 */
	public static function drop_tables() {
		global $wpdb;
		foreach ( array( 'submissions', 'answers', 'appointments', 'events' ) as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::OPTION_DB_VERSION );
	}
}
