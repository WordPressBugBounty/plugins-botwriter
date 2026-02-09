<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// varibales globales de opciones
$botwriter_countries = [
    'af' => 'Afghanistan',
    'al' => 'Albania',
    'dz' => 'Algeria',
    'as' => 'American Samoa',
    'ad' => 'Andorra',
    'ao' => 'Angola',
    'ai' => 'Anguilla',
    'aq' => 'Antarctica',
    'ag' => 'Antigua and Barbuda',
    'ar' => 'Argentina',
    'am' => 'Armenia',
    'aw' => 'Aruba',
    'au' => 'Australia',
    'at' => 'Austria',
    'az' => 'Azerbaijan',
    'bs' => 'Bahamas',
    'bh' => 'Bahrain',
    'bd' => 'Bangladesh',
    'bb' => 'Barbados',
    'by' => 'Belarus',
    'be' => 'Belgium',
    'bz' => 'Belize',
    'bj' => 'Benin',
    'bm' => 'Bermuda',
    'bt' => 'Bhutan',
    'bo' => 'Bolivia',
    'ba' => 'Bosnia and Herzegovina',
    'bw' => 'Botswana',
    'bv' => 'Bouvet Island',
    'br' => 'Brazil',
    'io' => 'British Indian Ocean Territory',
    'bn' => 'Brunei Darussalam',
    'bg' => 'Bulgaria',
    'bf' => 'Burkina Faso',
    'bi' => 'Burundi',
    'kh' => 'Cambodia',
    'cm' => 'Cameroon',
    'ca' => 'Canada',
    'cv' => 'Cape Verde',
    'ky' => 'Cayman Islands',
    'cf' => 'Central African Republic',
    'td' => 'Chad',
    'cl' => 'Chile',
    'cn' => 'China',
    'cx' => 'Christmas Island',
    'cc' => 'Cocos (Keeling) Islands',
    'co' => 'Colombia',
    'km' => 'Comoros',
    'cg' => 'Congo',
    'cd' => 'Congo, the Democratic Republic of the',
    'ck' => 'Cook Islands',
    'cr' => 'Costa Rica',
    'ci' => 'Cote Divoire',
    'hr' => 'Croatia',
    'cu' => 'Cuba',
    'cy' => 'Cyprus',
    'cz' => 'Czech Republic',
    'dk' => 'Denmark',
    'dj' => 'Djibouti',
    'dm' => 'Dominica',
    'do' => 'Dominican Republic',
    'ec' => 'Ecuador',
    'eg' => 'Egypt',
    'sv' => 'El Salvador',
    'gq' => 'Equatorial Guinea',
    'er' => 'Eritrea',
    'ee' => 'Estonia',
    'et' => 'Ethiopia',
    'fk' => 'Falkland Islands (Malvinas)',
    'fo' => 'Faroe Islands',
    'fj' => 'Fiji',
    'fi' => 'Finland',
    'fr' => 'France',
    'gf' => 'French Guiana',
    'pf' => 'French Polynesia',
    'tf' => 'French Southern Territories',
    'ga' => 'Gabon',
    'gm' => 'Gambia',
    'ge' => 'Georgia',
    'de' => 'Germany',
    'gh' => 'Ghana',
    'gi' => 'Gibraltar',
    'gr' => 'Greece',
    'gl' => 'Greenland',
    'gd' => 'Grenada',
    'gp' => 'Guadeloupe',
    'gu' => 'Guam',
    'gt' => 'Guatemala',
    'gn' => 'Guinea',
    'gw' => 'Guinea-Bissau',
    'gy' => 'Guyana',
    'ht' => 'Haiti',
    'hm' => 'Heard Island and Mcdonald Islands',
    'va' => 'Holy See (Vatican City State)',
    'hn' => 'Honduras',
    'hk' => 'Hong Kong',
    'hu' => 'Hungary',
    'is' => 'Iceland',
    'in' => 'India',
    'id' => 'Indonesia',
    'ir' => 'Iran, Islamic Republic of',
    'iq' => 'Iraq',
    'ie' => 'Ireland',
    'il' => 'Israel',
    'it' => 'Italy',
    'jm' => 'Jamaica',
    'jp' => 'Japan',
    'jo' => 'Jordan',
    'kz' => 'Kazakhstan',
    'ke' => 'Kenya',
    'ki' => 'Kiribati',
    'kp' => 'North Korea',
    'kr' => 'South Korea',
    'kw' => 'Kuwait',
    'kg' => 'Kyrgyzstan',
    'la' => 'Lao Peoples Democratic Republic',
    'lv' => 'Latvia',
    'lb' => 'Lebanon',
    'ls' => 'Lesotho',
    'lr' => 'Liberia',
    'ly' => 'Libya',
    'li' => 'Liechtenstein',
    'lt' => 'Lithuania',
    'lu' => 'Luxembourg',
    'mo' => 'Macao',
    'mk' => 'North Macedonia',
    'mg' => 'Madagascar',
    'mw' => 'Malawi',
    'my' => 'Malaysia',
    'mv' => 'Maldives',
    'ml' => 'Mali',
    'mt' => 'Malta',
    'mh' => 'Marshall Islands',
    'mq' => 'Martinique',
    'mr' => 'Mauritania',
    'mu' => 'Mauritius',
    'yt' => 'Mayotte',
    'mx' => 'Mexico',
    'fm' => 'Micronesia, Federated States of',
    'md' => 'Moldova, Republic of',
    'mc' => 'Monaco',
    'mn' => 'Mongolia',
    'ms' => 'Montserrat',
    'ma' => 'Morocco',
    'mz' => 'Mozambique',
    'mm' => 'Myanmar',
    'na' => 'Namibia',
    'nr' => 'Nauru',
    'np' => 'Nepal',
    'nl' => 'Netherlands',
    'nc' => 'New Caledonia',
    'nz' => 'New Zealand',
    'ni' => 'Nicaragua',
    'ne' => 'Niger',
    'ng' => 'Nigeria',
    'nu' => 'Niue',
    'nf' => 'Norfolk Island',
    'mp' => 'Northern Mariana Islands',
    'no' => 'Norway',
    'om' => 'Oman',
    'pk' => 'Pakistan',
    'pw' => 'Palau',
    'ps' => 'Palestinian Territory, Occupied',
    'pa' => 'Panama',
    'pg' => 'Papua New Guinea',
    'py' => 'Paraguay',
    'pe' => 'Peru',
    'ph' => 'Philippines',
    'pn' => 'Pitcairn',
    'pl' => 'Poland',
    'pt' => 'Portugal',
    'pr' => 'Puerto Rico',
    'qa' => 'Qatar',
    're' => 'Reunion',
    'ro' => 'Romania',
    'ru' => 'Russian Federation',
    'rw' => 'Rwanda',
    'sh' => 'Saint Helena',
    'kn' => 'Saint Kitts and Nevis',
    'lc' => 'Saint Lucia',
    'pm' => 'Saint Pierre and Miquelon',
    'vc' => 'Saint Vincent and the Grenadines',
    'ws' => 'Samoa',
    'sm' => 'San Marino',
    'st' => 'Sao Tome and Principe',
    'sa' => 'Saudi Arabia',
    'sn' => 'Senegal',
    'rs' => 'Serbia and Montenegro',
    'sc' => 'Seychelles',
    'sl' => 'Sierra Leone',
    'sg' => 'Singapore',
    'sk' => 'Slovakia',
    'si' => 'Slovenia',
    'sb' => 'Solomon Islands',
    'so' => 'Somalia',
    'za' => 'South Africa',
    'gs' => 'South Georgia and the South Sandwich Islands',
    'es' => 'Spain',
    'lk' => 'Sri Lanka',
    'sd' => 'Sudan',
    'sr' => 'Suriname',
    'sj' => 'Svalbard and Jan Mayen',
    'sz' => 'Swaziland',
    'se' => 'Sweden',
    'ch' => 'Switzerland',
    'sy' => 'Syrian Arab Republic',
    'tw' => 'Taiwan',
    'tj' => 'Tajikistan',
    'tz' => 'Tanzania, United Republic of',
    'th' => 'Thailand',
    'tl' => 'Timor-Leste',
    'tg' => 'Togo',
    'tk' => 'Tokelau',
    'to' => 'Tonga',
    'tt' => 'Trinidad and Tobago',
    'tn' => 'Tunisia',
    'tr' => 'Turkey',
    'tm' => 'Turkmenistan',
    'tc' => 'Turks and Caicos Islands',
    'tv' => 'Tuvalu',
    'ug' => 'Uganda',
    'ua' => 'Ukraine',
    'ae' => 'United Arab Emirates',
    'uk' => 'United Kingdom',
    'gb' => 'United Kingdom',
    'us' => 'United States',
    'um' => 'United States Minor Outlying Islands',
    'uy' => 'Uruguay',
    'uz' => 'Uzbekistan',
    'vu' => 'Vanuatu',
    've' => 'Venezuela',
    'vn' => 'Viet Nam',
    'vg' => 'Virgin Islands, British',
    'vi' => 'Virgin Islands, U.S.',
    'wf' => 'Wallis and Futuna',
    'eh' => 'Western Sahara',
    'ye' => 'Yemen',
    'zm' => 'Zambia',
    'zw' => 'Zimbabwe',
    'gg' => 'Guernsey',
    'je' => 'Jersey',
    'im' => 'Isle of Man',
    'me' => 'Montenegro',
];
       
