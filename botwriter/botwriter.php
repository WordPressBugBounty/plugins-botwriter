<?php
/* 
Plugin Name: BotWriter – AI Writer & SEO Content Generator
Plugin URI:  https://www.wpbotwriter.com
Description: Plugin for automatically generating posts using artificial intelligence. Create content from scratch with AI and generate custom images. Optimize content for SEO, including tags, titles, and image descriptions. Advanced features like ChatGPT, automatic content creation, image generation, SEO optimization, and AI training make this plugin a complete tool for writers and content creators.
Version: 3.4.1
Author: estebandezafra
Requires PHP: 7.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: botwriter
Domain Path: /languages
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
} 




if (!defined('BOTWRITER_VERSION')) {
    define('BOTWRITER_VERSION', '3.4.1');
}

// Plugin directory path (with trailing slash)
if (!defined('BOTWRITER_PLUGIN_DIR')) {
    define('BOTWRITER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

define('BOTWRITER_URL', plugin_dir_url(__FILE__));

define('BOTWRITER_API_URL', "https://api.wpbotwriter.com/");



// Debugging constant for development
if (!defined('BOTWRITER_DEBUG')) {
    define('BOTWRITER_DEBUG', false);
}


if (!function_exists('botwriter_is_seo_module_enabled')) {
    function botwriter_is_seo_module_enabled() {
        return get_option('botwriter_seo_module_enabled', '1') === '1';
    }
}


/**
 * Returns absolute filesystem path to the plugin debug log file
 * (wp-content/uploads/botwriter-debug.log) or false if uploads dir
 * is not writable.
 */
if (!function_exists('botwriter_debug_log_path')) {
    function botwriter_debug_log_path() {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return false;
        }
        $base = isset($uploads['basedir']) ? $uploads['basedir'] : '';
        if (!$base) {
            return false;
        }
        return trailingslashit($base) . 'botwriter-debug.log';
    }
}

/**
 * Whether the UI-toggle for debug logging to file is enabled.
 */
if (!function_exists('botwriter_debug_log_enabled')) {
    function botwriter_debug_log_enabled() {
        return get_option('botwriter_debug_logging', '0') === '1';
    }
}


if (!function_exists('botwriter_log')) {
    function botwriter_log($message, array $context = []) {
        $botwriter_debug = defined('BOTWRITER_DEBUG') && BOTWRITER_DEBUG === true;
        $wp_debug_log    = defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $file_log        = botwriter_debug_log_enabled();
        if (!$botwriter_debug && !$wp_debug_log && !$file_log) {
            return;
        }

        if (!is_string($message)) {
            $encoded = wp_json_encode($message);
            if ($encoded !== false) {
                $message = $encoded;
            } else {
                // Fallback to safe string/serialization without using print_r
                if (is_scalar($message)) {
                    $message = (string) $message;
                } else {
                    $message = maybe_serialize($message);
                }
            }
        }

        if (!empty($context)) {
            $encoded_context = wp_json_encode($context);
            if ($encoded_context !== false) {
                $message .= ' ' . $encoded_context;
            }
        }

        $line = '[BotWriter] ' . $message;

        if ($botwriter_debug || $wp_debug_log) {
            error_log($line);
        }

        if ($file_log) {
            $path = botwriter_debug_log_path();
            if ($path) {
                // Cap file size at 5 MB by truncating-then-rotating in place.
                if (file_exists($path) && filesize($path) > 5 * 1024 * 1024) {
                    @file_put_contents($path, '');
                }
                $stamp = gmdate('Y-m-d H:i:s');
                @file_put_contents(
                    $path,
                    '[' . $stamp . ' UTC] ' . $line . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }
        }
    }
}


/**
 * Add plugin action links (Settings link next to Deactivate)
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'botwriter_plugin_action_links');
function botwriter_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=botwriter_settings') . '">' . __('Settings', 'botwriter') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Add plugin row meta links (Website, FAQ, Support below plugin description)
 */
add_filter('plugin_row_meta', 'botwriter_plugin_row_meta', 10, 2);
function botwriter_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'website' => '<a href="https://www.wpbotwriter.com" target="_blank" rel="noopener">' . __('Website', 'botwriter') . '</a>',
            'faq'     => '<a href="https://wpbotwriter.com/faq.html" target="_blank" rel="noopener">' . __('FAQ', 'botwriter') . '</a>',
            'support' => '<a href="https://wordpress.org/support/plugin/botwriter/" target="_blank" rel="noopener">' . __('Support', 'botwriter') . '</a>',
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}


require plugin_dir_path( __FILE__ ) . 'includes/posts.php';
require plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/logs.php';
require plugin_dir_path( __FILE__ ) . 'includes/dedup.php';
require plugin_dir_path( __FILE__ ) . 'includes/announcements.php';
require plugin_dir_path( __FILE__ ) . 'includes/super.php';
require plugin_dir_path( __FILE__ ) . 'includes/addnew.php';
require plugin_dir_path( __FILE__ ) . 'includes/quickpost.php';
require plugin_dir_path( __FILE__ ) . 'includes/rewriter.php';
require plugin_dir_path( __FILE__ ) . 'includes/siterewriter.php';
require plugin_dir_path( __FILE__ ) . 'includes/templates.php';
require plugin_dir_path( __FILE__ ) . 'includes/default-templates.php';
if (botwriter_is_seo_module_enabled()) {
    require plugin_dir_path( __FILE__ ) . 'includes/seo/seo.php';
}

// WooCommerce AI Content Optimizer (loads only when WooCommerce is active)
require plugin_dir_path( __FILE__ ) . 'includes/woocommerce-ai/class-bw-woo-ai.php';
add_action( 'plugins_loaded', function () {
    $bw_woo_ai = new BotWriter_Woo_AI();
    $bw_woo_ai->init();
} );


// Enqueque JS Files
function botwriter_enqueue_scripts() { 
    $my_plugin_dir = plugin_dir_url(__FILE__);        
	$screen = get_current_screen();
    $slug = $screen->id;							   

    	
	
    wp_register_script( 'bootstrapjs',$my_plugin_dir.'/assets/js/bootstrap.min.js' , array('jquery'), false, true );
    wp_enqueue_script( 'bootstrapjs' );

    
    wp_register_script( 'botwriter_bootstrap_bundle',$my_plugin_dir.'/assets/js/bootstrap.bundle.min.js' , array('jquery'), false, true );
    wp_enqueue_script( 'botwriter_bootstrap_bundle' );


    wp_register_script( 'botwriter_botwriter',$my_plugin_dir.'/assets/js/botwriter.js' , array('jquery'), false, true );
    wp_enqueue_script( 'botwriter_botwriter' );
    wp_localize_script('botwriter_botwriter', 'botwriter_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('botwriter_super_nonce'),
        'rss_nonce' => wp_create_nonce('botwriter_check_rss_nonce'),
        'wp_categories_nonce' => wp_create_nonce('botwriter_wp_categories_nonce'),
    ));

    wp_enqueue_script('botwriter-admin-ajax-status', $my_plugin_dir.'/assets/js/admin-ajax-status.js', ['jquery'], null, true);
    wp_localize_script('botwriter-admin-ajax-status', 'botwriter_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('botwriter_cambiar_status_nonce')
    ]);


    wp_enqueue_script('botwriter-dismiss-script', $my_plugin_dir .  '/assets/js/botwriter_dismiss.js', array('jquery'), null, true);
    wp_localize_script('botwriter-dismiss-script','botwriterData',
        array(
            'nonce'   => wp_create_nonce('botwriter_dismiss_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        )
    );

						
    if ($slug=="botwriter_page_botwriter_automatic_post_new" || $slug === 'botwriter_page_botwriter_super_page' || $slug === 'botwriter_page_botwriter_write_now' || $slug === 'botwriter_page_botwriter_rewriter_page' || $slug === 'botwriter_page_botwriter_siterewriter_page') {
        wp_register_script('botwriter_automatic_posts', $my_plugin_dir . 'assets/js/posts.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_automatic_posts');
        wp_localize_script('botwriter_automatic_posts', 'botwriter_posts_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'taxonomies_nonce' => wp_create_nonce('botwriter_taxonomies_nonce'),
        ));
    }
    

    if ($slug==="botwriter_page_botwriter_logs") {
        wp_register_script('botwriter_logs', $my_plugin_dir . 'assets/js/logs.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_logs');
        wp_localize_script('botwriter_logs', 'botwriter_logs_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_logs_delete_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this log entry? This action cannot be undone.', 'botwriter'),
            'confirm_bulk_delete' => __('Are you sure you want to delete the selected log entries? This action cannot be undone.', 'botwriter'),
            'error_delete' => __('Error deleting log. Please try again.', 'botwriter'),
        ));

        // Reuse the featured image regeneration modal inside BotWriter logs.
        wp_register_script('botwriter_post_image_regeneration', $my_plugin_dir . 'assets/js/post-image-regeneration.js', array('jquery'), BOTWRITER_VERSION, true);
        wp_enqueue_script('botwriter_post_image_regeneration');
        wp_localize_script('botwriter_post_image_regeneration', 'botwriter_post_image_regeneration', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_regenerate_image_nonce'),
            'i18n'     => array(
                'link_text'         => __('Regenerate', 'botwriter'),
                'modal_title'       => __('BotWriter Image Regeneration', 'botwriter'),
                'modal_subtitle'    => __('Generate and preview a featured image before applying it.', 'botwriter'),
                'provider'          => __('Current provider', 'botwriter'),
                'model'             => __('Current model', 'botwriter'),
                'prompt_label'      => __('Image Prompt', 'botwriter'),
                'cleanup_label'     => __('Previous featured image', 'botwriter'),
                'cleanup_keep'      => __('Keep in media library', 'botwriter'),
                'cleanup_delete'    => __('Delete permanently (if not used elsewhere)', 'botwriter'),
                'current_image'     => __('Current featured image', 'botwriter'),
                'no_current_image'  => __('This post has no featured image yet.', 'botwriter'),
                'btn_regenerate'    => __('Regenerate', 'botwriter'),
                'btn_accept'        => __('Accept', 'botwriter'),
                'btn_close'         => __('Close', 'botwriter'),
                'loading_context'   => __('Loading data...', 'botwriter'),
                'generating'        => __('Generating image preview...', 'botwriter'),
                'applying'          => __('Applying featured image...', 'botwriter'),
                'missing_log'       => __('No saved image prompt was found for this post. Please write your prompt manually.', 'botwriter'),
                'provider_none'     => __('Image provider is currently set to "none" in settings. Select an image provider first.', 'botwriter'),
                'invalid_post'      => __('No valid published post is linked to this log entry.', 'botwriter'),
                'empty_prompt'      => __('Please enter an image prompt before regenerating.', 'botwriter'),
                'working'           => __('Regenerating image...', 'botwriter'),
                'generic_error'     => __('Could not regenerate the image. Please try again.', 'botwriter'),
            ),
        ));
    }

    																											   

    if ($slug === 'botwriter_page_botwriter_super_page') { 
        wp_register_script('botwriter_super', $my_plugin_dir . 'assets/js/super.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_super');         
        wp_localize_script('botwriter_super', 'botwriter_super_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_super_nonce')            
        ));
    }

    if ($slug === 'botwriter_page_botwriter_rewriter_page') {
        wp_register_script('botwriter_rewriter', $my_plugin_dir . 'assets/js/rewriter.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_rewriter');
        wp_localize_script('botwriter_rewriter', 'botwriter_rewriter_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_rewriter_nonce'),
            'logs_url' => admin_url('admin.php?page=botwriter_logs'),
        ));
    }

    if ($slug === 'botwriter_page_botwriter_siterewriter_page') {
        wp_register_script('botwriter_siterewriter', $my_plugin_dir . 'assets/js/siterewriter.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_siterewriter');
        wp_localize_script('botwriter_siterewriter', 'botwriter_siterewriter_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_siterewriter_nonce'),
            'logs_url' => admin_url('admin.php?page=botwriter_logs'),
        ));
    }

    if ($slug === 'botwriter_page_botwriter_settings') {
        wp_register_script('botwriter_settings', $my_plugin_dir . 'assets/js/botwriter-settings.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_settings');
        wp_localize_script('botwriter_settings', 'botwriter_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('botwriter_settings_nonce'),
            'i18n' => array(
                'saving' => __('Saving...', 'botwriter'),
                'saved' => __('Saved', 'botwriter'),
                'error' => __('Error', 'botwriter'),
                'connection_error' => __('Connection error', 'botwriter'),
                'connection_failed' => __('Connection failed', 'botwriter'),
                'hide' => __('Hide', 'botwriter'),
                'show' => __('Show', 'botwriter'),
                'active' => __('Active', 'botwriter'),
                'enter_api_key' => __('Please enter an API key first.', 'botwriter'),
                'enter_api_url' => __('Please enter an API URL', 'botwriter'),
                'testing' => __('Testing...', 'botwriter'),
                'models_found' => __('Models found:', 'botwriter'),
                'configure_openai_key' => __('Configure OpenAI API key in Text AI tab first.', 'botwriter'),
                'confirm_reset_models' => __('Are you sure you want to reset all model lists to factory defaults?', 'botwriter'),
            )
        ));

        wp_register_script('botwriter_debug_log', $my_plugin_dir . 'assets/js/debug-log.js', array('jquery', 'botwriter_settings'), BOTWRITER_VERSION, true);
        wp_enqueue_script('botwriter_debug_log');
        wp_localize_script('botwriter_debug_log', 'botwriter_debug_log', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('botwriter_settings_nonce'),
            'i18n' => array(
                'loading' => __('Loading...', 'botwriter'),
                'size' => __('Size:', 'botwriter'),
                'showing_last' => __('showing last 512 KB', 'botwriter'),
                'logging_off' => __('logging is OFF', 'botwriter'),
                'error' => __('Error.', 'botwriter'),
                'request_failed' => __('Request failed.', 'botwriter'),
                'confirm_clear' => __('Clear the debug log file?', 'botwriter'),
                'clearing' => __('Clearing...', 'botwriter'),
                'cleared' => __('Log cleared.', 'botwriter'),
            ),
        ));
    }

    if ($slug === 'botwriter_page_botwriter_write_now') {
        wp_register_script('botwriter_quickpost', $my_plugin_dir . 'assets/js/quickpost.js', array('jquery'), false, true);
        wp_enqueue_script('botwriter_quickpost');
        wp_localize_script('botwriter_quickpost', 'botwriter_quickpost_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_quickpost_nonce')
        ));
    }

    // Regenerate featured image modal (post editor)
    if ($screen && $screen->base === 'post' && current_user_can('manage_options')) {
        wp_register_script('botwriter_post_image_regeneration', $my_plugin_dir . 'assets/js/post-image-regeneration.js', array('jquery'), BOTWRITER_VERSION, true);
        wp_enqueue_script('botwriter_post_image_regeneration');
        wp_localize_script('botwriter_post_image_regeneration', 'botwriter_post_image_regeneration', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('botwriter_regenerate_image_nonce'),
            'i18n'     => array(
                'link_text'      => __('Regenerate', 'botwriter'),
                'modal_title'    => __('BotWriter Image Regeneration', 'botwriter'),
                'modal_subtitle' => __('Generate and preview a featured image before applying it.', 'botwriter'),
                'provider'       => __('Current provider', 'botwriter'),
                'model'          => __('Current model', 'botwriter'),
                'prompt_label'   => __('Image Prompt', 'botwriter'),
                'cleanup_label'  => __('Previous featured image', 'botwriter'),
                'cleanup_keep'   => __('Keep in media library', 'botwriter'),
                'cleanup_delete' => __('Delete permanently (if not used elsewhere)', 'botwriter'),
                'current_image'  => __('Current featured image', 'botwriter'),
                'no_current_image' => __('This post has no featured image yet.', 'botwriter'),
                'btn_regenerate' => __('Regenerate', 'botwriter'),
                'btn_accept'     => __('Accept', 'botwriter'),
                'btn_close'      => __('Close', 'botwriter'),
                'loading_context'=> __('Loading data...', 'botwriter'),
                'generating'     => __('Generating image preview...', 'botwriter'),
                'applying'       => __('Applying featured image...', 'botwriter'),
                'missing_log'    => __('No saved image prompt was found for this post. Please write your prompt manually.', 'botwriter'),
                'provider_none'  => __('Image provider is currently set to "none" in settings. Select an image provider first.', 'botwriter'),
                'invalid_post'   => __('No valid published post is linked to this log entry.', 'botwriter'),
                'empty_prompt' => __('Please enter an image prompt before regenerating.', 'botwriter'),
                'working'      => __('Regenerating image...', 'botwriter'),
                'generic_error'=> __('Could not regenerate the image. Please try again.', 'botwriter'),
            ),
        ));
    }

    // Floating AI assistant widget (post editor)
    if ($screen && $screen->base === 'post' && (string) $screen->post_type === 'post' && current_user_can('edit_posts') && get_option('botwriter_editor_assistant_enabled', '1') === '1') {
        wp_register_script('botwriter_editor_assistant', $my_plugin_dir . 'assets/js/editor-ai-assistant.js', array('jquery'), BOTWRITER_VERSION, true);
        wp_enqueue_script('botwriter_editor_assistant');
        wp_localize_script('botwriter_editor_assistant', 'botwriter_editor_ai', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('botwriter_editor_assistant_nonce'),
            'robot_image' => $my_plugin_dir . 'assets/images/robot.png',
            'robot_face_image' => $my_plugin_dir . 'assets/images/robot_face.png',
            'settings' => array(
                'skip_heading_links' => '1',
                'seo_module_enabled' => botwriter_is_seo_module_enabled() ? '1' : '0',
            ),
            'i18n' => array(
                'widget_title' => __('BotWriter Copilot', 'botwriter'),
                'tab_prompt' => __('Prompt', 'botwriter'),
                'tab_seo' => __('SEO', 'botwriter'),
                'intro' => __('Select what to update', 'botwriter'),
                'seo_intro' => __('Review the current post SEO checks.', 'botwriter'),
                'seo_subtab_analysis' => __('SEO analysis', 'botwriter'),
                'seo_subtab_readability' => __('Readability', 'botwriter'),
                'seo_loading' => __('Loading SEO report...', 'botwriter'),
                'seo_missing_post' => __('Save the post first to view SEO reports.', 'botwriter'),
                'seo_error' => __('Could not load SEO report.', 'botwriter'),
                'seo_empty' => __('No SEO checks available.', 'botwriter'),
                'target_text' => __('Text', 'botwriter'),
                'target_title' => __('Title', 'botwriter'),
                'target_tags' => __('Tags', 'botwriter'),
                'target_excerpt' => __('Excerpt', 'botwriter'),
                'target_seo_meta' => __('SEO Meta', 'botwriter'),
                'target_internal_links' => __('Internal Links', 'botwriter'),
                'suggestions_title' => __('Suggestions', 'botwriter'),
                'prompt_placeholder' => __('Describe exactly what you want to improve...', 'botwriter'),
                'links_prompt_placeholder' => __('What kind of internal links do you want (educational, conversion, cluster, etc.)?', 'botwriter'),
                'keyphrases_label' => __('Keyphrases (up to 5, comma-separated)', 'botwriter'),
                'keyphrases_placeholder' => __('e.g. internal linking, seo writing, topic clusters', 'botwriter'),
                'links_title' => __('Suggested internal links', 'botwriter'),
                'insert_link' => __('Insert', 'botwriter'),
                'inserted_link' => __('Inserted', 'botwriter'),
                'open_link' => __('Open', 'botwriter'),
                'links_ready' => __('Suggestions are ready. Insert the links you want, then Keep or Undo.', 'botwriter'),
                'links_mode_ai' => __('AI mode: suggestions ranked by semantic relevance and anchor fit.', 'botwriter'),
                'links_mode_noai' => __('Deterministic mode: suggestions ranked using taxonomy and keyword overlap (no AI call).', 'botwriter'),
                'links_empty' => __('No relevant internal links were found yet.', 'botwriter'),
                'link_inserted' => __('Internal link inserted. Review and choose Keep or Undo.', 'botwriter'),
                'link_already_exists' => __('This URL is already linked in the content.', 'botwriter'),
                'same_response_notice' => __('AI returned the same text. No changes were applied.', 'botwriter'),
                'sending' => __('Thinking', 'botwriter'),
                'keep' => __('Keep', 'botwriter'),
                'undo' => __('Undo', 'botwriter'),
                'confirm_label' => __('Apply this AI change?', 'botwriter'),
                'missing_prompt' => __('Write a prompt or choose a suggestion first.', 'botwriter'),
                'generic_error' => __('Could not generate a response. Please try again.', 'botwriter'),
                'empty_response' => __('The assistant returned an empty response.', 'botwriter'),
                'updated_notice' => __('Updated. Review and choose Keep or Undo.', 'botwriter'),
                'reverted_notice' => __('Change reverted.', 'botwriter'),
                'kept_notice' => __('Change kept. Save or update the post when ready.', 'botwriter'),
            ),
        ));
    }

    

}
add_action('admin_enqueue_scripts','botwriter_enqueue_scripts');

/**
 * Retrieve the latest BotWriter log associated with a published post.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function botwriter_get_latest_log_by_post_id($post_id) {
    global $wpdb;

    $post_id = intval($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $table_name = $wpdb->prefix . 'botwriter_logs';
    $log = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id_post_published = %d ORDER BY id DESC LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    botwriter_log('Image prompt lookup: latest log by post', array(
        'post_id' => $post_id,
        'found' => is_array($log),
        'log_id' => is_array($log) ? intval($log['id'] ?? 0) : 0,
        'id_post_published' => is_array($log) ? intval($log['id_post_published'] ?? 0) : 0,
        'image_prompt_len' => is_array($log) ? strlen(trim((string) ($log['image_prompt'] ?? ''))) : 0,
    ));

    return is_array($log) ? $log : null;
}

/**
 * Retrieve the latest BotWriter log for a post that has a non-empty image_prompt.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function botwriter_get_latest_log_with_image_prompt_by_post_id($post_id) {
    global $wpdb;

    $post_id = intval($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $table_name = $wpdb->prefix . 'botwriter_logs';
    $log = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id_post_published = %d AND image_prompt IS NOT NULL AND TRIM(image_prompt) <> '' ORDER BY id DESC LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    botwriter_log('Image prompt lookup: latest log WITH prompt by post', array(
        'post_id' => $post_id,
        'found' => is_array($log),
        'log_id' => is_array($log) ? intval($log['id'] ?? 0) : 0,
        'id_post_published' => is_array($log) ? intval($log['id_post_published'] ?? 0) : 0,
        'image_prompt_len' => is_array($log) ? strlen(trim((string) ($log['image_prompt'] ?? ''))) : 0,
    ));

    return is_array($log) ? $log : null;
}

/**
 * Resolve current image model from provider settings.
 *
 * @param string $provider Provider slug.
 * @return string
 */
function botwriter_get_current_image_model_by_provider($provider) {
    $provider = sanitize_key((string) $provider);

    if ($provider === 'stockphoto') {
        $preferred = sanitize_key((string) get_option('botwriter_stockphoto_preferred', 'random'));
        $allowed_preferred = array('pixabay', 'pexels', 'unsplash', 'openverse', 'random');
        if (!in_array($preferred, $allowed_preferred, true)) {
            $preferred = 'random';
        }

        return $preferred;
    }
    if ($provider === 'none') {
        return 'none';
    }

    $default_model = function_exists('botwriter_get_provider_default_image_model')
        ? (string) botwriter_get_provider_default_image_model($provider)
        : '';
    if ($default_model === '') {
        $fallback_defaults = array(
            'dalle' => 'gpt-image-1',
            'gemini' => 'gemini-2.5-flash-image',
            'fal' => 'fal-ai/flux-pro/v1.1',
            'replicate' => 'black-forest-labs/flux-1.1-pro',
            'stability' => 'sd3.5-large-turbo',
            'cloudflare' => 'flux-1-schnell',
        );
        $default_model = (string) ($fallback_defaults[$provider] ?? 'gpt-image-1');
    }

    $option_name = function_exists('botwriter_get_image_model_option_name')
        ? botwriter_get_image_model_option_name($provider)
        : ($provider === 'gemini' ? 'botwriter_gemini_image_model' : "botwriter_{$provider}_model");

    $model = (string) get_option($option_name, $default_model);

    if (function_exists('botwriter_normalize_image_model')) {
        $normalized_model = (string) botwriter_normalize_image_model($provider, $model);

        // Persist normalized aliases (for example legacy Gemini 2.0 IDs)
        // so the settings UI reflects the real value used in dispatch.
        if ($normalized_model !== '' && $normalized_model !== $model) {
            update_option($option_name, $normalized_model);
            botwriter_log('Image model option auto-normalized', array(
                'provider' => $provider,
                'option_name' => $option_name,
                'raw_model' => $model,
                'normalized_model' => $normalized_model,
            ));
        }

        return $normalized_model;
    }

    return $model;
}

/**
 * Normalize image size values to the canonical semantic set.
 *
 * Legacy values such as square_hd or landscape_16_9 may still exist in
 * older installations/options. The direct /images endpoint expects only
 * landscape|square|portrait.
 *
 * @param string $size Raw size value.
 * @return string
 */
function botwriter_normalize_ai_image_size($size) {
    $normalized = strtolower(trim((string) $size));

    if ($normalized === '' || $normalized === 'square' || $normalized === 'square_hd'
        || $normalized === '1:1' || $normalized === '1024x1024') {
        return 'square';
    }

    if ($normalized === 'landscape' || $normalized === 'landscape_4_3' || $normalized === 'landscape_16_9'
        || $normalized === '4:3' || $normalized === '16:9' || $normalized === '1536x1024'
        || $normalized === '1792x1024') {
        return 'landscape';
    }

    if ($normalized === 'portrait' || $normalized === 'portrait_4_3' || $normalized === 'portrait_16_9'
        || $normalized === '3:4' || $normalized === '9:16' || $normalized === '1024x1536'
        || $normalized === '1024x1792') {
        return 'portrait';
    }

    return 'square';
}

/**
 * Return current image settings used for regeneration.
 * Uses active plugin settings at execution time.
 *
 * @return array
 */
