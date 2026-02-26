<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Site Rewriter Page Handler
 * 3-step wizard: Crawl Website → Select & Review Content → Create Rewrite Task
 */
function botwriter_siterewriter_page_handler() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $dir_images = plugin_dir_url(dirname(__FILE__)) . '/assets/images/';
?>

<style>
.siterewriter-page-row {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 6px;
    transition: background 0.15s;
}
.siterewriter-page-row:hover {
    background: #f7f7ff;
}
.siterewriter-page-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    flex-wrap: wrap;
}
.siterewriter-page-label input[type="checkbox"] {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
}
.siterewriter-page-title {
    font-weight: 600;
    color: #1d2327;
    flex: 1 1 250px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.siterewriter-page-url {
    flex: 1 1 300px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
}
.siterewriter-page-url a {
    color: #2271b1;
    text-decoration: none;
}
.siterewriter-page-url a:hover {
    text-decoration: underline;
}
.siterewriter-page-depth {
    flex-shrink: 0;
    background: #f0f0f1;
    color: #666;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
}
</style>

<div class="wrap">
    <h1><?php esc_html_e('Site Rewriter', 'botwriter'); ?></h1>
    <p><?php esc_html_e('Crawl an entire website, select the pages you want, and rewrite them all with AI.', 'botwriter'); ?></p>

    <!-- Step 1: Crawl Settings -->
    <div id="siterewriter_step1" class="super-ia bw-flex-col">
        <div class="bw-flex-row-center bw-mb-15">
            <div class="super-ia-image bw-img-shrink">
                <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI', 'botwriter'); ?>" class="bw-hue-rotate-200 bw-img-80">
            </div>
            <div>
                <h2 class="super-title"><?php esc_html_e('Step 1: Crawl Website', 'botwriter'); ?></h2>
                <p class="super-text"><?php esc_html_e('Enter the root URL of the website to crawl. The system will discover internal pages and extract their titles.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="col-md-6">
            <label for="siterewriter_root_url"><?php esc_html_e('Website URL:', 'botwriter'); ?></label>
            <input type="url" id="siterewriter_root_url" name="siterewriter_root_url"
                   placeholder="https://example.com" style="width: 100%;" />
        </div>
        <br>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div>
                <label for="siterewriter_depth"><?php esc_html_e('Crawl Depth:', 'botwriter'); ?></label>
                <select id="siterewriter_depth" name="siterewriter_depth">
                    <option value="1" selected>1 — <?php esc_html_e('Pages linked from root', 'botwriter'); ?></option>
                    <option value="2">2 — <?php esc_html_e('+ Pages linked from those', 'botwriter'); ?></option>
                    <option value="3">3 — <?php esc_html_e('+ One more level deep', 'botwriter'); ?></option>
                </select>
                <p class="form-text"><?php esc_html_e('How many link levels to follow from the root URL.', 'botwriter'); ?></p>
            </div>
            <div>
                <label for="siterewriter_max_urls"><?php esc_html_e('Max Pages:', 'botwriter'); ?></label>
                <input type="number" id="siterewriter_max_urls" name="siterewriter_max_urls"
                       value="20" min="1" max="200" style="width: 80px;" />
                <p class="form-text"><?php esc_html_e('Maximum number of pages to discover (1–200).', 'botwriter'); ?></p>
            </div>
        </div>
        <br>

        <div>
            <button id="siterewriter_crawl_btn" class="button-primary"><?php esc_html_e('Crawl Website', 'botwriter'); ?></button>
            <button id="siterewriter_stop_btn" class="button" style="display:none; margin-left:8px;"><?php esc_html_e('Stop', 'botwriter'); ?></button>
        </div>
        <div id="siterewriter_crawl_status" class="bw-mt-10"></div>
        <div id="siterewriter_crawl_live" style="display:none; margin-top:12px; max-height:350px; overflow-y:auto; border:1px solid #ddd; border-radius:6px; padding:8px 12px; background:#fafafa;"></div>
    </div>

    <!-- Step 2: Select Pages & Fetch Content -->
    <div id="siterewriter_step2" style="display: none;" class="bw-mt-20">
        <div class="super-ia bw-flex-col">
            <h2 class="super-title"><?php esc_html_e('Step 2: Select Pages', 'botwriter'); ?></h2>
            <p class="super-text"><?php esc_html_e('Select which pages to rewrite, then click "Fetch Content" to extract the article text.', 'botwriter'); ?></p>

            <div style="margin-bottom: 10px;">
                <label style="cursor:pointer;"><input type="checkbox" id="siterewriter_select_all" checked> <?php esc_html_e('Select / Deselect All', 'botwriter'); ?></label>
                <span id="siterewriter_selected_count" style="margin-left: 15px; color: #666;"></span>
            </div>

            <div id="siterewriter_pages_list"></div>
            <br>
            <div>
                <button id="siterewriter_fetch_btn" class="button-primary"><?php esc_html_e('Use Selected Pages', 'botwriter'); ?></button>
            </div>
            <div id="siterewriter_fetch_status" class="bw-mt-10"></div>
        </div>
    </div>

    <!-- Step 3: Review Content & Create Task -->
    <div id="siterewriter_step3" style="display: none;" class="bw-mt-20">
        <div class="super-ia bw-flex-col">
            <h2 class="super-title"><?php esc_html_e('Step 3: Review & Create Task', 'botwriter'); ?></h2>
            <p class="super-text"><?php esc_html_e('Review extracted content, set rewrite instructions, and configure the task.', 'botwriter'); ?></p>

            <div id="siterewriter_articles_list"></div>
            <br>

            <div class="col-md-6">
                <label for="siterewriter_prompt"><?php esc_html_e('Rewrite Instructions:', 'botwriter'); ?></label>
                <textarea id="siterewriter_prompt" name="siterewriter_prompt" rows="4" cols="60"
                          placeholder="<?php esc_attr_e('e.g. Rewrite in a friendly conversational tone. Add practical examples. Make it 30% longer.', 'botwriter'); ?>"></textarea>
                <p class="form-text"><?php esc_html_e('Global instructions applied to every article.', 'botwriter'); ?></p>
            </div>
            <br>

            <!-- Category -->
            <div class="bw-rewriter-props-extra">
                <div class="col-md-6">
                    <label for="siterewriter_category" class="form-label"><?php esc_html_e('Category:', 'botwriter'); ?></label>
                    <select id="siterewriter_category" name="siterewriter_category" class="form-select">
                        <option value="0"><?php esc_html_e('— Default —', 'botwriter'); ?></option>
                        <?php
                        $categories = get_categories(array('orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false));
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="form-text"><?php esc_html_e('Category for the rewritten posts.', 'botwriter'); ?></p>
                </div>
            </div>
            <br>

            <!-- Shared task properties form (writer, language, length, days…) -->
            <?php
            $siterewriter_default_item = array(
                /* translators: %s: current date and time */
                'task_name'          => sprintf(__('Site Rewriter %s', 'botwriter'), wp_date('M j, Y H:i')),
                'writer'             => 'ai_cerebro',
                'narration'          => 'Descriptive',
                'custom_style'       => '',
                'post_language'      => substr(get_locale(), 0, 2),
                'post_length'        => '800',
                'custom_post_length' => '',
                'post_status'        => 'draft',
                'days'               => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'times_per_day'      => 1,
                'author_selection'   => strval(get_current_user_id()),
                'disable_ai_images'  => 0,
                'template_id'        => null,
            );
            botwriter_super_form_meta_box_handler($siterewriter_default_item);
            ?>
            <br>

            <div>
                <button id="siterewriter_create_btn" class="button-primary"><?php esc_html_e('Create Rewrite Task', 'botwriter'); ?></button>
            </div>
            <div id="siterewriter_create_status" class="bw-mt-10"></div>
        </div>
    </div>

</div>

<?php
}


// ========================================================================
// Crawl functions
// ========================================================================

/**
 * Crawl a single page: fetch it, extract the title, and return internal links found.
 * The BFS queue is managed client-side in JS for progressive/live UI updates.
 *
 * @param string $url         The URL to fetch.
 * @param string $base_domain The domain to restrict links to.
 * @return array|WP_Error     [ 'url', 'title', 'links' => [...] ]
 */
function botwriter_siterewriter_crawl_page($url, $base_domain) {
    $parsed = wp_parse_url($url);
    $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
    $base_url = $scheme . '://' . $base_domain;

    $http_args = array(
        'timeout'     => 20,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'sslverify'   => false,
        'redirection' => 5,
        'headers'     => array(
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language'           => 'en-US,en;q=0.9,es;q=0.8',
            'Accept-Encoding'           => 'identity',
            'Cache-Control'             => 'no-cache',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'none',
            'Sec-Fetch-User'            => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Ch-Ua'                 => '"Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile'          => '?0',
            'Sec-Ch-Ua-Platform'        => '"Windows"',
        ),
    );

    $response = wp_remote_get($url, $http_args);
    if (is_wp_error($response)) {
        return new WP_Error('fetch_error', $response->get_error_message());
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('http_error', sprintf('HTTP %d', $status_code));
    }

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    if ($content_type && strpos($content_type, 'text/html') === false) {
        return new WP_Error('not_html', 'Not an HTML page.');
    }

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
        return new WP_Error('empty', 'Empty response.');
    }

    $title = botwriter_siterewriter_extract_title($html);
    $links = botwriter_siterewriter_extract_links($html, $base_url, $base_domain);

    // Also extract article content so the second fetch step can be skipped
    $content         = '';
    $content_warning = '';
    if (function_exists('botwriter_rewriter_extract_content_from_html')) {
        $extract = botwriter_rewriter_extract_content_from_html($html, $url);
        if (!is_wp_error($extract)) {
            $content         = $extract['content'] ?? '';
            $content_warning = $extract['content_warning'] ?? '';
        }
    }

    return array(
        'url'             => $url,
        'title'           => $title,
        'links'           => $links,
        'content'         => $content,
        'content_warning' => $content_warning,
    );
}


/**
 * Extract internal links from an HTML page.
 *
 * @param string $html        Full page HTML.
 * @param string $base_url    Scheme + host of the target site (e.g. https://example.com).
 * @param string $base_domain Host only (e.g. example.com).
 * @return array Unique normalized URLs.
 */
function botwriter_siterewriter_extract_links($html, $base_url, $base_domain) {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $anchors = $doc->getElementsByTagName('a');
    $links   = array();

    $skip_extensions = array(
        'jpg','jpeg','png','gif','svg','webp','pdf','css','js','zip','rar',
        'mp3','mp4','avi','doc','docx','xls','xlsx','ico','woff','woff2','ttf','eot',
    );

    $skip_patterns = array(
        '/wp-admin', '/wp-login', '/wp-content/', '/wp-includes/',
        '/feed', '/tag/', '/author/', '/page/',
        '/cart', '/checkout', '/my-account', '/search',
        '#', 'javascript:', 'mailto:', 'tel:',
        '?replytocom=', '?share=', '/xmlrpc.php', '/wp-json/',
        '/category/', '/categories/', '/archivo/', '/archives/', '/etiqueta/',
    );

    foreach ($anchors as $anchor) {
        $href = trim($anchor->getAttribute('href'));
        if (empty($href) || $href === '/' || $href === '#') {
            continue;
        }

        // Skip unwanted patterns
        $skip = false;
        foreach ($skip_patterns as $pattern) {
            if (stripos($href, $pattern) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        // Resolve relative URLs
        if (strpos($href, 'http') !== 0 && strpos($href, '//') !== 0) {
            $href = (strpos($href, '/') === 0)
                ? $base_url . $href
                : rtrim($base_url, '/') . '/' . $href;
        }

        // Protocol-relative
        if (strpos($href, '//') === 0) {
            $href = 'https:' . $href;
        }

        // Parse and validate domain
        $parsed = wp_parse_url($href);
        if (!isset($parsed['host'])) {
            continue;
        }

        $link_domain  = strtolower($parsed['host']);
        $check_domain = strtolower($base_domain);
        if (
            $link_domain !== $check_domain &&
            $link_domain !== 'www.' . $check_domain &&
            'www.' . $link_domain !== $check_domain
        ) {
            continue;
        }

        // Skip non-HTML extensions
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!empty($ext) && in_array($ext, $skip_extensions)) {
            continue;
        }

        // Normalize for dedup
        $norm = (isset($parsed['scheme']) ? $parsed['scheme'] : 'https')
              . '://' . $parsed['host'] . rtrim($path, '/');

        $links[$norm] = true;
    }

    return array_keys($links);
}


/**
 * Quick title extraction from raw HTML using regex (no full DOM parse needed).
 */
function botwriter_siterewriter_extract_title($html) {
    // og:title (property before content)
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return sanitize_text_field(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    // og:title (content before property)
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m)) {
        return sanitize_text_field(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    // <title>
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return sanitize_text_field(html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8'));
    }
    // <h1>
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        return sanitize_text_field(html_entity_decode(wp_strip_all_tags(trim($m[1])), ENT_QUOTES, 'UTF-8'));
    }

    return '';
}


/**
 * Normalize a URL for deduplication (lowercased scheme+host, no trailing slash, no fragment).
 */
function botwriter_siterewriter_normalize_url($url) {
    $parsed = wp_parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return $url;
    }

    $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'https';
    $host   = strtolower($parsed['host']);
    $path   = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';

    return $scheme . '://' . $host . $path;
}
