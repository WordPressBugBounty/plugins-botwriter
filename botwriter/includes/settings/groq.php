<?php
/**
 * Groq Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Groq provider info
 */
function botwriter_get_groq_info() {
    return [
        'name' => 'Groq',
        'description' => __('Ultra-fast LPU inference. The fastest AI API available - up to 10x faster than competitors!', 'botwriter'),
        'website' => 'https://groq.com',
        'api_url' => 'https://console.groq.com/keys',
        'docs_url' => 'https://console.groq.com/docs/quickstart',
        'pricing_url' => 'https://groq.com/pricing/',
        'free_credits' => __('FREE TIER: Generous free usage with rate limits!', 'botwriter'),
        'pricing_summary' => [
            'llama-3.3-70b' => '$0.59/1M input, $0.79/1M output',
            'llama-3.1-8b' => '$0.05/1M input, $0.08/1M output',
            'llama-4-scout' => '$0.11/1M input, $0.34/1M output',
            'mixtral-8x7b' => '$0.24/1M input, $0.24/1M output',
        ],
        'features' => [
            __('🚀 FASTEST API - up to 1000+ tokens/second!', 'botwriter'),
            __('Free tier with generous limits', 'botwriter'),
            __('Custom LPU (Language Processing Unit) hardware', 'botwriter'),
            __('Low latency - real-time applications', 'botwriter'),
            __('Open-source models (Llama, Mixtral)', 'botwriter'),
            __('Pay-as-you-go, no commitments', 'botwriter'),
        ],
        'pros' => [
            __('Incredibly fast - 10x faster', 'botwriter'),
            __('Very affordable pricing', 'botwriter'),
            __('Great free tier', 'botwriter'),
            __('No idle charges', 'botwriter'),
        ],
        'cons' => [
            __('Only runs open-source models', 'botwriter'),
            __('No image generation', 'botwriter'),
            __('Smaller model selection', 'botwriter'),
        ],
    ];
}

/**
 * Render Groq settings tab content
 */
function botwriter_render_groq_settings($settings, $is_active) {
    $info = botwriter_get_groq_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_groq_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_groq_api_key'))); ?>"
                           placeholder="gsk_...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="groq">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your Groq API key starts with "gsk_"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('groq', 'botwriter_groq_model', $settings['botwriter_groq_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="groq">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
            </div>
        </div>

        <div class="provider-info-card highlight-speed">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <div class="speed-banner">
                <span class="dashicons dashicons-performance"></span>
                <strong><?php esc_html_e('⚡ The FASTEST AI API on the planet!', 'botwriter'); ?></strong>
            </div>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://console.groq.com/" target="_blank">console.groq.com</a> <?php esc_html_e('and sign up', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://console.groq.com/keys" target="_blank"><?php esc_html_e('API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create API Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key (starts with "gsk_") and paste it above', 'botwriter'); ?></li>
            </ol>
            <div class="info-tip success">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php esc_html_e('No credit card required for the free tier! Start generating content immediately.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight free-highlight">
                <span class="free-credits-badge large"><?php esc_html_e('🎉 FREE TIER', 'botwriter'); ?></span>
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
                <span class="dashicons dashicons-performance"></span>
                <strong><?php esc_html_e('Speed Stats:', 'botwriter'); ?></strong>
                <?php esc_html_e('Llama 3.3 70B: ~394 tokens/sec! Llama 3.1 8B: ~840 tokens/sec!', 'botwriter'); ?>
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
                <a href="https://console.groq.com/playground" target="_blank" class="link-item">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e('Playground', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
