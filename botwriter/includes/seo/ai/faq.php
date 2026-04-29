<?php
/**
 * BotWriter SEO — FAQ generator (Phase 2.2).
 * Generates 4–6 FAQ items via AI, stores them in post meta and renders
 * an HTML block + FAQPage JSON-LD on the front-end.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_faq_log($message, $context = array()) {
    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO FAQ] ' . $message, is_array($context) ? $context : array('context' => $context));
    }
}

function botwriter_seo_faq_extract_ai_text($resp) {
    if (is_wp_error($resp)) {
        return array(
            'ok' => false,
            'text' => '',
            'format' => 'wp_error',
            'error' => $resp->get_error_message(),
        );
    }
    if (is_string($resp)) {
        $text = trim($resp);
        return array(
            'ok' => $text !== '',
            'text' => $text,
            'format' => 'string',
            'error' => $text === '' ? 'empty_string' : '',
        );
    }
    if (is_array($resp)) {
        $ok = !empty($resp['ok']);
        $text = trim((string) ($resp['text'] ?? ''));
        if (!$ok && $text !== '') { $ok = true; }
        return array(
            'ok' => $ok,
            'text' => $text,
            'format' => 'array',
            'error' => (string) ($resp['error'] ?? ($ok ? '' : 'empty_or_not_ok')),
        );
    }
    return array(
        'ok' => false,
        'text' => '',
        'format' => gettype($resp),
        'error' => 'unsupported_response_format',
    );
}

function botwriter_seo_generate_faq_for_post($post_id) {
    $t = microtime(true);
    botwriter_seo_faq_log('start generate faq', array('post_id' => (int) $post_id));

    $post = get_post($post_id);
    if (!$post) {
        botwriter_seo_faq_log('skip (post not found)', array('post_id' => (int) $post_id));
        return false;
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_faq_log('skip (missing API key)', array(
            'post_id' => (int) $post_id,
            'provider' => (string) ($config['provider'] ?? ''),
            'model' => (string) ($config['model'] ?? ''),
        ));
        return false;
    }

    $body = wp_strip_all_tags($post->post_content);
    if (function_exists('mb_substr')) { $body = mb_substr($body, 0, 4000, 'UTF-8'); }

    $prompt = "You are an SEO editor. Given the article below, generate between 4 and 6 question/answer pairs that are RELEVANT, NON-OBVIOUS and answer common doubts a reader of that article may have.\n"
        . "Reply STRICTLY as a JSON object: {\"faqs\":[{\"q\":\"...\",\"a\":\"...\"}]}.\n"
        . "Use the SAME LANGUAGE as the article. Each answer between 30 and 80 words. No markdown.\n\n"
        . "TITLE: " . $post->post_title . "\n\nCONTENT:\n" . $body;

    $t_ai = microtime(true);
    botwriter_seo_faq_log('AI call start', array(
        'post_id' => (int) $post_id,
        'provider' => (string) ($config['provider'] ?? ''),
        'model' => (string) ($config['model'] ?? ''),
        'prompt_len' => strlen($prompt),
    ));
    $resp = botwriter_call_editor_worker($config['provider'], $config['key'], $config['model'], $prompt, 1200, 0.6);
    $ai = botwriter_seo_faq_extract_ai_text($resp);
    botwriter_seo_faq_log('AI call end', array(
        'post_id' => (int) $post_id,
        'ok' => !empty($ai['ok']) ? 1 : 0,
        'format' => (string) ($ai['format'] ?? ''),
        'error' => (string) ($ai['error'] ?? ''),
        'text_len' => strlen((string) ($ai['text'] ?? '')),
        'elapsed_ms' => round((microtime(true) - $t_ai) * 1000),
    ));
    if (empty($ai['ok']) || empty($ai['text'])) { return false; }

    $data = botwriter_seo_parse_json_loose($ai['text']);
    if (!$data || empty($data['faqs']) || !is_array($data['faqs'])) {
        botwriter_seo_faq_log('parse failed: no faqs array', array(
            'post_id' => (int) $post_id,
            'raw_excerpt' => function_exists('mb_substr') ? mb_substr((string) $ai['text'], 0, 240, 'UTF-8') : substr((string) $ai['text'], 0, 240),
        ));
        return false;
    }

    $clean = array();
    foreach ($data['faqs'] as $row) {
        if (!is_array($row)) { continue; }
        $q = trim((string) ($row['q'] ?? ''));
        $a = trim((string) ($row['a'] ?? ''));
        if ($q === '' || $a === '') { continue; }
        $clean[] = array('q' => wp_kses_post($q), 'a' => wp_kses_post($a));
    }
    if (!$clean) {
        botwriter_seo_faq_log('parse failed: faqs empty after cleaning', array('post_id' => (int) $post_id));
        return false;
    }
    update_post_meta($post_id, '_botwriter_seo_faq', $clean);
    update_post_meta($post_id, '_botwriter_seo_faq_updated', time());
    if (function_exists('botwriter_seo_compute_score')) {
        botwriter_seo_compute_score($post_id, true);
    }

    botwriter_seo_faq_log('faq saved', array(
        'post_id' => (int) $post_id,
        'faq_count' => count($clean),
        'elapsed_ms_total' => round((microtime(true) - $t) * 1000),
    ));

    return $clean;
}

function botwriter_seo_parse_json_loose($text) {
    $text = trim((string) $text);
    if ($text === '') { return null; }
    if (preg_match('/```(?:json)?\s*(.*?)```/is', $text, $m)) { $text = $m[1]; }
    $first = strpos($text, '{');
    $last = strrpos($text, '}');
    if ($first === false || $last === false) { return null; }
    $candidate = substr($text, $first, $last - $first + 1);
    $decoded = json_decode($candidate, true);
    if (is_array($decoded)) { return $decoded; }
    // Try a basic single-quote replacement.
    $alt = preg_replace('/(?<!\\\\)\'/', '"', $candidate);
    return is_string($alt) ? json_decode($alt, true) : null;
}

/**
 * Append the rendered FAQ block at the bottom of post content on the front-end.
 */