function botwriter_get_current_image_generation_settings() {
    $provider = (string) get_option('botwriter_image_provider', 'stockphoto');
    $model = botwriter_get_current_image_model_by_provider($provider);
    $size = botwriter_normalize_ai_image_size((string) get_option('botwriter_ai_image_size', 'square'));
    $stockphoto_preferred = sanitize_key((string) get_option('botwriter_stockphoto_preferred', 'random'));
    $allowed_preferred = array('pixabay', 'pexels', 'unsplash', 'openverse', 'random');
    if (!in_array($stockphoto_preferred, $allowed_preferred, true)) {
        $stockphoto_preferred = 'random';
    }

    return array(
        'provider' => $provider,
        'model' => $model,
        'size' => $size,
        'quality' => (string) get_option('botwriter_ai_image_quality', 'medium'),
        'style' => (string) get_option('botwriter_ai_image_style', 'realistic'),
        'style_custom' => (string) get_option('botwriter_ai_image_style_custom', ''),
        'stockphoto_preferred' => $stockphoto_preferred,
        'stockphoto_selection' => (string) get_option('botwriter_stockphoto_selection', 'random_top10'),
        'stockphoto_attribution' => (string) get_option('botwriter_stockphoto_attribution', 'caption'),
    );
}

/**
 * Return post meta keys used to persist image prompts.
 *
 * @return array
 */
function botwriter_get_image_prompt_meta_keys() {
    return array(
        'ai' => 'botwriter_image_prompt',
        'stock' => 'botwriter_stockphoto_prompt',
        'last' => 'botwriter_image_prompt_last',
        'provider' => 'botwriter_image_prompt_last_provider',
    );
}

/**
 * Persist image prompt metadata on the post itself.
 *
 * @param int    $post_id Post ID.
 * @param string $prompt Prompt text.
 * @param string $provider Provider used for generation.
 * @return bool
 */
function botwriter_save_post_image_prompt_meta($post_id, $prompt, $provider = '') {
    $post_id = intval($post_id);
    if ($post_id <= 0) {
        botwriter_log('Image prompt meta save skipped: invalid post_id', array('post_id' => $post_id));
        return false;
    }

    $prompt = trim(sanitize_textarea_field((string) $prompt));
    if ($prompt === '') {
        botwriter_log('Image prompt meta save skipped: empty prompt', array(
            'post_id' => $post_id,
            'provider' => $provider,
        ));
        return false;
    }

    $provider = sanitize_key((string) $provider);
    $keys = botwriter_get_image_prompt_meta_keys();

    update_post_meta($post_id, $keys['last'], $prompt);

    if ($provider === 'stockphoto') {
        update_post_meta($post_id, $keys['stock'], $prompt);
    } else {
        update_post_meta($post_id, $keys['ai'], $prompt);
    }

    if ($provider !== '') {
        update_post_meta($post_id, $keys['provider'], $provider);
    }

    botwriter_log('Image prompt meta saved', array(
        'post_id' => $post_id,
        'provider' => $provider,
        'prompt_len' => strlen($prompt),
        'saved_ai_meta' => ($provider !== 'stockphoto'),
        'saved_stock_meta' => ($provider === 'stockphoto'),
        'meta_key_last' => $keys['last'],
    ));

    return true;
}

/**
 * Resolve the best prompt saved on post meta for current provider context.
 *
 * @param int    $post_id Post ID.
 * @param string $provider Current provider.
 * @return array
 */
function botwriter_get_post_image_prompt_from_meta($post_id, $provider = '') {
    $post_id = intval($post_id);
    $provider = sanitize_key((string) $provider);
    $keys = botwriter_get_image_prompt_meta_keys();

    $ai_prompt = trim((string) get_post_meta($post_id, $keys['ai'], true));
    $stock_prompt = trim((string) get_post_meta($post_id, $keys['stock'], true));
    $last_prompt = trim((string) get_post_meta($post_id, $keys['last'], true));

    botwriter_log('Image prompt lookup: post meta snapshot', array(
        'post_id' => $post_id,
        'provider' => $provider,
        'ai_len' => strlen($ai_prompt),
        'stock_len' => strlen($stock_prompt),
        'last_len' => strlen($last_prompt),
        'meta_ai_key' => $keys['ai'],
        'meta_stock_key' => $keys['stock'],
        'meta_last_key' => $keys['last'],
    ));

    if ($provider === 'stockphoto') {
        if ($stock_prompt !== '') {
            botwriter_log('Image prompt lookup: selected meta_stock', array('post_id' => $post_id, 'len' => strlen($stock_prompt)));
            return array('prompt' => $stock_prompt, 'source' => 'meta_stock');
        }
        if ($ai_prompt !== '') {
            botwriter_log('Image prompt lookup: selected meta_ai for stock provider', array('post_id' => $post_id, 'len' => strlen($ai_prompt)));
            return array('prompt' => $ai_prompt, 'source' => 'meta_ai');
        }
    } else {
        if ($ai_prompt !== '') {
            botwriter_log('Image prompt lookup: selected meta_ai', array('post_id' => $post_id, 'len' => strlen($ai_prompt)));
            return array('prompt' => $ai_prompt, 'source' => 'meta_ai');
        }
    }

    if ($last_prompt !== '') {
        botwriter_log('Image prompt lookup: selected meta_last', array('post_id' => $post_id, 'len' => strlen($last_prompt)));
        return array('prompt' => $last_prompt, 'source' => 'meta_last');
    }

    if ($stock_prompt !== '') {
        botwriter_log('Image prompt lookup: selected stock fallback', array('post_id' => $post_id, 'len' => strlen($stock_prompt)));
        return array('prompt' => $stock_prompt, 'source' => 'meta_stock');
    }

    botwriter_log('Image prompt lookup: no prompt found in post meta', array('post_id' => $post_id));

    return array('prompt' => '', 'source' => 'none');
}

/**
 * Resolve the best available image prompt from generated post data.
 *
 * @param array $data Data used to generate/publish the post.
 * @return string
 */
function botwriter_resolve_image_prompt_from_post_data($data) {
    if (!is_array($data)) {
        botwriter_log('Image prompt resolve from post data: invalid payload');
        return '';
    }

    $explicit_prompt = isset($data['image_prompt']) ? trim(sanitize_textarea_field((string) $data['image_prompt'])) : '';
    if ($explicit_prompt !== '') {
        botwriter_log('Image prompt resolve from post data: using explicit image_prompt', array(
            'len' => strlen($explicit_prompt),
            'has_image_provider' => isset($data['image_provider']),
        ));
        return $explicit_prompt;
    }

    // Edge/legacy fallback: when image_prompt is not returned, the title is the usual fallback basis.
    $title_based_prompt = isset($data['aigenerated_title']) ? trim(sanitize_text_field((string) $data['aigenerated_title'])) : '';
    if ($title_based_prompt !== '') {
        botwriter_log('Image prompt resolve from post data: fallback to generated title', array(
            'title_len' => strlen($title_based_prompt),
            'has_image_prompt_key' => array_key_exists('image_prompt', $data),
        ));
        return $title_based_prompt;
    }

    botwriter_log('Image prompt resolve from post data: fallback to hardcoded default');
    return 'Blog post illustration';
}

/**
 * Build UI context for image regeneration modal.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function botwriter_get_image_regeneration_context($post_id) {
    $post_id = intval($post_id);
    $settings = botwriter_get_current_image_generation_settings();
    $provider = (string) ($settings['provider'] ?? '');

    $prompt_context = botwriter_get_post_image_prompt_from_meta($post_id, $provider);
    $prefill_prompt = (string) ($prompt_context['prompt'] ?? '');
    $prompt_source = (string) ($prompt_context['source'] ?? 'none');

    botwriter_log('Image regeneration context: after meta lookup', array(
        'post_id' => $post_id,
        'provider' => $provider,
        'prompt_source' => $prompt_source,
        'prompt_len' => strlen($prefill_prompt),
    ));

    $latest_log = null;
    if ($prefill_prompt === '') {
        // Prefer the newest log that actually contains an image prompt.
        $latest_log = botwriter_get_latest_log_with_image_prompt_by_post_id($post_id);

        // Backward-compat fallback: if no prompt-carrying log exists, inspect latest log anyway.
        if (!is_array($latest_log)) {
            $latest_log = botwriter_get_latest_log_by_post_id($post_id);
        }

        $prefill_prompt = is_array($latest_log) ? trim((string) ($latest_log['image_prompt'] ?? '')) : '';
        if ($prefill_prompt !== '') {
            $prompt_source = 'log';
            // Legacy migration: if prompt exists in log but not in post meta, persist it now.
            botwriter_save_post_image_prompt_meta($post_id, $prefill_prompt, $provider);
            botwriter_log('Image regeneration context: prompt recovered from log and migrated to meta', array(
                'post_id' => $post_id,
                'log_id' => intval($latest_log['id'] ?? 0),
                'prompt_len' => strlen($prefill_prompt),
            ));
        }
    }

    if ($prefill_prompt === '') {
        $title_prompt = trim((string) get_the_title($post_id));
        if ($title_prompt !== '') {
            $prefill_prompt = $title_prompt;
            $prompt_source = 'post_title';
            botwriter_log('Image regeneration context: fallback to post title', array(
                'post_id' => $post_id,
                'title_len' => strlen($title_prompt),
            ));
        }
    }

    $has_meta_prompt = in_array($prompt_source, array('meta_ai', 'meta_stock', 'meta_last'), true);

    $current_image_src = '';
    $current_attachment_id = intval(get_post_thumbnail_id($post_id));
    if ($current_attachment_id > 0) {
        $current_image = wp_get_attachment_image_src($current_attachment_id, 'medium_large');
        if (is_array($current_image) && !empty($current_image[0])) {
            $current_image_src = esc_url_raw($current_image[0]);
        }
    }

    botwriter_log('Image regeneration context resolved', array(
        'post_id' => $post_id,
        'provider' => $provider,
        'prompt_source' => $prompt_source,
        'prompt_len' => strlen($prefill_prompt),
        'has_meta_prompt' => $has_meta_prompt,
        'has_current_image' => ($current_image_src !== ''),
        'has_log' => is_array($latest_log),
        'log_id' => is_array($latest_log) ? intval($latest_log['id'] ?? 0) : 0,
    ));

    return array(
        'post_id' => $post_id,
        'prompt' => $prefill_prompt,
        'has_log' => is_array($latest_log),
        'has_meta_prompt' => $has_meta_prompt,
        'has_prompt' => ($prefill_prompt !== ''),
        'prompt_source' => $prompt_source,
        'provider' => $provider,
        'model' => (string) ($settings['model'] ?? ''),
        'provider_disabled' => ($provider === 'none'),
        'current_attachment_id' => $current_attachment_id,
        'current_image_src' => $current_image_src,
    );
}

/**
 * Generate an image URL using the direct backend endpoint (/images) with current settings.
 *
 * @param string $prompt Prompt text.
 * @return array
 */
function botwriter_generate_image_with_current_settings($prompt) {
    $prompt = trim((string) $prompt);
    if ($prompt === '') {
        return array('success' => false, 'message' => __('Image prompt is required.', 'botwriter'));
    }

    $settings = botwriter_get_current_image_generation_settings();
    $provider = (string) $settings['provider'];
    $model = (string) $settings['model'];

    if ($provider === 'none') {
        return array('success' => false, 'message' => __('Image provider is disabled in settings.', 'botwriter'));
    }

    $style_value = (string) ($settings['style_custom'] ?: $settings['style']);
    if ($style_value === 'realistic' || $style_value === 'none') {
        $style_value = '';
    }

    $payload = array(
        'prompt' => $prompt,
        'domain' => esc_url_raw(get_site_url()),
        'api_key' => get_option('botwriter_api_key'),
        'site_token' => get_option('botwriter_site_token', ''),
        // Image regenerations are UX actions and should not consume license quota.
        'no_count' => true,
        'provider' => $provider,
        'model' => $model,
        'size' => (string) $settings['size'],
        'quality' => (string) $settings['quality'],
        'style' => $style_value,
        'stockphoto_preferred' => (string) $settings['stockphoto_preferred'],
        'stockphoto_selection' => (string) $settings['stockphoto_selection'],
        'stockphoto_attribution' => (string) $settings['stockphoto_attribution'],
        // Forward client keys (edge endpoint overlays provider keys from this payload)
        'openai_api_key' => botwriter_decrypt_api_key(get_option('botwriter_openai_api_key')),
        'google_api_key' => botwriter_decrypt_api_key(get_option('botwriter_google_api_key')),
        'fal_api_key' => botwriter_decrypt_api_key(get_option('botwriter_fal_api_key')),
        'replicate_api_key' => botwriter_decrypt_api_key(get_option('botwriter_replicate_api_key')),
        'stability_api_key' => botwriter_decrypt_api_key(get_option('botwriter_stability_api_key')),
        'cloudflare_api_key' => botwriter_decrypt_api_key(get_option('botwriter_cloudflare_api_key')),
        'cloudflare_account_id' => get_option('botwriter_cloudflare_account_id'),
    );

    $ssl_verify = get_option('botwriter_sslverify');
    $ssl_verify = ($ssl_verify !== 'no');

    $remote_url = BOTWRITER_API_URL . 'images';
    $response = wp_remote_post($remote_url, array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($payload),
        'timeout' => 120,
        'sslverify' => $ssl_verify,
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);
    $result = json_decode($body_raw, true);

    if ($status_code !== 200 || !is_array($result) || ($result['status'] ?? '') !== 'success' || empty($result['download_url'])) {
        $error_message = '';
        if (is_array($result)) {
            $error_message = (string) ($result['error'] ?? $result['message'] ?? '');
        }
        if ($error_message === '') {
            $error_message = __('Image generation failed on server.', 'botwriter');
        }
        return array('success' => false, 'message' => $error_message);
    }

    $download_url = (string) $result['download_url'];
    $image_url = $download_url;
    if (strpos($download_url, 'http://') !== 0 && strpos($download_url, 'https://') !== 0) {
        $image_url = rtrim(BOTWRITER_API_URL, '/') . '/' . ltrim($download_url, '/');
    }

    return array(
        'success' => true,
        'image_url' => $image_url,
        'provider' => $provider,
        'model' => $model,
    );
}

/**
 * AJAX: fetch context for image regeneration modal.
 */
function botwriter_get_post_image_regeneration_context_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    check_ajax_referer('botwriter_regenerate_image_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id <= 0 || !get_post($post_id)) {
        wp_send_json_error(array('message' => __('Invalid post.', 'botwriter')));
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('You cannot edit this post.', 'botwriter')));
    }

    $context = botwriter_get_image_regeneration_context($post_id);
    botwriter_log('AJAX context response for image regeneration', array(
        'post_id' => $post_id,
        'prompt_source' => $context['prompt_source'] ?? 'unknown',
        'prompt_len' => strlen((string) ($context['prompt'] ?? '')),
        'has_meta_prompt' => !empty($context['has_meta_prompt']),
        'has_log' => !empty($context['has_log']),
    ));
    wp_send_json_success($context);
}
add_action('wp_ajax_botwriter_get_post_image_regeneration_context', 'botwriter_get_post_image_regeneration_context_ajax');

/**
 * AJAX: generate preview image only (does not apply to post yet).
 */
function botwriter_generate_post_image_preview_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    check_ajax_referer('botwriter_regenerate_image_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';

    if ($post_id <= 0 || !get_post($post_id)) {
        wp_send_json_error(array('message' => __('Invalid post.', 'botwriter')));
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('You cannot edit this post.', 'botwriter')));
    }

    $generated = botwriter_generate_image_with_current_settings($prompt);
    if (empty($generated['success'])) {
        wp_send_json_error(array('message' => (string) ($generated['message'] ?? __('Image generation failed.', 'botwriter'))));
    }

    wp_send_json_success(array(
        'post_id' => $post_id,
        'prompt' => trim((string) $prompt),
        'image_url' => (string) $generated['image_url'],
        'provider' => (string) $generated['provider'],
        'model' => (string) $generated['model'],
    ));
}
add_action('wp_ajax_botwriter_generate_post_image_preview', 'botwriter_generate_post_image_preview_ajax');

/**
 * Check if an attachment is used as featured image by posts other than the current one.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $exclude_post_id Post ID to exclude.
 * @return bool
 */
function botwriter_is_attachment_featured_elsewhere($attachment_id, $exclude_post_id = 0) {
    global $wpdb;

    $attachment_id = intval($attachment_id);
    $exclude_post_id = intval($exclude_post_id);

    if ($attachment_id <= 0) {
        return false;
    }

    $query = "SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d";
    $params = array($attachment_id);

    if ($exclude_post_id > 0) {
        $query .= " AND post_id <> %d";
        $params[] = $exclude_post_id;
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is built dynamically and prepared with placeholders for the only user-supplied values.
    $count = $wpdb->get_var($wpdb->prepare($query, $params));
    return intval($count) > 0;
}

/**
 * Register a lightweight image-only log entry for future prompt prefill.
 *
 * @param int        $post_id Post ID.
 * @param string     $prompt Prompt used.
 * @param string     $image_url Generated image URL.
 * @param string     $provider Current provider.
 * @param string     $model Current model.
 * @param array|null $source_log Existing latest log for this post.
 * @return int|false
 */
function botwriter_register_only_image_log($post_id, $prompt, $image_url, $provider, $model, $source_log = null) {
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return false;
    }

    $base = array(
        'id_task' => 0,
        'id_task_server' => 0,
        'post_status' => $post->post_status ?: 'draft',
        'task_name' => sprintf(
            /* translators: %d: post ID. */
            __('Image regeneration for post #%d', 'botwriter'),
            intval($post_id)
        ),
        'task_type' => 'only_image',
        'writer' => 'orion',
        'narration' => 'Descriptive',
        'custom_style' => '',
        'post_language' => substr(get_locale(), 0, 2),
        'post_length' => '800',
        'link_post_original' => get_permalink($post_id),
        'id_post_published' => intval($post_id),
        'task_status' => 'completed',
        'error' => '',
        'website_name' => '',
        'website_type' => 'ai',
        'domain_name' => esc_url_raw(get_site_url()),
        'post_type' => $post->post_type ?: 'post',
        'category_id' => '',
        'taxonomy_data' => '',
        'website_category_id' => '',
        'aigenerated_title' => get_the_title($post_id),
        'aigenerated_content' => '',
        'aigenerated_tags' => '',
        'aigenerated_image' => $image_url,
        'post_count' => '1',
        'post_order' => '',
        'title_prompt' => '',
        'content_prompt' => '',
        'tags_prompt' => '',
        'image_prompt' => $prompt,
        'image_generating_status' => 'completed',
        'author_selection' => strval($post->post_author ?: get_current_user_id()),
        'news_time_published' => '',
        'news_language' => '',
        'news_country' => '',
        'news_keyword' => '',
        'news_source' => '',
        'rss_source' => '',
        'ai_keywords' => '',
        'disable_ai_images' => 0,
        'template_id' => null,
        'intentosfase1' => 0,
        'last_execution_time' => current_time('mysql'),
    );

    // Reuse as much context as possible from latest known log.
    if (is_array($source_log) && !empty($source_log)) {
        $inherit_keys = array(
            'id_task',
            'post_status',
            'task_name',
            'writer',
            'narration',
            'custom_style',
            'post_language',
            'post_length',
            'website_name',
            'website_type',
            'domain_name',
            'post_type',
            'category_id',
            'taxonomy_data',
            'website_category_id',
            'title_prompt',
            'content_prompt',
            'tags_prompt',
            'author_selection',
            'ai_keywords',
            'template_id',
        );

        foreach ($inherit_keys as $key) {
            if (array_key_exists($key, $source_log) && $source_log[$key] !== null && $source_log[$key] !== '') {
                $base[$key] = $source_log[$key];
            }
        }
    }

    // Ensure this log is identifiable as image-only and references current settings context.
    $base['task_type'] = 'only_image';
    $base['task_status'] = 'completed';
    $base['id_post_published'] = intval($post_id);
    $base['image_prompt'] = $prompt;
    $base['aigenerated_image'] = $image_url;
    $base['error'] = '';
    $base['last_execution_time'] = current_time('mysql');
    $base['task_name'] = sprintf(
        /* translators: 1: image provider name, 2: image model name, 3: post ID. */
        __('Only image regeneration (%1$s / %2$s) - Post #%3$d', 'botwriter'),
        $provider,
        $model,
        intval($post_id)
    );

    return botwriter_logs_register($base);
}

/**
 * AJAX: apply a regenerated image URL as featured image for an existing post.
 * If no image_url is provided, it can generate one using current settings (legacy fallback).
 */
function botwriter_apply_post_regenerated_image_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    check_ajax_referer('botwriter_regenerate_image_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
    $cleanup_policy = isset($_POST['cleanup_policy']) ? sanitize_key(wp_unslash($_POST['cleanup_policy'])) : 'keep_old';
    $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
    $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';

    if ($post_id <= 0 || !get_post($post_id)) {
        wp_send_json_error(array('message' => __('Invalid post.', 'botwriter')));
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('You cannot edit this post.', 'botwriter')));
    }

    if (trim($prompt) === '') {
        wp_send_json_error(array('message' => __('Image prompt is required.', 'botwriter')));
    }

    if (!in_array($cleanup_policy, array('keep_old', 'delete_old'), true)) {
        $cleanup_policy = 'keep_old';
    }

    if ($image_url === '') {
        // Legacy fallback: if called without image_url, generate directly now.
        $generated = botwriter_generate_image_with_current_settings($prompt);
        if (empty($generated['success'])) {
            wp_send_json_error(array('message' => (string) ($generated['message'] ?? __('Image generation failed.', 'botwriter'))));
        }
        $image_url = (string) $generated['image_url'];
        $provider = (string) $generated['provider'];
        $model = (string) $generated['model'];
    }

    if (strpos($image_url, 'http://') !== 0 && strpos($image_url, 'https://') !== 0) {
        wp_send_json_error(array('message' => __('Invalid image URL.', 'botwriter')));
    }

    if ($provider === '' || $model === '') {
        $settings = botwriter_get_current_image_generation_settings();
        if ($provider === '') {
            $provider = (string) ($settings['provider'] ?? 'dalle');
        }
        if ($model === '') {
            $model = (string) ($settings['model'] ?? 'gpt-image-1');
        }
    }

    $old_thumbnail_id = get_post_thumbnail_id($post_id);
    $post_title = get_the_title($post_id);

    botwriter_attach_image_to_post($post_id, $image_url, $post_title);
    $new_thumbnail_id = get_post_thumbnail_id($post_id);

    if (empty($new_thumbnail_id)) {
        wp_send_json_error(array('message' => __('Image was generated but could not be attached as featured image.', 'botwriter')));
    }

    $deleted_old = false;
    $delete_note = '';
    if ($cleanup_policy === 'delete_old' && !empty($old_thumbnail_id) && intval($old_thumbnail_id) !== intval($new_thumbnail_id)) {
        if (botwriter_is_attachment_featured_elsewhere(intval($old_thumbnail_id), $post_id)) {
            $delete_note = __('Previous featured image was not deleted because it is used by other posts.', 'botwriter');
        } else {
            $deleted_old = (bool) wp_delete_attachment(intval($old_thumbnail_id), true);
            if (!$deleted_old) {
                $delete_note = __('Previous featured image could not be deleted automatically.', 'botwriter');
            }
        }
    }

    $latest_log = botwriter_get_latest_log_by_post_id($post_id);
    botwriter_log('Apply regenerated image: persisting prompt to log/meta', array(
        'post_id' => $post_id,
        'provider' => $provider,
        'model' => $model,
        'prompt_len' => strlen((string) $prompt),
        'latest_log_id' => is_array($latest_log) ? intval($latest_log['id'] ?? 0) : 0,
    ));
    $log_id = botwriter_register_only_image_log($post_id, $prompt, $image_url, $provider, $model, $latest_log);
    botwriter_save_post_image_prompt_meta($post_id, $prompt, $provider);

    $thumb_src = wp_get_attachment_image_src($new_thumbnail_id, 'medium');
    $featured_src = is_array($thumb_src) ? $thumb_src[0] : '';

    wp_send_json_success(array(
        'message' => __('Featured image regenerated successfully.', 'botwriter'),
        'post_id' => $post_id,
        'image_url' => $image_url,
        'featured_image_src' => $featured_src,
        'attachment_id' => intval($new_thumbnail_id),
        'provider' => $provider,
        'model' => $model,
        'deleted_old' => $deleted_old,
        'delete_note' => $delete_note,
        'log_id' => $log_id ?: 0,
    ));
}
add_action('wp_ajax_botwriter_apply_post_regenerated_image', 'botwriter_apply_post_regenerated_image_ajax');
// Backward-compatible alias for previous one-step endpoint name.
add_action('wp_ajax_botwriter_regenerate_post_image', 'botwriter_apply_post_regenerated_image_ajax');

/**
 * Build an instruction prompt for the post editor assistant.
 *
 * @param string $target Selected field target.
 * @param string $user_prompt User instruction.
 * @param array  $context Current post context.
 * @return string
 */
function botwriter_build_editor_assistant_prompt($target, $user_prompt, $context) {
    $rules = array(
        'text' => 'Return only the improved post body as valid HTML. Do not include title, tags, excerpt, SEO meta, or explanations.',
        'title' => 'Return only one improved post title as plain text. No quotes, bullets, or commentary.',
        'tags' => 'Return only a comma-separated list of tags. No hashtags, no numbering, and no extra text.',
        'excerpt' => 'Return only one short excerpt (max 160 characters) as plain text.',
        'seo_meta' => 'Return only one SEO meta description (max 160 characters) as plain text.',
    );

    $context_payload = array(
        'title' => (string) ($context['title'] ?? ''),
        'content' => (string) ($context['content'] ?? ''),
        'tags' => (string) ($context['tags'] ?? ''),
        'excerpt' => (string) ($context['excerpt'] ?? ''),
        'seo_meta' => (string) ($context['seo_meta'] ?? ''),
    );

    $context_json = wp_json_encode($context_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($context_json) || $context_json === '') {
        $context_json = '{}';
    }

    $target_rule = isset($rules[$target]) ? $rules[$target] : $rules['text'];

    return "You are BotWriter inline editor assistant for WordPress.\n"
        . "The user is editing a post right now.\n"
        . "Selected field: {$target}\n"
        . "Output format rule: {$target_rule}\n"
        . "Keep the same language as the source content unless the user asks otherwise.\n"
        . "Never use markdown code fences.\n\n"
        . "User instruction:\n{$user_prompt}\n\n"
        . "Current post context JSON:\n{$context_json}";
}

/**
 * Call the Cloudflare Worker for editor assistant generation.
 *
 * Uses a dedicated /editor endpoint and falls back to /woo only when the
 * new endpoint is not available yet.
 *
 * @param string $provider Provider key (openai, anthropic, google, etc.).
 * @param string $api_key Provider API key.
 * @param string $model Model name.
 * @param string $prompt Prompt text.
 * @param int    $max_tokens Max output tokens.
 * @param float  $temperature Temperature.
 * @return string|WP_Error
 */
