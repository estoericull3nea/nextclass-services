<?php
/**
 * Uninstall handler.
 *
 * Data is preserved unless the administrator enabled "Delete all Next Class
 * Services data when the plugin is uninstalled" in Settings.
 *
 * @package NextClassServices
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ncs_settings = get_option( 'ncs_settings', array() );

if ( empty( $ncs_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove definitions (assessments and booking services) and their meta.
$ncs_ids = get_posts(
	array(
		'post_type'      => array( 'ncs_assessment', 'ncs_service' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
foreach ( $ncs_ids as $ncs_id ) {
	wp_delete_post( $ncs_id, true );
}

// Drop custom tables.
foreach ( array( 'submissions', 'answers', 'appointments', 'events' ) as $ncs_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ncs_{$ncs_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove options and transients created by the plugin.
foreach ( array( 'ncs_settings', 'ncs_db_version', 'ncs_caps_version', 'ncs_installed_at' ) as $ncs_option ) {
	delete_option( $ncs_option );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ncs\_%' OR option_name LIKE '\_transient\_timeout\_ncs\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Scheduled tasks.
wp_clear_scheduled_hook( 'ncs_daily_cleanup' );

// Capabilities.
$ncs_caps = array( 'ncs_manage_assessments', 'ncs_manage_submissions', 'ncs_manage_booking_services', 'ncs_manage_appointments', 'ncs_view_analytics', 'ncs_manage_settings' );
foreach ( wp_roles()->role_objects as $ncs_role ) {
	foreach ( $ncs_caps as $ncs_cap ) {
		$ncs_role->remove_cap( $ncs_cap );
	}
}
