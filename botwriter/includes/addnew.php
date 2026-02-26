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
  $add_new_url_super_manual = esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_super_page&mode=manual'));
  $add_new_url_rewriter = esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_rewriter_page'));
  $add_new_url_siterewriter = esc_url(get_admin_url(get_current_blog_id(), 'admin.php?page=botwriter_siterewriter_page'));
  

?>
<div class="wrap">
  <h1><?php esc_html_e('Create New Task', 'botwriter'); ?></h1>
  <p class="bw-addnew-subtitle"><?php esc_html_e('Choose how you want to generate content with AI.', 'botwriter'); ?></p>

  <div class="bw-addnew-grid">

    <!-- Option 1: Standard Task -->
    <div class="bw-addnew-card bw-addnew-card--blue">
      <div class="bw-addnew-card-header">
        <div class="bw-addnew-card-titles">
          <span class="bw-addnew-badge bw-addnew-badge--blue"><?php esc_html_e('Scheduled', 'botwriter'); ?></span>
          <h2 class="bw-addnew-card-title"><?php esc_html_e('Standard Task', 'botwriter'); ?></h2>
        </div>
        <div class="bw-addnew-card-icon">
          <img src="<?php echo esc_url($dir_images . 'robot_icon2.png'); ?>" alt="<?php echo esc_attr__('AI BOT', 'botwriter'); ?>">
        </div>
      </div>
      <div class="bw-addnew-card-body">
        <p class="bw-addnew-card-desc"><?php esc_html_e('Create scheduled tasks to generate articles from prompts, keywords, RSS feeds, or external WordPress sites.', 'botwriter'); ?></p>
        <ul class="bw-addnew-card-features">
          <li><?php esc_html_e('Articles from Prompt (Scheduled)', 'botwriter'); ?></li>
          <li><?php esc_html_e('Articles from Keywords', 'botwriter'); ?></li>
          <li><?php esc_html_e('Articles from RSS Feed', 'botwriter'); ?></li>
          <li><?php esc_html_e('Articles from External WordPress', 'botwriter'); ?></li>
        </ul>
      </div>
      <div class="bw-addnew-card-footer">
        <a class="button button-primary bw-addnew-btn" href="<?php echo esc_url($add_new_url_automatic_post_new); ?>"><?php esc_html_e('Create Standard Task', 'botwriter'); ?> →</a>
      </div>
    </div>

    <!-- Option 2: Auto-pilot Super Task -->
    <div class="bw-addnew-card bw-addnew-card--purple">
      <div class="bw-addnew-card-header">
        <div class="bw-addnew-card-titles">
          <span class="bw-addnew-badge bw-addnew-badge--purple"><?php esc_html_e('Auto-Pilot', 'botwriter'); ?></span>
          <h2 class="bw-addnew-card-title"><?php esc_html_e('Super Task AI', 'botwriter'); ?></h2>
        </div>
        <div class="bw-addnew-card-icon">
          <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI BRAIN', 'botwriter'); ?>">
        </div>
      </div>
      <div class="bw-addnew-card-body">
        <p class="bw-addnew-card-desc"><?php esc_html_e('Generate a complete series of articles automatically. The AI creates titles, summaries, and content for you.', 'botwriter'); ?></p>
        <ul class="bw-addnew-card-features">
          <li><?php esc_html_e('Blog Improvement Articles', 'botwriter'); ?></li>
          <li><?php esc_html_e('Tutorial & Step-by-Step Packs', 'botwriter'); ?></li>
          <li><?php esc_html_e('Tips, Tricks & Recommendations', 'botwriter'); ?></li>
          <li><?php esc_html_e('Reviews & Buying Guides', 'botwriter'); ?></li>
        </ul>
      </div>
      <div class="bw-addnew-card-footer">
        <a class="button button-primary bw-addnew-btn" href="<?php echo esc_url($add_new_url_super_page); ?>"><?php esc_html_e('Create Super Task', 'botwriter'); ?> →</a>
      </div>
    </div>

    <!-- Option 3: Manual Super Task -->
    <div class="bw-addnew-card bw-addnew-card--green">
      <div class="bw-addnew-card-header">
        <div class="bw-addnew-card-titles">
          <span class="bw-addnew-badge bw-addnew-badge--green"><?php esc_html_e('BYO Titles', 'botwriter'); ?></span>
          <h2 class="bw-addnew-card-title"><?php esc_html_e('Manual Super Task', 'botwriter'); ?></h2>
        </div>
        <div class="bw-addnew-card-icon">
          <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI BRAIN MANUAL', 'botwriter'); ?>" class="bw-hue-rotate-90">
        </div>
      </div>
      <div class="bw-addnew-card-body">
        <p class="bw-addnew-card-desc"><?php esc_html_e('Have a list of titles? Paste them and let the AI write the articles for you one by one.', 'botwriter'); ?></p>
        <ul class="bw-addnew-card-features">
          <li><?php esc_html_e('Paste your own list of titles', 'botwriter'); ?></li>
          <li><?php esc_html_e('Global prompt for all articles', 'botwriter'); ?></li>
          <li><?php esc_html_e('AI generates content & images', 'botwriter'); ?></li>
          <li><?php esc_html_e('Review and edit before publishing', 'botwriter'); ?></li>
        </ul>
      </div>
      <div class="bw-addnew-card-footer">
        <a class="button button-primary bw-addnew-btn" href="<?php echo esc_url($add_new_url_super_manual); ?>"><?php esc_html_e('Create Manual Task', 'botwriter'); ?> →</a>
      </div>
    </div>

    <!-- Option 4: Content Rewriter -->
    <div class="bw-addnew-card bw-addnew-card--orange">
      <div class="bw-addnew-card-header">
        <div class="bw-addnew-card-titles">
          <span class="bw-addnew-badge bw-addnew-badge--orange"><?php esc_html_e('Rewrite', 'botwriter'); ?></span>
          <h2 class="bw-addnew-card-title"><?php esc_html_e('Content Rewriter', 'botwriter'); ?></h2>
        </div>
        <div class="bw-addnew-card-icon">
          <img src="<?php echo esc_url($dir_images . 'rewriter.png'); ?>" alt="<?php echo esc_attr__('AI REWRITER', 'botwriter'); ?>">
        </div>
      </div>
      <div class="bw-addnew-card-body">
        <p class="bw-addnew-card-desc"><?php esc_html_e('Fetch articles from any URL, extract the content, and let the AI rewrite them in your own voice.', 'botwriter'); ?></p>
        <ul class="bw-addnew-card-features">
          <li><?php esc_html_e('Paste URLs of articles to rewrite', 'botwriter'); ?></li>
          <li><?php esc_html_e('Auto-extracts title and content', 'botwriter'); ?></li>
          <li><?php esc_html_e('Manual paste fallback', 'botwriter'); ?></li>
          <li><?php esc_html_e('Custom rewrite instructions', 'botwriter'); ?></li>
        </ul>
      </div>
      <div class="bw-addnew-card-footer">
        <a class="button button-primary bw-addnew-btn" href="<?php echo esc_url($add_new_url_rewriter); ?>"><?php esc_html_e('Open Rewriter', 'botwriter'); ?> →</a>
      </div>
    </div>

    <!-- Option 5: Site Rewriter -->
    <div class="bw-addnew-card bw-addnew-card--teal">
      <div class="bw-addnew-card-header">
        <div class="bw-addnew-card-titles">
          <span class="bw-addnew-badge bw-addnew-badge--teal"><?php esc_html_e('Crawl & Rewrite', 'botwriter'); ?></span>
          <h2 class="bw-addnew-card-title"><?php esc_html_e('Site Rewriter', 'botwriter'); ?></h2>
        </div>
        <div class="bw-addnew-card-icon">
          <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('SITE REWRITER', 'botwriter'); ?>" class="bw-hue-rotate-200">
        </div>
      </div>
      <div class="bw-addnew-card-body">
        <p class="bw-addnew-card-desc"><?php esc_html_e('Crawl an entire website, pick the pages you want, and rewrite them all with AI. Full site migration in minutes.', 'botwriter'); ?></p>
        <ul class="bw-addnew-card-features">
          <li><?php esc_html_e('Automatic page discovery from root URL', 'botwriter'); ?></li>
          <li><?php esc_html_e('Configurable crawl depth (1–3 levels)', 'botwriter'); ?></li>
          <li><?php esc_html_e('Select which pages to rewrite', 'botwriter'); ?></li>
          <li><?php esc_html_e('Up to 200 pages per crawl', 'botwriter'); ?></li>
        </ul>
      </div>
      <div class="bw-addnew-card-footer">
        <a class="button button-primary bw-addnew-btn" href="<?php echo esc_url($add_new_url_siterewriter); ?>"><?php esc_html_e('Open Site Rewriter', 'botwriter'); ?> →</a>
      </div>
    </div>

  </div><!-- .bw-addnew-grid -->
</div>

<?php
}
