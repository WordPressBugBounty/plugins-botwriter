<?php
/**
 * BotWriter SEO — bulk actions undo snapshots and restore flow.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_bulk_undo_tables_ready() {
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    global $wpdb;
    $runs = botwriter_seo_table('bulk_undo_runs');
    $items = botwriter_seo_table('bulk_undo_items');

    $ready = (
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runs)) === $runs
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items)) === $items
    );

    return $ready;
}

function botwriter_seo_bulk_undo_runs_table() {
    return botwriter_seo_table('bulk_undo_runs');
}

function botwriter_seo_bulk_undo_items_table() {
    return botwriter_seo_table('bulk_undo_items');
}

function botwriter_seo_bulk_action_label($action) {
    $labels = array(
        'regen_meta_description' => __('Refresh meta descriptions', 'botwriter'),
        'refresh_intro' => __('Strengthen intro & keyword placement', 'botwriter'),
        'expand_content' => __('Expand thin content', 'botwriter'),
        'normalize_headings' => __('Normalize heading levels', 'botwriter'),
        'regen_seo_title' => __('Refresh SEO titles', 'botwriter'),
        'regen_alt_text' => __('Fill missing image ALT', 'botwriter'),
        'rebuild_internal_links' => __('Rebuild internal links', 'botwriter'),
        'add_external_references' => __('Add external references', 'botwriter'),
        'regen_faq' => __('Generate FAQ blocks', 'botwriter'),
        'rewrite_slug' => __('Rewrite slug for SEO', 'botwriter'),
        'embed_index' => __('Rebuild semantic index', 'botwriter'),
    );

    $action = sanitize_key($action);
    if (isset($labels[$action])) {
        return $labels[$action];
    }

    return ucwords(str_replace('_', ' ', $action));
}

function botwriter_seo_bulk_undo_revert_run($run_id) {
    return botwriter_seo_bulk_undo_restore_run($run_id);
}

function botwriter_seo_bulk_undo_type_for_action($action) {
    $map = array(
        'regen_meta_description' => 'meta',
        'refresh_intro' => 'content',
        'expand_content' => 'content',
        'normalize_headings' => 'content',
        'regen_seo_title' => 'meta',
        'regen_alt_text' => 'media',
        'rebuild_internal_links' => 'content',
        'add_external_references' => 'content',
        'regen_faq' => 'meta',
        'rewrite_slug' => 'none',
        'embed_index' => 'none',
    );

    $action = sanitize_key($action);
    return isset($map[$action]) ? $map[$action] : 'none';
}

function botwriter_seo_bulk_undo_run_id_from_args($args) {
    return is_array($args) ? absint($args['undo_run_id'] ?? 0) : 0;
}

function botwriter_seo_bulk_undo_meta_field_keys($field) {
    $active = function_exists('botwriter_seo_active_plugin') ? botwriter_seo_meta_keys(botwriter_seo_active_plugin()) : array();
    $native = function_exists('botwriter_seo_meta_keys') ? botwriter_seo_meta_keys('native') : array();
    $keys = array();

    if (!empty($active[$field])) {
        $keys[] = $active[$field];
    }
    if (!empty($native[$field])) {
        $keys[] = $native[$field];
    }

    return array_values(array_unique(array_filter($keys)));
}

function botwriter_seo_bulk_undo_delete_meta_field($post_id, $field) {
    foreach (botwriter_seo_bulk_undo_meta_field_keys($field) as $meta_key) {
        delete_post_meta($post_id, $meta_key);
    }
}

function botwriter_seo_bulk_undo_meta_field_state($post_id, $field) {
    $exists = false;
    foreach (botwriter_seo_bulk_undo_meta_field_keys($field) as $meta_key) {
        if (metadata_exists('post', $post_id, $meta_key)) {
            $exists = true;
            break;
        }
    }

    return array(
        'exists' => $exists ? 1 : 0,
        'value' => function_exists('botwriter_seo_get_meta') ? (string) botwriter_seo_get_meta($post_id, $field) : '',
    );
}

function botwriter_seo_bulk_undo_restore_meta_field($post_id, $field, $state) {
    $exists = !empty($state['exists']);
    $value = isset($state['value']) ? (string) $state['value'] : '';

    if ($exists) {
        if (function_exists('botwriter_seo_set_meta')) {
            botwriter_seo_set_meta($post_id, $field, $value);
        }
    } else {
        botwriter_seo_bulk_undo_delete_meta_field($post_id, $field);
    }
}

function botwriter_seo_bulk_undo_post_meta_state($post_id, $meta_key) {
    return array(
        'exists' => metadata_exists('post', $post_id, $meta_key) ? 1 : 0,
        'value' => get_post_meta($post_id, $meta_key, true),
    );
}

function botwriter_seo_bulk_undo_restore_post_meta_state($post_id, $meta_key, $state) {
    if (!empty($state['exists'])) {
        update_post_meta($post_id, $meta_key, $state['value']);
    } else {
        delete_post_meta($post_id, $meta_key);
    }
}

function botwriter_seo_bulk_undo_states_equal($left, $right) {
    return wp_json_encode($left) === wp_json_encode($right);
}

function botwriter_seo_bulk_undo_get_run($run_id) {
    if ($run_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return null;
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_runs');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $run_id), ARRAY_A);

    return is_array($row) ? $row : null;
}

function botwriter_seo_bulk_undo_delete_run($run_id) {
    if ($run_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return;
    }

    global $wpdb;
    $items = botwriter_seo_table('bulk_undo_items');
    $runs = botwriter_seo_table('bulk_undo_runs');

    $wpdb->delete($items, array('run_id' => $run_id), array('%d'));
    $wpdb->delete($runs, array('id' => $run_id), array('%d'));
}

function botwriter_seo_bulk_undo_create_run($job, $action, $undo_type, $args, $total = 0) {
    if (!botwriter_seo_bulk_undo_tables_ready()) {
        return 0;
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_runs');
    $payload = is_array($args) ? $args : array();

    unset($payload['undo_run_id']);
    unset($payload['undo_type']);

    $inserted = $wpdb->insert($table, array(
        'job' => sanitize_key($job),
        'action_key' => sanitize_key($action),
        'undo_type' => sanitize_key($undo_type),
        'status' => 'running',
        'args' => wp_json_encode($payload),
        'target_items' => max(0, (int) $total),
        'processed_items' => 0,
        'changed_items' => 0,
        'reverted_items' => 0,
        'created_by' => get_current_user_id() ?: null,
        'created_at' => time(),
        'finished_at' => 0,
        'reverted_at' => 0,
        'last_error' => null,
    ), array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'));

    if (!$inserted) {
        if (function_exists('botwriter_log')) {
            botwriter_log('[SEO Bulk] undo run create failed', array(
                'job' => $job,
                'action' => $action,
                'undo_type' => $undo_type,
                'db_error' => $wpdb->last_error,
            ));
        }
        return 0;
    }

    return (int) $wpdb->insert_id;
}

function botwriter_seo_bulk_undo_sync_run_state($state, $processed = array(), $cancelled = false) {
    $run_id = botwriter_seo_bulk_undo_run_id_from_args($state['args'] ?? array());
    if ($run_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return;
    }

    $run = botwriter_seo_bulk_undo_get_run($run_id);
    if (!$run) {
        return;
    }

    $changed_items = (int) ($run['changed_items'] ?? 0);
    $finished = $cancelled || (($state['status'] ?? '') === 'done');
    if ($finished && $changed_items <= 0) {
        botwriter_seo_bulk_undo_delete_run($run_id);
        return;
    }

    $status = 'running';
    if ($cancelled) {
        $status = 'cancelled';
    } elseif (($state['status'] ?? '') === 'done') {
        $status = 'completed';
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_runs');
    $wpdb->update($table, array(
        'status' => $status,
        'target_items' => max(0, (int) ($state['total'] ?? 0)),
        'processed_items' => max(0, (int) ($state['done'] ?? 0)),
        'finished_at' => $finished ? time() : 0,
    ), array('id' => $run_id), array('%s', '%d', '%d', '%d'), array('%d'));
}

add_filter('botwriter_seo_job_prepare_args_actions', function ($args, $init) {
    $args = is_array($args) ? $args : array();
    $action = sanitize_key($args['action'] ?? '');
    $undo_type = botwriter_seo_bulk_undo_type_for_action($action);

    if ($action === '' || $undo_type === 'none') {
        return $args;
    }

    $run_id = botwriter_seo_bulk_undo_create_run('actions', $action, $undo_type, $args, (int) ($init['total'] ?? 0));
    if ($run_id > 0) {
        $args['undo_run_id'] = $run_id;
        $args['undo_type'] = $undo_type;
    }

    return $args;
}, 10, 2);

add_action('botwriter_seo_job_after_tick_actions', function ($state, $processed) {
    botwriter_seo_bulk_undo_sync_run_state($state, $processed, false);
}, 10, 2);

add_action('botwriter_seo_job_cancelled_actions', function ($state) {
    botwriter_seo_bulk_undo_sync_run_state($state, array(), true);
}, 10, 1);

function botwriter_seo_bulk_undo_extract_image_entries($html, $only_missing = false) {
    if (!class_exists('DOMDocument')) {
        return array();
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . (string) $html . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$loaded) {
        return array();
    }

    $entries = array();
    $index = 0;
    foreach ($dom->getElementsByTagName('img') as $img) {
        if (!($img instanceof DOMElement)) {
            continue;
        }
        $had_alt = $img->hasAttribute('alt');
        $alt = $had_alt ? (string) $img->getAttribute('alt') : '';
        if ($only_missing && $had_alt && trim($alt) !== '') {
            $index++;
            continue;
        }
        $entries[] = array(
            'index' => $index,
            'src' => (string) $img->getAttribute('src'),
            'had_alt' => $had_alt ? 1 : 0,
            'alt' => $alt,
        );
        $index++;
    }

    return $entries;
}

function botwriter_seo_bulk_undo_capture_content_snapshot($post_id, $action, $args = array()) {
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }

    $snapshot = array(
        'type' => 'content',
        'post_content' => (string) $post->post_content,
    );

    if ($action === 'refresh_intro') {
        $snapshot['focus'] = botwriter_seo_bulk_undo_meta_field_state($post_id, 'focus');
    }

    return $snapshot;
}

function botwriter_seo_bulk_undo_capture_meta_snapshot($post_id, $action) {
    $snapshot = array(
        'type' => 'meta',
        'fields' => array(),
    );

    switch (sanitize_key($action)) {
        case 'regen_meta_description':
            $snapshot['fields']['desc'] = botwriter_seo_bulk_undo_meta_field_state($post_id, 'desc');
            break;
        case 'regen_seo_title':
            $snapshot['fields']['title'] = botwriter_seo_bulk_undo_meta_field_state($post_id, 'title');
            break;
        case 'regen_faq':
            $snapshot['fields']['_botwriter_seo_faq'] = botwriter_seo_bulk_undo_post_meta_state($post_id, '_botwriter_seo_faq');
            $snapshot['fields']['_botwriter_seo_faq_updated'] = botwriter_seo_bulk_undo_post_meta_state($post_id, '_botwriter_seo_faq_updated');
            $snapshot['fields']['_botwriter_seo_faq_visible'] = botwriter_seo_bulk_undo_post_meta_state($post_id, '_botwriter_seo_faq_visible');
            break;
    }

    return !empty($snapshot['fields']) ? $snapshot : false;
}

function botwriter_seo_bulk_undo_capture_media_snapshot($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }

    $entries = botwriter_seo_bulk_undo_extract_image_entries((string) $post->post_content, true);
    if (empty($entries)) {
        return false;
    }

    return array(
        'type' => 'media',
        'images' => $entries,
    );
}

function botwriter_seo_bulk_undo_capture_snapshot($action, $post_id, $args = array()) {
    $undo_type = botwriter_seo_bulk_undo_type_for_action($action);

    switch ($undo_type) {
        case 'content':
            return botwriter_seo_bulk_undo_capture_content_snapshot($post_id, $action, $args);
        case 'meta':
            return botwriter_seo_bulk_undo_capture_meta_snapshot($post_id, $action);
        case 'media':
            return botwriter_seo_bulk_undo_capture_media_snapshot($post_id);
    }

    return false;
}

function botwriter_seo_bulk_undo_media_snapshot_changed($post_id, $snapshot) {
    $post = get_post($post_id);
    if (!$post || empty($snapshot['images']) || !is_array($snapshot['images'])) {
        return false;
    }

    $current = botwriter_seo_bulk_undo_extract_image_entries((string) $post->post_content, false);
    if (empty($current)) {
        return false;
    }

    $used = array();
    foreach ($snapshot['images'] as $entry) {
        $matched = null;
        foreach ($current as $idx => $candidate) {
            if (isset($used[$idx])) {
                continue;
            }
            if (($entry['src'] ?? '') !== '' && ($candidate['src'] ?? '') === ($entry['src'] ?? '')) {
                $matched = $candidate;
                $used[$idx] = true;
                break;
            }
            if ($matched === null && isset($entry['index'], $candidate['index']) && (int) $candidate['index'] === (int) $entry['index']) {
                $matched = $candidate;
            }
        }
        if ($matched === null) {
            continue;
        }
        if (!botwriter_seo_bulk_undo_states_equal(
            array('had_alt' => (int) ($entry['had_alt'] ?? 0), 'alt' => (string) ($entry['alt'] ?? '')),
            array('had_alt' => (int) ($matched['had_alt'] ?? 0), 'alt' => (string) ($matched['alt'] ?? ''))
        )) {
            return true;
        }
    }

    return false;
}

function botwriter_seo_bulk_undo_snapshot_has_changed($action, $post_id, $snapshot) {
    if (!is_array($snapshot) || empty($snapshot['type'])) {
        return false;
    }

    switch ($snapshot['type']) {
        case 'content':
            $post = get_post($post_id);
            if (!$post) {
                return false;
            }
            if ((string) $post->post_content !== (string) ($snapshot['post_content'] ?? '')) {
                return true;
            }
            if (isset($snapshot['focus'])) {
                return !botwriter_seo_bulk_undo_states_equal($snapshot['focus'], botwriter_seo_bulk_undo_meta_field_state($post_id, 'focus'));
            }
            return false;

        case 'meta':
            foreach ((array) ($snapshot['fields'] ?? array()) as $field => $state) {
                if (strpos($field, '_botwriter_') === 0) {
                    $current = botwriter_seo_bulk_undo_post_meta_state($post_id, $field);
                } else {
                    $current = botwriter_seo_bulk_undo_meta_field_state($post_id, $field);
                }
                if (!botwriter_seo_bulk_undo_states_equal($state, $current)) {
                    return true;
                }
            }
            return false;

        case 'media':
            return botwriter_seo_bulk_undo_media_snapshot_changed($post_id, $snapshot);
    }

    return false;
}

function botwriter_seo_bulk_undo_store_snapshot($run_id, $post_id, $undo_type, $snapshot) {
    if ($run_id <= 0 || $post_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return false;
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_items');
    $inserted = $wpdb->insert($table, array(
        'run_id' => $run_id,
        'post_id' => $post_id,
        'undo_type' => sanitize_key($undo_type),
        'status' => 'active',
        'snapshot' => wp_json_encode($snapshot),
        'created_at' => time(),
        'reverted_at' => 0,
    ), array('%d', '%d', '%s', '%s', '%s', '%d', '%d'));

    if (!$inserted) {
        if (function_exists('botwriter_log')) {
            botwriter_log('[SEO Bulk] undo snapshot save failed', array(
                'run_id' => $run_id,
                'post_id' => $post_id,
                'undo_type' => $undo_type,
                'db_error' => $wpdb->last_error,
            ));
        }
        return false;
    }

    $runs = botwriter_seo_table('bulk_undo_runs');
    $wpdb->query($wpdb->prepare("UPDATE $runs SET changed_items = changed_items + 1 WHERE id = %d", $run_id));

    return true;
}

function botwriter_seo_bulk_undo_get_run_items($run_id, $status = '') {
    if ($run_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return array();
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_items');

    if ($status !== '') {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE run_id = %d AND status = %s ORDER BY id ASC",
            $run_id,
            sanitize_key($status)
        ), ARRAY_A);
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE run_id = %d ORDER BY id ASC",
            $run_id
        ), ARRAY_A);
    }

    return is_array($rows) ? $rows : array();
}

function botwriter_seo_bulk_undo_restore_content_snapshot($post_id, $snapshot) {
    $content = isset($snapshot['post_content']) ? (string) $snapshot['post_content'] : '';
    $ok = function_exists('botwriter_seo_bulk_update_post_content')
        ? botwriter_seo_bulk_update_post_content($post_id, $content)
        : !is_wp_error(wp_update_post(array('ID' => $post_id, 'post_content' => $content), true));

    if (!$ok) {
        return false;
    }

    if (isset($snapshot['focus'])) {
        botwriter_seo_bulk_undo_restore_meta_field($post_id, 'focus', $snapshot['focus']);
        if (function_exists('botwriter_seo_compute_score')) {
            botwriter_seo_compute_score($post_id, true);
        }
    }

    return true;
}

function botwriter_seo_bulk_undo_restore_meta_snapshot($post_id, $snapshot) {
    $restored = false;

    foreach ((array) ($snapshot['fields'] ?? array()) as $field => $state) {
        if (strpos($field, '_botwriter_') === 0) {
            botwriter_seo_bulk_undo_restore_post_meta_state($post_id, $field, $state);
        } else {
            botwriter_seo_bulk_undo_restore_meta_field($post_id, $field, $state);
        }
        $restored = true;
    }

    if ($restored && function_exists('botwriter_seo_compute_score')) {
        botwriter_seo_compute_score($post_id, true);
    }

    return $restored;
}

function botwriter_seo_bulk_undo_restore_media_snapshot($post_id, $snapshot) {
    $post = get_post($post_id);
    if (!$post || !class_exists('DOMDocument') || empty($snapshot['images']) || !is_array($snapshot['images'])) {
        return false;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . (string) $post->post_content . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$loaded) {
        return false;
    }

    $targets = array();
    $index = 0;
    foreach ($dom->getElementsByTagName('img') as $img) {
        if (!($img instanceof DOMElement)) {
            continue;
        }
        $targets[] = array(
            'node' => $img,
            'index' => $index,
            'src' => (string) $img->getAttribute('src'),
        );
        $index++;
    }

    $used = array();
    $changed = false;
    foreach ($snapshot['images'] as $entry) {
        $target_idx = null;
        foreach ($targets as $idx => $candidate) {
            if (isset($used[$idx])) {
                continue;
            }
            if (($entry['src'] ?? '') !== '' && $candidate['src'] === ($entry['src'] ?? '')) {
                $target_idx = $idx;
                break;
            }
            if ($target_idx === null && isset($entry['index']) && (int) $candidate['index'] === (int) $entry['index']) {
                $target_idx = $idx;
            }
        }

        if ($target_idx === null || empty($targets[$target_idx]['node'])) {
            continue;
        }

        $img = $targets[$target_idx]['node'];
        $used[$target_idx] = true;
        if (!empty($entry['had_alt'])) {
            $img->setAttribute('alt', (string) ($entry['alt'] ?? ''));
        } else {
            $img->removeAttribute('alt');
        }
        $changed = true;
    }

    if (!$changed) {
        return false;
    }

    $body = $dom->getElementsByTagName('div')->item(0);
    if (!($body instanceof DOMElement)) {
        return false;
    }

    $html = '';
    foreach ($body->childNodes as $node) {
        $html .= $dom->saveHTML($node);
    }

    $ok = function_exists('botwriter_seo_bulk_update_post_content')
        ? botwriter_seo_bulk_update_post_content($post_id, $html)
        : !is_wp_error(wp_update_post(array('ID' => $post_id, 'post_content' => $html), true));

    return (bool) $ok;
}

function botwriter_seo_bulk_undo_run_is_busy($run_id) {
    $run_id = absint($run_id);
    if ($run_id <= 0) {
        return false;
    }

    $job = botwriter_seo_job_state('actions');
    $job_run_id = botwriter_seo_bulk_undo_run_id_from_args($job['args'] ?? array());

    return $job_run_id === $run_id && in_array($job['status'] ?? '', array('pending', 'running'), true);
}

function botwriter_seo_bulk_undo_run_can_revert($run) {
    if (!is_array($run)) {
        return false;
    }

    $changed = max(0, (int) ($run['changed_items'] ?? 0));
    $reverted = max(0, (int) ($run['reverted_items'] ?? 0));

    return in_array($run['status'] ?? '', array('completed', 'cancelled', 'partial'), true) && $changed > $reverted;
}

function botwriter_seo_bulk_undo_item_can_revert($item) {
    return is_array($item) && sanitize_key((string) ($item['status'] ?? '')) === 'active';
}

function botwriter_seo_bulk_undo_get_item($item_id) {
    if ($item_id <= 0 || !botwriter_seo_bulk_undo_tables_ready()) {
        return null;
    }

    global $wpdb;
    $table = esc_sql(botwriter_seo_table('bulk_undo_items'));
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for use as an identifier.
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $item_id), ARRAY_A);

    return is_array($row) ? $row : null;
}

function botwriter_seo_bulk_undo_restore_item_row($item) {
    $item_id = (int) ($item['id'] ?? 0);
    $post_id = (int) ($item['post_id'] ?? 0);
    $snapshot = json_decode((string) ($item['snapshot'] ?? ''), true);

    if ($item_id <= 0 || $post_id <= 0 || !is_array($snapshot)) {
        /* translators: %d: post ID with an invalid undo snapshot */
        return new WP_Error('undo_item_invalid', sprintf(__('Invalid snapshot for post %d.', 'botwriter'), $post_id));
    }

    $ok = false;
    switch ($snapshot['type'] ?? '') {
        case 'content':
            $ok = botwriter_seo_bulk_undo_restore_content_snapshot($post_id, $snapshot);
            break;
        case 'meta':
            $ok = botwriter_seo_bulk_undo_restore_meta_snapshot($post_id, $snapshot);
            break;
        case 'media':
            $ok = botwriter_seo_bulk_undo_restore_media_snapshot($post_id, $snapshot);
            break;
    }

    if (!$ok) {
        /* translators: %d: post ID that could not be restored */
        return new WP_Error('undo_item_restore_failed', sprintf(__('Could not restore post %d.', 'botwriter'), $post_id));
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_items');
    $wpdb->update($table, array(
        'status' => 'reverted',
        'reverted_at' => time(),
    ), array('id' => $item_id), array('%s', '%d'), array('%d'));

    return array(
        'item_id' => $item_id,
        'post_id' => $post_id,
        'run_id' => (int) ($item['run_id'] ?? 0),
    );
}

