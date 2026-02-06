<?php
/**
 * OpenRouter Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get OpenRouter provider info
 */
function botwriter_get_openrouter_info() {
    return [
        'name' => 'OpenRouter',
        'description' => __('Unified API for 200+ AI models from all providers. One key, all models!', 'botwriter'),
        'website' => 'https://openrouter.ai',
        'api_url' => 'https://openrouter.ai/keys',
        'docs_url' => 'https://openrouter.ai/docs/quick-start',
        'pricing_url' => 'https://openrouter.ai/models',
        'free_credits' => __('Some models are 100% FREE! Also offers free credits for new users.', 'botwriter'),
        'pricing_summary' => [
            'claude-sonnet-4' => '~$3/1M input, $15/1M output',
            'gpt-4o' => '~$2.5/1M input, $10/1M output',
            'llama-3.3-70b' => '~$0.40/1M input, $0.40/1M output',
            'FREE models' => '$0 (with limits)',
        ],
        'features' => [
            __('🌐 200+ models from ALL providers in one API', 'botwriter'),
            __('FREE models available!', 'botwriter'),
            __('Automatic fallbacks if a model is down', 'botwriter'),
            __('Pay only for what you use', 'botwriter'),
            __('Compare models side-by-side', 'botwriter'),
            __('No provider lock-in', 'botwriter'),
            __('Model routing & load balancing', 'botwriter'),
        ],
        'pros' => [
            __('One API for everything', 'botwriter'),
            __('Free models available', 'botwriter'),
            __('Easy to switch models', 'botwriter'),
            __('Great model comparison', 'botwriter'),
        ],
        'cons' => [
            __('Slight price markup over direct', 'botwriter'),
            __('Additional latency layer', 'botwriter'),
        ],
    ];
}

/**
 * Render OpenRouter settings tab content
 */
function botwriter_render_openrouter_settings($settings, $is_active) {
    $info = botwriter_get_openrouter_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_openrouter_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_openrouter_api_key'))); ?>"
                           placeholder="sk-or-...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="openrouter">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your OpenRouter API key starts with "sk-or-"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('openrouter', 'botwriter_openrouter_model', $settings['botwriter_openrouter_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="openrouter">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Browse 200+ models at', 'botwriter'); ?> <a href="https://openrouter.ai/models" target="_blank">openrouter.ai/models</a></p>
            </div>
        </div>

        <div class="provider-info-card highlight-unified">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <div class="unified-banner">
                <span class="dashicons dashicons-networking"></span>
                <strong><?php esc_html_e('🌐 One API Key = Access to 200+ AI Models!', 'botwriter'); ?></strong>
            </div>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://openrouter.ai/" target="_blank">openrouter.ai</a> <?php esc_html_e('and sign up (Google/GitHub/Email)', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://openrouter.ai/keys" target="_blank"><?php esc_html_e('API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key (starts with "sk-or-") and paste it above', 'botwriter'); ?></li>
                <li><?php esc_html_e('Optionally add credits at', 'botwriter'); ?> <a href="https://openrouter.ai/credits" target="_blank"><?php esc_html_e('Credits page', 'botwriter'); ?></a></li>
            </ol>
            <div class="info-tip">
                <span class="dashicons dashicons-awards"></span>
                <p><?php esc_html_e('Some models are completely FREE! Look for models with ":free" suffix.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight">
                <span class="free-credits-badge"><?php esc_html_e('Free Models Available', 'botwriter'); ?></span>
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
                <strong><?php esc_html_e('Pro Tip:', 'botwriter'); ?></strong>
                <?php esc_html_e('OpenRouter shows real-time pricing and availability for all models!', 'botwriter'); ?>
            </div>

            <p class="pricing-note">
                <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-external"></span> <?php esc_html_e('Browse All Models & Pricing', 'botwriter'); ?>
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
                    <span class="dashicons dashicons-editor-ul"></span>
                    <?php esc_html_e('All Models', 'botwriter'); ?>
                </a>
                <a href="https://openrouter.ai/rankings" target="_blank" class="link-item">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e('Rankings', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
