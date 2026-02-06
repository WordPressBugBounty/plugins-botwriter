<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add an action to the 'admin_notices' hook to execute our function
add_action('admin_notices', 'botwriter_create_alert');

// Function to create admin alerts in the WordPress dashboard
function botwriter_create_alert() {
    // Get the alerts, settings, API key, and announcements
    $alerts = get_option('botwriter_alerts');
    $settings = get_option('botwriter_settings');
    $announcements = get_option('botwriter_announcements', []);
    $welcome_dismissed = get_option('botwriter_welcome_dismissed', false);

    // Unserialize the settings if they are not an array
    $settings = $settings ? maybe_unserialize($settings) : [];

    // Check for active announcements
    $has_announcement = !empty($announcements) && array_filter($announcements, function($announcement) {
        return isset($announcement['active']) && $announcement['active'] == "1";
    });

    // Check if ANY AI provider API key is configured (multi-provider support)
    $has_any_text_key = false;
    $text_providers = [
        'botwriter_openai_api_key',
        'botwriter_anthropic_api_key',
        'botwriter_google_api_key',
        'botwriter_mistral_api_key',
        'botwriter_groq_api_key',
        'botwriter_openrouter_api_key'
    ];
    
    foreach ($text_providers as $provider_key) {
        $key_value = get_option($provider_key);
        if (!empty($key_value)) {
            if (function_exists('botwriter_decrypt_api_key')) {
                $decrypted = botwriter_decrypt_api_key($key_value);
                if (!empty($decrypted)) {
                    $has_any_text_key = true;
                    break;
                }
            } else {
                $has_any_text_key = true;
                break;
            }
        }
    }
    
    $api_key_missing = !$has_any_text_key;

    // Show welcome banner if API key is missing and not dismissed
    if ($api_key_missing && !$welcome_dismissed) {
        $settings_url = admin_url('admin.php?page=botwriter_settings');
        $robot_icon = plugin_dir_url(dirname(__FILE__)) . 'assets/images/robot_icon2.png';
        
        // Random tips to show
        $tips = [
            __('💡 Pro Tip: Get a FREE Gemini API key for text + FREE Cloudflare Workers AI for images!', 'botwriter'),
            __('💡 Pro Tip: Groq offers blazing fast AI with generous free tier - perfect for testing!', 'botwriter'),
            __('💡 Pro Tip: Use Mistral AI for great quality at lower costs than OpenAI.', 'botwriter'),
            __('💡 Pro Tip: OpenRouter gives you access to 100+ AI models with one API key!', 'botwriter'),
        ];
        $random_tip = $tips[array_rand($tips)];
        ?>
        <div class="botwriter-welcome-banner" id="botwriter-welcome-banner">
            <button type="button" class="botwriter-welcome-dismiss" id="botwriter-welcome-dismiss" title="<?php esc_attr_e('Dismiss', 'botwriter'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            
            <div class="botwriter-welcome-content">
                <div class="botwriter-welcome-icon">
                    <img src="<?php echo esc_url($robot_icon); ?>" alt="BotWriter Robot" />
                </div>
                
                <div class="botwriter-welcome-text">
                    <h2>🎉 <?php esc_html_e('Welcome to BotWriter!', 'botwriter'); ?></h2>
                    <p class="botwriter-welcome-subtitle">
                        <?php esc_html_e('Your AI-powered content creation assistant is ready to help you generate amazing posts.', 'botwriter'); ?>
                    </p>
                    
                    <div class="botwriter-welcome-steps">
                        <div class="botwriter-welcome-step">
                            <span class="step-number">1</span>
                            <span class="step-text"><?php esc_html_e('Add your AI API key', 'botwriter'); ?></span>
                        </div>
                        <div class="botwriter-welcome-step">
                            <span class="step-number">2</span>
                            <span class="step-text"><?php esc_html_e('Create your first task', 'botwriter'); ?></span>
                        </div>
                        <div class="botwriter-welcome-step">
                            <span class="step-number">3</span>
                            <span class="step-text"><?php esc_html_e('Watch the magic happen!', 'botwriter'); ?></span>
                        </div>
                    </div>
                    
                    <div class="botwriter-welcome-tip">
                        <?php echo esc_html($random_tip); ?>
                    </div>
                    
                    <div class="botwriter-welcome-actions">
                        <a href="<?php echo esc_url($settings_url); ?>" class="botwriter-welcome-btn primary">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e('Configure API Keys', 'botwriter'); ?>
                        </a>
                        <a href="https://ai.google.dev/" target="_blank" class="botwriter-welcome-btn secondary">
                            <?php esc_html_e('Get Free Gemini Key', 'botwriter'); ?> →
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CSS is now in assets/css/welcome-banner.css -->
        
        <script>
        jQuery(document).ready(function($) {
            $('#botwriter-welcome-dismiss').on('click', function() {
                $('#botwriter-welcome-banner').fadeOut(300, function() {
                    $(this).remove();
                });
                // Save dismissal via AJAX
                $.post(ajaxurl, {
                    action: 'botwriter_dismiss_welcome',
                    security: '<?php echo esc_attr( wp_create_nonce( 'botwriter_dismiss_welcome_nonce' ) ); ?>'
                });
            });
        });
        </script>
        <?php
    }

    // Display announcements/info notice (separately) if any
    if ($has_announcement) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('BotWriter Announcement:', 'botwriter') . '</strong></p>';
        if (!empty($alerts)) {
            echo '<p>' . esc_html($alerts) . '</p>';
        }
        if (!empty($announcements)) {
            foreach ($announcements as $announcement_id => $announcement) {
                if (isset($announcement['active']) && $announcement['active'] == "1") {
                    echo '<p>' . esc_html($announcement['title']) . ': ' . wp_kses_post($announcement['message']) . '</p>';
                    echo '<button data-announcement-id="' . esc_attr($announcement_id) . '" class="button botwriter-dismiss-announcement">' . esc_html__('Dismiss', 'botwriter') . '</button>';
                }
            }
        }
        echo '</div>';
    }
}

