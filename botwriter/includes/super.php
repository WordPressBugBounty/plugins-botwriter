<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function botwriter_super_page_handler(){
  // Verify user has permission
  if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'botwriter'));
  }

  global $wpdb;
  $message = '';
  $notice = '';

  
  $is_editing = isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0;
  if ($is_editing) {
      $id = intval($_REQUEST['id']);
      $super1status=$id;
  } else {
    $super1status=botwriter_super1_check_task_finish();
  }




  $api_key=get_option('botwriter_api_key'); 
  // si las 2 primers letras son PK es que es una clave de pago
  $user_pro=false;
  if (substr($api_key, 0, 2) == 'PK') {
   $user_pro=true;
  } 

  
   
    $table_name = $wpdb->prefix . 'botwriter_tasks'; 
    $dir_images = plugin_dir_url(dirname(__FILE__)) . '/assets/images/';
  
    
?>
  <?php $super_mode = isset($_GET['mode']) && $_GET['mode'] === 'manual' ? 'manual' : 'auto'; ?>
  <input type="hidden" id="super1status" name="super1status" value="<?php echo esc_attr($super1status); ?>">
  <input type="hidden" id="super_mode" name="super_mode" value="<?php echo esc_attr($super_mode); ?>">
  <div class="wrap">


  <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>

  <?php // by default and  process the form data    
    $default = array(
        'id' => 0,
        /* translators: %d: next super task number */
        'task_name'             => sprintf(__('Super Task %d', 'botwriter'), $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE website_type LIKE 'super%'") + 1),
        'writer'                => 'ai_cerebro',  
        'narration'             => 'Descriptive',
        'custom_style'          => '',
        'post_language' => '',
        'post_length' => '800',
        'custom_post_length' => '',
        'post_status'      => 'publish',
        'days'             => '',
        'times_per_day'    => 1,
        'execution_count' => 0, // Initialize execution count to zero
        'last_execution_date' => gmdate('Y-m-d H:i:s', current_time('timestamp')), // Set last execution date to null initially
        'website_type'      => 'ai',

        'website_name'              => '',                
        'domain_name'              => '',
        'category_id'              => '0',
        'website_category_id'      => '',
        'website_category_name'      => '',
        'aigenerated_title'        => '',
        'aigenerated_content'      => '',
        'aigenerated_tags'         => '',
        'aigenerated_image'        => '',
        'post_count'               => '',
        'post_order'               => '',
        'title_prompt'             => '',
        'content_prompt'           => '',
        'tags_prompt'              => '',
        'image_prompt'             => '',
        'image_generating_status'  => '',
        'author_selection'         => '',

        'news_keyword'             => '',
        'news_country'             => '',
        'news_language'            => '', 
        'news_time_published'      => '',
        'news_source'              => '',
        'rss_source'               => '',
        'ai_keywords'              => '',
        'disable_ai_images'        => 0,
        'template_id'              => null,


      
        
    );


    if ( isset($_REQUEST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), basename(__FILE__))) {

      /*
      echo "<pre>";
      print_r($_POST);
      echo "</pre>";
      */

        $days = isset($_POST['days']) ? implode(",", array_map('sanitize_text_field', wp_unslash($_POST['days']))) : "";
        $times_per_day = isset($_POST['times_per_day']) ? intval(wp_unslash($_POST['times_per_day'])) : 1;

        
        // Process only the specific values needed
        $item = array(
            'id'                => isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0,

            'task_name'         => isset($_POST['task_name']) ? sanitize_text_field(wp_unslash($_POST['task_name'])) : '',
            'post_status'       => isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : '',
            'days'              => $days,
            'times_per_day'     => $times_per_day,                    
            'writer'            => 'ai_cerebro',
            'narration'         => isset($_POST['narration']) ? sanitize_text_field(wp_unslash($_POST['narration'])) : '',
            'post_length'       => isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : '',
            'custom_post_length'=> isset($_POST['custom_post_length']) ? sanitize_text_field(wp_unslash($_POST['custom_post_length'])) : '',
            'custom_style'      => isset($_POST['custom_style']) ? sanitize_text_field(wp_unslash($_POST['custom_style'])) : '',
            'post_language'     => isset($_POST['post_language']) ? sanitize_text_field(wp_unslash($_POST['post_language'])) : '',

            'website_type'      => 'super2',
            'website_name'      => '',
            'domain_name'       => isset($_POST['domain_name']) ? sanitize_url(wp_unslash($_POST['domain_name'])) : '',
            'category_id'       => isset($_POST['category_id']) ? sanitize_text_field(wp_unslash($_POST['category_id'])) : '0',
            'website_category_id'=> '',
            'website_category_name'=> '',
            'aigenerated_title'  => '',
            'aigenerated_content'=> '',
            'aigenerated_tags'   => '',
            'aigenerated_image'  => '',
            'post_count'         => 5,
            'post_order'         => '',
            'title_prompt'      => '',

            'content_prompt' => '',
            
            'tags_prompt'       => '',
            'image_prompt'      => '',
            'image_generating_status' => '',
            
            'author_selection'  => isset($_POST['author_selection']) ? sanitize_text_field(wp_unslash($_POST['author_selection'])) : '',

            'news_keyword'      => isset($_POST['news_keyword']) ? sanitize_text_field(wp_unslash($_POST['news_keyword'])) : '',
            'news_country'      => isset($_POST['news_country']) ? sanitize_text_field(wp_unslash($_POST['news_country'])) : '',
            'news_language'     => isset($_POST['news_language']) ? sanitize_text_field(wp_unslash($_POST['news_language'])) : '',
            'news_time_published' => isset($_POST['news_time_published']) ? sanitize_text_field(wp_unslash($_POST['news_time_published'])) : '',
            'news_source'       => isset($_POST['news_source']) ? sanitize_text_field(wp_unslash($_POST['news_source'])) : '',

            'rss_source'        => isset($_POST['rss_source']) ? sanitize_text_field(wp_unslash($_POST['rss_source'])) : '',
            'ai_keywords'       => isset($_POST['ai_keywords']) ? sanitize_text_field(wp_unslash($_POST['ai_keywords'])) : '',
            'disable_ai_images' => isset($_POST['disable_ai_images']) ? intval(wp_unslash($_POST['disable_ai_images'])) : 0,
            'template_id'       => isset($_POST['template_id']) && !empty($_POST['template_id']) ? intval(wp_unslash($_POST['template_id'])) : null

        );
        
        

        
          

          //$item_valid = botwriter_validate_website($item);
          $item_valid = true;
          if ($item_valid === true) {
            
            
            //$item = array_map('sanitize_text_field', $item); // Sanitize all inputs
              if ($item['id'] == 0) {
                  // Do not insert primary key 'id' explicitly to allow AUTO_INCREMENT to work
                  $insert_data = $item;
                  unset($insert_data['id']);
                  $result = $wpdb->insert($table_name, $insert_data);
                  
                  //echo "<h1>" . $wpdb->last_error . "</h1>  ";

                  $item['id'] = $wpdb->insert_id;



                  if ($result) {
                      $message = __('New task was successfully saved!', 'botwriter');
                      // asignamos el id de la tarea
                      botwriter_super_assign_id_task($item['id']);
                      // y borramos el log, solo puede haber 1 tarea super1
                      $wpdb->delete($wpdb->prefix . 'botwriter_logs', array('website_type' => 'super1'));
                      ?>
                       <div id="redirecion" data-url="<?php echo esc_url( admin_url('admin.php?page=botwriter_automatic_posts') ); ?>"></div>                    
                      <?php
                      
                      
                  } else {
                      $notice = __('There was an error while saving item', 'botwriter');
                  }
              } else {
                  $result = $wpdb->update($table_name, $item, array('id' => $item['id']));
                   
        
               if ($result !== false) {
                    if ($result === 0) {
                      $message = __('It was successfully updated', 'botwriter');                                                                  
                      ?>
                        <div id="redirecion" data-url="<?php echo esc_url( admin_url('admin.php?page=botwriter_automatic_posts') ); ?>"></div>
                      <?php
                      
                    } else {
                        $message = __('It was successfully updated!', 'botwriter');                                            
                      ?>
                        <div id="redirecion" data-url="<?php echo esc_url( admin_url('admin.php?page=botwriter_automatic_posts') ); ?>"></div>
                      <?php
                    }
                } else {
                        $notice = __('There was an error while updating item: ', 'botwriter') . $wpdb->last_error;
                }
              }
          } else {
              
              $notice = $item_valid;
          }
    }
    else {
        
      $item = $default;
      if (isset($_REQUEST['id'])) {
          $sanitized_id = absint($_REQUEST['id']); // Sanitize as an integer
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $sanitized_id), ARRAY_A);
          if (!$item) {
              $item = $default;
              $notice = __('Item not found', 'botwriter');
          }
      }
      
    }
    // end of process the form data
    ?>


    
    <?php     
    $title_text = $is_editing ? __('Edit Super Task', 'botwriter') : __('Add New Super Task', 'botwriter');
    ?>
    <h2><?php echo esc_html($title_text); ?> <a class="add-new-h2"
      href="<?php echo esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_automatic_posts')); ?>"><?php esc_html_e('Back to List', 'botwriter'); ?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo esc_attr($notice) ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo esc_attr($message) ?></p></div>
    <?php endif;?>
    

    <?php if (botwriter_super1_check_first_time()){ ?>      
    
      <div class="super-task-welcome-box">
      <h3>First Time! Welcome to the Super Task AI!</h3>
      <p>Super Task AI is a powerful tool that allows you to create a large number of articles in a few seconds. You can choose from predefined article packs or create a custom pack. The AI will generate titles and summaries for your articles based on your blog content. You can customize the prompts or use the default ones.</p>
      <h4>The first time Botwriter proposes some articles to improve your blog, review them below and save the task.</h4>
      </div>
      
    <?php } ?>

      <?php if (!$is_editing) { ?>
       
        <div class="super-content">
          <h2 class="super-title"><?php echo $super_mode === 'manual' ? 'Manual titles for articles' : 'Creation of titles and summaries for articles'; ?></h2>
          <p class="super-text"><?php echo $super_mode === 'manual' ? 'Paste your list of titles and the AI will write the articles.' : 'Choose a predefined one or customize.'; ?></p>

          <div id="new_supertask">
          <div class="super-ia">      


            <div class="super-ia-image">
            <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI', 'botwriter'); ?>"<?php echo $super_mode === 'manual' ? ' style="filter: hue-rotate(90deg);"' : ''; ?>>
            </div>

            <form id="form_super1" name="form_super1">
            <div class="col-md-6">
              <label for="super1">Select the type of article pack:</label> 
              <select id="super1_prompt" name="super1_prompt" onchange="toggleCustomPromptInput()">
                <option value="Pack of Blog Improvement Articles">Pack of Blog Improvement Articles</option>
                <option value="Pack of Tutorial/Step-by-Step Articles">Pack of Tutorial/Step-by-Step Articles</option>
                <option value="Pack of Tips, Tricks, and Recommendations Articles">Pack of Tips, Tricks, and Recommendations Articles</option>
                <option value="Pack of Reviews, Buying Guides, etc.">Pack of Reviews, Buying Guides, etc.</option>              
                <option value="Custom">Custom Pack</option>
                <option value="Manual"<?php echo $super_mode === 'manual' ? ' selected' : ''; ?>>Manual Titles</option>                              
              </select>                            
              <p class="form-text">Choose a pack and the AI will automatically propose articles based on your blog content. Choose Custom Pack for a custom prompt, or Manual Titles to paste your own list.</p>
              
            </div>
            

            <div class="col-md-6" id="customPromptInput" style="display: none;">
                <label for="custom_prompt">Custom Prompt:</label>
                <textarea id="super1_custom_prompt" name="super1_custom_prompt" rows="4" cols="50" placeholder="For example: Articles about pet care or Articles about the solar system, etc"></textarea>
                <br><br>
            </div>

            <div class="col-md-6" id="manualTitlesInput" style="display: none;">
                <label for="manual_titles">List of Titles (one per line):</label>
                <textarea id="super1_manual_titles" name="super1_manual_titles" rows="10" cols="50" placeholder="How to train your dog&#10;Best recipes for beginners&#10;10 tips for better sleep&#10;..."></textarea>
                <br><br>
                <label for="manual_global_prompt">Global Instructions/Prompt (Optional):</label>
                <textarea id="super1_manual_global_prompt" name="super1_manual_global_prompt" rows="4" cols="50" placeholder="Optional: Write in a friendly tone, include practical examples, etc."></textarea>
                <br><br>
            </div>
            

            <div class="col-md-6" id="div_category_id"  style="display: none;">
                <label for="category_id" class="form-label">Category:</label>
                <select id="category_id" name="category_id" required class="form-select">
                    <?php
                    $selected_category = 0;                    
                    $categories = get_categories(array(
                        'orderby' => 'name',
                        'order' => 'ASC',
                        'hide_empty' => false
                    ));

                    foreach ($categories as $category) {
                      if (isset($selected_categories) && in_array($category->term_id, $selected_categories)) {
                        echo '<option value="' . esc_attr($category->term_id) . '" selected>' . esc_html($category->name) . '</option>';
                        continue;
                      }
                      echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                    }
                    ?>
                </select>
                <p class="form-text">Select one category where the posts will be published.</p>
                
            </div>
              
              
              <div class="col-md-6" id="div_numarticles">
                <label for="super1number">Number of Articles (1-50max):</label>
                <input type="number" id="super1_numarticles" name="super1_numarticles" value="10" min="1" max="50"><br>            
                
              </div>
                <br>
              <button id="super1button_createtask" name="super1button_createtask">Create Titles</button>
              
              <!-- Free tier warning for Super Tasks -->
              <div class="bw-supertask-tier-warning">
                <span class="dashicons dashicons-warning"></span>
                <div>
                  <strong><?php esc_html_e('Free Tier Limitations', 'botwriter'); ?></strong>
                  <p><?php esc_html_e('Super Tasks require generating multiple articles in sequence. Free tier API plans (Google, Mistral, Groq, OpenRouter) may have rate limits or quotas that cause failures.', 'botwriter'); ?></p>
                  <p><?php esc_html_e('For best results:', 'botwriter'); ?></p>
                  <ul>
                    <li><?php esc_html_e('Use a paid API plan for your text provider', 'botwriter'); ?></li>
                    <li><?php esc_html_e('Or use regular scheduled tasks instead of Super Tasks', 'botwriter'); ?></li>
                  </ul>
                </div>
              </div>
                          
            </form>
          </div>

        </div>
        <div id="label countup"></div><div id='countup'></div>
        
       </div>
      <?php } ?>



  <div id='resultados'>
    <?php 
    // si es editing que sea vea el html
    if ($is_editing) {
      
      echo botwriter_super1_view_articles_html($id);
    }
    ?>  
  </div>
  


    <?php

    
    add_meta_box('botwriter_super_form_meta_box', __('Super Task Properties', 'botwriter'), 'botwriter_super_form_meta_box_handler', 'botwriter_super_post_new', 'normal', 'default');

    if ($is_editing) {      
      $display_form_part2 = 'block';
    } else {
      $display_form_part2 = 'none';
    }
    ?>
  
  <div id="form_part2" style="display: <?php echo esc_attr($display_form_part2); ?>;">    
      <form id="form" method="POST">
          <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(basename(__FILE__))); ?>"/>        
          <input type="hidden" name="id" value="<?php echo esc_attr($item['id']) ?>"/>
          <div class="metabox-holder" id="poststuff">
              <div id="post-body">
                  <div id="post-body-content">                    
                      <?php do_meta_boxes('botwriter_super_post_new', 'normal', $item); ?>
                      <input type="submit" value="<?php esc_attr_e('Save', 'botwriter')?>" id="submit" class="button-primary" name="submit" onclick="preSelectedOptions()">
                  </div>
              </div>
          </div>
      </form>
  </div>
