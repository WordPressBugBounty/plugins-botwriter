<?php
/**
 * BotWriter SEO — SERP analysis (Phase 3.3).
 * Generic adapter that talks to SerpAPI when an API key is set.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_serp_provider() {
    return get_option('botwriter_seo_serp_provider', 'serpapi');
}
function botwriter_seo_serp_key() {
    return (string) get_option('botwriter_seo_serp_api_key', '');
}

function botwriter_seo_serp_top($keyword, $hl = 'en', $gl = 'us') {
    $key = botwriter_seo_serp_key();
    if ($key === '') { return null; }
    $cache_key = 'bw_serp_' . md5($keyword . '|' . $hl . '|' . $gl);
    $cached = get_transient($cache_key);
    if (is_array($cached)) { return $cached; }
    $url = add_query_arg(array(
        'engine' => 'google', 'q' => $keyword, 'hl' => $hl, 'gl' => $gl, 'num' => 10,
        'api_key' => $key,
    ), 'https://serpapi.com/search.json');
    $resp = wp_remote_get($url, array(
        'timeout' => 30,
        'sslverify' => (get_option('botwriter_sslverify', 'yes') === 'yes'),
    ));
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) { return null; }
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body)) { return null; }
    $organic = array();
    foreach ((array) ($body['organic_results'] ?? array()) as $r) {
        $organic[] = array(
            'position' => (int) ($r['position'] ?? 0),
            'title' => (string) ($r['title'] ?? ''),
            'link' => (string) ($r['link'] ?? ''),
            'snippet' => (string) ($r['snippet'] ?? ''),
        );
    }
    $paa = array();
    foreach ((array) ($body['related_questions'] ?? array()) as $q) {
        $paa[] = (string) ($q['question'] ?? '');
    }
    $out = array('organic' => $organic, 'paa' => $paa);
    set_transient($cache_key, $out, 12 * HOUR_IN_SECONDS);
    return $out;
}