function botwriter_seo_bulk_undo_refresh_run_restore_state($run_id, $last_error = null) {
    $run = botwriter_seo_bulk_undo_get_run($run_id);
    if (!$run) {
        return null;
    }

    global $wpdb;
    $table = botwriter_seo_table('bulk_undo_items');
    $reverted = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE run_id = %d AND status = %s",
        $run_id,
        'reverted'
    ));
    $active = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE run_id = %d AND status = %s",
        $run_id,
        'active'
    ));

    $status = (string) ($run['status'] ?? 'completed');
    if ($reverted > 0 && $active > 0) {
        $status = 'partial';
    } elseif ($reverted > 0 && $active === 0) {
        $status = 'reverted';
    } elseif (in_array($status, array('partial', 'reverted'), true)) {
        $status = 'completed';
    }

    $update = array(
        'status' => $status,
        'reverted_items' => $reverted,
        'reverted_at' => $reverted > 0 ? time() : 0,
    );
    $formats = array('%s', '%d', '%d');

    if ($last_error !== null) {
        $update['last_error'] = (string) $last_error;
        $formats[] = '%s';
    }

    $runs = botwriter_seo_table('bulk_undo_runs');
    $wpdb->update($runs, $update, array('id' => $run_id), $formats, array('%d'));

    return botwriter_seo_bulk_undo_get_run($run_id);
}

