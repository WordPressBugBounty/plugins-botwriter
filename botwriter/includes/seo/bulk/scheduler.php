<?php
/**
 * BotWriter SEO — bulk job scheduler.
 * A lightweight WP-Cron based runner that processes batches per tick.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BOTWRITER_SEO_JOB_OPTION_PREFIX = 'botwriter_seo_job_';
const BOTWRITER_SEO_JOB_HOOK = 'botwriter_seo_job_tick';

function botwriter_seo_job_cancel_flag_key($job) {
    return 'botwriter_seo_job_cancelled_' . sanitize_key($job);
}

function botwriter_seo_job_is_cancelled($job) {
    return (bool) get_transient(botwriter_seo_job_cancel_flag_key($job));
}

function botwriter_seo_job_apply_cancelled_state($job, $state = array()) {
    $state = is_array($state) ? $state : array();
    $state['job'] = sanitize_key($job);
    $state['status'] = 'cancelled';
    $state['finished_at'] = !empty($state['finished_at']) ? (int) $state['finished_at'] : time();
    $state['message'] = __('Cancelled by user.', 'botwriter');
    $state['action_label'] = botwriter_seo_job_action_label($state);

    return $state;
}

function botwriter_seo_job_action_label($state) {
    $args = is_array($state['args'] ?? null) ? $state['args'] : array();
    $action = sanitize_key($args['action'] ?? '');

    if ($action !== '' && function_exists('botwriter_seo_bulk_action_label')) {
        return botwriter_seo_bulk_action_label($action);
    }

    return __('Bulk action progress', 'botwriter');
}

function botwriter_seo_job_state($job) {
    $job = sanitize_key($job);
    $state = get_option(BOTWRITER_SEO_JOB_OPTION_PREFIX . $job);
    if (!is_array($state)) {
        $state = array(
            'job' => $job,
            'status' => 'idle',
            'total' => 0,
            'done' => 0,
            'cursor' => 0,
            'message' => '',
            'args' => array(),
            'started_at' => 0,
            'finished_at' => 0,
            'recent' => array(),
            'last_tick_at' => 0,
        );
    }
    if (botwriter_seo_job_is_cancelled($job) && ($state['status'] ?? 'idle') !== 'idle') {
        $state = botwriter_seo_job_apply_cancelled_state($job, $state);
    }
    $state['action_label'] = botwriter_seo_job_action_label($state);
    return $state;
}

function botwriter_seo_job_save($job, $state) {
    update_option(BOTWRITER_SEO_JOB_OPTION_PREFIX . sanitize_key($job), $state, false);
}

function botwriter_seo_job_start($job, $args = array()) {
    $job = sanitize_key($job);
    if (!has_filter('botwriter_seo_job_init_' . $job)) {
        return new WP_Error('unknown_job', sprintf('Unknown job: %s', $job));
    }
    delete_transient(botwriter_seo_job_cancel_flag_key($job));
    $init = apply_filters('botwriter_seo_job_init_' . $job, array('total' => 0, 'message' => ''), $args);
    $args = apply_filters('botwriter_seo_job_prepare_args_' . $job, $args, $init);
    $state = array(
        'job' => $job,
        'status' => 'pending',
        'total' => intval($init['total'] ?? 0),
        'done' => 0,
        'cursor' => 0,
        'message' => (string) ($init['message'] ?? ''),
        'args' => $args,
        'started_at' => time(),
        'finished_at' => 0,
        'recent' => array(),
        'last_tick_at' => 0,
    );
    $state['action_label'] = botwriter_seo_job_action_label($state);
    botwriter_seo_job_save($job, $state);
    if (!wp_next_scheduled(BOTWRITER_SEO_JOB_HOOK, array($job))) {
        wp_schedule_single_event(time() + 1, BOTWRITER_SEO_JOB_HOOK, array($job));
    }
    return $state;
}

function botwriter_seo_job_cancel($job) {
    $job = sanitize_key($job);
    $state = botwriter_seo_job_state($job);
    if (!in_array($state['status'], array('pending', 'running'), true)) {
        return $state;
    }

    set_transient(botwriter_seo_job_cancel_flag_key($job), time(), DAY_IN_SECONDS);
    $state = botwriter_seo_job_apply_cancelled_state($job, $state);
    botwriter_seo_job_save($job, $state);

    // Remove any queued future ticks and clear lock.
    while ($ts = wp_next_scheduled(BOTWRITER_SEO_JOB_HOOK, array($job))) {
        wp_unschedule_event($ts, BOTWRITER_SEO_JOB_HOOK, array($job));
    }
    delete_transient('botwriter_seo_job_lock_' . $job);

    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] job cancelled', array(
            'job' => $job,
            'done' => (int) ($state['done'] ?? 0),
            'total' => (int) ($state['total'] ?? 0),
        ));
    }

    do_action('botwriter_seo_job_cancelled_' . $job, $state);

    return $state;
}

add_action(BOTWRITER_SEO_JOB_HOOK, 'botwriter_seo_job_tick', 10, 1);
function botwriter_seo_job_tick($job) {
    $job = sanitize_key($job);
    if (botwriter_seo_job_is_cancelled($job)) {
        $state = botwriter_seo_job_state($job);
        botwriter_seo_job_save($job, $state);
        return;
    }
    $state = botwriter_seo_job_state($job);
    if (!in_array($state['status'], array('pending', 'running'), true)) { return; }

    // Prevent overlapping ticks for the same job (cron + inline status tick).
    $lock_key = 'botwriter_seo_job_lock_' . $job;
    if (get_transient($lock_key)) {
        if (function_exists('botwriter_log')) {
            botwriter_log('[SEO Bulk] tick skipped (lock active)', array('job' => $job));
        }
        return;
    }
    set_transient($lock_key, time(), 120);

    $state['status'] = 'running';
    $batch_size = (int) apply_filters('botwriter_seo_job_batch_size_' . $job, 8, $state);
    $batch_size = max(1, min(50, $batch_size));

    $processed = apply_filters('botwriter_seo_job_run_' . $job, array(
        'done' => 0,
        'cursor' => $state['cursor'],
        'message' => $state['message'],
        'finished' => false,
    ), $state, $batch_size);

    $latest_state = botwriter_seo_job_state($job);
    if (($latest_state['status'] ?? '') === 'cancelled' || botwriter_seo_job_is_cancelled($job)) {
        delete_transient($lock_key);
        botwriter_seo_job_save($job, $latest_state);
        do_action('botwriter_seo_job_after_tick_' . $job, $latest_state, $processed);
        return;
    }

    $state['done'] = min($state['total'], $state['done'] + intval($processed['done'] ?? 0));
    $state['cursor'] = intval($processed['cursor'] ?? $state['cursor']);
    $state['message'] = (string) ($processed['message'] ?? $state['message']);
    $state['last_tick_at'] = time();

    if (!empty($processed['recent']) && is_array($processed['recent'])) {
        $existing = isset($state['recent']) && is_array($state['recent']) ? $state['recent'] : array();
        // Prepend new items, dedupe by 'id', cap to last 50.
        $merged = array_merge(array_reverse($processed['recent']), $existing);
        $seen = array(); $deduped = array();
        foreach ($merged as $item) {
            $k = isset($item['id']) ? (int) $item['id'] : md5(serialize($item));
            if (isset($seen[$k])) { continue; }
            $seen[$k] = true;
            $deduped[] = $item;
            if (count($deduped) >= 50) { break; }
        }
        $state['recent'] = $deduped;
    }

    if (!empty($processed['finished']) || ($state['total'] > 0 && $state['done'] >= $state['total'])) {
        $state['status'] = 'done';
        $state['finished_at'] = time();
        if (function_exists('botwriter_seo_event')) {
            botwriter_seo_event('job_completed', array('job' => $job, 'done' => $state['done'], 'total' => $state['total']));
        }
    } else {
        // Schedule next tick.
        wp_schedule_single_event(time() + 5, BOTWRITER_SEO_JOB_HOOK, array($job));
    }

    botwriter_seo_job_save($job, $state);
    delete_transient($lock_key);
    do_action('botwriter_seo_job_after_tick_' . $job, $state, $processed);
}

// AJAX: start job + status.
add_action('wp_ajax_bw_seo_job_start', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $job = isset($_POST['job']) ? sanitize_key(wp_unslash($_POST['job'])) : '';
    $args = array();
    $raw_args = isset($_POST['args']) ? sanitize_textarea_field(wp_unslash($_POST['args'])) : '';
    if ($raw_args !== '') {
        $decoded = json_decode($raw_args, true);
        if (is_array($decoded)) { $args = $decoded; }
    }
    $state = botwriter_seo_job_start($job, $args);
    if (is_wp_error($state)) { wp_send_json_error(array('message' => $state->get_error_message())); }
    wp_send_json_success($state);
});
add_action('wp_ajax_bw_seo_job_status', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $job = isset($_POST['job']) ? sanitize_key(wp_unslash($_POST['job'])) : '';
    $state = botwriter_seo_job_state($job);
    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] status poll (before tick)', array(
            'job' => $job,
            'status' => (string) ($state['status'] ?? ''),
            'done' => (int) ($state['done'] ?? 0),
            'total' => (int) ($state['total'] ?? 0),
            'cursor' => (int) ($state['cursor'] ?? 0),
        ));
    }
    // Run an inline tick so progress advances even if WP-Cron doesn't fire.
    if (in_array($state['status'], array('pending', 'running'), true)) {
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Bounded extension to advance bulk job within an AJAX poll.
        @set_time_limit(20);
        botwriter_seo_job_tick($job);
        $state = botwriter_seo_job_state($job);
    }
    // Optional: clients can request only items added after a known offset.
    $since = isset($_POST['since']) ? absint(wp_unslash($_POST['since'])) : 0;
    if ($since > 0 && !empty($state['recent']) && is_array($state['recent'])) {
        $state['recent'] = array_values(array_filter($state['recent'], function ($it) use ($since) {
            return isset($it['ts']) && (int) $it['ts'] > $since;
        }));
    }
    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] status poll (after tick)', array(
            'job' => $job,
            'status' => (string) ($state['status'] ?? ''),
            'done' => (int) ($state['done'] ?? 0),
            'total' => (int) ($state['total'] ?? 0),
            'cursor' => (int) ($state['cursor'] ?? 0),
        ));
    }
    wp_send_json_success($state);
});

add_action('wp_ajax_bw_seo_job_cancel', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $job = sanitize_key($_POST['job'] ?? '');
    if ($job === '') { wp_send_json_error(array('message' => 'missing_job')); }
    $state = botwriter_seo_job_cancel($job);
    wp_send_json_success($state);
});
