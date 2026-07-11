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
 * Deprecated model IDs that must not appear as selectable options.
 *
 * @param string $provider Provider key.
 * @param string $model_id Model ID.
 * @return bool
 */
function botwriter_is_deprecated_text_model_choice($provider, $model_id) {
    $provider = sanitize_key((string) $provider);
    $model_id = strtolower(trim((string) $model_id));

    if ($model_id === '') {
        return false;
    }

    $blocked = [
        'google' => [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'models/gemini-2.5-flash',
            'models/gemini-2.5-flash-lite',
        ],
        'openrouter' => [
            'google/gemini-2.5-flash',
        ],
    ];

    if (!isset($blocked[$provider])) {
        return false;
    }

    return in_array($model_id, $blocked[$provider], true);
}

/**
 * Filter deprecated model IDs from a model map.
 *
 * @param string $provider Provider key.
 * @param array  $models   Map model_id => model_name.
 * @return array
 */
function botwriter_filter_deprecated_text_models($provider, $models) {
    if (!is_array($models)) {
        return [];
    }

    $filtered = [];
    foreach ($models as $model_id => $model_name) {
        if (botwriter_is_deprecated_text_model_choice($provider, $model_id)) {
            continue;
        }
        $filtered[$model_id] = $model_name;
    }

    return $filtered;
}

/**
 * Sanitize stored models data to enforce current curated defaults.
 *
 * @param array $models_data Models catalog data.
 * @return array [sanitized_data, changed]
 */
function botwriter_sanitize_models_data($models_data) {
    if (!is_array($models_data) || empty($models_data['providers']) || !is_array($models_data['providers'])) {
        return [$models_data, false];
    }

    $changed = false;

    if (isset($models_data['providers']['google']) && is_array($models_data['providers']['google'])) {
        $current_google_default = (string) ($models_data['providers']['google']['default'] ?? '');
        if ($current_google_default === '' || botwriter_is_deprecated_text_model_choice('google', $current_google_default)) {
            $models_data['providers']['google']['default'] = 'gemini-3.5-flash';
            $changed = true;
        }
    }

    foreach ($models_data['providers'] as $provider => $provider_data) {
        if (!is_array($provider_data)) {
            continue;
        }

        if (!empty($provider_data['groups']) && is_array($provider_data['groups'])) {
            foreach ($provider_data['groups'] as $group_name => $group_models) {
                if (!is_array($group_models)) {
                    continue;
                }

                $filtered_group_models = botwriter_filter_deprecated_text_models($provider, $group_models);
                if ($filtered_group_models !== $group_models) {
                    $changed = true;
                }

                if (empty($filtered_group_models)) {
                    unset($models_data['providers'][$provider]['groups'][$group_name]);
                } else {
                    $models_data['providers'][$provider]['groups'][$group_name] = $filtered_group_models;
                }
            }
        }

        if (!empty($provider_data['all_models']) && is_array($provider_data['all_models'])) {
            $filtered_all_models = [];
            foreach ($provider_data['all_models'] as $model_row) {
                $model_id = is_array($model_row) ? (string) ($model_row['id'] ?? '') : '';
                if ($model_id !== '' && botwriter_is_deprecated_text_model_choice($provider, $model_id)) {
                    $changed = true;
                    continue;
                }
                $filtered_all_models[] = $model_row;
            }

            if ($filtered_all_models !== $provider_data['all_models']) {
                $models_data['providers'][$provider]['all_models'] = $filtered_all_models;
                $changed = true;
            }
        }
    }

    if ($changed) {
        $models_data['updated'] = function_exists('current_time')
            ? current_time('Y-m-d')
            : gmdate('Y-m-d');
    }

    return [$models_data, $changed];
}

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

    if (!empty($models_data)) {
        list($sanitized_models_data, $changed) = botwriter_sanitize_models_data($models_data);
        if ($changed) {
            $models_data = $sanitized_models_data;
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
    $provider = sanitize_key((string) $provider);
    
    if (empty($models_data['providers'][$provider])) {
        return [];
    }
    
    $provider_data = $models_data['providers'][$provider];
    $result = [];
    
    // Add grouped models (our curated list)
    if (!empty($provider_data['groups'])) {
        foreach ($provider_data['groups'] as $group_name => $models) {
            $filtered_models = botwriter_filter_deprecated_text_models($provider, $models);
            if (!empty($filtered_models)) {
                $result[$group_name] = $filtered_models;
            }
        }
    }
    
    // Add all_models if present (from API discovery)
    if (!empty($provider_data['all_models'])) {
        $result['*** ALL MODELS ***'] = [];
        foreach ($provider_data['all_models'] as $model) {
            if (!is_array($model)) {
                continue;
            }
            $model_id = isset($model['id']) ? (string) $model['id'] : '';
            if ($model_id === '') {
                continue;
            }
            if (botwriter_is_deprecated_text_model_choice($provider, $model_id)) {
                continue;
            }
            $model_name = isset($model['name']) && $model['name'] !== $model_id
                ? $model['name'] . ' (' . $model_id . ')'
                : $model_id;
            $result['*** ALL MODELS ***'][$model_id] = $model_name;
        }

        if (empty($result['*** ALL MODELS ***'])) {
            unset($result['*** ALL MODELS ***']);
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
    $provider = sanitize_key((string) $provider);
    
    if (empty($models_data['providers'][$provider])) {
        return false;
    }
    
    // Update the all_models list, filtering deprecated selectable aliases.
    $filtered_models = [];
    foreach ((array) $models as $model_row) {
        $model_id = is_array($model_row) ? (string) ($model_row['id'] ?? '') : '';
        if ($model_id !== '' && botwriter_is_deprecated_text_model_choice($provider, $model_id)) {
            continue;
        }
        $filtered_models[] = $model_row;
    }

    $models_data['providers'][$provider]['all_models'] = $filtered_models;
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

    $text_ok = botwriter_get_models_data() !== false;
    $image_ok = true;

    // Keep the reset button behavior consistent with its wording:
    // reset all model lists (text + image).
    if (function_exists('botwriter_reset_image_models_to_default')) {
        $image_ok = botwriter_reset_image_models_to_default();
    } else {
        delete_option('botwriter_image_models_data');
    }

    return $text_ok && $image_ok;
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
    
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs_str is assembled only from esc_attr()-escaped keys and values.
    echo '<select name="' . esc_attr($field_name) . '" class="form-select model-select" data-provider="' . esc_attr($provider) . '"' . $attrs_str . ' style="max-width: 400px;">';
    
    foreach ($models as $group_name => $group_models) {
        echo '<optgroup label="' . esc_attr($group_name) . '">';
        foreach ($group_models as $model_id => $model_name) {
            echo '<option value="' . esc_attr($model_id) . '"';
            selected($current_value, $model_id);
            echo '>' . esc_html($model_name) . '</option>';
        }
        echo '</optgroup>';
    }
    
    echo '</select>';
}
