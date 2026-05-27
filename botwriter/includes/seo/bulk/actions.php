<?php
/**
 * BotWriter SEO — bulk actions (Phase 1.3).
 * Apply an action to every post matched by simple criteria.
 *
 * Supported actions:
 *  - regen_meta_description (AI)
 *  - refresh_intro (AI)
 *  - expand_content (AI)
 *  - normalize_headings (deterministic HTML cleanup)
 *  - regen_alt_text (AI for images missing alt)
 *  - rebuild_internal_links (calls postprocess engine)
 *  - add_external_references (SERP-powered references block)
 *  - regen_faq (AI; requires ai/faq.php)
 *  - embed_index (compute/refresh embeddings)
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralized logger for bulk actions.
 */
function botwriter_seo_bulk_log($message, $context = array()) {
    if (function_exists('botwriter_log')) {
        botwriter_log('[SEO Bulk] ' . $message, is_array($context) ? $context : array('context' => $context));
    }
}

function botwriter_seo_bulk_provider_label($provider) {
    $provider = sanitize_key((string) $provider);
    $map = array(
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'google' => 'Google Gemini',
        'mistral' => 'Mistral',
        'groq' => 'Groq',
        'openrouter' => 'OpenRouter',
    );
    return isset($map[$provider]) ? $map[$provider] : ucfirst($provider);
}

function botwriter_seo_bulk_action_success($changed = true, $extra = array()) {
    $result = array(
        'ok' => true,
        'changed' => (bool) $changed,
        'error_code' => '',
        'error_type' => '',
        'error_label' => '',
        'error_message' => '',
    );
    if (is_array($extra)) {
        $result = array_merge($result, $extra);
    }
    return $result;
}

function botwriter_seo_bulk_action_error($code, $message, $type = 'unknown', $label = '') {
    $message = trim(wp_strip_all_tags((string) $message));
    return array(
        'ok' => false,
        'changed' => false,
        'error_code' => sanitize_key((string) $code),
        'error_type' => sanitize_key((string) $type),
        'error_label' => $label !== '' ? (string) $label : __('Could not apply action', 'botwriter'),
        'error_message' => $message,
    );
}

function botwriter_seo_bulk_normalize_action_result($raw) {
    if (is_array($raw)) {
        return array(
            'ok' => !empty($raw['ok']),
            'changed' => !empty($raw['changed']),
            'error_code' => sanitize_key((string) ($raw['error_code'] ?? '')),
            'error_type' => sanitize_key((string) ($raw['error_type'] ?? '')),
            'error_label' => (string) ($raw['error_label'] ?? ''),
            'error_message' => trim(wp_strip_all_tags((string) ($raw['error_message'] ?? ''))),
        );
    }

    if (is_bool($raw)) {
        return $raw
            ? botwriter_seo_bulk_action_success(true)
            : botwriter_seo_bulk_action_error(
                'no_change_applied',
                __('The action did not produce a visible change on this post.', 'botwriter'),
                'skip',
                __('No change applied', 'botwriter')
            );
    }

    return botwriter_seo_bulk_action_error(
        'invalid_action_result',
        __('Unexpected action response format.', 'botwriter'),
        'unknown',
        __('Unexpected response', 'botwriter')
    );
}

function botwriter_seo_bulk_action_error_from_ai($ai, $fallback_message = '') {
    $error_code = sanitize_key((string) ($ai['error_code'] ?? 'ai_request_failed'));
    $error_type = sanitize_key((string) ($ai['error_type'] ?? 'server'));
    $error_label = (string) ($ai['error_label'] ?? __('AI service error', 'botwriter'));
    $error_message = trim((string) ($ai['error_message'] ?? $ai['error'] ?? ''));
    if ($error_message === '') {
        $error_message = $fallback_message !== ''
            ? $fallback_message
            : __('Could not get a valid response from the AI service.', 'botwriter');
    }

    return botwriter_seo_bulk_action_error($error_code, $error_message, $error_type, $error_label);
}

function botwriter_seo_bulk_classify_ai_error($error_code, $error_message, $error_data = array()) {
    $code = sanitize_key((string) $error_code);
    $message = trim(wp_strip_all_tags((string) $error_message));
    $data = is_array($error_data) ? $error_data : array();
    $http_code = isset($data['http_code']) ? (int) $data['http_code'] : 0;
    $worker_error_code = sanitize_key((string) ($data['worker_error_code'] ?? ''));
    $haystack = strtolower(trim($code . ' ' . $worker_error_code . ' ' . $message . ' http ' . $http_code));

    $type = 'server';
    $label = __('AI service error', 'botwriter');
    $friendly = '';

    $looks_like_auth =
        strpos($haystack, 'api key') !== false
        || strpos($haystack, 'unauthorized') !== false
        || strpos($haystack, 'invalid_api_key') !== false
        || strpos($haystack, 'invalid key') !== false
        || $http_code === 401
        || $http_code === 403;

    if (strpos($haystack, 'missing api key') !== false || strpos($haystack, 'missing_api_key') !== false) {
        $type = 'configuration';
        $label = __('Missing API key', 'botwriter');
        $friendly = __('Missing API key for the selected AI provider.', 'botwriter');
    } elseif (
        strpos($haystack, 'quota') !== false
        || strpos($haystack, 'insufficient_quota') !== false
        || strpos($haystack, 'billing') !== false
        || strpos($haystack, 'credits') !== false
        || strpos($haystack, 'payment') !== false
    ) {
        $type = 'quota';
        $label = __('Quota exceeded', 'botwriter');
        $friendly = __('AI quota or billing limit reached. Check your provider plan and try again.', 'botwriter');
    } elseif (
        strpos($haystack, 'rate limit') !== false
        || strpos($haystack, 'too many requests') !== false
        || $http_code === 429
    ) {
        $type = 'rate_limit';
        $label = __('Rate limit reached', 'botwriter');
        $friendly = __('AI provider rate limit reached. Retry in a few minutes.', 'botwriter');
    } elseif (
        strpos($haystack, 'model') !== false
        && (
            strpos($haystack, 'overloaded') !== false
            || strpos($haystack, 'unavailable') !== false
            || strpos($haystack, 'not found') !== false
            || strpos($haystack, 'unsupported') !== false
            || strpos($haystack, 'does not exist') !== false
        )
    ) {
        $type = 'model';
        $label = __('Model unavailable', 'botwriter');
        $friendly = __('The selected model is unavailable, saturated, or unsupported right now.', 'botwriter');
    } elseif (
        strpos($haystack, 'timeout') !== false
        || strpos($haystack, 'timed out') !== false
        || strpos($haystack, 'connection') !== false
        || strpos($haystack, 'network') !== false
        || $code === 'editor_worker_network'
    ) {
        $type = 'transport';
        $label = __('Network error', 'botwriter');
        $friendly = __('Network/transport error while contacting the AI service.', 'botwriter');
    } elseif ($looks_like_auth) {
        $type = 'provider_auth';
        $label = __('Authentication failed', 'botwriter');
        $friendly = __('AI authentication failed. Verify API key permissions and provider.', 'botwriter');
    } elseif ($http_code >= 500) {
        $type = 'server';
        $label = __('Temporary server error', 'botwriter');
        $friendly = __('Temporary AI service error (server side). Please retry shortly.', 'botwriter');
    }

    if ($friendly === '') {
        $friendly = $message !== ''
            ? $message
            : __('The AI service returned an unknown error.', 'botwriter');
    } elseif ($message !== '' && stripos($friendly, $message) === false) {
        $friendly .= ' ' . sprintf(__('Details: %s', 'botwriter'), $message);
    }

    return array(
        'error_code' => $code !== '' ? $code : 'ai_request_failed',
        'error_type' => $type,
        'error_label' => $label,
        'error_message' => $friendly,
        'http_code' => $http_code,
    );
}

