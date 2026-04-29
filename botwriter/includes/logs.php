<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function botwriter_logs_page_handler() {

    // Check if the user has the necessary capability
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['botwriter_logs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['botwriter_logs_nonce'])), 'botwriter_logs_action')
        ) {
            // The nonce is valid, continue execution
        } else {
            wp_die(esc_html__('Security check failed', 'botwriter'));
        }
    }

    // Instantiate the logs table class
    $logs_table = new botwriter_Logs_Table();
    $logs_table->prepare_items();

    // Display the page
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('BotWriter Logs', 'botwriter') . '</h1>';    
    echo '<div id="countup"></div>';
    
    echo '<form method="post">';
    wp_nonce_field('botwriter_logs_action', 'botwriter_logs_nonce');
    $logs_table->search_box(__('Search logs', 'botwriter'), 'botwriter-logs-search');
    $logs_table->display();
    echo '</form>';
    echo '</div>';
}



class botwriter_Logs_Table extends WP_List_Table {

    
    private $table_data;

    public function __construct() {
        parent::__construct(array(
            'singular' => 'log',
            'plural'   => 'logs',
            'ajax'     => false,
        ));
    }

    public function get_columns() {
        return array(
            'cb'                 => '<input type="checkbox" />',
            'created_at'         => __('Created At', 'botwriter'),
            'writer'             => __('Writer', 'botwriter'),
            'task_name'          => __('Task Name', 'botwriter'),
            'task_status'        => __('Task Status', 'botwriter'),
            'aigenerated_title'  => __('AI Generated Title', 'botwriter'),
            'aigenerated_image'  => __('AI Generated Image', 'botwriter'),
            'id_post_published' => __('Post Published', 'botwriter'),
            'actions'            => __('Actions', 'botwriter'),
        );
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%d" />',
            intval($item['id'])
        );
    }

    public function get_bulk_actions() {
        return array(
            'bulk_delete' => __('Delete', 'botwriter'),
        );
    }

    public function process_bulk_action() {
        if ('bulk_delete' === $this->current_action()) {
            if ( ! isset($_POST['botwriter_logs_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['botwriter_logs_nonce'])), 'botwriter_logs_action') ) {
                return;
            }
            if ( ! current_user_can('manage_options') ) {
                return;
            }
            $log_ids = isset($_POST['log_ids']) ? array_map('intval', $_POST['log_ids']) : array();
            if ( ! empty($log_ids) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'botwriter_logs';
                $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $log_ids));
            }
        }
    }

    public function column_actions($item) {
        $log_id = intval($item['id']);
        return '<button type="button" class="button button-small botwriter-delete-log" data-log-id="' . esc_attr($log_id) . '" title="' . esc_attr__('Delete this log', 'botwriter') . '"><span class="dashicons dashicons-trash" style="vertical-align: middle;"></span></button>';
    }

    function column_writer($item){
        $dir_images_writers = BOTWRITER_URL . '/assets/images/writers/';
        $writer=$item['writer'];
        $writer = strtolower($writer);

        
        $img= '<img src="' . esc_url($dir_images_writers . $writer . '.jpeg') . '" alt="' . esc_attr($writer) . '" class="writer-photo">';
        return $img;
        
    }

    // Create a function for the id_post_published column that shows View and Edit buttons
    function column_id_post_published($item){
        $id_post_published = $item['id_post_published'];
        if ($id_post_published == 0 || empty($id_post_published)) {
            return '<span style="color: #999;">—</span>';
        } else {
            $view_link = get_permalink($id_post_published);
            $edit_link = get_edit_post_link($id_post_published);
            
            $buttons = '<div style="display: flex; gap: 5px;">';
            
            // View button
            $buttons .= '<a href="' . esc_url($view_link) . '" target="_blank" class="button button-small" title="' . esc_attr__('View post', 'botwriter') . '" style="display: inline-flex; align-items: center; gap: 3px;">';
            $buttons .= '<span class="dashicons dashicons-visibility" style="font-size: 14px; width: 14px; height: 14px;"></span>';
            $buttons .= '<span>' . esc_html__('View', 'botwriter') . '</span>';
            $buttons .= '</a>';
            
            // Edit button
            $buttons .= '<a href="' . esc_url($edit_link) . '" class="button button-small" title="' . esc_attr__('Edit post', 'botwriter') . '" style="display: inline-flex; align-items: center; gap: 3px;">';
            $buttons .= '<span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px;"></span>';
            $buttons .= '<span>' . esc_html__('Edit', 'botwriter') . '</span>';
            $buttons .= '</a>';
            
            $buttons .= '</div>';
            
            return $buttons;
        }
    }
    
    
    function prepare_items()
    {
                // Read-only search term from list table request.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search param for list table.
                $search_query = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

                // Process actions first (e.g., bulk delete), then fetch filtered rows.
                $this->process_bulk_action();
                $this->table_data = $this->get_table_data($search_query);

        $columns = $this->get_columns();
        $hidden = ( is_array(get_user_meta( get_current_user_id(), 'managetoplevel_page_list_tablecolumnshidden', true)) ) ? get_user_meta( get_current_user_id(), 'managetoplevel_page_list_tablecolumnshidden', true) : array();
        $sortable = $this->get_sortable_columns();
        $primary  = 'name';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

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

          // Get table data
          private function get_table_data( $search = '' ) {
            global $wpdb;
											   
        
            $table = $wpdb->prefix."botwriter_logs";
            
        
            if ( ! empty( $search ) ) {
                $like = '%' . $wpdb->esc_like( $search ) . '%';

                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT *
                           FROM {$table}
                          WHERE website_type <> %s
                            AND (
                                task_name LIKE %s
                                OR aigenerated_title LIKE %s
                                OR link_post_original LIKE %s
                                OR rss_source LIKE %s
                                OR error LIKE %s
                                OR task_status LIKE %s
                            )",
                        'super1',
                        $like,
                        $like,
                        $like,
                        $like,
                        $like,
                        $like
                    ),
                    ARRAY_A
                );
            }

            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE website_type <> %s", 'super1'),
                ARRAY_A
            );
        }
    

          // Sorting function
          function usort_reorder($a, $b)
          { 
              // If no sort, default to task_name          
              // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort param for list table.
              $sanitized_orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
    
              $orderby = (!empty($sanitized_orderby)) ? $sanitized_orderby : 'created_at';
      
              // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort param for list table.
              $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'desc  ';
    
              // filtrar order solo asd o desc
                $order = in_array($order, array('asc', 'desc')) ? $order : 'desc';
                // filter orderby only allowed columns
                $orderby = in_array($orderby, array('created_at', 'task_status', 'aigenerated_title')) ? $orderby : 'task_name';
                
    
              
      
              // Determine sort order
              $result = strcmp($a[$orderby], $b[$orderby]);
      
              // Send final sort direction to usort
              return ($order === 'asc') ? $result : -$result;
          }
    
    
    public function column_default($item, $column_name) {
        // Default handler for columns
        switch ($column_name) {
            case 'created_at':
            case 'task_status':
            case 'aigenerated_title':
                return esc_html($item[$column_name]); // Escape output for safety
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : ''; // Fallback for other columns
        }
    }

    public function column_task_status($item) {        
        // 4 cases: inqueue, pending, completed, error 
        $task_status = $item['task_status'];        
        $intento_tiempo = array(0=>0,1=>0,2=>5,3=>10,4=>30,5=>60,6=>120,7=>240,8=>480); // minutes
        $intentosfase1 = $item["intentosfase1"];
                    
            // if it is error show in red and show that it will retry in time * attempts
            if ($task_status == 'error') {            
                $id_task_server = $item['id_task_server'];
                $txt= '<span style="color:red;">Error (id server:' . $id_task_server . ')</span>';
                if ($item["error"]!='') {
                    $txt.= '<br>' . wp_kses_post($item["error"]) . "<br>";
                }
                // writenow tasks don't retry, so don't show retry info
                $task_type = isset($item['task_type']) ? $item['task_type'] : '';
                if ($intentosfase1 < 8 && $task_type !== 'writenow') {  // these are the ones it has taken                    
                    $tiempo = $intento_tiempo[$intentosfase1+1]; // next attempt
                    $created_at = strtotime($item["created_at"]);
                    $tiempo_siguiente_intento = $created_at + $tiempo*60;            
                    $txt.= '<br>Attempt ' . $intentosfase1 . ' of 8';
                    $txt.= '<br>Will retry at ' . gmdate('Y-m-d H:i:s', $tiempo_siguiente_intento);                
                }                                
            } else {
                $txt=esc_html($task_status);
            }
            
            return $txt;

    }

    public function column_aigenerated_image($item) {
        $id_post_published = intval($item['id_post_published'] ?? 0);
        $image_html = '';

        // check if the post is published
        if ($id_post_published != 0) {
            // get the post image url
            $post_thumbnail_id = get_post_thumbnail_id($id_post_published);
            $post_thumbnail_url = wp_get_attachment_image_src($post_thumbnail_id, 'thumbnail');
            if ($post_thumbnail_url) {
                $image_html = '<img src="' . esc_url($post_thumbnail_url[0]) . '" alt="Post Image" width="50">';
            }
        }

        // Render the image or fallback to text if invalid
        if ($image_html === '' && filter_var($item['aigenerated_image'], FILTER_VALIDATE_URL)) {
            $image_html = '<img src="' . esc_url($item['aigenerated_image']) . '" alt="Generated Image" width="50">';
        }

        if ($image_html === '' && !filter_var($item['aigenerated_image'], FILTER_VALIDATE_URL)) {
            $fallback = trim((string) ($item['aigenerated_image'] ?? ''));
            $image_html = ($fallback !== '')
                ? '<span style="font-size:11px;color:#666;">' . esc_html($fallback) . '</span>'
                : '<span style="color:#999;">—</span>';
        }

        $edit_link = '';
        if ($id_post_published > 0) {
            $edit_link = '<a href="#" class="botwriter-regenerate-image-link" data-post-id="' . esc_attr($id_post_published) . '" title="' . esc_attr__('Edit featured image', 'botwriter') . '" style="display:inline-flex;align-items:center;gap:4px;font-size:12px;margin-top:6px;">'
                . '<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;"></span>'
                . '<span>' . esc_html__('Edit', 'botwriter') . '</span>'
                . '</a>';
        }

        return '<div class="botwriter-log-image-actions" style="display:flex;flex-direction:column;align-items:flex-start;">' . $image_html . $edit_link . '</div>';
    }
	
	public function column_task_name($item) {
        $txt = esc_html($item['task_name'] ?? '');

        if (($item['website_type'] ?? '') === 'super2') {
            $txt .= '<br><span style="color:blue;">' . esc_html($item['title_prompt']) . '</span>';
        }

        if (($item['website_type'] ?? '') === 'rss') {
            $source_url_raw = trim((string) ($item['link_post_original'] ?? ''));
            $source_label = esc_html__('Source URL', 'botwriter');

            if ($source_url_raw !== '') {
                $source_url = esc_url($source_url_raw);
                if ($source_url !== '') {
                    $txt .= '<br><span style="font-size:11px;color:#555;"><strong>' . $source_label . ':</strong> <a href="' . $source_url . '" target="_blank" rel="noopener" style="word-break:break-all;">' . esc_html($source_url_raw) . '</a></span>';
                } else {
                    $txt .= '<br><span style="font-size:11px;color:#555;"><strong>' . $source_label . ':</strong> ' . esc_html($source_url_raw) . '</span>';
                }
            } else {
                $txt .= '<br><span style="font-size:11px;color:#999;"><strong>' . $source_label . ':</strong> ' . esc_html__('not available yet', 'botwriter') . '</span>';
            }
        }

        return $txt;
    }


    public function no_items() {
        esc_html__('No logs found.', 'botwriter');
    }

    public function get_sortable_columns() {
        return array(
            'created_at' => array('created_at', true),
													 
            'task_status' => array('task_status', false),
            'aigenerated_title' => array('aigenerated_title', false),
        );
    }

    
}


