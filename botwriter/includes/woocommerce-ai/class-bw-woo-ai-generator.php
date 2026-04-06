<?php
/**
 * BotWriter WooCommerce AI - Generator
 *
 * Handles AI content generation for WooCommerce products.
 * Routes requests through the Cloudflare Worker /woo endpoint.
 *
 * @package BotWriter
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BW_Woo_AI_Generator {

    /** Max tokens for description generation. */
    const MAX_TOKENS_DESCRIPTION = 2048;

    /** Max tokens for shorter generations (summary, tags, etc). */
    const MAX_TOKENS_SHORT = 512;

    /** Temperature for content generation. */
    const TEMPERATURE = 0.7;

    /** Supported template fields. */
    const TEMPLATE_FIELDS = [ 'title', 'description', 'short_description', 'tags', 'review_summary', 'alt_tags', 'seo_meta', 'seo_title', 'category_description' ];

    /* ------------------------------------------------------------------
     * Default prompt templates (used when user has not customized)
     * ----------------------------------------------------------------*/

    /**
     * Return the default (built-in) prompt template for a field.
     *
     * @param  string $field  One of the TEMPLATE_FIELDS keys.
     * @return string         The raw template with {{placeholders}}.
     */
    public static function get_default_template( $field ) {
        $templates = [
            'title' =>
"You are an expert WooCommerce SEO copywriter.

Rewrite and optimize the following product title for SEO and conversions.

Rules:
- Maximum 70 characters
- Include the main keyword naturally
- Make it compelling and click-worthy
- Keep the product name recognizable
- {{language_instruction}}

Current product name: {{product_name}}
{{#if categories}}Categories: {{categories}}
{{/if}}{{#if description_excerpt}}Description excerpt: {{description_excerpt}}
{{/if}}
Respond ONLY with the new product title. No quotes, no extra text.",

            'description' =>
"You are an expert WooCommerce SEO copywriter.

Rewrite and optimize the following product description for SEO.

Rules:
- Include clear benefits and features
- Use bullet points for key features
- Add a short FAQ section (2-3 questions)
- Use proper HTML tags: <h2>, <p>, <ul>, <li>, <strong>
- Length: 800-1200 words
- {{language_instruction}}
- Do NOT invent technical specifications that are not in the original

Product name: {{product_name}}
{{#if categories}}Categories: {{categories}}
{{/if}}{{#if price}}Price: {{price}}
{{/if}}{{#if sku}}SKU: {{sku}}
{{/if}}{{#if attributes}}Attributes: {{attributes}}
{{/if}}
Current description:
{{description}}

Respond ONLY with the HTML content. No markdown code blocks, no extra text.",

            'short_description' =>
"You are an expert WooCommerce copywriter.

Create a concise, compelling product summary (80-120 words).

Rules:
- Highlight the main benefit and key features
- Use clear, persuasive language
- {{language_instruction}}

Product name: {{product_name}}
{{#if description_excerpt}}Full description: {{description_excerpt}}
{{/if}}
Respond ONLY with the summary text in plain text (no HTML tags). No extra explanation.",

            'tags' =>
"You are an SEO specialist.

Generate exactly 10 SEO-optimized WooCommerce product tags.

Rules:
- Tags must be relevant for search and discovery
- Mix broad and specific tags
- {{language_instruction}}

Product name: {{product_name}}
{{#if categories}}Categories: {{categories}}
{{/if}}{{#if description_excerpt}}Description excerpt: {{description_excerpt}}
{{/if}}
Respond ONLY with a comma-separated list of tags. No numbering, no extra text.",

            'review_summary' =>
"You are a product review analyst.

Summarize the following customer reviews.

Include:
- Overall sentiment (positive/mixed/negative)
- Key pros (bullet points)
- Key cons (bullet points)
- Brief recommendation
- {{language_instruction}}

Product: {{product_name}}
Reviews:
{{reviews}}

Respond ONLY with the summary in HTML (<h3>, <p>, <ul>, <li>). No markdown.",

            'alt_tags' =>
"You are an SEO and accessibility specialist.

Generate descriptive, SEO-friendly alt text for a product image.

Rules:
- Between 5-15 words
- Descriptive and specific to the product
- Include the product name naturally
- {{language_instruction}}

Product name: {{product_name}}
{{#if categories}}Category: {{categories}}
{{/if}}
Respond ONLY with the alt text. No quotes, no explanation.",

            'seo_meta' =>
"You are an expert SEO copywriter.

Write a compelling meta description for this WooCommerce product page.

Rules:
- Exactly ONE sentence, maximum 155 characters
- Must entice clicks from search results
- Include the product name and a key benefit
- {{language_instruction}}

Product: {{product_name}}
{{#if description_excerpt}}Description excerpt: {{description_excerpt}}
{{/if}}
Respond ONLY with the meta description text. No quotes.",

            'seo_title' =>
"You are an expert SEO copywriter.

Write an optimized SEO title (title tag) for this WooCommerce product page.

Rules:
- Maximum 60 characters
- Include the main keyword at the beginning
- Make it compelling for search results
- Include a benefit or modifier (e.g. 'Buy', 'Best', 'Premium')
- {{language_instruction}}

Product: {{product_name}}
{{#if categories}}Categories: {{categories}}
{{/if}}{{#if description_excerpt}}Description excerpt: {{description_excerpt}}
{{/if}}
Respond ONLY with the SEO title text. No quotes, no extra text.",

            'category_description' =>
"You are an expert WooCommerce SEO copywriter.

Write an engaging, SEO-optimized description for a WooCommerce product category page.

Rules:
- 150-300 words
- Highlight what types of products the category contains
- Include relevant keywords naturally
- Use proper HTML: <p>, <strong>, <ul>, <li>
- Compelling, informative, and sales-oriented
- {{language_instruction}}

Category name: {{category_name}}
{{#if parent_category}}Parent category: {{parent_category}}
{{/if}}{{#if product_titles}}Products in this category: {{product_titles}}
{{/if}}{{#if current_description}}Current description: {{current_description}}
{{/if}}
Respond ONLY with the HTML content. No markdown code blocks, no extra text.",
        ];

        return isset( $templates[ $field ] ) ? $templates[ $field ] : '';
    }

    /* ------------------------------------------------------------------
     * Template rendering engine
     * ----------------------------------------------------------------*/

    /**
     * Build the final prompt for a field using the template system.
     *
     * 1. Load user template from DB (or fall back to default).
     * 2. Replace {{placeholders}} with product data.
     * 3. Process {{#if field}}…{{/if}} conditionals.
     *
     * @param  string $field        Field key.
     * @param  array  $product_data Product context from get_product_data().
     * @param  string $language     Language code or 'auto'.
     * @return string               The fully-rendered prompt.
     */
    private function render_template( $field, $product_data, $language ) {
        // 1. Load template.
        $saved     = get_option( 'bw_woo_ai_templates', [] );
        $template  = ! empty( $saved[ $field ] ) ? $saved[ $field ] : self::get_default_template( $field );

        if ( empty( $template ) ) {
            return '';
        }

        // 2. Build language instruction.
        $lang = $language === 'auto'
            ? 'Language: same as the original content.'
            : "Language: {$language}.";

        // 3. Build description excerpt (200 words stripped).
        $desc_excerpt = '';
        if ( ! empty( $product_data['description'] ) ) {
            $desc_excerpt = wp_trim_words( wp_strip_all_tags( $product_data['description'] ), 200 );
        }

        // 4. Placeholder map.
        $replacements = [
            '{{product_name}}'         => $product_data['name'] ?? '',
            '{{description}}'          => $product_data['description'] ?? '',
            '{{short_description}}'    => $product_data['short_description'] ?? '',
            '{{description_excerpt}}'  => $desc_excerpt,
            '{{categories}}'           => $product_data['categories'] ?? '',
            '{{price}}'                => $product_data['price'] ?? '',
            '{{sku}}'                  => $product_data['sku'] ?? '',
            '{{attributes}}'           => $product_data['attributes'] ?? '',
            '{{tags}}'                 => $product_data['tags'] ?? '',
            '{{reviews}}'              => $product_data['reviews'] ?? '',
            '{{language_instruction}}' => $lang,
        ];

        $prompt = str_replace(
            array_keys( $replacements ),
            array_values( $replacements ),
            $template
        );

        // 5. Process conditionals: {{#if key}}content{{/if}}
        $prompt = preg_replace_callback(
            '/\{\{#if (\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function ( $m ) use ( $product_data, $replacements ) {
                $key = $m[1];
                // Check product_data first, then replacements map.
                $val = isset( $product_data[ $key ] ) ? $product_data[ $key ] : '';
                return ! empty( $val ) ? $m[2] : '';
            },
            $prompt
        );

        // 6. Clean up multiple blank lines left by removed conditionals.
        $prompt = preg_replace( '/\n{3,}/', "\n\n", $prompt );

        return trim( $prompt );
    }

    /* ------------------------------------------------------------------
     * Core generation – call the AI provider
     * ----------------------------------------------------------------*/

    /**
     * Generate content for a single product field.
     *
     * @param  array  $product_data Product context data.
     * @param  string $field        Field to generate (description, short_description, tags, …).
     * @param  string $provider     AI provider key.
     * @param  string $language     Target language code or 'auto'.
     * @return string|WP_Error      Generated text or error.
     */
    public function generate_field( $product_data, $field, $provider, $language = 'auto' ) {
        // Validate field.
        if ( ! in_array( $field, self::TEMPLATE_FIELDS, true ) ) {
            return new WP_Error( 'invalid_field', "Unknown field: {$field}" );
        }

        // Build prompt from template.
        $prompt = $this->render_template( $field, $product_data, $language );
        if ( empty( $prompt ) ) {
            return new WP_Error( 'empty_template', "No template found for field: {$field}" );
        }

        // Determine max tokens by field.
        $short_fields = [ 'title', 'short_description', 'tags', 'alt_tags', 'seo_meta', 'seo_title' ];
        $max_tokens   = in_array( $field, $short_fields, true ) ? self::MAX_TOKENS_SHORT : self::MAX_TOKENS_DESCRIPTION;

        // Get API key.
        $api_key = botwriter_get_provider_api_key( $provider );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', "No API key configured for provider: {$provider}" );
        }

        $ssl_verify = get_option( 'botwriter_sslverify', 'yes' ) === 'yes';

        // Use the existing BotWriter translate_api_call helper which supports all providers.
        $result = $this->call_provider( $provider, $api_key, $prompt, $max_tokens, $ssl_verify );

        if ( is_wp_error( $result ) ) {
            botwriter_log( '[Woo AI] generate_field error', [
                'provider' => $provider,
                'field'    => $field,
                'error'    => $result->get_error_message(),
            ] );
            return $result;
        }

        // Strip markdown code fences if the model wrapped output.
        $result = preg_replace( '/^```(?:html|json)?\s*/i', '', trim( $result ) );
        $result = preg_replace( '/\s*```$/', '', $result );

        botwriter_log( '[Woo AI] generate_field SUCCESS', [
            'provider' => $provider,
            'field'    => $field,
            'text_len' => strlen( $result ),
            'text_preview' => mb_substr( $result, 0, 200 ),
        ] );

        return trim( $result );
    }

    /* ------------------------------------------------------------------
     * Provider API call — routed through the Cloudflare Worker proxy
     * ----------------------------------------------------------------*/

    /**
     * Call the AI provider via the Cloudflare Worker /woo endpoint.
     *
     * The Worker handles model-specific quirks (max_tokens vs max_completion_tokens,
     * Responses API for newer OpenAI models, etc.) and normalises the response.
     *
     * @param  string $provider    AI provider key (openai, anthropic, google, …).
     * @param  string $api_key     User's decrypted API key for the provider.
     * @param  string $prompt      The rendered prompt.
     * @param  int    $max_tokens  Max output tokens.
     * @param  bool   $ssl_verify  Whether to verify SSL.
     * @return string|WP_Error     Generated text on success, WP_Error on failure.
     */
    public function call_provider( $provider, $api_key, $prompt, $max_tokens, $ssl_verify ) {
        $timeout     = 120;
        $temperature = self::TEMPERATURE;

        botwriter_log( '[Woo AI] call_provider REQUEST (via Worker)', [
            'provider'   => $provider,
            'max_tokens' => $max_tokens,
            'prompt_len' => strlen( $prompt ),
            'prompt_preview' => mb_substr( $prompt, 0, 300 ),
        ] );

        // Map provider names: PHP uses 'google', Worker uses 'gemini'
        $provider_map = array( 'google' => 'gemini' );
        $worker_provider = isset( $provider_map[ $provider ] ) ? $provider_map[ $provider ] : $provider;

        // Get the model from settings
        $model = $this->get_provider_model( $provider );

        // Map provider to the client_keys field name the Worker expects
        $key_field_map = array(
            'openai'     => 'openai_api_key',
            'anthropic'  => 'anthropic_api_key',
            'google'     => 'google_api_key',
            'mistral'    => 'mistral_api_key',
            'groq'       => 'groq_api_key',
            'openrouter' => 'openrouter_api_key',
        );

        // Build domain (strip protocol and trailing slash)
        $domain = preg_replace( '#^https?://#', '', home_url() );
        $domain = rtrim( $domain, '/' );

        $payload = array(
            'prompt'          => $prompt,
            'domain'          => $domain,
            'provider'        => $worker_provider,
            'model'           => $model,
            'max_tokens'      => $max_tokens,
            'temperature'     => $temperature,
            'site_token'      => get_option( 'botwriter_site_token', '' ),
        );

        // Forward the user's API key so the Worker can use it
        if ( ! empty( $api_key ) && isset( $key_field_map[ $provider ] ) ) {
            $payload[ $key_field_map[ $provider ] ] = $api_key;
        }

        $response = wp_remote_post( BOTWRITER_API_URL . 'woo', array(
            'timeout'   => $timeout,
            'sslverify' => $ssl_verify,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'body'      => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        botwriter_log( '[Woo AI] Worker response', [
            'http_code'    => $http_code,
            'status'       => $data['status'] ?? 'unknown',
            'body_preview' => mb_substr( $body, 0, 1000 ),
        ] );

        if ( $http_code !== 200 || ( isset( $data['status'] ) && $data['status'] === 'error' ) ) {
            $msg = $data['error'] ?? "HTTP {$http_code}";
            return new WP_Error( 'api_error', $msg );
        }

        $content = $data['content'] ?? '';
        if ( empty( $content ) ) {
            return new WP_Error( 'empty_response', 'AI returned an empty response.' );
        }

        // Capture server warning (e.g. upcoming plan changes) as admin notice
        if ( ! empty( $data['warning'] ) && function_exists( 'botwriter_announcements_add' ) ) {
            botwriter_announcements_add(
                __( 'Service notice', 'botwriter' ),
                $data['warning']
            );
        }

        return $content;
    }

    /**
     * Get the configured model for a provider from WordPress options.
     *
     * @param  string $provider Provider key.
     * @return string|null      Model name or null.
     */
    private function get_provider_model( $provider ) {
        $model_options = array(
            'openai'     => array( 'botwriter_openai_model', 'gpt-4o-mini' ),
            'anthropic'  => array( 'botwriter_anthropic_model', 'claude-haiku-4-20250414' ),
            'google'     => array( 'botwriter_google_model', 'gemini-2.0-flash' ),
            'mistral'    => array( 'botwriter_mistral_model', 'mistral-small-latest' ),
            'groq'       => array( 'botwriter_groq_model', 'llama-3.3-70b-versatile' ),
            'openrouter' => array( 'botwriter_openrouter_model', 'google/gemini-2.0-flash-001' ),
        );

        if ( isset( $model_options[ $provider ] ) ) {
            return get_option( $model_options[ $provider ][0], $model_options[ $provider ][1] );
        }

        return null;
    }

    /* ------------------------------------------------------------------
     * Collect product data for prompt building
     * ----------------------------------------------------------------*/

    /**
     * Gather all relevant product data for prompt context.
     */
    public function get_product_data( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return null;
        }

        $categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
        $cat_names  = is_wp_error( $categories ) ? '' : implode( ', ', $categories );

        $tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
        $tag_names = is_wp_error( $tags ) ? '' : implode( ', ', $tags );

        // Gather attributes.
        $attributes_text = '';
        $attributes = $product->get_attributes();
        if ( ! empty( $attributes ) ) {
            $parts = [];
            foreach ( $attributes as $attr ) {
                if ( is_a( $attr, 'WC_Product_Attribute' ) ) {
                    $name   = wc_attribute_label( $attr->get_name() );
                    $values = $attr->is_taxonomy()
                        ? implode( ', ', wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'names' ] ) )
                        : implode( ', ', $attr->get_options() );
                    $parts[] = "{$name}: {$values}";
                }
            }
            $attributes_text = implode( '; ', $parts );
        }

        // Gather reviews.
        $reviews_text = '';
        $comments = get_comments( [
            'post_id' => $product_id,
            'status'  => 'approve',
            'number'  => 20,
            'type'    => 'review',
        ] );
        if ( ! empty( $comments ) ) {
            $review_parts = [];
            foreach ( $comments as $c ) {
                $rating = get_comment_meta( $c->comment_ID, 'rating', true );
                $r = $rating ? "({$rating}/5) " : '';
                $review_parts[] = $r . wp_strip_all_tags( $c->comment_content );
            }
            $reviews_text = implode( "\n---\n", $review_parts );
        }

        return [
            'id'                => $product_id,
            'name'              => $product->get_name(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price'             => $product->get_price(),
            'sku'               => $product->get_sku(),
            'categories'        => $cat_names,
            'tags'              => $tag_names,
            'attributes'        => $attributes_text,
            'reviews'           => $reviews_text,
            'image_id'          => $product->get_image_id(),
            'gallery_ids'       => $product->get_gallery_image_ids(),
            'type'              => $product->get_type(),
        ];
    }

    /* ------------------------------------------------------------------
     * AJAX: generate content for a batch of products
     * ----------------------------------------------------------------*/

    public function ajax_generate() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) $_POST['product_ids'] ) : [];
        $fields      = isset( $_POST['fields'] ) ? array_map( 'sanitize_key', (array) $_POST['fields'] ) : [];
        $provider    = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : get_option( 'botwriter_text_provider', 'openai' );
        $language    = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'auto';

        if ( empty( $product_ids ) || empty( $fields ) ) {
            wp_send_json_error( 'Missing product IDs or fields.' );
        }

        $results = [];

        foreach ( $product_ids as $pid ) {
            $product_data = $this->get_product_data( $pid );
            if ( ! $product_data ) {
                $results[ $pid ] = [ 'error' => 'Product not found.' ];
                continue;
            }

            $generated = [];
            foreach ( $fields as $field ) {
                // Skip review_summary if no reviews.
                if ( $field === 'review_summary' && empty( $product_data['reviews'] ) ) {
                    $generated[ $field ] = [
                        'status' => 'skipped',
                        'reason' => __( 'No reviews found.', 'botwriter' ),
                    ];
                    continue;
                }

                $result = $this->generate_field( $product_data, $field, $provider, $language );

                if ( is_wp_error( $result ) ) {
                    $generated[ $field ] = [
                        'status' => 'error',
                        'error'  => $result->get_error_message(),
                    ];
                    botwriter_log( 'WooCommerce AI generation error', [
                        'product_id' => $pid,
                        'field'      => $field,
                        'provider'   => $provider,
                        'error'      => $result->get_error_message(),
                    ] );
                } else {
                    $generated[ $field ] = [
                        'status'  => 'success',
                        'content' => $result,
                    ];
                }
            }

            $current = [
                'title'             => $product_data['name'],
                'description'       => $product_data['description'],
                'short_description' => $product_data['short_description'],
                'tags'              => $product_data['tags'],
            ];

            $results[ $pid ] = [
                'product_name' => $product_data['name'],
                'current'      => $current,
                'generated'    => $generated,
            ];
        }

        wp_send_json_success( $results );
    }

    /* ------------------------------------------------------------------
     * AJAX: generate for a single product (all requested fields)
     * ----------------------------------------------------------------*/

    public function ajax_generate_single() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $fields     = isset( $_POST['fields'] ) ? array_map( 'sanitize_key', (array) $_POST['fields'] ) : [];
        $provider   = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : get_option( 'botwriter_text_provider', 'openai' );
        $language   = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'auto';

        // Backwards compat: if a single 'field' param is sent instead of 'fields[]'
        if ( empty( $fields ) && ! empty( $_POST['field'] ) ) {
            $fields = [ sanitize_key( $_POST['field'] ) ];
        }

        if ( ! $product_id || empty( $fields ) ) {
            wp_send_json_error( 'Missing product ID or fields.' );
        }

        $product_data = $this->get_product_data( $product_id );
        if ( ! $product_data ) {
            wp_send_json_error( 'Product not found.' );
        }

        $generated = [];
        foreach ( $fields as $field ) {
            if ( $field === 'review_summary' && empty( $product_data['reviews'] ) ) {
                $generated[ $field ] = [
                    'status' => 'skipped',
                    'reason' => __( 'No reviews found.', 'botwriter' ),
                ];
                continue;
            }

            $result = $this->generate_field( $product_data, $field, $provider, $language );

            if ( is_wp_error( $result ) ) {
                $generated[ $field ] = [
                    'status' => 'error',
                    'error'  => $result->get_error_message(),
                ];
                botwriter_log( 'WooCommerce AI generation error', [
                    'product_id' => $product_id,
                    'field'      => $field,
                    'provider'   => $provider,
                    'error'      => $result->get_error_message(),
                ] );
            } else {
                $generated[ $field ] = [
                    'status'  => 'success',
                    'content' => $result,
                ];
            }
        }

        $current = [
            'title'             => $product_data['name'],
            'description'       => $product_data['description'],
            'short_description' => $product_data['short_description'],
            'tags'              => $product_data['tags'],
        ];

        wp_send_json_success( [
            'product_id'   => $product_id,
            'product_name' => $product_data['name'],
            'current'      => $current,
            'generated'    => $generated,
        ] );
    }

    /* ------------------------------------------------------------------
     * Category data gathering
     * ----------------------------------------------------------------*/

    /**
     * Gather category data for prompt context.
     *
     * @param  int       $term_id  Category term ID.
     * @return array|null
     */
    public function get_category_data( $term_id ) {
        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        // Parent category name.
        $parent_name = '';
        if ( $term->parent > 0 ) {
            $parent = get_term( $term->parent, 'product_cat' );
            if ( $parent && ! is_wp_error( $parent ) ) {
                $parent_name = $parent->name;
            }
        }

        // Product titles in this category (up to 20).
        $product_ids = wc_get_products( [
            'category' => [ $term->slug ],
            'status'   => 'publish',
            'limit'    => 20,
            'return'   => 'ids',
        ] );

        $titles = [];
        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( $product ) {
                $titles[] = $product->get_name();
            }
        }

        return [
            'category_name'       => $term->name,
            'current_description' => $term->description,
            'parent_category'     => $parent_name,
            'product_titles'      => implode( ', ', $titles ),
            'product_count'       => $term->count,
        ];
    }

    /* ------------------------------------------------------------------
     * Category template rendering
     * ----------------------------------------------------------------*/

    private function render_category_template( $category_data, $language ) {
        $saved    = get_option( 'bw_woo_ai_templates', [] );
        $template = ! empty( $saved['category_description'] )
            ? $saved['category_description']
            : self::get_default_template( 'category_description' );

        if ( empty( $template ) ) {
            return '';
        }

        $lang = $language === 'auto'
            ? 'Language: same as the category name.'
            : "Language: {$language}.";

        $replacements = [
            '{{category_name}}'        => $category_data['category_name'] ?? '',
            '{{current_description}}'  => $category_data['current_description'] ?? '',
            '{{parent_category}}'      => $category_data['parent_category'] ?? '',
            '{{product_titles}}'       => $category_data['product_titles'] ?? '',
            '{{product_count}}'        => $category_data['product_count'] ?? '',
            '{{language_instruction}}' => $lang,
        ];

        $prompt = str_replace(
            array_keys( $replacements ),
            array_values( $replacements ),
            $template
        );

        // Process conditionals.
        $prompt = preg_replace_callback(
            '/\{\{#if (\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function ( $m ) use ( $category_data ) {
                $key = $m[1];
                $val = isset( $category_data[ $key ] ) ? $category_data[ $key ] : '';
                return ! empty( $val ) ? $m[2] : '';
            },
            $prompt
        );

        $prompt = preg_replace( '/\n{3,}/', "\n\n", $prompt );

        return trim( $prompt );
    }

    /* ------------------------------------------------------------------
     * Generate category description
     * ----------------------------------------------------------------*/

    public function generate_category_field( $category_data, $provider, $language = 'auto' ) {
        $prompt = $this->render_category_template( $category_data, $language );
        if ( empty( $prompt ) ) {
            return new WP_Error( 'empty_template', 'No template found for category_description.' );
        }

        $api_key = botwriter_get_provider_api_key( $provider );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', "No API key configured for provider: {$provider}" );
        }

        $ssl_verify = get_option( 'botwriter_sslverify', 'yes' ) === 'yes';
        $result     = $this->call_provider( $provider, $api_key, $prompt, self::MAX_TOKENS_DESCRIPTION, $ssl_verify );

        if ( is_wp_error( $result ) ) {
            botwriter_log( '[Woo AI] generate_category error', [
                'provider' => $provider,
                'category' => $category_data['category_name'] ?? '',
                'error'    => $result->get_error_message(),
            ] );
            return $result;
        }

        $result = preg_replace( '/^```(?:html|json)?\s*/i', '', trim( $result ) );
        $result = preg_replace( '/\s*```$/', '', $result );

        botwriter_log( '[Woo AI] generate_category SUCCESS', [
            'provider' => $provider,
            'category' => $category_data['category_name'] ?? '',
            'text_len' => strlen( $result ),
        ] );

        return trim( $result );
    }

    /* ------------------------------------------------------------------
     * AJAX: generate category description
     * ----------------------------------------------------------------*/

    public function ajax_generate_category() {
        BW_Woo_AI::verify_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $provider    = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : get_option( 'botwriter_text_provider', 'openai' );
        $language    = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'auto';

        if ( ! $category_id ) {
            wp_send_json_error( 'Missing category ID.' );
        }

        $category_data = $this->get_category_data( $category_id );
        if ( ! $category_data ) {
            wp_send_json_error( 'Category not found.' );
        }

        $result = $this->generate_category_field( $category_data, $provider, $language );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( [
            'category_id'   => $category_id,
            'category_name' => $category_data['category_name'],
            'current'       => $category_data['current_description'],
            'generated'     => $result,
        ] );
    }
}
