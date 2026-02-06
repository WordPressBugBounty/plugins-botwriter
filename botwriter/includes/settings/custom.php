<?php
/**
 * Custom Provider / Self-Hosting Settings
 * 
 * Allows users to configure custom AI providers that use OpenAI-compatible APIs.
 * Supports self-hosted solutions (Ollama, LM Studio) and cloud providers (Grok, Groq, etc.)
 * 
 * When custom provider is selected, Direct Mode is automatically enabled for that task type.
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Custom Provider info
 */
function botwriter_get_custom_info() {
    return [
        'name' => __('Custom Provider or Self-Hosting', 'botwriter'),
        'description' => __('Connect to any OpenAI-compatible API endpoint. Your data goes directly to your chosen provider without passing through botwriter.com.', 'botwriter'),
        'features' => [
            __('Works with any OpenAI-compatible API (Grok, Groq, DeepSeek, Ollama, etc.)', 'botwriter'),
            __('Self-hosted options for complete privacy', 'botwriter'),
            __('No data passes through botwriter.com - Direct Mode', 'botwriter'),
            __('Support for local image generation (Automatic1111, ComfyUI)', 'botwriter'),
        ],
        'examples' => [
            'cloud' => [
                ['name' => 'Grok (xAI)', 'url' => 'https://api.x.ai/v1'],
                ['name' => 'Groq', 'url' => 'https://api.groq.com/openai/v1'],
                ['name' => 'DeepSeek', 'url' => 'https://api.deepseek.com/v1'],
                ['name' => 'Together AI', 'url' => 'https://api.together.xyz/v1'],
                ['name' => 'Perplexity', 'url' => 'https://api.perplexity.ai'],
                ['name' => 'Fireworks AI', 'url' => 'https://api.fireworks.ai/inference/v1'],
            ],
            'local' => [
                ['name' => 'Ollama', 'url' => 'http://localhost:11434/v1'],
                ['name' => 'LM Studio', 'url' => 'http://localhost:1234/v1'],
                ['name' => 'LocalAI', 'url' => 'http://localhost:8080/v1'],
            ],
            'images' => [
                ['name' => 'Automatic1111', 'url' => 'http://localhost:7860', 'note' => 'Start with --api flag'],
                ['name' => 'ComfyUI', 'url' => 'http://localhost:8188', 'note' => 'Default installation'],
                ['name' => 'LocalAI Images', 'url' => 'http://localhost:8080/v1', 'note' => 'OpenAI-compatible'],
            ],
        ],
    ];
}

/**
 * Render Custom Text Provider settings
 * Called when 'custom' is selected as text_provider
 */
