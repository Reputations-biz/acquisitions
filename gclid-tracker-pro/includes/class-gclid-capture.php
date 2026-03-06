<?php
/**
 * GCLID Capture handler for GCLID Tracker Pro
 *
 * Processes incoming AJAX requests from the frontend
 * JavaScript, validates data, parses user agent info,
 * stores to database, and triggers Google Sheets sync.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Capture {

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
     * Initialize hooks
     */
    public function init() {
        // AJAX handlers (both logged-in and non-logged-in users)
        add_action( 'wp_ajax_gclid_capture', array( $this, 'handle_capture' ) );
        add_action( 'wp_ajax_nopriv_gclid_capture', array( $this, 'handle_capture' ) );

        // Enqueue frontend script
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue the frontend capture script
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'gclid-capture',
            GCLID_TRACKER_PLUGIN_URL . 'public/js/gclid-capture.js',
            array(),
            GCLID_TRACKER_VERSION,
            true
        );

        $capture_all = get_option( 'gclid_tracker_capture_all_visits', '0' );

        wp_localize_script( 'gclid-capture', 'gclidTrackerConfig', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'gclid_capture_nonce' ),
            'captureUtms'   => get_option( 'gclid_tracker_capture_utms', '1' ) === '1',
            'captureFbclid' => get_option( 'gclid_tracker_capture_fbclid', '1' ) === '1',
            'captureMsclkid'=> get_option( 'gclid_tracker_capture_msclkid', '1' ) === '1',
            'captureAll'    => $capture_all === '1',
        ) );
    }

    /**
     * Handle the AJAX capture request
     */
    public function handle_capture() {
        // Verify nonce
        if ( ! check_ajax_referer( 'gclid_capture_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ), 403 );
        }

        // Sanitize input
        $gclid        = isset( $_POST['gclid'] ) ? sanitize_text_field( wp_unslash( $_POST['gclid'] ) ) : '';
        $fbclid       = isset( $_POST['fbclid'] ) ? sanitize_text_field( wp_unslash( $_POST['fbclid'] ) ) : '';
        $msclkid      = isset( $_POST['msclkid'] ) ? sanitize_text_field( wp_unslash( $_POST['msclkid'] ) ) : '';
        $landing_page = isset( $_POST['landing_page'] ) ? esc_url_raw( wp_unslash( $_POST['landing_page'] ) ) : '';
        $referrer     = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
        $utm_source   = isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '';
        $utm_medium   = isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '';
        $utm_campaign = isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '';
        $utm_term     = isset( $_POST['utm_term'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_term'] ) ) : '';
        $utm_content  = isset( $_POST['utm_content'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_content'] ) ) : '';

        // Must have at least a landing page
        if ( empty( $landing_page ) ) {
            wp_send_json_error( array( 'message' => 'Missing landing page.' ), 400 );
        }

        // Check if we should capture all visits or only those with click IDs/UTMs
        $capture_all = get_option( 'gclid_tracker_capture_all_visits', '0' );
        if ( $capture_all !== '1' ) {
            // Only capture if there's a GCLID, FBCLID, MSCLKID, or UTM parameter
            $has_tracking = ! empty( $gclid ) || ! empty( $fbclid ) || ! empty( $msclkid )
                || ! empty( $utm_source ) || ! empty( $utm_medium ) || ! empty( $utm_campaign );
            if ( ! $has_tracking ) {
                wp_send_json_error( array( 'message' => 'No tracking parameters found.' ), 200 );
            }
        }

        // Get IP address
        $ip_address = '';
        if ( get_option( 'gclid_tracker_enable_ip_logging', '1' ) === '1' ) {
            $ip_address = $this->get_client_ip();
        }

        // Parse user agent
        $user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $ua_info     = $this->parse_user_agent( $user_agent );

        // Build capture data
        $data = array(
            'gclid'        => $gclid,
            'fbclid'       => $fbclid,
            'msclkid'      => $msclkid,
            'landing_page' => $landing_page,
            'referrer'     => $referrer,
            'utm_source'   => $utm_source,
            'utm_medium'   => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_term'     => $utm_term,
            'utm_content'  => $utm_content,
            'ip_address'   => $ip_address,
            'user_agent'   => $user_agent,
            'device_type'  => $ua_info['device_type'],
            'browser'      => $ua_info['browser'],
            'os'           => $ua_info['os'],
            'captured_at'  => current_time( 'mysql', true ), // Store as UTC; converted to PT on output
        );

        // Insert into database
        $insert_id = $this->db->insert_capture( $data );

        if ( $insert_id === false ) {
            wp_send_json_error( array( 'message' => 'Failed to save capture.' ), 500 );
        }

        // Attempt immediate sync to Google Sheets
        $sheets = new GCLID_Sheets();
        $sync_result = null;
        if ( $sheets->is_configured() ) {
            $data['id'] = $insert_id;
            $row = $sheets->capture_to_row( $data );
            $sheets->ensure_headers();
            $result = $sheets->append_rows( array( $row ) );

            if ( $result['success'] ) {
                $this->db->mark_synced( array( $insert_id ) );
                $sync_result = true;
            } else {
                $sync_result = false;
            }
        }

        wp_send_json_success( array(
            'message'    => 'Capture saved successfully.',
            'capture_id' => $insert_id,
            'synced'     => $sync_result,
        ) );
    }

    /**
     * Get the client's IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Parse user agent string to extract device, browser, and OS info
     *
     * @param string $user_agent User agent string
     * @return array
     */
    private function parse_user_agent( $user_agent ) {
        $result = array(
            'device_type' => 'Desktop',
            'browser'     => 'Unknown',
            'os'          => 'Unknown',
        );

        if ( empty( $user_agent ) ) {
            return $result;
        }

        // Detect device type
        $mobile_keywords = array( 'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'webOS', 'BlackBerry', 'Opera Mini', 'IEMobile' );
        $tablet_keywords = array( 'iPad', 'Tablet', 'Kindle', 'Silk', 'PlayBook' );

        foreach ( $tablet_keywords as $keyword ) {
            if ( stripos( $user_agent, $keyword ) !== false ) {
                $result['device_type'] = 'Tablet';
                break;
            }
        }

        if ( $result['device_type'] === 'Desktop' ) {
            foreach ( $mobile_keywords as $keyword ) {
                if ( stripos( $user_agent, $keyword ) !== false ) {
                    $result['device_type'] = 'Mobile';
                    break;
                }
            }
        }

        // Detect browser
        $browsers = array(
            'Edge'    => '/Edg[e\/]?([\d.]+)/',
            'Chrome'  => '/Chrome\/([\d.]+)/',
            'Firefox' => '/Firefox\/([\d.]+)/',
            'Safari'  => '/Version\/([\d.]+).*Safari/',
            'Opera'   => '/OPR\/([\d.]+)/',
            'IE'      => '/(?:MSIE |Trident.*rv:)([\d.]+)/',
        );

        foreach ( $browsers as $name => $pattern ) {
            if ( preg_match( $pattern, $user_agent, $matches ) ) {
                $result['browser'] = $name . ' ' . $matches[1];
                break;
            }
        }

        // Detect OS
        $os_patterns = array(
            'Windows 11'   => '/Windows NT 10\.0.*Build\/[2-9]/',
            'Windows 10'   => '/Windows NT 10\.0/',
            'Windows 8.1'  => '/Windows NT 6\.3/',
            'Windows 8'    => '/Windows NT 6\.2/',
            'Windows 7'    => '/Windows NT 6\.1/',
            'macOS'        => '/Mac OS X ([\d_]+)/',
            'iOS'          => '/(?:iPhone|iPad|iPod).*OS ([\d_]+)/',
            'Android'      => '/Android ([\d.]+)/',
            'Linux'        => '/Linux/',
            'Chrome OS'    => '/CrOS/',
        );

        foreach ( $os_patterns as $name => $pattern ) {
            if ( preg_match( $pattern, $user_agent, $matches ) ) {
                if ( isset( $matches[1] ) ) {
                    $version = str_replace( '_', '.', $matches[1] );
                    $result['os'] = $name . ' ' . $version;
                } else {
                    $result['os'] = $name;
                }
                break;
            }
        }

        return $result;
    }
}
