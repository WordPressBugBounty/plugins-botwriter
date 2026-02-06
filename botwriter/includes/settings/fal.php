<?php
/**
 * Fal.ai Image Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Fal.ai models configuration
 * Updated: December 2025 with latest models
 */
function botwriter_get_fal_models() {
    return [
        // Google Nano Banana (newest!)
        'Google' => [
            'fal-ai/nano-banana-pro' => '🍌 Nano Banana Pro (newest!)',
            'fal-ai/gemini-3-pro-image-preview' => 'Gemini 3 Pro Image',
        ],
        // ByteDance Seedream
        'ByteDance' => [
            'fal-ai/bytedance/seedream/v4.5/text-to-image' => 'Seedream 4.5 (newest!)',
            'fal-ai/bytedance/seedream/v4/text-to-image' => 'Seedream 4.0',
        ],
        // Flux 2.x (Latest)
        'Flux 2.x' => [
            'fal-ai/flux-2-pro' => 'Flux 2 Pro (best)',
            'fal-ai/flux-2-flex' => 'Flux 2 Flex',
            'fal-ai/flux-2' => 'Flux 2 Dev',
            'fal-ai/flux-2/lora' => 'Flux 2 LoRA',
        ],
        // Flux Pro / Kontext
        'Flux Kontext' => [
            'fal-ai/flux-pro/kontext/max' => 'Flux Kontext Max',
            'fal-ai/flux-pro/kontext' => 'Flux Kontext Pro',
            'fal-ai/flux-kontext-lora' => 'Flux Kontext LoRA',
        ],
        // Flux 1.x
        'Flux 1.x' => [
            'fal-ai/flux-pro/v1.1' => 'Flux Pro v1.1',
            'fal-ai/flux/dev' => 'Flux Dev',
            'fal-ai/flux/schnell' => 'Flux Schnell (ultra fast)',
        ],
        // Recraft
        'Recraft' => [
            'fal-ai/recraft/v3/text-to-image' => 'Recraft V3 (SOTA)',
        ],
        // Qwen Image
        'Qwen' => [
            'fal-ai/qwen-image' => 'Qwen Image (best text)',
        ],
        // Other Notable
        'Other' => [
            'fal-ai/z-image/turbo' => 'Z-Image Turbo (super fast)',
            'fal-ai/piflow' => 'PiFlow (fast, quality)',
            'fal-ai/flux-realism' => 'Flux Realism',
            'fal-ai/flux-lora' => 'Flux LoRA',
            'imagineart/imagineart-1.5-preview/text-to-image' => 'ImagineArt 1.5',
            'bria/fibo/generate' => 'Bria Fibo (enterprise)',
        ],
    ];
}

/**
 * Get Fal.ai provider info
 */
function botwriter_get_fal_info() {
    return [
        'name' => 'Fal.ai',
        'description' => __('Fast, reliable inference for Flux and other cutting-edge image models. Known for excellent quality and speed.', 'botwriter'),
        'website' => 'https://fal.ai',
        'api_url' => 'https://fal.ai/dashboard/keys',
        'docs_url' => 'https://docs.fal.ai/',
        'pricing_url' => 'https://fal.ai/pricing',
        'free_credits' => __('Free credits on signup for testing', 'botwriter'),
        'pricing_summary' => [
            '🍌 Nano Banana Pro' => '~$0.02/image',
            'Seedream 4.5' => '~$0.03/image',
            'Flux 2 Pro' => '~$0.05/image',
            'Flux Pro v1.1' => '~$0.04/image',
            'Flux Schnell' => '~$0.003/image (ultra cheap!)',
            'Recraft V3' => '~$0.04/image',
            'Z-Image Turbo' => '~$0.002/image',
        ],
        'features' => [
            __('Hosts the best Flux models', 'botwriter'),
            __('Very fast generation (seconds)', 'botwriter'),
            __('Excellent image quality', 'botwriter'),
            __('LoRA support for custom styles', 'botwriter'),
            __('Competitive pricing', 'botwriter'),
            __('Video generation also available', 'botwriter'),
        ],
        'pros' => [
            __('Best Flux hosting', 'botwriter'),
            __('Fast inference', 'botwriter'),
            __('Great documentation', 'botwriter'),
            __('Flexible pricing', 'botwriter'),
        ],
        'cons' => [
            __('Smaller model selection', 'botwriter'),
            __('Newer platform', 'botwriter'),
        ],
    ];
}

/**
 * Render Fal.ai settings tab content
 */
function botwriter_render_fal_settings($settings, $is_active) {
    $info = botwriter_get_fal_info();
    $models = botwriter_get_fal_models();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_fal_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_fal_api_key'))); ?>"
                           placeholder="...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="fal">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Get your API key from the Fal.ai dashboard', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <select name="botwriter_fal_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $group => $group_models): ?>
                        <optgroup label="<?php echo esc_attr($group); ?>">
                            <?php foreach ($group_models as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['botwriter_fal_model'], $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://fal.ai/login" target="_blank">fal.ai</a> <?php esc_html_e('and sign up', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://fal.ai/dashboard/keys" target="_blank"><?php esc_html_e('Dashboard → API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy and paste the key above', 'botwriter'); ?></li>
                <li><?php esc_html_e('Add credits at', 'botwriter'); ?> <a href="https://fal.ai/dashboard/billing" target="_blank"><?php esc_html_e('Billing', 'botwriter'); ?></a></li>
            </ol>
            <div class="info-tip">
                <span class="dashicons dashicons-awards"></span>
                <p><?php esc_html_e('Flux Schnell is incredibly cheap - great for testing and high-volume use!', 'botwriter'); ?></p>
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

            <div class="cost-saving-tip">
                <span class="dashicons dashicons-lightbulb"></span>
                <strong><?php esc_html_e('Budget Tip:', 'botwriter'); ?></strong>
                <?php esc_html_e('Flux Schnell at $0.003/image means 333 images per $1!', 'botwriter'); ?>
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
                <a href="https://fal.ai/models" target="_blank" class="link-item">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <?php esc_html_e('All Models', 'botwriter'); ?>
                </a>
                <a href="https://status.fal.ai/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('Status', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
