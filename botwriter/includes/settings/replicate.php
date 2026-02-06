<?php
/**
 * Replicate Image Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Replicate models configuration
 * Updated: December 2025 with latest models
 */
function botwriter_get_replicate_models() {
    return [
        // Google - Latest Imagen & Nano Banana
        'Google' => [
            'google/nano-banana-pro' => '🍌 Nano Banana Pro (newest!)',
            'google/imagen-4' => 'Imagen 4 (flagship)',
            'google/imagen-4-fast' => 'Imagen 4 Fast',
            'google/imagen-4-ultra' => 'Imagen 4 Ultra (best quality)',
            'google/imagen-3' => 'Imagen 3',
            'google/imagen-3-fast' => 'Imagen 3 Fast',
        ],
        // ByteDance - Seedream Series
        'ByteDance' => [
            'bytedance/seedream-4.5' => 'Seedream 4.5 (newest!)',
            'bytedance/seedream-4' => 'Seedream 4 (4K, 15M+ runs)',
            'bytedance/seedream-3' => 'Seedream 3 (best overall)',
        ],
        // Black Forest Labs - Flux 2.x & 1.x
        'Flux 2.x' => [
            'black-forest-labs/flux-2-pro' => 'Flux 2 Pro (8 references)',
            'black-forest-labs/flux-2-flex' => 'Flux 2 Flex (10 references)',
            'black-forest-labs/flux-2-dev' => 'Flux 2 Dev',
            'black-forest-labs/flux-kontext-max' => 'Flux Kontext Max',
            'black-forest-labs/flux-kontext-pro' => 'Flux Kontext Pro',
        ],
        'Flux 1.x' => [
            'black-forest-labs/flux-1.1-pro' => 'Flux 1.1 Pro',
            'black-forest-labs/flux-1.1-pro-ultra' => 'Flux 1.1 Pro Ultra (4MP)',
            'black-forest-labs/flux-dev' => 'Flux Dev',
            'black-forest-labs/flux-schnell' => 'Flux Schnell (ultra fast)',
        ],
        // Ideogram - Best for text in images
        'Ideogram' => [
            'ideogram-ai/ideogram-v3-quality' => 'Ideogram V3 Quality (best text)',
            'ideogram-ai/ideogram-v3-turbo' => 'Ideogram V3 Turbo',
            'ideogram-ai/ideogram-v3-balanced' => 'Ideogram V3 Balanced',
            'ideogram-ai/ideogram-v2' => 'Ideogram V2',
            'ideogram-ai/ideogram-v2-turbo' => 'Ideogram V2 Turbo',
        ],
        // Alibaba Qwen
        'Qwen' => [
            'qwen/qwen-image' => 'Qwen Image (best text rendering)',
        ],
        // Recraft & Others
        'Recraft' => [
            'recraft-ai/recraft-v3' => 'Recraft V3 (SOTA benchmark)',
            'recraft-ai/recraft-v3-svg' => 'Recraft V3 SVG (vector output)',
        ],
        // Stability AI
        'Stability AI' => [
            'stability-ai/stable-diffusion-3.5-large' => 'SD 3.5 Large',
            'stability-ai/stable-diffusion-3.5-large-turbo' => 'SD 3.5 Large Turbo',
            'stability-ai/stable-diffusion-3.5-medium' => 'SD 3.5 Medium',
            'stability-ai/sdxl' => 'SDXL',
        ],
        // Other Notable Models
        'Other' => [
            'luma/photon' => 'Luma Photon',
            'luma/photon-flash' => 'Luma Photon Flash',
            'minimax/image-01' => 'Minimax Image 01',
            'leonardoai/lucid-origin' => 'Leonardo Lucid Origin',
            'tencent/hunyuan-image-3' => 'Tencent Hunyuan Image 3',
            'prunaai/flux-fast' => 'Pruna Flux Fast (fastest)',
        ],
    ];
}

/**
 * Get Replicate provider info
 */
