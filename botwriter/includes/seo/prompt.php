<?php
/**
 * BotWriter SEO module — AI prompt construction, response parsing, and
 * deterministic (no-AI) suggestion builder.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build prompt for generating internal-link suggestions in JSON format.
 *
 * @param string $user_prompt Freeform user prompt.
 * @param array  $context Post context.
 * @param array  $keyphrases Optional keyphrases.
 * @param array  $candidates Candidate links.
 * @return string
 */
function botwriter_build_editor_internal_links_prompt($user_prompt, $context, $keyphrases, $candidates) {
    $candidate_payload = array();
    foreach ($candidates as $candidate) {
        $candidate_payload[] = array(
            'url' => (string) ($candidate['url'] ?? ''),
            'title' => (string) ($candidate['title'] ?? ''),
            'excerpt' => (string) ($candidate['excerpt'] ?? ''),
        );
    }

    $context_payload = array(
        'title' => (string) ($context['title'] ?? ''),
        'content_excerpt' => wp_trim_words(wp_strip_all_tags((string) ($context['content'] ?? '')), 220, ''),
        'tags' => (string) ($context['tags'] ?? ''),
        'excerpt' => (string) ($context['excerpt'] ?? ''),
        'keyphrases' => array_values($keyphrases),
    );

    $context_json = wp_json_encode($context_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($context_json) || $context_json === '') {
        $context_json = '{}';
    }

    $candidates_json = wp_json_encode($candidate_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($candidates_json) || $candidates_json === '') {
        $candidates_json = '[]';
    }

    $prompt = "You are an internal linking assistant for WordPress SEO.\n"
        . "Suggest the best internal links for this article.\n"
        . "Use ONLY URLs from the candidates list.\n"
        . "Return STRICT JSON only (no markdown):\n"
        . "{\n  \"suggestions\": [\n    {\n      \"url\": \"https://example.com/post\",\n      \"title\": \"Post title\",\n      \"anchor\": \"short anchor text\",\n      \"reason\": \"why this link helps\",\n      \"insert_after\": \"short phrase from article where link fits\"\n    }\n  ]\n}\n"
        . "Rules:\n"
        . "- Max 8 suggestions.\n"
        . "- Anchor must be natural, concise (2-7 words), and not generic.\n"
        . "- The 'insert_after' value MUST be a literal phrase copied from the article (3-7 words). The phrase will be wrapped as the anchor.\n"
        . "- Prefer topic diversity and relevance to keyphrases.\n"
        . "- If user prompt asks a style (educational/conversion/etc), follow it.\n\n"
        . "User request:\n{$user_prompt}\n\n"
        . "Current article context JSON:\n{$context_json}\n\n"
        . "Internal link candidates JSON:\n{$candidates_json}";

    botwriter_log('SEO internal links prompt: built', array(
        'user_prompt_len' => strlen((string) $user_prompt),
        'candidate_count' => count($candidate_payload),
        'prompt_len' => strlen($prompt),
    ));

    return $prompt;
}

/**
 * Parse internal-link suggestion JSON from AI output and keep only safe/allowed URLs.
 *
 * @param string $raw Raw AI output.
 * @param array  $candidates Candidate URLs map.
 * @return array
 */