function botwriter_seo_bulk_undo_restore_item($item_id) {
    $item_id = absint($item_id);
    $item = botwriter_seo_bulk_undo_get_item($item_id);
    if (!$item) {
        return new WP_Error('undo_item_missing', __('Undo snapshot not found.', 'botwriter'));
    }

    $run_id = (int) ($item['run_id'] ?? 0);
    $run = botwriter_seo_bulk_undo_get_run($run_id);
    if (!$run) {
        return new WP_Error('undo_run_missing', __('Undo run not found.', 'botwriter'));
    }

    if (botwriter_seo_bulk_undo_run_is_busy($run_id)) {
        return new WP_Error('undo_run_busy', __('Finish or cancel the current bulk run before restoring it.', 'botwriter'));
    }

    if (!botwriter_seo_bulk_undo_item_can_revert($item)) {
        return new WP_Error('undo_item_inactive', __('This snapshot has already been restored.', 'botwriter'));
    }

    $restored = botwriter_seo_bulk_undo_restore_item_row($item);
    if (is_wp_error($restored)) {
        return $restored;
    }

    $run = botwriter_seo_bulk_undo_refresh_run_restore_state($run_id, '');

    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] undo restore item', array(
            'run_id' => $run_id,
            'item_id' => $item_id,
            'post_id' => (int) ($item['post_id'] ?? 0),
            'action' => (string) ($run['action_key'] ?? ''),
        ));
    }

    return array(
        'item' => botwriter_seo_bulk_undo_get_item($item_id),
        'run' => $run,
        'reverted' => 1,
        'errors' => array(),
    );
}

