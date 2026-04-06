<?php
/**
 * BotWriter WooCommerce AI - Main Orchestrator
 *
 * Registers admin pages, enqueues assets and wires AJAX handlers
 * for the WooCommerce AI Content Optimizer feature.
 *
 * @package BotWriter
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BW_Woo_AI {

    /** Nonce action shared across all WooCommerce AI AJAX calls. */
    const NONCE_ACTION = 'bw_woo_ai_nonce';

    /** Capability required for all operations. */
    const CAPABILITY = 'manage_options';

    /** @var BW_Woo_AI_Products */
    public $products;

    /** @var BW_Woo_AI_Generator */
    public $generator;

    /** @var BW_Woo_AI_Preview */
    public $preview;

    /** @var BW_Woo_AI_History */
    public $history;

    /** @var BW_Woo_AI_API */
    public $api;

    /** @var BW_Woo_AI_Reviews */
    public $reviews;

    /**
     * Boot the module – called once from the main plugin file.
     */
    public function init() {
        // Bail early when WooCommerce is not active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $this->load_dependencies();

        $this->products  = new BW_Woo_AI_Products();
        $this->generator = new BW_Woo_AI_Generator();
        $this->preview   = new BW_Woo_AI_Preview();
        $this->history   = new BW_Woo_AI_History();
        $this->api       = new BW_Woo_AI_API();
        $this->reviews   = new BW_Woo_AI_Reviews();

        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        $this->register_ajax_handlers();
    }

    /* ------------------------------------------------------------------
     * File loading
     * ----------------------------------------------------------------*/

    private function load_dependencies() {
        $dir = BOTWRITER_PLUGIN_DIR . 'includes/woocommerce-ai/';
        require_once $dir . 'class-bw-woo-ai-products.php';
        require_once $dir . 'class-bw-woo-ai-generator.php';
        require_once $dir . 'class-bw-woo-ai-preview.php';
        require_once $dir . 'class-bw-woo-ai-history.php';
        require_once $dir . 'class-bw-woo-ai-api.php';
        require_once $dir . 'class-bw-woo-ai-reviews.php';
    }

    /* ------------------------------------------------------------------
     * Admin menu
     * ----------------------------------------------------------------*/

    public function register_menu() {
        add_submenu_page(
            'botwriter_menu',
            __( 'WooCommerce AI', 'botwriter' ),
            __( 'WooCommerce AI', 'botwriter' ),
            self::CAPABILITY,
            'botwriter_woo_ai',
            [ $this, 'render_page' ]
        );
    }

    /* ------------------------------------------------------------------
     * Page routing
     * ----------------------------------------------------------------*/

    public function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        // Simple tab routing via GET param.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab routing on admin page, no data modification.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'bulk';
        $allowed_tabs = [ 'bulk', 'categories', 'reviews', 'history', 'settings' ];
        if ( ! in_array( $tab, $allowed_tabs, true ) ) {
            $tab = 'bulk';
        }

        echo '<div class="wrap bw-woo-ai-wrap">';
        echo '<h1 class="wp-heading-inline" style="display:none;">WooCommerce AI</h1>';
        $this->render_header( $tab );

        switch ( $tab ) {
            case 'bulk':
                $this->render_bulk();
                break;
            case 'categories':
                $this->render_categories_page();
                break;
            case 'reviews':
                $this->reviews->render();
                break;
            case 'history':
                $this->history->render();
                break;
            case 'settings':
                $this->render_settings();
                break;
        }

        echo '</div>';
    }

    /* ------------------------------------------------------------------
     * Header / tab navigation
     * ----------------------------------------------------------------*/

    private function render_header( $current_tab ) {
        $base_url = admin_url( 'admin.php?page=botwriter_woo_ai' );
        $tabs = [
            'bulk'       => __( 'Bulk Optimizer', 'botwriter' ),
            'categories' => __( 'Categories', 'botwriter' ),
            'reviews'    => __( 'Reviews', 'botwriter' ),
            'history'    => __( 'History', 'botwriter' ),
            'settings'   => __( 'Settings', 'botwriter' ),
        ];
        ?>
        <div class="bw-woo-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px 30px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
            <h2 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 600; color: white;">
                <span style="margin-right: 8px;">🛒</span><?php esc_html_e( 'WooCommerce AI Content Optimizer', 'botwriter' ); ?>
            </h2>
            <p style="margin: 0; opacity: .9; font-size: 14px;"><?php esc_html_e( 'Optimize your product content in bulk with AI.', 'botwriter' ); ?></p>
        </div>

        <nav class="nav-tab-wrapper bw-woo-tabs" style="margin-bottom: 20px;">
            <?php foreach ( $tabs as $slug => $label ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
                   class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /* ------------------------------------------------------------------
     * Bulk Optimizer page (orchestrates full flow)
     * ----------------------------------------------------------------*/

    private function render_bulk() {
        ?>
        <div class="bw-woo-bulk-wrap">
            <!-- Step indicator -->
            <div class="bw-woo-steps" style="display:flex;gap:8px;margin-bottom:20px;">
                <span class="bw-step active" data-step="1">1. <?php esc_html_e( 'Select Products', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="2">2. <?php esc_html_e( 'Choose Fields', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="3">3. <?php esc_html_e( 'AI Provider', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="4">4. <?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></span>
            </div>

            <!-- Step 1: product selection table (loaded via AJAX) -->
            <div class="bw-bulk-step" id="bw-step-1">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Filter & Select Products', 'botwriter' ); ?></h3>
                    <?php $this->products->render_filter_bar(); ?>
                    <div id="bw-products-table-wrap">
                        <p class="description"><?php esc_html_e( 'Loading products…', 'botwriter' ); ?></p>
                    </div>
                    <button type="button" class="button button-primary" id="bw-bulk-next-1" disabled>
                        <?php esc_html_e( 'Next: Choose Fields →', 'botwriter' ); ?>
                    </button>
                </div>
            </div>

            <!-- Step 2: choose fields to generate -->
            <div class="bw-bulk-step" id="bw-step-2" style="display:none;">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Select Fields to Generate', 'botwriter' ); ?></h3>
                    <div class="bw-fields-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin:15px 0;">
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="title"> <?php esc_html_e( 'Product Title', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="description" checked> <?php esc_html_e( 'Product Description', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="short_description"> <?php esc_html_e( 'Short Description', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="tags"> <?php esc_html_e( 'Product Tags', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="review_summary"> <?php esc_html_e( 'Review Summary', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="alt_tags"> <?php esc_html_e( 'Image ALT Tags', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="seo_meta"> <?php esc_html_e( 'SEO Meta Description', 'botwriter' ); ?></label>
                        <label class="bw-field-option"><input type="checkbox" name="bw_fields[]" value="seo_title"> <?php esc_html_e( 'SEO Title', 'botwriter' ); ?></label>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-bulk-prev-2">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-bulk-next-2"><?php esc_html_e( 'Next: AI Provider →', 'botwriter' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Step 3: AI provider -->
            <div class="bw-bulk-step" id="bw-step-3" style="display:none;">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Choose AI Provider', 'botwriter' ); ?></h3>
                    <?php $this->render_provider_selector(); ?>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-bulk-prev-3">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-bulk-generate"><?php esc_html_e( 'Generate Content →', 'botwriter' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Step 4: preview & apply -->
            <div class="bw-bulk-step" id="bw-step-4" style="display:none;">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></h3>
                    <div id="bw-generation-progress" style="margin-bottom:15px;">
                        <div class="bw-progress-bar"><div class="bw-progress-fill" style="width:0%"></div></div>
                        <p class="bw-progress-text"><?php esc_html_e( 'Generating…', 'botwriter' ); ?> <span id="bw-progress-count">0/0</span></p>
                    </div>
                    <div id="bw-preview-results"></div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-bulk-prev-4" style="display:none;">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-bulk-apply" style="display:none;"><?php esc_html_e( 'Apply All Approved Changes', 'botwriter' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Categories Optimizer page (wizard flow)
     * ----------------------------------------------------------------*/

    private function render_categories_page() {
        ?>
        <div class="bw-woo-cat-wrap">
            <!-- Step indicator -->
            <div class="bw-woo-steps bw-cat-steps" style="display:flex;gap:8px;margin-bottom:20px;">
                <span class="bw-step active" data-step="c1">1. <?php esc_html_e( 'Select Categories', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="c2">2. <?php esc_html_e( 'AI Provider', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="c3">3. <?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></span>
            </div>

            <!-- Step 1: category selection -->
            <div class="bw-cat-step" id="bw-cat-step-1">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Select Categories to Optimize', 'botwriter' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'Choose which product categories should get AI-generated descriptions. The AI will use the category name and product titles for context.', 'botwriter' ); ?></p>
                    <div id="bw-categories-table-wrap">
                        <p class="description"><?php esc_html_e( 'Loading categories…', 'botwriter' ); ?></p>
                    </div>
                    <button type="button" class="button button-primary" id="bw-cat-next-1" disabled>
                        <?php esc_html_e( 'Next: AI Provider →', 'botwriter' ); ?>
                    </button>
                </div>
            </div>

            <!-- Step 2: provider -->
            <div class="bw-cat-step" id="bw-cat-step-2" style="display:none;">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Choose AI Provider', 'botwriter' ); ?></h3>
                    <?php $this->render_provider_selector(); ?>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-cat-prev-2">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-cat-generate"><?php esc_html_e( 'Generate Descriptions →', 'botwriter' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Step 3: preview & apply -->
            <div class="bw-cat-step" id="bw-cat-step-3" style="display:none;">
                <div class="bw-woo-card">
                    <h3><?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></h3>
                    <div id="bw-cat-generation-progress" style="margin-bottom:15px;">
                        <div class="bw-progress-bar"><div class="bw-cat-progress-fill bw-progress-fill" style="width:0%"></div></div>
                        <p class="bw-progress-text"><?php esc_html_e( 'Generating…', 'botwriter' ); ?> <span id="bw-cat-progress-count">0/0</span></p>
                    </div>
                    <div id="bw-cat-preview-results"></div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-cat-prev-3" style="display:none;">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-cat-apply" style="display:none;"><?php esc_html_e( 'Apply All Approved Changes', 'botwriter' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Provider selector (reuses existing plugin settings)
     * ----------------------------------------------------------------*/

    private function render_provider_selector() {
        $current = get_option( 'botwriter_text_provider', 'openai' );
        $providers = [
            'openai'     => 'OpenAI',
            'anthropic'  => 'Anthropic (Claude)',
            'google'     => 'Google (Gemini)',
            'mistral'    => 'Mistral',
            'groq'       => 'Groq',
            'openrouter' => 'OpenRouter',
        ];

        // Only show providers that have an API key configured.
        $available = [];
        foreach ( $providers as $key => $label ) {
            $api_key = function_exists( 'botwriter_get_provider_api_key' )
                ? botwriter_get_provider_api_key( $key )
                : '';
            if ( ! empty( $api_key ) ) {
                $available[ $key ] = $label;
            }
        }

        if ( empty( $available ) ) {
            echo '<div class="notice notice-warning inline"><p>';
            printf(
                /* translators: %s link to settings page */
                esc_html__( 'No AI provider configured. %s to set up an API key.', 'botwriter' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=botwriter_settings' ) ) . '">' . esc_html__( 'Go to Settings', 'botwriter' ) . '</a>'
            );
            echo '</p></div>';
            return;
        }
        ?>
        <div style="margin:15px 0;">
            <label for="bw-woo-provider"><strong><?php esc_html_e( 'Text AI Provider', 'botwriter' ); ?></strong></label><br>
            <select id="bw-woo-provider" class="form-select" style="min-width:250px;margin-top:5px;">
                <?php foreach ( $available as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>

            <div style="margin-top:10px;">
                <label for="bw-woo-language"><strong><?php esc_html_e( 'Output Language', 'botwriter' ); ?></strong></label><br>
                <select id="bw-woo-language" class="form-select" style="min-width:250px;margin-top:5px;">
                    <option value="auto"><?php esc_html_e( 'Same as original content', 'botwriter' ); ?></option>
                    <?php
                    global $botwriter_languages;
                    if ( ! empty( $botwriter_languages ) && is_array( $botwriter_languages ) ) {
                        foreach ( $botwriter_languages as $code => $name ) {
                            if ( $code === 'any' ) continue;
                            echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Settings sub-tab
     * ----------------------------------------------------------------*/

    private function render_settings() {
        $batch_size    = get_option( 'bw_woo_ai_batch_size', 5 );
        $request_delay = get_option( 'bw_woo_ai_request_delay', 2 );
        $saved_templates = get_option( 'bw_woo_ai_templates', [] );

        $template_labels = [
            'title'                  => __( 'Product Title', 'botwriter' ),
            'description'            => __( 'Product Description', 'botwriter' ),
            'short_description'      => __( 'Short Description', 'botwriter' ),
            'tags'                   => __( 'Product Tags', 'botwriter' ),
            'review_summary'         => __( 'Review Summary', 'botwriter' ),
            'alt_tags'               => __( 'Image ALT Tags', 'botwriter' ),
            'seo_meta'               => __( 'SEO Meta Description', 'botwriter' ),
            'seo_title'              => __( 'SEO Title', 'botwriter' ),
            'category_description'   => __( 'Category Description', 'botwriter' ),
        ];
        ?>
        <!-- General settings -->
        <div class="bw-woo-card">
            <h3><?php esc_html_e( 'General Settings', 'botwriter' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="bw-woo-batch"><?php esc_html_e( 'Batch Size', 'botwriter' ); ?></label></th>
                    <td>
                        <input type="number" id="bw-woo-batch" min="1" max="20" value="<?php echo esc_attr( $batch_size ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of products processed per AJAX request (1-20).', 'botwriter' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bw-woo-delay"><?php esc_html_e( 'Delay Between Requests', 'botwriter' ); ?></label></th>
                    <td>
                        <input type="number" id="bw-woo-delay" min="0" max="60" step="0.5" value="<?php echo esc_attr( $request_delay ); ?>" class="small-text">
                        <span><?php esc_html_e( 'seconds', 'botwriter' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Pause between each product generation to avoid API rate limits (0-60).', 'botwriter' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Prompt Templates -->
        <div class="bw-woo-card">
            <h3><?php esc_html_e( 'Prompt Templates', 'botwriter' ); ?></h3>
            <p class="description" style="margin-bottom:15px;">
                <?php esc_html_e( 'Customize the prompts sent to the AI for each field type. Use placeholders to insert product data dynamically.', 'botwriter' ); ?>
            </p>

            <?php foreach ( $template_labels as $field_key => $label ) :
                $default  = BW_Woo_AI_Generator::get_default_template( $field_key );
                $current  = ! empty( $saved_templates[ $field_key ] ) ? $saved_templates[ $field_key ] : $default;
            ?>
                <div class="bw-template-block" style="margin-bottom:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <label for="bw-tpl-<?php echo esc_attr( $field_key ); ?>">
                            <strong>📝 <?php echo esc_html( $label ); ?></strong>
                        </label>
                        <button type="button" class="button button-small bw-tpl-reset"
                                data-field="<?php echo esc_attr( $field_key ); ?>"
                                title="<?php esc_attr_e( 'Reset to default template', 'botwriter' ); ?>">
                            ↺ <?php esc_html_e( 'Reset', 'botwriter' ); ?>
                        </button>
                    </div>
                    <textarea id="bw-tpl-<?php echo esc_attr( $field_key ); ?>"
                              class="bw-tpl-textarea large-text code"
                              data-field="<?php echo esc_attr( $field_key ); ?>"
                              rows="10"
                              style="font-family:monospace;font-size:12px;line-height:1.5;"><?php echo esc_textarea( $current ); ?></textarea>
                    <textarea class="bw-tpl-default" data-field="<?php echo esc_attr( $field_key ); ?>"
                              style="display:none;"><?php echo esc_textarea( $default ); ?></textarea>
                </div>
            <?php endforeach; ?>

            <!-- Placeholder reference -->
            <div class="bw-woo-card" style="background:#f0f6ff;border:1px solid #c3dafe;margin-top:10px;">
                <h4 style="margin:0 0 8px 0;font-size:14px;">📋 <?php esc_html_e( 'Available Placeholders', 'botwriter' ); ?></h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:4px 20px;font-size:12px;font-family:monospace;">
                    <span><code>{{product_name}}</code> — <?php esc_html_e( 'Product title', 'botwriter' ); ?></span>
                    <span><code>{{description}}</code> — <?php esc_html_e( 'Full description (HTML)', 'botwriter' ); ?></span>
                    <span><code>{{short_description}}</code> — <?php esc_html_e( 'Short description', 'botwriter' ); ?></span>
                    <span><code>{{description_excerpt}}</code> — <?php esc_html_e( 'Description (200 words, plain text)', 'botwriter' ); ?></span>
                    <span><code>{{categories}}</code> — <?php esc_html_e( 'Category names', 'botwriter' ); ?></span>
                    <span><code>{{price}}</code> — <?php esc_html_e( 'Product price', 'botwriter' ); ?></span>
                    <span><code>{{sku}}</code> — <?php esc_html_e( 'Product SKU', 'botwriter' ); ?></span>
                    <span><code>{{attributes}}</code> — <?php esc_html_e( 'Product attributes', 'botwriter' ); ?></span>
                    <span><code>{{tags}}</code> — <?php esc_html_e( 'Existing tags', 'botwriter' ); ?></span>
                    <span><code>{{reviews}}</code> — <?php esc_html_e( 'Customer reviews text', 'botwriter' ); ?></span>
                    <span><code>{{language_instruction}}</code> — <?php esc_html_e( 'Auto language rule', 'botwriter' ); ?></span>
                    <span><code>{{category_name}}</code> — <?php esc_html_e( 'Category name (categories)', 'botwriter' ); ?></span>
                    <span><code>{{parent_category}}</code> — <?php esc_html_e( 'Parent category name', 'botwriter' ); ?></span>
                    <span><code>{{product_titles}}</code> — <?php esc_html_e( 'Product titles in category', 'botwriter' ); ?></span>
                    <span><code>{{current_description}}</code> — <?php esc_html_e( 'Current category description', 'botwriter' ); ?></span>
                    <span><code>{{product_count}}</code> — <?php esc_html_e( 'Number of products in category', 'botwriter' ); ?></span>
                </div>
                <p style="margin:10px 0 0 0;font-size:12px;color:#555;">
                    <strong><?php esc_html_e( 'Conditionals:', 'botwriter' ); ?></strong>
                    <code>{{#if categories}}...{{/if}}</code> — <?php esc_html_e( 'Only includes the block if the field has data.', 'botwriter' ); ?>
                </p>
            </div>
        </div>

        <div style="margin-top:15px;">
            <button type="button" class="button button-primary" id="bw-woo-save-settings"><?php esc_html_e( 'Save Settings', 'botwriter' ); ?></button>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Asset enqueue
     * ----------------------------------------------------------------*/

    public function enqueue_assets() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'botwriter_woo_ai' ) === false ) {
            return;
        }

        $base_url = BOTWRITER_URL;
        $base_dir = BOTWRITER_PLUGIN_DIR;

        wp_enqueue_style(
            'bw-woo-ai-css',
            $base_url . 'assets/css/woo-ai.css',
            [],
            file_exists( $base_dir . 'assets/css/woo-ai.css' ) ? filemtime( $base_dir . 'assets/css/woo-ai.css' ) : BOTWRITER_VERSION
        );

        wp_enqueue_script(
            'bw-woo-ai-js',
            $base_url . 'assets/js/woo-ai.js',
            [ 'jquery' ],
            file_exists( $base_dir . 'assets/js/woo-ai.js' ) ? filemtime( $base_dir . 'assets/js/woo-ai.js' ) : BOTWRITER_VERSION,
            true
        );

        wp_localize_script( 'bw-woo-ai-js', 'bw_woo_ai', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
            'batch_size'    => absint( get_option( 'bw_woo_ai_batch_size', 5 ) ),
            'request_delay' => floatval( get_option( 'bw_woo_ai_request_delay', 2 ) ),
            'i18n'          => [
                'generating'   => __( 'Generating…', 'botwriter' ),
                'done'         => __( 'Done!', 'botwriter' ),
                'error'        => __( 'Error', 'botwriter' ),
                'no_selection' => __( 'Please select at least one product.', 'botwriter' ),
                'no_fields'    => __( 'Please select at least one field to generate.', 'botwriter' ),
                'confirm_apply'=> __( 'Apply changes to all approved products?', 'botwriter' ),
                'applied'      => __( 'Changes applied successfully.', 'botwriter' ),
                'reverted'     => __( 'Changes reverted successfully.', 'botwriter' ),
                'approve'      => __( 'Approve', 'botwriter' ),
                'reject'       => __( 'Reject', 'botwriter' ),
                'edit'         => __( 'Edit', 'botwriter' ),
                'original'     => __( 'Original', 'botwriter' ),
                'ai_generated' => __( 'AI Generated', 'botwriter' ),
                'loading'      => __( 'Loading…', 'botwriter' ),
                'no_products'  => __( 'No products found.', 'botwriter' ),
                'selected'     => __( 'selected', 'botwriter' ),
                'select_all'   => __( 'Select All', 'botwriter' ),
                'saved'        => __( 'Settings saved.', 'botwriter' ),
                'rev_generating'     => __( 'Generating reviews…', 'botwriter' ),
                'rev_done'           => __( 'Reviews generated successfully!', 'botwriter' ),
                'rev_error'          => __( 'Error generating reviews.', 'botwriter' ),
                'rev_no_products'    => __( 'Please select at least one product.', 'botwriter' ),
                'rev_settings_saved' => __( 'Review settings saved.', 'botwriter' ),
                'rev_deleted'        => __( 'AI reviews deleted.', 'botwriter' ),
                'rev_confirm_delete' => __( 'Delete all AI-generated reviews for this product?', 'botwriter' ),
            ],
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX registration
     * ----------------------------------------------------------------*/

    private function register_ajax_handlers() {
        $actions = [
            'bw_woo_ai_get_products'      => [ $this->products,  'ajax_get_products' ],
            'bw_woo_ai_get_all_product_ids'=> [ $this->products,  'ajax_get_all_product_ids' ],
            'bw_woo_ai_get_categories'     => [ $this->products,  'ajax_get_categories' ],
            'bw_woo_ai_generate'           => [ $this->generator, 'ajax_generate' ],
            'bw_woo_ai_apply'              => [ $this->preview,   'ajax_apply' ],
            'bw_woo_ai_revert'             => [ $this->history,   'ajax_revert' ],
            'bw_woo_ai_get_history'        => [ $this->history,   'ajax_get_history' ],
            'bw_woo_ai_get_diff'           => [ $this->history,   'ajax_get_diff' ],
            'bw_woo_ai_revert_field'       => [ $this->history,   'ajax_revert_field' ],
            'bw_woo_ai_save_settings'      => [ $this, 'ajax_save_settings' ],
            'bw_woo_ai_generate_single'    => [ $this->generator, 'ajax_generate_single' ],
            'bw_woo_ai_generate_category'  => [ $this->generator, 'ajax_generate_category' ],
            'bw_woo_ai_apply_categories'   => [ $this->preview,   'ajax_apply_categories' ],
            'bw_woo_ai_get_category_list'  => [ $this->products,  'ajax_get_category_list' ],
            'bw_woo_ai_save_review_settings' => [ $this->reviews, 'ajax_save_review_settings' ],
            'bw_woo_ai_generate_reviews'     => [ $this->reviews, 'ajax_generate_reviews' ],
            'bw_woo_ai_apply_reviews'        => [ $this->reviews, 'ajax_apply_reviews' ],
            'bw_woo_ai_delete_ai_reviews'    => [ $this->reviews, 'ajax_delete_ai_reviews' ],
        ];

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", $callback );
        }
    }

    /* ------------------------------------------------------------------
     * Settings AJAX
     * ----------------------------------------------------------------*/

    public function ajax_save_settings() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $batch = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 5;
        $batch = max( 1, min( 20, $batch ) );
        update_option( 'bw_woo_ai_batch_size', $batch );

        $delay = isset( $_POST['request_delay'] ) ? floatval( $_POST['request_delay'] ) : 2;
        $delay = max( 0, min( 60, $delay ) );
        update_option( 'bw_woo_ai_request_delay', $delay );

        // Save prompt templates.
        $template_fields = BW_Woo_AI_Generator::TEMPLATE_FIELDS;
        $templates = get_option( 'bw_woo_ai_templates', [] );
        foreach ( $template_fields as $f ) {
            $key = 'template_' . $f;
            if ( isset( $_POST[ $key ] ) ) {
                $templates[ $f ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
            }
        }
        update_option( 'bw_woo_ai_templates', $templates );

        wp_send_json_success( [ 'batch_size' => $batch, 'request_delay' => $delay ] );
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    /**
     * Verify AJAX nonce + capability. wp_send_json_error on failure.
     */
    public static function verify_request() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
    }
}
