<?php
/**
 * Google Gemini Image Provider Settings
 * 
 * @package BotWriter
 * @since 2.0.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Gemini image models configuration
 */
function botwriter_get_gemini_image_models() {
    return [
        'gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image (fast, efficient)',
        'gemini-3-pro-image-preview' => 'Gemini 3 Pro Image Preview (advanced, 4K)',
    ];
}

/**
 * Get Gemini image provider info
 */
function botwriter_get_gemini_image_info() {
    return [
        'name' => 'Google Gemini',
        'description' => __('Google\'s AI image generation using Gemini models. Excellent quality and fast generation.', 'botwriter'),
        'website' => 'https://ai.google.dev',
        'api_url' => 'https://aistudio.google.com/apikey',
        'docs_url' => 'https://ai.google.dev/gemini-api/docs/image-generation',
        'pricing_url' => 'https://ai.google.dev/gemini-api/docs/pricing',
        'free_credits' => __('Uses same API key as Gemini text models', 'botwriter'),
        'pricing_summary' => [
            'Gemini 2.5 Flash Image' => __('See pricing page', 'botwriter'),
            'Gemini 3 Pro Image Preview' => __('See pricing page', 'botwriter'),
        ],
        'features' => [
            __('Uses the same API key as Google Gemini text models', 'botwriter'),
            __('High-quality image generation', 'botwriter'),
            __('Excellent text rendering in images', 'botwriter'),
            __('Multiple aspect ratios supported', 'botwriter'),
            __('Fast generation with 2.5 Flash', 'botwriter'),
        ],
        'pros' => [
            __('Same API key as text models', 'botwriter'),
            __('Great prompt understanding', 'botwriter'),
            __('Fast generation speed', 'botwriter'),
            __('High quality outputs', 'botwriter'),
        ],
        'cons' => [
            __('Some content restrictions', 'botwriter'),
            __('Requires Google API billing', 'botwriter'),
        ],
    ];
}

/**
 * Render Gemini image settings tab content
 */
function botwriter_render_gemini_settings($settings, $is_active) {
    $info = botwriter_get_gemini_image_info();
    $models = botwriter_get_gemini_image_models();
    $current_model = $settings['botwriter_gemini_image_model'] ?? 'gemini-2.5-flash-image';
    $google_api_key = $settings['botwriter_google_api_key'] ?? '';
    $has_key = !empty($google_api_key);
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            
            <div class="info-notice">
                <span class="dashicons dashicons-info"></span>
                <p><strong><?php esc_html_e('Gemini Images uses Google AI API.', 'botwriter'); ?></strong> 
                <?php esc_html_e('This is the same API key used in Text AI → Google Gemini. You can configure it from either tab.', 'botwriter'); ?></p>
            </div>

            <div class="form-row">
                <label><?php esc_html_e('Google API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" 
                           name="botwriter_google_api_key" 
                           class="form-control api-key-input" 
                           value="<?php echo esc_attr(function_exists('botwriter_decrypt_api_key') ? botwriter_decrypt_api_key(get_option('botwriter_google_api_key')) : ''); ?>"
                           placeholder="AIza..." />
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                </div>
                <p class="description"><?php esc_html_e('Get your API key from', 'botwriter'); ?> <a href="<?php echo esc_url($info['api_url']); ?>" target="_blank">Google AI Studio</a></p>
            </div>

            <div class="form-row">
                <button type="button" class="button button-secondary test-api-key" data-provider="gemini">
                    <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                </button>
                <span class="test-api-result"></span>
            </div>

            <div class="form-row">
                <label><?php esc_html_e('Image Model:', 'botwriter'); ?></label>
                <select name="botwriter_gemini_image_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $id => $name): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($current_model, $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('2.5 Flash Image is recommended for speed. 3 Pro Preview for advanced features.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Use Gemini Images', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Get a free API key from', 'botwriter'); ?> <a href="<?php echo esc_url($info['api_url']); ?>" target="_blank"><?php esc_html_e('Google AI Studio', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Enter your Google API key above (or in Text AI → Google Gemini)', 'botwriter'); ?></li>
                <li><?php esc_html_e('Select Google Gemini as your image provider here', 'botwriter'); ?></li>
                <li><?php esc_html_e('Choose your preferred model (2.5 Flash Image for free tier)', 'botwriter'); ?></li>
                <li><?php esc_html_e('That\'s it! The same API key works for both text and images', 'botwriter'); ?></li>
            </ol>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight">
                <span class="free-credits-badge"><?php esc_html_e('Shared API Key', 'botwriter'); ?></span>
                <p><?php echo esc_html($info['free_credits']); ?></p>
            </div>

            <table class="pricing-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Model', 'botwriter'); ?></th>
                        <th><?php esc_html_e('Price', 'botwriter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($info['pricing_summary'] as $model => $price): ?>
                    <tr>
                        <td><code><?php echo esc_html($model); ?></code></td>
                        <td><strong><?php echo esc_html($price); ?></strong></td>
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
                    <?php esc_html_e('Image Docs', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e('Pricing', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['website']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Google AI', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
