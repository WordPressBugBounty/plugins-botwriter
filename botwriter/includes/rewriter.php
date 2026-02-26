<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Content Rewriter Page Handler
 * Renders the Content Rewriter wizard UI.
 */
function botwriter_rewriter_page_handler() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $dir_images = plugin_dir_url(dirname(__FILE__)) . '/assets/images/';
?>

<div class="wrap">
    <h1><?php esc_html_e('Content Rewriter', 'botwriter'); ?></h1>
    <p><?php esc_html_e('Paste URLs of articles to fetch, extract and rewrite them with AI.', 'botwriter'); ?></p>

    <!-- Step 1: URL Input -->
    <div id="rewriter_step1" class="super-ia bw-flex-col">
        <div class="bw-flex-row-center bw-mb-15">
            <div class="super-ia-image bw-img-shrink">
                <img src="<?php echo esc_url($dir_images . 'ai_cerebro.png'); ?>" alt="<?php echo esc_attr__('AI', 'botwriter'); ?>" class="bw-hue-rotate-200 bw-img-80">
            </div>
            <div>
                <h2 class="super-title"><?php esc_html_e('Step 1: Paste URLs', 'botwriter'); ?></h2>
                <p class="super-text"><?php esc_html_e('Enter the URLs of the articles you want to rewrite (one per line). The system will fetch and extract the content.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="col-md-6">
            <label for="rewriter_urls"><?php esc_html_e('Article URLs (one per line):', 'botwriter'); ?></label>
            <textarea id="rewriter_urls" name="rewriter_urls" rows="8" cols="60" placeholder="https://example.com/article-1&#10;https://example.com/article-2&#10;https://example.com/article-3"></textarea>
        </div>
        <br>
        <div>
            <button id="rewriter_fetch_btn" class="button-primary"><?php esc_html_e('Fetch Content', 'botwriter'); ?></button>
        </div>
        <div id="rewriter_fetch_status" class="bw-mt-10"></div>
    </div>

    <!-- Step 2: Review Extracted Content -->
    <div id="rewriter_step2" style="display: none;" class="bw-mt-20">
        <div class="super-ia bw-flex-col">
            <h2 class="super-title"><?php esc_html_e('Step 2: Review Extracted Content', 'botwriter'); ?></h2>
            <p class="super-text"><?php esc_html_e('Review the extracted content from each URL. You can edit, remove or manually paste content for failed extractions.', 'botwriter'); ?></p>
            <div id="rewriter_articles_list"></div>
            <br>
            <div>
                <button id="rewriter_add_manual_btn" class="button" type="button"><?php esc_html_e('+ Add Article Manually', 'botwriter'); ?></button>
            </div>
        </div>
    </div>

    <!-- Step 3: Rewrite Options + Create Task -->
    <div id="rewriter_step3" style="display: none;" class="bw-mt-20">
        <div class="super-ia bw-flex-col">
            <h2 class="super-title"><?php esc_html_e('Step 3: Rewrite Settings', 'botwriter'); ?></h2>
            <p class="super-text"><?php esc_html_e('Configure how the AI should rewrite the articles.', 'botwriter'); ?></p>

            <div class="col-md-6">
                <label for="rewriter_prompt"><?php esc_html_e('Rewrite Instructions:', 'botwriter'); ?></label>
                <textarea id="rewriter_prompt" name="rewriter_prompt" rows="4" cols="60" placeholder="<?php esc_attr_e('e.g. Rewrite in a friendly conversational tone. Add practical examples. Make it 30% longer with more detail.', 'botwriter'); ?>"></textarea>
                <p class="form-text"><?php esc_html_e('Global instructions for rewriting all articles. This will be sent to the AI along with each original article.', 'botwriter'); ?></p>
            </div>
            <br>

            <!-- Task Properties Grid -->
            <div class="bw-rewriter-props-extra">
                <div class="col-md-6">
                    <label for="rewriter_category" class="form-label"><?php esc_html_e('Category:', 'botwriter'); ?></label>
                    <select id="rewriter_category" name="rewriter_category" class="form-select">
                        <option value="0"><?php esc_html_e('— Default —', 'botwriter'); ?></option>
                        <?php
                        $categories = get_categories(array(
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'hide_empty' => false
                        ));
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="form-text"><?php esc_html_e('Select the category where the rewritten posts will be published.', 'botwriter'); ?></p>
                </div>
            </div>
            <br>

            <!-- Reuse Super Task Properties form -->
            <?php
            $rewriter_default_item = array(
                /* translators: %s: current date and time */
                'task_name'         => sprintf(__('Rewriter Task %s', 'botwriter'), wp_date('M j, Y H:i')),
                'writer'            => 'ai_cerebro',
                'narration'         => 'Descriptive',
                'custom_style'      => '',
                'post_language'     => substr(get_locale(), 0, 2),
                'post_length'       => '800',
                'custom_post_length' => '',
                'post_status'       => 'draft',
                'days'              => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'times_per_day'     => 1,
                'author_selection'  => strval(get_current_user_id()),
                'disable_ai_images' => 0,
                'template_id'       => null,
            );
            botwriter_super_form_meta_box_handler($rewriter_default_item);
            ?>
            <!-- End Super Task Properties -->
            <br>

            <div>
                <button id="rewriter_create_btn" class="button-primary"><?php esc_html_e('Create Rewrite Task', 'botwriter'); ?></button>
            </div>
            <div id="rewriter_create_status" class="bw-mt-10"></div>
        </div>
    </div>

</div>

<?php
}


