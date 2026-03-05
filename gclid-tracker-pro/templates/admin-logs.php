<?php
/**
 * Admin Capture Log template for GCLID Tracker Pro
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_url = admin_url( 'admin.php?page=gclid-tracker-logs' );
$export_url  = wp_nonce_url(
    add_query_arg( array( 'gclid_export' => '1' ), admin_url( 'admin.php' ) ),
    'gclid_export_csv'
);
?>
<div class="wrap gclid-tracker-wrap">
    <h1>
        <span class="dashicons dashicons-list-view" style="font-size: 28px; margin-right: 8px;"></span>
        <?php esc_html_e( 'GCLID Tracker Pro — Capture Log', 'gclid-tracker-pro' ); ?>
    </h1>

    <!-- Toolbar -->
    <div class="gclid-log-toolbar">
        <div class="gclid-log-toolbar-left">
            <!-- Search -->
            <form method="get" action="<?php echo esc_url( $current_url ); ?>" class="gclid-search-form">
                <input type="hidden" name="page" value="gclid-tracker-logs">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                    placeholder="<?php esc_attr_e( 'Search GCLID, URL, source, campaign, IP...', 'gclid-tracker-pro' ); ?>"
                    class="gclid-search-input">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'gclid-tracker-pro' ); ?></button>
                <?php if ( ! empty( $search ) ) : ?>
                <a href="<?php echo esc_url( $current_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'gclid-tracker-pro' ); ?></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="gclid-log-toolbar-right">
            <!-- Bulk Actions -->
            <select id="gclid-bulk-action" class="gclid-bulk-select">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'gclid-tracker-pro' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete Selected', 'gclid-tracker-pro' ); ?></option>
                <option value="sync"><?php esc_html_e( 'Sync Selected', 'gclid-tracker-pro' ); ?></option>
            </select>
            <button type="button" class="button" id="gclid-apply-bulk"><?php esc_html_e( 'Apply', 'gclid-tracker-pro' ); ?></button>

            <!-- Export -->
            <a href="<?php echo esc_url( $export_url ); ?>" class="button">
                <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                <?php esc_html_e( 'Export CSV', 'gclid-tracker-pro' ); ?>
            </a>
        </div>
    </div>

    <!-- Results Info -->
    <div class="gclid-results-info">
        <?php
        printf(
            esc_html__( 'Showing %1$d-%2$d of %3$d captures', 'gclid-tracker-pro' ),
            ( ( $page - 1 ) * $per_page ) + 1,
            min( $page * $per_page, $total_items ),
            $total_items
        );
        ?>
    </div>

    <!-- Captures Table -->
    <table class="widefat striped gclid-captures-table">
        <thead>
            <tr>
                <th class="check-column">
                    <input type="checkbox" id="gclid-select-all">
                </th>
                <?php
                $columns = array(
                    'id'              => __( 'ID', 'gclid-tracker-pro' ),
                    'gclid'           => __( 'GCLID', 'gclid-tracker-pro' ),
                    'landing_page'    => __( 'Landing Page', 'gclid-tracker-pro' ),
                    'utm_source'      => __( 'Source', 'gclid-tracker-pro' ),
                    'utm_medium'      => __( 'Medium', 'gclid-tracker-pro' ),
                    'utm_campaign'    => __( 'Campaign', 'gclid-tracker-pro' ),
                    'ip_address'      => __( 'IP', 'gclid-tracker-pro' ),
                    'captured_at'     => __( 'Captured', 'gclid-tracker-pro' ),
                    'synced_to_sheets'=> __( 'Synced', 'gclid-tracker-pro' ),
                );

                foreach ( $columns as $col_key => $col_label ) :
                    $sort_url = add_query_arg( array(
                        'page'    => 'gclid-tracker-logs',
                        'orderby' => $col_key,
                        'order'   => ( $orderby === $col_key && $order === 'ASC' ) ? 'DESC' : 'ASC',
                        's'       => $search,
                    ), admin_url( 'admin.php' ) );

                    $sort_class = '';
                    $sort_indicator = '';
                    if ( $orderby === $col_key ) {
                        $sort_class = 'sorted ' . strtolower( $order );
                        $sort_indicator = $order === 'ASC' ? ' &#9650;' : ' &#9660;';
                    }
                ?>
                <th class="<?php echo esc_attr( $sort_class ); ?>">
                    <a href="<?php echo esc_url( $sort_url ); ?>">
                        <?php echo esc_html( $col_label ) . $sort_indicator; ?>
                    </a>
                </th>
                <?php endforeach; ?>
                <th><?php esc_html_e( 'Actions', 'gclid-tracker-pro' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $captures ) ) : ?>
            <tr>
                <td colspan="<?php echo count( $columns ) + 2; ?>" class="gclid-no-data">
                    <?php esc_html_e( 'No captures found.', 'gclid-tracker-pro' ); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php foreach ( $captures as $capture ) : ?>
                <tr data-id="<?php echo esc_attr( $capture['id'] ); ?>">
                    <td class="check-column">
                        <input type="checkbox" class="gclid-row-check" value="<?php echo esc_attr( $capture['id'] ); ?>">
                    </td>
                    <td><?php echo esc_html( $capture['id'] ); ?></td>
                    <td>
                        <?php if ( ! empty( $capture['gclid'] ) ) : ?>
                        <code class="gclid-code" title="<?php echo esc_attr( $capture['gclid'] ); ?>">
                            <?php echo esc_html( substr( $capture['gclid'], 0, 20 ) ); ?><?php echo strlen( $capture['gclid'] ) > 20 ? '...' : ''; ?>
                        </code>
                        <?php else : ?>
                        <span class="gclid-empty">—</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $capture['fbclid'] ) ) : ?>
                        <br><small class="gclid-badge gclid-badge-fb">FB</small>
                        <?php endif; ?>
                        <?php if ( ! empty( $capture['msclkid'] ) ) : ?>
                        <br><small class="gclid-badge gclid-badge-ms">MS</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( $capture['landing_page'] ); ?>" target="_blank" rel="noopener"
                            title="<?php echo esc_attr( $capture['landing_page'] ); ?>">
                            <?php
                            $parsed = wp_parse_url( $capture['landing_page'] );
                            $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
                            echo esc_html( strlen( $path ) > 40 ? substr( $path, 0, 40 ) . '...' : $path );
                            ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( $capture['utm_source'] ?: '—' ); ?></td>
                    <td><?php echo esc_html( $capture['utm_medium'] ?: '—' ); ?></td>
                    <td><?php echo esc_html( $capture['utm_campaign'] ?: '—' ); ?></td>
                    <td>
                        <?php if ( ! empty( $capture['ip_address'] ) ) : ?>
                        <code><?php echo esc_html( $capture['ip_address'] ); ?></code>
                        <?php if ( ! empty( $capture['device_type'] ) ) : ?>
                        <br><small><?php echo esc_html( $capture['device_type'] ); ?></small>
                        <?php endif; ?>
                        <?php else : ?>
                        <span class="gclid-empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html( $capture['captured_at'] ); ?>
                        <?php if ( ! empty( $capture['browser'] ) ) : ?>
                        <br><small><?php echo esc_html( $capture['browser'] ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $capture['synced_to_sheets'] ) : ?>
                        <span class="gclid-sync-status gclid-synced" title="<?php echo esc_attr( $capture['synced_at'] ); ?>">
                            <span class="dashicons dashicons-yes"></span>
                        </span>
                        <?php else : ?>
                        <span class="gclid-sync-status gclid-unsynced">
                            <span class="dashicons dashicons-minus"></span>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small gclid-delete-btn"
                            data-id="<?php echo esc_attr( $capture['id'] ); ?>"
                            title="<?php esc_attr_e( 'Delete', 'gclid-tracker-pro' ); ?>">
                            <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="gclid-pagination">
        <?php
        $pagination_args = array(
            'base'    => add_query_arg( 'paged', '%#%', $current_url ),
            'format'  => '',
            'current' => $page,
            'total'   => $total_pages,
            'prev_text' => '&laquo; ' . __( 'Previous', 'gclid-tracker-pro' ),
            'next_text' => __( 'Next', 'gclid-tracker-pro' ) . ' &raquo;',
        );

        if ( ! empty( $search ) ) {
            $pagination_args['add_args'] = array( 's' => $search );
        }
        if ( ! empty( $orderby ) ) {
            $pagination_args['add_args']['orderby'] = $orderby;
            $pagination_args['add_args']['order']   = $order;
        }

        echo paginate_links( $pagination_args );
        ?>
    </div>
    <?php endif; ?>
</div>
