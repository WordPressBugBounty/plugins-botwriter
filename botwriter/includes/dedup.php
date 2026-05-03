<?php
/**
 * BotWriter — Cross-task duplicate detection for RSS / WordPress sources.
 *
 * Public API:
 *   - botwriter_dedup_is_duplicate( array $candidate ): array
 *
 * Settings (whitelisted in includes/settings.php):
 *   - botwriter_dedup_enabled            (UI)    "1" / "0"        default "1"
 *   - botwriter_dedup_title_threshold    (UI)    int 0-100        default 70
 *   - botwriter_dedup_window_days        (hidden) int 1-30        default 7
 *   - botwriter_dedup_url_normalize      (hidden) "1" / "0"       default "1"
 *   - botwriter_dedup_content_threshold  (hidden) int 0-100       default 80
 *   - botwriter_dedup_max_history        (hidden) int 50-1000     default 200
 *   - botwriter_dedup_scope              (hidden) "task" / "site" default "site"
 *   - botwriter_dedup_action             (hidden) "skip"|"log_only" default "skip"
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return all dedup options merged with their defaults.
 *
 * @return array
 */
function botwriter_dedup_get_options() {
    return array(
        'enabled'           => get_option('botwriter_dedup_enabled', '1') === '1',
        'title_threshold'   => max(0, min(100, intval(get_option('botwriter_dedup_title_threshold', 70)))),
        'content_threshold' => max(0, min(100, intval(get_option('botwriter_dedup_content_threshold', 80)))),
        'window_days'       => max(1, min(30, intval(get_option('botwriter_dedup_window_days', 7)))),
        'max_history'       => max(50, min(1000, intval(get_option('botwriter_dedup_max_history', 200)))),
        'url_normalize'     => get_option('botwriter_dedup_url_normalize', '1') === '1',
        'scope'             => get_option('botwriter_dedup_scope', 'site') === 'task' ? 'task' : 'site',
        'action'            => get_option('botwriter_dedup_action', 'skip') === 'log_only' ? 'log_only' : 'skip',
    );
}

/**
 * Normalize a URL so equivalent links compare equal.
 *
 * - Lowercase scheme + host
 * - Strip "www."
 * - Drop fragment
 * - Drop common tracking query params (utm_*, fbclid, gclid, intcmp, ref, source, ...)
 * - Trim trailing slash from path
 *
 * @param string $url
 * @return string Normalized URL (or original on parse failure).
 */
function botwriter_dedup_normalize_url($url) {
    if (!is_string($url) || $url === '') {
        return '';
    }

    $url = trim($url);
    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return strtolower($url);
    }

    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
    $host   = strtolower($parts['host']);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $path = isset($parts['path']) ? $parts['path'] : '/';
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    $query = '';
    if (!empty($parts['query'])) {
        $pairs = array();
        parse_str($parts['query'], $pairs);

        // Drop tracking params
        $drop_exact = array('fbclid', 'gclid', 'intcmp', 'ref', 'source', 'mc_cid', 'mc_eid', '_hsenc', '_hsmi');
        foreach ($pairs as $k => $v) {
            $kl = strtolower($k);
            if (strpos($kl, 'utm_') === 0 || in_array($kl, $drop_exact, true)) {
                unset($pairs[$k]);
            }
        }

        if (!empty($pairs)) {
            ksort($pairs);
            $query = '?' . http_build_query($pairs);
        }
    }

    return $scheme . '://' . $host . $path . $query;
}

/**
 * Reduce a string to a comparable form: lowercase, strip tags, collapse whitespace.
 *
 * @param string $text
 * @return string
 */
function botwriter_dedup_normalize_text($text) {
    if (!is_string($text)) {
        return '';
    }
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
}

/**
 * Compute a similarity percentage (0-100) between two strings.
 *
 * Uses PHP's similar_text(); inputs are normalized first. For very long strings
 * we sample the first 2000 chars to keep the cost bounded.
 *
 * @param string $a
 * @param string $b
 * @return float
 */
function botwriter_dedup_similarity($a, $b) {
    $a = botwriter_dedup_normalize_text($a);
    $b = botwriter_dedup_normalize_text($b);
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 100.0;
    }
    if (strlen($a) > 2000) { $a = substr($a, 0, 2000); }
    if (strlen($b) > 2000) { $b = substr($b, 0, 2000); }

    $percent = 0.0;
    similar_text($a, $b, $percent);
    return (float) $percent;
}

/**
 * Fetch recent log rows used for dedup comparison.
 *
 * @param array      $opts    Options from botwriter_dedup_get_options().
 * @param int|null   $id_task Restrict to this task when scope === 'task'.
 * @return array Rows with link_post_original, aigenerated_title, aigenerated_content.
 */
