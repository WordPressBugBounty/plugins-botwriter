<?php
/**
 * Direct Image Generator for Self-Hosted Models
 * 
 * Handles image generation directly from the plugin to self-hosted
 * AI servers (LocalAI, Automatic1111, ComfyUI) without going through botwriter.com
 * 
 * @package BotWriter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BotWriter_Direct_Image_Generator
 */
class BotWriter_Direct_Image_Generator {
    
    /**
     * Supported provider types
     */
    const TYPE_OPENAI = 'openai';          // LocalAI or OpenAI-compatible
    const TYPE_AUTOMATIC1111 = 'automatic1111'; // Stable Diffusion WebUI
    const TYPE_COMFYUI = 'comfyui';         // ComfyUI workflow-based
    
    /**
     * Default timeouts
     */
    const DEFAULT_TIMEOUT = 400;           // 6.5 minutes for most providers
    const COMFYUI_POLL_INTERVAL = 10;      // Poll every 10 seconds
    const COMFYUI_MAX_POLL_TIME = 600;     // Max 10 minutes
    
    /**
     * Generate image using self-hosted model
     * 
     * @param array $task_data Task data with URL, provider type, prompt, etc.
     * @return array Result with success status, image_url/image_data, or error
     */
    public function generate($task_data) {
        $url = $task_data['selfhosted_image_url'] ?? '';
        $provider_type = $task_data['selfhosted_image_type'] ?? self::TYPE_OPENAI;
        $prompt = $task_data['image_prompt'] ?? '';
        
        // Validate
        if (empty($url)) {
            return $this->error('Self-hosted image server URL is not configured.');
        }
        
        if (empty($prompt)) {
            return $this->error('Image prompt is empty.');
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('Invalid self-hosted image server URL: ' . $url);
        }
        
        botwriter_log('Direct image generation', [
            'provider_type' => $provider_type,
            'url' => $url,
            'prompt_length' => strlen($prompt),
        ]);
        
        // Route to appropriate handler
        switch ($provider_type) {
            case self::TYPE_AUTOMATIC1111:
                return $this->generate_automatic1111($url, $prompt, $task_data);
                
            case self::TYPE_COMFYUI:
                return $this->generate_comfyui($url, $prompt, $task_data);
                
            case self::TYPE_OPENAI:
            default:
                return $this->generate_openai($url, $prompt, $task_data);
        }
    }
    
