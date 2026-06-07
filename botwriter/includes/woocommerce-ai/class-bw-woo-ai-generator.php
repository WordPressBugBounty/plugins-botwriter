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

class BotWriter_Woo_AI_Generator {

    /** Max tokens for description generation. */
    const MAX_TOKENS_DESCRIPTION = 4096;

    /** Max tokens for shorter generations (summary, tags, etc). */
    const MAX_TOKENS_SHORT = 2048;

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
        $result = $this->call_provider( $provider, $api_key, $prompt, $max_tokens, $ssl_verify, $field );

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
        $result = $this->normalize_generated_response( $result, $field );

        botwriter_log( '[Woo AI] generate_field SUCCESS', [
            'provider' => $provider,
            'field'    => $field,
            'text_len' => strlen( $result ),
            'text_preview' => mb_substr( $result, 0, 200 ),
        ] );

        return trim( $result );
    }

    /**
     * Extract usable content when a provider returns a JSON wrapper instead of raw text.
     *
     * Gemini may return objects like {"html":"..."} when JSON output is enabled.
     *
     * @param string $result Raw provider result.
     * @param string $field  Woo AI field being generated.
     * @return string
     */
    private function normalize_generated_response( $result, $field ) {
        $trimmed = trim( $result );
        if ( $trimmed === '' ) {
            return '';
        }

        $candidate = $trimmed;
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
                $extracted = $this->extract_generated_text_from_payload( $decoded, $field );
                if ( $extracted !== '' ) {
                    return $extracted;
                }
            }

            break;
        }

        return $candidate;
    }

    /**
     * Extract plain text from structured AI payloads (objects, arrays, nested wrappers).
     *
     * @param array  $payload Decoded JSON payload.
     * @param string $field   Woo AI field being generated.
     * @return string
     */
    private function extract_generated_text_from_payload( $payload, $field ) {
        $preferred_keys = [
            'description'          => [ 'html', 'description', 'content', 'text' ],
            'category_description' => [ 'html', 'description', 'content', 'text' ],
            'review_summary'       => [ 'html', 'content', 'summary', 'text' ],
            'short_description'    => [ 'short_description', 'summary', 'content', 'text', 'html' ],
            'title'                => [ 'title', 'name', 'text', 'content' ],
            'seo_title'            => [ 'seo_title', 'title', 'text', 'content' ],
            'seo_meta'             => [ 'seo_meta', 'meta_description', 'description', 'text', 'content' ],
            'alt_tags'             => [ 'alt_tags', 'alt_text', 'text', 'content' ],
            'tags'                 => [ 'tags', 'keywords', 'text', 'content' ],
        ];

        $keys = isset( $preferred_keys[ $field ] ) ? $preferred_keys[ $field ] : [ 'content', 'text', 'html' ];

        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $payload ) ) {
                continue;
            }

            $value = $this->pick_generated_text_value( $payload[ $key ], $field, $keys, 0 );
            if ( $value !== '' ) {
                return $value;
            }
        }

        if ( array_values( $payload ) === $payload ) {
            if ( $field === 'tags' ) {
                $tags = [];
                foreach ( $payload as $item ) {
                    $value = $this->pick_generated_text_value( $item, $field, $keys, 0 );
                    if ( $value !== '' ) {
                        $tags[] = $value;
                    }
                }

                $tags = array_values( array_unique( array_filter( $tags ) ) );
                if ( ! empty( $tags ) ) {
                    return implode( ', ', $tags );
                }
            }

            foreach ( $payload as $item ) {
                $value = $this->pick_generated_text_value( $item, $field, $keys, 0 );
                if ( $value !== '' ) {
                    return $value;
                }
            }
        }

        foreach ( $payload as $item ) {
            $value = $this->pick_generated_text_value( $item, $field, $keys, 0 );
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Recursively pick the best text value from a decoded payload node.
     *
     * @param mixed  $value          Candidate value.
     * @param string $field          Woo AI field being generated.
     * @param array  $preferred_keys Candidate keys for nested objects.
     * @param int    $depth          Recursion depth guard.
     * @return string
     */
    private function pick_generated_text_value( $value, $field, $preferred_keys, $depth = 0 ) {
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

        $is_list = array_values( $value ) === $value;

        if ( $field === 'tags' && $is_list ) {
            $tags = [];
            foreach ( $value as $item ) {
                if ( is_scalar( $item ) && ! is_bool( $item ) ) {
                    $tag = trim( (string) $item );
                } else {
                    $tag = $this->pick_generated_text_value( $item, $field, $preferred_keys, $depth + 1 );
                }

                if ( $tag !== '' ) {
                    $tags[] = $tag;
                }
            }

            $tags = array_values( array_unique( array_filter( $tags ) ) );
            return ! empty( $tags ) ? implode( ', ', $tags ) : '';
        }

        foreach ( $preferred_keys as $key ) {
            if ( ! array_key_exists( $key, $value ) ) {
                continue;
            }

            $candidate = $this->pick_generated_text_value( $value[ $key ], $field, $preferred_keys, $depth + 1 );
            if ( $candidate !== '' ) {
                return $candidate;
            }
        }

        foreach ( $value as $item ) {
            $candidate = $this->pick_generated_text_value( $item, $field, $preferred_keys, $depth + 1 );
            if ( $candidate !== '' ) {
                return $candidate;
            }
        }

        return '';
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
    * @param  string $field       Optional Woo field identifier for analytics/logging.
     * @return string|WP_Error     Generated text on success, WP_Error on failure.
     */
    public function call_provider( $provider, $api_key, $prompt, $max_tokens, $ssl_verify, $field = '' ) {
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

        if ( ! empty( $field ) ) {
            $payload['field'] = $field;
        }

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

        if ( is_array( $data ) && ! empty( $data['site_token'] ) ) {
            update_option( 'botwriter_site_token', sanitize_text_field( (string) $data['site_token'] ) );
        }

        botwriter_log( '[Woo AI] Worker response', [
            'http_code'    => $http_code,
            'status'       => $data['status'] ?? 'unknown',
            'body_preview' => mb_substr( $body, 0, 1000 ),
        ] );

        if ( $http_code !== 200 || ( isset( $data['status'] ) && $data['status'] === 'error' ) ) {
            $msg = $data['error'] ?? "HTTP {$http_code}";

            $token_issue = in_array( $msg, array( 'invalid_site_token', 'token_required' ), true )
                || stripos( (string) $msg, 'site token mismatch' ) !== false
                || stripos( (string) $msg, 'token_witryny' ) !== false;

            // Self-heal stale token after plugin reinstall/domain re-pair: clear local token and retry once.
            if ( $token_issue && ! empty( $payload['site_token'] ) ) {
                delete_option( 'botwriter_site_token' );

                $retry_payload = $payload;
                $retry_payload['site_token'] = '';

                botwriter_log( '[Woo AI] Site token mismatch detected. Retrying request with empty site_token.', [
                    'provider' => $provider,
                    'error'    => $msg,
                ] );

                $retry_response = wp_remote_post( BOTWRITER_API_URL . 'woo', array(
                    'timeout'   => $timeout,
                    'sslverify' => $ssl_verify,
                    'headers'   => array( 'Content-Type' => 'application/json' ),
                    'body'      => wp_json_encode( $retry_payload ),
                ) );

                if ( is_wp_error( $retry_response ) ) {
                    return $retry_response;
                }

                $retry_http_code = wp_remote_retrieve_response_code( $retry_response );
                $retry_body      = wp_remote_retrieve_body( $retry_response );
                $retry_data      = json_decode( $retry_body, true );

                if ( is_array( $retry_data ) && ! empty( $retry_data['site_token'] ) ) {
                    update_option( 'botwriter_site_token', sanitize_text_field( (string) $retry_data['site_token'] ) );
                }

                botwriter_log( '[Woo AI] Worker retry response', [
                    'http_code'    => $retry_http_code,
                    'status'       => $retry_data['status'] ?? 'unknown',
                    'body_preview' => mb_substr( $retry_body, 0, 1000 ),
                ] );

                if ( $retry_http_code !== 200 || ( isset( $retry_data['status'] ) && $retry_data['status'] === 'error' ) ) {
                    $retry_msg = $retry_data['error'] ?? "HTTP {$retry_http_code}";
                    return new WP_Error( 'api_error', $retry_msg );
                }

                $retry_content = $retry_data['content'] ?? '';
                if ( empty( $retry_content ) ) {
                    return new WP_Error( 'empty_response', 'AI returned an empty response.' );
                }

                if ( ! empty( $retry_data['warning'] ) && function_exists( 'botwriter_announcements_add' ) ) {
                    botwriter_announcements_add(
                        __( 'Service notice', 'botwriter' ),
                        $retry_data['warning']
                    );
                }

                return $retry_content;
            }

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
            'google'     => array( 'botwriter_google_model', 'gemini-2.5-flash' ),
            'mistral'    => array( 'botwriter_mistral_model', 'mistral-small-latest' ),
            'groq'       => array( 'botwriter_groq_model', 'llama-3.3-70b-versatile' ),
            'openrouter' => array( 'botwriter_openrouter_model', 'google/gemini-2.5-flash' ),
        );

        if ( isset( $model_options[ $provider ] ) ) {
            $option_name = $model_options[ $provider ][0];
            $default_model = $model_options[ $provider ][1];
            $configured_model = (string) get_option( $option_name, $default_model );
            $normalized_model = $this->normalize_provider_model( $provider, $configured_model );

            if ( $normalized_model !== $configured_model ) {
                botwriter_log( '[Woo AI] Normalized deprecated provider model', array(
                    'provider'   => $provider,
                    'from_model' => $configured_model,
                    'to_model'   => $normalized_model,
                ) );
            }

            return $normalized_model;
        }

        return null;
    }

    /**
     * Normalize deprecated provider model aliases to currently supported models.
     *
     * @param string $provider Provider key.
     * @param string $model    Configured model.
     * @return string
     */
    private function normalize_provider_model( $provider, $model ) {
        $provider = strtolower( (string) $provider );
        $model = trim( (string) $model );

        if ( $model === '' ) {
            return $model;
        }

        $deprecated_map = array(
            'google' => array(
                'gemini-2.0-flash'        => 'gemini-2.5-flash',
                'gemini-2.0-flash-lite'   => 'gemini-2.5-flash-lite',
                'gemini-1.5-flash'        => 'gemini-2.5-flash',
                'gemini-1.5-pro'          => 'gemini-2.5-pro',
                'models/gemini-2.0-flash' => 'gemini-2.5-flash',
                'models/gemini-1.5-flash' => 'gemini-2.5-flash',
                'models/gemini-1.5-pro'   => 'gemini-2.5-pro',
            ),
            'openrouter' => array(
                'google/gemini-2.0-flash'          => 'google/gemini-2.5-flash',
                'google/gemini-2.0-flash-001'      => 'google/gemini-2.5-flash',
                'google/gemini-2.0-flash-exp:free' => 'google/gemini-2.5-flash',
            ),
        );

        if ( ! isset( $deprecated_map[ $provider ] ) ) {
            return $model;
        }

        $key = strtolower( $model );
        return isset( $deprecated_map[ $provider ][ $key ] )
            ? $deprecated_map[ $provider ][ $key ]
            : $model;
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
        BotWriter_Woo_AI::verify_request();
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
        BotWriter_Woo_AI::verify_request();
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
        $result     = $this->call_provider( $provider, $api_key, $prompt, self::MAX_TOKENS_DESCRIPTION, $ssl_verify, 'category_description' );

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
        $result = $this->normalize_generated_response( $result, 'category_description' );

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
        BotWriter_Woo_AI::verify_request();
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
