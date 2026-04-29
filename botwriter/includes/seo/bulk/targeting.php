<?php
/**
 * BotWriter SEO — generic post targeting for bulk jobs.
 *
 * Builds the WHERE clause used by analysis + actions jobs, supporting:
 *   - post_types[]   (default: post, page)
 *   - term_ids[]     (term_taxonomy_id whitelist; OR semantics across taxonomies)
 *   - only_changed   (post_modified > _botwriter_seo_analyzed_at)
 *   - score_lt / score_gt
 *   - missing_meta_max (meta description length below N → 0 means empty)
 *   - missing_alt    (post has at least one image without alt — slow, applied per batch)
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Allowed post types for SEO bulk jobs (filterable).
 */
function botwriter_seo_supported_post_types() {
    $types = array('post', 'page');
    if (post_type_exists('product')) {
        $types[] = 'product';
    }
    /**
     * Filter the post types selectable in scope modals.
     */
    return apply_filters('botwriter_seo_supported_post_types', $types);
}

/**
 * Sanitize args coming from the front-end.
 */
function botwriter_seo_sanitize_target_args($args) {
    $args = is_array($args) ? $args : array();
    $allowed_types = botwriter_seo_supported_post_types();
    $post_types = isset($args['post_types']) ? (array) $args['post_types'] : $allowed_types;
    $post_types = array_values(array_intersect($post_types, $allowed_types));
    if (empty($post_types)) { $post_types = $allowed_types; }

    $term_ids = isset($args['term_ids']) ? array_map('intval', (array) $args['term_ids']) : array();
    $term_ids = array_values(array_filter($term_ids, function ($v) { return $v > 0; }));

    $link_role = isset($args['link_role']) ? sanitize_key((string) $args['link_role']) : '';
    if (!in_array($link_role, array('orphan', 'deadend', 'any'), true)) { $link_role = ''; }

    return array(
        'post_types'        => $post_types,
        'term_ids'          => $term_ids,
        'only_changed'      => !empty($args['only_changed']),
        'score_lt'          => isset($args['score_lt']) && $args['score_lt'] !== '' ? max(0, min(100, (int) $args['score_lt'])) : null,
        'score_gt'          => isset($args['score_gt']) && $args['score_gt'] !== '' ? max(0, min(100, (int) $args['score_gt'])) : null,
        'missing_meta_max'  => isset($args['missing_meta_max']) && $args['missing_meta_max'] !== '' ? max(0, (int) $args['missing_meta_max']) : null,
        'missing_alt'       => !empty($args['missing_alt']),
        'missing_faq'       => !empty($args['missing_faq']),
        'missing_seo_title' => !empty($args['missing_seo_title']),
        'has_images'        => !empty($args['has_images']),
        'slug_length_gt'    => isset($args['slug_length_gt']) && $args['slug_length_gt'] !== '' ? max(0, (int) $args['slug_length_gt']) : null,
        'link_role'         => $link_role,
        'confirm_destructive' => !empty($args['confirm_destructive']),
        // Keep through any extra args (e.g. action key)
        'action'            => isset($args['action']) ? sanitize_key($args['action']) : '',
    );
}

/**
 * Build the SQL fragment + params needed to target posts.
 * Returns array(string $sql, array $params).
 */
function botwriter_seo_target_query_parts($args) {
    global $wpdb;
    $args = botwriter_seo_sanitize_target_args($args);
    $params = array();

    $placeholders = implode(',', array_fill(0, count($args['post_types']), '%s'));
    $where = "p.post_status='publish' AND p.post_type IN ($placeholders)";
    foreach ($args['post_types'] as $pt) { $params[] = $pt; }

    $joins = '';

    if (!empty($args['term_ids'])) {
        $tt_in = implode(',', array_map('intval', $args['term_ids']));
        $where .= " AND p.ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($tt_in))";
    }

    if ($args['score_lt'] !== null || $args['score_gt'] !== null) {
        $joins .= " INNER JOIN {$wpdb->postmeta} pm_score ON pm_score.post_id=p.ID AND pm_score.meta_key='_botwriter_seo_score'";
        if ($args['score_lt'] !== null) {
            $where .= " AND CAST(pm_score.meta_value AS UNSIGNED) < %d";
            $params[] = $args['score_lt'];
        }
        if ($args['score_gt'] !== null) {
            $where .= " AND CAST(pm_score.meta_value AS UNSIGNED) > %d";
            $params[] = $args['score_gt'];
        }
    }

    if (!empty($args['only_changed'])) {
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_an ON pm_an.post_id=p.ID AND pm_an.meta_key='_botwriter_seo_analyzed_at'";
        $where .= " AND (pm_an.meta_value IS NULL OR p.post_modified_gmt > FROM_UNIXTIME(CAST(pm_an.meta_value AS UNSIGNED)))";
    }

    if ($args['missing_meta_max'] !== null) {
        // Concatenated COALESCE across known SEO description metas + post_excerpt.
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_y ON pm_y.post_id=p.ID AND pm_y.meta_key='_yoast_wpseo_metadesc'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_r ON pm_r.post_id=p.ID AND pm_r.meta_key='rank_math_description'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_a ON pm_a.post_id=p.ID AND pm_a.meta_key='_aioseop_description'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_b ON pm_b.post_id=p.ID AND pm_b.meta_key='_botwriter_seo_meta_description'";
        $where .= " AND CHAR_LENGTH(COALESCE(NULLIF(pm_y.meta_value,''),NULLIF(pm_r.meta_value,''),NULLIF(pm_a.meta_value,''),NULLIF(pm_b.meta_value,''),NULLIF(p.post_excerpt,''),'')) < %d";
        $params[] = $args['missing_meta_max'];
    }

    if (!empty($args['missing_seo_title'])) {
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_yt ON pm_yt.post_id=p.ID AND pm_yt.meta_key='_yoast_wpseo_title'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_rt ON pm_rt.post_id=p.ID AND pm_rt.meta_key='rank_math_title'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_at ON pm_at.post_id=p.ID AND pm_at.meta_key='_aioseop_title'";
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_bt ON pm_bt.post_id=p.ID AND pm_bt.meta_key='_botwriter_seo_title'";
        $where .= " AND COALESCE(NULLIF(pm_yt.meta_value,''),NULLIF(pm_rt.meta_value,''),NULLIF(pm_at.meta_value,''),NULLIF(pm_bt.meta_value,'')) IS NULL";
    }

    if (!empty($args['missing_faq'])) {
        $joins .= " LEFT JOIN {$wpdb->postmeta} pm_faq ON pm_faq.post_id=p.ID AND pm_faq.meta_key='_botwriter_seo_faq'";
        $where .= " AND (pm_faq.meta_value IS NULL OR pm_faq.meta_value='' OR pm_faq.meta_value='a:0:{}')";
    }

    if (!empty($args['missing_alt']) || !empty($args['has_images'])) {
        // Cheap "has images" via post_content LIKE '<img'. Fine-grained missing-alt
        // detection happens inside the action itself (DOM-based) for accuracy.
        $where .= " AND p.post_content LIKE '%<img %'";
    }

    if ($args['slug_length_gt'] !== null && $args['slug_length_gt'] > 0) {
        $where .= " AND CHAR_LENGTH(p.post_name) > %d";
        $params[] = $args['slug_length_gt'];
    }

    if ($args['link_role'] === 'orphan' || $args['link_role'] === 'deadend') {
        $audit = function_exists('botwriter_seo_audit_summary') ? botwriter_seo_audit_summary() : array();
        $list = ($args['link_role'] === 'orphan')
            ? (array) ($audit['orphans'] ?? array())
            : (array) ($audit['deadends'] ?? array());
        $list = array_filter(array_map('intval', $list));
        if (empty($list)) {
            $where .= " AND 0=1"; // no matches
        } else {
            $in = implode(',', $list);
            $where .= " AND p.ID IN ($in)";
        }
    }

    return array($joins, $where, $params);
}

