<?php
/**
 * Stock Photo (Image Banks) Provider Settings
 *
 * Renders configuration for free stock image banks:
 * Pixabay, Pexels, Unsplash, Openverse.
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Stock Photo settings
 * Called when 'stockphoto' is selected as image_provider
 */
function botwriter_render_stockphoto_settings($settings, $is_active) {
    $preferred = get_option('botwriter_stockphoto_preferred', 'pixabay');
    $selection = get_option('botwriter_stockphoto_selection', 'random_top10');
    $attribution = get_option('botwriter_stockphoto_attribution', 'caption');
    ?>
    <div class="provider-config-section stockphoto-provider-section">

        <!-- Info banner -->
        <div class="notice notice-success inline bw-notice-info-inline" style="margin-bottom: 20px;">
            <p>
                <span class="dashicons dashicons-camera bw-icon-info"></span>
                <strong><?php esc_html_e('Free Stock Images', 'botwriter'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Uses free image banks instead of AI generation. No API keys needed — images are sourced from Pixabay, Pexels, Unsplash, and Openverse with automatic fallback.', 'botwriter'); ?>
            </p>
        </div>

        <div class="settings-grid-2col">
            <!-- Preferred Bank -->
            <div class="setting-card">
                <div class="form-row">
                    <label><span class="dashicons dashicons-images-alt2"></span> <?php esc_html_e('Preferred Image Bank:', 'botwriter'); ?></label>
                    <select name="botwriter_stockphoto_preferred" class="form-select botwriter-autosave" id="stockphoto_preferred">
                        <option value="pixabay" <?php selected($preferred, 'pixabay'); ?>>📷 Pixabay</option>
                        <option value="pexels" <?php selected($preferred, 'pexels'); ?>>📸 Pexels</option>
                        <option value="unsplash" <?php selected($preferred, 'unsplash'); ?>>🏞️ Unsplash</option>
                        <option value="openverse" <?php selected($preferred, 'openverse'); ?>>🌐 Openverse (Creative Commons)</option>
                    </select>
                </div>
                <p class="description">
                    <?php esc_html_e('The system will search this bank first. If no results are found, it automatically tries the other banks.', 'botwriter'); ?>
                </p>
                <div class="bank-details" style="margin-top: 12px;">
                    <table class="specs-table">
                        <tr><td><strong>Pixabay</strong></td><td><?php esc_html_e('Large library, no attribution required', 'botwriter'); ?></td></tr>
                        <tr><td><strong>Pexels</strong></td><td><?php esc_html_e('High quality photos, no attribution required', 'botwriter'); ?></td></tr>
                        <tr><td><strong>Unsplash</strong></td><td><?php esc_html_e('Professional photos, no attribution required', 'botwriter'); ?></td></tr>
                        <tr><td><strong>Openverse</strong></td><td><?php esc_html_e('CC licensed, attribution may be required', 'botwriter'); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Selection Mode -->
            <div class="setting-card">
                <div class="form-row">
                    <label><span class="dashicons dashicons-randomize"></span> <?php esc_html_e('Image Selection:', 'botwriter'); ?></label>
                    <select name="botwriter_stockphoto_selection" class="form-select botwriter-autosave" id="stockphoto_selection">
                        <option value="first" <?php selected($selection, 'first'); ?>>🎯 <?php esc_html_e('Most Relevant (first result)', 'botwriter'); ?></option>
                        <option value="random_top5" <?php selected($selection, 'random_top5'); ?>>🎲 <?php esc_html_e('Random from Top 5', 'botwriter'); ?></option>
                        <option value="random_top10" <?php selected($selection, 'random_top10'); ?>>🎲 <?php esc_html_e('Random from Top 10 (recommended)', 'botwriter'); ?></option>
                    </select>
                </div>
                <p class="description">
                    <?php esc_html_e('How to pick an image from the search results. Random selection adds variety across posts with similar topics.', 'botwriter'); ?>
                </p>
            </div>
        </div>

        <!-- Attribution Settings -->
        <div class="settings-grid-2col" style="margin-top: 20px;">
            <div class="setting-card">
                <div class="form-row">
                    <label><span class="dashicons dashicons-editor-quote"></span> <?php esc_html_e('Image Attribution:', 'botwriter'); ?></label>
                    <select name="botwriter_stockphoto_attribution" class="form-select botwriter-autosave" id="stockphoto_attribution">
                        <option value="caption" <?php selected($attribution, 'caption'); ?>>📝 <?php esc_html_e('Image Caption (recommended)', 'botwriter'); ?></option>
                        <option value="content_footer" <?php selected($attribution, 'content_footer'); ?>>📄 <?php esc_html_e('Post Content Footer', 'botwriter'); ?></option>
                        <option value="disabled" <?php selected($attribution, 'disabled'); ?>>🚫 <?php esc_html_e('Disabled (no attribution)', 'botwriter'); ?></option>
                    </select>
                </div>
                <p class="description">
                    <?php esc_html_e('Where to display photographer credit. Caption stores it in the image metadata. Footer appends it to the post content.', 'botwriter'); ?>
                </p>
            </div>

            <div class="setting-card">
                <div class="notice notice-warning inline bw-notice-info-inline" style="margin: 0;">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php esc_html_e('Attribution Notice', 'botwriter'); ?></strong>
                    </p>
                    <p>
                        <?php esc_html_e('Pixabay, Pexels, and Unsplash do NOT require attribution. However, Openverse images may use CC-BY/CC-BY-SA licenses that legally require credit. Disabling attribution may carry legal risk for Openverse images.', 'botwriter'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div class="info-tip highlight-tip" style="margin-top: 20px;">
            <span class="dashicons dashicons-lightbulb"></span>
            <div>
                <p><strong><?php esc_html_e('💡 How it works:', 'botwriter'); ?></strong></p>
                <p><?php esc_html_e('The AI generates a short English keyword phrase describing the article\'s ideal image (e.g., "sunset beach tropical palm trees"). This phrase is used to search the image bank. The system automatically falls back to other banks if no results are found.', 'botwriter'); ?></p>
            </div>
        </div>

        <!-- Cost comparison -->
        <div class="cost-comparison" style="margin-top: 20px;">
            <h5><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Cost per Image', 'botwriter'); ?></h5>
            <div class="cost-cards">
                <div class="cost-card cheapest">
                    <span class="provider">Pixabay</span>
                    <span class="cost">$0.00</span>
                    <span class="badge"><?php esc_html_e('FREE', 'botwriter'); ?></span>
                </div>
                <div class="cost-card cheapest">
                    <span class="provider">Pexels</span>
                    <span class="cost">$0.00</span>
                    <span class="badge"><?php esc_html_e('FREE', 'botwriter'); ?></span>
                </div>
                <div class="cost-card cheapest">
                    <span class="provider">Unsplash</span>
                    <span class="cost">$0.00</span>
                    <span class="badge"><?php esc_html_e('FREE', 'botwriter'); ?></span>
                </div>
                <div class="cost-card cheapest">
                    <span class="provider">Openverse</span>
                    <span class="cost">$0.00</span>
                    <span class="badge"><?php esc_html_e('FREE', 'botwriter'); ?></span>
                </div>
            </div>
        </div>

    </div>
    <?php
}
