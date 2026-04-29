<?php
/**
 * BotWriter SEO — custom DB tables + version migration.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('BOTWRITER_SEO_DB_VERSION', '1.1.0');

function botwriter_seo_table($name) {
    global $wpdb;
    return $wpdb->prefix . 'botwriter_seo_' . $name;
}

function botwriter_seo_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $emb = botwriter_seo_table('embeddings');
    $red = botwriter_seo_table('redirects');
    $abt = botwriter_seo_table('ab_titles');
    $evt = botwriter_seo_table('events');
    $rfq = botwriter_seo_table('refresh_queue');
    $gsc = botwriter_seo_table('gsc_daily');
    $bur = botwriter_seo_table('bulk_undo_runs');
    $bui = botwriter_seo_table('bulk_undo_items');

    $sql = array();

    $sql[] = "CREATE TABLE $emb (
        post_id BIGINT UNSIGNED NOT NULL,
        model VARCHAR(120) NOT NULL,
        dim INT UNSIGNED NOT NULL,
        vector LONGBLOB NOT NULL,
        hash CHAR(40) NOT NULL,
        updated_at INT UNSIGNED NOT NULL,
        PRIMARY KEY (post_id),
        KEY hash_idx (hash)
    ) $charset;";

    $sql[] = "CREATE TABLE $red (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_path VARCHAR(255) NOT NULL,
        target_path VARCHAR(255) NOT NULL,
        status_code SMALLINT NOT NULL DEFAULT 301,
        hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
        post_id BIGINT UNSIGNED DEFAULT NULL,
        created_at INT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_path (source_path)
    ) $charset;";

    $sql[] = "CREATE TABLE $abt (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        variant_a TEXT NOT NULL,
        variant_b TEXT NOT NULL,
        current_variant TINYINT NOT NULL DEFAULT 0,
        metrics_a TEXT,
        metrics_b TEXT,
        started_at INT UNSIGNED NOT NULL,
        ends_at INT UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        winner TINYINT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY status_idx (status)
    ) $charset;";

    $sql[] = "CREATE TABLE $evt (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(60) NOT NULL,
        post_id BIGINT UNSIGNED DEFAULT NULL,
        severity VARCHAR(20) NOT NULL DEFAULT 'info',
        payload TEXT,
        created_at INT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        KEY event_type (event_type),
        KEY post_id (post_id),
        KEY created_at (created_at)
    ) $charset;";

    $sql[] = "CREATE TABLE $rfq (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        reason VARCHAR(80) NOT NULL,
        priority TINYINT NOT NULL DEFAULT 5,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        scheduled_for INT UNSIGNED NOT NULL,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT,
        created_at INT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY status_scheduled (status, scheduled_for)
    ) $charset;";

    $sql[] = "CREATE TABLE $gsc (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        date DATE NOT NULL,
        query VARCHAR(255) DEFAULT NULL,
        clicks INT UNSIGNED NOT NULL DEFAULT 0,
        impressions INT UNSIGNED NOT NULL DEFAULT 0,
        ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
        position DECIMAL(6,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY post_date (post_id, date),
        KEY date_idx (date)
    ) $charset;";

    $sql[] = "CREATE TABLE $bur (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        job VARCHAR(40) NOT NULL,
        action_key VARCHAR(80) NOT NULL,
        undo_type VARCHAR(20) NOT NULL DEFAULT 'none',
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        args LONGTEXT NULL,
        target_items INT UNSIGNED NOT NULL DEFAULT 0,
        processed_items INT UNSIGNED NOT NULL DEFAULT 0,
        changed_items INT UNSIGNED NOT NULL DEFAULT 0,
        reverted_items INT UNSIGNED NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED DEFAULT NULL,
        created_at INT UNSIGNED NOT NULL,
        finished_at INT UNSIGNED NOT NULL DEFAULT 0,
        reverted_at INT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        PRIMARY KEY (id),
        KEY status_created (status, created_at),
        KEY action_created (action_key, created_at)
    ) $charset;";

    $sql[] = "CREATE TABLE $bui (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id BIGINT UNSIGNED NOT NULL,
        post_id BIGINT UNSIGNED NOT NULL,
        undo_type VARCHAR(20) NOT NULL DEFAULT 'none',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        snapshot LONGTEXT NOT NULL,
        created_at INT UNSIGNED NOT NULL,
        reverted_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY run_post (run_id, post_id),
        KEY run_status (run_id, status),
        KEY post_id (post_id)
    ) $charset;";

    foreach ($sql as $stmt) {
        dbDelta($stmt);
    }

    update_option('botwriter_seo_db_version', BOTWRITER_SEO_DB_VERSION);
}

function botwriter_seo_maybe_upgrade_db() {
    $current = get_option('botwriter_seo_db_version', '');
    if ($current !== BOTWRITER_SEO_DB_VERSION) {
        botwriter_seo_install_tables();
    }
}
add_action('admin_init', 'botwriter_seo_maybe_upgrade_db');

/**
 * Insert a structured event into the SEO events log.
 *
 * @param string   $type
 * @param array    $payload
 * @param int|null $post_id
 * @param string   $severity info|warning|critical
 */
function botwriter_seo_event($type, $payload = array(), $post_id = null, $severity = 'info') {
    global $wpdb;
    $wpdb->insert(botwriter_seo_table('events'), array(
        'event_type' => substr((string) $type, 0, 60),
        'post_id'    => $post_id ? intval($post_id) : null,
        'severity'   => in_array($severity, array('info', 'warning', 'critical'), true) ? $severity : 'info',
        'payload'    => wp_json_encode((array) $payload),
        'created_at' => time(),
    ));
}

function botwriter_seo_recent_events($limit = 20, $severity = '') {
    global $wpdb;
    $tbl = esc_sql(botwriter_seo_table('events'));
    $limit = max(1, min(500, intval($limit)));

    if ($severity !== '' && in_array($severity, array('info', 'warning', 'critical'), true)) {
        $query = $wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE severity = %s ORDER BY id DESC LIMIT %d",
            $severity,
            $limit
        );
    } else {
        $query = $wpdb->prepare("SELECT * FROM {$tbl} ORDER BY id DESC LIMIT %d", $limit);
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
    $rows = $wpdb->get_results($query, ARRAY_A);

    return is_array($rows) ? $rows : array();
}
