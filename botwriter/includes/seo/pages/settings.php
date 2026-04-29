<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_page_settings() {
    if (!empty($_POST['save_settings'])) {
        check_admin_referer('bw_seo_settings');
        $max_links = isset($_POST['max_links']) ? absint(wp_unslash($_POST['max_links'])) : 3;
        $serp_key = isset($_POST['serp_key']) ? sanitize_text_field(wp_unslash($_POST['serp_key'])) : '';
        $opts = array(
            'botwriter_seo_auto_internal_links_enabled' => !empty($_POST['auto_links']) ? '1' : '0',
            'botwriter_seo_ai_internal_links_enabled' => !empty($_POST['ai_links']) ? '1' : '0',
            'botwriter_seo_auto_internal_links_max_links' => max(1, min(8, $max_links)),
            'botwriter_seo_embeddings_provider' => isset($_POST['emb_provider']) ? sanitize_key(wp_unslash($_POST['emb_provider'])) : 'openai',
            'botwriter_seo_serp_provider' => isset($_POST['serp_provider']) ? sanitize_key(wp_unslash($_POST['serp_provider'])) : 'serpapi',
            'botwriter_seo_serp_api_key' => $serp_key,
        );
        foreach ($opts as $k => $v) { update_option($k, $v); }
        echo '<div class="bw-notice success">' . esc_html__('Settings saved.', 'botwriter') . '</div>';
    }

    $max_links_value = (string) get_option('botwriter_seo_auto_internal_links_max_links', '3');
    $serp_api_key_value = (string) get_option('botwriter_seo_serp_api_key', '');

    echo '<form method="post">';
    wp_nonce_field('bw_seo_settings');

    echo '<div class="bw-seo-grid cols-2">';

    botwriter_seo_card_open(__('Internal links engine', 'botwriter'), 'admin-links');
    echo '<div class="bw-form-row"><label>' . esc_html__('Auto-insert on publish', 'botwriter') . '</label><input type="checkbox" name="auto_links" value="1"' . checked((string) get_option('botwriter_seo_auto_internal_links_enabled', '1'), '1', false) . ' /></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Use AI suggestions', 'botwriter') . '</label><input type="checkbox" name="ai_links" value="1"' . checked((string) get_option('botwriter_seo_ai_internal_links_enabled', '1'), '1', false) . ' /></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Max links / article', 'botwriter') . '</label><input type="number" name="max_links" min="1" max="8" value="' . esc_attr($max_links_value) . '" /></div>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('Embeddings (for bulk semantic index)', 'botwriter'), 'networking');
    echo '<div class="bw-form-row"><label>' . esc_html__('Provider', 'botwriter') . '</label>';
    $p = (string) get_option('botwriter_seo_embeddings_provider', 'openai');
    echo '<select name="emb_provider"><option value="openai"' . selected($p, 'openai', false) . '>OpenAI text-embedding-3-small</option><option value="fallback"' . selected($p, 'fallback', false) . '>Hash fallback (256d)</option></select></div>';
    botwriter_seo_card_close();

    botwriter_seo_card_open(__('SERP', 'botwriter'), 'visibility');
    $sp = (string) get_option('botwriter_seo_serp_provider', 'serpapi');
    echo '<div class="bw-form-row"><label>' . esc_html__('Provider', 'botwriter') . '</label>';
    echo '<select name="serp_provider"><option value="serpapi"' . selected($sp, 'serpapi', false) . '>SerpAPI</option></select></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('API key', 'botwriter') . '</label><input type="password" name="serp_key" value="' . esc_attr($serp_api_key_value) . '" /></div>';
    botwriter_seo_card_close();

    echo '</div>';

    echo '<p class="bw-mt-16"><button class="bw-button primary" name="save_settings" value="1"><span class="dashicons dashicons-saved"></span> ' . esc_html__('Save all settings', 'botwriter') . '</button></p>';
    echo '</form>';
}
