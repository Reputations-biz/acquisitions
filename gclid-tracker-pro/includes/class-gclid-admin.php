<?php
/**
 * Admin interface for GCLID Tracker Pro
 *
 * Registers admin menu pages, settings fields, and
 * handles all admin-side AJAX operations including
 * test connections, manual sync, and bulk actions.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Admin {

    /**
     * Database instance
     *
     * @var GCLID_DB
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new GCLID_DB();
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Admin AJAX handlers
        add_action( 'wp_ajax_gclid_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_gclid_manual_sync', array( $this, 'ajax_manual_sync' ) );
        add_action( 'wp_ajax_gclid_delete_capture', array( $this, 'ajax_delete_capture' ) );
        add_action( 'wp_ajax_gclid_bulk_action', array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_gclid_get_stats', array( $this, 'ajax_get_stats' ) );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . GCLID_TRACKER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing links
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=gclid-tracker-settings' ) . '">' . __( 'Settings', 'gclid-tracker-pro' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Register admin menu pages
     */
    public function add_admin_menu() {
        // Main menu - Dashboard
        add_menu_page(
            __( 'GCLID Tracker', 'gclid-tracker-pro' ),
            __( 'GCLID Tracker', 'gclid-tracker-pro' ),
            'manage_options',
            'gclid-tracker',
            array( $this, 'render_dashboard_page' ),
            'dashicons-analytics',
            30
        );

        // Submenu - Dashboard
        add_submenu_page(
            'gclid-tracker',
            __( 'Dashboard', 'gclid-tracker-pro' ),
            __( 'Dashboard', 'gclid-tracker-pro' ),
            'manage_options',
            'gclid-tracker',
            array( $this, 'render_dashboard_page' )
        );

        // Submenu - Capture Log
        add_submenu_page(
            'gclid-tracker',
            __( 'Capture Log', 'gclid-tracker-pro' ),
            __( 'Capture Log', 'gclid-tracker-pro' ),
            'manage_options',
            'gclid-tracker-logs',
            array( $this, 'render_logs_page' )
        );

        // Submenu - Settings
        add_submenu_page(
            'gclid-tracker',
            __( 'Settings', 'gclid-tracker-pro' ),
            __( 'Settings', 'gclid-tracker-pro' ),
            'manage_options',
            'gclid-tracker-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Google Sheets settings
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_spreadsheet_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_sheet_name', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_sync_enabled', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );

        // Capture settings
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_capture_utms', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_capture_fbclid', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_capture_msclkid', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_capture_all_visits', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_enable_ip_logging', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );
        register_setting( 'gclid_tracker_settings', 'gclid_tracker_data_retention_days', array(
            'sanitize_callback' => 'absint',
        ) );

        // Handle credentials file upload separately
        if ( isset( $_POST['gclid_tracker_credentials_json'] ) && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'gclid_tracker_settings-options' );
            $json = sanitize_textarea_field( wp_unslash( $_POST['gclid_tracker_credentials_json'] ) );
            if ( ! empty( $json ) ) {
                $decoded = json_decode( $json, true );
                if ( $decoded && isset( $decoded['client_email'] ) && isset( $decoded['private_key'] ) ) {
                    update_option( 'gclid_tracker_google_credentials', $json );
                } else {
                    add_settings_error(
                        'gclid_tracker_settings',
                        'invalid_credentials',
                        __( 'Invalid service account JSON. Please check the file contents.', 'gclid-tracker-pro' ),
                        'error'
                    );
                }
            }
        }

        // Handle clear credentials
        if ( isset( $_POST['gclid_tracker_clear_credentials'] ) && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'gclid_tracker_settings-options' );
            delete_option( 'gclid_tracker_google_credentials' );
            delete_transient( 'gclid_tracker_access_token' );
        }
    }

    /**
     * Sanitize checkbox value
     *
     * @param mixed $value Input value
     * @return string '1' or '0'
     */
    public function sanitize_checkbox( $value ) {
        return $value ? '1' : '0';
    }

    /**
     * Enqueue admin CSS and JS
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our plugin pages
        $plugin_pages = array(
            'toplevel_page_gclid-tracker',
            'gclid-tracker_page_gclid-tracker-logs',
            'gclid-tracker_page_gclid-tracker-settings',
        );

        if ( ! in_array( $hook, $plugin_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'gclid-admin-style',
            GCLID_TRACKER_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            GCLID_TRACKER_VERSION
        );

        wp_enqueue_script(
            'gclid-admin-script',
            GCLID_TRACKER_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery' ),
            GCLID_TRACKER_VERSION,
            true
        );

        // Chart.js for dashboard
        if ( $hook === 'toplevel_page_gclid-tracker' ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );
        }

        wp_localize_script( 'gclid-admin-script', 'gclidAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gclid_admin_nonce' ),
        ) );
    }

    /**
     * Render the Dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->db->get_stats();
        include GCLID_TRACKER_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    /**
     * Render the Capture Log page
     */
    public function render_logs_page() {
        $per_page = 20;
        $page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $orderby  = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'captured_at';
        $order    = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        $captures    = $this->db->get_captures( $per_page, $page, $orderby, $order, $search );
        $total_items = $this->db->get_total_count( $search );
        $total_pages = ceil( $total_items / $per_page );

        include GCLID_TRACKER_PLUGIN_DIR . 'templates/admin-logs.php';
    }

    /**
     * Render the Settings page
     */
    public function render_settings_page() {
        $credentials = get_option( 'gclid_tracker_google_credentials', '' );
        $has_credentials = false;
        $service_email   = '';

        if ( ! empty( $credentials ) ) {
            $decoded = json_decode( $credentials, true );
            if ( $decoded && isset( $decoded['client_email'] ) ) {
                $has_credentials = true;
                $service_email   = $decoded['client_email'];
            }
        }

        include GCLID_TRACKER_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * AJAX: Test Google Sheets connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'gclid_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $sheets = new GCLID_Sheets();
        $result = $sheets->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Manual sync to Google Sheets
     */
    public function ajax_manual_sync() {
        check_ajax_referer( 'gclid_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $sheets = new GCLID_Sheets();
        $result = $sheets->sync_pending();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Delete a single capture
     */
    public function ajax_delete_capture() {
        check_ajax_referer( 'gclid_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $id = isset( $_POST['capture_id'] ) ? intval( $_POST['capture_id'] ) : 0;
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid capture ID.' ) );
        }

        $result = $this->db->delete_capture( $id );
        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Capture deleted.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete capture.' ) );
        }
    }

    /**
     * AJAX: Bulk action on captures
     */
    public function ajax_bulk_action() {
        check_ajax_referer( 'gclid_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        $ids    = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();

        if ( empty( $ids ) ) {
            wp_send_json_error( array( 'message' => 'No items selected.' ) );
        }

        switch ( $action ) {
            case 'delete':
                $result = $this->db->bulk_delete( $ids );
                wp_send_json_success( array( 'message' => $result . ' record(s) deleted.' ) );
                break;

            case 'sync':
                $sheets  = new GCLID_Sheets();
                $db      = new GCLID_DB();
                $rows    = array();
                $sync_ids = array();

                foreach ( $ids as $id ) {
                    $capture = $db->get_capture( $id );
                    if ( $capture && ! $capture['synced_to_sheets'] ) {
                        $rows[]     = $sheets->capture_to_row( $capture );
                        $sync_ids[] = $id;
                    }
                }

                if ( ! empty( $rows ) ) {
                    $sheets->ensure_headers();
                    $result = $sheets->append_rows( $rows );
                    if ( $result['success'] ) {
                        $db->mark_synced( $sync_ids );
                        wp_send_json_success( array( 'message' => count( $sync_ids ) . ' record(s) synced.' ) );
                    } else {
                        wp_send_json_error( array( 'message' => 'Sync failed: ' . $result['message'] ) );
                    }
                } else {
                    wp_send_json_success( array( 'message' => 'No unsynced records in selection.' ) );
                }
                break;

            default:
                wp_send_json_error( array( 'message' => 'Invalid action.' ) );
        }
    }

    /**
     * AJAX: Get dashboard stats
     */
    public function ajax_get_stats() {
        check_ajax_referer( 'gclid_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $stats = $this->db->get_stats();
        wp_send_json_success( $stats );
    }
}