function botwriter_render_custom_settings($settings, $is_active) {
    $info = botwriter_get_custom_info();
    
    // Current values for TEXT
    $current_url = get_option('botwriter_custom_text_url', '');
    $current_api_key = botwriter_decrypt_api_key(get_option('botwriter_custom_text_api_key', ''));
    $current_model = get_option('botwriter_custom_text_model', '');
    
    // Check Action Scheduler availability
    $action_scheduler_available = class_exists('BotWriter_Action_Scheduler_Loader') && BotWriter_Action_Scheduler_Loader::is_available();
    $action_scheduler_file = BOTWRITER_PLUGIN_DIR . 'libraries/action-scheduler/action-scheduler.php';
    $action_scheduler_exists = file_exists($action_scheduler_file);
    ?>
    
    <div class="provider-config-section custom-provider-section">
        <?php if (!$action_scheduler_exists): ?>
        <!-- Action Scheduler Missing Warning -->
        <div class="action-scheduler-error-notice">
            <span class="dashicons dashicons-dismiss"></span>
            <div>
                <p><strong><?php esc_html_e('Action Scheduler Library Missing!', 'botwriter'); ?></strong></p>
                <p><?php esc_html_e('The Action Scheduler library is required for Custom Provider mode but was not found. Please reinstall the plugin or contact support.', 'botwriter'); ?></p>
                <p><code><?php echo esc_html($action_scheduler_file); ?></code></p>
            </div>
        </div>
        <?php elseif (!$action_scheduler_available): ?>
        <!-- Action Scheduler Not Initialized Warning -->
        <div class="action-scheduler-warning-notice">
            <span class="dashicons dashicons-info"></span>
            <div>
                <p><strong><?php esc_html_e('Action Scheduler Initializing...', 'botwriter'); ?></strong></p>
                <p><?php esc_html_e('The Action Scheduler library is present but may not be fully initialized. If you experience issues, try reloading the page or deactivating conflicting plugins.', 'botwriter'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SSL Warning for local servers -->
        <div class="ssl-warning-notice">
            <span class="dashicons dashicons-warning"></span>
            <div>
                <p><strong><?php esc_html_e('Using localhost or HTTP?', 'botwriter'); ?></strong></p>
                <p><?php 
                    printf(
                        /* translators: %s: link to General Settings */
                        esc_html__('For local servers (http://localhost), you may need to disable SSL verification in %s.', 'botwriter'),
                        '<a href="#" class="go-to-general-settings">' . esc_html__('General Settings', 'botwriter') . '</a>'
                    ); 
                ?></p>
            </div>
        </div>
        
        <!-- API Configuration Card -->
        <div class="provider-config-card">
            <h4><?php esc_html_e('API Configuration', 'botwriter'); ?></h4>
            
            <!-- API URL -->
            <div class="form-row">
                <label><?php esc_html_e('API Base URL:', 'botwriter'); ?></label>
                <div class="input-with-test">
                    <input type="url" name="botwriter_custom_text_url" id="botwriter_custom_text_url" 
                           class="form-control botwriter-autosave" 
                           value="<?php echo esc_attr($current_url); ?>"
                           placeholder="https://api.example.com/v1">
                    <button type="button" class="button button-secondary test-custom-text-connection">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Test', 'botwriter'); ?>
                    </button>
                    <span class="test-result" id="test-custom-text-result"></span>
                </div>
                <p class="description"><?php esc_html_e('The base URL of the OpenAI-compatible API (without /chat/completions).', 'botwriter'); ?></p>
            </div>
            
            <!-- API Key -->
            <div class="form-row">
                <label><?php esc_html_e('API Key:', 'botwriter'); ?></label>
                <div class="api-key-wrapper">
                    <input type="password" name="botwriter_custom_text_api_key" id="botwriter_custom_text_api_key"
                           class="form-control api-key-input botwriter-autosave" 
                           value="<?php echo esc_attr($current_api_key); ?>"
                           placeholder="<?php esc_attr_e('Optional for local servers', 'botwriter'); ?>">
                    <button type="button" class="button toggle-api-key"><?php esc_html_e('Show', 'botwriter'); ?></button>
                </div>
                <p class="description"><?php esc_html_e('Leave empty for local servers that don\'t require authentication (Ollama, LM Studio).', 'botwriter'); ?></p>
            </div>
            
            <!-- Model -->
            <div class="form-row">
                <label><?php esc_html_e('Model:', 'botwriter'); ?></label>
                <input type="text" name="botwriter_custom_text_model" id="botwriter_custom_text_model"
                       class="form-control botwriter-autosave" 
                       value="<?php echo esc_attr($current_model); ?>"
                       placeholder="grok-2, llama3.2, deepseek-chat...">
                <p class="description"><?php esc_html_e('The model identifier as required by your provider.', 'botwriter'); ?></p>
            </div>
            
            <!-- Model Compatibility Notice -->
            <div class="notice notice-warning inline bw-notice-warning-inline">
                <p>
                    <span class="dashicons dashicons-info bw-icon-warning"></span>
                    <strong><?php esc_html_e('Model Compatibility', 'botwriter'); ?></strong>
                </p>
                <p class="bw-my-8">
                    <?php esc_html_e('There are many AI models available and we cannot guarantee full compatibility with all of them. Some models may not follow the expected response format.', 'botwriter'); ?>
                </p>
                <p class="bw-my-8">
                    <strong><?php esc_html_e('Tested models:', 'botwriter'); ?></strong> <code>qwen3-vl-8b-instruct, deepseek-r1-0528-qwen3-8b</code>
                </p>
                <p class="bw-my-8">
                    <?php 
                    printf(
                        /* translators: %s: support ticket URL */
                        esc_html__('If you find a model that doesn\'t work properly, please %s so we can add support for it.', 'botwriter'),
                        '<a href="https://wpbotwriter.com/log-a-support-ticket/" target="_blank">' . esc_html__('submit a support ticket', 'botwriter') . '</a>'
                    ); 
                    ?>
                </p>
            </div>
        </div>

        <!-- Examples Reference Card -->
        <div class="provider-info-card">
            <h4><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('Quick Reference', 'botwriter'); ?></h4>
            
            <div class="examples-grid">
                <div class="examples-column">
                    <h5><?php esc_html_e('☁️ Cloud Providers', 'botwriter'); ?></h5>
                    <table class="examples-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Provider', 'botwriter'); ?></th>
                                <th><?php esc_html_e('API Base URL', 'botwriter'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($info['examples']['cloud'] as $example): ?>
                            <tr>
                                <td><strong><?php echo esc_html($example['name']); ?></strong></td>
                                <td><code><?php echo esc_html($example['url']); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="examples-column">
                    <h5><?php esc_html_e('🏠 Self-Hosted', 'botwriter'); ?></h5>
                    <table class="examples-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Software', 'botwriter'); ?></th>
                                <th><?php esc_html_e('Default URL', 'botwriter'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($info['examples']['local'] as $example): ?>
                            <tr>
                                <td><strong><?php echo esc_html($example['name']); ?></strong></td>
                                <td><code><?php echo esc_html($example['url']); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Custom Image Provider settings
 * Called when 'custom' is selected as image_provider
 * Note: The main settings loop looks for botwriter_render_{provider}_settings
 * For images, we need a separate function since 'custom' is used for both
 */
function botwriter_render_custom_image_settings($settings, $is_active) {
    ?>
    <div class="provider-config-section custom-provider-section">
        <!-- Feature In Development Notice -->
        <div class="notice notice-warning inline bw-notice-development">
            <p>
                <span class="dashicons dashicons-hammer bw-icon-warning"></span>
                <strong><?php esc_html_e('Feature In Development', 'botwriter'); ?></strong>
            </p>
            <p class="bw-my-8">
                <?php esc_html_e('Custom Image Provider support is currently under development. This feature will allow you to use local image generation services like Automatic1111, ComfyUI, or any OpenAI-compatible image API.', 'botwriter'); ?>
            </p>
            <p class="bw-my-8">
                <?php 
                printf(
                    /* translators: %s: support ticket URL */
                    esc_html__('If you need custom image generation for your project, please %s and we\'ll prioritize adding support for your use case.', 'botwriter'),
                    '<a href="https://wpbotwriter.com/log-a-support-ticket/" target="_blank"><strong>' . esc_html__('submit a support ticket', 'botwriter') . '</strong></a>'
                ); 
                ?>
            </p>
            <p class="bw-tip-box">
                <span class="dashicons dashicons-lightbulb bw-icon-primary"></span>
                <strong><?php esc_html_e('Tip:', 'botwriter'); ?></strong> 
                <?php esc_html_e('In the meantime, you can select "No Image Generation" to create posts without images, or use one of the cloud providers (DALL-E, Gemini, etc.) if you\'re not using Custom Provider for text.', 'botwriter'); ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Render "No Image Generation" settings
 * Called when 'none' is selected as image_provider
 */
function botwriter_render_none_settings($settings, $is_active) {
    ?>
    <div class="provider-config-section none-provider-section">
        <!-- Info box -->
        <div class="notice notice-info inline bw-notice-info-inline" style="margin-bottom: 20px;">
            <p>
                <span class="dashicons dashicons-info bw-icon-info"></span>
                <strong><?php esc_html_e('AI Image Generation Disabled', 'botwriter'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Posts will be created without featured images. You can add images manually after post creation.', 'botwriter'); ?>
            </p>
        </div>
        
        <!-- All Sources Images Promotion Banner -->
        <div class="bw-asi-promo-banner">
            <div class="bw-asi-promo-header">
                <span class="bw-asi-promo-icon">🖼️</span>
                <div class="bw-asi-promo-title">
                    <strong><?php esc_html_e('Want FREE Images for Your AI Posts?', 'botwriter'); ?></strong>
                    <span class="bw-asi-promo-badge"><?php esc_html_e('Recommended', 'botwriter'); ?></span>
                </div>
            </div>
            
            <div class="bw-asi-promo-content">
                <p class="bw-asi-promo-subtitle">
                    <?php esc_html_e('Use All Sources Images to automatically add images to BotWriter-generated posts:', 'botwriter'); ?>
                </p>
                
                <div class="bw-asi-promo-features">
                    <div class="bw-asi-feature-group">
                        <span class="bw-asi-feature-icon">📷</span>
                        <div>
                            <strong><?php esc_html_e('FREE Stock Photos', 'botwriter'); ?></strong>
                            <span><?php esc_html_e('Pexels, Unsplash, Pixabay, Flickr, Openverse', 'botwriter'); ?></span>
                        </div>
                    </div>
                    <div class="bw-asi-feature-group">
                        <span class="bw-asi-feature-icon">🤖</span>
                        <div>
                            <strong><?php esc_html_e('AI Generation', 'botwriter'); ?></strong>
                            <span><?php esc_html_e('DALL-E, Stable Diffusion, Gemini, Replicate', 'botwriter'); ?></span>
                        </div>
                    </div>
                    <div class="bw-asi-feature-group">
                        <span class="bw-asi-feature-icon">🎬</span>
                        <div>
                            <strong><?php esc_html_e('GIFs & Videos', 'botwriter'); ?></strong>
                            <span><?php esc_html_e('GIPHY, YouTube thumbnails', 'botwriter'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="bw-asi-promo-benefits">
                    <span><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Auto-generate on post publish', 'botwriter'); ?></span>
                    <span><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Bulk process existing posts', 'botwriter'); ?></span>
                    <span><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Insert images inside content', 'botwriter'); ?></span>
                </div>
            </div>
            
            <div class="bw-asi-promo-actions">
                <a href="<?php echo esc_url(admin_url('plugin-install.php?s=all+sources+images&tab=search&type=term')); ?>" class="button button-primary bw-asi-btn-install">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Install Free Plugin', 'botwriter'); ?>
                </a>
                <a href="https://wordpress.org/plugins/all-sources-images/" target="_blank" class="button bw-asi-btn-learn">
                    <?php esc_html_e('Learn More', 'botwriter'); ?> →
                </a>
            </div>
            
            <p class="bw-asi-promo-note">
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('Works perfectly with BotWriter! Auto-add images when posts are created.', 'botwriter'); ?>
            </p>
        </div>
    </div>
    <?php
}
