<?php
/* 
Plugin Name: BotWriter
Plugin URI:  https://www.wpbotwriter.com
Description: Plugin for automatically generating posts using artificial intelligence. Create content from scratch with AI and generate custom images. Optimize content for SEO, including tags, titles, and image descriptions. Advanced features like ChatGPT, automatic content creation, image generation, SEO optimization, and AI training make this plugin a complete tool for writers and content creators.
Version: 2.2.0
Author: estebandezafra
Requires PHP: 7.0
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: botwriter
Domain Path: /languages
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
} 




if (!defined('BOTWRITER_VERSION')) {
    define('BOTWRITER_VERSION', '2.2.0');
}

// Plugin directory path (with trailing slash)
if (!defined('BOTWRITER_PLUGIN_DIR')) {
    define('BOTWRITER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

define('BOTWRITER_URL', plugin_dir_url(__FILE__));

define('BOTWRITER_API_URL', "https://wpbotwriter.com/public2/");




// Debugging constant for development
if (!defined('BOTWRITER_DEBUG')) {
    define('BOTWRITER_DEBUG', false);
}


if (!function_exists('botwriter_log')) {
    function botwriter_log($message, array $context = []) {
        $botwriter_debug = defined('BOTWRITER_DEBUG') && BOTWRITER_DEBUG === true;
        $wp_debug_log = defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        if (!$botwriter_debug && !$wp_debug_log) {
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

        error_log('[BotWriter] ' . $message);
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
            'faq'     => '<a href="https://wpbotwriter.com/faq-frequently-asked-questions/" target="_blank" rel="noopener">' . __('FAQ', 'botwriter') . '</a>',
            'support' => '<a href="https://wpbotwriter.com/support" target="_blank" rel="noopener">' . __('Support', 'botwriter') . '</a>',
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}


require plugin_dir_path( __FILE__ ) . 'includes/posts.php';
require plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/logs.php';
require plugin_dir_path( __FILE__ ) . 'includes/announcements.php';
require plugin_dir_path( __FILE__ ) . 'includes/super.php';
require plugin_dir_path( __FILE__ ) . 'includes/addnew.php';
require plugin_dir_path( __FILE__ ) . 'includes/quickpost.php';
require plugin_dir_path( __FILE__ ) . 'includes/rewriter.php';
require plugin_dir_path( __FILE__ ) . 'includes/siterewriter.php';
require plugin_dir_path( __FILE__ ) . 'includes/templates.php';
require plugin_dir_path( __FILE__ ) . 'includes/default-templates.php';

// Load Action Scheduler for self-hosted direct mode
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-action-scheduler-loader.php';
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-direct-database.php';
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-direct-content-sources.php';
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-direct-text-generator.php';
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-direct-image-generator.php';
require plugin_dir_path( __FILE__ ) . 'includes/direct-mode/class-direct-task-runner.php';

// Initialize Action Scheduler immediately (don't wait for plugins_loaded)
BotWriter_Action_Scheduler_Loader::init();




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


    if (strpos((string)$slug, 'botwriter') !== false) {
        wp_enqueue_script('botwriter-dismiss-script', $my_plugin_dir .  '/assets/js/botwriter_dismiss.js', array('jquery'), null, true);    
        wp_localize_script('botwriter-dismiss-script','botwriterData',
            array(
                'nonce'   => wp_create_nonce('botwriter_dismiss_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

						
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
            'error_delete' => __('Error deleting log. Please try again.', 'botwriter'),
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
                'custom_image_warning_title' => __('Custom Provider Mode', 'botwriter'),
                'custom_image_warning_text' => __('When using Custom Provider for text, image generation is limited to Custom Provider or None. Cloud image providers (DALL-E, Gemini, etc.) are not available in this mode.', 'botwriter'),
            )
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

    

}
add_action('admin_enqueue_scripts','botwriter_enqueue_scripts');



if (!function_exists('deactivate_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}


function botwriter_enqueue_styles(){
    $my_plugin_dir = plugin_dir_url(__FILE__);
    $screen = get_current_screen();

    $slug = $screen->id;

    // Welcome banner CSS - load on ALL admin pages if not dismissed
    // (because admin_notices shows on all pages)
    $welcome_dismissed = get_option('botwriter_welcome_dismissed', false);
    if (!$welcome_dismissed) {
        wp_register_style('botwriter_welcome_banner', $my_plugin_dir . 'assets/css/welcome-banner.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/welcome-banner.css'));
        wp_enqueue_style('botwriter_welcome_banner');
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

        // Admin menu styles (hide duplicate submenus)
        wp_register_style('botwriter_admin_menu', $my_plugin_dir . 'assets/css/admin-menu.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin-menu.css'));
        wp_enqueue_style('botwriter_admin_menu');

        // Settings page specific styles
        if (strpos((string)$slug, 'botwriter_settings') !== false) {
            wp_register_style('botwriter_settings', $my_plugin_dir . 'assets/css/settings.css', array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/settings.css'));
            wp_enqueue_style('botwriter_settings');
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
                    <span style="margin-right: 10px;">🤖</span><?php echo esc_html__('BotWriter', 'botwriter'); ?>
                </h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.95;">
                    <?php echo esc_html__('AI-Powered Content Creation for WordPress', 'botwriter'); ?>
                </p>
            </div>

            <!-- Quick Start Alert -->
            <?php if (!$has_api_key): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 0 8px 8px 0; margin-bottom: 25px;">
                <strong style="color: #856404;">⚡ <?php echo esc_html__('Quick Start:', 'botwriter'); ?></strong>
                <span style="color: #856404;">
                    <?php echo esc_html__('Configure your AI provider API key to get started.', 'botwriter'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>" style="color: #856404; font-weight: 600;"><?php echo esc_html__('Go to Settings →', 'botwriter'); ?></a>
                </span>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <p style="font-size: 15px; line-height: 1.7; color: #444; margin: 0;">
                    <?php echo esc_html__('BotWriter automates content creation using the latest AI models. Connect your preferred AI provider, configure your content sources, and let BotWriter generate SEO-optimized articles with AI-generated images—completely hands-free.', 'botwriter'); ?>
                </p>
            </div>

            <!-- Features Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px;">
                
                <!-- Text AI Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="font-size: 24px; margin-bottom: 12px;">✍️</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Multi-Provider Text AI', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Choose from OpenAI (GPT-4o), Anthropic (Claude), Google (Gemini), Mistral, Groq, or OpenRouter. Use your own API keys.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Image AI Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="font-size: 24px; margin-bottom: 12px;">🎨</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('AI Image Generation', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Generate featured images with DALL-E, Stable Diffusion, Flux, Recraft, and more via Replicate, Stability AI, or Fal.ai.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Content Sources Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="font-size: 24px; margin-bottom: 12px;">📡</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Multiple Content Sources', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Import and rewrite content from any WordPress site, RSS feed, or news API. Prevent duplicates automatically.', 'botwriter'); ?>
                    </p>
                </div>

                <!-- Automation Card -->
                <div style="background: white; padding: 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="font-size: 24px; margin-bottom: 12px;">⚙️</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;"><?php echo esc_html__('Full Automation', 'botwriter'); ?></h3>
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        <?php echo esc_html__('Schedule unlimited tasks, set publishing frequency, and let BotWriter work 24/7. Monitor everything from the Logs.', 'botwriter'); ?>
                    </p>
                </div>

            </div>

            <!-- Getting Started Steps -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; color: #333;">
                    🚀 <?php echo esc_html__('Getting Started', 'botwriter'); ?>
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: <?php echo $has_api_key ? '#28a745' : '#667eea'; ?>; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">
                            <?php echo $has_api_key ? '✓' : '1'; ?>
                        </div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Configure your AI Provider', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Add your API key from OpenAI, Anthropic (Claude), Google (Gemini), Mistral, Groq, or OpenRouter.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($settings_url); ?>"><?php echo esc_html__('Settings →', 'botwriter'); ?></a>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">2</div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Create Your First Task', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Define your content source, AI prompts, categories, and publishing schedule.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($addnew_url); ?>"><?php echo esc_html__('Add New →', 'botwriter'); ?></a>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0;">3</div>
                        <div>
                            <strong style="color: #333;"><?php echo esc_html__('Activate and Monitor', 'botwriter'); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <?php echo esc_html__('Enable your tasks and watch BotWriter generate posts automatically. Check the Logs for status updates.', 'botwriter'); ?>
                                <a href="<?php echo esc_url($logs_url); ?>"><?php echo esc_html__('Logs →', 'botwriter'); ?></a>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Quick Links -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                <a href="<?php echo esc_url($settings_url); ?>" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    <div style="font-size: 20px; margin-bottom: 6px;">⚙️</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Settings', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($addnew_url); ?>" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    <div style="font-size: 20px; margin-bottom: 6px;">➕</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Add New', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($tasks_url); ?>" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    <div style="font-size: 20px; margin-bottom: 6px;">📋</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Tasks', 'botwriter'); ?></div>
                </a>
                <a href="<?php echo esc_url($logs_url); ?>" style="background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    <div style="font-size: 20px; margin-bottom: 6px;">📊</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Logs', 'botwriter'); ?></div>
                </a>
                <a href="https://wpbotwriter.com/faq-frequently-asked-questions/" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: white; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(102, 126, 234, 0.3)';">
                    <div style="font-size: 20px; margin-bottom: 6px;">❓</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html__('Help & FAQ', 'botwriter'); ?></div>
                </a>
            </div>

            <!-- Footer -->
            <div style="text-align: center; margin-top: 30px; padding: 15px; color: #888; font-size: 12px;">
                <?php echo esc_html__('BotWriter', 'botwriter'); ?> v<?php echo esc_html(BOTWRITER_VERSION); ?> — 100% Free
                <br>
                <a href="https://www.wpbotwriter.com" target="_blank" style="color: #667eea; text-decoration: none;"><?php echo esc_html__('Website', 'botwriter'); ?></a>
                &nbsp;•&nbsp;
                <a href="https://wpbotwriter.com/faq-frequently-asked-questions/" target="_blank" style="color: #667eea; text-decoration: none;">FAQ</a>
                &nbsp;•&nbsp;
                <a href="https://wpbotwriter.com/support" target="_blank" style="color: #667eea; text-decoration: none;"><?php echo esc_html__('Support', 'botwriter'); ?></a>
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
    $site_url = get_site_url();
    $admin_email = get_option('admin_email');
    
    $remote_url = BOTWRITER_API_URL . 'activation.php';
    
    //options
    $api_key = get_option('botwriter_api_key');    
    if ($api_key) {        
        return;
    }

    if (get_option('botwriter_paused_tasks') === false) {
        update_option('botwriter_paused_tasks', "2");
    }
    
    if (get_option('botwriter_email') === false) {
        update_option('botwriter_email', get_option('admin_email'));
    }

    if (get_option('botwriter_email_confirmed') === false) {
        update_option('botwriter_email_confirmed', '0');
    }

    if (get_option('botwriter_cron_active') === false) {
        update_option('botwriter_cron_active', '1');
    }
    
    if (get_option('botwriter_ai_image_size') === false) {
        update_option('botwriter_ai_image_size', 'square');
    }
    
    if (get_option('botwriter_sslverify') === false) {
        update_option('botwriter_sslverify', 'yes');
    }

    if (get_option('botwriter_openai_model') === false) {
        update_option('botwriter_openai_model', 'gpt-5-mini');
    }
    if (get_option('botwriter_ai_image_quality') === false) {
        update_option('botwriter_ai_image_quality', 'medium');
    }

    $data = array(
        'user_domainname' => $site_url,
        'email_blog' => $admin_email,
    );

    $ssl_verify = get_option('botwriter_sslverify');
    if ($ssl_verify === 'no') {
        $ssl_verify = false;
    } else {
        $ssl_verify = true;
    }   
    
    $challenge_response = wp_remote_post($remote_url, array(
        'method'    => 'POST',
        'body'      => $data,
        'timeout'   => 45,
        'headers'   => array(),
        'sslverify' => $ssl_verify, 
    ));

    
    
    if (is_wp_error($challenge_response)) {
        $error_message = $challenge_response->get_error_message();
        //error_log("Error sending data to $remote_url: $error_message");
        return;
    }

    $challenge_body = wp_remote_retrieve_body($challenge_response);
    $challenge_result = json_decode($challenge_body, true);

    
    if (!isset($challenge_result['status']) || $challenge_result['status'] !== 'success' || !isset($challenge_result['challenge'])) {
        //error_log('Invalid challenge response from server.');
        return;
    }

    $challenge = $challenge_result['challenge'];

    
    $secret_key = '1c7b2be420b05ec389c6b7fd59ec5d7db0e457425a81fc88312dee66f3c2c663'; 
    $challenge_response_hash = hash_hmac('sha256', $challenge, $secret_key);

    
    $response_data = array(
        'user_domainname' => $site_url,
        'email_blog' => $admin_email,
        'challenge_response' => $challenge_response_hash,
    );

    $final_response = wp_remote_post($remote_url, array(
        'method'    => 'POST', 
        'body'      => $response_data,
        'timeout'   => 45,
        'headers'   => array(),
        'sslverify' => $ssl_verify, 
    ));


    if (is_wp_error($final_response)) {
        $error_message = $final_response->get_error_message();
        //error_log("Error sending challenge response to $remote_url: $error_message");
    } else {        
        $body = wp_remote_retrieve_body($final_response);
        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 'success' && isset($result['api_key'])) {            
            update_option('botwriter_api_key', sanitize_text_field($result['api_key']));
            //error_log('API Key received and stored successfully.');            
            
        } else {
            //error_log('API Key not received or invalid response.');
        }    
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

        // Create/update direct mode table if class exists
        if (class_exists('BotWriter_Direct_Database')) {
            BotWriter_Direct_Database::create_table();
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

        $table = $wpdb->prefix . 'botwriter_tasks';

        if ('delete_all' === $this->current_action() || ('delete' === $this->current_action() && isset($_REQUEST['id']))) {
            $request_id = isset($_REQUEST['id']) ? array_map('absint', (array) $_REQUEST['id']) : array();

            if (!empty($request_id)) {
                // Prepare the DELETE query with proper escaping
                $placeholders = implode(',', array_fill(0, count($request_id), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN({$placeholders})", $request_id));
            }
        }
    }

    

      // Get table data
      private function get_table_data( $search = '' ) {
        global $wpdb;
    
        $table = $wpdb->prefix."botwriter_tasks";
        
    
                if ( ! empty( $search ) ) {
            $prepared_search = $wpdb->esc_like( $search );
            $prepared_search = '%' . $wpdb->esc_like( $search ) . '%';
    
            return $wpdb->get_results(
                $wpdb->prepare(
                                        "SELECT * FROM {$table} WHERE name LIKE %s AND (task_type IS NULL OR task_type <> %s)",
                                        $prepared_search,
                                        'writenow'
                ),
                ARRAY_A
            );
                } else {         
                    return $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$table} WHERE (task_type IS NULL OR task_type <> %s)",
                            'writenow'
                        ),
                        ARRAY_A
                    );                            
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
          $sanitized_orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';

          $orderby = (!empty($sanitized_orderby)) ? $sanitized_orderby : 'task_name';
  
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

    // Check if cron is active
    $cron_active = get_option('botwriter_cron_active');
    botwriter_log('=== CRON START ===', [
        'cron_active_option' => $cron_active,
        'timestamp' => current_time('Y-m-d H:i:s'),
    ]);
    
    if ($cron_active !== '1') {
        botwriter_log('CRON DISABLED - exiting', ['cron_active' => $cron_active]);
        return;
    }

    // STOPFORMANY: refuse to dispatch new tasks while the flag is active
    if (get_option('botwriter_stopformany', false)) {
        botwriter_log('CRON BLOCKED by STOPFORMANY — too many consecutive errors on server');
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
                botwriter_send1_data_to_server((array) $event);                                      
                // Update execution count in the database and last_execution_time
                $current_time = current_time('Y-m-d H:i:s'); // Usar hora local de WordPress
                $wpdb->update($table_name_tasks, ['execution_count' => $task["execution_count"] + 1, 'last_execution_time' => $current_time], ['id' => $task["id"]]);            
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
}

function botwriter_execute_events_pass2(){  
    
    // check if the task is still in queue or finished
    global $wpdb;    
    $table_name_logs = $wpdb->prefix . 'botwriter_logs';
    // INQUEUE
    $events2 = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name_logs} WHERE task_status=%s", 'inqueue'));    
    botwriter_log('Phase 2 queue check', ['inqueue_count' => count($events2)]);
    foreach ($events2 as $event) {
        $event = (array) $event;
        // Execute the event        
        botwriter_send2_data_to_server( (array) $event);                
    } // end INQUEUE


    //IN ERROR, depending on the attempt, it is resent later or marked as finished
    // Exclude 'writenow' tasks from automatic retries - they should only be retried manually
    $events1 = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name_logs} WHERE task_status=%s AND intentosfase1 < %d AND (task_type IS NULL OR task_type <> 'writenow')", 'error', 8));
    botwriter_log('Phase 1 retries fetched', ['error_count' => count($events1)]);
    $intento_tiempo = array(0=>0,1=>0,2=>5,3=>10,4=>30,5=>60,6=>120,7=>240,8=>480); // minutos    
    foreach ($events1 as $event) {
        $event = (array) $event;
        // Execute the event if the time has passed 
        $intentosfase1 = $event["intentosfase1"];
        $tiempo = $intento_tiempo[$intentosfase1+1];
        $created_at = strtotime($event["created_at"]);        
        $now = current_time('timestamp');

        $diff = $now - $created_at;       
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
        botwriter_attach_image_to_post($post_id, $data['aigenerated_image'], $data['aigenerated_title'], $translated_image_slug);
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

    // Generate SEO meta description using AI (unless disabled)
    if ( function_exists( 'botwriter_generate_seo_meta' ) && get_option( 'botwriter_meta_disabled', '0' ) !== '1' ) {
        $post_language = $data['post_language'] ?? '';
        $meta_description = botwriter_generate_seo_meta(
            $data['aigenerated_title'],
            $data['aigenerated_content'],
            $post_language
        );
        if ( $meta_description ) {
            botwriter_apply_seo_meta( $post_id, $meta_description );
        }
    }

    return $post_id;
}
 
 
 
 
// Function to send data to the server pass1
function botwriter_send1_data_to_server($data) {
    
    // ========================================
    // CHECK FOR DIRECT MODE (CUSTOM PROVIDER)
    // ========================================
    // If text_provider is 'custom', automatically enable direct mode
    $text_provider = get_option('botwriter_text_provider', 'openai');
    $custom_text_url = get_option('botwriter_custom_text_url', '');
    
    if ($text_provider === 'custom' && !empty($custom_text_url) && class_exists('BotWriter_Action_Scheduler_Loader')) {
        return botwriter_direct_mode_dispatch($data);
    }
    // ========================================
        
    Global $botwriter_version;
    $remote_url = BOTWRITER_API_URL . 'redis_api_cola.php';
    
    // Use constant to avoid get_plugin_data() and early translation loading
    $botwriter_version = BOTWRITER_VERSION;

    // settings
    $data['version'] = $botwriter_version;
    $data['api_key'] = get_option('botwriter_api_key');    // la api_key del programa
    $data["user_domainname"] = esc_url(get_site_url()); 
    $data["ai_image_size"]=get_option('botwriter_ai_image_size');
    $data["ai_image_quality"]=get_option('botwriter_ai_image_quality');
    $data["ai_image_style"]=get_option('botwriter_ai_image_style', 'realistic');
    $data["ai_image_style_custom"]=get_option('botwriter_ai_image_style_custom', '');
    
    // Use task-specific setting for disable_ai_images (already in $data from task/log)
    // If not present, default to 0 (images enabled)
    if (!isset($data["disable_ai_images"])) {
        $data["disable_ai_images"] = 0;
    }
    
    // Provider selections
    $data['text_provider'] = get_option('botwriter_text_provider', 'openai');
    $data['image_provider'] = get_option('botwriter_image_provider', 'dalle');
    
    // Get current text model based on provider
    $text_provider = $data['text_provider'];
    $text_model_defaults = [
        'openai' => 'gpt-5-mini',
        'anthropic' => 'claude-sonnet-4-5-20250929',
        'google' => 'gemini-2.5-flash',
        'mistral' => 'mistral-large-latest',
        'groq' => 'llama-3.3-70b-versatile',
        'openrouter' => 'anthropic/claude-sonnet-4',
    ];
    $data['text_model'] = get_option("botwriter_{$text_provider}_model", $text_model_defaults[$text_provider] ?? 'gpt-5-mini');
    
    // Get current image model based on provider
    $image_provider = $data['image_provider'];
    $image_model_defaults = [
        'dalle' => 'gpt-image-1',
        'gemini' => 'gemini-2.5-flash-image',
        'fal' => 'fal-ai/flux-pro/v1.1',
        'replicate' => 'black-forest-labs/flux-1.1-pro',
        'stability' => 'sd3.5-large-turbo',
        'cloudflare' => 'flux-1-schnell',
    ];
    // Gemini uses a different option name (gemini_image_model instead of gemini_model to avoid conflict with text)
    $image_model_option = ($image_provider === 'gemini') ? 'botwriter_gemini_image_model' : "botwriter_{$image_provider}_model";
    $data['image_model'] = get_option($image_model_option, $image_model_defaults[$image_provider] ?? 'gpt-image-1');
    
    // Also send the specific field name the server expects
    if ($image_provider === 'gemini') {
        $data['gemini_image_model'] = $data['image_model'];
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
            botwriter_logs_register($data, $data['id']);
            return false;
        }

        // Populate data with the pre-fetched article so the prompt builder can use it
        $data['source_title']       = $rss_result['source_title'];
        $data['source_content']     = $rss_result['source_content'];
        $data['link_post_original'] = $rss_result['link_original'];
        $data['source_prefetched']  = '1';

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
            botwriter_logs_register($data, $data['id']);
            return false;
        }

        $data['source_title']       = $wp_result['source_title'];
        $data['source_content']     = $wp_result['source_content'];
        $data['link_post_original'] = $wp_result['link_original'];
        $data['source_prefetched']  = '1';

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
    ]);

    $data["error"]= "";

    $ssl_verify = get_option('botwriter_sslverify');


    if ($ssl_verify === 'no') {
        $ssl_verify = false;
    } else {
        $ssl_verify = true;
    }   



    $response = wp_remote_post($remote_url, array(
        'method'    => 'POST',
        'body'      => $data,
        'timeout'   => 45,
        'headers'   => array(),
        'sslverify' => $ssl_verify, 
    ));

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
                botwriter_logs_register($data, $data["id"]);             
                botwriter_log('Phase 1 request accepted', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $result['id_task_server'],
                ]);
                return $result['id_task_server'];                 
            } else { // error                
                $data["task_status"]="error";
                botwriter_logs_register($data, $data["id"]);                
                botwriter_log('Phase 1 request rejected', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
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

/**
 * Dispatch task to direct mode (self-hosted processing)
 * 
 * Uses Action Scheduler to process tasks directly without going through botwriter.com
 * 
 * @since 2.1.0
 * @param array $data Task/log data
 * @return mixed Task ID on success, false on failure
 */
