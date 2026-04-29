<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_render_job_card($job, $title, $description, $args = array(), $icon = 'analytics') {
    $state = botwriter_seo_job_state($job);
    $pct = $state['total'] > 0 ? round(($state['done'] / $state['total']) * 100) : 0;
    $running = in_array($state['status'], array('running', 'pending'), true);
    $btn_label = $running
        ? __('Running…', 'botwriter')
        : __('Configure & Run', 'botwriter');
    botwriter_seo_card_open($title, $icon);
    echo '<p>' . esc_html($description) . '</p>';
    echo '<div class="bw-job" data-job="' . esc_attr($job) . '">';
    echo '<div class="bw-job-label">' . esc_html(ucfirst($state['status'])) . '</div>';
    echo '<div class="bw-progress"><div data-progress="' . esc_attr((string) $pct) . '"></div></div>';
    echo '<div class="stats"><span class="bw-job-stats">' . (int) $state['done'] . ' / ' . (int) $state['total'] . ' (' . esc_html((string) $pct) . '%)</span><span class="bw-job-message">' . esc_html($state['message'] ?? '') . '</span></div>';
    echo '<div class="bw-job-feed-wrap"' . ($running ? '' : ' hidden') . '>';
    echo '<div class="bw-job-feed-title"><span class="dashicons dashicons-list-view"></span> ' . esc_html__('Live results', 'botwriter') . '</div>';
    echo '<ul class="bw-job-feed" id="bw-job-feed-' . esc_attr($job) . '"></ul>';
    echo '</div>';
    echo '</div>';
    echo '<div class="bw-card-actions">';
    echo '<button class="bw-button primary bw-job-configure" data-job="' . esc_attr($job) . '" data-args=\'' . esc_attr(wp_json_encode($args)) . '\' data-mode="analysis"' . ($running ? ' disabled' : '') . '>';
    echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html($btn_label);
    echo '</button>';
    echo '</div>';
    botwriter_seo_card_close();
}

function botwriter_seo_page_bulk_analysis() {
    botwriter_seo_render_job_card(
        'analysis',
        __('Recompute SEO scores', 'botwriter'),
        __('Walks every published post, runs all SEO checks and stores a fresh score. Safe to re-run; only updates posts whose content changed.', 'botwriter'),
        array(),
        'chart-area'
    );

    botwriter_seo_card_open(__('All analyses', 'botwriter'), 'list-view');
    ?>
    <div class="bw-analyses" id="bw-analyses">
        <div class="bw-analyses-toolbar">
            <div class="bw-analyses-search">
                <span class="dashicons dashicons-search"></span>
                <input type="search" class="bw-analyses-q" placeholder="<?php esc_attr_e('Search by title…', 'botwriter'); ?>" />
            </div>
            <div class="bw-analyses-filter">
                <label><?php esc_html_e('Grade:', 'botwriter'); ?></label>
                <select class="bw-analyses-grade">
                    <option value=""><?php esc_html_e('All', 'botwriter'); ?></option>
                    <option value="excellent"><?php esc_html_e('Excellent (85+)', 'botwriter'); ?></option>
                    <option value="good"><?php esc_html_e('Good (70–84)', 'botwriter'); ?></option>
                    <option value="fair"><?php esc_html_e('Needs work (50–69)', 'botwriter'); ?></option>
                    <option value="poor"><?php esc_html_e('Poor (<50)', 'botwriter'); ?></option>
                </select>
            </div>
            <div class="bw-analyses-pp">
                <label><?php esc_html_e('Per page:', 'botwriter'); ?></label>
                <select class="bw-analyses-perpage">
                    <option>10</option><option selected>25</option><option>50</option><option>100</option>
                </select>
            </div>
        </div>
        <div class="bw-analyses-table-wrap">
            <table class="bw-table bw-analyses-table">
                <thead>
                    <tr>
                        <th class="sortable" data-orderby="title"><?php esc_html_e('Post', 'botwriter'); ?> <span class="bw-sort-ind"></span></th>
                        <th class="sortable" data-orderby="score"><?php esc_html_e('Score', 'botwriter'); ?> <span class="bw-sort-ind"></span></th>
                        <th class="sortable" data-orderby="readability"><?php esc_html_e('Readability', 'botwriter'); ?> <span class="bw-sort-ind"></span></th>
                        <th class="sortable" data-orderby="date"><?php esc_html_e('Date', 'botwriter'); ?> <span class="bw-sort-ind"></span></th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="5"><div class="bw-empty"><span class="bw-spinner"></span><p><?php esc_html_e('Loading…', 'botwriter'); ?></p></div></td></tr></tbody>
            </table>
        </div>
        <div class="bw-analyses-footer">
            <div class="bw-analyses-info"></div>
            <div class="bw-analyses-pager"></div>
        </div>
    </div>
    <?php
    botwriter_seo_card_close();

    // Modal container (rendered once).
    echo '<div class="bw-modal-overlay" id="bw-seo-modal" hidden>';
    echo '  <div class="bw-modal" role="dialog" aria-modal="true" aria-labelledby="bw-seo-modal-title">';
    echo '    <button type="button" class="bw-modal-close" aria-label="' . esc_attr__('Close', 'botwriter') . '"><span class="dashicons dashicons-no-alt"></span></button>';
    echo '    <div class="bw-modal-body"><div class="bw-empty"><span class="bw-spinner"></span><p>' . esc_html__('Loading report…', 'botwriter') . '</p></div></div>';
    echo '  </div>';
    echo '</div>';
}

