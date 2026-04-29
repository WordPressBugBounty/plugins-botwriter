<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_page_internal_links() {
    $audit = botwriter_seo_audit_summary();
    echo '<div class="bw-seo-grid cols-4 bw-grid-mb-18">';
    botwriter_seo_kpi(__('Total posts', 'botwriter'), $audit['total_posts'], 'admin-post', 'info');
    botwriter_seo_kpi(__('Internal links', 'botwriter'), $audit['total_internal_links'], 'admin-links', 'success');
    botwriter_seo_kpi(__('Orphans', 'botwriter'), count($audit['orphans']), 'warning', 'warn');
    botwriter_seo_kpi(__('Dead-ends', 'botwriter'), count($audit['deadends']), 'no', 'danger');
    echo '</div>';

    echo '<div class="bw-seo-grid cols-2">';
    botwriter_seo_card_open(__('Orphan posts (no incoming links)', 'botwriter'), 'warning');
    if (empty($audit['orphans'])) {
        echo '<div class="bw-empty"><span class="dashicons dashicons-yes-alt"></span><p>' . esc_html__('No orphan posts — every post is linked from somewhere.', 'botwriter') . '</p></div>';
    } else {
        echo '<table class="bw-table"><thead><tr><th>' . esc_html__('Post', 'botwriter') . '</th><th class="actions"></th></tr></thead><tbody>';
        foreach (array_slice($audit['orphans'], 0, 30) as $pid) {
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html(get_the_title($pid)) . '</a></td>';
            echo '<td class="actions"><button class="bw-button bw-run-action" data-action="bw_seo_rebuild_links" data-args=\'{"post_id":' . (int) $pid . '}\'>' . esc_html__('Rebuild links', 'botwriter') . '</button></td></tr>';
        }
        echo '</tbody></table>';
    }
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Top hubs (most incoming links)', 'botwriter'), 'star-filled');
    if (empty($audit['top_hubs'])) {
        echo '<div class="bw-empty"><p>' . esc_html__('No data yet.', 'botwriter') . '</p></div>';
    } else {
        echo '<table class="bw-table"><thead><tr><th>' . esc_html__('Post', 'botwriter') . '</th><th>' . esc_html__('Incoming', 'botwriter') . '</th></tr></thead><tbody>';
        foreach ($audit['top_hubs'] as $pid => $count) {
            if ($count < 1) { continue; }
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html(get_the_title($pid)) . '</a></td><td><span class="bw-tag success">' . (int) $count . '</span></td></tr>';
        }
        echo '</tbody></table>';
    }
    botwriter_seo_card_close();
    echo '</div>';

    // Manual run-on-post helper
    botwriter_seo_card_open(__('Run engine on a single post', 'botwriter'), 'admin-tools');
    if (
        isset($_POST['bw_seo_run_post'], $_POST['_bw_run_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_bw_run_nonce'])), 'bw_seo_run_post')
    ) {
        $pid = isset($_POST['bw_seo_post_id']) ? absint(wp_unslash($_POST['bw_seo_post_id'])) : 0;
        $r = botwriter_seo_auto_internal_links_postprocess($pid);
        echo '<div class="bw-notice success">' . esc_html(sprintf(
            /* translators: 1: number of inserted links, 2: insertion strategy label. */
            __('Inserted %1$d link(s) — strategy: %2$s', 'botwriter'),
            (int) ($r['inserted'] ?? 0),
            (string) ($r['strategy'] ?? '')
        )) . '</div>';
    }
    echo '<form method="post"><div class="bw-form-row">';
    wp_nonce_field('bw_seo_run_post', '_bw_run_nonce');
    echo '<label>' . esc_html__('Post ID', 'botwriter') . '</label>';
    echo '<input type="number" name="bw_seo_post_id" min="1" required />';
    echo '<button class="bw-button primary" name="bw_seo_run_post" value="1"><span class="dashicons dashicons-controls-play"></span> ' . esc_html__('Run', 'botwriter') . '</button>';
    echo '</div></form>';
    botwriter_seo_card_close();
}
