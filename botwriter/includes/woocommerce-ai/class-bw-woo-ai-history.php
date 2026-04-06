<?php
/**
 * BotWriter WooCommerce AI - History & Revert
 *
 * Manages the optimization history and provides revert/undo functionality.
 * Backups are stored in post meta by BW_Woo_AI_Preview.
 *
 * @package BotWriter
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BW_Woo_AI_History {

    /* ------------------------------------------------------------------
     * History tab render
     * ----------------------------------------------------------------*/

    public function render() {
        ?>
        <div class="bw-woo-card">
            <h3><?php esc_html_e( 'Optimization History', 'botwriter' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Products that have been optimized with AI. You can revert any product to its original content.', 'botwriter' ); ?></p>

            <div class="bw-history-filters" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:15px;">
                <select id="bw-history-filter-category" style="min-width:180px;">
                    <option value=""><?php esc_html_e( 'All categories', 'botwriter' ); ?></option>
                </select>
                <input type="search" id="bw-history-search" placeholder="<?php esc_attr_e( 'Search by product name…', 'botwriter' ); ?>" style="min-width:220px;">
                <button type="button" class="button" id="bw-history-filter-btn"><?php esc_html_e( 'Filter', 'botwriter' ); ?></button>
            </div>

            <div id="bw-history-table-wrap">
                <p class="description"><?php esc_html_e( 'Loading history…', 'botwriter' ); ?></p>
            </div>

            <div style="margin-top:15px;display:flex;gap:10px;">
                <button type="button" class="button" id="bw-revert-selected" disabled>
                    <?php esc_html_e( 'Revert Selected', 'botwriter' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * AJAX: get history (optimized products)
     * ----------------------------------------------------------------*/

    public function ajax_get_history() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = 20;
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        // Query products that have been AI-optimized.
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'     => BW_Woo_AI_Preview::OPTIMIZED_KEY,
                'compare' => 'EXISTS',
            ] ],
            'orderby'        => 'meta_value',
            'meta_key'       => BW_Woo_AI_Preview::OPTIMIZED_KEY,
            'order'          => 'DESC',
        ];

        if ( ! empty( $category ) ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category,
            ] ];
        }

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            $timestamp = get_post_meta( $pid, BW_Woo_AI_Preview::BACKUP_PREFIX . 'timestamp', true );
            $provider  = get_post_meta( $pid, '_bw_woo_ai_provider', true );
            $model     = get_post_meta( $pid, '_bw_woo_ai_model', true );

            $has_backup = ! empty( get_post_meta( $pid, BW_Woo_AI_Preview::BACKUP_PREFIX . 'timestamp', true ) );

            $items[] = [
                'id'           => $pid,
                'name'         => $product->get_name(),
                'optimized_at' => $timestamp,
                'provider'     => $provider,
                'model'        => $model,
                'has_backup'   => $has_backup,
                'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
            ];
        }

        wp_send_json_success( [
            'items'       => $items,
            'total'       => $query->found_posts,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: revert products to backup
     * ----------------------------------------------------------------*/

    public function ajax_revert() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) $_POST['product_ids'] ) : [];

        if ( empty( $product_ids ) ) {
            wp_send_json_error( 'No products selected.' );
        }

        $reverted = 0;
        $errors   = [];

        foreach ( $product_ids as $pid ) {
            $result = $this->revert_product( $pid );
            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            } else {
                $reverted++;
            }
        }

        botwriter_log( 'WooCommerce AI revert', [
            'reverted' => $reverted,
            'errors'   => count( $errors ),
        ] );

        wp_send_json_success( [
            'reverted' => $reverted,
            'errors'   => $errors,
        ] );
    }

    /* ------------------------------------------------------------------
     * Revert a single product
     * ----------------------------------------------------------------*/

    /**
     * Restore a product's content from backup meta.
     *
     * @param  int $product_id
     * @return true|WP_Error
     */
    public function revert_product( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', "Product {$product_id} not found." );
        }

        $prefix = BW_Woo_AI_Preview::BACKUP_PREFIX;

        // Check that a backup exists.
        $backup_ts = get_post_meta( $product_id, $prefix . 'timestamp', true );
        if ( empty( $backup_ts ) ) {
            return new WP_Error( 'no_backup', "No backup found for product {$product_id}." );
        }

        // Restore title.
        if ( metadata_exists( 'post', $product_id, $prefix . 'title' ) ) {
            $product->set_name( (string) get_post_meta( $product_id, $prefix . 'title', true ) );
        }

        // Restore description.
        if ( metadata_exists( 'post', $product_id, $prefix . 'description' ) ) {
            $product->set_description( (string) get_post_meta( $product_id, $prefix . 'description', true ) );
        }

        // Restore short description.
        if ( metadata_exists( 'post', $product_id, $prefix . 'short_description' ) ) {
            $product->set_short_description( (string) get_post_meta( $product_id, $prefix . 'short_description', true ) );
        }

        // Restore tags (empty backup = clear all tags).
        if ( metadata_exists( 'post', $product_id, $prefix . 'tags' ) ) {
            $tags = get_post_meta( $product_id, $prefix . 'tags', true );
            if ( empty( $tags ) ) {
                wp_set_object_terms( $product_id, [], 'product_tag' );
            } else {
                $tag_names = array_map( 'trim', explode( ',', $tags ) );
                $tag_names = array_filter( $tag_names );
                wp_set_object_terms( $product_id, $tag_names, 'product_tag' );
            }
        }

        // Restore ALT tag.
        if ( metadata_exists( 'post', $product_id, $prefix . 'alt_tags' ) ) {
            $image_id = $product->get_image_id();
            if ( $image_id ) {
                update_post_meta( $image_id, '_wp_attachment_image_alt', (string) get_post_meta( $product_id, $prefix . 'alt_tags', true ) );
            }
        }

        // Restore review summary.
        if ( metadata_exists( 'post', $product_id, $prefix . 'review_summary' ) ) {
            update_post_meta( $product_id, '_bw_review_summary', (string) get_post_meta( $product_id, $prefix . 'review_summary', true ) );
        }

        // Restore SEO meta.
        if ( metadata_exists( 'post', $product_id, $prefix . 'seo_meta' ) ) {
            $this->restore_seo_meta( $product_id, (string) get_post_meta( $product_id, $prefix . 'seo_meta', true ) );
        }

        // Restore SEO title.
        if ( metadata_exists( 'post', $product_id, $prefix . 'seo_title' ) ) {
            $this->restore_seo_title( $product_id, (string) get_post_meta( $product_id, $prefix . 'seo_title', true ) );
        }

        $product->save();

        // Clean up optimization markers.
        delete_post_meta( $product_id, BW_Woo_AI_Preview::OPTIMIZED_KEY );
        delete_post_meta( $product_id, '_bw_woo_ai_provider' );
        delete_post_meta( $product_id, '_bw_woo_ai_model' );

        // Clean up BotWriter's own SEO fallback keys.
        delete_post_meta( $product_id, '_bw_woo_seo_meta' );
        delete_post_meta( $product_id, '_bw_woo_seo_title' );

        // Remove backup meta.
        $backup_keys = [
            'title', 'description', 'short_description', 'tags',
            'alt_tags', 'review_summary', 'seo_meta', 'seo_title', 'timestamp',
        ];
        foreach ( $backup_keys as $key ) {
            delete_post_meta( $product_id, $prefix . $key );
        }

        return true;
    }

    /* ------------------------------------------------------------------
     * AJAX: get diff (backup vs current) for a single product
     * ----------------------------------------------------------------*/

    public function ajax_get_diff() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Missing product ID.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found.' );
        }

        $prefix    = BW_Woo_AI_Preview::BACKUP_PREFIX;
        $backup_ts = get_post_meta( $product_id, $prefix . 'timestamp', true );

        if ( empty( $backup_ts ) ) {
            wp_send_json_error( 'No backup found for this product.' );
        }

        // Gather current values.
        $tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
        $current_tags = ( ! is_wp_error( $tags ) ) ? implode( ', ', $tags ) : '';

        $image_id    = $product->get_image_id();
        $current_alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';

        $field_map = [
            'title'             => [
                'label'   => 'Title',
                'backup'  => get_post_meta( $product_id, $prefix . 'title', true ),
                'current' => $product->get_name(),
            ],
            'description'       => [
                'label'   => 'Description',
                'backup'  => get_post_meta( $product_id, $prefix . 'description', true ),
                'current' => $product->get_description(),
            ],
            'short_description' => [
                'label'   => 'Short Description',
                'backup'  => get_post_meta( $product_id, $prefix . 'short_description', true ),
                'current' => $product->get_short_description(),
            ],
            'tags'              => [
                'label'   => 'Tags',
                'backup'  => get_post_meta( $product_id, $prefix . 'tags', true ),
                'current' => $current_tags,
            ],
            'alt_tags'          => [
                'label'   => 'Image ALT Text',
                'backup'  => get_post_meta( $product_id, $prefix . 'alt_tags', true ),
                'current' => $current_alt,
            ],
            'review_summary'    => [
                'label'   => 'Review Summary',
                'backup'  => get_post_meta( $product_id, $prefix . 'review_summary', true ),
                'current' => get_post_meta( $product_id, '_bw_review_summary', true ),
            ],
            'seo_meta'          => [
                'label'   => 'SEO Meta Description',
                'backup'  => get_post_meta( $product_id, $prefix . 'seo_meta', true ),
                'current' => $this->get_current_seo_meta( $product_id ),
            ],
            'seo_title'         => [
                'label'   => 'SEO Title',
                'backup'  => get_post_meta( $product_id, $prefix . 'seo_title', true ),
                'current' => $this->get_current_seo_title( $product_id ),
            ],
        ];

        // Only include fields that have a backup key and actually changed.
        $fields = [];
        foreach ( $field_map as $key => $info ) {
            // Check if the backup meta key exists at all (not just non-empty).
            if ( ! metadata_exists( 'post', $product_id, $prefix . $key ) ) {
                continue;
            }
            $backup_val  = (string) $info['backup'];
            $current_val = (string) $info['current'];
            // Only include fields that actually changed.
            if ( $backup_val === $current_val ) {
                continue;
            }
            $fields[] = [
                'field'   => $key,
                'label'   => $info['label'],
                'old'     => $backup_val,
                'current' => $current_val,
            ];
        }

        wp_send_json_success( [
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'backup_date'  => $backup_ts,
            'fields'       => $fields,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: revert a single field for a product
     * ----------------------------------------------------------------*/

    public function ajax_revert_field() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $field      = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';

        if ( ! $product_id || ! $field ) {
            wp_send_json_error( 'Missing product ID or field.' );
        }

        $allowed = [ 'title', 'description', 'short_description', 'tags', 'alt_tags', 'review_summary', 'seo_meta', 'seo_title' ];
        if ( ! in_array( $field, $allowed, true ) ) {
            wp_send_json_error( 'Invalid field.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found.' );
        }

        $prefix    = BW_Woo_AI_Preview::BACKUP_PREFIX;

        // Check that backup meta key exists (value can be empty — means field was empty before).
        if ( ! metadata_exists( 'post', $product_id, $prefix . $field ) ) {
            wp_send_json_error( 'No backup found for this field.' );
        }

        $backup_val = get_post_meta( $product_id, $prefix . $field, true );

        switch ( $field ) {
            case 'title':
                $product->set_name( (string) $backup_val );
                $product->save();
                break;

            case 'description':
                $product->set_description( $backup_val );
                $product->save();
                break;

            case 'short_description':
                $product->set_short_description( $backup_val );
                $product->save();
                break;

            case 'tags':
                if ( empty( $backup_val ) ) {
                    wp_set_object_terms( $product_id, [], 'product_tag' );
                } else {
                    $tag_names = array_map( 'trim', explode( ',', $backup_val ) );
                    $tag_names = array_filter( $tag_names );
                    wp_set_object_terms( $product_id, $tag_names, 'product_tag' );
                }
                break;

            case 'alt_tags':
                $image_id = $product->get_image_id();
                if ( $image_id ) {
                    update_post_meta( $image_id, '_wp_attachment_image_alt', $backup_val );
                }
                break;

            case 'review_summary':
                update_post_meta( $product_id, '_bw_review_summary', $backup_val );
                break;

            case 'seo_meta':
                $this->restore_seo_meta( $product_id, $backup_val );
                break;

            case 'seo_title':
                $this->restore_seo_title( $product_id, $backup_val );
                break;
        }

        // Remove the backup key for this field.
        delete_post_meta( $product_id, $prefix . $field );

        wp_send_json_success( [
            'product_id' => $product_id,
            'field'      => $field,
            'reverted'   => true,
        ] );
    }

    /* ------------------------------------------------------------------
     * Restore SEO meta to plugins
     * ----------------------------------------------------------------*/

    private function restore_seo_meta( $product_id, $meta ) {
        $meta = sanitize_text_field( $meta );

        // Always update BotWriter's own fallback key.
        update_post_meta( $product_id, '_bw_woo_seo_meta', $meta );

        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_metadesc', $meta );
        }
        if ( class_exists( 'RankMath' ) ) {
            update_post_meta( $product_id, 'rank_math_description', $meta );
        }
        if ( function_exists( 'seopress_init' ) ) {
            update_post_meta( $product_id, '_seopress_titles_desc', $meta );
        }
    }

    /* ------------------------------------------------------------------
     * Restore SEO title to plugins
     * ----------------------------------------------------------------*/

    private function restore_seo_title( $product_id, $title ) {
        $title = sanitize_text_field( $title );

        // Always update BotWriter's own fallback key.
        update_post_meta( $product_id, '_bw_woo_seo_title', $title );

        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_title', $title );
        }
        if ( class_exists( 'RankMath' ) ) {
            update_post_meta( $product_id, 'rank_math_title', $title );
        }
        if ( function_exists( 'seopress_init' ) ) {
            update_post_meta( $product_id, '_seopress_titles_title', $title );
        }
    }

    /* ------------------------------------------------------------------
     * Get current SEO meta from active plugin
     * ----------------------------------------------------------------*/

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
     * Get current SEO title from active plugin
     * ----------------------------------------------------------------*/

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
}
