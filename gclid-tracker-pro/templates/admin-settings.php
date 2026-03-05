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

    <?php settings_errors( 'gclid_tracker_settings' ); ?>

    <form method="post" action="options.php" id="gclid-settings-form">
        <?php settings_fields( 'gclid_tracker_settings' ); ?>

        <!-- Google Sheets Connection -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-media-spreadsheet" style="color: #34a853;"></span>
                <?php esc_html_e( 'Google Sheets Connection', 'gclid-tracker-pro' ); ?>
            </h2>
            <p class="description">
                <?php esc_html_e( 'Connect a Google Spreadsheet to automatically sync captured GCLID data. You need a Google Cloud Service Account with the Google Sheets API enabled.', 'gclid-tracker-pro' ); ?>
            </p>

            <div class="gclid-setup-steps">
                <h4><?php esc_html_e( 'Setup Instructions:', 'gclid-tracker-pro' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Go to the Google Cloud Console and create a project (or use an existing one).', 'gclid-tracker-pro' ); ?></li>
                    <li><?php esc_html_e( 'Enable the "Google Sheets API" for your project.', 'gclid-tracker-pro' ); ?></li>
                    <li><?php esc_html_e( 'Create a Service Account and download the JSON key file.', 'gclid-tracker-pro' ); ?></li>
                    <li><?php esc_html_e( 'Share your Google Spreadsheet with the service account email address (give it Editor access).', 'gclid-tracker-pro' ); ?></li>
                    <li><?php esc_html_e( 'Paste the JSON key file contents below and enter your Spreadsheet ID.', 'gclid-tracker-pro' ); ?></li>
                </ol>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Service Account Credentials', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <?php if ( $has_credentials ) : ?>
                        <div class="gclid-credentials-status gclid-status-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php esc_html_e( 'Credentials configured', 'gclid-tracker-pro' ); ?></strong>
                            <br>
                            <span class="description">
                                <?php
                                printf(
                                    esc_html__( 'Service Account: %s', 'gclid-tracker-pro' ),
                                    '<code>' . esc_html( $service_email ) . '</code>'
                                );
                                ?>
                            </span>
                            <br><br>
                            <button type="submit" name="gclid_tracker_clear_credentials" value="1" class="button button-secondary"
                                onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to remove the credentials?', 'gclid-tracker-pro' ); ?>');">
                                <?php esc_html_e( 'Remove Credentials', 'gclid-tracker-pro' ); ?>
                            </button>
                        </div>
                        <?php else : ?>
                        <div class="gclid-credentials-status gclid-status-disconnected">
                            <span class="dashicons dashicons-dismiss"></span>
                            <strong><?php esc_html_e( 'No credentials configured', 'gclid-tracker-pro' ); ?></strong>
                        </div>
                        <br>
                        <textarea name="gclid_tracker_credentials_json" rows="8" class="large-text code"
                            placeholder='<?php esc_attr_e( 'Paste the contents of your service account JSON key file here...', 'gclid-tracker-pro' ); ?>'></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Paste the entire contents of the downloaded JSON key file. This will be stored securely in your WordPress database.', 'gclid-tracker-pro' ); ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_spreadsheet_id"><?php esc_html_e( 'Spreadsheet ID', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gclid_tracker_spreadsheet_id" id="gclid_tracker_spreadsheet_id"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_spreadsheet_id', '' ) ); ?>"
                            class="regular-text" placeholder="e.g., 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms">
                        <p class="description">
                            <?php esc_html_e( 'The Spreadsheet ID from the Google Sheets URL. For example, in the URL https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit, copy the SPREADSHEET_ID portion.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_sheet_name"><?php esc_html_e( 'Sheet Name', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gclid_tracker_sheet_name" id="gclid_tracker_sheet_name"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_sheet_name', 'Sheet1' ) ); ?>"
                            class="regular-text" placeholder="Sheet1">
                        <p class="description">
                            <?php esc_html_e( 'The name of the specific sheet tab within the spreadsheet. Default is "Sheet1".', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Sync', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_sync_enabled" value="1"
                                <?php checked( get_option( 'gclid_tracker_sync_enabled', '0' ), '1' ); ?>>
                            <?php esc_html_e( 'Automatically sync captures to Google Sheets', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When enabled, each new capture will be immediately pushed to your Google Spreadsheet. A daily cron job will also retry any failed syncs.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Test Connection', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="gclid-test-connection"
                            <?php echo ! $has_credentials ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-cloud" style="margin-top: 4px;"></span>
                            <?php esc_html_e( 'Test Connection', 'gclid-tracker-pro' ); ?>
                        </button>
                        <span id="gclid-test-result" class="gclid-test-result"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Capture Settings -->
        <div class="gclid-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-settings" style="color: #4285f4;"></span>
                <?php esc_html_e( 'Capture Settings', 'gclid-tracker-pro' ); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture UTM Parameters', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_utms" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_utms', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Also capture utm_source, utm_medium, utm_campaign, utm_term, and utm_content', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture Facebook Click ID', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_fbclid" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_fbclid', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture fbclid parameter from Facebook Ads', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture Microsoft Click ID', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_msclkid" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_msclkid', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture msclkid parameter from Microsoft Ads', 'gclid-tracker-pro' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture All Visits', 'gclid-tracker-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gclid_tracker_capture_all_visits" value="1"
                                <?php checked( get_option( 'gclid_tracker_capture_all_visits', '0' ), '1' ); ?>>
                            <?php esc_html_e( 'Capture all visitor sessions, even without tracking parameters', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Warning: This can generate a large volume of data. By default, only visits with a GCLID, FBCLID, MSCLKID, or UTM parameter are captured.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Privacy & Data -->
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
                            <?php esc_html_e( 'Store visitor IP addresses with captures', 'gclid-tracker-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Disable this if you need to comply with privacy regulations like GDPR that restrict IP address collection.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gclid_tracker_data_retention_days"><?php esc_html_e( 'Data Retention', 'gclid-tracker-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="gclid_tracker_data_retention_days" id="gclid_tracker_data_retention_days"
                            value="<?php echo esc_attr( get_option( 'gclid_tracker_data_retention_days', '90' ) ); ?>"
                            min="0" max="3650" class="small-text">
                        <?php esc_html_e( 'days', 'gclid-tracker-pro' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Automatically delete local capture records older than this many days. Set to 0 to keep records indefinitely. Data already synced to Google Sheets will not be affected.', 'gclid-tracker-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'gclid-tracker-pro' ) ); ?>
    </form>
</div>
