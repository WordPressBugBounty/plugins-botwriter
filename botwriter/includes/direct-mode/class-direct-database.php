<?php
/**
 * Direct Mode Database Handler
 * 
 * Manages the local database table for direct mode task processing.
 * This replicates the server-side logs_server table but runs locally.
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Direct_Database
 */
class BotWriter_Direct_Database {
    
    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'botwriter_direct_tasks';
    
    /**
     * Database version for migrations
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Option name for DB version
     */
    const VERSION_OPTION = 'botwriter_direct_db_version';
    
    /**
     * Get full table name with prefix
     * 
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
    
    /**
     * Create or update the direct tasks table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if table exists and version matches
        $installed_version = get_option(self::VERSION_OPTION, '0');
        
        if ($installed_version === self::DB_VERSION) {
            return; // Already up to date
        }
        
        $sql = "CREATE TABLE {$table_name} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `log_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to botwriter_logs.id',
            `task_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to botwriter_tasks.id',
            
            -- Task configuration
            `website_type` varchar(50) DEFAULT 'ai',
            `text_provider` varchar(50) DEFAULT 'selfhosted',
            `image_provider` varchar(50) DEFAULT 'selfhosted',
            `text_model` varchar(100) DEFAULT NULL,
            `image_model` varchar(100) DEFAULT NULL,
            
            -- Content generation settings
            `post_language` varchar(10) DEFAULT 'en',
            `post_length` int(11) DEFAULT 800,
            `writer` varchar(100) DEFAULT NULL,
            `narration` varchar(100) DEFAULT NULL,
            `custom_style` text DEFAULT NULL,
            
            -- External source settings
            `rss_source` text DEFAULT NULL,
            `domain_name` varchar(255) DEFAULT NULL,
            `website_category_id` varchar(255) DEFAULT NULL,
            `news_keyword` varchar(255) DEFAULT NULL,
            `news_country` varchar(10) DEFAULT NULL,
            `news_language` varchar(10) DEFAULT NULL,
            `news_time_published` varchar(10) DEFAULT NULL,
            `news_source` varchar(255) DEFAULT NULL,
            
            -- Prompts
            `client_prompt` longtext DEFAULT NULL,
            `title_prompt` text DEFAULT NULL,
            `content_prompt` text DEFAULT NULL,
            `image_prompt` text DEFAULT NULL,
            `ai_keywords` text DEFAULT NULL,
            
            -- Generated content
            `aigenerated_title` text DEFAULT NULL,
            `aigenerated_content` longtext DEFAULT NULL,
            `aigenerated_tags` text DEFAULT NULL,
            `aigenerated_image` text DEFAULT NULL,
            
            -- Status and tracking
            `task_status` varchar(50) DEFAULT 'pending',
            `error` text DEFAULT NULL,
            `attempts` int(11) DEFAULT 0,
            `action_scheduler_id` bigint(20) unsigned DEFAULT NULL,
            
            -- Post creation
            `post_id` bigint(20) unsigned DEFAULT NULL,
            `post_status` varchar(20) DEFAULT 'draft',
            `link_post_original` text DEFAULT NULL,
            `links_published` longtext DEFAULT NULL,
            `titles_published` longtext DEFAULT NULL,
            
            -- Self-hosted configuration
            `selfhosted_url` varchar(500) DEFAULT NULL,
            `selfhosted_model` varchar(100) DEFAULT NULL,
            `selfhosted_timeout` int(11) DEFAULT 300,
            `selfhosted_image_url` varchar(500) DEFAULT NULL,
            `selfhosted_image_type` varchar(50) DEFAULT 'openai',
            `selfhosted_image_model` varchar(100) DEFAULT NULL,
            
            -- Metadata
            `tokens_used` int(11) DEFAULT NULL,
            `generation_time` float DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `started_at` datetime DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (`id`),
            KEY `idx_log_id` (`log_id`),
            KEY `idx_task_id` (`task_id`),
            KEY `idx_task_status` (`task_status`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_action_scheduler_id` (`action_scheduler_id`)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Update version
        update_option(self::VERSION_OPTION, self::DB_VERSION);
        
        botwriter_log('Direct mode database table created/updated', [
            'version' => self::DB_VERSION,
        ]);
    }
    
    /**
     * Insert a new direct task
     * 
     * @param array $data Task data
     * @return int|false Inserted ID or false on failure
     */
    public static function insert($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Set defaults
        $defaults = [
            'task_status' => 'pending',
            'attempts' => 0,
            'created_at' => current_time('mysql'),
        ];
        
        $data = array_merge($defaults, $data);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            botwriter_log('Failed to insert direct task', [
                'error' => $wpdb->last_error,
            ], 'error');
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a direct task
     * 
     * @param int $id Task ID
     * @param array $data Data to update
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get a direct task by ID
     * 
     * @param int $id Task ID
     * @return object|null Task data or null
     */
    public static function get($id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get a direct task by log_id
     * 
     * @param int $log_id Log ID
     * @return object|null Task data or null
     */
    public static function get_by_log_id($log_id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE log_id = %d ORDER BY id DESC LIMIT 1",
            $log_id
        ));
    }
    
