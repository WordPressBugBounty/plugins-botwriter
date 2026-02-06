<?php
/**
 * Direct Content Sources for Self-Hosted Mode
 * 
 * Handles fetching content from external sources:
 * - RSS feeds
 * - External WordPress sites (via REST API)
 * - News APIs (using free NewsAPI.org)
 * 
 * This replicates the server-side functionality for direct mode processing.
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Direct_Content_Sources
 */
class BotWriter_Direct_Content_Sources {
    
    /**
     * Default timeout for HTTP requests
     */
    const DEFAULT_TIMEOUT = 30;
    
    /**
     * News API key (free tier: 100 requests/day)
     * Users can configure their own in settings
     */
    const DEFAULT_NEWS_API_KEY = '125d10b3e67e4ca6a46b81295488f74c';
    
    /**
     * Fetch content from external source based on website_type
     * 
     * @param array $task_data Task data with source configuration
     * @return array Result with success, content, link_original, or error
     */
    public function fetch_content($task_data) {
        $website_type = strtolower($task_data['website_type'] ?? '');
        
        // For AI type, no external content needed
        if ($website_type === 'ai' || $website_type === '') {
            return [
                'success' => true,
                'content' => '',
                'link_original' => '',
            ];
        }
        
        // For super1, handled separately
        if ($website_type === 'super1' || $website_type === 'super2') {
            return [
                'success' => true,
                'content' => '',
                'link_original' => '',
            ];
        }
        
        switch ($website_type) {
            case 'rss':
                return $this->fetch_rss_content($task_data);
                
            case 'wordpress':
                return $this->fetch_wordpress_content($task_data);
                
            case 'news':
                return $this->fetch_news_content($task_data);
                
            default:
                return $this->error("Unknown content source type: {$website_type}");
        }
    }
    
    /**
     * Fetch content from RSS feed
     * 
     * @param array $task_data Task data with rss_source
     * @return array Result
     */
    public function fetch_rss_content($task_data) {
        $rss_url = $task_data['rss_source'] ?? '';
        $published_links = $task_data['links'] ?? '';
        
        if (empty($rss_url)) {
            return $this->error('RSS source URL is empty or not configured.');
        }
        
        // Validate URL
        if (!filter_var($rss_url, FILTER_VALIDATE_URL)) {
            return $this->error('Invalid RSS feed URL: ' . $rss_url);
        }
        
        botwriter_log('Fetching RSS feed', ['url' => $rss_url]);
        
        // Fetch RSS content
        $response = wp_remote_get($rss_url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'sslverify' => false,
            'user-agent' => 'BotWriter/2.1 WordPress Plugin',
        ]);
        
