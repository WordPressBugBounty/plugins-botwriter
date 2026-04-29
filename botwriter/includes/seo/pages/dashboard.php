<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_page_dashboard() {
    if (!empty($_POST['bw_toggle_seo_module']) && !empty($_POST['_bw_toggle_seo_nonce'])) {
        if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_bw_toggle_seo_nonce'])), 'bw_toggle_seo_module')) {
            $enabled = !empty($_POST['botwriter_seo_module_enabled']) ? '1' : '0';
            update_option('botwriter_seo_module_enabled', $enabled);

            echo '<div class="bw-notice success">' . esc_html__('SEO Module setting saved.', 'botwriter') . '</div>';

            if ($enabled !== '1') {
                echo '<div class="bw-notice info">' . esc_html__('SEO module loading is now disabled. The SEO menu will disappear on the next page load. You can re-enable it from BotWriter Settings > General Settings > SEO Module.', 'botwriter') . '</div>';
            }
        } else {
            echo '<div class="bw-notice danger">' . esc_html__('Security check failed. Please try again.', 'botwriter') . '</div>';
        }
    }

    $seo_module_enabled = (string) get_option('botwriter_seo_module_enabled', '1');

    global $wpdb;
    $audit = botwriter_seo_audit_summary();
    $embeddings_table = esc_sql(botwriter_seo_table('embeddings'));
    $total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page')");
    $avg_score = (int) $wpdb->get_var("SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key='_botwriter_seo_score'");
    $low_score = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_botwriter_seo_score' AND CAST(meta_value AS UNSIGNED) < 70");
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for use as an identifier.
    $emb_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$embeddings_table}");
    $events = botwriter_seo_recent_events(15);

    echo '<div class="bw-seo-grid cols-4 bw-grid-mb-18">';
    botwriter_seo_kpi(__('Avg. SEO score', 'botwriter'), $avg_score ?: '—', 'chart-area', 'primary');
    botwriter_seo_kpi(__('Posts indexed', 'botwriter'), $total_posts, 'admin-post', 'info');
    botwriter_seo_kpi(__('Low-score posts', 'botwriter'), $low_score, 'warning', 'warn');
    botwriter_seo_kpi(__('Internal links', 'botwriter'), (int) $audit['total_internal_links'], 'admin-links', 'success');
    echo '</div>';

    echo '<div class="bw-seo-grid cols-3">';
    botwriter_seo_card_open(__('Site health', 'botwriter'), 'shield');
    echo '<ul class="bw-checks">';
    /* translators: %d: number of orphan posts */
    echo '<li><span class="icon ' . ($audit['orphans'] ? 'fail' : 'ok') . '">' . ($audit['orphans'] ? '!' : '✓') . '</span><div>' . sprintf(esc_html__('Orphan posts: %d', 'botwriter'), count($audit['orphans'])) . '</div></li>';
    /* translators: %d: number of dead-end posts */
    echo '<li><span class="icon ' . ($audit['deadends'] ? 'fail' : 'ok') . '">' . ($audit['deadends'] ? '!' : '✓') . '</span><div>' . sprintf(esc_html__('Dead-end posts: %d', 'botwriter'), count($audit['deadends'])) . '</div></li>';
    /* translators: 1: indexed embeddings count, 2: total publishable posts */
    echo '<li><span class="icon ' . ($emb_count >= $total_posts ? 'ok' : 'fail') . '">' . ($emb_count >= $total_posts ? '✓' : '!') . '</span><div>' . esc_html(sprintf(__('Embeddings indexed: %1$d / %2$d', 'botwriter'), $emb_count, $total_posts)) . '</div></li>';
    echo '</ul>';
    echo '<div class="bw-card-actions">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_bulk_analysis')) . '" class="bw-button primary">' . esc_html__('Run analysis', 'botwriter') . '</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_internal_links')) . '" class="bw-button">' . esc_html__('Audit Internal Links', 'botwriter') . '</a>';
    echo '</div>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Recent events', 'botwriter'), 'admin-comments');
    if (empty($events)) {
        echo '<div class="bw-empty"><span class="dashicons dashicons-buddicons-activity"></span><p>' . esc_html__('No events yet — run the watchdog or publish a post.', 'botwriter') . '</p></div>';
    } else {
        foreach ($events as $e) {
            $sev = in_array($e['severity'], array('warn', 'danger', 'info', 'success'), true) ? $e['severity'] : 'info';
            echo '<div class="bw-event"><span class="dot ' . esc_attr($sev) . '"></span><div><div class="body">' . esc_html($e['event_type']) . ($e['post_id'] ? ' — <a href="' . esc_url(get_edit_post_link((int) $e['post_id'])) . '">#' . (int) $e['post_id'] . '</a>' : '') . '</div><div class="meta">' . esc_html(date_i18n('Y-m-d H:i', (int) $e['created_at'])) . '</div></div></div>';
        }
    }
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Quick actions', 'botwriter'), 'admin-tools');
    echo '<div class="bw-card-actions bw-card-actions-stack">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_bulk_actions')) . '" class="bw-button"><span class="dashicons dashicons-update"></span> ' . esc_html__('Regenerate SEO meta in bulk', 'botwriter') . '</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_internal_links')) . '" class="bw-button"><span class="dashicons dashicons-admin-links"></span> ' . esc_html__('Audit Internal Links', 'botwriter') . '</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_llmstxt')) . '" class="bw-button"><span class="dashicons dashicons-format-aside"></span> ' . esc_html__('Open llms.txt', 'botwriter') . '</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_media_alt')) . '" class="bw-button"><span class="dashicons dashicons-format-image"></span> ' . esc_html__('Generate Media ALT', 'botwriter') . '</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=botwriter_seo_settings')) . '" class="bw-button"><span class="dashicons dashicons-admin-generic"></span> ' . esc_html__('Settings', 'botwriter') . '</a>';
    echo '</div>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('SEO Module', 'botwriter'), 'admin-settings');
    echo '<form method="post">';
    wp_nonce_field('bw_toggle_seo_module', '_bw_toggle_seo_nonce');
    echo '<div class="bw-form-row">';
    echo '<label><input type="checkbox" name="botwriter_seo_module_enabled" value="1"' . checked($seo_module_enabled, '1', false) . ' /> ' . esc_html__('Enable SEO Module', 'botwriter') . '</label>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Disabling this turns off loading of the whole SEO module on next requests. Re-enable later from BotWriter Settings > General Settings > SEO Module.', 'botwriter') . '</p>';
    echo '<div class="bw-card-actions"><button class="bw-button" name="bw_toggle_seo_module" value="1"><span class="dashicons dashicons-saved"></span> ' . esc_html__('Save', 'botwriter') . '</button></div>';
    echo '</form>';
    botwriter_seo_card_close();

    echo '</div>';

}
