<?php
/**
 * BotWriter Models Manager
 * 
 * Handles loading, storing and updating AI provider models from JSON.
 * Models are stored in WordPress options and can be updated dynamically.
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Option name for storing models data
 */
define('BOTWRITER_MODELS_OPTION', 'botwriter_models_data');

/**
 * Get the models data, initializing from JSON if needed
 * 
 * @return array The models data structure
 */
function botwriter_get_models_data() {
    $models_data = get_option(BOTWRITER_MODELS_OPTION);
    
    // If no data exists, load from default JSON file
    if (empty($models_data)) {
        $models_data = botwriter_load_default_models();
        if ($models_data) {
            update_option(BOTWRITER_MODELS_OPTION, $models_data);
        }
    }
    
    return $models_data;
}

/**
 * Load models from the default JSON file
 * 
 * @return array|false The models data or false on failure
 */
function botwriter_load_default_models() {
    $json_file = plugin_dir_path(dirname(__FILE__)) . 'models-default.json';
    
    if (!file_exists($json_file)) {
        return false;
    }
    
    $json_content = file_get_contents($json_file);
    if ($json_content === false) {
        return false;
    }
    
    $models_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    return $models_data;
}

/**
 * Get models for a specific provider
 * 
 * @param string $provider The provider name (openai, anthropic, google, etc.)
 * @return array The models grouped structure for select options
 */
function botwriter_get_provider_models($provider) {
    $models_data = botwriter_get_models_data();
    
    if (empty($models_data['providers'][$provider])) {
        return [];
    }
    
    $provider_data = $models_data['providers'][$provider];
    $result = [];
    
    // Add grouped models (our curated list)
    if (!empty($provider_data['groups'])) {
        foreach ($provider_data['groups'] as $group_name => $models) {
            $result[$group_name] = $models;
        }
    }
    
    // Add all_models if present (from API discovery)
    if (!empty($provider_data['all_models'])) {
        $result['*** ALL MODELS ***'] = [];
        foreach ($provider_data['all_models'] as $model) {
            $model_id = $model['id'];
            $model_name = isset($model['name']) && $model['name'] !== $model['id'] 
                ? $model['name'] . ' (' . $model['id'] . ')' 
                : $model['id'];
            $result['*** ALL MODELS ***'][$model_id] = $model_name;
        }
    }
    
    return $result;
}

/**
 * Get the default model for a provider
 * 
 * @param string $provider The provider name
 * @return string The default model ID
 */
function botwriter_get_provider_default_model($provider) {
    $models_data = botwriter_get_models_data();
    
    if (empty($models_data['providers'][$provider]['default'])) {
        return '';
    }
    
    return $models_data['providers'][$provider]['default'];
}

/**
 * Update the all_models list for a provider
 * 
 * @param string $provider The provider name
 * @param array $models Array of models with 'id' and 'name' keys
 * @return bool True on success
 */
function botwriter_update_provider_all_models($provider, $models) {
    $models_data = botwriter_get_models_data();
    
    if (empty($models_data['providers'][$provider])) {
        return false;
    }
    
    // Update the all_models list
    $models_data['providers'][$provider]['all_models'] = $models;
    $models_data['updated'] = current_time('Y-m-d');
    
    return update_option(BOTWRITER_MODELS_OPTION, $models_data);
}

/**
 * Reset models to default from JSON file
 * 
 * @return bool True on success
 */
function botwriter_reset_models_to_default() {
    delete_option(BOTWRITER_MODELS_OPTION);
    return botwriter_get_models_data() !== false;
}

/**
 * Render a model select dropdown for a provider
 * 
 * @param string $provider The provider name
 * @param string $field_name The form field name
 * @param string $current_value The currently selected value
 * @param array $extra_attrs Extra HTML attributes
 */
function botwriter_render_model_select($provider, $field_name, $current_value, $extra_attrs = []) {
    $models = botwriter_get_provider_models($provider);
    $default = botwriter_get_provider_default_model($provider);
    
    if (empty($current_value)) {
        $current_value = $default;
    }
    
    $attrs_str = '';
    foreach ($extra_attrs as $key => $value) {
        $attrs_str .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
    }
    
    echo '<select name="' . esc_attr($field_name) . '" class="form-select model-select" data-provider="' . esc_attr($provider) . '"' . $attrs_str . ' style="max-width: 400px;">';
    
    foreach ($models as $group_name => $group_models) {
        echo '<optgroup label="' . esc_attr($group_name) . '">';
        foreach ($group_models as $model_id => $model_name) {
            $selected = selected($current_value, $model_id, false);
            echo '<option value="' . esc_attr($model_id) . '"' . $selected . '>' . esc_html($model_name) . '</option>';
        }
        echo '</optgroup>';
    }
    
    echo '</select>';
}