/**
 * AJAX: paginated/sortable list of all SEO analyses.
 */
add_action('wp_ajax_bw_seo_analyses_list', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }

    global $wpdb;
    $page    = isset($_POST['page']) ? max(1, absint(wp_unslash($_POST['page']))) : 1;
    $per     = isset($_POST['per_page']) ? max(5, min(100, absint(wp_unslash($_POST['per_page'])))) : 25;
    $orderby = isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'score';
    $order   = isset($_POST['order']) && strtoupper(sanitize_key(wp_unslash($_POST['order']))) === 'DESC' ? 'DESC' : 'ASC';
    $q       = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    $grade   = isset($_POST['grade']) ? sanitize_key(wp_unslash($_POST['grade'])) : '';

    $orderby_sql = 'CAST(pm.meta_value AS UNSIGNED) ' . $order;
    if ($orderby === 'title') { $orderby_sql = 'p.post_title ' . $order; }
    elseif ($orderby === 'date') { $orderby_sql = 'p.post_date ' . $order; }
    elseif ($orderby === 'readability') { $orderby_sql = 'CAST(COALESCE(rm.meta_value,0) AS UNSIGNED) ' . $order; }

    $where = "pm.meta_key='_botwriter_seo_score' AND p.post_status='publish'";
    $params = array();
    if ($q !== '') {
        $where .= ' AND p.post_title LIKE %s';
        $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    $grade_map = array(
        'excellent' => array(85, 100),
        'good'      => array(70, 84),
        'fair'      => array(50, 69),
        'poor'      => array(0, 49),
    );
    if (isset($grade_map[$grade])) {
        $where .= ' AND CAST(pm.meta_value AS UNSIGNED) BETWEEN %d AND %d';
        $params[] = $grade_map[$grade][0];
        $params[] = $grade_map[$grade][1];
    }

    $sql_count = "SELECT COUNT(*) FROM {$wpdb->posts} p
                   JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id
                   WHERE {$where}";
    if ($params) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a prepared SQL string built from trusted table names and sanitized clauses.
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses trusted table names and sanitized clauses.
        $total = (int) $wpdb->get_var($sql_count);
    }

    $offset = ($page - 1) * $per;
    $sql = "SELECT p.ID, p.post_title, p.post_date,
                   CAST(pm.meta_value AS UNSIGNED) AS score,
                   CAST(COALESCE(rm.meta_value,0) AS UNSIGNED) AS readability
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id
            LEFT JOIN {$wpdb->postmeta} rm ON rm.post_id=p.ID AND rm.meta_key='_botwriter_seo_readability_score'
            WHERE {$where}
            ORDER BY {$orderby_sql}
            LIMIT %d OFFSET %d";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a prepared SQL string built from trusted table names and sanitized clauses.
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, array($per, $offset))), ARRAY_A);

    $items = array();
    foreach ((array) $rows as $r) {
        $score = (int) $r['score'];
        $grade_v = ($score >= 85) ? 'excellent' : (($score >= 70) ? 'good' : (($score >= 50) ? 'fair' : 'poor'));
        $rscore = (int) $r['readability'];
        $rgrade = ($rscore >= 85) ? 'excellent' : (($rscore >= 70) ? 'good' : (($rscore >= 50) ? 'fair' : 'poor'));
        $items[] = array(
            'id'          => (int) $r['ID'],
            'title'       => (string) $r['post_title'],
            'score'       => $score,
            'grade'       => $grade_v,
            'readability' => $rscore,
            'r_grade'     => $rgrade,
            'date'        => mysql2date(get_option('date_format'), $r['post_date']),
            'edit_url'    => get_edit_post_link((int) $r['ID'], 'raw'),
            'view_url'    => get_permalink((int) $r['ID']),
        );
    }

    wp_send_json_success(array(
        'items' => $items,
        'total' => $total,
        'page'  => $page,
        'per'   => $per,
        'pages' => max(1, (int) ceil($total / $per)),
    ));
});