function botwriter_call_editor_worker($provider, $api_key, $model, $prompt, $max_tokens = 2048, $temperature = 0.35) {
    $ssl_verify = get_option('botwriter_sslverify', 'yes') === 'yes';

    $provider_map = array(
        'google' => 'gemini',
    );
    $worker_provider = isset($provider_map[$provider]) ? $provider_map[$provider] : $provider;

    $key_field_map = array(
        'openai' => 'openai_api_key',
        'anthropic' => 'anthropic_api_key',
        'google' => 'google_api_key',
        'mistral' => 'mistral_api_key',
        'groq' => 'groq_api_key',
        'openrouter' => 'openrouter_api_key',
    );

    $domain = preg_replace('#^https?://#', '', home_url());
    $domain = rtrim((string) $domain, '/');

    $payload = array(
        'prompt' => $prompt,
        'domain' => $domain,
        'provider' => $worker_provider,
        'model' => $model,
        'max_tokens' => intval($max_tokens),
        'temperature' => floatval($temperature),
        'site_token' => get_option('botwriter_site_token', ''),
        // Keep editor assistant out of quota checks for now.
        'no_count' => true,
        'assistant' => 'post_editor',
    );

    if (!empty($api_key) && isset($key_field_map[$provider])) {
        $payload[$key_field_map[$provider]] = $api_key;
    }

    $base_url = rtrim(BOTWRITER_API_URL, '/');
    $endpoints = array(
        $base_url . '/editor',
        $base_url . '/woo',
    );

    $endpoint_total = count($endpoints);
    foreach ($endpoints as $index => $remote_url) {
        $response = wp_remote_post($remote_url, array(
            'timeout' => 90,
            'sslverify' => $ssl_verify,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            if ($index === $endpoint_total - 1) {
                return new WP_Error('editor_worker_network', $response->get_error_message(), array(
                    'provider' => (string) $provider,
                    'worker_provider' => (string) $worker_provider,
                    'model' => (string) $model,
                    'endpoint' => (string) $remote_url,
                    'transport_code' => (string) $response->get_error_code(),
                ));
            }
            continue;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // If the new route is not deployed yet, retry once with /woo.
        if ($http_code === 404 && $index === 0) {
            continue;
        }

        if (!empty($data['site_token'])) {
            update_option('botwriter_site_token', sanitize_text_field((string) $data['site_token']));
        }

        if (!empty($data['warning']) && function_exists('botwriter_announcements_add')) {
            botwriter_announcements_add(
                __('Service notice', 'botwriter'),
                (string) $data['warning']
            );
        }

        if ($http_code !== 200 || (isset($data['status']) && $data['status'] === 'error')) {
            $error_message = '';
            if (is_array($data)) {
                $error_message = (string) ($data['error'] ?? $data['message'] ?? '');
            }
            if ($error_message === '') {
                $error_message = "HTTP {$http_code}";
            }
            return new WP_Error('editor_worker_error', $error_message, array(
                'provider' => (string) $provider,
                'worker_provider' => (string) $worker_provider,
                'model' => (string) $model,
                'endpoint' => (string) $remote_url,
                'http_code' => (int) $http_code,
                'worker_error_code' => is_array($data) ? (string) ($data['error_code'] ?? '') : '',
            ));
        }

        $content = is_array($data) ? (string) ($data['content'] ?? '') : '';
        if ($content === '') {
            return new WP_Error('editor_worker_empty', __('AI returned an empty response.', 'botwriter'));
        }

        return $content;
    }

    return new WP_Error('editor_worker_unavailable', __('Editor assistant service is currently unavailable.', 'botwriter'));
}

// SEO module relocated to includes/seo/ — see botwriter_seo_register_admin_menu and botwriter_seo_auto_internal_links_postprocess.

/**
 * Strip wrapping quotes added by model responses.
 *
 * @param string $text Input text.
 * @return string
 */
function botwriter_editor_strip_wrapping_quotes($text) {
    $text = trim((string) $text);

    for ($i = 0; $i < 3; $i++) {
        $next = preg_replace("/^[\"'`\\x{201C}\\x{201D}\\x{00AB}\\x{00BB}\\x{2018}\\x{2019}]+|[\"'`\\x{201C}\\x{201D}\\x{00AB}\\x{00BB}\\x{2018}\\x{2019}]+$/u", '', $text);
        $next = trim((string) $next);
        if ($next === $text) {
            break;
        }
        $text = $next;
    }

    return $text;
}

/**
 * AJAX: generate post editor assistant output for selected field.
 */
function botwriter_editor_assistant_generate_ajax() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    check_ajax_referer('botwriter_editor_assistant_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('You cannot edit this post.', 'botwriter')));
    }

    $allowed_targets = array('text', 'title', 'tags', 'excerpt', 'seo_meta', 'internal_links');
    $target = isset($_POST['target']) ? sanitize_key(wp_unslash($_POST['target'])) : 'text';
    if (!in_array($target, $allowed_targets, true)) {
        $target = 'text';
    }

    $user_prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
    if ($user_prompt === '') {
        wp_send_json_error(array('message' => __('Prompt is required.', 'botwriter')));
    }

    $context = array(
        'title' => isset($_POST['context_title']) ? sanitize_text_field(wp_unslash($_POST['context_title'])) : '',
        'content' => isset($_POST['context_content']) ? wp_kses_post(wp_unslash($_POST['context_content'])) : '',
        'tags' => isset($_POST['context_tags']) ? sanitize_text_field(wp_unslash($_POST['context_tags'])) : '',
        'excerpt' => isset($_POST['context_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['context_excerpt'])) : '',
        'seo_meta' => isset($_POST['context_seo_meta']) ? sanitize_textarea_field(wp_unslash($_POST['context_seo_meta'])) : '',
    );

    $context_limits = array(
        'title' => 300,
        'content' => 30000,
        'tags' => 1000,
        'excerpt' => 500,
        'seo_meta' => 500,
    );
    foreach ($context_limits as $field => $max_length) {
        $value = (string) ($context[$field] ?? '');
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) > $max_length) {
                $context[$field] = mb_substr($value, 0, $max_length);
            }
        } elseif (strlen($value) > $max_length) {
            $context[$field] = substr($value, 0, $max_length);
        }
    }

    $keyphrases_raw = isset($_POST['context_keyphrases']) ? sanitize_text_field(wp_unslash($_POST['context_keyphrases'])) : '';
    $keyphrases = botwriter_editor_parse_keyphrases($keyphrases_raw);

    $provider = sanitize_key((string) get_option('botwriter_text_provider', 'openai'));
    $model = function_exists('botwriter_get_current_text_model')
        ? (string) botwriter_get_current_text_model()
        : (string) get_option('botwriter_openai_model', 'gpt-5.4-mini');
    $api_key = function_exists('botwriter_get_provider_api_key')
        ? (string) botwriter_get_provider_api_key($provider)
        : '';

    // SEO settings tab controls automatic publish post-processing, not the editor widget.
    $internal_links_ai_enabled = true;
    $internal_links_noai_enabled = true;

    if ($target === 'internal_links') {
        $candidates = botwriter_editor_get_internal_link_candidates($post_id, $context, 26);
        if (empty($candidates)) {
            wp_send_json_success(array(
                'target' => $target,
                'suggestions' => array(),
                'provider' => $provider,
                'model' => $model,
                'candidate_count' => 0,
                'strategy' => 'no_candidates',
            ));
        }

        $use_ai_strategy = $internal_links_ai_enabled && $api_key !== '';
        if (!$use_ai_strategy) {
            if (!$internal_links_noai_enabled) {
                if ($api_key === '') {
                    wp_send_json_error(array('message' => __('Please configure the API key for your selected text provider, or enable deterministic internal-link mode in SEO settings.', 'botwriter')));
                }

                wp_send_json_error(array('message' => __('Internal-link generation is disabled. Enable AI mode or deterministic mode in SEO settings.', 'botwriter')));
            }

            $suggestions = botwriter_editor_build_internal_links_noai_suggestions($candidates, $context, $keyphrases, 8);
            wp_send_json_success(array(
                'target' => $target,
                'suggestions' => $suggestions,
                'provider' => $provider,
                'model' => $model,
                'candidate_count' => count($candidates),
                'keyphrases' => $keyphrases,
                'strategy' => 'no_ai',
            ));
        }

        $links_prompt = botwriter_build_editor_internal_links_prompt($user_prompt, $context, $keyphrases, $candidates);
        $generated_links = botwriter_call_editor_worker($provider, $api_key, $model, $links_prompt, 2400, 0.2);

        if (is_wp_error($generated_links)) {
            $error_message = $generated_links->get_error_message();
            if ($error_message === '') {
                $error_message = __('Could not generate internal link suggestions.', 'botwriter');
            }
            wp_send_json_error(array('message' => $error_message));
        }

        $suggestions = botwriter_parse_editor_internal_links_response((string) $generated_links, $candidates);

        wp_send_json_success(array(
            'target' => $target,
            'suggestions' => $suggestions,
            'provider' => $provider,
            'model' => $model,
            'candidate_count' => count($candidates),
            'keyphrases' => $keyphrases,
            'strategy' => 'ai',
        ));
    }

    if ($api_key === '') {
        wp_send_json_error(array('message' => __('Please configure the API key for your selected text provider in BotWriter settings.', 'botwriter')));
    }

    $max_tokens = ($target === 'text') ? 4096 : 700;
    $assistant_prompt = botwriter_build_editor_assistant_prompt($target, $user_prompt, $context);
    $generated = botwriter_call_editor_worker($provider, $api_key, $model, $assistant_prompt, $max_tokens, 0.35);

    if (is_wp_error($generated)) {
        $error_message = $generated->get_error_message();
        if ($error_message === '') {
            $error_message = __('Could not generate a response.', 'botwriter');
        }
        wp_send_json_error(array('message' => $error_message));
    }

    $content = trim((string) $generated);
    $content = preg_replace('/^```(?:[a-zA-Z0-9_-]+)?\s*/', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $content = trim((string) $content);

    if ($content === '') {
        wp_send_json_error(array('message' => __('AI returned an empty response.', 'botwriter')));
    }

    if ($target === 'title') {
        $content = sanitize_text_field($content);
        $content = botwriter_editor_strip_wrapping_quotes($content);
    } elseif ($target === 'tags') {
        $parts = preg_split('/[\r\n,]+/', $content);
        $parts = is_array($parts) ? $parts : array();
        $tags = array();
        foreach ($parts as $part) {
            $tag = trim(sanitize_text_field((string) $part));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));
        $content = implode(', ', $tags);
    } elseif ($target === 'excerpt' || $target === 'seo_meta') {
        $content = botwriter_editor_strip_wrapping_quotes($content);
        if (function_exists('botwriter_sanitize_meta_description')) {
            $content = botwriter_sanitize_meta_description($content);
        } else {
            $content = sanitize_textarea_field($content);
        }
    } else {
        $content = botwriter_editor_strip_wrapping_quotes($content);
        $content = str_replace(array("\\r\\n", "\\n", "\\r"), "\n", $content);
        $content = preg_replace('/(\r?\n){3,}/', "\n\n", $content);
        $content = wp_kses_post($content);
    }

    wp_send_json_success(array(
        'target' => $target,
        'content' => $content,
        'provider' => $provider,
        'model' => $model,
    ));
}
add_action('wp_ajax_botwriter_editor_ai_generate', 'botwriter_editor_assistant_generate_ajax');

/**
 * Render SEO checks for editor widget tab.
 *
 * @param array $seo_report SEO report array.
 * @return string
 */
function botwriter_editor_render_seo_checks_html($seo_report) {
    $seo_report = is_array($seo_report) ? $seo_report : array();
    $seo_score  = (int) ($seo_report['score'] ?? 0);
    $seo_grade  = (string) ($seo_report['grade'] ?? 'n/a');
    $grade_label = function_exists('botwriter_seo_grade_label')
        ? botwriter_seo_grade_label($seo_grade)
        : ucfirst($seo_grade);

    $seo_counts = array('good' => 0, 'warn' => 0, 'bad' => 0);
    foreach ((array) ($seo_report['checks'] ?? array()) as $check) {
        $status = function_exists('botwriter_seo_check_status')
            ? botwriter_seo_check_status($check)
            : (!empty($check['passed']) ? 'good' : 'bad');
        $seo_counts[$status] = ($seo_counts[$status] ?? 0) + 1;
    }

    ob_start();
    ?>
    <div class="bw-editor-ai-seo-score-box bw-grade-<?php echo esc_attr($seo_grade); ?>">
        <div class="bw-editor-ai-seo-score-main"><?php echo (int) $seo_score; ?></div>
        <div class="bw-editor-ai-seo-score-label"><?php echo esc_html($grade_label); ?></div>
    </div>
    <div class="bw-summary-row">
        <span class="bw-pill good"><span class="dashicons dashicons-yes-alt"></span> <?php echo (int) ($seo_counts['good'] ?? 0); ?> <?php esc_html_e('passed', 'botwriter'); ?></span>
        <span class="bw-pill warn"><span class="dashicons dashicons-warning"></span> <?php echo (int) ($seo_counts['warn'] ?? 0); ?> <?php esc_html_e('to improve', 'botwriter'); ?></span>
        <span class="bw-pill bad"><span class="dashicons dashicons-dismiss"></span> <?php echo (int) ($seo_counts['bad'] ?? 0); ?> <?php esc_html_e('issues', 'botwriter'); ?></span>
    </div>
    <ul class="bw-report-checks">
        <?php foreach ((array) ($seo_report['checks'] ?? array()) as $check) :
            $status = function_exists('botwriter_seo_check_status')
                ? botwriter_seo_check_status($check)
                : (!empty($check['passed']) ? 'good' : 'bad');
            $icon = function_exists('botwriter_seo_status_icon')
                ? botwriter_seo_status_icon($status)
                : ($status === 'good' ? 'dashicons-yes-alt' : ($status === 'warn' ? 'dashicons-warning' : 'dashicons-dismiss'));
        ?>
            <li class="bw-check bw-status-<?php echo esc_attr($status); ?>">
                <span class="dashicons <?php echo esc_attr($icon); ?> bw-check-icon"></span>
                <div class="bw-check-body">
                    <div class="bw-check-label"><?php echo esc_html((string) ($check['label'] ?? '')); ?></div>
                    <?php if (!empty($check['hint'])) : ?>
                        <div class="bw-check-hint"><?php echo esc_html((string) $check['hint']); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ((int) ($check['weight'] ?? 0) > 0) : ?>
                    <span class="bw-weight" title="<?php esc_attr_e('Weight', 'botwriter'); ?>"><?php echo (int) ($check['weight'] ?? 0); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render readability checks for editor widget tab.
 *
 * @param array $readability_report Readability report array.
 * @return string
 */
function botwriter_editor_render_readability_checks_html($readability_report) {
    $readability_report = is_array($readability_report) ? $readability_report : array();
    $read_score  = (int) ($readability_report['score'] ?? 0);
    $read_grade  = (string) ($readability_report['grade'] ?? 'n/a');
    $grade_label = function_exists('botwriter_seo_grade_label')
        ? botwriter_seo_grade_label($read_grade)
        : ucfirst($read_grade);

    $read_counts = array('good' => 0, 'warn' => 0, 'bad' => 0);
    foreach ((array) ($readability_report['checks'] ?? array()) as $check) {
        $status = (string) ($check['status'] ?? 'bad');
        $read_counts[$status] = ($read_counts[$status] ?? 0) + 1;
    }

    ob_start();
    ?>
    <div class="bw-editor-ai-seo-score-box bw-grade-<?php echo esc_attr($read_grade); ?>">
        <div class="bw-editor-ai-seo-score-main"><?php echo (int) $read_score; ?></div>
        <div class="bw-editor-ai-seo-score-label"><?php echo esc_html($grade_label); ?></div>
    </div>
    <div class="bw-summary-row">
        <span class="bw-pill good"><span class="dashicons dashicons-yes-alt"></span> <?php echo (int) ($read_counts['good'] ?? 0); ?> <?php esc_html_e('great', 'botwriter'); ?></span>
        <span class="bw-pill warn"><span class="dashicons dashicons-warning"></span> <?php echo (int) ($read_counts['warn'] ?? 0); ?> <?php esc_html_e('ok', 'botwriter'); ?></span>
        <span class="bw-pill bad"><span class="dashicons dashicons-dismiss"></span> <?php echo (int) ($read_counts['bad'] ?? 0); ?> <?php esc_html_e('hard', 'botwriter'); ?></span>
    </div>
    <ul class="bw-report-checks">
        <?php foreach ((array) ($readability_report['checks'] ?? array()) as $check) :
            $status = (string) ($check['status'] ?? 'bad');
            $icon = function_exists('botwriter_seo_status_icon')
                ? botwriter_seo_status_icon($status)
                : ($status === 'good' ? 'dashicons-yes-alt' : ($status === 'warn' ? 'dashicons-warning' : 'dashicons-dismiss'));
        ?>
            <li class="bw-check bw-status-<?php echo esc_attr($status); ?>">
                <span class="dashicons <?php echo esc_attr($icon); ?> bw-check-icon"></span>
                <div class="bw-check-body">
                    <div class="bw-check-label">
                        <?php echo esc_html((string) ($check['label'] ?? '')); ?>
                        <?php if (!empty($check['value'])) : ?>
                            <span class="bw-tag"><?php echo esc_html((string) $check['value']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($check['hint'])) : ?>
                        <div class="bw-check-hint"><?php echo esc_html((string) $check['hint']); ?></div>
                    <?php endif; ?>
                </div>
                <span class="bw-weight" title="<?php esc_attr_e('Weight', 'botwriter'); ?>"><?php echo (int) ($check['weight'] ?? 0); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return (string) ob_get_clean();
}

/**
 * AJAX: return SEO and readability report sections for editor widget.
 */
function botwriter_editor_assistant_get_seo_report_ajax() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    check_ajax_referer('botwriter_editor_assistant_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
    if ($post_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid post.', 'botwriter')));
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('You cannot edit this post.', 'botwriter')));
    }

    if (!function_exists('botwriter_seo_compute_score') || !function_exists('botwriter_seo_compute_readability')) {
        wp_send_json_error(array('message' => __('SEO module is not available.', 'botwriter')));
    }

    $seo_report = botwriter_seo_compute_score($post_id);
    $readability_report = botwriter_seo_compute_readability($post_id);

    wp_send_json_success(array(
        'seo_html' => botwriter_editor_render_seo_checks_html($seo_report),
        'readability_html' => botwriter_editor_render_readability_checks_html($readability_report),
    ));
}
add_action('wp_ajax_botwriter_editor_ai_get_seo_report', 'botwriter_editor_assistant_get_seo_report_ajax');



if (!function_exists('deactivate_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}


function botwriter_enqueue_styles(){
    $my_plugin_dir = plugin_dir_url(__FILE__);
    $screen = get_current_screen();

    $slug = $screen->id;

    // Keep submenu cleanup styles available across the whole admin so folded flyouts stay filtered too.
    wp_register_style('botwriter_admin_menu', $my_plugin_dir . 'assets/css/admin-menu.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin-menu.css'));
    wp_enqueue_style('botwriter_admin_menu');

    // Welcome banner CSS - load on ALL admin pages if not dismissed
    // (because admin_notices shows on all pages)
    $welcome_dismissed = get_option('botwriter_welcome_dismissed', false);
    if (!$welcome_dismissed) {
        wp_register_style('botwriter_welcome_banner', $my_plugin_dir . 'assets/css/welcome-banner.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/welcome-banner.css'));
        wp_enqueue_style('botwriter_welcome_banner');
    }

    if ($screen && $screen->base === 'post' && (string) $screen->post_type === 'post' && current_user_can('edit_posts') && get_option('botwriter_editor_assistant_enabled', '1') === '1') {
        wp_register_style('botwriter_editor_assistant', $my_plugin_dir . 'assets/css/editor-ai-assistant.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/editor-ai-assistant.css'));
        wp_enqueue_style('botwriter_editor_assistant');
    }

    // Only enqueue other styles for BotWriter admin screens

    if (strpos((string)$slug, 'botwriter') !== false) {

        // Register and enqueue styles with dynamic versioning for better caching
        wp_register_style('botwriter_bootstrap', $my_plugin_dir . 'assets/css/bootstrap.min.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/bootstrap.min.css'));
        wp_enqueue_style('botwriter_bootstrap');
        
        wp_register_style('botwriter_jquery_ui', $my_plugin_dir . 'assets/css/jquery-ui.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/jquery-ui.css'));
        wp_enqueue_style('botwriter_jquery_ui');

        wp_register_style('botwriter_loader', $my_plugin_dir . 'assets/css/loader.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/loader.css'));
        wp_enqueue_style('botwriter_loader');

        wp_register_style('botwriter_style', $my_plugin_dir . 'assets/css/style.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/style.css'));
        wp_enqueue_style('botwriter_style');

        // Settings page specific styles
        if (strpos((string)$slug, 'botwriter_settings') !== false) {
            wp_register_style('botwriter_settings', $my_plugin_dir . 'assets/css/settings.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/settings.css'));
            wp_enqueue_style('botwriter_settings');
        }

        if ($slug === 'botwriter_page_botwriter_siterewriter_page') {
            wp_register_style('botwriter_siterewriter', $my_plugin_dir . 'assets/css/siterewriter.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/siterewriter.css'));
            wp_enqueue_style('botwriter_siterewriter');
        }
    }
}

add_action('admin_enqueue_scripts', 'botwriter_enqueue_styles');



  
// Hook to add the admin menu
add_action('admin_menu', function() {
    add_menu_page(
        __('BotWriter', 'botwriter'),  
        __('BotWriter', 'botwriter'),
        'manage_options',
        'botwriter_menu',
        'botwriter_admin_page',
        plugin_dir_url(__FILE__) . '/assets/images/icono25.png',
        90
    );

    add_submenu_page('botwriter_menu',
        __('Write now', 'botwriter'),
        __('Write now', 'botwriter'),
        'manage_options',
        'botwriter_write_now',
        'botwriter_quick_post_page_handler'
    );

    add_submenu_page('botwriter_menu',
        __('New task', 'botwriter'),  
        __('New task', 'botwriter'),
        'manage_options',
        'botwriter_addnew_page',
        'botwriter_addnew_page_handler'
    );

    // Register under parent to avoid deprecations (null parent). We'll hide it from the submenu below.
    add_submenu_page('botwriter_menu',
        __('Super Task AI', 'botwriter'),
        __('Super Task AI', 'botwriter'),
        'manage_options',
        'botwriter_super_page',
        'botwriter_super_page_handler'
    );

    add_submenu_page('botwriter_menu',
        __('Tasks AI', 'botwriter'),
        __('Tasks AI', 'botwriter'),
        'manage_options',
        'botwriter_automatic_posts',
        'botwriter_automatic_posts_page'
    );

    add_submenu_page('botwriter_menu',
        __('Content Rewriter', 'botwriter'),
        __('Content Rewriter', 'botwriter'),
        'manage_options',
        'botwriter_rewriter_page',
        'botwriter_rewriter_page_handler'
    );

    add_submenu_page('botwriter_menu',
        __('Site Rewriter', 'botwriter'),
        __('Site Rewriter', 'botwriter'),
        'manage_options',
        'botwriter_siterewriter_page',
        'botwriter_siterewriter_page_handler'
    );

     // for development
     /*
    add_submenu_page('botwriter_menu', 
        __('Test Call', 'botwriter'),
        __('Test Call', 'botwriter'),
        'manage_options',
        'botwriter_prueba',
        'botwriter_prueba'
    );
    */
    

    // Register the edit/detail page under the parent, then hide it programmatically to avoid null parent deprecations
    $hook = add_submenu_page('botwriter_menu',                                 
        __('Add New Task', 'botwriter'),
        __('Add New Task', 'botwriter'),
        'manage_options',                
        'botwriter_automatic_post_new',        
        'botwriter_form_page_handler'          
    );

    add_submenu_page('botwriter_menu',
        __('Settings', 'botwriter'),
        __('Settings', 'botwriter'),
        'manage_options',
        'botwriter_settings',
        'botwriter_settings_page_handler'
    );

    add_submenu_page('botwriter_menu',
        __('Templates', 'botwriter'),
        __('Templates', 'botwriter'),
        'manage_options',
        'botwriter_templates',
        'botwriter_templates_page_handler'
    );

    add_submenu_page('botwriter_menu',
        __('Logs', 'botwriter'),
        get_option('botwriter_stopformany', false)
            ? __('Logs', 'botwriter') . ' <span class="update-plugins count-1" style="background:#d63638;"><span class="plugin-count">!</span></span>'
            : __('Logs', 'botwriter'),
        'manage_options',
        'botwriter_logs',
        'botwriter_logs_page_handler'
    );
});

add_filter('submenu_file', function($submenu_file) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter used to highlight hidden submenu pages.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (in_array($page, array('botwriter_rewriter_page', 'botwriter_siterewriter_page'), true)) {
        return 'botwriter_addnew_page';
    }
    return $submenu_file;
});

// CSS for hiding duplicate submenu entries is now in assets/css/admin-menu.css
// and enqueued via botwriter_enqueue_styles()





function botwriter_prueba() {
    if (!current_user_can('manage_options')) {
        return;
    }    
    
    ?>

    <h1>Prueba...</h1>
    <div>        
        Llamando a la funcion que ejecuta las tareas
    </div>

    <?php
    botwriter_scheduled_events_execute_tasks();

}






