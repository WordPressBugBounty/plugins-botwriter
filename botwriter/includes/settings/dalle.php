<?php
/**
 * DALL-E (OpenAI) Image Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get DALL-E models configuration
 */
function botwriter_get_dalle_models() {
    return [
        'gpt-image-1' => 'GPT Image 1 (latest - best quality)',
        'dall-e-3' => 'DALL-E 3 (high quality)',
        'dall-e-2' => 'DALL-E 2 (legacy)',
    ];
}

/**
 * Get DALL-E provider info
 */
function botwriter_get_dalle_info() {
    return [
        'name' => 'DALL-E (OpenAI)',
        'description' => __('OpenAI\'s image generation models. The latest GPT Image 1 offers the best quality and prompt adherence.', 'botwriter'),
        'website' => 'https://openai.com',
        'api_url' => 'https://platform.openai.com/api-keys',
        'docs_url' => 'https://platform.openai.com/docs/guides/images',
        'pricing_url' => 'https://openai.com/api/pricing/',
        'free_credits' => __('Included in OpenAI API free credits ($5 for new accounts)', 'botwriter'),
        'pricing_summary' => [
            'GPT Image 1 (Low)' => '~$0.01/image',
            'GPT Image 1 (Medium)' => '~$0.04/image',
            'GPT Image 1 (High)' => '~$0.17/image',
            'DALL-E 3 (1024x1024)' => '$0.04/image',
            'DALL-E 3 (1792x1024)' => '$0.08/image',
        ],
        'features' => [
            __('Uses the same API key as OpenAI text models', 'botwriter'),
            __('Excellent prompt understanding', 'botwriter'),
            __('High quality, realistic images', 'botwriter'),
            __('Multiple size/quality options', 'botwriter'),
            __('Great for product images and illustrations', 'botwriter'),
        ],
        'pros' => [
            __('Same API key as text models', 'botwriter'),
            __('Best prompt understanding', 'botwriter'),
            __('Very realistic outputs', 'botwriter'),
        ],
        'cons' => [
            __('Higher cost than alternatives', 'botwriter'),
            __('Limited style controls', 'botwriter'),
        ],
    ];
}

/**
 * Render DALL-E settings tab content
 */
function botwriter_render_dalle_settings($settings, $is_active) {
    $info = botwriter_get_dalle_info();
    $models = botwriter_get_dalle_models();
    $openai_api_key = $settings['botwriter_openai_api_key'] ?? '';
    $has_key = !empty($openai_api_key);
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            
            <div class="info-notice">
                <span class="dashicons dashicons-info"></span>
                <p><strong><?php esc_html_e('DALL-E uses OpenAI API.', 'botwriter'); ?></strong> 
                <?php esc_html_e('This is the same API key used in Text AI → OpenAI. You can configure it from either tab.', 'botwriter'); ?></p>
            </div>

            <div class="form-row">
                <label><?php esc_html_e('OpenAI API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" 
                           name="botwriter_openai_api_key" 
                           class="form-control api-key-input" 
                           value="<?php echo esc_attr(function_exists('botwriter_decrypt_api_key') ? botwriter_decrypt_api_key(get_option('botwriter_openai_api_key')) : ''); ?>"
                           placeholder="sk-..." />
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                </div>
                <p class="description"><?php esc_html_e('Get your API key from', 'botwriter'); ?> <a href="<?php echo esc_url($info['api_url']); ?>" target="_blank">platform.openai.com/api-keys</a></p>
            </div>

            <div class="form-row">
                <button type="button" class="button button-secondary test-api-key" data-provider="dalle">
                    <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                </button>
                <span class="test-api-result"></span>
            </div>

            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <select name="botwriter_dalle_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $id => $name): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['botwriter_dalle_model'], $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Use DALL-E', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Enter your OpenAI API key above (or in Text AI → OpenAI)', 'botwriter'); ?></li>
                <li><?php esc_html_e('Select DALL-E as your image provider here', 'botwriter'); ?></li>
                <li><?php esc_html_e('Choose your preferred model and quality settings', 'botwriter'); ?></li>
                <li><?php esc_html_e('That\'s it! The same API key works for both text and images', 'botwriter'); ?></li>
            </ol>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight">
                <span class="free-credits-badge"><?php esc_html_e('Shared Credits', 'botwriter'); ?></span>
                <p><?php echo esc_html($info['free_credits']); ?></p>
            </div>

            <table class="pricing-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Model / Quality', 'botwriter'); ?></th>
                        <th><?php esc_html_e('Price', 'botwriter'); ?></th>
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
                    <?php esc_html_e('OpenAI API Keys', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['docs_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-book"></span>
                    <?php esc_html_e('Image Docs', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e('Pricing', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
