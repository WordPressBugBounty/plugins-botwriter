<?php
/**
 * BotWriter SEO — per-post score (Phase 1.1).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_get_meta_title($post_id, $fallback = '') {
    $title = function_exists('botwriter_seo_get_meta')
        ? trim((string) botwriter_seo_get_meta($post_id, 'title'))
        : '';

    if ($title !== '') {
        return $title;
    }

    return trim((string) $fallback);
}

function botwriter_seo_get_faq_state($post_id) {
    $faqs = get_post_meta($post_id, '_botwriter_seo_faq', true);
    $count = is_array($faqs) ? count($faqs) : 0;
    $visible_raw = get_post_meta($post_id, '_botwriter_seo_faq_visible', true);
    $visible = $count > 0 && ($visible_raw === '' || (int) $visible_raw !== 0);
    $mode = 'none';
    if ($count > 0) {
        $mode = $visible ? 'visible_schema' : 'schema_only';
    }

    return array(
        'count' => $count,
        'visible' => $visible,
        'mode' => $mode,
        'hash' => $count . '|' . ($visible ? '1' : '0'),
    );
}

function botwriter_seo_get_embedding_state($post_id) {
    global $wpdb;

    $table = esc_sql(botwriter_seo_table('embeddings'));
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for use as an identifier.
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT model, updated_at FROM {$table} WHERE post_id=%d", $post_id),
        ARRAY_A
    );

    if (!is_array($row)) {
        return array(
            'indexed' => false,
            'model' => '',
            'updated_at' => 0,
            'hash' => '0',
        );
    }

    return array(
        'indexed' => true,
        'model' => (string) ($row['model'] ?? ''),
        'updated_at' => (int) ($row['updated_at'] ?? 0),
        'hash' => '1|' . (string) ($row['model'] ?? '') . '|' . (int) ($row['updated_at'] ?? 0),
    );
}

/**
 * Compute the SEO score for a post and return a structured report.
 * Cached as post meta with a content hash so it auto-invalidates.
 *
 * @param int  $post_id
 * @param bool $force Recompute even if cache is fresh.
 * @return array{score:int, grade:string, checks:array, computed_at:int}
 */
