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

          // Get table data
          private function get_table_data( $search = '' ) {
            global $wpdb;
											   
        
            $table = $wpdb->prefix."botwriter_logs";
            
        
            if ( ! empty( $search ) ) {
                $prepared_search = $wpdb->esc_like( $search );
                $prepared_search = '%' . $wpdb->esc_like( $search ) . '%';
															
		   
        
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE task_name LIKE %s ",
                        $prepared_search
                    ),
                    ARRAY_A
                );
            } else {         
              return $wpdb->get_results(
                  $wpdb->prepare("SELECT * FROM {$table} WHERE website_type <> %s", 'super1'),
                  ARRAY_A
              );
            }
        }
    

          // Sorting function
          function usort_reorder($a, $b)
          { 
              // If no sort, default to task_name          
              $sanitized_orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
    
              $orderby = (!empty($sanitized_orderby)) ? $sanitized_orderby : 'created_at';
      
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
                    $txt.= '<br>' . esc_html($item["error"]) . "<br>";
                }
                if ($intentosfase1 < 8) {  // these are the ones it has taken                    
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
        // check if the post is published
        $id_post_published = $item['id_post_published'];
        if ($id_post_published != 0) {
            // get the post image url
            $post_thumbnail_id = get_post_thumbnail_id($id_post_published);
            $post_thumbnail_url = wp_get_attachment_image_src($post_thumbnail_id, 'thumbnail');
            if ($post_thumbnail_url) {
                return '<img src="' . esc_url($post_thumbnail_url[0]) . '" alt="Post Image" width="50">';
            }
        }

        // Render the image or fallback to text if invalid
        if (filter_var($item['aigenerated_image'], FILTER_VALIDATE_URL)) {
            return '<img src="' . esc_url($item['aigenerated_image']) . '" alt="Generated Image" width="50">';
        } else {
            return esc_html($item['aigenerated_image']);
        }
			
    }
	
	public function column_task_name($item) {
        if ($item['website_type']=="super2") {
            $txt = esc_html($item['task_name']);            
            $txt .= '<br><span style="color:blue;">' . esc_html($item['title_prompt']) . '</span>';
            return $txt;
        } else {
            return esc_html($item['task_name']);
        }
        
        
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
     

    $links = $wpdb->get_results($wpdb->prepare("SELECT link_post_original FROM {$wpdb->prefix}botwriter_logs WHERE id_task = %d ORDER BY id DESC LIMIT 50", $id_task), ARRAY_A);
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
    

    $results = $wpdb->get_results($wpdb->prepare("SELECT aigenerated_title FROM {$wpdb->prefix}botwriter_logs WHERE id_task = %d ORDER BY id DESC LIMIT 50", $id_task), ARRAY_A);
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
        'category_id',
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