/**
 * Extract article content from a URL using DOMDocument.
 *
 * @param string $url The URL to extract content from.
 * @return array|WP_Error Array with 'title', 'content', 'excerpt', 'url' or WP_Error.
 */
function botwriter_rewriter_extract_content($url, $prefetched_html = null) {
    if ($prefetched_html !== null) {
        // Use pre-fetched HTML (e.g. from Site Rewriter crawl)
        return botwriter_rewriter_extract_content_from_html($prefetched_html, $url);
    }

    // Build a Referer from the target domain (sites often block requests without one)
    $parsed = wp_parse_url($url);
    $referer = (isset($parsed['scheme']) ? $parsed['scheme'] : 'https') . '://' . ($parsed['host'] ?? '');

    // Fetch the page with realistic browser headers to avoid 403 blocks
    $response = wp_remote_get($url, array(
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'sslverify'  => false,
        'redirection' => 5,
        'headers' => array(
            'Accept'            => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language'   => 'en-US,en;q=0.9,es;q=0.8',
            'Accept-Encoding'   => 'identity',
            'Cache-Control'     => 'no-cache',
            'Referer'           => $referer,
            'Sec-Fetch-Dest'    => 'document',
            'Sec-Fetch-Mode'    => 'navigate',
            'Sec-Fetch-Site'    => 'same-origin',
            'Sec-Fetch-User'    => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Ch-Ua'         => '"Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile'  => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
        ),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code === 403) {
        return new WP_Error('http_403', 'Access denied (403). The website is blocking automated requests from your server. Try using "Add Article Manually" to paste the content instead.');
    }
    if ($status_code !== 200) {
        return new WP_Error('http_error', sprintf('HTTP %d error fetching URL.', $status_code));
    }

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
        return new WP_Error('empty_response', 'Empty response from URL.');
    }

    return botwriter_rewriter_extract_content_from_html($html, $url);
}

/**
 * Extract article content from pre-fetched HTML.
 * Shared logic used by both botwriter_rewriter_extract_content() and
 * botwriter_siterewriter_crawl_page() to avoid re-downloading pages.
 *
 * @param string $html Raw HTML.
 * @param string $url  Original URL (used for output only).
 * @return array|WP_Error
 */