// First screen of the plugin
function botwriter_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if any API key is configured
    $has_api_key = false;
    $text_providers = [
        'botwriter_openai_api_key',
        'botwriter_anthropic_api_key',
        'botwriter_google_api_key',
        'botwriter_mistral_api_key',
        'botwriter_groq_api_key',
        'botwriter_openrouter_api_key'
    ];
    foreach ($text_providers as $provider_key) {
        $key_value = get_option($provider_key);
        if (!empty($key_value) && function_exists('botwriter_decrypt_api_key')) {
            $decrypted = botwriter_decrypt_api_key($key_value);
            if (!empty($decrypted)) {
                $has_api_key = true;
                break;
            }
        }
    }
    
    $settings_url = admin_url('admin.php?page=botwriter_settings');
    $addnew_url = admin_url('admin.php?page=botwriter_addnew_page');
    $tasks_url = admin_url('admin.php?page=botwriter_automatic_posts');
    $logs_url = admin_url('admin.php?page=botwriter_logs');
    ?>    
    <div class="wrap">
        <div style="max-width: 900px; margin: 0 auto;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: 600;">
                    <span class="dashicons dashicons-superhero" style="margin-right: 10px; font-size: 28px; width: 28px; height: 28px;"></span><?php echo esc_html__('BotWriter', 'botwriter'); ?>
                </h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.95;">
                    <?php echo esc_html__('AI-Powered Content Creation for WordPress', 'botwriter'); ?>
                </p>
            </div>

            <!-- Quick Start Alert -->
            <?php if (!$has_api_key): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 0 8px 8px 0; margin-bottom: 25px;">
                <strong style="color: #856404;"><span class="dashicons dashicons-lightbulb" style="font-size: 18px; width: 18px; height: 18px; vertical-align: text-bottom;"></span> <?php echo esc_html__('Quick Start:', 'botwriter'); ?></strong>
                <span style="color: #856404;">
                    <?php echo esc_html__('Configure your AI provider API key to get started.', 'botwriter'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>" style="color: #856404; font-weight: 600;"><?php echo esc_html__('Go to Settings', 'botwriter'); ?> &rarr;</a>
                </span>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <p style="font-size: 15px; line-height: 1.7; color: #444; margin: 0;">
                    <?php echo esc_html__('BotWriter automates content creation using the latest AI models. Connect your preferred AI provider, configure your content sources, and let BotWriter generate SEO-optimized articles with AI-generated images, completely hands-free.', 'botwriter'); ?>
                </p>
            </div>

            <!-- Features Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px;">
                
                <!-- Text AI Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="margin-bottom: 12px;"><span class="dashicons dashicons-edit" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Multi-Provider Text AI', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Choose from OpenAI (GPT-4o), Anthropic (Claude), Google (Gemini), Mistral, Groq, or OpenRouter. Use your own API keys.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Image AI Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="margin-bottom: 12px;"><span class="dashicons dashicons-format-image" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('AI Image Generation', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Generate featured images with DALL-E, Stable Diffusion, Flux, Recraft, and more via Replicate, Stability AI, or Fal.ai.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Content Sources Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="margin-bottom: 12px;"><span class="dashicons dashicons-rss" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Multiple Content Sources', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Import and rewrite content from any WordPress site, RSS feed, or news API. Prevent duplicates automatically.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Automation Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="margin-bottom: 12px;"><span class="dashicons dashicons-admin-generic" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Full Automation', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Schedule unlimited tasks, set publishing frequency, and let BotWriter work 24/7. Monitor everything from the Logs.', 'botwriter'); ?>
                    </p>
                </div>

            </div>

            <!-- Getting Started Steps -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; color: #333;">
                    <span class="dashicons dashicons-controls-play" style="font-size: 20px; width: 20px; height: 20px; vertical-align: text-bottom;"></span> <?php echo esc_html__('Getting Started', 'botwriter'); ?>
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: <?php echo $has_api_key ? '#28a745' : '#667eea'; ?>; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">
                            <?php echo $has_api_key ? '&#10003;' : '1'; ?>
                        </div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Configure your AI Provider', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Add your API key from OpenAI, Anthropic (Claude), Google (Gemini), Mistral, Groq, or OpenRouter.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($settings_url); ?>"><?php echo esc_html__('Settings', 'botwriter'); ?> &rarr;</a>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">2</div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Create Your First Task', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Define your content source, AI prompts, categories, and publishing schedule.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($addnew_url); ?>"><?php echo esc_html__('Add New', 'botwriter'); ?> &rarr;</a>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">3</div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Activate and Monitor', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Enable your tasks and watch BotWriter generate posts automatically. Check the Logs for status updates.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($logs_url); ?>"><?php echo esc_html__('Logs', 'botwriter'); ?> &rarr;</a>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Quick Links -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                <a href="<?php echo esc_url($settings_url); ?>" class="botwriter-quick-link" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                    <div style="margin-bottom: 6px;"><span class="dashicons dashicons-admin-generic" style="font-size: 20px; width: 20px; height: 20px;"></span></div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Settings', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($addnew_url); ?>" class="botwriter-quick-link" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                    <div style="margin-bottom: 6px;"><span class="dashicons dashicons-plus-alt2" style="font-size: 20px; width: 20px; height: 20px;"></span></div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Add New', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($tasks_url); ?>" class="botwriter-quick-link" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                    <div style="margin-bottom: 6px;"><span class="dashicons dashicons-list-view" style="font-size: 20px; width: 20px; height: 20px;"></span></div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Tasks', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($logs_url); ?>" class="botwriter-quick-link" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                    <div style="margin-bottom: 6px;"><span class="dashicons dashicons-chart-bar" style="font-size: 20px; width: 20px; height: 20px;"></span></div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Logs', 'botwriter'); ?></div>
                </a>
                <a href="https://wpbotwriter.com/faq.html" target="_blank" class="botwriter-quick-link botwriter-quick-link-highlight" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: white; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); transition: transform 0.2s, box-shadow 0.2s;">
                    <div style="margin-bottom: 6px;"><span class="dashicons dashicons-editor-help" style="font-size: 20px; width: 20px; height: 20px;"></span></div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Help & FAQ', 'botwriter'); ?></div>
                </a>
            </div>

            <!-- Footer -->
            <div style="text-align: center; margin-top: 30px; padding: 15px; color: #888; font-size: 12px;">
                <?php echo esc_html__('BotWriter', 'botwriter'); ?> v<?php echo esc_html(BOTWRITER_VERSION); ?> &mdash; 100% Free
                <br>
                <a href="https://www.wpbotwriter.com" target="_blank" style="color: #667eea; text-decoration: none;"><?php echo esc_html__('Website', 'botwriter'); ?></a>
                &nbsp;&bull;&nbsp;
                <a href="https://wpbotwriter.com/faq.html" target="_blank" style="color: #667eea; text-decoration: none;">FAQ</a>
                &nbsp;&bull;&nbsp;
                <a href="https://wordpress.org/support/plugin/botwriter/" target="_blank" style="color: #667eea; text-decoration: none;"><?php echo esc_html__('Support', 'botwriter'); ?></a>
            </div>

        </div>
    </div>
    
    <?php
}



// Hook that runs on plugin activation
register_activation_hook(__FILE__, 'botwriter_plugin_activate');
function botwriter_plugin_activate() {    
    // Store first install date if missing
    if (get_option('botwriter_install_date') === false) {
        update_option('botwriter_install_date', current_time('timestamp'));
    }
    botwriter_activate_apikey_and_defaults();
    botwriter_create_table();
    // Create the first supertask if it doesn't exist
    /*
    if (!botwriter_super1_check_task_exist()) {
        botwriter_super1_create_first_task();
    }
    */
}


function botwriter_activate_apikey_and_defaults() {    
    if (get_option('botwriter_paused_tasks') === false) {
        update_option('botwriter_paused_tasks', "2");
    }
    
    if (get_option('botwriter_email') === false) {
        update_option('botwriter_email', get_option('admin_email'));
    }

    if (get_option('botwriter_cron_active') === false) {
        update_option('botwriter_cron_active', '1');
    }

    if (get_option('botwriter_image_provider') === false) {
        update_option('botwriter_image_provider', 'stockphoto');
    }

    if (get_option('botwriter_stockphoto_preferred') === false) {
        update_option('botwriter_stockphoto_preferred', 'random');
    }

    if (get_option('botwriter_stockphoto_selection') === false) {
        update_option('botwriter_stockphoto_selection', 'random_top10');
    }

    if (get_option('botwriter_stockphoto_attribution') === false) {
        update_option('botwriter_stockphoto_attribution', 'caption');
    }
    
    if (get_option('botwriter_ai_image_size') === false) {
        update_option('botwriter_ai_image_size', 'square');
    }
    
    if (get_option('botwriter_sslverify') === false) {
        update_option('botwriter_sslverify', 'yes');
    }

    if (get_option('botwriter_openai_model') === false) {
        update_option('botwriter_openai_model', 'gpt-5.4-mini');
    }
    if (get_option('botwriter_ai_image_quality') === false) {
        update_option('botwriter_ai_image_quality', 'medium');
    }

    if (get_option('botwriter_seo_featured_image_alt_enabled') === false) {
        update_option('botwriter_seo_featured_image_alt_enabled', '1');
    }

    if (get_option('botwriter_seo_publish_focus_keyword_enabled') === false) {
        update_option('botwriter_seo_publish_focus_keyword_enabled', '0');
    }

    if (get_option('botwriter_seo_publish_faq_enabled') === false) {
        update_option('botwriter_seo_publish_faq_enabled', '0');
    }

    if (get_option('botwriter_seo_publish_faq_mode') === false) {
        update_option('botwriter_seo_publish_faq_mode', 'visible_schema');
    }

    if (get_option('botwriter_seo_social_meta_enabled') === false) {
        update_option('botwriter_seo_social_meta_enabled', '0');
    }
}


// Compatibility check for different WordPress versions
add_action('plugins_loaded', 'botwriter_compatibility_check');

function botwriter_compatibility_check() {
    global $wp_version;

    if (version_compare($wp_version, '4.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));

        wp_die(esc_html__('This plugin requires WordPress 4.0 or higher', 'botwriter'));
    }
}


// Ensure DB schema migrations run even for sites that didn't re-activate the plugin
add_action('plugins_loaded', 'botwriter_maybe_add_task_type_col', 20);
function botwriter_maybe_add_task_type_col() {
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'botwriter_tasks';
    // Bail if table is missing
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tasks_table_name));
    if ($table_exists !== $tasks_table_name) {
        return;
    }
    // Add task_type if missing
    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'task_type'));
    if (!$col) {
        $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `task_type` VARCHAR(50) NULL AFTER `website_type`");
    }
}



// funciones extra 
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
  
  
  function botwriter_create_table() {
    global $wpdb;
    try {

        $tasks_table_name = $wpdb->prefix . 'botwriter_tasks';        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tasks_table_name)) !== $tasks_table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $tasks_sql = "CREATE TABLE $tasks_table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `post_status` VARCHAR(20) NOT NULL,
                `task_name` VARCHAR(255) NOT NULL,
                `writer` VARCHAR(255) NOT NULL, 
                `narration`  VARCHAR(255),
                `custom_style`  VARCHAR(255),
                `post_language` VARCHAR(255) NOT NULL,                
                `post_length` VARCHAR(255) NOT NULL,
                `custom_post_length` VARCHAR(255) NOT NULL,
                `days` VARCHAR(255) NOT NULL,
                `times_per_day` INT NOT NULL,
                `execution_count` INT DEFAULT 0,
                `last_execution_date` DATE DEFAULT NULL,
                `last_execution_time` TIMESTAMP DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `status` int DEFAULT 1,
                `website_name` VARCHAR(255),
                `website_type` VARCHAR(255),
                `task_type` VARCHAR(50) DEFAULT NULL,
                `domain_name` VARCHAR(255) NOT NULL,
                `post_type` VARCHAR(50) DEFAULT 'post',
                `category_id` VARCHAR(255),
                `taxonomy_data` TEXT,
                `website_category_id` VARCHAR(255),
                `website_category_name` VARCHAR(255),
                `aigenerated_title` TEXT NOT NULL,
                `aigenerated_content` TEXT NOT NULL,
                `aigenerated_tags` TEXT NOT NULL,
                `aigenerated_image` TEXT NOT NULL,
                `post_count` VARCHAR(255),
                `post_order` VARCHAR(255),
                `title_prompt` TEXT NOT NULL,
                `content_prompt` TEXT NOT NULL,
                `tags_prompt` TEXT NOT NULL,
                `image_prompt` TEXT NOT NULL,
                `image_generating_status` VARCHAR(255),
                `author_selection` VARCHAR(255),
                `news_time_published` VARCHAR(255),
                `news_language` VARCHAR(255),
                `news_country` VARCHAR(255),
                `news_keyword` VARCHAR(255),
                `news_source` VARCHAR(255),
                `rss_source` VARCHAR(255),
                `ai_keywords` TEXT NOT NULL,
                `disable_ai_images` TINYINT(1) DEFAULT 0,
                `template_id` INT(11) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($tasks_sql);
        } else {
            // Ensure new column task_type exists for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'task_type'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `task_type` VARCHAR(50) NULL AFTER `website_type`");
            }
            
            // Ensure new column disable_ai_images exists for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'disable_ai_images'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `disable_ai_images` TINYINT(1) DEFAULT 0");
            }
            
            // Ensure new column template_id exists for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'template_id'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `template_id` INT(11) DEFAULT NULL");
            }
            
            // Ensure new column post_type exists for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'post_type'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `post_type` VARCHAR(50) DEFAULT 'post' AFTER `domain_name`");
            }
            
            // Ensure new column taxonomy_data exists for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $tasks_table_name LIKE %s", 'taxonomy_data'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $tasks_table_name ADD COLUMN `taxonomy_data` TEXT AFTER `category_id`");
            }
        }

        // Table botwriter_logs
        $logs_table_name = $wpdb->prefix . 'botwriter_logs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logs_table_name)) !== $logs_table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $logs_sql = "CREATE TABLE $logs_table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_task` int(11) NOT NULL, 
                `id_task_server` int(11) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `last_execution_time` TIMESTAMP DEFAULT 0, 
                `intentosfase1` int(11) NOT NULL DEFAULT 0,
                `intentosfase2` int(11) NOT NULL DEFAULT 0,                
                `task_status` VARCHAR(255),
                `task_type` VARCHAR(50) DEFAULT NULL,
                `error` TEXT,
                `link_post_original` TEXT,
                `id_post_published` int(11) default 0,                
                `post_status` VARCHAR(20) NOT NULL,
                `task_name` VARCHAR(255) NOT NULL,
                `writer` VARCHAR(255) NOT NULL,
                `narration`  VARCHAR(255),
                `custom_style`  VARCHAR(255),
                `post_language` VARCHAR(255) NOT NULL,
                `post_length` VARCHAR(255) NOT NULL,                                                
                `custom_post_length` VARCHAR(255) NOT NULL,                                                
                `website_name` VARCHAR(255),
                `website_type` VARCHAR(255),
                `domain_name` VARCHAR(255) NOT NULL,
                `post_type` VARCHAR(50) DEFAULT 'post',
                `category_id` VARCHAR(255),
                `taxonomy_data` TEXT,
                `website_category_id` VARCHAR(255),
                `aigenerated_title` TEXT NOT NULL,
                `aigenerated_content` TEXT NOT NULL,
                `aigenerated_tags` TEXT NOT NULL,
                `aigenerated_image` TEXT NOT NULL,
                `post_count` VARCHAR(255),
                `post_order` VARCHAR(255),
                `title_prompt` TEXT NOT NULL,
                `content_prompt` TEXT NOT NULL,
                `tags_prompt` TEXT NOT NULL,
                `image_prompt` TEXT NOT NULL,
                `image_generating_status` VARCHAR(255),
                `author_selection` VARCHAR(255),
                `news_time_published` VARCHAR(255),
                `news_language` VARCHAR(255),
                `news_country` VARCHAR(255),
                `news_keyword` VARCHAR(255),
                `news_source` VARCHAR(255),
                `rss_source` VARCHAR(255),
                `ai_keywords` TEXT NOT NULL,
                `disable_ai_images` TINYINT(1) DEFAULT 0,
                `template_id` INT(11) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($logs_sql);
        } else {
            // Ensure new column disable_ai_images exists in logs table for legacy installs
            $logs_table_name = $wpdb->prefix . 'botwriter_logs';
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $logs_table_name LIKE %s", 'disable_ai_images'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $logs_table_name ADD COLUMN `disable_ai_images` TINYINT(1) DEFAULT 0");
            }
            
            // Ensure new column template_id exists in logs table for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $logs_table_name LIKE %s", 'template_id'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $logs_table_name ADD COLUMN `template_id` INT(11) DEFAULT NULL");
            }
            
            // Ensure new column task_type exists in logs table for legacy installs (for writenow exclusion)
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $logs_table_name LIKE %s", 'task_type'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $logs_table_name ADD COLUMN `task_type` VARCHAR(50) DEFAULT NULL AFTER `task_status`");
            }
            
            // Ensure new column post_type exists in logs table for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $logs_table_name LIKE %s", 'post_type'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $logs_table_name ADD COLUMN `post_type` VARCHAR(50) DEFAULT 'post' AFTER `domain_name`");
            }
            
            // Ensure new column taxonomy_data exists in logs table for legacy installs
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $logs_table_name LIKE %s", 'taxonomy_data'));
            if (!$col) {
                $wpdb->query("ALTER TABLE $logs_table_name ADD COLUMN `taxonomy_data` TEXT AFTER `category_id`");
            }
        }

        

        $tasks_table_name = $wpdb->prefix . 'botwriter_super';        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tasks_table_name)) !== $tasks_table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $tasks_sql = "CREATE TABLE $tasks_table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_task` int(11) NOT NULL,
                `id_log` int(11) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `content` TEXT NOT NULL,
                `category_id` VARCHAR(255),
                `category_name` VARCHAR(255), 
                `task_status` VARCHAR(255),               
                PRIMARY KEY (`id`)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($tasks_sql);
        }

        // Table botwriter_templates for prompt templates
        $templates_table_name = $wpdb->prefix . 'botwriter_templates';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $templates_table_name)) !== $templates_table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $templates_sql = "CREATE TABLE $templates_table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `content` LONGTEXT NOT NULL,
                `is_default` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($templates_sql);

            // Insert all default templates
            botwriter_insert_all_default_templates();
        }

    } catch (Exception $e) {
        
        //error_log("Error creating botwriter tables: " . $e->getMessage());
    }
  }
  
/**
 * Insert the default prompt template (legacy function - now uses default-templates.php)
 * @deprecated Use botwriter_insert_all_default_templates() instead
 */
function botwriter_insert_default_template() {
    // Now handled by botwriter_insert_all_default_templates() in default-templates.php
    botwriter_insert_all_default_templates();
}

/**
 * Get the default template content with all placeholders
 * Uses the content from default-templates.php if available
 */
function botwriter_get_default_template_content() {
    // Try to get from the default templates array
    if (function_exists('botwriter_get_default_template_by_name')) {
        $default = botwriter_get_default_template_by_name('Default Template');
        if ($default && !empty($default['content'])) {
            return $default['content'];
        }
    }
    
    // Fallback hardcoded template
    $template = 'Write an article for a blog, follow these instructions:

-The article must be HTML, with proper opening and closing H2-H4 tags for headings, and <p> for paragraphs.
-The length should be approximately {{post_length}} words.
-The article language must be: {{post_language}}.
-Narrative style: {{writer_style}}
-The topic must be related to some of the following keywords: {{prompt_or_keywords}}

-IMPORTANT: Do not title or label the last paragraph with Conclusion, Final Thoughts, Summary, or any similar term. The last paragraph should integrate naturally into the article, without any heading or subheading. It should subtly close the article by reinforcing the main message or idea, offering a final reflection, or leaving the reader with a powerful takeaway, but without explicitly indicating it is the end.';

    return $template;
}

/**
 * Get template by ID or default template
 */
function botwriter_get_template($template_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    if ($template_id) {
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $template_id), ARRAY_A);
    } else {
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE is_default = %d", 1), ARRAY_A);
    }
    
    if (!$template) {
        // Return hardcoded default if no template in DB
        return [
            'id' => 0,
            'name' => 'Default Template',
            'content' => botwriter_get_default_template_content(),
            'is_default' => 1
        ];
    }
    
    return $template;
}

/**
 * Get all templates
 */
function botwriter_get_all_templates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';

    if (function_exists('botwriter_ensure_default_templates_exist')) {
        botwriter_ensure_default_templates_exist();
    }

    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name DESC", ARRAY_A);
}

/**
 * Save a template (insert or update)
 */
function botwriter_save_template($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    if (!empty($data['id'])) {
        // Update
        return $wpdb->update($table_name, [
            'name' => sanitize_text_field($data['name']),
            'content' => wp_kses_post($data['content'])
        ], ['id' => intval($data['id'])]);
    } else {
        // Insert
        return $wpdb->insert($table_name, [
            'name' => sanitize_text_field($data['name']),
            'content' => wp_kses_post($data['content']),
            'is_default' => 0
        ]);
    }
}

/**
 * Set a template as default (unset other defaults)
 */
function botwriter_set_default_template($template_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    // Check if template exists
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $template_id));
    if (!$template) {
        return false;
    }
    
    // Remove default from all templates
    $wpdb->update($table_name, ['is_default' => 0], ['is_default' => 1]);
    
    // Set new default
    return $wpdb->update($table_name, ['is_default' => 1], ['id' => intval($template_id)]);
}

/**
 * Delete a template (cannot delete default)
 */
function botwriter_delete_template($template_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    // Prevent deleting default template
    $is_default = $wpdb->get_var($wpdb->prepare("SELECT is_default FROM $table_name WHERE id = %d", $template_id));
    if ($is_default == 1) {
        return false;
    }
    
    return $wpdb->delete($table_name, ['id' => intval($template_id)]);
}

/**
 * Build prompt from template by replacing placeholders with actual values
 * Uses Mustache-like syntax: {{variable}} for simple values, {{#section}}...{{/section}} for conditionals
 */
function botwriter_build_prompt_from_template($template_content, $data) {
    global $botwriter_languages;
    
    // Map writer styles
    $writer_styles = [
        'ai_cerebro' => '',
        'orion' => '',
        'cloe' => 'ironic critic, sarcastic and witty',
        'lucida' => 'analytical critic, precise and direct',
        'max' => 'Passionate and descriptive',
        'gael' => 'Reflective, introspective and poetic',
    ];
    
    // Prepare data for template — NOTE: this is the legacy function,
    // main flow uses botwriter_build_client_prompt() below.
    $writer = strtolower($data['writer'] ?? '');
    $writer_style = '';
    
    if ($writer === 'custom') {
        $narration = strtolower($data['narration'] ?? '');
        if ($narration === 'custom') {
            $writer_style = $data['custom_style'] ?? '';
        } else {
            $writer_style = $narration;
        }
    } elseif (isset($writer_styles[$writer])) {
        $writer_style = $writer_styles[$writer];
    }
    
    // Get language name from code
    $post_language_code = $data['post_language'] ?? 'en';
    $post_language = $botwriter_languages[$post_language_code] ?? 'English';
    
    // Post length
    $post_length = $data['post_length'] ?? '800';
    if (!is_numeric($post_length)) {
        $post_length = 800;
    }
    $post_length = min(intval($post_length), 4000);
    
    // Build replacements array
    // Note: source_content, existing_titles, title_prompt, content_prompt are handled server-side
    $replacements = [
        'post_length' => $post_length,
        'post_language' => $post_language,
        'writer_style' => $writer_style,
        'prompt_or_keywords' => $data['ai_keywords'] ?? '',
    ];
    
    $prompt = $template_content;
    
    // Replace variables: {{variable}}
    foreach ($replacements as $key => $value) {
        $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
    }
    
    // Clean up short instruction lines left empty after variable replacement
    // e.g. "-Narrative style: " or "-Topic: " when the value is empty
    // Never remove lines containing ENDARTICLE or other source markers
    $lines = explode("\n", $prompt);
    $cleaned_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^-[^:]+:\s*$/', $trimmed) && strpos($trimmed, 'ENDARTICLE') === false && strlen($trimmed) < 40) {
            continue;
        }
        $cleaned_lines[] = $line;
    }
    $prompt = implode("\n", $cleaned_lines);
    
    // Clean up multiple blank lines
    $prompt = preg_replace('/\n{3,}/', "\n\n", $prompt);
    $prompt = trim($prompt);
    
    return $prompt;
}

/**
 * Build the complete prompt on the client side using the template system
 * For types that require external content (wordpress, rss, news), 
 * the server will add the source content to the prompt
 */
