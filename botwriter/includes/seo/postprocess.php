<?php
/**
 * BotWriter SEO module — publish-time post-process orchestrator.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Automatic internal-link SEO post-processing for BotWriter-generated posts.
 *
 * Pipeline:
 *   1. Build internal-link candidates from the rest of the site.
 *   2. If AI is enabled and a key is configured, ask the model for suggestions.
 *   3. Always fall back to deterministic suggestions (no-AI builder).
 *   4. Run the wrap-only insertion engine, with the opportunity-first matcher
 *      filling any remaining slots so insertion never silently produces zero
 *      links when there are real topic matches in the content.
 *
 * @param int   $post_id Post ID.
 * @param array $context Optional context (title/content/tags/excerpt).
 * @return array
 */
function botwriter_seo_auto_internal_links_postprocess($post_id, $context = array()) {
    $result = array(
        'updated' => false,
        'inserted' => 0,
        'strategy' => 'disabled',
        'candidate_count' => 0,
        'suggestion_count' => 0,
        'opportunity_inserts' => 0,
    );

    $post_id = intval($post_id);
    $auto_enabled = get_option('botwriter_seo_auto_internal_links_enabled', '0');

    botwriter_log('SEO auto postprocess: start', array(
        'post_id' => $post_id,
        'auto_enabled_option' => (string) $auto_enabled,
    ));

    if ($post_id <= 0 || $auto_enabled !== '1') {
        return $result;
    }

    $max_links = max(1, min(8, intval(get_option('botwriter_seo_auto_internal_links_max_links', '3'))));

    $content = isset($context['content']) ? (string) $context['content'] : '';
    if ($content === '') {
        $content = (string) get_post_field('post_content', $post_id);
    }
    if ($content === '') {
        $result['strategy'] = 'empty_content';
        return $result;
    }

    $title = isset($context['title']) ? (string) $context['title'] : '';
    if ($title === '') {
        $title = (string) get_the_title($post_id);
    }

    $excerpt = isset($context['excerpt']) ? (string) $context['excerpt'] : '';
    if ($excerpt === '') {
        $excerpt = (string) get_post_field('post_excerpt', $post_id);
    }

    $tags = isset($context['tags']) ? (string) $context['tags'] : '';
    if ($tags === '') {
        $tag_names = wp_get_post_tags($post_id, array('fields' => 'names'));
        if (is_array($tag_names) && !empty($tag_names)) {
            $tags = implode(', ', $tag_names);
        }
    }

    $seo_context = array(
        'title' => $title,
        'content' => $content,
        'tags' => $tags,
        'excerpt' => $excerpt,
        'seo_meta' => '',
    );

    $candidates = botwriter_editor_get_internal_link_candidates($post_id, $seo_context, max(26, $max_links * 8));
    $result['candidate_count'] = count($candidates);

    if (empty($candidates)) {
        $result['strategy'] = 'no_candidates';
        botwriter_log('SEO auto postprocess: no candidates', array('post_id' => $post_id));
        return $result;
    }

    $suggestions = botwriter_editor_build_internal_links_noai_suggestions($candidates, $seo_context, array(), max($max_links * 2, 8));
    $result['suggestion_count'] = count($suggestions);

    // First pass: deterministic suggestions + opportunity-first matcher.
    $apply = botwriter_seo_apply_internal_links_to_content($content, $suggestions, $max_links, $candidates);
    $inserted = intval($apply['inserted'] ?? 0);
    $opportunity_inserts = intval($apply['opportunity_inserts'] ?? 0);
    $strategy = !empty($suggestions) ? 'no_ai' : 'opportunity';
    $ai_attempted = false;

    // Second pass (fallback): if deterministic pass inserted nothing,
    // try AI suggestions when a provider key is available.
    if ($inserted <= 0 && function_exists('botwriter_call_editor_worker')) {
        $provider = sanitize_key((string) get_option('botwriter_text_provider', 'openai'));
        $model = function_exists('botwriter_get_current_text_model')
            ? (string) botwriter_get_current_text_model()
            : (string) get_option('botwriter_openai_model', 'gpt-4o-mini');
        $api_key = function_exists('botwriter_get_provider_api_key')
            ? (string) botwriter_get_provider_api_key($provider)
            : '';

        if ($api_key !== '') {
            $ai_attempted = true;
            $auto_prompt = sprintf(
                /* translators: %d: maximum links to insert */
                __('Automatically insert up to %d contextual internal links in this article. Avoid heading tags and existing links.', 'botwriter'),
                $max_links
            );

            $links_prompt = botwriter_build_editor_internal_links_prompt($auto_prompt, $seo_context, array(), $candidates);
            $generated_links = botwriter_call_editor_worker($provider, $api_key, $model, $links_prompt, 2200, 0.2);

            if (!is_wp_error($generated_links)) {
                $ai_suggestions = botwriter_parse_editor_internal_links_response((string) $generated_links, $candidates);
                if (!empty($ai_suggestions)) {
                    $result['suggestion_count'] = count($ai_suggestions);
                    $apply = botwriter_seo_apply_internal_links_to_content($content, $ai_suggestions, $max_links, $candidates);
                    $inserted = intval($apply['inserted'] ?? 0);
                    $opportunity_inserts = intval($apply['opportunity_inserts'] ?? 0);
                    $strategy = 'ai_fallback';
                }
            } else {
                botwriter_log('SEO auto postprocess: AI fallback call failed', array(
                    'post_id' => $post_id,
                    'error' => $generated_links->get_error_message(),
                ));
            }
        }
    }

    $result['inserted'] = $inserted;
    $result['opportunity_inserts'] = $opportunity_inserts;

    if ($inserted <= 0) {
        $result['strategy'] = $ai_attempted ? 'ai_fallback_no_match' : 'no_match';
        botwriter_log('SEO auto postprocess: no links inserted', array(
            'post_id' => $post_id,
            'strategy' => $strategy,
            'ai_attempted' => $ai_attempted ? 1 : 0,
            'suggestion_count' => $result['suggestion_count'],
        ));
        return $result;
    }

    if ($strategy !== 'opportunity' && $opportunity_inserts > 0) {
        $strategy .= '+opportunity';
    }
    $result['strategy'] = $strategy;

    $updated_content = isset($apply['content']) ? (string) $apply['content'] : '';
    if ($updated_content === '') {
        return $result;
    }

    $update_result = wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $updated_content,
    ));

    if (is_wp_error($update_result) || intval($update_result) <= 0) {
        $result['strategy'] = 'update_failed';
        botwriter_log('SEO auto postprocess: wp_update_post failed', array(
            'post_id' => $post_id,
            'error' => is_wp_error($update_result) ? $update_result->get_error_message() : 'invalid_id',
        ));
        return $result;
    }

    $result['updated'] = true;

    botwriter_log('SEO auto internal links applied', array(
        'post_id' => $post_id,
        'strategy' => $strategy,
        'inserted' => $inserted,
        'opportunity_inserts' => $opportunity_inserts,
        'max_links' => $max_links,
        'candidate_count' => $result['candidate_count'],
        'suggestion_count' => $result['suggestion_count'],
    ));

    return $result;
}
