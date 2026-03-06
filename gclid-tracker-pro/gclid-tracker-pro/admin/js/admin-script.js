/**
 * GCLID Tracker Pro - Admin Script
 *
 * Handles all admin UI interactions including OAuth connect flow,
 * test connection, manual sync, bulk actions, and record deletion.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    if (typeof gclidAdmin === 'undefined') {
        return;
    }

    var config = gclidAdmin;

    // =========================================
    // Copy redirect URI to clipboard
    // =========================================
    $(document).on('click', '.gclid-copy-btn', function () {
        var $btn  = $(this);
        var text  = $btn.data('copy');

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.text('Copied!');
                setTimeout(function () { $btn.text('Copy'); }, 2000);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            $btn.text('Copied!');
            setTimeout(function () { $btn.text('Copy'); }, 2000);
        }
    });

    // =========================================
    // Show / hide client secret
    // =========================================
    $(document).on('click', '#gclid-toggle-secret', function () {
        var $field = $('#gclid_tracker_oauth_client_secret');
        var $btn   = $(this);
        if ($field.attr('type') === 'password') {
            $field.attr('type', 'text');
            $btn.text('Hide');
        } else {
            $field.attr('type', 'password');
            $btn.text('Show');
        }
    });

    // =========================================
    // Test Google Sheets connection
    // =========================================
    $(document).on('click', '#gclid-test-connection', function () {
        var $btn    = $(this);
        var $result = $('#gclid-test-result');

        $btn.prop('disabled', true);
        $result.removeClass('success error').html('<span class="gclid-spinner"></span> Testing...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gclid_test_connection',
                nonce:  config.nonce,
            },
            success: function (response) {
                if (response.success) {
                    var msg = '<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message;
                    if (response.data.sheet_names && response.data.sheet_names.length > 0) {
                        msg += '<br><small style="font-weight:400;">Tabs found: ' + response.data.sheet_names.join(', ') + '</small>';
                    }
                    $result.addClass('success').html(msg);
                } else {
                    $result.addClass('error').html(
                        '<span class="dashicons dashicons-dismiss"></span> ' + response.data.message
                    );
                }
            },
            error: function () {
                $result.addClass('error').html(
                    '<span class="dashicons dashicons-dismiss"></span> Request failed. Please try again.'
                );
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // =========================================
    // Manual sync to Google Sheets
    // =========================================
    $(document).on('click', '#gclid-manual-sync', function () {
        var $btn         = $(this);
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update" style="margin-top:4px;animation:gclid-spin 0.8s linear infinite;"></span> Syncing...'
        );

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gclid_manual_sync',
                nonce:  config.nonce,
            },
            success: function (response) {
                if (response.success) {
                    alert('✓ ' + response.data.message);
                    if (response.data.synced_count > 0) {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('Sync request failed. Please try again.');
            },
            complete: function () {
                $btn.prop('disabled', false).html(originalHtml);
            },
        });
    });

    // =========================================
    // Delete a single capture
    // =========================================
    $(document).on('click', '.gclid-delete-btn', function () {
        var $btn = $(this);
        var id   = $btn.data('id');

        if (!confirm('Delete this capture record? This cannot be undone.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action:     'gclid_delete_capture',
                nonce:      config.nonce,
                capture_id: id,
            },
            success: function (response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('Delete request failed.');
                $btn.prop('disabled', false);
            },
        });
    });

    // =========================================
    // Select all checkboxes
    // =========================================
    $(document).on('change', '#gclid-select-all', function () {
        $('.gclid-row-check').prop('checked', $(this).prop('checked'));
    });

    // =========================================
    // Apply bulk action
    // =========================================
    $(document).on('click', '#gclid-apply-bulk', function () {
        var action = $('#gclid-bulk-action').val();
        if (!action) {
            alert('Please select a bulk action.');
            return;
        }

        var ids = [];
        $('.gclid-row-check:checked').each(function () {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert('Please select at least one record.');
            return;
        }

        var confirmMsg = action === 'delete'
            ? 'Delete ' + ids.length + ' record(s)? This cannot be undone.'
            : 'Sync ' + ids.length + ' record(s) to Google Sheets?';

        if (!confirm(confirmMsg)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action:      'gclid_bulk_action',
                nonce:       config.nonce,
                bulk_action: action,
                ids:         ids,
            },
            success: function (response) {
                if (response.success) {
                    alert('✓ ' + response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('Bulk action request failed.');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Apply');
            },
        });
    });

})(jQuery);
