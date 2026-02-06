<?php
/**
 * Action Scheduler Loader for BotWriter
 * 
 * Handles loading Action Scheduler library, checking if it's already loaded
 * by another plugin (like WooCommerce) to avoid conflicts.
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Action_Scheduler_Loader
 * 
 * Manages Action Scheduler initialization for BotWriter's direct mode processing.
 */
class BotWriter_Action_Scheduler_Loader {
    
    /**
     * Minimum required version of Action Scheduler
     */
    const MIN_VERSION = '3.5.0';
    
    /**
     * Version bundled with BotWriter
     */
    const BUNDLED_VERSION = '3.9.2';
    
    /**
     * Whether Action Scheduler was loaded by this plugin
     */
    private static $loaded_by_botwriter = false;
    
    /**
     * Initialize the Action Scheduler loader
     */
    public static function init() {
        // Load Action Scheduler immediately if not already loaded
        self::maybe_load_action_scheduler();
        
        // Register our custom hooks after Action Scheduler is initialized
        add_action('action_scheduler_init', [__CLASS__, 'register_botwriter_hooks']);
        
        // Add admin notice if version is too old
        add_action('admin_notices', [__CLASS__, 'maybe_show_version_notice']);
    }
    
    /**
     * Load Action Scheduler if not already loaded by another plugin
     */
    public static function maybe_load_action_scheduler() {
        // Check if Action Scheduler is already loaded (e.g., by WooCommerce)
        if (class_exists('ActionScheduler_Versions')) {
            // Already loaded, check version
            self::$loaded_by_botwriter = false;
            botwriter_log('Action Scheduler already loaded by another plugin', [
                'external_load' => true,
            ]);
            return;
        }
        
        // Check if the function already exists (older versions)
        if (function_exists('as_enqueue_async_action')) {
            self::$loaded_by_botwriter = false;
            botwriter_log('Action Scheduler functions already available', [
                'external_load' => true,
            ]);
            return;
        }
        
        // Load our bundled version
        $action_scheduler_file = BOTWRITER_PLUGIN_DIR . 'libraries/action-scheduler/action-scheduler.php';
        
        if (file_exists($action_scheduler_file)) {
            require_once $action_scheduler_file;
            self::$loaded_by_botwriter = true;
            botwriter_log('Action Scheduler loaded by BotWriter', [
                'version' => self::BUNDLED_VERSION,
            ]);
        } else {
            botwriter_log('Action Scheduler library not found', [
                'expected_path' => $action_scheduler_file,
            ], 'error');
        }
    }
    
    /**
     * Register BotWriter hooks with Action Scheduler
     */
    public static function register_botwriter_hooks() {
        // Hook for processing direct text generation
        add_action('botwriter_direct_generate_text', [__CLASS__, 'handle_direct_text_generation'], 10, 1);
        
        // Hook for processing direct image generation
        add_action('botwriter_direct_generate_image', [__CLASS__, 'handle_direct_image_generation'], 10, 1);
        
        // Hook for complete direct task (text + image + post)
        add_action('botwriter_direct_process_task', [__CLASS__, 'handle_direct_task'], 10, 2);
        
        // Hook for polling ComfyUI results
        add_action('botwriter_check_comfyui_result', [__CLASS__, 'handle_comfyui_poll'], 10, 1);
        
        // Ensure recurring check for stuck tasks
        if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('botwriter_cleanup_stuck_tasks')) {
            as_schedule_recurring_action(
                time() + 300, // Start in 5 minutes
                HOUR_IN_SECONDS, // Run every hour
                'botwriter_cleanup_stuck_tasks',
                [],
                'botwriter'
            );
        }
        
