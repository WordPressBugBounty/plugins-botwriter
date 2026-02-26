<?php
/**
 * BotWriter Settings Page
 * 
 * Modular settings page with tabbed interface for Text AI and Image AI providers.
 * Each provider has its own configuration file in the settings/ folder.
 * Auto-saves via AJAX when any field changes.
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include models manager
require_once plugin_dir_path(__FILE__) . 'models.php';

// Include provider configuration files
$botwriter_settings_dir = plugin_dir_path(__FILE__) . 'settings/';
require_once $botwriter_settings_dir . 'openai.php';
require_once $botwriter_settings_dir . 'anthropic.php';
require_once $botwriter_settings_dir . 'google.php';
require_once $botwriter_settings_dir . 'mistral.php';
require_once $botwriter_settings_dir . 'groq.php';
require_once $botwriter_settings_dir . 'openrouter.php';
require_once $botwriter_settings_dir . 'dalle.php';
require_once $botwriter_settings_dir . 'gemini.php';
require_once $botwriter_settings_dir . 'fal.php';
require_once $botwriter_settings_dir . 'replicate.php';
require_once $botwriter_settings_dir . 'stability.php';
require_once $botwriter_settings_dir . 'cloudflare.php';
require_once $botwriter_settings_dir . 'custom.php';

// Global notice accumulator for settings-related warnings
$botwriter_notice = '';

// Register AJAX handlers
add_action('wp_ajax_botwriter_save_settings', 'botwriter_ajax_save_settings');
add_action('wp_ajax_botwriter_test_api_key', 'botwriter_ajax_test_api_key');
add_action('wp_ajax_botwriter_test_model', 'botwriter_ajax_test_model');
add_action('wp_ajax_botwriter_reset_models', 'botwriter_ajax_reset_models');
add_action('wp_ajax_botwriter_test_custom_provider', 'botwriter_ajax_test_custom_provider');

/**
 * AJAX handler to test API key connectivity
 * Uses the /models endpoint which doesn't consume tokens
 */
function botwriter_ajax_test_api_key() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'botwriter_settings_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'botwriter')));
    }

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

    if (empty($provider)) {
        wp_send_json_error(array('message' => __('No provider specified.', 'botwriter')));
    }

    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('Please enter an API key first.', 'botwriter')));
    }

    // Provider endpoints for testing (using /models which is free and doesn't consume tokens)
    $providers_config = array(
        // Text providers
        'openai' => array(
            'url' => 'https://api.openai.com/v1/models',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'anthropic' => array(
            'url' => 'https://api.anthropic.com/v1/models',
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
        ),
        'google' => array(
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
            'headers' => array(),
        ),
        'mistral' => array(
            'url' => 'https://api.mistral.ai/v1/models',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'groq' => array(
            'url' => 'https://api.groq.com/openai/v1/models',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'openrouter' => array(
            'url' => 'https://openrouter.ai/api/v1/models',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        // Image providers
        'dalle' => array(
            'url' => 'https://api.openai.com/v1/models',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'fal' => array(
            'url' => 'https://api.fal.ai/v1/models',
            'headers' => array(
                'Authorization' => 'Key ' . $api_key,
            ),
        ),
        'replicate' => array(
            'url' => 'https://api.replicate.com/v1/account',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'stability' => array(
            'url' => 'https://api.stability.ai/v1/user/account',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        'cloudflare' => array(
            'url' => 'https://api.cloudflare.com/client/v4/user/tokens/verify',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ),
        // Gemini image uses Google text provider API key
        'gemini' => array(
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
            'headers' => array(),
        ),
    );

    if (!isset($providers_config[$provider])) {
        wp_send_json_error(array('message' => __('Unknown provider.', 'botwriter')));
    }

    $config = $providers_config[$provider];
    $ssl_verify = get_option('botwriter_sslverify', 'yes') === 'yes';

    $response = wp_remote_get($config['url'], array(
        'timeout' => 15,
        'sslverify' => $ssl_verify,
        'headers' => $config['headers'],
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'message' => sprintf(
                /* translators: %s: Error message from the API */
                __('Connection error: %s', 'botwriter'),
                $response->get_error_message()
            ),
        ));
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check for success (200 OK)
    if ($code === 200) {
        // Extract models list
        $models = array();
        $models_data = array();
        
        if (isset($data['data']) && is_array($data['data'])) {
            $models_data = $data['data'];
        } elseif (isset($data['models']) && is_array($data['models'])) {
            $models_data = $data['models'];
        }
        
        // Build models array with id and name
        foreach ($models_data as $model) {
            $model_id = '';
            $model_name = '';
            
            // Different providers have different response structures
            if (isset($model['id'])) {
                $model_id = $model['id'];
                $model_name = isset($model['name']) ? $model['name'] : $model['id'];
            } elseif (isset($model['name'])) {
                $model_id = $model['name'];
                $model_name = isset($model['displayName']) ? $model['displayName'] : (isset($model['display_name']) ? $model['display_name'] : $model['name']);
            }
            
            // Google Gemini returns model names with "models/" prefix - remove it
            if (strpos($model_id, 'models/') === 0) {
                $model_id = substr($model_id, 7); // Remove "models/" prefix
            }
            if (strpos($model_name, 'models/') === 0) {
                $model_name = substr($model_name, 7);
            }
            
            // Filter OpenAI models: exclude non-text models
            if ($provider === 'openai') {
                $exclude_patterns = array('dall-e', 'whisper', 'tts', 'embedding', 'moderation');
                $should_exclude = false;
                foreach ($exclude_patterns as $pattern) {
                    if (stripos($model_id, $pattern) !== false) {
                        $should_exclude = true;
                        break;
                    }
                }
                if ($should_exclude) {
                    continue;
                }
            }
            
            // Filter Google models: only those supporting generateContent
            if ($provider === 'google') {
                $supported_methods = isset($model['supportedGenerationMethods']) ? $model['supportedGenerationMethods'] : array();
                if (!in_array('generateContent', $supported_methods)) {
                    continue;
                }
            }
            
            if (!empty($model_id)) {
                $models[] = array(
                    'id' => $model_id,
                    'name' => $model_name,
                );
            }
        }
        
        // Sort models alphabetically by id
        usort($models, function($a, $b) {
            return strcasecmp($a['id'], $b['id']);
        });
        
        $model_count = count($models);

        // Save models to database using our models manager
        if ($model_count > 0) {
            botwriter_update_provider_all_models($provider, $models);
        }

        $message = __('API key is valid!', 'botwriter');
        if ($model_count > 0) {
            $message .= ' ' . sprintf(
                /* translators: %d: Number of models available */
                _n('%d model available.', '%d models available.', $model_count, 'botwriter'),
                $model_count
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'models' => $models,
            'provider' => $provider,
        ));
    }

    // Handle error responses
    $error_message = __('Invalid API key.', 'botwriter');
    
    if ($code === 401 || $code === 403) {
        $error_message = __('Invalid or unauthorized API key.', 'botwriter');
    } elseif ($code === 429) {
        $error_message = __('Rate limit exceeded. Please try again later.', 'botwriter');
    } elseif ($code === 500 || $code === 502 || $code === 503) {
        $error_message = __('Provider service temporarily unavailable.', 'botwriter');
    }

    // Try to get error message from response
    if (isset($data['error']['message'])) {
        $error_message = sanitize_text_field($data['error']['message']);
    } elseif (isset($data['message'])) {
        $error_message = sanitize_text_field($data['message']);
    }

    wp_send_json_error(array('message' => $error_message));
}

/**
 * AJAX handler to test model connectivity
 * Sends a minimal prompt to verify the model works
 */
function botwriter_ajax_test_model() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'botwriter_settings_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'botwriter')));
    }

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
    $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';

    if (empty($provider)) {
        wp_send_json_error(array('message' => __('No provider specified.', 'botwriter')));
    }

    if (empty($model)) {
        wp_send_json_error(array('message' => __('No model specified.', 'botwriter')));
    }

    // Get API key for provider
    $api_key_option = 'botwriter_' . $provider . '_api_key';
    $api_key = botwriter_decrypt_api_key(get_option($api_key_option));

    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('Please configure the API key first.', 'botwriter')));
    }

    $ssl_verify = get_option('botwriter_sslverify', 'yes') === 'yes';
    $test_message = 'Say "Ready!" in one word.';

    // Provider-specific API calls
    switch ($provider) {
        case 'openai':
            // GPT-5 and GPT-4.1 models require max_completion_tokens instead of max_tokens
            $is_new_model = preg_match('/^(gpt-5|gpt-4\.1|o\d)/i', $model);
            $token_param = $is_new_model ? 'max_completion_tokens' : 'max_tokens';
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array('role' => 'user', 'content' => $test_message)
                    ),
                    $token_param => 10,
                )),
            ));
            break;

        case 'anthropic':
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => array(
                        array('role' => 'user', 'content' => $test_message)
                    ),
                )),
            ));
            break;

        case 'google':
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
            $response = wp_remote_post($url, array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $test_message)
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'maxOutputTokens' => 10,
                    ),
                )),
            ));
            break;

        case 'mistral':
            $response = wp_remote_post('https://api.mistral.ai/v1/chat/completions', array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array('role' => 'user', 'content' => $test_message)
                    ),
                    'max_tokens' => 10,
                )),
            ));
            break;

        case 'groq':
            $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array('role' => 'user', 'content' => $test_message)
                    ),
                    'max_tokens' => 10,
                )),
            ));
            break;

        case 'openrouter':
            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
                'timeout' => 30,
                'sslverify' => $ssl_verify,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(),
                    'X-Title' => 'BotWriter',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array('role' => 'user', 'content' => $test_message)
                    ),
                    'max_tokens' => 10,
                )),
            ));
            break;

        default:
            wp_send_json_error(array('message' => __('Unknown provider.', 'botwriter')));
            return;
    }

    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'message' => sprintf(
                /* translators: %s: Error message */
                __('Connection error: %s', 'botwriter'),
                $response->get_error_message()
            ),
        ));
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Extract response text based on provider
    $reply = '';
    if ($code === 200) {
        switch ($provider) {
            case 'openai':
            case 'mistral':
            case 'groq':
            case 'openrouter':
                $reply = $data['choices'][0]['message']['content'] ?? '';
                break;
            case 'anthropic':
                $reply = $data['content'][0]['text'] ?? '';
                break;
            case 'google':
                $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                break;
        }
        
        if (!empty($reply)) {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %s: Model response */
                    __('Model responded: "%s"', 'botwriter'),
                    esc_html(trim($reply))
                ),
            ));
        } else {
            wp_send_json_success(array('message' => __('Model is working!', 'botwriter')));
        }
    }

    // Handle error responses
    $error_message = __('Model test failed.', 'botwriter');
    
    if ($code === 401 || $code === 403) {
        $error_message = __('Invalid or unauthorized API key.', 'botwriter');
    } elseif ($code === 404) {
        $error_message = __('Model not found. It may not be available for your account.', 'botwriter');
    } elseif ($code === 429) {
        $error_message = __('Rate limit exceeded. Please try again later.', 'botwriter');
    } elseif ($code === 500 || $code === 502 || $code === 503) {
        $error_message = __('Provider service temporarily unavailable.', 'botwriter');
    }

    // Try to get error message from response
    if (isset($data['error']['message'])) {
        $error_message = sanitize_text_field($data['error']['message']);
    } elseif (isset($data['message'])) {
        $error_message = sanitize_text_field($data['message']);
    }

    wp_send_json_error(array('message' => $error_message));
}