$botwriter_languages = [
            'any' => 'Any Language',
            'af' => 'Afrikaans',
            'sq' => 'Albanian',
            'am' => 'Amharic',
            'ar' => 'Arabic',
            'hy' => 'Armenian',
            'az' => 'Azerbaijani',
            'eu' => 'Basque',
            'be' => 'Belarusian',
            'bn' => 'Bengali',
            'bs' => 'Bosnian',
            'bg' => 'Bulgarian',
            'ca' => 'Catalan',
            'ceb' => 'Cebuano',
            'ny' => 'Chichewa',
            'zh-CN' => 'Chinese',
            'co' => 'Corsican',
            'hr' => 'Croatian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'nl' => 'Dutch',
            'en' => 'English',
            'eo' => 'Esperanto',
            'et' => 'Estonian',
            'tl' => 'Filipino',
            'fi' => 'Finnish',
            'fr' => 'French',
            'fy' => 'Frisian',
            'gl' => 'Galician',
            'ka' => 'Georgian',
            'de' => 'German',
            'el' => 'Greek',
            'gu' => 'Gujarati',
            'ht' => 'Haitian Creole',
            'ha' => 'Hausa',
            'haw' => 'Hawaiian',
            'iw' => 'Hebrew',
            'hi' => 'Hindi',
            'hmn' => 'Hmong',
            'hu' => 'Hungarian',
            'is' => 'Icelandic',
            'ig' => 'Igbo',
            'id' => 'Indonesian',
            'ga' => 'Irish',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'jw' => 'Javanese',
            'kn' => 'Kannada',
            'kk' => 'Kazakh',
            'km' => 'Khmer',
            'ko' => 'Korean',
            'ku' => 'Kurdish (Kurmanji)',
            'ky' => 'Kyrgyz',
            'lo' => 'Lao',
            'la' => 'Latin',
            'lv' => 'Latvian',
            'lt' => 'Lithuanian',
            'lb' => 'Luxembourgish',
            'mk' => 'Macedonian',
            'mg' => 'Malagasy',
            'ms' => 'Malay',
            'ml' => 'Malayalam',
            'mt' => 'Maltese',
            'mi' => 'Maori',
            'mr' => 'Marathi',
            'mn' => 'Mongolian',
            'my' => 'Myanmar (Burmese)',
            'ne' => 'Nepali',
            'no' => 'Norwegian',
            'ps' => 'Pashto',
            'fa' => 'Persian',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'pa' => 'Punjabi',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sm' => 'Samoan',
            'gd' => 'Scots Gaelic',
            'sr' => 'Serbian',
            'st' => 'Sesotho',
            'sn' => 'Shona',
            'sd' => 'Sindhi',
            'si' => 'Sinhala',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'so' => 'Somali',
            'es' => 'Spanish',
            'su' => 'Sundanese',
            'sw' => 'Swahili',
            'sv' => 'Swedish',
            'tg' => 'Tajik',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'th' => 'Thai',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz' => 'Uzbek',
            'vi' => 'Vietnamese',
            'cy' => 'Welsh',
            'xh' => 'Xhosa',
            'yi' => 'Yiddish',
            'yo' => 'Yoruba',
            'zu' => 'Zulu',
        ];

