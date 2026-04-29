<?php
/**
 * BotWriter SEO — Media Library ALT generator.
 *
 * Lists image attachments missing _wp_attachment_image_alt and provides a
 * one-click bulk button that generates ALT text with AI for each one.
 * Uses the existing AI provider (botwriter_seo_ai_config()).
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Count attachments without ALT text.
 */
function botwriter_seo_media_alt_count_missing() {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
            WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR m.meta_value = '')",
        '_wp_attachment_image_alt',
        'attachment',
        'image/%'
    );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query was prepared above; Plugin Check does not follow prepared SQL variables reliably.
    return (int) $wpdb->get_var($sql);
}

/**
 * Fetch a batch of attachment IDs missing ALT text.
 */
function botwriter_seo_media_alt_fetch_missing($limit, $cursor = 0) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
         WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR m.meta_value = '') AND p.ID > %d
         ORDER BY p.ID ASC LIMIT %d",
        '_wp_attachment_image_alt',
        'attachment',
        'image/%',
        (int) $cursor,
        max(1, min(50, (int) $limit))
    );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query was prepared above; Plugin Check does not follow prepared SQL variables reliably.
    return array_map('intval', (array) $wpdb->get_col($sql));
}

/**
 * Generate ALT text for a single attachment using AI.
 * Returns array(ok, alt, error).
 */
function botwriter_seo_media_alt_generate($attachment_id) {
    $attachment_id = (int) $attachment_id;
    $att = get_post($attachment_id);
    if (!$att || $att->post_type !== 'attachment') {
        return array('ok' => false, 'error' => 'not_attachment');
    }

    if (!function_exists('botwriter_seo_ai_config') || !function_exists('botwriter_call_editor_worker')) {
        return array('ok' => false, 'error' => 'ai_unavailable');
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        return array('ok' => false, 'error' => 'missing_api_key');
    }

    // Build context: attachment title + parent post title if any.
    $title = trim((string) $att->post_title);
    $parent_title = '';
    if ($att->post_parent) {
        $parent_title = (string) get_the_title($att->post_parent);
    }
    $filename = basename((string) get_attached_file($attachment_id));

    $prompt = "Write a concise (max 12 words) descriptive ALT text for an image. "
        . "Same language as the page it is used on. No quotes, no period, no prefix.\n\n"
        . "ATTACHMENT TITLE: " . $title . "\n"
        . "FILENAME: " . $filename . "\n"
        . ($parent_title !== '' ? "PARENT POST TITLE: " . $parent_title . "\n" : '');

    $resp = botwriter_call_editor_worker($config['provider'], $config['key'], $config['model'], $prompt, 60, 0.5);
    $ai = botwriter_seo_bulk_ai_extract_text($resp);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return array('ok' => false, 'error' => (string) ($ai['error'] ?? 'no_ai_text'));
    }

    $alt = trim(wp_strip_all_tags($ai['text']));
    $alt = trim($alt, " .\"'");
    if ($alt === '') {
        return array('ok' => false, 'error' => 'empty_alt');
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
    return array('ok' => true, 'alt' => $alt);
}

/**
 * AJAX: process a small batch (UI keeps polling until count = 0).
 */
add_action('wp_ajax_bw_seo_media_alt_batch', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('upload_files')) { wp_send_json_error(array('message' => 'forbidden'), 403); }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int sanitizes scalar input.
    $batch_size = isset($_POST['batch']) ? max(1, min(10, (int) wp_unslash($_POST['batch']))) : 3;
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int sanitizes scalar input.
    $cursor = isset($_POST['cursor']) ? (int) wp_unslash($_POST['cursor']) : 0;
    $ids = botwriter_seo_media_alt_fetch_missing($batch_size, $cursor);

    $processed = array();
    $last = $cursor;
    foreach ($ids as $id) {
        $r = botwriter_seo_media_alt_generate($id);
        $processed[] = array(
            'id' => (int) $id,
            'ok' => !empty($r['ok']),
            'alt' => (string) ($r['alt'] ?? ''),
            'error' => (string) ($r['error'] ?? ''),
            'edit_url' => get_edit_post_link($id, 'raw'),
            'thumb' => wp_get_attachment_image_url($id, 'thumbnail'),
            'title' => get_the_title($id),
        );
        $last = $id;
    }

    wp_send_json_success(array(
        'processed' => $processed,
        'cursor'    => $last,
        'remaining' => botwriter_seo_media_alt_count_missing(),
    ));
});

/**
 * Page renderer.
 */
function botwriter_seo_page_media_alt() {
    $missing = botwriter_seo_media_alt_count_missing();
    $sample_ids = botwriter_seo_media_alt_fetch_missing(20, 0);

    echo '<div class="bw-card bw-bulk-hero">';
    echo '<div class="bw-bulk-hero-icon"><span class="dashicons dashicons-format-image"></span></div>';
    echo '<div class="bw-bulk-hero-body">';
    echo '<h2>' . esc_html__('Media Library — ALT generator', 'botwriter') . '</h2>';
    echo '<p>' . esc_html__('Fill missing ALT text on every image attachment in your Media Library, not just inside post content. Improves Google Image Search visibility and accessibility (WCAG).', 'botwriter') . '</p>';
    echo '</div></div>';

    echo '<div class="bw-card">';
    echo '<h3><span class="dashicons dashicons-chart-bar"></span> ' . esc_html__('Status', 'botwriter') . '</h3>';
    /* translators: %d: number of image attachments missing ALT text */
    echo '<p><strong>' . esc_html(sprintf(_n('%d image attachment without ALT text.', '%d image attachments without ALT text.', $missing, 'botwriter'), $missing)) . '</strong></p>';
    if ($missing > 0) {
        echo '<div class="bw-card-actions"><button class="bw-button primary" id="bw-media-alt-run" data-initial-missing="' . esc_attr((string) $missing) . '" data-batch="3"><span class="dashicons dashicons-controls-play"></span> ' . esc_html__('Generate ALT for all missing', 'botwriter') . '</button></div>';
        echo '<div class="bw-progress bw-mt-10"><div id="bw-media-alt-bar" data-progress="0"></div></div>';
        echo '<p id="bw-media-alt-status" class="bw-status-text bw-text-muted"></p>';
    } else {
        echo '<p class="bw-text-success">' . esc_html__('All image attachments already have ALT text. Nothing to do.', 'botwriter') . '</p>';
    }
    echo '</div>';

    if (!empty($sample_ids)) {
        echo '<div class="bw-card">';
        echo '<h3><span class="dashicons dashicons-list-view"></span> ' . esc_html__('Next batch preview', 'botwriter') . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th></th><th>' . esc_html__('Title', 'botwriter') . '</th><th>' . esc_html__('Filename', 'botwriter') . '</th><th></th></tr></thead><tbody id="bw-media-alt-list">';
        foreach ($sample_ids as $id) {
            $thumb = wp_get_attachment_image_url($id, 'thumbnail');
            echo '<tr data-id="' . (int) $id . '">';
            echo '<td>' . ($thumb ? '<img src="' . esc_url($thumb) . '" class="bw-thumb-48" alt="" />' : '') . '</td>';
            echo '<td>' . esc_html(get_the_title($id)) . '</td>';
            echo '<td><code>' . esc_html(basename((string) get_attached_file($id))) . '</code></td>';
            echo '<td><a href="' . esc_url((string) get_edit_post_link($id)) . '">' . esc_html__('Edit', 'botwriter') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
