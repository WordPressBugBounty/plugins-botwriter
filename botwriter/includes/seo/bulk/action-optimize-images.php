<?php
/**
 * BotWriter SEO — Image optimizer bulk action.
 *
 * Walks every targeted post, collects its featured image + inline images,
 * resolves them to attachment IDs and runs them through the existing
 * botwriter_process_image() pipeline (resize → re-encode to WebP → cap
 * filesize). Optionally rewrites URLs inside post_content when an image
 * was converted to a different extension.
 *
 * Settings are inherited from the global Image Post-Processing options
 * configured at admin.php?page=botwriter_settings (output format,
 * max width, compression quality, max file size).
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Resolve the attachment ID for a given image URL, with a fast cache.
 */
function botwriter_seo_optimizer_attachment_id_from_url($url) {
    static $cache = array();
    $url = (string) $url;
    if ($url === '') { return 0; }

    $key = md5($url);
    if (isset($cache[$key])) { return (int) $cache[$key]; }

    if (function_exists('attachment_url_to_postid')) {
        // Strip size suffix like -300x200 to find the base attachment.
        $stripped = preg_replace('~-\d+x\d+(?=\.[a-z0-9]{2,5}(\?|$))~i', '', $url);
        $id = (int) attachment_url_to_postid($stripped);
        if ($id <= 0 && $stripped !== $url) {
            $id = (int) attachment_url_to_postid($url);
        }
        $cache[$key] = $id;
        return $id;
    }
    return 0;
}

/**
 * Decide whether a file already meets the configured optimization targets.
 */
function botwriter_seo_optimizer_already_optimized($file_path) {
    if (!file_exists($file_path)) { return true; }
    $output_format  = get_option('botwriter_image_output_format', 'webp');
    $max_width      = (int) get_option('botwriter_image_max_width', 1200);
    $max_filesize_kb = (int) get_option('botwriter_image_max_filesize', 120);
    $current_ext = strtolower((string) pathinfo($file_path, PATHINFO_EXTENSION));
    if ($output_format !== 'original') {
        $expected = $output_format === 'jpeg' ? 'jpg' : $output_format;
        if ($current_ext !== $expected && !($expected === 'jpg' && $current_ext === 'jpeg')) {
            return false;
        }
    }
    $size = (int) @filesize($file_path);
    if ($max_filesize_kb > 0 && $size > $max_filesize_kb * 1024) { return false; }
    if ($max_width > 0) {
        $info = @getimagesize($file_path);
        if (is_array($info) && !empty($info[0]) && $info[0] > $max_width) { return false; }
    }
    return true;
}

/**
 * Optimize a single attachment in place.
 * Returns array(ok, changed, old_url, new_url, old_size, new_size).
 */
function botwriter_seo_optimizer_run_attachment($attachment_id) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return array('ok' => false, 'changed' => false, 'reason' => 'invalid_id');
    }
    $att = get_post($attachment_id);
    if (!$att || $att->post_type !== 'attachment') {
        return array('ok' => false, 'changed' => false, 'reason' => 'not_attachment');
    }
    $mime = (string) $att->post_mime_type;
    if (strpos($mime, 'image/') !== 0) {
        return array('ok' => false, 'changed' => false, 'reason' => 'not_image');
    }
    if ($mime === 'image/svg+xml' || $mime === 'image/gif') {
        return array('ok' => true, 'changed' => false, 'reason' => 'unsupported_format');
    }

    $file = (string) get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
        return array('ok' => false, 'changed' => false, 'reason' => 'file_missing');
    }
    if (botwriter_seo_optimizer_already_optimized($file)) {
        return array('ok' => true, 'changed' => false, 'reason' => 'already_optimized');
    }

    $old_url  = wp_get_attachment_url($attachment_id);
    $old_size = (int) @filesize($file);

    if (!function_exists('botwriter_process_image')) {
        return array('ok' => false, 'changed' => false, 'reason' => 'process_fn_missing');
    }
    $new_file = botwriter_process_image($file);
    if (!$new_file || !file_exists($new_file)) {
        return array('ok' => false, 'changed' => false, 'reason' => 'process_failed');
    }

    $changed_extension = ($new_file !== $file);

    // If the file path/extension changed, update the attachment record.
    if ($changed_extension) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $wp_filetype = wp_check_filetype(basename($new_file), null);
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_mime_type' => (string) $wp_filetype['type'],
        ));
        update_attached_file($attachment_id, $new_file);
        $meta = wp_generate_attachment_metadata($attachment_id, $new_file);
        wp_update_attachment_metadata($attachment_id, $meta);
    } else {
        // Same file path, just regenerate sizes so thumbnails reflect new bytes.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attachment_id, $new_file);
        wp_update_attachment_metadata($attachment_id, $meta);
    }

    $new_url  = wp_get_attachment_url($attachment_id);
    $new_size = (int) @filesize($new_file);

    return array(
        'ok'       => true,
        'changed'  => true,
        'old_url'  => (string) $old_url,
        'new_url'  => (string) $new_url,
        'old_size' => $old_size,
        'new_size' => $new_size,
        'extension_changed' => $changed_extension,
    );
}

