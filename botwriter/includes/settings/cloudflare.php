<?php
/**
 * Cloudflare Workers AI Image Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Cloudflare Workers AI models configuration
 */
function botwriter_get_cloudflare_models() {
    return [
        'Flux (Black Forest Labs)' => [
            'flux-1-schnell' => 'FLUX.1 Schnell ⭐ (Fast, FREE tier - RECOMMENDED)',
            'flux-2-dev' => 'FLUX.2 Dev (Multi-reference, Partner - experimental)',
        ],
        'Stable Diffusion' => [
            'stable-diffusion-xl-lightning' => 'SDXL Lightning (Very fast, Beta)',
            'stable-diffusion-xl-base-1.0' => 'SDXL Base 1.0 (Beta)',
        ],
        'Other' => [
            'dreamshaper-8-lcm' => 'Dreamshaper 8 LCM (Photorealism)',
        ],
    ];
}

/**
 * Get Cloudflare Workers AI provider info
 */
function botwriter_get_cloudflare_info() {
    return [
        'name' => 'Cloudflare AI',
        'description' => __('Cloudflare Workers AI provides serverless AI inference with generous free tier. Uses FLUX.1 Schnell for high-quality images.', 'botwriter'),
        'website' => 'https://www.cloudflare.com/developer-platform/workers-ai/',
        'api_url' => 'https://dash.cloudflare.com/?to=/:account/ai/workers-ai',
        'docs_url' => 'https://developers.cloudflare.com/workers-ai/models/',
        'pricing_url' => 'https://developers.cloudflare.com/workers-ai/platform/pricing/',
        'free_credits' => __('10,000 Neurons/day FREE (~230 images with FLUX.1 Schnell!)', 'botwriter'),
        'pricing_summary' => [
            'FLUX.1 Schnell' => '~43 neurons (~$0.00047/image)',
            'FLUX.2 Dev' => '~150 neurons (~$0.0017/image)',
            'SDXL Lightning' => 'Beta pricing',
        ],
        'features' => [
            __('10,000 Neurons FREE daily!', 'botwriter'),
            __('FLUX.1 Schnell - 12B parameter model', 'botwriter'),
            __('No GPU management needed', 'botwriter'),
            __('Serverless & auto-scaling', 'botwriter'),
            __('Global edge network', 'botwriter'),
            __('Simple REST API', 'botwriter'),
        ],
        'pros' => [
            __('~230 FREE images/day', 'botwriter'),
            __('High quality FLUX model', 'botwriter'),
            __('Very cheap after free tier', 'botwriter'),
            __('Global Cloudflare network', 'botwriter'),
        ],
        'cons' => [
            __('No image size control', 'botwriter'),
            __('Limited to 8 steps max', 'botwriter'),
            __('Requires Cloudflare account', 'botwriter'),
        ],
    ];
}

/**
 * Render Cloudflare Workers AI settings tab content
 */
function botwriter_render_cloudflare_settings($settings, $is_active) {
    $info = botwriter_get_cloudflare_info();
    $models = botwriter_get_cloudflare_models();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            
            <div class="form-row">
                <label><?php esc_html_e('Account ID:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="text" name="botwriter_cloudflare_account_id" class="form-control" 
                           value="<?php echo esc_attr(get_option('botwriter_cloudflare_account_id')); ?>"
                           placeholder="e.g., a1b2c3d4e5f6g7h8i9j0...">
                </div>
                <p class="description">
                    <?php esc_html_e('Find your Account ID in the Cloudflare Dashboard → Overview → right sidebar', 'botwriter'); ?>
                    <br><a href="https://dash.cloudflare.com/" target="_blank"><?php esc_html_e('Open Cloudflare Dashboard', 'botwriter'); ?> →</a>
                </p>
            </div>
            
            <div class="form-row">
                <label><?php esc_html_e('API Token:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_cloudflare_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_cloudflare_api_key'))); ?>"
                           placeholder="Your Cloudflare API Token">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="cloudflare">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description">
                    <?php esc_html_e('Create a token with "Workers AI" read permission', 'botwriter'); ?>
                    <br><a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank"><?php esc_html_e('Create API Token', 'botwriter'); ?> →</a>
                </p>
            </div>
            
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <select name="botwriter_cloudflare_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $group => $group_models): ?>
                        <optgroup label="<?php echo esc_attr($group); ?>">
                            <?php foreach ($group_models as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['botwriter_cloudflare_model'], $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('FLUX.1 Schnell recommended - best balance of quality and free tier usage', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-info-card">
            <div class="provider-info-header">
                <h4>
                    <span class="dashicons dashicons-cloud"></span>
                    <?php echo esc_html($info['name']); ?>
                </h4>
                <div class="provider-links">
                    <a href="<?php echo esc_url($info['website']); ?>" target="_blank" class="provider-link">
                        <span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('Website', 'botwriter'); ?>
                    </a>
                    <a href="<?php echo esc_url($info['docs_url']); ?>" target="_blank" class="provider-link">
                        <span class="dashicons dashicons-book"></span> <?php esc_html_e('Docs', 'botwriter'); ?>
                    </a>
                    <a href="<?php echo esc_url($info['pricing_url']); ?>" target="_blank" class="provider-link">
                        <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing', 'botwriter'); ?>
                    </a>
                </div>
            </div>
            <p class="provider-description"><?php echo esc_html($info['description']); ?></p>
            
            <?php if (!empty($info['free_credits'])): ?>
            <div class="free-tier-highlight">
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php echo esc_html($info['free_credits']); ?></strong>
            </div>
            <?php endif; ?>

            <div class="provider-details-grid">
                <div class="provider-features">
                    <h5><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Features', 'botwriter'); ?></h5>
                    <ul>
                        <?php foreach ($info['features'] as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="provider-pricing">
                    <h5><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing', 'botwriter'); ?></h5>
                    <ul class="pricing-list">
                        <?php foreach ($info['pricing_summary'] as $model => $price): ?>
                            <li><span class="model-name"><?php echo esc_html($model); ?>:</span> <span class="price"><?php echo esc_html($price); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="provider-pros-cons">
                    <div class="pros">
                        <h5><span class="dashicons dashicons-thumbs-up"></span> <?php esc_html_e('Pros', 'botwriter'); ?></h5>
                        <ul>
                            <?php foreach ($info['pros'] as $pro): ?>
                                <li><?php echo esc_html($pro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="cons">
                        <h5><span class="dashicons dashicons-thumbs-down"></span> <?php esc_html_e('Cons', 'botwriter'); ?></h5>
                        <ul>
                            <?php foreach ($info['cons'] as $con): ?>
                                <li><?php echo esc_html($con); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="setup-instructions">
                <h5><span class="dashicons dashicons-info"></span> <?php esc_html_e('Setup Instructions', 'botwriter'); ?></h5>
                <ol>
                    <li><?php esc_html_e('Create a free Cloudflare account at cloudflare.com', 'botwriter'); ?></li>
                    <li><?php esc_html_e('Go to your dashboard and copy your Account ID from the sidebar', 'botwriter'); ?></li>
                    <li><?php esc_html_e('Create an API Token with "Workers AI" read permissions', 'botwriter'); ?></li>
                    <li><?php esc_html_e('Paste both values above', 'botwriter'); ?></li>
                </ol>
            </div>
        </div>
    </div>
    <?php
}
