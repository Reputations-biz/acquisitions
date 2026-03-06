<?php
/**
 * CSV Export handler for GCLID Tracker Pro
 *
 * Generates and streams CSV downloads of captured data
 * with optional date range filtering.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Export {

    /**
     * Initialize hooks
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'handle_export' ) );
    }

    /**
     * Handle CSV export request
     */
    public function handle_export() {
        if ( ! isset( $_GET['gclid_export'] ) || $_GET['gclid_export'] !== '1' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized access.' );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'gclid_export_csv' ) ) {
            wp_die( 'Invalid security token.' );
        }

        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

        $db       = new GCLID_DB();
        $captures = $db->get_captures_for_export( $date_from, $date_to );

        $filename = 'gclid-captures-' . gmdate( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Excel compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // Header row
        fputcsv( $output, array(
            'ID',
            'GCLID',
            'FBCLID',
            'MSCLKID',
            'Landing Page',
            'Referrer',
            'UTM Source',
            'UTM Medium',
            'UTM Campaign',
            'UTM Term',
            'UTM Content',
            'IP Address',
            'User Agent',
            'Country',
            'City',
            'Device Type',
            'Browser',
            'OS',
            'Captured At',
            'Synced to Sheets',
            'Synced At',
        ) );

        // Data rows
        foreach ( $captures as $row ) {
            fputcsv( $output, array(
                $row['id'],
                $row['gclid'],
                $row['fbclid'],
                $row['msclkid'],
                $row['landing_page'],
                $row['referrer'],
                $row['utm_source'],
                $row['utm_medium'],
                $row['utm_campaign'],
                $row['utm_term'],
                $row['utm_content'],
                $row['ip_address'],
                $row['user_agent'],
                $row['country'],
                $row['city'],
                $row['device_type'],
                $row['browser'],
                $row['os'],
                $row['captured_at'],
                $row['synced_to_sheets'] ? 'Yes' : 'No',
                $row['synced_at'],
            ) );
        }

        fclose( $output );
        exit;
    }
}
