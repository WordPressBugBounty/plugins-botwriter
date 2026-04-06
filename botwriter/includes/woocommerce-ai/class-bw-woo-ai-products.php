<?php
/**
 * BotWriter WooCommerce AI - Products
 *
 * Handles product listing, filtering, and category operations.
 *
 * @package BotWriter
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BW_Woo_AI_Products {

    /* ------------------------------------------------------------------
     * Main products tab render
     * ----------------------------------------------------------------*/

    public function render() {
        ?>
        <div class="bw-woo-card">
            <h3><?php esc_html_e( 'Products Overview', 'botwriter' ); ?></h3>
            <p class="description"><?php esc_html_e( 'View and manage your WooCommerce products. Select products and optimize their content with AI.', 'botwriter' ); ?></p>

            <?php $this->render_filter_bar(); ?>

            <div id="bw-products-table-wrap">
                <p class="description"><?php esc_html_e( 'Loading products…', 'botwriter' ); ?></p>
            </div>

            <div style="margin-top:15px;display:flex;gap:10px;">
                <button type="button" class="button button-primary" id="bw-optimize-selected" disabled>
                    <?php esc_html_e( 'Optimize Selected', 'botwriter' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Categories tab render
     * ----------------------------------------------------------------*/

    public function render_categories() {
        ?>
        <div class="bw-woo-card">
            <h3><?php esc_html_e( 'Product Categories', 'botwriter' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Optimize category descriptions with AI.', 'botwriter' ); ?></p>
            <div id="bw-categories-table-wrap">
                <p class="description"><?php esc_html_e( 'Loading categories…', 'botwriter' ); ?></p>
            </div>
            <div style="margin-top:15px;">
                <button type="button" class="button button-primary" id="bw-optimize-categories" disabled>
                    <?php esc_html_e( 'Generate Category Descriptions', 'botwriter' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Filter bar (reused in Products tab & Bulk Optimizer)
     * ----------------------------------------------------------------*/

    public function render_filter_bar() {
        ?>
        <div class="bw-filter-bar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:15px 0;padding:15px;background:#f9f9f9;border-radius:8px;">
            <div>
                <label for="bw-filter-status"><strong><?php esc_html_e( 'Filter', 'botwriter' ); ?></strong></label><br>
                <select id="bw-filter-status" class="form-select" style="min-width:200px;">
                    <option value="all"><?php esc_html_e( 'All Products', 'botwriter' ); ?></option>
                    <option value="no_description"><?php esc_html_e( 'Without Description', 'botwriter' ); ?></option>
                    <option value="short_description"><?php esc_html_e( 'Without Short Description', 'botwriter' ); ?></option>
                    <option value="short_content"><?php esc_html_e( 'Description < 100 words', 'botwriter' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Custom…', 'botwriter' ); ?></option>
                </select>
            </div>
            <div>
                <label for="bw-filter-category"><strong><?php esc_html_e( 'Category', 'botwriter' ); ?></strong></label><br>
                <select id="bw-filter-category" class="form-select" style="min-width:180px;">
                    <option value=""><?php esc_html_e( 'All Categories', 'botwriter' ); ?></option>
                </select>
            </div>
            <div>
                <label for="bw-filter-stock"><strong><?php esc_html_e( 'Stock', 'botwriter' ); ?></strong></label><br>
                <select id="bw-filter-stock" class="form-select" style="min-width:140px;">
                    <option value=""><?php esc_html_e( 'Any', 'botwriter' ); ?></option>
                    <option value="instock"><?php esc_html_e( 'In Stock', 'botwriter' ); ?></option>
                    <option value="outofstock"><?php esc_html_e( 'Out of Stock', 'botwriter' ); ?></option>
                    <option value="onbackorder"><?php esc_html_e( 'On Backorder', 'botwriter' ); ?></option>
                </select>
            </div>
            <div>
                <label for="bw-filter-type"><strong><?php esc_html_e( 'Type', 'botwriter' ); ?></strong></label><br>
                <select id="bw-filter-type" class="form-select" style="min-width:140px;">
                    <option value=""><?php esc_html_e( 'Any Type', 'botwriter' ); ?></option>
                    <option value="simple"><?php esc_html_e( 'Simple', 'botwriter' ); ?></option>
                    <option value="variable"><?php esc_html_e( 'Variable', 'botwriter' ); ?></option>
                    <option value="external"><?php esc_html_e( 'External', 'botwriter' ); ?></option>
                    <option value="grouped"><?php esc_html_e( 'Grouped', 'botwriter' ); ?></option>
                </select>
            </div>
            <div>
                <label for="bw-filter-search"><strong><?php esc_html_e( 'Search', 'botwriter' ); ?></strong></label><br>
                <input type="text" id="bw-filter-search" placeholder="<?php esc_attr_e( 'Product name…', 'botwriter' ); ?>" style="min-width:160px;">
            </div>
            <div>
                <button type="button" class="button" id="bw-filter-apply"><?php esc_html_e( 'Filter', 'botwriter' ); ?></button>
            </div>
        </div>

        <!-- Custom filter row (hidden by default, shown when "Custom…" is selected) -->
        <div id="bw-custom-filter-row" class="bw-custom-filter-row" style="display:none;margin:0 0 15px 0;padding:15px;background:#f0f0f1;border-radius:8px;display:none;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                <div>
                    <label for="bw-custom-field"><strong><?php esc_html_e( 'Field', 'botwriter' ); ?></strong></label><br>
                    <select id="bw-custom-field" class="form-select" style="min-width:180px;">
                        <option value="description_words"><?php esc_html_e( 'Description (words)', 'botwriter' ); ?></option>
                        <option value="short_description_words"><?php esc_html_e( 'Short Description (words)', 'botwriter' ); ?></option>
                        <option value="price"><?php esc_html_e( 'Price', 'botwriter' ); ?></option>
                        <option value="sku"><?php esc_html_e( 'SKU', 'botwriter' ); ?></option>
                        <option value="tags_count"><?php esc_html_e( 'Number of Tags', 'botwriter' ); ?></option>
                        <option value="reviews_count"><?php esc_html_e( 'Number of Reviews', 'botwriter' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="bw-custom-condition"><strong><?php esc_html_e( 'Condition', 'botwriter' ); ?></strong></label><br>
                    <select id="bw-custom-condition" class="form-select" style="min-width:140px;">
                        <option value="gt">&gt; <?php esc_html_e( 'Greater than', 'botwriter' ); ?></option>
                        <option value="lt">&lt; <?php esc_html_e( 'Less than', 'botwriter' ); ?></option>
                        <option value="eq">= <?php esc_html_e( 'Equal to', 'botwriter' ); ?></option>
                        <option value="gte">&ge; <?php esc_html_e( 'Greater or equal', 'botwriter' ); ?></option>
                        <option value="lte">&le; <?php esc_html_e( 'Less or equal', 'botwriter' ); ?></option>
                        <option value="is_empty"><?php esc_html_e( 'Is empty', 'botwriter' ); ?></option>
                        <option value="is_not_empty"><?php esc_html_e( 'Is not empty', 'botwriter' ); ?></option>
                        <option value="contains"><?php esc_html_e( 'Contains', 'botwriter' ); ?></option>
                    </select>
                </div>
                <div id="bw-custom-value-wrap">
                    <label for="bw-custom-value"><strong><?php esc_html_e( 'Value', 'botwriter' ); ?></strong></label><br>
                    <input type="text" id="bw-custom-value" placeholder="<?php esc_attr_e( 'e.g. 2000', 'botwriter' ); ?>" style="min-width:120px;">
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * AJAX: get products (filtered, paginated)
     * ----------------------------------------------------------------*/

    public function ajax_get_products() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? min( absint( $_POST['per_page'] ), 500 ) : 20;
        if ( $per_page < 1 ) {
            $per_page = 20;
        }

        $args = [
            'status'   => 'publish',
            'limit'    => -1,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'ids',
        ];

        // Category filter
        if ( ! empty( $_POST['category'] ) ) {
            $args['category'] = [ sanitize_text_field( wp_unslash( $_POST['category'] ) ) ];
        }

        // Product type filter
        if ( ! empty( $_POST['product_type'] ) ) {
            $args['type'] = sanitize_key( $_POST['product_type'] );
        }

        // Stock status filter
        if ( ! empty( $_POST['stock_status'] ) ) {
            $args['stock_status'] = sanitize_key( $_POST['stock_status'] );
        }

        // Search filter
        $search = '';
        if ( ! empty( $_POST['search'] ) ) {
            $search = sanitize_text_field( wp_unslash( $_POST['search'] ) );
        }

        // Content filter
        $content_filter = '';
        if ( ! empty( $_POST['filter_status'] ) ) {
            $content_filter = sanitize_key( $_POST['filter_status'] );
        }

        // Custom filter params
        $custom_field     = '';
        $custom_condition = '';
        $custom_value     = '';
        if ( $content_filter === 'custom' ) {
            $custom_field     = ! empty( $_POST['custom_field'] )     ? sanitize_key( $_POST['custom_field'] )                               : '';
            $custom_condition = ! empty( $_POST['custom_condition'] ) ? sanitize_key( $_POST['custom_condition'] )                            : '';
            $custom_value     = isset( $_POST['custom_value'] )       ? sanitize_text_field( wp_unslash( $_POST['custom_value'] ) )          : '';
        }

        // Fetch all matching IDs first, then apply content filters, then paginate.
        // This ensures total count and pagination are accurate.

        // If search specified, use WP_Query; otherwise wc_get_products
        if ( ! empty( $search ) ) {
            $wp_args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                's'              => $search,
                'fields'         => 'ids',
            ];
            if ( ! empty( $_POST['category'] ) ) {
                $wp_args['tax_query'] = [ [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( wp_unslash( $_POST['category'] ) ),
                ] ];
            }
            $query = new WP_Query( $wp_args );
            $all_ids = $query->posts;
        } else {
            $all_ids = wc_get_products( $args );
        }

        // Apply content/custom filters to get the true filtered set
        $has_content_filter = ( $content_filter && $content_filter !== 'all' );
        if ( $has_content_filter ) {
            $filtered_ids = [];
            foreach ( $all_ids as $pid ) {
                $product = wc_get_product( $pid );
                if ( ! $product ) {
                    continue;
                }

                $desc       = $product->get_description();
                $short_desc = $product->get_short_description();
                $word_count = str_word_count( wp_strip_all_tags( $desc ) );

                if ( $content_filter === 'no_description' && ! empty( $desc ) ) {
                    continue;
                }
                if ( $content_filter === 'short_description' && ! empty( $short_desc ) ) {
                    continue;
                }
                if ( $content_filter === 'short_content' && $word_count >= 100 ) {
                    continue;
                }

                if ( $content_filter === 'custom' && ! empty( $custom_field ) && ! empty( $custom_condition ) ) {
                    $field_value = null;

                    switch ( $custom_field ) {
                        case 'description_words':
                            $field_value = $word_count;
                            break;
                        case 'short_description_words':
                            $field_value = str_word_count( wp_strip_all_tags( $short_desc ) );
                            break;
                        case 'price':
                            $field_value = (float) $product->get_price();
                            break;
                        case 'sku':
                            $field_value = $product->get_sku();
                            break;
                        case 'tags_count':
                            $tags = wp_get_post_terms( $pid, 'product_tag', [ 'fields' => 'ids' ] );
                            $field_value = is_wp_error( $tags ) ? 0 : count( $tags );
                            break;
                        case 'reviews_count':
                            $field_value = (int) $product->get_review_count();
                            break;
                    }

                    if ( $field_value !== null && ! $this->evaluate_custom_condition( $field_value, $custom_condition, $custom_value ) ) {
                        continue;
                    }
                }

                $filtered_ids[] = $pid;
            }
        } else {
            $filtered_ids = $all_ids;
        }

        // Total after all filters
        $total = count( $filtered_ids );

        // Paginate the filtered IDs
        $paged_ids = array_slice( $filtered_ids, ( $page - 1 ) * $per_page, $per_page );

        // Build full product data only for the current page
        $products = [];
        foreach ( $paged_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            $desc       = $product->get_description();
            $short_desc = $product->get_short_description();
            $word_count = str_word_count( wp_strip_all_tags( $desc ) );

            $categories = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
            $cat_names  = is_wp_error( $categories ) ? [] : $categories;

            $products[] = [
                'id'               => $pid,
                'name'             => $product->get_name(),
                'type'             => $product->get_type(),
                'sku'              => $product->get_sku(),
                'price'            => $product->get_price(),
                'categories'       => implode( ', ', $cat_names ),
                'desc_length'      => $word_count,
                'has_description'   => ! empty( $desc ),
                'has_short_desc'    => ! empty( $short_desc ),
                'stock_status'     => $product->get_stock_status(),
                'image'            => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
                'ai_optimized'     => (bool) get_post_meta( $pid, '_bw_woo_ai_optimized', true ),
                'review_count'     => (int) $product->get_review_count(),
                'average_rating'   => $product->get_average_rating(),
            ];
        }

        wp_send_json_success( [
            'products'   => $products,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages'=> (int) ceil( $total / $per_page ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: get all product IDs matching current filters (no pagination)
     * Used by "Select all matching products" feature.
     * ----------------------------------------------------------------*/

    public function ajax_get_all_product_ids() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $args = [
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        ];

        if ( ! empty( $_POST['category'] ) ) {
            $args['category'] = [ sanitize_text_field( wp_unslash( $_POST['category'] ) ) ];
        }
        if ( ! empty( $_POST['product_type'] ) ) {
            $args['type'] = sanitize_key( $_POST['product_type'] );
        }
        if ( ! empty( $_POST['stock_status'] ) ) {
            $args['stock_status'] = sanitize_key( $_POST['stock_status'] );
        }

        $search = '';
        if ( ! empty( $_POST['search'] ) ) {
            $search = sanitize_text_field( wp_unslash( $_POST['search'] ) );
        }

        if ( ! empty( $search ) ) {
            $wp_args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                's'              => $search,
                'fields'         => 'ids',
            ];
            if ( ! empty( $_POST['category'] ) ) {
                $wp_args['tax_query'] = [ [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( wp_unslash( $_POST['category'] ) ),
                ] ];
            }
            $query       = new \WP_Query( $wp_args );
            $product_ids = $query->posts;
        } else {
            $product_ids = wc_get_products( $args );
        }

        // Content filter (same logic as ajax_get_products)
        $content_filter = '';
        if ( ! empty( $_POST['filter_status'] ) ) {
            $content_filter = sanitize_key( $_POST['filter_status'] );
        }
        $custom_field     = '';
        $custom_condition = '';
        $custom_value     = '';
        if ( $content_filter === 'custom' ) {
            $custom_field     = ! empty( $_POST['custom_field'] )     ? sanitize_key( $_POST['custom_field'] )                      : '';
            $custom_condition = ! empty( $_POST['custom_condition'] ) ? sanitize_key( $_POST['custom_condition'] )                   : '';
            $custom_value     = isset( $_POST['custom_value'] )       ? sanitize_text_field( wp_unslash( $_POST['custom_value'] ) ) : '';
        }

        // Build minimal data: id + name for each.
        $items = [];
        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            // Apply content filter
            if ( $content_filter ) {
                $desc       = $product->get_description();
                $short_desc = $product->get_short_description();
                $word_count = str_word_count( wp_strip_all_tags( $desc ) );

                if ( $content_filter === 'no_description' && ! empty( $desc ) ) {
                    continue;
                }
                if ( $content_filter === 'short_description' && ! empty( $short_desc ) ) {
                    continue;
                }
                if ( $content_filter === 'short_content' && $word_count >= 100 ) {
                    continue;
                }
                if ( $content_filter === 'custom' && ! empty( $custom_field ) && ! empty( $custom_condition ) ) {
                    $field_value = null;
                    switch ( $custom_field ) {
                        case 'description_words':
                            $field_value = $word_count;
                            break;
                        case 'short_description_words':
                            $field_value = str_word_count( wp_strip_all_tags( $short_desc ) );
                            break;
                        case 'price':
                            $field_value = (float) $product->get_price();
                            break;
                        case 'sku':
                            $field_value = $product->get_sku();
                            break;
                        case 'tags_count':
                            $tags = wp_get_post_terms( $pid, 'product_tag', [ 'fields' => 'ids' ] );
                            $field_value = is_wp_error( $tags ) ? 0 : count( $tags );
                            break;
                        case 'reviews_count':
                            $field_value = (int) $product->get_review_count();
                            break;
                    }
                    if ( $field_value !== null && ! $this->evaluate_custom_condition( $field_value, $custom_condition, $custom_value ) ) {
                        continue;
                    }
                }
            }

            // Apply review-specific filters (used by Reviews tab)
            $rev_filter = ! empty( $_POST['rev_filter'] ) ? sanitize_key( $_POST['rev_filter'] ) : '';
            if ( $rev_filter ) {
                $rev_n       = isset( $_POST['rev_filter_n'] ) ? intval( $_POST['rev_filter_n'] ) : 5;
                $rev_count   = (int) $product->get_review_count();
                $avg_rating  = (float) $product->get_average_rating();

                if ( $rev_filter === 'no_reviews' && $rev_count > 0 ) {
                    continue;
                }
                if ( $rev_filter === 'few_reviews' && $rev_count >= $rev_n ) {
                    continue;
                }
                if ( $rev_filter === 'low_rating' && ( $rev_count === 0 || $avg_rating >= $rev_n ) ) {
                    continue;
                }
            }

            $items[] = [
                'id'   => $pid,
                'name' => $product->get_name(),
            ];
        }

        wp_send_json_success( [ 'items' => $items, 'total' => count( $items ) ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: get product categories
     * ----------------------------------------------------------------*/

    public function ajax_get_categories() {
        BW_Woo_AI::verify_request();

        $categories = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $categories ) ) {
            wp_send_json_error( $categories->get_error_message() );
        }

        // Index by term_id for hierarchy lookup.
        $by_id = [];
        foreach ( $categories as $cat ) {
            $by_id[ $cat->term_id ] = $cat;
        }

        // Calculate depth.
        $get_depth = function ( $term_id ) use ( $by_id ) {
            $depth = 0;
            $id    = $term_id;
            while ( isset( $by_id[ $id ] ) && $by_id[ $id ]->parent > 0 ) {
                $depth++;
                $id = $by_id[ $id ]->parent;
            }
            return $depth;
        };

        // Sort hierarchically: parents before children, alphabetically within level.
        $sorted       = [];
        $add_children = null;
        $add_children = function ( $parent_id ) use ( $categories, &$sorted, &$add_children ) {
            $children = [];
            foreach ( $categories as $cat ) {
                if ( (int) $cat->parent === (int) $parent_id ) {
                    $children[] = $cat;
                }
            }
            usort( $children, function ( $a, $b ) {
                return strcasecmp( $a->name, $b->name );
            } );
            foreach ( $children as $child ) {
                $sorted[] = $child;
                $add_children( $child->term_id );
            }
        };
        $add_children( 0 );

        $result = [];
        foreach ( $sorted as $cat ) {
            $result[] = [
                'id'              => $cat->term_id,
                'name'            => $cat->name,
                'slug'            => $cat->slug,
                'count'           => $cat->count,
                'parent'          => $cat->parent,
                'depth'           => $get_depth( $cat->term_id ),
                'description'     => $cat->description,
                'has_description' => ! empty( $cat->description ),
            ];
        }

        wp_send_json_success( $result );
    }

    /* ------------------------------------------------------------------
     * AJAX: get category list for filter dropdown
     * ----------------------------------------------------------------*/

    public function ajax_get_category_list() {
        BW_Woo_AI::verify_request();

        botwriter_log( 'WooAI: ajax_get_category_list called' );

        $categories = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'name',
        ] );

        if ( is_wp_error( $categories ) ) {
            wp_send_json_error( $categories->get_error_message() );
        }

        // Build hierarchical sorted list with depth.
        $by_parent = [];
        foreach ( $categories as $cat ) {
            $by_parent[ $cat->parent ][] = $cat;
        }

        $result = [];
        $this->build_category_tree( $by_parent, 0, 0, $result );

        // DEBUG: add marker to first element so it shows in Network tab
        if ( ! empty( $result ) ) {
            $result[0]['_build'] = '2026-03-13a';
        }

        wp_send_json_success( $result );
    }

    /**
     * Recursively build a flat, depth-annotated category list sorted alphabetically.
     */
    private function build_category_tree( &$by_parent, $parent_id, $depth, &$result ) {
        if ( empty( $by_parent[ $parent_id ] ) ) {
            return;
        }
        // Sort siblings alphabetically.
        usort( $by_parent[ $parent_id ], function ( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );
        foreach ( $by_parent[ $parent_id ] as $cat ) {
            $result[] = [
                'id'    => $cat->term_id,
                'slug'  => $cat->slug,
                'name'  => $cat->name,
                'count' => $cat->count,
                'depth' => $depth,
            ];
            $this->build_category_tree( $by_parent, $cat->term_id, $depth + 1, $result );
        }
    }

    /* ------------------------------------------------------------------
     * Custom filter condition evaluator
     * ----------------------------------------------------------------*/

    /**
     * Evaluate a custom filter condition.
     *
     * @param  mixed  $field_value     The value of the product field.
     * @param  string $condition        Condition operator key.
     * @param  string $compare_value    User-supplied comparison value.
     * @return bool
     */
    private function evaluate_custom_condition( $field_value, $condition, $compare_value ) {
        switch ( $condition ) {
            case 'gt':
                return (float) $field_value > (float) $compare_value;
            case 'lt':
                return (float) $field_value < (float) $compare_value;
            case 'eq':
                return (string) $field_value === (string) $compare_value;
            case 'gte':
                return (float) $field_value >= (float) $compare_value;
            case 'lte':
                return (float) $field_value <= (float) $compare_value;
            case 'is_empty':
                return empty( $field_value ) || $field_value === 0 || $field_value === '0';
            case 'is_not_empty':
                return ! empty( $field_value ) && $field_value !== 0 && $field_value !== '0';
            case 'contains':
                return stripos( (string) $field_value, $compare_value ) !== false;
            default:
                return true;
        }
    }
}