function botwriter_seo_bulk_undo_restore_run($run_id) {
    $run_id = absint($run_id);
    $run = botwriter_seo_bulk_undo_get_run($run_id);
    if (!$run) {
        return new WP_Error('undo_run_missing', __('Undo run not found.', 'botwriter'));
    }

    if (botwriter_seo_bulk_undo_run_is_busy($run_id)) {
        return new WP_Error('undo_run_busy', __('Finish or cancel the current bulk run before restoring it.', 'botwriter'));
    }

    $items = botwriter_seo_bulk_undo_get_run_items($run_id, 'active');
    if (empty($items)) {
        return new WP_Error('undo_run_empty', __('This run no longer has active snapshots to restore.', 'botwriter'));
    }

    $reverted = 0;
    $errors = array();

    foreach ($items as $item) {
        $restored = botwriter_seo_bulk_undo_restore_item_row($item);
        if (is_wp_error($restored)) {
            $errors[] = $restored->get_error_message();
            continue;
        }
        $reverted++;
    }

    $run = botwriter_seo_bulk_undo_refresh_run_restore_state($run_id, !empty($errors) ? implode(' | ', array_slice($errors, 0, 5)) : '');

    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] undo restore run', array(
            'run_id' => $run_id,
            'action' => (string) ($run['action_key'] ?? ''),
            'reverted' => $reverted,
            'errors' => $errors,
        ));
    }

    return array(
        'run' => $run,
        'reverted' => $reverted,
        'errors' => $errors,
    );
}

function botwriter_seo_bulk_undo_list_runs($limit = 12) {
    global $wpdb;

    $limit = max(1, min(50, (int) $limit));
    $runs_table = esc_sql(botwriter_seo_bulk_undo_runs_table());
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for use as an identifier.
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$runs_table} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);

    return is_array($rows) ? $rows : array();
}

function botwriter_seo_bulk_undo_status_badge($status) {
    switch (sanitize_key($status)) {
        case 'running':
            return array(__('Running', 'botwriter'), 'bw-chip-info');
        case 'completed':
            return array(__('Ready to restore', 'botwriter'), 'bw-chip-success');
        case 'cancelled':
            return array(__('Cancelled', 'botwriter'), 'bw-chip-warn');
        case 'reverted':
            return array(__('Restored', 'botwriter'), 'bw-chip-success');
        case 'partial':
            return array(__('Partially restored', 'botwriter'), 'bw-chip-warn');
    }

    return array(ucfirst((string) $status), 'bw-chip-muted');
}

function botwriter_seo_bulk_undo_type_badge($type) {
    switch (sanitize_key($type)) {
        case 'content':
            return array(__('Content snapshot', 'botwriter'), 'bw-chip-success');
        case 'meta':
            return array(__('Meta snapshot', 'botwriter'), 'bw-chip-info');
        case 'media':
            return array(__('Image ALT snapshot', 'botwriter'), 'bw-chip-muted');
    }

    return array(__('No snapshot', 'botwriter'), 'bw-chip-danger');
}

function botwriter_seo_bulk_undo_item_status_badge($status) {
    switch (sanitize_key($status)) {
        case 'active':
            return array(__('Pending restore', 'botwriter'), 'bw-chip-info');
        case 'reverted':
            return array(__('Restored', 'botwriter'), 'bw-chip-success');
    }

    return array(ucfirst((string) $status), 'bw-chip-muted');
}

