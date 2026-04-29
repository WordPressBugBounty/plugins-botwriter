<?php
/**
 * BotWriter SEO — site audit (Phase 1.4).
 * Internal-link graph: incoming/outgoing per post, orphans and dead-ends.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build (or refresh) the internal link graph.
 * Returns: array(post_id => array('outgoing'=>int[], 'incoming'=>int[]))
 *
 * Cached as a site option for 6 hours unless $force.
 */
function botwriter_seo_build_link_graph($force = false) {
    if (!$force) {
        $cached = get_transient('botwriter_seo_link_graph');
        if (is_array($cached)) { return $cached; }
    }

    global $wpdb;
    $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

    $rows = $wpdb->get_results(
        "SELECT ID, post_content FROM {$wpdb->posts}
         WHERE post_status='publish' AND post_type IN ('post','page')",
        ARRAY_A
    );
    if (!is_array($rows)) { $rows = array(); }

    // Build slug → id map.
    $slug_to_id = array();
    foreach ($rows as $r) {
        $url = get_permalink($r['ID']);
        $path = wp_parse_url($url, PHP_URL_PATH);
        if ($path) {
            $slug_to_id[trim($path, '/')] = (int) $r['ID'];
        }
    }

    $graph = array();
    foreach ($rows as $r) {
        $id = (int) $r['ID'];
        $graph[$id] = array('outgoing' => array(), 'incoming' => array());
    }

    foreach ($rows as $r) {
        $id = (int) $r['ID'];
        if (!preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', (string) $r['post_content'], $m)) { continue; }
        foreach ($m[1] as $href) {
            if ($href === '' || stripos($href, 'mailto:') === 0 || $href[0] === '#') { continue; }
            $host = wp_parse_url($href, PHP_URL_HOST);
            if ($host && $host !== $home_host) { continue; }
            $path = trim((string) wp_parse_url($href, PHP_URL_PATH), '/');
            if ($path === '' || !isset($slug_to_id[$path])) { continue; }
            $target = $slug_to_id[$path];
            if ($target === $id) { continue; }
            if (!in_array($target, $graph[$id]['outgoing'], true)) {
                $graph[$id]['outgoing'][] = $target;
            }
            if (!in_array($id, $graph[$target]['incoming'], true)) {
                $graph[$target]['incoming'][] = $id;
            }
        }
    }

    set_transient('botwriter_seo_link_graph', $graph, 6 * HOUR_IN_SECONDS);
    return $graph;
}

function botwriter_seo_audit_summary($force = false) {
    $graph = botwriter_seo_build_link_graph($force);
    $orphans = array();
    $deadends = array();
    $hubs = array();
    $total = 0;
    foreach ($graph as $id => $node) {
        $in = count($node['incoming']);
        $out = count($node['outgoing']);
        $total += $out;
        if ($in === 0) { $orphans[] = $id; }
        if ($out === 0) { $deadends[] = $id; }
        $hubs[$id] = $in;
    }
    arsort($hubs);
    return array(
        'total_posts' => count($graph),
        'total_internal_links' => $total,
        'orphans' => $orphans,
        'deadends' => $deadends,
        'top_hubs' => array_slice($hubs, 0, 10, true),
    );
}

add_action('save_post', function ($post_id) {
    delete_transient('botwriter_seo_link_graph');
}, 100);
