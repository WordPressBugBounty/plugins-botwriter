<?php
/**
 * BotWriter SEO — llms.txt generator + serve handler.
 *
 * Provides a /llms.txt endpoint at the site root following the llmstxt.org
 * spec. Generates a markdown index of the site (title, tagline, top pages,
 * grouped by post type) so AI crawlers can discover content efficiently.
 *
 * Storage:
 *   - botwriter_seo_llms_txt_cache       Cached markdown body.
 *   - botwriter_seo_llms_txt_generated_at Unix ts of last generation.
 *   - botwriter_seo_llms_txt_override    Manual override (preserved across regenerations).
 *   - botwriter_seo_llms_txt_settings    Array of per-post-type include flags + caps.
 *   - botwriter_seo_llms_txt_auto        '1' to refresh daily via cron.
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) { exit; }

const BOTWRITER_SEO_LLMSTXT_CRON = 'botwriter_seo_llmstxt_refresh';

/**
 * Default settings.
 */
function botwriter_seo_llmstxt_settings() {
    $defaults = array(
        'post_types'   => array('post', 'page'),
        'limit_per_pt' => 200,
        'exclude_noindex' => 1,
        'tagline_mode' => 'tagline', // tagline | ai | manual
        'manual_tagline' => '',
    );
    $stored = get_option('botwriter_seo_llms_txt_settings', array());
    if (!is_array($stored)) { $stored = array(); }
    return array_merge($defaults, $stored);
}

/**
 * Build the llms.txt body from current settings.
 */
function botwriter_seo_llmstxt_build() {
    $settings = botwriter_seo_llmstxt_settings();
    $site_name = wp_strip_all_tags(get_bloginfo('name'));
    $site_url  = home_url('/');

    // Tagline.
    $tagline = '';
    switch ($settings['tagline_mode']) {
        case 'ai':
            $tagline = botwriter_seo_llmstxt_ai_tagline($site_name);
            break;
        case 'manual':
            $tagline = (string) $settings['manual_tagline'];
            break;
        default:
            $tagline = wp_strip_all_tags(get_bloginfo('description'));
            break;
    }
    if ($tagline === '') {
        /* translators: %s: website name */
        $tagline = sprintf(__('Content index for %s', 'botwriter'), $site_name);
    }

    $lines = array();
    $lines[] = '# ' . $site_name;
    $lines[] = '';
    $lines[] = '> ' . $tagline;
    $lines[] = '';
    $lines[] = sprintf('Site: %s', untrailingslashit($site_url));
    $lines[] = sprintf('Generated: %s', gmdate('Y-m-d\TH:i:s\Z'));
    $lines[] = '';

    $allowed = function_exists('botwriter_seo_supported_post_types')
        ? botwriter_seo_supported_post_types()
        : array('post', 'page');

    $pts = array_values(array_intersect((array) $settings['post_types'], $allowed));
    if (empty($pts)) { $pts = array('post', 'page'); }

    $limit = max(1, min(1000, (int) $settings['limit_per_pt']));

    foreach ($pts as $pt) {
        $obj = get_post_type_object($pt);
        if (!$obj) { continue; }
        $items = botwriter_seo_llmstxt_collect_items($pt, $limit, !empty($settings['exclude_noindex']));
        if (empty($items)) { continue; }

        $lines[] = '## ' . $obj->labels->name;
        $lines[] = '';
        foreach ($items as $row) {
            $title = trim((string) $row['title']);
            $url   = (string) $row['url'];
            $desc  = trim((string) $row['desc']);
            if ($title === '' || $url === '') { continue; }
            $line = '- [' . str_replace(array('[', ']'), array('(', ')'), $title) . '](' . esc_url_raw($url) . ')';
            if ($desc !== '') {
                $desc = preg_replace('~\s+~', ' ', $desc);
                if (function_exists('mb_substr')) {
                    $desc = mb_substr($desc, 0, 200, 'UTF-8');
                } else {
                    $desc = substr($desc, 0, 200);
                }
                $line .= ': ' . $desc;
            }
            $lines[] = $line;
        }
        $lines[] = '';
    }

    $body = implode("\n", $lines) . "\n";

    /**
     * Filter the final llms.txt body before saving.
     */
    return (string) apply_filters('botwriter_seo_llmstxt_body', $body, $settings);
}