function botwriter_direct_mode_dispatch($data) {
    // Ensure Action Scheduler is available
    if (!BotWriter_Action_Scheduler_Loader::is_available()) {
        botwriter_log('Direct mode: Action Scheduler not available', [], 'error');
        $data['task_status'] = 'error';
        $data['error'] = 'Action Scheduler library is not available. Please reinstall the plugin.';
        botwriter_logs_register($data, $data['id']);
        return false;
    }
    
    // Build prompt from template if not super1
    $website_type = $data['website_type'] ?? '';
    if ($website_type !== 'super1' && empty($data['client_prompt'])) {
        $data['client_prompt'] = botwriter_build_client_prompt($data);
    }
    
    // Add custom text provider settings to data
    $data['selfhosted_url'] = get_option('botwriter_custom_text_url', '');
    $data['selfhosted_model'] = get_option('botwriter_custom_text_model', '');
    $data['selfhosted_api_key'] = botwriter_decrypt_api_key(get_option('botwriter_custom_text_api_key', ''));
    $data['selfhosted_timeout'] = intval(get_option('botwriter_custom_text_timeout', 400));
    
    // Check if image provider is also custom
    $image_provider = get_option('botwriter_image_provider', 'dalle');
    if ($image_provider === 'custom') {
        $data['selfhosted_image_url'] = get_option('botwriter_custom_image_url', '');
        $data['selfhosted_image_type'] = get_option('botwriter_custom_image_type', 'openai');
        $data['selfhosted_image_model'] = get_option('botwriter_custom_image_model', '');
        $data['selfhosted_image_api_key'] = ''; // Local servers typically don't need auth
        $data['selfhosted_image_timeout'] = intval(get_option('botwriter_custom_image_timeout', 400));
        
        // Provider-specific settings for Automatic1111/ComfyUI
        $data['negative_prompt'] = get_option('botwriter_negative_prompt', 'blurry, low quality, distorted, deformed');
        $data['a1111_steps'] = get_option('botwriter_a1111_steps', 20);
        $data['a1111_cfg_scale'] = get_option('botwriter_a1111_cfg_scale', 7.0);
        $data['a1111_sampler'] = get_option('botwriter_a1111_sampler', 'DPM++ 2M Karras');
        $data['comfyui_workflow'] = get_option('botwriter_comfyui_workflow', '');
    }
    
    // Add common settings
    $data['ai_image_size'] = get_option('botwriter_ai_image_size', '1024x1024');
    $data['ai_image_quality'] = get_option('botwriter_ai_image_quality', 'standard');
    
    // Update task status
    $current_time = gmdate('Y-m-d H:i:s', current_time('timestamp'));
    $data['last_execution_time'] = $current_time;
    $data['task_status'] = 'direct_queued';
    $data['intentosfase1'] = isset($data['intentosfase1']) ? $data['intentosfase1'] + 1 : 1;
    
    // Save to logs table (standard BotWriter flow)
    botwriter_logs_register($data, $data['id']);
    
    // Also create a record in the direct tasks table for detailed tracking
    if (class_exists('BotWriter_Direct_Database')) {
        $direct_task_id = BotWriter_Direct_Database::create_from_log($data['id'], $data);
        if ($direct_task_id) {
            $data['direct_task_id'] = $direct_task_id;
            botwriter_log('Direct mode: Created direct task record', [
                'direct_task_id' => $direct_task_id,
                'log_id' => $data['id'],
            ]);
        }
    }
    
    // Enqueue for Action Scheduler processing
    $result = BotWriter_Action_Scheduler_Loader::enqueue_direct_task($data['id'], $data);
    
    if ($result) {
        botwriter_log('Direct mode: Task enqueued', [
            'log_id' => $data['id'],
            'task_id' => $data['id_task'] ?? null,
            'action_id' => $result,
        ]);
        
        // Return the log ID as a "server ID" equivalent
        return $data['id'];
    } else {
        botwriter_log('Direct mode: Failed to enqueue task', [
            'log_id' => $data['id'],
        ], 'error');
        
        $data['task_status'] = 'error';
        $data['error'] = 'Failed to enqueue task for direct processing.';
        botwriter_logs_register($data, $data['id']);
        return false;
    }
}