/**
 * Normalize editor worker responses across legacy/new formats.
 *
 * Supported formats:
 *  - string content
 *  - WP_Error
 *  - array('ok' => true, 'text' => '...')
 */
function botwriter_seo_bulk_ai_extract_text($resp) {
    if (is_wp_error($resp)) {
        $classified = botwriter_seo_bulk_classify_ai_error(
            (string) $resp->get_error_code(),
            (string) $resp->get_error_message(),
            $resp->get_error_data()
        );
        return array(
            'ok' => false,
            'text' => '',
            'format' => 'wp_error',
            'error' => (string) ($classified['error_message'] ?? ''),
            'error_code' => (string) ($classified['error_code'] ?? 'ai_request_failed'),
            'error_type' => (string) ($classified['error_type'] ?? 'server'),
            'error_label' => (string) ($classified['error_label'] ?? __('AI service error', 'botwriter')),
            'error_message' => (string) ($classified['error_message'] ?? ''),
        );
    }
    if (is_string($resp)) {
        $text = trim($resp);
        return array(
            'ok' => $text !== '',
            'text' => $text,
            'format' => 'string',
            'error' => $text === '' ? 'empty_string' : '',
            'error_code' => $text === '' ? 'empty_response' : '',
            'error_type' => $text === '' ? 'empty_response' : '',
            'error_label' => $text === '' ? __('Empty response', 'botwriter') : '',
            'error_message' => $text === '' ? __('AI returned an empty response.', 'botwriter') : '',
        );
    }
    if (is_array($resp)) {
        $ok = !empty($resp['ok']);
        $text = trim((string) ($resp['text'] ?? ''));
        if (!$ok && $text !== '') {
            // Some providers return text without explicit ok=true.
            $ok = true;
        }
        return array(
            'ok' => $ok,
            'text' => $text,
            'format' => 'array',
            'error' => (string) ($resp['error'] ?? ($ok ? '' : 'empty_or_not_ok')),
            'error_code' => $ok ? '' : sanitize_key((string) ($resp['error_code'] ?? 'empty_or_not_ok')),
            'error_type' => $ok ? '' : sanitize_key((string) ($resp['error_type'] ?? 'server')),
            'error_label' => $ok ? '' : (string) ($resp['error_label'] ?? __('AI service error', 'botwriter')),
            'error_message' => $ok ? '' : trim(wp_strip_all_tags((string) ($resp['error_message'] ?? $resp['error'] ?? ''))),
        );
    }
    return array(
        'ok' => false,
        'text' => '',
        'format' => gettype($resp),
        'error' => 'unsupported_response_format',
        'error_code' => 'unsupported_response_format',
        'error_type' => 'unknown',
        'error_label' => __('Unsupported response', 'botwriter'),
        'error_message' => __('Unsupported AI response format.', 'botwriter'),
    );
}

function botwriter_seo_bulk_ai_request($action, $post_id, $config, $prompt, $max_tokens, $temperature) {
    $t = microtime(true);
    botwriter_seo_bulk_log('AI call start: ' . $action, array(
        'post_id' => (int) $post_id,
        'provider' => (string) ($config['provider'] ?? ''),
        'model' => (string) ($config['model'] ?? ''),
        'prompt_len' => strlen((string) $prompt),
    ));
    $resp = botwriter_call_editor_worker($config['provider'], $config['key'], $config['model'], $prompt, $max_tokens, $temperature);
    $ai = botwriter_seo_bulk_ai_extract_text($resp);
    botwriter_seo_bulk_log('AI call end: ' . $action, array(
        'post_id' => (int) $post_id,
        'ok' => !empty($ai['ok']) ? 1 : 0,
        'format' => (string) ($ai['format'] ?? ''),
        'error' => (string) ($ai['error'] ?? ''),
        'error_code' => (string) ($ai['error_code'] ?? ''),
        'error_type' => (string) ($ai['error_type'] ?? ''),
        'error_message' => (string) ($ai['error_message'] ?? ''),
        'text_len' => strlen((string) ($ai['text'] ?? '')),
        'elapsed_ms' => round((microtime(true) - $t) * 1000),
    ));
    return $ai;
}

function botwriter_seo_bulk_get_primary_keyword($post_id) {
    $keyword = function_exists('botwriter_seo_get_meta')
        ? trim((string) botwriter_seo_get_meta($post_id, 'focus'))
        : '';

    if ($keyword === '') {
        $keyword = trim((string) get_post_meta($post_id, '_botwriter_seo_primary_keyword', true));
    }

    return $keyword;
}

function botwriter_seo_bulk_clean_ai_html($html, $wrap_paragraph = false) {
    $html = trim((string) $html);
    $html = preg_replace('~^```(?:html)?\s*|\s*```$~i', '', $html);
    $html = trim((string) $html);
    $html = wp_kses_post($html);

    if ($html === '') {
        return '';
    }

    if ($wrap_paragraph && stripos($html, '<p') === false) {
        $text = trim(wp_strip_all_tags($html));
        return $text === '' ? '' : '<p>' . esc_html($text) . '</p>';
    }

    return $html;
}

function botwriter_seo_bulk_replace_first_paragraphs($content, $replacement, $count) {
    $count = max(1, (int) $count);
    $seen = 0;
    $matched = false;
    $updated = preg_replace_callback(
        '~<p\b[^>]*>.*?</p>~is',
        function ($match) use (&$seen, &$matched, $count, $replacement) {
            $seen++;
            if ($seen === 1) {
                $matched = true;
                return $replacement;
            }
            if ($seen <= $count) {
                return '';
            }
            return $match[0];
        },
        (string) $content
    );

    if (!$matched) {
        return trim((string) $replacement) . "\n\n" . ltrim((string) $content);
    }

    return (string) $updated;
}

function botwriter_seo_bulk_update_post_content($post_id, $html) {
    $html = trim((string) $html);
    if ($html === '') {
        return false;
    }

    $result = wp_update_post(array(
        'ID' => (int) $post_id,
        'post_content' => $html,
    ), true);

    if (is_wp_error($result)) {
        botwriter_seo_bulk_log('content update failed', array(
            'post_id' => (int) $post_id,
            'error' => $result->get_error_message(),
        ));
        return false;
    }

    botwriter_seo_compute_score($post_id, true);
    return true;
}

function botwriter_seo_bulk_suggest_primary_keyword($post_id, $post, $config, $persist = true) {
    $excerpt = wp_strip_all_tags((string) $post->post_content);
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerpt, 0, 1800, 'UTF-8');
    }

    $prompt = "Suggest a SINGLE primary SEO keyword (1-4 words) for the article below. Return only the keyword, no quotes or explanations. Same language as the article.\n\n"
        . "TITLE: " . $post->post_title . "\n\n"
        . "CONTENT:\n" . $excerpt;

    $ai = botwriter_seo_bulk_ai_request('suggest_primary_keyword', $post_id, $config, $prompt, 30, 0.4);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return '';
    }

    $keyword = trim(wp_strip_all_tags($ai['text']));
    $keyword = trim($keyword, " .\"'");
    if ($keyword === '') {
        return '';
    }

    if ($persist) {
        botwriter_seo_set_meta($post_id, 'focus', $keyword);
    }
    return $keyword;
}

function botwriter_seo_bulk_normalize_host($url) {
    $host = (string) wp_parse_url($url, PHP_URL_HOST);
    $host = strtolower(trim($host));
    return preg_replace('~^www\.~', '', $host);
}

function botwriter_seo_bulk_dom_rename_heading($dom, $node, $tag_name) {
    if (!($node instanceof DOMElement)) {
        return null;
    }

    $new_node = $dom->createElement($tag_name);
    if ($node->hasAttributes()) {
        foreach ($node->attributes as $attribute) {
            $new_node->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }
    }
    while ($node->firstChild) {
        $new_node->appendChild($node->firstChild);
    }
    $node->parentNode->replaceChild($new_node, $node);
    return $new_node;
}

