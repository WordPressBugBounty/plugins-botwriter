<?php
/**
 * Google (Gemini) Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Google provider info
 */
function botwriter_get_google_info() {
    return [
        'name' => 'Google AI (Gemini)',
        'description' => __('Google\'s most capable AI models. Excellent multimodal capabilities and generous free tier.', 'botwriter'),
        'website' => 'https://ai.google.dev',
        'api_url' => 'https://aistudio.google.com/apikey',
        'docs_url' => 'https://ai.google.dev/gemini-api/docs',
        'pricing_url' => 'https://ai.google.dev/pricing',
        'free_credits' => __('FREE TIER: Unlimited free usage with rate limits (no credit card required!)', 'botwriter'),
        'pricing_summary' => [
            'gemini-2.5-flash' => 'FREE or $0.30/1M input, $2.50/1M output',
            'gemini-2.5-flash-lite' => 'FREE or $0.10/1M input, $0.40/1M output',
            'gemini-2.5-pro' => 'FREE or $1.25/1M input, $10/1M output',
            'gemini-2.0-flash' => 'FREE or $0.10/1M input, $0.40/1M output',
        ],
        'features' => [
            __('Generous FREE tier - no credit card required!', 'botwriter'),
            __('1 million token context window', 'botwriter'),
            __('Native multimodal (text, images, video, audio)', 'botwriter'),
            __('Google Search grounding included', 'botwriter'),
            __('Excellent for reasoning and coding', 'botwriter'),
            __('Built-in image generation (Imagen)', 'botwriter'),
        ],
        'pros' => [
            __('Best free tier in the industry', 'botwriter'),
            __('No credit card for free tier', 'botwriter'),
            __('Massive context window', 'botwriter'),
            __('Fast inference speed', 'botwriter'),
        ],
        'cons' => [
            __('Free tier has rate limits', 'botwriter'),
            __('Some features paid-only', 'botwriter'),
        ],
    ];
}

/**
 * Render Google settings tab content
 */
function botwriter_render_google_settings($settings, $is_active) {
    $info = botwriter_get_google_info();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_google_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_google_api_key'))); ?>"
                           placeholder="AIza...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="google">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key & Update Models', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your Google AI API key starts with "AIza"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <div class="model-select-wrapper">
                    <?php botwriter_render_model_select('google', 'botwriter_google_model', $settings['botwriter_google_model']); ?>
                    <button type="button" class="button button-secondary test-model" data-provider="google">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Test Model', 'botwriter'); ?>
                    </button>
                    <span class="test-model-result"></span>
                </div>
            </div>
        </div>

        <div class="provider-info-card highlight-free">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <div class="free-banner">
                <span class="dashicons dashicons-awards"></span>
                <strong><?php esc_html_e('100% FREE to start - No credit card required!', 'botwriter'); ?></strong>
            </div>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a> <?php esc_html_e('and sign in with your Google account', 'botwriter'); ?></li>
                <li><?php esc_html_e('Click', 'botwriter'); ?> <a href="https://aistudio.google.com/apikey" target="_blank"><?php esc_html_e('"Get API Key"', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create API Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key and paste it above', 'botwriter'); ?></li>
            </ol>
            <div class="info-tip success">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php esc_html_e('That\'s it! No billing setup needed for the free tier. Start using immediately!', 'botwriter'); ?></p>
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
                        <th><?php esc_html_e('Cost', 'botwriter'); ?></th>
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
                <strong><?php esc_html_e('Free Tier Limits:', 'botwriter'); ?></strong>
                <?php esc_html_e('15 RPM (requests/min), 1M TPM (tokens/min), 1,500 RPD (requests/day).', 'botwriter'); ?>
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
                <a href="https://aistudio.google.com/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-welcome-widgets-menus"></span>
                    <?php esc_html_e('AI Studio', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