/**
 * Check status of a direct mode task (Custom Provider via Action Scheduler)
 * Simulates the server's phase 2 response behavior
 * 
 * @since 2.1.0
 * @param array $data Task/log data
 * @return mixed Result array on completion, false if still processing
 */
function botwriter_direct_mode_check_status($data) {
    global $wpdb;
    
    $log_id = $data['id'] ?? 0;
    if (!$log_id) {
        return false;
    }
    
    // Reload the log to get the latest status (Action Scheduler may have updated it)
    $log = botwriter_logs_get($log_id);
    if (!$log) {
        return false;
    }
    
    $task_status = $log['task_status'] ?? '';
    
    botwriter_log('Direct mode: Checking status', [
        'log_id' => $log_id,
        'task_status' => $task_status,
    ]);
    
    // If completed, the Action Scheduler has already created the post
    if ($task_status === 'completed') {
        botwriter_log('Direct mode: Task completed', [
            'log_id' => $log_id,
            'post_id' => $log['id_post_published'] ?? 0,
        ]);
        
        // Return success - post was already created by Action Scheduler
        return [
            'success' => true,
            'task_status' => 'completed',
            'id_post_published' => $log['id_post_published'] ?? 0,
        ];
    }
    
    // If error, return the error
    if ($task_status === 'error') {
        botwriter_log('Direct mode: Task error', [
            'log_id' => $log_id,
            'error' => $log['error'] ?? 'Unknown error',
        ]);
        return false;
    }
    
    // Still processing (direct_queued or processing)
    // Check if Action Scheduler action is still pending/running
    if (function_exists('as_get_scheduled_actions')) {
        $actions = as_get_scheduled_actions([
            'hook' => 'botwriter_direct_process_task',
            'status' => ActionScheduler_Store::STATUS_PENDING,
            'args' => ['log_id' => $log_id],
            'per_page' => 1,
        ]);
        
        if (!empty($actions)) {
            // Action is still pending, update status to show progress
            if ($task_status === 'direct_queued') {
                $data['task_status'] = 'processing';
                botwriter_logs_register($data, $log_id);
            }
            botwriter_log('Direct mode: Still processing', [
                'log_id' => $log_id,
            ]);
            return false;
        }
        
        // Check running actions
        $running_actions = as_get_scheduled_actions([
            'hook' => 'botwriter_direct_process_task',
            'status' => ActionScheduler_Store::STATUS_RUNNING,
            'args' => ['log_id' => $log_id],
            'per_page' => 1,
        ]);
        
        if (!empty($running_actions)) {
            if ($task_status !== 'processing') {
                $data['task_status'] = 'processing';
                botwriter_logs_register($data, $log_id);
            }
            botwriter_log('Direct mode: Currently running', [
                'log_id' => $log_id,
            ]);
            return false;
        }
    }
    
    // Action not found and not completed/error - might have failed silently
    // Check how long it's been since last execution
    $last_execution = strtotime($log['last_execution_time'] ?? '');
    $now = current_time('timestamp');
    $elapsed = $now - $last_execution;
    
    // If more than 10 minutes have passed, mark as error
    if ($elapsed > 600) {
        $data['task_status'] = 'error';
        $data['error'] = 'Task processing timed out. Please check Action Scheduler status.';
        botwriter_logs_register($data, $log_id);
        botwriter_log('Direct mode: Timeout', [
            'log_id' => $log_id,
            'elapsed' => $elapsed,
        ]);
        return false;
    }
    
    // Still waiting
    return false;
}