/**
 * Get list of available text AI providers
 */
function botwriter_get_text_providers() {
    return [
        'openai' => 'OpenAI (GPT-4o, GPT-4.1)',
        'anthropic' => 'Anthropic (Claude)',
        'google' => 'Google (Gemini)',
        'mistral' => 'Mistral AI',
        'groq' => 'Groq (Llama, Mixtral)',
        'openrouter' => 'OpenRouter (Multiple)',
    ];
}

/**
 * Get list of available image AI providers
 */
function botwriter_get_image_providers() {
    return [
        'dalle' => 'DALL-E (OpenAI)',
        'gemini' => 'Google Gemini',
        'fal' => 'Fal.ai (Flux)',
        'replicate' => 'Replicate',
        'stability' => 'Stability AI',
        'cloudflare' => 'Cloudflare AI',
    ];
}

/**
 * Get the current text model based on selected provider
 */
function botwriter_get_current_text_model() {
    $provider = get_option('botwriter_text_provider', 'openai');
    $defaults = [
        'openai' => 'gpt-5-mini',
        'anthropic' => 'claude-sonnet-4-5-20250929',
        'google' => 'gemini-2.5-flash',
        'mistral' => 'mistral-large-latest',
        'groq' => 'llama-3.3-70b-versatile',
        'openrouter' => 'anthropic/claude-sonnet-4',
    ];
    return get_option("botwriter_{$provider}_model", $defaults[$provider] ?? 'gpt-5-mini');
}

