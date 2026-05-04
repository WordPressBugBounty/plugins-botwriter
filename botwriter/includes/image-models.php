<?php
/**
 * BotWriter Image Models Manager
 *
 * Handles loading/storing image provider models from a dedicated JSON catalog.
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BOTWRITER_IMAGE_MODELS_OPTION')) {
    define('BOTWRITER_IMAGE_MODELS_OPTION', 'botwriter_image_models_data');
}

/**
 * Fallback catalog used when JSON file is unavailable.
 *
 * @return array
 */
function botwriter_get_image_models_fallback_catalog() {
    return array(
        'version' => '1.0.0',
        'updated' => gmdate('Y-m-d'),
        'providers' => array(
            'dalle' => array(
                'default' => 'gpt-image-1',
                'groups' => array(
                    'GPT Image' => array(
                        'gpt-image-1' => 'GPT Image 1',
                    ),
                ),
                'all_models' => array(),
            ),
            'gemini' => array(
                'default' => 'gemini-2.5-flash-image',
                'groups' => array(
                    'Gemini Image' => array(
                        'gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image',
                    ),
                ),
                'all_models' => array(),
            ),
            'fal' => array(
                'default' => 'fal-ai/flux-pro/v1.1',
                'groups' => array(
                    'Fal.ai' => array(
                        'fal-ai/flux-pro/v1.1' => 'Flux Pro v1.1',
                    ),
                ),
                'all_models' => array(),
            ),
            'replicate' => array(
                'default' => 'black-forest-labs/flux-1.1-pro',
                'groups' => array(
                    'Replicate' => array(
                        'black-forest-labs/flux-1.1-pro' => 'Flux 1.1 Pro',
                    ),
                ),
                'all_models' => array(),
            ),
            'stability' => array(
                'default' => 'sd3.5-large-turbo',
                'groups' => array(
                    'Stability' => array(
                        'sd3.5-large-turbo' => 'SD 3.5 Large Turbo',
                    ),
                ),
                'all_models' => array(),
            ),
            'cloudflare' => array(
                'default' => 'flux-1-schnell',
                'groups' => array(
                    'Cloudflare' => array(
                        'flux-1-schnell' => 'FLUX.1 Schnell',
                    ),
                ),
                'all_models' => array(),
            ),
            'stockphoto' => array(
                'default' => 'random',
                'groups' => array(
                    'Stock Photo' => array(
                        'random' => 'Random (auto rotate)',
                        'pixabay' => 'Pixabay',
                        'pexels' => 'Pexels',
                        'unsplash' => 'Unsplash',
                        'openverse' => 'Openverse',
                    ),
                ),
                'all_models' => array(),
            ),
            'none' => array(
                'default' => 'none',
                'groups' => array(
                    'Disabled' => array(
                        'none' => 'No Image Generation',
                    ),
                ),
                'all_models' => array(),
            ),
        ),
    );
}

/**
 * Load default image models from JSON file.
 *
 * @return array
 */
function botwriter_load_default_image_models() {
    $json_file = plugin_dir_path(dirname(__FILE__)) . 'image-models-default.json';

    if (!file_exists($json_file)) {
        return botwriter_get_image_models_fallback_catalog();
    }

    $json_content = file_get_contents($json_file);
    if ($json_content === false) {
        return botwriter_get_image_models_fallback_catalog();
    }

    $models_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($models_data)) {
        return botwriter_get_image_models_fallback_catalog();
    }

    if (empty($models_data['providers']) || !is_array($models_data['providers'])) {
        return botwriter_get_image_models_fallback_catalog();
    }

    return $models_data;
}

/**
 * Get persisted image models data, initialized from JSON if needed.
 *
 * @return array
 */
