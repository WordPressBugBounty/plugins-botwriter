<?php
/**
 * BotWriter SEO — embeddings storage & indexer (Phase 4.1).
 * Computes embeddings for posts via OpenAI text-embedding-3-small (1536 dims),
 * stores them as packed binary vectors in wp_botwriter_seo_embeddings.
 *
 * Falls back to a hash-based deterministic embedding when no AI key is set,
 * so the rest of the semantic features remain functional for testing.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BOTWRITER_SEO_EMBED_MODEL = 'text-embedding-3-small';
const BOTWRITER_SEO_EMBED_DIM = 1536;
const BOTWRITER_SEO_EMBED_FALLBACK_DIM = 256;

function botwriter_seo_embedding_provider() {
    $provider = get_option('botwriter_seo_embeddings_provider', 'openai');
    return apply_filters('botwriter_seo_embedding_provider', $provider);
}

function botwriter_seo_embedding_text_for_post($post_id) {
    $post = get_post($post_id);
    if (!$post) { return ''; }
    $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
    $tag_str = is_array($tags) ? implode(', ', $tags) : '';
    $body = wp_strip_all_tags($post->post_content);
    if (function_exists('mb_substr')) { $body = mb_substr($body, 0, 4000, 'UTF-8'); }
    return trim($post->post_title . "\n" . $tag_str . "\n" . $body);
}

function botwriter_seo_embedding_index_post($post_id, $force = false) {
    $text = botwriter_seo_embedding_text_for_post($post_id);
    if ($text === '') { return false; }
    $hash = sha1($text);

    global $wpdb;
    $table = esc_sql(botwriter_seo_table('embeddings'));
    $query = $wpdb->prepare("SELECT hash FROM {$table} WHERE post_id = %d", $post_id);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $current_hash = $wpdb->get_var($query);
    if (!$force && $current_hash === $hash) { return true; }

    $vector = botwriter_seo_embed_text($text);
    if (!is_array($vector) || empty($vector)) { return false; }
    $packed = botwriter_seo_pack_vector($vector);
    $dim = count($vector);
    $model = botwriter_seo_embedding_provider() === 'openai' ? BOTWRITER_SEO_EMBED_MODEL : 'fallback-hash';

    $query = $wpdb->prepare("SELECT post_id FROM {$table} WHERE post_id = %d", $post_id);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $exists = $wpdb->get_var($query);
    if ($exists) {
        $wpdb->update($table, array(
            'model' => $model, 'dim' => $dim, 'vector' => $packed, 'hash' => $hash, 'updated_at' => time(),
        ), array('post_id' => $post_id), array('%s', '%d', '%s', '%s', '%d'), array('%d'));
    } else {
        $wpdb->insert($table, array(
            'post_id' => $post_id, 'model' => $model, 'dim' => $dim,
            'vector' => $packed, 'hash' => $hash, 'updated_at' => time(),
        ), array('%d', '%s', '%d', '%s', '%s', '%d'));
    }
    return true;
}

function botwriter_seo_embed_text($text) {
    $config = botwriter_seo_ai_config();
    if (botwriter_seo_embedding_provider() === 'openai' && !empty($config['key'])) {
        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'timeout' => 30,
            'sslverify' => (get_option('botwriter_sslverify', 'yes') === 'yes'),
            'headers' => array(
                'Authorization' => 'Bearer ' . $config['key'],
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => BOTWRITER_SEO_EMBED_MODEL,
                'input' => function_exists('mb_substr') ? mb_substr($text, 0, 7000, 'UTF-8') : substr($text, 0, 7000),
            )),
        ));
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (!empty($body['data'][0]['embedding']) && is_array($body['data'][0]['embedding'])) {
                return $body['data'][0]['embedding'];
            }
        }
    }
    return botwriter_seo_embed_fallback($text);
}

/**
 * Deterministic fallback embedding via token hashing into a fixed-dim vector.
 */
function botwriter_seo_embed_fallback($text) {
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', strtolower(remove_accents((string) $text)), -1, PREG_SPLIT_NO_EMPTY);
    $dim = BOTWRITER_SEO_EMBED_FALLBACK_DIM;
    $v = array_fill(0, $dim, 0.0);
    if (!is_array($tokens)) { return $v; }
    foreach ($tokens as $t) {
        if (function_exists('mb_strlen') ? mb_strlen($t) < 3 : strlen($t) < 3) { continue; }
        $h = crc32($t);
        $i = $h % $dim;
        $sign = (($h >> 16) & 1) ? 1.0 : -1.0;
        $v[$i] += $sign;
    }
    // L2 normalize.
    $norm = 0.0;
    foreach ($v as $x) { $norm += $x * $x; }
    $norm = sqrt($norm);
    if ($norm > 0) { foreach ($v as $i => $x) { $v[$i] = $x / $norm; } }
    return $v;
}

function botwriter_seo_pack_vector(array $v) {
    return pack('g*', ...$v);
}
function botwriter_seo_unpack_vector($packed, $dim) {
    if (!$packed) { return array(); }
    $u = unpack('g*', $packed);
    return is_array($u) ? array_values($u) : array();
}
function botwriter_seo_cosine($a, $b) {
    $n = min(count($a), count($b));
    if ($n === 0) { return 0.0; }
    $dot = 0.0; $na = 0.0; $nb = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $dot += $a[$i] * $b[$i];
        $na += $a[$i] * $a[$i];
        $nb += $b[$i] * $b[$i];
    }
    if ($na <= 0 || $nb <= 0) { return 0.0; }
    return $dot / (sqrt($na) * sqrt($nb));
}

function botwriter_seo_get_embedding($post_id) {
    global $wpdb;
    $table = esc_sql(botwriter_seo_table('embeddings'));
    $query = $wpdb->prepare("SELECT vector, dim FROM {$table} WHERE post_id = %d", $post_id);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $row = $wpdb->get_row($query, ARRAY_A);
    if (!$row) { return null; }
    return botwriter_seo_unpack_vector($row['vector'], (int) $row['dim']);
}

function botwriter_seo_all_embeddings() {
    global $wpdb;
    $table = esc_sql(botwriter_seo_table('embeddings'));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $rows = $wpdb->get_results("SELECT post_id, vector, dim FROM {$table}", ARRAY_A);
    $out = array();
    foreach ((array) $rows as $r) {
        $out[(int) $r['post_id']] = botwriter_seo_unpack_vector($r['vector'], (int) $r['dim']);
    }
    return $out;
}

// Bulk job to (re)index everything.
add_filter('botwriter_seo_job_init_embed_index', function ($init) {
    global $wpdb;
    $init['total'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_status='publish' AND post_type IN ('post','page')"
    );
    return $init;
});
add_filter('botwriter_seo_job_run_embed_index', function ($result, $state, $batch_size) {
    global $wpdb;
    $cursor = (int) $state['cursor'];
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_status='publish' AND post_type IN ('post','page') AND ID > %d
         ORDER BY ID ASC LIMIT %d", $cursor, $batch_size
    ));
    $done = 0; $last = $cursor;
    foreach ((array) $ids as $id) {
        botwriter_seo_embedding_index_post((int) $id);
        $done++; $last = (int) $id;
    }
    $result['done'] = $done;
    $result['cursor'] = $last;
    $result['finished'] = ($done < $batch_size);
    return $result;
}, 10, 3);

// Recompute on save.
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    if ($post->post_status !== 'publish') { return; }
    if (!in_array($post->post_type, array('post', 'page'), true)) { return; }
    botwriter_seo_embedding_index_post($post_id);
}, 50, 2);