function botwriter_dedup_get_recent_rows($opts, $id_task = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'botwriter_logs';
    $table_sql = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    if ($table_sql === '') {
        return array();
    }

    $since = gmdate('Y-m-d H:i:s', time() - ($opts['window_days'] * DAY_IN_SECONDS));
    $limit = (int) $opts['max_history'];

    if ($opts['scope'] === 'task' && $id_task) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT link_post_original, aigenerated_title, aigenerated_content
                   FROM `" . $table_sql . "`
                  WHERE id_task = %d
                    AND task_status = 'completed'
                    AND created_at >= %s
                  ORDER BY id DESC
                  LIMIT %d",
                (int) $id_task,
                $since,
                $limit
            ),
            ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT link_post_original, aigenerated_title, aigenerated_content
                   FROM `" . $table_sql . "`
                  WHERE task_status = 'completed'
                    AND created_at >= %s
                  ORDER BY id DESC
                  LIMIT %d",
                $since,
                $limit
            ),
            ARRAY_A
        );
    }

    return is_array($rows) ? $rows : array();
}

/**
 * Decide whether a candidate article (URL + title + optional content) should be
 * considered a duplicate of something already published recently.
 *
 * @param array $candidate {
 *     @type string $url      Source URL (link_post_original).
 *     @type string $title    Source article title.
 *     @type string $content  Source article content (optional).
 *     @type int    $id_task  Current task id (optional, used when scope = 'task').
 * }
 * @return array {
 *     @type bool   $is_duplicate
 *     @type string $reason         'disabled' | 'url' | 'title' | 'content' | 'none'
 *     @type float  $score          0-100 similarity (only when is_duplicate)
 *     @type string $matched_url    URL of the matched previous post
 *     @type string $action         'skip' | 'log_only'
 * }
 */
function botwriter_dedup_is_duplicate(array $candidate) {
    $opts = botwriter_dedup_get_options();

    $result = array(
        'is_duplicate' => false,
        'reason'       => 'none',
        'score'        => 0.0,
        'matched_url'  => '',
        'action'       => $opts['action'],
    );

    if (!$opts['enabled']) {
        $result['reason'] = 'disabled';
        return $result;
    }

    $cand_url     = isset($candidate['url']) ? (string) $candidate['url'] : '';
    $cand_title   = isset($candidate['title']) ? (string) $candidate['title'] : '';
    $cand_content = isset($candidate['content']) ? (string) $candidate['content'] : '';
    $id_task      = isset($candidate['id_task']) ? (int) $candidate['id_task'] : null;

    $cand_url_norm = $opts['url_normalize'] ? botwriter_dedup_normalize_url($cand_url) : strtolower(trim($cand_url));

    $rows = botwriter_dedup_get_recent_rows($opts, $id_task);
    if (empty($rows)) {
        return $result;
    }

    foreach ($rows as $row) {
        $row_url = isset($row['link_post_original']) ? (string) $row['link_post_original'] : '';
        if ($row_url !== '' && $cand_url_norm !== '') {
            $row_url_norm = $opts['url_normalize'] ? botwriter_dedup_normalize_url($row_url) : strtolower(trim($row_url));
            if ($row_url_norm !== '' && $row_url_norm === $cand_url_norm) {
                $result['is_duplicate'] = true;
                $result['reason']       = 'url';
                $result['score']        = 100.0;
                $result['matched_url']  = $row_url;
                return $result;
            }
        }
    }

    if ($cand_title !== '' && $opts['title_threshold'] > 0) {
        foreach ($rows as $row) {
            $row_title = isset($row['aigenerated_title']) ? (string) $row['aigenerated_title'] : '';
            if ($row_title === '') {
                continue;
            }
            $score = botwriter_dedup_similarity($cand_title, $row_title);
            if ($score >= $opts['title_threshold']) {
                $result['is_duplicate'] = true;
                $result['reason']       = 'title';
                $result['score']        = $score;
                $result['matched_url']  = isset($row['link_post_original']) ? (string) $row['link_post_original'] : '';
                return $result;
            }
        }
    }

    if ($cand_content !== '' && $opts['content_threshold'] > 0) {
        foreach ($rows as $row) {
            $row_content = isset($row['aigenerated_content']) ? (string) $row['aigenerated_content'] : '';
            if ($row_content === '') {
                continue;
            }
            $score = botwriter_dedup_similarity($cand_content, $row_content);
            if ($score >= $opts['content_threshold']) {
                $result['is_duplicate'] = true;
                $result['reason']       = 'content';
                $result['score']        = $score;
                $result['matched_url']  = isset($row['link_post_original']) ? (string) $row['link_post_original'] : '';
                return $result;
            }
        }
    }

    return $result;
}