function botwriter_get_image_models_data() {
    $models_data = get_option(BOTWRITER_IMAGE_MODELS_OPTION);

    if (empty($models_data) || !is_array($models_data) || empty($models_data['providers'])) {
        $models_data = botwriter_load_default_image_models();
        update_option(BOTWRITER_IMAGE_MODELS_OPTION, $models_data);
    }

    return $models_data;
}

/**
 * Get grouped image models for a provider.
 *
 * @param string $provider Provider slug.
 * @return array
 */
function botwriter_get_provider_image_models($provider) {
    $provider = sanitize_key((string) $provider);
    $models_data = botwriter_get_image_models_data();

    if (empty($models_data['providers'][$provider]['groups']) || !is_array($models_data['providers'][$provider]['groups'])) {
        return array();
    }

    return $models_data['providers'][$provider]['groups'];
}

/**
 * Get a flattened map of image models for a provider.
 *
 * @param string $provider Provider slug.
 * @return array
 */
function botwriter_get_provider_image_models_flat($provider) {
    $grouped = botwriter_get_provider_image_models($provider);
    if (empty($grouped) || !is_array($grouped)) {
        return array();
    }

    $flat = array();
    foreach ($grouped as $group_models) {
        if (!is_array($group_models)) {
            continue;
        }

        foreach ($group_models as $model_id => $model_label) {
            $flat[(string) $model_id] = (string) $model_label;
        }
    }

    return $flat;
}

/**
 * Get provider default image model.
 *
 * @param string $provider Provider slug.
 * @return string
 */
function botwriter_get_provider_default_image_model($provider) {
    $provider = sanitize_key((string) $provider);
    $models_data = botwriter_get_image_models_data();

    if (!empty($models_data['providers'][$provider]['default'])) {
        return (string) $models_data['providers'][$provider]['default'];
    }

    return '';
}

/**
 * Normalize an image model against the provider catalog.
 *
 * @param string $provider Provider slug.
 * @param string $model Model value to normalize.
 * @return string
 */
function botwriter_normalize_image_model($provider, $model) {
    $provider = sanitize_key((string) $provider);
    $default_model = (string) botwriter_get_provider_default_image_model($provider);

    if ($provider === 'stockphoto') {
        $model = sanitize_key((string) $model);
        $valid_stock_models = array('pixabay', 'pexels', 'unsplash', 'openverse', 'random');

        if (in_array($model, $valid_stock_models, true)) {
            return $model;
        }

        return 'random';
    }
    if ($provider === 'none') {
        return 'none';
    }

    if ($default_model === '') {
        $default_model = 'gpt-image-1';
    }

    $model = trim((string) $model);
    if ($model === '') {
        return $default_model;
    }

    if ($provider === 'gemini') {
        $gemini_aliases = array(
            'gemini-2.0-flash-preview-image-generation' => 'gemini-2.5-flash-image',
            'gemini-2.0-flash-exp-image-generation' => 'gemini-2.5-flash-image',
            'gemini-2.0-flash-image' => 'gemini-2.5-flash-image',
            'gemini-2.5-flash-image-preview' => 'gemini-2.5-flash-image',
            'gemini-3.1-flash-image' => 'gemini-3.1-flash-image-preview',
            'gemini-3-pro-image' => 'gemini-3-pro-image-preview',
        );

        $model_lc = strtolower($model);
        if (isset($gemini_aliases[$model_lc])) {
            $model = $gemini_aliases[$model_lc];
        } else {
            $model = $model_lc;
        }
    }

    $valid_models = array_keys(botwriter_get_provider_image_models_flat($provider));
    if (!empty($valid_models) && in_array($model, $valid_models, true)) {
        return $model;
    }

    return $default_model;
}

/**
 * Return option name used to store the model for an image provider.
 *
 * @param string $provider Provider slug.
 * @return string
 */
function botwriter_get_image_model_option_name($provider) {
    $provider = sanitize_key((string) $provider);
    if ($provider === 'gemini') {
        return 'botwriter_gemini_image_model';
    }

    return "botwriter_{$provider}_model";
}
