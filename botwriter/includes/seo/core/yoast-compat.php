<?php
/**
 * BotWriter SEO — Yoast / Rank Math / AIOSEO compatibility layer (Phase 1.5).
 * Read existing meta from the dominant SEO plugin and write back to it.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_active_plugin() {
    if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) { return 'yoast'; }
    if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) { return 'rankmath'; }
    if (defined('AIOSEO_VERSION') || function_exists('aioseo')) { return 'aioseo'; }
    return 'native';
}

function botwriter_seo_meta_keys($which) {
    $map = array(
        'yoast' => array(
            'title' => '_yoast_wpseo_title',
            'desc' => '_yoast_wpseo_metadesc',
            'focus' => '_yoast_wpseo_focuskw',
        ),
        'rankmath' => array(
            'title' => 'rank_math_title',
            'desc' => 'rank_math_description',
            'focus' => 'rank_math_focus_keyword',
        ),
        'aioseo' => array(
            'title' => '_aioseop_title',
            'desc' => '_aioseop_description',
            'focus' => '_aioseop_keywords',
        ),
        'native' => array(
            'title' => '_botwriter_seo_title',
            'desc' => '_botwriter_seo_meta_description',
            'focus' => '_botwriter_seo_primary_keyword',
        ),
    );
    return $map[$which] ?? $map['native'];
}

function botwriter_seo_get_meta($post_id, $field) {
    $keys = botwriter_seo_meta_keys(botwriter_seo_active_plugin());
    if (!isset($keys[$field])) { return ''; }
    $value = get_post_meta($post_id, $keys[$field], true);
    if ($value !== '') { return $value; }
    // Fallback to native key if SEO plugin meta empty.
    $native = botwriter_seo_meta_keys('native');
    return (string) get_post_meta($post_id, $native[$field], true);
}

function botwriter_seo_set_meta($post_id, $field, $value) {
    $value = (string) $value;
    $active = botwriter_seo_meta_keys(botwriter_seo_active_plugin());
    $native = botwriter_seo_meta_keys('native');
    if (isset($active[$field])) { update_post_meta($post_id, $active[$field], $value); }
    if (isset($native[$field])) { update_post_meta($post_id, $native[$field], $value); }
    return true;
}
