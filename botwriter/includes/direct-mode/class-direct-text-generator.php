<?php
/**
 * Direct Text Generator for Self-Hosted Models
 * 
 * Handles text generation directly from the plugin to self-hosted
 * AI servers without going through botwriter.com
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Direct_Text_Generator
 */
class BotWriter_Direct_Text_Generator {
    
    /**
     * Default timeout for self-hosted requests (6.5 minutes)
     */
    const DEFAULT_TIMEOUT = 400;
    
    /**
     * Maximum prompt length
     */
    const MAX_PROMPT_LENGTH = 32000;
    
    /**
     * Generate text using self-hosted model
     * 
     * @param array $task_data Task data with URL, model, prompt, etc.
     * @return array Result with success status, content, or error
     */
    public function generate($task_data) {
        $url = $task_data['selfhosted_url'] ?? '';
        $model = $task_data['selfhosted_model'] ?? '';
        $api_key = $task_data['selfhosted_api_key'] ?? '';
        
        // Validate URL
        if (empty($url)) {
            return $this->error('Self-hosted server URL is not configured.');
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('Invalid self-hosted server URL: ' . $url);
        }
        
        // Build the prompt
        $prompt = $this->build_prompt($task_data);
        
        if (strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return $this->error('Prompt exceeds maximum length of ' . self::MAX_PROMPT_LENGTH . ' characters.');
        }
        
        // Determine endpoint (OpenAI-compatible format)
        // If URL ends with /v1, append only /chat/completions
        // Otherwise, append full /v1/chat/completions
        $url = rtrim($url, '/');
        if (preg_match('/\/v1$/i', $url)) {
            $endpoint = $url . '/chat/completions';
        } else {
            $endpoint = $url . '/v1/chat/completions';
        }
        
        // Log for debugging
        botwriter_log('Text generation endpoint: ' . $endpoint);
        botwriter_log('Model: ' . $model);
        
        // Build request payload
        $payload = $this->build_payload($prompt, $model, $task_data);
        
        // Make the request
        $result = $this->make_request($endpoint, $payload, $api_key, $task_data);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Parse the response
        return $this->parse_response($result['response']);
    }
    
    /**
     * Build the prompt from task data
     * 
     * @param array $task_data Task data
     * @return string Complete prompt
     */
    private function build_prompt($task_data) {
        $prompt = '';
        
        // Get the client prompt if available
        if (!empty($task_data['client_prompt'])) {
            $prompt = $task_data['client_prompt'];
        } else {
            // Build prompt from individual components
            $website_type = $task_data['website_type'] ?? 'blog';
            $language = $task_data['language'] ?? 'English';
            $writing_style = $task_data['writing_style'] ?? 'informative';
            $keyword = $task_data['keyword'] ?? '';
            
            $prompt = "Write a blog article about: {$keyword}\n";
            $prompt .= "Language: {$language}\n";
            $prompt .= "Writing style: {$writing_style}\n";
            $prompt .= "Type: {$website_type}\n";
        }
        
                // JSON output format instructions
                $prompt .= "\n\n" .
                        "Write a complete blog article and respond with a JSON object containing exactly these 4 fields:\n\n" .
                        "{\n" .
                        '  "aigenerated_title": "Your engaging article title",' . "\n" .
                        '  "aigenerated_content": "<h2>Introduction</h2><p>Your content with HTML tags...</p><h2>Main Section</h2><p>More content...</p><h2>Conclusion</h2><p>Final thoughts...</p>",' . "\n" .
                        '  "aigenerated_tags": "tag1, tag2, tag3",' . "\n" .
                        '  "aigenerated_image_prompt": "A detailed description for the featured image"' . "\n" .
                        "}\n\n" .
                        "IMPORTANT:\n" .
                        "- Respond ONLY with the JSON object, no other text\n" .
                        "- Use proper HTML tags in aigenerated_content (<h2>, <p>, <ul>, <li>, etc.)\n" .
                        "- Escape quotes inside strings with backslash\n" .
                        "- Do not include markdown code blocks";
        
        return $prompt;
    }
    
