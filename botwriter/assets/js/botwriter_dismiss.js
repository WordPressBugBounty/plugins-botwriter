jQuery(document).ready(function($) {
    // Dismiss welcome banner
    $(document).on('click', '#botwriter-welcome-dismiss', function() {
        var nonce = $(this).data('nonce');
        $('#botwriter-welcome-banner').fadeOut(300, function() {
            $(this).remove();
        });
        $.post(botwriterData.ajaxurl, {
            action: 'botwriter_dismiss_welcome',
            security: nonce
        });
    });

    // Dismiss review notice
    $(document).on('click', '.botwriter-review-notice .notice-dismiss', function() {
        var data = {
            action: 'botwriter_dismiss_review_notice',
            security: botwriterData.nonce
        };

        console.log("Dismissing review notice:", data);

        $.post(botwriterData.ajaxurl, data, function(response) {
            if (response.success) {
                $('.botwriter-review-notice').fadeOut();
            }
        });
    });

    // Dismiss announcements
    $(document).on('click', '.botwriter-dismiss-announcement', function() {
        var announcement_id = $(this).data('announcement-id');

        var data = {
            action: 'botwriter_dismiss_announcement',
            security: botwriterData.nonce,
            announcement_id: announcement_id
        };

        console.log("Dismissing announcement:", data);

        $.post(botwriterData.ajaxurl, data, function(response) {
            if (response.success) {
                location.reload();  
            }
        });
    });

    // Reset STOPFORMANY state
    $(document).on('click', '#botwriter-stopformany-reset', function() {
        var btn = $(this);
        var spinner = $('#botwriter-stopformany-spinner');
        var successMessage = btn.data('success-message') || 'Tasks resumed!';
        var errorMessage = btn.data('error-message') || 'Error resetting. Please try again.';
        var networkError = btn.data('network-error') || 'Network error. Please try again.';

        btn.prop('disabled', true);
        spinner.addClass('is-active');

        $.post(botwriterData.ajaxurl, {
            action: 'botwriter_stopformany_reset',
            security: btn.data('nonce')
        }, function(r) {
            spinner.removeClass('is-active');
            if (r.success) {
                $('#botwriter-stopformany-notice')
                    .removeClass('notice-error').addClass('notice-success')
                    .html('<p><strong>✅ ' + successMessage + '</strong></p>');
                $('a[href*="botwriter_logs"] .update-plugins').remove();
            } else {
                btn.prop('disabled', false);
                alert((r && r.data) ? r.data : errorMessage);
            }
        }).fail(function() {
            spinner.removeClass('is-active');
            btn.prop('disabled', false);
            alert(networkError);
        });
    });

    // Dismiss consecutive errors notice
    $(document).on('click', '.botwriter-dismiss-errors', function() {
        var nonce = $(this).data('nonce');
        $('#botwriter-errors-notice').fadeOut(300, function() {
            $(this).remove();
        });
        $.post(botwriterData.ajaxurl, {
            action: 'botwriter_dismiss_errors_notice',
            security: nonce
        });
    });
});