<?php
/**
 * Google Sheets API integration for GCLID Tracker Pro
 *
 * Handles authentication via Service Account and
 * appending captured data rows to Google Spreadsheets.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCLID_Sheets {

    /**
     * Google Sheets API base URL
     *
     * @var string
     */
    private $api_base = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * OAuth2 token endpoint
     *
     * @var string
     */
    private $token_url = 'https://oauth2.googleapis.com/token';

    /**
     * Service account credentials
     *
     * @var array|null
     */
    private $credentials = null;

    /**
     * Cached access token
     *
     * @var string|null
     */
    private $access_token = null;

    /**
     * Constructor
     */
    public function __construct() {
        $creds_json = get_option( 'gclid_tracker_google_credentials', '' );
        if ( ! empty( $creds_json ) ) {
            $this->credentials = json_decode( $creds_json, true );
        }
    }

    /**
     * Check if credentials are configured
     *
     * @return bool
     */
    public function has_credentials() {
        return ! empty( $this->credentials )
            && isset( $this->credentials['client_email'] )
            && isset( $this->credentials['private_key'] );
    }

    /**
     * Check if sync is fully configured and enabled
     *
     * @return bool
     */
    public function is_configured() {
        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $sync_enabled   = get_option( 'gclid_tracker_sync_enabled', '0' );

        return $this->has_credentials()
            && ! empty( $spreadsheet_id )
            && $sync_enabled === '1';
    }

    /**
     * Generate a JWT token for service account authentication
     *
     * @return string JWT token
     */
    private function generate_jwt() {
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        );

        $now = time();
        $claims = array(
            'iss'   => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud'   => $this->token_url,
            'iat'   => $now,
            'exp'   => $now + 3600,
        );

        $header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
        $claims_encoded  = $this->base64url_encode( wp_json_encode( $claims ) );
        $signing_input   = $header_encoded . '.' . $claims_encoded;

        // Sign with RSA-SHA256
        $private_key = openssl_pkey_get_private( $this->credentials['private_key'] );
        if ( ! $private_key ) {
            return '';
        }

        $signature = '';
        openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );

        return $signing_input . '.' . $this->base64url_encode( $signature );
    }

    /**
     * Base64url encode
     *
     * @param string $data Data to encode
     * @return string
     */
    private function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Get an access token from Google OAuth2
     *
     * @return string|false Access token or false on failure
     */
    private function get_access_token() {
        // Check cached token
        $cached = get_transient( 'gclid_tracker_access_token' );
        if ( $cached ) {
            return $cached;
        }

        if ( ! $this->has_credentials() ) {
            return false;
        }

        $jwt = $this->generate_jwt();
        if ( empty( $jwt ) ) {
            return false;
        }

        $response = wp_remote_post( $this->token_url, array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Token request failed: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['access_token'] ) ) {
            $expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3500;
            set_transient( 'gclid_tracker_access_token', $body['access_token'], $expires_in );
            return $body['access_token'];
        }

        $this->log_error( 'Token response error: ' . wp_json_encode( $body ) );
        return false;
    }

    /**
     * Test the connection to Google Sheets
     *
     * @return array Result with 'success' and 'message' keys
     */
    public function test_connection() {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token. Please check your service account credentials.',
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
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
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
            $title = isset( $body['properties']['title'] ) ? $body['properties']['title'] : 'Unknown';
            $sheets = array();
            if ( isset( $body['sheets'] ) ) {
                foreach ( $body['sheets'] as $sheet ) {
                    $sheets[] = $sheet['properties']['title'];
                }
            }
            return array(
                'success'     => true,
                'message'     => 'Successfully connected to spreadsheet: "' . esc_html( $title ) . '"',
                'title'       => $title,
                'sheet_names' => $sheets,
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

        // Check if row 1 has data
        $range = $sheet_name . '!A1:T1';
        $url   = $this->api_base . '/' . $spreadsheet_id . '/values/' . urlencode( $range );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // If no values, write headers
        if ( empty( $body['values'] ) ) {
            $headers = array( array(
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
                'Country',
                'City',
                'Device Type',
                'Browser',
                'OS',
                'User Agent',
                'Captured At',
            ) );

            return $this->write_rows( $range, $headers );
        }

        return true;
    }

    /**
     * Write rows to a range
     *
     * @param string $range  Sheet range
     * @param array  $values Row values
     * @return bool
     */
    private function write_rows( $range, $values ) {
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
                'message' => 'Failed to obtain access token.',
            );
        }

        $spreadsheet_id = get_option( 'gclid_tracker_spreadsheet_id', '' );
        $sheet_name     = get_option( 'gclid_tracker_sheet_name', 'Sheet1' );
        $range          = $sheet_name . '!A:S';

        $url = $this->api_base . '/' . $spreadsheet_id . '/values/' . urlencode( $range ) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';

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
        return array(
            isset( $capture['id'] ) ? $capture['id'] : '',
            isset( $capture['gclid'] ) ? $capture['gclid'] : '',
            isset( $capture['fbclid'] ) ? $capture['fbclid'] : '',
            isset( $capture['msclkid'] ) ? $capture['msclkid'] : '',
            isset( $capture['landing_page'] ) ? $capture['landing_page'] : '',
            isset( $capture['referrer'] ) ? $capture['referrer'] : '',
            isset( $capture['utm_source'] ) ? $capture['utm_source'] : '',
            isset( $capture['utm_medium'] ) ? $capture['utm_medium'] : '',
            isset( $capture['utm_campaign'] ) ? $capture['utm_campaign'] : '',
            isset( $capture['utm_term'] ) ? $capture['utm_term'] : '',
            isset( $capture['utm_content'] ) ? $capture['utm_content'] : '',
            isset( $capture['ip_address'] ) ? $capture['ip_address'] : '',
            isset( $capture['country'] ) ? $capture['country'] : '',
            isset( $capture['city'] ) ? $capture['city'] : '',
            isset( $capture['device_type'] ) ? $capture['device_type'] : '',
            isset( $capture['browser'] ) ? $capture['browser'] : '',
            isset( $capture['os'] ) ? $capture['os'] : '',
            isset( $capture['user_agent'] ) ? $capture['user_agent'] : '',
            isset( $capture['captured_at'] ) ? $capture['captured_at'] : '',
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

        // Ensure headers exist
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
     * @param string $message Error message
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[GCLID Tracker Pro] ' . $message );
        }
    }
}