</div>


<?php
}
 



  

function botwriter_super_form_meta_box_handler($item)
{
    Global $botwriter_languages, $botwriter_countries;

    $selected_days = isset($item["days"]) ? explode(",", $item["days"]) : array();
    $times_per_day = isset($item["times_per_day"]) ? $item["times_per_day"] : 1;

    $dir_images_writers = plugin_dir_url(dirname(__FILE__)) . '/assets/images/writers/';
    $dir_images_icons = plugin_dir_url(dirname(__FILE__)) . '/assets/images/icons/';
?>
    <div id="loading">
        <div class="loader"> 
            <div class="inner one"></div>
            <div class="inner two"></div>
            <div class="inner three"></div>
        </div>
    </div>

    <form> 
        <div class="form2bc">
            <div class="container">
                <?php
                $botwriter_admin_email = botwriter_get_admin_email();
                $botwriter_domain_name = esc_url(get_site_url());
                ?>

                <input type="hidden" id="botwriter_admin_email" value="<?php echo esc_attr($botwriter_admin_email); ?>">
                <input type="hidden" id="botwriter_domain_name" value="<?php echo esc_attr($botwriter_domain_name); ?>">
                
                <div class="col-md-6">
                    <label class="form-label">Task Name:</label>
                    <input id="task_name" name="task_name" type="text" class="form-control" value="<?php echo esc_attr($item['task_name']); ?>" required>
                </div>
                <br>

                <div class="col-md-6">
                    <label class="form-label">Author Selection</label>
                    <select name="author_selection" class="form-select">
                        <?php
                        $authors = get_users();
                        foreach ($authors as $author) {
                            $author_id = $author->ID;
                            $author_name = $author->display_name;
                            $selected = ($item['author_selection'] == strval($author_id)) ? 'selected' : '';
                            echo "<option value='" . esc_attr($author_id) . "' " . esc_attr($selected) . ">" . esc_html($author_name) . "</option>";
                        }
                        ?>
                    </select>
                    <p class="form-text">Select an author from the list.</p>
                </div>

                <div class="col-md-6">
                    <label for="post_status" class="form-label">Post Status:</label>
                    <select id="post_status" name="post_status" class="form-select">        
                        <option value="publish" <?php selected($item['post_status'], 'publish'); ?>>Publish</option>
                        <option value="draft" <?php selected($item['post_status'], 'draft'); ?>>Draft</option>
                    </select>      
                    <p class="form-text">Select the status of the post. Choose 'Draft' if you want to revise it before publishing.</p>      
                </div>

                <div class="col-md-6">
                    <label class="form-label">Days of the Week:</label><br>
                    <?php 
                    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days_of_week as $day) {                                
                        $is_checked = in_array($day, $selected_days) ? 'checked' : '';
                        echo "<input type='checkbox' name='days[]' value='" . esc_attr($day) . "' " . esc_attr($is_checked) . "> " . esc_html($day) . "<br>";
                    }
                    ?>
                    <p class="form-text">Select the days on which you want to write and publish.</p>            
                </div>

                <div class="col-md-6">
                    <label class="form-label">Post per Day:</label>
                    <input type="number" name="times_per_day" min="1" value="<?php echo esc_attr($times_per_day); ?>" required>
                </div>

                <div class="col-md-6">
                    <div class="bw-schedule-tip">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php esc_html_e('This schedule runs only until all articles in the task are published. For example, if you have 3 articles and set 1 post per day on weekdays, they will be published over 3 days and then the task will stop automatically.', 'botwriter'); ?></span>
                    </div>
                </div>
                <br>

                <?php
                $default_language_code = substr(get_locale(), 0, 2);
                if (empty($item['post_language'])) {
                    $item['post_language'] = $default_language_code;
                }
                ?>

                <div class="col-md-6">
                    <label for="post_language" class="form-label">Post Language:</label>
                    <select class="form-select" id="post_language" name="post_language">
                        <?php
                        foreach ($botwriter_languages as $code => $name) {
                            $selected = ($item['post_language'] == $code) ? 'selected' : '';
                            echo "<option value='" . esc_attr($code) . "' " . esc_attr($selected) . ">" . esc_html($name) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <br>

                <!-- Post Length -->
                <div class="col-md-6">
                    <label class="form-label">Post Length:</label>
                    <select id="post_length" name="post_length" class="form-select" onchange="toggleCustomLengthInput()">
                        <option value="400" <?php selected($item['post_length'], 400); ?>>Short (400 words)</option>
                        <option value="800" <?php selected($item['post_length'], 800); ?>>Medium (800 words)</option>
                        <option value="1600" <?php selected($item['post_length'], 1600); ?>>Long (1600 words)</option>
                        <option value="custom" <?php echo (!in_array($item['post_length'], [400, 800, 1600])) ? 'selected' : ''; ?>>Custom</option>
                    </select>
                    <p class="form-text">Select the desired post length or choose custom to enter a specific number of words.</p>
                </div>
                <!-- End Post Length -->

                <!-- Custom Length Input -->
                <div class="col-md-6" id="customLengthInput" style="display: <?php echo (!in_array($item['post_length'], [400, 800, 1600])) ? 'block' : 'none'; ?>;">
                    <label class="form-label">Custom Length (max 4000):</label>
                    <input id="custom_post_length" name="custom_post_length" type="number" class="form-control" value="<?php echo esc_html($item['custom_post_length']); ?>" onchange="updatePostLength()">
                    <p class="form-text">Enter the number of words you want the post to have.</p>
                </div>
                <br>

                <!-- Template Selection -->
                <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e('Prompt Template:', 'botwriter'); ?></label>
                    <select id="template_id" name="template_id" class="form-select">
                        <?php
                        $templates = botwriter_get_all_templates();
                        $selected_template_id = isset($item['template_id']) && !empty($item['template_id']) ? intval($item['template_id']) : null;
                        
                        // If no template selected, find the default one
                        if ($selected_template_id === null) {
                            foreach ($templates as $tpl) {
                                if (!empty($tpl['is_default'])) {
                                    $selected_template_id = intval($tpl['id']);
                                    break;
                                }
                            }
                        }
                        
                        foreach ($templates as $tpl) {
                            $selected = ($selected_template_id == $tpl['id']) ? 'selected' : '';
                            $label = esc_html($tpl['name']);
                            if (!empty($tpl['is_default'])) {
                                $label .= ' ★';
                            }
                            echo '<option value="' . esc_attr($tpl['id']) . '" ' . $selected . '>' . $label . '</option>';
                        }
                        ?>
                    </select>
                    <p class="form-text"><?php esc_html_e('Select the prompt template to use for content generation. Templates marked with ★ are the default.', 'botwriter'); ?></p>
                </div>
                <br>

                <!-- Disable AI Images -->
                <div class="col-md-6">
                    <?php 
                    $image_provider = get_option('botwriter_image_provider', 'dalle');
                    $force_disable_images = ($image_provider === 'none');
                    ?>
                    <label class="form-label"><?php esc_html_e('AI Image Generation:', 'botwriter'); ?></label>
                    <input type="hidden" name="disable_ai_images" value="<?php echo $force_disable_images ? '1' : '0'; ?>">
                    <input type="checkbox" name="disable_ai_images" value="1" id="disable_ai_images_checkbox"
                           <?php checked($force_disable_images || $item['disable_ai_images'], 1); ?>
                           <?php echo $force_disable_images ? 'disabled' : ''; ?>>
                    <span><?php esc_html_e('Disable AI image generation for this task', 'botwriter'); ?></span>
                    <?php if ($force_disable_images): ?>
                    <p class="form-text bw-text-error bw-disabled-images-warning"><span class="dashicons dashicons-info bw-icon-small"></span> <?php esc_html_e('Image generation is globally disabled. Change the Image Provider in Settings to enable it.', 'botwriter'); ?></p>
                    <?php else: ?>
                    <p class="form-text" id="disable_ai_images_description"><?php esc_html_e('Check this option to generate only text articles without AI-generated images for this specific task.', 'botwriter'); ?></p>
                    <?php endif; ?>
                    <?php 
                    $is_checked = $force_disable_images || $item['disable_ai_images'];
                    $tip_style = $is_checked ? '' : 'display:none;';
                    ?>
                    <p class="form-text bw-asi-inline-tip" id="bw_asi_tip" style="<?php echo esc_attr($tip_style); ?>"><span class="dashicons dashicons-images-alt2"></span> <?php printf(
                        /* translators: %s: link to All Sources Images plugin */
                        esc_html__('Tip: Use %s to add free images from Pexels, Unsplash & Pixabay automatically.', 'botwriter'),
                        '<a href="' . esc_url(admin_url('plugin-install.php?s=all+sources+images&tab=search&type=term')) . '" target="_blank">All Sources Images</a>'
                    ); ?></p>
                </div>
                <br>

            </div> <!-- Fin div class="container" -->
        </div> <!-- Fin div class="form2bc" -->
    </form>
<?php
} // End of the function