add_filter('the_content', function ($content) {
    if (!is_singular(array('post', 'page'))) { return $content; }
    $post_id = get_the_ID();
    $visible = get_post_meta($post_id, '_botwriter_seo_faq_visible', true);
    if ($visible !== '' && (int) $visible === 0) { return $content; }
    $faqs = get_post_meta($post_id, '_botwriter_seo_faq', true);
    if (!is_array($faqs) || empty($faqs)) { return $content; }

    $html = '<section class="bw-seo-faq" itemscope itemtype="https://schema.org/FAQPage">';
    $html .= '<h2>' . esc_html__('Frequently Asked Questions', 'botwriter') . '</h2>';
    foreach ($faqs as $f) {
        $html .= '<div class="bw-seo-faq-item" itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">';
        $html .= '<h3 itemprop="name">' . wp_kses_post($f['q']) . '</h3>';
        $html .= '<div itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">';
        $html .= '<div itemprop="text">' . wp_kses_post(wpautop($f['a'])) . '</div>';
        $html .= '</div></div>';
    }
    $html .= '</section>';
    return $content . $html;
});

/**
 * Inject FAQPage JSON-LD on singular post views.
 */
add_action('wp_head', function () {
    if (!is_singular(array('post', 'page'))) { return; }
    $faqs = get_post_meta(get_the_ID(), '_botwriter_seo_faq', true);
    if (!is_array($faqs) || empty($faqs)) { return; }
    $payload = array(
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array(),
    );
    foreach ($faqs as $f) {
        $payload['mainEntity'][] = array(
            '@type' => 'Question',
            'name' => wp_strip_all_tags($f['q']),
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text' => wp_strip_all_tags($f['a']),
            ),
        );
    }
    echo "\n<script type=\"application/ld+json\">" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}, 30);