/**
 * Collect all attachment IDs referenced by a given post (featured + inline).
 */
function botwriter_seo_optimizer_collect_post_attachments($post_id) {
    $post_id = (int) $post_id;
    $ids = array();

    $thumb = (int) get_post_thumbnail_id($post_id);
    if ($thumb > 0) { $ids[$thumb] = true; }

    $content = (string) get_post_field('post_content', $post_id);
    if ($content !== '' && preg_match_all('~<img[^>]+src=["\']([^"\']+)["\']~i', $content, $m)) {
        foreach ($m[1] as $url) {
            $aid = botwriter_seo_optimizer_attachment_id_from_url($url);
            if ($aid > 0) { $ids[$aid] = true; }
        }
    }

    return array_keys($ids);
}

/**
 * Action runner. Returns array(ok, changed, summary).
 */
function botwriter_seo_action_optimize_images($post_id, $args = array()) {
    $att_ids = botwriter_seo_optimizer_collect_post_attachments($post_id);
    if (empty($att_ids)) {
        if (function_exists('botwriter_seo_bulk_log')) {
            botwriter_seo_bulk_log('optimize_images skipped (no attachments)', array('post_id' => (int) $post_id));
        }
        return false;
    }

    $any_changed = false;
    $url_replacements = array();
    $stats = array('attempted' => 0, 'changed' => 0, 'bytes_saved' => 0, 'errors' => 0);

    foreach ($att_ids as $aid) {
        $stats['attempted']++;
        $r = botwriter_seo_optimizer_run_attachment($aid);
        if (!empty($r['ok']) && !empty($r['changed'])) {
            $any_changed = true;
            $stats['changed']++;
            $stats['bytes_saved'] += max(0, (int) ($r['old_size'] ?? 0) - (int) ($r['new_size'] ?? 0));
            if (!empty($r['extension_changed']) && !empty($r['old_url']) && !empty($r['new_url']) && $r['old_url'] !== $r['new_url']) {
                $url_replacements[$r['old_url']] = $r['new_url'];
            }
        } elseif (empty($r['ok'])) {
            $stats['errors']++;
        }
    }

    // Rewrite post_content if any URLs changed extension.
    if (!empty($url_replacements)) {
        $content = (string) get_post_field('post_content', $post_id);
        if ($content !== '') {
            $new_content = strtr($content, $url_replacements);
            if ($new_content !== $content) {
                wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));
            }
        }
    }

    if (function_exists('botwriter_seo_bulk_log')) {
        botwriter_seo_bulk_log('optimize_images result', array(
            'post_id' => (int) $post_id,
            'attempted' => (int) $stats['attempted'],
            'changed' => (int) $stats['changed'],
            'bytes_saved' => (int) $stats['bytes_saved'],
            'errors' => (int) $stats['errors'],
        ));
    }

    return $any_changed;
}

/**
 * Hook into the action dispatcher (botwriter_seo_apply_action) via a
 * dedicated filter we register here. This avoids having to edit
 * actions.php to add a new switch case.
 */
add_action('botwriter_seo_apply_action_optimize_images', function ($result, $post_id, $args) {
    $ok = (bool) botwriter_seo_action_optimize_images($post_id, $args);
    $result['ok'] = $ok;
    $result['changed'] = $ok;
    return $result;
}, 10, 3);