function botwriter_seo_compute_score($post_id, $force = false) {
    $post_id = intval($post_id);
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return array('score' => 0, 'grade' => 'n/a', 'checks' => array(), 'computed_at' => 0);
    }

    $allowed = function_exists('botwriter_seo_supported_post_types')
        ? botwriter_seo_supported_post_types()
        : array('post', 'page');
    if (!in_array($post->post_type, $allowed, true)) {
        return array('score' => 0, 'grade' => 'n/a', 'checks' => array(), 'computed_at' => 0);
    }

    $content = (string) $post->post_content;

    $primary_keyword = trim((string) get_post_meta($post_id, '_botwriter_seo_primary_keyword', true));
    if ($primary_keyword === '') {
        // Fall back to first tag as primary keyword.
        $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
        if (is_array($tags) && !empty($tags)) {
            $primary_keyword = (string) $tags[0];
        }
    }

    $title = botwriter_seo_get_meta_title($post_id, (string) $post->post_title);
    $meta_desc = botwriter_seo_get_meta_description($post_id);
    $faq_state = botwriter_seo_get_faq_state($post_id);
    $embedding_state = botwriter_seo_get_embedding_state($post_id);
    $featured_image_id = (int) get_post_thumbnail_id($post_id);
    $featured_image_alt = '';
    if ($featured_image_id > 0) {
        $featured_image_alt = trim((string) get_post_meta($featured_image_id, '_wp_attachment_image_alt', true));
    }
    $hash = sha1(implode('|', array(
        (string) $post->post_title,
        $content,
        (string) $post->post_modified_gmt,
        (string) $post->post_name,
        $primary_keyword,
        $title,
        $meta_desc,
        (string) $faq_state['hash'],
        (string) $embedding_state['hash'],
        (string) $featured_image_id,
        $featured_image_alt,
    )));

    if (!$force) {
        $cached = get_post_meta($post_id, '_botwriter_seo_report', true);
        if (is_array($cached) && !empty($cached['hash']) && $cached['hash'] === $hash) {
            update_post_meta($post_id, '_botwriter_seo_analyzed_at', time());
            return $cached;
        }
    }

    $kw_norm = strtolower(remove_accents($primary_keyword));

    $plain = wp_strip_all_tags($content);
    $word_count = botwriter_seo_word_count($plain);
    $title_len = function_exists('mb_strlen') ? (int) mb_strlen($title, 'UTF-8') : strlen($title);
    $meta_len = function_exists('mb_strlen') ? (int) mb_strlen($meta_desc, 'UTF-8') : strlen($meta_desc);

    $first_para = botwriter_seo_first_paragraph($content);
    $first_para_norm = strtolower(remove_accents(wp_strip_all_tags($first_para)));

    // Heading / link / image stats via DOM.
    $stats = botwriter_seo_dom_stats($content);
    $stats['has_featured_image'] = $featured_image_id > 0 ? 1 : 0;

    if ($featured_image_id > 0) {
        $featured_in_content = strpos((string) $content, 'wp-image-' . $featured_image_id) !== false;
        if (!$featured_in_content) {
            $stats['images']++;
            if ($featured_image_alt === '') {
                $stats['images_missing_alt']++;
            }
        }
    }

    $checks = array();
    $score_max = 0;
    $score_got = 0;

    $add = function ($id, $label, $weight, $passed, $hint = '') use (&$checks, &$score_max, &$score_got) {
        $score_max += $weight;
        if ($passed) {
            $score_got += $weight;
        }
        $checks[] = array(
            'id' => $id,
            'label' => $label,
            'weight' => $weight,
            'passed' => (bool) $passed,
            'hint' => (string) $hint,
        );
    };

    // Title
    $add('title_len', __('SEO title length 30–65 chars', 'botwriter'), 8, ($title_len >= 30 && $title_len <= 65), sprintf('%d chars', $title_len));
    $add('title_keyword', __('Primary keyword in title', 'botwriter'), 8,
        ($kw_norm !== '' && strpos(strtolower(remove_accents($title)), $kw_norm) !== false),
        $kw_norm === '' ? __('No keyword set', 'botwriter') : '');

    // Meta description
    $add('meta_present', __('Meta description present', 'botwriter'), 6, ($meta_len > 0));
    $add('meta_length', __('Meta description 80–160 chars', 'botwriter'), 6, ($meta_len >= 80 && $meta_len <= 160), sprintf('%d chars', $meta_len));
    $add('meta_keyword', __('Primary keyword in meta description', 'botwriter'), 6,
        ($kw_norm !== '' && $meta_len > 0 && strpos(strtolower(remove_accents($meta_desc)), $kw_norm) !== false));

    // Content
    $add('word_count', __('Word count ≥ 300', 'botwriter'), 8, ($word_count >= 300), sprintf('%d words', $word_count));
    $add('first_para_keyword', __('Primary keyword in first paragraph', 'botwriter'), 6,
        ($kw_norm !== '' && strpos($first_para_norm, $kw_norm) !== false));

    // Headings
    $add('h2_present', __('At least one H2', 'botwriter'), 6, ($stats['h2'] >= 1), sprintf('h2=%d', $stats['h2']));
    $add('no_h1_in_content', __('No H1 inside the content', 'botwriter'), 4, ($stats['h1'] === 0), sprintf('h1=%d', $stats['h1']));
    $add('headings_hierarchy', __('Sub-headings used (H3+)', 'botwriter'), 4, ($stats['h3'] + $stats['h4'] >= 1));

    // Links
    $add('internal_links', __('At least one internal link', 'botwriter'), 8, ($stats['links_internal'] >= 1), sprintf('%d internal', $stats['links_internal']));
    $add('external_links', __('At least one external reference', 'botwriter'), 4, ($stats['links_external'] >= 1));

    // FAQ enrichment (informational, not scored)
    $faq_hint = __('No FAQ generated', 'botwriter');
    if ($faq_state['count'] > 0) {
        $faq_hint = sprintf(
            /* translators: 1: FAQ count, 2: FAQ visibility mode. */
            __('%1$d FAQs · %2$s', 'botwriter'),
            (int) $faq_state['count'],
            $faq_state['visible'] ? __('Visible + schema', 'botwriter') : __('Schema only', 'botwriter')
        );
    }
    $add('faq_present', __('FAQ enrichment available', 'botwriter'), 0, ($faq_state['count'] > 0), $faq_hint);

    // Images
    $add('images_present', __('Has at least one image', 'botwriter'), 4, ($stats['images'] >= 1));
    $add('images_alt', __('All images have alt text', 'botwriter'), 6,
        ($stats['images'] === 0) || ($stats['images_missing_alt'] === 0),
        sprintf('%d missing alt', $stats['images_missing_alt']));

    // Slug
    $slug = (string) $post->post_name;
    $slug_len = strlen($slug);
    $add('slug_length', __('Slug 3–60 chars', 'botwriter'), 4, ($slug_len >= 3 && $slug_len <= 60), sprintf('%d chars', $slug_len));
    $add('slug_keyword', __('Primary keyword in slug', 'botwriter'), 4,
        ($kw_norm !== '' && strpos(strtolower(remove_accents($slug)), str_replace(' ', '-', $kw_norm)) !== false));

    $stats['faq_count'] = (int) $faq_state['count'];
    $stats['faq_visible'] = !empty($faq_state['visible']) ? 1 : 0;
    $stats['faq_mode'] = (string) $faq_state['mode'];
    $stats['embedding_indexed'] = !empty($embedding_state['indexed']) ? 1 : 0;
    $stats['embedding_model'] = (string) ($embedding_state['model'] ?? '');
    $stats['embedding_updated_at'] = (int) ($embedding_state['updated_at'] ?? 0);

    $score = $score_max > 0 ? (int) round(($score_got / $score_max) * 100) : 0;

    if ($score >= 85) {
        $grade = 'excellent';
    } elseif ($score >= 70) {
        $grade = 'good';
    } elseif ($score >= 50) {
        $grade = 'fair';
    } else {
        $grade = 'poor';
    }

    $report = array(
        'score' => $score,
        'grade' => $grade,
        'seo_title' => $title,
        'word_count' => $word_count,
        'meta_len' => $meta_len,
        'title_len' => $title_len,
        'primary_keyword' => $primary_keyword,
        'stats' => $stats,
        'checks' => $checks,
        'hash' => $hash,
        'computed_at' => time(),
    );

    update_post_meta($post_id, '_botwriter_seo_score', $score);
    update_post_meta($post_id, '_botwriter_seo_report', $report);
    update_post_meta($post_id, '_botwriter_seo_analyzed_at', time());

    return $report;
}