/**
 * AJAX handler to reset models to default
 */
function botwriter_ajax_reset_models() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'botwriter_settings_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'botwriter')));
    }

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    // Reset models to default
    if (botwriter_reset_models_to_default()) {
        wp_send_json_success(array(
            'message' => __('Models reset to defaults successfully!', 'botwriter'),
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Failed to reset models. Please try again.', 'botwriter'),
        ));
    }
}

/**
 * AJAX handler to test custom provider connections
 */
function botwriter_ajax_test_custom_provider() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'botwriter_settings_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'botwriter')));
    }

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'botwriter')));
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'text';
    $image_type = isset($_POST['image_type']) ? sanitize_text_field(wp_unslash($_POST['image_type'])) : '';

    if (empty($url)) {
        wp_send_json_error(array('message' => __('Please enter a URL.', 'botwriter')));
    }

    $ssl_verify = get_option('botwriter_sslverify', 'yes') === 'yes';
    $headers = array('Content-Type' => 'application/json');
    
    if (!empty($api_key)) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    if ($type === 'text') {
        // Test text provider by fetching models list
        $models_url = rtrim($url, '/') . '/models';
        
        $response = wp_remote_get($models_url, array(
            'timeout' => 15,
            'sslverify' => $ssl_verify,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __('Connection failed: %s', 'botwriter'),
                    $response->get_error_message()
                ),
            ));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code === 200) {
            $models = array();
            
            // Extract model IDs from response
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $model) {
                    if (isset($model['id'])) {
                        $models[] = $model['id'];
                    }
                }
            } elseif (isset($data['models']) && is_array($data['models'])) {
                // Ollama format
                foreach ($data['models'] as $model) {
                    if (isset($model['name'])) {
                        $models[] = $model['name'];
                    }
                }
            }

            wp_send_json_success(array(
                'message' => __('Connection successful!', 'botwriter'),
                'models' => $models,
            ));
        }

        // Handle errors
        $error_msg = __('Connection failed.', 'botwriter');
        if ($code === 401 || $code === 403) {
            $error_msg = __('Authentication failed. Check your API key.', 'botwriter');
        } elseif ($code === 404) {
            // Try alternative endpoint for Ollama
            $alt_url = rtrim($url, '/') . '/api/tags';
            $alt_response = wp_remote_get($alt_url, array(
                'timeout' => 15,
                'sslverify' => $ssl_verify,
                'headers' => $headers,
            ));
            
            if (!is_wp_error($alt_response) && wp_remote_retrieve_response_code($alt_response) === 200) {
                $alt_data = json_decode(wp_remote_retrieve_body($alt_response), true);
                $models = array();
                if (isset($alt_data['models'])) {
                    foreach ($alt_data['models'] as $model) {
                        $models[] = $model['name'] ?? $model['model'] ?? '';
                    }
                }
                wp_send_json_success(array(
                    'message' => __('Connection successful! (Ollama detected)', 'botwriter'),
                    'models' => array_filter($models),
                ));
            }
            $error_msg = __('Endpoint not found. Check the URL.', 'botwriter');
        } elseif (isset($data['error']['message'])) {
            $error_msg = sanitize_text_field($data['error']['message']);
        }

        wp_send_json_error(array('message' => $error_msg));

    } else {
        // Test image provider
        if ($image_type === 'automatic1111') {
            // Test A1111 by checking options endpoint
            $test_url = rtrim($url, '/') . '/sdapi/v1/options';
        } elseif ($image_type === 'comfyui') {
            // Test ComfyUI by checking system stats
            $test_url = rtrim($url, '/') . '/system_stats';
        } else {
            // Generic OpenAI-compatible
            $test_url = rtrim($url, '/') . '/models';
        }

        $response = wp_remote_get($test_url, array(
            'timeout' => 15,
            'sslverify' => $ssl_verify,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __('Connection failed: %s', 'botwriter'),
                    $response->get_error_message()
                ),
            ));
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            $type_names = array(
                'automatic1111' => 'Automatic1111',
                'comfyui' => 'ComfyUI',
                'openai' => __('OpenAI-compatible server', 'botwriter'),
            );
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %s: Server type name */
                    __('%s detected! Connection successful.', 'botwriter'),
                    $type_names[$image_type] ?? __('Server', 'botwriter')
                ),
            ));
        }

        wp_send_json_error(array(
            'message' => sprintf(
                /* translators: %d: HTTP status code */
                __('Connection failed. HTTP %d', 'botwriter'),
                $code
            ),
        ));
    }
}

