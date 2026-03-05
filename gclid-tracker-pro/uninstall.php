<?php
/**
 * Uninstall handler for GCLID Tracker Pro
 *
 * Removes all plugin data from the database when
 * the plugin is deleted through the WordPress admin.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove options
$options = array(
    'gclid_tracker_google_credentials',
    'gclid_tracker_spreadsheet_id',
    'gclid_tracker_sheet_name',
    'gclid_tracker_capture_utms',
    'gclid_tracker_data_retention_days',
    'gclid_tracker_enable_ip_logging',
    'gclid_tracker_sync_enabled',
    'gclid_tracker_capture_all_visits',
    'gclid_tracker_capture_fbclid',
    'gclid_tracker_capture_msclkid',
    'gclid_tracker_db_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
delete_transient( 'gclid_tracker_access_token' );

// Drop custom table
global $wpdb;
$table_name = $wpdb->prefix . 'gclid_captures';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Clear scheduled cron
wp_clear_scheduled_hook( 'gclid_tracker_daily_cleanup' );
