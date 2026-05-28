<?php
/**
 * BotWriter WooCommerce AI - Preview & Apply
 *
 * Handles saving generated content back to WooCommerce products,
 * creating backups before applying, and SEO plugin integration.
 *
 * @package BotWriter
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotWriter_Woo_AI_Preview {

    /** Meta key prefix for backups. */
    const BACKUP_PREFIX = '_bw_backup_';

    /** Meta key that marks a product as AI-optimized. */
    const OPTIMIZED_KEY = '_bw_woo_ai_optimized';

    /* ------------------------------------------------------------------
     * AJAX: apply approved changes
     * ----------------------------------------------------------------*/

    public function ajax_apply() {
        BotWriter_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Items are unslashed here and each supported field is sanitized before use below.
        $items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];

        if ( empty( $items ) || ! is_array( $items ) ) {
            wp_send_json_error( 'No items to apply.' );
        }

        $applied = 0;
        $errors  = [];

        foreach ( $items as $item ) {
            $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $fields     = [];

            if ( isset( $item['fields'] ) && is_array( $item['fields'] ) ) {
                foreach ( $item['fields'] as $field_key => $field_value ) {
                    if ( ! is_string( $field_key ) ) {
                        continue;
                    }

                    $fields[ sanitize_key( $field_key ) ] = is_scalar( $field_value ) ? (string) $field_value : '';
                }
            }

            if ( ! $product_id || empty( $fields ) ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $errors[] = "Product {$product_id} not found.";
                continue;
            }

            $result = $this->apply_fields( $product, $fields );
            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            } else {
                $applied++;
            }
        }

        // Log the operation.
        botwriter_log( 'WooCommerce AI apply', [
            'applied' => $applied,
            'errors'  => count( $errors ),
        ] );

        wp_send_json_success( [
            'applied' => $applied,
            'errors'  => $errors,
        ] );
    }

    /* ------------------------------------------------------------------
     * Apply fields to a single product
     * ----------------------------------------------------------------*/

    /**
     * Apply AI-generated content to a product, backing up originals first.
     *
     * @param  WC_Product $product   The product object.
     * @param  array      $fields    Assoc array [ field_name => content ].
     * @return true|WP_Error
     */
    public function apply_fields( $product, $fields ) {
        $product_id = $product->get_id();
        $provider   = isset( $fields['_provider'] ) ? sanitize_key( $fields['_provider'] ) : '';
        $model      = isset( $fields['_model'] ) ? sanitize_text_field( $fields['_model'] ) : '';

        // Create backup before any changes.
        $this->create_backup( $product );

        // Apply each field.
        foreach ( $fields as $field => $content ) {
            // Skip metadata fields.
            if ( strpos( $field, '_' ) === 0 ) {
                continue;
            }

            $content = $this->sanitize_field_content( $field, $content );

            switch ( $field ) {
                case 'title':
                    $product->set_name( $content );
                    break;

                case 'description':
                    $product->set_description( $content );
                    break;

                case 'short_description':
                    $product->set_short_description( $content );
                    break;

                case 'tags':
                    $tag_names = array_map( 'trim', explode( ',', $content ) );
                    $tag_names = array_filter( $tag_names );
                    wp_set_object_terms( $product_id, $tag_names, 'product_tag' );
                    break;

                case 'review_summary':
                    update_post_meta( $product_id, '_bw_review_summary', $content );
                    break;

                case 'alt_tags':
                    $this->apply_alt_tags( $product, $content );
                    break;

                case 'seo_meta':
                    $this->apply_seo_meta( $product_id, $content );
                    break;

                case 'seo_title':
                    $this->apply_seo_title( $product_id, $content );
                    break;
            }
        }

        $product->save();

        // Mark as AI-optimized.
        update_post_meta( $product_id, self::OPTIMIZED_KEY, current_time( 'mysql' ) );
        update_post_meta( $product_id, '_bw_woo_ai_provider', $provider );
        update_post_meta( $product_id, '_bw_woo_ai_model', $model );

        return true;
    }

    /* ------------------------------------------------------------------
     * Backup management
     * ----------------------------------------------------------------*/

    /**
     * Create a backup of all current product data before overwriting.
     */
    private function create_backup( $product ) {
        $product_id = $product->get_id();
        $timestamp  = current_time( 'mysql' );

        // Backup main fields.
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'title', $product->get_name() );
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'description', $product->get_description() );
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'short_description', $product->get_short_description() );

        // Backup tags (always store, even if empty).
        $tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
        $tags_str = ( ! is_wp_error( $tags ) ) ? implode( ', ', $tags ) : '';
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'tags', $tags_str );

        // Backup ALT tags (always store, even if no image).
        $image_id = $product->get_image_id();
        $alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'alt_tags', (string) $alt );

        // Backup review summary (always store).
        $review_summary = get_post_meta( $product_id, '_bw_review_summary', true );
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'review_summary', (string) $review_summary );

        // Backup SEO meta (always store).
        $seo_meta = $this->get_current_seo_meta( $product_id );
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'seo_meta', (string) $seo_meta );

        // Backup SEO title (always store).
        $seo_title = $this->get_current_seo_title( $product_id );
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'seo_title', (string) $seo_title );

        // Store backup timestamp and provider info.
        update_post_meta( $product_id, self::BACKUP_PREFIX . 'timestamp', $timestamp );
    }

    /* ------------------------------------------------------------------
     * ALT tag application
     * ----------------------------------------------------------------*/

    private function apply_alt_tags( $product, $alt_text ) {
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
        }

        // Also update gallery images if alt text is a newline-separated list.
        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) && strpos( $alt_text, "\n" ) !== false ) {
            $alts = array_map( 'trim', explode( "\n", $alt_text ) );
            // First line was for the main image, rest for gallery.
            array_shift( $alts );
            foreach ( $gallery_ids as $i => $gid ) {
                if ( isset( $alts[ $i ] ) && ! empty( $alts[ $i ] ) ) {
                    update_post_meta( $gid, '_wp_attachment_image_alt', sanitize_text_field( $alts[ $i ] ) );
                }
            }
        }
    }

    /* ------------------------------------------------------------------
     * SEO meta application (Yoast, RankMath, SEOPress)
     * ----------------------------------------------------------------*/

    private function apply_seo_meta( $product_id, $meta_description ) {
        $meta_description = $this->normalize_structured_seo_text( $meta_description, 'seo_meta' );
        $meta_description = sanitize_text_field( $meta_description );

        // Always store in BotWriter's own meta key (fallback when no SEO plugin).
        update_post_meta( $product_id, '_bw_woo_seo_meta', $meta_description );

        // Yoast SEO.
        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_metadesc', $meta_description );
        }

        // Rank Math.
        if ( class_exists( 'RankMath' ) ) {
            update_post_meta( $product_id, 'rank_math_description', $meta_description );
        }

        // SEOPress.
        if ( function_exists( 'seopress_init' ) ) {
            update_post_meta( $product_id, '_seopress_titles_desc', $meta_description );
        }

        // All in One SEO.
        if ( class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
            update_post_meta( $product_id, '_aioseo_description', $meta_description );
        }

        // The SEO Framework.
        if ( function_exists( 'the_seo_framework' ) ) {
            update_post_meta( $product_id, '_genesis_description', $meta_description );
        }
    }

    /**
     * Get current SEO meta description from whatever plugin is active.
     */
    private function get_current_seo_meta( $product_id ) {
        if ( defined( 'WPSEO_VERSION' ) ) {
            return get_post_meta( $product_id, '_yoast_wpseo_metadesc', true );
        }
        if ( class_exists( 'RankMath' ) ) {
            return get_post_meta( $product_id, 'rank_math_description', true );
        }
        if ( function_exists( 'seopress_init' ) ) {
            return get_post_meta( $product_id, '_seopress_titles_desc', true );
        }
        // Fallback: BotWriter's own stored value.
        return (string) get_post_meta( $product_id, '_bw_woo_seo_meta', true );
    }

    /* ------------------------------------------------------------------
     * SEO title application (Yoast, RankMath, SEOPress, AIOSEO, TSF)
     * ----------------------------------------------------------------*/

    private function apply_seo_title( $product_id, $seo_title ) {
        $seo_title = $this->normalize_structured_seo_text( $seo_title, 'seo_title' );
        $seo_title = sanitize_text_field( $seo_title );

        // Always store in BotWriter's own meta key (fallback when no SEO plugin).
        update_post_meta( $product_id, '_bw_woo_seo_title', $seo_title );

        // Yoast SEO.
        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_title', $seo_title );
        }

        // Rank Math.
        if ( class_exists( 'RankMath' ) ) {
            update_post_meta( $product_id, 'rank_math_title', $seo_title );
        }

        // SEOPress.
        if ( function_exists( 'seopress_init' ) ) {
            update_post_meta( $product_id, '_seopress_titles_title', $seo_title );
        }

        // All in One SEO.
        if ( class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
            update_post_meta( $product_id, '_aioseo_title', $seo_title );
        }

        // The SEO Framework.
        if ( function_exists( 'the_seo_framework' ) ) {
            update_post_meta( $product_id, '_genesis_title', $seo_title );
        }
    }

    /**
     * Normalize structured AI output (JSON arrays/objects) into plain SEO text.
     *
     * @param string $content Raw AI response content.
     * @param string $field   Supported values: seo_title, seo_meta.
     * @return string
     */
    private function normalize_structured_seo_text( $content, $field ) {
        $candidate = trim( (string) $content );
        if ( $candidate === '' ) {
            return '';
        }

        $candidate = preg_replace( '/^```(?:html|json)?\s*/i', '', $candidate );
        $candidate = preg_replace( '/\s*```$/', '', $candidate );
        $candidate = trim( (string) $candidate );

        for ( $depth = 0; $depth < 2; $depth++ ) {
            $decoded = json_decode( $candidate, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                break;
            }

            if ( is_string( $decoded ) ) {
                $candidate = trim( $decoded );
                continue;
            }

            if ( is_array( $decoded ) ) {
                $extracted = $this->extract_structured_seo_text_value( $decoded, $field );
                if ( $extracted !== '' ) {
                    return $extracted;
                }
            }

            break;
        }

        return $candidate;
    }

    /**
     * Extract SEO text from decoded payloads that may contain nested wrappers.
     *
     * @param array  $payload Decoded JSON payload.
     * @param string $field   Supported values: seo_title, seo_meta.
     * @return string
     */
    private function extract_structured_seo_text_value( $payload, $field ) {
        $preferred = [
            'seo_title' => [ 'seo_title', 'title', 'text', 'content' ],
            'seo_meta'  => [ 'seo_meta', 'meta_description', 'description', 'text', 'content' ],
        ];

        $keys = isset( $preferred[ $field ] ) ? $preferred[ $field ] : [ 'text', 'content' ];

        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $payload ) ) {
                continue;
            }

            $value = $this->pick_structured_seo_text_value( $payload[ $key ], $keys, 0 );
            if ( $value !== '' ) {
                return $value;
            }
        }

        foreach ( $payload as $value ) {
            $candidate = $this->pick_structured_seo_text_value( $value, $keys, 0 );
            if ( $candidate !== '' ) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Recursively pick the first useful scalar text from nested decoded values.
     *
     * @param mixed $value Candidate value.
     * @param array $keys  Preferred keys.
     * @param int   $depth Recursion depth guard.
     * @return string
     */
    private function pick_structured_seo_text_value( $value, $keys, $depth = 0 ) {
        if ( $depth > 4 ) {
            return '';
        }

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( is_scalar( $value ) && ! is_bool( $value ) ) {
            return trim( (string) $value );
        }

        if ( ! is_array( $value ) ) {
            return '';
        }

        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $value ) ) {
                continue;
            }

            $candidate = $this->pick_structured_seo_text_value( $value[ $key ], $keys, $depth + 1 );
            if ( $candidate !== '' ) {
                return $candidate;
            }
        }

        foreach ( $value as $item ) {
            $candidate = $this->pick_structured_seo_text_value( $item, $keys, $depth + 1 );
            if ( $candidate !== '' ) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Get current SEO title from whatever plugin is active.
     */
    private function get_current_seo_title( $product_id ) {
        if ( defined( 'WPSEO_VERSION' ) ) {
            return get_post_meta( $product_id, '_yoast_wpseo_title', true );
        }
        if ( class_exists( 'RankMath' ) ) {
            return get_post_meta( $product_id, 'rank_math_title', true );
        }
        if ( function_exists( 'seopress_init' ) ) {
            return get_post_meta( $product_id, '_seopress_titles_title', true );
        }
        // Fallback: BotWriter's own stored value.
        return (string) get_post_meta( $product_id, '_bw_woo_seo_title', true );
    }

    /* ------------------------------------------------------------------
     * Field sanitization
     * ----------------------------------------------------------------*/

    private function sanitize_field_content( $field, $content ) {
        switch ( $field ) {
            case 'description':
                return wp_kses_post( $content );

            case 'short_description':
                return wp_kses_post( $content );

            case 'review_summary':
                return wp_kses_post( $content );

            case 'title':
            case 'tags':
            case 'alt_tags':
                return sanitize_text_field( $content );

            case 'seo_meta':
                return sanitize_text_field( $this->normalize_structured_seo_text( $content, 'seo_meta' ) );

            case 'seo_title':
                return sanitize_text_field( $this->normalize_structured_seo_text( $content, 'seo_title' ) );

            default:
                return sanitize_text_field( $content );
        }
    }

    /* ------------------------------------------------------------------
     * AJAX: apply approved category description changes
     * ----------------------------------------------------------------*/

    public function ajax_apply_categories() {
        BotWriter_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Items are unslashed here and each supported field is sanitized before use below.
        $items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];

        if ( empty( $items ) || ! is_array( $items ) ) {
            wp_send_json_error( 'No items to apply.' );
        }

        $applied = 0;
        $errors  = [];

        foreach ( $items as $item ) {
            $cat_id      = isset( $item['category_id'] ) ? absint( $item['category_id'] ) : 0;
            $description = isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '';

            if ( ! $cat_id ) {
                continue;
            }

            $result = wp_update_term( $cat_id, 'product_cat', [
                'description' => $description,
            ] );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            } else {
                $applied++;
            }
        }

        botwriter_log( 'WooCommerce AI category apply', [
            'applied' => $applied,
            'errors'  => count( $errors ),
        ] );

        wp_send_json_success( [
            'applied' => $applied,
            'errors'  => $errors,
        ] );
    }
}