/**
 * AJAX handler to save individual settings
 */
function botwriter_ajax_save_settings() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'botwriter_settings_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'botwriter')]);
    }

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied.', 'botwriter')]);
    }

    $field = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';
    $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

    if (empty($field)) {
        wp_send_json_error(['message' => __('No field specified.', 'botwriter')]);
    }

    // List of allowed fields
    $allowed_fields = [
        'botwriter_text_provider',
        'botwriter_image_provider',
        'botwriter_openai_api_key',
        'botwriter_anthropic_api_key',
        'botwriter_google_api_key',
        'botwriter_mistral_api_key',
        'botwriter_groq_api_key',
        'botwriter_openrouter_api_key',
        'botwriter_fal_api_key',
        'botwriter_replicate_api_key',
        'botwriter_stability_api_key',
        'botwriter_cloudflare_api_key',
        'botwriter_cloudflare_account_id',
        'botwriter_openai_model',
        'botwriter_anthropic_model',
        'botwriter_google_model',
        'botwriter_mistral_model',
        'botwriter_groq_model',
        'botwriter_openrouter_model',
        'botwriter_dalle_model',
        'botwriter_gemini_image_model',
        'botwriter_fal_model',
        'botwriter_replicate_model',
        'botwriter_stability_model',
        'botwriter_cloudflare_model',
        'botwriter_ai_image_size',
        'botwriter_ai_image_quality',
        'botwriter_ai_image_style',
        'botwriter_ai_image_style_custom',
        'botwriter_image_postprocess_enabled',
        'botwriter_image_output_format',
        'botwriter_image_max_width',
        'botwriter_image_compression',
        'botwriter_image_max_filesize',
        'botwriter_sslverify',
        'botwriter_cron_active',
        'botwriter_paused_tasks',
        'botwriter_tags_disabled',
        'botwriter_meta_disabled',
        // Custom provider fields (text)
        'botwriter_custom_text_url',
        'botwriter_custom_text_api_key',
        'botwriter_custom_text_model',
        // Custom provider fields (image)
        'botwriter_custom_image_type',
        'botwriter_custom_image_url',
        'botwriter_custom_image_model',
        // SEO Translation fields
        'botwriter_seo_translation_enabled',
        'botwriter_seo_target_language',
        'botwriter_seo_translate_title',
        'botwriter_seo_translate_tags',
        'botwriter_seo_translate_image',
    ];

    if (!in_array($field, $allowed_fields)) {
        wp_send_json_error(['message' => __('Invalid field.', 'botwriter')]);
    }

    // API key fields that need encryption
    $api_key_fields = [
        'botwriter_openai_api_key',
        'botwriter_anthropic_api_key',
        'botwriter_google_api_key',
        'botwriter_mistral_api_key',
        'botwriter_groq_api_key',
        'botwriter_openrouter_api_key',
        'botwriter_fal_api_key',
        'botwriter_replicate_api_key',
        'botwriter_stability_api_key',
        'botwriter_cloudflare_api_key',
        'botwriter_custom_text_api_key',
    ];

    // Process the value
    // URL fields that need esc_url_raw sanitization
    $url_fields = [
        'botwriter_custom_text_url',
        'botwriter_custom_image_url',
    ];

    if (in_array($field, $api_key_fields)) {
        $value = sanitize_text_field($value);
        if (!empty($value)) {
            // Special validation for OpenAI key
            if ($field === 'botwriter_openai_api_key') {
                if (strpos($value, 'sk-') !== 0) {
                    wp_send_json_error(['message' => __('OpenAI API Key must start with "sk-".', 'botwriter')]);
                }
            }
            $value = botwriter_encrypt_api_key_generic($value);
        }
    } elseif (in_array($field, $url_fields)) {
        $value = esc_url_raw($value);
    } elseif ($field === 'botwriter_email') {
        $value = sanitize_email($value);
    } elseif ($field === 'botwriter_paused_tasks') {
        $value = max(2, intval($value));
    } elseif ($field === 'botwriter_cron_active' || $field === 'botwriter_tags_disabled'
        || $field === 'botwriter_meta_disabled'
        || $field === 'botwriter_seo_translation_enabled' || $field === 'botwriter_seo_translate_title'
        || $field === 'botwriter_seo_translate_tags' || $field === 'botwriter_seo_translate_image') {
        $value = ($value === '1' || $value === 'true' || $value === true) ? '1' : '0';
    } else {
        $value = sanitize_text_field($value);
    }

    // Save the option
    update_option($field, $value);

    // Handle cron activation/deactivation
    if ($field === 'botwriter_cron_active') {
        if ($value === '1') {
            botwriter_scheduled_events_plugin_activate();
        } else {
            botwriter_scheduled_events_plugin_deactivate();
        }
    }

    // Enforce image provider coherence when text provider changes to 'custom'
    if ($field === 'botwriter_text_provider' && $value === 'custom') {
        $current_image_provider = get_option('botwriter_image_provider', 'dalle');
        $allowed_image_providers = ['custom', 'none'];
        
        if (!in_array($current_image_provider, $allowed_image_providers)) {
            update_option('botwriter_image_provider', 'none');
        }
    }

    wp_send_json_success(['message' => __('Saved', 'botwriter'), 'field' => $field]);
}