function botwriter_seo_bulk_undo_feedback_html($kind, $message, $errors = array()) {
    $message = trim((string) $message);
    if ($message === '') {
        return '';
    }

    $kind = in_array($kind, array('success', 'warn', 'danger', 'info'), true) ? $kind : 'info';
    $icon = in_array($kind, array('warn', 'danger'), true) ? 'warning' : 'yes-alt';

    ob_start();
    echo '<div class="bw-notice ' . esc_attr($kind) . '">';
    echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
    echo '<div>' . esc_html($message) . '</div>';
    echo '</div>';

    if (!empty($errors)) {
        echo '<div class="bw-undo-error-list">';
        foreach ($errors as $error) {
            echo '<div class="bw-undo-error-item">' . esc_html((string) $error) . '</div>';
        }
        echo '</div>';
    }

    return ob_get_clean();
}

function botwriter_seo_bulk_undo_warning_for_type($type) {
    switch (sanitize_key($type)) {
        case 'content':
            return __('Restoring this run replaces the current post body with the exact content captured before the bulk action. Any newer manual edits to the same body content will be overwritten.', 'botwriter');
        case 'meta':
            return __('Restoring this run reverts the SEO/meta fields captured before the bulk action. This is usually safe and localized to the stored metadata.', 'botwriter');
        case 'media':
            return __('Restoring this run removes or resets the ALT attributes that were captured before the bulk action, without touching the rest of the post body.', 'botwriter');
    }

    return __('Restoring this run applies the snapshot captured before the action started.', 'botwriter');
}

function botwriter_seo_bulk_undo_format_time($timestamp) {
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return __('Not finished yet', 'botwriter');
    }

    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
}

function botwriter_seo_bulk_undo_describe_args($args_json) {
    $args = json_decode((string) $args_json, true);
    if (!is_array($args)) {
        return '';
    }

    $bits = array();

    if (!empty($args['post_types']) && is_array($args['post_types'])) {
        $labels = array();
        foreach ($args['post_types'] as $post_type) {
            $obj = get_post_type_object($post_type);
            $labels[] = $obj ? $obj->labels->name : ucfirst((string) $post_type);
        }
        if (!empty($labels)) {
            $bits[] = implode(', ', array_slice($labels, 0, 2));
        }
    }

    if (!empty($args['term_ids']) && is_array($args['term_ids'])) {
        $count = count(array_filter(array_map('absint', $args['term_ids'])));
        if ($count > 0) {
            /* translators: %d: number of taxonomy term filters applied */
            $bits[] = sprintf(_n('%d term filter', '%d term filters', $count, 'botwriter'), $count);
        }
    }

    if (!empty($args['only_changed'])) {
        $bits[] = __('Only changed items', 'botwriter');
    }

    if (isset($args['score_lt']) && $args['score_lt'] !== '') {
        /* translators: %d: upper SEO score threshold */
        $bits[] = sprintf(__('SEO < %d', 'botwriter'), (int) $args['score_lt']);
    }
    if (isset($args['score_gt']) && $args['score_gt'] !== '') {
        /* translators: %d: lower SEO score threshold */
        $bits[] = sprintf(__('SEO > %d', 'botwriter'), (int) $args['score_gt']);
    }

    if (!empty($args['missing_faq'])) {
        $bits[] = __('Missing FAQ only', 'botwriter');
    }
    if (!empty($args['missing_seo_title'])) {
        $bits[] = __('Missing SEO title only', 'botwriter');
    }
    if (isset($args['missing_meta_max']) && $args['missing_meta_max'] !== '') {
        /* translators: %d: maximum allowed meta description length */
        $bits[] = sprintf(__('Meta under %d chars', 'botwriter'), (int) $args['missing_meta_max']);
    }
    if (isset($args['target_words']) && $args['target_words'] !== '') {
        /* translators: %d: target word count */
        $bits[] = sprintf(__('Target %d words', 'botwriter'), (int) $args['target_words']);
    }
    if (!empty($args['link_role']) && $args['link_role'] !== 'any') {
        /* translators: %s: internal link role filter */
        $bits[] = sprintf(__('Role: %s', 'botwriter'), sanitize_text_field((string) $args['link_role']));
    }

    return implode(' · ', array_slice($bits, 0, 3));
}

function botwriter_seo_bulk_undo_has_history() {
    if (!botwriter_seo_bulk_undo_tables_ready()) {
        return false;
    }

    global $wpdb;
    $table = botwriter_seo_bulk_undo_runs_table();
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE changed_items > 0") > 0;
}

function botwriter_seo_bulk_undo_field_meta($field) {
    switch ((string) $field) {
        case 'desc':
            return array(__('SEO meta description', 'botwriter'), 'text');
        case 'title':
            return array(__('SEO title', 'botwriter'), 'text');
        case 'focus':
            return array(__('Primary keyword', 'botwriter'), 'text');
        case '_botwriter_seo_faq':
            return array(__('FAQ block', 'botwriter'), 'faq');
        case '_botwriter_seo_faq_visible':
            return array(__('FAQ visibility', 'botwriter'), 'text');
        default:
            return array(ucwords(str_replace('_', ' ', (string) $field)), 'text');
    }
}

function botwriter_seo_bulk_undo_field_state_value($field, $state) {
    $field = (string) $field;
    $state = is_array($state) ? $state : array();
    $exists = !empty($state['exists']);
    $value = $state['value'] ?? '';

    if ($field === '_botwriter_seo_faq_visible') {
        if (!$exists) {
            return __('Not set', 'botwriter');
        }
        return !empty($value) ? __('Visible to readers', 'botwriter') : __('Schema only', 'botwriter');
    }

    if (!$exists) {
        return $field === '_botwriter_seo_faq' ? array() : '';
    }

    if ($field === '_botwriter_seo_faq') {
        return is_array($value) ? $value : array();
    }

    return (string) $value;
}

function botwriter_seo_bulk_undo_media_entry_label($entry) {
    $entry = is_array($entry) ? $entry : array();
    $path = (string) wp_parse_url((string) ($entry['src'] ?? ''), PHP_URL_PATH);
    $filename = $path !== '' ? wp_basename($path) : '';
    if ($filename === '') {
        /* translators: %d: image position inside the snapshot */
        $filename = sprintf(__('Image #%d', 'botwriter'), (int) ($entry['index'] ?? 0) + 1);
    }
    /* translators: %s: image filename */
    return sprintf(__('Image ALT: %s', 'botwriter'), $filename);
}

function botwriter_seo_bulk_undo_match_media_entry($entries, $target, &$used = array()) {
    $entries = is_array($entries) ? $entries : array();
    $target = is_array($target) ? $target : array();
    $src = (string) ($target['src'] ?? '');
    $index = isset($target['index']) ? (int) $target['index'] : -1;

    foreach ($entries as $offset => $candidate) {
        if (isset($used[$offset])) {
            continue;
        }
        if ($src !== '' && (string) ($candidate['src'] ?? '') === $src) {
            $used[$offset] = true;
            return $candidate;
        }
    }

    foreach ($entries as $offset => $candidate) {
        if (isset($used[$offset])) {
            continue;
        }
        if ((int) ($candidate['index'] ?? -1) === $index) {
            $used[$offset] = true;
            return $candidate;
        }
    }

    return null;
}

