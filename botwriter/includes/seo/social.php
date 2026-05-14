<?php
/**
 * BotWriter SEO — social metadata output (Open Graph + Twitter Cards).
 *
 * Emits deterministic social tags only when enabled and no major SEO plugin
 * is detected, to avoid duplicate tags in <head>.
 *
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether BotWriter should emit social meta tags.
 *
 * @return bool
 */
function botwriter_is_seo_social_meta_enabled() {
    return get_option('botwriter_seo_social_meta_enabled', '0') === '1';
}

/**
 * Detect if another SEO plugin should own social metadata output.
 *
 * @return bool
 */
function botwriter_seo_social_has_external_provider() {
    if (function_exists('botwriter_seo_active_plugin') && botwriter_seo_active_plugin() !== 'native') {
        return true;
    }

    if (defined('SEOPRESS_VERSION') || class_exists('SEOPress\\Core\\Kernel')) {
        return true;
    }

    if (defined('THE_SEO_FRAMEWORK_VERSION') || function_exists('the_seo_framework')) {
        return true;
    }

    return false;
}

/**
 * Build a deterministic social description.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function botwriter_seo_social_description($post_id) {
    $description = '';

    if (function_exists('botwriter_seo_get_meta')) {
        $description = (string) botwriter_seo_get_meta($post_id, 'desc');
    }

    if ($description === '') {
        $description = trim((string) get_post_field('post_excerpt', $post_id));
    }

    if ($description === '') {
        $content = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
        $description = wp_trim_words($content, 30, '');
    }

    $description = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($description)));

    return $description;
}

/**
 * Output Open Graph and Twitter Card tags.
 */
function botwriter_seo_output_social_meta() {
    if (!is_singular()) {
        return;
    }

    if (!botwriter_is_seo_social_meta_enabled()) {
        return;
    }

    if (botwriter_seo_social_has_external_provider()) {
        return;
    }

    if (apply_filters('botwriter_seo_skip_social_meta', false) === true) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $url = get_permalink($post_id);
    if (!$url) {
        return;
    }

    $title = '';
    if (function_exists('botwriter_seo_get_meta')) {
        $title = (string) botwriter_seo_get_meta($post_id, 'title');
    }
    if ($title === '') {
        $title = wp_strip_all_tags((string) get_the_title($post_id));
    }

    $description = botwriter_seo_social_description($post_id);
    $site_name = (string) get_bloginfo('name');
    $locale = str_replace('-', '_', (string) get_locale());
    $post_type = (string) get_post_type($post_id);
    $og_type = $post_type === 'post' ? 'article' : 'website';

    $image_url = '';
    $image_width = 0;
    $image_height = 0;
    if (has_post_thumbnail($post_id)) {
        $thumb_id = (int) get_post_thumbnail_id($post_id);
        $image_data = wp_get_attachment_image_src($thumb_id, 'full');
        if (is_array($image_data) && !empty($image_data[0])) {
            $image_url = (string) $image_data[0];
            $image_width = isset($image_data[1]) ? intval($image_data[1]) : 0;
            $image_height = isset($image_data[2]) ? intval($image_data[2]) : 0;
        }
    }

    $twitter_card = $image_url !== '' ? 'summary_large_image' : 'summary';

    echo "\n<!-- BotWriter social meta -->\n";
    echo '<meta property="og:locale" content="' . esc_attr($locale) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    if ($description !== '') {
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
    if ($site_name !== '') {
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
    }

    if ($image_url !== '') {
        echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
        if ($image_width > 0) {
            echo '<meta property="og:image:width" content="' . esc_attr((string) $image_width) . '" />' . "\n";
        }
        if ($image_height > 0) {
            echo '<meta property="og:image:height" content="' . esc_attr((string) $image_height) . '" />' . "\n";
        }
    }

    if ($og_type === 'article') {
        $published = get_post_time('c', true, $post_id);
        $modified = get_post_modified_time('c', true, $post_id);
        if ($published) {
            echo '<meta property="article:published_time" content="' . esc_attr((string) $published) . '" />' . "\n";
        }
        if ($modified) {
            echo '<meta property="article:modified_time" content="' . esc_attr((string) $modified) . '" />' . "\n";
        }
    }

    echo '<meta name="twitter:card" content="' . esc_attr($twitter_card) . '" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
    if ($description !== '') {
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
    }
    if ($image_url !== '') {
        echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
    }
}
add_action('wp_head', 'botwriter_seo_output_social_meta', 40);