function botwriter_seo_word_count($text) {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') { return 0; }
    $tokens = preg_split('/\s+/u', $text);
    return is_array($tokens) ? count($tokens) : 0;
}

function botwriter_seo_first_paragraph($html) {
    if (preg_match('/<p[^>]*>(.*?)<\/p>/is', (string) $html, $m)) {
        return $m[1];
    }
    $plain = wp_strip_all_tags((string) $html);
    return function_exists('mb_substr') ? mb_substr($plain, 0, 600, 'UTF-8') : substr($plain, 0, 600);
}

function botwriter_seo_get_meta_description($post_id) {
    $sources = array(
        get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
        get_post_meta($post_id, 'rank_math_description', true),
        get_post_meta($post_id, '_aioseop_description', true),
        get_post_meta($post_id, '_botwriter_seo_meta_description', true),
        get_post_field('post_excerpt', $post_id),
    );
    foreach ($sources as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function botwriter_seo_dom_stats($html) {
    $stats = array(
        'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0,
        'links_internal' => 0, 'links_external' => 0,
        'images' => 0, 'images_missing_alt' => 0,
        'paragraphs' => 0,
    );

    if (!class_exists('DOMDocument') || trim((string) $html) === '') {
        return $stats;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $home = wp_parse_url(home_url('/'), PHP_URL_HOST);

    foreach (array('h1', 'h2', 'h3', 'h4', 'p', 'img', 'a') as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        if ($tag === 'h1') { $stats['h1'] = $nodes->length; }
        elseif ($tag === 'h2') { $stats['h2'] = $nodes->length; }
        elseif ($tag === 'h3') { $stats['h3'] = $nodes->length; }
        elseif ($tag === 'h4') { $stats['h4'] = $nodes->length; }
        elseif ($tag === 'p') { $stats['paragraphs'] = $nodes->length; }
        elseif ($tag === 'img') {
            $stats['images'] = $nodes->length;
            foreach ($nodes as $img) {
                if (!$img->hasAttribute('alt') || trim((string) $img->getAttribute('alt')) === '') {
                    $stats['images_missing_alt']++;
                }
            }
        } elseif ($tag === 'a') {
            foreach ($nodes as $a) {
                $href = (string) $a->getAttribute('href');
                if ($href === '' || stripos($href, 'mailto:') === 0 || stripos($href, '#') === 0) { continue; }
                $host = wp_parse_url($href, PHP_URL_HOST);
                if (!$host || $host === $home) {
                    $stats['links_internal']++;
                } else {
                    $stats['links_external']++;
                }
            }
        }
    }

    return $stats;
}

/**
 * Recompute on save_post (debounced via transient).
 */
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    $allowed = function_exists('botwriter_seo_supported_post_types')
        ? botwriter_seo_supported_post_types()
        : array('post', 'page');
    if (!in_array($post->post_type, $allowed, true)) { return; }
    if ($post->post_status !== 'publish') { return; }
    botwriter_seo_compute_score($post_id, true);
}, 30, 2);

/**
 * Admin column on the posts table.
 */
add_filter('manage_post_posts_columns', function ($columns) {
    $columns['botwriter_seo'] = __('SEO', 'botwriter');
    return $columns;
});
add_action('manage_post_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'botwriter_seo') { return; }
    $score = (int) get_post_meta($post_id, '_botwriter_seo_score', true);
    if ($score <= 0) {
        $report = botwriter_seo_compute_score($post_id);
        $score = (int) ($report['score'] ?? 0);
    }
    $grade = ($score >= 85) ? 'excellent' : (($score >= 70) ? 'good' : (($score >= 50) ? 'fair' : 'poor'));
    echo '<span class="bw-seo-badge bw-seo-' . esc_attr($grade) . '" title="' . esc_attr(sprintf('SEO score: %d', $score)) . '">' . esc_html((string) $score) . '</span>';
}, 10, 2);