function botwriter_get_info_blog() {
  // Obtener título y descripción del blog
  $blog_title       = get_bloginfo('name');
  $blog_description = get_bloginfo('description');

  // Obtener solo las categorías de WordPress (excluyendo las de WooCommerce)
  $categories = get_categories(array(
      'hide_empty' => false, // Obtener también las categorías sin posts
      'taxonomy'   => 'category' // Asegura que solo obtenemos categorías de posts, no de productos
  ));

  $cats = array();

  if (!empty($categories)) {
      foreach ($categories as $cat) {
          // Configurar argumentos para obtener los últimos 5 posts de la categoría actual
          $args = array(
              'post_type'      => 'post', // Solo posts, no productos ni otros CPTs
              'post_status'    => 'publish',
              'cat'            => $cat->term_id, // Filtrar por el ID de la categoría
              'posts_per_page' => 5,
              'orderby'        => 'date',
              'order'          => 'DESC'
          );
          $query = new WP_Query($args);
          $posts = array();

          if ($query->have_posts()) {
              while ($query->have_posts()) {
                  $query->the_post();
                  $posts[] = array(
                      'title'       => get_the_title(),
                      'description' => get_the_excerpt(), // Para contenido completo usar get_the_content()
                      'date'        => get_the_date()       // Fecha del post
                  );
              }
              wp_reset_postdata(); // Importante para restaurar el loop principal
          }

          // Agregar la categoría incluso si no tiene posts
          $cats[] = array(
              'id'          => $cat->term_id,
              'name'        => $cat->name,              
              'description' => $cat->description,
              'posts'       => $posts // Se agrega vacío si no tiene posts
          );
      }
  }

  // Obtener páginas publicadas
  $pages_args = array(
      'post_type'      => 'page',
      'post_status'    => 'publish',
      'posts_per_page' => 10,
      'orderby'        => 'date',
      'order'          => 'DESC'
  );
  $pages_query = new WP_Query($pages_args);
  $pages = array();

  if ($pages_query->have_posts()) {
      while ($pages_query->have_posts()) {
          $pages_query->the_post();
          // Obtener el excerpt, y si no existe, tomar los primeros 200 caracteres del contenido
          $excerpt = get_the_excerpt();
          if (empty($excerpt)) {
              $content = wp_strip_all_tags(get_the_content()); // Quitar etiquetas HTML del contenido
              $excerpt = substr($content, 0, 250); // Tomar los primeros 200 caracteres
              if (strlen($content) > 250) {
                  $excerpt .= '...'; // Agregar puntos suspensivos si el contenido es más largo
              }
          }

          $pages[] = array(
              'id'          => get_the_ID(),
              'title'       => get_the_title(),
              'description' => $excerpt, // Usar el excerpt o los primeros 200 caracteres
              //'date'        => get_the_date(),    // Fecha de creación
              //'modified'    => get_the_modified_date(), // Fecha de última modificación
              //'slug'        => get_post_field('post_name') // Slug de la página
          );
      }
      wp_reset_postdata(); // Restaurar el loop principal
  }

  // Armar el array con la información completa del blog
  $info_blog = array(
      'title'       => $blog_title,
      'description' => $blog_description,
      'categories'  => $cats,
      'pages'       => $pages // Nueva sección para las páginas
  );

  return $info_blog;
}

