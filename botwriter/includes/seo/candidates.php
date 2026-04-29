<?php
/**
 * BotWriter SEO module — keyphrase parsing, keyword extraction, and
 * internal-link candidate discovery (WP_Query + scoring).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parse comma-separated keyphrases (up to 5).
 *
 * @param string $raw Raw input from UI.
 * @return array
 */
function botwriter_editor_parse_keyphrases($raw) {
    $parts = preg_split('/[,\r\n]+/', (string) $raw);
    $parts = is_array($parts) ? $parts : array();

    $result = array();
    foreach ($parts as $part) {
        $phrase = trim(sanitize_text_field((string) $part));
        if ($phrase === '') {
            continue;
        }
        $result[] = $phrase;
    }

    $result = array_values(array_unique($result));
    return array_slice($result, 0, 5);
}

/**
 * Extract top keywords from a text block for basic relevance scoring.
 *
 * @param string $text Text source.
 * @param int    $limit Max keywords.
 * @return array
 */
function botwriter_editor_extract_keywords($text, $limit = 20) {
    $normalized = strtolower(remove_accents(wp_strip_all_tags((string) $text)));
    $tokens = preg_split('/[^a-z0-9]+/i', $normalized);
    $tokens = is_array($tokens) ? $tokens : array();

    $stopwords = array(
        'this', 'that', 'with', 'from', 'your', 'have', 'will', 'about', 'into', 'their', 'them',
        'como', 'para', 'porque', 'sobre', 'entre', 'donde', 'desde', 'hasta', 'estas', 'estos',
        'aqui', 'esta', 'este', 'very', 'more', 'much', 'cada', 'cuando', 'which', 'what', 'where',
        'and', 'the', 'for', 'you', 'are', 'una', 'uno', 'unos', 'unas', 'pero', 'que', 'por', 'con',
    );

    $freq = array();
    foreach ($tokens as $token) {
        $word = trim($token);
        if ($word === '' || strlen($word) < 4 || is_numeric($word) || in_array($word, $stopwords, true)) {
            continue;
        }
        $freq[$word] = isset($freq[$word]) ? $freq[$word] + 1 : 1;
    }

    arsort($freq);
    return array_slice(array_keys($freq), 0, max(1, intval($limit)));
}

/**
 * Discover relevant internal post URLs for the current post context.
 *
 * @param int   $post_id Current post ID.
 * @param array $context Post context payload from widget.
 * @param int   $limit Max candidates to return.
 * @return array
 */
function botwriter_editor_get_internal_link_candidates($post_id, $context, $limit = 24) {
    $post_id = intval($post_id);
    $limit = max(5, intval($limit));

    $title = (string) ($context['title'] ?? '');
    $excerpt = (string) ($context['excerpt'] ?? '');
    $content_plain = wp_strip_all_tags((string) ($context['content'] ?? ''));
    $content_short = wp_trim_words($content_plain, 180, '');
    $seed_text = trim($title . ' ' . $excerpt . ' ' . $content_short);

    $keywords = botwriter_editor_extract_keywords($seed_text, 24);
    $context_tag_names = array_map('trim', explode(',', (string) ($context['tags'] ?? '')));
    $context_tag_names = array_values(array_filter(array_map('strtolower', $context_tag_names)));

    botwriter_log('SEO internal candidates: start', array(
        'post_id' => $post_id,
        'limit' => $limit,
        'title_len' => strlen($title),
        'excerpt_len' => strlen($excerpt),
        'content_plain_len' => strlen($content_plain),
        'seed_text_len' => strlen($seed_text),
        'keyword_count' => count($keywords),
        'keywords_sample' => array_slice($keywords, 0, 10),
        'context_tag_names_count' => count($context_tag_names),
    ));

    $current_cat_ids = array();
    $current_tag_ids = array();
    if ($post_id > 0) {
        $cat_terms = get_the_terms($post_id, 'category');
        if (is_array($cat_terms)) {
            $current_cat_ids = array_map('intval', wp_list_pluck($cat_terms, 'term_id'));
        }

        $tag_terms = get_the_terms($post_id, 'post_tag');
        if (is_array($tag_terms)) {
            $current_tag_ids = array_map('intval', wp_list_pluck($tag_terms, 'term_id'));
        }
    }

    $exclude = array();
    if ($post_id > 0) {
        $exclude[] = $post_id;
    }

    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 160,
        'post__not_in' => $exclude,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ));

    $candidates = array();

    if ($query->have_posts()) {
        foreach ($query->posts as $candidate_post) {
            $candidate_id = intval($candidate_post->ID);
            if ($candidate_id <= 0) {
                continue;
            }

            $url = get_permalink($candidate_id);
            if (!$url) {
                continue;
            }

            $candidate_title = trim((string) get_the_title($candidate_id));
            if ($candidate_title === '') {
                continue;
            }

            $candidate_excerpt = trim((string) wp_strip_all_tags(get_the_excerpt($candidate_id)));
            if ($candidate_excerpt === '') {
                $candidate_excerpt = trim((string) wp_strip_all_tags(wp_trim_words((string) $candidate_post->post_content, 40, '...')));
            }

            $candidate_text = strtolower(remove_accents($candidate_title . ' ' . $candidate_excerpt));

            $score = 0.0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && strpos($candidate_text, $keyword) !== false) {
                    $score += 1.35;
                }
            }

            $candidate_tag_names_list = array();

            $candidate_cat_terms = get_the_terms($candidate_id, 'category');
            if (is_array($candidate_cat_terms) && !empty($current_cat_ids)) {
                $candidate_cat_ids = array_map('intval', wp_list_pluck($candidate_cat_terms, 'term_id'));
                $shared_cats = array_intersect($current_cat_ids, $candidate_cat_ids);
                $score += count($shared_cats) * 2.7;
            }

            $candidate_tag_terms = get_the_terms($candidate_id, 'post_tag');
            if (is_array($candidate_tag_terms)) {
                $candidate_tag_names_list = array_values(array_map('strval', wp_list_pluck($candidate_tag_terms, 'name')));

                if (!empty($current_tag_ids)) {
                    $candidate_tag_ids = array_map('intval', wp_list_pluck($candidate_tag_terms, 'term_id'));
                    $shared_tags = array_intersect($current_tag_ids, $candidate_tag_ids);
                    $score += count($shared_tags) * 2.2;
                }

                if (!empty($context_tag_names)) {
                    $candidate_tag_names_lc = array_map('strtolower', $candidate_tag_names_list);
                    $shared_tag_names = array_intersect($context_tag_names, $candidate_tag_names_lc);
                    $score += count($shared_tag_names) * 1.3;
                }
            }

            $days_old = max(0, floor((time() - strtotime((string) $candidate_post->post_date_gmt)) / DAY_IN_SECONDS));
            $score += max(0, 2 - ($days_old / 365));

            if ($score <= 0) {
                continue;
            }

            $candidates[] = array(
                'post_id' => $candidate_id,
                'url' => esc_url_raw($url),
                'title' => $candidate_title,
                'excerpt' => wp_trim_words($candidate_excerpt, 26, '...'),
                'tags' => $candidate_tag_names_list,
                'score' => round($score, 3),
            );
        }
    }

    wp_reset_postdata();

    usort($candidates, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return 0;
        }
        return ($a['score'] > $b['score']) ? -1 : 1;
    });

    $final = array_slice($candidates, 0, $limit);

    botwriter_log('SEO internal candidates: result', array(
        'post_id' => $post_id,
        'limit' => $limit,
        'result_count' => count($final),
    ));

    return $final;
}
