<?php
/**
 * Stability AI Image Provider Settings
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Stability AI models configuration
 */
function botwriter_get_stability_models() {
    return [
        'Stable Diffusion 3.5' => [
            'sd3.5-large-turbo' => 'SD 3.5 Large Turbo (fast)',
            'sd3.5-large' => 'SD 3.5 Large (highest quality)',
            'sd3.5-medium' => 'SD 3.5 Medium (balanced)',
        ],
        'Stable Diffusion 3' => [
            'sd3-large' => 'SD3 Large',
            'sd3-medium' => 'SD3 Medium',
        ],
        'Premium' => [
            'core' => 'Stable Image Core',
            'ultra' => 'Stable Image Ultra',
        ],
    ];
}

/**
 * Get Stability AI provider info
 */
function botwriter_get_stability_info() {
    return [
        'name' => 'Stability AI',
        'description' => __('Creators of Stable Diffusion, the most popular open-source image model. Credit-based pricing with excellent quality.', 'botwriter'),
        'website' => 'https://stability.ai',
        'api_url' => 'https://platform.stability.ai/account/keys',
        'docs_url' => 'https://platform.stability.ai/docs/getting-started',
        'pricing_url' => 'https://platform.stability.ai/pricing',
        'free_credits' => __('25 FREE credits on signup (no credit card required!)', 'botwriter'),
        'pricing_summary' => [
            'SD 3.5 Large' => '6.5 credits (~$0.065/image)',
            'SD 3.5 Large Turbo' => '4 credits (~$0.04/image)',
            'SD 3.5 Medium' => '3.5 credits (~$0.035/image)',
            'Stable Image Core' => '3 credits (~$0.03/image)',
            'Creative Upscaler' => '60 credits (~$0.60/upscale)',
        ],
        'features' => [
            __('25 free credits to start!', 'botwriter'),
            __('Creators of Stable Diffusion', 'botwriter'),
            __('Excellent image quality', 'botwriter'),
            __('Image editing tools (inpaint, outpaint)', 'botwriter'),
            __('Upscaling (up to 4K)', 'botwriter'),
            __('Background removal', 'botwriter'),
            __('3D and audio generation too', 'botwriter'),
        ],
        'pros' => [
            __('Free credits to start', 'botwriter'),
            __('Pioneer in AI imagery', 'botwriter'),
            __('Many editing tools', 'botwriter'),
            __('Predictable credit pricing', 'botwriter'),
        ],
        'cons' => [
            __('Credit system can be confusing', 'botwriter'),
            __('Credits expire after 1 year', 'botwriter'),
        ],
    ];
}

/**
 * Render Stability AI settings tab content
 */
function botwriter_render_stability_settings($settings, $is_active) {
    $info = botwriter_get_stability_info();
    $models = botwriter_get_stability_models();
    ?>
    <div class="provider-config-section">
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_stability_api_key" class="form-control api-key-input" 
                           value="<?php echo esc_attr(botwriter_decrypt_api_key(get_option('botwriter_stability_api_key'))); ?>"
                           placeholder="sk-...">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                    <button type="button" class="button button-secondary test-api-key" data-provider="stability">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test API Key', 'botwriter'); ?>
                    </button>
                    <span class="test-api-result"></span>
                </div>
                <p class="description"><?php esc_html_e('Your Stability API key starts with "sk-"', 'botwriter'); ?></p>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <select name="botwriter_stability_model" class="form-select" style="width: 100%; max-width: 400px;">
                    <?php foreach ($models as $group => $group_models): ?>
                        <optgroup label="<?php echo esc_attr($group); ?>">
                            <?php foreach ($group_models as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['botwriter_stability_model'], $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="provider-info-card highlight-free">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('How to Get Your API Key', 'botwriter'); ?></h4>
            <div class="free-banner">
                <span class="dashicons dashicons-awards"></span>
                <strong><?php esc_html_e('🎁 25 FREE credits on signup!', 'botwriter'); ?></strong>
            </div>
            <ol class="setup-steps">
                <li><?php esc_html_e('Go to', 'botwriter'); ?> <a href="https://platform.stability.ai/" target="_blank">platform.stability.ai</a> <?php esc_html_e('and create an account', 'botwriter'); ?></li>
                <li><?php esc_html_e('Navigate to', 'botwriter'); ?> <a href="https://platform.stability.ai/account/keys" target="_blank"><?php esc_html_e('Account → API Keys', 'botwriter'); ?></a></li>
                <li><?php esc_html_e('Click "Create API Key"', 'botwriter'); ?></li>
                <li><?php esc_html_e('Copy the key and paste it above', 'botwriter'); ?></li>
            </ol>
            <div class="info-tip success">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php esc_html_e('You get 25 free credits immediately - no credit card needed! That\'s about 6-8 high-quality images.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="provider-pricing-card">
            <h4><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing Overview', 'botwriter'); ?></h4>
            
            <div class="pricing-highlight free-highlight">
                <span class="free-credits-badge large"><?php esc_html_e('🎁 25 FREE Credits', 'botwriter'); ?></span>
                <p><?php echo esc_html($info['free_credits']); ?></p>
            </div>

            <div class="credits-explanation">
                <p><strong><?php esc_html_e('How credits work:', 'botwriter'); ?></strong> <?php esc_html_e('1 credit = $0.01. Buy credits in bundles starting at $10.', 'botwriter'); ?></p>
            </div>

            <table class="pricing-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Model', 'botwriter'); ?></th>
                        <th><?php esc_html_e('Credits / Est. Cost', 'botwriter'); ?></th>
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
                <a href="https://platform.stability.ai/account/credits" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Buy Credits', 'botwriter'); ?>
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
                <a href="https://stabilityai.instatus.com/" target="_blank" class="link-item">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('Status', 'botwriter'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
