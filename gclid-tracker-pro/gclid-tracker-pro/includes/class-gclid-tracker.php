<?php
/**
 * Main plugin class for GCLID Tracker Pro
 *
 * Bootstraps all plugin components and registers
 * the daily cleanup cron job for data retention.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Tracker {

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize capture handler (frontend + AJAX)
        $capture = new GCLID_Capture();
        $capture->init();

        // Initialize export handler
        $export = new GCLID_Export();
        $export->init();

        // Initialize admin (only in admin context)
        if ( is_admin() ) {
            $admin = new GCLID_Admin();
            $admin->init();
        }

        // Register cron handler
        add_action( 'gclid_tracker_daily_cleanup', array( $this, 'daily_cleanup' ) );

        // Check for DB updates
        $this->maybe_update_db();
    }

    /**
     * Check if database needs updating
     */
    private function maybe_update_db() {
        $installed_version = get_option( 'gclid_tracker_db_version', '0' );
        if ( version_compare( $installed_version, GCLID_TRACKER_DB_VERSION, '<' ) ) {
            $db = new GCLID_DB();
            $db->create_table();
        }
    }

    /**
     * Daily cleanup cron job
     *
     * Removes old records based on retention settings
     * and attempts to sync any pending records.
     */
    public function daily_cleanup() {
        $retention_days = (int) get_option( 'gclid_tracker_data_retention_days', 90 );

        if ( $retention_days > 0 ) {
            $db = new GCLID_DB();
            $deleted = $db->cleanup_old_records( $retention_days );

            if ( $deleted > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[GCLID Tracker Pro] Daily cleanup: Deleted ' . $deleted . ' records older than ' . $retention_days . ' days.' );
            }
        }

        // Also try to sync any pending records
        $sheets = new GCLID_Sheets();
        if ( $sheets->is_configured() ) {
            $sheets->sync_pending();
        }
    }
}