        // Hook for cleaning up stuck tasks
        add_action('botwriter_cleanup_stuck_tasks', [__CLASS__, 'cleanup_stuck_tasks']);
    }
    
    /**
     * Handle direct text generation task
     * 
     * @param array $task_data Task data including prompt, model, URL, etc.
     */
    public static function handle_direct_text_generation($task_data) {
        $generator = new BotWriter_Direct_Text_Generator();
        return $generator->generate($task_data);
    }
    
    /**
     * Handle direct image generation task
     * 
     * @param array $task_data Task data including prompt, format, URL, etc.
     */
    public static function handle_direct_image_generation($task_data) {
        $generator = new BotWriter_Direct_Image_Generator();
        return $generator->generate($task_data);
    }
    
    /**
     * Handle complete direct task (text + image + post creation)
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Complete task data
     */
    public static function handle_direct_task($log_id, $task_data) {
        try {
            botwriter_log('[BotWriter] handle_direct_task STARTED - Log ID: ' . $log_id);
            $runner = new BotWriter_Direct_Task_Runner();
            $result = $runner->process_task($log_id, $task_data);
            botwriter_log('[BotWriter] handle_direct_task FINISHED - Log ID: ' . $log_id . ', Success: ' . ($result['success'] ?? 'unknown'));
            return $result;
        } catch (\Exception $e) {
            botwriter_log('[BotWriter] handle_direct_task EXCEPTION: ' . $e->getMessage());
            botwriter_log('[BotWriter] handle_direct_task TRACE: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        } catch (\Error $e) {
            botwriter_log('[BotWriter] handle_direct_task ERROR: ' . $e->getMessage());
            botwriter_log('[BotWriter] handle_direct_task TRACE: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Fatal Error: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Handle ComfyUI polling for image generation
     * 
     * @param int $log_id Log entry ID
     */
    public static function handle_comfyui_poll($log_id) {
        $runner = new BotWriter_Direct_Task_Runner();
        return $runner->handle_comfyui_poll($log_id);
    }
    
    /**
     * Cleanup tasks that have been stuck in 'processing' for too long
     */
    public static function cleanup_stuck_tasks() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'botwriter_logs';
        $stuck_threshold = gmdate('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // Find tasks stuck in 'processing' for more than 1 hour
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed
        $stuck_tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} 
             WHERE task_status = 'processing' 
             AND last_execution_time < %s",
            $stuck_threshold
        ));
        
        foreach ($stuck_tasks as $task) {
            $wpdb->update(
                $table,
                [
                    'task_status' => 'error',
                    'last_execution_time' => current_time('mysql'),
                ],
                ['id' => $task->id]
            );
            
            botwriter_log('Marked stuck task as error', [
                'log_id' => $task->id,
            ]);
        }
        
        if (count($stuck_tasks) > 0) {
            botwriter_log('Cleaned up stuck tasks', [
                'count' => count($stuck_tasks),
            ]);
        }
    }
    
    /**
     * Show admin notice if Action Scheduler version is too old
     */
    public static function maybe_show_version_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only check if we're using external Action Scheduler
        if (self::$loaded_by_botwriter) {
            return;
        }
        
        // Check version
        if (class_exists('ActionScheduler_Versions')) {
            $version = ActionScheduler_Versions::instance()->latest_version();
            if (version_compare($version, self::MIN_VERSION, '<')) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>BotWriter:</strong> 
                        <?php
                        /* translators: 1: loaded Action Scheduler version, 2: minimum recommended version */
                        $message = sprintf(
                            esc_html__('The Action Scheduler version (%1$s) loaded by another plugin is older than recommended (%2$s). Self-hosted features may not work correctly.', 'botwriter'),
                            esc_html($version),
                            esc_html(self::MIN_VERSION)
                        );
                        echo $message;
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Check if Action Scheduler is available and ready
     * 
     * @return bool
     */
    public static function is_available() {
        // If function doesn't exist, try to load Action Scheduler
        if (!function_exists('as_enqueue_async_action')) {
            self::maybe_load_action_scheduler();
            
            // After loading, check if ActionScheduler_Versions exists and initialize
            if (class_exists('ActionScheduler_Versions', false)) {
                ActionScheduler_Versions::initialize_latest_version();
            }
        }
        
        return function_exists('as_enqueue_async_action');
    }
    
    /**
     * Check if self-hosted direct mode should be used
     * 
     * @param string $text_provider Text provider slug
     * @param string $image_provider Image provider slug
     * @return bool
     */
    public static function should_use_direct_mode($text_provider, $image_provider) {
        $selfhosted_text_providers = ['selfhosted'];
        $selfhosted_image_providers = ['selfhosted_image'];
        
        return in_array($text_provider, $selfhosted_text_providers, true) 
            || in_array($image_provider, $selfhosted_image_providers, true);
    }
    
    /**
     * Enqueue a task for direct processing
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task data
     * @return int|false Action ID or false on failure
     */
    public static function enqueue_direct_task($log_id, $task_data) {
        if (!self::is_available()) {
            botwriter_log('Cannot enqueue direct task: Action Scheduler not available', [], 'error');
            return false;
        }
        
        // Enqueue for immediate async processing
        $action_id = as_enqueue_async_action(
            'botwriter_direct_process_task',
            [
                'log_id' => $log_id,
                'task_data' => $task_data,
            ],
            'botwriter'
        );
        
        if ($action_id) {
            botwriter_log('Direct task enqueued', [
                'action_id' => $action_id,
                'log_id' => $log_id,
                'text_provider' => $task_data['text_provider'] ?? null,
                'image_provider' => $task_data['image_provider'] ?? null,
            ]);
        }
        
        return $action_id;
    }
    
    /**
     * Get Action Scheduler admin URL
     * 
     * @return string
     */
    public static function get_admin_url() {
        return admin_url('admin.php?page=action-scheduler');
    }
}
