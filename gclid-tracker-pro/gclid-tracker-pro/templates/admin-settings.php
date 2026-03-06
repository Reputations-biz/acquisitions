<?php
/**
 * Admin Settings template for GCLID Tracker Pro
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap gclid-tracker-wrap">
    <h1>
        <span class="dashicons dashicons-admin-generic" style="font-size: 28px; margin-right: 8px;"></span>
        <?php esc_html_e( 'GCLID Tracker Pro — Settings', 'gclid-tracker-pro' ); ?>
    </h1>

    <?php
    // OAuth status notices
    if ( $oauth_status === 'success' ) : ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php esc_html_e( 'Google account connected successfully!', 'gclid-tracker-pro' ); ?></strong>
            <?php if ( ! empty( $connected_email ) ) : ?>
            <?php printf( esc_html__( 'Connected as: %s', 'gclid-tracker-pro' ), '<strong>' . esc_html( $connected_email ) . '</strong>' ); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php elseif ( $oauth_status === 'error' ) : ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <strong><?php esc_html_e( 'Google authorization failed.', 'gclid-tracker-pro' ); ?></strong>
            <?php if ( ! empty( $oauth_error ) ) : ?>
            <?php echo esc_html( $oauth_error ); ?>
            <?php else : ?>
            <?php esc_html_e( 'Please check your Client ID and Client Secret and try again.', 'gclid-tracker-pro' ); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php elseif ( $oauth_status === 'disconnected' ) : ?>
    <div class="notice notice-info is-dismissible">
        <p><?php esc_html_e( 'Google account disconnected.', 'gclid-tracker-pro' ); ?></p>
    </div>
    <?php endif; ?>

    <?php settings_errors( 'gclid_tracker_settings' ); ?>

    <form method="post" action="options.php" id="gclid-settings-form">
        <?php settings_fields( 'gclid_tracker_settings' ); ?>

        <!-- =============================================
             SECTION 1: Google Account Connection
             ============================================= -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-google" style="color: #4285f4;"></span>
                <?php esc_html_e( 'Google Account Connection', 'gclid-tracker-pro' ); ?>
            </h2>

            <?php if ( $is_connected ) : ?>
            <!-- CONNECTED STATE -->
            <div class="gclid-oauth-connected">
                <div class="gclid-oauth-status-badge connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <div>
                        <strong><?php esc_html_e( 'Connected to Google', 'gclid-tracker-pro' ); ?></strong>
                        <?php if ( ! empty( $connected_email ) ) : ?>
                        <br><span class="gclid-connected-email"><?php echo esc_html( $connected_email ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="gclid-oauth-actions">
                    <a href="<?php echo esc_url( $disconnect_url ); ?>"
                       class="button button-secondary gclid-disconnect-btn"
                       onclick="return confirm('<?php esc_attr_e( 'Disconnect your Google account? This will stop syncing to Google Sheets until you reconnect.', 'gclid-tracker-pro' ); ?>');">
                        <span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Disconnect', 'gclid-tracker-pro' ); ?>
                    </a>
                    <button type="button" class="button" id="gclid-test-connection">
                        <span class="dashicons dashicons-cloud" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Test Spreadsheet Access', 'gclid-tracker-pro' ); ?>
                    </button>
                    <span id="gclid-test-result" class="gclid-test-result"></span>
                </div>
            </div>

            <?php else : ?>
            <!-- DISCONNECTED STATE -->
            <p class="description">
                <?php esc_html_e( 'Connect your Google account to enable real-time syncing of captured data to a Google Spreadsheet. You only need a Google account — no service accounts or JSON files required.', 'gclid-tracker-pro' ); ?>
            </p>

            <div class="gclid-setup-steps">
                <h4><?php esc_html_e( 'One-time setup (takes about 3 minutes):', 'gclid-tracker-pro' ); ?></h4>
                <ol>
                    <li>
                        <?php
                        printf(
                            wp_kses(
                                __( 'Go to the <a href="%s" target="_blank" rel="noopener">Google Cloud Console</a> and create or select a project.', 'gclid-tracker-pro' ),
                                array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                            ),
                            'https://console.cloud.google.com/'
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        printf(
                            wp_kses(
                                __( 'Enable the <a href="%s" target="_blank" rel="noopener">Google Sheets API</a> for your project.', 'gclid-tracker-pro' ),
                                array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                            ),
                            'https://console.cloud.google.com/apis/library/sheets.googleapis.com'
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        printf(
                            wp_kses(
                                __( 'Go to <a href="%s" target="_blank" rel="noopener">Credentials</a> &rarr; <strong>Create Credentials</strong> &rarr; <strong>OAuth client ID</strong>.', 'gclid-tracker-pro' ),
                                array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array() )
                            ),
                            'https://console.cloud.google.com/apis/credentials'
                        );
                        ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Choose "Web application" as the application type.', 'gclid-tracker-pro' ); ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Under "Authorized redirect URIs", add the following URL exactly:', 'gclid-tracker-pro' ); ?>
                        <br>
                        <code class="gclid-redirect-uri"><?php echo esc_html( ( new GCLID_Sheets() )->get_redirect_uri() ); ?></code>
                        <button type="button" class="button button-small gclid-copy-btn"
                            data-copy="<?php echo esc_attr( ( new GCLID_Sheets() )->get_redirect_uri() ); ?>">
                            <?php esc_html_e( 'Copy', 'gclid-tracker-pro' ); ?>
                        </button>
                    </li>
                    <li><?php esc_html_e( 'Copy the Client ID and Client Secret shown and paste them below.', 'gclid-tracker-pro' ); ?></li>
                    <li><?php esc_html_e( 'Click "Save Settings", then click "Connect with Google".', 'gclid-tracker-pro' ); ?></li>
                </ol>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_oauth_client_id"><?php esc_html_e( 'Client ID', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gclid_tracker_oauth_client_id" id="gclid_tracker_oauth_client_id"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_oauth_client_id', '' ) ); ?>"
                            class="large-text" placeholder="xxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                        <p class="description"><?php esc_html_e( 'The OAuth 2.0 Client ID from Google Cloud Console.', 'gclid-tracker-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_oauth_client_secret"><?php esc_html_e( 'Client Secret', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="gclid_tracker_oauth_client_secret" id="gclid_tracker_oauth_client_secret"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_oauth_client_secret', '' ) ); ?>"
                            class="regular-text" autocomplete="new-password">
                        <button type="button" class="button button-small" id="gclid-toggle-secret">
                            <?php esc_html_e( 'Show', 'gclid-tracker-pro' ); ?>
                        </button>
                        <p class="description"><?php esc_html_e( 'The OAuth 2.0 Client Secret from Google Cloud Console.', 'gclid-tracker-pro' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php if ( $has_credentials ) : ?>
            <div class="gclid-connect-cta">
                <a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary button-hero gclid-connect-btn">
                    <span class="dashicons dashicons-google" style="margin-top: 6px;"></span>
                    <?php esc_html_e( 'Connect with Google', 'gclid-tracker-pro' ); ?>
                </a>
                <p class="description">
                    <?php esc_html_e( 'You will be redirected to Google to authorize access. Only Google Sheets access will be requested — no access to your email or other files.', 'gclid-tracker-pro' ); ?>
                </p>
            </div>
            <?php else : ?>
            <p class="description" style="margin-top: 12px;">
                <span class="dashicons dashicons-info" style="color: #646970;"></span>
                <?php esc_html_e( 'Save your Client ID and Client Secret above, then the "Connect with Google" button will appear here.', 'gclid-tracker-pro' ); ?>
            </p>
            <?php endif; ?>

            <?php endif; // end disconnected state ?>
        </div>

        <!-- =============================================
             SECTION 2: Spreadsheet Configuration
             ============================================= -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-media-spreadsheet" style="color: #34a853;"></span>
                <?php esc_html_e( 'Spreadsheet Configuration', 'gclid-tracker-pro' ); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_spreadsheet_id"><?php esc_html_e( 'Spreadsheet ID', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gclid_tracker_spreadsheet_id" id="gclid_tracker_spreadsheet_id"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_spreadsheet_id', '' ) ); ?>"
                            class="regular-text" placeholder="e.g., 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms">
                        <p class="description">
                            <?php esc_html_e( 'Found in your Google Sheets URL: docs.google.com/spreadsheets/d/', 'gclid-tracker-pro' ); ?>
                            <strong><?php esc_html_e( 'SPREADSHEET_ID', 'gclid-tracker-pro' ); ?></strong>
                            <?php esc_html_e( '/edit', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_sheet_name"><?php esc_html_e( 'Sheet Tab Name', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gclid_tracker_sheet_name" id="gclid_tracker_sheet_name"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_sheet_name', 'Sheet1' ) ); ?>"
                            class="regular-text" placeholder="Sheet1">
                        <p class="description">
                            <?php esc_html_e( 'The name of the tab within the spreadsheet where data will be written. Default is "Sheet1".', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Auto-Sync', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_sync_enabled" value="1"
                                <?php checked( get_option( 'gclid_tracker_sync_enabled', '0' ), '1' ); ?>>
                            <?php esc_html_e( 'Automatically push each new capture to Google Sheets in real-time', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'All captures are always saved locally first. This option enables the live push to your spreadsheet. A daily background job also retries any failed syncs.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- =============================================
             SECTION 3: Capture Settings
             ============================================= -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-settings" style="color: #4285f4;"></span>
                <?php esc_html_e( 'Capture Settings', 'gclid-tracker-pro' ); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'UTM Parameters', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_utms" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_utms', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture utm_source, utm_medium, utm_campaign, utm_term, utm_content', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Facebook Click ID', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_fbclid" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_fbclid', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture fbclid from Facebook / Instagram Ads', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Microsoft Click ID', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_msclkid" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_msclkid', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture msclkid from Microsoft / Bing Ads', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture All Visits', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_all_visits" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_all_visits', '0' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture every visitor session, even without any tracking parameters', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'By default, only sessions with a GCLID, FBCLID, MSCLKID, or UTM parameter are captured. Enable this to log all traffic.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- =============================================
             SECTION 4: Privacy & Data Retention
             ============================================= -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-shield" style="color: #ea4335;"></span>
                <?php esc_html_e( 'Privacy & Data Retention', 'gclid-tracker-pro' ); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Log IP Addresses', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_enable_ip_logging" value="1"
                                <?php checked( get_option( 'gclid_tracker_enable_ip_logging', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Store visitor IP addresses alongside captures', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Disable this to improve GDPR compliance. IP addresses are never sent to Google Sheets if logging is disabled.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_data_retention_days"><?php esc_html_e( 'Data Retention Period', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="gclid_tracker_data_retention_days" id="gclid_tracker_data_retention_days"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_data_retention_days', '90' ) ); ?>"
                            min="0" max="3650" class="small-text">
                        <?php esc_html_e( 'days', 'gclid-tracker-pro' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Local capture records older than this will be automatically deleted each day. Set to 0 to keep records indefinitely. Records already synced to Google Sheets are not affected.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'gclid-tracker-pro' ) ); ?>
    </form>
</div>
