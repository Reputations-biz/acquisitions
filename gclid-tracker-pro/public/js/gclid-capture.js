/**
 * GCLID Tracker Pro - Frontend Capture Script
 *
 * Captures GCLID, FBCLID, MSCLKID, and UTM parameters from
 * the current URL on page load. Uses sessionStorage to ensure
 * only one capture per browser session. Sends data to the
 * WordPress backend via AJAX for storage and Google Sheets sync.
 *
 * @package GCLID_Tracker_Pro
 * @since 1.0.0
 */

(function () {
    'use strict';

    // Bail if config not available
    if (typeof gclidTrackerConfig === 'undefined') {
        return;
    }

    var config = gclidTrackerConfig;
    var SESSION_KEY = 'gclid_tracker_captured';

    /**
     * Get a URL parameter by name
     *
     * @param {string} name Parameter name
     * @returns {string} Parameter value or empty string
     */
    function getUrlParam(name) {
        var params = new URLSearchParams(window.location.search);
        return params.get(name) || '';
    }

    /**
     * Check if this session has already been captured
     *
     * @returns {boolean}
     */
    function isAlreadyCaptured() {
        try {
            return sessionStorage.getItem(SESSION_KEY) === '1';
        } catch (e) {
            // sessionStorage not available (e.g., private browsing restrictions)
            return false;
        }
    }

    /**
     * Mark this session as captured
     */
    function markCaptured() {
        try {
            sessionStorage.setItem(SESSION_KEY, '1');
        } catch (e) {
            // Silently fail
        }
    }

    /**
     * Collect all tracking parameters from the URL
     *
     * @returns {object} Tracking data
     */
    function collectData() {
        var data = {
            gclid: getUrlParam('gclid'),
            landing_page: window.location.href,
            referrer: document.referrer || '',
        };

        // Capture Facebook Click ID
        if (config.captureFbclid) {
            data.fbclid = getUrlParam('fbclid');
        }

        // Capture Microsoft Click ID
        if (config.captureMsclkid) {
            data.msclkid = getUrlParam('msclkid');
        }

        // Capture UTM parameters
        if (config.captureUtms) {
            data.utm_source = getUrlParam('utm_source');
            data.utm_medium = getUrlParam('utm_medium');
            data.utm_campaign = getUrlParam('utm_campaign');
            data.utm_term = getUrlParam('utm_term');
            data.utm_content = getUrlParam('utm_content');
        }

        return data;
    }

    /**
     * Check if collected data has any tracking parameters worth saving
     *
     * @param {object} data Collected data
     * @returns {boolean}
     */
    function hasTrackingData(data) {
        if (config.captureAll) {
            return true;
        }

        return !!(
            data.gclid ||
            data.fbclid ||
            data.msclkid ||
            data.utm_source ||
            data.utm_medium ||
            data.utm_campaign
        );
    }

    /**
     * Send capture data to the server via AJAX
     *
     * @param {object} data Capture data
     */
    function sendCapture(data) {
        var formData = new FormData();
        formData.append('action', 'gclid_capture');
        formData.append('nonce', config.nonce);

        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }

        // Use Fetch API if available, fallback to XMLHttpRequest
        if (typeof fetch !== 'undefined') {
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true,
            }).catch(function () {
                // Silently fail - don't disrupt user experience
            });
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', config.ajaxUrl, true);
            xhr.withCredentials = true;
            xhr.send(formData);
        }
    }

    /**
     * Main initialization
     */
    function init() {
        // Don't capture if already captured this session
        if (isAlreadyCaptured()) {
            return;
        }

        // Collect tracking data
        var data = collectData();

        // Check if there's anything worth capturing
        if (!hasTrackingData(data)) {
            return;
        }

        // Mark session as captured before sending
        markCaptured();

        // Small delay to not block page rendering
        setTimeout(function () {
            sendCapture(data);
        }, 100);
    }

    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