add_filter('botwriter_seo_job_init_actions', function ($init, $args) {
    $args = is_array($args) ? $args : array();
    // Apply per-action default filters (e.g. regen_alt_text only on posts with missing alt).
    $args = botwriter_seo_actions_default_filters($args);
    if (function_exists('botwriter_seo_target_count')) {
        $total = botwriter_seo_target_count($args);
    } else {
        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page')");
    }
    $init['total'] = $total;
    /* translators: %d: number of posts queued for the bulk action */
    $init['message'] = sprintf(__('%d items queued', 'botwriter'), $total);
    botwriter_seo_bulk_log('job init', array(
        'action' => sanitize_key($args['action'] ?? ''),
        'total' => $total,
        'args' => $args,
    ));
    return $init;
}, 10, 2);

/**
 * Keep AI actions responsive in UI: process 1 item per tick so
 * the Live progress card updates continuously instead of waiting for
 * long 8-item batches to finish.
 */
add_filter('botwriter_seo_job_batch_size_actions', function ($size, $state) {
    $args = is_array($state['args'] ?? null) ? $state['args'] : array();
    $action = sanitize_key($args['action'] ?? '');
    $ai_actions = array('regen_meta_description', 'refresh_intro', 'expand_content', 'regen_seo_title', 'regen_alt_text', 'regen_faq', 'rewrite_slug');
    if (in_array($action, $ai_actions, true)) {
        return 1;
    }
    // Image optimization can be slow per post (multiple attachments + re-encode).
    if ($action === 'optimize_images') {
        return 1;
    }
    return (int) $size;
}, 10, 2);

function botwriter_seo_bulk_recent_result_status($apply) {
    $ok = !empty($apply['ok']);
    $changed = !empty($apply['changed']);
    $error_type = sanitize_key((string) ($apply['error_type'] ?? ''));
    $error_label = trim((string) ($apply['error_label'] ?? ''));

    if ($ok && $changed) {
        return array('success', __('Changed successfully', 'botwriter'));
    }

    if ($ok) {
        return array('info', __('Processed without visible change', 'botwriter'));
    }

    if ($error_type === 'skip') {
        return array('info', $error_label !== '' ? $error_label : __('Skipped', 'botwriter'));
    }

    return array('warn', $error_label !== '' ? $error_label : __('No change applied', 'botwriter'));
}

function botwriter_seo_bulk_recent_result_detail($apply) {
    $ok = !empty($apply['ok']);
    $changed = !empty($apply['changed']);
    $undo_type = sanitize_key((string) ($apply['undo_type'] ?? 'none'));
    $undo_saved = !empty($apply['undo_saved']);

    if ($ok && $changed && $undo_saved) {
        return __('Undo snapshot saved for this post.', 'botwriter');
    }

    if ($ok && $changed) {
        return $undo_type === 'none'
            ? __('Change applied. This action does not store a simple undo snapshot.', 'botwriter')
            : __('Change applied successfully.', 'botwriter');
    }

    if ($ok && !$changed) {
        return $undo_type === 'none'
            ? __('The action ran, but it did not produce a visible difference on this post.', 'botwriter')
            : __('The action finished, but there was no stored content/meta difference to save.', 'botwriter');
    }

    $error_message = trim((string) ($apply['error_message'] ?? ''));
    if ($error_message !== '') {
        return $error_message;
    }

    return __('The action could not apply a change to this post. Check the filters or logs if you expected an update.', 'botwriter');
}

add_filter('botwriter_seo_job_run_actions', function ($result, $state, $batch_size) {
    $args = is_array($state['args']) ? $state['args'] : array();
    $args = botwriter_seo_actions_default_filters($args);
    $action = sanitize_key($args['action'] ?? '');
    $action_label = function_exists('botwriter_seo_bulk_action_label') ? botwriter_seo_bulk_action_label($action) : $action;
    $cursor = (int) $state['cursor'];
    $t_batch = microtime(true);

    if (function_exists('botwriter_seo_target_ids')) {
        $ids = botwriter_seo_target_ids($args, $cursor, $batch_size);
    } else {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status='publish' AND post_type IN ('post','page') AND ID > %d
             ORDER BY ID ASC LIMIT %d",
            $cursor, $batch_size
        ));
    }
    botwriter_seo_bulk_log('batch fetched ids', array(
        'action' => $action,
        'cursor' => $cursor,
        'batch_size' => (int) $batch_size,
        'ids_count' => is_array($ids) ? count($ids) : 0,
    ));

    $done = 0; $last = $cursor; $recent = array();
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $id = (int) $id;
            $t_item = microtime(true);
            $apply = botwriter_seo_apply_action($action, $id, $args);
            $ok = !empty($apply['ok']);
            $changed = isset($apply['changed']) ? !empty($apply['changed']) : $ok;
            $is_skip = !$ok && sanitize_key((string) ($apply['error_type'] ?? '')) === 'skip';
            $title = get_the_title($id);
            list($result_tone, $result_label) = botwriter_seo_bulk_recent_result_status($apply);
            $result_detail = botwriter_seo_bulk_recent_result_detail($apply);
            botwriter_seo_bulk_log('item processed', array(
                'action' => $action,
                'post_id' => $id,
                'ok' => $ok ? 1 : 0,
                'changed' => $changed ? 1 : 0,
                'error_code' => (string) ($apply['error_code'] ?? ''),
                'error_type' => (string) ($apply['error_type'] ?? ''),
                'elapsed_ms' => round((microtime(true) - $t_item) * 1000),
            ));
            $recent[] = array(
                'id'        => $id,
                'title'     => $title !== '' ? $title : sprintf('#%d', $id),
                'result_tone' => $result_tone,
                'result_label' => $result_label,
                'detail'    => $result_detail,
                'status'    => $ok ? ($changed ? 'good' : 'skipped') : ($is_skip ? 'skipped' : 'bad'),
                'message'   => $ok
                    ? ($changed ? __('Updated', 'botwriter') : __('Processed', 'botwriter'))
                    : ($is_skip ? __('Skipped', 'botwriter') : __('Failed', 'botwriter')),
                'error_code' => (string) ($apply['error_code'] ?? ''),
                'error_type' => (string) ($apply['error_type'] ?? ''),
                'edit_url'  => get_edit_post_link($id, 'raw'),
                'view_url'  => get_permalink($id),
                'ts'        => time(),
            );
            $done++;
            $last = $id;
        }
    }
    $result['done'] = $done;
    $result['cursor'] = $last;
    $result['recent'] = $recent;
    $result['finished'] = ($done < $batch_size);
    $result['message'] = $done > 0
        /* translators: 1: bulk action label, 2: last processed post ID */
        ? sprintf(__('%1$s — last post ID %2$d', 'botwriter'), $action_label, $last)
        : __('No more items match the filter.', 'botwriter');
    botwriter_seo_bulk_log('batch finished', array(
        'action' => $action,
        'done_in_batch' => $done,
        'last_id' => $last,
        'finished' => !empty($result['finished']) ? 1 : 0,
        'elapsed_ms' => round((microtime(true) - $t_batch) * 1000),
    ));
    return $result;
}, 10, 3);

/**
 * Apply per-action default scope filters when the user hasn't overridden them.
 */
