<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function botwriter_addnew_page_handler(){
  // Verify permissions
  if (!current_user_can('manage_options')) {
      return;
  }
  
  $dir_images = plugin_dir_url(dirname(__FILE__)) . '/assets/images/';

  $add_new_url_automatic_post_new = esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_automatic_post_new'));

  $add_new_url_super_page = esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_super_page'));
  


?>
  <div class="super-ia">      
      <div>
      <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI BRAIN', 'botwriter'); ?>" >
      </div>
      <div class="super-content">
            <h2 class="super-title">Super Tasks AI (ARTICLE PACKS)</h2> 
            <br>          
            <ul>
              <li>Pack of Blog Improvement Articles</li>
              <li>Pack of Tutorial/Step-by-Step Articles</li>
              <li>Pack of Tips, Tricks, and Recommendations Articles</li>
              <li>Pack of Reviews & Buying Guides.</li>
                <li>Customized Thematic Articles, etc.</li>              
                <li><a class="add-new-h2" href="<?php echo esc_url($add_new_url_super_page); ?>"><?php esc_html_e('Add new', 'botwriter'); ?></a></li>
            </ul>          
      </div>      
  </div>
  <br><br>
  <div class="super-ia">      
      <div>
      <img src="<?php echo esc_url($dir_images . 'robot_icon2.png'); ?>" alt="<?php echo esc_attr__('AI BOT', 'botwriter'); ?>" >
      </div>
      <div class="super-content">
          <h2 class="super-title">Tasks AI</h2>
          <br>
          <ul>
              <li>Articles from Prompt</li>
              <li>Articles from Keywords</li>              
              <li>Articles from RSS Feed</li>
              <li>Articles from External Wordpress</li>              
                <li><a class="add-new-h2" href="<?php echo esc_url($add_new_url_automatic_post_new); ?>"><?php esc_html_e('Add new', 'botwriter'); ?></a></li>                     
            </ul>                    
      </div>      
  </div>

<?php
}
