<?php
/**
 * Direct Task Runner for Self-Hosted Processing
 * 
 * Orchestrates content fetching, text generation, image generation,
 * and WordPress post creation for self-hosted tasks.
 * 
 * Replicates the server-side processing flow but runs entirely
 * within the WordPress plugin.
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Direct_Task_Runner
 */
class BotWriter_Direct_Task_Runner {
    
    /**
     * Text generator instance
     * 
     * @var BotWriter_Direct_Text_Generator
     */
    private $text_generator;
    
    /**
     * Image generator instance
     * 
     * @var BotWriter_Direct_Image_Generator
     */
    private $image_generator;
    
    /**
     * Content sources handler
     * 
     * @var BotWriter_Direct_Content_Sources
     */
    private $content_sources;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->text_generator = new BotWriter_Direct_Text_Generator();
        $this->image_generator = new BotWriter_Direct_Image_Generator();
        $this->content_sources = new BotWriter_Direct_Content_Sources();
    }
    
    /**
     * Process a complete task (fetch content + text + image + post creation)
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task data
     * @return array Result
     */
    public function process_task($log_id, $task_data) {
        global $wpdb;
        
        // Always log for debugging
        botwriter_log('========== TASK STARTED ==========');
        botwriter_log('Log ID: ' . $log_id);
        botwriter_log('Task ID: ' . ($task_data['task_id'] ?? $task_data['id_task'] ?? 0));
        botwriter_log('Website type: ' . ($task_data['website_type'] ?? 'ai'));
        botwriter_log('Selfhosted URL: ' . ($task_data['selfhosted_url'] ?? 'NOT SET'));
        botwriter_log('Selfhosted Model: ' . ($task_data['selfhosted_model'] ?? 'NOT SET'));
        
        botwriter_log('Direct task runner: Starting task', [
            'log_id' => $log_id,
            'task_id' => $task_data['task_id'] ?? $task_data['id_task'] ?? 0,
            'website_type' => $task_data['website_type'] ?? 'ai',
        ]);
        
        // Update status to processing
        $this->update_log_status($log_id, 'processing');
        
        // =====================================================
        // Step 0: Fetch external content if needed (RSS, WordPress, News)
        // =====================================================
        $website_type = strtolower($task_data['website_type'] ?? 'ai');
        
        if (in_array($website_type, ['rss', 'wordpress', 'news'], true)) {
            $source_result = $this->content_sources->fetch_content($task_data);
            
            if (!$source_result['success']) {
                $this->mark_failed($log_id, $source_result['error']);
                return $source_result;
            }
            
            // Update task_data with source content
            if (!empty($source_result['content'])) {
                $task_data['source_content'] = $source_result['content'];
                $task_data['link_post_original'] = $source_result['link_original'] ?? '';
                
                // Rebuild prompt with source content
                $task_data['client_prompt'] = $this->content_sources->build_prompt_with_source(
                    $task_data, 
                    $source_result
                );
                
                botwriter_log('Direct task runner: External content fetched', [
                    'log_id' => $log_id,
                    'source_type' => $website_type,
                    'source_title' => $source_result['source_title'] ?? '',
                    'link_original' => $source_result['link_original'] ?? '',
                ]);
            }
        }
        
        // =====================================================
        // Step 1: Generate text content
        // =====================================================
        botwriter_log('Step 1: Generating text content...');
        $text_result = $this->text_generator->generate($task_data);
        
        if (!$text_result['success']) {
            botwriter_log('Text generation FAILED: ' . ($text_result['error'] ?? 'Unknown error'));
            $this->mark_failed($log_id, $text_result['error']);
            return $text_result;
        }
        
        botwriter_log('Text generation SUCCESS - Title: ' . ($text_result['title'] ?? 'No title'));
        botwriter_log('Direct task runner: Text generated', [
            'log_id' => $log_id,
            'title' => $text_result['title'],
        ]);
        
        // Prepare image generation data
        $image_task_data = array_merge($task_data, [
            'image_prompt' => $text_result['image_prompt'] ?? '',
        ]);
        
        // =====================================================
        // Step 2: Generate image (if image provider is configured)
        // =====================================================
        botwriter_log('Step 2: Checking image generation...');
        $image_result = null;
        $attachment_id = 0;
        
        $should_gen_image = $this->should_generate_image($task_data);
        botwriter_log('Should generate image: ' . ($should_gen_image ? 'YES' : 'NO'));
        
        if ($should_gen_image) {
            botwriter_log('Generating image...');
            $image_result = $this->image_generator->generate($image_task_data);
            
            if ($image_result['success']) {
                // Check if it's pending (ComfyUI polling)
                if (!empty($image_result['status']) && $image_result['status'] === 'pending') {
                    // Schedule polling
                    $this->schedule_image_poll($log_id, $task_data, $text_result, $image_result);
                    
                    return [
                        'success' => true,
                        'status' => 'pending_image',
                        'message' => 'Text generated, waiting for image generation.',
                    ];
                }
                
                // Save image to media library
                $media_result = $this->image_generator->save_to_media_library($image_result, 0);
                
                if ($media_result['success']) {
                    $attachment_id = $media_result['attachment_id'];
                    botwriter_log('Image saved - Attachment ID: ' . $attachment_id);
                    botwriter_log('Direct task runner: Image saved', [
                        'log_id' => $log_id,
                        'attachment_id' => $attachment_id,
                    ]);
                } else {
                    botwriter_log('Image save failed: ' . ($media_result['error'] ?? 'Unknown'));
                    botwriter_log('Direct task runner: Image save failed', [
                        'log_id' => $log_id,
                        'error' => $media_result['error'],
                    ], 'warning');
                }
            } else {
                botwriter_log('Image generation failed: ' . ($image_result['error'] ?? 'Unknown'));
                botwriter_log('Direct task runner: Image generation failed', [
                    'log_id' => $log_id,
                    'error' => $image_result['error'],
                ], 'warning');
            }
        }
        
        // Step 3: Create WordPress post
        botwriter_log('Step 3: Creating WordPress post...');
        botwriter_log('Post title: ' . ($text_result['title'] ?? 'NO TITLE'));
        botwriter_log('Post content length: ' . strlen($text_result['content'] ?? ''));
        
        $post_result = $this->create_post($text_result, $task_data, $attachment_id);
        
        if (!$post_result['success']) {
            botwriter_log('Post creation FAILED: ' . ($post_result['error'] ?? 'Unknown'));
            $this->mark_failed($log_id, $post_result['error']);
            return $post_result;
        }
        
        botwriter_log('Post created successfully - ID: ' . $post_result['post_id']);
        
        // Step 4: Save generated content to log and mark as completed
        botwriter_log('Step 4: Saving generated content and marking as completed...');
        $this->mark_completed($log_id, $post_result['post_id'], $text_result);
        botwriter_log('========== TASK COMPLETED ==========');
        
        return [
            'success' => true,
            'post_id' => $post_result['post_id'],
            'attachment_id' => $attachment_id,
            'title' => $text_result['title'],
        ];
    }
    
    /**
     * Process only text generation
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task data
     * @return array Result
     */
    public function process_text_only($log_id, $task_data) {
        $this->update_log_status($log_id, 'processing_text');
        
        $result = $this->text_generator->generate($task_data);
        
        if (!$result['success']) {
            $this->mark_failed($log_id, $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Process only image generation
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task data
     * @return array Result
     */
    public function process_image_only($log_id, $task_data) {
        $this->update_log_status($log_id, 'processing_image');
        
        $result = $this->image_generator->generate($task_data);
        
        if (!$result['success']) {
            // Don't mark as completely failed for image errors
            botwriter_log('Image generation failed', ['error' => $result['error']], 'warning');
        }
        
        return $result;
    }
    
    /**
     * Continue task after image polling completes
     * 
     * @param int $log_id Log entry ID
     * @param array $saved_state Saved task state
     * @param array $image_result Image generation result
     * @return array Result
     */
    public function continue_after_image($log_id, $saved_state, $image_result) {
        $task_data = $saved_state['task_data'];
        $text_result = $saved_state['text_result'];
        
        $attachment_id = 0;
        
        if ($image_result['success'] && $image_result['status'] !== 'pending') {
            // Save image
            $media_result = $this->image_generator->save_to_media_library($image_result, 0);
            
            if ($media_result['success']) {
                $attachment_id = $media_result['attachment_id'];
            }
        }
        
        // Create post
        $post_result = $this->create_post($text_result, $task_data, $attachment_id);
        
        if (!$post_result['success']) {
            $this->mark_failed($log_id, $post_result['error']);
            return $post_result;
        }
        
        $this->mark_completed($log_id, $post_result['post_id']);
        
        return [
            'success' => true,
            'post_id' => $post_result['post_id'],
            'attachment_id' => $attachment_id,
        ];
    }
    
    /**
     * Check if image should be generated
     * 
     * @param array $task_data Task data
     * @return bool
     */
    private function should_generate_image($task_data) {
        // Check if self-hosted image provider is configured
        if (!empty($task_data['selfhosted_image_url'])) {
            return true;
        }
        
        // Check if any image provider is enabled
        $image_provider = $task_data['image_provider'] ?? get_option('botwriter_ai_image_provider', '');
        
        if (empty($image_provider) || $image_provider === 'none') {
            return false;
        }
        
        // For non-self-hosted providers, return false (they use the regular flow)
        return false;
    }
    
    /**
     * Create WordPress post
     * 
     * @param array $text_result Text generation result
     * @param array $task_data Original task data
     * @param int $attachment_id Featured image attachment ID
     * @return array Result
     */
    private function create_post($text_result, $task_data, $attachment_id = 0) {
        // Prepare post data
        $post_status = $task_data['post_status'] ?? 'draft';
        $post_author = $task_data['author_id'] ?? get_current_user_id();
        $post_category = $task_data['category'] ?? [];
        
        if (!is_array($post_category)) {
            $post_category = [$post_category];
        }
        
        $post_data = [
            'post_title' => wp_strip_all_tags($text_result['title']),
            'post_content' => $text_result['content'],
            'post_status' => $post_status,
            'post_author' => $post_author,
            'post_category' => array_map('intval', $post_category),
        ];
        
        // Insert post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return [
                'success' => false,
                'error' => 'Failed to create post: ' . $post_id->get_error_message(),
            ];
        }
        
        // Set featured image
        if ($attachment_id > 0) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        // Set tags
        if (!empty($text_result['tags'])) {
            $tags = array_map('trim', explode(',', $text_result['tags']));
            wp_set_post_tags($post_id, $tags);
        }
        
        // Add meta to track this as AI-generated
        update_post_meta($post_id, '_botwriter_generated', 1);
        update_post_meta($post_id, '_botwriter_provider', 'selfhosted');
        update_post_meta($post_id, '_botwriter_model', $task_data['selfhosted_model'] ?? 'unknown');
        update_post_meta($post_id, '_botwriter_generated_date', current_time('mysql'));
        
        // Store original source link if available (for RSS/WordPress/News sources)
        if (!empty($task_data['link_post_original'])) {
            update_post_meta($post_id, '_botwriter_source_link', $task_data['link_post_original']);
        }
        
        botwriter_log('Direct task runner: Post created', [
            'post_id' => $post_id,
            'title' => $text_result['title'],
            'status' => $post_status,
            'link_original' => $task_data['link_post_original'] ?? '',
        ]);
        
        return [
            'success' => true,
            'post_id' => $post_id,
        ];
    }
    
    /**
     * Schedule image polling for ComfyUI
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task data
     * @param array $text_result Text generation result
     * @param array $image_result Initial image result with prompt_id
     */
    private function schedule_image_poll($log_id, $task_data, $text_result, $image_result) {
        $saved_state = [
            'task_data' => $task_data,
            'text_result' => $text_result,
            'comfyui_prompt_id' => $image_result['comfyui_prompt_id'],
            'poll_count' => 0,
            'comfyui_elapsed' => 0,
        ];
        
        // Save state to log
        $this->save_task_state($log_id, $saved_state);
        
        // Schedule polling action
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time() + $image_result['poll_interval'],
                'botwriter_check_comfyui_result',
                [
                    'log_id' => $log_id,
                ],
                'botwriter'
            );
        }
    }
    
    /**
     * Handle ComfyUI poll callback
     * 
     * @param int $log_id Log entry ID
     */
    public function handle_comfyui_poll($log_id) {
        $saved_state = $this->get_task_state($log_id);
        
        if (!$saved_state) {
            botwriter_log('ComfyUI poll: No saved state', ['log_id' => $log_id], 'error');
            return;
        }
        
        $task_data = $saved_state['task_data'];
        $prompt_id = $saved_state['comfyui_prompt_id'];
        $elapsed = $saved_state['comfyui_elapsed'] ?? 0;
        
        // Poll for result
        $poll_task_data = array_merge($task_data, [
            'comfyui_prompt_id' => $prompt_id,
            'comfyui_elapsed' => $elapsed,
        ]);
        
        $image_result = $this->image_generator->poll_comfyui(
            $task_data['selfhosted_image_url'],
            $prompt_id,
            $poll_task_data
        );
        
        if (!$image_result['success']) {
            // Error - continue with no image
            botwriter_log('ComfyUI poll error', ['error' => $image_result['error']], 'warning');
            $this->continue_after_image($log_id, $saved_state, $image_result);
            return;
        }
        
        if (!empty($image_result['status']) && $image_result['status'] === 'pending') {
            // Still waiting - reschedule
            $saved_state['poll_count']++;
            $saved_state['comfyui_elapsed'] = $elapsed + ($image_result['poll_interval'] ?? 10);
            $this->save_task_state($log_id, $saved_state);
            
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(
                    time() + ($image_result['poll_interval'] ?? 10),
                    'botwriter_check_comfyui_result',
                    ['log_id' => $log_id],
                    'botwriter'
                );
            }
            return;
        }
        
        // Got result - continue
        $this->continue_after_image($log_id, $saved_state, $image_result);
    }
    
    /**
     * Update log status
     * 
     * @param int $log_id Log entry ID
     * @param string $status New status
     */
    private function update_log_status($log_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'botwriter_logs';
        
        $wpdb->update(
            $table,
            [
                'task_status' => $status,
                'last_execution_time' => current_time('mysql'),
            ],
            ['id' => $log_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Mark task as failed
     * 
     * @param int $log_id Log entry ID
     * @param string $error Error message
     */
    private function mark_failed($log_id, $error) {
        global $wpdb;
        $table = $wpdb->prefix . 'botwriter_logs';
        
        // Get current retry count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT intentosfase1, intentosfase2 FROM {$table} WHERE id = %d",
            $log_id
        ));
        
        $retries = intval($log->intentosfase1 ?? 0);
        
        // Max 3 retries
        if ($retries < 3) {
            $wpdb->update(
                $table,
                [
                    'task_status' => 'pending',
                    'intentosfase1' => $retries + 1,
                    'last_execution_time' => current_time('mysql'),
                ],
                ['id' => $log_id],
                ['%s', '%d', '%s'],
                ['%d']
            );
            
            botwriter_log('Direct task: Marked for retry', [
                'log_id' => $log_id,
                'retry' => $retries + 1,
                'error' => $error,
            ]);
        } else {
            $wpdb->update(
                $table,
                [
                    'task_status' => 'error',
                    'last_execution_time' => current_time('mysql'),
                ],
                ['id' => $log_id],
                ['%s', '%s'],
                ['%d']
            );
            
            botwriter_log('Direct task: Marked as error (max retries)', [
                'log_id' => $log_id,
                'error' => $error,
            ], 'error');
            
            // Add announcement for user
            if (function_exists('botwriter_announcements_add')) {
                botwriter_announcements_add(
                    'direct_task_error',
                    /* translators: 1: log ID, 2: error message */
                    sprintf(__('Self-hosted task %1$d failed: %2$s', 'botwriter'), $log_id, $error),
                    'error'
                );
            }
        }
    }
    
    /**
     * Mark task as completed
     * 
     * @param int $log_id Log entry ID
     * @param int $post_id Created post ID
     * @param array $text_result Generated text content
     */
    private function mark_completed($log_id, $post_id, $text_result = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'botwriter_logs';
        
        // Prepare update data with generated content (same fields as server response)
        $update_data = [
            'task_status' => 'completed',
            'id_post_published' => $post_id,
            'last_execution_time' => current_time('mysql'),
        ];
        
        // Add generated content fields if provided
        if (!empty($text_result)) {
            $update_data['aigenerated_title'] = $text_result['title'] ?? '';
            $update_data['aigenerated_content'] = $text_result['content'] ?? '';
            $update_data['aigenerated_tags'] = $text_result['tags'] ?? '';
            $update_data['aigenerated_image'] = $text_result['image_prompt'] ?? '';
        }
        
        $wpdb->update(
            $table,
            $update_data,
            ['id' => $log_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        botwriter_log('mark_completed - Log ID: ' . $log_id . ', Post ID: ' . $post_id);
        
        botwriter_log('Direct task: Completed', [
            'log_id' => $log_id,
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Save task state for async operations
     * 
     * @param int $log_id Log entry ID
     * @param array $state State to save
     */
    private function save_task_state($log_id, $state) {
        update_option('botwriter_task_state_' . $log_id, $state, false);
    }
    
    /**
     * Get saved task state
     * 
     * @param int $log_id Log entry ID
     * @return array|null Saved state or null
     */
    private function get_task_state($log_id) {
        return get_option('botwriter_task_state_' . $log_id, null);
    }
    
    /**
     * Clear saved task state
     * 
     * @param int $log_id Log entry ID
     */
    private function clear_task_state($log_id) {
        delete_option('botwriter_task_state_' . $log_id);
    }
}