// Function to send data to the server pass2
function botwriter_send2_data_to_server($data) {  
    Global $wpdb;    
    
    // ========================================
    // CHECK FOR DIRECT MODE (CUSTOM PROVIDER)
    // ========================================
    // If task_status is 'direct_queued' or 'processing', check Action Scheduler status
    $task_status = $data['task_status'] ?? '';
    if (in_array($task_status, ['direct_queued', 'processing'], true)) {
        return botwriter_direct_mode_check_status($data);
    }
    // ========================================
    
    $data['api_key'] = get_option('botwriter_api_key');
    $data["user_domainname"] = esc_url(get_site_url());

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

    $response = wp_remote_post($remote_url, array(
        'method'    => 'POST',
        'body'      => $data,
        'timeout'   => 45,
        'headers'   => array(),
        'sslverify' => $ssl_verify
    ));

    
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
        
            //echo 'Data recive: <pre>' . print_r($result, true) . '</pre>'; 
            
            // results errors
            if (isset($result["task_status"]) && $result["task_status"] == "error") {
                $data["task_status"]="error";
                $data["error"]=$result["error"];                                
                botwriter_log('Phase 2 reported error', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $data['id_task_server'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
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
                botwriter_logs_register($result, $data["id"]);                          
                botwriter_log('Phase 2 completed event', [
                    'log_id' => $data['id'] ?? null,
                    'task_id' => $data['id_task'] ?? null,
                    'id_task_server' => $data['id_task_server'] ?? null,
                ]);
                
                $result=botwriter_logs_get($data["id"]);  // merge the result with the log

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


function botwriter_attach_image_to_post($post_id, $image_url, $post_title, $translated_image_slug = '') {
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
        $content_prompt = sanitize_text_field(wp_unslash($_POST['custom_prompt']));
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
    
    if (!isset($_POST['manual_titles']) || empty(trim(wp_unslash($_POST['manual_titles'])))) {
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

    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', $_POST['urls']) : array();

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
    $articles_json  = isset($_POST['articles']) ? wp_unslash($_POST['articles']) : '';
    $rewrite_prompt = isset($_POST['rewrite_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['rewrite_prompt'])) : '';
    $category_id    = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    // Task properties from Step 3 form
    $post_status       = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'draft';
    $post_language     = isset($_POST['post_language']) ? sanitize_text_field(wp_unslash($_POST['post_language'])) : substr(get_locale(), 0, 2);
    $author_selection  = isset($_POST['author_selection']) ? sanitize_text_field(wp_unslash($_POST['author_selection'])) : strval(get_current_user_id());
    $post_length       = isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : '800';
    $custom_post_length = isset($_POST['custom_post_length']) ? sanitize_text_field(wp_unslash($_POST['custom_post_length'])) : '';
    $template_id       = isset($_POST['template_id']) && !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
    $disable_ai_images = isset($_POST['disable_ai_images']) ? intval($_POST['disable_ai_images']) : 0;
    $days              = isset($_POST['days']) ? sanitize_text_field(wp_unslash($_POST['days'])) : 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
    $times_per_day     = isset($_POST['times_per_day']) ? intval($_POST['times_per_day']) : 1;
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

    @set_time_limit(0);

    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', $_POST['urls']) : array();

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

    $articles_json  = isset($_POST['articles'])       ? wp_unslash($_POST['articles'])                             : '';
    $rewrite_prompt = isset($_POST['rewrite_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['rewrite_prompt'])) : '';
    $category_id    = isset($_POST['category_id'])    ? intval($_POST['category_id'])                               : 0;

    $post_status        = isset($_POST['post_status'])       ? sanitize_text_field(wp_unslash($_POST['post_status']))       : 'draft';
    $post_language      = isset($_POST['post_language'])     ? sanitize_text_field(wp_unslash($_POST['post_language']))     : substr(get_locale(), 0, 2);
    $author_selection   = isset($_POST['author_selection'])  ? sanitize_text_field(wp_unslash($_POST['author_selection']))  : strval(get_current_user_id());
    $post_length        = isset($_POST['post_length'])       ? sanitize_text_field(wp_unslash($_POST['post_length']))       : '800';
    $custom_post_length = isset($_POST['custom_post_length'])? sanitize_text_field(wp_unslash($_POST['custom_post_length'])): '';
    $template_id        = isset($_POST['template_id']) && !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
    $disable_ai_images  = isset($_POST['disable_ai_images']) ? intval($_POST['disable_ai_images'])                          : 0;
    $days               = isset($_POST['days'])              ? sanitize_text_field(wp_unslash($_POST['days']))              : 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
    $times_per_day      = isset($_POST['times_per_day'])     ? intval($_POST['times_per_day'])                              : 1;
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
