<?php
/**
 * Database operations for GCLID Tracker Pro
 *
 * Handles all database table creation, CRUD operations,
 * and data retention cleanup for captured click IDs.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_DB {

    /**
     * Database table name (without prefix)
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gclid_captures';
    }

    /**
     * Get the full table name
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Create the captures table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            gclid VARCHAR(255) DEFAULT '',
            fbclid VARCHAR(255) DEFAULT '',
            msclkid VARCHAR(255) DEFAULT '',
            landing_page TEXT NOT NULL,
            referrer TEXT DEFAULT '',
            utm_source VARCHAR(255) DEFAULT '',
            utm_medium VARCHAR(255) DEFAULT '',
            utm_campaign VARCHAR(255) DEFAULT '',
            utm_term VARCHAR(255) DEFAULT '',
            utm_content VARCHAR(255) DEFAULT '',
            ip_address VARCHAR(45) DEFAULT '',
            user_agent TEXT DEFAULT '',
            country VARCHAR(100) DEFAULT '',
            city VARCHAR(100) DEFAULT '',
            device_type VARCHAR(50) DEFAULT '',
            browser VARCHAR(100) DEFAULT '',
            os VARCHAR(100) DEFAULT '',
            captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            synced_to_sheets TINYINT(1) NOT NULL DEFAULT 0,
            synced_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_gclid (gclid),
            KEY idx_captured_at (captured_at),
            KEY idx_synced (synced_to_sheets)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'gclid_tracker_db_version', GCLID_TRACKER_DB_VERSION );
    }

    /**
     * Insert a new capture record
     *
     * @param array $data Capture data
     * @return int|false Insert ID or false on failure
     */
    public function insert_capture( $data ) {
        global $wpdb;

        $defaults = array(
            'gclid'          => '',
            'fbclid'         => '',
            'msclkid'        => '',
            'landing_page'   => '',
            'referrer'       => '',
            'utm_source'     => '',
            'utm_medium'     => '',
            'utm_campaign'   => '',
            'utm_term'       => '',
            'utm_content'    => '',
            'ip_address'     => '',
            'user_agent'     => '',
            'country'        => '',
            'city'           => '',
            'device_type'    => '',
            'browser'        => '',
            'os'             => '',
            'captured_at'    => current_time( 'mysql' ),
            'synced_to_sheets' => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%d',
            )
        );

        if ( $result === false ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get captures with pagination
     *
     * @param int    $per_page Items per page
     * @param int    $page     Current page
     * @param string $orderby  Column to order by
     * @param string $order    ASC or DESC
     * @param string $search   Search term
     * @return array
     */
    public function get_captures( $per_page = 20, $page = 1, $orderby = 'captured_at', $order = 'DESC', $search = '' ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;

        // Whitelist orderby columns
        $allowed_orderby = array( 'id', 'gclid', 'landing_page', 'utm_source', 'utm_medium', 'utm_campaign', 'captured_at', 'synced_to_sheets', 'ip_address' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'captured_at';
        }

        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        $where = '';
        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where = $wpdb->prepare(
                " WHERE gclid LIKE %s OR landing_page LIKE %s OR utm_source LIKE %s OR utm_campaign LIKE %s OR ip_address LIKE %s",
                $like, $like, $like, $like, $like
            );
        }

        $sql = "SELECT * FROM {$this->table_name}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ), ARRAY_A );

        return $results ? $results : array();
    }

    /**
     * Get total capture count
     *
     * @param string $search Search term
     * @return int
     */
    public function get_total_count( $search = '' ) {
        global $wpdb;

        $where = '';
        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where = $wpdb->prepare(
                " WHERE gclid LIKE %s OR landing_page LIKE %s OR utm_source LIKE %s OR utm_campaign LIKE %s OR ip_address LIKE %s",
                $like, $like, $like, $like, $like
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}{$where}" );
    }

    /**
     * Get unsynced captures
     *
     * @param int $limit Maximum records to return
     * @return array
     */
    public function get_unsynced( $limit = 50 ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE synced_to_sheets = 0 ORDER BY captured_at ASC LIMIT %d",
            $limit
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );
        return $results ? $results : array();
    }

    /**
     * Mark records as synced
     *
     * @param array $ids Record IDs to mark
     * @return int|false Number of rows updated or false
     */
    public function mark_synced( $ids ) {
        global $wpdb;

        if ( empty( $ids ) ) {
            return 0;
        }

        $ids = array_map( 'intval', $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET synced_to_sheets = 1, synced_at = %s WHERE id IN ({$placeholders})",
                array_merge( array( current_time( 'mysql' ) ), $ids )
            )
        );
    }

    /**
     * Delete old records based on retention period
     *
     * @param int $days Number of days to retain
     * @return int Number of rows deleted
     */
    public function cleanup_old_records( $days ) {
        global $wpdb;

        if ( $days <= 0 ) {
            return 0;
        }

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE captured_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $stats = array();

        // Total captures
        $stats['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        // Today's captures
        $stats['today'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(captured_at) = %s",
                current_time( 'Y-m-d' )
            )
        );

        // This week
        $stats['this_week'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE captured_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // This month
        $stats['this_month'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE captured_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // With GCLID
        $stats['with_gclid'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE gclid != '' AND gclid IS NOT NULL"
        );

        // Synced count
        $stats['synced'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE synced_to_sheets = 1"
        );

        // Unsynced count
        $stats['unsynced'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE synced_to_sheets = 0"
        );

        // Top UTM sources (last 30 days)
        $stats['top_sources'] = $wpdb->get_results(
            "SELECT utm_source, COUNT(*) as count FROM {$this->table_name} 
             WHERE utm_source != '' AND captured_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY utm_source ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Top campaigns (last 30 days)
        $stats['top_campaigns'] = $wpdb->get_results(
            "SELECT utm_campaign, COUNT(*) as count FROM {$this->table_name} 
             WHERE utm_campaign != '' AND captured_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY utm_campaign ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Daily captures (last 30 days)
        $stats['daily_captures'] = $wpdb->get_results(
            "SELECT DATE(captured_at) as date, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE captured_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY DATE(captured_at) ORDER BY date ASC",
            ARRAY_A
        );

        // Top landing pages
        $stats['top_landing_pages'] = $wpdb->get_results(
            "SELECT landing_page, COUNT(*) as count FROM {$this->table_name} 
             WHERE captured_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY landing_page ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Get a single capture by ID
     *
     * @param int $id Record ID
     * @return array|null
     */
    public function get_capture( $id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Delete a capture by ID
     *
     * @param int $id Record ID
     * @return int|false
     */
    public function delete_capture( $id ) {
        global $wpdb;
        return $wpdb->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Bulk delete captures
     *
     * @param array $ids Record IDs
     * @return int|false
     */
    public function bulk_delete( $ids ) {
        global $wpdb;

        if ( empty( $ids ) ) {
            return 0;
        }

        $ids = array_map( 'intval', $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
                $ids
            )
        );
    }

    /**
     * Get all captures for export
     *
     * @param string $date_from Start date
     * @param string $date_to   End date
     * @return array
     */
    public function get_captures_for_export( $date_from = '', $date_to = '' ) {
        global $wpdb;

        $where = '1=1';
        $params = array();

        if ( ! empty( $date_from ) ) {
            $where .= ' AND captured_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ( ! empty( $date_to ) ) {
            $where .= ' AND captured_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY captured_at DESC";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Drop the table (used on uninstall)
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
    }
}
