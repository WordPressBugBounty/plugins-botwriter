/**
 * BotWriter Debug Log panel (Settings > General).
 *
 * Handles refresh/clear actions for wp-content/uploads/botwriter-debug.log.
 */
jQuery(function ($) {
    var $viewer = $('#botwriter-debug-log-viewer');
    var $status = $('#botwriter-debug-log-status');

    if (!$viewer.length || !$status.length) {
        return;
    }

    var cfg = window.botwriter_debug_log || {};
    var i18n = cfg.i18n || {};
    var ajaxUrl = cfg.ajax_url || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    var nonce = cfg.nonce || $('#botwriter_settings_nonce').val() || '';

    function t(key, fallback) {
        return Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : fallback;
    }

    function fmtBytes(value) {
        var n = parseInt(value, 10);
        if (!n) {
            return '0 B';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1024 * 1024) {
            return (n / 1024).toFixed(1) + ' KB';
        }
        return (n / 1024 / 1024).toFixed(2) + ' MB';
    }

    function setStatus(text) {
        $status.text(text || '');
    }

    function refreshDebugLog() {
        if (!ajaxUrl) {
            setStatus(t('request_failed', 'Request failed.'));
            return;
        }

        setStatus(t('loading', 'Loading...'));

        $.post(ajaxUrl, {
            action: 'botwriter_debug_log_fetch',
            nonce: nonce
        }).done(function (resp) {
            if (resp && resp.success) {
                var payload = resp.data || {};
                $viewer.val(payload.content || '');
                $viewer.scrollTop($viewer[0].scrollHeight);

                var msg = t('size', 'Size:') + ' ' + fmtBytes(payload.size);
                if (payload.truncated) {
                    msg += ' - ' + t('showing_last', 'showing last 512 KB');
                }
                if (!payload.enabled) {
                    msg += ' - ' + t('logging_off', 'logging is OFF');
                }
                setStatus(msg);
            } else {
                var err = resp && resp.data && resp.data.message ? resp.data.message : t('error', 'Error.');
                setStatus(err);
            }
        }).fail(function () {
            setStatus(t('request_failed', 'Request failed.'));
        });
    }

    $('#botwriter-debug-log-refresh').on('click', function () {
        refreshDebugLog();
    });

    $('#botwriter-debug-log-clear').on('click', function () {
        if (!window.confirm(t('confirm_clear', 'Clear the debug log file?'))) {
            return;
        }

        setStatus(t('clearing', 'Clearing...'));

        $.post(ajaxUrl, {
            action: 'botwriter_debug_log_clear',
            nonce: nonce
        }).done(function (resp) {
            if (resp && resp.success) {
                $viewer.val('');
                var message = resp.data && resp.data.message ? resp.data.message : t('cleared', 'Log cleared.');
                setStatus(message);
            } else {
                var err = resp && resp.data && resp.data.message ? resp.data.message : t('error', 'Error.');
                setStatus(err);
            }
        }).fail(function () {
            setStatus(t('request_failed', 'Request failed.'));
        });
    });

    // Auto-load on General tab open.
    if ($('#main-tab-general').is(':visible') || $('#main-tab-general').hasClass('active')) {
        refreshDebugLog();
    } else {
        $(document).on('click', '[data-main-tab="general"], a[href="#main-tab-general"]', function () {
            setTimeout(refreshDebugLog, 50);
        });
    }
});
