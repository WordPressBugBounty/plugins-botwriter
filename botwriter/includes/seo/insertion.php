<?php
/**
 * BotWriter SEO module — DOM insertion engine and opportunity-first matcher.
 *
 * Quality rules respected throughout:
 *   - Never insert links inside H1-H6, existing <a>, <script> or <style>.
 *   - Never duplicate URLs already present in content.
 *   - Anchor text must be real text already present in the source content
 *     (no synthetic appends, no anchors stitched out of thin air).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if a URL already exists in anchor links in a DOM root.
 *
 * @param DOMElement $root Root element.
 * @param string     $url URL to check.
 * @return bool
 */
function botwriter_seo_dom_has_url($root, $url) {
    if (!($root instanceof DOMElement)) {
        return false;
    }

    $target = trim((string) $url);
    if ($target === '') {
        return false;
    }

    $target_normalized = rtrim($target, '/');
    $xpath = new DOMXPath($root->ownerDocument);
    $links = $xpath->query('.//a[@href]', $root);
    if (!($links instanceof DOMNodeList)) {
        return false;
    }

    foreach ($links as $link) {
        if (!($link instanceof DOMElement)) {
            continue;
        }

        $href = trim((string) $link->getAttribute('href'));
        if ($href === '') {
            continue;
        }

        if ($href === $target || rtrim($href, '/') === $target_normalized) {
            return true;
        }
    }

    return false;
}

/**
 * Insert one link by matching text in eligible text nodes.
 *
 * @param DOMElement $root Root content element.
 * @param string     $needle Phrase to find.
 * @param string     $url URL to link.
 * @param string     $anchor Anchor text.
 * @param string     $mode wrap|after
 * @param array      $disallowed_tags Lowercase tag names where links are forbidden.
 * @return bool
 */
function botwriter_seo_dom_insert_link_by_needle($root, $needle, $url, $anchor, $mode, $disallowed_tags) {
    if (!($root instanceof DOMElement)) {
        return false;
    }

    $needle = trim((string) $needle);
    $url = trim((string) $url);
    $anchor = trim((string) $anchor);
    $mode = ($mode === 'after') ? 'after' : 'wrap';

    if ($needle === '' || $url === '') {
        return false;
    }

    $xpath = new DOMXPath($root->ownerDocument);
    $nodes = $xpath->query('.//text()', $root);
    if (!($nodes instanceof DOMNodeList)) {
        return false;
    }

    $needle_len = botwriter_seo_mb_strlen($needle);

    foreach ($nodes as $node) {
        if (!($node instanceof DOMText)) {
            continue;
        }

        if (botwriter_seo_node_has_disallowed_ancestor($node, $disallowed_tags)) {
            continue;
        }

        $source = (string) $node->nodeValue;
        if (trim($source) === '') {
            continue;
        }

        $index = botwriter_seo_mb_stripos($source, $needle);
        if ($index === false) {
            continue;
        }

        $matched = botwriter_seo_mb_substr($source, $index, $needle_len);
        $before = botwriter_seo_mb_substr($source, 0, $index);
        $after = botwriter_seo_mb_substr($source, $index + $needle_len);

        $fragment = $root->ownerDocument->createDocumentFragment();
        if ($before !== '') {
            $fragment->appendChild($root->ownerDocument->createTextNode($before));
        }

        if ($mode === 'after') {
            $fragment->appendChild($root->ownerDocument->createTextNode($matched . ' '));
        }

        $link = $root->ownerDocument->createElement('a');
        $link->setAttribute('href', $url);
        $link_text = ($mode === 'after') ? ($anchor !== '' ? $anchor : $matched) : $matched;
        $link->appendChild($root->ownerDocument->createTextNode($link_text));
        $fragment->appendChild($link);

        if ($after !== '') {
            $fragment->appendChild($root->ownerDocument->createTextNode($after));
        }

        $node->parentNode->replaceChild($fragment, $node);

        botwriter_log('SEO DOM insert: success', array(
            'mode' => $mode,
            'needle' => botwriter_seo_debug_preview($needle, 120),
            'url' => $url,
            'anchor' => botwriter_seo_debug_preview($anchor, 80),
        ));

        return true;
    }

    return false;
}

