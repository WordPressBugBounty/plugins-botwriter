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
            <button type="button" class="botwriter-welcome-dismiss" id="botwriter-welcome-dismiss" data-nonce="<?php echo esc_attr( wp_create_nonce( 'botwriter_dismiss_welcome_nonce' ) ); ?>" title="<?php esc_attr_e('Dismiss', 'botwriter'); ?>">
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

// =====================================================
// STOPFORMANY — Tasks paused due to too many consecutive errors
// =====================================================

/**
 * Show a critical admin notice when STOPFORMANY is active.
 * The notice includes a "Reset & Resume" button that calls the server
 * endpoint to clear the consecutive-error counter and removes the local flag.
 */
add_action('admin_notices', 'botwriter_stopformany_notice');
function botwriter_stopformany_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!get_option('botwriter_stopformany', false)) {
        return;
    }

    $logs_url = admin_url('admin.php?page=botwriter_logs');
    $nonce    = wp_create_nonce('botwriter_stopformany_reset_nonce');
    ?>
    <div class="notice notice-error" id="botwriter-stopformany-notice" style="border-left-color:#d63638;">
        <p><strong>🛑 <?php esc_html_e('BotWriter: All tasks have been STOPPED due to too many consecutive errors!', 'botwriter'); ?></strong></p>
        <p><?php esc_html_e('The server detected 100+ consecutive task errors for your site and paused all task processing to prevent further failures.', 'botwriter'); ?></p>
        <p><?php esc_html_e('Please review your logs, fix the underlying issue (API key, provider configuration, etc.), then click the button below to resume.', 'botwriter'); ?></p>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($logs_url); ?>">
                <span class="dashicons dashicons-warning" style="vertical-align:middle; margin-right:4px;"></span>
                <?php esc_html_e('View Logs', 'botwriter'); ?>
            </a>
            <button type="button" class="button" id="botwriter-stopformany-reset" data-nonce="<?php echo esc_attr($nonce); ?>" data-success-message="<?php echo esc_attr(__('Tasks resumed! The error counter has been reset.', 'botwriter')); ?>" data-error-message="<?php echo esc_attr(__('Error resetting. Please try again.', 'botwriter')); ?>" data-network-error="<?php echo esc_attr(__('Network error. Please try again.', 'botwriter')); ?>" style="margin-left:8px;">
                <?php esc_html_e('Reset & Resume Tasks', 'botwriter'); ?>
            </button>
            <span id="botwriter-stopformany-spinner" class="spinner" style="float:none; margin-top:0;"></span>
        </p>
    </div>
    <?php
}

/**
 * AJAX: Reset the STOPFORMANY flag (calls server + clears local option).
 */
add_action('wp_ajax_botwriter_stopformany_reset', 'botwriter_stopformany_reset_handler');
function botwriter_stopformany_reset_handler() {
    check_ajax_referer('botwriter_stopformany_reset_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    // Call the server endpoint to reset the counter
    $remote_url = BOTWRITER_API_URL . 'redis_reset_stop.php';
    $ssl_verify = get_option('botwriter_sslverify', 'yes') !== 'no';

    $response = wp_remote_post($remote_url, array(
        'timeout'   => 15,
        'sslverify' => $ssl_verify,
        'body'      => array(
            'user_domainname' => esc_url(get_site_url()),
            'site_token'      => get_option('botwriter_site_token', ''),
        ),
    ));

    $server_ok = false;
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            $server_ok = true;
        }
    }

    // Always clear the local flag even if the server call failed
    // (the server will reject the next task if the counter is still high,
    //  re-activating the flag automatically)
    delete_option('botwriter_stopformany');

    botwriter_log('STOPFORMANY reset requested', [
        'server_ok' => $server_ok,
    ]);

    if ($server_ok) {
        wp_send_json_success();
    } else {
        // Local flag cleared; warn that server reset may have failed
        wp_send_json_success(['message' => 'Local flag cleared. Server reset may have failed — tasks will resume but may be stopped again if errors persist.']);
    }
}

/**
 * Check for consecutive errors in logs and show warning notice
 * Shows when 5+ consecutive errors are found in the most recent logs
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
    
    if ($consecutive_errors < 5) {
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
    
}

/**
 * Count consecutive errors from the most recent logs
 * 
 * @return int Number of consecutive errors
 */
function botwriter_count_consecutive_errors() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_logs';
    
    // Get the last 20 logs ordered by most recent first
    $recent_logs = $wpdb->get_results(
        "SELECT task_status FROM {$table_name} ORDER BY id DESC LIMIT 20",
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

/**
 * Show a warning when WordPress cron is disabled and BotWriter has active tasks.
 */
add_action('admin_notices', 'botwriter_wp_cron_disabled_notice');
function botwriter_wp_cron_disabled_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('botwriter_should_show_wp_cron_disabled_warning') || !botwriter_should_show_wp_cron_disabled_warning()) {
        return;
    }

    $active_tasks = function_exists('botwriter_get_enabled_tasks_count') ? (int) botwriter_get_enabled_tasks_count() : 0;
    $settings_url = admin_url('admin.php?page=botwriter_settings');

    echo '<div class="notice notice-warning">';
    echo '<p><strong>' . esc_html__('BotWriter scheduling warning:', 'botwriter') . '</strong> ';
    echo esc_html(sprintf(
        /* translators: %d: number of active tasks */
        _n(
            'WordPress cron is disabled (DISABLE_WP_CRON) and BotWriter has %d active task.',
            'WordPress cron is disabled (DISABLE_WP_CRON) and BotWriter has %d active tasks.',
            $active_tasks,
            'botwriter'
        ),
        $active_tasks
    ));
    echo ' ' . esc_html__('Automatic task execution will not run unless a real server cron triggers wp-cron.php.', 'botwriter') . '</p>';
    echo '<p>' . esc_html__('Please enable WP-Cron or ask your hosting administrator to configure server cron.', 'botwriter') . ' ';
    echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Open BotWriter Settings', 'botwriter') . '</a>.</p>';
    echo '</div>';
}

?>