    /**
     * Build the API request payload
     * 
     * @param string $prompt The prompt
     * @param string $model Model name
     * @param array $task_data Additional task data
     * @return array Payload for API request
     */
    private function build_payload($prompt, $model, $task_data) {
        // Build messages array - some models don't support 'system' role
        // so we prepend system instructions to the user prompt instead
        $system_instruction = 'You are an expert content writer. Write complete, well-structured articles. Follow the format instructions exactly.';
        
        // Combine system instruction with user prompt
        $full_prompt = $system_instruction . "\n\n" . $prompt;
        
        // Use reasonable max_tokens - 4096 is enough for a good article
        // Smaller models tend to repeat themselves with higher limits
        $max_tokens = intval($task_data['max_tokens'] ?? 4096);
        if ($max_tokens > 8192) {
            $max_tokens = 4096; // Cap it for smaller models
        }
        
        $payload = [
            'model' => $model ?: 'default',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $full_prompt
                ]
            ],
            'temperature' => floatval($task_data['temperature'] ?? 0.7),
            'max_tokens' => $max_tokens,
            'stream' => false,
        ];
        
        // Some servers support JSON mode
        if (!empty($task_data['force_json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        
        return $payload;
    }
    
    /**
     * Make HTTP request to self-hosted server
     * 
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param string $api_key API key (optional)
     * @param array $task_data Task data for timeout settings
     * @return array Result with success and response/error
     */
    private function make_request($endpoint, $payload, $api_key, $task_data) {
        $timeout = intval($task_data['selfhosted_timeout'] ?? self::DEFAULT_TIMEOUT);
        
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        
        // Always log to wp-content/debug.log for troubleshooting
        botwriter_log('Direct text request - Endpoint: ' . $endpoint);
        botwriter_log('Direct text request - Model: ' . $payload['model']);
        botwriter_log('Direct text request - Timeout: ' . $timeout . 's');
        
        botwriter_log('Direct text generation request', [
            'endpoint' => $endpoint,
            'model' => $payload['model'],
            'timeout' => $timeout,
        ]);
        
        $start_time = microtime(true);
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => $timeout,
            'sslverify' => false, // Self-hosted often uses self-signed certs
            'data_format' => 'body',
        ]);
        
        $elapsed_time = round(microtime(true) - $start_time, 2);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            botwriter_log('Direct text ERROR: ' . $error_message);
            botwriter_log('Direct text elapsed time: ' . $elapsed_time . 's');
            botwriter_log('Direct text generation error', [
                'endpoint' => $endpoint,
                'error' => $error_message,
                'elapsed_time' => $elapsed_time,
            ], 'error');
            
            return $this->error('Connection error: ' . $error_message);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        botwriter_log('Direct text response - HTTP Code: ' . $http_code);
        botwriter_log('Direct text response - Body length: ' . strlen($body) . ' bytes');
        botwriter_log('Direct text response - Elapsed: ' . $elapsed_time . 's');
        
        botwriter_log('Direct text generation response', [
            'http_code' => $http_code,
            'elapsed_time' => $elapsed_time,
            'body_length' => strlen($body),
        ]);
        
        if ($http_code !== 200) {
            $error_body = json_decode($body, true);
            $error_msg = $error_body['error']['message'] 
                ?? $error_body['error'] 
                ?? "HTTP {$http_code} error";
            
            return $this->error("Self-hosted API error: {$error_msg}");
        }
        
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error('Invalid JSON response from server: ' . json_last_error_msg());
        }
        
        return [
            'success' => true,
            'response' => $decoded,
        ];
    }
    
    /**
     * Parse the API response to extract article content
     * 
     * @param array $response Decoded API response
     * @return array Parsed content or error
     */
    private function parse_response($response) {
        // Extract content from OpenAI-compatible response
        $content_text = $response['choices'][0]['message']['content'] ?? '';
        
        // Log raw response for debugging
        botwriter_log('Raw model response (first 2000 chars): ' . substr($content_text, 0, 2000));
        
        if (empty($content_text)) {
            return $this->error('Empty response from self-hosted model.');
        }
        
        $parsed = [
            'title' => '',
            'content' => '',
            'tags' => '',
            'image_prompt' => '',
        ];
        
        // First, try to parse as JSON (preferred format)
        $json_parsed = json_decode($content_text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            botwriter_log('JSON parse failed: ' . json_last_error_msg());
            // Try to clean and repair JSON
            $json_parsed = $this->repair_json($content_text);
        }
        
        if ($json_parsed) {
            botwriter_log('JSON parsed successfully - keys: ' . implode(', ', array_keys($json_parsed)));
            
            $parsed['title'] = $json_parsed['aigenerated_title'] ?? $json_parsed['title'] ?? '';
            $parsed['content'] = $json_parsed['aigenerated_content'] ?? $json_parsed['content'] ?? '';
            $parsed['tags'] = $json_parsed['aigenerated_tags'] ?? $json_parsed['tags'] ?? '';
            $parsed['image_prompt'] = $json_parsed['aigenerated_image_prompt'] ?? $json_parsed['image_prompt'] ?? $json_parsed['image'] ?? '';
        }
        
        // If JSON failed, try text format as fallback
        if (empty($parsed['title']) || empty($parsed['content'])) {
            botwriter_log('JSON incomplete, trying text format fallback');
            $text_parsed = $this->parse_text_format($content_text);
            
            $parsed['title'] = $parsed['title'] ?: $text_parsed['title'];
            $parsed['content'] = $parsed['content'] ?: $text_parsed['content'];
            $parsed['tags'] = $parsed['tags'] ?: $text_parsed['tags'];
            $parsed['image_prompt'] = $parsed['image_prompt'] ?: $text_parsed['image_prompt'];
        }
        
        // Last resort: regex extraction
        if (empty($parsed['title']) || empty($parsed['content'])) {
            botwriter_log('Trying regex extraction');
            $regex_parsed = $this->extract_fields_regex($content_text);
            
            if ($regex_parsed) {
                $parsed['title'] = $parsed['title'] ?: ($regex_parsed['aigenerated_title'] ?? $regex_parsed['title'] ?? '');
                $parsed['content'] = $parsed['content'] ?: ($regex_parsed['aigenerated_content'] ?? $regex_parsed['content'] ?? '');
                $parsed['tags'] = $parsed['tags'] ?: ($regex_parsed['aigenerated_tags'] ?? $regex_parsed['tags'] ?? '');
                $parsed['image_prompt'] = $parsed['image_prompt'] ?: ($regex_parsed['aigenerated_image_prompt'] ?? $regex_parsed['image_prompt'] ?? '');
            }
        }
        
        botwriter_log('Final parsed - Title: ' . substr($parsed['title'] ?? '', 0, 50) . ', Content length: ' . strlen($parsed['content'] ?? ''));
        
        if (empty($parsed['title'])) {
            return $this->error('Response missing required field: title');
        }
        
        if (empty($parsed['content'])) {
            return $this->error('Response missing required field: content');
        }
        
        return [
            'success' => true,
            'title' => $parsed['title'],
            'content' => $parsed['content'],
            'tags' => $parsed['tags'],
            'image_prompt' => $parsed['image_prompt'],
        ];
    }
    
    /**
     * Parse simple text format (TITLE:, CONTENT:, TAGS:, IMAGE:)
     * 
     * @param string $text Raw text
     * @return array Parsed fields
     */
    private function parse_text_format($text) {
        $result = [
            'title' => '',
            'content' => '',
            'tags' => '',
            'image_prompt' => '',
        ];
        
        // Extract TITLE
        if (preg_match('/TITLE:\s*(.+?)(?=\n(?:CONTENT:|TAGS:|IMAGE:)|$)/si', $text, $m)) {
            $result['title'] = trim($m[1]);
        }
        
        // Extract CONTENT - everything between CONTENT: and (TAGS: or IMAGE: or end)
        if (preg_match('/CONTENT:\s*([\s\S]+?)(?=\n(?:TAGS:|IMAGE:)|$)/i', $text, $m)) {
            $content = trim($m[1]);
            // If content doesn't have HTML tags, wrap paragraphs
            if (strpos($content, '<') === false) {
                $paragraphs = preg_split('/\n\n+/', $content);
                $content = '<p>' . implode('</p><p>', array_map('trim', $paragraphs)) . '</p>';
            }
            $result['content'] = $content;
        }
        
        // Extract TAGS
        if (preg_match('/TAGS:\s*(.+?)(?=\n(?:IMAGE:)|$)/si', $text, $m)) {
            $result['tags'] = trim($m[1]);
        }
        
        // Extract IMAGE
        if (preg_match('/IMAGE:\s*(.+?)$/si', $text, $m)) {
            $result['image_prompt'] = trim($m[1]);
        }
        
        botwriter_log('Text format parse - Title: ' . ($result['title'] ? 'found' : 'not found') . ', Content: ' . (strlen($result['content']) > 0 ? strlen($result['content']) . ' chars' : 'not found'));
        
        return $result;
    }
    
    /**
     * Attempt to repair malformed JSON
     * 
     * @param string $text Raw text that should be JSON
     * @return array|null Parsed array or null
     */
    private function repair_json($text) {
        // Remove leading/trailing whitespace
        $text = trim($text);
        
        // Remove markdown code blocks
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);
        
        // Try parsing again
        $parsed = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsed;
        }
        
        // Try to find JSON object in the text (greedy match)
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json_str = $matches[0];
            
            // Try parsing the extracted JSON
            $parsed = json_decode($json_str, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
            
            // Fix common issues in the extracted JSON
            $json_str = preg_replace('/,\s*}/', '}', $json_str); // Trailing commas
            $json_str = preg_replace('/,\s*]/', ']', $json_str); // Trailing commas in arrays
            
            // Fix unescaped newlines in strings
            $json_str = preg_replace('/(?<!\\\\)\n/', '\\n', $json_str);
            
            $parsed = json_decode($json_str, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        
        botwriter_log('JSON repair: All attempts failed, last error: ' . json_last_error_msg());
        return null;
    }
    
    /**
     * Extract fields using regex as last resort
     * 
     * @param string $text Raw text
     * @return array Extracted fields
     */
    private function extract_fields_regex($text) {
        $result = [];
        
        // Try to extract title - look for the key and capture everything until the next quote
        if (preg_match('/"aigenerated_title"\s*:\s*"([^"]+)"/i', $text, $m)) {
            $result['aigenerated_title'] = $m[1];
        } elseif (preg_match('/"title"\s*:\s*"([^"]+)"/i', $text, $m)) {
            $result['aigenerated_title'] = $m[1];
        }
        
        // Try to extract content - this is complex because content has HTML with quotes
        // Strategy: find the key, then match until we find the closing pattern
        if (preg_match('/"aigenerated_content"\s*:\s*"(.*?)"\s*(?:,\s*"[a-z_]+"\s*:|,?\s*})/is', $text, $m)) {
            $result['aigenerated_content'] = stripcslashes($m[1]);
        } elseif (preg_match('/"content"\s*:\s*"(.*?)"\s*(?:,\s*"[a-z_]+"\s*:|,?\s*})/is', $text, $m)) {
            $result['aigenerated_content'] = stripcslashes($m[1]);
        }
        
        // Alternative: extract content between the key and the next key or end of JSON
        if (empty($result['aigenerated_content'])) {
            // Find position of "aigenerated_content": "
            $content_start = strpos($text, '"aigenerated_content"');
            if ($content_start !== false) {
                // Find the colon and opening quote
                $colon_pos = strpos($text, ':', $content_start);
                if ($colon_pos !== false) {
                    $quote_start = strpos($text, '"', $colon_pos + 1);
                    if ($quote_start !== false) {
                        // Now find the closing quote - it's before the next key or closing brace
                        // Look for pattern: ", "key" or "}
                        $remaining = substr($text, $quote_start + 1);
                        
                        // Find positions of possible end markers
                        $end_patterns = ['", "aigenerated_tags"', '", "image_prompt"', '", "tags"', '"}'];
                        $end_pos = strlen($remaining);
                        
                        foreach ($end_patterns as $pattern) {
                            $pos = strpos($remaining, $pattern);
                            if ($pos !== false && $pos < $end_pos) {
                                $end_pos = $pos;
                            }
                        }
                        
                        // Also look for just "} at the end
                        $pos = strrpos($remaining, '"}');
                        if ($pos !== false && $pos < $end_pos) {
                            $end_pos = $pos;
                        }
                        
                        $content = substr($remaining, 0, $end_pos);
                        if (!empty($content)) {
                            $result['aigenerated_content'] = stripcslashes($content);
                            botwriter_log('Content extracted via position method, length: ' . strlen($content));
                        }
                    }
                }
            }
        }
        
        // Try to extract tags
        if (preg_match('/"aigenerated_tags"\s*:\s*"([^"]+)"/i', $text, $m)) {
            $result['aigenerated_tags'] = $m[1];
        } elseif (preg_match('/"tags"\s*:\s*"([^"]+)"/i', $text, $m)) {
            $result['aigenerated_tags'] = $m[1];
        }
        
        // Try to extract image prompt
        if (preg_match('/"image_prompt"\s*:\s*"([^"]+)"/i', $text, $m)) {
            $result['image_prompt'] = $m[1];
        }
        
        botwriter_log('Regex extraction result: ' . implode(', ', array_keys($result)));
        
        return $result;
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
