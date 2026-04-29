<?php
/**
 * BotWriter SEO — admin pages router (modern Yoast-style UI).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registry of all SEO admin pages.
 */
function botwriter_seo_pages() {
    return array(
        'botwriter_seo' => array('label' => __('Dashboard', 'botwriter'), 'icon' => 'chart-area', 'render' => 'botwriter_seo_page_dashboard'),
        'botwriter_seo_bulk_analysis' => array('label' => __('Bulk analysis', 'botwriter'), 'icon' => 'analytics', 'render' => 'botwriter_seo_page_bulk_analysis'),
        'botwriter_seo_bulk_actions' => array('label' => __('Bulk actions', 'botwriter'), 'icon' => 'admin-tools', 'render' => 'botwriter_seo_page_bulk_actions'),
        'botwriter_seo_internal_links' => array('label' => __('Audit Internal Links', 'botwriter'), 'icon' => 'admin-links', 'render' => 'botwriter_seo_page_internal_links'),
        'botwriter_seo_llmstxt' => array('label' => __('llms.txt', 'botwriter'), 'icon' => 'format-aside', 'render' => 'botwriter_seo_page_llmstxt'),
        'botwriter_seo_media_alt' => array('label' => __('Media ALT', 'botwriter'), 'icon' => 'format-image', 'render' => 'botwriter_seo_page_media_alt'),
        'botwriter_seo_settings' => array('label' => __('Settings', 'botwriter'), 'icon' => 'admin-generic', 'render' => 'botwriter_seo_page_settings'),
    );
}

function botwriter_seo_primary_page_slugs() {
    return array(
        'botwriter_seo',
        'botwriter_seo_bulk_analysis',
        'botwriter_seo_bulk_actions',
    );
}

function botwriter_seo_secondary_pages() {
    return array_diff_key(
        botwriter_seo_pages(),
        array_fill_keys(botwriter_seo_primary_page_slugs(), true)
    );
}

/**
 * Register SEO pages only under BotWriter. Non-primary pages stay accessible
 * via direct URLs and are hidden visually in the WP sidebar via CSS.
 */
add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) { return; }
    $cap = 'manage_options';

    add_submenu_page(
        'botwriter_menu',
        __('SEO', 'botwriter'),
        __('SEO', 'botwriter'),
        $cap,
        'botwriter_seo',
        'botwriter_seo_router'
    );

    foreach (botwriter_seo_pages() as $slug => $page) {
        if ($slug === 'botwriter_seo') { continue; }
        add_submenu_page('botwriter_menu', $page['label'], $page['label'], $cap, $slug, 'botwriter_seo_router');
    }
}, 100);

add_filter('parent_file', function ($parent_file) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter used to highlight the current admin section.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page && strpos($page, 'botwriter_seo') === 0) {
        return 'botwriter_menu';
    }
    return $parent_file;
});

add_filter('submenu_file', function ($submenu_file) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter used to highlight the current admin section.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page && strpos($page, 'botwriter_seo') === 0) {
        return 'botwriter_seo';
    }
    return $submenu_file;
});