function botwriter_build_client_prompt($data) {
    global $botwriter_languages;
    
    // Get the template - use task-specific template if set, otherwise default
    $template_id = isset($data['template_id']) && !empty($data['template_id']) ? intval($data['template_id']) : null;
    $template = botwriter_get_template($template_id);
    $template_content = $template['content'];
    
    // Map writer styles
    $writer_styles = [
        'ai_cerebro' => '',
        'orion' => '',
        'cloe' => 'ironic critic, sarcastic and witty',
        'lucida' => 'analytical critic, precise and direct',
        'max' => 'Passionate and descriptive',
        'gael' => 'Reflective, introspective and poetic',
    ];
    
    // Prepare writer style
    $writer = strtolower($data['writer'] ?? '');
    $writer_style = '';
    
    if ($writer === 'custom') {
        $narration = strtolower($data['narration'] ?? '');
        if ($narration === 'custom') {
            $writer_style = $data['custom_style'] ?? '';
        } else {
            $writer_style = $narration;
        }
    } elseif (isset($writer_styles[$writer])) {
        $writer_style = $writer_styles[$writer];
    }
    
    // Get language name from code
    $post_language_code = $data['post_language'] ?? 'en';
    $post_language = $botwriter_languages[$post_language_code] ?? 'English';
    
    // Post length
    $post_length = $data['post_length'] ?? '800';
    if (!is_numeric($post_length)) {
        $post_length = 800;
    }
    $post_length = min(intval($post_length), 4000);
    
    // Determine what content to include based on website_type
    $website_type = $data['website_type'] ?? '';
    $source_title = '';
    $source_content = '';
    $ai_keywords = '';
    $existing_titles = '';
    $title_prompt = '';
    $content_prompt = '';
    
    switch ($website_type) {
        case 'ai':
        case '':
            // AI mode: use keywords and avoid existing titles
            $ai_keywords = $data['ai_keywords'] ?? '';
            $existing_titles = $data['titles'] ?? '';
            break;
            
        case 'super2':
            // Super2: use title_prompt and content_prompt from outline
            $title_prompt = $data['title_prompt'] ?? '';
            $content_prompt = $data['content_prompt'] ?? '';
            break;
            
        case 'rss':
            // RSS content is now pre-fetched on the client side
            // Data is populated by botwriter_send1_data_to_server before calling this function
            $source_title = $data['source_title'] ?? '';
            $source_content = $data['source_content'] ?? '';
            break;

        case 'wordpress':
            // WordPress content is now pre-fetched on the client side
            // Data is populated by botwriter_send1_data_to_server before calling this function
            $source_title = $data['source_title'] ?? '';
            $source_content = $data['source_content'] ?? '';
            break;

        case 'news':
            // News still requires server-side content fetching
            // We leave source_title and source_content empty, server will fill them
            break;
    }
    
    // Build replacements array
    $replacements = [
        'post_length' => $post_length,
        'post_language' => $post_language,
        'writer_style' => $writer_style,
        'source_title' => $source_title,
        'source_content' => $source_content,
        'ai_keywords' => $ai_keywords,
        'prompt_or_keywords' => $ai_keywords,  // Alias for templates
        'existing_titles' => $existing_titles,
        'title_prompt' => $title_prompt,
        'content_prompt' => $content_prompt,
    ];
    
    $prompt = $template_content;

    botwriter_log('PROMPT BUILD: before source embed', [
        'website_type'       => $website_type,
        'source_title_empty' => empty($source_title),
        'source_title'       => mb_substr($source_title, 0, 100),
        'source_content_len' => strlen($source_content),
        'template_len'       => strlen($template_content),
    ]);
    
    // For RSS/WordPress: embed source content directly (already pre-fetched on client)
    if (in_array($website_type, ['rss', 'wordpress']) && !empty($source_title)) {
        $prompt .= "\n\n-Based on this news article (I indicate the end with the word ENDARTICLE):\n\n" . $source_title . "\n" . $source_content . "\n\nENDARTICLE:\n";
        botwriter_log('PROMPT BUILD: ENDARTICLE block appended', [
            'prompt_len_after' => strlen($prompt),
        ]);
    } else {
        botwriter_log('PROMPT BUILD: ENDARTICLE block NOT appended', [
            'reason' => !in_array($website_type, ['rss', 'wordpress']) ? 'type not rss/wordpress' : 'source_title is empty',
        ]);
    }
    // For Super2: embed title and content instructions from the outline
    if ($website_type === 'super2') {
        // Rewrite instructions go BEFORE the ENDARTICLE block (rewriter / siterewriter tasks)
        $rewrite_prompt = $data['rewrite_prompt'] ?? '';
        if (!empty($rewrite_prompt)) {
            $prompt .= "\n-" . $rewrite_prompt;
        }
        if (!empty($title_prompt)) {
            $prompt .= "\n-The article title must be: " . $title_prompt;
        }
        if (!empty($content_prompt)) {
            $prompt .= "\n\n-Based on the following content (I indicate the end with the word ENDARTICLE):\n\n" . $content_prompt . "\n\nENDARTICLE\n";
        }
        botwriter_log('PROMPT BUILD: super2 title/content appended', [
            'title_prompt' => mb_substr($title_prompt, 0, 100),
            'content_prompt_len' => strlen($content_prompt),
            'has_rewrite_prompt' => !empty($rewrite_prompt),
        ]);
    }
    // For News: server still needs to fetch content
    if ($website_type === 'news') {
        $prompt .= "\n\n{{SERVER_SOURCE_CONTENT}}";
    }
    
    // Replace variables: {{variable}}
    foreach ($replacements as $key => $value) {
        $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
    }

    $prompt_before_clean = $prompt;
    botwriter_log('PROMPT BUILD: BEFORE cleanup', [
        'prompt_len'              => strlen($prompt),
        'contains_ENDARTICLE'     => (strpos($prompt, 'ENDARTICLE') !== false),
        'contains_Based_on'       => (strpos($prompt, 'Based on this') !== false),
        'first_300_chars'         => mb_substr($prompt, 0, 300),
        'last_300_chars'          => mb_substr($prompt, -300),
    ]);
    
    // Clean up lines that have empty placeholders (lines ending with : or empty after replacement)
    $lines = explode("\n", $prompt);
    $cleaned_lines = [];
    $removed_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Skip short instruction lines left empty after variable replacement
        // e.g. "-Narrative style: " or "-Topic: " — never remove ENDARTICLE marker
        if (preg_match('/^-[^:]+:\s*$/', $trimmed) && strpos($trimmed, 'ENDARTICLE') === false && strlen($trimmed) < 40) {
            $removed_lines[] = $trimmed . ' (len=' . strlen($trimmed) . ')';
            continue;
        }
        $cleaned_lines[] = $line;
    }
    $prompt = implode("\n", $cleaned_lines);
    
    if (!empty($removed_lines)) {
        botwriter_log('PROMPT BUILD: lines REMOVED by cleanup', [
            'count' => count($removed_lines),
            'lines' => $removed_lines,
        ]);
    }
    
    // Clean up multiple blank lines
    $prompt = preg_replace('/\n{3,}/', "\n\n", $prompt);
    $prompt = trim($prompt);

    botwriter_log('PROMPT BUILD: AFTER cleanup (final)', [
        'prompt_len'              => strlen($prompt),
        'contains_ENDARTICLE'     => (strpos($prompt, 'ENDARTICLE') !== false),
        'contains_Based_on'       => (strpos($prompt, 'Based on this') !== false),
        'first_300_chars'         => mb_substr($prompt, 0, 300),
        'last_300_chars'          => mb_substr($prompt, -300),
    ]);
    
    return $prompt;
}



// Extending class
class botwriter_tasks_Table extends WP_List_Table
{    
    // Define table columns
    function get_columns()
    {
        $columns = array(
                'cb'            => '<input type="checkbox" />',
                'writer'        => __('Writer', 'botwriter'),
                'task_name'          => __('Task Name', 'botwriter'),                                
                'days'          => __('Days', 'botwriter'),                
                'times_per_day' => __('Times per Day', 'botwriter'),
                'type'          => __('Type', 'botwriter'),
                'status'        => __('Status', 'botwriter') 

        );
        return $columns;
    }


    // define $table_data property
    private $table_data;

    // Bind table with columns, data and all
    function prepare_items()
    {
        //data
        if ( isset( $_POST['s'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'botwriter_nonce' ) ) {
          $search_query = sanitize_text_field(wp_unslash($_POST['s']));
          $this->table_data = $this->get_table_data($search_query);
        } else {
          $this->table_data = $this->get_table_data();
        }
      

        $columns = $this->get_columns();
        $hidden = ( is_array(get_user_meta( get_current_user_id(), 'managetoplevel_page_list_tablecolumnshidden', true)) ) ? get_user_meta( get_current_user_id(), 'managetoplevel_page_list_tablecolumnshidden', true) : array();
        $sortable = $this->get_sortable_columns();
        $primary  = 'name';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);
        $this->process_bulk_action();
        $this->table_data = $this->get_table_data();

        usort($this->table_data, array($this, 'usort_reorder'));

        /* pagination */ 
        $per_page = $this->get_items_per_page('elements_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        $this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
                'total_items' => $total_items, // total number of items
                'per_page'    => $per_page, // items to show on a page
                'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
        ));
        
        $this->items = $this->table_data;
    }

     

        function column_task_name($item){
            $slug='botwriter_automatic_post_new';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug used to build edit links.
            $page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';

            if ($item["website_type"] == 'super2') {
                $slug='botwriter_super_page';
                $url_edit= wp_nonce_url('?page=' . $slug . '&id=' . $item['id'], "botwriter_tasks_action");
            }  else {
                $url_edit= wp_nonce_url('?page=' . $slug . '&id=' . $item['id'], "botwriter_tasks_action");
            }

            $url_delete= wp_nonce_url('?page=' . $page . '&action=delete&id=' . $item['id'], "botwriter_tasks_action");

            $actions = array(
                'edit' => sprintf('<a href="%s">%s</a>', $url_edit, __('Edit', 'botwriter')),                
                'delete' => sprintf('<a href="%s">%s</a>', $url_delete, __('Delete', 'botwriter')),
            );

            $id=$item['id'];
            return sprintf('%s %s',
                "<a class='row-title' href='?page=$slug&id=$id&_wpnonce=" . wp_create_nonce('botwriter_tasks_action') . "'>" . $item['task_name'] . "</a>",
                $this->row_actions($actions)
            );
        }

        function column_writer($item){
            $dir_images_writers = plugin_dir_url(__FILE__) . 'assets/images/writers/';
            $writer=$item['writer'];
            $writer = strtolower($writer);

            $slug='botwriter_automatic_post_new';
            $id=$item['id'];         
            $link="<a class='row-title' href='?page=$slug&id=$id'>";
            $img= '<img src="' . esc_url($dir_images_writers . $writer . '.jpeg') . '" alt="' . esc_attr($writer) . '" class="writer-photo">';
            return $link . $img . '</a>';
            
        }

        

        function column_status($item){
            
            $status = $item['status'];
            $status_opuesto = $status ? 0 : 1;
            $icono = $status ? 'dashicons-yes' : 'dashicons-dismiss';
            $texto_status = $status ? 'Desactivate' : 'Activate';
        
            return sprintf(
                '<a href="#" class="icono-status dashicons %s" data-id="%d" data-status="%d" title="%s"></a>',
                $icono,
                $item['id'],
                $status_opuesto,
                $texto_status
            );

            
        }

        /*
    function column_category_id($item) {
        $categories = get_categories();
        $category_name = '';
        foreach ($categories as $category) {
            $aux_cateforias=explode(',',$item['category_id']);
            if (in_array($category->term_id, $aux_cateforias)) {
                $category_name .= $category->name . ', ';
            }
            
        }        
        $category_name = rtrim($category_name, ', ');
        return $category_name;
    }
        */


    
      

       // To show bulk action dropdown
    function get_bulk_actions()
    {
            $actions = array(
                    'delete_all'    => __('Delete', 'botwriter'),
                    
            );
            return $actions;
    }

    function process_bulk_action()
    {   
        // Verify user has permission
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Verify nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), "botwriter_tasks_action")) {
            return;
        }
         
        global $wpdb;

        $table = esc_sql($wpdb->prefix . 'botwriter_tasks');

        if ('delete_all' === $this->current_action() || ('delete' === $this->current_action() && isset($_REQUEST['id']))) {
            $request_id = isset($_REQUEST['id']) ? array_map('absint', (array) wp_unslash($_REQUEST['id'])) : array();

            if (!empty($request_id)) {
                // Prepare the DELETE query with proper escaping
                $placeholders = implode(',', array_fill(0, count($request_id), '%d'));
                $query = $wpdb->prepare("DELETE FROM {$table} WHERE id IN({$placeholders})", $request_id);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and placeholders are prepared from sanitized IDs.
                $wpdb->query($query);
            }
        }
    }

    

      // Get table data
      private function get_table_data( $search = '' ) {
        global $wpdb;
    
        $table = esc_sql($wpdb->prefix . 'botwriter_tasks');
        
    
                if ( ! empty( $search ) ) {
            $prepared_search = $wpdb->esc_like( $search );
            $prepared_search = '%' . $wpdb->esc_like( $search ) . '%';
    
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE name LIKE %s AND (task_type IS NULL OR task_type <> %s)",
                $prepared_search,
                'writenow'
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
            return $wpdb->get_results($query, ARRAY_A);
                } else {         
                    $query = $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE (task_type IS NULL OR task_type <> %s)",
                        'writenow'
                    );
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and escaped for identifier use.
                    return $wpdb->get_results($query, ARRAY_A);                            
        }
    }
    
    function column_default($item, $column_name)
    {

          switch ($column_name) {
                case 'id':
                case 'website_type':             
                case 'website_name':                
                case 'task_name':               
                case 'category_id':          
                case 'website_category_id':
                default:
                    return $item[$column_name];
          }
    }

    // Render the combined Type column: website_type + task_type
    function column_type($item) {
        $parts = array();
        if (!empty($item['website_type'])) {
            $parts[] = sanitize_text_field($item['website_type']);
        }
        if (!empty($item['task_type'])) {
            $parts[] = sanitize_text_field($item['task_type']);
        }
        $label = !empty($parts) ? implode(' / ', $parts) : __('—', 'botwriter');
        return esc_html($label);
    }

    function column_cb($item){
        return sprintf(
                '<input type="checkbox" name="id[]" value="%s" />',
                $item['id']
        );
    }

    public function get_sortable_columns(){ 
      $sortable_columns = array(
            'task_name'  => array('task_name', false),            
            'days'  => array('days', false),            
            'id'   => array('id', true)
      );
      return $sortable_columns;
    }

      // Sorting function
      function usort_reorder($a, $b)
      { 
          // If no sort, default to task_name          
          // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort param for list table.
          $sanitized_orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';

          $orderby = (!empty($sanitized_orderby)) ? $sanitized_orderby : 'task_name';
  
          // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort param for list table.
          $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'asc';

          // filtrar order solo asd o desc
            $order = in_array($order, array('asc', 'desc')) ? $order : 'asc';
            // filter orderby only allowed columns
            $orderby = in_array($orderby, array('task_name', 'days', 'id')) ? $orderby : 'task_name';
            

          
  
          // Determine sort order
          $result = strcmp($a[$orderby], $b[$orderby]);
  
          // Send final sort direction to usort
          return ($order === 'asc') ? $result : -$result;
      }

} // end class botwriter_tasks_Table






function botwriter_validate_website($item,$is_manual = false)
{
    
    $messages = array();

    
    if (empty($item['task_name'])) $messages[] = __('Task Name is required', 'botwriter');
    if (empty($item['website_type'])) $messages[] = __('Website Type is required', 'botwriter');
    
    // Category/taxonomy validation: require category_id for 'post' type, or taxonomy_data for other types
    $post_type = isset($item['post_type']) ? $item['post_type'] : 'post';
    if ($post_type === 'post') {
        if (empty($item['category_id']) && empty($item['taxonomy_data'])) {
            $messages[] = __('Category is required', 'botwriter');
        }
    }
    // For other post types, taxonomy selection is optional


    if($item['website_type'] == 'wordpress'){
        if (empty($item['domain_name'])) { 
            $messages[] = __('Domain Name is required', 'botwriter');
        }
        if( !botwriter_isValidDomain(sanitize_text_field($item['domain_name'])) ){
            $messages[] = __('Domain name should be valid.', 'botwriter');
        }
    }

    if ($item['website_type'] == 'rss') {
        if (empty($item['rss_source'])) {
            $messages[] = __('RSS Source is required', 'botwriter');
        }
    }

    if ($item['website_type'] == 'news') {
        if (empty($item['news_keyword'])) {
            $messages[] = __('News keyword is required', 'botwriter');
        }
    }

    if (empty($messages)) return true;
    return implode('<br />', $messages);
}


function botwriter_isValidDomain($domain) {
    // WordPress wp_http_validate_url
    $valid_url = wp_http_validate_url( $domain);
      
    if (!is_wp_error($valid_url)) {
        return true;
    } else {
        return false;
    }
  }
  
  
  function botwriter_is_site_working($site_url, $site_type) {
    $response = false;
  
    if ($site_type === 'wordpress') {
        // Check if the WordPress REST API is accessible
        $api_url = rtrim($site_url, '/') . '/wp-json/wp/v2/posts';
        $headers = @get_headers($api_url);
        if ($headers && strpos((string)$headers[0], '200') !== false) {
            $response = true;
        }
    } elseif ($site_type === 'rss') {
        // Check if the RSS feed is accessible
        $rss = @simplexml_load_file($site_url);
        if ($rss) {
            $response = true;
        }
    }
  
    return $response;
  }
  

//wp-cron:
 

// Add a custom schedule for cron jobs

function botwriter_add_custom_cron_schedule($schedules) {
    if (!isset($schedules['every_30'])) {
        $schedules['every_30'] = array(
            'interval' => 30, // 30 seconds
            'display'  => __('Every thirty seconds', 'botwriter')
        );        
    }
    return $schedules; 
}
add_filter('cron_schedules', 'botwriter_add_custom_cron_schedule'); 

// Ensure cron is scheduled on admin load (in case activation hook didn't run)
function botwriter_ensure_cron_scheduled() {
    if (get_option('botwriter_cron_active') === '0') {
        return;
    }

    if (!wp_next_scheduled('botwriter_scheduled_events_plugin_cron')) {
        $scheduled = wp_schedule_event(time() + 30, 'every_30', 'botwriter_scheduled_events_plugin_cron');
        botwriter_log('Cron scheduled (admin init)', [
            'scheduled' => $scheduled ? 'yes' : 'no',
        ]);
    }
}
add_action('admin_init', 'botwriter_ensure_cron_scheduled');

// Schedule the cron job during plugin activation
function botwriter_scheduled_events_plugin_activate() {
    if (get_option('botwriter_cron_active')=="0") {
        return;
    }
    if (!wp_next_scheduled('botwriter_scheduled_events_plugin_cron')) {
        wp_schedule_event(time(), 'every_30', 'botwriter_scheduled_events_plugin_cron');                
    } 
}
register_activation_hook(__FILE__, 'botwriter_scheduled_events_plugin_activate');

// Register the cron task
add_action('botwriter_scheduled_events_plugin_cron', 'botwriter_scheduled_events_execute_tasks');




// Clear the cron job upon plugin deactivation
function botwriter_scheduled_events_plugin_deactivate() {
    wp_clear_scheduled_hook('botwriter_scheduled_events_plugin_cron');    
}
register_deactivation_hook(__FILE__, 'botwriter_scheduled_events_plugin_deactivate');




 function botwriter_scheduled_events_execute_tasks() {
    global $wpdb;
    $table_name_tasks = $wpdb->prefix . 'botwriter_tasks';
    $table_name_logs = $wpdb->prefix . 'botwriter_logs';
    $table_name_super = $wpdb->prefix . 'botwriter_super';

    // ── Prevent overlapping cron runs (race condition guard) ──
    // Use a transient lock so two cron ticks cannot run simultaneously.
    // Lock expires after 120 seconds as a safety net.
    $lock_key = 'botwriter_cron_lock';
    if (get_transient($lock_key)) {
        botwriter_log('CRON SKIPPED — another cron run is still in progress');
        return;
    }
    set_transient($lock_key, time(), 120);

    // Check if cron is active
    $cron_active = get_option('botwriter_cron_active');
    botwriter_log('=== CRON START ===', [
        'cron_active_option' => $cron_active,
        'timestamp' => current_time('Y-m-d H:i:s'),
    ]);
    
    if ($cron_active !== '1') {
        botwriter_log('CRON DISABLED - exiting', ['cron_active' => $cron_active]);
        delete_transient($lock_key);
        return;
    }

    // STOPFORMANY: refuse to dispatch new tasks while the flag is active
    if (get_option('botwriter_stopformany', false)) {
        botwriter_log('CRON BLOCKED by STOPFORMANY — too many consecutive errors on server');
        delete_transient($lock_key);
        return;
    }

    // Get the current day (English name) and date based on WordPress local time
    // Use DateTime with wp_timezone() to respect site timezone and keep English day name
    try {
        $dt = new DateTime('now', wp_timezone());
        $current_day_en = $dt->format('l');
    } catch (Exception $e) {
        // Fallback, still English but GMT-based
        $current_day_en = gmdate('l');
    }
    $current_date = current_time('Y-m-d'); 

    botwriter_log('Cron dispatcher triggered', [
        'day' => $current_day_en,
        'date' => $current_date,
        'timezone' => wp_timezone_string(),
    ]);

    // PHASE 2
    botwriter_execute_events_pass2();
     
    //PHASE 1 Execute each event if it meets the conditions
    // Get tasks scheduled for today and status=1  
    // Exclude one-off Write now tasks from cron to avoid duplicate logs
    $tasks = (array) $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_name_tasks} WHERE days LIKE %s AND status = %d AND (task_type IS NULL OR task_type <> %s)",
            '%' . $wpdb->esc_like($current_day_en) . '%',
            1,
            'writenow'
        ),
        ARRAY_A
    );
    botwriter_log('Tasks evaluated for cron tick', [
        'count' => count($tasks),
        'query_day' => $current_day_en,
    ]);
    
    // Log all tasks found for debugging
    if (count($tasks) === 0) {
        // Check total active tasks to see if it's a day mismatch
        $all_active = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name_tasks} WHERE status = 1");
        botwriter_log('NO TASKS FOUND for today', [
            'current_day' => $current_day_en,
            'total_active_tasks' => $all_active,
        ]);
    }
    
    foreach ($tasks as $task) {
        botwriter_log('Evaluating task', [
            'task_id' => $task['id'],
            'task_name' => $task['task_name'],
            'days' => $task['days'],
            'times_per_day' => $task['times_per_day'],
            'execution_count' => $task['execution_count'],
            'last_execution_date' => $task['last_execution_date'],
            'last_execution_time' => $task['last_execution_time'],
            'website_type' => $task['website_type'],
        ]);
        
        // Skip Write now tasks defensively (in case of legacy rows)
        if (!empty($task['task_type']) && $task['task_type'] === 'writenow') {
            botwriter_log('Skipping writenow task', ['task_id' => $task['id']]);
            continue;
        }

    // Reset execution count daily
        if ($task["last_execution_date"] !== $current_date) {
            $wpdb->update($table_name_tasks, ['execution_count' => 0, 'last_execution_date' => $current_date], ['id' => $task["id"]]);            
            $task["execution_count"] = 0;
            botwriter_log('Reset execution count for new day', ['task_id' => $task['id']]);
        }

        // Check if the task is a supertask and if it exists
        $super_exists = true;
        if ($task["website_type"] == 'super2') { //supertask                                                                                    
            $super = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_super WHERE id_task = %d AND (task_status IS NULL OR task_status = '')", $task["id"]), ARRAY_A);
            if (!$super) {
                $super_exists = false;                            
                botwriter_log('Super2 task skipped, no pending outline', [
                    'task_id' => $task['id'],
                ]);
            }                                             
        }

        // Check if the task can still be executed based on its daily limit
        if ($task["execution_count"] < $task["times_per_day"] && $super_exists) {                            
            $last_execution_time = $task["last_execution_time"];
            $now = current_time('timestamp'); 
            $diff = $now - strtotime($last_execution_time);
            $pause = get_option('botwriter_paused_tasks');
            $pause = is_numeric($pause) ? intval($pause) : 2;
            
            botwriter_log('Checking pause time', [
                'task_id' => $task['id'],
                'last_execution_time' => $last_execution_time,
                'now' => current_time('Y-m-d H:i:s'),
                'diff_seconds' => $diff,
                'pause_minutes' => $pause,
                'required_seconds' => 60 * $pause,
                'can_execute' => ($diff > 60 * $pause) ? 'YES' : 'NO',
            ]);
			
            if ($diff > 60 * $pause) { //                 
			
                $event = $task;                                                       
                $event["task_status"] = "pending";            
                $event["id_task"] = $task["id"];              
                $event["intentosfase1"] = 0;
                $id_log = botwriter_logs_register($event);  // create log in db
                $event["id"] = $id_log;
                if ($task["website_type"] == 'super2') { // supertask                                                
                    botwriter_log('Preparing super2 dispatch', [
                        'task_id' => $task['id'],
                        'log_id' => $id_log,
                    ]);
                    $prepared_event = botwriter_super_prepare_event($event);
                    if ($prepared_event === false) {
                        botwriter_log('Super2 preparation returned false', [
                            'task_id' => $task['id'],
                            'log_id' => $id_log,
                        ]);
                        continue;
                    }
                    $event = $prepared_event;
                    $id_log = botwriter_logs_register($event, $id_log);  // actualizamos log con los datos de super2
                }
                botwriter_log('Queueing phase 1 send', [
                    'task_id' => $task['id'],
                    'log_id' => $id_log,
                    'website_type' => $task['website_type'],
                ]);
                // Update execution count BEFORE the HTTP call to prevent
                // overlapping cron ticks from creating duplicate logs.
                $current_time = current_time('Y-m-d H:i:s');
                $wpdb->update($table_name_tasks, ['execution_count' => $task["execution_count"] + 1, 'last_execution_time' => $current_time], ['id' => $task["id"]]);            
                botwriter_send1_data_to_server((array) $event);
            } else {
                botwriter_log('Task paused - waiting for pause interval', [
                    'task_id' => $task['id'],
                    'diff_seconds' => $diff,
                    'required_seconds' => 60 * $pause,
                    'remaining_seconds' => (60 * $pause) - $diff,
                ]);
            }            
        } else {
            if ($super_exists) {
                botwriter_log('Task skipped due to daily limit reached', [
                    'task_id' => $task['id'],
                    'execution_count' => $task['execution_count'],
                    'times_per_day' => $task['times_per_day'],
                ]);
            }
        }
    }  // end tasks
    
    botwriter_log('=== CRON END ===', ['timestamp' => current_time('Y-m-d H:i:s')]);

    // ── Release cron lock ──
    delete_transient('botwriter_cron_lock');
}

function botwriter_execute_events_pass2(){  
    
    // check if the task is still in queue or finished
    global $wpdb;    
    $table_name_tasks = $wpdb->prefix . 'botwriter_tasks';
    $table_name_logs = $wpdb->prefix . 'botwriter_logs';
    // INQUEUE
    $events2 = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name_logs} WHERE task_status=%s", 'inqueue'));    
    botwriter_log('Phase 2 queue check', ['inqueue_count' => count($events2)]);
    foreach ($events2 as $event) {
        $event = (array) $event;

        // ── Atomically mark as 'polling' to prevent overlapping cron ticks ──
        // Only update if the status is still 'inqueue'; if another cron tick
        // already changed it, affected_rows will be 0 and we skip this log.
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name_logs} SET task_status = 'polling' WHERE id = %d AND task_status = 'inqueue'",
            $event['id']
        ));
        if ($affected === 0) {
            botwriter_log('Phase 2 skipped — already being polled', ['log_id' => $event['id']]);
            continue;
        }

        // Execute the event (send2 will set final status: completed/error/inqueue)
        $result = botwriter_send2_data_to_server( (array) $event);

        // If send2 did NOT update the log status (returned false without changing it),
        // restore to 'inqueue' so the next tick can retry.
        if ($result === false) {
            $current_status = $wpdb->get_var($wpdb->prepare(
                "SELECT task_status FROM {$table_name_logs} WHERE id = %d",
                $event['id']
            ));
            if ($current_status === 'polling') {
                $wpdb->update($table_name_logs, ['task_status' => 'inqueue'], ['id' => $event['id']]);
            }
        }
    } // end INQUEUE


    //IN ERROR, depending on the attempt, it is resent later or marked as finished
    // Exclude 'writenow' tasks from automatic retries - they should only be retried manually
    $events1 = (array) $wpdb->get_results(
        $wpdb->prepare(
            "SELECT l.*, t.id AS task_exists, t.status AS task_enabled
               FROM {$table_name_logs} l
          LEFT JOIN {$table_name_tasks} t ON t.id = l.id_task
              WHERE l.task_status = %s
                AND l.intentosfase1 < %d
                AND (l.task_type IS NULL OR l.task_type <> 'writenow')",
            'error',
            8
        ),
        ARRAY_A
    );

    $retryable_events = array();
    $closed_missing_task = 0;
    $closed_disabled_task = 0;

    foreach ($events1 as $event_row) {
        $event_row = (array) $event_row;
        $task_exists = !empty($event_row['task_exists']);
        $task_enabled = isset($event_row['task_enabled']) ? (int) $event_row['task_enabled'] : 0;

        if (!$task_exists || $task_enabled !== 1) {
            $reason = !$task_exists
                ? 'Retry stopped: linked task was deleted'
                : 'Retry stopped: linked task is disabled';

            $prev_error = isset($event_row['error']) ? trim((string) $event_row['error']) : '';
            $new_error = $prev_error !== '' ? ($prev_error . ' | ' . $reason) : $reason;

            $wpdb->update(
                $table_name_logs,
                array(
                    'intentosfase1' => 8,
                    'error' => $new_error,
                ),
                array('id' => (int) $event_row['id'])
            );

            if (!$task_exists) {
                $closed_missing_task++;
            } else {
                $closed_disabled_task++;
            }
            continue;
        }

        $retryable_events[] = $event_row;
    }

    botwriter_log('Phase 1 retries fetched', [
        'error_count' => count($retryable_events),
        'closed_missing_task' => $closed_missing_task,
        'closed_disabled_task' => $closed_disabled_task,
    ]);

    $intento_tiempo = array(0=>0,1=>0,2=>5,3=>10,4=>30,5=>60,6=>120,7=>240,8=>480); // minutos    
    foreach ($retryable_events as $event) {
        $event = (array) $event;
        // Execute the event if the time has passed 
        $intentosfase1 = $event["intentosfase1"];
        $tiempo = $intento_tiempo[$intentosfase1+1];
        $retry_reference = !empty($event['last_execution_time']) ? $event['last_execution_time'] : $event['created_at'];
        $retry_reference_ts = strtotime($retry_reference);
        if ($retry_reference_ts === false) {
            $retry_reference_ts = strtotime($event['created_at']);
        }
        $now = current_time('timestamp');

        $diff = $now - $retry_reference_ts;       
        if ($diff > $tiempo * 60) {        
            botwriter_log('Retrying phase 1 request', [
                'log_id' => $event['id'],
                'task_id' => $event['id_task'],
                'attempt' => $intentosfase1 + 1,
            ]);
            botwriter_send1_data_to_server( (array) $event);
        }
        
    } // END LOGS IN ERROR
   

}