function botwriter_seo_actions_default_filters($args) {
    $action = sanitize_key($args['action'] ?? '');
    switch ($action) {
        case 'regen_meta_description':
            // Default: items with empty/short meta description (<30 chars) when the user didn't set the limit.
            if (!isset($args['missing_meta_max']) || $args['missing_meta_max'] === '') {
                $args['missing_meta_max'] = 30;
            }
            break;
        case 'refresh_intro':
            if (!isset($args['intro_paragraphs']) || $args['intro_paragraphs'] === '') {
                $args['intro_paragraphs'] = 2;
            }
            if (!isset($args['auto_keyword'])) {
                $args['auto_keyword'] = 1;
            }
            break;
        case 'expand_content':
            if (!isset($args['target_words']) || $args['target_words'] === '') {
                $args['target_words'] = 450;
            }
            break;
        case 'normalize_headings':
            if (!isset($args['demote_h1'])) {
                $args['demote_h1'] = 1;
            }
            if (!isset($args['add_subheadings'])) {
                $args['add_subheadings'] = 1;
            }
            break;
        case 'regen_seo_title':
            // Default: only items missing a custom SEO title.
            if (!isset($args['missing_seo_title'])) {
                $args['missing_seo_title'] = 1;
            }
            break;
        case 'add_external_references':
            if (!isset($args['external_links_count']) || $args['external_links_count'] === '') {
                $args['external_links_count'] = 2;
            }
            break;
        case 'regen_alt_text':
            // Force has-images filter so we don't process imageless posts.
            $args['has_images'] = 1;
            break;
        case 'optimize_images':
            // Target only posts with inline images or featured image.
            $args['has_images'] = 1;
            break;
        case 'regen_faq':
            // Default: only items without an FAQ block yet.
            if (!isset($args['missing_faq'])) {
                $args['missing_faq'] = 1;
            }
            // Default: FAQ block is visible to users in post content.
            if (!isset($args['faq_visible'])) {
                $args['faq_visible'] = 1;
            }
            break;
        case 'rewrite_slug':
            // Default: only slugs longer than 60 chars.
            if (!isset($args['slug_length_gt']) || $args['slug_length_gt'] === '') {
                $args['slug_length_gt'] = 60;
            }
            break;
        case 'rebuild_internal_links':
        case 'embed_index':
            break;
    }
    return $args;
}

function botwriter_seo_apply_action($action, $post_id, $args = array()) {
    botwriter_seo_bulk_log('apply action', array(
        'action' => $action,
        'post_id' => (int) $post_id,
    ));

    $run_id = botwriter_seo_bulk_undo_run_id_from_args($args);
    $undo_type = $run_id > 0 ? botwriter_seo_bulk_undo_type_for_action($action) : 'none';
    $snapshot = false;
    if ($run_id > 0 && $undo_type !== 'none') {
        $snapshot = botwriter_seo_bulk_undo_capture_snapshot($action, $post_id, $args);
    }

    $raw_result = false;

    switch ($action) {
        case 'regen_meta_description':
            $raw_result = botwriter_seo_action_regen_meta_description($post_id);
            break;
        case 'refresh_intro':
            $raw_result = botwriter_seo_action_refresh_intro($post_id, $args);
            break;
        case 'expand_content':
            $raw_result = botwriter_seo_action_expand_content($post_id, $args);
            break;
        case 'normalize_headings':
            $raw_result = botwriter_seo_action_normalize_headings($post_id, $args);
            break;
        case 'regen_seo_title':
            $raw_result = botwriter_seo_action_regen_seo_title($post_id);
            break;
        case 'rewrite_slug':
            $raw_result = botwriter_seo_action_rewrite_slug($post_id, $args);
            break;
        case 'add_external_references':
            $raw_result = botwriter_seo_action_add_external_references($post_id, $args);
            break;
        case 'regen_alt_text':
            $raw_result = botwriter_seo_action_regen_alt_text($post_id);
            break;
        case 'rebuild_internal_links':
            $raw_result = botwriter_seo_action_rebuild_internal_links($post_id);
            break;
        case 'regen_faq':
            if (!function_exists('botwriter_seo_generate_faq_for_post')) {
                botwriter_seo_bulk_log('regen_faq skipped (function missing)', array('post_id' => (int) $post_id));
                $raw_result = botwriter_seo_bulk_action_error(
                    'faq_function_missing',
                    __('FAQ generator function is not available in this installation.', 'botwriter'),
                    'configuration',
                    __('FAQ engine unavailable', 'botwriter')
                );
                break;
            }
            $t = microtime(true);
            $ok = (bool) botwriter_seo_generate_faq_for_post($post_id);
            if ($ok) {
                $visible = (!isset($args['faq_visible']) || !empty($args['faq_visible'])) ? 1 : 0;
                update_post_meta($post_id, '_botwriter_seo_faq_visible', $visible);
                botwriter_seo_compute_score($post_id, true);
            }
            botwriter_seo_bulk_log('regen_faq result', array(
                'post_id' => (int) $post_id,
                'ok' => $ok ? 1 : 0,
                'faq_visible' => (!isset($args['faq_visible']) || !empty($args['faq_visible'])) ? 1 : 0,
                'elapsed_ms' => round((microtime(true) - $t) * 1000),
            ));
            $raw_result = $ok
                ? botwriter_seo_bulk_action_success(true)
                : botwriter_seo_bulk_action_error(
                    'faq_not_generated',
                    __('FAQ was not generated for this post.', 'botwriter'),
                    'skip',
                    __('No FAQ generated', 'botwriter')
                );
            break;
        case 'embed_index':
            if (!function_exists('botwriter_seo_embedding_index_post')) {
                botwriter_seo_bulk_log('embed_index skipped (function missing)', array('post_id' => (int) $post_id));
                $raw_result = botwriter_seo_bulk_action_error(
                    'embedding_function_missing',
                    __('Embedding index function is not available in this installation.', 'botwriter'),
                    'configuration',
                    __('Embedding engine unavailable', 'botwriter')
                );
                break;
            }
            $t = microtime(true);
            $ok = (bool) botwriter_seo_embedding_index_post($post_id, true);
            botwriter_seo_bulk_log('embed_index result', array(
                'post_id' => (int) $post_id,
                'ok' => $ok ? 1 : 0,
                'elapsed_ms' => round((microtime(true) - $t) * 1000),
            ));
            $raw_result = $ok
                ? botwriter_seo_bulk_action_success(true)
                : botwriter_seo_bulk_action_error(
                    'embedding_not_updated',
                    __('Could not update semantic embedding for this post.', 'botwriter'),
                    'skip',
                    __('Embedding not updated', 'botwriter')
                );
            break;
        case 'optimize_images':
            if (!function_exists('botwriter_seo_action_optimize_images')) {
                botwriter_seo_bulk_log('optimize_images skipped (function missing)', array('post_id' => (int) $post_id));
                $raw_result = botwriter_seo_bulk_action_error(
                    'image_optimizer_missing',
                    __('Image optimizer function is not available in this installation.', 'botwriter'),
                    'configuration',
                    __('Image optimizer unavailable', 'botwriter')
                );
                break;
            }
            $raw_result = (bool) botwriter_seo_action_optimize_images($post_id, $args);
            break;
        default:
            botwriter_seo_bulk_log('unknown action', array('action' => $action, 'post_id' => (int) $post_id));
            $raw_result = botwriter_seo_bulk_action_error(
                'unknown_action',
                sprintf(__('Unknown bulk action: %s', 'botwriter'), (string) $action),
                'configuration',
                __('Unknown action', 'botwriter')
            );
            break;
    }

    $normalized = botwriter_seo_bulk_normalize_action_result($raw_result);
    $ok = !empty($normalized['ok']);

    $changed = false;
    $undo_saved = false;
    if ($run_id > 0 && $undo_type !== 'none') {
        if ($ok && $snapshot && botwriter_seo_bulk_undo_snapshot_has_changed($action, $post_id, $snapshot)) {
            $changed = true;
            $undo_saved = botwriter_seo_bulk_undo_store_snapshot($run_id, $post_id, $undo_type, $snapshot);
        }
    } else {
        $changed = !empty($normalized['changed']);
    }

    botwriter_seo_bulk_log('apply action result', array(
        'action' => $action,
        'post_id' => (int) $post_id,
        'ok' => $ok ? 1 : 0,
        'changed' => $changed ? 1 : 0,
        'error_code' => (string) ($normalized['error_code'] ?? ''),
        'error_type' => (string) ($normalized['error_type'] ?? ''),
        'error_message' => (string) ($normalized['error_message'] ?? ''),
        'undo_type' => $undo_type,
        'undo_run_id' => $run_id,
        'undo_saved' => $undo_saved ? 1 : 0,
    ));

    return array(
        'ok' => (bool) $ok,
        'changed' => $changed,
        'error_code' => (string) ($normalized['error_code'] ?? ''),
        'error_type' => (string) ($normalized['error_type'] ?? ''),
        'error_label' => (string) ($normalized['error_label'] ?? ''),
        'error_message' => (string) ($normalized['error_message'] ?? ''),
        'undo_saved' => $undo_saved,
        'undo_type' => $undo_type,
    );
}

