<?php
/**
 * Anthropic (Claude) Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Anthropic provider info
 */
function botwriter_get_anthropic_info() {
    return [
        'name' => 'Anthropic (Claude)',
        'description' => __('Creator of Claude, known for being helpful, harmless, and honest. Excellent for long-form content and nuanced writing.', 'botwriter'),
        'website' => 'https://anthropic.com',
        'api_url' => 'https://console.anthropic.com/settings/keys',
        'docs_url' => 'https://docs.anthropic.com/',
        'pricing_url' => 'https://anthropic.com/pricing#api',
        'free_credits' => __('$5 free credits for new API accounts', 'botwriter'),
        'pricing_summary' => [
            'claude-opus-4.5' => '$5/1M input, $25/1M output',
            'claude-sonnet-4.5' => '$3/1M input, $15/1M output',
            'claude-haiku-4.5' => '$1/1M input, $5/1M output',
        ],
        'features' => [
            __('200K context window (can read entire books)', 'botwriter'),
            __('Excellent at following complex instructions', 'botwriter'),
            __('Superior for long-form, nuanced content', 'botwriter'),
            __('Strong safety and ethical guidelines', 'botwriter'),
            __('Great at coding and technical writing', 'botwriter'),
        ],
        'pros' => [
            __('Best for long-form content', 'botwriter'),
            __('Very nuanced responses', 'botwriter'),
            __('Excellent instruction following', 'botwriter'),
            __('Prompt caching (save up to 90%)', 'botwriter'),
        ],
        'cons' => [
            __('Can be overly cautious sometimes', 'botwriter'),
            __('No image generation (text only)', 'botwriter'),
        ],
    ];
}

/**
 * Render Anthropic settings tab content
 */
function botwriter_render_anthropic_settings($settings, $is_active) {
    $info = botwriter_get_anthropic_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_anthropic_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_anthropic_api_key'))); ?>"
                           placeholder="sk-ant-...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="anthropic">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your Anthropic API key starts with "sk-ant-"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('anthropic', 'botwriter_anthropic_model', $settings['botwriter_anthropic_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="anthropic">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://console.anthropic.com/login" target="_blank">console.anthropic.com</a> <?php esc_html_e('and create an account', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://console.anthropic.com/settings/keys" target="_blank"><?php esc_html_e('API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key (it starts with "sk-ant-") and paste it above', 'botwriter'); ?></li>
                <li><?php esc_html_e('Add billing information in', 'botwriter'); ?> <a href="https://console.anthropic.com/settings/billing" target="_blank"><?php esc_html_e('Billing settings', 'botwriter'); ?></a></li>
            </ol>
            <div class="info-tip">
                <span class="dashicons dashicons-lightbulb"></span>
                <p><?php esc_html_e('Tip: New accounts receive $5 in free API credits to get started!', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight">
                <span class="free-credits-badge"><?php esc_html_e('Free Credits', 'botwriter'); ?></span>
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
                <span class="dashicons dashicons-saved"></span>
                <strong><?php esc_html_e('Save 50%:', 'botwriter'); ?></strong>
                <?php esc_html_e('Use Batch Processing for non-urgent tasks with 24-hour turnaround.', 'botwriter'); ?>
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
                <a href="https://status.anthropic.com/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('API Status', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
