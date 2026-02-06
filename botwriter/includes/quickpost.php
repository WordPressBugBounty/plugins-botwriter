<?php
if (!defined('ABSPATH')) { exit; }

// AJAX: create task and enqueue immediately
add_action('wp_ajax_botwriter_quick_create', 'botwriter_quick_create');
function botwriter_quick_create() {
    if (!current_user_can('manage_options')) { wp_send_json_error('forbidden'); }
    check_ajax_referer('botwriter_quickpost_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_tasks';

    // Reuse the same schema/validation as normal form
    // Force defaults for Write Now: today and 1 time
    // Use gmdate to satisfy WPCS (day name in English); current_time('timestamp') already accounts for WP tz
    $today = gmdate('l', current_time('timestamp'));
    $days = $today;
    $times_per_day = 1;

    // Whitelist for post_status
    $raw_status = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'publish';
    $allowed_status = array('publish','draft','pending','private','future');
    $safe_status = in_array($raw_status, $allowed_status, true) ? $raw_status : 'publish';

    $item = array(
        'task_name'         => isset($_POST['task_name']) ? sanitize_text_field(wp_unslash($_POST['task_name'])) : '',
        'post_status'       => $safe_status,
        'days'              => $days,
        'times_per_day'     => $times_per_day,
        'writer'            => isset($_POST['writer']) ? sanitize_text_field(wp_unslash($_POST['writer'])) : 'orion',
        'narration'         => isset($_POST['narration']) ? sanitize_text_field(wp_unslash($_POST['narration'])) : 'Descriptive',
        'post_length'       => isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : '800',
        'custom_post_length'=> isset($_POST['custom_post_length']) ? sanitize_text_field(wp_unslash($_POST['custom_post_length'])) : '',
        'custom_style'      => isset($_POST['custom_style']) ? sanitize_text_field(wp_unslash($_POST['custom_style'])) : '',
        'post_language'     => isset($_POST['post_language']) ? sanitize_text_field(wp_unslash($_POST['post_language'])) : '',
        'website_type'      => isset($_POST['website_type']) ? sanitize_text_field(wp_unslash($_POST['website_type'])) : 'ai',
    'website_name'      => '',
    'domain_name'       => isset($_POST['domain_name']) ? esc_url_raw(wp_unslash($_POST['domain_name'])) : '',
        'category_id'       => isset($_POST['category_id']) ? array_map('intval', (array) wp_unslash($_POST['category_id'])) : array(),
        'website_category_id'=> isset($_POST['website_category_id']) ? array_map('intval', (array) wp_unslash($_POST['website_category_id'])) : array(),
        'website_category_name'=> isset($_POST['website_category_name']) ? sanitize_text_field(wp_unslash($_POST['website_category_name'])) : '',
        'aigenerated_title'  => '',
        'aigenerated_content'=> '',
        'aigenerated_tags'   => '',
        'aigenerated_image'  => '',
        'post_count'         => 1,
        'post_order'         => '',
        'title_prompt'       => isset($_POST['title_prompt']) ? sanitize_text_field(wp_unslash($_POST['title_prompt'])) : '',
        'content_prompt'     => isset($_POST['content_prompt']) ? sanitize_text_field(wp_unslash($_POST['content_prompt'])) : '',
        'tags_prompt'        => isset($_POST['tags_prompt']) ? sanitize_text_field(wp_unslash($_POST['tags_prompt'])) : '',
        'image_prompt'       => isset($_POST['image_prompt']) ? sanitize_text_field(wp_unslash($_POST['image_prompt'])) : '',
        'image_generating_status' => '',
    'author_selection'   => isset($_POST['author_selection']) ? sanitize_text_field(wp_unslash($_POST['author_selection'])) : '',
    'task_type'          => 'writenow',
        'news_keyword'       => isset($_POST['news_keyword']) ? sanitize_text_field(wp_unslash($_POST['news_keyword'])) : '',
        'news_country'       => isset($_POST['news_country']) ? sanitize_text_field(wp_unslash($_POST['news_country'])) : '',
        'news_language'      => isset($_POST['news_language']) ? sanitize_text_field(wp_unslash($_POST['news_language'])) : '',
        'news_time_published'=> isset($_POST['news_time_published']) ? sanitize_text_field(wp_unslash($_POST['news_time_published'])) : '',
        'news_source'        => isset($_POST['news_source']) ? sanitize_text_field(wp_unslash($_POST['news_source'])) : '',
        'rss_source'         => isset($_POST['rss_source']) ? sanitize_text_field(wp_unslash($_POST['rss_source'])) : '',
        'ai_keywords'        => isset($_POST['ai_keywords']) ? sanitize_text_field(wp_unslash($_POST['ai_keywords'])) : '',
        'disable_ai_images'  => isset($_POST['disable_ai_images']) ? intval(wp_unslash($_POST['disable_ai_images'])) : 0,
        'template_id'        => isset($_POST['template_id']) && !empty($_POST['template_id']) ? intval(wp_unslash($_POST['template_id'])) : null,
    );

    // Convert arrays to comma strings
    $item['category_id'] = implode(',', $item['category_id']);
    $item['website_category_id'] = implode(',', $item['website_category_id']);

    // Validate
    if (!function_exists('botwriter_validate_website')) { wp_send_json_error('validator-missing'); }
    $validation = botwriter_validate_website($item, true);
    if ($validation !== true) {
        wp_send_json_error($validation);
    }

    // Insert task
    $inserted = $wpdb->insert($table_name, $item);
    if ($inserted === false) {
        wp_send_json_error('db-error: ' . $wpdb->last_error);
    }
    $task_id = intval($wpdb->insert_id);

    // Immediately mark it as executed today so cron won't pick it and create another log
    $wpdb->update(
        $table_name,
        array(
            'execution_count'    => 1,
            'last_execution_date'=> current_time('Y-m-d'),
            'last_execution_time'=> current_time('Y-m-d H:i:s'),
        ),
        array('id' => $task_id),
        array('%d','%s','%s'),
        array('%d')
    );

    // Build initial event/log and kick phase 1
    $event = $item;
    $event['task_status'] = 'pending';
    $event['id_task'] = $task_id;
    $event['intentosfase1'] = 0;

    if (!function_exists('botwriter_logs_register')) { wp_send_json_error('logs-missing'); }
    $log_id = botwriter_logs_register($event);
    $event['id'] = $log_id;

    if (!function_exists('botwriter_send1_data_to_server')) { wp_send_json_error('send1-missing'); }
    botwriter_send1_data_to_server($event);

    wp_send_json_success(array('task_id' => $task_id, 'log_id' => $log_id));
}

// AJAX: poll status and try advancing phase 2
add_action('wp_ajax_botwriter_quick_poll', 'botwriter_quick_poll');
function botwriter_quick_poll() {
    if (!current_user_can('manage_options')) { wp_send_json_error('forbidden'); }
    check_ajax_referer('botwriter_quickpost_nonce', 'nonce');

    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    if ($log_id <= 0) { wp_send_json_error('invalid-log'); }

    if (!function_exists('botwriter_logs_get')) { wp_send_json_error('logs-missing'); }
    $log = botwriter_logs_get($log_id);
    if (!$log) { wp_send_json_error('log-not-found'); }

    $status = $log['task_status'];

    // Try to advance phase 2 when pending/inqueue/direct_queued/processing
    // send2 will handle routing to server or Action Scheduler based on status
    if (in_array($status, array('pending', 'inqueue', 'direct_queued', 'processing'), true)) {
        if (!function_exists('botwriter_send2_data_to_server')) { wp_send_json_error('send2-missing'); }
        botwriter_send2_data_to_server($log);
        // reload
        $log = botwriter_logs_get($log_id);
        $status = $log['task_status'];
    }

    $progress = 10; // default
    if ($status === 'inqueue') { $progress = 60; }
    if ($status === 'direct_queued') { $progress = 30; }
    if ($status === 'processing') { $progress = 60; }
    if ($status === 'completed') { $progress = 100; }
    if ($status === 'error') { $progress = 100; }

    $edit_link = '';
    $view_link = '';
    if (!empty($log['id_post_published'])) {
        $pid = intval($log['id_post_published']);
        $edit_link = get_edit_post_link($pid, '');
        $view_link = get_permalink($pid);
    }

    $response = array(
        'status' => $status,
        'progress' => $progress,
        'id_post_published' => isset($log['id_post_published']) ? intval($log['id_post_published']) : 0,
        'error' => isset($log['error']) ? $log['error'] : '',
        'edit_link' => $edit_link,
        'view_link' => $view_link,
    );

    wp_send_json_success($response);
}

// AJAX: retry phase 1 for an errored quickpost log
add_action('wp_ajax_botwriter_quick_retry', 'botwriter_quick_retry');
function botwriter_quick_retry() {
    if (!current_user_can('manage_options')) { wp_send_json_error('forbidden'); }
    check_ajax_referer('botwriter_quickpost_nonce', 'nonce');

    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    if ($log_id <= 0) { wp_send_json_error('invalid-log'); }

    if (!function_exists('botwriter_logs_get')) { wp_send_json_error('logs-missing'); }
    $log = botwriter_logs_get($log_id);
    if (!$log) { wp_send_json_error('log-not-found'); }

    // Ensure we resend full task data (prompts, ai_keywords, etc.) by merging with source task
    global $wpdb;
    $table_tasks = $wpdb->prefix . 'botwriter_tasks';
    if (!empty($log['id_task'])) {
        $task_row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $table_tasks . ' WHERE id = %d',
                intval($log['id_task'])
            ),
            ARRAY_A
        );
        if (is_array($task_row)) {
            // Avoid overriding log id with task id
            unset($task_row['id']);
            // Task fields should be present; let log engine fields override when colliding
            $log = array_merge($task_row, $log);
        }
    }

    // Prepare for a new phase 1 dispatch: clear error and mark pending
    $log['task_status'] = 'pending';
    $log['error'] = '';
    // Optionally clear server id so a fresh queue id is assigned
    $log['id_task_server'] = 0;

    if (!function_exists('botwriter_logs_register')) { wp_send_json_error('logs-missing'); }
    botwriter_logs_register($log, $log_id);

    if (!function_exists('botwriter_send1_data_to_server')) { wp_send_json_error('send1-missing'); }
    botwriter_send1_data_to_server($log);

    wp_send_json_success(array('log_id' => $log_id));
}