function botwriter_seo_action_regen_meta_description($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        botwriter_seo_bulk_log('regen_meta_description skipped (post not found)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_not_found',
            __('Post not found.', 'botwriter'),
            'skip',
            __('Post not found', 'botwriter')
        );
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('regen_meta_description skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }
    $excerpt = wp_strip_all_tags($post->post_content);
    if (function_exists('mb_substr')) { $excerpt = mb_substr($excerpt, 0, 2200, 'UTF-8'); }
    $prompt = "Write a single SEO meta description (max 158 chars) in the SAME LANGUAGE as the article. Be specific, descriptive, no quotes.\n\n"
        . "TITLE: " . $post->post_title . "\n\n"
        . "CONTENT:\n" . $excerpt;
    $ai = botwriter_seo_bulk_ai_request('regen_meta_description', $post_id, $config, $prompt, 200, 0.6);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return botwriter_seo_bulk_action_error_from_ai(
            $ai,
            __('Could not generate SEO meta description.', 'botwriter')
        );
    }
    $desc = trim(wp_strip_all_tags($ai['text']));
    if (function_exists('mb_substr')) { $desc = mb_substr($desc, 0, 158, 'UTF-8'); }
    botwriter_seo_set_meta($post_id, 'desc', $desc);
    botwriter_seo_compute_score($post_id, true);
    botwriter_seo_bulk_log('regen_meta_description updated', array('post_id' => (int) $post_id, 'desc_len' => strlen($desc)));
    return botwriter_seo_bulk_action_success(true);
}

function botwriter_seo_action_refresh_intro($post_id, $args = array()) {
    $post = get_post($post_id);
    if (!$post || trim((string) $post->post_content) === '') {
        botwriter_seo_bulk_log('refresh_intro skipped (post missing or empty)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_empty_or_missing',
            __('Post is missing or has empty content.', 'botwriter'),
            'skip',
            __('Post has no content', 'botwriter')
        );
    }

    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('refresh_intro skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }

    $intro_paragraphs = max(1, min(3, (int) ($args['intro_paragraphs'] ?? 2)));
    $auto_keyword = !isset($args['auto_keyword']) || !empty($args['auto_keyword']);
    $keyword = botwriter_seo_bulk_get_primary_keyword($post_id);
    $keyword_was_missing = ($keyword === '');
    if ($keyword === '' && $auto_keyword) {
        $keyword = botwriter_seo_bulk_suggest_primary_keyword($post_id, $post, $config, false);
    }

    $opening = function_exists('botwriter_seo_first_paragraph')
        ? wp_strip_all_tags((string) botwriter_seo_first_paragraph($post->post_content))
        : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 60, '');
    $excerpt = wp_strip_all_tags((string) $post->post_content);
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerpt, 0, 2200, 'UTF-8');
    }

    $prompt = "Rewrite ONLY the article introduction. Return valid HTML with exactly {$intro_paragraphs} <p> paragraphs, same language as the article, no headings, no lists, no markdown. Keep the promise aligned with the article body and make paragraph 1 stronger for search intent.";
    if ($keyword !== '') {
        $prompt .= " Use this primary keyword naturally in the first paragraph: {$keyword}.";
    }
    $prompt .= "\n\nTITLE: " . $post->post_title . "\n\nCURRENT OPENING:\n" . $opening . "\n\nARTICLE EXCERPT:\n" . $excerpt;

    $ai = botwriter_seo_bulk_ai_request('refresh_intro', $post_id, $config, $prompt, 420, 0.7);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return botwriter_seo_bulk_action_error_from_ai(
            $ai,
            __('Could not regenerate the post introduction.', 'botwriter')
        );
    }

    $replacement = botwriter_seo_bulk_clean_ai_html($ai['text'], true);
    if ($replacement === '') {
        return botwriter_seo_bulk_action_error(
            'empty_replacement',
            __('AI returned an empty introduction.', 'botwriter'),
            'skip',
            __('Empty AI output', 'botwriter')
        );
    }

    $updated = botwriter_seo_bulk_replace_first_paragraphs($post->post_content, $replacement, $intro_paragraphs);
    if (trim($updated) === trim((string) $post->post_content)) {
        botwriter_seo_bulk_log('refresh_intro skipped (content unchanged)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'content_unchanged',
            __('Introduction rewrite produced no content difference.', 'botwriter'),
            'skip',
            __('No visible change', 'botwriter')
        );
    }

    $ok = botwriter_seo_bulk_update_post_content($post_id, $updated);
    if ($ok) {
        if ($keyword_was_missing && $auto_keyword && $keyword !== '') {
            botwriter_seo_set_meta($post_id, 'focus', $keyword);
            botwriter_seo_compute_score($post_id, true);
        }
        botwriter_seo_bulk_log('refresh_intro updated', array(
            'post_id' => (int) $post_id,
            'intro_paragraphs' => $intro_paragraphs,
            'keyword' => $keyword,
        ));
    }
    return $ok
        ? botwriter_seo_bulk_action_success(true)
        : botwriter_seo_bulk_action_error(
            'content_update_failed',
            __('Could not save updated introduction content.', 'botwriter'),
            'server',
            __('Content update failed', 'botwriter')
        );
}