function botwriter_generate_post($data){
    $data = botwriter_normalize_generated_post_payload($data);

    // Determine post type (default to 'post' for backward compatibility)
    $post_type = isset($data['post_type']) && !empty($data['post_type']) ? $data['post_type'] : 'post';
    
    // Build post data array
    $post_data = array(
        'post_title' => $data['aigenerated_title'],
        'post_content' => $data['aigenerated_content'],
        'post_status' => $data['post_status'],
        'post_author' => $data['author_selection'],
        'post_type' => $post_type,
    );
    
    // For 'post' type with category_id (backward compatibility)
    if ($post_type === 'post' && !empty($data['category_id'])) {
        $post_data['post_category'] = array_map('intval', explode(',', $data['category_id']));
    }
    
    // Create the post
    $post_id = wp_insert_post($post_data);
    
    if ($post_id === 0) {
        //error_log('Error creating post');
        return false;
    }
    
    // Assign taxonomy terms from taxonomy_data (if present)
    if (!empty($data['taxonomy_data'])) {
        $taxonomy_data = json_decode($data['taxonomy_data'], true);
        if (is_array($taxonomy_data)) {
            foreach ($taxonomy_data as $taxonomy_name => $term_ids) {
                if (!empty($term_ids) && taxonomy_exists($taxonomy_name)) {
                    $term_ids = array_map('intval', (array)$term_ids);
                    wp_set_object_terms($post_id, $term_ids, $taxonomy_name);
                }
            }
        }
    }

    // Add tags to the post (unless disabled in settings)
    $tags_disabled = get_option('botwriter_tags_disabled', '0');
    if ($tags_disabled !== '1' && !empty($data['aigenerated_tags'])) {
        $tags = explode(',', $data['aigenerated_tags']);
        wp_set_post_tags($post_id, $tags);
    }

    // SEO Slug Translation: translate post slug, tag slugs, and get image slug
    $translated_image_slug = '';
    if (function_exists('botwriter_apply_translated_slugs')) {
        $translated_image_slug = botwriter_apply_translated_slugs(
            $post_id, 
            $data['aigenerated_title'], 
            $data['aigenerated_tags'] ?? ''
        );
    }

    // Add image to the post only if image URL is provided and images are not disabled for this task
    $task_disable_images = isset($data['disable_ai_images']) ? intval($data['disable_ai_images']) : 0;
    if (!empty($data['aigenerated_image']) && $task_disable_images !== 1) {
        // Pass attribution data for stock photos
        $image_attribution = isset($data['image_attribution']) ? $data['image_attribution'] : null;
        botwriter_attach_image_to_post($post_id, $data['aigenerated_image'], $data['aigenerated_title'], $translated_image_slug, $image_attribution);

        // Handle stock photo attribution in post content (footer mode)
        if (!empty($image_attribution) && is_array($image_attribution)) {
            $attribution_mode = get_option('botwriter_stockphoto_attribution', 'caption');
            if ($attribution_mode === 'content_footer') {
                $author = sanitize_text_field($image_attribution['author'] ?? '');
                $source = sanitize_text_field($image_attribution['source'] ?? '');
                $source_url = esc_url($image_attribution['source_url'] ?? '');
                $author_url = esc_url($image_attribution['author_url'] ?? '');

                if ($author || $source) {
                    $credit_parts = array();
                    if ($author) {
                        $credit_parts[] = $author_url
                            ? sprintf('<a href="%s" rel="nofollow noopener" target="_blank">%s</a>', $author_url, esc_html($author))
                            : esc_html($author);
                    }
                    if ($source) {
                        $credit_parts[] = $source_url
                            ? sprintf('<a href="%s" rel="nofollow noopener" target="_blank">%s</a>', $source_url, esc_html($source))
                            : esc_html($source);
                    }
                    $credit_html = '<p class="botwriter-image-attribution"><small>'
                        . sprintf(
                            /* translators: %s: attribution credit (author / source) */
                            esc_html__('Photo by %s', 'botwriter'),
                            implode(' / ', $credit_parts)
                        )
                        . '</small></p>';

                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => get_post_field('post_content', $post_id) . "\n" . $credit_html,
                    ));
                }
            }
        }
    } else {
        $skip_reason = '';
        if (empty($data['aigenerated_image'])) {
            $skip_reason = 'No image URL provided';
        } elseif ($task_disable_images === 1) {
            $skip_reason = 'AI images disabled for this task';
        }
        
        botwriter_log('Image attachment skipped during post creation', [
            'post_id' => $post_id,
            'post_title' => $data['aigenerated_title'],
            'image_url' => $data['aigenerated_image'] ?? 'not provided',
            'reason' => $skip_reason
        ]);
    }

    // Automatic SEO post-processing: insert internal links directly on publish.
    $seo_internal_links_result = array(
        'updated' => false,
        'inserted' => 0,
        'strategy' => 'disabled',
    );
    if (function_exists('botwriter_seo_auto_internal_links_postprocess')) {
        $seo_publish_context = array(
            'title' => (string) ($data['aigenerated_title'] ?? ''),
            'content' => (string) get_post_field('post_content', $post_id),
            'tags' => (string) ($data['aigenerated_tags'] ?? ''),
            'excerpt' => (string) get_post_field('post_excerpt', $post_id),
        );

        botwriter_log('SEO publish post-processing start', array(
            'post_id' => $post_id,
            'title_len' => strlen((string) $seo_publish_context['title']),
            'content_len' => strlen((string) $seo_publish_context['content']),
            'tags_len' => strlen((string) $seo_publish_context['tags']),
            'excerpt_len' => strlen((string) $seo_publish_context['excerpt']),
            'content_preview' => botwriter_seo_debug_preview((string) $seo_publish_context['content'], 360),
        ));

        $seo_internal_links_result = botwriter_seo_auto_internal_links_postprocess($post_id, array(
            'title' => (string) $seo_publish_context['title'],
            'content' => (string) $seo_publish_context['content'],
            'tags' => (string) $seo_publish_context['tags'],
            'excerpt' => (string) $seo_publish_context['excerpt'],
        ));
    }

    // Generate SEO meta description using AI (if enabled in SEO settings)
    if ( function_exists( 'botwriter_generate_seo_meta' ) && function_exists( 'botwriter_is_seo_ai_meta_enabled' ) && botwriter_is_seo_ai_meta_enabled() ) {
        $post_language = $data['post_language'] ?? '';
        $meta_source_content = (string) get_post_field('post_content', $post_id);
        $meta_description = botwriter_generate_seo_meta(
            $data['aigenerated_title'],
            $meta_source_content,
            $post_language
        );
        if ( $meta_description ) {
            botwriter_apply_seo_meta( $post_id, $meta_description );
        }
    }

    $seo_focus_keyword_result = array(
        'enabled' => false,
        'generated' => false,
        'length' => 0,
    );
    if (
        function_exists('botwriter_generate_seo_focus_keyword')
        && function_exists('botwriter_is_seo_publish_focus_keyword_enabled')
        && botwriter_is_seo_publish_focus_keyword_enabled()
    ) {
        $seo_focus_keyword_result['enabled'] = true;
        $post_language = $data['post_language'] ?? '';
        $focus_source_content = (string) get_post_field('post_content', $post_id);
        $focus_keyword = botwriter_generate_seo_focus_keyword(
            (string) ($data['aigenerated_title'] ?? ''),
            $focus_source_content,
            $post_language
        );

        if (!empty($focus_keyword) && function_exists('botwriter_apply_seo_focus_keyword')) {
            botwriter_apply_seo_focus_keyword($post_id, (string) $focus_keyword);
            $seo_focus_keyword_result['generated'] = true;
            $seo_focus_keyword_result['length'] = function_exists('mb_strlen')
                ? mb_strlen((string) $focus_keyword)
                : strlen((string) $focus_keyword);
        }
    }

    $seo_faq_result = array(
        'enabled' => false,
        'generated' => false,
        'mode' => 'disabled',
        'visible' => null,
    );
    if (get_option('botwriter_seo_publish_faq_enabled', '0') === '1') {
        $seo_faq_result['enabled'] = true;
        $faq_mode = sanitize_key((string) get_option('botwriter_seo_publish_faq_mode', 'visible_schema'));
        if ($faq_mode !== 'visible_schema' && $faq_mode !== 'schema_only') {
            $faq_mode = 'visible_schema';
        }

        $faq_visible = $faq_mode === 'schema_only' ? 0 : 1;
        $seo_faq_result['mode'] = $faq_mode;
        $seo_faq_result['visible'] = $faq_visible;

        if (function_exists('botwriter_seo_generate_faq_for_post')) {
            $faq_generated = (bool) botwriter_seo_generate_faq_for_post($post_id);
            $seo_faq_result['generated'] = $faq_generated;
            if ($faq_generated) {
                update_post_meta($post_id, '_botwriter_seo_faq_visible', $faq_visible);
                if (function_exists('botwriter_seo_compute_score')) {
                    botwriter_seo_compute_score($post_id, true);
                }
            }
        } else {
            $seo_faq_result['mode'] = 'function_missing';
        }
    }

    botwriter_log('SEO publish post-processing summary', array(
        'post_id' => $post_id,
        'internal_links_strategy' => (string) ($seo_internal_links_result['strategy'] ?? 'unknown'),
        'internal_links_inserted' => intval($seo_internal_links_result['inserted'] ?? 0),
        'internal_links_updated' => !empty($seo_internal_links_result['updated']) ? 1 : 0,
        'focus_keyword_enabled' => !empty($seo_focus_keyword_result['enabled']) ? 1 : 0,
        'focus_keyword_generated' => !empty($seo_focus_keyword_result['generated']) ? 1 : 0,
        'focus_keyword_length' => intval($seo_focus_keyword_result['length'] ?? 0),
        'faq_enabled' => !empty($seo_faq_result['enabled']) ? 1 : 0,
        'faq_generated' => !empty($seo_faq_result['generated']) ? 1 : 0,
        'faq_mode' => (string) ($seo_faq_result['mode'] ?? 'disabled'),
        'faq_visible' => isset($seo_faq_result['visible']) ? intval($seo_faq_result['visible']) : -1,
    ));

    // Persist image prompt in post meta so regeneration never depends on logs.
    $saved_image_prompt = botwriter_resolve_image_prompt_from_post_data($data);
    botwriter_log('Post creation: image prompt resolution before meta save', array(
        'post_id' => $post_id,
        'resolved_prompt_len' => strlen((string) $saved_image_prompt),
        'incoming_image_prompt_len' => strlen(trim((string) ($data['image_prompt'] ?? ''))),
        'incoming_image_provider' => isset($data['image_provider']) ? sanitize_key((string) $data['image_provider']) : '',
        'has_image_attribution' => !empty($data['image_attribution']),
    ));
    if ($saved_image_prompt !== '') {
        $saved_provider = isset($data['image_provider']) ? sanitize_key((string) $data['image_provider']) : '';

        if ($saved_provider === '' && !empty($data['image_attribution'])) {
            $saved_provider = 'stockphoto';
        }

        if ($saved_provider === '') {
            $settings = botwriter_get_current_image_generation_settings();
            $saved_provider = (string) ($settings['provider'] ?? '');
        }

        botwriter_log('Post creation: saving image prompt meta', array(
            'post_id' => $post_id,
            'provider' => $saved_provider,
            'prompt_len' => strlen((string) $saved_image_prompt),
        ));

        botwriter_save_post_image_prompt_meta($post_id, $saved_image_prompt, $saved_provider);
    }

    return $post_id;
}

/**
 * Parse a JSON-like AI payload from a raw content string.
 * Handles markdown fences and typographic quotes used by some providers.
 *
 * @param mixed $raw_content
 * @return array|null
 */
function botwriter_parse_generated_payload_from_content($raw_content) {
    if (!is_string($raw_content)) {
        return null;
    }

    $clean = trim($raw_content);
    if ($clean === '') {
        return null;
    }

    // Normalize BOM + typographic quotes before parsing.
    $clean = preg_replace('/^\xEF\xBB\xBF/u', '', $clean);
    $clean = preg_replace('/[\x{201C}\x{201D}\x{201E}\x{201F}]/u', '"', $clean);
    $clean = preg_replace('/[\x{2018}\x{2019}\x{201A}\x{201B}]/u', "'", $clean);

    // Strip markdown code fences.
    if (preg_match('/^```(?:json|JSON)?\s*\n?(.*?)\n?```$/su', $clean, $matches)) {
        $clean = trim($matches[1]);
    } elseif (preg_match('/```(?:json|JSON)?\s*\n?(.*?)\n?```/su', $clean, $matches)) {
        $clean = trim($matches[1]);
    } elseif (preg_match('/^`{1,3}(?:json|JSON)?\s*\n?([\s\S]+)$/u', $clean, $matches)) {
        $inner = trim($matches[1]);
        $clean = preg_replace('/`{1,3}\s*$/u', '', $inner);
        $clean = trim((string) $clean);
    }

    // Some providers prepend a stray "json" token before the object.
    $clean = preg_replace('/^json\s*(?=\{|\[)/i', '', $clean);

    // Remove decorative wrappers around the payload.
    $clean = trim($clean, " \t\n\r\0\x0B`'\"");

    $parsed = json_decode($clean, true);

    if (!is_array($parsed) && preg_match('/\{[\s\S]*\}/u', $clean, $json_match)) {
        $parsed = json_decode($json_match[0], true);
    }

    if (!is_array($parsed) || !isset($parsed['aigenerated_content'])) {
        return null;
    }

    return $parsed;
}

/**
 * Ensure the post payload uses parsed fields when content accidentally contains
 * wrapped JSON returned by the AI model.
 *
 * @param mixed $data
 * @return mixed
 */
function botwriter_normalize_generated_post_payload($data) {
    if (!is_array($data)) {
        return $data;
    }

    $raw_content = isset($data['aigenerated_content']) ? (string) $data['aigenerated_content'] : '';
    $parsed_payload = botwriter_parse_generated_payload_from_content($raw_content);

    if (!is_array($parsed_payload)) {
        return $data;
    }

    $normalized = $data;
    $changed = false;

    $field_map = array('aigenerated_title', 'aigenerated_content', 'aigenerated_tags', 'image_prompt', 'image_keywords');
    foreach ($field_map as $field) {
        if (!array_key_exists($field, $parsed_payload)) {
            continue;
        }

        $new_value = $parsed_payload[$field];
        if ($field === 'aigenerated_tags' && is_array($new_value)) {
            $new_value = implode(', ', $new_value);
        }
        $new_value = is_string($new_value) ? trim($new_value) : '';

        if ($new_value === '') {
            continue;
        }

        $current_value = isset($normalized[$field]) ? (string) $normalized[$field] : '';
        if ($current_value !== $new_value) {
            $normalized[$field] = $new_value;
            $changed = true;
        }
    }

    if ($changed) {
        botwriter_log('Post payload normalized from wrapped JSON response', array(
            'title_len' => strlen((string) ($normalized['aigenerated_title'] ?? '')),
            'content_len' => strlen((string) ($normalized['aigenerated_content'] ?? '')),
            'tags_len' => strlen((string) ($normalized['aigenerated_tags'] ?? '')),
            'image_prompt_len' => strlen((string) ($normalized['image_prompt'] ?? '')),
        ));
    }

    return $normalized;
}
 
 
 
 
/**
 * Send a legacy compat request with one automatic site_token recovery retry.
 *
 * When the backend reports token mismatch, clear local token and retry once.
 * This self-heals cloned/reinstalled sites where the stored token is stale.
 *
 * @param string $remote_url Endpoint URL.
 * @param array  $data       Request body.
 * @param bool   $ssl_verify SSL verify flag.
 * @param int    $timeout    Request timeout in seconds.
 * @return array|WP_Error
 */
function botwriter_post_compat_with_token_recovery($remote_url, $data, $ssl_verify, $timeout = 45) {
    $request_args = array(
        'method'    => 'POST',
        'body'      => $data,
        'timeout'   => $timeout,
        'headers'   => array(),
        'sslverify' => (bool) $ssl_verify,
    );

    $response = wp_remote_post($remote_url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ((int) $http_code !== 200) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    if (!is_array($result)) {
        return $response;
    }

    $error_code = (string) ($result['error'] ?? '');
    $error_message = (string) ($result['error_message'] ?? '');
    $token_issue = in_array($error_code, array('invalid_site_token', 'token_required'), true)
        || stripos($error_message, 'site token mismatch') !== false
        || stripos($error_message, 'requires authentication') !== false;

    if (!$token_issue) {
        return $response;
    }

    $current_token = (string) get_option('botwriter_site_token', '');
    if ($current_token === '') {
        return $response;
    }

    delete_option('botwriter_site_token');
    $retry_data = $data;
    $retry_data['site_token'] = '';

    botwriter_log('Site token mismatch detected. Retrying request with empty site_token.', array(
        'error' => $error_code,
        'message' => $error_message,
    ));

    return wp_remote_post($remote_url, array(
        'method'    => 'POST',
        'body'      => $retry_data,
        'timeout'   => $timeout,
        'headers'   => array(),
        'sslverify' => (bool) $ssl_verify,
    ));
}

// Function to send data to the server pass1
function botwriter_send1_data_to_server($data) {
    
    Global $botwriter_version;
    $remote_url = BOTWRITER_API_URL . 'redis_api_cola.php';
    
    // Use constant to avoid get_plugin_data() and early translation loading
    $botwriter_version = BOTWRITER_VERSION;

    // settings
    $data['version'] = $botwriter_version;
    $data['api_key'] = get_option('botwriter_api_key');    // la api_key del programa
    $data["user_domainname"] = esc_url(get_site_url());
    $data['site_token'] = get_option('botwriter_site_token', ''); 

    $data["ai_image_size"] = botwriter_normalize_ai_image_size((string) get_option('botwriter_ai_image_size', 'square'));
    $data["ai_image_quality"]=get_option('botwriter_ai_image_quality');
    $data["ai_image_style"]=get_option('botwriter_ai_image_style', 'realistic');
    $data["ai_image_style_custom"]=get_option('botwriter_ai_image_style_custom', '');
    
    // Use task-specific setting for disable_ai_images (already in $data from task/log)
    // If not present, default to 0 (images enabled)
    if (!isset($data["disable_ai_images"])) {
        $data["disable_ai_images"] = 0;
    }

    // If image generation fails, publish the post without image instead of erroring
    $data['image_error_continue'] = get_option('botwriter_image_error_continue', '0');
    
    // Provider selections
    $data['text_provider'] = get_option('botwriter_text_provider', 'openai');
    $data['image_provider'] = get_option('botwriter_image_provider', 'stockphoto');

    // Global provider "none" always means no AI images, regardless of legacy task flags.
    if ($data['image_provider'] === 'none') {
        $data['disable_ai_images'] = 1;
    }
    
    // Stock photo settings (sent always, used only when image_provider=stockphoto)
    $data['stockphoto_preferred'] = botwriter_get_current_image_model_by_provider('stockphoto');
    $data['stockphoto_selection'] = get_option('botwriter_stockphoto_selection', 'random_top10');
    $data['stockphoto_attribution'] = get_option('botwriter_stockphoto_attribution', 'caption');
    
    // Get current text model based on provider
    $text_provider = $data['text_provider'];
    $text_model_defaults = [
        'openai' => 'gpt-5.4-mini',
        'anthropic' => 'claude-sonnet-4-6',
        'google' => 'gemini-2.5-flash',
        'mistral' => 'mistral-large-latest',
        'groq' => 'llama-3.3-70b-versatile',
        'openrouter' => 'anthropic/claude-sonnet-4.6',
    ];
    $data['text_model'] = get_option("botwriter_{$text_provider}_model", $text_model_defaults[$text_provider] ?? 'gpt-5.4-mini');
    
    // Get current image model based on provider
    $image_provider = $data['image_provider'];
    $image_model_default = null;
    $image_model_option = null;
    $image_model_raw = null;

    if ($image_provider === 'stockphoto') {
        $data['image_model'] = $data['stockphoto_preferred'];
    } elseif ($image_provider === 'none') {
        $data['image_model'] = 'none';
    } else {
        $image_model_default = function_exists('botwriter_get_provider_default_image_model')
            ? (string) botwriter_get_provider_default_image_model($image_provider)
            : '';
        if ($image_model_default === '') {
            $image_model_default = 'gpt-image-1';
        }

        $image_model_option = function_exists('botwriter_get_image_model_option_name')
            ? botwriter_get_image_model_option_name($image_provider)
            : ($image_provider === 'gemini' ? 'botwriter_gemini_image_model' : "botwriter_{$image_provider}_model");

        $image_model_raw = (string) get_option($image_model_option, $image_model_default);

        if (function_exists('botwriter_get_current_image_model_by_provider')) {
            $data['image_model'] = botwriter_get_current_image_model_by_provider($image_provider);
        } else {
            $data['image_model'] = $image_model_raw;
        }
    }
    
    // Also send the specific field name the server expects
    if ($image_provider === 'gemini') {
        $data['gemini_image_model'] = $data['image_model'];

        $gemini_catalog_models = array();
        $image_model_raw_in_catalog = null;
        if (function_exists('botwriter_get_provider_image_models_flat')) {
            $gemini_catalog_models = array_keys(botwriter_get_provider_image_models_flat('gemini'));
            if (!empty($gemini_catalog_models)) {
                $image_model_raw_in_catalog = in_array(strtolower((string) $image_model_raw), $gemini_catalog_models, true);
            }
        }

        botwriter_log('Image model trace before phase 1 send', array(
            'log_id' => $data['id'] ?? null,
            'task_id' => $data['id_task'] ?? null,
            'image_provider' => $image_provider,
            'image_model_option' => $image_model_option,
            'image_model_default' => $image_model_default,
            'image_model_raw' => $image_model_raw,
            'image_model_resolved' => $data['image_model'] ?? null,
            'image_model_changed' => ((string) ($image_model_raw ?? '') !== (string) ($data['image_model'] ?? '')),
            'image_model_raw_in_catalog' => $image_model_raw_in_catalog,
            'gemini_catalog_models' => $gemini_catalog_models,
        ));
    }
    
    // Send all API keys (decrypted) - server will use the ones needed
    $data['openai_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_openai_api_key'));
    $data['anthropic_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_anthropic_api_key'));
    $data['google_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_google_api_key'));
    $data['mistral_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_mistral_api_key'));
    $data['groq_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_groq_api_key'));
    $data['openrouter_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_openrouter_api_key'));
    $data['fal_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_fal_api_key'));
    $data['replicate_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_replicate_api_key'));
    $data['stability_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_stability_api_key'));
    $data['cloudflare_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_cloudflare_api_key'));
    $data['cloudflare_account_id'] = get_option('botwriter_cloudflare_account_id');
    
    // Legacy field (kept for backward compatibility)
    $data["openai_model"]=get_option('botwriter_openai_model', 'gpt-4o-mini');

    $current_time = gmdate('Y-m-d H:i:s', current_time('timestamp'));
    $data["last_execution_time"]=$current_time;
    
    $last_execution_time = $data["last_execution_time"];   
    
    
    $category_ids=array_map('intval', explode(',', $data['category_id']));
    $titles = botwriter_get_logs_titles($data['id_task']);
    if ($titles === false) {
        $data['titles'] = '';
    } else {
        $data['titles'] = implode(' | ', $titles);
    }
            

    // add the links of the posts where it has been copied
    $links = botwriter_get_logs_links($data['id_task']);
    if ($links === false) {
        $data['links'] = '';
    } else {
        $data['links'] = implode(',', $links);
    }    

    // post_lenght
    if ($data['post_length'] === 'custom') {
        $data['post_length'] = $data['custom_post_length'];
    } 

    // =====================================================
    // CLIENT-SIDE RSS FETCH (pre-fetch before prompt build)
    // =====================================================
    $website_type = $data['website_type'] ?? '';
    if ($website_type === 'rss') {
        $rss_result = botwriter_fetch_rss_content(
            $data['rss_source'] ?? '',
            $data['links'] ?? ''
        );

        if ( ! $rss_result['success'] ) {
            botwriter_log('Client RSS fetch failed', [
                'log_id'  => $data['id'] ?? null,
                'task_id' => $data['id_task'] ?? null,
                'error'   => $rss_result['error'] ?? 'Unknown error',
            ]);
            $data['task_status'] = 'error';
            $data['error'] = $rss_result['error'] ?? 'RSS fetch failed';
            $data['intentosfase1'] = isset($data['intentosfase1']) ? (int) $data['intentosfase1'] + 1 : 1;
            botwriter_logs_register($data, $data['id']);
            return false;
        }

        // Populate data with the pre-fetched article so the prompt builder can use it
        $data['source_title']       = $rss_result['source_title'];
        $data['source_content']     = $rss_result['source_content'];
        $data['link_post_original'] = $rss_result['link_original'];
        $data['source_prefetched']  = '1';

        // Reserve the source link in the log row BEFORE the phase 1 dispatch.
        // This protects against a second cron tick picking the same article
        // while this dispatch is still in flight (HTTP latency, retries, etc.).
        if ( ! empty( $data['id'] ) ) {
            botwriter_logs_register( $data, $data['id'] );
        }

        botwriter_log('Client RSS article ready', [
            'log_id'       => $data['id'] ?? null,
            'task_id'      => $data['id_task'] ?? null,
            'article_link' => $rss_result['link_original'],
        ]);
    }
    // =====================================================

    // =====================================================
    // CLIENT-SIDE WORDPRESS FETCH (pre-fetch before prompt build)
    // =====================================================
    if ($website_type === 'wordpress') {
        $wp_result = botwriter_fetch_wordpress_content(
            $data['domain_name'] ?? '',
            $data['website_category_id'] ?? '',
            $data['links'] ?? ''
        );

        if ( ! $wp_result['success'] ) {
            botwriter_log('Client WordPress fetch failed', [
                'log_id'  => $data['id'] ?? null,
                'task_id' => $data['id_task'] ?? null,
                'error'   => $wp_result['error'] ?? 'Unknown error',
            ]);
            $data['task_status'] = 'error';
            $data['error'] = $wp_result['error'] ?? 'WordPress fetch failed';
            $data['intentosfase1'] = isset($data['intentosfase1']) ? (int) $data['intentosfase1'] + 1 : 1;
            botwriter_logs_register($data, $data['id']);
            return false;
        }

        $data['source_title']       = $wp_result['source_title'];
        $data['source_content']     = $wp_result['source_content'];
        $data['link_post_original'] = $wp_result['link_original'];
        $data['source_prefetched']  = '1';

        // Reserve the source link in the log row BEFORE the phase 1 dispatch
        // (see RSS branch above for rationale).
        if ( ! empty( $data['id'] ) ) {
            botwriter_logs_register( $data, $data['id'] );
        }

        botwriter_log('Client WordPress article ready', [
            'log_id'       => $data['id'] ?? null,
            'task_id'      => $data['id_task'] ?? null,
            'article_link' => $wp_result['link_original'],
        ]);
    }
    // =====================================================

    // Build prompt from template (except for super1 which is handled by server)
    if ($website_type !== 'super1') {
        $data['client_prompt'] = botwriter_build_client_prompt($data);
    }

    botwriter_log('SEND1: after prompt build', [
        'log_id'              => $data['id'] ?? null,
        'website_type'        => $website_type,
        'has_client_prompt'   => !empty($data['client_prompt']),
        'client_prompt_len'   => strlen($data['client_prompt'] ?? ''),
        'source_prefetched'   => $data['source_prefetched'] ?? '0',
        'has_source_title'    => !empty($data['source_title']),
        'has_source_content'  => !empty($data['source_content']),
        'link_post_original'  => $data['link_post_original'] ?? '',
    ]);
    
    botwriter_log('Dispatching phase 1 request', [
        'log_id' => $data['id'] ?? null,
        'task_id' => $data['id_task'] ?? null,
        'website_type' => $data['website_type'] ?? null,
        'status' => $data['task_status'] ?? null,
        'attempt' => $data['intentosfase1'] ?? null,
        'text_provider' => $data['text_provider'] ?? null,
        'text_model' => $data['text_model'] ?? null,
        'image_provider' => $data['image_provider'] ?? null,
        'image_model' => $data['image_model'] ?? null,
        'gemini_image_model' => $data['gemini_image_model'] ?? null,
    ]);

    $data["error"]= "";

    $ssl_verify = get_option('botwriter_sslverify');


    if ($ssl_verify === 'no') {
        $ssl_verify = false;
    } else {
        $ssl_verify = true;
    }   



    $response = botwriter_post_compat_with_token_recovery($remote_url, $data, $ssl_verify, 45);

    $data["intentosfase1"]++;
    botwriter_logs_register($data, $data["id"]); 
    
	$last_execution_time=$data["last_execution_time"];												  
    
    

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();        
        botwriter_log('Phase 1 request error', [
            'log_id' => $data['id'] ?? null,
            'task_id' => $data['id_task'] ?? null,
            'website_type' => $data['website_type'] ?? null,
            'error' => $error_message,
        ]);
        $data["task_status"]="error";
        botwriter_logs_register($data, $data["id"]);            
        return false;

    } else {
        
        if ($response['response']['code'] === 200) {
        
            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);        

            //error_log("Data received: " . print_r($body, true));

            if (!empty($result['site_token'])) {
                update_option('botwriter_site_token', sanitize_text_field((string) $result['site_token']));
            }

            // STOPFORMANY: server reports too many consecutive errors
            if (isset($result['error']) && strpos($result['error'], 'STOPFORMANY') === 0) {
                update_option('botwriter_stopformany', true);
                $data['task_status'] = 'error';
                $data['error'] = $result['error'];
                $data['intentosfase1'] = 8; // stop retries
                botwriter_logs_register($data, $data['id']);
                botwriter_log('STOPFORMANY activated from phase 1', [
                    'log_id'  => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'error'   => $result['error'],
                ]);
                return false;
            }

            if (isset($result['id_task_server']) && $result['id_task_server'] !== 0) { // ok                
                $data["id_task_server"]=$result['id_task_server'];
                $data["task_status"]='inqueue';

                // Capture site_token from server response (auto-provisioning)
                if (!empty($result['site_token'])) {
                    update_option('botwriter_site_token', sanitize_text_field($result['site_token']));
                }

                botwriter_logs_register($data, $data["id"]);             
                botwriter_log('Phase 1 request accepted', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $result['id_task_server'],
                ]);
                return $result['id_task_server'];                 
            } else { // error — id_task_server is 0 or missing
                $data["task_status"]="error";
                // Use full error_message (may include reset link) when available
                $data["error"] = !empty($result['error_message']) ? $result['error_message'] : ($result['error'] ?? '');

                // Generic terminal flag — server says stop retrying
                if (!empty($result['terminal'])) {
                    $data['intentosfase1'] = 8;
                }
                // Show server-provided admin notice
                if (!empty($result['error_message']) && (int)($result['error_level'] ?? 0) === 1) {
                    botwriter_announcements_add(
                        __('Service notice', 'botwriter'),
                        wp_kses_post($result['error_message'])
                    );
                }

                // Handle known server errors (same logic as Phase 2)
                if ($data["error"] == "Maximum monthly posts limit reached") {
                    botwriter_announcements_add("Maximum monthly posts limit reached", "You have reached the maximum monthly posts limit. Please upgrade your plan to continue using the plugin. <a href='https://wpbotwriter.com' target='_blank'>Go to upgrade</a>");
                    $data["intentosfase1"] = 8;
                }
                if ($data["error"] == "Payment date exceeded") {
                    botwriter_announcements_add("Payment date exceeded", "Your subscription payment date has exceeded. Please renew your subscription to continue using the plugin. <a href='https://wpbotwriter.com' target='_blank'>Go to renew</a>");
                    $data["intentosfase1"] = 8;
                }
                if ($data["error"] == "API Key error") {
                    botwriter_announcements_add("API Key error", "Your API Key is invalid. Please check your API Key in the plugin settings. <a href='admin.php?page=botwriter_settings'>Go to Settings</a>");
                    $data["intentosfase1"] = 8;
                }

                botwriter_logs_register($data, $data["id"]);                
                botwriter_log('Phase 1 request rejected', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'error' => $data['error'],
                    'response_length' => isset($body) ? strlen($body) : null,
                ]);
                return false;
            }            
        } else {  // error          
            $data["task_status"]="error";
            botwriter_logs_register($data, $data["id"]);            
            botwriter_log('Phase 1 non-200 response', [
                'log_id' => $data['id'] ?? null,
                'task_id' => $data['id_task'] ?? null,
                'status_code' => $response['response']['code'],
            ]);
            return false;
        }         
    }

    
}