/**
 * Enqueue assets on every SEO page.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter used to decide whether SEO assets are needed.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $is_seo = $page && strpos($page, 'botwriter_seo') === 0;
    if (!$is_seo) { return; }

    $base = plugin_dir_url(dirname(__FILE__)) . 'seo/assets/';
    wp_enqueue_style('botwriter-seo', $base . 'seo.css', array('dashicons'), '1.0');
    wp_enqueue_script('botwriter-seo', $base . 'seo.js', array('jquery'), '1.0', true);

    // Build a UI-friendly post type list (label + count of published items).
    $pt_options = array();
    if (function_exists('botwriter_seo_supported_post_types')) {
        foreach (botwriter_seo_supported_post_types() as $pt) {
            $obj = get_post_type_object($pt);
            if (!$obj) { continue; }
            $count = (int) wp_count_posts($pt)->publish;
            $pt_options[] = array(
                'name'  => $pt,
                'label' => $obj->labels->name,
                'count' => $count,
                'icon'  => ($pt === 'product') ? 'cart' : (($pt === 'page') ? 'admin-page' : 'admin-post'),
            );
        }
    }

    wp_localize_script('botwriter-seo', 'BotwriterSEO', array(
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bw_seo_admin'),
        'post_types' => $pt_options,
        'i18n' => array(
            'starting' => __('Starting…', 'botwriter'),
            'regenerating' => __('Regenerating…', 'botwriter'),
            'completed' => __('Completed', 'botwriter'),
            'run_again' => __('Run again', 'botwriter'),
            'retry' => __('Retry', 'botwriter'),
            'error' => __('Error', 'botwriter'),
            'network_error' => __('Network error.', 'botwriter'),
            'suggest' => __('Suggest', 'botwriter'),
            'loading' => __('Loading…', 'botwriter'),
            'fetching_live_html' => __('Fetching live HTML…', 'botwriter'),
            'view_analysis' => __('View Analysis', 'botwriter'),
            'edit_post' => __('Edit post', 'botwriter'),
            'showing' => __('Showing', 'botwriter'),
            'of' => __('of', 'botwriter'),
            'remaining' => __('remaining', 'botwriter'),
            'done_label' => __('done.', 'botwriter'),
            'findings' => __('Findings', 'botwriter'),
            'no_issues_detected' => __('No issues detected.', 'botwriter'),
            'title_label' => __('Title', 'botwriter'),
            'meta_description_label' => __('Meta description', 'botwriter'),
            'canonical_label' => __('Canonical', 'botwriter'),
            'open_graph_label' => __('Open Graph', 'botwriter'),
            'twitter_cards_label' => __('Twitter Cards', 'botwriter'),
            'json_ld_label' => __('JSON-LD', 'botwriter'),
            'valid' => __('valid', 'botwriter'),
            'invalid' => __('invalid', 'botwriter'),
            'no_results' => __('No results', 'botwriter'),
            'configure_run' => __('Configure & Run', 'botwriter'),
            'cancel' => __('Cancel', 'botwriter'),
            'run' => __('Run', 'botwriter'),
            'scope_title' => __('Choose what to analyze', 'botwriter'),
            'scope_action_title' => __('Configure bulk action', 'botwriter'),
            'post_types' => __('Content types', 'botwriter'),
            'categories' => __('Categories / terms', 'botwriter'),
            'all_terms' => __('All — no filter', 'botwriter'),
            'only_changed' => __('Only items modified since their last analysis', 'botwriter'),
            'only_changed_action' => __('Only items modified since the last action', 'botwriter'),
            'matching' => __('Matching items', 'botwriter'),
            'matching_select_type' => __('Select at least one content type to estimate the run.', 'botwriter'),
            'matching_ready' => __('These items will be processed if you press Run.', 'botwriter'),
            'matching_empty' => __('No items match the current filters yet.', 'botwriter'),
            'matching_confirm' => __('Matches found. Confirm the destructive action to enable Run.', 'botwriter'),
            'matching_error' => __('Could not estimate the current filters.', 'botwriter'),
            'recompute' => __('Recompute count', 'botwriter'),
            'filters' => __('Filters', 'botwriter'),
            'score_lt' => __('SEO score lower than', 'botwriter'),
            'score_gt' => __('SEO score greater than', 'botwriter'),
            'missing_meta' => __('Meta description shorter than (chars)', 'botwriter'),
            'hint_missing_meta' => __('Items whose meta description is empty or shorter than this will be processed.', 'botwriter'),
            'missing_seo_title' => __('Only items without a custom SEO title', 'botwriter'),
            'hint_missing_seo_title' => __('Skip posts that already have a Yoast / Rank Math / AIOSEO / native SEO title set.', 'botwriter'),
            'missing_faq' => __('Only items without an FAQ block yet', 'botwriter'),
            'hint_missing_faq' => __('Avoids regenerating FAQs that already exist (saves AI quota).', 'botwriter'),
            'intro_paragraphs' => __('Rewrite first paragraphs', 'botwriter'),
            'hint_intro_paragraphs' => __('How many opening paragraphs should be regenerated. Recommended: 2.', 'botwriter'),
            'auto_keyword' => __('Suggest and save a primary keyword if missing', 'botwriter'),
            'hint_auto_keyword' => __('Useful when the post has no focus keyword yet and you still want the intro to place it early.', 'botwriter'),
            'target_words' => __('Target minimum word count', 'botwriter'),
            'hint_target_words' => __('If the post is already above this threshold, the action skips it.', 'botwriter'),
            'demote_h1' => __('Convert in-content H1 to H2', 'botwriter'),
            'hint_demote_h1' => __('Keeps the page with a single H1 while preserving the section title text.', 'botwriter'),
            'add_subheadings' => __('Create an H3 level when the post only has H2s', 'botwriter'),
            'hint_add_subheadings' => __('Applies a light hierarchy fix without rewriting the whole article.', 'botwriter'),
            'external_links_count' => __('External references to add', 'botwriter'),
            'hint_external_links_count' => __('Number of outbound sources to append in the references block. Requires SERP API access.', 'botwriter'),
            'link_role' => __('Target by linking role', 'botwriter'),
            'link_role_any' => __('Any post', 'botwriter'),
            'link_role_orphan' => __('Orphans (no incoming links)', 'botwriter'),
            'link_role_deadend' => __('Dead-ends (no outgoing links)', 'botwriter'),
            'hint_link_role' => __('Use orphan/dead-end to focus on the posts that benefit most.', 'botwriter'),
            'slug_length_gt' => __('Slug longer than (chars)', 'botwriter'),
            'hint_slug_length' => __('Only rewrite URLs that are too long. Recommended ≥ 60.', 'botwriter'),
            'confirm_destructive' => __('I understand this changes URLs and creates 301 redirects', 'botwriter'),
            'faq_visible' => __('Show FAQ block to readers inside post content', 'botwriter'),
            'hint_faq_visible' => __('When disabled, only FAQ schema is kept for SEO rich results.', 'botwriter'),
            'cancel_job' => __('Cancel current run', 'botwriter'),
            'confirm_cancel_job' => __('Cancel current run?', 'botwriter'),
            'cancelling' => __('Cancelling…', 'botwriter'),
            'cancelled' => __('Cancelled', 'botwriter'),
            'retrying' => __('Connection hiccup. Retrying…', 'botwriter'),
            'estimating' => __('Estimating…', 'botwriter'),
            'updated' => __('Updated', 'botwriter'),
            'skipped' => __('Skipped', 'botwriter'),
            'undo_restore_confirm' => __('Restore all snapshots from this run? This will overwrite newer edits made to the same fields.', 'botwriter'),
            'undo_restore_item_confirm' => __('Restore the stored snapshot for this post? This will overwrite newer edits made to the same fields.', 'botwriter'),
            'undo_restoring' => __('Restoring…', 'botwriter'),
            'undo_item_restoring' => __('Restoring post…', 'botwriter'),
            'undo_history_error' => __('Could not load undo history.', 'botwriter'),
            'undo_detail_error' => __('Could not load undo details.', 'botwriter'),
            'undo_network_error' => __('Network error while loading undo data.', 'botwriter'),
        ),
    ));
});

/**
 * Router. Renders the chrome (header + tabs) and delegates body rendering.
 */