        if (is_wp_error($response)) {
            return $this->error('Failed to fetch RSS feed: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return $this->error("RSS feed returned HTTP {$http_code}");
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse RSS/Atom feed
        $items = $this->parse_feed($body);
        
        if (empty($items)) {
            return $this->error('No items found in RSS feed or failed to parse.');
        }
        
        // Find first unpublished item
        $published_array = array_filter(array_map('trim', explode(',', $published_links)));
        
        foreach ($items as $item) {
            if (!in_array($item['link'], $published_array, true)) {
                $content = "-Based on this news article:\n";
                $content .= $item['title'] . "\n";
                $content .= $item['description'] . "\n\n";
                
                return [
                    'success' => true,
                    'content' => $content,
                    'link_original' => $item['link'],
                    'source_title' => $item['title'],
                ];
            }
        }
        
        return $this->error('No new articles found in RSS feed. All articles have already been published.');
    }
    
    /**
     * Parse RSS or Atom feed content
     * 
     * @param string $xml_content Raw XML content
     * @return array Array of items with title, link, description, date
     */
    private function parse_feed($xml_content) {
        // Suppress XML errors
        libxml_use_internal_errors(true);
        
        try {
            $xml = new SimpleXMLElement($xml_content, LIBXML_NOCDATA);
        } catch (Exception $e) {
            botwriter_log('RSS parse error', ['error' => $e->getMessage()], 'error');
            return [];
        }
        
        $items = [];
        
        // Check if it's Atom
        if (isset($xml->entry)) {
            // Atom format
            foreach ($xml->entry as $entry) {
                $link = '';
                // Atom links can be complex
                if (isset($entry->link['href'])) {
                    $link = (string)$entry->link['href'];
                } elseif (isset($entry->link)) {
                    foreach ($entry->link as $l) {
                        if ((string)$l['rel'] === 'alternate' || empty((string)$l['rel'])) {
                            $link = (string)$l['href'];
                            break;
                        }
                    }
                }
                
                $items[] = [
                    'title' => wp_strip_all_tags((string)$entry->title),
                    'link' => $link,
                    'description' => wp_strip_all_tags((string)($entry->summary ?? $entry->content ?? '')),
                    'date' => (string)($entry->updated ?? $entry->published ?? ''),
                ];
            }
        } else {
            // Standard RSS format
            $channel = $xml->channel ?? $xml;
            foreach ($channel->item as $item) {
                // Get content:encoded if available
                $content = '';
                $namespaces = $item->getNamespaces(true);
                if (isset($namespaces['content'])) {
                    $content_ns = $item->children($namespaces['content']);
                    if (isset($content_ns->encoded)) {
                        $content = wp_strip_all_tags((string)$content_ns->encoded);
                    }
                }
                
                $description = wp_strip_all_tags((string)$item->description);
                
                $items[] = [
                    'title' => wp_strip_all_tags((string)$item->title),
                    'link' => (string)$item->link,
                    'description' => $content ?: $description,
                    'date' => (string)($item->pubDate ?? ''),
                ];
            }
        }
        
        return $items;
    }
    
    /**
     * Fetch content from external WordPress site via REST API
     * 
     * @param array $task_data Task data with domain_name and website_category_id
     * @return array Result
     */
    public function fetch_wordpress_content($task_data) {
        $domain_name = $task_data['domain_name'] ?? '';
        $category_ids = $task_data['website_category_id'] ?? '';
        $published_links = $task_data['links'] ?? '';
        
        if (empty($domain_name)) {
            return $this->error('WordPress domain name is empty or not configured.');
        }
        
        if (empty($category_ids)) {
            return $this->error('WordPress category IDs are not configured.');
        }
        
        // Validate URL
        if (!filter_var($domain_name, FILTER_VALIDATE_URL)) {
            // Try adding https://
            $domain_name = 'https://' . ltrim($domain_name, '/');
            if (!filter_var($domain_name, FILTER_VALIDATE_URL)) {
                return $this->error('Invalid WordPress domain: ' . $domain_name);
            }
        }
        
        // Build REST API URL
        $api_url = rtrim($domain_name, '/') . '/wp-json/wp/v2/posts';
        $api_url .= '?categories=' . urlencode($category_ids);
        $api_url .= '&per_page=15';
        
        botwriter_log('Fetching WordPress posts', ['url' => $api_url]);
        
        $response = wp_remote_get($api_url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'sslverify' => false,
            'user-agent' => 'BotWriter/2.1 WordPress Plugin',
        ]);
        
        if (is_wp_error($response)) {
            return $this->error('Failed to connect to WordPress site: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return $this->error("WordPress REST API returned HTTP {$http_code}. Make sure the site has REST API enabled.");
        }
        
        $body = wp_remote_retrieve_body($response);
        $posts = json_decode($body, true);
        
        if (!is_array($posts) || empty($posts)) {
            return $this->error('No posts found in WordPress site or failed to parse response.');
        }
        
        // Find first unpublished post
        $published_array = array_filter(array_map('trim', explode(',', $published_links)));
        
        foreach ($posts as $post) {
            $post_link = $post['link'] ?? '';
            
            if (!in_array($post_link, $published_array, true)) {
                $title = $post['title']['rendered'] ?? '';
                $post_content = $post['content']['rendered'] ?? '';
                
                // Clean HTML
                $title = wp_strip_all_tags(html_entity_decode($title));
                $post_content = wp_strip_all_tags(html_entity_decode($post_content));
                
                $content = "-Based on this article (I indicate the end with the word ENDARTICLE):\n\n";
                $content .= $title . "\n";
                $content .= $post_content . "\n\n";
                $content .= "ENDARTICLE:\n\n";
                
                return [
                    'success' => true,
                    'content' => $content,
                    'link_original' => $post_link,
                    'source_title' => $title,
                ];
            }
        }
        
        return $this->error('No new posts found in WordPress site. All posts have already been published.');
    }
    
    /**
     * Fetch content from News API
     * 
     * @param array $task_data Task data with news settings
     * @return array Result
     */
    public function fetch_news_content($task_data) {
        $keyword = $task_data['news_keyword'] ?? '';
        $country = $task_data['news_country'] ?? '';
        $language = $task_data['news_language'] ?? 'en';
        $time_published = $task_data['news_time_published'] ?? '';
        $source = $task_data['news_source'] ?? '';
        $published_links = $task_data['links'] ?? '';
        
        if (empty($keyword)) {
            return $this->error('News keyword is empty or not configured.');
        }
        
        // Get API key (user can configure their own)
        $api_key = get_option('botwriter_news_api_key', self::DEFAULT_NEWS_API_KEY);
        
        if (empty($api_key)) {
            return $this->error('News API key is not configured. Get one free at newsapi.org');
        }
        
        // Build NewsAPI URL
        $url = 'https://newsapi.org/v2/everything';
        $params = [
            'q' => str_replace(',', ' ', $keyword),
            'apiKey' => $api_key,
            'pageSize' => 20,
            'sortBy' => 'publishedAt',
        ];
        
        // Add language
        if (!empty($language) && $language !== 'any') {
            $params['language'] = $language;
        }
        
        // Add source/domain filter
        if (!empty($source)) {
            $params['domains'] = $source;
        }
        
        // Add time filter
        if (!empty($time_published)) {
            $time_map = [
                'h' => '-1 hour',
                'd' => '-1 day',
                'w' => '-1 week',
                'm' => '-1 month',
                'y' => '-1 year',
            ];
            if (isset($time_map[$time_published])) {
                $params['from'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($time_map[$time_published]));
            }
        }
        
        $api_url = $url . '?' . http_build_query($params);
        
        botwriter_log('Fetching news', ['keyword' => $keyword, 'language' => $language]);
        
        $response = wp_remote_get($api_url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'BotWriter/2.1 WordPress Plugin',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $this->error('Failed to connect to News API: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($http_code !== 200 || ($data['status'] ?? '') !== 'ok') {
            $error_msg = $data['message'] ?? $data['error'] ?? "HTTP {$http_code}";
            return $this->error('News API error: ' . $error_msg);
        }
        
        $articles = $data['articles'] ?? [];
        
        if (empty($articles)) {
            return $this->error('No news articles found for keyword: ' . $keyword);
        }
        
        // Find first unpublished article
        $published_array = array_filter(array_map('trim', explode(',', $published_links)));
        
        foreach ($articles as $article) {
            $article_url = $article['url'] ?? '';
            
            if (!in_array($article_url, $published_array, true)) {
                $title = $article['title'] ?? '';
                $description = $article['description'] ?? '';
                $article_content = $article['content'] ?? $description;
                
                $content = "-Based on this news article:\n";
                $content .= $title . "\n";
                $content .= $description . "\n";
                if ($article_content !== $description) {
                    $content .= $article_content . "\n";
                }
                $content .= "\n";
                
                return [
                    'success' => true,
                    'content' => $content,
                    'link_original' => $article_url,
                    'source_title' => $title,
                ];
            }
        }
        
        return $this->error('No new news articles found. All articles have already been published.');
    }
    
    /**
     * Build the complete prompt with external content included
     * 
     * @param array $task_data Original task data
     * @param array $source_result Result from fetch_content
     * @return string Complete prompt
     */
    public function build_prompt_with_source($task_data, $source_result) {
        // Start with the client prompt or build one
        $prompt = $task_data['client_prompt'] ?? '';
        
        if (empty($prompt)) {
            // Build basic prompt
            $prompt = $this->build_basic_prompt($task_data);
        }
        
        // Add source content if available
        if (!empty($source_result['content'])) {
            // Insert source content before JSON instructions
            // Find where JSON instructions start
            $json_marker = 'Return the result in the following JSON format';
            $pos = strpos($prompt, $json_marker);
            
            if ($pos !== false) {
                $prompt = substr($prompt, 0, $pos) . $source_result['content'] . "\n" . substr($prompt, $pos);
            } else {
                $prompt .= "\n" . $source_result['content'];
            }
        }
        
        return $prompt;
    }
    
    /**
     * Build basic prompt from task settings
     * 
     * @param array $task_data Task data
     * @return string Basic prompt
     */
    private function build_basic_prompt($task_data) {
        $languages = botwriter_get_languages();
        
        $post_length = intval($task_data['post_length'] ?? 800);
        if ($post_length > 4000) $post_length = 4000;
        if ($post_length < 100) $post_length = 800;
        
        $language = $task_data['post_language'] ?? 'en';
        $language_name = $languages[$language] ?? 'English';
        
        $prompt = "Write an article for a blog, follow these instructions:\n";
        $prompt .= "-The article must be HTML, with proper opening and closing H1-H4 tags for headings, and <p> for paragraphs.\n";
        $prompt .= "-The length should be approximately {$post_length} words.\n";
        $prompt .= "-The article language must be: {$language_name}.\n";
        
        // Add AI keywords if available
        $keywords = $task_data['ai_keywords'] ?? '';
        if (!empty($keywords)) {
            $prompt .= "-Keywords to include: {$keywords}\n";
        }
        
        // Add titles to avoid repetition
        $titles = $task_data['titles'] ?? '';
        if (!empty($titles)) {
            $prompt .= "\n-Do not repeat articles we have already published, these are their titles:\n{$titles}\n";
        }
        
        // Add JSON output instructions
        $prompt .= $this->get_json_instructions();
        
        return $prompt;
    }
    
    /**
     * Get JSON output instructions
     * 
     * @return string JSON instructions
     */
    private function get_json_instructions() {
        return "\n\n-Also generate a detailed, photorealistic quality prompt for the image based on this article.
-Do not include the article title in aigenerated_content, and do not start with an <h1> to <h6> header, start the content with the first <p> paragraph.
-Return the result in the following JSON format:

{
    \"aigenerated_title\": \"[Article title]\",
    \"aigenerated_content\": \"[Article content]\",
    \"aigenerated_tags\": \"[Article tags]\",
    \"image_prompt\": \"[Image prompt]\"
}";
    }
    
    /**
     * Create error response
     * 
     * @param string $message Error message
     * @return array Error response
     */
    private function error($message) {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}

/**
 * Helper function to get languages array
 * Matches server-side $wpbotwriter_languages
 */
function botwriter_get_languages() {
    return [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'pl' => 'Polish',
        'ru' => 'Russian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh' => 'Chinese',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'tr' => 'Turkish',
        'vi' => 'Vietnamese',
        'th' => 'Thai',
        'id' => 'Indonesian',
        'ms' => 'Malay',
        'sv' => 'Swedish',
        'da' => 'Danish',
        'no' => 'Norwegian',
        'fi' => 'Finnish',
        'cs' => 'Czech',
        'el' => 'Greek',
        'he' => 'Hebrew',
        'hu' => 'Hungarian',
        'ro' => 'Romanian',
        'sk' => 'Slovak',
        'uk' => 'Ukrainian',
        'bg' => 'Bulgarian',
        'hr' => 'Croatian',
        'sr' => 'Serbian',
        'sl' => 'Slovenian',
        'et' => 'Estonian',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'ca' => 'Catalan',
        'eu' => 'Basque',
        'gl' => 'Galician',
    ];
}