function botwriter_super1_create_first_task() {
  $info_blog = botwriter_get_info_blog();
  $json_info_blog = json_encode($info_blog, JSON_PRETTY_PRINT);
  $content_prompt = $json_info_blog;
  botwriter_super1_create_task('FIRSTSUPER1',"", $content_prompt);
}

// create a log not task, only 1 for titles
function botwriter_super1_create_task($task_name, $title_prompt, $content_prompt,$post_count=10,$category_id=0) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'botwriter_logs';
  $current_time = gmdate('Y-m-d H:i:s', current_time('timestamp'));

  botwriter_log('Initializing super1 task log', [
      'task_name' => $task_name,
      'post_count' => $post_count,
      'category_id' => $category_id,
  ]);

// default values
$data = array(
    'id'                      => 1,
    'id_task'                 => 0,    
    'created_at'              => '',
    'last_execution_time'     => $current_time = gmdate('Y-m-d H:i:s', current_time('timestamp')),
    'intentosfase1'           => 0,
    'intentosfase2'           => 0,
    'task_status'             => '',
    'error'                   => '',
    'link_post_original'      => '',
    'id_post_published'       => 0,
    'post_status'             => 'draft',
    'task_name'               => $task_name, 
    'writer'                  => 'ai_cerebro', 
    'narration'               => 'Descriptive',
    'custom_style'            => '',
    'post_language'           => substr(get_locale(), 0, 2),
    'post_length'             => 2000,
    'custom_post_length'      => '',
    'website_name'            => '',
    'website_type'            => 'super1',
    'domain_name'             => '',
    'category_id'             => $category_id,
    'website_category_id'     => '',
    'aigenerated_title'       => '',
    'aigenerated_content'     => '',
    'aigenerated_tags'        => '',
    'aigenerated_image'       => '',
    'post_count'              => $post_count,
    'post_order'              => '',
    'title_prompt'            => $title_prompt,
    'content_prompt'          => $content_prompt,
    'tags_prompt'             => '',
    'image_prompt'            => '',
    'image_generating_status' => '',
    'author_selection'        => 1,
    'news_time_published'     => '',
    'news_language'           => '',
    'news_country'            => '',
    'news_keyword'            => '',
    'news_source'             => '',
    'rss_source'              => '',
    'ai_keywords'             => '',
    'version'                 => '',
    'api_key'                 => '', 
    'user_domainname'         => '',
    'ai_image_size'           => '',
    'titles'                  => '',
    'links'                   => '',
);
  
  $event=$data;                                                       
  $event["task_status"] = "pending";            
  $event["id_task"] = $data["id"];              
  $event["intentosfase1"] = 0;
  $id_log=botwriter_logs_register($event);  // create log in db
  $event["id"]=$id_log;
  botwriter_log('Super1 log registered', [
      'log_id' => $id_log,
      'task_name' => $task_name,
  ]);
  
  botwriter_send1_data_to_server( (array) $event);                                      
  return $id_log;
}

 
function botwriter_super1_log_to_bd($result,$id_log) {
  global $wpdb;    
    $table_name = $wpdb->prefix . 'botwriter_super';
    $aigenerated_content = $result["aigenerated_content"];
    $title_prompt = $result["title_prompt"];
    
    try {                  
      $data_array=json_decode($aigenerated_content, true);
    } catch (Exception $e) {
         echo "Error: " . esc_html($e->getMessage());
    }
    
    if (!is_array($data_array)) {
      botwriter_log('Super1 JSON decode failed', [
          'log_id' => $id_log,
      ]);
      return false; // Or throw an exception as needed
    }

    /*
    echo "<h1>Finish Task titulos etc</h1>"; 
    echo "<pre>";
    print_r($data_array);
    echo "</pre>"; 
    */

botwriter_log('Persisting super1 outlines', [
  'log_id' => $id_log,
  'articles' => count($data_array),
]);

foreach ($data_array as $post) {
  // Verifica si el artículo ya existe para evitar duplicados (por título)
  $existing_post = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table_name WHERE title = %s",
      $post['title']
  ));



  if (!$existing_post) {
      if ($title_prompt!="Custom") {        
        $data = array(
            'title' => $post['title'],
            'content' => $post['resumen'],
            'category_name' => $post['categoria'],
            'category_id' => $post['categoria_id'],
            'id_log' => $id_log
        );
      } else {
        $data = array(
          'title' => $post['title'],
          'content' => $post['resumen'],
          'category_name' => get_cat_name($result['category_id']),
          'category_id' => $result['category_id'],
          'id_log' => $id_log
      );
      }

    $wpdb->insert($table_name, $data);
    botwriter_log('Super1 outline stored', [
      'log_id' => $id_log,
      'title' => $post['title'],
    ]);
  } else {
    botwriter_log('Super1 outline skipped (duplicate)', [
      'log_id' => $id_log,
      'title' => $post['title'],
    ]);
  }
}


 

}

