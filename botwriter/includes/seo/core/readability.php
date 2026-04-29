<?php
/**
 * BotWriter SEO — Readability scoring (language-agnostic approximation).
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compute readability metrics for a post.
 * Returns array with grouped checks each having {id,label,status,value,hint}.
 *
 * status ∈ good|warn|bad
 *
 * @param int  $post_id
 * @param bool $force
 * @return array
 */
function botwriter_seo_compute_readability($post_id, $force = false) {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post) {
        return array('score' => 0, 'grade' => 'n/a', 'checks' => array(), 'computed_at' => 0);
    }

    $html = (string) $post->post_content;
    $hash = sha1($html . '|' . $post->post_modified_gmt);

    if (!$force) {
        $cached = get_post_meta($post_id, '_botwriter_seo_readability_report', true);
        if (is_array($cached) && !empty($cached['hash']) && $cached['hash'] === $hash) {
            update_post_meta($post_id, '_botwriter_seo_readability_at', time());
            return $cached;
        }
    }

    $plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
    $words = $plain === '' ? array() : preg_split('/\s+/u', $plain);
    $word_count = is_array($words) ? count($words) : 0;

    // Sentences (split on . ! ? ¡ ¿ … and Chinese/Japanese full stops)
    $sentences = $plain === '' ? array() : preg_split('/(?<=[\.!?…。！？])\s+/u', $plain);
    $sentences = array_values(array_filter(array_map('trim', $sentences ?: array()), 'strlen'));
    $sentence_count = count($sentences);

    // Syllable approximation: count vowel groups per word (works decently for ES/EN).
    $syllables = 0;
    foreach ($words as $w) {
        $w = strtolower(remove_accents($w));
        $w = preg_replace('/[^a-z]/', '', $w);
        if ($w === '') { continue; }
        preg_match_all('/[aeiouy]+/', $w, $m);
        $count = isset($m[0]) ? count($m[0]) : 0;
        $syllables += max(1, $count);
    }
    $avg_words_per_sentence = $sentence_count > 0 ? $word_count / $sentence_count : 0;
    $avg_syllables_per_word = $word_count > 0 ? $syllables / $word_count : 0;

    // Flesch Reading Ease (English baseline; still useful as a relative gauge).
    $flesch = 206.835 - (1.015 * $avg_words_per_sentence) - (84.6 * $avg_syllables_per_word);
    $flesch = max(0, min(100, round($flesch, 1)));

    // Long sentences (>20 words) ratio.
    $long_sentences = 0;
    foreach ($sentences as $s) {
        $sw = preg_split('/\s+/u', $s);
        if (is_array($sw) && count($sw) > 20) { $long_sentences++; }
    }
    $long_ratio = $sentence_count > 0 ? ($long_sentences / $sentence_count) * 100 : 0;

    // Paragraph length analysis.
    $paragraphs = array();
    if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $pm)) {
        foreach ($pm[1] as $p) {
            $pp = trim(wp_strip_all_tags($p));
            if ($pp !== '') { $paragraphs[] = $pp; }
        }
    }
    if (empty($paragraphs) && $plain !== '') { $paragraphs = array($plain); }
    $long_paragraphs = 0;
    foreach ($paragraphs as $p) {
        $pw = preg_split('/\s+/u', $p);
        if (is_array($pw) && count($pw) > 150) { $long_paragraphs++; }
    }
    $para_count = count($paragraphs);
    $long_para_ratio = $para_count > 0 ? ($long_paragraphs / $para_count) * 100 : 0;

    // Subheading distribution: words between H2/H3 should rarely exceed 300.
    $sub_chunks = preg_split('/<h[23][^>]*>.*?<\/h[23]>/is', $html);
    $worst_chunk_words = 0;
    if (is_array($sub_chunks)) {
        foreach ($sub_chunks as $chunk) {
            $cw = botwriter_seo_word_count(wp_strip_all_tags($chunk));
            if ($cw > $worst_chunk_words) { $worst_chunk_words = $cw; }
        }
    }

    // Transition words (small bilingual sample — ES/EN).
    $transitions = array(
        'además','también','sin embargo','por lo tanto','asimismo','en consecuencia','por ejemplo','finalmente','primero','segundo',
        'además','mientras','aunque','porque','debido a','de hecho','en resumen','en conclusión',
        'however','therefore','moreover','furthermore','for example','in addition','finally','first','second',
        'meanwhile','although','because','due to','in fact','in summary','in conclusion','besides','consequently'
    );
    $transitions = array_unique(array_map(function ($t) { return strtolower(remove_accents($t)); }, $transitions));
    $tr_count = 0;
    $plain_norm = strtolower(remove_accents($plain));
    foreach ($transitions as $t) {
        $tr_count += substr_count($plain_norm, ' ' . $t . ' ');
    }
    $tr_ratio = $sentence_count > 0 ? ($tr_count / $sentence_count) * 100 : 0;

    // Passive voice approximation (English: be + past participle ending in 'ed' or common irregulars).
    $passive_hits = 0;
    if (preg_match_all('/\b(is|are|was|were|been|being|be)\s+([a-z]+ed|done|made|taken|seen|given|known|shown|written)\b/i', $plain, $pv)) {
        $passive_hits = count($pv[0]);
    }
    $passive_ratio = $sentence_count > 0 ? ($passive_hits / $sentence_count) * 100 : 0;

    // Consecutive sentence starters (3+ in a row starting with same word).
    $consec_max = 0; $consec_cur = 1; $prev_starter = null;
    foreach ($sentences as $s) {
        $sw = preg_split('/\s+/u', trim($s));
        $starter = isset($sw[0]) ? strtolower(remove_accents($sw[0])) : '';
        if ($starter !== '' && $starter === $prev_starter) {
            $consec_cur++;
            if ($consec_cur > $consec_max) { $consec_max = $consec_cur; }
        } else {
            $consec_cur = 1;
        }
        $prev_starter = $starter;
    }

    $checks = array();
    $score_total = 0; $score_max = 0;

    $add = function ($id, $label, $weight, $status, $value = '', $hint = '') use (&$checks, &$score_total, &$score_max) {
        $score_max += $weight;
        $score_total += ($status === 'good' ? $weight : ($status === 'warn' ? $weight * 0.5 : 0));
        $checks[] = array(
            'id'     => $id,
            'label'  => $label,
            'weight' => $weight,
            'status' => $status,
            'value'  => $value,
            'hint'   => $hint,
        );
    };

    // 1. Flesch
    $st = ($flesch >= 60) ? 'good' : (($flesch >= 30) ? 'warn' : 'bad');
    $add('flesch', __('Flesch reading ease', 'botwriter'), 10, $st, $flesch . '/100',
        __('Aim for 60+ (plain English). Lower is harder to read.', 'botwriter'));

    // 2. Avg sentence length
    $asl = round($avg_words_per_sentence, 1);
    $st = ($asl > 0 && $asl <= 18) ? 'good' : (($asl <= 25) ? 'warn' : 'bad');
    $add('avg_sentence_len', __('Average sentence length', 'botwriter'), 8, $st, $asl . ' words',
        __('Keep most sentences under 20 words.', 'botwriter'));

    // 3. Long sentences
    $lr = round($long_ratio, 0);
    $st = ($lr <= 25) ? 'good' : (($lr <= 40) ? 'warn' : 'bad');
    $add('long_sentences', __('Long sentences (>20 words)', 'botwriter'), 6, $st, $lr . '%',
        __('Try to keep this under 25%.', 'botwriter'));

    // 4. Long paragraphs
    $lp = round($long_para_ratio, 0);
    $st = ($lp <= 10) ? 'good' : (($lp <= 25) ? 'warn' : 'bad');
    $add('long_paragraphs', __('Long paragraphs (>150 words)', 'botwriter'), 6, $st, $lp . '%',
        __('Break long paragraphs into shorter blocks.', 'botwriter'));

    // 5. Subheading distribution
    $st = ($worst_chunk_words <= 300) ? 'good' : (($worst_chunk_words <= 450) ? 'warn' : 'bad');
    $add('subheading_distribution', __('Subheading distribution', 'botwriter'), 8, $st, $worst_chunk_words . ' words',
        __('Add an H2/H3 every ~300 words to ease scanning.', 'botwriter'));

    // 6. Transition words
    $tr = round($tr_ratio, 0);
    $st = ($tr >= 30) ? 'good' : (($tr >= 15) ? 'warn' : 'bad');
    $add('transition_words', __('Transition words ratio', 'botwriter'), 6, $st, $tr . '%',
        __('Use transitions in at least 30% of sentences.', 'botwriter'));

    // 7. Passive voice (best-effort; English approximation).
    $pv = round($passive_ratio, 0);
    $st = ($pv <= 10) ? 'good' : (($pv <= 20) ? 'warn' : 'bad');
    $add('passive_voice', __('Passive voice', 'botwriter'), 6, $st, $pv . '%',
        __('Prefer active voice; keep passive under 10%.', 'botwriter'));

    // 8. Consecutive sentence starters
    $st = ($consec_max <= 2) ? 'good' : (($consec_max <= 3) ? 'warn' : 'bad');
    $add('consecutive_starters', __('Consecutive sentence starters', 'botwriter'), 4, $st, $consec_max . ' in a row',
        __('Vary how sentences start.', 'botwriter'));

    // 9. Word count baseline.
    $st = ($word_count >= 600) ? 'good' : (($word_count >= 300) ? 'warn' : 'bad');
    $add('word_count', __('Word count', 'botwriter'), 4, $st, $word_count . ' words',
        __('Aim for at least 600 words for in-depth content.', 'botwriter'));

    $score = $score_max > 0 ? (int) round(($score_total / $score_max) * 100) : 0;
    $grade = ($score >= 85) ? 'excellent' : (($score >= 70) ? 'good' : (($score >= 50) ? 'fair' : 'poor'));

    $report = array(
        'score'       => $score,
        'grade'       => $grade,
        'flesch'      => $flesch,
        'word_count'  => $word_count,
        'sentences'   => $sentence_count,
        'paragraphs'  => $para_count,
        'checks'      => $checks,
        'hash'        => $hash,
        'computed_at' => time(),
    );

    update_post_meta($post_id, '_botwriter_seo_readability_score', $score);
    update_post_meta($post_id, '_botwriter_seo_readability_report', $report);
    update_post_meta($post_id, '_botwriter_seo_readability_at', time());

    return $report;
}

