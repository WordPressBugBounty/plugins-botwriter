jQuery(document).ready(function($) {
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
});