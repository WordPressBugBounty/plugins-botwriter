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


/**
 * Fetch and parse an RSS/Atom feed from the client side using wp_remote_get.
 *
 * Inspired by the server-side leerRSSFuente() in api_rss.php and the
 * direct-mode class-direct-content-sources.php implementation.
 *
 * @param string $rss_url         The RSS feed URL.
 * @param string $published_links Comma-separated list of already-published links.
 * @return array {
 *     @type bool   $success        Whether a new article was found.
 *     @type string $error          Error message (only when success is false).
 *     @type string $source_title   Title of the selected article.
 *     @type string $source_content Description/content of the selected article.
 *     @type string $link_original  Permalink of the selected article.
 * }
 */
function botwriter_fetch_rss_content( $rss_url, $published_links = '' ) {

    // Validate URL
    if ( empty( $rss_url ) ) {
        return [ 'success' => false, 'error' => 'RSS source URL is empty or not configured.' ];
    }
    $rss_url = filter_var( $rss_url, FILTER_SANITIZE_URL );
    if ( ! filter_var( $rss_url, FILTER_VALIDATE_URL ) ) {
        return [ 'success' => false, 'error' => 'Invalid RSS feed URL.' ];
    }

    $ssl_verify = get_option( 'botwriter_sslverify', 'yes' );

    botwriter_log( 'Client RSS fetch', [ 'url' => $rss_url ] );

    // Fetch the feed
    $response = wp_remote_get( $rss_url, [
        'timeout'    => 30,
        'sslverify'  => ( $ssl_verify !== 'no' ),
        'user-agent' => 'BotWriter/' . BOTWRITER_VERSION . ' WordPress Plugin',
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'error' => 'Failed to fetch RSS feed: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        return [ 'success' => false, 'error' => "RSS feed returned HTTP {$http_code}" ];
    }

    $body = wp_remote_retrieve_body( $response );

    // Parse XML
    libxml_use_internal_errors( true );
    try {
        $xml = new SimpleXMLElement( $body, LIBXML_NOCDATA );
    } catch ( Exception $e ) {
        return [ 'success' => false, 'error' => 'Error parsing RSS feed: ' . $e->getMessage() ];
    }

    // Build items array (Atom or RSS)
    $items = [];

    if ( isset( $xml->entry ) ) {
        // Atom format
        foreach ( $xml->entry as $entry ) {
            $link = '';
            if ( isset( $entry->link['href'] ) ) {
                $link = (string) $entry->link['href'];
            } else {
                foreach ( $entry->link as $l ) {
                    if ( (string) $l['rel'] === 'alternate' || empty( (string) $l['rel'] ) ) {
                        $link = (string) $l['href'];
                        break;
                    }
                }
            }
            $items[] = [
                'title'       => wp_strip_all_tags( (string) $entry->title ),
                'link'        => $link,
                'description' => wp_strip_all_tags( (string) ( $entry->summary ?? $entry->content ?? '' ) ),
                'content'     => wp_strip_all_tags( (string) ( $entry->content ?? $entry->summary ?? '' ) ),
                'date'        => (string) ( $entry->updated ?? $entry->published ?? '' ),
            ];
        }
    } else {
        // Standard RSS
        $channel = $xml->channel ?? $xml;
        if ( isset( $channel->item ) ) {
            foreach ( $channel->item as $item ) {
                $content = '';
                $namespaces = $item->getNamespaces( true );
                if ( isset( $namespaces['content'] ) ) {
                    $content_ns = $item->children( $namespaces['content'] );
                    if ( isset( $content_ns->encoded ) ) {
                        $content = wp_strip_all_tags( (string) $content_ns->encoded );
                    }
                }
                $description = wp_strip_all_tags( (string) $item->description );

                $items[] = [
                    'title'       => wp_strip_all_tags( (string) $item->title ),
                    'link'        => (string) $item->link,
                    'description' => $description,
                    'content'     => ! empty( $content ) ? $content : $description,
                    'date'        => (string) ( $item->pubDate ?? '' ),
                ];
            }
        }
    }

    if ( empty( $items ) ) {
        return [ 'success' => false, 'error' => 'No items found in RSS feed or failed to parse.' ];
    }

    // Find first article not already published
    $published_array = array_filter( array_map( 'trim', explode( ',', $published_links ) ) );

    foreach ( $items as $article ) {
        if ( ! in_array( $article['link'], $published_array, true ) ) {
            botwriter_log( 'Client RSS article selected', [
                'title' => $article['title'],
                'link'  => $article['link'],
            ] );
            return [
                'success'        => true,
                'source_title'   => $article['title'],
                'source_content' => ! empty( $article['content'] ) ? $article['content'] : $article['description'],
                'link_original'  => $article['link'],
            ];
        }
    }

    return [ 'success' => false, 'error' => 'No new articles found in RSS feed. All articles have already been published.' ];
}


/**
 * Fetch a single unpublished article from a remote WordPress site via its REST API.
 *
 * Mirrors the logic previously handled server-side in getWebsitePosts.php.
 * Uses wp_remote_get to call /wp-json/wp/v2/posts on the target domain,
 * then picks the first post whose link is not in $published_links.
 *
 * @param string $domain_name     URL of the remote WordPress site.
 * @param string $category_ids    Comma-separated list of category IDs.
 * @param string $published_links Comma-separated list of already-published links.
 * @return array {
 *     @type bool   $success        Whether an article was found.
 *     @type string $error          Error message (when success is false).
 *     @type string $source_title   Title of the selected article.
 *     @type string $source_content Content of the selected article (HTML stripped).
 *     @type string $link_original  Permalink of the selected article.
 * }
 */
function botwriter_fetch_wordpress_content( $domain_name, $category_ids, $published_links = '' ) {

    if ( empty( $domain_name ) ) {
        return [ 'success' => false, 'error' => 'WordPress domain name is empty or not configured.' ];
    }

    if ( empty( $category_ids ) ) {
        return [ 'success' => false, 'error' => 'WordPress category IDs are not configured.' ];
    }

    // Ensure the domain has a scheme
    if ( ! preg_match( '#^https?://#i', $domain_name ) ) {
        $domain_name = 'https://' . ltrim( $domain_name, '/' );
    }
    $domain_name = filter_var( $domain_name, FILTER_SANITIZE_URL );
    if ( ! filter_var( $domain_name, FILTER_VALIDATE_URL ) ) {
        return [ 'success' => false, 'error' => 'Invalid WordPress domain: ' . $domain_name ];
    }

    // Sanitise category IDs (only digits and commas)
    $category_ids = preg_replace( '/[^0-9,]/', '', $category_ids );
    if ( empty( $category_ids ) ) {
        return [ 'success' => false, 'error' => 'Invalid category IDs.' ];
    }

    // Build REST API URL
    $api_url = rtrim( $domain_name, '/' ) . '/wp-json/wp/v2/posts';
    $api_url .= '?categories=' . urlencode( $category_ids );
    $api_url .= '&per_page=15';

    $ssl_verify = get_option( 'botwriter_sslverify', 'yes' );

    botwriter_log( 'Client WordPress fetch', [ 'url' => $api_url ] );

    $response = wp_remote_get( $api_url, [
        'timeout'    => 30,
        'sslverify'  => ( $ssl_verify !== 'no' ),
        'user-agent' => 'BotWriter/' . BOTWRITER_VERSION . ' WordPress Plugin',
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'error' => 'Failed to connect to WordPress site: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        return [ 'success' => false, 'error' => "WordPress REST API returned HTTP {$http_code}. Make sure the site has REST API enabled." ];
    }

    $body  = wp_remote_retrieve_body( $response );
    $posts = json_decode( $body, true );

    if ( ! is_array( $posts ) || empty( $posts ) ) {
        return [ 'success' => false, 'error' => 'No posts found in WordPress site or failed to parse response.' ];
    }

    // Find first unpublished post
    $published_array = array_filter( array_map( 'trim', explode( ',', $published_links ) ) );

    foreach ( $posts as $post ) {
        $post_link = $post['link'] ?? '';

        if ( ! in_array( $post_link, $published_array, true ) ) {
            $title        = wp_strip_all_tags( html_entity_decode( $post['title']['rendered'] ?? '' ) );
            $post_content = wp_strip_all_tags( html_entity_decode( $post['content']['rendered'] ?? '' ) );

            botwriter_log( 'Client WordPress article selected', [
                'title' => $title,
                'link'  => $post_link,
            ] );

            return [
                'success'        => true,
                'source_title'   => $title,
                'source_content' => $post_content,
                'link_original'  => $post_link,
            ];
        }
    }

    return [ 'success' => false, 'error' => 'No new posts found in WordPress site. All posts have already been published.' ];
}


/**
 * Fetch categories from a remote WordPress site via its REST API.
 *
 * Mirrors the logic previously handled server-side in getWebsiteCategories.php.
 *
 * @param string $domain_name URL of the remote WordPress site.
 * @return array {
 *     @type bool   $success    Whether categories were retrieved.
 *     @type string $error      Error message (when success is false).
 *     @type array  $categories Array of [ id, name, slug ] objects.
 * }
 */
function botwriter_fetch_wordpress_categories( $domain_name ) {

    if ( empty( $domain_name ) ) {
        return [ 'success' => false, 'error' => 'WordPress domain name is empty.' ];
    }

    // Ensure the domain has a scheme
    if ( ! preg_match( '#^https?://#i', $domain_name ) ) {
        $domain_name = 'https://' . ltrim( $domain_name, '/' );
    }
    $domain_name = filter_var( $domain_name, FILTER_SANITIZE_URL );
    if ( ! filter_var( $domain_name, FILTER_VALIDATE_URL ) ) {
        return [ 'success' => false, 'error' => 'Invalid WordPress domain: ' . $domain_name ];
    }

    $api_url    = rtrim( $domain_name, '/' ) . '/wp-json/wp/v2/categories';
    $ssl_verify = get_option( 'botwriter_sslverify', 'yes' );

    $response = wp_remote_get( $api_url, [
        'timeout'    => 15,
        'sslverify'  => ( $ssl_verify !== 'no' ),
        'user-agent' => 'BotWriter/' . BOTWRITER_VERSION . ' WordPress Plugin',
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'error' => 'Failed to connect to WordPress site: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        return [ 'success' => false, 'error' => "WordPress REST API returned HTTP {$http_code}." ];
    }

    $body       = wp_remote_retrieve_body( $response );
    $categories = json_decode( $body, true );

    if ( ! is_array( $categories ) || empty( $categories ) ) {
        return [ 'success' => false, 'error' => 'No categories found or failed to parse response.' ];
    }

    $filtered = array_map( function ( $cat ) {
        return [
            'id'   => $cat['id']   ?? 0,
            'name' => $cat['name'] ?? '',
            'slug' => $cat['slug'] ?? '',
        ];
    }, $categories );

    return [ 'success' => true, 'categories' => $filtered ];
}


/**
 * Generate an SEO meta description for a post using the configured AI text provider.
 *
 * Uses the same lightweight fast-model strategy as SEO Slug Translation.
 * The result is a single sentence of ≤160 characters suitable for the
 * <meta name="description"> tag.
 *
 * @param string $title   Post title.
 * @param string $content Post HTML content (will be stripped to plain text).
 * @param string $language_code  Post language code (e.g. 'es', 'en').
 * @return string|false  The meta description, or false on failure.
 */
function botwriter_generate_seo_meta( $title, $content, $language_code = '' ) {
    global $botwriter_languages;

    // Feature disabled?
    if ( get_option( 'botwriter_meta_disabled', '0' ) === '1' ) {
        return false;
    }

    // Strip HTML and trim content for the prompt (first ~800 chars is enough context)
    $plain = wp_strip_all_tags( $content );
    $plain = preg_replace( '/\s+/', ' ', $plain );
    $plain = mb_substr( $plain, 0, 800 );

    // Resolve language name
    $lang_name = '';
    if ( ! empty( $language_code ) && isset( $botwriter_languages[ $language_code ] ) ) {
        $lang_name = $botwriter_languages[ $language_code ];
    }

    $prompt  = "You are an expert SEO copywriter.\n";
    $prompt .= "Write a compelling meta description for the following blog post.\n";
    $prompt .= "Rules:\n";
    $prompt .= "- Exactly ONE sentence, maximum 155 characters.\n";
    $prompt .= "- Must summarize the article accurately and entice clicks.\n";
    $prompt .= "- Do NOT include the title verbatim.\n";
    $prompt .= "- Do NOT use quotes or special characters.\n";
    if ( ! empty( $lang_name ) ) {
        $prompt .= "- The meta description language MUST be: {$lang_name}.\n";
    }
    $prompt .= "\nTitle: {$title}\n";
    $prompt .= "Content excerpt: {$plain}\n\n";
    $prompt .= "Respond ONLY with the meta description text, nothing else.";

    // Determine provider
    $provider = get_option( 'botwriter_text_provider', 'openai' );

    // Custom provider path
    if ( $provider === 'custom' ) {
        return botwriter_generate_meta_custom( $prompt );
    }

    $api_key = botwriter_get_provider_api_key( $provider );
    if ( empty( $api_key ) ) {
        botwriter_log( 'SEO Meta: No API key for provider ' . $provider, [], 'warning' );
        return false;
    }

    $model      = botwriter_get_seo_translation_model( $provider ); // fast & cheap model
    $ssl_verify = get_option( 'botwriter_sslverify', 'yes' ) === 'yes';

    $response = botwriter_translate_api_call( $provider, $api_key, $model, $prompt, $ssl_verify );

    if ( is_wp_error( $response ) ) {
        botwriter_log( 'SEO Meta API error', [ 'provider' => $provider, 'error' => $response->get_error_message() ], 'error' );
        return false;
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        botwriter_log( 'SEO Meta HTTP error', [ 'provider' => $provider, 'http_code' => $http_code ], 'error' );
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $text = botwriter_extract_translation_text( $provider, $body );

    if ( empty( $text ) ) {
        botwriter_log( 'SEO Meta: Empty response', [ 'provider' => $provider ], 'error' );
        return false;
    }

    $meta = botwriter_sanitize_meta_description( $text );

    botwriter_log( 'SEO Meta generated', [
        'provider' => $provider,
        'length'   => mb_strlen( $meta ),
    ] );

    return $meta;
}

/**
 * Generate meta description via custom/self-hosted provider.
 *
 * @param string $prompt The prompt to send.
 * @return string|false
 */
function botwriter_generate_meta_custom( $prompt ) {
    $url     = get_option( 'botwriter_custom_text_url', '' );
    $api_key = botwriter_get_provider_api_key( 'custom_text' );
    $model   = get_option( 'botwriter_custom_text_model', '' );

    if ( empty( $url ) ) {
        botwriter_log( 'SEO Meta: Custom provider URL not configured', [], 'warning' );
        return false;
    }

    $endpoint = rtrim( $url, '/' ) . '/v1/chat/completions';
    $headers  = [ 'Content-Type' => 'application/json' ];
    if ( ! empty( $api_key ) ) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    $response = wp_remote_post( $endpoint, [
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => $headers,
        'body'      => wp_json_encode( [
            'model'       => $model ?: 'default',
            'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens'  => 100,
            'temperature' => 0.4,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        botwriter_log( 'SEO Meta custom error', [ 'error' => $response->get_error_message() ], 'error' );
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $text = botwriter_extract_translation_text( 'openai', $body );

    if ( empty( $text ) ) {
        return false;
    }

    return botwriter_sanitize_meta_description( $text );
}

/**
 * Clean and trim an AI-generated meta description so it is safe for SEO tags.
 *
 * @param string $text Raw AI output.
 * @return string
 */
function botwriter_sanitize_meta_description( $text ) {
    $text = wp_strip_all_tags( $text );
    $text = trim( $text, " \t\n\r\0\x0B\"'" );
    // Ensure ≤ 160 characters, cut at last word boundary
    if ( mb_strlen( $text ) > 160 ) {
        $text = mb_substr( $text, 0, 157 );
        $text = preg_replace( '/\s+\S*$/', '', $text );
        $text .= '...';
    }
    return $text;
}

/**
 * Save SEO meta description to the post's excerpt and to popular SEO plugin fields.
 *
 * Supports: Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework.
 *
 * @param int    $post_id         The post ID.
 * @param string $meta_description The meta description.
 */
function botwriter_apply_seo_meta( $post_id, $meta_description ) {
    if ( empty( $meta_description ) || ! $post_id ) {
        return;
    }

    // Always save as post excerpt (native WP)
    wp_update_post( [
        'ID'           => $post_id,
        'post_excerpt' => $meta_description,
    ] );

    // Yoast SEO
    if ( defined( 'WPSEO_VERSION' ) || metadata_exists( 'post', $post_id, '_yoast_wpseo_metadesc' ) ) {
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
    }

    // Rank Math
    if ( class_exists( 'RankMath' ) || metadata_exists( 'post', $post_id, 'rank_math_description' ) ) {
        update_post_meta( $post_id, 'rank_math_description', $meta_description );
    }

    // All in One SEO (AIOSEO)
    if ( function_exists( 'aioseo' ) ) {
        global $wpdb;
        $aioseo_table = $wpdb->prefix . 'aioseo_posts';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$aioseo_table}'" ) === $aioseo_table ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$aioseo_table} WHERE post_id = %d", $post_id
            ) );
            if ( $exists ) {
                $wpdb->update( $aioseo_table, [ 'description' => $meta_description ], [ 'post_id' => $post_id ] );
            } else {
                $wpdb->insert( $aioseo_table, [ 'post_id' => $post_id, 'description' => $meta_description ] );
            }
        }
    }

    // SEOPress
    if ( defined( 'SEOPRESS_VERSION' ) ) {
        update_post_meta( $post_id, '_seopress_titles_desc', $meta_description );
    }

    // The SEO Framework
    if ( function_exists( 'the_seo_framework' ) ) {
        update_post_meta( $post_id, '_genesis_description', $meta_description );
    }

    botwriter_log( 'SEO Meta applied to post', [
        'post_id' => $post_id,
        'length'  => mb_strlen( $meta_description ),
        'plugins' => implode( ', ', array_filter( [
            defined( 'WPSEO_VERSION' ) ? 'Yoast' : '',
            class_exists( 'RankMath' ) ? 'RankMath' : '',
            function_exists( 'aioseo' ) ? 'AIOSEO' : '',
            defined( 'SEOPRESS_VERSION' ) ? 'SEOPress' : '',
            function_exists( 'the_seo_framework' ) ? 'TSF' : '',
        ] ) ) ?: 'none (excerpt only)',
    ] );
}