function botwriter_get_logs_links($id_task) {
    global $wpdb;

    // Per-task "already-handled" list. Excludes rows that ended in error so a
    // failed task can be retried on the same source URL, but keeps pending /
    // in-flight rows so two cron ticks don't pick the same article.
    $links = $wpdb->get_results($wpdb->prepare(
        "SELECT link_post_original FROM {$wpdb->prefix}botwriter_logs
          WHERE id_task = %d
            AND (task_status IS NULL OR task_status <> 'error')
          ORDER BY id DESC LIMIT 50",
        $id_task
    ), ARRAY_A);
    $links_array = [];
    if (empty($links)) {
        return false;
    } else {
        foreach ($links as $link) {
            if ($link['link_post_original'] != '') {
                $links_array[] = $link['link_post_original'];
            }
        }
    }
    return $links_array;

}


function botwriter_get_logs_titles($id_task) {
    global $wpdb;

    // Used to feed prompt context (avoid repeating titles). Same status filter
    // as botwriter_get_logs_links: skip error rows so retries are allowed.
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT aigenerated_title FROM {$wpdb->prefix}botwriter_logs
          WHERE id_task = %d
            AND (task_status IS NULL OR task_status <> 'error')
          ORDER BY id DESC LIMIT 50",
        $id_task
    ), ARRAY_A);
    $titles_array = [];
    if (empty($results)) {
        return false;
    } else {
        foreach ($results as $result) {
            if ($result['aigenerated_title'] != '') {
                $titles_array[] = $result['aigenerated_title'];
                //echo "<br>title in the db: " . $result['aigenerated_title'];
            }
        }
    }
    return $titles_array; 
    
}
 
 