/**
 * Pull the most recent published items for a post type along with a short
 * description (meta description → excerpt → first paragraph).
 */
function botwriter_seo_llmstxt_collect_items($post_type, $limit, $exclude_noindex) {
    $args = array(
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'fields'         => 'ids',
    );
    $ids = get_posts($args);
    $out = array();
    foreach ($ids as $id) {
        if ($exclude_noindex) {
            $robots = '';
            if (function_exists('botwriter_seo_get_meta')) {
                $robots = (string) botwriter_seo_get_meta($id, 'robots');
            }
            if (stripos($robots, 'noindex') !== false) { continue; }
            // Yoast/RankMath meta-robots-noindex flags.
            if (get_post_meta($id, '_yoast_wpseo_meta-robots-noindex', true) === '1') { continue; }
            if (get_post_meta($id, 'rank_math_robots', true)) {
                $rmr = (array) get_post_meta($id, 'rank_math_robots', true);
                if (in_array('noindex', $rmr, true)) { continue; }
            }
        }

        $title = get_the_title($id);
        $url   = get_permalink($id);
        $desc  = '';
        if (function_exists('botwriter_seo_get_meta_description')) {
            $desc = (string) botwriter_seo_get_meta_description($id);
        }
        if ($desc === '') {
            $excerpt = get_post_field('post_excerpt', $id);
            if ($excerpt) {
                $desc = wp_strip_all_tags($excerpt);
            }
        }
        if ($desc === '') {
            $content = get_post_field('post_content', $id);
            $desc = wp_strip_all_tags(strip_shortcodes($content));
        }
        $out[] = array('title' => $title, 'url' => $url, 'desc' => $desc);
    }
    return $out;
}

/**
 * Optional AI-generated tagline (1 cheap call).
 */