/**
 * Get the current image model based on selected provider
 */
function botwriter_get_current_image_model() {
    $provider = get_option('botwriter_image_provider', 'dalle');
    $defaults = [
        'dalle' => 'gpt-image-1',
        'fal' => 'fal-ai/flux-pro/v1.1',
        'replicate' => 'black-forest-labs/flux-1.1-pro',
        'stability' => 'sd3-large-turbo',
        'ideogram' => 'V_2_TURBO',
    ];
    return get_option("botwriter_{$provider}_model", $defaults[$provider] ?? 'gpt-image-1');
}

/**
 * Get the API key for a specific provider
 */
function botwriter_get_provider_api_key($provider) {
    $key = get_option("botwriter_{$provider}_api_key", '');
    if (!empty($key)) {
        return botwriter_decrypt_api_key($key);
    }
    return '';
}


/**
 * SEO Slug Translation
 * 
 * Translates post title, tags and image filename into SEO-friendly
 * slugs in the target language using the configured AI text provider.
 * 
 * @param string $title     The post title to translate
 * @param string $tags      Comma-separated tags (optional)
 * @param string $language  Target language code (e.g., 'en')
 * @return array|false  Array with 'post_slug', 'tag_slugs', 'image_slug' or false on failure
 */
function botwriter_translate_slugs($title, $tags = '', $language = 'en') {
    // Check if translation is enabled
    if (get_option('botwriter_seo_translation_enabled', '0') !== '1') {
        return false;
    }

    $translate_title = get_option('botwriter_seo_translate_title', '1') === '1';
    $translate_tags  = get_option('botwriter_seo_translate_tags', '1') === '1';
    $translate_image = get_option('botwriter_seo_translate_image', '1') === '1';
    $target_lang     = get_option('botwriter_seo_target_language', 'en');

    // Nothing to translate
    if (!$translate_title && !$translate_tags && !$translate_image) {
        return false;
    }

    // Build the prompt
    $items = array();
    if ($translate_title || $translate_image) {
        $items[] = 'Title: ' . $title;
    }
    if ($translate_tags && !empty($tags)) {
        $items[] = 'Tags: ' . $tags;
    }

    $language_names = array(
        'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
        'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'ru' => 'Russian',
        'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese', 'ar' => 'Arabic',
        'hi' => 'Hindi', 'tr' => 'Turkish', 'pl' => 'Polish', 'sv' => 'Swedish',
        'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish', 'cs' => 'Czech',
        'ro' => 'Romanian', 'hu' => 'Hungarian', 'el' => 'Greek', 'th' => 'Thai',
        'vi' => 'Vietnamese', 'id' => 'Indonesian', 'ms' => 'Malay', 'uk' => 'Ukrainian',
    );
    $lang_name = $language_names[$target_lang] ?? 'English';

    $prompt  = "You are a translator. Your task is to TRANSLATE the following text into {$lang_name} and then convert it into URL-friendly slugs.\n";
    $prompt .= "IMPORTANT: You MUST translate the meaning into {$lang_name} first, then format as a slug. Do NOT just remove accents or spaces from the original language.\n";
    $prompt .= "Example: if the input is in Spanish 'Receta de Paella Valenciana' and target is English, the slug must be 'valencian-paella-recipe', NOT 'receta-de-paella-valenciana'.\n";
    $prompt .= "Rules: lowercase, hyphens instead of spaces, no special characters, no accents, max 6 words per slug.\n\n";
    $prompt .= implode("\n", $items) . "\n\n";
    $prompt .= "Respond ONLY with valid JSON, no explanation, no markdown:\n";
    $prompt .= '{"post_slug": "translated-title-slug", "tag_slugs": ["tag-one", "tag-two"], "image_slug": "image-filename-slug"}';

    // Determine which provider to use
    $provider = get_option('botwriter_text_provider', 'openai');
    
    // Custom/self-hosted provider uses direct mode
    if ($provider === 'custom') {
        return botwriter_translate_slugs_custom($prompt);
    }

    $api_key = botwriter_get_provider_api_key($provider);
    if (empty($api_key)) {
        botwriter_log('SEO Translation: No API key for provider ' . $provider, [], 'warning');
        return false;
    }

    // Use a fast/cheap model for translations
    $model = botwriter_get_seo_translation_model($provider);
    $ssl_verify = get_option('botwriter_sslverify', 'yes') === 'yes';

    // Make the API call based on provider
    $response = botwriter_translate_api_call($provider, $api_key, $model, $prompt, $ssl_verify);

    if (is_wp_error($response)) {
        botwriter_log('SEO Translation API error', ['provider' => $provider, 'error' => $response->get_error_message()], 'error');
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);

    if ($http_code !== 200) {
        botwriter_log('SEO Translation HTTP error', ['provider' => $provider, 'http_code' => $http_code], 'error');
        return false;
    }

    // Extract text content from provider-specific response format
    $text = botwriter_extract_translation_text($provider, $body);
    if (empty($text)) {
        botwriter_log('SEO Translation: Empty response from provider', ['provider' => $provider], 'error');
        return false;
    }

    // Parse JSON from response
    $result = botwriter_parse_slug_json($text);
    if ($result === false) {
        botwriter_log('SEO Translation: Failed to parse JSON', ['provider' => $provider, 'raw' => substr($text, 0, 500)], 'error');
        return false;
    }

    botwriter_log('SEO Translation success', [
        'provider' => $provider,
        'post_slug' => $result['post_slug'] ?? '',
        'tag_count' => count($result['tag_slugs'] ?? []),
    ]);

    return $result;
}

