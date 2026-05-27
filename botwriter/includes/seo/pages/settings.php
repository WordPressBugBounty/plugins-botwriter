<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function botwriter_seo_page_settings() {
    if (!empty($_POST['save_settings'])) {
        check_admin_referer('bw_seo_settings');
        $max_links = isset($_POST['max_links']) ? absint(wp_unslash($_POST['max_links'])) : 3;
        $serp_key = isset($_POST['serp_key']) ? sanitize_text_field(wp_unslash($_POST['serp_key'])) : '';

        $provider_labels = function_exists('botwriter_get_text_providers')
            ? botwriter_get_text_providers()
            : array(
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic',
                'google' => 'Google (Gemini)',
                'mistral' => 'Mistral AI',
                'groq' => 'Groq',
                'openrouter' => 'OpenRouter',
            );

        $bulk_provider = isset($_POST['bulk_ai_provider']) ? sanitize_key(wp_unslash($_POST['bulk_ai_provider'])) : '';
        if ($bulk_provider !== '' && !isset($provider_labels[$bulk_provider])) {
            $bulk_provider = '';
        }

        $bulk_model = isset($_POST['bulk_ai_model']) ? sanitize_text_field(wp_unslash($_POST['bulk_ai_model'])) : '';

        $opts = array(
            'botwriter_seo_auto_internal_links_enabled' => !empty($_POST['auto_links']) ? '1' : '0',
            'botwriter_seo_ai_internal_links_enabled' => !empty($_POST['ai_links']) ? '1' : '0',
            'botwriter_seo_auto_internal_links_max_links' => max(1, min(8, $max_links)),
            'botwriter_seo_embeddings_provider' => isset($_POST['emb_provider']) ? sanitize_key(wp_unslash($_POST['emb_provider'])) : 'openai',
            'botwriter_seo_serp_provider' => isset($_POST['serp_provider']) ? sanitize_key(wp_unslash($_POST['serp_provider'])) : 'serpapi',
            'botwriter_seo_serp_api_key' => $serp_key,
            // Optional overrides for Bulk Actions AI. Empty = inherit global text settings.
            'botwriter_seo_ai_provider' => $bulk_provider,
            'botwriter_seo_ai_model' => $bulk_model,
        );
        foreach ($opts as $k => $v) { update_option($k, $v); }
        echo '<div class="bw-notice success">' . esc_html__('Settings saved.', 'botwriter') . '</div>';
    }

    $max_links_value = (string) get_option('botwriter_seo_auto_internal_links_max_links', '3');
    $serp_api_key_value = (string) get_option('botwriter_seo_serp_api_key', '');

    $provider_labels = function_exists('botwriter_get_text_providers')
        ? botwriter_get_text_providers()
        : array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'google' => 'Google (Gemini)',
            'mistral' => 'Mistral AI',
            'groq' => 'Groq',
            'openrouter' => 'OpenRouter',
        );

    $bulk_provider_override = sanitize_key((string) get_option('botwriter_seo_ai_provider', ''));
    if ($bulk_provider_override !== '' && !isset($provider_labels[$bulk_provider_override])) {
        $bulk_provider_override = '';
    }

    $bulk_model_override = (string) get_option('botwriter_seo_ai_model', '');
    $global_provider = sanitize_key((string) get_option('botwriter_text_provider', 'openai'));
    if ($global_provider === '' || !isset($provider_labels[$global_provider])) {
        $global_provider = 'openai';
    }

    $global_model = function_exists('botwriter_get_current_text_model')
        ? (string) botwriter_get_current_text_model()
        : (string) get_option("botwriter_{$global_provider}_model", '');

    $effective_config = function_exists('botwriter_seo_ai_config') ? botwriter_seo_ai_config() : array(
        'provider' => $bulk_provider_override !== '' ? $bulk_provider_override : $global_provider,
        'model' => $bulk_model_override !== '' ? $bulk_model_override : $global_model,
    );

    $effective_provider = sanitize_key((string) ($effective_config['provider'] ?? 'openai'));
    $effective_model = (string) ($effective_config['model'] ?? '');

    echo '<form method="post">';
    wp_nonce_field('bw_seo_settings');

    echo '<div class="bw-seo-grid cols-2">';

    botwriter_seo_card_open(__('Internal links engine', 'botwriter'), 'admin-links');
    echo '<div class="bw-form-row"><label>' . esc_html__('Auto-insert on publish', 'botwriter') . '</label><input type="checkbox" name="auto_links" value="1"' . checked((string) get_option('botwriter_seo_auto_internal_links_enabled', '0'), '1', false) . ' /></div>';
    echo '<div class="bw-form-row"><label>' . esc_html__('Use AI suggestions', 'botwriter') . '</label><input type="checkbox" name="ai_links" value="1"' . checked((string) get_option('botwriter_seo_ai_internal_links_enabled', '0'), '1', false) . ' /></div>';
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

    botwriter_seo_card_open(__('Bulk AI provider/model', 'botwriter'), 'admin-generic');
    echo '<div class="bw-form-row"><label>' . esc_html__('Provider override', 'botwriter') . '</label>';
    echo '<select name="bulk_ai_provider">';
    echo '<option value=""' . selected($bulk_provider_override, '', false) . '>' . esc_html__('Inherit from global Text AI settings (recommended)', 'botwriter') . '</option>';
    foreach ($provider_labels as $provider_key => $provider_label) {
        echo '<option value="' . esc_attr($provider_key) . '"' . selected($bulk_provider_override, $provider_key, false) . '>' . esc_html($provider_label) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="bw-form-row"><label>' . esc_html__('Model override', 'botwriter') . '</label>';
    echo '<input type="text" name="bulk_ai_model" value="' . esc_attr($bulk_model_override) . '" placeholder="' . esc_attr__('Leave empty to inherit global model', 'botwriter') . '" /></div>';

    echo '<p class="description">' . esc_html(sprintf(
        /* translators: 1: provider, 2: model */
        __('Global Text AI is currently: %1$s / %2$s', 'botwriter'),
        (string) ($provider_labels[$global_provider] ?? $global_provider),
        $global_model !== '' ? $global_model : __('(auto)', 'botwriter')
    )) . '</p>';

    echo '<p class="description">' . esc_html(sprintf(
        /* translators: 1: effective provider, 2: effective model */
        __('Bulk actions will use: %1$s / %2$s', 'botwriter'),
        (string) ($provider_labels[$effective_provider] ?? $effective_provider),
        $effective_model !== '' ? $effective_model : __('(auto)', 'botwriter')
    )) . '</p>';
    botwriter_seo_card_close();

    echo '</div>';

    echo '<p class="bw-mt-16"><button class="bw-button primary" name="save_settings" value="1"><span class="dashicons dashicons-saved"></span> ' . esc_html__('Save all settings', 'botwriter') . '</button></p>';
    echo '</form>';
}