function botwriter_super1_view_articles_html($id_task=0) {



  global $wpdb;
  $table_name = $wpdb->prefix . 'botwriter_super';
  $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE id_task = %d ORDER BY category_id", $id_task), ARRAY_A);
  //

  $html = '<a href="javascript:void(0);" onclick="botwriter_reset_super1();" class="button-primary">Reset Task and Try Again</a>';
  $contador = 0;
  $auxcategoria = "";

  foreach ($posts as $post) {
    $task_status = $post['task_status'];
    if ($auxcategoria != $post['category_name']) {
      $html .= '<h2>' . esc_html($post['category_name']) . '</h2>';
    }

    $html .= '<div class="super-article" data-id="' . esc_attr($post['id']) . '">';
    $html .= '<div class="super-number">' . ++$contador . '</div>';
    $html .= '<div class="super-content">';
    $html .= '<h2 class="super-title">' . esc_html($post['title']) . '</h2>';
    $html .= '<p class="super-text">' . esc_html($post['content']) . '</p>';
    $html .= '</div>';
    if ($task_status == 'completed') {
      $html .= '<div class="super-status">Completed ✅</div>';
    } 
    // si esta en cola
    if ($task_status == 'inqueue') {
      $html .= '<div class="super-status">In Queue 🔄</div>';
    }
    // si hay un error
    if ($task_status == 'error') {
      $html .= '<div class="super-status">Error ❌</div>';
    }

    // si es blanco o null
    if ($task_status == '') {     
      $html .= '<div class="super-actions">';
      $html .= '<button class="super-edit" data-id="' . esc_attr($post['id']) . '">✏️</button>';
      $html .= '<button class="super-delete" data-id="' . esc_attr($post['id']) . '">❌</button>';
      $html .= '</div>';
    }


    $html .= '</div>';

    $auxcategoria = $post['category_name'];
  }

  return $html;
}


