<?php
/**
 * BotWriter SEO — automatic redirects (Phase 5.4).
 * Captures slug changes (post_updated) and resolves on 404 via template_redirect.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('post_updated', function ($post_id, $post_after, $post_before) {
    if ($post_after->post_status !== 'publish') { return; }
    if ($post_before->post_name === $post_after->post_name) { return; }
    if (!in_array($post_after->post_type, array('post', 'page'), true)) { return; }
    $old_url = str_replace($post_after->post_name, $post_before->post_name, get_permalink($post_after));
    $old_path = trim((string) wp_parse_url($old_url, PHP_URL_PATH), '/');
    $new_path = trim((string) wp_parse_url(get_permalink($post_after), PHP_URL_PATH), '/');
    if ($old_path === '' || $old_path === $new_path) { return; }
    botwriter_seo_redirect_add($old_path, $new_path, 301, $post_id);
}, 10, 3);

function botwriter_seo_redirect_add($source_path, $target_path, $code = 301, $post_id = null) {
    global $wpdb;
    $table = esc_sql(botwriter_seo_table('redirects'));
    $query = $wpdb->prepare("SELECT id FROM {$table} WHERE source_path = %s", $source_path);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $exists = $wpdb->get_var($query);
    if ($exists) {
        $wpdb->update($table, array('target_path' => $target_path, 'status_code' => $code), array('id' => $exists));
        return (int) $exists;
    }
    $wpdb->insert($table, array(
        'source_path' => $source_path, 'target_path' => $target_path,
        'status_code' => $code, 'hits' => 0, 'post_id' => $post_id, 'created_at' => time(),
    ), array('%s', '%s', '%d', '%d', '%d', '%d'));
    botwriter_seo_event('redirect_added', array('from' => $source_path, 'to' => $target_path), (int) $post_id);
    return (int) $wpdb->insert_id;
}

add_action('template_redirect', function () {
    if (!is_404()) { return; }
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $request = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
    if ($request === '') { return; }
    global $wpdb;
    $table = esc_sql(botwriter_seo_table('redirects'));
    $query = $wpdb->prepare("SELECT * FROM {$table} WHERE source_path = %s LIMIT %d", $request, 1);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $row = $wpdb->get_row($query, ARRAY_A);
    if (!$row) { return; }
    $query = $wpdb->prepare("UPDATE {$table} SET hits = hits + 1 WHERE id = %d", $row['id']);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $wpdb->query($query);
    wp_safe_redirect(home_url('/' . ltrim($row['target_path'], '/')), (int) $row['status_code']);
    exit;
});

function botwriter_seo_redirects_list($limit = 100) {
    global $wpdb;
    $table = esc_sql(botwriter_seo_table('redirects'));
    $query = $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max(1, min(500, (int) $limit)));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    return $wpdb->get_results($query, ARRAY_A);
}