/**
 * Get the fastest/cheapest model for SEO translation based on provider
 */
function botwriter_get_seo_translation_model($provider) {
    $fast_models = array(
        'openai'     => 'gpt-4o-mini',
        'anthropic'  => 'claude-haiku-4-20250414',
        'google'     => 'gemini-2.5-flash',
        'mistral'    => 'mistral-small-latest',
        'groq'       => 'llama-3.3-70b-versatile',
        'openrouter' => 'google/gemini-2.0-flash-001',
    );
    return $fast_models[$provider] ?? 'gpt-4o-mini';
}

/**
 * Make API call for slug translation to the appropriate provider
 */
function botwriter_translate_api_call($provider, $api_key, $model, $prompt, $ssl_verify) {
    $timeout = 30;

    switch ($provider) {
        case 'openai':
            return wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                    'max_tokens' => 200, 'temperature' => 0.3,
                )),
            ));

        case 'anthropic':
            return wp_remote_post('https://api.anthropic.com/v1/messages', array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array('x-api-key' => $api_key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'model' => $model, 'max_tokens' => 200,
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                )),
            ));

        case 'google':
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
            return wp_remote_post($url, array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'contents' => array(array('parts' => array(array('text' => $prompt)))),
                    'generationConfig' => array('maxOutputTokens' => 200, 'temperature' => 0.3),
                )),
            ));

        case 'mistral':
            return wp_remote_post('https://api.mistral.ai/v1/chat/completions', array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                    'max_tokens' => 200, 'temperature' => 0.3,
                )),
            ));

        case 'groq':
            return wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                    'max_tokens' => 200, 'temperature' => 0.3,
                )),
            ));

        case 'openrouter':
            return wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
                'timeout' => $timeout, 'sslverify' => $ssl_verify,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(), 'X-Title' => 'BotWriter',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                    'max_tokens' => 200, 'temperature' => 0.3,
                )),
            ));

        default:
            return new WP_Error('unknown_provider', 'Unknown text provider for translation: ' . $provider);
    }
}

/**
 * Handle translation via custom/self-hosted provider
 */