/**
 * Count matching posts.
 */
function botwriter_seo_target_count($args) {
    global $wpdb;
    list($joins, $where, $params) = botwriter_seo_target_query_parts($args);
    $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$joins} WHERE {$where}";
    if ($params) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query string is prepared dynamically from trusted table names and sanitized clauses.
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query string is built from trusted table names and sanitized clauses.
    return (int) $wpdb->get_var($sql);
}

/**
 * Fetch a batch of matching IDs after $cursor.
 */
function botwriter_seo_target_ids($args, $cursor, $limit) {
    global $wpdb;
    list($joins, $where, $params) = botwriter_seo_target_query_parts($args);
    $where .= " AND p.ID > %d";
    $params[] = (int) $cursor;
    $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$joins} WHERE {$where} ORDER BY p.ID ASC LIMIT %d";
    $params[] = (int) $limit;
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query string is prepared dynamically from trusted table names and sanitized clauses.
    return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $params)));
}

/**
 * For UI: list taxonomies attached to the given post types and their top terms.
 */
function botwriter_seo_taxonomies_for_post_types($post_types) {
    $out = array();
    foreach ((array) $post_types as $pt) {
        $taxes = get_object_taxonomies($pt, 'objects');
        foreach ($taxes as $tax) {
            if (!$tax->public && !$tax->show_ui) { continue; }
            if (!isset($out[$tax->name])) {
                $out[$tax->name] = array(
                    'name'      => $tax->name,
                    'label'     => $tax->labels->singular_name,
                    'post_type' => $pt,
                );
            }
        }
    }
    return $out;
}

add_action('wp_ajax_bw_seo_terms', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each entry is sanitized via sanitize_key() on the next line.
    $post_types = isset($_POST['post_types']) ? (array) wp_unslash($_POST['post_types']) : array();
    $post_types = array_values(array_intersect(array_map('sanitize_key', $post_types), botwriter_seo_supported_post_types()));
    if (empty($post_types)) { wp_send_json_success(array('taxonomies' => array())); }

    $taxes = botwriter_seo_taxonomies_for_post_types($post_types);
    $out = array();
    foreach ($taxes as $name => $tax) {
        $terms = get_terms(array(
            'taxonomy'   => $name,
            'hide_empty' => true,
            'number'     => 200,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));
        if (is_wp_error($terms)) { continue; }
        $out[$name] = array(
            'name'      => $name,
            'label'     => $tax['label'],
            'post_type' => $tax['post_type'],
            'terms'     => array_map(function ($t) {
                return array(
                    'id'    => (int) $t->term_taxonomy_id,
                    'name'  => $t->name,
                    'count' => (int) $t->count,
                );
            }, $terms),
        );
    }
    wp_send_json_success(array('taxonomies' => $out));
});

/**
 * AJAX: return how many items match a given target args object (for modal preview).
 */
add_action('wp_ajax_bw_seo_target_count', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $args = array();
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON payload; parsed via json_decode() and field-level sanitization happens downstream.
    $raw_args = isset($_POST['args']) ? wp_unslash($_POST['args']) : '';
    if ($raw_args !== '') {
        $decoded = json_decode($raw_args, true);
        if (is_array($decoded)) { $args = $decoded; }
    }
    // Apply per-action defaults if applicable.
    if (function_exists('botwriter_seo_actions_default_filters') && !empty($args['action'])) {
        $args = botwriter_seo_actions_default_filters($args);
    }
    wp_send_json_success(array('count' => botwriter_seo_target_count($args)));
});