// Function to send data to the server pass2
function botwriter_send2_data_to_server($data) {  
    Global $wpdb;    
    
    $data['api_key'] = get_option('botwriter_api_key');
    $data["user_domainname"] = esc_url(get_site_url());
    $data['site_token'] = get_option('botwriter_site_token', '');

    $remote_url =  BOTWRITER_API_URL . 'redis_api_finish.php';
            
    $ssl_verify = get_option('botwriter_sslverify');
    if ($ssl_verify === 'no') {
        $ssl_verify = false;
    } else {
        $ssl_verify = true;
    }   

    botwriter_log('Dispatching phase 2 request', [
        'log_id' => $data['id'] ?? null,
        'task_id' => $data['id_task'] ?? null,
        'website_type' => $data['website_type'] ?? null,
        'id_task_server' => $data['id_task_server'] ?? null,
    ]);

    $response = botwriter_post_compat_with_token_recovery($remote_url, $data, $ssl_verify, 45);

    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        botwriter_log('Phase 2 request error', [
            'log_id' => $data['id'] ?? null,
            'task_id' => $data['id_task'] ?? null,
            'website_type' => $data['website_type'] ?? null,
            'error' => $error_message,
        ]);
        $data["error"]= "Error sending data " . $error_message;
        return false;
    } else {
            
        if ($response['response']['code'] === 200) {
        
            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            if (!empty($result['site_token'])) {
                update_option('botwriter_site_token', sanitize_text_field((string) $result['site_token']));
            }
        
            //echo 'Data recive: <pre>' . print_r($result, true) . '</pre>'; 
            
            // results errors
            if (isset($result["task_status"]) && $result["task_status"] == "error") {
                $data["task_status"]="error";
                // Use full error_message (may include reset link) when available
                $data["error"] = !empty($result['error_message']) ? $result['error_message'] : ($result['error'] ?? '');
                botwriter_log('Phase 2 reported error', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $data['id_task_server'] ?? null,
                    'error' => $data['error'],
                ]);

                // Generic terminal flag — server says stop retrying
                if (!empty($result['terminal'])) {
                    $data['intentosfase1'] = 8;
                }
                // Show server-provided admin notice
                if (!empty($result['error_message']) && (int)($result['error_level'] ?? 0) === 1) {
                    botwriter_announcements_add(
                        __('Service notice', 'botwriter'),
                        wp_kses_post($result['error_message'])
                    );
                }

                if ($data["error"]=="Maximum monthly posts limit reached") {
                    botwriter_announcements_add("Maximum monthly posts limit reached", "You have reached the maximum monthly posts limit. Please upgrade your plan to continue using the plugin. <a href='https://wpbotwriter.com' target='_blank'>Go to upgrade</a>");
                    $data["intentosfase1"]=8;
                } 
                if ($data["error"]=="Payment date exceeded") {
                    botwriter_announcements_add("Payment date exceeded", "Your subscription payment date has exceeded. Please renew your subscription to continue using the plugin. <a href='https://wpbotwriter.com' target='_blank'>Go to renew</a>");
                    $data["intentosfase1"]=8;
                }
                if ($data["error"]=="API Key error") {
                    botwriter_announcements_add("API Key error", "Your API Key is invalid. Please check your API Key in the plugin settings. <a href='admin.php?page=botwriter_settings'>Go to Settings</a>");
                    $data["intentosfase1"]=8;
                }
                // STOPFORMANY: server reports too many consecutive errors
                if (isset($result['error']) && strpos($result['error'], 'STOPFORMANY') === 0) {
                    update_option('botwriter_stopformany', true);
                    $data['intentosfase1'] = 8;
                    botwriter_log('STOPFORMANY activated from phase 2', [
                        'log_id'  => $data['id'] ?? null,
                        'task_id' => $data['id_task'] ?? null,
                        'error'   => $result['error'],
                    ]);
                }
                
                botwriter_logs_register($data, $data["id"]);
                return false;  
            }  
            
            // result completed
            botwriter_log('Phase 2 response payload', [
                'log_id' => $data['id'] ?? null,
                'task_id' => $data['id_task'] ?? null,
                'id_task_server' => $data['id_task_server'] ?? null,
                'task_status' => $result['task_status'] ?? null,
            ]);
            if (isset($result["task_status"]) && $result["task_status"] == "completed") {
                // Preserve image_attribution from server response (not stored in DB)
                $image_attribution = isset($result['image_attribution']) ? $result['image_attribution'] : null;

                botwriter_logs_register($result, $data["id"]);                          
                botwriter_log('Phase 2 completed event', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $data['id_task_server'] ?? null,
                ]);
                
                $result=botwriter_logs_get($data["id"]);  // merge the result with the log

                // Re-attach image_attribution (not persisted in logs table)
                if ($image_attribution) {
                    $result['image_attribution'] = $image_attribution;
                }

                // ── Guard: prevent duplicate post creation ──
                // If this log already has a published post, skip generation.
                if (!empty($result['id_post_published']) && intval($result['id_post_published']) > 0) {
                    botwriter_log('Phase 2 skipped — post already published', [
                        'log_id' => $data['id'],
                        'post_id' => $result['id_post_published'],
                    ]);
                    return $result;
                }

                if ($result["website_type"] == "super1") {                                                            
                    botwriter_super1_log_to_bd($result,$data["id"]);                    

                } else {
                    $post_id=botwriter_generate_post($result);
                    $result["id_post_published"]=$post_id;
                }

                botwriter_logs_register($result, $data["id"]);               
                if ($result["website_type"] == "super2") {
                    //update tabla super poner el task_Status en completed
                    $wpdb->update($wpdb->prefix . 'botwriter_super', ['task_status' => 'completed'], ['id_log' => $data["id"]]);
                }


                return $result;
            } 
            
            // other results, inqueue, pending, etc
            $now=current_time('timestamp');
            $last_execution_time = strtotime($data["last_execution_time"]);
            $diff = $now - $last_execution_time;
            if ($diff > 60 * 5) { // 5 minutes
                    $data["task_status"]="error";
                    $data["error"]="Error in server";
                    botwriter_logs_register($data, $data["id"]);                    
            botwriter_log('Phase 2 timeout detected', [
            'log_id' => $data['id'] ?? null,
            'task_id' => $data['id_task'] ?? null,
            ]);
            } 
            return false;
                           
           
            
            


        } else {            // error
            // update log
            $data["task_status"]="error";
            botwriter_logs_register($data, $data["id"]);                        
            botwriter_log('Phase 2 non-200 response', [
                'log_id' => $data['id'] ?? null,
                'task_id' => $data['id_task'] ?? null,
                'status_code' => $response['response']['code'],
            ]);
            return false;
        }        
    }

    
        
}



 

function botwriter_cambiar_status_ajax() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    check_ajax_referer('botwriter_cambiar_status_nonce', 'nonce');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nuevo_status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    
    

    if ($id > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'botwriter_tasks';

        $result = $wpdb->update(
            $table_name,
            ['status' => $nuevo_status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Estado actualizado correctamente']);
        } else {
            wp_send_json_error(['message' => 'Error al actualizar el estado']);
        }
    } else {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    wp_die();
}
add_action('wp_ajax_botwriter_cambiar_status', 'botwriter_cambiar_status_ajax');

/**
 * AJAX handler to check/preview an RSS feed from the admin UI.
 * Reads the feed client-side using botwriter_fetch_rss_content().
 */
function botwriter_check_rss_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'error' => 'Permission denied' ] );
    }

    check_ajax_referer( 'botwriter_check_rss_nonce', 'nonce' );

    $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
    if ( empty( $url ) ) {
        wp_send_json_error( [ 'error' => 'RSS URL is required.' ] );
    }

    $result = botwriter_fetch_rss_content( $url );
    if ( $result['success'] ) {
        wp_send_json_success( [
            'title'       => $result['source_title'],
            'description' => $result['source_content'],
            'link'        => $result['link_original'],
        ] );
    } else {
        wp_send_json_error( [ 'error' => $result['error'] ] );
    }
}
add_action( 'wp_ajax_botwriter_check_rss', 'botwriter_check_rss_ajax' );

/**
 * AJAX handler to fetch categories from a remote WordPress site.
 * Reads categories client-side using botwriter_fetch_wordpress_categories().
 */
function botwriter_get_wordpress_categories_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'error' => 'Permission denied' ] );
    }

    check_ajax_referer( 'botwriter_wp_categories_nonce', 'nonce' );

    $domain = isset( $_POST['website_domainname'] ) ? esc_url_raw( wp_unslash( $_POST['website_domainname'] ) ) : '';
    if ( empty( $domain ) ) {
        wp_send_json_error( [ 'error' => 'WordPress domain is required.' ] );
    }

    $result = botwriter_fetch_wordpress_categories( $domain );
    if ( $result['success'] ) {
        wp_send_json_success( $result['categories'] );
    } else {
        wp_send_json_error( [ 'error' => $result['error'] ] );
    }
}
add_action( 'wp_ajax_botwriter_get_wp_categories', 'botwriter_get_wordpress_categories_ajax' );

// AJAX handler for deleting a log entry
add_action('wp_ajax_botwriter_delete_log', 'botwriter_delete_log_ajax');
function botwriter_delete_log_ajax() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    // Verify nonce
    check_ajax_referer('botwriter_logs_delete_nonce', 'nonce');
    
    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    
    if ($log_id > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'botwriter_logs';
        
        $result = $wpdb->delete($table_name, array('id' => $log_id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Log deleted successfully', 'botwriter')]);
        } else {
            wp_send_json_error(['message' => __('Error deleting log', 'botwriter')]);
        }
    } else {
        wp_send_json_error(['message' => __('Invalid log ID', 'botwriter')]);
    }
    
    wp_die();
}

// AJAX handler for bulk deleting log entries
add_action('wp_ajax_botwriter_bulk_delete_logs', 'botwriter_bulk_delete_logs_ajax');
function botwriter_bulk_delete_logs_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    check_ajax_referer('botwriter_logs_delete_nonce', 'nonce');

    $log_ids = isset($_POST['log_ids']) ? array_map('intval', $_POST['log_ids']) : array();
    $log_ids = array_filter($log_ids, function($id) { return $id > 0; });

    if (empty($log_ids)) {
        wp_send_json_error(['message' => __('No logs selected', 'botwriter')]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_logs';
    $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
    $table_name = esc_sql($table_name);
    $query = $wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $log_ids);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally and placeholders are prepared from sanitized IDs.
    $deleted = $wpdb->query($query);

    if ($deleted !== false) {
        wp_send_json_success(['message' => sprintf(
            /* translators: %d: number of deleted log entries. */
            __('%d log(s) deleted successfully', 'botwriter'),
            $deleted
        )]);
    } else {
        wp_send_json_error(['message' => __('Error deleting logs', 'botwriter')]);
    }

    wp_die();
}

// AJAX handler for getting taxonomies and terms for a post type
add_action('wp_ajax_botwriter_get_taxonomies', 'botwriter_get_taxonomies_ajax');
function botwriter_get_taxonomies_ajax() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    // Verify nonce
    check_ajax_referer('botwriter_taxonomies_nonce', 'nonce');
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post';
    
    // Get taxonomies for this post type
    $taxonomies = get_object_taxonomies($post_type, 'objects');
    
    $result = array();
    // Skip tag taxonomies since AI generates tags automatically
    $skip_taxonomies = array('post_tag', 'product_tag');
    
    foreach ($taxonomies as $taxonomy) {
        // Skip non-public taxonomies
        if (!$taxonomy->public) {
            continue;
        }
        
        // Skip tag taxonomies (AI generates tags)
        if (in_array($taxonomy->name, $skip_taxonomies, true)) {
            continue;
        }
        
        // Get terms for this taxonomy
        $terms = get_terms(array(
            'taxonomy' => $taxonomy->name,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        
        $terms_data = array();
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $terms_data[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent' => $term->parent,
                );
            }
        }
        
        $result[] = array(
            'name' => $taxonomy->name,
            'label' => $taxonomy->label,
            'hierarchical' => $taxonomy->hierarchical,
            'terms' => $terms_data,
        );
    }
    
    wp_send_json_success($result);
}


function botwriter_attach_image_to_post($post_id, $image_url, $post_title, $translated_image_slug = '', $image_attribution = null) {
        if (!$image_url || !$post_id || empty(trim($image_url))) {
            botwriter_log('Image attachment skipped', [
                'post_id' => $post_id,
                'image_url' => $image_url ?: 'empty',
                'reason' => 'Invalid post ID or empty image URL'
            ]);
            return 'Invalid post ID or image URL';
        }
        
  if ($image_url && $post_id) {            
            //$image_data = file_get_contents($image_url);
            $image_data = wp_remote_retrieve_body(wp_remote_get($image_url));
            $upload_dir = wp_upload_dir();
            // Use translated image slug if available, otherwise fall back to title
            if (!empty($translated_image_slug)) {
                $base_name = sanitize_file_name($translated_image_slug);
            } else {
                $base_name = sanitize_file_name(remove_accents($post_title));
            }
            if (empty($base_name)) {
                $base_name = 'botwriter-post-' . $post_id;
            }
            /**
             * Filter the filename used for BotWriter generated images.
             *
             * @param string $base_name Base filename without extension.
             * @param int    $post_id   Post ID.
             * @param string $post_title Post title.
             */
            $base_name = apply_filters('botwriter_image_filename', $base_name, $post_id, $post_title);
            $filename = $base_name . '.jpg';
            $filename = wp_unique_filename($upload_dir['path'], $filename);
            
            if (wp_mkdir_p($upload_dir['path'])) {
                $file = $upload_dir['path'] . '/' . $filename;                
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;                
            }
            
            global $wp_filesystem;
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
            if ( $wp_filesystem->put_contents( $file, $image_data, FS_CHMOD_FILE ) ) {
                //error_log('Imagen guardada exitosamente en: ' . $file);
            } else {
                //error_log('Error al guardar la imagen en: ' . $file);
            }
    
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = [
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => sanitize_file_name($filename),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
    
            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            // Apply post-processing if enabled
            $postprocess_enabled = get_option('botwriter_image_postprocess_enabled', '0');
            if ($postprocess_enabled === '1') {
                $processed_file = botwriter_process_image($file);
                if ($processed_file && $processed_file !== $file) {
                    // Update file reference if format changed
                    $file = $processed_file;
                    // Update attachment with new file info
                    $wp_filetype = wp_check_filetype(basename($file), null);
                    wp_update_post([
                        'ID' => $attach_id,
                        'post_mime_type' => $wp_filetype['type'],
                    ]);
                    update_attached_file($attach_id, $file);
                }
            }
            
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);
            set_post_thumbnail($post_id, $attach_id);

            // Deterministic featured-image ALT: fill empty ALT from post title.
            if (get_option('botwriter_seo_featured_image_alt_enabled', '1') === '1') {
                $current_alt = trim((string) get_post_meta($attach_id, '_wp_attachment_image_alt', true));
                if ($current_alt === '') {
                    $fallback_alt = sanitize_text_field($post_title);
                    if ($fallback_alt === '') {
                        $fallback_alt = sanitize_text_field((string) get_the_title($post_id));
                    }
                    if ($fallback_alt !== '') {
                        update_post_meta($attach_id, '_wp_attachment_image_alt', $fallback_alt);
                    }
                }
            }

            // Apply stock photo attribution as image caption.
            if (!empty($image_attribution) && is_array($image_attribution)) {
                $attribution_mode = get_option('botwriter_stockphoto_attribution', 'caption');
                if ($attribution_mode !== 'disabled') {
                    $author = sanitize_text_field($image_attribution['author'] ?? '');
                    $source = sanitize_text_field($image_attribution['source'] ?? '');

                    // Build caption: "Photo by Author on Source"
                    $caption = '';
                    if ($author && $source) {
                        $caption = sprintf(
                            /* translators: 1: photographer name, 2: image source name */
                            esc_html__('Photo by %1$s on %2$s', 'botwriter'),
                            $author,
                            $source
                        );
                    } elseif ($source) {
                        $caption = sprintf(
                            /* translators: %s: image source name. */
                            esc_html__('Photo from %s', 'botwriter'),
                            $source
                        );
                    }

                    if ($caption && $attribution_mode === 'caption') {
                        wp_update_post(array(
                            'ID' => $attach_id,
                            'post_excerpt' => $caption,
                        ));
                    }

                    // Store full attribution data as post meta for later use
                    update_post_meta($attach_id, '_botwriter_image_attribution', $image_attribution);
                }
            }
    
            
        }
    }

/**
 * Process image for optimization (resize, compress, convert format)
 * Uses WordPress wp_get_image_editor for maximum hosting compatibility.
 * 
 * @param string $file_path Full path to the image file.
 * @return string|false New file path if processed, original path if no changes, false on error.
 */
