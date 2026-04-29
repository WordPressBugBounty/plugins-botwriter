<?php
/**
 * BotWriter SEO module — low-level utilities (debug, mb helpers, DOM helpers).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build a short preview string for SEO debug logs.
 *
 * @param string $text Source text.
 * @param int    $max Maximum chars.
 * @return string
 */
function botwriter_seo_debug_preview($text, $max = 180) {
    $text = trim(wp_strip_all_tags((string) $text));
    $text = preg_replace('/\s+/', ' ', $text);
    $max = max(20, intval($max));

    $len = function_exists('mb_strlen')
        ? (int) mb_strlen($text, 'UTF-8')
        : strlen($text);

    if ($len <= $max) {
        return (string) $text;
    }

    if (function_exists('mb_substr')) {
        return (string) mb_substr($text, 0, $max, 'UTF-8') . '...';
    }

    return (string) substr($text, 0, $max) . '...';
}

/**
 * Build a compact sample payload for internal-link suggestions in logs.
 *
 * @param array $rows Suggestion rows.
 * @param int   $limit Max rows.
 * @return array
 */
function botwriter_seo_debug_sample_suggestions($rows, $limit = 5) {
    $limit = max(1, intval($limit));
    $sample = array();

    foreach (array_slice((array) $rows, 0, $limit) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $sample[] = array(
            'url' => (string) ($row['url'] ?? ''),
            'title' => botwriter_seo_debug_preview((string) ($row['title'] ?? ''), 90),
            'anchor' => botwriter_seo_debug_preview((string) ($row['anchor'] ?? ''), 70),
            'insert_after' => botwriter_seo_debug_preview((string) ($row['insert_after'] ?? ''), 70),
            'reason' => botwriter_seo_debug_preview((string) ($row['reason'] ?? ''), 110),
        );
    }

    return $sample;
}

/**
 * Whether verbose low-level SEO trace logs are enabled.
 *
 * @return bool
 */
function botwriter_seo_trace_verbose_enabled() {
    return defined('BOTWRITER_SEO_TRACE_VERBOSE') && constant('BOTWRITER_SEO_TRACE_VERBOSE') === true;
}

/**
 * Stopword dictionary for anchor-quality filtering.
 *
 * @return array
 */
function botwriter_seo_anchor_stopwords() {
    return array(
        'a', 'an', 'the', 'and', 'or', 'of', 'to', 'for', 'in', 'on', 'at', 'by', 'with', 'from', 'as', 'is', 'are',
        'was', 'were', 'be', 'been', 'being', 'that', 'this', 'these', 'those', 'it', 'its', 'your', 'our', 'their',
        'about', 'into', 'over', 'under', 'between', 'through', 'across',
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'y', 'o', 'de', 'del', 'al', 'en', 'con', 'por',
        'para', 'sin', 'sobre', 'entre', 'desde', 'hasta', 'que', 'como', 'es', 'son', 'fue', 'fueron', 'ser',
    );
}

/**
 * Multibyte-safe stripos helper.
 *
 * @param string $haystack Haystack text.
 * @param string $needle Needle text.
 * @return int|false
 */
function botwriter_seo_mb_stripos($haystack, $needle) {
    $haystack = (string) $haystack;
    $needle = (string) $needle;
    $using_mb = function_exists('mb_stripos');

    $result = $using_mb
        ? mb_stripos($haystack, $needle, 0, 'UTF-8')
        : stripos($haystack, $needle);

    if (botwriter_seo_trace_verbose_enabled()) {
        $haystack_len = function_exists('mb_strlen')
            ? (int) mb_strlen($haystack, 'UTF-8')
            : strlen($haystack);
        $needle_len = function_exists('mb_strlen')
            ? (int) mb_strlen($needle, 'UTF-8')
            : strlen($needle);

        botwriter_log('SEO mb_stripos', array(
            'using_mb' => $using_mb ? 1 : 0,
            'haystack_len' => $haystack_len,
            'needle_len' => $needle_len,
            'needle_preview' => botwriter_seo_debug_preview($needle, 120),
            'found' => ($result !== false) ? 1 : 0,
            'index' => ($result === false) ? null : intval($result),
        ));
    }

    return $result;
}

/**
 * Multibyte-safe strlen helper.
 *
 * @param string $text Input text.
 * @return int
 */
function botwriter_seo_mb_strlen($text) {
    $text = (string) $text;
    $using_mb = function_exists('mb_strlen');

    $len = $using_mb
        ? (int) mb_strlen($text, 'UTF-8')
        : strlen($text);

    if (botwriter_seo_trace_verbose_enabled()) {
        botwriter_log('SEO mb_strlen', array(
            'using_mb' => $using_mb ? 1 : 0,
            'bytes_len' => strlen($text),
            'char_len' => $len,
            'text_preview' => botwriter_seo_debug_preview($text, 140),
        ));
    }

    return $len;
}

/**
 * Multibyte-safe substr helper.
 *
 * @param string   $text Input text.
 * @param int      $start Start offset.
 * @param int|null $length Optional length.
 * @return string
 */
function botwriter_seo_mb_substr($text, $start, $length = null) {
    $text = (string) $text;
    $start = intval($start);
    $using_mb = function_exists('mb_substr');

    if ($using_mb) {
        if ($length === null) {
            $result = (string) mb_substr($text, $start, null, 'UTF-8');
        } else {
            $result = (string) mb_substr($text, $start, intval($length), 'UTF-8');
        }
    } else {
        if ($length === null) {
            $result = (string) substr($text, $start);
        } else {
            $result = (string) substr($text, $start, intval($length));
        }
    }

    if (botwriter_seo_trace_verbose_enabled()) {
        $source_len = function_exists('mb_strlen')
            ? (int) mb_strlen($text, 'UTF-8')
            : strlen($text);
        $result_len = function_exists('mb_strlen')
            ? (int) mb_strlen($result, 'UTF-8')
            : strlen($result);

        botwriter_log('SEO mb_substr', array(
            'using_mb' => $using_mb ? 1 : 0,
            'source_len' => $source_len,
            'start' => $start,
            'length' => ($length === null) ? null : intval($length),
            'result_len' => $result_len,
            'source_preview' => botwriter_seo_debug_preview($text, 120),
            'result_preview' => botwriter_seo_debug_preview($result, 120),
        ));
    }

    return $result;
}

/**
 * Check whether the node is inside any disallowed ancestor tag.
 *
 * @param DOMNode $node Target node.
 * @param array   $disallowed_tags Lowercase tag names.
 * @return bool
 */
function botwriter_seo_node_has_disallowed_ancestor($node, $disallowed_tags) {
    if (!($node instanceof DOMNode)) {
        return false;
    }

    $current = $node->parentNode;
    while ($current instanceof DOMNode) {
        if ($current instanceof DOMElement) {
            $tag = strtolower((string) $current->tagName);
            if (in_array($tag, $disallowed_tags, true)) {
                return true;
            }
        }
        $current = $current->parentNode;
    }

    return false;
}

/**
 * Get inner HTML from a DOM element.
 *
 * @param DOMElement $element Element.
 * @return string
 */
function botwriter_seo_dom_inner_html($element) {
    if (!($element instanceof DOMElement)) {
        return '';
    }

    $html = '';
    foreach ($element->childNodes as $child) {
        $html .= $element->ownerDocument->saveHTML($child);
    }

    return (string) $html;
}