/**
 * Main settings page handler
 */
function botwriter_settings_page_handler() {
    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'botwriter'));
    } 

    // Add metabox
    add_meta_box(
        'botwriter_settings',
        __('WP BotWriter Settings', 'botwriter'),
        'botwriter_settings_meta_box_handler',
        'botwriter_settings_page',
        'normal',
        'default'
    );

    ?>
    <div class="wrap botwriter-settings-wrap">
        <h2><?php esc_html_e('Settings', 'botwriter'); ?>
            <span id="botwriter-save-status" class="botwriter-save-indicator"></span>
        </h2>

        <div id="botwriter-settings-form">
            <input type="hidden" id="botwriter_settings_nonce" value="<?php echo esc_attr(wp_create_nonce('botwriter_settings_nonce')); ?>" />
            <input id="botwriter_domain_name" type="hidden" name="url" value="<?php echo esc_attr(get_site_url()); ?>" />

            <div class="metabox-holder" id="poststuff">
                <div id="post-body">
                    <div id="post-body-content">
                        <?php do_meta_boxes('botwriter_settings_page', 'normal', null); ?>
                    </div>
                </div>
            </div>
        </div>
        <div id='subscription'></div>
        <div id="response_div" class="bw-response-area"></div>
    </div>
    <?php
}

/**
 * Main metabox handler - renders the settings form content
 */