// AJAX handler for dismissing welcome banner
add_action('wp_ajax_botwriter_dismiss_welcome', 'botwriter_dismiss_welcome_handler');
function botwriter_dismiss_welcome_handler() {
    check_ajax_referer('botwriter_dismiss_welcome_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    update_option('botwriter_welcome_dismissed', true);
    wp_send_json_success();
}

// AJAX handler for dismissing announcements
function botwriter_dismiss_announcement() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    check_ajax_referer('botwriter_dismiss_nonce', 'security');

    if (isset($_POST['announcement_id'])) {
        $announcement_id = sanitize_text_field(wp_unslash($_POST['announcement_id']));
        $announcements = get_option('botwriter_announcements', []);

        if (isset($announcements[$announcement_id])) {
            $announcements[$announcement_id]['active'] = 0;
            update_option('botwriter_announcements', $announcements);
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Invalid announcement ID']);
        }
    } else {
        wp_send_json_error(['message' => 'Missing announcement ID']);
    }
}
add_action('wp_ajax_botwriter_dismiss_announcement', 'botwriter_dismiss_announcement');

// Function to add new announcements
function botwriter_announcements_add($title, $message) {
    $announcements = get_option('botwriter_announcements', []);
    $title = sanitize_text_field($title);
    $message = wp_kses_post($message);

    foreach ($announcements as $announcement) {
        if ($announcement['title'] === $title && $announcement['message'] === $message && $announcement['active'] == 1) {
            return;
        }
    }

    $announcements[] = [
        'id' => md5($title . $message . time()),
        'title' => $title,
        'message' => $message,
        'active' => 1
    ];
    update_option('botwriter_announcements', $announcements);
}

/**
 * Review prompt after 20 days of usage
 * - Stores install date on activation; here we check delta >= 20 days
 * - Shows only on BotWriter admin screens
 * - Dismiss persists via nonce-protected query param
 */
add_action('admin_init', 'botwriter_review_handle_dismiss');
function botwriter_review_handle_dismiss() {
    if (!is_admin()) {
        return;
    }
    if (isset($_GET['botwriter_dismiss_review']) && $_GET['botwriter_dismiss_review'] === '1') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'botwriter_review_nonce')) {
            return;
        }
        // Temporary dismiss for 3 days
        $until = current_time('timestamp') + DAY_IN_SECONDS * 3;
        update_option('botwriter_review_dismissed_until', $until);
        // Clean URL
        if (function_exists('wp_safe_redirect')) {
            $url = remove_query_arg(array('botwriter_dismiss_review', '_wpnonce'));
            wp_safe_redirect($url);
            exit;
        }
    }
    // Mark as already reviewed (also dismiss permanently)
    if (isset($_GET['botwriter_review_done']) && $_GET['botwriter_review_done'] === '1') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'botwriter_review_nonce')) {
            return;
        }
        update_option('botwriter_review_done', '1');
        update_option('botwriter_review_dismissed', '1');
        if (function_exists('wp_safe_redirect')) {
            $url = remove_query_arg(array('botwriter_review_done', '_wpnonce'));
            wp_safe_redirect($url);
            exit;
        }
    }
}