    /**
     * Get pending tasks
     * 
     * @param int $limit Max number of tasks to return
     * @return array Array of task objects
     */
    public static function get_pending($limit = 10) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE task_status IN ('pending', 'retry') 
             ORDER BY created_at ASC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get tasks in progress
     * 
     * @return array Array of task objects
     */
    public static function get_in_progress() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE task_status = 'processing' 
             ORDER BY started_at ASC"
        );
    }
    
    /**
     * Mark task as started
     * 
     * @param int $id Task ID
     * @param int $action_scheduler_id Action Scheduler action ID
     * @return bool
     */
    public static function mark_started($id, $action_scheduler_id = null) {
        return self::update($id, [
            'task_status' => 'processing',
            'started_at' => current_time('mysql'),
            'action_scheduler_id' => $action_scheduler_id,
        ]);
    }
    
    /**
     * Mark task as completed
     * 
     * @param int $id Task ID
     * @param array $result Generation result
     * @return bool
     */
    public static function mark_completed($id, $result = []) {
        $data = [
            'task_status' => 'completed',
            'completed_at' => current_time('mysql'),
        ];
        
        if (!empty($result['post_id'])) {
            $data['post_id'] = $result['post_id'];
        }
        if (!empty($result['title'])) {
            $data['aigenerated_title'] = $result['title'];
        }
        if (!empty($result['content'])) {
            $data['aigenerated_content'] = $result['content'];
        }
        if (!empty($result['tags'])) {
            $data['aigenerated_tags'] = $result['tags'];
        }
        if (!empty($result['image'])) {
            $data['aigenerated_image'] = $result['image'];
        }
        if (!empty($result['tokens_used'])) {
            $data['tokens_used'] = $result['tokens_used'];
        }
        if (!empty($result['generation_time'])) {
            $data['generation_time'] = $result['generation_time'];
        }
        
        return self::update($id, $data);
    }
    
    /**
     * Mark task as failed
     * 
     * @param int $id Task ID
     * @param string $error Error message
     * @param bool $can_retry Whether to mark for retry
     * @return bool
     */
    public static function mark_failed($id, $error, $can_retry = true) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Get current attempts
        $task = self::get($id);
        if (!$task) {
            return false;
        }
        
        $attempts = intval($task->attempts) + 1;
        $max_attempts = 3;
        
        if ($can_retry && $attempts < $max_attempts) {
            $status = 'retry';
        } else {
            $status = 'error';
        }
        
        return self::update($id, [
            'task_status' => $status,
            'error' => $error,
            'attempts' => $attempts,
        ]);
    }
    
    /**
     * Create a direct task from log data
     * 
     * @param int $log_id Log entry ID
     * @param array $task_data Task/log data
     * @return int|false Direct task ID or false
     */
    public static function create_from_log($log_id, $task_data) {
        $data = [
            'log_id' => $log_id,
            'task_id' => $task_data['id_task'] ?? $task_data['task_id'] ?? null,
            
            // Configuration
            'website_type' => $task_data['website_type'] ?? 'ai',
            'text_provider' => $task_data['text_provider'] ?? 'selfhosted',
            'image_provider' => $task_data['image_provider'] ?? 'selfhosted',
            'text_model' => $task_data['selfhosted_model'] ?? $task_data['text_model'] ?? null,
            'image_model' => $task_data['selfhosted_image_model'] ?? null,
            
            // Content settings
            'post_language' => $task_data['post_language'] ?? 'en',
            'post_length' => intval($task_data['post_length'] ?? 800),
            'writer' => $task_data['writer'] ?? null,
            'narration' => $task_data['narration'] ?? null,
            'custom_style' => $task_data['custom_style'] ?? null,
            
            // External sources
            'rss_source' => $task_data['rss_source'] ?? null,
            'domain_name' => $task_data['domain_name'] ?? null,
            'website_category_id' => $task_data['website_category_id'] ?? null,
            'news_keyword' => $task_data['news_keyword'] ?? null,
            'news_country' => $task_data['news_country'] ?? null,
            'news_language' => $task_data['news_language'] ?? null,
            'news_time_published' => $task_data['news_time_published'] ?? null,
            'news_source' => $task_data['news_source'] ?? null,
            
            // Prompts
            'client_prompt' => $task_data['client_prompt'] ?? null,
            'title_prompt' => $task_data['title_prompt'] ?? null,
            'content_prompt' => $task_data['content_prompt'] ?? null,
            'ai_keywords' => $task_data['ai_keywords'] ?? null,
            
            // Post settings
            'post_status' => $task_data['post_status'] ?? 'draft',
            'links_published' => $task_data['links'] ?? null,
            'titles_published' => $task_data['titles'] ?? null,
            
            // Self-hosted config
            'selfhosted_url' => $task_data['selfhosted_url'] ?? null,
            'selfhosted_model' => $task_data['selfhosted_model'] ?? null,
            'selfhosted_timeout' => intval($task_data['selfhosted_timeout'] ?? 300),
            'selfhosted_image_url' => $task_data['selfhosted_image_url'] ?? null,
            'selfhosted_image_type' => $task_data['selfhosted_image_type'] ?? 'openai',
            'selfhosted_image_model' => $task_data['selfhosted_image_model'] ?? null,
        ];
        
        return self::insert($data);
    }
    
    /**
     * Cleanup old completed/error tasks
     * 
     * @param int $days Keep tasks for this many days
     * @return int Number of deleted rows
     */
    public static function cleanup_old($days = 30) {
        global $wpdb;
        
        $table = self::get_table_name();
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
             WHERE task_status IN ('completed', 'error') 
             AND created_at < %s",
            $threshold
        ));
        
        if ($deleted > 0) {
            botwriter_log('Direct tasks cleanup', [
                'deleted' => $deleted,
                'days' => $days,
            ]);
        }
        
        return $deleted;
    }
    
    /**
     * Get task statistics
     * 
     * @return array Stats array
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $stats = $wpdb->get_results(
            "SELECT task_status, COUNT(*) as count 
             FROM {$table} 
             GROUP BY task_status"
        );
        
        $result = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'error' => 0,
            'retry' => 0,
            'total' => 0,
        ];
        
        foreach ($stats as $row) {
            $result[$row->task_status] = intval($row->count);
            $result['total'] += intval($row->count);
        }
        
        return $result;
    }
    
    /**
     * Drop the table (for uninstall)
     */
    public static function drop_table() {
        global $wpdb;
        
        $table = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
        delete_option(self::VERSION_OPTION);
    }
}

// Register activation hook to create table
register_activation_hook(BOTWRITER_PLUGIN_DIR . 'botwriter.php', ['BotWriter_Direct_Database', 'create_table']);

// Also check on plugin load for upgrades
add_action('plugins_loaded', function() {
    if (get_option(BotWriter_Direct_Database::VERSION_OPTION, '0') !== BotWriter_Direct_Database::DB_VERSION) {
        BotWriter_Direct_Database::create_table();
    }
}, 20);