    /**
     * Generate image using OpenAI-compatible API (LocalAI, etc.)
     * 
     * @param string $url Server URL
     * @param string $prompt Image prompt
     * @param array $task_data Additional settings
     * @return array Result
     */
    private function generate_openai($url, $prompt, $task_data) {
        $endpoint = rtrim($url, '/') . '/v1/images/generations';
        
        $size = $task_data['image_size'] ?? '1024x1024';
        $model = $task_data['selfhosted_image_model'] ?? 'dall-e-3';
        $api_key = $task_data['selfhosted_image_api_key'] ?? '';
        
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'b64_json', // Get base64 to avoid URL issues
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => intval($task_data['selfhosted_image_timeout'] ?? self::DEFAULT_TIMEOUT),
            'sslverify' => false,
        ]);
        
        if (is_wp_error($response)) {
            return $this->error('Connection error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            $error = json_decode($body, true);
            $msg = $error['error']['message'] ?? $error['error'] ?? "HTTP {$http_code}";
            return $this->error("OpenAI-compatible API error: {$msg}");
        }
        
        $decoded = json_decode($body, true);
        
        // Extract image data
        if (!empty($decoded['data'][0]['b64_json'])) {
            return [
                'success' => true,
                'image_data' => $decoded['data'][0]['b64_json'],
                'format' => 'base64',
            ];
        } elseif (!empty($decoded['data'][0]['url'])) {
            return [
                'success' => true,
                'image_url' => $decoded['data'][0]['url'],
                'format' => 'url',
            ];
        }
        
        return $this->error('No image data in response.');
    }
    
    /**
     * Generate image using Automatic1111 WebUI API
     * 
     * @param string $url Server URL
     * @param string $prompt Image prompt
     * @param array $task_data Additional settings
     * @return array Result
     */
    private function generate_automatic1111($url, $prompt, $task_data) {
        $endpoint = rtrim($url, '/') . '/sdapi/v1/txt2img';
        
        // Parse size
        $size = $task_data['image_size'] ?? '1024x1024';
        $parts = explode('x', $size);
        $width = intval($parts[0] ?? 1024);
        $height = intval($parts[1] ?? 1024);
        
        // Get settings
        $negative_prompt = $task_data['negative_prompt'] ?? 'blurry, low quality, distorted, deformed';
        $steps = intval($task_data['a1111_steps'] ?? 20);
        $cfg_scale = floatval($task_data['a1111_cfg_scale'] ?? 7.0);
        $sampler = $task_data['a1111_sampler'] ?? 'DPM++ 2M Karras';
        
        $payload = [
            'prompt' => $prompt,
            'negative_prompt' => $negative_prompt,
            'width' => $width,
            'height' => $height,
            'steps' => $steps,
            'cfg_scale' => $cfg_scale,
            'sampler_name' => $sampler,
            'n_iter' => 1,
            'batch_size' => 1,
        ];
        
        // Optional: override model
        if (!empty($task_data['a1111_checkpoint'])) {
            $payload['override_settings'] = [
                'sd_model_checkpoint' => $task_data['a1111_checkpoint'],
            ];
        }
        
        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => intval($task_data['selfhosted_image_timeout'] ?? self::DEFAULT_TIMEOUT),
            'sslverify' => false,
        ]);
        
        if (is_wp_error($response)) {
            return $this->error('Connection error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            $error = json_decode($body, true);
            $msg = $error['detail'] ?? $error['error'] ?? "HTTP {$http_code}";
            return $this->error("Automatic1111 API error: {$msg}");
        }
        
        $decoded = json_decode($body, true);
        
        if (!empty($decoded['images'][0])) {
            return [
                'success' => true,
                'image_data' => $decoded['images'][0],
                'format' => 'base64',
            ];
        }
        
        return $this->error('No image data in Automatic1111 response.');
    }
    
    /**
     * Generate image using ComfyUI API (with polling)
     * 
     * @param string $url Server URL
     * @param string $prompt Image prompt
     * @param array $task_data Additional settings
     * @return array Result or pending status for polling
     */
    private function generate_comfyui($url, $prompt, $task_data) {
        $url = rtrim($url, '/');
        
        // Check if this is a poll request
        if (!empty($task_data['comfyui_prompt_id'])) {
            return $this->poll_comfyui($url, $task_data['comfyui_prompt_id'], $task_data);
        }
        
        // Get workflow template
        $workflow = $this->get_comfyui_workflow($prompt, $task_data);
        
        // Queue the prompt
        $queue_response = wp_remote_post($url . '/prompt', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['prompt' => $workflow]),
            'timeout' => 30,
            'sslverify' => false,
        ]);
        
        if (is_wp_error($queue_response)) {
            return $this->error('ComfyUI connection error: ' . $queue_response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($queue_response);
        $body = wp_remote_retrieve_body($queue_response);
        
        if ($http_code !== 200) {
            $error = json_decode($body, true);
            $msg = $error['error']['message'] ?? $error['error'] ?? "HTTP {$http_code}";
            return $this->error("ComfyUI queue error: {$msg}");
        }
        
        $decoded = json_decode($body, true);
        $prompt_id = $decoded['prompt_id'] ?? '';
        
        if (empty($prompt_id)) {
            return $this->error('ComfyUI did not return a prompt_id.');
        }
        
        botwriter_log('ComfyUI prompt queued', ['prompt_id' => $prompt_id]);
        
        // Return pending status - caller should schedule polling
        return [
            'success' => true,
            'status' => 'pending',
            'comfyui_prompt_id' => $prompt_id,
            'poll_interval' => self::COMFYUI_POLL_INTERVAL,
            'max_poll_time' => self::COMFYUI_MAX_POLL_TIME,
        ];
    }
    
    /**
     * Poll ComfyUI for generation result
     * 
     * @param string $url Server URL
     * @param string $prompt_id The prompt ID to check
     * @param array $task_data Additional settings
     * @return array Result
     */
    public function poll_comfyui($url, $prompt_id, $task_data) {
        $url = rtrim($url, '/');
        
        // Check history for this prompt
        $history_response = wp_remote_get($url . '/history/' . $prompt_id, [
            'timeout' => 30,
            'sslverify' => false,
        ]);
        
        if (is_wp_error($history_response)) {
            return $this->error('ComfyUI poll error: ' . $history_response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($history_response);
        $history = json_decode($body, true);
        
        // Check if our prompt is in history
        if (empty($history[$prompt_id])) {
            // Still processing
            $elapsed = intval($task_data['comfyui_elapsed'] ?? 0);
            if ($elapsed >= self::COMFYUI_MAX_POLL_TIME) {
                return $this->error('ComfyUI generation timed out after ' . self::COMFYUI_MAX_POLL_TIME . ' seconds.');
            }
            
            return [
                'success' => true,
                'status' => 'pending',
                'comfyui_prompt_id' => $prompt_id,
                'poll_interval' => self::COMFYUI_POLL_INTERVAL,
            ];
        }
        
        $prompt_history = $history[$prompt_id];
        
        // Check for errors
        if (!empty($prompt_history['status']['status_str']) && 
            $prompt_history['status']['status_str'] !== 'success') {
            $errors = $prompt_history['status']['messages'] ?? [];
            $error_msg = !empty($errors) ? implode(', ', $errors) : 'Unknown error';
            return $this->error('ComfyUI generation failed: ' . $error_msg);
        }
        
        // Get output images
        $outputs = $prompt_history['outputs'] ?? [];
        
        foreach ($outputs as $node_outputs) {
            if (!empty($node_outputs['images'])) {
                $image = $node_outputs['images'][0];
                $filename = $image['filename'];
                $subfolder = $image['subfolder'] ?? '';
                
                // Fetch the image
                $image_url = $url . '/view?' . http_build_query([
                    'filename' => $filename,
                    'subfolder' => $subfolder,
                    'type' => $image['type'] ?? 'output',
                ]);
                
                $image_response = wp_remote_get($image_url, [
                    'timeout' => 60,
                    'sslverify' => false,
                ]);
                
                if (is_wp_error($image_response)) {
                    continue;
                }
                
                $image_data = wp_remote_retrieve_body($image_response);
                
                if (!empty($image_data)) {
                    return [
                        'success' => true,
                        'image_data' => base64_encode($image_data),
                        'format' => 'base64',
                        'filename' => $filename,
                    ];
                }
            }
        }
        
        return $this->error('ComfyUI completed but no images found in output.');
    }
    
    /**
     * Get ComfyUI workflow for image generation
     * 
     * @param string $prompt Image prompt
     * @param array $task_data Settings
     * @return array Workflow definition
     */
    private function get_comfyui_workflow($prompt, $task_data) {
        // Check for custom workflow
        if (!empty($task_data['comfyui_workflow'])) {
            $workflow = json_decode($task_data['comfyui_workflow'], true);
            if ($workflow) {
                // Replace prompt placeholder
                $workflow_json = json_encode($workflow);
                $workflow_json = str_replace('{{PROMPT}}', addslashes($prompt), $workflow_json);
                return json_decode($workflow_json, true);
            }
        }
        
        // Default simple workflow for SDXL
        $size = $task_data['image_size'] ?? '1024x1024';
        $parts = explode('x', $size);
        $width = intval($parts[0] ?? 1024);
        $height = intval($parts[1] ?? 1024);
        
        $negative = $task_data['negative_prompt'] ?? 'blurry, low quality, distorted, deformed, ugly';
        $steps = intval($task_data['comfyui_steps'] ?? 20);
        $cfg = floatval($task_data['comfyui_cfg'] ?? 7.0);
        $checkpoint = $task_data['comfyui_checkpoint'] ?? 'sd_xl_base_1.0.safetensors';
        
        // Basic SDXL workflow
        return [
            '1' => [
                'class_type' => 'CheckpointLoaderSimple',
                'inputs' => [
                    'ckpt_name' => $checkpoint,
                ],
            ],
            '2' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => $prompt,
                    'clip' => ['1', 1],
                ],
            ],
            '3' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => $negative,
                    'clip' => ['1', 1],
                ],
            ],
            '4' => [
                'class_type' => 'EmptyLatentImage',
                'inputs' => [
                    'width' => $width,
                    'height' => $height,
                    'batch_size' => 1,
                ],
            ],
            '5' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'seed' => wp_rand(0, 999999999),
                    'steps' => $steps,
                    'cfg' => $cfg,
                    'sampler_name' => 'euler',
                    'scheduler' => 'normal',
                    'denoise' => 1.0,
                    'model' => ['1', 0],
                    'positive' => ['2', 0],
                    'negative' => ['3', 0],
                    'latent_image' => ['4', 0],
                ],
            ],
            '6' => [
                'class_type' => 'VAEDecode',
                'inputs' => [
                    'samples' => ['5', 0],
                    'vae' => ['1', 2],
                ],
            ],
            '7' => [
                'class_type' => 'SaveImage',
                'inputs' => [
                    'filename_prefix' => 'botwriter',
                    'images' => ['6', 0],
                ],
            ],
        ];
    }
    
    /**
     * Save image to WordPress media library
     * 
     * @param array $result Generation result
     * @param int $post_id Optional post to attach to
     * @return array Result with attachment ID or error
     */
    public function save_to_media_library($result, $post_id = 0) {
        if (!$result['success']) {
            return $result;
        }
        
        // Get image data
        if ($result['format'] === 'base64') {
            $image_data = base64_decode($result['image_data']);
        } elseif ($result['format'] === 'url') {
            $response = wp_remote_get($result['image_url'], [
                'timeout' => 60,
                'sslverify' => false,
            ]);
            
            if (is_wp_error($response)) {
                return $this->error('Failed to download image: ' . $response->get_error_message());
            }
            
            $image_data = wp_remote_retrieve_body($response);
        } else {
            return $this->error('Unknown image format: ' . $result['format']);
        }
        
        if (empty($image_data)) {
            return $this->error('Empty image data.');
        }
        
        // Detect image type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        
        $extension_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        
        $extension = $extension_map[$mime_type] ?? 'png';
        
        // Generate filename
        $filename = 'botwriter-' . uniqid() . '.' . $extension;
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if (!empty($upload['error'])) {
            return $this->error('Upload error: ' . $upload['error']);
        }
        
        // Create attachment
        $attachment = [
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        
        if (is_wp_error($attachment_id)) {
            return $this->error('Attachment error: ' . $attachment_id->get_error_message());
        }
        
        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        ];
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