function botwriter_settings_meta_box_handler() { 
    // Get current values with defaults
    $text_provider = get_option('botwriter_text_provider', 'openai');
    $image_provider = get_option('botwriter_image_provider', 'dalle');
    
    $settings = botwriter_get_all_settings();
    
    // Provider lists
    $text_providers = [
        'openai' => 'OpenAI (GPT-5, GPT-4o)',
        'anthropic' => 'Anthropic (Claude)',
        'google' => 'Google (Gemini) - FREE TIER',
        'mistral' => 'Mistral AI - FREE TIER',
        'groq' => 'Groq (Ultra Fast) - FREE TIER',
        'openrouter' => 'OpenRouter (Multiple) - FREE TIER',
        'custom' => '⚡ Custom Provider or Self-Hosting',
    ];
    
    $image_providers = [
        'dalle' => 'DALL-E (OpenAI)',
        'gemini' => 'Google Gemini',
        'fal' => 'Fal.ai (Flux)',
        'replicate' => 'Replicate',
        'stability' => 'Stability AI',
        'cloudflare' => 'Cloudflare AI (FREE)',
        'custom' => '⚡ Custom Provider or Self-Hosting',
        'none' => '🚫 No Image Generation',
    ];

    ?>

    <!-- Main Tabs Navigation (Text AI / Image AI) -->
    <div class="botwriter-main-tabs">
        <a href="#" class="main-tab main-tab-active" data-main-tab="text">
            <span class="dashicons dashicons-edit"></span>
            <?php esc_html_e('Text AI Providers', 'botwriter'); ?>
        </a>
        <a href="#" class="main-tab" data-main-tab="images">
            <span class="dashicons dashicons-format-image"></span>
            <?php esc_html_e('Image AI Providers', 'botwriter'); ?>
        </a>
        <a href="#" class="main-tab" data-main-tab="imagesettings">
            <span class="dashicons dashicons-admin-appearance"></span>
            <?php esc_html_e('Image Settings', 'botwriter'); ?>
        </a>
        <a href="#" class="main-tab" data-main-tab="general">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('General Settings', 'botwriter'); ?>
        </a>
        <a href="#" class="main-tab" data-main-tab="seo">
            <span class="dashicons dashicons-translation"></span>
            <?php esc_html_e('SEO Translation', 'botwriter'); ?>
        </a>
    </div>

    <!-- Tab: Text AI Providers -->
    <div id="main-tab-text" class="botwriter-main-tab-content active">
        <div class="provider-selection-header">
            <h3><?php esc_html_e('Select Text AI Provider', 'botwriter'); ?></h3>
            <div class="form-row">
                <select name="botwriter_text_provider" id="botwriter_text_provider" class="form-select provider-select">
                    <?php foreach ($text_providers as $id => $name): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($text_provider, $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Provider Content Sections -->
        <?php 
        foreach ($text_providers as $id => $name):
            $is_active = ($id === $text_provider);
            $render_func = 'botwriter_render_' . $id . '_settings';
        ?>
        <div class="provider-content <?php echo $is_active ? 'active' : ''; ?>" 
             id="provider-<?php echo esc_attr($id); ?>" data-provider="<?php echo esc_attr($id); ?>">
            <?php 
            if (function_exists($render_func)) {
                $render_func($settings, $is_active);
            } else {
                echo '<p>' . esc_html__('Provider configuration not available.', 'botwriter') . '</p>';
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab: Image AI Providers -->
    <div id="main-tab-images" class="botwriter-main-tab-content">
        <div class="provider-selection-header">
            <h3><?php esc_html_e('Select Image AI Provider', 'botwriter'); ?></h3>
            <div class="form-row">
                <select name="botwriter_image_provider" id="botwriter_image_provider" class="form-select provider-select">
                    <?php foreach ($image_providers as $id => $name): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($image_provider, $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Image Provider Content Sections -->
        <?php 
        foreach ($image_providers as $id => $name):
            $is_active = ($id === $image_provider);
            // Special case: custom provider uses different function for images
            if ($id === 'custom') {
                $render_func = 'botwriter_render_custom_image_settings';
            } else {
                $render_func = 'botwriter_render_' . $id . '_settings';
            }
        ?>
        <div class="provider-content <?php echo $is_active ? 'active' : ''; ?>" 
             id="image-provider-<?php echo esc_attr($id); ?>" data-provider="<?php echo esc_attr($id); ?>">
            <?php 
            if (function_exists($render_func)) {
                $render_func($settings, $is_active);
            } else {
                echo '<p>' . esc_html__('Provider configuration not available.', 'botwriter') . '</p>';
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab: Image Settings -->
    <div id="main-tab-imagesettings" class="botwriter-main-tab-content">
        <div class="image-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-format-image"></span>
                <?php esc_html_e('Image Generation Settings', 'botwriter'); ?>
            </h4>
            
            <div class="settings-grid-2col">
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-image-crop"></span> <?php esc_html_e('Image Format:', 'botwriter'); ?></label>
                        <select name="botwriter_ai_image_size" class="form-select" id="image_size_select">
                            <option value="landscape" <?php selected($settings['botwriter_ai_image_size'], 'landscape'); ?>><?php esc_html_e('🖼️ Landscape (16:9) - Blog headers', 'botwriter'); ?></option>
                            <option value="square" <?php selected($settings['botwriter_ai_image_size'], 'square'); ?>><?php esc_html_e('⬛ Square (1:1) - Social media', 'botwriter'); ?></option>
                            <option value="portrait" <?php selected($settings['botwriter_ai_image_size'], 'portrait'); ?>><?php esc_html_e('📱 Portrait (9:16) - Mobile/Stories', 'botwriter'); ?></option>
                        </select>
                    </div>
                    
                    <div class="format-preview" id="format_preview">
                        <div class="preview-box landscape">
                            <span>16:9</span>
                        </div>
                    </div>
                    
                    <div class="format-specs">
                        <p class="description"><strong><?php esc_html_e('Approximate dimensions by provider:', 'botwriter'); ?></strong></p>
                        <table class="specs-table" id="size_specs_table">
                            <tr><td>DALL-E</td><td id="dalle_size">1792×1024</td></tr>
                            <tr><td>Fal.ai/Flux</td><td id="fal_size">1344×768</td></tr>
                            <tr><td>Stability AI</td><td id="stability_size">1344×768</td></tr>
                            <tr><td>Replicate</td><td id="replicate_size">1344×768</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Image Quality:', 'botwriter'); ?></label>
                        <select name="botwriter_ai_image_quality" class="form-select" id="image_quality_select">
                            <option value="low" <?php selected($settings['botwriter_ai_image_quality'], 'low'); ?>><?php esc_html_e('⚡ Low - Faster & Cheaper', 'botwriter'); ?></option>
                            <option value="medium" <?php selected($settings['botwriter_ai_image_quality'], 'medium'); ?>><?php esc_html_e('⚖️ Medium - Balanced', 'botwriter'); ?></option>
                            <option value="high" <?php selected($settings['botwriter_ai_image_quality'], 'high'); ?>><?php esc_html_e('✨ High - Best Quality', 'botwriter'); ?></option>
                        </select>
                    </div>
                    
                    <div class="quality-indicator" id="quality_indicator">
                        <div class="quality-bar medium">
                            <div class="bar-fill"></div>
                        </div>
                        <div class="quality-labels">
                            <span><?php esc_html_e('Speed', 'botwriter'); ?></span>
                            <span><?php esc_html_e('Quality', 'botwriter'); ?></span>
                        </div>
                    </div>
                    
                    <div class="quality-specs">
                        <p class="description"><strong><?php esc_html_e('Provider settings mapping:', 'botwriter'); ?></strong></p>
                        <table class="specs-table" id="quality_specs_table">
                            <tr><td>DALL-E</td><td id="dalle_quality">standard</td></tr>
                            <tr><td>Fal.ai</td><td id="fal_quality">20 steps</td></tr>
                            <tr><td>Stability AI</td><td id="stability_quality">sd3-large</td></tr>
                            <tr><td>Replicate</td><td id="replicate_quality">25 steps</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Image Style Settings -->
            <div class="settings-grid-2col" style="margin-top: 20px;">
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-art"></span> <?php esc_html_e('Image Style:', 'botwriter'); ?></label>
                        <select name="botwriter_ai_image_style" class="form-select botwriter-autosave" id="image_style_select">
                            <option value="realistic" <?php selected($settings['botwriter_ai_image_style'], 'realistic'); ?>><?php esc_html_e('📷 Realistic / Photographic', 'botwriter'); ?></option>
                            <option value="digital-art" <?php selected($settings['botwriter_ai_image_style'], 'digital-art'); ?>><?php esc_html_e('🎨 Digital Art', 'botwriter'); ?></option>
                            <option value="illustration" <?php selected($settings['botwriter_ai_image_style'], 'illustration'); ?>><?php esc_html_e('✏️ Illustration', 'botwriter'); ?></option>
                            <option value="cartoon" <?php selected($settings['botwriter_ai_image_style'], 'cartoon'); ?>><?php esc_html_e('🎭 Cartoon', 'botwriter'); ?></option>
                            <option value="comic" <?php selected($settings['botwriter_ai_image_style'], 'comic'); ?>><?php esc_html_e('💥 Comic Book', 'botwriter'); ?></option>
                            <option value="anime" <?php selected($settings['botwriter_ai_image_style'], 'anime'); ?>><?php esc_html_e('🌸 Anime / Manga', 'botwriter'); ?></option>
                            <option value="3d-render" <?php selected($settings['botwriter_ai_image_style'], '3d-render'); ?>><?php esc_html_e('🧊 3D Render', 'botwriter'); ?></option>
                            <option value="watercolor" <?php selected($settings['botwriter_ai_image_style'], 'watercolor'); ?>><?php esc_html_e('🖌️ Watercolor', 'botwriter'); ?></option>
                            <option value="oil-painting" <?php selected($settings['botwriter_ai_image_style'], 'oil-painting'); ?>><?php esc_html_e('🎨 Oil Painting', 'botwriter'); ?></option>
                            <option value="minimalist" <?php selected($settings['botwriter_ai_image_style'], 'minimalist'); ?>><?php esc_html_e('◻️ Minimalist', 'botwriter'); ?></option>
                            <option value="vintage" <?php selected($settings['botwriter_ai_image_style'], 'vintage'); ?>><?php esc_html_e('📻 Vintage / Retro', 'botwriter'); ?></option>
                            <option value="cinematic" <?php selected($settings['botwriter_ai_image_style'], 'cinematic'); ?>><?php esc_html_e('🎬 Cinematic', 'botwriter'); ?></option>
                            <option value="none" <?php selected($settings['botwriter_ai_image_style'], 'none'); ?>><?php esc_html_e('⚪ None (no style prefix)', 'botwriter'); ?></option>
                            <option value="custom" <?php selected($settings['botwriter_ai_image_style'], 'custom'); ?>><?php esc_html_e('✨ Custom (enter below)', 'botwriter'); ?></option>
                        </select>
                    </div>
                    <div class="form-row" id="custom_style_row" style="<?php echo $settings['botwriter_ai_image_style'] === 'custom' ? '' : 'display:none;'; ?>">
                        <label><?php esc_html_e('Custom Style:', 'botwriter'); ?></label>
                        <input type="text" name="botwriter_ai_image_style_custom" class="form-control botwriter-autosave" value="<?php echo esc_attr($settings['botwriter_ai_image_style_custom']); ?>" placeholder="<?php esc_attr_e('e.g., "Studio Ghibli style", "pencil sketch"', 'botwriter'); ?>">
                    </div>
                    <p class="description"><?php esc_html_e('Style prefix added to image prompts. This affects the visual appearance of generated images.', 'botwriter'); ?></p>
                </div>
                
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Image Post-Processing:', 'botwriter'); ?></label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="botwriter_image_postprocess_enabled" class="botwriter-autosave" value="1" <?php checked($settings['botwriter_image_postprocess_enabled'], '1'); ?>>
                            <span class="slider round"></span>
                        </label>
                        <span class="toggle-label"><?php esc_html_e('Enable optimization', 'botwriter'); ?></span>
                    </div>
                    <p class="description"><?php esc_html_e('Optimize images for web: convert to WebP, resize, and compress for faster loading and Google Discover.', 'botwriter'); ?></p>
                </div>
            </div>
            
            <!-- Post-processing options (shown when enabled) -->
            <div id="postprocess_options" class="settings-grid-2col" style="margin-top: 15px; <?php echo $settings['botwriter_image_postprocess_enabled'] === '1' ? '' : 'display:none;'; ?>">
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-format-image"></span> <?php esc_html_e('Output Format:', 'botwriter'); ?></label>
                        <select name="botwriter_image_output_format" class="form-select botwriter-autosave">
                            <option value="webp" <?php selected($settings['botwriter_image_output_format'], 'webp'); ?>><?php esc_html_e('WebP (recommended)', 'botwriter'); ?></option>
                            <option value="jpeg" <?php selected($settings['botwriter_image_output_format'], 'jpeg'); ?>><?php esc_html_e('JPEG', 'botwriter'); ?></option>
                            <option value="png" <?php selected($settings['botwriter_image_output_format'], 'png'); ?>><?php esc_html_e('PNG', 'botwriter'); ?></option>
                            <option value="original" <?php selected($settings['botwriter_image_output_format'], 'original'); ?>><?php esc_html_e('Keep Original', 'botwriter'); ?></option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label><span class="dashicons dashicons-leftright"></span> <?php esc_html_e('Max Width (px):', 'botwriter'); ?></label>
                        <input type="number" name="botwriter_image_max_width" class="form-control small-input botwriter-autosave" value="<?php echo esc_attr($settings['botwriter_image_max_width']); ?>" min="400" max="2560" step="100">
                        <p class="description"><?php esc_html_e('Images wider than this will be resized. Recommended: 1200px for blog headers.', 'botwriter'); ?></p>
                    </div>
                </div>
                <div class="setting-card">
                    <div class="form-row">
                        <label><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Compression Quality:', 'botwriter'); ?></label>
                        <input type="range" name="botwriter_image_compression" class="form-range botwriter-autosave" value="<?php echo esc_attr($settings['botwriter_image_compression']); ?>" min="50" max="100" step="5" id="compression_slider">
                        <span id="compression_value"><?php echo esc_html($settings['botwriter_image_compression']); ?>%</span>
                    </div>
                    <p class="description"><?php esc_html_e('Lower = smaller file size but less quality. Recommended: 80-85% for web.', 'botwriter'); ?></p>
                    <div class="form-row">
                        <label><span class="dashicons dashicons-database"></span> <?php esc_html_e('Max File Size (KB):', 'botwriter'); ?></label>
                        <input type="number" name="botwriter_image_max_filesize" class="form-control small-input botwriter-autosave" value="<?php echo esc_attr($settings['botwriter_image_max_filesize']); ?>" min="50" max="500" step="10">
                        <p class="description"><?php esc_html_e('Target max file size. Set 0 to disable. Google Discover recommends ≤120KB.', 'botwriter'); ?></p>
                    </div>
                </div>
            </div>

            <div class="info-tip highlight-tip">
                <span class="dashicons dashicons-lightbulb"></span>
                <div>
                    <p><strong><?php esc_html_e('💡 Pro Tip:', 'botwriter'); ?></strong></p>
                    <p><?php esc_html_e('You can disable AI image generation for individual tasks in the task editor. This is useful to save costs on articles where you plan to add your own images.', 'botwriter'); ?></p>
                </div>
            </div>
            
            <div class="cost-comparison">
                <h5><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Estimated Cost per Image', 'botwriter'); ?></h5>
                <div class="cost-cards">
                    <div class="cost-card">
                        <span class="provider">DALL-E</span>
                        <span class="cost" id="dalle_cost">$0.04 - $0.08</span>
                    </div>
                    <div class="cost-card">
                        <span class="provider">Fal.ai</span>
                        <span class="cost" id="fal_cost">$0.03 - $0.05</span>
                    </div>
                    <div class="cost-card cheapest">
                        <span class="provider">Stability</span>
                        <span class="cost" id="stability_cost">$0.02 - $0.04</span>
                        <span class="badge"><?php esc_html_e('Cheapest', 'botwriter'); ?></span>
                    </div>
                    <div class="cost-card">
                        <span class="provider">Replicate</span>
                        <span class="cost" id="replicate_cost">$0.003 - $0.05</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: General Settings -->
    <div id="main-tab-general" class="botwriter-main-tab-content">
        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e('Task Scheduling', 'botwriter'); ?>
            </h4>

            <div class="form-row">
                <label><?php esc_html_e('Pause between daily posts of the same task:', 'botwriter'); ?></label>
                <div class="input-with-suffix">
                    <input type="number" name="botwriter_paused_tasks" value="<?php echo esc_attr($settings['botwriter_paused_tasks']); ?>" min="2" class="small-input botwriter-autosave">
                    <span class="suffix"><?php esc_html_e('minutes', 'botwriter'); ?></span>
                </div>
            </div>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_cron_active" value="1" <?php checked(get_option('botwriter_cron_active'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Enable automatic task execution', 'botwriter'); ?>
                </label>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php esc_html_e('Advanced Settings', 'botwriter'); ?>
            </h4>

            <div class="form-row checkbox-row warning-option">
                <label>
                    <input type="checkbox" name="botwriter_sslverify" value="no" <?php checked($settings['botwriter_sslverify'], 'no'); ?> class="botwriter-autosave">
                    <span class="warning-text"><?php esc_html_e('Disable SSL Verification (not recommended)', 'botwriter'); ?></span>
                </label>
                <p class="description"><?php esc_html_e('Only enable this if you have SSL certificate issues with API connections.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-hammer"></span>
                <?php esc_html_e('Tools', 'botwriter'); ?>
            </h4>

            <div class="form-row">
                <a href="javascript:void(0);" onclick="botwriter_reset_super1();" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e('Reset Super Task', 'botwriter'); ?>
                </a>
                <p class="description"><?php esc_html_e('Use this to reset a stuck Super Task and start fresh.', 'botwriter'); ?></p>
            </div>

            <div class="form-row" style="margin-top: 15px;">
                <a href="#" class="button button-secondary" id="btn-reset-models">
                    <span class="dashicons dashicons-database-remove" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e('Reset Models to Default', 'botwriter'); ?>
                </a>
                <span id="reset-models-result" style="margin-left: 10px;"></span>
                <p class="description"><?php esc_html_e('Reset all AI model lists to factory defaults. Use this if model lists are corrupted or outdated.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Others', 'botwriter'); ?>
            </h4>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_tags_disabled" value="1" <?php checked(get_option('botwriter_tags_disabled'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Disable Tags', 'botwriter'); ?>
                </label>
                <p class="description"><?php esc_html_e('When enabled, posts created by BotWriter will not have tags assigned.', 'botwriter'); ?></p>
            </div>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_meta_disabled" value="1" <?php checked(get_option('botwriter_meta_disabled', '0'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Disable Meta Description', 'botwriter'); ?>
                </label>
                <p class="description"><?php esc_html_e('When enabled, BotWriter will NOT auto-generate an SEO meta description for new posts. The meta is generated using a fast AI call after each post is created and is saved to the post excerpt and to popular SEO plugins (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework).', 'botwriter'); ?></p>
            </div>
        </div>

        <!-- Hidden fields -->
        <div style="display:none;">
            <input id="botwriter_email" type="text" name="botwriter_email" value="<?php echo esc_attr(get_option('botwriter_email')); ?>">
            <input id="botwriter_api_key" type="text" name="botwriter_api_key" value="<?php echo esc_attr(get_option('botwriter_api_key')); ?>">
        </div>
    </div>

    <!-- Tab: SEO Translation -->
    <div id="main-tab-seo" class="botwriter-main-tab-content">
        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-translation"></span>
                <?php esc_html_e('SEO Slug Translation', 'botwriter'); ?>
            </h4>
            <p class="description bw-mb-10">
                <?php esc_html_e('By default, WordPress generates URL slugs from your post title in whatever language you write it. If you create content in Spanish, your slugs will be in Spanish (e.g. /receta-paella-valenciana/). The same applies to tag URLs and image filenames.', 'botwriter'); ?>
            </p>
            <p class="description bw-mb-10">
                <?php esc_html_e('This feature is useful if you write content in a non-English language but want your URLs in English for international SEO — or vice versa. If you already write in your target slug language, you do not need this.', 'botwriter'); ?>
            </p>
            <p class="description bw-mb-15">
                <?php esc_html_e('It uses a single, lightweight API call per post with your configured Text AI provider — cost is negligible (fractions of a cent per post).', 'botwriter'); ?>
            </p>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_seo_translation_enabled" value="1" <?php checked(get_option('botwriter_seo_translation_enabled', '0'), '1'); ?> class="botwriter-autosave">
                    <strong><?php esc_html_e('Enable SEO Slug Translation', 'botwriter'); ?></strong>
                </label>
                <p class="description"><?php esc_html_e('When enabled, slugs will be translated using your configured text AI provider before each post is published.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Target Language', 'botwriter'); ?>
            </h4>

            <div class="form-row">
                <label for="botwriter_seo_target_language"><?php esc_html_e('Translate slugs to:', 'botwriter'); ?></label>
                <?php
                $seo_lang = get_option('botwriter_seo_target_language', 'en');
                $seo_languages = array(
                    'en' => 'English', 'es' => 'Spanish (Español)', 'fr' => 'French (Français)',
                    'de' => 'German (Deutsch)', 'it' => 'Italian (Italiano)', 'pt' => 'Portuguese (Português)',
                    'nl' => 'Dutch (Nederlands)', 'ru' => 'Russian (Русский)',
                    'ja' => 'Japanese (日本語)', 'ko' => 'Korean (한국어)',
                    'zh' => 'Chinese (中文)', 'ar' => 'Arabic (العربية)',
                    'hi' => 'Hindi (हिन्दी)', 'tr' => 'Turkish (Türkçe)',
                    'pl' => 'Polish (Polski)', 'sv' => 'Swedish (Svenska)',
                    'da' => 'Danish (Dansk)', 'no' => 'Norwegian (Norsk)',
                    'fi' => 'Finnish (Suomi)', 'cs' => 'Czech (Čeština)',
                    'ro' => 'Romanian (Română)', 'hu' => 'Hungarian (Magyar)',
                    'el' => 'Greek (Ελληνικά)', 'th' => 'Thai (ไทย)',
                    'vi' => 'Vietnamese (Tiếng Việt)', 'id' => 'Indonesian (Bahasa)',
                    'ms' => 'Malay (Melayu)', 'uk' => 'Ukrainian (Українська)',
                );
                ?>
                <select name="botwriter_seo_target_language" id="botwriter_seo_target_language" class="botwriter-autosave bw-select-wide">
                    <?php foreach ($seo_languages as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($seo_lang, $code); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Select the language your URL slugs should be translated to. Typically, choose English for best global SEO.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('What to Translate', 'botwriter'); ?>
            </h4>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_seo_translate_title" value="1" <?php checked(get_option('botwriter_seo_translate_title', '1'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Post Slug (URL)', 'botwriter'); ?>
                </label>
                <p class="description"><?php esc_html_e('Translate the post URL slug. Example: "receta-paella-valenciana" → "valencian-paella-recipe"', 'botwriter'); ?></p>
            </div>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_seo_translate_tags" value="1" <?php checked(get_option('botwriter_seo_translate_tags', '1'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Tag Slugs', 'botwriter'); ?>
                </label>
                <p class="description"><?php esc_html_e('Translate tag URL slugs for consistent multi-language taxonomy URLs.', 'botwriter'); ?></p>
            </div>

            <div class="form-row checkbox-row">
                <label>
                    <input type="checkbox" name="botwriter_seo_translate_image" value="1" <?php checked(get_option('botwriter_seo_translate_image', '1'), '1'); ?> class="botwriter-autosave">
                    <?php esc_html_e('Image Filenames', 'botwriter'); ?>
                </label>
                <p class="description"><?php esc_html_e('Use translated slugs for AI-generated image filenames, improving image SEO.', 'botwriter'); ?></p>
            </div>
        </div>

        <div class="general-settings-section">
            <h4 class="section-title">
                <span class="dashicons dashicons-info-outline"></span>
                <?php esc_html_e('How It Works', 'botwriter'); ?>
            </h4>
            <p class="description">
                <?php esc_html_e('When a post is generated, BotWriter makes one extra API call using your configured Text AI provider to translate the title, tags, and image names into SEO-friendly slugs. This uses a fast, lightweight model (e.g. GPT-4o-mini, Gemini Flash, Haiku) with ~200 tokens — costing less than $0.001 per post. No additional API key is needed.', 'botwriter'); ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Get all settings with defaults
 */
function botwriter_get_all_settings() {
    return array(
        'botwriter_ai_image_size' => get_option('botwriter_ai_image_size', 'square'),
        'botwriter_ai_image_quality' => get_option('botwriter_ai_image_quality', 'medium'),
        'botwriter_ai_image_style' => get_option('botwriter_ai_image_style', 'realistic'),
        'botwriter_ai_image_style_custom' => get_option('botwriter_ai_image_style_custom', ''),
        'botwriter_image_postprocess_enabled' => get_option('botwriter_image_postprocess_enabled', '0'),
        'botwriter_image_output_format' => get_option('botwriter_image_output_format', 'webp'),
        'botwriter_image_max_width' => get_option('botwriter_image_max_width', '1200'),
        'botwriter_image_compression' => get_option('botwriter_image_compression', '85'),
        'botwriter_image_max_filesize' => get_option('botwriter_image_max_filesize', '120'),
        'botwriter_sslverify' => get_option('botwriter_sslverify', 'yes'),
        'botwriter_cron_active' => get_option('botwriter_cron_active', '1'),
        'botwriter_paused_tasks' => get_option('botwriter_paused_tasks', '2'),
        'botwriter_tags_disabled' => get_option('botwriter_tags_disabled', '0'),
        'botwriter_meta_disabled' => get_option('botwriter_meta_disabled', '0'),
        // SEO Translation
        'botwriter_seo_translation_enabled' => get_option('botwriter_seo_translation_enabled', '0'),
        'botwriter_seo_target_language' => get_option('botwriter_seo_target_language', 'en'),
        'botwriter_seo_translate_title' => get_option('botwriter_seo_translate_title', '1'),
        'botwriter_seo_translate_tags' => get_option('botwriter_seo_translate_tags', '1'),
        'botwriter_seo_translate_image' => get_option('botwriter_seo_translate_image', '1'),
        // API keys (encrypted, just check if they exist for display purposes)
        'botwriter_openai_api_key' => get_option('botwriter_openai_api_key', ''),
        'botwriter_google_api_key' => get_option('botwriter_google_api_key', ''),
        'botwriter_anthropic_api_key' => get_option('botwriter_anthropic_api_key', ''),
        'botwriter_mistral_api_key' => get_option('botwriter_mistral_api_key', ''),
        'botwriter_groq_api_key' => get_option('botwriter_groq_api_key', ''),
        'botwriter_openrouter_api_key' => get_option('botwriter_openrouter_api_key', ''),
        'botwriter_fal_api_key' => get_option('botwriter_fal_api_key', ''),
        'botwriter_replicate_api_key' => get_option('botwriter_replicate_api_key', ''),
        'botwriter_stability_api_key' => get_option('botwriter_stability_api_key', ''),
        'botwriter_cloudflare_api_key' => get_option('botwriter_cloudflare_api_key', ''),
        // Text models
        'botwriter_openai_model' => get_option('botwriter_openai_model', 'gpt-5-mini'),
        'botwriter_anthropic_model' => get_option('botwriter_anthropic_model', 'claude-sonnet-4-5-20250929'),
        'botwriter_google_model' => get_option('botwriter_google_model', 'gemini-2.5-flash'),
        'botwriter_mistral_model' => get_option('botwriter_mistral_model', 'mistral-large-latest'),
        'botwriter_groq_model' => get_option('botwriter_groq_model', 'llama-3.3-70b-versatile'),
        'botwriter_openrouter_model' => get_option('botwriter_openrouter_model', 'anthropic/claude-sonnet-4'),
        // Image models
        'botwriter_dalle_model' => get_option('botwriter_dalle_model', 'gpt-image-1'),
        'botwriter_gemini_image_model' => get_option('botwriter_gemini_image_model', 'gemini-2.5-flash-image'),
        'botwriter_fal_model' => get_option('botwriter_fal_model', 'fal-ai/flux-pro/v1.1'),
        'botwriter_replicate_model' => get_option('botwriter_replicate_model', 'black-forest-labs/flux-1.1-pro'),
        'botwriter_stability_model' => get_option('botwriter_stability_model', 'sd3.5-large-turbo'),
        'botwriter_cloudflare_model' => get_option('botwriter_cloudflare_model', 'flux-1-schnell'),
        'botwriter_cloudflare_account_id' => get_option('botwriter_cloudflare_account_id', ''),
    );
}

// Generic function to encrypt any API key
function botwriter_encrypt_api_key_generic($api_key) {
    if (empty($api_key)) {
        return '';
    }
    
    if (!function_exists('openssl_encrypt')) {
        return $api_key;
    }

    if (!defined('AUTH_KEY')) {
        return $api_key;
    }

    $key = hash('sha256', AUTH_KEY, true);
    $encrypted = openssl_encrypt($api_key, 'AES-256-ECB', $key, 0);
    
    if ($encrypted === false) {
        return $api_key;
    }

    return base64_encode($encrypted);
}

// Legacy function for backwards compatibility (OpenAI key)
function botwriter_encrypt_api_key($api_key) {
    global $botwriter_notice;

    if (!function_exists('openssl_encrypt')) {
        update_option('botwriter_openai_api_key', $api_key);
        $botwriter_notice .= __('The OpenSSL extension is not available. The API Key will be stored unencrypted, which is not secure. Contact your hosting provider.', 'botwriter') . '<br>';
        return $api_key;
    }

    if (!defined('AUTH_KEY')) {
        $botwriter_notice .= __('AUTH_KEY not found in wp-config.php. Please configure WordPress security keys.', 'botwriter') . '<br>';
        return get_option('botwriter_openai_api_key');
    }

    $key = hash('sha256', AUTH_KEY, true);
    $encrypted = openssl_encrypt($api_key, 'AES-256-ECB', $key, 0);
    if ($encrypted === false) {
        $botwriter_notice .= __('Failed to encrypt the API Key.', 'botwriter') . '<br>';
        return get_option('botwriter_openai_api_key');
    }

    $encrypted_base64 = base64_encode($encrypted);
    update_option('botwriter_openai_api_key', $encrypted_base64);

    return $encrypted_base64;
}

// Decrypt API key (works for any provider)
function botwriter_decrypt_api_key($encrypted_api_key) {
    if (empty($encrypted_api_key)) {
        return '';
    }

    if (!function_exists('openssl_decrypt')) {
        return $encrypted_api_key;
    }

    if (!defined('AUTH_KEY')) {
        return '';
    }

    $key = hash('sha256', AUTH_KEY, true);
    $encrypted = base64_decode($encrypted_api_key);

    if (!$encrypted) {
        return '';
    }

    $decrypted = openssl_decrypt($encrypted, 'AES-256-ECB', $key, 0);
    if ($decrypted === false) {
        return '';
    }

    return $decrypted;
}

// Validate OpenAI API Key format and test it
function botwriter_open_api_key_validate($input) {
    global $botwriter_notice;

    $input = sanitize_text_field($input);

    // Validate basic format (e.g., starts with "sk-")
    if (strpos($input, 'sk-') !== 0) {
        $botwriter_notice .= __('The OpenAI API Key must start with "sk-".', 'botwriter') . '<br>';
        return false;
    }

    // Make a test request to the OpenAI API
    $response = wp_remote_get('https://api.openai.com/v1/models', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $input,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        $botwriter_notice .= __('Error validating the API Key: ', 'botwriter') . $response->get_error_message() . '<br>';
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        /* translators: %s: HTTP response code returned by OpenAI when validating the API key. */
        $botwriter_notice .= sprintf(__('The OpenAI API Key is invalid or lacks access. Error code: %s', 'botwriter'), $response_code) . '<br>';
        return false;
    }

    return true;
}
