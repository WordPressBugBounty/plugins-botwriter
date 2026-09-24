<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_enqueue_scripts', function () {
    if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'botwriter_seo_rewrite') { return; }
    $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
    wp_enqueue_style('botwriter-seo-rewrite', $base . 'rewrite.css', array('botwriter-seo'), filemtime(dirname(__DIR__) . '/assets/rewrite.css'));
    wp_enqueue_script('botwriter-seo-rewrite', $base . 'rewrite.js', array('jquery', 'botwriter-seo'), filemtime(dirname(__DIR__) . '/assets/rewrite.js'), true);
    wp_localize_script('botwriter-seo-rewrite', 'BotwriterRewrite', array(
        'defaultPrompt' => botwriter_seo_rewrite_default_prompt(),
        'i18n' => array(
            'empty' => __('No matching content.', 'botwriter'),
            'error' => __('The request failed. Your saved proposals are still available.', 'botwriter'),
            'review' => __('Review', 'botwriter'),
            'approved' => __('Approved', 'botwriter'),
            'pending' => __('Pending review', 'botwriter'),
            'none' => __('No proposal', 'botwriter'),
            'selected' => __('Selected:', 'botwriter'),
            'page' => __('Page', 'botwriter'),
            'of' => __('of', 'botwriter'),
            'saved' => __('Saved.', 'botwriter'),
            'done' => __('Finished', 'botwriter'),
            'reviewReady' => __('Generated proposals are ready to review in Pending proposals.', 'botwriter'),
            'stopped' => __('Stopped after the current request.', 'botwriter'),
            'generating' => __('Generating', 'botwriter'),
            'applying' => __('Applying', 'botwriter'),
            'success' => __('Success', 'botwriter'),
            'failed' => __('Failed', 'botwriter'),
            'confirmApply' => __('Overwrite the selected approved posts? Backups will be saved in the history below.', 'botwriter'),
            'confirmGenerate' => __('Generate proposals for the selected posts? AI usage may incur costs. Existing proposals for these posts will be replaced.', 'botwriter'),
            'confirmDiscard' => __('Discard this proposal? The original post will not change.', 'botwriter'),
            'confirmRegenerate' => __('Replace this proposal with a new one using the instructions below?', 'botwriter'),
            'conflict' => __('The original has changed. Regenerate before applying.', 'botwriter'),
            'original' => __('Original', 'botwriter'),
            'proposal' => __('Proposal', 'botwriter'),
            'fragment' => __('Text fragment', 'botwriter'),
            'allTerms' => __('All categories / terms', 'botwriter'),
            'unsaved' => __('There are unsaved edits or an active operation.', 'botwriter'),
        ),
    ));
});