function botwriter_seo_action_expand_content($post_id, $args = array()) {
    $post = get_post($post_id);
    if (!$post || trim((string) $post->post_content) === '') {
        botwriter_seo_bulk_log('expand_content skipped (post missing or empty)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_empty_or_missing',
            __('Post is missing or has empty content.', 'botwriter'),
            'skip',
            __('Post has no content', 'botwriter')
        );
    }

    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('expand_content skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }

    $target_words = max(300, min(2500, (int) ($args['target_words'] ?? 450)));
    $plain = wp_strip_all_tags((string) $post->post_content);
    $current_words = function_exists('botwriter_seo_word_count')
        ? (int) botwriter_seo_word_count($plain)
        : str_word_count($plain);
    if ($current_words >= $target_words) {
        botwriter_seo_bulk_log('expand_content skipped (already above target)', array(
            'post_id' => (int) $post_id,
            'current_words' => $current_words,
            'target_words' => $target_words,
        ));
        return botwriter_seo_bulk_action_error(
            'already_above_target',
            __('Post is already above the target word count.', 'botwriter'),
            'skip',
            __('Already above target', 'botwriter')
        );
    }

    $needed_words = max(100, min(450, $target_words - $current_words + 40));
    $keyword = botwriter_seo_bulk_get_primary_keyword($post_id);
    $excerpt = $plain;
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerpt, 0, 2600, 'UTF-8');
    }

    $prompt = "Write ONE new supporting section for the article below. Return valid HTML starting with a single <h2> followed by 2-4 <p> paragraphs, same language as the article, no FAQ, no conclusion, no markdown. Add about {$needed_words} new words and avoid repeating what the article already says.";
    if ($keyword !== '') {
        $prompt .= " Keep this primary keyword naturally present in the new section when relevant: {$keyword}.";
    }
    $prompt .= "\n\nTITLE: " . $post->post_title . "\n\nCURRENT WORD COUNT: " . $current_words . "\nTARGET WORD COUNT: " . $target_words . "\n\nARTICLE EXCERPT:\n" . $excerpt;

    $ai = botwriter_seo_bulk_ai_request('expand_content', $post_id, $config, $prompt, 650, 0.7);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return botwriter_seo_bulk_action_error_from_ai(
            $ai,
            __('Could not generate a supporting section.', 'botwriter')
        );
    }

    $section = botwriter_seo_bulk_clean_ai_html($ai['text']);
    if ($section === '') {
        return botwriter_seo_bulk_action_error(
            'empty_generated_section',
            __('AI returned an empty section.', 'botwriter'),
            'skip',
            __('Empty AI output', 'botwriter')
        );
    }
    if (stripos($section, '<h2') === false && stripos($section, '<h3') === false) {
        $section = '<h2>' . esc_html__('Additional details', 'botwriter') . '</h2>' . $section;
    }
    if (stripos($section, '<p') === false) {
        $text = trim(wp_strip_all_tags($section));
        if ($text === '') {
            return botwriter_seo_bulk_action_error(
                'invalid_generated_section',
                __('AI generated invalid section markup.', 'botwriter'),
                'skip',
                __('Invalid AI output', 'botwriter')
            );
        }
        $section = '<h2>' . esc_html__('Additional details', 'botwriter') . '</h2><p>' . esc_html($text) . '</p>';
    }

    $updated = rtrim((string) $post->post_content) . "\n\n" . $section;
    $ok = botwriter_seo_bulk_update_post_content($post_id, $updated);
    if ($ok) {
        botwriter_seo_bulk_log('expand_content updated', array(
            'post_id' => (int) $post_id,
            'current_words' => $current_words,
            'target_words' => $target_words,
        ));
    }
    return $ok
        ? botwriter_seo_bulk_action_success(true)
        : botwriter_seo_bulk_action_error(
            'content_update_failed',
            __('Could not save expanded content.', 'botwriter'),
            'server',
            __('Content update failed', 'botwriter')
        );
}

function botwriter_seo_action_normalize_headings($post_id, $args = array()) {
    $post = get_post($post_id);
    if (!$post || trim((string) $post->post_content) === '' || !class_exists('DOMDocument')) {
        botwriter_seo_bulk_log('normalize_headings skipped (post missing, empty, or DOM unavailable)', array('post_id' => (int) $post_id));
        return false;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $html = '<!DOCTYPE html><html><body><div id="botwriter-seo-root">' . $post->post_content . '</div></body></html>';
    $prev = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$loaded) {
        botwriter_seo_bulk_log('normalize_headings skipped (DOM load failed)', array('post_id' => (int) $post_id));
        return false;
    }

    $xpath = new DOMXPath($dom);
    $root_nodes = $xpath->query('//*[@id="botwriter-seo-root"]');
    if (!($root_nodes instanceof DOMNodeList) || $root_nodes->length === 0) {
        return false;
    }
    $root = $root_nodes->item(0);
    if (!($root instanceof DOMElement)) {
        return false;
    }

    $demote_h1 = !isset($args['demote_h1']) || !empty($args['demote_h1']);
    $add_subheadings = !isset($args['add_subheadings']) || !empty($args['add_subheadings']);
    $changed = false;

    if ($demote_h1) {
        $h1_nodes = array();
        $h1_list = $xpath->query('//*[@id="botwriter-seo-root"]//h1');
        if ($h1_list instanceof DOMNodeList) {
            foreach ($h1_list as $node) {
                if ($node instanceof DOMElement) {
                    $h1_nodes[] = $node;
                }
            }
        }
        foreach ($h1_nodes as $node) {
            botwriter_seo_bulk_dom_rename_heading($dom, $node, 'h2');
            $changed = true;
        }
    }

    if ($add_subheadings) {
        $h3_count = 0;
        $h3_list = $xpath->query('//*[@id="botwriter-seo-root"]//h3 | //*[@id="botwriter-seo-root"]//h4');
        if ($h3_list instanceof DOMNodeList) {
            $h3_count = $h3_list->length;
        }
        if ($h3_count === 0) {
            $h2_nodes = array();
            $h2_list = $xpath->query('//*[@id="botwriter-seo-root"]//h2');
            if ($h2_list instanceof DOMNodeList) {
                foreach ($h2_list as $node) {
                    if ($node instanceof DOMElement) {
                        $h2_nodes[] = $node;
                    }
                }
            }
            if (count($h2_nodes) >= 2) {
                $target = $h2_nodes[count($h2_nodes) - 1];
                botwriter_seo_bulk_dom_rename_heading($dom, $target, 'h3');
                $changed = true;
            }
        }
    }

    if (!$changed) {
        botwriter_seo_bulk_log('normalize_headings skipped (nothing to change)', array('post_id' => (int) $post_id));
        return false;
    }

    $updated = '';
    foreach ($root->childNodes as $node) {
        $updated .= $dom->saveHTML($node);
    }

    $ok = botwriter_seo_bulk_update_post_content($post_id, $updated);
    if ($ok) {
        botwriter_seo_bulk_log('normalize_headings updated', array(
            'post_id' => (int) $post_id,
            'demote_h1' => $demote_h1 ? 1 : 0,
            'add_subheadings' => $add_subheadings ? 1 : 0,
        ));
    }
    return $ok;
}

