=== GCLID Tracker Pro ===
Contributors: bdouglas73
Tags: gclid, google ads, tracking, google sheets, utm, analytics, click id
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture Google Click ID (GCLID), Facebook Click ID (FBCLID), Microsoft Click ID (MSCLKID), and UTM parameters from all website visitors and sync to Google Sheets in real-time.

== Description ==

**GCLID Tracker Pro** automatically captures advertising click IDs and UTM parameters from every visitor to your WordPress website and pushes the data to a Google Spreadsheet in real-time.

= Key Features =

* **GCLID Capture** — Automatically detects and stores Google Click IDs from Google Ads traffic
* **Multi-Platform Click IDs** — Also captures Facebook (fbclid) and Microsoft (msclkid) click identifiers
* **UTM Parameter Tracking** — Records utm_source, utm_medium, utm_campaign, utm_term, and utm_content
* **Google Sheets Sync** — Real-time push to your Google Spreadsheet via the Sheets API v4
* **Session-Based Capture** — Only captures once per browser session to avoid duplicates
* **Admin Dashboard** — Visual dashboard with charts, statistics, and top sources/campaigns
* **Capture Log** — Searchable, sortable log of all captured data with pagination
* **CSV Export** — Export all captured data as CSV with date range filtering
* **User Agent Parsing** — Automatically detects device type, browser, and operating system
* **IP Logging** — Optional IP address capture (can be disabled for GDPR compliance)
* **Data Retention** — Configurable automatic cleanup of old records
* **Daily Cron Sync** — Automatic retry of any failed Google Sheets syncs
* **Bulk Actions** — Delete or sync multiple records at once
* **Privacy Controls** — Granular settings for what data to collect

= How It Works =

1. A lightweight JavaScript snippet runs on every page of your site
2. When a visitor arrives with a GCLID, FBCLID, MSCLKID, or UTM parameter in the URL, the script captures it
3. The data is sent to your WordPress backend via AJAX and stored in a local database table
4. If Google Sheets sync is enabled, the data is immediately pushed to your spreadsheet
5. A daily cron job retries any records that failed to sync

= Setup =

1. Install and activate the plugin
2. Go to **GCLID Tracker > Settings**
3. Create a Google Cloud Service Account with the Sheets API enabled
4. Download the JSON key file and paste its contents into the credentials field
5. Share your Google Spreadsheet with the service account email (Editor access)
6. Enter your Spreadsheet ID and enable sync
7. Click "Test Connection" to verify everything works

== Installation ==

1. Upload the `gclid-tracker-pro` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **GCLID Tracker > Settings** to configure Google Sheets integration

== Frequently Asked Questions ==

= What is a GCLID? =

GCLID (Google Click Identifier) is a unique tracking parameter that Google Ads appends to your landing page URLs when someone clicks on your ad. It allows you to track which ad clicks lead to conversions.

= Do I need a Google Cloud account? =

Yes, you need a Google Cloud project with the Google Sheets API enabled and a Service Account to authenticate. The free tier is sufficient for most websites.

= Will this slow down my website? =

No. The capture script is lightweight (under 2KB), loads asynchronously, and only fires once per session. It has zero impact on page load performance.

= Is this GDPR compliant? =

The plugin includes privacy controls. You can disable IP address logging and set automatic data retention periods. However, you should consult with a legal professional regarding your specific compliance requirements.

= What happens if Google Sheets sync fails? =

All captures are stored locally in your WordPress database first. A daily cron job automatically retries any records that failed to sync. You can also manually trigger a sync from the dashboard.

== Changelog ==

= 1.0.0 =
* Initial release
* GCLID, FBCLID, and MSCLKID capture
* UTM parameter tracking
* Google Sheets API v4 integration with Service Account auth
* Admin dashboard with charts and statistics
* Searchable capture log with pagination
* CSV export with date filtering
* Configurable data retention
* User agent parsing for device, browser, and OS detection
* Privacy controls for IP logging
* Bulk actions (delete, sync)
* Daily cron job for cleanup and sync retry