function botwriter_parse_editor_internal_links_response($raw, $candidates) {
    $allowed = array();
    foreach ($candidates as $candidate) {
        $url = esc_url_raw((string) ($candidate['url'] ?? ''));
        if ($url !== '') {
            $allowed[$url] = $candidate;
        }
    }

    $raw = (string) $raw;
    botwriter_log('SEO internal links parse: start', array(
        'raw_len' => strlen($raw),
        'candidate_count' => count((array) $candidates),
        'allowed_urls_count' => count($allowed),
    ));

    $text = trim($raw);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = trim((string) $text);

    $text_for_json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text_for_json = is_string($text_for_json) ? $text_for_json : $text;

    $decoded = json_decode($text_for_json, true);
    if (!is_array($decoded)) {
        $start = strpos($text_for_json, '{');
        $end = strrpos($text_for_json, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $snippet = substr($text_for_json, $start, $end - $start + 1);
            $decoded = json_decode($snippet, true);
        }
    }

    $rows = array();
    if (is_array($decoded)) {
        if (isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
            $rows = $decoded['suggestions'];
        } elseif (array_values($decoded) === $decoded) {
            $rows = $decoded;
        }
    }

    $suggestions = array();
    $seen = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $url = esc_url_raw((string) ($row['url'] ?? ''));
        if ($url === '' || !isset($allowed[$url])) {
            continue;
        }

        $title = trim(sanitize_text_field((string) ($row['title'] ?? '')));
        if ($title === '') {
            $title = (string) ($allowed[$url]['title'] ?? 'Related article');
        }

        $anchor_raw = trim(sanitize_text_field((string) ($row['anchor'] ?? '')));
        $anchor = botwriter_seo_pick_meaningful_anchor($anchor_raw, $title);

        $reason = trim(sanitize_text_field((string) ($row['reason'] ?? '')));
        $insert_after = trim(sanitize_text_field((string) ($row['insert_after'] ?? ($row['insert_after_phrase'] ?? ''))));

        $fingerprint = md5($url . '|' . strtolower($anchor));
        if (isset($seen[$fingerprint])) {
            continue;
        }
        $seen[$fingerprint] = true;

        $suggestions[] = array(
            'url' => $url,
            'title' => $title,
            'anchor' => $anchor,
            'reason' => $reason,
            'insert_after' => $insert_after,
        );

        if (count($suggestions) >= 8) {
            break;
        }
    }

    if (!empty($suggestions)) {
        botwriter_log('SEO internal links parse: success', array(
            'suggestion_count' => count($suggestions),
        ));
        return $suggestions;
    }

    foreach ($candidates as $candidate) {
        $url = esc_url_raw((string) ($candidate['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        $title = trim(sanitize_text_field((string) ($candidate['title'] ?? '')));
        if ($title === '') {
            continue;
        }

        $anchor = botwriter_seo_pick_meaningful_anchor('', $title);

        $suggestions[] = array(
            'url' => $url,
            'title' => $title,
            'anchor' => $anchor,
            'reason' => __('Relevant related article for this topic.', 'botwriter'),
            'insert_after' => '',
        );

        if (count($suggestions) >= 6) {
            break;
        }
    }

    botwriter_log('SEO internal links parse: fallback', array(
        'fallback_suggestion_count' => count($suggestions),
    ));

    return $suggestions;
}

/**
 * Build deterministic internal-link suggestions without AI.
 *
 * @param array $candidates Candidate links from site content.
 * @param array $context Current editor context.
 * @param array $keyphrases Optional keyphrases from user input.
 * @param int   $limit Max suggestions.
 * @return array
 */
function botwriter_editor_build_internal_links_noai_suggestions($candidates, $context, $keyphrases, $limit = 8) {
    $limit = max(1, intval($limit));

    $content_plain = strtolower(remove_accents(wp_strip_all_tags((string) ($context['content'] ?? ''))));
    $normalized_keyphrases = array();
    foreach ((array) $keyphrases as $keyphrase) {
        $clean = trim((string) $keyphrase);
        if ($clean === '') {
            continue;
        }
        $normalized_keyphrases[] = array(
            'raw' => $clean,
            'norm' => strtolower(remove_accents($clean)),
        );
    }

    $rows = array();

    foreach ((array) $candidates as $candidate) {
        $url = esc_url_raw((string) ($candidate['url'] ?? ''));
        $title = trim(sanitize_text_field((string) ($candidate['title'] ?? '')));
        if ($url === '' || $title === '') {
            continue;
        }

        $excerpt = trim(sanitize_text_field((string) ($candidate['excerpt'] ?? '')));
        $candidate_text = strtolower(remove_accents($title . ' ' . $excerpt));

        $score = floatval($candidate['score'] ?? 0);
        $matched_keyphrases = 0;
        $insert_after = '';

        foreach ($normalized_keyphrases as $keyphrase_data) {
            $needle = (string) ($keyphrase_data['norm'] ?? '');
            if ($needle === '') {
                continue;
            }

            if (strpos($candidate_text, $needle) !== false) {
                $score += 2.0;
                $matched_keyphrases++;
            }

            if ($insert_after === '' && strpos($content_plain, $needle) !== false) {
                $insert_after = (string) ($keyphrase_data['raw'] ?? '');
            }
        }

        if ($score <= 0) {
            continue;
        }

        $reason = $matched_keyphrases > 0
            ? sprintf(
                /* translators: %d: number of matched keyphrases */
                __('Matched %d target keyphrases and taxonomy relevance.', 'botwriter'),
                $matched_keyphrases
            )
            : __('Ranked by shared categories/tags and keyword overlap.', 'botwriter');

        $rows[] = array(
            'url' => $url,
            'title' => $title,
            'anchor' => botwriter_seo_pick_meaningful_anchor('', $title),
            'reason' => $reason,
            'insert_after' => $insert_after,
            '_score' => $score,
        );
    }

    usort($rows, function ($a, $b) {
        $a_score = floatval($a['_score'] ?? 0);
        $b_score = floatval($b['_score'] ?? 0);
        if ($a_score === $b_score) {
            return 0;
        }
        return ($a_score > $b_score) ? -1 : 1;
    });

    $final = array();
    $seen_urls = array();
    foreach ($rows as $row) {
        $url = (string) ($row['url'] ?? '');
        if ($url === '' || isset($seen_urls[$url])) {
            continue;
        }
        $seen_urls[$url] = true;
        unset($row['_score']);
        $final[] = $row;

        if (count($final) >= $limit) {
            break;
        }
    }

    botwriter_log('SEO no-AI suggestions: result', array(
        'limit' => $limit,
        'final_count' => count($final),
    ));

    return $final;
}