function botwriter_super1_check_task_exist() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'botwriter_logs';
  $task = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE website_type = %d",
    'super1'
  ));
  if ($task) { 
      return true;  
  } else {
    return false;
  }    
}


// crea una funcion que mire en la tabla task si la tarea super esta completeda (la ultima que se ha creado)
function botwriter_super1_check_task_finish() {
  global $wpdb; 
  $table_name = $wpdb->prefix . 'botwriter_logs';
  $task = $wpdb->get_row("SELECT * FROM $table_name WHERE website_type = 'super1' order by id desc limit 1");

  if ($task) {
    if ($task->task_status == 'completed') {
      botwriter_log('Super1 task status completed', [
          'log_id' => $task->id,
      ]);
      return 'completed';
    }
    
    if ($task->task_status == 'error') {
      botwriter_log('Super1 task status error', [
          'log_id' => $task->id,
      ]);
      return 'error';      
    }
    
    if ($task->task_status == 'pending' || $task->task_status == 'inqueue') {      
      botwriter_log('Super1 task still pending', [
          'log_id' => $task->id,
          'status' => $task->task_status,
      ]);
      botwriter_execute_events_pass2();
      return 'inqueue';
    }
  }

    
  botwriter_log('Super1 task status unknown');
  return "";
}



function botwriter_super_prepare_event($event) {

  global $wpdb;
  $table_name = $wpdb->prefix . 'botwriter_super';
  $articulo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id_task = %d AND (task_status IS NULL OR task_status = '') ORDER BY category_id", $event["id_task"]), ARRAY_A);

  /*
  echo "<h1>Prepare Event</h1>";
  echo "<pre>";
  print_r($articulo);
  echo "</pre>";
  */
  
  

  if (!empty($articulo['title'])) {
    botwriter_log('Super2 outline selected', [
        'task_id' => $event['id_task'],
        'log_id' => $event['id'],
        'article_id' => $articulo['id'],
    ]);
    // For rewriter/siterewriter tasks, preserve the original content_prompt
    // (rewrite instructions) before overwriting with the article content.
    $task_type = $event['task_type'] ?? '';
    if (in_array($task_type, ['rewriter', 'siterewriter'], true) && !empty($event['content_prompt'])) {
        $event['rewrite_prompt'] = $event['content_prompt'];
    }
    $event['content_prompt'] = $articulo['content'];
    $event['title_prompt'] = $articulo['title'];
    $event['category_id'] = $articulo['category_id'];
    $id = $articulo['id'];
    $id_log=$event["id"];
    // actualizamos el status y el id_log con el nuevo id_log
    $wpdb->update($table_name, array('id_log' => $id_log, 'task_status' => 'inqueue'), array('id' => $id));

    
  } else {
    botwriter_log('Super2 outline not found', [
        'task_id' => $event['id_task'],
        'log_id' => $event['id'],
    ]);
    return false;
  }


  return $event;
}

function botwriter_super_assign_id_task($id_task) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'botwriter_super';
  // update the super table with the id_task for those with id_task 0
  $wpdb->update($table_name, array('id_task' => $id_task), array('id_task' => 0));
  
  return $id_task;
}

function botwriter_super1_check_first_time() {
  global $wpdb; 
  $table_name = $wpdb->prefix . 'botwriter_logs';
  $task = $wpdb->get_row("SELECT * FROM $table_name WHERE website_type = 'super1' AND task_name = 'FIRSTSUPER1'");
  if ($task) {
    return true;
  } else {
    return false;
  }      
}