function botwriter_seo_llmstxt_ai_tagline($site_name) {
    if (!function_exists('botwriter_seo_ai_config') || !function_exists('botwriter_call_editor_worker')) {
        return '';
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) { return ''; }

    // Sample a few recent posts to give context.
    $titles = get_posts(array(
        'post_type'      => array('post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));
    $sample = array();
    foreach ($titles as $tid) { $sample[] = '- ' . get_the_title($tid); }
    $sample_str = implode("\n", $sample);

    $prompt = "Write ONE concise tagline (max 25 words) describing the website below for AI crawlers reading its llms.txt. "
        . "Output the tagline only, no quotes, no prefix, in the same language as the titles.\n\n"
        . "SITE: " . $site_name . "\n\nRECENT TITLES:\n" . $sample_str;

    $resp = botwriter_call_editor_worker($config['provider'], $config['key'], $config['model'], $prompt, 80, 0.6);
    $ai = botwriter_seo_bulk_ai_extract_text($resp);
    if (empty($ai['ok']) || empty($ai['text'])) { return ''; }
    return trim(wp_strip_all_tags($ai['text']), " .\"'");
}

/**
 * Regenerate cache and return the body.
 */
function botwriter_seo_llmstxt_regenerate() {
    $body = botwriter_seo_llmstxt_build();
    update_option('botwriter_seo_llms_txt_cache', $body, false);
    update_option('botwriter_seo_llms_txt_generated_at', time(), false);
    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO llms.txt] regenerated', array('bytes' => strlen($body)));
    }
    return $body;
}

/**
 * Serve /llms.txt from the WP root (no physical file required).
 */
add_action('init', 'botwriter_seo_llmstxt_register_rewrite');
function botwriter_seo_llmstxt_register_rewrite() {
    add_rewrite_rule('^llms\.txt$', 'index.php?botwriter_seo_llmstxt=1', 'top');
    add_rewrite_tag('%botwriter_seo_llmstxt%', '([0-9]+)');
}

function botwriter_seo_llmstxt_is_current_request($wp = null) {
    if ($wp && !empty($wp->query_vars['botwriter_seo_llmstxt'])) {
        return true;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $expected_path = (string) wp_parse_url(home_url('/llms.txt'), PHP_URL_PATH);
    if ($request_path === '' || $expected_path === '') {
        return false;
    }

    $request_path = untrailingslashit('/' . ltrim($request_path, '/'));
    $expected_path = untrailingslashit('/' . ltrim($expected_path, '/'));
    return $request_path === $expected_path;
}

add_action('parse_request', function ($wp) {
    if (botwriter_seo_llmstxt_is_current_request($wp)) {
        botwriter_seo_llmstxt_serve();
        exit;
    }
});

add_action('template_redirect', function () {
    if (botwriter_seo_llmstxt_is_current_request()) {
        botwriter_seo_llmstxt_serve();
        exit;
    }
}, 0);

add_filter('redirect_canonical', function ($redirect_url) {
    if (botwriter_seo_llmstxt_is_current_request()) {
        return false;
    }
    return $redirect_url;
});

function botwriter_seo_llmstxt_serve() {
    $physical = ABSPATH . 'llms.txt';

    if (file_exists($physical) && is_readable($physical)) {
        $body = (string) file_get_contents($physical);
    } else {
        $override = get_option('botwriter_seo_llms_txt_override', '');
        $body = is_string($override) && trim($override) !== ''
            ? (string) $override
            : (string) get_option('botwriter_seo_llms_txt_cache', '');

        if ($body === '') {
            $body = botwriter_seo_llmstxt_regenerate();
        }
    }

    if (isset($GLOBALS['wp_query']) && is_object($GLOBALS['wp_query'])) {
        $GLOBALS['wp_query']->is_404 = false;
    }
    status_header(200);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    if (function_exists('send_nosniff_header')) {
        send_nosniff_header();
    }
    header('X-Robots-Tag: noindex, follow');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Serving plain-text markdown; escaping would alter the llms.txt payload.
    echo $body;
}

/**
 * Optional daily refresh.
 */
add_action(BOTWRITER_SEO_LLMSTXT_CRON, function () {
    if (get_option('botwriter_seo_llms_txt_auto') !== '1') { return; }
    botwriter_seo_llmstxt_regenerate();
});

add_action('init', function () {
    if (get_option('botwriter_seo_llms_txt_auto') === '1' && !wp_next_scheduled(BOTWRITER_SEO_LLMSTXT_CRON)) {
        wp_schedule_event(time() + 3600, 'daily', BOTWRITER_SEO_LLMSTXT_CRON);
    }
    if (get_option('botwriter_seo_llms_txt_auto') !== '1') {
        $ts = wp_next_scheduled(BOTWRITER_SEO_LLMSTXT_CRON);
        if ($ts) { wp_unschedule_event($ts, BOTWRITER_SEO_LLMSTXT_CRON); }
    }
});

/**
 * Admin AJAX: regenerate.
 */
add_action('wp_ajax_bw_seo_llmstxt_regen', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $body = botwriter_seo_llmstxt_regenerate();
    wp_send_json_success(array(
        'body' => $body,
        'generated_at' => (int) get_option('botwriter_seo_llms_txt_generated_at', 0),
    ));
});

/**
 * Admin page renderer.
 */
function botwriter_seo_page_llmstxt() {
    $settings = botwriter_seo_llmstxt_settings();

    if (!empty($_POST['save_llmstxt'])) {
        check_admin_referer('bw_seo_llmstxt');
        $allowed = function_exists('botwriter_seo_supported_post_types')
            ? botwriter_seo_supported_post_types()
            : array('post', 'page');
        $posted_post_types = isset($_POST['post_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['post_types'])) : array();
        $posted_limit = isset($_POST['limit_per_pt']) ? absint(wp_unslash($_POST['limit_per_pt'])) : 200;
        $posted_tagline_mode = isset($_POST['tagline_mode']) ? sanitize_key(wp_unslash($_POST['tagline_mode'])) : 'tagline';
        $posted_manual_tagline = isset($_POST['manual_tagline']) ? sanitize_text_field(wp_unslash($_POST['manual_tagline'])) : '';
        $new = array(
            'post_types'   => array_values(array_intersect($posted_post_types, $allowed)),
            'limit_per_pt' => max(1, min(1000, $posted_limit)),
            'exclude_noindex' => !empty($_POST['exclude_noindex']) ? 1 : 0,
            'tagline_mode' => in_array($posted_tagline_mode, array('tagline', 'ai', 'manual'), true)
                ? $posted_tagline_mode
                : 'tagline',
            'manual_tagline' => $posted_manual_tagline,
        );
        if (empty($new['post_types'])) { $new['post_types'] = array('post', 'page'); }
        update_option('botwriter_seo_llms_txt_settings', $new, false);
        update_option('botwriter_seo_llms_txt_auto', !empty($_POST['auto_refresh']) ? '1' : '0');

        $override = isset($_POST['override']) ? sanitize_textarea_field(wp_unslash($_POST['override'])) : '';
        update_option('botwriter_seo_llms_txt_override', $override, false);

        botwriter_seo_llmstxt_regenerate();
        echo '<div class="bw-notice success">' . esc_html__('Saved and regenerated.', 'botwriter') . '</div>';
        $settings = botwriter_seo_llmstxt_settings();
    }

    $cache = (string) get_option('botwriter_seo_llms_txt_cache', '');
    $override = (string) get_option('botwriter_seo_llms_txt_override', '');
    $generated_at = (int) get_option('botwriter_seo_llms_txt_generated_at', 0);
    $auto = get_option('botwriter_seo_llms_txt_auto', '0') === '1';
    $physical = file_exists(ABSPATH . 'llms.txt');

    $allowed = function_exists('botwriter_seo_supported_post_types')
        ? botwriter_seo_supported_post_types()
        : array('post', 'page');

    echo '<div class="bw-card bw-bulk-hero">';
    echo '<div class="bw-bulk-hero-icon"><span class="dashicons dashicons-format-aside"></span></div>';
    echo '<div class="bw-bulk-hero-body">';
    echo '<h2>' . esc_html__('llms.txt — AI search profile', 'botwriter') . '</h2>';
    echo '<p>' . esc_html__('Publish a markdown index at /llms.txt so AI crawlers (Perplexity, ChatGPT, Claude, etc.) can discover your most important pages and what your site is about.', 'botwriter') . '</p>';
    echo '<p><a class="bw-button" href="' . esc_url(home_url('/llms.txt')) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span> ' . esc_html__('Open /llms.txt', 'botwriter') . '</a>';
    if ($generated_at) {
        /* translators: %s: localized generation datetime */
        echo ' &nbsp; <span class="description">' . esc_html(sprintf(__('Last generated: %s', 'botwriter'), date_i18n('Y-m-d H:i', $generated_at))) . '</span>';
    }
    echo '</p>';
    if ($physical) {
        echo '<div class="bw-notice warn">' . esc_html__('A physical llms.txt file exists in your site root. The static file will be served instead of the generated one.', 'botwriter') . '</div>';
    }
    echo '</div></div>';

    echo '<form method="post" class="bw-seo-grid cols-2">';
    wp_nonce_field('bw_seo_llmstxt');

    botwriter_seo_card_open(__('Content sources', 'botwriter'), 'category');
    echo '<div class="bw-form-row"><label>' . esc_html__('Include post types', 'botwriter') . '</label><div>';
    foreach ($allowed as $pt) {
        $obj = get_post_type_object($pt);
        if (!$obj) { continue; }
        echo '<label class="bw-inline-check"><input type="checkbox" name="post_types[]" value="' . esc_attr($pt) . '"' . checked(in_array($pt, (array) $settings['post_types'], true), true, false) . ' /> ' . esc_html($obj->labels->name) . '</label>';
    }
    echo '</div></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Max items per type', 'botwriter') . '</label><input type="number" name="limit_per_pt" min="1" max="1000" value="' . esc_attr($settings['limit_per_pt']) . '" /></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Exclude noindex pages', 'botwriter') . '</label><input type="checkbox" name="exclude_noindex" value="1"' . ($settings['exclude_noindex'] ? ' checked' : '') . ' /></div>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Tagline & automation', 'botwriter'), 'edit');
    $tm = (string) $settings['tagline_mode'];
    echo '<div class="bw-form-row"><label>' . esc_html__('Tagline source', 'botwriter') . '</label><select name="tagline_mode">';
    echo '<option value="tagline"' . selected($tm, 'tagline', false) . '>' . esc_html__('WordPress site tagline', 'botwriter') . '</option>';
    echo '<option value="ai"' . selected($tm, 'ai', false) . '>' . esc_html__('Generate with AI on regenerate', 'botwriter') . '</option>';
    echo '<option value="manual"' . selected($tm, 'manual', false) . '>' . esc_html__('Manual', 'botwriter') . '</option>';
    echo '</select></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Manual tagline', 'botwriter') . '</label><input type="text" name="manual_tagline" value="' . esc_attr($settings['manual_tagline']) . '" placeholder="' . esc_attr__('Used only when tagline source is Manual', 'botwriter') . '" /></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Auto-refresh daily', 'botwriter') . '</label><input type="checkbox" name="auto_refresh" value="1"' . ($auto ? ' checked' : '') . ' /></div>';
    botwriter_seo_card_close();

    echo '</form>'; // close grid form open above used to render cards inside; reopen for textareas

    echo '<form method="post">';
    wp_nonce_field('bw_seo_llmstxt');
    // Re-emit fields so submitting any form persists everything.
    echo '<input type="hidden" name="post_types[]" value="' . esc_attr($settings['post_types'][0] ?? 'post') . '" />';

    botwriter_seo_card_open(__('Manual override', 'botwriter'), 'edit-large');
    echo '<p class="description">' . esc_html__('If you fill this textarea, its content will be served instead of the generated body. Leave empty to use the generated one.', 'botwriter') . '</p>';
    echo '<textarea name="override" rows="10" class="bw-mono-textarea">' . esc_textarea($override) . '</textarea>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Current /llms.txt preview', 'botwriter'), 'visibility');
    echo '<pre class="bw-code-preview">' . esc_html($cache !== '' ? $cache : __('(empty — press Save & regenerate)', 'botwriter')) . '</pre>';
    botwriter_seo_card_close();

    echo '<p class="bw-actions-row bw-mt-16">';
    echo '<button class="bw-button primary" name="save_llmstxt" value="1"><span class="dashicons dashicons-saved"></span> ' . esc_html__('Save & regenerate', 'botwriter') . '</button>';
    echo '<button type="button" class="bw-button" id="bw-llmstxt-regen"><span class="dashicons dashicons-update"></span> ' . esc_html__('Regenerate now', 'botwriter') . '</button>';
    echo '</p>';
    echo '</form>';
}

/**
 * Activation/deactivation rewrite flush helpers (idempotent).
 */
add_action('admin_init', function () {
    if (get_option('botwriter_seo_llmstxt_flushed') !== '1') {
        flush_rewrite_rules(false);
        update_option('botwriter_seo_llmstxt_flushed', '1');
    }
});