function botwriter_seo_page_rewrite() {
    $config = botwriter_seo_ai_config();
    $available = method_exists('WP_HTML_Tag_Processor', 'set_modifiable_text');
    $types = botwriter_seo_rewrite_types();
    unset($types['attachment']);
    ?>
    <div id="bw-rewrite" data-available="<?php echo $available ? '1' : '0'; ?>">
        <header class="bw-rewrite-heading">
            <h2><?php esc_html_e('Overwrite posts', 'botwriter'); ?></h2>
            <a href="<?php echo esc_url(admin_url('admin.php?page=botwriter_seo_settings')); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span> <?php echo esc_html(botwriter_seo_bulk_provider_label($config['provider']) . ' / ' . $config['model']); ?></a>
        </header>
        <?php if (!$available) : ?><p class="notice notice-warning"><?php esc_html_e('Rewriting requires WordPress 6.7 or newer.', 'botwriter'); ?></p><?php endif; ?>
        <div class="bw-rewrite-tabs" role="tablist" aria-label="<?php esc_attr_e('Rewrite workflow', 'botwriter'); ?>">
            <button type="button" role="tab" id="bw-rewrite-select-tab" aria-selected="true" aria-controls="bw-rewrite-library" data-view="select"><?php esc_html_e('Select content', 'botwriter'); ?></button>
            <button type="button" role="tab" id="bw-rewrite-pending-tab" aria-selected="false" aria-controls="bw-rewrite-library" data-view="pending"><?php esc_html_e('Pending proposals', 'botwriter'); ?></button>
        </div>
        <section id="bw-rewrite-library" role="tabpanel" aria-labelledby="bw-rewrite-select-tab">
            <form id="bw-rewrite-filters" class="bw-rewrite-toolbar">
                <label><?php esc_html_e('Search', 'botwriter'); ?><input type="search" id="bw-rewrite-search"></label>
                <label id="bw-rewrite-type-label"><?php esc_html_e('Content type', 'botwriter'); ?><select id="bw-rewrite-type">
                    <?php foreach ($types as $type) : ?><option value="<?php echo esc_attr($type->name); ?>"><?php echo esc_html($type->labels->name); ?></option><?php endforeach; ?>
                </select></label>
                <label><?php esc_html_e('Status', 'botwriter'); ?><select id="bw-rewrite-status"><option value=""><?php esc_html_e('All statuses', 'botwriter'); ?></option>
                    <?php foreach (array('publish', 'draft', 'pending', 'private', 'future') as $status) : $object = get_post_status_object($status); ?>
                        <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($object->label); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label id="bw-rewrite-term-label"><?php esc_html_e('Category / term', 'botwriter'); ?><select id="bw-rewrite-term"><option value=""><?php esc_html_e('All categories / terms', 'botwriter'); ?></option>
                    <?php foreach ($types as $type) :
                        $taxonomies = get_object_taxonomies($type->name, 'objects');
                        foreach ($taxonomies as $taxonomy) :
                            if (!$taxonomy->show_ui || !$taxonomy->hierarchical) { continue; }
                            $terms = get_terms(array('taxonomy' => $taxonomy->name, 'hide_empty' => false, 'number' => 200));
                            if (is_wp_error($terms)) { continue; }
                            foreach ($terms as $term) : ?>
                                <option data-type="<?php echo esc_attr($type->name); ?>" value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html($taxonomy->labels->singular_name . ': ' . $term->name); ?></option>
                            <?php endforeach;
                        endforeach;
                    endforeach; ?>
                </select></label>
                <button type="submit" class="button"><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php esc_html_e('Search', 'botwriter'); ?></button>
            </form>
            <div class="bw-rewrite-table-wrap"><table class="widefat striped bw-rewrite-table">
                <thead><tr><td class="check-column"><input id="bw-rewrite-all" type="checkbox" aria-label="<?php esc_attr_e('Select this page', 'botwriter'); ?>"></td><th><?php esc_html_e('Title', 'botwriter'); ?></th><th><?php esc_html_e('Type / status', 'botwriter'); ?></th><th><?php esc_html_e('Proposal', 'botwriter'); ?></th><th><?php esc_html_e('Actions', 'botwriter'); ?></th></tr></thead>
                <tbody id="bw-rewrite-rows"></tbody>
            </table></div>
            <div class="bw-rewrite-toolbar bw-rewrite-pager">
                <span id="bw-rewrite-count" aria-live="polite"></span>
                <button type="button" class="button" id="bw-rewrite-clear"><?php esc_html_e('Clear selection', 'botwriter'); ?></button>
                <span id="bw-rewrite-page"></span>
                <button type="button" class="button" id="bw-rewrite-prev" title="<?php esc_attr_e('Previous page', 'botwriter'); ?>" aria-label="<?php esc_attr_e('Previous page', 'botwriter'); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
                <button type="button" class="button" id="bw-rewrite-next" title="<?php esc_attr_e('Next page', 'botwriter'); ?>" aria-label="<?php esc_attr_e('Next page', 'botwriter'); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
            </div>
        </section>
        <section id="bw-rewrite-instructions">
            <h3><label for="bw-rewrite-prompt"><?php esc_html_e('Rewrite instructions', 'botwriter'); ?></label></h3>
            <textarea id="bw-rewrite-prompt" rows="5" maxlength="8000"><?php echo esc_textarea(get_option('botwriter_seo_rewrite_prompt', botwriter_seo_rewrite_default_prompt())); ?></textarea>
            <div class="bw-rewrite-toolbar">
                <button type="button" class="button" id="bw-rewrite-save-template"><span class="dashicons dashicons-saved" aria-hidden="true"></span> <?php esc_html_e('Save as default', 'botwriter'); ?></button>
                <button type="button" class="button" id="bw-rewrite-reset-template"><span class="dashicons dashicons-undo" aria-hidden="true"></span> <?php esc_html_e('Restore original instructions', 'botwriter'); ?></button>
                <button type="button" class="button button-primary" id="bw-rewrite-generate" disabled><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e('Generate proposals', 'botwriter'); ?></button>
            </div>
        </section>
        <div class="bw-rewrite-toolbar">
            <button type="button" class="button button-primary" id="bw-rewrite-apply" hidden disabled><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e('Apply selected approved proposals', 'botwriter'); ?></button>
            <button type="button" class="button" id="bw-rewrite-stop" hidden><span class="dashicons dashicons-controls-pause" aria-hidden="true"></span> <?php esc_html_e('Stop after current post', 'botwriter'); ?></button>
        </div>
        <div id="bw-rewrite-message" role="status" aria-live="polite"></div>
        <progress id="bw-rewrite-progress" max="1" value="0" hidden></progress>
        <ul id="bw-rewrite-results" aria-live="polite"></ul>
        <dialog id="bw-rewrite-dialog" aria-labelledby="bw-rewrite-dialog-title">
            <div class="bw-rewrite-heading"><h2 id="bw-rewrite-dialog-title"></h2><button type="button" class="button" id="bw-rewrite-close" aria-label="<?php esc_attr_e('Close', 'botwriter'); ?>" title="<?php esc_attr_e('Close', 'botwriter'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
            <div id="bw-rewrite-conflict" role="alert" hidden></div>
            <div class="bw-rewrite-comparison"><div><h3><?php esc_html_e('Original', 'botwriter'); ?></h3><iframe id="bw-rewrite-original-preview" sandbox title="<?php esc_attr_e('Original', 'botwriter'); ?>"></iframe></div><div><h3><?php esc_html_e('Proposal', 'botwriter'); ?></h3><iframe id="bw-rewrite-preview" sandbox title="<?php esc_attr_e('Proposal', 'botwriter'); ?>"></iframe></div></div>
            <details><summary><?php esc_html_e('Edit proposal text', 'botwriter'); ?></summary><div id="bw-rewrite-fragments"></div></details>
            <label for="bw-rewrite-detail-prompt"><?php esc_html_e('Rewrite instructions', 'botwriter'); ?></label><textarea id="bw-rewrite-detail-prompt" rows="4" maxlength="8000"></textarea>
            <div id="bw-rewrite-detail-message" role="status"></div>
            <div class="bw-rewrite-toolbar">
                <button type="button" class="button" id="bw-rewrite-regenerate"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e('Regenerate', 'botwriter'); ?></button>
                <button type="button" class="button" id="bw-rewrite-discard"><span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e('Discard', 'botwriter'); ?></button>
                <button type="button" class="button" id="bw-rewrite-save-proposal"><?php esc_html_e('Save proposal', 'botwriter'); ?></button>
                <button type="button" class="button button-primary" id="bw-rewrite-approve"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e('Approve proposal', 'botwriter'); ?></button>
            </div>
        </dialog>
    </div>
    <?php botwriter_seo_render_bulk_undo_history_section();
}