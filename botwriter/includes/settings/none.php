<?php
/**
 * "No Image Generation" Settings
 *
 * Shows informational notice and promotes All Sources Images plugin
 * when the user disables AI image generation.
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
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
