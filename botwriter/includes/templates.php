<?php
/**
 * BotWriter Templates Management
 * 
 * Handles CRUD operations for prompt templates and provides the admin UI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the templates management page
 */
function botwriter_templates_page_handler() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    // Handle form submissions
    $message = '';
    $message_type = 'updated';
    
    // Handle set default action
    if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'set_default' && isset($_GET['template_id'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'botwriter_set_default_template')) {
            wp_die(__('Security check failed', 'botwriter'));
        }
        
        $template_id = intval($_GET['template_id']);
        $result = botwriter_set_default_template($template_id);
        
        if ($result !== false) {
            $message = __('Default template updated successfully.', 'botwriter');
        } else {
            $message = __('Error setting default template.', 'botwriter');
            $message_type = 'error';
        }
    }
    
    // Handle delete action
    if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'delete' && isset($_GET['template_id'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'botwriter_delete_template')) {
            wp_die(__('Security check failed', 'botwriter'));
        }
        
        $template_id = intval($_GET['template_id']);
        $result = botwriter_delete_template($template_id);
        
        if ($result === false) {
            $message = __('Cannot delete the default template.', 'botwriter');
            $message_type = 'error';
        } else {
            $message = __('Template deleted successfully.', 'botwriter');
        }
    }
    
    // Handle save action
    if (isset($_POST['botwriter_save_template']) && isset($_POST['_wpnonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'botwriter_save_template')) {
            wp_die(__('Security check failed', 'botwriter'));
        }
        
        $template_data = [
            'id' => isset($_POST['template_id']) ? intval($_POST['template_id']) : 0,
            'name' => isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '',
            'content' => isset($_POST['template_content']) ? wp_kses_post(wp_unslash($_POST['template_content'])) : ''
        ];
        
        if (empty($template_data['name'])) {
            $message = __('Template name is required.', 'botwriter');
            $message_type = 'error';
        } else {
            $result = botwriter_save_template($template_data);
            if ($result !== false) {
                $message = __('Template saved successfully.', 'botwriter');
            } else {
                $message = __('Error saving template.', 'botwriter');
                $message_type = 'error';
            }
        }
    }
    
    // Check if we're editing a template
    $editing_template = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['template_id'])) {
        $editing_template = botwriter_get_template(intval($_GET['template_id']));
    }
    
    // Get all templates
    $templates = botwriter_get_all_templates();
    
    // Ensure default template exists
    if (empty($templates)) {
        botwriter_insert_default_template();
        $templates = botwriter_get_all_templates();
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Prompt Templates', 'botwriter'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=botwriter_templates&action=new')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'botwriter'); ?>
        </a>
        <hr class="wp-header-end">
        
        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type === 'error' ? 'error' : 'success'); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit'])): ?>
            <!-- Template Editor Form -->
            <?php botwriter_render_template_editor($editing_template); ?>
        <?php else: ?>
            <!-- Templates List -->
            <?php botwriter_render_templates_list($templates); ?>
        <?php endif; ?>
        
        <!-- Available Placeholders Reference -->
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2><?php esc_html_e('Available Placeholders', 'botwriter'); ?></h2>
            <p><?php esc_html_e('Use these placeholders in your templates. They will be replaced with actual values when generating content.', 'botwriter'); ?></p>
            
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Placeholder', 'botwriter'); ?></th>
                        <th><?php esc_html_e('Description', 'botwriter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>{{post_length}}</code></td>
                        <td><?php esc_html_e('Number of words for the article (e.g., 800, 1200)', 'botwriter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{{post_language}}</code></td>
                        <td><?php esc_html_e('Language name (e.g., English, Spanish)', 'botwriter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{{writer_style}}</code></td>
                        <td><?php esc_html_e('Writing style based on selected writer persona', 'botwriter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{{prompt_or_keywords}}</code></td>
                        <td><?php esc_html_e('User-provided prompt or keywords for content generation', 'botwriter'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Render the template editor form
 */
function botwriter_render_template_editor($template = null) {
    $is_new = empty($template);
    $is_default = !$is_new && !empty($template['is_default']);
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('botwriter_save_template'); ?>
        
        <?php if (!$is_new): ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr($template['id']); ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="template_name"><?php esc_html_e('Template Name', 'botwriter'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="template_name" 
                           id="template_name" 
                           class="regular-text" 
                           value="<?php echo esc_attr($template['name'] ?? ''); ?>"
                           <?php echo $is_default ? 'readonly' : ''; ?>
                           required>
                    <?php if ($is_default): ?>
                        <p class="description"><?php esc_html_e('Default template name cannot be changed.', 'botwriter'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_content"><?php esc_html_e('Template Content', 'botwriter'); ?></label>
                </th>
                <td>
                    <textarea name="template_content" 
                              id="template_content" 
                              rows="20" 
                              class="large-text code"
                              style="font-family: monospace; width: 100%;"><?php echo esc_textarea($template['content'] ?? botwriter_get_default_template_content()); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Use placeholders like {{post_length}} to insert dynamic values. See the reference below.', 'botwriter'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" 
                   name="botwriter_save_template" 
                   class="button button-primary" 
                   value="<?php echo $is_new ? esc_attr__('Create Template', 'botwriter') : esc_attr__('Save Changes', 'botwriter'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=botwriter_templates')); ?>" class="button">
                <?php esc_html_e('Cancel', 'botwriter'); ?>
            </a>
        </p>
    </form>
    <?php
}

/**
 * Render the templates list table
 */
function botwriter_render_templates_list($templates) {
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column"><?php esc_html_e('Name', 'botwriter'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Type', 'botwriter'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Created', 'botwriter'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Actions', 'botwriter'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No templates found.', 'botwriter'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=botwriter_templates&action=edit&template_id=' . $template['id'])); ?>">
                                    <?php echo esc_html($template['name']); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php if ($template['is_default']): ?>
                                <span class="dashicons dashicons-star-filled" style="color: #f0c33c;"></span>
                                <?php esc_html_e('Default', 'botwriter'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Custom', 'botwriter'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($template['created_at']))); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=botwriter_templates&action=edit&template_id=' . $template['id'])); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'botwriter'); ?>
                            </a>
                            <?php if (!$template['is_default']): ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=botwriter_templates&action=set_default&template_id=' . $template['id']), 'botwriter_set_default_template')); ?>" 
                                   class="button button-small button-primary">
                                    <?php esc_html_e('Set as Default', 'botwriter'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=botwriter_templates&action=delete&template_id=' . $template['id']), 'botwriter_delete_template')); ?>" 
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this template?', 'botwriter'); ?>');">
                                    <?php esc_html_e('Delete', 'botwriter'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Get templates for dropdown select in task forms
 */
function botwriter_get_templates_dropdown_options($selected_id = null) {
    $templates = botwriter_get_all_templates();
    $options = '<option value="">' . esc_html__('-- Use Default Template --', 'botwriter') . '</option>';
    
    foreach ($templates as $template) {
        $selected = ($selected_id && $selected_id == $template['id']) ? 'selected' : '';
        $label = $template['name'];
        if ($template['is_default']) {
            $label .= ' (' . __('Default', 'botwriter') . ')';
        }
        $options .= sprintf(
            '<option value="%d" %s>%s</option>',
            esc_attr($template['id']),
            $selected,
            esc_html($label)
        );
    }
    
    return $options;
}