/**
 * Try to wrap any natural candidate phrase from source text.
 *
 * @param DOMElement $root Root content element.
 * @param string     $source_text Source text used to derive candidates.
 * @param string     $url URL to link.
 * @param array      $disallowed_tags Lowercase tag names where links are forbidden.
 * @param int        $max_words Maximum words per candidate phrase.
 * @param bool       $include_full_phrase Whether to include full source phrase as candidate.
 * @return array
 */
function botwriter_seo_dom_try_wrap_from_text($root, $source_text, $url, $disallowed_tags, $max_words = 8, $include_full_phrase = true) {
    $result = array(
        'applied' => false,
        'needle' => '',
        'candidate_count' => 0,
    );

    if (!($root instanceof DOMElement)) {
        return $result;
    }

    $url = trim((string) $url);
    if ($url === '') {
        return $result;
    }

    $candidates = botwriter_seo_build_phrase_candidates_from_text($source_text, $max_words, 2, $include_full_phrase);
    $result['candidate_count'] = count($candidates);

    if (empty($candidates)) {
        return $result;
    }

    foreach ($candidates as $candidate) {
        $applied = botwriter_seo_dom_insert_link_by_needle($root, $candidate, $url, $candidate, 'wrap', $disallowed_tags);
        if ($applied) {
            $result['applied'] = true;
            $result['needle'] = $candidate;
            return $result;
        }
    }

    return $result;
}

/**
 * Build a per-candidate keyword index used by the opportunity-first matcher.
 *
 * Each candidate exposes "focus" tokens (from the title, the strongest signal)
 * and "support" tokens (from tag names). The function also computes per-token
 * document frequency over the focus space so the matcher can require rare,
 * distinctive matches and reject generic shared-vocabulary tokens like
 * "experiencia", "guia", "vida", etc.
 *
 * @param array $candidates Candidate rows from botwriter_editor_get_internal_link_candidates().
 * @return array{
 *     index: array<string, array{focus: array<string,bool>, support: array<string,bool>, title: string, tags: array, score: float}>,
 *     df_focus: array<string, int>,
 *     total: int
 * }
 */
