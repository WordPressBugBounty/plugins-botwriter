<?php
/**
 * BotWriter SEO — Per-post report renderer + AJAX endpoint for the modal viewer.
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('botwriter_seo_status_icon')) {
    /**
     * Map a status to a Dashicons class.
     */
    function botwriter_seo_status_icon($status) {
        switch ($status) {
            case 'good': return 'dashicons-yes-alt';
            case 'warn': return 'dashicons-warning';
            case 'bad':  return 'dashicons-dismiss';
        }
        return 'dashicons-marker';
    }
}

if (!function_exists('botwriter_seo_grade_label')) {
    function botwriter_seo_grade_label($grade) {
        $map = array(
            'excellent' => __('Excellent', 'botwriter'),
            'good'      => __('Good', 'botwriter'),
            'fair'      => __('Needs work', 'botwriter'),
            'poor'      => __('Poor', 'botwriter'),
            'n/a'       => __('Not analyzed', 'botwriter'),
        );
        return $map[$grade] ?? ucfirst((string) $grade);
    }
}

/**
 * Render the full HTML report for one post (used by the modal viewer).
 */
function botwriter_seo_render_post_report_html($post_id) {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post) {
        return '<div class="bw-empty"><span class="dashicons dashicons-warning"></span><p>' . esc_html__('Post not found.', 'botwriter') . '</p></div>';
    }

    $seo  = botwriter_seo_compute_score($post_id);
    $read = botwriter_seo_compute_readability($post_id);

    $seo_score   = (int) ($seo['score'] ?? 0);
    $seo_grade   = (string) ($seo['grade'] ?? 'n/a');
    $read_score  = (int) ($read['score'] ?? 0);
    $read_grade  = (string) ($read['grade'] ?? 'n/a');

    // Counters by status for the SEO section
    $seo_counts = array('good' => 0, 'warn' => 0, 'bad' => 0);
    foreach ((array) ($seo['checks'] ?? array()) as $c) {
        $st = botwriter_seo_check_status($c);
        $seo_counts[$st] = ($seo_counts[$st] ?? 0) + 1;
    }
    $read_counts = array('good' => 0, 'warn' => 0, 'bad' => 0);
    foreach ((array) ($read['checks'] ?? array()) as $c) {
        $st = (string) ($c['status'] ?? 'bad');
        $read_counts[$st] = ($read_counts[$st] ?? 0) + 1;
    }

    ob_start(); ?>
    <div class="bw-report">
        <header class="bw-report-header">
            <div class="bw-report-title">
                <span class="dashicons dashicons-analytics"></span>
                <div>
                    <h2><?php echo esc_html(get_the_title($post_id) ?: sprintf('#%d', $post_id)); ?></h2>
                    <p class="bw-muted">
                        <span class="dashicons dashicons-admin-post"></span> <?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type); ?>
                        &nbsp;·&nbsp;
                        <span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html(get_the_date('', $post_id)); ?>
                        &nbsp;·&nbsp;
                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Edit', 'botwriter'); ?></a>
                        &nbsp;·&nbsp;
                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span> <?php esc_html_e('View', 'botwriter'); ?></a>
                    </p>
                </div>
            </div>
            <div class="bw-report-scores">
                <div class="bw-score-ring bw-grade-<?php echo esc_attr($seo_grade); ?>">
                    <div class="ring" data-ring-progress="<?php echo esc_attr((string) ((int) $seo_score)); ?>">
                        <span class="num"><?php echo (int) $seo_score; ?></span>
                    </div>
                    <div class="lbl"><span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO', 'botwriter'); ?></div>
                    <div class="grade"><?php echo esc_html(botwriter_seo_grade_label($seo_grade)); ?></div>
                </div>
                <div class="bw-score-ring bw-grade-<?php echo esc_attr($read_grade); ?>">
                    <div class="ring" data-ring-progress="<?php echo esc_attr((string) ((int) $read_score)); ?>">
                        <span class="num"><?php echo (int) $read_score; ?></span>
                    </div>
                    <div class="lbl"><span class="dashicons dashicons-book"></span> <?php esc_html_e('Readability', 'botwriter'); ?></div>
                    <div class="grade"><?php echo esc_html(botwriter_seo_grade_label($read_grade)); ?></div>
                </div>
            </div>
        </header>

        <div class="bw-report-tabs">
            <a href="#bw-tab-seo" class="is-active" data-tab="bw-tab-seo"><span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO Analysis', 'botwriter'); ?></a>
            <a href="#bw-tab-read" data-tab="bw-tab-read"><span class="dashicons dashicons-book"></span> <?php esc_html_e('Readability', 'botwriter'); ?></a>
            <a href="#bw-tab-stats" data-tab="bw-tab-stats"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Content Stats', 'botwriter'); ?></a>
        </div>

        <section class="bw-tab is-active" id="bw-tab-seo">
            <div class="bw-summary-row">
                <span class="bw-pill good"><span class="dashicons dashicons-yes-alt"></span> <?php echo (int) $seo_counts['good']; ?> <?php esc_html_e('passed', 'botwriter'); ?></span>
                <span class="bw-pill warn"><span class="dashicons dashicons-warning"></span> <?php echo (int) $seo_counts['warn']; ?> <?php esc_html_e('to improve', 'botwriter'); ?></span>
                <span class="bw-pill bad"><span class="dashicons dashicons-dismiss"></span> <?php echo (int) $seo_counts['bad']; ?> <?php esc_html_e('issues', 'botwriter'); ?></span>
            </div>
            <ul class="bw-report-checks">
                <?php foreach ((array) ($seo['checks'] ?? array()) as $c) :
                    $st = botwriter_seo_check_status($c);
                    $icon = botwriter_seo_status_icon($st);
                ?>
                    <li class="bw-check bw-status-<?php echo esc_attr($st); ?>">
                        <span class="dashicons <?php echo esc_attr($icon); ?> bw-check-icon"></span>
                        <div class="bw-check-body">
                            <div class="bw-check-label"><?php echo esc_html($c['label']); ?></div>
                            <?php if (!empty($c['hint'])) : ?>
                                <div class="bw-check-hint"><?php echo esc_html($c['hint']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ((int) ($c['weight'] ?? 0) > 0) : ?>
                            <span class="bw-weight" title="<?php esc_attr_e('Weight', 'botwriter'); ?>"><?php echo (int) ($c['weight'] ?? 0); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="bw-tab" id="bw-tab-read" hidden>
            <div class="bw-summary-row">
                <span class="bw-pill good"><span class="dashicons dashicons-yes-alt"></span> <?php echo (int) $read_counts['good']; ?> <?php esc_html_e('great', 'botwriter'); ?></span>
                <span class="bw-pill warn"><span class="dashicons dashicons-warning"></span> <?php echo (int) $read_counts['warn']; ?> <?php esc_html_e('ok', 'botwriter'); ?></span>
                <span class="bw-pill bad"><span class="dashicons dashicons-dismiss"></span> <?php echo (int) $read_counts['bad']; ?> <?php esc_html_e('hard', 'botwriter'); ?></span>
            </div>
            <ul class="bw-report-checks">
                <?php foreach ((array) ($read['checks'] ?? array()) as $c) :
                    $st = (string) ($c['status'] ?? 'bad');
                    $icon = botwriter_seo_status_icon($st);
                ?>
                    <li class="bw-check bw-status-<?php echo esc_attr($st); ?>">
                        <span class="dashicons <?php echo esc_attr($icon); ?> bw-check-icon"></span>
                        <div class="bw-check-body">
                            <div class="bw-check-label">
                                <?php echo esc_html($c['label']); ?>
                                <?php if (!empty($c['value'])) : ?>
                                    <span class="bw-tag"><?php echo esc_html($c['value']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($c['hint'])) : ?>
                                <div class="bw-check-hint"><?php echo esc_html($c['hint']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="bw-weight" title="<?php esc_attr_e('Weight', 'botwriter'); ?>"><?php echo (int) ($c['weight'] ?? 0); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="bw-tab" id="bw-tab-stats" hidden>
            <?php
            $stats = (array) ($seo['stats'] ?? array());
            $faq_mode = (string) ($stats['faq_mode'] ?? 'none');
            $faq_label = __('None', 'botwriter');
            if ($faq_mode === 'visible_schema') {
                $faq_label = __('Visible + schema', 'botwriter');
            } elseif ($faq_mode === 'schema_only') {
                $faq_label = __('Schema only', 'botwriter');
            }
            $embedding_label = !empty($stats['embedding_indexed']) ? __('Indexed', 'botwriter') : __('Missing', 'botwriter');
            $kv = array(
                array('dashicons-text', __('Word count', 'botwriter'), (int) ($seo['word_count'] ?? 0)),
                array('dashicons-editor-paragraph', __('Paragraphs', 'botwriter'), (int) ($stats['paragraphs'] ?? 0)),
                array('dashicons-heading', __('H2 headings', 'botwriter'), (int) ($stats['h2'] ?? 0)),
                array('dashicons-heading', __('H3 headings', 'botwriter'), (int) ($stats['h3'] ?? 0)),
                array('dashicons-admin-links', __('Internal links', 'botwriter'), (int) ($stats['links_internal'] ?? 0)),
                array('dashicons-external', __('External links', 'botwriter'), (int) ($stats['links_external'] ?? 0)),
                array('dashicons-format-image', __('Images', 'botwriter'), (int) ($stats['images'] ?? 0)),
                array('dashicons-warning', __('Images missing alt', 'botwriter'), (int) ($stats['images_missing_alt'] ?? 0)),
                array('dashicons-editor-textcolor', __('SEO title length', 'botwriter'), (int) ($seo['title_len'] ?? 0)),
                array('dashicons-editor-quote', __('Meta description length', 'botwriter'), (int) ($seo['meta_len'] ?? 0)),
                array('dashicons-format-chat', __('FAQ items', 'botwriter'), (int) ($stats['faq_count'] ?? 0)),
                array('dashicons-feedback', __('FAQ output', 'botwriter'), $faq_label),
                array('dashicons-networking', __('Semantic index', 'botwriter'), $embedding_label),
                array('dashicons-book', __('Flesch reading ease', 'botwriter'), (float) ($read['flesch'] ?? 0)),
                array('dashicons-editor-alignleft', __('Sentences', 'botwriter'), (int) ($read['sentences'] ?? 0)),
            );
            ?>
            <div class="bw-stats-grid">
                <?php foreach ($kv as $row) : ?>
                    <div class="bw-stat-card">
                        <span class="dashicons <?php echo esc_attr($row[0]); ?>"></span>
                        <div class="bw-stat-body">
                            <div class="bw-stat-value"><?php echo esc_html((string) $row[2]); ?></div>
                            <div class="bw-stat-label"><?php echo esc_html($row[1]); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($seo['seo_title'])) : ?>
                <div class="bw-notice info">
                    <span class="dashicons dashicons-editor-textcolor"></span>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: resolved SEO title. */
                            __('Resolved SEO title: %s', 'botwriter'),
                            '<strong>' . esc_html((string) $seo['seo_title']) . '</strong>'
                        ),
                        array('strong' => array())
                    );
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($stats['embedding_indexed'])) : ?>
                <div class="bw-notice info">
                    <span class="dashicons dashicons-networking"></span>
                    <?php
                    $model = trim((string) ($stats['embedding_model'] ?? ''));
                    $updated_at = (int) ($stats['embedding_updated_at'] ?? 0);
                    $parts = array();
                    if ($model !== '') {
                        /* translators: %s: embedding model name */
                        $parts[] = sprintf(__('model: %s', 'botwriter'), $model);
                    }
                    if ($updated_at > 0) {
                        /* translators: %s: localized embedding index update date/time */
                        $parts[] = sprintf(__('updated: %s', 'botwriter'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $updated_at));
                    }
                    echo esc_html(implode(' · ', $parts));
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($seo['primary_keyword'])) : ?>
                <div class="bw-notice info">
                    <span class="dashicons dashicons-tag"></span>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: primary keyword. */
                            __('Primary keyword: %s', 'botwriter'),
                            '<strong>' . esc_html($seo['primary_keyword']) . '</strong>'
                        ),
                        array('strong' => array())
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX endpoint that returns the rendered modal HTML for a given post.
 */
add_action('wp_ajax_bw_seo_post_report', function () {
    check_ajax_referer('bw_seo_admin', '_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'forbidden'), 403); }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id <= 0) { wp_send_json_error(array('message' => 'invalid post')); }
    wp_send_json_success(array('html' => botwriter_seo_render_post_report_html($post_id)));
});