function botwriter_seo_bulk_undo_build_content_diff_fields($post_id, $snapshot) {
    $fields = array();
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return $fields;
    }

    $old_content = (string) ($snapshot['post_content'] ?? '');
    $current_content = (string) $post->post_content;
    if ($old_content !== $current_content) {
        $fields[] = array(
            'label' => __('Post content', 'botwriter'),
            'format' => 'html',
            'old' => $old_content,
            'current' => $current_content,
        );
    }

    if (isset($snapshot['focus'])) {
        $old_focus = botwriter_seo_bulk_undo_field_state_value('focus', $snapshot['focus']);
        $current_focus = botwriter_seo_bulk_undo_field_state_value('focus', botwriter_seo_bulk_undo_meta_field_state($post_id, 'focus'));
        if ($old_focus !== $current_focus) {
            $fields[] = array(
                'label' => __('Primary keyword', 'botwriter'),
                'format' => 'text',
                'old' => $old_focus,
                'current' => $current_focus,
            );
        }
    }

    return $fields;
}

function botwriter_seo_bulk_undo_build_meta_diff_fields($post_id, $snapshot) {
    $fields = array();

    foreach ((array) ($snapshot['fields'] ?? array()) as $field => $state) {
        if ($field === '_botwriter_seo_faq_updated') {
            continue;
        }

        list($label, $format) = botwriter_seo_bulk_undo_field_meta($field);
        $old_value = botwriter_seo_bulk_undo_field_state_value($field, $state);
        if (strpos((string) $field, '_botwriter_') === 0) {
            $current_state = botwriter_seo_bulk_undo_post_meta_state($post_id, $field);
        } else {
            $current_state = botwriter_seo_bulk_undo_meta_field_state($post_id, $field);
        }
        $current_value = botwriter_seo_bulk_undo_field_state_value($field, $current_state);

        if (wp_json_encode($old_value) === wp_json_encode($current_value)) {
            continue;
        }

        $fields[] = array(
            'label' => $label,
            'format' => $format,
            'old' => $old_value,
            'current' => $current_value,
        );
    }

    return $fields;
}

function botwriter_seo_bulk_undo_build_media_diff_fields($post_id, $snapshot) {
    $fields = array();
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return $fields;
    }

    $current_entries = botwriter_seo_bulk_undo_extract_image_entries((string) $post->post_content, false);
    $used = array();

    foreach ((array) ($snapshot['images'] ?? array()) as $entry) {
        $current = botwriter_seo_bulk_undo_match_media_entry($current_entries, $entry, $used);
        $old_value = !empty($entry['had_alt']) ? (string) ($entry['alt'] ?? '') : '';
        $current_value = ($current && !empty($current['had_alt'])) ? (string) ($current['alt'] ?? '') : '';

        if ($old_value === $current_value) {
            continue;
        }

        $fields[] = array(
            'label' => botwriter_seo_bulk_undo_media_entry_label($entry),
            'format' => 'text',
            'old' => $old_value,
            'current' => $current_value,
        );
    }

    return $fields;
}

function botwriter_seo_bulk_undo_build_item_diff_fields($item) {
    $post_id = (int) ($item['post_id'] ?? 0);
    $snapshot = json_decode((string) ($item['snapshot'] ?? ''), true);
    if ($post_id <= 0 || !is_array($snapshot) || empty($snapshot['type'])) {
        return array();
    }

    switch ((string) $snapshot['type']) {
        case 'content':
            return botwriter_seo_bulk_undo_build_content_diff_fields($post_id, $snapshot);
        case 'meta':
            return botwriter_seo_bulk_undo_build_meta_diff_fields($post_id, $snapshot);
        case 'media':
            return botwriter_seo_bulk_undo_build_media_diff_fields($post_id, $snapshot);
    }

    return array();
}

