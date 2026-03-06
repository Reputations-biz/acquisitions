<?php
/**
 * Plugin Name: GCLID Tracker Pro
 * Plugin URI: https://github.com/bdouglas73/acquisitions
 * Description: Captures Google Click ID (GCLID) and UTM parameters from all website visitors and syncs the data to a Google Spreadsheet in real-time. Connect your Google account with one click — no service accounts or JSON files required. Includes a full admin dashboard, local logging, CSV export, and data retention management.
 * Version: 1.2.3
 * Author: Brian Douglas
 * Author URI: https://github.com/bdouglas73
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gclid-tracker-pro
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'GCLID_TRACKER_VERSION', '1.2.3' );
define( 'GCLID_TRACKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCLID_TRACKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GCLID_TRACKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GCLID_TRACKER_DB_VERSION', '1.0.0' );

// Include required files
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-db.php';
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-sheets.php';
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-capture.php';
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-admin.php';
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-export.php';
require_once GCLID_TRACKER_PLUGIN_DIR . 'includes/class-gclid-tracker.php';

/**
 * Plugin activation hook
 */
function gclid_tracker_activate() {
    $db = new GCLID_DB();
    $db->create_table();

    // Set default options (only if not already set)
    $defaults = array(
        'gclid_tracker_capture_utms'          => '1',
        'gclid_tracker_data_retention_days'   => '90',
        'gclid_tracker_enable_ip_logging'     => '1',
        'gclid_tracker_sync_enabled'          => '0',
        'gclid_tracker_spreadsheet_id'        => '',
        'gclid_tracker_sheet_name'            => 'Sheet1',
        'gclid_tracker_oauth_client_id'       => '',
        'gclid_tracker_oauth_client_secret'   => '',
        'gclid_tracker_capture_all_visits'    => '0',
        'gclid_tracker_capture_fbclid'        => '1',
        'gclid_tracker_capture_msclkid'       => '1',
    );

    foreach ( $defaults as $key => $value ) {
        if ( get_option( $key ) === false ) {
            add_option( $key, $value );
        }
    }

    // Schedule daily cleanup cron
    if ( ! wp_next_scheduled( 'gclid_tracker_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'gclid_tracker_daily_cleanup' );
    }
}
register_activation_hook( __FILE__, 'gclid_tracker_activate' );

/**
 * Plugin deactivation hook
 */
function gclid_tracker_deactivate() {
    wp_clear_scheduled_hook( 'gclid_tracker_daily_cleanup' );
}
register_deactivation_hook( __FILE__, 'gclid_tracker_deactivate' );

/**
 * Initialize the plugin
 */
function gclid_tracker_init() {
    $plugin = new GCLID_Tracker();
    $plugin->init();
}
add_action( 'plugins_loaded', 'gclid_tracker_init' );