function botwriter_seo_router() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter for selecting the admin page renderer.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'botwriter_seo';
    $pages = botwriter_seo_pages();
    if (!isset($pages[$page])) { $page = 'botwriter_seo'; }
    $current = $pages[$page];
    $primary_pages = array_intersect_key($pages, array_fill_keys(botwriter_seo_primary_page_slugs(), true));
    $secondary_pages = botwriter_seo_secondary_pages();
    $other_active = isset($secondary_pages[$page]);

    echo '<div class="bw-seo-wrap">';
    echo '<div class="bw-seo-header">';
    echo '<div>';
    echo '<h1 class="bw-seo-title"><span class="bw-seo-logo">SEO</span> BotWriter SEO</h1>';
    echo '<p class="bw-seo-subtitle">' . esc_html__('Editorial intelligence, semantic insights and SEO autopilot for your site.', 'botwriter') . '</p>';
    echo '</div>';
    echo '</div>';

    // Tabs
    echo '<nav class="bw-seo-tabs">';
    foreach ($primary_pages as $slug => $p) {
        $url = admin_url('admin.php?page=' . $slug);
        echo '<a href="' . esc_url($url) . '"' . ($slug === $page ? ' class="is-active"' : '') . '>';
        echo '<span class="dashicons dashicons-' . esc_attr($p['icon']) . '"></span>';
        echo esc_html($p['label']);
        echo '</a>';
    }

    echo '<details class="' . esc_attr($other_active ? 'bw-seo-more is-active' : 'bw-seo-more') . '">';
    echo '<summary>';
    echo '<span class="dashicons dashicons-category"></span>';
    echo esc_html__('Other Tools & Settings', 'botwriter');
    echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
    echo '</summary>';
    echo '<div class="bw-seo-more-menu">';
    foreach ($secondary_pages as $slug => $p) {
        $url = admin_url('admin.php?page=' . $slug);
        echo '<a href="' . esc_url($url) . '"' . ($slug === $page ? ' class="is-active"' : '') . '>';
        echo '<span class="dashicons dashicons-' . esc_attr($p['icon']) . '"></span>';
        echo esc_html($p['label']);
        echo '</a>';
    }
    echo '</div>';
    echo '</details>';
    echo '</nav>';

    // Body
    if (function_exists($current['render'])) {
        call_user_func($current['render']);
    } else {
        echo '<div class="bw-card"><div class="bw-empty"><span class="dashicons dashicons-info"></span><p>' . esc_html__('Page not implemented yet.', 'botwriter') . '</p></div></div>';
    }

    echo '</div>';
}

/**
 * Helpers used by page renderers.
 */
function botwriter_seo_card_open($title, $icon = 'admin-generic') {
    echo '<div class="bw-card"><h3><span class="dashicons dashicons-' . esc_attr($icon) . '"></span> ' . esc_html($title) . '</h3>';
}
function botwriter_seo_card_close() { echo '</div>'; }
function botwriter_seo_kpi($label, $value, $icon = 'chart-bar', $color = 'primary', $delta = '') {
    echo '<div class="bw-card"><div class="bw-kpi">';
    echo '<span class="icon bg-' . esc_attr($color) . '"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span></span>';
    echo '<span class="label">' . esc_html($label) . '</span>';
    echo '<span class="value">' . esc_html((string) $value) . '</span>';
    if ($delta !== '') { echo '<span class="delta">' . esc_html($delta) . '</span>'; }
    echo '</div></div>';
}

require_once __DIR__ . '/pages/dashboard.php';
require_once __DIR__ . '/pages/bulk-analysis.php';
require_once __DIR__ . '/pages/bulk-actions.php';
require_once __DIR__ . '/pages/internal-links.php';
require_once __DIR__ . '/pages/settings.php';