function botwriter_seo_bulk_undo_render_diff_value($value, $format = 'text') {
    if ($format === 'faq') {
        $faqs = is_array($value) ? $value : array();
        if (empty($faqs)) {
            return '<em class="bw-undo-diff-empty">' . esc_html__('— empty —', 'botwriter') . '</em>';
        }

        $html = '<div class="bw-undo-faq-preview">';
        foreach ($faqs as $faq) {
            if (!is_array($faq)) {
                continue;
            }
            $question = (string) ($faq['q'] ?? '');
            $answer = (string) ($faq['a'] ?? '');
            $html .= '<div class="bw-undo-faq-item">';
            $html .= '<strong>' . esc_html($question) . '</strong>';
            $html .= '<div>' . wp_kses_post(wpautop($answer)) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    if ($format === 'html') {
        $value = (string) $value;
        if (trim($value) === '') {
            return '<em class="bw-undo-diff-empty">' . esc_html__('— empty —', 'botwriter') . '</em>';
        }
        return '<div class="bw-undo-diff-html-preview">' . wp_kses_post($value) . '</div>';
    }

    $value = is_scalar($value) ? (string) $value : wp_json_encode($value);
    if (!is_string($value) || trim($value) === '') {
        return '<em class="bw-undo-diff-empty">' . esc_html__('— empty —', 'botwriter') . '</em>';
    }

    return '<div class="bw-undo-diff-text">' . nl2br(esc_html($value)) . '</div>';
}

function botwriter_seo_bulk_undo_render_item_diff_card($item) {
    $item_id = (int) ($item['id'] ?? 0);
    $run_id = (int) ($item['run_id'] ?? 0);
    $post_id = (int) ($item['post_id'] ?? 0);
    $title = get_the_title($post_id);
    $edit_url = get_edit_post_link($post_id, 'raw');
    $view_url = get_permalink($post_id);
    $can_revert = botwriter_seo_bulk_undo_item_can_revert($item);
    list($item_status, $item_class) = botwriter_seo_bulk_undo_item_status_badge((string) ($item['status'] ?? ''));
    $diff_fields = botwriter_seo_bulk_undo_build_item_diff_fields($item);

    ob_start();
    echo '<article class="bw-undo-item">';
    echo '<div class="bw-undo-item-head">';
    echo '<div class="bw-undo-item-copy">';
    echo '<div class="bw-undo-primary">' . esc_html($title !== '' ? $title : sprintf('#%d', $post_id)) . '</div>';
    echo '<div class="bw-undo-secondary">#' . esc_html((string) $post_id) . '</div>';
    echo '</div>';
    echo '<div class="bw-undo-item-meta">';
    echo '<span class="bw-info-chip ' . esc_attr($item_class) . '">' . esc_html($item_status) . '</span>';
    echo '<div class="bw-undo-actions">';
    if ($can_revert) {
        echo '<button type="button" class="bw-button danger bw-undo-item-revert" data-item-id="' . esc_attr($item_id) . '" data-run-id="' . esc_attr($run_id) . '"><span class="dashicons dashicons-undo"></span> ' . esc_html__('Undo this post', 'botwriter') . '</button>';
    }
    if ($edit_url) {
        echo '<a class="bw-button" href="' . esc_url($edit_url) . '" target="_blank"><span class="dashicons dashicons-edit"></span> ' . esc_html__('Edit', 'botwriter') . '</a>';
    }
    if ($view_url) {
        echo '<a class="bw-button" href="' . esc_url($view_url) . '" target="_blank"><span class="dashicons dashicons-visibility"></span> ' . esc_html__('View', 'botwriter') . '</a>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';

    if (empty($diff_fields)) {
        echo '<div class="bw-notice info bw-undo-item-empty">';
        echo '<span class="dashicons dashicons-saved"></span>';
        echo '<div>' . esc_html__('There are no remaining differences between the stored snapshot and the current post state for this item.', 'botwriter') . '</div>';
        echo '</div>';
    } else {
        echo '<div class="bw-undo-diff-fields">';
        foreach ($diff_fields as $field) {
            echo '<section class="bw-undo-diff-field">';
            echo '<div class="bw-undo-diff-field-header"><strong>' . esc_html((string) ($field['label'] ?? '')) . '</strong></div>';
            echo '<div class="bw-undo-diff-columns">';
            echo '<div class="bw-undo-diff-col bw-undo-diff-col-old">';
            echo '<span class="bw-undo-diff-col-label">' . esc_html__('Original', 'botwriter') . '</span>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped admin HTML for the diff value.
            echo '<div class="bw-undo-diff-col-content">' . botwriter_seo_bulk_undo_render_diff_value($field['old'] ?? '', $field['format'] ?? 'text') . '</div>';
            echo '</div>';
            echo '<div class="bw-undo-diff-col bw-undo-diff-col-new">';
            echo '<span class="bw-undo-diff-col-label">' . esc_html__('Current', 'botwriter') . '</span>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped admin HTML for the diff value.
            echo '<div class="bw-undo-diff-col-content">' . botwriter_seo_bulk_undo_render_diff_value($field['current'] ?? '', $field['format'] ?? 'text') . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</section>';
        }
        echo '</div>';
    }

    echo '</article>';
    return ob_get_clean();
}

function botwriter_seo_bulk_undo_history_html() {
    $runs = botwriter_seo_bulk_undo_list_runs(15);

    ob_start();
    if (empty($runs)) {
        echo '<div class="bw-empty">';
        echo '<span class="dashicons dashicons-undo"></span>';
        echo '<p>' . esc_html__('No undo snapshots yet. Once a reversible bulk action changes something, it will appear here.', 'botwriter') . '</p>';
        echo '</div>';
        return ob_get_clean();
    }

    echo '<div class="bw-undo-table-wrap">';
    echo '<table class="bw-table bw-undo-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Action', 'botwriter') . '</th>';
    echo '<th>' . esc_html__('Created', 'botwriter') . '</th>';
    echo '<th>' . esc_html__('Progress', 'botwriter') . '</th>';
    echo '<th>' . esc_html__('Snapshot', 'botwriter') . '</th>';
    echo '<th>' . esc_html__('Status', 'botwriter') . '</th>';
    echo '<th class="actions">' . esc_html__('Actions', 'botwriter') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($runs as $run) {
        $run_id = (int) ($run['id'] ?? 0);
        $action = (string) ($run['action_key'] ?? '');
        $action_label = botwriter_seo_bulk_action_label($action);
        $summary = botwriter_seo_bulk_undo_describe_args($run['args'] ?? '');
        list($snapshot_label, $snapshot_class) = botwriter_seo_bulk_undo_type_badge($run['undo_type'] ?? '');
        list($status_label, $status_class) = botwriter_seo_bulk_undo_status_badge($run['status'] ?? '');
        $created = botwriter_seo_bulk_undo_format_time((int) ($run['created_at'] ?? 0));
        $changed = max(0, (int) ($run['changed_items'] ?? 0));
        $processed = max(0, (int) ($run['processed_items'] ?? 0));
        $target = max(0, (int) ($run['target_items'] ?? 0));
        $reverted = max(0, (int) ($run['reverted_items'] ?? 0));
        $can_revert = botwriter_seo_bulk_undo_run_can_revert($run);

        echo '<tr>';
        echo '<td>';
        echo '<div class="bw-undo-primary">' . esc_html($action_label) . '</div>';
        if ($summary !== '') {
            echo '<div class="bw-undo-secondary">' . esc_html($summary) . '</div>';
        }
        echo '</td>';
        echo '<td>';
        echo '<div class="bw-undo-stat"><strong>' . esc_html($created) . '</strong>';
        if (!empty($run['finished_at'])) {
            /* translators: %s: localized finish timestamp */
            echo '<span>' . esc_html(sprintf(__('Finished %s', 'botwriter'), botwriter_seo_bulk_undo_format_time((int) $run['finished_at']))) . '</span>';
        }
        echo '</div>';
        echo '</td>';
        echo '<td>';
        /* translators: %d: number of stored snapshots */
        echo '<div class="bw-undo-stat"><strong>' . esc_html(sprintf(_n('%d snapshot', '%d snapshots', $changed, 'botwriter'), $changed)) . '</strong>';
        /* translators: 1: processed items count, 2: target items count */
        echo '<span>' . esc_html(sprintf(__('%1$d / %2$d processed', 'botwriter'), $processed, $target)) . '</span>';
        if ($reverted > 0) {
            /* translators: %d: number of snapshots already restored */
            echo '<span>' . esc_html(sprintf(_n('%d already restored', '%d already restored', $reverted, 'botwriter'), $reverted)) . '</span>';
        }
        echo '</div>';
        echo '</td>';
        echo '<td><span class="bw-info-chip ' . esc_attr($snapshot_class) . '">' . esc_html($snapshot_label) . '</span></td>';
        echo '<td><span class="bw-info-chip ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
        echo '<td class="actions">';
        echo '<div class="bw-undo-actions">';
        echo '<button type="button" class="bw-button bw-undo-run-details" data-run-id="' . esc_attr($run_id) . '">';
        echo '<span class="dashicons dashicons-visibility"></span> ' . esc_html__('View diff', 'botwriter');
        echo '</button>';
        if ($can_revert) {
            echo '<button type="button" class="bw-button danger bw-undo-run-revert" data-run-id="' . esc_attr($run_id) . '">';
            echo '<span class="dashicons dashicons-undo"></span> ' . esc_html__('Undo run', 'botwriter');
            echo '</button>';
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    return ob_get_clean();
}

function botwriter_seo_bulk_undo_detail_html($run_id, $feedback = array()) {
    $run = botwriter_seo_bulk_undo_get_run($run_id);
    if (!$run) {
        return '<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Undo run not found.', 'botwriter') . '</div>';
    }

    $items = botwriter_seo_bulk_undo_get_run_items($run_id);
    $can_revert = botwriter_seo_bulk_undo_run_can_revert($run);
    list($snapshot_label, $snapshot_class) = botwriter_seo_bulk_undo_type_badge($run['undo_type'] ?? '');
    list($status_label, $status_class) = botwriter_seo_bulk_undo_status_badge($run['status'] ?? '');
    $summary = botwriter_seo_bulk_undo_describe_args($run['args'] ?? '');

    ob_start();
    echo '<div class="bw-undo-detail">';
    echo '<div class="bw-undo-detail-head">';
    echo '<div class="bw-undo-detail-copy">';
    echo '<h2>' . esc_html(botwriter_seo_bulk_action_label((string) ($run['action_key'] ?? ''))) . '</h2>';
    echo '<p>' . esc_html__('This run stores the before-state captured for each affected post right before the bulk action modified it.', 'botwriter') . '</p>';
    if ($summary !== '') {
        echo '<p class="bw-undo-secondary">' . esc_html($summary) . '</p>';
    }
    echo '</div>';
    echo '<div class="bw-undo-detail-chips">';
    echo '<span class="bw-info-chip ' . esc_attr($snapshot_class) . '">' . esc_html($snapshot_label) . '</span>';
    echo '<span class="bw-info-chip ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="bw-undo-detail-feedback">';
    if (!empty($feedback['message'])) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped admin HTML for undo feedback.
        echo botwriter_seo_bulk_undo_feedback_html($feedback['kind'] ?? 'info', $feedback['message'], $feedback['errors'] ?? array());
    }
    echo '</div>';

    echo '<div class="bw-notice warn">';
    echo '<span class="dashicons dashicons-warning"></span>';
    echo '<div>' . esc_html(botwriter_seo_bulk_undo_warning_for_type((string) ($run['undo_type'] ?? ''))) . '</div>';
    echo '</div>';

    echo '<div class="bw-undo-detail-tools">';
    if ($can_revert) {
        echo '<button type="button" class="bw-button danger bw-undo-detail-revert-run" data-run-id="' . esc_attr($run_id) . '"><span class="dashicons dashicons-undo"></span> ' . esc_html__('Undo all remaining snapshots', 'botwriter') . '</button>';
    }
    echo '</div>';

    echo '<div class="bw-undo-detail-stats">';
    echo '<div class="bw-undo-detail-stat"><span>' . esc_html__('Created', 'botwriter') . '</span><strong>' . esc_html(botwriter_seo_bulk_undo_format_time((int) ($run['created_at'] ?? 0))) . '</strong></div>';
    /* translators: 1: processed items count, 2: target items count */
    echo '<div class="bw-undo-detail-stat"><span>' . esc_html__('Processed', 'botwriter') . '</span><strong>' . esc_html(sprintf(__('%1$d / %2$d', 'botwriter'), (int) ($run['processed_items'] ?? 0), (int) ($run['target_items'] ?? 0))) . '</strong></div>';
    echo '<div class="bw-undo-detail-stat"><span>' . esc_html__('Snapshots', 'botwriter') . '</span><strong>' . esc_html((string) (int) ($run['changed_items'] ?? 0)) . '</strong></div>';
    echo '<div class="bw-undo-detail-stat"><span>' . esc_html__('Restored', 'botwriter') . '</span><strong>' . esc_html((string) (int) ($run['reverted_items'] ?? 0)) . '</strong></div>';
    echo '</div>';

    if (empty($items)) {
        echo '<div class="bw-empty"><span class="dashicons dashicons-media-text"></span><p>' . esc_html__('This run does not have stored items anymore.', 'botwriter') . '</p></div>';
    } else {
        echo '<div class="bw-undo-items">';
        foreach ($items as $item) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped admin HTML for the undo diff card.
            echo botwriter_seo_bulk_undo_render_item_diff_card($item);
        }
        echo '</div>';
    }

    echo '</div>';
    return ob_get_clean();
}

function botwriter_seo_bulk_undo_section_html() {
    if (!botwriter_seo_bulk_undo_has_history()) {
        return '';
    }

    ob_start();
    if (function_exists('botwriter_seo_card_open')) {
        botwriter_seo_card_open(__('Undo & history', 'botwriter'), 'undo');
    } else {
        echo '<div class="bw-card">';
        echo '<h3><span class="dashicons dashicons-undo"></span> ' . esc_html__('Undo & history', 'botwriter') . '</h3>';
    }

    echo '<p class="bw-undo-history-lead">' . esc_html__('Every reversible bulk run stores a before-snapshot for each post it actually changed. You can inspect those runs here and restore them later if needed.', 'botwriter') . '</p>';
    echo '<div class="bw-notice info">';
    echo '<span class="dashicons dashicons-info-outline"></span>';
    echo '<div>' . esc_html__('Undo is field-aware: content actions restore the previous body, meta actions restore the previous SEO/meta values, and ALT actions restore the previous image ALT attributes. URL rewrites and semantic indexing stay outside this simple undo flow.', 'botwriter') . '</div>';
    echo '</div>';
    echo '<div id="bw-undo-feedback" class="bw-undo-feedback"></div>';
    echo '<div id="bw-bulk-undo-history">';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped admin HTML for the undo history table.
    echo botwriter_seo_bulk_undo_history_html();
    echo '</div>';

    if (function_exists('botwriter_seo_card_close')) {
        botwriter_seo_card_close();
    } else {
        echo '</div>';
    }

    return ob_get_clean();
}

function botwriter_seo_render_bulk_undo_history_section() {
    $html = botwriter_seo_bulk_undo_section_html();
    echo '<div id="bw-bulk-undo-root"' . ($html === '' ? ' hidden' : '') . '>';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section helper returns escaped admin HTML.
    echo $html;
    echo '</div>';
}

add_action('wp_ajax_bw_seo_bulk_undo_history', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'forbidden'), 403);
    }

    $html = botwriter_seo_bulk_undo_section_html();
    wp_send_json_success(array(
        'html' => $html,
        'has_history' => $html !== '',
    ));
});