// AJAX: cancel and delete the quickpost task and its log
add_action('wp_ajax_botwriter_quick_cancel', 'botwriter_quick_cancel');
function botwriter_quick_cancel() {
    if (!current_user_can('manage_options')) { wp_send_json_error('forbidden'); }
    check_ajax_referer('botwriter_quickpost_nonce', 'nonce');

    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $log_id  = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    if ($task_id <= 0 || $log_id <= 0) { wp_send_json_error('invalid-ids'); }

    global $wpdb;
    $table_tasks = $wpdb->prefix . 'botwriter_tasks';
    $table_logs  = $wpdb->prefix . 'botwriter_logs';

    // Only allow cancel for Write now tasks for safety
    $task = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT id, task_type FROM ' . $table_tasks . ' WHERE id = %d',
            $task_id
        ),
        ARRAY_A
    );
    if (!$task || (isset($task['task_type']) && $task['task_type'] !== 'writenow')) {
        wp_send_json_error('not-writenow');
    }

    // Delete log and task
    $wpdb->delete($table_logs, array('id' => $log_id), array('%d'));
    $wpdb->delete($table_tasks, array('id' => $task_id), array('%d'));

    wp_send_json_success(array('deleted' => true));
}

// Page handler rendering identical form and AJAX UI
function botwriter_quick_post_page_handler() {
    if (!current_user_can('manage_options')) { return; }

    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_tasks';

    // Defaults similar to posts.php $default
    $item = array(
        'id' => 0,
        /* translators: %d: next quick post number */
        'task_name' => sprintf(__('Quick Post %d', 'botwriter'), $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE website_type = 'ai'") + 1),
        'writer' => 'orion',
        'narration' => 'Descriptive',
        'custom_style' => '',
        'post_language' => '',
        'post_length' => '800',
        'custom_post_length' => '',
        'post_status' => 'publish',
        'days' => '',
        'times_per_day' => 1,
        'execution_count' => 0,
        'last_execution_date' => null,
        'website_type' => 'ai',
        'website_name' => '',
        'domain_name' => '',
        'category_id' => '',
        'website_category_id' => '',
        'website_category_name' => '',
        'aigenerated_title' => '',
        'aigenerated_content' => '',
        'aigenerated_tags' => '',
        'aigenerated_image' => '',
        'post_count' => '',
        'post_order' => '',
        'title_prompt' => '',
        'content_prompt' => '',
        'tags_prompt' => '',
        'image_prompt' => '',
        'image_generating_status' => '',
        'author_selection' => '',
        'news_keyword' => '',
        'news_country' => '',
        'news_language' => '',
        'news_time_published' => '',
        'news_source' => '',
        'rss_source' => '',
        'ai_keywords' => '',
    );

    // Register the same meta box handler for this page render
    add_meta_box('botwriter_post_form_meta_box', __('Task Form', 'botwriter'), 'botwriter_post_form_meta_box_handler', 'botwriter_automatic_post_new', 'normal', 'default');

    echo '<div class="wrap">';
    echo '<h2>' . esc_html__('Write now', 'botwriter') . '</h2>';
    echo '<p class="description" style="max-width:800px;">' .
        esc_html__('This page is for writing a single article immediately. For recurring schedules or multiple runs, use', 'botwriter') .
        ' <a href="' . esc_url(admin_url('admin.php?page=botwriter_addnew_page')) . '">' . esc_html__('New task', 'botwriter') . '</a>.' .
        '</p>';

    echo '<form id="botwriter-quick-form" method="post">';
    echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('botwriter_quickpost_nonce')) . '">';
    echo '<div class="metabox-holder" id="poststuff"><div id="post-body"><div id="post-body-content">';
    do_meta_boxes('botwriter_automatic_post_new', 'normal', $item);
    // Progress bar should appear above the buttons
    $bw_video_url = esc_url( BOTWRITER_URL . 'assets/images/bot_working.mp4');
    $bw_done_url  = esc_url( BOTWRITER_URL . 'assets/images/robot_icon2.png');
    echo '<div id="bwqp-progress" style="display:none;margin-top:10px;max-width:900px;">';
    // Row: left media, right steps/messages
    echo '<div id="bwqp-row" style="display:flex; align-items:flex-start; gap:16px;">';
    // Left media (no borders, left aligned)
    echo '<div id="bwqp-media" style="display:none; width:256px;">';
    echo '<video id="bwqp-video" width="256" height="256" muted playsinline loop style="display:none;">';
    echo '<source src="' . esc_url( $bw_video_url ) . '" type="video/mp4" />';
    echo '</video>';
    echo '<img id="bwqp-done" src="' . esc_url( $bw_done_url ) . '" width="256" height="256" alt="Done" style="display:none;" />';
    echo '</div>';
    // Right messages/steps container
    echo '<div id="bwqp-steps" style="flex:1 1 auto; min-height:256px; padding-top:6px;">';
    echo '</div>';
    echo '</div>'; // close row
    // Progress bar and status inside the progress container
    echo '<div style="background:#e5e5e5;border-radius:4px;overflow:hidden;">';
    echo '<div id="bwqp-bar" style="width:0%;background:#2271b1;color:#fff;padding:6px 10px;transition:width .3s;">0%</div>';
    echo '</div>';
    echo '<p id="bwqp-status" style="margin-top:6px;"></p>';
    echo '</div>'; // close #bwqp-progress
    echo '<p class="submit" style="margin-top:12px;">';
    echo '<button type="button" id="botwriter-quick-create" class="button button-primary">' . esc_html__('Create Now', 'botwriter') . '</button> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=botwriter_automatic_posts')) . '">' . esc_html__('Back to Tasks', 'botwriter') . '</a>';
    echo '</p>';
    echo '</div></div></div>';
    echo '</form>';
    echo '</div>';
}
