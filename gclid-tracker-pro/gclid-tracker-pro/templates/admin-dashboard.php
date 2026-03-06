<?php
/**
 * Admin Dashboard template for GCLID Tracker Pro
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
        <span class="dashicons dashicons-analytics" style="font-size: 28px; margin-right: 8px;"></span>
        <?php esc_html_e( 'GCLID Tracker Pro — Dashboard', 'gclid-tracker-pro' ); ?>
    </h1>

    <?php
    $sheets = new GCLID_Sheets();
    $is_configured = $sheets->is_configured();
    ?>

    <?php if ( ! $is_configured ) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'Google Sheets sync is not configured.', 'gclid-tracker-pro' ); ?></strong>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=gclid-tracker-settings' ) ); ?>">
                <?php esc_html_e( 'Go to Settings', 'gclid-tracker-pro' ); ?>
            </a>
            <?php esc_html_e( 'to connect your Google Spreadsheet.', 'gclid-tracker-pro' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="gclid-stats-grid">
        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #4285f4;">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['total'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'Total Captures', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>

        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #34a853;">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['today'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'Today', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>

        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #fbbc04;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['this_week'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'This Week', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>

        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #ea4335;">
                <span class="dashicons dashicons-admin-site-alt3"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['with_gclid'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'With GCLID', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>

        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #9c27b0;">
                <span class="dashicons dashicons-cloud-saved"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['synced'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'Synced to Sheets', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>

        <div class="gclid-stat-card">
            <div class="gclid-stat-icon" style="background: #ff5722;">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="gclid-stat-content">
                <span class="gclid-stat-number"><?php echo esc_html( number_format( $stats['unsynced'] ) ); ?></span>
                <span class="gclid-stat-label"><?php esc_html_e( 'Pending Sync', 'gclid-tracker-pro' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="gclid-quick-actions">
        <?php if ( $stats['unsynced'] > 0 && $is_configured ) : ?>
        <button type="button" class="button button-primary" id="gclid-manual-sync">
            <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
            <?php printf( esc_html__( 'Sync %d Pending Record(s)', 'gclid-tracker-pro' ), $stats['unsynced'] ); ?>
        </button>
        <?php endif; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gclid-tracker-logs' ) ); ?>" class="button">
            <span class="dashicons dashicons-list-view" style="margin-top: 4px;"></span>
            <?php esc_html_e( 'View All Captures', 'gclid-tracker-pro' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gclid-tracker-settings' ) ); ?>" class="button">
            <span class="dashicons dashicons-admin-generic" style="margin-top: 4px;"></span>
            <?php esc_html_e( 'Settings', 'gclid-tracker-pro' ); ?>
        </a>
    </div>

    <!-- Charts Row -->
    <div class="gclid-charts-row">
        <div class="gclid-chart-card gclid-chart-wide">
            <h3><?php esc_html_e( 'Captures — Last 30 Days', 'gclid-tracker-pro' ); ?></h3>
            <canvas id="gclid-daily-chart" height="300"></canvas>
        </div>
    </div>

    <div class="gclid-charts-row">
        <div class="gclid-chart-card">
            <h3><?php esc_html_e( 'Top UTM Sources', 'gclid-tracker-pro' ); ?></h3>
            <?php if ( ! empty( $stats['top_sources'] ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Source', 'gclid-tracker-pro' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Count', 'gclid-tracker-pro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats['top_sources'] as $source ) : ?>
                    <tr>
                        <td><?php echo esc_html( $source['utm_source'] ); ?></td>
                        <td style="text-align: right;"><?php echo esc_html( number_format( $source['count'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="gclid-no-data"><?php esc_html_e( 'No UTM source data yet.', 'gclid-tracker-pro' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="gclid-chart-card">
            <h3><?php esc_html_e( 'Top Campaigns', 'gclid-tracker-pro' ); ?></h3>
            <?php if ( ! empty( $stats['top_campaigns'] ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Campaign', 'gclid-tracker-pro' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Count', 'gclid-tracker-pro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats['top_campaigns'] as $campaign ) : ?>
                    <tr>
                        <td><?php echo esc_html( $campaign['utm_campaign'] ); ?></td>
                        <td style="text-align: right;"><?php echo esc_html( number_format( $campaign['count'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="gclid-no-data"><?php esc_html_e( 'No campaign data yet.', 'gclid-tracker-pro' ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="gclid-charts-row">
        <div class="gclid-chart-card gclid-chart-wide">
            <h3><?php esc_html_e( 'Top Landing Pages', 'gclid-tracker-pro' ); ?></h3>
            <?php if ( ! empty( $stats['top_landing_pages'] ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Landing Page', 'gclid-tracker-pro' ); ?></th>
                        <th style="text-align: right; width: 100px;"><?php esc_html_e( 'Count', 'gclid-tracker-pro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats['top_landing_pages'] as $lp ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( $lp['landing_page'] ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $lp['landing_page'] ); ?>
                            </a>
                        </td>
                        <td style="text-align: right;"><?php echo esc_html( number_format( $lp['count'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="gclid-no-data"><?php esc_html_e( 'No landing page data yet.', 'gclid-tracker-pro' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily captures chart
    var dailyData = <?php echo wp_json_encode( $stats['daily_captures'] ); ?>;
    var ctx = document.getElementById('gclid-daily-chart');

    if (ctx && typeof Chart !== 'undefined' && dailyData.length > 0) {
        var labels = dailyData.map(function(d) { return d.date; });
        var values = dailyData.map(function(d) { return parseInt(d.count, 10); });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '<?php esc_html_e( 'Captures', 'gclid-tracker-pro' ); ?>',
                    data: values,
                    borderColor: '#4285f4',
                    backgroundColor: 'rgba(66, 133, 244, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#4285f4',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 15,
                        }
                    }
                }
            }
        });
    }
});
</script>