// Register a log in the botwriter_logs table (insert or update)
function botwriter_logs_register($data, $id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_logs';

    // Validate that $data is an array
    if (!is_array($data)) {
        return false; // Or throw an exception as needed
    }

    // List of allowed keys
    $allowed_keys = array(
        'id_task',
        'id_task_server',
        'post_status',
        'task_name',
        'task_type',
        'writer',
        'narration',
        'custom_style',
        'post_language',
        'post_length',
        'link_post_original',
        'id_post_published',
        'task_status',
        'error',
        'website_name',
        'website_type',
        'domain_name',
        'post_type',
        'category_id',
        'taxonomy_data',
        'website_category_id',
        'aigenerated_title',
        'aigenerated_content',
        'aigenerated_tags',
        'aigenerated_image',
        'post_count',
        'post_order',
        'title_prompt',
        'content_prompt',
        'tags_prompt',
        'image_prompt',
        'image_generating_status',
        'author_selection',
        'news_time_published',
        'news_language',
        'news_country',
        'news_keyword',
        'news_source',
        'rss_source',
        'ai_keywords',
        'disable_ai_images',
        'template_id',
        'intentosfase1',
        'last_execution_time'

    );

    // Create the array with only the existing values in $data
    $insert_data = array();
    foreach ($allowed_keys as $key) {
        if (isset($data[$key])) {
            $insert_data[$key] = $data[$key];
        }
    }

    if ($id) {
        // Update the existing record
        $where = array('id' => $id);
        $updated = $wpdb->update($table_name, $insert_data, $where);
        
        // If task_status is 'completed', reset the errors notice
        if (isset($insert_data['task_status']) && $insert_data['task_status'] === 'completed') {
            if (function_exists('botwriter_reset_errors_notice_dismissed')) {
                botwriter_reset_errors_notice_dismissed();
            }
        }

        // Return the result of the update
        return $updated !== false ? $id : false;
    } else {
        // Insert a new record
        // Add the creation date
        $current_time = current_time('mysql');
        $insert_data['created_at'] = $current_time;

        $wpdb->insert($table_name, $insert_data);
        
        // If the new log is 'completed', reset the errors notice dismissed flag
        // This allows the notice to reappear if errors occur again later
        if (isset($insert_data['task_status']) && $insert_data['task_status'] === 'completed') {
            if (function_exists('botwriter_reset_errors_notice_dismissed')) {
                botwriter_reset_errors_notice_dismissed();
            }
        }

        // Return the ID of the new record
        return $wpdb->insert_id;
    }
}

// Function that given an id returns an array with the data of a log
function botwriter_logs_get($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_logs';

    $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

    return $log;
}