function botwriter_process_image($file_path) {
    if (!file_exists($file_path)) {
        botwriter_log('Image post-processing: File not found', ['path' => $file_path]);
        return false;
    }
    
    // Get settings
    $output_format = get_option('botwriter_image_output_format', 'webp');
    $max_width = intval(get_option('botwriter_image_max_width', 1200));
    $compression = intval(get_option('botwriter_image_compression', 85));
    $max_filesize = intval(get_option('botwriter_image_max_filesize', 120)) * 1024; // Convert KB to bytes
    
    // Log settings at start
    $original_size = filesize($file_path);
    botwriter_log('Image post-processing: Starting', [
        'file' => basename($file_path),
        'original_size_kb' => round($original_size / 1024, 1),
        'settings' => [
            'output_format' => $output_format,
            'max_width' => $max_width,
            'compression' => $compression,
            'max_filesize_kb' => $max_filesize / 1024
        ]
    ]);
    
    // Get WordPress image editor
    $editor = wp_get_image_editor($file_path);
    if (is_wp_error($editor)) {
        botwriter_log('Image post-processing: Failed to load editor', [
            'path' => $file_path,
            'error' => $editor->get_error_message()
        ]);
        return $file_path; // Return original on error
    }
    
    // Log which editor is being used
    botwriter_log('Image post-processing: Editor loaded', [
        'editor_class' => get_class($editor)
    ]);
    
    $size = $editor->get_size();
    $modified = false;
    
    botwriter_log('Image post-processing: Original dimensions', [
        'width' => $size['width'],
        'height' => $size['height']
    ]);
    
    // Resize if wider than max width
    if ($max_width > 0 && $size['width'] > $max_width) {
        $new_height = intval($size['height'] * ($max_width / $size['width']));
        $result = $editor->resize($max_width, $new_height, false);
        if (!is_wp_error($result)) {
            $modified = true;
            botwriter_log('Image post-processing: Resized', [
                'from' => $size['width'] . 'x' . $size['height'],
                'to' => $max_width . 'x' . $new_height
            ]);
        }
    }
    
    // Set quality
    $editor->set_quality($compression);
    
    // Determine output file path and mime type
    $path_info = pathinfo($file_path);
    $new_extension = $path_info['extension'];
    $mime_type = null;
    
    if ($output_format !== 'original') {
        switch ($output_format) {
            case 'webp':
                // Check if WebP is supported via GD or Imagick
                $webp_supported = function_exists('imagewebp');
                if (!$webp_supported && extension_loaded('imagick') && class_exists('Imagick')) {
                    // Check Imagick WebP support dynamically to avoid static analysis errors
                    $imagick_formats = call_user_func(['Imagick', 'queryFormats'], 'WEBP');
                    $webp_supported = !empty($imagick_formats);
                }
                if ($webp_supported) {
                    $new_extension = 'webp';
                    $mime_type = 'image/webp';
                } else {
                    // Fallback to JPEG if WebP not supported
                    $new_extension = 'jpg';
                    $mime_type = 'image/jpeg';
                    botwriter_log('Image post-processing: WebP not supported, falling back to JPEG');
                }
                break;
            case 'jpeg':
                $new_extension = 'jpg';
                $mime_type = 'image/jpeg';
                break;
            case 'png':
                $new_extension = 'png';
                $mime_type = 'image/png';
                break;
        }
    }
    
    // Build new file path
    $new_file_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $new_extension;
    
    botwriter_log('Image post-processing: Format decision', [
        'original_extension' => $path_info['extension'],
        'new_extension' => $new_extension,
        'mime_type' => $mime_type,
        'new_file_path' => basename($new_file_path)
    ]);
    
    // Save the image
    $save_args = [];
    if ($mime_type) {
        $save_args['mime_type'] = $mime_type;
    }
    
    $saved = $editor->save($new_file_path, $mime_type);
    
    if (is_wp_error($saved)) {
        botwriter_log('Image post-processing: Failed to save', [
            'path' => $new_file_path,
            'error' => $saved->get_error_message()
        ]);
        return $file_path; // Return original on error
    }
    
    $new_file_path = $saved['path'];
    
    botwriter_log('Image post-processing: Initial save complete', [
        'saved_file' => basename($new_file_path),
        'size_kb' => round(filesize($new_file_path) / 1024, 1)
    ]);
    
    // If max filesize is set and file is too large, reduce quality iteratively
    if ($max_filesize > 0) {
        $current_size = filesize($new_file_path);
        $quality = $compression;
        $attempts = 0;
        $max_attempts = 5;
        
        botwriter_log('Image post-processing: Checking filesize target', [
            'current_kb' => round($current_size / 1024, 1),
            'target_kb' => $max_filesize / 1024,
            'needs_compression' => $current_size > $max_filesize
        ]);
        
        while ($current_size > $max_filesize && $quality > 40 && $attempts < $max_attempts) {
            $quality -= 10;
            $attempts++;
            
            botwriter_log('Image post-processing: Compression attempt', [
                'attempt' => $attempts,
                'quality' => $quality
            ]);
            
            // Reload and resave with lower quality
            $editor = wp_get_image_editor($new_file_path);
            if (!is_wp_error($editor)) {
                $editor->set_quality($quality);
                $saved = $editor->save($new_file_path, $mime_type);
                if (!is_wp_error($saved)) {
                    $current_size = filesize($saved['path']);
                    $new_file_path = $saved['path'];
                }
            }
        }
        
        if ($attempts > 0) {
            botwriter_log('Image post-processing: Compressed for filesize target', [
                'target_kb' => $max_filesize / 1024,
                'final_kb' => round($current_size / 1024, 1),
                'final_quality' => $quality,
                'attempts' => $attempts
            ]);
        }
    }
    
    // Delete original file if format changed
    if ($new_file_path !== $file_path && file_exists($file_path) && file_exists($new_file_path)) {
        wp_delete_file($file_path);
        botwriter_log('Image post-processing: Converted format', [
            'from' => $path_info['extension'],
            'to' => $new_extension,
            'new_size_kb' => round(filesize($new_file_path) / 1024, 1)
        ]);
    }
    
    // Final summary log
    $final_size = filesize($new_file_path);
    botwriter_log('Image post-processing: Complete', [
        'original_file' => basename($file_path),
        'final_file' => basename($new_file_path),
        'original_size_kb' => round($original_size / 1024, 1),
        'final_size_kb' => round($final_size / 1024, 1),
        'size_reduction_percent' => round((1 - ($final_size / $original_size)) * 100, 1)
    ]);
    
    return $new_file_path;
}
  
    
  
    add_action('plugins_loaded', 'botwriter_check_update');
    function botwriter_check_update() {        
        // Ensure install date exists for legacy installs
        if (get_option('botwriter_install_date') === false) {
            update_option('botwriter_install_date', current_time('timestamp'));
        }
        // Use constant instead of get_plugin_data() to obtain current plugin version
        $plugin_version = BOTWRITER_VERSION;

        // Get the previously installed version
        $version_instalada = get_option('botwriter_version');
        
        if ($version_instalada != $plugin_version) {
            botwriter_create_table(); 
            
            // Insert default templates if none exist (for updates from older versions)
            botwriter_insert_all_default_templates();

            // Migration: reset 'custom' provider to defaults (removed in this version)
            if (get_option('botwriter_text_provider') === 'custom') {
                update_option('botwriter_text_provider', 'openai');
            }
            if (get_option('botwriter_image_provider') === 'custom') {
                update_option('botwriter_image_provider', 'stockphoto');
            }
            // Clean up custom provider options
            delete_option('botwriter_custom_text_url');
            delete_option('botwriter_custom_text_api_key');
            delete_option('botwriter_custom_text_model');
            delete_option('botwriter_custom_text_timeout');
            delete_option('botwriter_custom_image_url');
            delete_option('botwriter_custom_image_type');
            delete_option('botwriter_custom_image_model');
            delete_option('botwriter_custom_image_timeout');

            // Drop direct mode table if it exists
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}botwriter_direct_tasks");
            
            update_option('botwriter_version', $plugin_version); // Update version in database
        }
    }


 
// new super functions
add_action('wp_ajax_botwriter_actualizar_articulo', 'botwriter_actualizar_articulo_callback');

function botwriter_actualizar_articulo_callback() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce'); // Validamos el nonce para seguridad
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_super';
    
    $id      = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';

    $result = $wpdb->update(
        $table_name,
        array('title' => $title, 'content' => $content),
        array('id' => $id)
    );

    if ($result !== false) {
        wp_send_json_success('Artículo actualizado correctamente.');
    } else {
        wp_send_json_error('Error al actualizar el artículo.');
    }

    wp_die();
}

add_action('wp_ajax_botwriter_eliminar_articulo', 'botwriter_eliminar_articulo_callback');

function botwriter_eliminar_articulo_callback() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce'); // Validamos el nonce para seguridad
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_super';

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    $result = $wpdb->delete($table_name, array('id' => $id));
    // consultar cuantos registros con id_task=0
    $result = $wpdb->get_results("SELECT * FROM $table_name WHERE id_task='0'");
    $num_rows = count($result);
    if ($num_rows == 0) {
        // borramos en la tabla logs la tarea super1 si no hay articulos
        $table_name_logs = $wpdb->prefix . 'botwriter_logs';
        $wpdb->delete($table_name_logs, array('website_type' => 'super1'));        
    }
    
    

    if ($result !== false) {
        wp_send_json_success('Artículo eliminado correctamente.');
    } else {
        wp_send_json_error('Error al eliminar el artículo.');
    }

    wp_die();
}
 
add_action('wp_ajax_botwriter_check_super1', 'botwriter_check_super1_callback');

function botwriter_check_super1_callback() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce'); // Validamos el nonce para seguridad 
    $estado_super1=botwriter_super1_check_task_finish();    
    if ($estado_super1=="completed") {        
        $response = botwriter_super1_view_articles_html();                
        wp_send_json_success($response);
        return;
    } 
    
    if ($estado_super1=="error") {         
        // borramos la tarea super1
        global $wpdb;
        $table_name = $wpdb->prefix . 'botwriter_super';            
        $wpdb->delete($table_name, array('id_task' => 0));        
        wp_send_json_error("error");         
        return;
    }     
    wp_send_json_error('inqueue');    

    
} 


add_action('wp_ajax_botwriter_create_super1', 'botwriter_create_super1_callback');

function botwriter_create_super1_callback() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce'); // Validamos el nonce para seguridad        
    if (!isset($_POST['prompt']) || !isset($_POST['numarticles'])) {
        botwriter_log('Super1 creation request missing parameters');
        wp_send_json_error('Missing required parameters.');
        wp_die();
    }
    
    $prompt = sanitize_text_field(wp_unslash($_POST['prompt']));
    $numarticles = intval(wp_unslash($_POST['numarticles']));
    if ($prompt=="Custom") {
        $category_id = isset($_POST['category_id']) ? sanitize_text_field(wp_unslash($_POST['category_id'])) : '0';
    }

    botwriter_log('Super1 creation request received', [
        'prompt' => $prompt,
        'num_articles' => $numarticles,
        'category_id' => isset($category_id) ? $category_id : null,
    ]);
    
    $title_prompt = $prompt;
    
    if ($prompt=="Custom") {
        $content_prompt = isset($_POST['custom_prompt']) ? sanitize_text_field(wp_unslash($_POST['custom_prompt'])) : '';
    } else {
        $info_blog = botwriter_get_info_blog();
        $json_info_blog = json_encode($info_blog, JSON_PRETTY_PRINT);
        $content_prompt = $json_info_blog;
    }
    $task_name = "Super1 Task " . current_time('Y-m-d H:i:s');
    $log_id = botwriter_super1_create_task($task_name, $title_prompt,$content_prompt, $numarticles, $category_id);

    botwriter_log('Super1 task queued', [
        'log_id' => $log_id,
        'title_prompt' => $title_prompt,
        'num_articles' => $numarticles,
    ]);

    wp_send_json_success("Task created successfully: " . $title_prompt . " " . $content_prompt . " " . $numarticles . " " . $category_id);
    

}


add_action('wp_ajax_botwriter_eliminar_super1', 'botwriter_eliminar_super1_y_logs0');

function botwriter_eliminar_super1_y_logs0() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce'); 
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_super';
    $table_name_logs = $wpdb->prefix . 'botwriter_logs';

    $result = $wpdb->delete($table_name, array('id_task' => 0));
    $result = $wpdb->delete($table_name_logs, array('website_type' => 'super1'));
    
    if ($result !== false) {
        wp_send_json_success('Super1 task deleted successfully.');
    } else {
        wp_send_json_error('Error');
    }
    wp_die();
}

add_action('wp_ajax_botwriter_create_super1_manual', 'botwriter_create_super1_manual_callback');

function botwriter_create_super1_manual_callback() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('botwriter_super_nonce');
    
    if (!isset($_POST['manual_titles']) || empty(trim(wp_unslash($_POST['manual_titles'])))) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only emptiness check; sanitized below before use.
        wp_send_json_error('No titles provided.');
        wp_die();
    }
    
    $raw_titles = sanitize_textarea_field(wp_unslash($_POST['manual_titles']));
    $global_prompt = isset($_POST['global_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['global_prompt'])) : '';
    $category_id = isset($_POST['category_id']) ? intval(wp_unslash($_POST['category_id'])) : 0;
    
    // Split titles by newline and filter empty lines
    $titles = array_filter(array_map('trim', explode("\n", $raw_titles)), function($t) {
        return !empty($t);
    });
    
    if (empty($titles)) {
        wp_send_json_error('No valid titles found.');
        wp_die();
    }
    
    // Limit to 100 titles max
    if (count($titles) > 100) {
        $titles = array_slice($titles, 0, 100);
    }
    
    botwriter_log('Manual Super1 creation request', [
        'num_titles' => count($titles),
        'category_id' => $category_id,
        'has_global_prompt' => !empty($global_prompt),
    ]);
    
    global $wpdb;
    
    // 1. Create a completed super1 log entry (bypassing AI generation)
    $log_data = array(
        'task_name' => 'Manual Super1 Task ' . current_time('Y-m-d H:i:s'),
        'task_status' => 'completed',
        'website_type' => 'super1',
        'title_prompt' => 'Manual',
        'content_prompt' => $global_prompt,
        'post_count' => count($titles),
        'category_id' => $category_id,
    );
    $log_id = botwriter_logs_register($log_data);
    
    if (!$log_id) {
        wp_send_json_error('Error creating log entry.');
        wp_die();
    }
    
    // 2. Insert each title into wp_botwriter_super
    $table_name = $wpdb->prefix . 'botwriter_super';
    $category_name = '';
    if ($category_id > 0) {
        $category_name = get_cat_name($category_id);
    }
    
    foreach ($titles as $title) {
        $data = array(
            'title'         => sanitize_text_field($title),
            'content'       => $global_prompt,
            'category_name' => $category_name,
            'category_id'   => $category_id,
            'id_log'        => $log_id,
            'id_task'       => 0, // draft, will be assigned on save
            'task_status'   => '',
        );
        $wpdb->insert($table_name, $data);
    }
    
    botwriter_log('Manual Super1 titles inserted', [
        'log_id' => $log_id,
        'count' => count($titles),
    ]);
    
    // 3. Return the articles HTML for immediate review
    $html = botwriter_super1_view_articles_html(0);
    wp_send_json_success($html);
}


// ========================================
// CONTENT REWRITER AJAX HANDLERS
// ========================================
add_action('wp_ajax_botwriter_rewriter_fetch', 'botwriter_rewriter_fetch_ajax');
add_action('wp_ajax_botwriter_rewriter_create_task', 'botwriter_rewriter_create_task_ajax');

/**
 * AJAX: Fetch and extract content from URLs
 */
function botwriter_rewriter_fetch_ajax() {
    check_ajax_referer('botwriter_rewriter_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', (array) wp_unslash($_POST['urls'])) : array();

    if (empty($urls)) {
        wp_send_json_error(__('No URLs provided.', 'botwriter'));
    }

    if (count($urls) > 20) {
        wp_send_json_error(__('Maximum 20 URLs allowed.', 'botwriter'));
    }

    $articles = array();
    $errors   = array();

    foreach ($urls as $url) {
        if (empty($url)) {
            continue;
        }

        $result = botwriter_rewriter_extract_content($url);

        if (is_wp_error($result)) {
            $errors[] = array(
                'url'   => $url,
                'error' => $result->get_error_message(),
            );
        } else {
            $articles[] = $result;
        }
    }

    wp_send_json_success(array(
        'articles' => $articles,
        'errors'   => $errors,
    ));
}

/**
 * AJAX: Create a Super Task with the articles to rewrite.
 *
 * Inserts the task as an active super2 task and stores each article
 * in the botwriter_super table. The cron loop picks them up via
 * botwriter_super_prepare_event() just like normal Super Tasks.
 */
function botwriter_rewriter_create_task_ajax() {
    check_ajax_referer('botwriter_rewriter_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    // wp_unslash only — sanitize_text_field corrupts JSON (strips tags/newlines)
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded first; each title/content field is sanitized below before use.
    $articles_json  = isset($_POST['articles']) ? wp_unslash($_POST['articles']) : '';
    $rewrite_prompt = isset($_POST['rewrite_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['rewrite_prompt'])) : '';
    $category_id    = isset($_POST['category_id']) ? absint(wp_unslash($_POST['category_id'])) : 0;

    // Task properties from Step 3 form
    $post_status       = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'draft';
    $post_language     = isset($_POST['post_language']) ? sanitize_text_field(wp_unslash($_POST['post_language'])) : substr(get_locale(), 0, 2);
    $author_selection  = isset($_POST['author_selection']) ? sanitize_text_field(wp_unslash($_POST['author_selection'])) : strval(get_current_user_id());
    $post_length       = isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : '800';
    $custom_post_length = isset($_POST['custom_post_length']) ? sanitize_text_field(wp_unslash($_POST['custom_post_length'])) : '';
    $template_id       = isset($_POST['template_id']) && !empty($_POST['template_id']) ? absint(wp_unslash($_POST['template_id'])) : null;
    $disable_ai_images = isset($_POST['disable_ai_images']) ? absint(wp_unslash($_POST['disable_ai_images'])) : 0;
    $days              = isset($_POST['days']) ? sanitize_text_field(wp_unslash($_POST['days'])) : 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
    $times_per_day     = isset($_POST['times_per_day']) ? absint(wp_unslash($_POST['times_per_day'])) : 1;
    $task_name_custom  = isset($_POST['task_name']) ? sanitize_text_field(wp_unslash($_POST['task_name'])) : '';

    // Validate post_status
    if (!in_array($post_status, array('draft', 'publish'), true)) {
        $post_status = 'draft';
    }

    $articles = json_decode($articles_json, true);
    if (!is_array($articles) || empty($articles)) {
        wp_send_json_error(__('No articles provided.', 'botwriter'));
    }

    global $wpdb;
    $tasks_table = $wpdb->prefix . 'botwriter_tasks';
    $super_table = $wpdb->prefix . 'botwriter_super';

    // Build default rewrite prompt if user didn't provide one
    if (empty($rewrite_prompt)) {
        $rewrite_prompt = 'Rewrite this article completely in your own words while preserving all key information. Make it original, engaging, and well-structured.';
    }

    // Count valid articles (filter empties before inserting)
    $valid_articles = array();
    foreach ($articles as $article) {
        $title   = sanitize_text_field($article['title'] ?? '');
        $content = wp_kses_post($article['content'] ?? '');
        if (!empty($title) || !empty($content)) {
            $valid_articles[] = array('title' => $title, 'content' => $content);
        }
    }

    if (empty($valid_articles)) {
        wp_send_json_error(__('All articles are empty.', 'botwriter'));
    }

    $task_name = !empty($task_name_custom) ? $task_name_custom : 'Rewriter: ' . count($valid_articles) . ' articles - ' . wp_date('M j, Y H:i');

    // Use the days and times_per_day from the form
    $wpdb->insert($tasks_table, array(
        'task_name'      => $task_name,
        'post_status'    => $post_status,
        'writer'         => 'ai_cerebro',
        'narration'      => 'Descriptive',
        'custom_style'   => '',
        'post_language'  => $post_language,
        'post_length'    => $post_length,
        'custom_post_length' => $custom_post_length,
        'days'           => $days,
        'times_per_day'  => $times_per_day > 0 ? $times_per_day : 1,
        'status'         => 1,
        'website_type'   => 'super2',
        'task_type'      => 'rewriter',
        'domain_name'    => get_site_url(),
        'category_id'    => $category_id > 0 ? strval($category_id) : '',
        'title_prompt'   => '',
        'content_prompt' => $rewrite_prompt,
        'tags_prompt'    => '',
        'image_prompt'   => '',
        'aigenerated_title'   => '',
        'aigenerated_content' => '',
        'aigenerated_tags'    => '',
        'aigenerated_image'   => '',
        'ai_keywords'    => '',
        'author_selection' => $author_selection,
        'disable_ai_images' => $disable_ai_images,
        'template_id'    => $template_id,
    ));

    $task_id = $wpdb->insert_id;

    if (!$task_id) {
        wp_send_json_error(__('Failed to create task.', 'botwriter'));
    }

    // Insert each article into the super table.
    // Only the article content goes here — rewrite instructions stay in the task's content_prompt.
    // super_prepare_event() preserves the rewrite_prompt, and
    // botwriter_build_client_prompt() outputs it BEFORE the ENDARTICLE-wrapped content.
    $inserted = 0;
    foreach ($valid_articles as $art) {
        $wpdb->insert($super_table, array(
            'id_task' => $task_id,
            'id_log'  => 0,
            'title'   => $art['title'],
            'content' => $art['content'],
        ));
        // task_status left NULL — super_prepare_event picks rows with NULL/empty task_status
        $inserted++;
    }

    botwriter_log('Content Rewriter: Task created', [
        'task_id'       => $task_id,
        'article_count' => $inserted,
    ]);

    wp_send_json_success(array(
        'task_id'  => $task_id,
        'count'    => $inserted,
        'edit_url' => wp_nonce_url(admin_url('admin.php?page=botwriter_super_page&id=' . $task_id), 'botwriter_tasks_action'),
    ));
}

// ========================================
// SITE REWRITER AJAX HANDLERS
// ========================================
add_action('wp_ajax_botwriter_siterewriter_crawl', 'botwriter_siterewriter_crawl_ajax');
add_action('wp_ajax_botwriter_siterewriter_fetch', 'botwriter_siterewriter_fetch_ajax');
add_action('wp_ajax_botwriter_siterewriter_create_task', 'botwriter_siterewriter_create_task_ajax');

/**
 * AJAX: Crawl a single page — returns title + internal links.
 * The JS manages the BFS queue for live/progressive UI updates.
 */
function botwriter_siterewriter_crawl_ajax() {
    check_ajax_referer('botwriter_siterewriter_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    $url         = isset($_POST['url'])         ? esc_url_raw(wp_unslash($_POST['url']))                : '';
    $base_domain = isset($_POST['base_domain']) ? sanitize_text_field(wp_unslash($_POST['base_domain'])) : '';

    if (empty($url) || empty($base_domain)) {
        wp_send_json_error(__('Missing URL or domain.', 'botwriter'));
    }

    $result = botwriter_siterewriter_crawl_page($url, $base_domain);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success($result);
}

/**
 * AJAX: Fetch and extract content from selected URLs.
 * Reuses botwriter_rewriter_extract_content() for full content extraction.
 */
function botwriter_siterewriter_fetch_ajax() {
    check_ajax_referer('botwriter_siterewriter_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Long-running image batch operation; safe inside an admin AJAX handler.
    @set_time_limit(0);

    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', (array) wp_unslash($_POST['urls'])) : array();

    if (empty($urls)) {
        wp_send_json_error(__('No URLs provided.', 'botwriter'));
    }

    $articles = array();
    $errors   = array();

    foreach ($urls as $url) {
        if (empty($url)) continue;

        $result = botwriter_rewriter_extract_content($url);

        if (is_wp_error($result)) {
            $errors[] = array(
                'url'   => $url,
                'error' => $result->get_error_message(),
            );
        } else {
            $articles[] = $result;
        }
    }

    wp_send_json_success(array(
        'articles' => $articles,
        'errors'   => $errors,
    ));
}

/**
 * AJAX: Create a Super Task with the articles to rewrite.
 * Identical flow to Content Rewriter but with task_type = 'siterewriter'.
 */
function botwriter_siterewriter_create_task_ajax() {
    check_ajax_referer('botwriter_siterewriter_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'botwriter'));
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded first; each title/content field is sanitized below before use.
    $articles_json  = isset($_POST['articles'])       ? wp_unslash($_POST['articles'])                             : '';
    $rewrite_prompt = isset($_POST['rewrite_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['rewrite_prompt'])) : '';
    $category_id    = isset($_POST['category_id'])    ? absint(wp_unslash($_POST['category_id']))                   : 0;

    $post_status        = isset($_POST['post_status'])       ? sanitize_text_field(wp_unslash($_POST['post_status']))       : 'draft';
    $post_language      = isset($_POST['post_language'])     ? sanitize_text_field(wp_unslash($_POST['post_language']))     : substr(get_locale(), 0, 2);
    $author_selection   = isset($_POST['author_selection'])  ? sanitize_text_field(wp_unslash($_POST['author_selection']))  : strval(get_current_user_id());
    $post_length        = isset($_POST['post_length'])       ? sanitize_text_field(wp_unslash($_POST['post_length']))       : '800';
    $custom_post_length = isset($_POST['custom_post_length'])? sanitize_text_field(wp_unslash($_POST['custom_post_length'])): '';
    $template_id        = isset($_POST['template_id']) && !empty($_POST['template_id']) ? absint(wp_unslash($_POST['template_id'])) : null;
    $disable_ai_images  = isset($_POST['disable_ai_images']) ? absint(wp_unslash($_POST['disable_ai_images']))                  : 0;
    $days               = isset($_POST['days'])              ? sanitize_text_field(wp_unslash($_POST['days']))              : 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
    $times_per_day      = isset($_POST['times_per_day'])     ? absint(wp_unslash($_POST['times_per_day']))                  : 1;
    $task_name_custom   = isset($_POST['task_name'])         ? sanitize_text_field(wp_unslash($_POST['task_name']))         : '';

    if (!in_array($post_status, array('draft', 'publish'), true)) {
        $post_status = 'draft';
    }

    $articles = json_decode($articles_json, true);
    if (!is_array($articles) || empty($articles)) {
        wp_send_json_error(__('No articles provided.', 'botwriter'));
    }

    global $wpdb;
    $tasks_table = $wpdb->prefix . 'botwriter_tasks';
    $super_table = $wpdb->prefix . 'botwriter_super';

    if (empty($rewrite_prompt)) {
        $rewrite_prompt = 'Rewrite this article completely in your own words while preserving all key information. Make it original, engaging, and well-structured.';
    }

    $valid_articles = array();
    foreach ($articles as $article) {
        $title   = sanitize_text_field($article['title'] ?? '');
        $content = wp_kses_post($article['content'] ?? '');
        if (!empty($title) || !empty($content)) {
            $valid_articles[] = array('title' => $title, 'content' => $content);
        }
    }

    if (empty($valid_articles)) {
        wp_send_json_error(__('All articles are empty.', 'botwriter'));
    }

    $task_name = !empty($task_name_custom)
        ? $task_name_custom
        : 'Site Rewriter: ' . count($valid_articles) . ' articles - ' . wp_date('M j, Y H:i');

    $wpdb->insert($tasks_table, array(
        'task_name'      => $task_name,
        'post_status'    => $post_status,
        'writer'         => 'ai_cerebro',
        'narration'      => 'Descriptive',
        'custom_style'   => '',
        'post_language'  => $post_language,
        'post_length'    => $post_length,
        'custom_post_length' => $custom_post_length,
        'days'           => $days,
        'times_per_day'  => $times_per_day > 0 ? $times_per_day : 1,
        'status'         => 1,
        'website_type'   => 'super2',
        'task_type'      => 'siterewriter',
        'domain_name'    => get_site_url(),
        'category_id'    => $category_id > 0 ? strval($category_id) : '',
        'title_prompt'   => '',
        'content_prompt' => $rewrite_prompt,
        'tags_prompt'    => '',
        'image_prompt'   => '',
        'aigenerated_title'   => '',
        'aigenerated_content' => '',
        'aigenerated_tags'    => '',
        'aigenerated_image'   => '',
        'ai_keywords'    => '',
        'author_selection' => $author_selection,
        'disable_ai_images' => $disable_ai_images,
        'template_id'    => $template_id,
    ));

    $task_id = $wpdb->insert_id;

    if (!$task_id) {
        wp_send_json_error(__('Failed to create task.', 'botwriter'));
    }

    // Insert each article into the super table.
    // Only the article content goes here — rewrite instructions stay in the task's content_prompt.
    // super_prepare_event() preserves the rewrite_prompt, and
    // botwriter_build_client_prompt() outputs it BEFORE the ENDARTICLE-wrapped content.
    $inserted = 0;
    foreach ($valid_articles as $art) {
        $wpdb->insert($super_table, array(
            'id_task' => $task_id,
            'id_log'  => 0,
            'title'   => $art['title'],
            'content' => $art['content'],
        ));
        $inserted++;
    }

    botwriter_log('Site Rewriter: Task created', [
        'task_id'       => $task_id,
        'article_count' => $inserted,
    ]);

    wp_send_json_success(array(
        'task_id'  => $task_id,
        'count'    => $inserted,
        'edit_url' => wp_nonce_url(admin_url('admin.php?page=botwriter_super_page&id=' . $task_id), 'botwriter_tasks_action'),
    ));
}

?>
