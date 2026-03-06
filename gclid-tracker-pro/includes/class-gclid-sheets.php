<?php
/**
 * Google Sheets API integration for GCLID Tracker Pro
 *
 * Handles OAuth 2.0 authorization code flow (web server flow).
 * The user clicks "Connect with Google", approves access on Google's
 * consent screen, and is redirected back with an authorization code
 * that is exchanged for access + refresh tokens stored in wp_options.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Sheets {

    /**
     * Google OAuth2 endpoints
     */
    private $auth_url    = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $token_url   = 'https://oauth2.googleapis.com/token';
    private $revoke_url  = 'https://oauth2.googleapis.com/revoke';

    /**
     * Google Sheets API base URL
     */
    private $api_base = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * Required OAuth scopes
     */
    private $scopes = array(
        'https://www.googleapis.com/auth/spreadsheets',
    );

    /**
     * The WordPress admin redirect URI for OAuth callback
     *
     * @return string
     */
    public function get_redirect_uri() {
        return admin_url( 'admin.php?page=gclid-tracker-settings&gclid_oauth_callback=1' );
    }

    /**
     * Check if OAuth credentials (Client ID + Secret) are configured
     *
     * @return bool
     */
    public function has_oauth_credentials() {
        $client_id     = get_option( 'gclid_tracker_oauth_client_id', '' );
        $client_secret = get_option( 'gclid_tracker_oauth_client_secret', '' );
        return ! empty( $client_id ) && ! empty( $client_secret );
    }

    /**
     * Check if the plugin is connected (has valid tokens)
     *
     * @return bool
     */
    public function is_connected() {
        $refresh_token = get_option( 'gclid_tracker_oauth_refresh_token', '' );
        return ! empty( $refresh_token );
    }

    /**
     * Check if sync is fully configured and enabled
     *
     * @return bool
     */
    public function is_configured() {
        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $sync_enabled   = get_option( 'gclid_tracker_sync_enabled', '0' );
        return $this->is_connected() && ! empty( $spreadsheet_id ) && $sync_enabled === '1';
    }

    /**
     * Build the Google OAuth authorization URL
     *
     * @return string
     */
    public function get_authorization_url() {
        $client_id = get_option( 'gclid_tracker_oauth_client_id', '' );

        // Generate and store a state token to prevent CSRF
        $state = wp_generate_password( 32, false );
        set_transient( 'gclid_tracker_oauth_state', $state, 600 );

        $params = array(
            'client_id'             => $client_id,
            'redirect_uri'          => $this->get_redirect_uri(),
            'response_type'         => 'code',
            'scope'                 => implode( ' ', $this->scopes ),
            'access_type'           => 'offline',   // Request refresh token
            'prompt'                => 'consent',   // Force consent to always get refresh token
            'state'                 => $state,
        );

        return $this->auth_url . '?' . http_build_query( $params );
    }

    /**
     * Exchange an authorization code for access + refresh tokens
     *
     * @param string $code  Authorization code from Google
     * @param string $state State parameter for CSRF validation
     * @return array Result with 'success' and 'message'
     */
    public function exchange_code( $code, $state ) {
        // Validate state to prevent CSRF
        $stored_state = get_transient( 'gclid_tracker_oauth_state' );
        delete_transient( 'gclid_tracker_oauth_state' );

        if ( empty( $stored_state ) || ! hash_equals( $stored_state, $state ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid OAuth state parameter. Please try connecting again.',
            );
        }

        $client_id     = get_option( 'gclid_tracker_oauth_client_id', '' );
        $client_secret = get_option( 'gclid_tracker_oauth_client_secret', '' );

        $response = wp_remote_post( $this->token_url, array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $this->get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Token exchange failed: ' . $response->get_error_message(),
            );
        }

        $code_http = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code_http !== 200 || empty( $body['access_token'] ) ) {
            $error = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : 'Unknown error' );
            return array(
                'success' => false,
                'message' => 'Google returned an error: ' . $error,
            );
        }

        // Store tokens
        update_option( 'gclid_tracker_oauth_access_token', $body['access_token'] );
        update_option( 'gclid_tracker_oauth_token_expiry', time() + (int) $body['expires_in'] - 60 );

        if ( ! empty( $body['refresh_token'] ) ) {
            update_option( 'gclid_tracker_oauth_refresh_token', $body['refresh_token'] );
        }

        // Get the connected account email
        $email = $this->get_connected_email( $body['access_token'] );
        if ( $email ) {
            update_option( 'gclid_tracker_oauth_connected_email', $email );
        }

        return array(
            'success' => true,
            'message' => 'Successfully connected to Google.',
            'email'   => $email,
        );
    }

    /**
     * Get the Google account email for the connected token
     *
     * @param string $access_token
     * @return string|false
     */
    private function get_connected_email( $access_token ) {
        $response = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['email'] ) ? $body['email'] : false;
    }

    /**
     * Get a valid access token, refreshing if necessary
     *
     * @return string|false
     */
    private function get_access_token() {
        // Check if current access token is still valid
        $access_token = get_option( 'gclid_tracker_oauth_access_token', '' );
        $expiry       = (int) get_option( 'gclid_tracker_oauth_token_expiry', 0 );

        if ( ! empty( $access_token ) && time() < $expiry ) {
            return $access_token;
        }

        // Refresh the token
        return $this->refresh_access_token();
    }

    /**
     * Refresh the access token using the refresh token
     *
     * @return string|false New access token or false on failure
     */
    private function refresh_access_token() {
        $refresh_token = get_option( 'gclid_tracker_oauth_refresh_token', '' );
        $client_id     = get_option( 'gclid_tracker_oauth_client_id', '' );
        $client_secret = get_option( 'gclid_tracker_oauth_client_secret', '' );

        if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
            return false;
        }

        $response = wp_remote_post( $this->token_url, array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'grant_type'    => 'refresh_token',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Token refresh failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['access_token'] ) ) {
            update_option( 'gclid_tracker_oauth_access_token', $body['access_token'] );
            update_option( 'gclid_tracker_oauth_token_expiry', time() + (int) $body['expires_in'] - 60 );
            return $body['access_token'];
        }

        // If refresh token is invalid/revoked, clear connection
        if ( isset( $body['error'] ) && in_array( $body['error'], array( 'invalid_grant', 'invalid_token' ), true ) ) {
            $this->disconnect();
        }

        $this->log_error( 'Token refresh error: ' . wp_json_encode( $body ) );
        return false;
    }

    /**
     * Disconnect (revoke tokens and clear stored data)
     *
     * @return bool
     */
    public function disconnect() {
        $access_token = get_option( 'gclid_tracker_oauth_access_token', '' );

        // Attempt to revoke the token at Google
        if ( ! empty( $access_token ) ) {
            wp_remote_post( $this->revoke_url, array(
                'body'    => array( 'token' => $access_token ),
                'timeout' => 10,
            ) );
        }

        // Clear all stored token data
        delete_option( 'gclid_tracker_oauth_access_token' );
        delete_option( 'gclid_tracker_oauth_token_expiry' );
        delete_option( 'gclid_tracker_oauth_refresh_token' );
        delete_option( 'gclid_tracker_oauth_connected_email' );

        return true;
    }

    /**
     * Test the connection to Google Sheets
     *
     * @return array Result with 'success' and 'message' keys
     */
    public function test_connection() {
        if ( ! $this->is_connected() ) {
            return array(
                'success' => false,
                'message' => 'Not connected to Google. Please connect your account first.',
            );
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain a valid access token. Please reconnect your Google account.',
            );
        }

        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        if ( empty( $spreadsheet_id ) ) {
            return array(
                'success' => false,
                'message' => 'No Spreadsheet ID configured.',
            );
        }

        $url = $this->api_base . '/' . $spreadsheet_id . '?fields=properties.title,sheets.properties.title';

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            $title  = isset( $body['properties']['title'] ) ? $body['properties']['title'] : 'Unknown';
            $sheets = array();
            if ( isset( $body['sheets'] ) ) {
                foreach ( $body['sheets'] as $sheet ) {
                    $sheets[] = $sheet['properties']['title'];
                }
            }
            return array(
                'success'     => true,
                'message'     => 'Successfully connected to: "' . esc_html( $title ) . '"',
                'title'       => $title,
                'sheet_names' => $sheets,
            );
        }

        if ( $code === 403 ) {
            return array(
                'success' => false,
                'message' => 'Access denied. Make sure your Google account has access to this spreadsheet.',
            );
        }

        if ( $code === 404 ) {
            return array(
                'success' => false,
                'message' => 'Spreadsheet not found. Please check the Spreadsheet ID.',
            );
        }

        $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown error (HTTP ' . $code . ')';
        return array(
            'success' => false,
            'message' => 'Google API error: ' . $error_msg,
        );
    }

    /**
     * Ensure the header row exists in the sheet
     *
     * @return bool
     */
    public function ensure_headers() {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return false;
        }

        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $sheet_name     = get_option( 'gclid_tracker_sheet_name', 'Sheet1' );
        $range          = $sheet_name . '!A1:J1';
        $url            = $this->api_base . '/' . $spreadsheet_id . '/values/' . urlencode( $range );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['values'] ) ) {
            // Write headers matching the exact column layout in the spreadsheet:
            // A=GCLID, B=FBCLID, C=MSCLKID, D=utm_source,
            // E=utm_medium, F=utm_campaign, G=utm_term, H=utm_content, I=ip_address
            $headers = array( array(
                'GCLID', 'FBCLID', 'MSCLKID',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'ip_address', 'timestamp',
            ) );
            return $this->put_values( $range, $headers );
        }

        return true;
    }

    /**
     * PUT values to a specific range
     *
     * @param string $range  Sheet range
     * @param array  $values Row values
     * @return bool
     */
    private function put_values( $range, $values ) {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return false;
        }

        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $url = $this->api_base . '/' . $spreadsheet_id . '/values/' . urlencode( $range ) . '?valueInputOption=RAW';

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'range'          => $range,
                'majorDimension' => 'ROWS',
                'values'         => $values,
            ) ),
            'timeout' => 30,
        ) );

        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
    }

    /**
     * Append rows to the spreadsheet
     *
     * @param array $rows Array of row arrays
     * @return array Result with 'success' and 'message'
     */
    public function append_rows( $rows ) {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token. Please reconnect your Google account.',
            );
        }

        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $sheet_name     = get_option( 'gclid_tracker_sheet_name', 'Sheet1' );
        $range          = $sheet_name . '!A:J';
        $url            = $this->api_base . '/' . $spreadsheet_id . '/values/' . urlencode( $range ) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'range'          => $range,
                'majorDimension' => 'ROWS',
                'values'         => $rows,
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            $updated = isset( $body['updates']['updatedRows'] ) ? $body['updates']['updatedRows'] : count( $rows );
            return array(
                'success' => true,
                'message' => $updated . ' row(s) appended successfully.',
            );
        }

        $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown error (HTTP ' . $code . ')';
        return array(
            'success' => false,
            'message' => 'Google API error: ' . $error_msg,
        );
    }

    /**
     * Convert a capture record to a spreadsheet row
     *
     * @param array $capture Capture record from database
     * @return array Row values
     */
    public function capture_to_row( $capture ) {
        // Column order matches the spreadsheet exactly (10 columns, no gaps):
        // A=GCLID, B=FBCLID, C=MSCLKID,
        // D=utm_source, E=utm_medium, F=utm_campaign, G=utm_term, H=utm_content,
        // I=ip_address (blank if IP logging is disabled or IP could not be detected)
        // J=timestamp (Pacific Time, format: MM/DD/YYYY HH:MM AM/PM PT)
        // Only the raw value is written — no variable name prefix.
        // If a parameter was not present in the URL, the cell is left blank ('').
        // IP detection failure never prevents the row from being inserted.

        // IP address: only write if logging is enabled and value is present
        $ip_logging_enabled = get_option( 'gclid_tracker_enable_ip_logging', '1' ) === '1';
        $ip_address         = ( $ip_logging_enabled && ! empty( $capture['ip_address'] ) )
                                ? $capture['ip_address']
                                : '';

        // Timestamp: convert stored UTC/site-time value to Pacific Time
        // Uses America/Los_Angeles which auto-handles PST (UTC-8) and PDT (UTC-7)
        $timestamp = '';
        if ( ! empty( $capture['captured_at'] ) ) {
            try {
                $dt = new DateTime( $capture['captured_at'], new DateTimeZone( 'UTC' ) );
                $dt->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) );
                // Format: 03/06/2026 08:17 AM PT
                $abbr      = $dt->format( 'T' ); // PST or PDT
                $timestamp = $dt->format( 'm/d/Y h:i A' ) . ' ' . $abbr;
            } catch ( Exception $e ) {
                $timestamp = $capture['captured_at']; // Fallback to raw value
            }
        }

        return array(
            $capture['gclid']        ?? '',   // A: GCLID
            $capture['fbclid']       ?? '',   // B: FBCLID
            $capture['msclkid']      ?? '',   // C: MSCLKID
            $capture['utm_source']   ?? '',   // D: utm_source
            $capture['utm_medium']   ?? '',   // E: utm_medium
            $capture['utm_campaign'] ?? '',   // F: utm_campaign
            $capture['utm_term']     ?? '',   // G: utm_term
            $capture['utm_content']  ?? '',   // H: utm_content
            $ip_address,                      // I: ip_address (blank if disabled or undetected)
            $timestamp,                       // J: timestamp in Pacific Time
        );
    }

    /**
     * Sync unsynced captures to Google Sheets
     *
     * @return array Result with 'success', 'message', and 'synced_count'
     */
    public function sync_pending() {
        if ( ! $this->is_configured() ) {
            return array(
                'success'      => false,
                'message'      => 'Google Sheets sync is not configured or enabled.',
                'synced_count' => 0,
            );
        }

        $this->ensure_headers();

        $db       = new GCLID_DB();
        $unsynced = $db->get_unsynced( 100 );

        if ( empty( $unsynced ) ) {
            return array(
                'success'      => true,
                'message'      => 'No pending records to sync.',
                'synced_count' => 0,
            );
        }

        $rows = array();
        $ids  = array();
        foreach ( $unsynced as $capture ) {
            $rows[] = $this->capture_to_row( $capture );
            $ids[]  = $capture['id'];
        }

        $result = $this->append_rows( $rows );

        if ( $result['success'] ) {
            $db->mark_synced( $ids );
            return array(
                'success'      => true,
                'message'      => count( $ids ) . ' record(s) synced to Google Sheets.',
                'synced_count' => count( $ids ),
            );
        }

        return array(
            'success'      => false,
            'message'      => 'Sync failed: ' . $result['message'],
            'synced_count' => 0,
        );
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[GCLID Tracker Pro] ' . $message );
        }
    }
}
