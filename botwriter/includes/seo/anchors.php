<?php
/**
 * BotWriter SEO module — anchor quality + phrase candidate helpers.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Determine whether a phrase is meaningful enough for an anchor.
 *
 * @param string $phrase Candidate phrase.
 * @return bool
 */
function botwriter_seo_is_meaningful_phrase($phrase) {
    $phrase = wp_specialchars_decode((string) $phrase, ENT_QUOTES);
    $phrase = trim(wp_strip_all_tags($phrase));
    $phrase = preg_replace('/\s+/u', ' ', $phrase);
    if ($phrase === '') {
        return false;
    }

    $tokens = preg_split('/\s+/u', strtolower(remove_accents($phrase)));
    $tokens = is_array($tokens)
        ? array_values(array_filter($tokens, function ($token) {
            return trim((string) $token) !== '';
        }))
        : array();

    if (count($tokens) < 2) {
        return false;
    }

    $stopwords = botwriter_seo_anchor_stopwords();
    $significant = 0;
    $last_clean = '';

    foreach ($tokens as $token) {
        $clean = preg_replace('/[^a-z0-9]/', '', (string) $token);
        if ($clean === '') {
            continue;
        }

        $last_clean = $clean;
        if (!in_array($clean, $stopwords, true) && !is_numeric($clean) && strlen($clean) >= 4) {
            $significant++;
        }
    }

    if ($last_clean !== '' && in_array($last_clean, $stopwords, true)) {
        return false;
    }

    return $significant >= 1;
}

/**
 * Pick a concise, meaningful anchor from AI anchor or fallback title.
 *
 * @param string $anchor_raw Anchor suggested by AI.
 * @param string $fallback_title Related post title.
 * @return string
 */
function botwriter_seo_pick_meaningful_anchor($anchor_raw, $fallback_title = '') {
    $anchor_raw = wp_specialchars_decode((string) $anchor_raw, ENT_QUOTES);
    $anchor_raw = trim(wp_strip_all_tags($anchor_raw));
    $anchor_raw = preg_replace('/\s+/u', ' ', $anchor_raw);

    if ($anchor_raw !== '') {
        $anchor_candidates = botwriter_seo_build_phrase_candidates_from_text($anchor_raw, 6, 2, false);
        foreach ($anchor_candidates as $candidate) {
            if (botwriter_seo_is_meaningful_phrase($candidate)) {
                return $candidate;
            }
        }
    }

    $fallback_title = wp_specialchars_decode((string) $fallback_title, ENT_QUOTES);
    $fallback_title = trim(wp_strip_all_tags($fallback_title));
    $fallback_title = preg_replace('/\s+/u', ' ', $fallback_title);

    if ($fallback_title !== '') {
        $title_candidates = botwriter_seo_build_phrase_candidates_from_text($fallback_title, 6, 2, false);
        foreach ($title_candidates as $candidate) {
            if (botwriter_seo_is_meaningful_phrase($candidate)) {
                return $candidate;
            }
        }

        $title_short = wp_trim_words($fallback_title, 6, '');
        if (botwriter_seo_is_meaningful_phrase($title_short)) {
            return $title_short;
        }
    }

    return __('Related article', 'botwriter');
}

/**
 * Build ordered phrase candidates from source text for natural in-content linking.
 *
 * @param string $text Source text.
 * @param int    $max_words Maximum words per candidate.
 * @param int    $min_words Minimum words per candidate.
 * @param bool   $include_full_phrase Whether to include the full source phrase as first candidate.
 * @return array
 */
function botwriter_seo_build_phrase_candidates_from_text($text, $max_words = 8, $min_words = 2, $include_full_phrase = true) {
    $clean = wp_specialchars_decode((string) $text, ENT_QUOTES);
    $clean = trim(wp_strip_all_tags($clean));
    $clean = preg_replace('/\s+/u', ' ', $clean);
    $clean = preg_replace('/^[\p{P}\p{Z}\s]+|[\p{P}\p{Z}\s]+$/u', '', (string) $clean);

    $max_words = max(2, intval($max_words));
    $min_words = max(1, min(intval($min_words), $max_words));

    if ($clean === '') {
        return array();
    }

    $candidates = array();
    $seen = array();

    $add_candidate = function ($phrase) use (&$candidates, &$seen) {
        $phrase = wp_specialchars_decode((string) $phrase, ENT_QUOTES);
        $phrase = trim((string) $phrase);
        $phrase = preg_replace('/\s+/u', ' ', $phrase);
        $phrase = preg_replace('/^[\p{P}\p{Z}\s]+|[\p{P}\p{Z}\s]+$/u', '', (string) $phrase);
        if ($phrase === '' || isset($seen[$phrase])) {
            return;
        }

        $len = function_exists('mb_strlen')
            ? (int) mb_strlen($phrase, 'UTF-8')
            : strlen($phrase);
        if ($len < 6) {
            return;
        }

        if (!botwriter_seo_is_meaningful_phrase($phrase)) {
            return;
        }

        $seen[$phrase] = true;
        $candidates[] = $phrase;
    };

    if ($include_full_phrase) {
        $add_candidate($clean);
    }

    $tokens = preg_split('/\s+/u', $clean);
    $tokens = is_array($tokens)
        ? array_values(array_filter($tokens, function ($token) {
            return trim((string) $token) !== '';
        }))
        : array();

    $token_count = count($tokens);
    if ($token_count === 0) {
        return $candidates;
    }

    $window_max = min($max_words, $token_count);

    for ($window = $window_max; $window >= $min_words; $window--) {
        for ($start = 0; $start <= ($token_count - $window); $start++) {
            $candidate = implode(' ', array_slice($tokens, $start, $window));
            $add_candidate($candidate);

            if (count($candidates) >= 80) {
                return $candidates;
            }
        }
    }

    return $candidates;
}

/**
 * Tokenize a string into significant lowercase ASCII tokens (length >= 4, not stopwords).
 *
 * @param string $text Source text.
 * @return array
 */
function botwriter_seo_significant_tokens($text) {
    $normalized = strtolower(remove_accents(wp_strip_all_tags((string) $text)));
    $tokens = preg_split('/[^a-z0-9]+/i', $normalized);
    $tokens = is_array($tokens) ? $tokens : array();

    $stopwords = botwriter_seo_anchor_stopwords();
    $out = array();
    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || strlen($token) < 4 || is_numeric($token) || in_array($token, $stopwords, true)) {
            continue;
        }
        $out[$token] = true;
    }

    return array_keys($out);
}