function botwriter_seo_build_candidate_keyword_index($candidates) {
    $index = array();
    $df_focus = array();

    foreach ((array) $candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $url = esc_url_raw((string) ($candidate['url'] ?? ''));
        $title = trim((string) ($candidate['title'] ?? ''));
        if ($url === '' || $title === '') {
            continue;
        }

        $tags = array();
        if (isset($candidate['tags']) && is_array($candidate['tags'])) {
            foreach ($candidate['tags'] as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        $focus_tokens = botwriter_seo_significant_tokens($title);
        if (empty($focus_tokens)) {
            continue;
        }

        $support_tokens = botwriter_seo_significant_tokens(implode(' ', $tags));

        $index[$url] = array(
            'focus' => array_fill_keys($focus_tokens, true),
            'support' => array_fill_keys($support_tokens, true),
            'title' => $title,
            'tags' => $tags,
            'score' => floatval($candidate['score'] ?? 0),
        );

        foreach ($focus_tokens as $tok) {
            $df_focus[$tok] = ($df_focus[$tok] ?? 0) + 1;
        }
    }

    return array(
        'index' => $index,
        'df_focus' => $df_focus,
        'total' => count($index),
    );
}

/**
 * Opportunity-first link insertion engine.
 *
 * Walks the source content, finds n-gram phrases (2-5 words) that contain at
 * least one significant token belonging to a candidate URL, and wraps the
 * source phrase as the anchor. This mirrors how human editors and tools like
 * Internal Link Juicer produce natural in-content links: it always uses real
 * source text, never invents anchors, and never appends links.
 *
 * @param DOMElement $root Root content element.
 * @param array      $candidates Candidate rows.
 * @param array      $disallowed_tags Lowercase tag names where links are forbidden.
 * @param int        $max_links Hard cap on inserts.
 * @param array      $exclude_urls URLs already linked or used in this run.
 * @return array{inserted:int, used_urls:array<int,string>, opportunities:int}
 */
function botwriter_seo_apply_opportunity_first($root, $candidates, $disallowed_tags, $max_links, $exclude_urls = array()) {
    $stats = array(
        'inserted' => 0,
        'used_urls' => array(),
        'opportunities' => 0,
    );

    if (!($root instanceof DOMElement)) {
        return $stats;
    }

    $max_links = max(1, intval($max_links));

    $built = botwriter_seo_build_candidate_keyword_index($candidates);
    $index = $built['index'];
    $df_focus = $built['df_focus'];
    $total_candidates = intval($built['total']);

    if (empty($index)) {
        botwriter_log('SEO opportunity-first: no candidate keyword index', array(
            'candidate_count' => count((array) $candidates),
        ));
        return $stats;
    }

    // Quality thresholds: a token is "rare" (distinctive) if it appears in
    // at most 25% of candidate titles. A single-token match is only allowed
    // when that token is rare; otherwise we require at least 2 overlapping
    // focus tokens between source phrase and candidate title.
    $rare_threshold = max(1, (int) ceil($total_candidates * 0.25));

    $used = array();
    foreach ((array) $exclude_urls as $url) {
        $url = (string) $url;
        if ($url !== '') {
            $used[$url] = true;
        }
    }

    // Drop URLs already present in the DOM.
    foreach (array_keys($index) as $url) {
        if (botwriter_seo_dom_has_url($root, $url)) {
            $used[$url] = true;
        }
    }

    $xpath = new DOMXPath($root->ownerDocument);
    $text_nodes = $xpath->query('.//text()', $root);
    if (!($text_nodes instanceof DOMNodeList)) {
        return $stats;
    }

    $stopwords = array_fill_keys(botwriter_seo_anchor_stopwords(), true);

    // Snapshot to a flat array so DOM mutations during loop don't break iteration.
    $snapshot = array();
    foreach ($text_nodes as $node) {
        if ($node instanceof DOMText) {
            $snapshot[] = $node;
        }
    }

    foreach ($snapshot as $node) {
        if ($stats['inserted'] >= $max_links) {
            break;
        }

        if (!$node->parentNode) {
            continue; // already removed by a prior insert.
        }

        if (botwriter_seo_node_has_disallowed_ancestor($node, $disallowed_tags)) {
            continue;
        }

        $source = (string) $node->nodeValue;
        if (trim($source) === '') {
            continue;
        }

        $tokens_raw = preg_split('/\s+/u', $source);
        $tokens_raw = is_array($tokens_raw) ? array_values(array_filter($tokens_raw, function ($t) {
            return trim((string) $t) !== '';
        })) : array();

        $token_count = count($tokens_raw);
        if ($token_count < 2) {
            continue;
        }

        // Pre-normalize tokens once (lowercase ASCII, no punctuation).
        $tokens_norm = array();
        foreach ($tokens_raw as $tok) {
            $norm = strtolower(remove_accents((string) $tok));
            $norm = preg_replace('/[^a-z0-9]/', '', $norm);
            $tokens_norm[] = (string) $norm;
        }

        $best = null; // ['url'=>..., 'phrase'=>..., 'score'=>...]

        $window_max = min(5, $token_count);
        for ($window = $window_max; $window >= 2; $window--) {
            for ($start = 0; $start + $window <= $token_count; $start++) {
                $phrase_tokens_norm = array_slice($tokens_norm, $start, $window);
                $phrase_tokens_raw = array_slice($tokens_raw, $start, $window);

                $first = $phrase_tokens_norm[0];
                $last = $phrase_tokens_norm[$window - 1];
                if ($first === '' || $last === '') {
                    continue;
                }
                if (isset($stopwords[$first]) || isset($stopwords[$last])) {
                    continue;
                }

                $significant = 0;
                foreach ($phrase_tokens_norm as $pt) {
                    if ($pt === '' || strlen($pt) < 4 || isset($stopwords[$pt]) || is_numeric($pt)) {
                        continue;
                    }
                    $significant++;
                }
                if ($significant < 1) {
                    continue;
                }

                // Score each candidate against the current source phrase.
                // Only TITLE tokens (focus) count as anchor-quality matches.
                // Tag tokens are not used for matching: they are too generic
                // and produced false positives like wrapping "tarantula"
                // anchors towards an ice-cream-on-the-beach post that just
                // happened to share a generic tag.
                foreach ($index as $url => $entry) {
                    if (isset($used[$url])) {
                        continue;
                    }

                    $cand_focus = $entry['focus'];
                    $focus_overlap = 0;
                    $rare_focus_overlap = 0;
                    $best_token_df = PHP_INT_MAX;

                    foreach ($phrase_tokens_norm as $pt) {
                        if ($pt === '' || !isset($cand_focus[$pt])) {
                            continue;
                        }
                        $focus_overlap++;
                        $df = isset($df_focus[$pt]) ? intval($df_focus[$pt]) : PHP_INT_MAX;
                        if ($df <= $rare_threshold) {
                            $rare_focus_overlap++;
                        }
                        if ($df < $best_token_df) {
                            $best_token_df = $df;
                        }
                    }

                    if ($focus_overlap <= 0) {
                        continue;
                    }

                    // Quality gate: require either >=2 focus tokens shared,
                    // or 1 token that is genuinely distinctive (rare across
                    // the candidate pool). This is what stopped weak single-
                    // generic-token matches from being inserted.
                    if ($focus_overlap < 2 && $rare_focus_overlap < 1) {
                        continue;
                    }

                    $stats['opportunities']++;

                    // Effective ranking: focus overlap dominates, then window
                    // length, then candidate base score, then token rarity.
                    $rarity_bonus = ($best_token_df > 0)
                        ? (1.0 / $best_token_df)
                        : 0.0;
                    $effective = ($focus_overlap * 100)
                        + ($rare_focus_overlap * 25)
                        + $window
                        + ($entry['score'] * 0.1)
                        + $rarity_bonus;

                    if ($best === null || $effective > $best['effective']) {
                        $phrase_raw = implode(' ', $phrase_tokens_raw);
                        $phrase_raw = preg_replace('/^[\p{P}\p{Z}\s]+|[\p{P}\p{Z}\s]+$/u', '', (string) $phrase_raw);
                        if ($phrase_raw === '') {
                            continue;
                        }

                        $best = array(
                            'url' => $url,
                            'phrase' => $phrase_raw,
                            'effective' => $effective,
                            'focus_overlap' => $focus_overlap,
                            'rare_focus_overlap' => $rare_focus_overlap,
                            'best_token_df' => $best_token_df,
                            'window' => $window,
                            'title' => $entry['title'],
                        );
                    }
                }
            }
        }

        if ($best === null) {
            continue;
        }

        $ok = botwriter_seo_dom_insert_link_by_needle(
            $root,
            $best['phrase'],
            $best['url'],
            $best['phrase'],
            'wrap',
            $disallowed_tags
        );

        if ($ok) {
            $used[$best['url']] = true;
            $stats['inserted']++;
            $stats['used_urls'][] = $best['url'];

            botwriter_log('SEO opportunity-first: insert', array(
                'url' => $best['url'],
                'anchor' => $best['phrase'],
                'focus_overlap' => $best['focus_overlap'],
                'rare_focus_overlap' => $best['rare_focus_overlap'],
                'best_token_df' => $best['best_token_df'],
                'window' => $best['window'],
                'inserted_total' => $stats['inserted'],
            ));
        }
    }

    botwriter_log('SEO opportunity-first: end', array(
        'inserted' => $stats['inserted'],
        'opportunities' => $stats['opportunities'],
        'index_size' => count($index),
        'rare_threshold' => $rare_threshold,
        'max_links' => $max_links,
    ));

    return $stats;
}

/**
 * Apply internal-link suggestions directly to HTML content.
 *
 * Strategy:
 *   1. Wrap-only matching against AI/no-AI suggestions (anchor / insert_after / title phrases).
 *   2. If any slot is left, run the opportunity-first engine over the remaining candidates.
 *
 * No links are ever appended outside the natural body text.
 *
 * @param string $content HTML content.
 * @param array  $suggestions Suggestion rows (url/title/anchor/insert_after).
 * @param int    $max_links Maximum links to insert.
 * @param array  $candidates Optional full candidate list used for opportunity-first retry.
 * @return array
 */
function botwriter_seo_apply_internal_links_to_content($content, $suggestions, $max_links = 3, $candidates = array()) {
    $result = array(
        'content' => (string) $content,
        'inserted' => 0,
        'used_urls' => array(),
        'opportunity_inserts' => 0,
    );

    $content = (string) $content;
    $suggestions = (array) $suggestions;
    $dom_available = class_exists('DOMDocument');

    botwriter_log('SEO apply links: start', array(
        'content_len' => strlen($content),
        'suggestion_count' => count($suggestions),
        'candidate_count' => count((array) $candidates),
        'max_links_raw' => $max_links,
        'dom_available' => $dom_available ? 1 : 0,
    ));

    if ($content === '' || (!$dom_available)) {
        return $result;
    }

    if (empty($suggestions) && empty($candidates)) {
        return $result;
    }

    $max_links = max(1, min(8, intval($max_links)));

    $dom = new DOMDocument('1.0', 'UTF-8');
    $html = '<!DOCTYPE html><html><body><div id="botwriter-seo-root">' . $content . '</div></body></html>';

    $previous_errors = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);

    if (!$loaded) {
        botwriter_log('SEO apply links: DOM load failed', array());
        return $result;
    }

    $xpath = new DOMXPath($dom);
    $root_nodes = $xpath->query('//*[@id="botwriter-seo-root"]');
    if (!($root_nodes instanceof DOMNodeList) || $root_nodes->length === 0) {
        return $result;
    }

    $root = $root_nodes->item(0);
    if (!($root instanceof DOMElement)) {
        return $result;
    }

    $disallowed_tags = array('a', 'script', 'style', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6');
    $used_urls = array();
    $inserted = 0;

    foreach ($suggestions as $index_key => $suggestion) {
        if ($inserted >= $max_links) {
            break;
        }

        if (!is_array($suggestion)) {
            continue;
        }

        $url = esc_url_raw((string) ($suggestion['url'] ?? ''));
        $title = trim(sanitize_text_field((string) ($suggestion['title'] ?? '')));
        $anchor = trim(sanitize_text_field((string) ($suggestion['anchor'] ?? $title)));
        $insert_after = trim(sanitize_text_field((string) ($suggestion['insert_after'] ?? '')));

        if ($url === '' || isset($used_urls[$url])) {
            continue;
        }

        if (botwriter_seo_dom_has_url($root, $url)) {
            continue;
        }

        $applied = false;
        $applied_by = '';
        $applied_needle = '';

        if ($anchor !== '') {
            $applied = botwriter_seo_dom_insert_link_by_needle($root, $anchor, $url, $anchor, 'wrap', $disallowed_tags);
            if ($applied) {
                $applied_by = 'anchor_wrap';
                $applied_needle = $anchor;
            }
        }

        if (!$applied && $insert_after !== '') {
            $try = botwriter_seo_dom_try_wrap_from_text($root, $insert_after, $url, $disallowed_tags, 6, false);
            if (!empty($try['applied'])) {
                $applied = true;
                $applied_by = 'insert_after_phrase_wrap';
                $applied_needle = (string) ($try['needle'] ?? '');
            }
        }

        if (!$applied && $anchor !== '') {
            $try = botwriter_seo_dom_try_wrap_from_text($root, $anchor, $url, $disallowed_tags, 8);
            if (!empty($try['applied'])) {
                $applied = true;
                $applied_by = 'anchor_phrase_wrap';
                $applied_needle = (string) ($try['needle'] ?? '');
            }
        }

        if (!$applied && $title !== '') {
            $try = botwriter_seo_dom_try_wrap_from_text($root, $title, $url, $disallowed_tags, 6, false);
            if (!empty($try['applied'])) {
                $applied = true;
                $applied_by = 'title_phrase_wrap';
                $applied_needle = (string) ($try['needle'] ?? '');
            }
        }

        if ($applied) {
            $inserted++;
            $used_urls[$url] = true;

            botwriter_log('SEO apply links: applied', array(
                'index' => intval($index_key),
                'url' => $url,
                'applied_by' => $applied_by,
                'applied_needle' => botwriter_seo_debug_preview($applied_needle, 140),
                'inserted_total' => $inserted,
            ));
        }
    }

    // Opportunity-first retry: fill remaining slots from candidates pool.
    $opportunity_inserts = 0;
    if ($inserted < $max_links && !empty($candidates)) {
        $remaining = $max_links - $inserted;
        $opp = botwriter_seo_apply_opportunity_first(
            $root,
            $candidates,
            $disallowed_tags,
            $remaining,
            array_keys($used_urls)
        );
        $opportunity_inserts = intval($opp['inserted'] ?? 0);
        $inserted += $opportunity_inserts;
        foreach ((array) ($opp['used_urls'] ?? array()) as $u) {
            $used_urls[(string) $u] = true;
        }
    }

    $result['content'] = botwriter_seo_dom_inner_html($root);
    $result['inserted'] = $inserted;
    $result['used_urls'] = array_keys($used_urls);
    $result['opportunity_inserts'] = $opportunity_inserts;

    botwriter_log('SEO apply links: end', array(
        'max_links' => $max_links,
        'inserted' => $inserted,
        'opportunity_inserts' => $opportunity_inserts,
        'used_urls' => $result['used_urls'],
        'content_len_after' => strlen((string) $result['content']),
    ));

    return $result;
}