/**
 * Map an SEO score "check" to good|warn|bad based on numeric hints (e.g. "1234 chars", "12 words").
 * Falls back to passed→good / fail→bad.
 */
function botwriter_seo_check_status($check) {
    $passed = !empty($check['passed']);
    $id = (string) ($check['id'] ?? '');
    $hint = (string) ($check['hint'] ?? '');
    $num = null;
    if (preg_match('/(-?\d+(?:\.\d+)?)/', $hint, $m)) { $num = (float) $m[1]; }

    $warn = function () { return 'warn'; };

    if ($passed) { return 'good'; }

    // Soft failures → warn.
    switch ($id) {
        case 'title_len':
            if ($num !== null && $num >= 20 && $num <= 75) { return $warn(); }
            break;
        case 'meta_length':
            if ($num !== null && $num >= 60 && $num <= 200) { return $warn(); }
            break;
        case 'word_count':
            if ($num !== null && $num >= 200) { return $warn(); }
            break;
        case 'images_alt':
            if ($num !== null && $num <= 2) { return $warn(); }
            break;
        case 'slug_length':
            if ($num !== null && $num >= 1 && $num <= 80) { return $warn(); }
            break;
        case 'headings_hierarchy':
        case 'external_links':
        case 'no_h1_in_content':
            return $warn();
    }
    return 'bad';
}

/**
 * Recompute readability whenever a post is saved.
 */
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    $allowed = function_exists('botwriter_seo_supported_post_types')
        ? botwriter_seo_supported_post_types()
        : array('post', 'page');
    if (!in_array($post->post_type, $allowed, true)) { return; }
    if ($post->post_status !== 'publish') { return; }
    botwriter_seo_compute_readability($post_id, true);
}, 35, 2);