function botwriter_get_replicate_info() {
    return [
        'name' => 'Replicate',
        'description' => __('Run open-source machine learning models with a simple API. Thousands of models available, pay per second of compute.', 'botwriter'),
        'website' => 'https://replicate.com',
        'api_url' => 'https://replicate.com/account/api-tokens',
        'docs_url' => 'https://replicate.com/docs',
        'pricing_url' => 'https://replicate.com/pricing',
        'free_credits' => __('Free credits to get started on signup', 'botwriter'),
        'pricing_summary' => [
            '🍌 Nano Banana Pro' => '~$0.02/image',
            'Imagen 4 Fast' => '~$0.02/image',
            'Seedream 4' => '~$0.03/image',
            'Flux 2 Pro' => '~$0.05/image',
            'Flux 1.1 Pro' => '$0.04/image',
            'Flux Schnell' => '$0.003/image (1000 for $3!)',
            'Ideogram V3 Quality' => '$0.08/image',
            'Recraft V3' => '$0.04/image',
        ],
        'features' => [
            __('Thousands of open-source models', 'botwriter'),
            __('Pay per second of compute', 'botwriter'),
            __('Run custom models', 'botwriter'),
            __('Fine-tuning available', 'botwriter'),
            __('Video, audio, and more', 'botwriter'),
            __('Community-contributed models', 'botwriter'),
        ],
        'pros' => [
            __('Huge model selection', 'botwriter'),
            __('Custom model support', 'botwriter'),
            __('Simple API', 'botwriter'),
            __('Pay only for compute used', 'botwriter'),
        ],
        'cons' => [
            __('Cold starts can be slow', 'botwriter'),
            __('Variable pricing', 'botwriter'),
        ],
    ];
}

/**
 * Render Replicate settings tab content
 */
function botwriter_render_replicate_settings($settings, $is_active) {
    $info = botwriter_get_replicate_info();
    $models = botwriter_get_replicate_models();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Token:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_replicate_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_replicate_api_key'))); ?>"
                           placeholder="r8_...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="replicate">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your Replicate API token starts with "r8_"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <select name="botwriter_replicate_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $group => $group_models): ?>
                        <optgroup label="<?php echo esc_attr($group); ?>">
                            <?php foreach ($group_models as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['botwriter_replicate_model'], $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Explore all models at', 'botwriter'); ?> <a href="https://replicate.com/explore" target="_blank">replicate.com/explore</a></p>
            </div>
        </div>

        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Token', 'botwriter'); ?></h4>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://replicate.com/" target="_blank">replicate.com</a> <?php esc_html_e('and sign up (GitHub recommended)', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://replicate.com/account/api-tokens" target="_blank"><?php esc_html_e('Account → API Tokens', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Copy your API token (starts with "r8_")', 'botwriter'); ?></li>
                <li><?php esc_html_e('Paste it above', 'botwriter'); ?></li>
            </ol>
            <div class="info-tip">
                <span class="dashicons dashicons-lightbulb"></span>
                <p><?php esc_html_e('Replicate hosts thousands of models - explore the collection to find the perfect one for your needs!', 'botwriter'); ?></p>
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
                <span class="dashicons dashicons-info"></span>
                <strong><?php esc_html_e('Note:', 'botwriter'); ?></strong>
                <?php esc_html_e('Replicate charges by compute time. Prices shown are estimates for typical images.', 'botwriter'); ?>
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
                    <?php esc_html_e('Get API Token', 'botwriter'); ?>
                </a>
                <a href="https://replicate.com/explore" target="_blank" class="link-item">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <?php esc_html_e('Explore Models', 'botwriter'); ?>
                </a>
                <a href="<?php echo esc_url($info['docs_url']); ?>" target="_blank" class="link-item">
                    <span class="dashicons dashicons-book"></span>
                    <?php esc_html_e('Documentation', 'botwriter'); ?>
                </a>
                <a href="https://replicatestatus.com/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('Status', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
