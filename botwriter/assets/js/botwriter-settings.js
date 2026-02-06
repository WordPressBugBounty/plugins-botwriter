/**
 * BotWriter Settings Page JavaScript
 * Handles AJAX auto-save, tab switching, and UI interactions
 */
jQuery(document).ready(function($) {
    var saveTimeout = null;
    var $saveStatus = $('#botwriter-save-status');
    var ajaxUrl = botwriter_settings.ajax_url;
    var nonce = $('#botwriter_settings_nonce').val();
    var i18n = botwriter_settings.i18n;

    // Show save status indicator
    function showSaveStatus(status, message) {
        $saveStatus.removeClass('saving saved error').addClass(status).text(message);
        
        if (status === 'saved') {
            setTimeout(function() {
                $saveStatus.removeClass('saved').text('');
            }, 2000);
        }
    }

    // Save a single field via AJAX
    function saveField(fieldName, fieldValue) {
        showSaveStatus('saving', i18n.saving);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'botwriter_save_settings',
                nonce: nonce,
                field: fieldName,
                value: fieldValue
            },
            success: function(response) {
                if (response.success) {
                    showSaveStatus('saved', i18n.saved);
                } else {
                    showSaveStatus('error', response.data.message || i18n.error);
                }
            },
            error: function() {
                showSaveStatus('error', i18n.connection_error);
            }
        });
    }

    // Handle input changes with debounce
    function handleFieldChange(element) {
        var $el = $(element);
        var fieldName = $el.attr('name');
        var fieldValue;

        if ($el.is(':checkbox')) {
            fieldValue = $el.is(':checked') ? $el.val() : '0';
        } else {
            fieldValue = $el.val();
        }

        // Clear previous timeout
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }

        // Debounce for text inputs (wait 500ms after user stops typing)
        if ($el.is('input[type="text"], input[type="password"], input[type="number"], input[type="url"], textarea')) {
            saveTimeout = setTimeout(function() {
                saveField(fieldName, fieldValue);
            }, 500);
        } else {
            // Immediately save for selects and checkboxes
            saveField(fieldName, fieldValue);
        }
    }

    // Attach change handlers to all form fields
    $(document).on('change', 'select[name^="botwriter_"], input[type="checkbox"][name^="botwriter_"]', function() {
        handleFieldChange(this);
    });

    // For text inputs, use keyup with debounce
    $(document).on('keyup', 'input[type="text"][name^="botwriter_"], input[type="password"][name^="botwriter_"], input[type="number"][name^="botwriter_"], input[type="url"][name^="botwriter_"]', function() {
        handleFieldChange(this);
    });

    // Also save on blur for text inputs (in case user tabs away)
    $(document).on('blur', 'input[type="text"][name^="botwriter_"], input[type="password"][name^="botwriter_"], input[type="number"][name^="botwriter_"], input[type="url"][name^="botwriter_"]', function() {
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }
        var fieldName = $(this).attr('name');
        var fieldValue = $(this).val();
        if (fieldValue) {
            saveField(fieldName, fieldValue);
        }
    });

    // Main tab switching
    $('.botwriter-main-tabs .main-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('main-tab');
        $('.botwriter-main-tabs .main-tab').removeClass('main-tab-active');
        $(this).addClass('main-tab-active');
        $('.botwriter-main-tab-content').removeClass('active');
        $('#main-tab-' + tabId).addClass('active');
    });

    // Go to General Settings link (from SSL warning in custom providers)
    $(document).on('click', '.go-to-general-settings', function(e) {
        e.preventDefault();
        $('.botwriter-main-tabs .main-tab').removeClass('main-tab-active');
        $('.botwriter-main-tabs .main-tab[data-main-tab="general"]').addClass('main-tab-active');
        $('.botwriter-main-tab-content').removeClass('active');
        $('#main-tab-general').addClass('active');
        // Scroll to top of settings
        $('html, body').animate({ scrollTop: $('.botwriter-main-tabs').offset().top - 50 }, 300);
    });

    // Toggle API key visibility
    $(document).on('click', '.toggle-api-key', function() {
        var input = $(this).closest('.api-key-wrapper').find('.api-key-input');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).text(i18n.hide);
        } else {
            input.attr('type', 'password');
            $(this).text(i18n.show);
        }
    });

    // Provider select change - show corresponding content
    $('#botwriter_text_provider').on('change', function() {
        var provider = $(this).val();
        $('#main-tab-text .provider-content').removeClass('active');
        $('#provider-' + provider).addClass('active');
        
        // Apply image provider coherence
        enforceImageProviderCoherence(provider);
    });

    $('#botwriter_image_provider').on('change', function() {
        var provider = $(this).val();
        $('#main-tab-images .provider-content').removeClass('active');
        $('#image-provider-' + provider).addClass('active');
    });

    /**
     * Enforce image provider coherence when text provider is "custom"
     * When using Custom Provider for text, only Custom or None can be used for images
     */
    function enforceImageProviderCoherence(textProvider) {
        var $imageSelect = $('#botwriter_image_provider');
        var $imageOptions = $imageSelect.find('option');
        var $coherenceWarning = $('#custom-image-coherence-warning');
        
        if (textProvider === 'custom') {
            // Disable all cloud providers, only allow custom and none
            $imageOptions.each(function() {
                var val = $(this).val();
                if (val !== 'custom' && val !== 'none') {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
            
            // If current selection is a cloud provider, switch to 'none'
            var currentImage = $imageSelect.val();
            if (currentImage !== 'custom' && currentImage !== 'none') {
                $imageSelect.val('none').trigger('change');
                // Also save the change
                saveField('botwriter_image_provider', 'none');
            }
            
            // Show warning if not already present
            if ($coherenceWarning.length === 0) {
                var warningHtml = '<div id="custom-image-coherence-warning" class="notice notice-warning inline" style="margin: 10px 0; padding: 10px 15px;">' +
                    '<p><span class="dashicons dashicons-warning" style="color: #dba617;"></span> ' +
                    '<strong>' + (i18n.custom_image_warning_title || 'Custom Provider Mode') + '</strong></p>' +
                    '<p>' + (i18n.custom_image_warning_text || 'When using Custom Provider for text, image generation is limited to Custom Provider or None. Cloud image providers (DALL-E, Gemini, etc.) are not available in this mode.') + '</p>' +
                    '</div>';
                $imageSelect.closest('.form-row').after(warningHtml);
            }
        } else {
            // Enable all image provider options
            $imageOptions.prop('disabled', false);
            
            // Remove warning
            $coherenceWarning.remove();
        }
    }

    // Apply coherence on page load
    enforceImageProviderCoherence($('#botwriter_text_provider').val());

    // Image Size/Format Preview
    var sizeSpecs = {
        landscape: {
            dalle: '1792×1024',
            fal: '1344×768',
            stability: '1344×768',
            ideogram: '1280×720',
            replicate: '1344×768',
            cloudflare: '1024×1024'
        },
        square: {
            dalle: '1024×1024',
            fal: '1024×1024',
            stability: '1024×1024',
            ideogram: '1024×1024',
            replicate: '1024×1024',
            cloudflare: '1024×1024'
        },
        portrait: {
            dalle: '1024×1792',
            fal: '768×1344',
            stability: '768×1344',
            ideogram: '720×1280',
            replicate: '768×1344',
            cloudflare: '1024×1024'
        }
    };

    function updateSizePreview() {
        var size = $('#image_size_select').val();
        var preview = $('#format_preview .preview-box');
        
        if (!size || !preview.length) return;
        
        preview.removeClass('landscape square portrait').addClass(size);
        
        if (size === 'landscape') {
            preview.html('16:9');
        } else if (size === 'square') {
            preview.html('1:1');
        } else {
            preview.html('9:16');
        }

        // Update specs table
        var specs = sizeSpecs[size];
        if (specs) {
            $('#dalle_size').text(specs.dalle);
            $('#fal_size').text(specs.fal);
            $('#stability_size').text(specs.stability);
            $('#ideogram_size').text(specs.ideogram);
            $('#replicate_size').text(specs.replicate);
            $('#cloudflare_size').text(specs.cloudflare);
        }
    }

    $('#image_size_select').on('change', updateSizePreview);
    updateSizePreview(); // Initialize

    // Image Quality Preview
    var qualitySpecs = {
        low: {
            dalle: 'standard',
            fal: '15 steps',
            stability: 'sd3.5-large-turbo',
            ideogram: 'V_2_TURBO',
            replicate: '15 steps',
            cloudflare: '4 steps'
        },
        medium: {
            dalle: 'standard',
            fal: '25 steps',
            stability: 'sd3.5-large',
            ideogram: 'V_2',
            replicate: '28 steps',
            cloudflare: '4 steps'
        },
        high: {
            dalle: 'hd',
            fal: '40 steps',
            stability: 'sd3.5-large',
            ideogram: 'V_2',
            replicate: '50 steps',
            cloudflare: '8 steps'
        }
    };

    var qualityCosts = {
        low: {
            dalle: '$0.04',
            fal: '$0.02',
            stability: '$0.02',
            ideogram: '$0.02',
            replicate: '$0.003',
            cloudflare: 'FREE'
        },
        medium: {
            dalle: '$0.04',
            fal: '$0.03',
            stability: '$0.035',
            ideogram: '$0.05',
            replicate: '$0.025',
            cloudflare: 'FREE'
        },
        high: {
            dalle: '$0.08',
            fal: '$0.05',
            stability: '$0.04',
            ideogram: '$0.10',
            replicate: '$0.05',
            cloudflare: 'FREE'
        }
    };

    function updateQualityPreview() {
        var quality = $('#image_quality_select').val();
        var bar = $('#quality_indicator .quality-bar');
        
        if (!quality || !bar.length) return;
        
        bar.removeClass('low medium high').addClass(quality);

        // Update specs table
        var specs = qualitySpecs[quality];
        if (specs) {
            $('#dalle_quality').text(specs.dalle);
            $('#fal_quality').text(specs.fal);
            $('#stability_quality').text(specs.stability);
            $('#ideogram_quality').text(specs.ideogram);
            $('#replicate_quality').text(specs.replicate);
            $('#cloudflare_quality').text(specs.cloudflare);
        }

        // Update cost cards
        var costs = qualityCosts[quality];
        if (costs) {
            $('#dalle_cost').text(costs.dalle);
            $('#fal_cost').text(costs.fal);
            $('#stability_cost').text(costs.stability);
            $('#ideogram_cost').text(costs.ideogram);
            $('#replicate_cost').text(costs.replicate);
            $('#cloudflare_cost').text(costs.cloudflare);
        }
    }

    $('#image_quality_select').on('change', updateQualityPreview);
    updateQualityPreview(); // Initialize

    // Image Style: Show/hide custom style input
    $('#image_style_select').on('change', function() {
        var style = $(this).val();
        if (style === 'custom') {
            $('#custom_style_row').slideDown(200);
        } else {
            $('#custom_style_row').slideUp(200);
        }
    });

    // Image Post-Processing: Show/hide options
    $('input[name="botwriter_image_postprocess_enabled"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#postprocess_options').slideDown(200);
        } else {
            $('#postprocess_options').slideUp(200);
        }
    });

    // Compression slider value display
    $('#compression_slider').on('input', function() {
        $('#compression_value').text($(this).val() + '%');
    });

    // Test API Key functionality
    $(document).on('click', '.test-api-key', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $wrapper = $btn.closest('.api-key-wrapper');
        var $result;
        var $input;
        var provider = $btn.data('provider');
        var apiKey;

        // DALL-E uses OpenAI's API key, Gemini (image) uses Google's API key
        if (provider === 'dalle') {
            $result = $btn.siblings('.test-api-result');
            $input = $('input[name="botwriter_openai_api_key"]');
            apiKey = $input.val();
        } else if (provider === 'gemini') {
            $result = $btn.siblings('.test-api-result');
            $input = $('input[name="botwriter_google_api_key"]');
            apiKey = $input.val();
        } else {
            $result = $wrapper.find('.test-api-result');
            $input = $wrapper.find('.api-key-input');
            apiKey = $input.val();
        }

        // Validate input
        if (!apiKey || apiKey.trim() === '') {
            var errorMsg;
            if (provider === 'dalle') {
                errorMsg = i18n.configure_openai_key || 'Configure OpenAI API key in Text AI tab first';
            } else if (provider === 'gemini') {
                errorMsg = i18n.configure_google_key || 'Configure Google API key in Text AI tab first';
            } else {
                errorMsg = i18n.enter_api_key;
            }
            $result.removeClass('success').addClass('error').html(
                '<span class="dashicons dashicons-warning"></span> ' + errorMsg
            );
            return;
        }

        // Show loading state
        $btn.prop('disabled', true).addClass('testing');
        $btn.find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-update spin');
        $result.removeClass('success error').html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'botwriter_test_api_key',
                nonce: nonce,
                provider: provider,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message
                    );
                    
                    // If models were returned, reload page to show updated models list
                    if (response.data.models && response.data.models.length > 0) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    $result.removeClass('success').addClass('error').html(
                        '<span class="dashicons dashicons-dismiss"></span> ' + response.data.message
                    );
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(
                    '<span class="dashicons dashicons-dismiss"></span> ' + i18n.connection_error
                );
            },
            complete: function() {
                // Reset button state
                $btn.prop('disabled', false).removeClass('testing');
                $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes-alt');
            }
        });
    });

    // Test Model functionality
    $(document).on('click', '.test-model', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $wrapper = $btn.closest('.model-select-wrapper');
        var $result = $wrapper.find('.test-model-result');
        var $select = $wrapper.find('.model-select');
        var provider = $btn.data('provider');
        var model = $select.val();

        if (!model) {
            $result.removeClass('success').addClass('error').html(
                '<span class="dashicons dashicons-warning"></span> ' + (i18n.select_model || 'Select a model first')
            );
            return;
        }

        // Show loading state
        $btn.prop('disabled', true).addClass('testing');
        $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update spin');
        $result.removeClass('success error').html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'botwriter_test_model',
                nonce: nonce,
                provider: provider,
                model: model
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message
                    );
                } else {
                    $result.removeClass('success').addClass('error').html(
                        '<span class="dashicons dashicons-dismiss"></span> ' + response.data.message
                    );
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(
                    '<span class="dashicons dashicons-dismiss"></span> ' + i18n.connection_error
                );
            },
            complete: function() {
                // Reset button state
                $btn.prop('disabled', false).removeClass('testing');
                $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-controls-play');
            }
        });
    });

    // Reset Models to Default functionality
    $(document).on('click', '#btn-reset-models', function(e) {
        e.preventDefault();
        
        if (!confirm(i18n.confirm_reset_models || 'Are you sure you want to reset all model lists to factory defaults?')) {
            return;
        }

        var $btn = $(this);
        var $result = $('#reset-models-result');

        $btn.prop('disabled', true);
        $btn.find('.dashicons').removeClass('dashicons-database-remove').addClass('dashicons-update spin');
        $result.removeClass('success error').html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'botwriter_reset_models',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(
                        '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' + response.data.message
                    );
                    // Reload page after 1.5 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.removeClass('success').addClass('error').html(
                        '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ' + response.data.message
                    );
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(
                    '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ' + i18n.connection_error
                );
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-database-remove');
            }
        });
    });

    // Test Custom Provider Text connection
    $(document).on('click', '.test-custom-text-connection', function() {
        var $btn = $(this);
        var $result = $('#test-custom-text-result');
        var url = $('#botwriter_custom_text_url').val();
        var apiKey = $('#botwriter_custom_text_api_key').val();
        
        if (!url) {
            $result.html('<span class="error">' + (i18n.enter_api_url || 'Please enter an API URL') + '</span>');
            return;
        }
        
        $btn.prop('disabled', true);
        $result.html('<span class="testing"><span class="spinner is-active"></span> ' + (i18n.testing || 'Testing...') + '</span>');
        
        $.post(ajaxUrl, {
            action: 'botwriter_test_custom_provider',
            nonce: nonce,
            url: url,
            api_key: apiKey,
            type: 'text'
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                var html = '<span class="success"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</span>';
                if (response.data.models && response.data.models.length > 0) {
                    html += '<div class="fetched-models"><small>' + (i18n.models_found || 'Models found:') + ' ' + response.data.models.slice(0, 5).join(', ');
                    if (response.data.models.length > 5) {
                        html += ' (+' + (response.data.models.length - 5) + ' more)';
                    }
                    html += '</small></div>';
                }
                $result.html(html);
            } else {
                $result.html('<span class="error"><span class="dashicons dashicons-no"></span> ' + response.data.message + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $result.html('<span class="error">' + (i18n.connection_failed || 'Connection failed') + '</span>');
        });
    });
});