function botwriter_rewriter_extract_content_from_html($html, $url = '') {
    // Suppress DOM warnings for malformed HTML
    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // --- Extract title ---
    $title = '';

    // Try og:title first
    $og_title_nodes = $xpath->query('//meta[@property="og:title"]/@content');
    if ($og_title_nodes && $og_title_nodes->length > 0) {
        $title = trim($og_title_nodes->item(0)->nodeValue);
    }

    // Try <h1> if og:title empty
    if (empty($title)) {
        $h1_nodes = $xpath->query('//h1');
        if ($h1_nodes && $h1_nodes->length > 0) {
            $title = trim($h1_nodes->item(0)->textContent);
        }
    }

    // Fallback to <title>
    if (empty($title)) {
        $title_nodes = $xpath->query('//title');
        if ($title_nodes && $title_nodes->length > 0) {
            $title = trim($title_nodes->item(0)->textContent);
        }
    }

    // --- Remove unwanted tags (always safe to remove) ---
    $remove_tags = array('script', 'style', 'nav', 'header', 'footer', 'aside', 'iframe', 'noscript', 'form', 'svg', 'figcaption');
    foreach ($remove_tags as $tag) {
        $elements = $xpath->query('//' . $tag);
        if ($elements) {
            // Collect into array first to avoid live NodeList issues
            $to_remove = array();
            foreach ($elements as $element) {
                $to_remove[] = $element;
            }
            foreach ($to_remove as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
    }

    // Remove noise elements by class/id patterns.
    // Only remove elements that do NOT contain substantial paragraph text
    // (prevents stripping parent containers on sites that put "social" or "share" classes on wrappers).
    $noise_patterns = array(
        '//*[contains(@class, "comment")]',
        '//*[contains(@class, "sidebar")]',
        '//*[contains(@class, "widget")]',
        '//*[contains(@class, "share")]',
        '//*[contains(@class, "social")]',
        '//*[contains(@class, "related")]',
        '//*[contains(@class, "advertisement")]',
        '//*[contains(@class, "ad-")]',
        '//*[contains(@class, "cookie")]',
        '//*[contains(@class, "newsletter")]',
        '//*[contains(@class, "popup")]',
        '//*[contains(@class, "author-bio")]',
        '//*[contains(@class, "post-tags")]',
        '//*[contains(@class, "breadcrumb")]',
        '//*[contains(@class, "navigation")]',
        '//*[contains(@class, "paginat")]',
        '//*[contains(@id, "comment")]',
        '//*[contains(@id, "sidebar")]',
        '//*[contains(@id, "footer")]',
        '//*[contains(@id, "header")]',
        '//*[contains(@id, "newsletter")]',
    );
    foreach ($noise_patterns as $pattern) {
        $elements = $xpath->query($pattern);
        if ($elements) {
            $to_remove = array();
            foreach ($elements as $element) {
                // Safety check: only remove if element has few real paragraphs
                // (avoids stripping parent containers that wrap the article)
                $p_count = 0;
                $paras = $xpath->query('.//p', $element);
                if ($paras) {
                    foreach ($paras as $p) {
                        if (strlen(trim($p->textContent)) > 40) {
                            $p_count++;
                        }
                    }
                }
                if ($p_count < 3) {
                    $to_remove[] = $element;
                }
            }
            foreach ($to_remove as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
    }

    // --- Extract main content ---
    $content = '';

    // Strategy 1: Schema.org articleBody (most reliable when present)
    $schema_nodes = $xpath->query('//*[@itemprop="articleBody"]');
    if ($schema_nodes && $schema_nodes->length > 0) {
        $content = botwriter_rewriter_get_text_content($schema_nodes->item(0));
    }

    // Strategy 2: Common content container classes (including popular WP themes)
    if (empty(trim($content)) || strlen(trim($content)) < 200) {
        $content_selectors = array(
            // Standard WordPress
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "post-content")]',
            '//*[contains(@class, "article-content")]',
            '//*[contains(@class, "article-body")]',
            '//*[contains(@class, "post-body")]',
            '//*[contains(@class, "single-content")]',
            // TagDiv themes (Flavor, Flavor HD, Flavor Pro, Flavor Magazine)
            '//*[contains(@class, "td-post-content")]',
            '//*[contains(@class, "tdb-block-inner")]',
            // Flavor / flavored themes
            '//*[contains(@class, "flavor-content")]',
            // flavor theme "the_content" wrapper
            '//*[contains(@class, "the_content_wrapper")]',
            // flavor "single-post-content"
            '//*[contains(@class, "single-post-content")]',
            // flavor article text
            '//*[contains(@class, "flavor-text")]',
            // flavor article body
            '//*[contains(@class, "flavor-article")]',
            // flavor tdb
            '//*[contains(@class, "tdb_single_content")]',
            // flavor td_module_wrap
            '//*[contains(@class, "td-module-content")]',
            // flavor td
            '//*[contains(@class, "td-ss-main-content")]',
            // flavor tdi
            '//*[contains(@class, "wpb_text_column")]',
            // GeneratePress
            '//*[contains(@class, "inside-article")]',
            // Astra
            '//*[contains(@class, "ast-post-format-")]',
            // OceanWP
            '//*[contains(@class, "entry")]',
            // Flavor / flavored themes
            '//*[contains(@class, "flavor-article")]',
            // Generic / other frameworks
            '//*[contains(@class, "article__body")]',
            '//*[contains(@class, "story-body")]',
            '//*[contains(@class, "c-article-body")]',
            '//*[contains(@class, "content-area")]',
            '//*[@id="content"]',
            '//*[@role="main"]',
            '//main',
        );

        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $candidate = botwriter_rewriter_get_text_content($nodes->item(0));
                if (strlen(trim($candidate)) > strlen(trim($content))) {
                    $content = $candidate;
                }
            }
        }
    }

    // Strategy 3: Look for <article> tag (after class-based, because article
    // tags often include metadata/author/share buttons besides the text)
    if (empty(trim($content)) || strlen(trim($content)) < 200) {
        $article_nodes = $xpath->query('//article');
        if ($article_nodes && $article_nodes->length > 0) {
            $candidate = botwriter_rewriter_get_text_content($article_nodes->item(0));
            if (strlen(trim($candidate)) > strlen(trim($content))) {
                $content = $candidate;
            }
        }
    }

    // Strategy 4: Find the div with the most <p> text (simplified readability heuristic)
    if (empty(trim($content)) || strlen(trim($content)) < 200) {
        $divs = $xpath->query('//div');
        $best_div = null;
        $best_score = 0;

        if ($divs) {
            foreach ($divs as $div) {
                $paragraphs = $xpath->query('.//p', $div);
                if (!$paragraphs) continue;
                
                $score = 0;
                $text_len = 0;
                foreach ($paragraphs as $p) {
                    $p_text = trim($p->textContent);
                    if (strlen($p_text) > 25) {
                        $score++;
                        $text_len += strlen($p_text);
                    }
                }
                // Prefer containers with more paragraphs + text, but penalize
                // very large containers (likely body/wrapper) to find the tightest match
                $child_divs = $xpath->query('./div', $div);
                $nesting_penalty = $child_divs ? $child_divs->length * 10 : 0;
                $total_score = $score * 100 + $text_len - $nesting_penalty;
                if ($total_score > $best_score && $score >= 2) {
                    $best_score = $total_score;
                    $best_div = $div;
                }
            }
        }

        if ($best_div) {
            $candidate = botwriter_rewriter_get_text_content($best_div);
            if (strlen(trim($candidate)) > strlen(trim($content))) {
                $content = $candidate;
            }
        }
    }

    // Clean up content
    $content = trim($content);

    // Truncate if extremely long (e.g. over 15000 chars)
    if (strlen($content) > 15000) {
        $content = substr($content, 0, 15000) . "\n\n[Content truncated...]";
    }

    if (empty($title) && empty($content)) {
        return new WP_Error('extraction_failed', 'Could not extract any content from the URL.');
    }

    // Flag partial extractions so the frontend can warn the user
    $content_warning = '';
    if (empty($content)) {
        $content_warning = 'no_content';
    } elseif (strlen($content) < 200) {
        $content_warning = 'short_content';
    }

    // Create excerpt from first 300 chars
    $excerpt = '';
    if (!empty($content)) {
        $excerpt = wp_trim_words(wp_strip_all_tags($content), 40, '...');
    }

    return array(
        'title'           => sanitize_text_field($title),
        'content'         => $content,
        'excerpt'         => $excerpt,
        'url'             => esc_url($url),
        'content_warning' => $content_warning,
    );
}


/**
 * Helper: Extract cleaned text content from a DOMNode.
 * Preserves paragraph structure but removes tags.
 *
 * @param DOMNode $node
 * @return string
 */
function botwriter_rewriter_get_text_content($node) {
    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text .= trim($child->textContent) . ' ';
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($child->nodeName);
            // Add line breaks for block elements
            if (in_array($tag, array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br', 'blockquote', 'pre', 'tr'))) {
                $inner = botwriter_rewriter_get_text_content($child);
                if (!empty(trim($inner))) {
                    $text .= "\n\n" . trim($inner);
                }
            } else {
                $text .= botwriter_rewriter_get_text_content($child);
            }
        }
    }
    return $text;
}