add_action('wp_ajax_bw_seo_bulk_undo_detail', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'forbidden'), 403);
    }

    $run_id = absint($_POST['run_id'] ?? 0);
    if ($run_id <= 0) {
        wp_send_json_error(array('message' => __('Missing undo run.', 'botwriter')));
    }

    wp_send_json_success(array(
        'html' => botwriter_seo_bulk_undo_detail_html($run_id),
    ));
});

add_action('wp_ajax_bw_seo_bulk_undo_revert_item', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'forbidden'), 403);
    }

    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Undo restore can take longer than default execution time.
    @set_time_limit(60);
    $item_id = absint($_POST['item_id'] ?? 0);
    if ($item_id <= 0) {
        wp_send_json_error(array('message' => __('Missing undo snapshot.', 'botwriter')));
    }

    $result = botwriter_seo_bulk_undo_restore_item($item_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $run_id = (int) (($result['run']['id'] ?? 0) ?: ($result['item']['run_id'] ?? 0));
    $message = __('Snapshot restored for this post.', 'botwriter');

    wp_send_json_success(array(
        'message' => $message,
        'errors' => array(),
        'html' => botwriter_seo_bulk_undo_section_html(),
        'has_history' => botwriter_seo_bulk_undo_has_history(),
        'detail_html' => botwriter_seo_bulk_undo_detail_html($run_id, array(
            'kind' => 'success',
            'message' => $message,
            'errors' => array(),
        )),
    ));
});

add_action('wp_ajax_bw_seo_bulk_undo_revert', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'forbidden'), 403);
    }

    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Full run revert can take longer than default execution time.
    @set_time_limit(90);
    $run_id = absint($_POST['run_id'] ?? 0);
    if ($run_id <= 0) {
        wp_send_json_error(array('message' => __('Missing undo run.', 'botwriter')));
    }

    $result = botwriter_seo_bulk_undo_revert_run($run_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    /* translators: %d: number of snapshots restored for the undo run */
    $message = sprintf(_n('%d snapshot restored.', '%d snapshots restored.', (int) $result['reverted'], 'botwriter'), (int) $result['reverted']);
    if (!empty($result['errors'])) {
        $message .= ' ' . __('Some items could not be restored. Review the details list for the remaining snapshots.', 'botwriter');
    }

    wp_send_json_success(array(
        'message' => $message,
        'errors' => $result['errors'],
        'html' => botwriter_seo_bulk_undo_section_html(),
        'has_history' => botwriter_seo_bulk_undo_has_history(),
        'detail_html' => botwriter_seo_bulk_undo_detail_html($run_id, array(
            'kind' => !empty($result['errors']) ? 'warn' : 'success',
            'message' => $message,
            'errors' => $result['errors'],
        )),
    ));
});