function botwriter_translate_slugs_custom($prompt) {
    $url     = get_option('botwriter_custom_text_url', '');
    $api_key = botwriter_get_provider_api_key('custom_text');
    $model   = get_option('botwriter_custom_text_model', '');

    if (empty($url)) {
        botwriter_log('SEO Translation: Custom provider URL not configured', [], 'warning');
        return false;
    }

    $endpoint = rtrim($url, '/') . '/v1/chat/completions';
    $headers = array('Content-Type' => 'application/json');
    if (!empty($api_key)) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    $response = wp_remote_post($endpoint, array(
        'timeout' => 30, 'sslverify' => false,
        'headers' => $headers,
        'body' => wp_json_encode(array(
            'model' => $model ?: 'default',
            'messages' => array(array('role' => 'user', 'content' => $prompt)),
            'max_tokens' => 200, 'temperature' => 0.3,
        )),
    ));

    if (is_wp_error($response)) {
        botwriter_log('SEO Translation custom error', ['error' => $response->get_error_message()], 'error');
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $text = botwriter_extract_translation_text('openai', $body); // Custom uses OpenAI-compatible format
    if (empty($text)) { return false; }
    return botwriter_parse_slug_json($text);
}

/**
 * Extract the text content from provider-specific response formats
 */
function botwriter_extract_translation_text($provider, $body) {
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return ''; }

    switch ($provider) {
        case 'anthropic':
            return $decoded['content'][0]['text'] ?? '';
        case 'google':
            return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        default: // openai, mistral, groq, openrouter — all OpenAI-compatible
            return $decoded['choices'][0]['message']['content'] ?? '';
    }
}

/**
 * Parse the JSON slug response from AI, with fallback for markdown-wrapped JSON
 */
function botwriter_parse_slug_json($text) {
    // Strip markdown code fences if present
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = trim($text);

    $data = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return false;
    }

    // Sanitize slugs
    $result = array(
        'post_slug'  => isset($data['post_slug']) ? sanitize_title($data['post_slug']) : '',
        'tag_slugs'  => array(),
        'image_slug' => isset($data['image_slug']) ? sanitize_file_name($data['image_slug']) : '',
    );

    if (isset($data['tag_slugs']) && is_array($data['tag_slugs'])) {
        foreach ($data['tag_slugs'] as $tag_slug) {
            $result['tag_slugs'][] = sanitize_title($tag_slug);
        }
    }

    return $result;
}

/**
 * Apply translated slugs to a post after it has been created
 *
 * @param int    $post_id     The post ID
 * @param string $title       Original post title
 * @param string $tags_string Comma-separated tags
 */
function botwriter_apply_translated_slugs($post_id, $title, $tags_string = '') {
    if (!$post_id || get_option('botwriter_seo_translation_enabled', '0') !== '1') {
        return;
    }

    $slugs = botwriter_translate_slugs($title, $tags_string);
    if ($slugs === false) {
        return;
    }

    $translate_title = get_option('botwriter_seo_translate_title', '1') === '1';
    $translate_tags  = get_option('botwriter_seo_translate_tags', '1') === '1';

    // Apply post slug
    if ($translate_title && !empty($slugs['post_slug'])) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_name' => wp_unique_post_slug(
                $slugs['post_slug'],
                $post_id,
                get_post_status($post_id),
                get_post_type($post_id),
                wp_get_post_parent_id($post_id)
            ),
        ));
    }

    // Apply tag slugs — match by original tag order, not alphabetical wp_get_post_tags order
    if ($translate_tags && !empty($slugs['tag_slugs']) && !empty($tags_string)) {
        $original_tags = array_map('trim', explode(',', $tags_string));
        $tag_slug_map  = $slugs['tag_slugs'];

        botwriter_log('SEO Translation: Applying tag slugs', [
            'post_id'       => $post_id,
            'original_tags' => $original_tags,
            'tag_slugs'     => $tag_slug_map,
        ]);

        foreach ($original_tags as $index => $tag_name) {
            if (!isset($tag_slug_map[$index]) || empty($tag_slug_map[$index])) {
                continue;
            }
            // Find the term by its name (the original tag text)
            $term = get_term_by('name', $tag_name, 'post_tag');
            if (!$term) {
                // Also try by slug WordPress auto-generated
                $term = get_term_by('slug', sanitize_title($tag_name), 'post_tag');
            }
            if ($term && !is_wp_error($term)) {
                $result = wp_update_term($term->term_id, 'post_tag', array(
                    'slug' => $tag_slug_map[$index],
                ));
                if (is_wp_error($result)) {
                    botwriter_log('SEO Translation: Failed to update tag slug', [
                        'tag_name' => $tag_name,
                        'term_id'  => $term->term_id,
                        'error'    => $result->get_error_message(),
                    ], 'warning');
                }
            } else {
                botwriter_log('SEO Translation: Tag term not found', [
                    'tag_name' => $tag_name,
                    'post_id'  => $post_id,
                ], 'warning');
            }
        }
    }

    // Return image slug for use during image attachment
    return $slugs['image_slug'] ?? '';
}
