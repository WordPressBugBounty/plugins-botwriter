<?php
/**
 * BotWriter SEO — bulk analysis job (Phase 1.2).
 * Recompute SEO score for every published post.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter('botwriter_seo_job_init_analysis', function ($init, $args) {
    $args = function_exists('botwriter_seo_sanitize_target_args') ? botwriter_seo_sanitize_target_args($args) : (array) $args;
    if (function_exists('botwriter_seo_target_count')) {
        $total = botwriter_seo_target_count($args);
    } else {
        global $wpdb;
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status='publish' AND post_type IN ('post','page')"
        );
    }
    $init['total'] = $total;
    /* translators: %d: number of posts to scan */
    $init['message'] = sprintf(__('Scanning %d items…', 'botwriter'), $total);
    return $init;
}, 10, 2);

add_filter('botwriter_seo_job_run_analysis', function ($result, $state, $batch_size) {
    $args = is_array($state['args']) ? $state['args'] : array();
    $cursor = (int) $state['cursor'];

    if (function_exists('botwriter_seo_target_ids')) {
        $ids = botwriter_seo_target_ids($args, $cursor, $batch_size);
    } else {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status='publish' AND post_type IN ('post','page')
               AND ID > %d
             ORDER BY ID ASC LIMIT %d",
            $cursor, $batch_size
        ));
    }

    $done = 0;
    $last = $cursor;
    $recent = array();
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $id = (int) $id;
            $report = botwriter_seo_compute_score($id, true);
            if (function_exists('botwriter_seo_compute_readability')) {
                botwriter_seo_compute_readability($id, true);
            }
            $score = (int) ($report['score'] ?? 0);
            $grade = (string) ($report['grade'] ?? 'n/a');
            $title = get_the_title($id);
            $recent[] = array(
                'id'       => $id,
                'title'    => $title !== '' ? $title : sprintf('#%d', $id),
                'score'    => $score,
                'grade'    => $grade,
                'edit_url' => get_edit_post_link($id, 'raw'),
                'view_url' => get_permalink($id),
                'ts'       => time(),
            );
            $done++;
            $last = $id;
        }
    }
    $result['done'] = $done;
    $result['cursor'] = $last;
    $result['recent'] = $recent;
    $result['finished'] = ($done < $batch_size);
    $result['message'] = $done > 0
        /* translators: %d: highest processed post ID */
        ? sprintf(__('Processed up to id %d', 'botwriter'), $last)
        : __('No more posts to process.', 'botwriter');
    return $result;
}, 10, 3);
