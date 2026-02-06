<?php
/**
 * OpenAI Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get OpenAI provider info
 */
function botwriter_get_openai_info() {
    return [
        'name' => 'OpenAI',
        'description' => __('The creators of ChatGPT. Industry leader in AI with the most advanced models for text generation.', 'botwriter'),
        'website' => 'https://openai.com',
        'api_url' => 'https://platform.openai.com/api-keys',
        'docs_url' => 'https://platform.openai.com/docs',
        'pricing_url' => 'https://openai.com/api/pricing/',
        'free_credits' => __('$5 free credits for new accounts (limited time offers may vary)', 'botwriter'),
        'pricing_summary' => [
            'gpt-5-mini' => '$0.25/1M input, $2.00/1M output',
            'gpt-5-nano' => '$0.05/1M input, $0.40/1M output',
            'gpt-4o' => '~$5/1M input, $15/1M output',
            'gpt-4o-mini' => '~$0.15/1M input, $0.60/1M output',
        ],
        'features' => [
            __('Industry-leading models for complex tasks', 'botwriter'),
            __('Excellent for coding and creative writing', 'botwriter'),
            __('Image generation with DALL-E (same API key)', 'botwriter'),
            __('Extensive documentation and community', 'botwriter'),
        ],
        'pros' => [
            __('Best overall quality', 'botwriter'),
            __('Most features and tools', 'botwriter'),
            __('Great documentation', 'botwriter'),
        ],
        'cons' => [
            __('Higher cost than alternatives', 'botwriter'),
            __('Rate limits on free tier', 'botwriter'),
        ],
    ];
}

/**
 * Render OpenAI settings tab content
 */
function botwriter_render_openai_settings($settings, $is_active) {
    $info = botwriter_get_openai_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_openai_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_openai_api_key'))); ?>"
                           placeholder="sk-...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="openai">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your OpenAI API key starts with "sk-"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('openai', 'botwriter_openai_model', $settings['botwriter_openai_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="openai">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://platform.openai.com/signup" target="_blank">platform.openai.com</a> <?php esc_html_e('and create an account', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://platform.openai.com/api-keys" target="_blank"><?php esc_html_e('API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create new secret key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key (it starts with "sk-") and paste it above', 'botwriter'); ?></li>
                <li><?php esc_html_e('Add billing information in', 'botwriter'); ?> <a href="https://platform.openai.com/account/billing" target="_blank"><?php esc_html_e('Billing settings', 'botwriter'); ?></a></li>
            </ol>
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
                <a href="https://status.openai.com/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('API Status', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