add_action('admin_notices', 'botwriter_review_notice');
function botwriter_review_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    // Show across all admin screens (not limited to BotWriter pages)

    // Ensure install date exists for older installs
    $install_ts = get_option('botwriter_install_date');
    if ($install_ts === false) {
        update_option('botwriter_install_date', current_time('timestamp'));
        return;
    }

    // Already dismissed permanently (legacy) or marked reviewed?
    if (get_option('botwriter_review_dismissed') === '1') {
        return;
    }
    if (get_option('botwriter_review_done') === '1') {
        return;
    }
    // Temporarily dismissed until timestamp
    $dismissed_until = get_option('botwriter_review_dismissed_until');
    if (is_numeric($dismissed_until) && current_time('timestamp') < intval($dismissed_until)) {
        return;
    }
    // Already reviewed?
    if (get_option('botwriter_review_done') === '1') {
        return;
    }

    $days_since = floor((current_time('timestamp') - (int) $install_ts) / DAY_IN_SECONDS);
    if ($days_since < 20) {
        return;
    }

    // Review link (adjust slug if different on wp.org)
    $review_url = 'https://wordpress.org/support/plugin/botwriter/reviews/#new-post';
    $dismiss_url = wp_nonce_url(add_query_arg('botwriter_dismiss_review', '1'), 'botwriter_review_nonce');
    $done_url = wp_nonce_url(add_query_arg('botwriter_review_done', '1'), 'botwriter_review_nonce');

    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>' . esc_html__('Enjoying BotWriter?', 'botwriter') . '</strong> ';
    echo esc_html__('This plugin is free. If it’s useful to you, we’d really appreciate a 5-star review — it helps us keep improving. Thank you!', 'botwriter') . '</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($review_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Leave a 5‑star review', 'botwriter') . '</a> ';
    echo '<a class="button" href="' . esc_url($done_url) . '">' . esc_html__('I already left a review', 'botwriter') . '</a> ';
    echo '<a class="button" href="' . esc_url($dismiss_url) . '">' . esc_html__('Remind me later', 'botwriter') . '</a></p>';
    echo '</div>';
}

/**
 * Check for consecutive errors in logs and show warning notice
 * Shows when 4+ consecutive errors are found in the most recent logs
 * Dismissable but reappears if errors continue after a successful log
 */
add_action('admin_notices', 'botwriter_consecutive_errors_notice');
function botwriter_consecutive_errors_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if dismissed
    if (get_option('botwriter_errors_notice_dismissed', false)) {
        return;
    }
    
    // Check for consecutive errors
    $consecutive_errors = botwriter_count_consecutive_errors();
    
    if ($consecutive_errors < 4) {
        return;
    }
    
    $logs_url = admin_url('admin.php?page=botwriter_logs');
    $dismiss_nonce = wp_create_nonce('botwriter_dismiss_errors_nonce');
    
    echo '<div class="notice notice-error" id="botwriter-errors-notice">';
    echo '<p><strong>⚠️ ' . esc_html__('BotWriter: Multiple consecutive errors detected!', 'botwriter') . '</strong></p>';
    echo '<p>' . sprintf(
        /* translators: %d is the number of consecutive errors */
        esc_html__('The last %d log entries have errors. Please check your configuration and logs.', 'botwriter'),
        $consecutive_errors
    ) . '</p>';
    echo '<p>';
    echo '<a class="button button-primary" href="' . esc_url($logs_url) . '">';
    echo '<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>';
    echo esc_html__('View Logs', 'botwriter');
    echo '</a> ';
    echo '<button type="button" class="button botwriter-dismiss-errors" data-nonce="' . esc_attr($dismiss_nonce) . '">';
    echo esc_html__('Dismiss', 'botwriter');
    echo '</button>';
    echo '</p>';
    echo '</div>';
    
    // Inline script for dismiss
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.botwriter-dismiss-errors').on('click', function() {
            var nonce = $(this).data('nonce');
            $('#botwriter-errors-notice').fadeOut(300, function() {
                $(this).remove();
            });
            $.post(ajaxurl, {
                action: 'botwriter_dismiss_errors_notice',
                security: nonce
            });
        });
    });
    </script>
    <?php
}

/**
 * Count consecutive errors from the most recent logs
 * 
 * @return int Number of consecutive errors
 */
function botwriter_count_consecutive_errors() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_logs';
    
    // Get the last 10 logs ordered by most recent first
    $recent_logs = $wpdb->get_results(
        "SELECT task_status FROM {$table_name} ORDER BY id DESC LIMIT 10",
        ARRAY_A
    );
    
    if (empty($recent_logs)) {
        return 0;
    }
    
    $consecutive_errors = 0;
    
    foreach ($recent_logs as $log) {
        if ($log['task_status'] === 'error') {
            $consecutive_errors++;
        } else {
            // Stop counting when we hit a non-error
            break;
        }
    }
    
    return $consecutive_errors;
}

/**
 * AJAX handler for dismissing errors notice
 */
add_action('wp_ajax_botwriter_dismiss_errors_notice', 'botwriter_dismiss_errors_notice_handler');
function botwriter_dismiss_errors_notice_handler() {
    check_ajax_referer('botwriter_dismiss_errors_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    update_option('botwriter_errors_notice_dismissed', true);
    wp_send_json_success();
}

/**
 * Reset the errors notice dismissed flag
 * Called when a successful (non-error) log is registered
 */
function botwriter_reset_errors_notice_dismissed() {
    delete_option('botwriter_errors_notice_dismissed');
}

?>