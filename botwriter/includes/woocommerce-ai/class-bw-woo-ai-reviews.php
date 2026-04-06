<?php
/**
 * BotWriter WooCommerce AI - Reviews Generator
 *
 * Generates realistic AI-powered product reviews (comments) for
 * WooCommerce products. Lets the admin configure tone, length,
 * rating distribution, scheduling and more.
 *
 * @package BotWriter
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BW_Woo_AI_Reviews {

    /** Option key for review generator settings. */
    const OPTION_KEY = 'bw_woo_ai_review_settings';

    /* ------------------------------------------------------------------
     * Default settings
     * ----------------------------------------------------------------*/

    public static function get_defaults() {
        return [
            // Number of reviews
            'reviews_mode'     => 'range',   // 'fixed' | 'range'
            'reviews_fixed'    => 5,
            'reviews_min'      => 3,
            'reviews_max'      => 8,

            // Rating distribution (must sum to 100)
            'rating_5_pct'     => 60,
            'rating_4_pct'     => 25,
            'rating_3_pct'     => 10,
            'rating_2_pct'     => 5,
            'rating_1_pct'     => 0,

            // Review length
            'length_mode'      => 'mixed',   // 'short' | 'medium' | 'long' | 'mixed'
            'length_short_pct' => 40,        // Only used in 'mixed' mode
            'length_medium_pct'=> 40,
            'length_long_pct'  => 20,

            // Content style
            'tone'             => 'natural',  // 'natural' | 'enthusiastic' | 'professional' | 'casual'
            'include_cons'     => 'sometimes', // 'never' | 'sometimes' | 'often'
            'reviewer_names'   => 'auto',     // 'auto' (AI-generated) | 'custom'
            'custom_names'     => '',          // Comma-separated list when 'custom'

            // Scheduling
            'date_mode'        => 'spread',   // 'now' | 'spread'
            'date_spread_days' => 90,         // Spread reviews over this many past days

            // Generation
            'language'         => 'auto',
            'mark_verified'    => 'no',       // 'yes' | 'no'
        ];
    }

    /* ------------------------------------------------------------------
     * Get merged settings (saved + defaults)
     * ----------------------------------------------------------------*/

    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $saved, self::get_defaults() );
    }

    /* ------------------------------------------------------------------
     * Render the Reviews tab
     * ----------------------------------------------------------------*/

    public function render() {
        $s = self::get_settings();
        global $botwriter_languages;
        ?>
        <div class="bw-woo-reviews-wrap">

            <!-- Step indicator -->
            <div class="bw-woo-steps bw-rev-steps" style="display:flex;gap:8px;margin-bottom:20px;">
                <span class="bw-step active" data-step="r1">1. <?php esc_html_e( 'Select Products', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="r2">2. <?php esc_html_e( 'Review Settings', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="r3">3. <?php esc_html_e( 'AI Provider', 'botwriter' ); ?></span>
                <span class="bw-step" data-step="r4">4. <?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></span>
            </div>

            <!-- ============================================================
                 STEP 1: Filter & Select Products
                 ============================================================ -->
            <div class="bw-rev-step" id="bw-rev-step-1">
                <div class="bw-woo-card">
                    <h3>📦 <?php esc_html_e( 'Filter & Select Products', 'botwriter' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'Choose which products should receive AI-generated reviews.', 'botwriter' ); ?></p>

                    <!-- Review-specific filter bar -->
                    <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:2px;"><?php esc_html_e( 'Review Filter', 'botwriter' ); ?></label>
                            <select id="bw-rev-filter" style="min-width:180px;">
                                <option value=""><?php esc_html_e( 'All products', 'botwriter' ); ?></option>
                                <option value="no_reviews"><?php esc_html_e( 'No reviews yet', 'botwriter' ); ?></option>
                                <option value="few_reviews"><?php esc_html_e( 'Less than N reviews', 'botwriter' ); ?></option>
                                <option value="low_rating"><?php esc_html_e( 'Average rating below N', 'botwriter' ); ?></option>
                            </select>
                        </div>
                        <div id="bw-rev-filter-n-wrap" style="display:none;">
                            <label style="font-size:12px;display:block;margin-bottom:2px;"><?php esc_html_e( 'Threshold', 'botwriter' ); ?></label>
                            <input type="number" id="bw-rev-filter-n" min="1" max="100" value="5" class="small-text" style="width:70px;">
                        </div>
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:2px;"><?php esc_html_e( 'Category', 'botwriter' ); ?></label>
                            <select id="bw-rev-category" style="min-width:150px;">
                                <option value=""><?php esc_html_e( 'All categories', 'botwriter' ); ?></option>
                            </select>
                        </div>
                        <div style="flex:1;min-width:200px;">
                            <label style="font-size:12px;display:block;margin-bottom:2px;"><?php esc_html_e( 'Search', 'botwriter' ); ?></label>
                            <input type="text" id="bw-rev-search" placeholder="<?php esc_attr_e( 'Search products…', 'botwriter' ); ?>" class="regular-text" style="width:100%;">
                        </div>
                    </div>

                    <div id="bw-rev-products-wrap">
                        <p class="description"><?php esc_html_e( 'Loading products…', 'botwriter' ); ?></p>
                    </div>

                    <button type="button" class="button button-primary" id="bw-rev-next-1" disabled>
                        <?php esc_html_e( 'Next: Review Settings →', 'botwriter' ); ?>
                    </button>
                </div>
            </div>

            <!-- ============================================================
                 STEP 2: Review Settings
                 ============================================================ -->
            <div class="bw-rev-step" id="bw-rev-step-2" style="display:none;">
                <div class="bw-woo-card">
                    <h3>⚙️ <?php esc_html_e( 'Review Settings', 'botwriter' ); ?></h3>

                    <!-- Number of reviews -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">📊 <?php esc_html_e( 'Number of Reviews', 'botwriter' ); ?></legend>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="bw_rev_mode" value="fixed" <?php checked( $s['reviews_mode'], 'fixed' ); ?>>
                            <?php esc_html_e( 'Fixed:', 'botwriter' ); ?>
                            <input type="number" id="bw-rev-fixed" min="1" max="50" value="<?php echo esc_attr( $s['reviews_fixed'] ); ?>" class="small-text">
                            <?php esc_html_e( 'reviews per product', 'botwriter' ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="bw_rev_mode" value="range" <?php checked( $s['reviews_mode'], 'range' ); ?>>
                            <?php esc_html_e( 'Random between', 'botwriter' ); ?>
                            <input type="number" id="bw-rev-min" min="1" max="50" value="<?php echo esc_attr( $s['reviews_min'] ); ?>" class="small-text">
                            <?php esc_html_e( 'and', 'botwriter' ); ?>
                            <input type="number" id="bw-rev-max" min="1" max="50" value="<?php echo esc_attr( $s['reviews_max'] ); ?>" class="small-text">
                        </label>
                    </fieldset>

                    <!-- Rating distribution -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">⭐ <?php esc_html_e( 'Rating Distribution', 'botwriter' ); ?></legend>
                        <p class="description" style="margin-top:0;"><?php esc_html_e( 'Percentage of reviews for each star rating (should sum to 100%).', 'botwriter' ); ?></p>
                        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;text-align:center;">
                            <?php for ( $star = 5; $star >= 1; $star-- ) :
                                $key = "rating_{$star}_pct";
                            ?>
                                <div>
                                    <label style="font-size:13px;">
                                        <?php echo esc_html( str_repeat( '⭐', $star ) ); ?><br>
                                        <input type="number" id="bw-rev-r<?php echo esc_attr( $star ); ?>" min="0" max="100"
                                               value="<?php echo esc_attr( $s[ $key ] ); ?>"
                                               class="small-text bw-rev-rating-pct" data-star="<?php echo esc_attr( $star ); ?>"
                                               style="width:60px;text-align:center;">%
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <p class="bw-rev-rating-total" style="text-align:right;font-size:12px;margin:6px 0 0;"></p>
                    </fieldset>

                    <!-- Review length -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">📏 <?php esc_html_e( 'Review Length', 'botwriter' ); ?></legend>
                        <select id="bw-rev-length-mode" style="width:100%;margin-bottom:8px;">
                            <option value="short" <?php selected( $s['length_mode'], 'short' ); ?>><?php esc_html_e( 'All Short (1-2 sentences)', 'botwriter' ); ?></option>
                            <option value="medium" <?php selected( $s['length_mode'], 'medium' ); ?>><?php esc_html_e( 'All Medium (2-4 sentences)', 'botwriter' ); ?></option>
                            <option value="long" <?php selected( $s['length_mode'], 'long' ); ?>><?php esc_html_e( 'All Long (paragraph)', 'botwriter' ); ?></option>
                            <option value="mixed" <?php selected( $s['length_mode'], 'mixed' ); ?>><?php esc_html_e( 'Mixed (realistic variety)', 'botwriter' ); ?></option>
                        </select>
                        <div id="bw-rev-length-mix" style="<?php echo $s['length_mode'] !== 'mixed' ? 'display:none;' : ''; ?>display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center;">
                            <label>
                                <?php esc_html_e( 'Short', 'botwriter' ); ?><br>
                                <input type="number" id="bw-rev-len-short" min="0" max="100" value="<?php echo esc_attr( $s['length_short_pct'] ); ?>" class="small-text" style="width:60px;">%
                            </label>
                            <label>
                                <?php esc_html_e( 'Medium', 'botwriter' ); ?><br>
                                <input type="number" id="bw-rev-len-medium" min="0" max="100" value="<?php echo esc_attr( $s['length_medium_pct'] ); ?>" class="small-text" style="width:60px;">%
                            </label>
                            <label>
                                <?php esc_html_e( 'Long', 'botwriter' ); ?><br>
                                <input type="number" id="bw-rev-len-long" min="0" max="100" value="<?php echo esc_attr( $s['length_long_pct'] ); ?>" class="small-text" style="width:60px;">%
                            </label>
                        </div>
                    </fieldset>

                    <!-- Content style -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">🎭 <?php esc_html_e( 'Content Style', 'botwriter' ); ?></legend>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <label for="bw-rev-tone"><strong><?php esc_html_e( 'Tone', 'botwriter' ); ?></strong></label>
                                <select id="bw-rev-tone" style="width:100%;">
                                    <option value="natural" <?php selected( $s['tone'], 'natural' ); ?>><?php esc_html_e( 'Natural', 'botwriter' ); ?></option>
                                    <option value="enthusiastic" <?php selected( $s['tone'], 'enthusiastic' ); ?>><?php esc_html_e( 'Enthusiastic', 'botwriter' ); ?></option>
                                    <option value="professional" <?php selected( $s['tone'], 'professional' ); ?>><?php esc_html_e( 'Professional', 'botwriter' ); ?></option>
                                    <option value="casual" <?php selected( $s['tone'], 'casual' ); ?>><?php esc_html_e( 'Casual', 'botwriter' ); ?></option>
                                </select>
                            </div>
                            <div>
                                <label for="bw-rev-cons"><strong><?php esc_html_e( 'Include minor cons', 'botwriter' ); ?></strong></label>
                                <select id="bw-rev-cons" style="width:100%;">
                                    <option value="never" <?php selected( $s['include_cons'], 'never' ); ?>><?php esc_html_e( 'Never', 'botwriter' ); ?></option>
                                    <option value="sometimes" <?php selected( $s['include_cons'], 'sometimes' ); ?>><?php esc_html_e( 'Sometimes (more realistic)', 'botwriter' ); ?></option>
                                    <option value="often" <?php selected( $s['include_cons'], 'often' ); ?>><?php esc_html_e( 'Often', 'botwriter' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Reviewer names -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">👤 <?php esc_html_e( 'Reviewer Names', 'botwriter' ); ?></legend>
                        <select id="bw-rev-names" style="width:100%;margin-bottom:8px;">
                            <option value="auto" <?php selected( $s['reviewer_names'], 'auto' ); ?>><?php esc_html_e( 'Auto-generate realistic names', 'botwriter' ); ?></option>
                            <option value="custom" <?php selected( $s['reviewer_names'], 'custom' ); ?>><?php esc_html_e( 'Use custom name list', 'botwriter' ); ?></option>
                        </select>
                        <div id="bw-rev-custom-names-wrap" style="<?php echo $s['reviewer_names'] !== 'custom' ? 'display:none;' : ''; ?>">
                            <textarea id="bw-rev-custom-names" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'John D., Maria G., Alex P., Sarah M. (comma-separated)', 'botwriter' ); ?>"><?php echo esc_textarea( $s['custom_names'] ); ?></textarea>
                        </div>
                    </fieldset>

                    <!-- Date & scheduling -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">📅 <?php esc_html_e( 'Review Dates', 'botwriter' ); ?></legend>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="bw_rev_date" value="now" <?php checked( $s['date_mode'], 'now' ); ?>>
                            <?php esc_html_e( 'All dated today', 'botwriter' ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="bw_rev_date" value="spread" <?php checked( $s['date_mode'], 'spread' ); ?>>
                            <?php esc_html_e( 'Spread randomly over the last', 'botwriter' ); ?>
                            <input type="number" id="bw-rev-spread-days" min="1" max="365" value="<?php echo esc_attr( $s['date_spread_days'] ); ?>" class="small-text">
                            <?php esc_html_e( 'days', 'botwriter' ); ?>
                        </label>
                    </fieldset>

                    <!-- Extra options -->
                    <fieldset style="margin-bottom:18px;padding:12px;border:1px solid #ddd;border-radius:8px;">
                        <legend style="font-weight:600;padding:0 6px;">🔧 <?php esc_html_e( 'Extra Options', 'botwriter' ); ?></legend>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" id="bw-rev-verified" <?php checked( $s['mark_verified'], 'yes' ); ?>>
                            <?php esc_html_e( 'Mark reviews as "Verified Owner"', 'botwriter' ); ?>
                        </label>
                    </fieldset>

                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-rev-prev-2">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-rev-next-2"><?php esc_html_e( 'Next: AI Provider →', 'botwriter' ); ?></button>
                        <button type="button" class="button" id="bw-rev-save-settings" style="margin-left:auto;">
                            💾 <?php esc_html_e( 'Save Settings', 'botwriter' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 STEP 3: AI Provider & Language
                 ============================================================ -->
            <div class="bw-rev-step" id="bw-rev-step-3" style="display:none;">
                <div class="bw-woo-card">
                    <h3>🤖 <?php esc_html_e( 'AI Provider & Language', 'botwriter' ); ?></h3>
                    <?php
                    $current   = get_option( 'botwriter_text_provider', 'openai' );
                    $providers = [
                        'openai'     => 'OpenAI',
                        'anthropic'  => 'Anthropic (Claude)',
                        'google'     => 'Google (Gemini)',
                        'mistral'    => 'Mistral',
                        'groq'       => 'Groq',
                        'openrouter' => 'OpenRouter',
                    ];
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
                            /* translators: %s: link to settings page */
                            esc_html__( 'No AI provider configured. %s to set up an API key.', 'botwriter' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=botwriter_settings' ) ) . '">' . esc_html__( 'Go to Settings', 'botwriter' ) . '</a>'
                        );
                        echo '</p></div>';
                    } else {
                    ?>
                    <div style="margin:15px 0;">
                        <label for="bw-rev-provider"><strong><?php esc_html_e( 'Text AI Provider', 'botwriter' ); ?></strong></label><br>
                        <select id="bw-rev-provider" class="form-select" style="min-width:250px;margin-top:5px;">
                            <?php foreach ( $available as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div style="margin-top:10px;">
                            <label for="bw-rev-language"><strong><?php esc_html_e( 'Output Language', 'botwriter' ); ?></strong></label><br>
                            <select id="bw-rev-language" class="form-select" style="min-width:250px;margin-top:5px;">
                                <option value="auto" <?php selected( $s['language'], 'auto' ); ?>><?php esc_html_e( 'Same as product content', 'botwriter' ); ?></option>
                                <?php
                                if ( ! empty( $botwriter_languages ) && is_array( $botwriter_languages ) ) {
                                    foreach ( $botwriter_languages as $code => $name ) {
                                        if ( $code === 'any' ) continue;
                                        echo '<option value="' . esc_attr( $code ) . '" ' . selected( $s['language'], $code, false ) . '>' . esc_html( $name ) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <?php } ?>

                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-rev-prev-3">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-rev-generate">
                            🚀 <?php esc_html_e( 'Generate Reviews →', 'botwriter' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 STEP 4: Preview & Apply
                 ============================================================ -->
            <div class="bw-rev-step" id="bw-rev-step-4" style="display:none;">
                <div class="bw-woo-card">
                    <h3>👁️ <?php esc_html_e( 'Preview & Apply', 'botwriter' ); ?></h3>
                    <div id="bw-rev-progress" style="margin-bottom:15px;">
                        <div class="bw-progress-bar"><div class="bw-progress-fill bw-rev-progress-fill" style="width:0%"></div></div>
                        <p class="bw-progress-text"><?php esc_html_e( 'Generating reviews…', 'botwriter' ); ?> <span id="bw-rev-progress-count">0/0</span></p>
                    </div>
                    <div id="bw-rev-preview-results"></div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="button" id="bw-rev-prev-4" style="display:none;">← <?php esc_html_e( 'Back', 'botwriter' ); ?></button>
                        <button type="button" class="button button-primary" id="bw-rev-apply" style="display:none;">
                            ✅ <?php esc_html_e( 'Apply All Approved Reviews', 'botwriter' ); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * AJAX: Save review settings
     * ----------------------------------------------------------------*/

    public function ajax_save_review_settings() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $s = [];
        $s['reviews_mode']      = isset( $_POST['reviews_mode'] ) ? sanitize_key( $_POST['reviews_mode'] ) : 'range';
        $s['reviews_fixed']     = isset( $_POST['reviews_fixed'] ) ? absint( $_POST['reviews_fixed'] ) : 5;
        $s['reviews_min']       = isset( $_POST['reviews_min'] ) ? absint( $_POST['reviews_min'] ) : 3;
        $s['reviews_max']       = isset( $_POST['reviews_max'] ) ? absint( $_POST['reviews_max'] ) : 8;

        $s['rating_5_pct']      = isset( $_POST['rating_5_pct'] ) ? absint( $_POST['rating_5_pct'] ) : 60;
        $s['rating_4_pct']      = isset( $_POST['rating_4_pct'] ) ? absint( $_POST['rating_4_pct'] ) : 25;
        $s['rating_3_pct']      = isset( $_POST['rating_3_pct'] ) ? absint( $_POST['rating_3_pct'] ) : 10;
        $s['rating_2_pct']      = isset( $_POST['rating_2_pct'] ) ? absint( $_POST['rating_2_pct'] ) : 5;
        $s['rating_1_pct']      = isset( $_POST['rating_1_pct'] ) ? absint( $_POST['rating_1_pct'] ) : 0;

        $s['length_mode']       = isset( $_POST['length_mode'] ) ? sanitize_key( $_POST['length_mode'] ) : 'mixed';
        $s['length_short_pct']  = isset( $_POST['length_short_pct'] ) ? absint( $_POST['length_short_pct'] ) : 40;
        $s['length_medium_pct'] = isset( $_POST['length_medium_pct'] ) ? absint( $_POST['length_medium_pct'] ) : 40;
        $s['length_long_pct']   = isset( $_POST['length_long_pct'] ) ? absint( $_POST['length_long_pct'] ) : 20;

        $s['tone']              = isset( $_POST['tone'] ) ? sanitize_key( $_POST['tone'] ) : 'natural';
        $s['include_cons']      = isset( $_POST['include_cons'] ) ? sanitize_key( $_POST['include_cons'] ) : 'sometimes';
        $s['reviewer_names']    = isset( $_POST['reviewer_names'] ) ? sanitize_key( $_POST['reviewer_names'] ) : 'auto';
        $s['custom_names']      = isset( $_POST['custom_names'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_names'] ) ) : '';

        $s['date_mode']         = isset( $_POST['date_mode'] ) ? sanitize_key( $_POST['date_mode'] ) : 'spread';
        $s['date_spread_days']  = isset( $_POST['date_spread_days'] ) ? absint( $_POST['date_spread_days'] ) : 90;

        $s['language']          = isset( $_POST['language'] ) ? sanitize_key( $_POST['language'] ) : 'auto';
        $s['mark_verified']     = ! empty( $_POST['mark_verified'] ) ? 'yes' : 'no';

        // Clamp values
        $s['reviews_fixed'] = max( 1, min( 50, $s['reviews_fixed'] ) );
        $s['reviews_min']   = max( 1, min( 50, $s['reviews_min'] ) );
        $s['reviews_max']   = max( $s['reviews_min'], min( 50, $s['reviews_max'] ) );
        $s['date_spread_days'] = max( 1, min( 365, $s['date_spread_days'] ) );

        update_option( self::OPTION_KEY, $s );

        wp_send_json_success( 'saved' );
    }

    /* ------------------------------------------------------------------
     * AJAX: Generate reviews for a single product
     * ----------------------------------------------------------------*/

    public function ajax_generate_reviews() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $provider   = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : get_option( 'botwriter_text_provider', 'openai' );

        if ( ! $product_id ) {
            wp_send_json_error( 'Missing product ID.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found.' );
        }

        $settings = self::get_settings();

        // Determine how many reviews to generate
        if ( $settings['reviews_mode'] === 'fixed' ) {
            $count = $settings['reviews_fixed'];
        } else {
            $count = wp_rand( $settings['reviews_min'], $settings['reviews_max'] );
        }

        // Build the prompt
        $prompt = $this->build_review_prompt( $product, $settings, $count );

        // Call AI
        $generator = new BW_Woo_AI_Generator();
        $api_key   = botwriter_get_provider_api_key( $provider );
        if ( empty( $api_key ) ) {
            wp_send_json_error( "No API key configured for provider: {$provider}" );
        }

        $ssl_verify = get_option( 'botwriter_sslverify', 'yes' ) === 'yes';
        $result     = $generator->call_provider( $provider, $api_key, $prompt, 4096, $ssl_verify );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // call_provider now returns the generated text directly
        $text = $result;

        if ( empty( $text ) ) {
            wp_send_json_error( 'Empty response from AI provider.' );
        }

        // Parse reviews from JSON response
        $reviews = $this->parse_reviews_response( $text, $settings, $count );

        if ( empty( $reviews ) ) {
            wp_send_json_error( 'Could not parse reviews from AI response.' );
        }

        // Return preview data — do NOT insert yet
        wp_send_json_success( [
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'requested'    => $count,
            'reviews'      => $reviews,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Apply approved reviews (insert into WooCommerce)
     * ----------------------------------------------------------------*/

    public function ajax_apply_reviews() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Missing product ID.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found.' );
        }

        $reviews_raw = isset( $_POST['reviews'] ) ? map_deep( wp_unslash( $_POST['reviews'] ), 'sanitize_text_field' ) : [];
        if ( empty( $reviews_raw ) || ! is_array( $reviews_raw ) ) {
            wp_send_json_error( 'No reviews to apply.' );
        }

        // Sanitize the reviews array
        $reviews = [];
        foreach ( $reviews_raw as $item ) {
            if ( ! is_array( $item ) ) continue;
            $reviews[] = [
                'name'    => sanitize_text_field( $item['name'] ?? 'Customer' ),
                'rating'  => max( 1, min( 5, intval( $item['rating'] ?? 5 ) ) ),
                'title'   => sanitize_text_field( $item['title'] ?? '' ),
                'content' => sanitize_textarea_field( $item['content'] ?? '' ),
            ];
        }

        if ( empty( $reviews ) ) {
            wp_send_json_error( 'No valid reviews to apply.' );
        }

        $settings = self::get_settings();
        $inserted = $this->insert_reviews( $product_id, $reviews, $settings );
        $this->recalculate_rating( $product_id );

        wp_send_json_success( [
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'inserted'     => $inserted,
        ] );
    }

    /* ------------------------------------------------------------------
     * Build the AI prompt
     * ----------------------------------------------------------------*/

    private function build_review_prompt( $product, $settings, $count ) {
        $name        = $product->get_name();
        $desc_raw    = $product->get_description();
        $desc_short  = $product->get_short_description();
        $price       = $product->get_price() ? wp_strip_all_tags( wc_price( $product->get_price() ) ) : '';
        $categories  = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
        $cat_str     = is_wp_error( $categories ) ? '' : implode( ', ', $categories );

        // Excerpt
        $desc_excerpt = wp_trim_words( wp_strip_all_tags( $desc_raw ), 150, '…' );

        // Rating distribution
        $rating_dist = [];
        for ( $s = 5; $s >= 1; $s-- ) {
            $pct = intval( $settings["rating_{$s}_pct"] );
            if ( $pct > 0 ) {
                $rating_dist[] = "{$s} stars: {$pct}%";
            }
        }

        // Length instructions
        $length_map = [
            'short'  => '1-2 sentences',
            'medium' => '2-4 sentences',
            'long'   => '4-8 sentences (a full paragraph)',
            'mixed'  => "mixed lengths — about {$settings['length_short_pct']}% short (1-2 sentences), {$settings['length_medium_pct']}% medium (2-4 sentences), {$settings['length_long_pct']}% long (paragraph)",
        ];
        $length_str = $length_map[ $settings['length_mode'] ] ?? $length_map['mixed'];

        // Tone
        $tone_map = [
            'natural'      => 'natural and varied',
            'enthusiastic' => 'enthusiastic and positive',
            'professional' => 'professional and detailed',
            'casual'       => 'casual and conversational',
        ];
        $tone_str = $tone_map[ $settings['tone'] ] ?? $tone_map['natural'];

        // Cons
        $cons_map = [
            'never'     => 'Do NOT include any negative aspects.',
            'sometimes' => 'Occasionally include a very minor constructive comment (about 20% of reviews) to sound more realistic.',
            'often'     => 'Include a minor constructive point in about 40% of reviews for realism.',
        ];
        $cons_str = $cons_map[ $settings['include_cons'] ] ?? $cons_map['sometimes'];

        // Names
        $names_str = '';
        if ( $settings['reviewer_names'] === 'custom' && ! empty( $settings['custom_names'] ) ) {
            $names_str = "Use names from this list (pick randomly): {$settings['custom_names']}";
        } else {
            $names_str = 'Generate realistic reviewer first names with last initial (e.g. "Sarah M.", "Carlos T.", "Emily W.").';
        }

        // Language
        $lang_str = $settings['language'] === 'auto'
            ? 'Write reviews in the same language as the product content.'
            : "Write all reviews in: {$settings['language']}.";

        // Rating distribution string
        $rating_dist_str = implode( ', ', $rating_dist );

        $prompt  = "You are simulating real customer reviews for an e-commerce product.\n\n";
        $prompt .= "Generate exactly {$count} product reviews as a JSON array.\n\n";
        $prompt .= "Product: {$name}\n";
        $prompt .= "{$cat_str}{$price}\n";
        $prompt .= "Description: {$desc_excerpt}\n";
        $prompt .= "{$desc_short}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Rating distribution: {$rating_dist_str}\n";
        $prompt .= "- Review length: {$length_str}\n";
        $prompt .= "- Tone: {$tone_str}\n";
        $prompt .= "- {$cons_str}\n";
        $prompt .= "- {$names_str}\n";
        $prompt .= "- {$lang_str}\n";
        $prompt .= "- Each review should feel unique and written by a different person\n";
        $prompt .= "- Reference specific product features when possible\n";
        $prompt .= "- DO NOT mention receiving the product for free or being asked to review\n\n";
        $prompt .= "Respond ONLY with a valid JSON array. Each object must have:\n";
        $prompt .= "{\n";
        $prompt .= "  \"name\": \"Reviewer Name\",\n";
        $prompt .= "  \"rating\": 5,\n";
        $prompt .= "  \"title\": \"Short review title\",\n";
        $prompt .= "  \"content\": \"The review text…\"\n";
        $prompt .= "}\n\n";
        $prompt .= "No markdown code blocks, no extra text — only the raw JSON array.";

        return $prompt;
    }

    /* ------------------------------------------------------------------
     * Parse AI response into structured reviews
     * ----------------------------------------------------------------*/

    private function parse_reviews_response( $text, $settings, $expected_count ) {
        // Try to extract JSON array
        $text = trim( $text );

        // Remove markdown code fences if present
        $text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
        $text = preg_replace( '/\s*```$/', '', $text );

        $decoded = json_decode( $text, true );

        if ( ! is_array( $decoded ) ) {
            // Try to find JSON array in the text
            if ( preg_match( '/\[.*\]/s', $text, $m ) ) {
                $decoded = json_decode( $m[0], true );
            }
        }

        if ( ! is_array( $decoded ) || empty( $decoded ) ) {
            return [];
        }

        $reviews = [];
        foreach ( $decoded as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $reviews[] = [
                'name'    => sanitize_text_field( $item['name'] ?? 'Customer' ),
                'rating'  => max( 1, min( 5, intval( $item['rating'] ?? 5 ) ) ),
                'title'   => sanitize_text_field( $item['title'] ?? '' ),
                'content' => sanitize_textarea_field( $item['content'] ?? '' ),
            ];
        }

        return $reviews;
    }

    /* ------------------------------------------------------------------
     * Insert reviews as WooCommerce product comments
     * ----------------------------------------------------------------*/

    private function insert_reviews( $product_id, $reviews, $settings ) {
        $inserted = 0;
        $now      = current_time( 'timestamp' );

        foreach ( $reviews as $i => $review ) {
            // Calculate date
            if ( $settings['date_mode'] === 'spread' ) {
                $max_offset = max( 1, $settings['date_spread_days'] ) * DAY_IN_SECONDS;
                $offset     = wp_rand( 0, $max_offset );
                $date       = gmdate( 'Y-m-d H:i:s', $now - $offset );
            } else {
                // "now" with a small random offset (a few minutes apart)
                $date = gmdate( 'Y-m-d H:i:s', $now - ( $i * wp_rand( 60, 300 ) ) );
            }

            $comment_data = [
                'comment_post_ID'      => $product_id,
                'comment_author'       => $review['name'],
                'comment_author_email' => $this->generate_fake_email( $review['name'] ),
                'comment_content'      => $review['content'],
                'comment_date'         => $date,
                'comment_date_gmt'     => get_gmt_from_date( $date ),
                'comment_approved'     => 1,
                'comment_type'         => 'review',
                'comment_meta'         => [
                    'rating' => $review['rating'],
                ],
            ];

            // Mark as verified owner if requested
            if ( $settings['mark_verified'] === 'yes' ) {
                $comment_data['comment_meta']['verified'] = 1;
            }

            // Mark as AI-generated (internal tracking)
            $comment_data['comment_meta']['_bw_ai_generated'] = 1;

            $comment_id = wp_insert_comment( $comment_data );

            if ( $comment_id && ! is_wp_error( $comment_id ) ) {
                // WooCommerce stores rating in comment meta
                update_comment_meta( $comment_id, 'rating', $review['rating'] );
                if ( $settings['mark_verified'] === 'yes' ) {
                    update_comment_meta( $comment_id, 'verified', 1 );
                }
                update_comment_meta( $comment_id, '_bw_ai_generated', 1 );
                $inserted++;
            }
        }

        return $inserted;
    }

    /* ------------------------------------------------------------------
     * Recalculate WooCommerce product average rating
     * ----------------------------------------------------------------*/

    private function recalculate_rating( $product_id ) {
        global $wpdb;

        // Count approved reviews from the database
        $review_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments}
             WHERE comment_post_ID = %d
               AND comment_approved = '1'
               AND comment_type = 'review'",
            $product_id
        ) );

        // Calculate average rating from comment meta
        $average_rating = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(cm.meta_value) FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
             WHERE cm.meta_key = 'rating'
               AND c.comment_post_ID = %d
               AND c.comment_approved = '1'
               AND c.comment_type = 'review'",
            $product_id
        ) );

        // Build rating counts per star (1-5)
        $rating_counts = [];
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT cm.meta_value AS star, COUNT(*) AS cnt
             FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
             WHERE cm.meta_key = 'rating'
               AND c.comment_post_ID = %d
               AND c.comment_approved = '1'
               AND c.comment_type = 'review'
             GROUP BY cm.meta_value",
            $product_id
        ) );
        foreach ( $rows as $row ) {
            $rating_counts[ (int) $row->star ] = (int) $row->cnt;
        }

        // Persist directly into post meta (WooCommerce reads these)
        update_post_meta( $product_id, '_wc_review_count', $review_count );
        update_post_meta( $product_id, '_wc_average_rating', round( $average_rating, 2 ) );
        update_post_meta( $product_id, '_wc_rating_count', $rating_counts );

        // Clear transient caches
        delete_transient( 'wc_average_rating_' . $product_id );
        delete_transient( 'wc_rating_count_' . $product_id );
        delete_transient( 'wc_review_count_' . $product_id );

        // Also update WordPress comment count
        wp_update_comment_count_now( $product_id );
    }

    /* ------------------------------------------------------------------
     * AJAX: Delete AI-generated reviews for a product
     * ----------------------------------------------------------------*/

    public function ajax_delete_ai_reviews() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Missing product ID.' );
        }

        global $wpdb;

        // Find all AI-generated reviews for this product
        $comment_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
             WHERE c.comment_post_ID = %d
               AND c.comment_type = 'review'
               AND cm.meta_key = '_bw_ai_generated'
               AND cm.meta_value = '1'",
            $product_id
        ) );

        $deleted = 0;
        foreach ( $comment_ids as $cid ) {
            if ( wp_delete_comment( $cid, true ) ) {
                $deleted++;
            }
        }

        // Recalculate rating
        $this->recalculate_rating( $product_id );

        wp_send_json_success( [
            'product_id' => $product_id,
            'deleted'    => $deleted,
        ] );
    }

    /* ------------------------------------------------------------------
     * Helper: Generate a plausible fake email from a name
     * ----------------------------------------------------------------*/

    private function generate_fake_email( $name ) {
        $slug   = sanitize_title( $name );
        $rand   = wp_rand( 10, 99 );
        $domain = 'example.com';
        return "{$slug}{$rand}@{$domain}";
    }
}
