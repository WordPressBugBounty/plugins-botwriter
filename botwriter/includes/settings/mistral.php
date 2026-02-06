<?php
/**
 * Mistral AI Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Mistral provider info
 */
function botwriter_get_mistral_info() {
    return [
        'name' => 'Mistral AI',
        'description' => __('European AI company with excellent open-weight models. Great balance of performance and cost.', 'botwriter'),
        'website' => 'https://mistral.ai',
        'api_url' => 'https://console.mistral.ai/api-keys',
        'docs_url' => 'https://docs.mistral.ai/',
        'pricing_url' => 'https://mistral.ai/technology/',
        'free_credits' => __('Free "Experiment" tier available with limited usage', 'botwriter'),
        'pricing_summary' => [
            'mistral-large' => '$2/1M input, $6/1M output',
            'mistral-medium' => '$0.40/1M input, $1.50/1M output',
            'mistral-small' => '$0.20/1M input, $0.60/1M output',
            'codestral' => '$0.30/1M input, $0.90/1M output',
            'ministral-8b' => '$0.10/1M input, $0.10/1M output',
        ],
        'features' => [
            __('European company (GDPR-compliant)', 'botwriter'),
            __('Open-weight models available', 'botwriter'),
            __('Excellent multilingual support', 'botwriter'),
            __('Competitive pricing', 'botwriter'),
            __('Codestral: specialized for code generation', 'botwriter'),
            __('Ministral: ultra-compact edge models', 'botwriter'),
        ],
        'pros' => [
            __('Great price/performance ratio', 'botwriter'),
            __('Strong European data protection', 'botwriter'),
            __('Open-source options', 'botwriter'),
            __('Fast inference', 'botwriter'),
        ],
        'cons' => [
            __('Smaller ecosystem than OpenAI', 'botwriter'),
            __('No image generation', 'botwriter'),
        ],
    ];
}

/**
 * Render Mistral settings tab content
 */
function botwriter_render_mistral_settings($settings, $is_active) {
    $info = botwriter_get_mistral_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_mistral_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_mistral_api_key'))); ?>"
                           placeholder="...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="mistral">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Get your API key from the Mistral AI Console', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('mistral', 'botwriter_mistral_model', $settings['botwriter_mistral_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="mistral">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://console.mistral.ai/" target="_blank">console.mistral.ai</a> <?php esc_html_e('and create an account', 'botwriter'); ?></li>
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://admin.mistral.ai/organization/billing" target="_blank"><?php esc_html_e('Billing', 'botwriter'); ?></a> <?php esc_html_e('to select your plan (Experiment or Scale)', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://console.mistral.ai/api-keys" target="_blank"><?php esc_html_e('API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create new key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy and paste the key above', 'botwriter'); ?></li>
            </ol>
            <div class="info-tip">
                <span class="dashicons dashicons-flag"></span>
                <p><?php esc_html_e('Mistral AI is based in Paris, France. Great choice for EU data compliance!', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight">
                <span class="free-credits-badge"><?php esc_html_e('Experiment Plan', 'botwriter'); ?></span>
                <p><?php echo esc_html($info['free_credits']); ?></p>
            </div>

            <table class="pricing-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Model', 'botwriter'); ?></th>
                        <th><?php esc_html_e('Cost (per 1M tokens)', 'botwriter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($info['pricing_summary'] as $model => $price): ?>
                    <tr>
                        <td><code><?php echo esc_html($model); ?></code></td>
                        <td><?php echo esc_html($price); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cost-saving-tip">
                <span class="dashicons dashicons-lightbulb"></span>
                <strong><?php esc_html_e('Best Value:', 'botwriter'); ?></strong>
                <?php esc_html_e('Mistral Medium offers 8x lower cost than Large with great quality!', 'botwriter'); ?>
            </div>

            <p class="pricing-note">
                <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-external"></span> <?php esc_html_e('View Full Pricing', 'botwriter'); ?>
                </a>
            </p>
        </div>

        <div class="provider-features-card">
            <h4><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Features & Benefits', 'botwriter'); ?></h4>
            <ul class="features-list">
                <?php foreach ($info['features'] as $feature): ?>
                <li><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <div class="pros-cons">
                <div class="pros">
                    <h5><?php esc_html_e('Pros', 'botwriter'); ?></h5>
                    <ul>
                        <?php foreach ($info['pros'] as $pro): ?>
                        <li><span class="dashicons dashicons-plus-alt2"></span> <?php echo esc_html($pro); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="cons">
                    <h5><?php esc_html_e('Cons', 'botwriter'); ?></h5>
                    <ul>
                        <?php foreach ($info['cons'] as $con): ?>
                        <li><span class="dashicons dashicons-minus"></span> <?php echo esc_html($con); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="provider-links-card">
            <h4><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Useful Links', 'botwriter'); ?></h4>
            <div class="links-grid">
                <a href="<?php echo esc_url($info['api_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php esc_html_e('Get API Key', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['docs_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-book"></span>
                    <?php esc_html_e('Documentation', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e('Pricing', 'botwriter'); ?>
                </a>
                <a href="https://discord.gg/mistralai" target="_blank" class="link-item">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php esc_html_e('Discord Community', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