function botwriter_seo_action_add_external_references($post_id, $args = array()) {
    $post = get_post($post_id);
    if (!$post || trim((string) $post->post_content) === '') {
        botwriter_seo_bulk_log('add_external_references skipped (post missing or empty)', array('post_id' => (int) $post_id));
        return false;
    }
    if (!function_exists('botwriter_seo_serp_top')) {
        botwriter_seo_bulk_log('add_external_references skipped (SERP helper missing)', array('post_id' => (int) $post_id));
        return false;
    }

    $desired_links = max(1, min(5, (int) ($args['external_links_count'] ?? 2)));
    $stats = function_exists('botwriter_seo_dom_stats')
        ? botwriter_seo_dom_stats((string) $post->post_content)
        : array('links_external' => 0);
    $existing_links = (int) ($stats['links_external'] ?? 0);
    if ($existing_links >= $desired_links) {
        botwriter_seo_bulk_log('add_external_references skipped (already has enough external links)', array(
            'post_id' => (int) $post_id,
            'existing_links' => $existing_links,
            'desired_links' => $desired_links,
        ));
        return false;
    }

    $keyword = botwriter_seo_bulk_get_primary_keyword($post_id);
    $query = $keyword !== '' ? $keyword : $post->post_title;
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $locale = str_replace('-', '_', strtolower((string) $locale));
    $parts = explode('_', $locale);
    $hl = !empty($parts[0]) ? $parts[0] : 'en';
    $gl = !empty($parts[1]) ? $parts[1] : 'us';
    $serp = botwriter_seo_serp_top($query, $hl, $gl);
    if (!is_array($serp) || empty($serp['organic']) || !is_array($serp['organic'])) {
        botwriter_seo_bulk_log('add_external_references skipped (no SERP results)', array(
            'post_id' => (int) $post_id,
            'query' => $query,
        ));
        return false;
    }

    $site_host = botwriter_seo_bulk_normalize_host(home_url('/'));
    $needed = max(1, $desired_links - $existing_links);
    $selected = array();
    foreach ($serp['organic'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $url = esc_url_raw((string) ($row['link'] ?? ''));
        $host = botwriter_seo_bulk_normalize_host($url);
        if ($url === '' || $host === '' || $host === $site_host) {
            continue;
        }
        if (isset($selected[$host]) || strpos((string) $post->post_content, $url) !== false) {
            continue;
        }
        $title = trim(sanitize_text_field((string) ($row['title'] ?? '')));
        $snippet = trim(wp_strip_all_tags((string) ($row['snippet'] ?? '')));
        if (function_exists('mb_substr')) {
            $snippet = mb_substr($snippet, 0, 180, 'UTF-8');
        }
        $selected[$host] = array(
            'url' => $url,
            'title' => $title !== '' ? $title : $host,
            'snippet' => $snippet,
        );
        if (count($selected) >= $needed) {
            break;
        }
    }

    if (empty($selected)) {
        botwriter_seo_bulk_log('add_external_references skipped (no valid external candidates)', array(
            'post_id' => (int) $post_id,
            'query' => $query,
        ));
        return false;
    }

    $section = '<section class="bw-seo-external-refs"><h2>' . esc_html__('Further reading', 'botwriter') . '</h2><ul>';
    foreach ($selected as $row) {
        $section .= '<li><a href="' . esc_url($row['url']) . '" target="_blank" rel="noopener">' . esc_html($row['title']) . '</a>';
        if ($row['snippet'] !== '') {
            $section .= ' - ' . esc_html($row['snippet']);
        }
        $section .= '</li>';
    }
    $section .= '</ul></section>';

    $content = preg_replace(
        '~<section\b[^>]*class=("|\')[^"\']*bw-seo-external-refs[^"\']*\1[^>]*>.*?</section>~is',
        '',
        (string) $post->post_content
    );
    $updated = rtrim((string) $content) . "\n\n" . $section;
    $ok = botwriter_seo_bulk_update_post_content($post_id, $updated);
    if ($ok) {
        botwriter_seo_bulk_log('add_external_references updated', array(
            'post_id' => (int) $post_id,
            'inserted_links' => count($selected),
            'query' => $query,
        ));
    }
    return $ok;
}

function botwriter_seo_action_regen_alt_text($post_id) {
    $post = get_post($post_id);
    if (!$post || !class_exists('DOMDocument')) {
        botwriter_seo_bulk_log('regen_alt_text skipped (post missing or DOMDocument unavailable)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_or_dom_missing',
            __('Post not found or DOM parser unavailable.', 'botwriter'),
            'skip',
            __('Cannot process images', 'botwriter')
        );
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('regen_alt_text skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $post->post_content . '</div>');
    libxml_clear_errors(); libxml_use_internal_errors($prev);

    $imgs = $dom->getElementsByTagName('img');
    $needs = array();
    foreach ($imgs as $img) {
        if (!$img->hasAttribute('alt') || trim((string) $img->getAttribute('alt')) === '') {
            $needs[] = $img;
        }
    }
    if (!$needs) {
        botwriter_seo_bulk_log('regen_alt_text skipped (no missing alt)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'no_missing_alt',
            __('No images without ALT text were found.', 'botwriter'),
            'skip',
            __('Nothing to fix', 'botwriter')
        );
    }

    botwriter_seo_bulk_log('regen_alt_text targets found', array(
        'post_id' => (int) $post_id,
        'missing_alt_count' => count($needs),
        'provider' => (string) ($config['provider'] ?? ''),
        'model' => (string) ($config['model'] ?? ''),
    ));

    $context = $post->post_title;
    $changed = false;
    $ai_ok = 0;
    $ai_fail = 0;
    $last_ai_error = array();
    foreach ($needs as $img) {
        $prompt = "Write a concise (max 12 words) descriptive ALT text in the same language as the article context. No quotes, no period.\n\n"
            . "ARTICLE TITLE: " . $context . "\n"
            . "IMAGE FILENAME: " . basename((string) $img->getAttribute('src'));
        $t = microtime(true);
        $resp = botwriter_call_editor_worker($config['provider'], $config['key'], $config['model'], $prompt, 60, 0.5);
        $ai = botwriter_seo_bulk_ai_extract_text($resp);
        if (!empty($ai['ok']) && !empty($ai['text'])) {
            $alt = trim(wp_strip_all_tags($ai['text']));
            $alt = trim($alt, " .\"'");
            $img->setAttribute('alt', $alt);
            $changed = true;
            $ai_ok++;
        } else {
            $ai_fail++;
            $last_ai_error = $ai;
        }
        botwriter_seo_bulk_log('AI call: regen_alt_text image', array(
            'post_id' => (int) $post_id,
            'ok' => !empty($ai['ok']) ? 1 : 0,
            'format' => (string) ($ai['format'] ?? ''),
            'error' => (string) ($ai['error'] ?? ''),
            'text_len' => strlen((string) ($ai['text'] ?? '')),
            'elapsed_ms' => round((microtime(true) - $t) * 1000),
        ));
    }
    if (!$changed) {
        botwriter_seo_bulk_log('regen_alt_text ended without changes', array('post_id' => (int) $post_id, 'ai_ok' => $ai_ok, 'ai_fail' => $ai_fail));
        if (!empty($last_ai_error)) {
            return botwriter_seo_bulk_action_error_from_ai(
                $last_ai_error,
                __('Could not generate ALT text for images.', 'botwriter')
            );
        }
        return botwriter_seo_bulk_action_error(
            'alt_not_updated',
            __('Could not update ALT text for this post.', 'botwriter'),
            'skip',
            __('No ALT changes applied', 'botwriter')
        );
    }
    $body = $dom->getElementsByTagName('div')->item(0);
    $html = '';
    foreach ($body->childNodes as $n) { $html .= $dom->saveHTML($n); }
    $saved = wp_update_post(array('ID' => $post_id, 'post_content' => $html), true);
    if (is_wp_error($saved)) {
        return botwriter_seo_bulk_action_error(
            'content_update_failed',
            $saved->get_error_message(),
            'server',
            __('Content update failed', 'botwriter')
        );
    }
    botwriter_seo_compute_score($post_id, true);
    botwriter_seo_bulk_log('regen_alt_text updated', array('post_id' => (int) $post_id, 'ai_ok' => $ai_ok, 'ai_fail' => $ai_fail));
    return botwriter_seo_bulk_action_success(true);
}

function botwriter_seo_action_rebuild_internal_links($post_id) {
    if (function_exists('botwriter_seo_auto_internal_links_postprocess')) {
        $t = microtime(true);
        $result = botwriter_seo_auto_internal_links_postprocess($post_id);
        $ok = !empty($result['updated']);
        botwriter_seo_bulk_log('rebuild_internal_links result', array(
            'post_id' => (int) $post_id,
            'ok' => $ok ? 1 : 0,
            'inserted' => (int) ($result['inserted'] ?? 0),
            'strategy' => (string) ($result['strategy'] ?? ''),
            'elapsed_ms' => round((microtime(true) - $t) * 1000),
        ));
        return $ok;
    }
    botwriter_seo_bulk_log('rebuild_internal_links skipped (function missing)', array('post_id' => (int) $post_id));
    return false;
}

/**
 * Regenerate the SEO title meta (NOT the post title). Writes through
 * botwriter_seo_set_meta() which respects the active SEO plugin (Yoast/Rank
 * Math/AIOSEO) or falls back to the BotWriter native meta.
 */
function botwriter_seo_action_regen_seo_title($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        botwriter_seo_bulk_log('regen_seo_title skipped (post not found)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_not_found',
            __('Post not found.', 'botwriter'),
            'skip',
            __('Post not found', 'botwriter')
        );
    }
    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('regen_seo_title skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }
    $excerpt = wp_strip_all_tags($post->post_content);
    if (function_exists('mb_substr')) { $excerpt = mb_substr($excerpt, 0, 1500, 'UTF-8'); }
    $site = get_bloginfo('name');
    $prompt = "Write ONE SEO title (max 60 chars) in the SAME LANGUAGE as the article. Front-load the main keyword, no clickbait, no quotes, no trailing site name.\n\n"
        . "POST TITLE: " . $post->post_title . "\n"
        . "SITE NAME: " . $site . "\n\n"
        . "CONTENT:\n" . $excerpt;
    $ai = botwriter_seo_bulk_ai_request('regen_seo_title', $post_id, $config, $prompt, 80, 0.6);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return botwriter_seo_bulk_action_error_from_ai(
            $ai,
            __('Could not generate SEO title.', 'botwriter')
        );
    }
    $title = trim(wp_strip_all_tags($ai['text']));
    $title = trim($title, " .\"'");
    if ($title === '') {
        return botwriter_seo_bulk_action_error(
            'empty_generated_title',
            __('AI returned an empty SEO title.', 'botwriter'),
            'skip',
            __('Empty AI output', 'botwriter')
        );
    }
    if (function_exists('mb_substr')) { $title = mb_substr($title, 0, 60, 'UTF-8'); }
    botwriter_seo_set_meta($post_id, 'title', $title);
    botwriter_seo_compute_score($post_id, true);
    botwriter_seo_bulk_log('regen_seo_title updated', array('post_id' => (int) $post_id, 'title_len' => strlen($title)));
    return botwriter_seo_bulk_action_success(true);
}

/**
 * Rewrite the post slug for SEO. DESTRUCTIVE: changes the public URL.
 * The existing post_updated hook in autopilot/redirects.php auto-creates
 * a 301 redirect from the old path to the new one, so existing inbound
 * links and bookmarks keep working. Skipped unless the user explicitly
 * confirmed the destructive flag in the bulk modal.
 */
function botwriter_seo_action_rewrite_slug($post_id, $args = array()) {
    if (empty($args['confirm_destructive'])) {
        botwriter_seo_bulk_log('rewrite_slug skipped (destructive confirm missing)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'destructive_confirm_missing',
            __('Slug rewrite requires explicit destructive confirmation.', 'botwriter'),
            'skip',
            __('Confirmation required', 'botwriter')
        );
    }
    $post = get_post($post_id);
    if (!$post) {
        botwriter_seo_bulk_log('rewrite_slug skipped (post not found)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'post_not_found',
            __('Post not found.', 'botwriter'),
            'skip',
            __('Post not found', 'botwriter')
        );
    }
    if (!in_array($post->post_type, array('post', 'page'), true)) {
        botwriter_seo_bulk_log('rewrite_slug skipped (unsupported post type)', array('post_id' => (int) $post_id, 'post_type' => (string) $post->post_type));
        return botwriter_seo_bulk_action_error(
            'unsupported_post_type',
            __('Slug rewrite only supports posts and pages.', 'botwriter'),
            'skip',
            __('Unsupported content type', 'botwriter')
        );
    }
    if ($post->post_status !== 'publish') {
        botwriter_seo_bulk_log('rewrite_slug skipped (not published)', array('post_id' => (int) $post_id, 'post_status' => (string) $post->post_status));
        return botwriter_seo_bulk_action_error(
            'post_not_published',
            __('Slug rewrite only runs on published posts.', 'botwriter'),
            'skip',
            __('Not published', 'botwriter')
        );
    }

    $config = botwriter_seo_ai_config();
    if (empty($config['key'])) {
        botwriter_seo_bulk_log('rewrite_slug skipped (missing API key)', array('post_id' => (int) $post_id));
        return botwriter_seo_bulk_action_error(
            'missing_api_key',
            sprintf(
                /* translators: %s: provider name */
                __('Missing API key for SEO Bulk provider: %s.', 'botwriter'),
                botwriter_seo_bulk_provider_label((string) ($config['provider'] ?? 'openai'))
            ),
            'configuration',
            __('Missing API key', 'botwriter')
        );
    }

    $kw = (string) get_post_meta($post_id, '_botwriter_seo_primary_keyword', true);
    $prompt = "Suggest ONE short SEO-friendly URL slug (3-6 lowercase words, hyphen-separated, ASCII, no stopwords like 'the', 'and', 'of') in the SAME LANGUAGE as the post title. Only output the slug, nothing else.\n\n"
        . "POST TITLE: " . $post->post_title . "\n"
        . ($kw !== '' ? ("FOCUS KEYWORD: " . $kw . "\n") : '')
        . "CURRENT SLUG: " . $post->post_name;
    $ai = botwriter_seo_bulk_ai_request('rewrite_slug', $post_id, $config, $prompt, 30, 0.4);
    if (empty($ai['ok']) || empty($ai['text'])) {
        return botwriter_seo_bulk_action_error_from_ai(
            $ai,
            __('Could not generate a new slug.', 'botwriter')
        );
    }
    $slug = trim(wp_strip_all_tags($ai['text']));
    $slug = preg_replace('~[^a-z0-9\-]+~', '-', strtolower(remove_accents($slug)));
    $slug = trim(preg_replace('~-+~', '-', $slug), '-');
    if ($slug === '' || $slug === $post->post_name) {
        botwriter_seo_bulk_log('rewrite_slug skipped (same/empty slug)', array('post_id' => (int) $post_id, 'suggested_slug' => (string) $slug));
        return botwriter_seo_bulk_action_error(
            'slug_unchanged',
            __('Generated slug was empty or equal to the current slug.', 'botwriter'),
            'skip',
            __('No slug change', 'botwriter')
        );
    }
    if (function_exists('mb_substr')) { $slug = mb_substr($slug, 0, 75, 'UTF-8'); }
    // wp_unique_post_slug guarantees uniqueness within the post type.
    $unique = wp_unique_post_slug($slug, $post_id, $post->post_status, $post->post_type, $post->post_parent);
    $saved = wp_update_post(array('ID' => $post_id, 'post_name' => $unique), true);
    if (is_wp_error($saved)) {
        return botwriter_seo_bulk_action_error(
            'slug_update_failed',
            $saved->get_error_message(),
            'server',
            __('Slug update failed', 'botwriter')
        );
    }
    botwriter_seo_bulk_log('rewrite_slug updated', array(
        'post_id' => (int) $post_id,
        'old_slug' => (string) $post->post_name,
        'new_slug' => (string) $unique,
    ));
    return botwriter_seo_bulk_action_success(true);
}

/**
 * Resolve current AI config from BotWriter options.
 */
function botwriter_seo_ai_config() {
    // Optional override values saved in SEO settings.
    $provider_override = sanitize_key((string) get_option('botwriter_seo_ai_provider', ''));
    $model_override = trim((string) get_option('botwriter_seo_ai_model', ''));

    // Default behavior: inherit provider/model from global Text AI settings.
    $provider = $provider_override !== ''
        ? $provider_override
        : sanitize_key((string) get_option('botwriter_text_provider', 'openai'));
    if ($provider === '') {
        $provider = 'openai';
    }

    $model = $model_override;
    if ($model === '') {
        if (function_exists('botwriter_get_current_text_model')) {
            $model = (string) botwriter_get_current_text_model();
        } else {
            $model = (string) get_option("botwriter_{$provider}_model", '');
        }
    }

    // Prefer the shared provider-aware helper from botwriter.php.
    $key = '';
    if (function_exists('botwriter_get_provider_api_key')) {
        $key = (string) botwriter_get_provider_api_key($provider);
    }

    // Backward compatibility fallback: legacy OpenAI encrypted option.
    if ($key === '') {
        $key_enc = get_option('botwriter_openai_api_key', '');
        if ($key_enc && defined('AUTH_KEY')) {
            $decoded = base64_decode($key_enc, true);
            if ($decoded !== false) {
                $plain = openssl_decrypt($decoded, 'AES-256-ECB', AUTH_KEY, OPENSSL_RAW_DATA);
                if ($plain !== false) { $key = $plain; }
            }
        }
    }

    if ($model === '') {
        $model = (string) get_option('botwriter_openai_model', 'gpt-5.4-mini');
    }

    return array('provider' => $provider, 'model' => $model, 'key' => $key);
}
