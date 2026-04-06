/**
 * BotWriter WooCommerce AI – Frontend Controller
 *
 * Drives the Products, Categories, Bulk Optimizer, History and Settings tabs.
 * Depends on jQuery and the localized bw_woo_ai object.
 *
 * @package BotWriter
 * @since   2.3.0
 */
(function ($) {
    'use strict';

    console.log('[BW-WOO-AI] JS loaded — build 2026-03-13a');

    if (typeof bw_woo_ai === 'undefined') {
        return;
    }

    var ajax  = bw_woo_ai.ajax_url,
        nonce = bw_woo_ai.nonce,
        i18n  = bw_woo_ai.i18n,
        batchSize = parseInt(bw_woo_ai.batch_size, 10) || 5,
        requestDelay = parseFloat(bw_woo_ai.request_delay) || 2;

    /* ================================================================
     *  UTILITIES
     * ================================================================ */

    function post(action, data, success, error) {
        data.action = action;
        data.nonce  = nonce;
        $.post(ajax, data, function (r) {
            if (r.success) {
                success(r.data);
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : (r.data || i18n.error);
                if (typeof error === 'function') error(msg);
                else alert(msg);
            }
        }).fail(function () {
            if (typeof error === 'function') error(i18n.error);
            else alert(i18n.error);
        });
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    /* ================================================================
     *  PRODUCT TABLE
     * ================================================================ */

    var selectedProducts = {};
    var currentProductPage = 1;

    function loadProducts(page) {
        page = page || 1;
        currentProductPage = page;
        var $wrap = $('#bw-products-table-wrap');
        $wrap.html('<p>' + i18n.loading + '</p>');

        var filterStatus = $('#bw-filter-status').val() || 'all';
        var params = {
            page: page,
            filter_status: filterStatus,
            category: $('#bw-filter-category').val() || '',
            stock_status: $('#bw-filter-stock').val() || '',
            product_type: $('#bw-filter-type').val() || '',
            search: $('#bw-filter-search').val() || ''
        };

        // Append custom filter params when applicable
        if (filterStatus === 'custom') {
            params.custom_field     = $('#bw-custom-field').val() || '';
            params.custom_condition = $('#bw-custom-condition').val() || '';
            params.custom_value     = $('#bw-custom-value').val() || '';
        }

        post('bw_woo_ai_get_products', params, function (data) {
            renderProductTable(data, $wrap);
        });
    }

    function renderProductTable(data, $wrap) {
        if (!data.products || !data.products.length) {
            $wrap.html('<p>' + i18n.no_products + '</p>');
            updateSelectionCount();
            return;
        }

        var html = '<table class="wp-list-table widefat fixed striped bw-product-table">';
        html += '<thead><tr>';
        html += '<th class="check-column"><input type="checkbox" id="bw-select-all"></th>';
        html += '<th></th>';
        html += '<th>' + escHtml('Product') + '</th>';
        html += '<th>' + escHtml('SKU') + '</th>';
        html += '<th>' + escHtml('Type') + '</th>';
        html += '<th>' + escHtml('Description Words') + '</th>';
        html += '<th>' + escHtml('Status') + '</th>';
        html += '</tr></thead><tbody>';

        data.products.forEach(function (p) {
            var checked = selectedProducts[p.id] ? ' checked' : '';
            var statusBadge = p.ai_optimized
                ? '<span class="bw-badge bw-badge-success">AI Optimized</span>'
                : (p.has_description ? '<span class="bw-badge bw-badge-default">Has Content</span>' : '<span class="bw-badge bw-badge-warning">No Description</span>');
            var thumb = p.image ? '<img src="' + escHtml(p.image) + '" width="36" height="36" style="border-radius:4px;object-fit:cover;">' : '';

            html += '<tr>';
            html += '<td><input type="checkbox" class="bw-product-cb" value="' + p.id + '"' + checked + '></td>';
            html += '<td>' + thumb + '</td>';
            html += '<td><strong>' + escHtml(p.name) + '</strong><br><small style="color:#888;">' + escHtml(p.categories) + '</small></td>';
            html += '<td>' + escHtml(p.sku || '—') + '</td>';
            html += '<td>' + escHtml(p.type) + '</td>';
            html += '<td>' + p.desc_length + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        // Pagination
        if (data.total_pages > 1) {
            html += '<div class="tablenav"><div class="tablenav-pages">';
            for (var i = 1; i <= data.total_pages; i++) {
                if (i === data.page) {
                    html += '<span class="tablenav-pages-navspan button disabled">' + i + '</span> ';
                } else {
                    html += '<a class="button bw-page-btn" data-page="' + i + '">' + i + '</a> ';
                }
            }
            html += '</div></div>';
        }

        html += '<p class="bw-selection-count" style="margin-top:8px;"></p>';
        html += '<p class="bw-select-all-matching" style="margin-top:4px;display:none;"><a href="#" id="bw-select-all-matching"></a></p>';

        $wrap.html(html);

        // Show "select all matching" link if there are multiple pages
        if (data.total_pages > 1) {
            $('.bw-select-all-matching').show();
            $('#bw-select-all-matching').text(
                (i18n.select_all_matching || 'Select all {total} matching products').replace('{total}', data.total)
            );
        }

        updateSelectionCount();
    }

    function updateSelectionCount() {
        var count = Object.keys(selectedProducts).length;
        $('.bw-selection-count').text(count + ' ' + i18n.selected);
        $('#bw-optimize-selected, #bw-bulk-next-1').prop('disabled', count === 0);
    }

    // Event delegation for product checkboxes
    $(document).on('change', '.bw-product-cb', function () {
        var id = $(this).val();
        if (this.checked) {
            var $row = $(this).closest('tr');
            selectedProducts[id] = {
                name: $row.find('td:eq(2) strong').text(),
                image: $row.find('td:eq(1) img').attr('src') || ''
            };
        } else {
            delete selectedProducts[id];
        }
        updateSelectionCount();
    });

    $(document).on('change', '#bw-select-all', function () {
        var checked = this.checked;
        $('.bw-product-cb').each(function () {
            this.checked = checked;
            var id = $(this).val();
            if (checked) {
                var $row = $(this).closest('tr');
                selectedProducts[id] = {
                    name: $row.find('td:eq(2) strong').text(),
                    image: $row.find('td:eq(1) img').attr('src') || ''
                };
            } else {
                delete selectedProducts[id];
            }
        });
        updateSelectionCount();
    });

    $(document).on('click', '.bw-page-btn', function () {
        loadProducts(parseInt($(this).data('page'), 10));
    });

    // Select all matching products (across all pages)
    $(document).on('click', '#bw-select-all-matching', function (e) {
        e.preventDefault();
        var $link = $(this).text(i18n.loading || 'Loading…');
        var params = {
            category:      $('#bw-filter-category').val() || '',
            stock_status:  $('#bw-filter-stock').val() || '',
            product_type:  $('#bw-filter-type').val() || '',
            search:        $('#bw-filter-search').val() || '',
            filter_status: $('#bw-filter-status').val() || 'all'
        };

        var filterStatus = $('#bw-filter-status').val() || 'all';
        if (filterStatus === 'custom') {
            params.custom_field     = $('#bw-custom-field').val() || '';
            params.custom_condition = $('#bw-custom-condition').val() || '';
            params.custom_value     = $('#bw-custom-value').val() || '';
        }

        post('bw_woo_ai_get_all_product_ids', params, function (data) {
            if (data.items && data.items.length) {
                data.items.forEach(function (item) {
                    selectedProducts[item.id] = { name: item.name, image: '' };
                });
                // Check visible checkboxes
                $('.bw-product-cb').each(function () {
                    if (selectedProducts[$(this).val()]) this.checked = true;
                });
                $('#bw-select-all').prop('checked', true);
            }
            updateSelectionCount();
            $link.text(
                (i18n.all_matching_selected || '{count} products selected').replace('{count}', Object.keys(selectedProducts).length)
            );
        }, function () {
            $link.text(i18n.error || 'Error');
        });
    });

    $(document).on('click', '#bw-filter-apply', function () {
        selectedProducts = {};
        loadProducts(1);
    });

    /* ================================================================
     *  CUSTOM FILTER – show/hide row
     * ================================================================ */

    $(document).on('change', '#bw-filter-status', function () {
        var $row = $('#bw-custom-filter-row');
        if ($(this).val() === 'custom') {
            $row.show();
        } else {
            $row.hide();
        }
    });

    // Hide/show value field when condition is is_empty or is_not_empty
    $(document).on('change', '#bw-custom-condition', function () {
        var cond = $(this).val();
        if (cond === 'is_empty' || cond === 'is_not_empty') {
            $('#bw-custom-value-wrap').hide();
        } else {
            $('#bw-custom-value-wrap').show();
        }
    });

    /* ================================================================
     *  CATEGORY TABLE
     * ================================================================ */

    var selectedCategories = {};

    function loadCategories() {
        var $wrap = $('#bw-categories-table-wrap');
        if (!$wrap.length) return;
        $wrap.html('<p>' + i18n.loading + '</p>');

        post('bw_woo_ai_get_categories', {}, function (data) {
            renderCategoryTable(data, $wrap);
        });
    }

    function renderCategoryTable(data, $wrap) {
        if (!data || !data.length) {
            $wrap.html('<p>' + i18n.no_products + '</p>');
            return;
        }

        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th class="check-column"><input type="checkbox" id="bw-cat-select-all"></th>';
        html += '<th>Category</th>';
        html += '<th>Products</th>';
        html += '<th>Description</th>';
        html += '<th>Status</th>';
        html += '</tr></thead><tbody>';

        data.forEach(function (c) {
            var checked = selectedCategories[c.id] ? ' checked' : '';
            var status = c.has_description
                ? '<span class="bw-badge bw-badge-success">Has Description</span>'
                : '<span class="bw-badge bw-badge-warning">No Description</span>';
            var desc = c.description ? escHtml(c.description.substring(0, 100)) + '…' : '<em style="color:#999;">Empty</em>';
            var indent = (c.depth || 0) * 24;

            html += '<tr>';
            html += '<td><input type="checkbox" class="bw-cat-cb" value="' + c.id + '"' + checked + '></td>';
            html += '<td><span class="bw-cat-indent" style="padding-left:' + indent + 'px;">';
            if (c.depth > 0) html += '<span class="bw-cat-arrow">└</span>';
            html += '<strong>' + escHtml(c.name) + '</strong></span></td>';
            html += '<td>' + c.count + '</td>';
            html += '<td>' + desc + '</td>';
            html += '<td>' + status + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $wrap.html(html);
    }

    $(document).on('change', '.bw-cat-cb', function () {
        var id = $(this).val();
        if (this.checked) {
            var name = $(this).closest('tr').find('td:eq(1) strong').text();
            selectedCategories[id] = { name: name };
        } else {
            delete selectedCategories[id];
        }
        var count = Object.keys(selectedCategories).length;
        $('#bw-optimize-categories, #bw-cat-next-1').prop('disabled', count === 0);
    });

    $(document).on('change', '#bw-cat-select-all', function () {
        var checked = this.checked;
        $('.bw-cat-cb').each(function () {
            this.checked = checked;
            var id = $(this).val();
            if (checked) {
                var name = $(this).closest('tr').find('td:eq(1) strong').text();
                selectedCategories[id] = { name: name };
            } else {
                delete selectedCategories[id];
            }
        });
        var count = Object.keys(selectedCategories).length;
        $('#bw-optimize-categories, #bw-cat-next-1').prop('disabled', count === 0);
    });

    /* ================================================================
     *  CATEGORY FILTER DROPDOWN LOADER
     * ================================================================ */

    function loadCategoryDropdown() {
        post('bw_woo_ai_get_category_list', {}, function (data) {
            var $sel = $('#bw-filter-category');
            if (!$sel.length || !data) return;
            data.forEach(function (c) {
                var prefix = '';
                for (var d = 0; d < (c.depth || 0); d++) prefix += '— ';
                $sel.append('<option value="' + escHtml(c.slug) + '">' + prefix + escHtml(c.name) + '</option>');
            });
        });
    }

    /* ================================================================
     *  BULK OPTIMIZER – STEP NAVIGATION
     * ================================================================ */

    function showStep(step) {
        $('.bw-bulk-step').hide();
        $('#bw-step-' + step).show();
        $('.bw-step').removeClass('active');
        $('.bw-step[data-step="' + step + '"]').addClass('active');
    }

    $(document).on('click', '#bw-bulk-next-1', function () { showStep(2); });
    $(document).on('click', '#bw-bulk-prev-2', function () { showStep(1); });
    $(document).on('click', '#bw-bulk-next-2', function () {
        var fields = [];
        $('input[name="bw_fields[]"]:checked').each(function () { fields.push($(this).val()); });
        if (!fields.length) { alert(i18n.no_fields); return; }
        showStep(3);
    });
    $(document).on('click', '#bw-bulk-prev-3', function () { showStep(2); });
    $(document).on('click', '#bw-bulk-prev-4', function () { showStep(3); });

    /* ================================================================
     *  BULK GENERATION – ONE-BY-ONE SEQUENTIAL AJAX
     * ================================================================ */

    var generationAborted = false;

    $(document).on('click', '#bw-bulk-generate', function () {
        var ids = Object.keys(selectedProducts);
        if (!ids.length) { alert(i18n.no_selection); return; }

        var fields = [];
        $('input[name="bw_fields[]"]:checked').each(function () { fields.push($(this).val()); });
        if (!fields.length) { alert(i18n.no_fields); return; }

        var provider = $('#bw-woo-provider').val() || 'openai';
        var language = $('#bw-woo-language').val() || 'auto';
        var $genBtn = $('#bw-bulk-generate').prop('disabled', true);

        $genBtn.prop('disabled', false);

        {
            showStep(4);
            $('#bw-preview-results').empty();
            $('#bw-bulk-apply, #bw-bulk-prev-4').hide();
            generationAborted = false;

            var total = ids.length;
            var processed = 0;
            var successCount = 0;
            var allResults = {};

            function updateProgress() {
                var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
                $('.bw-progress-fill').css('width', pct + '%');
                $('#bw-progress-count').text(processed + '/' + total);
            }

            // Create all compact (placeholder) cards upfront
            ids.forEach(function (pid) {
                renderCompactCard(pid);
            });

            updateProgress();

            // Process one product at a time, sequentially
            function processNext(idx) {
                if (generationAborted || idx >= ids.length) {
                    // All done (or aborted)
                    $('.bw-progress-text').text(generationAborted ? i18n.error : i18n.done);
                    $('#bw-bulk-apply, #bw-bulk-prev-4').show();
                    return;
                }

                var pid = ids[idx];
                // Mark the compact card as generating
                $('#bw-compact-' + pid).find('.bw-compact-status')
                    .html('<span class="spinner is-active" style="float:none;margin:0;"></span> ' + i18n.generating);

                post('bw_woo_ai_generate_single', {
                    product_id: pid,
                    fields: fields,
                    provider: provider,
                    language: language
                }, function (data) {
                    allResults[pid] = data;
                    processed++;
                    successCount++;
                    updateProgress();
                    expandCompactCard(pid, data);

                    // Delay before next product
                    if (idx + 1 < ids.length && requestDelay > 0) {
                        setTimeout(function () { processNext(idx + 1); }, requestDelay * 1000);
                    } else {
                        processNext(idx + 1);
                    }
                }, function (err) {
                    processed++;
                    updateProgress();
                    expandCompactCard(pid, { error: err, product_name: (selectedProducts[pid] && selectedProducts[pid].name) || '#' + pid });

                    if (idx + 1 < ids.length && requestDelay > 0) {
                        setTimeout(function () { processNext(idx + 1); }, requestDelay * 1000);
                    } else {
                        processNext(idx + 1);
                    }
                });
            }

            processNext(0);

            // Store for apply
            $(document).data('bw_woo_results', allResults);
        }
    });

    /* ================================================================
     *  ACCORDION CARDS – Compact & Expanded
     * ================================================================ */

    /**
     * Render a compact placeholder card for a product (before generation starts).
     */
    function renderCompactCard(pid) {
        var meta = (typeof selectedProducts[pid] === 'object') ? selectedProducts[pid] : {};
        var name = meta.name || '#' + pid;
        var thumb = meta.image
            ? '<img src="' + escHtml(meta.image) + '" class="bw-compact-thumb"> '
            : '';

        var html = '<div class="bw-preview-card bw-compact-card" id="bw-compact-' + pid + '" data-pid="' + pid + '" data-approved="1">';
        html += '<div class="bw-compact-header">';
        html += '<span class="bw-compact-toggle dashicons dashicons-arrow-right-alt2"></span>';
        html += '<span class="bw-compact-name">' + thumb + escHtml(name) + ' <small>#' + pid + '</small></span>';
        html += '<span class="bw-compact-status bw-badge bw-badge-default">' + i18n.loading + '</span>';
        html += '</div>';
        html += '<div class="bw-compact-body" style="display:none;"></div>';
        html += '</div>';
        $('#bw-preview-results').append(html);
    }

    /**
     * Expand a compact card with the generation result (success or error).
     */
    function expandCompactCard(pid, result) {
        var $card = $('#bw-compact-' + pid);
        var name = escHtml(result.product_name || 'Product #' + pid);

        // Update header
        $card.find('.bw-compact-name').html('<strong>' + name + '</strong>');

        if (result.error) {
            $card.addClass('bw-preview-error');
            $card.find('.bw-compact-status').html('<span class="bw-badge bw-badge-error">' + i18n.error + '</span>');
            $card.find('.bw-compact-body').html('<p class="bw-error-text">' + escHtml(result.error) + '</p>');
            return;
        }

        // Success badge + reject toggle (approved by default)
        $card.find('.bw-compact-status').html(
            '<span class="bw-badge bw-badge-success">' + i18n.done + '</span> ' +
            '<button type="button" class="button button-small bw-reject-btn" data-pid="' + pid + '" title="' + i18n.reject + '">\u2715</button>'
        );

        // Build expanded body
        var bodyHtml = '';
        if (result.generated) {
            $.each(result.generated, function (field, fdata) {
                if (fdata.status === 'skipped') {
                    bodyHtml += '<div class="bw-field-preview"><strong>' + escHtml(field) + ':</strong> <em>' + escHtml(fdata.reason) + '</em></div>';
                    return;
                }
                if (fdata.status === 'error') {
                    bodyHtml += '<div class="bw-field-preview bw-field-error"><strong>' + escHtml(field) + ':</strong> <span class="bw-error-text">' + escHtml(fdata.error) + '</span></div>';
                    return;
                }

                var currentContent = (result.current && result.current[field]) || '';
                var currentPreview = currentContent ? currentContent.substring(0, 200) : '<em>Empty</em>';

                bodyHtml += '<div class="bw-field-preview" data-field="' + escHtml(field) + '">';
                bodyHtml += '<strong>' + escHtml(field.replace(/_/g, ' ')) + '</strong>';
                bodyHtml += '<div class="bw-diff-wrap">';
                bodyHtml += '<div class="bw-diff-old"><span class="bw-diff-label">' + i18n.original + '</span><div class="bw-diff-content">' + currentPreview + '</div></div>';
                bodyHtml += '<div class="bw-diff-new"><span class="bw-diff-label">' + i18n.ai_generated + '</span><div class="bw-diff-content bw-editable" contenteditable="true">' + fdata.content + '</div></div>';
                bodyHtml += '</div>';
                bodyHtml += '</div>';
            });
        }

        $card.find('.bw-compact-body').html(bodyHtml);
    }

    // Toggle accordion expand / collapse
    $(document).on('click', '.bw-compact-header', function (e) {
        // Don't toggle when clicking buttons
        if ($(e.target).is('button') || $(e.target).closest('button').length) return;

        var $card = $(this).closest('.bw-preview-card');
        var $body = $card.find('.bw-compact-body');
        var $toggle = $card.find('.bw-compact-toggle');

        $body.slideToggle(200);
        $toggle.toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
    });

    // Reject toggle (approved by default; click to reject, click again to un-reject)
    $(document).on('click', '.bw-reject-btn', function (e) {
        e.stopPropagation();
        var $card = $(this).closest('.bw-preview-card');
        var isRejected = $card.hasClass('bw-rejected');
        if (isRejected) {
            $card.attr('data-approved', '1').removeClass('bw-rejected');
            $(this).removeClass('bw-active');
        } else {
            $card.attr('data-approved', '0').addClass('bw-rejected');
            $(this).addClass('bw-active');
        }
    });

    // Auto-save edited AI content on blur
    $(document).on('blur', '.bw-editable', function () {
        var $card = $(this).closest('.bw-preview-card');
        var pid = $card.data('pid');
        var cid = $card.data('cid');
        var field = $(this).closest('.bw-field-preview').data('field');
        var content = $(this).html();

        if (pid) {
            var allResults = $(document).data('bw_woo_results') || {};
            if (allResults[pid] && allResults[pid].generated && allResults[pid].generated[field]) {
                allResults[pid].generated[field].content = content;
            }
        } else if (cid) {
            var catResults = $(document).data('bw_woo_cat_results') || {};
            if (catResults[cid]) {
                catResults[cid].generated = content;
            }
        }
    });

    /* ================================================================
     *  APPLY CHANGES
     * ================================================================ */

    $(document).on('click', '#bw-bulk-apply', function () {
        if (!confirm(i18n.confirm_apply)) return;

        var $btn = $(this).prop('disabled', true).text(i18n.loading);
        var allResults = $(document).data('bw_woo_results') || {};
        var items = [];

        $('.bw-preview-card[data-approved="1"]').each(function () {
            var pid = $(this).data('pid');
            var result = allResults[pid];
            if (!result || !result.generated) return;

            var fields = {};
            var $card = $(this);

            $.each(result.generated, function (field, fdata) {
                if (fdata.status !== 'success') return;

                // Get potentially edited content from the contenteditable div
                var $editable = $card.find('.bw-field-preview[data-field="' + field + '"] .bw-editable');
                if ($editable.length) {
                    fields[field] = $editable.html();
                } else {
                    fields[field] = fdata.content;
                }
            });

            fields._provider = $('#bw-woo-provider').val() || '';

            if (Object.keys(fields).length > 1) { // > 1 because _provider is always there
                items.push({ product_id: pid, fields: fields });
            }
        });

        if (!items.length) {
            alert(i18n.no_selection);
            $btn.prop('disabled', false).text(i18n.applied);
            return;
        }

        post('bw_woo_ai_apply', { items: items }, function (data) {
            $btn.text(i18n.applied + ' (' + data.applied + ')');
            if (data.errors && data.errors.length) {
                alert(i18n.error + ': ' + data.errors.join(', '));
            }
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text(i18n.error);
        });
    });

    /* ================================================================
     *  SINGLE PRODUCT OPTIMIZE (Products tab)
     * ================================================================ */

    /* (Products tab removed – optimize goes directly through Bulk) */

    /* ================================================================
     *  HISTORY TABLE
     * ================================================================ */

    var selectedHistory = {};
    var historyPage = 1;

    function getHistoryFilters() {
        return {
            category: $('#bw-history-filter-category').val() || '',
            search:   $('#bw-history-search').val() || ''
        };
    }

    function loadHistory(page) {
        var $wrap = $('#bw-history-table-wrap');
        if (!$wrap.length) return;
        historyPage = page || 1;
        $wrap.html('<p>' + i18n.loading + '</p>');

        var params = $.extend({ page: historyPage }, getHistoryFilters());
        post('bw_woo_ai_get_history', params, function (data) {
            renderHistoryTable(data, $wrap);
        });
    }

    function renderHistoryTable(data, $wrap) {
        if (!data.items || !data.items.length) {
            $wrap.html('<p>No optimization history found.</p>');
            return;
        }

        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th class="check-column"><input type="checkbox" id="bw-history-select-all"></th>';
        html += '<th></th>';
        html += '<th>Product</th>';
        html += '<th>Optimized At</th>';
        html += '<th>Provider</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        data.items.forEach(function (item) {
            var checked = selectedHistory[item.id] ? ' checked' : '';
            var thumb = item.image ? '<img src="' + escHtml(item.image) + '" width="36" height="36" style="border-radius:4px;object-fit:cover;">' : '';

            html += '<tr>';
            html += '<td><input type="checkbox" class="bw-history-cb" value="' + item.id + '"' + checked + '></td>';
            html += '<td>' + thumb + '</td>';
            html += '<td><strong>' + escHtml(item.name) + '</strong></td>';
            html += '<td>' + escHtml(item.optimized_at || '—') + '</td>';
            html += '<td>' + escHtml(item.provider || '—') + '</td>';
            html += '<td>';
            if (item.has_backup) {
                html += '<button type="button" class="button button-small bw-view-diff" data-pid="' + item.id + '">View Diff</button> ';
                html += '<button type="button" class="button button-small bw-revert-single" data-pid="' + item.id + '">Revert</button>';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        // Pagination
        if (data.total_pages > 1) {
            html += '<div class="tablenav"><div class="tablenav-pages" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-top:10px;">';
            html += '<span class="displaying-num" style="margin-right:8px;">' + data.total + ' items</span>';
            // Prev
            if (data.page > 1) {
                html += '<a class="button bw-history-page-btn" data-page="1">&laquo;</a>';
                html += '<a class="button bw-history-page-btn" data-page="' + (data.page - 1) + '">&lsaquo;</a>';
            }
            // Page window
            var start = Math.max(1, data.page - 2);
            var end   = Math.min(data.total_pages, data.page + 2);
            for (var i = start; i <= end; i++) {
                if (i === data.page) {
                    html += '<span class="tablenav-pages-navspan button disabled current">' + i + '</span>';
                } else {
                    html += '<a class="button bw-history-page-btn" data-page="' + i + '">' + i + '</a>';
                }
            }
            // Next
            if (data.page < data.total_pages) {
                html += '<a class="button bw-history-page-btn" data-page="' + (data.page + 1) + '">&rsaquo;</a>';
                html += '<a class="button bw-history-page-btn" data-page="' + data.total_pages + '">&raquo;</a>';
            }
            html += '</div></div>';
        }

        $wrap.html(html);
    }

    $(document).on('change', '.bw-history-cb', function () {
        var id = $(this).val();
        if (this.checked) selectedHistory[id] = true;
        else delete selectedHistory[id];
        $('#bw-revert-selected').prop('disabled', Object.keys(selectedHistory).length === 0);
    });

    $(document).on('change', '#bw-history-select-all', function () {
        var checked = this.checked;
        $('.bw-history-cb').each(function () {
            this.checked = checked;
            if (checked) selectedHistory[$(this).val()] = true;
            else delete selectedHistory[$(this).val()];
        });
        $('#bw-revert-selected').prop('disabled', Object.keys(selectedHistory).length === 0);
    });

    $(document).on('click', '.bw-history-page-btn', function () {
        loadHistory(parseInt($(this).data('page'), 10));
    });

    // History filters
    $(document).on('click', '#bw-history-filter-btn', function () {
        selectedHistory = {};
        $('#bw-revert-selected').prop('disabled', true);
        loadHistory(1);
    });
    $(document).on('keydown', '#bw-history-search', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#bw-history-filter-btn').trigger('click');
        }
    });

    // Populate history category dropdown (reuse same endpoint)
    function loadHistoryCategoryDropdown() {
        post('bw_woo_ai_get_category_list', {}, function (data) {
            var $sel = $('#bw-history-filter-category');
            if (!$sel.length || !data) return;
            data.forEach(function (c) {
                var prefix = '';
                for (var d = 0; d < (c.depth || 0); d++) prefix += '— ';
                $sel.append('<option value="' + escHtml(c.slug) + '">' + prefix + escHtml(c.name) + '</option>');
            });
        });
    }

    // Revert single
    $(document).on('click', '.bw-revert-single', function () {
        var pid = $(this).data('pid');
        var $btn = $(this).prop('disabled', true).text(i18n.loading);
        post('bw_woo_ai_revert', { product_ids: [pid] }, function () {
            $btn.text(i18n.reverted);
            setTimeout(function () { loadHistory(historyPage); }, 1000);
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text('Revert');
        });
    });

    // Revert selected
    $(document).on('click', '#bw-revert-selected', function () {
        var ids = Object.keys(selectedHistory);
        if (!ids.length) return;
        var $btn = $(this).prop('disabled', true).text(i18n.loading);
        post('bw_woo_ai_revert', { product_ids: ids }, function (data) {
            $btn.text(i18n.reverted + ' (' + data.reverted + ')');
            selectedHistory = {};
            setTimeout(function () { loadHistory(historyPage); }, 1500);
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text('Revert Selected');
        });
    });

    /* ================================================================
     *  DIFF MODAL
     * ================================================================ */

    // Inject modal markup once.
    $('body').append(
        '<div id="bw-diff-overlay" class="bw-diff-overlay" style="display:none;">' +
            '<div class="bw-diff-modal">' +
                '<div class="bw-diff-modal-header">' +
                    '<h3 id="bw-diff-modal-title"></h3>' +
                    '<button type="button" class="bw-diff-modal-close" title="Close">&times;</button>' +
                '</div>' +
                '<div id="bw-diff-modal-body" class="bw-diff-modal-body"></div>' +
            '</div>' +
        '</div>'
    );

    // Open diff modal.
    $(document).on('click', '.bw-view-diff', function () {
        var pid  = $(this).data('pid');
        var $btn = $(this).prop('disabled', true).text(i18n.loading);

        post('bw_woo_ai_get_diff', { product_id: pid }, function (data) {
            $btn.prop('disabled', false).text('View Diff');
            renderDiffModal(data);
        }, function (err) {
            $btn.prop('disabled', false).text('View Diff');
            alert(err);
        });
    });

    function renderDiffModal(data) {
        $('#bw-diff-modal-title').text(data.product_name + ' — Changes');

        var body = '';
        if (!data.fields || !data.fields.length) {
            body = '<p>No changes detected for this product.</p>';
        } else {
            data.fields.forEach(function (f) {
                body += '<div class="bw-diff-field bw-diff-field-changed" data-pid="' + data.product_id + '" data-field="' + escHtml(f.field) + '">';
                body += '<div class="bw-diff-field-header">';
                body += '<strong>' + escHtml(f.label) + '</strong>';
                body += '<button type="button" class="button button-small bw-revert-field-btn" data-pid="' + data.product_id + '" data-field="' + escHtml(f.field) + '">Revert</button>';
                body += '</div>';
                body += '<div class="bw-diff-columns">';
                body += '<div class="bw-diff-col bw-diff-col-old">';
                body += '<span class="bw-diff-col-label">Original</span>';
                body += '<div class="bw-diff-col-content">' + formatDiffContent(f.old) + '</div>';
                body += '</div>';
                body += '<div class="bw-diff-col bw-diff-col-new">';
                body += '<span class="bw-diff-col-label">Current</span>';
                body += '<div class="bw-diff-col-content">' + formatDiffContent(f.current) + '</div>';
                body += '</div>';
                body += '</div>';
                body += '</div>';
            });
        }

        $('#bw-diff-modal-body').html(body);
        $('#bw-diff-overlay').fadeIn(200);
    }

    function formatDiffContent(val) {
        if (!val) return '<em style="color:#999;">— empty —</em>';
        // If it looks like HTML, render safely inside a wrapper.
        if (/<[a-z][\s\S]*>/i.test(val)) {
            return '<div class="bw-diff-html-preview">' + val + '</div>';
        }
        return escHtml(val);
    }

    // Close modal.
    $(document).on('click', '.bw-diff-modal-close, .bw-diff-overlay', function (e) {
        if (e.target === this) {
            $('#bw-diff-overlay').fadeOut(200);
        }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#bw-diff-overlay').fadeOut(200);
        }
    });

    // Per-field revert.
    $(document).on('click', '.bw-revert-field-btn', function () {
        var $btn  = $(this);
        var pid   = $btn.data('pid');
        var field = $btn.data('field');

        $btn.prop('disabled', true).text(i18n.loading);

        post('bw_woo_ai_revert_field', { product_id: pid, field: field }, function () {
            // Mark the row as reverted.
            var $field = $btn.closest('.bw-diff-field');
            $btn.replaceWith('<span class="bw-diff-reverted-badge">Reverted</span>');
            $field.removeClass('bw-diff-field-changed').addClass('bw-diff-field-reverted');
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text('Revert');
        });
    });

    /* ================================================================
     *  SETTINGS
     * ================================================================ */

    $(document).on('click', '#bw-woo-save-settings', function () {
        var $btn = $(this).prop('disabled', true).text(i18n.loading);

        var data = {
            batch_size: $('#bw-woo-batch').val(),
            request_delay: $('#bw-woo-delay').val()
        };

        // Collect all prompt templates
        $('.bw-tpl-textarea').each(function () {
            var field = $(this).data('field');
            data['template_' + field] = $(this).val();
        });

        post('bw_woo_ai_save_settings', data, function (resp) {
            // Update runtime values
            if (resp.request_delay !== undefined) requestDelay = parseFloat(resp.request_delay);
            $btn.prop('disabled', false).text(i18n.saved);
            setTimeout(function () { $btn.text('Save Settings'); }, 2000);
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text(i18n.error);
        });
    });

    // Reset individual template to default
    $(document).on('click', '.bw-tpl-reset', function () {
        var field = $(this).data('field');
        var defaultVal = $('.bw-tpl-default[data-field="' + field + '"]').val();
        if (defaultVal !== undefined) {
            $('#bw-tpl-' + field).val(defaultVal);
        }
    });

    /* ================================================================
     *  CATEGORY WIZARD – STEP NAVIGATION
     * ================================================================ */

    function showCatStep(step) {
        $('.bw-cat-step').hide();
        $('#bw-cat-step-' + step).show();
        $('.bw-cat-steps .bw-step').removeClass('active');
        $('.bw-cat-steps .bw-step[data-step="c' + step + '"]').addClass('active');
    }

    $(document).on('click', '#bw-cat-next-1', function () { showCatStep(2); });
    $(document).on('click', '#bw-cat-prev-2', function () { showCatStep(1); });
    $(document).on('click', '#bw-cat-prev-3', function () { showCatStep(2); });

    /* ================================================================
     *  CATEGORY GENERATION – ONE-BY-ONE SEQUENTIAL AJAX
     * ================================================================ */

    $(document).on('click', '#bw-cat-generate', function () {
        var ids = Object.keys(selectedCategories);
        if (!ids.length) { alert(i18n.no_selection); return; }

        var provider = $('#bw-woo-provider').val() || 'openai';
        var language = $('#bw-woo-language').val() || 'auto';
        var $genBtn = $('#bw-cat-generate').prop('disabled', true);

        $genBtn.prop('disabled', false);

        {
            showCatStep(3);
            $('#bw-cat-preview-results').empty();
            $('#bw-cat-apply, #bw-cat-prev-3').hide();

            var total = ids.length;
            var processed = 0;
            var successCount = 0;
            var catResults = {};

            function updateCatProgress() {
                var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
                $('.bw-cat-progress-fill').css('width', pct + '%');
                $('#bw-cat-progress-count').text(processed + '/' + total);
            }

            // Create compact cards upfront
            ids.forEach(function (cid) {
                var meta = (typeof selectedCategories[cid] === 'object') ? selectedCategories[cid] : {};
                var name = meta.name || '#' + cid;
                var html = '<div class="bw-preview-card bw-compact-card" id="bw-cat-compact-' + cid + '" data-cid="' + cid + '" data-approved="1">';
                html += '<div class="bw-compact-header">';
                html += '<span class="bw-compact-toggle dashicons dashicons-arrow-right-alt2"></span>';
                html += '<span class="bw-compact-name">' + escHtml(name) + '</span>';
                html += '<span class="bw-compact-status bw-badge bw-badge-default">' + i18n.loading + '</span>';
                html += '</div>';
                html += '<div class="bw-compact-body" style="display:none;"></div>';
                html += '</div>';
                $('#bw-cat-preview-results').append(html);
            });

            updateCatProgress();

            function processCatNext(idx) {
                if (idx >= ids.length) {
                    $('#bw-cat-generation-progress .bw-progress-text').text(i18n.done);
                    $('#bw-cat-apply, #bw-cat-prev-3').show();
                    return;
                }

                var cid = ids[idx];
                $('#bw-cat-compact-' + cid).find('.bw-compact-status')
                    .html('<span class="spinner is-active" style="float:none;margin:0;"></span> ' + i18n.generating);

                post('bw_woo_ai_generate_category', {
                    category_id: cid,
                    provider: provider,
                    language: language
                }, function (data) {
                    catResults[cid] = data;
                    processed++;
                    successCount++;
                    updateCatProgress();
                    expandCatCard(cid, data);

                    if (idx + 1 < ids.length && requestDelay > 0) {
                        setTimeout(function () { processCatNext(idx + 1); }, requestDelay * 1000);
                    } else {
                        processCatNext(idx + 1);
                    }
                }, function (err) {
                    processed++;
                    updateCatProgress();
                    expandCatCard(cid, { error: err, category_name: '#' + cid });

                    if (idx + 1 < ids.length && requestDelay > 0) {
                        setTimeout(function () { processCatNext(idx + 1); }, requestDelay * 1000);
                    } else {
                        processCatNext(idx + 1);
                    }
                });
            }

            processCatNext(0);
            $(document).data('bw_woo_cat_results', catResults);
        }
    });

    function expandCatCard(cid, result) {
        var $card = $('#bw-cat-compact-' + cid);
        var name = escHtml(result.category_name || '#' + cid);

        $card.find('.bw-compact-name').html('<strong>' + name + '</strong>');

        if (result.error) {
            $card.addClass('bw-preview-error');
            $card.find('.bw-compact-status').html('<span class="bw-badge bw-badge-error">' + i18n.error + '</span>');
            $card.find('.bw-compact-body').html('<p class="bw-error-text">' + escHtml(result.error) + '</p>');
            return;
        }

        $card.find('.bw-compact-status').html(
            '<span class="bw-badge bw-badge-success">' + i18n.done + '</span> ' +
            '<button type="button" class="button button-small bw-reject-btn" data-cid="' + cid + '" title="' + i18n.reject + '">\u2715</button>'
        );

        var currentPreview = result.current ? escHtml(result.current.substring(0, 200)) : '<em>Empty</em>';

        var bodyHtml = '<div class="bw-field-preview" data-field="category_description">';
        bodyHtml += '<strong>Category Description</strong>';
        bodyHtml += '<div class="bw-diff-wrap">';
        bodyHtml += '<div class="bw-diff-old"><span class="bw-diff-label">' + i18n.original + '</span><div class="bw-diff-content">' + (currentPreview || '<em>Empty</em>') + '</div></div>';
        bodyHtml += '<div class="bw-diff-new"><span class="bw-diff-label">' + i18n.ai_generated + '</span><div class="bw-diff-content bw-editable" contenteditable="true">' + result.generated + '</div></div>';
        bodyHtml += '</div>';
        bodyHtml += '</div>';

        $card.find('.bw-compact-body').html(bodyHtml);
    }

    /* ================================================================
     *  APPLY CATEGORY CHANGES
     * ================================================================ */

    $(document).on('click', '#bw-cat-apply', function () {
        if (!confirm(i18n.confirm_apply)) return;

        var $btn = $(this).prop('disabled', true).text(i18n.loading);
        var catResults = $(document).data('bw_woo_cat_results') || {};
        var items = [];

        $('#bw-cat-preview-results .bw-preview-card[data-approved="1"]').each(function () {
            var cid = $(this).data('cid');
            var result = catResults[cid];
            if (!result || result.error) return;

            var $editable = $(this).find('.bw-editable');
            var content = $editable.length ? $editable.html() : result.generated;

            items.push({ category_id: cid, description: content });
        });

        if (!items.length) {
            alert(i18n.no_selection);
            $btn.prop('disabled', false).text(i18n.applied);
            return;
        }

        post('bw_woo_ai_apply_categories', { items: items }, function (data) {
            $btn.text(i18n.applied + ' (' + data.applied + ')');
            if (data.errors && data.errors.length) {
                alert(i18n.error + ': ' + data.errors.join(', '));
            }
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text(i18n.error);
        });
    });

    /* ================================================================
     *  REVIEWS TAB – 4-STEP WIZARD
     * ================================================================ */

    var revSelectedProducts = {};
    var revGenerating = false;
    var revPreviewData = {};   // pid -> { reviews: [...] }
    var revCurrentPage = 1;

    /* --- Step navigation --- */

    function showRevStep(step) {
        $('.bw-rev-step').hide();
        $('#bw-rev-step-' + step).show();
        $('.bw-rev-steps .bw-step').removeClass('active');
        $('.bw-rev-steps .bw-step[data-step="r' + step + '"]').addClass('active');
    }

    $(document).on('click', '#bw-rev-next-1', function () { showRevStep(2); });
    $(document).on('click', '#bw-rev-prev-2', function () { showRevStep(1); });
    $(document).on('click', '#bw-rev-next-2', function () {
        // Validate rating total
        var ratingTotal = 0;
        $('.bw-rev-rating-pct').each(function () { ratingTotal += parseInt($(this).val()) || 0; });
        if (ratingTotal !== 100) {
            alert('Rating distribution must sum to 100% (currently ' + ratingTotal + '%)');
            return;
        }
        showRevStep(3);
    });
    $(document).on('click', '#bw-rev-prev-3', function () { showRevStep(2); });
    $(document).on('click', '#bw-rev-prev-4', function () { showRevStep(3); });

    /* --- Review-specific filter --- */

    $(document).on('change', '#bw-rev-filter', function () {
        var v = $(this).val();
        $('#bw-rev-filter-n-wrap').toggle(v === 'few_reviews' || v === 'low_rating');
        revLoadProducts();
    });
    $(document).on('change', '#bw-rev-filter-n', function () { revLoadProducts(); });

    /* --- Load products for reviews tab --- */

    function revLoadProducts(page) {
        page = page || 1;
        revCurrentPage = page;
        var $wrap = $('#bw-rev-products-wrap');
        if (!$wrap.length) return;

        $wrap.html('<p>' + i18n.loading + '</p>');

        var data = { page: page };
        var search   = $('#bw-rev-search').val();
        var category = $('#bw-rev-category').val();
        if (search)   data.search   = search;
        if (category) data.category = category;

        post('bw_woo_ai_get_products', data, function (res) {
            var products = res.products || [];

            // Apply review-specific client-side filters
            var filterType = $('#bw-rev-filter').val();
            var filterN    = parseInt($('#bw-rev-filter-n').val()) || 5;

            if (filterType === 'no_reviews') {
                products = products.filter(function (p) { return (parseInt(p.review_count) || 0) === 0; });
            } else if (filterType === 'few_reviews') {
                products = products.filter(function (p) { return (parseInt(p.review_count) || 0) < filterN; });
            } else if (filterType === 'low_rating') {
                products = products.filter(function (p) {
                    var rc = parseInt(p.review_count) || 0;
                    var avg = parseFloat(p.average_rating) || 0;
                    return rc > 0 && avg < filterN;
                });
            }

            if (!products.length) {
                var noMsg = '<p>' + i18n.no_products + '</p>';
                // Still show pagination if there are other pages
                if (res.total_pages > 1) {
                    noMsg += revBuildPagination(res.page, res.total_pages);
                }
                $wrap.html(noMsg);
                revUpdateCount();
                return;
            }

            var html = '<div style="max-height:420px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;">';
            html += '<table class="widefat striped" style="margin:0;">';
            html += '<thead><tr><th style="width:30px;"><input type="checkbox" id="bw-rev-select-all"></th><th style="width:40px;"></th><th>Product</th><th>Reviews</th><th>Avg Rating</th><th style="width:80px;"></th></tr></thead>';
            html += '<tbody>';

            products.forEach(function (p) {
                var checked = revSelectedProducts[p.id] ? ' checked' : '';
                var reviewCount = p.review_count || 0;
                var avgRating = p.average_rating ? parseFloat(p.average_rating).toFixed(1) : '—';
                var thumb = p.image ? '<img src="' + escHtml(p.image) + '" width="36" height="36" style="border-radius:4px;object-fit:cover;">' : '';
                html += '<tr>';
                html += '<td><input type="checkbox" class="bw-rev-product-cb" value="' + p.id + '" data-name="' + escHtml(p.name) + '"' + checked + '></td>';
                html += '<td>' + thumb + '</td>';
                html += '<td><strong>' + escHtml(p.name) + '</strong></td>';
                html += '<td>' + reviewCount + '</td>';
                html += '<td>' + avgRating + '</td>';
                html += '<td><button type="button" class="button button-small bw-rev-delete-btn" data-id="' + p.id + '" title="Delete AI reviews">🗑️</button></td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            // Pagination
            if (res.total_pages > 1) {
                html += revBuildPagination(res.page, res.total_pages);
            }

            html += '<p class="description" style="margin-top:8px;"><span class="bw-rev-count">0</span> ' + i18n.selected + '</p>';
            html += '<p class="bw-rev-select-all-matching" style="margin-top:4px;display:none;"><a href="#" id="bw-rev-select-all-matching"></a></p>';
            $wrap.html(html);

            // Show "select all matching" link if multiple pages
            if (res.total_pages > 1) {
                var revFilter = $('#bw-rev-filter').val();
                $('.bw-rev-select-all-matching').show();
                if (revFilter) {
                    // When review filters are active, server total doesn't reflect them — show generic text
                    $('#bw-rev-select-all-matching').text(
                        i18n.select_all_matching_generic || 'Select all matching products (including other pages)'
                    );
                } else {
                    $('#bw-rev-select-all-matching').text(
                        (i18n.select_all_matching || 'Select all {total} matching products').replace('{total}', res.total)
                    );
                }
            }

            revUpdateCount();
        }, function () {
            $wrap.html('<p style="color:#d63638;">' + i18n.error + '</p>');
        });
    }

    function revBuildPagination(current, totalPages) {
        var h = '<div class="tablenav"><div class="tablenav-pages">';
        for (var i = 1; i <= totalPages; i++) {
            if (i === current) {
                h += '<span class="tablenav-pages-navspan button disabled">' + i + '</span> ';
            } else {
                h += '<a class="button bw-rev-page-btn" data-page="' + i + '">' + i + '</a> ';
            }
        }
        h += '</div></div>';
        return h;
    }

    function revUpdateCount() {
        var count = Object.keys(revSelectedProducts).length;
        $('.bw-rev-count').text(count);
        $('#bw-rev-next-1').prop('disabled', count === 0);
    }

    // Product checkbox handling
    $(document).on('change', '.bw-rev-product-cb', function () {
        var id = $(this).val();
        if (this.checked) {
            revSelectedProducts[id] = { name: $(this).data('name') || '#' + id };
        } else {
            delete revSelectedProducts[id];
        }
        revUpdateCount();
    });

    $(document).on('change', '#bw-rev-select-all', function () {
        var checked = this.checked;
        $('.bw-rev-product-cb').each(function () {
            this.checked = checked;
            var id = $(this).val();
            if (checked) {
                revSelectedProducts[id] = { name: $(this).data('name') || '#' + id };
            } else {
                delete revSelectedProducts[id];
            }
        });
        revUpdateCount();
    });

    // Pagination
    $(document).on('click', '.bw-rev-page-btn', function () {
        revLoadProducts(parseInt($(this).data('page'), 10));
    });

    // Select all matching products across all pages (Reviews)
    $(document).on('click', '#bw-rev-select-all-matching', function (e) {
        e.preventDefault();
        var $link = $(this).text(i18n.loading || 'Loading…');
        var params = {
            search:       $('#bw-rev-search').val() || '',
            category:     $('#bw-rev-category').val() || '',
            rev_filter:   $('#bw-rev-filter').val() || '',
            rev_filter_n: $('#bw-rev-filter-n').val() || '5'
        };

        post('bw_woo_ai_get_all_product_ids', params, function (data) {
            if (data.items && data.items.length) {
                data.items.forEach(function (item) {
                    revSelectedProducts[item.id] = { name: item.name };
                });
                // Check visible checkboxes
                $('.bw-rev-product-cb').each(function () {
                    if (revSelectedProducts[$(this).val()]) this.checked = true;
                });
                $('#bw-rev-select-all').prop('checked', true);
            }
            revUpdateCount();
            $link.text(
                (i18n.all_matching_selected || '{count} products selected').replace('{count}', Object.keys(revSelectedProducts).length)
            );
        }, function () {
            $link.text(i18n.error || 'Error');
        });
    });

    // Search & filter
    var revSearchTimer = null;
    $(document).on('input', '#bw-rev-search', function () {
        clearTimeout(revSearchTimer);
        revSearchTimer = setTimeout(function () { revLoadProducts(); }, 400);
    });
    $(document).on('change', '#bw-rev-category', function () { revLoadProducts(); });

    // Load categories into the reviews dropdown
    function revLoadCategoryDropdown() {
        var $sel = $('#bw-rev-category');
        if (!$sel.length) return;
        post('bw_woo_ai_get_category_list', {}, function (cats) {
            cats.forEach(function (c) {
                var prefix = '';
                for (var d = 0; d < (c.depth || 0); d++) prefix += '— ';
                $sel.append('<option value="' + escHtml(c.slug) + '">' + prefix + escHtml(c.name) + ' (' + c.count + ')</option>');
            });
        });
    }

    // Rating total calculation
    $(document).on('input', '.bw-rev-rating-pct', function () {
        var total = 0;
        $('.bw-rev-rating-pct').each(function () { total += parseInt($(this).val()) || 0; });
        var $p = $('.bw-rev-rating-total');
        $p.text('Total: ' + total + '%');
        $p.css('color', total === 100 ? '#00a32a' : '#d63638');
    });

    // Length mode toggle
    $(document).on('change', '#bw-rev-length-mode', function () {
        $('#bw-rev-length-mix').toggle($(this).val() === 'mixed');
    });

    // Reviewer names toggle
    $(document).on('change', '#bw-rev-names', function () {
        $('#bw-rev-custom-names-wrap').toggle($(this).val() === 'custom');
    });

    // Collect all review settings from the UI
    function revCollectSettings() {
        return {
            reviews_mode:      $('input[name="bw_rev_mode"]:checked').val() || 'range',
            reviews_fixed:     $('#bw-rev-fixed').val(),
            reviews_min:       $('#bw-rev-min').val(),
            reviews_max:       $('#bw-rev-max').val(),
            rating_5_pct:      $('#bw-rev-r5').val(),
            rating_4_pct:      $('#bw-rev-r4').val(),
            rating_3_pct:      $('#bw-rev-r3').val(),
            rating_2_pct:      $('#bw-rev-r2').val(),
            rating_1_pct:      $('#bw-rev-r1').val(),
            length_mode:       $('#bw-rev-length-mode').val(),
            length_short_pct:  $('#bw-rev-len-short').val(),
            length_medium_pct: $('#bw-rev-len-medium').val(),
            length_long_pct:   $('#bw-rev-len-long').val(),
            tone:              $('#bw-rev-tone').val(),
            include_cons:      $('#bw-rev-cons').val(),
            reviewer_names:    $('#bw-rev-names').val(),
            custom_names:      $('#bw-rev-custom-names').val(),
            date_mode:         $('input[name="bw_rev_date"]:checked').val() || 'spread',
            date_spread_days:  $('#bw-rev-spread-days').val(),
            language:          $('#bw-rev-language').val(),
            mark_verified:     $('#bw-rev-verified').is(':checked') ? 'yes' : 'no'
        };
    }

    // Save settings
    $(document).on('click', '#bw-rev-save-settings', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('⏳');
        var data = revCollectSettings();
        post('bw_woo_ai_save_review_settings', data, function () {
            $btn.prop('disabled', false).text('💾 ' + (i18n.rev_settings_saved || 'Saved!'));
            setTimeout(function () { $btn.text('💾 Save Settings'); }, 2000);
        }, function (err) {
            alert(err);
            $btn.prop('disabled', false).text('💾 Save Settings');
        });
    });

    /* --- Generate reviews (preview mode, no insert) --- */

    $(document).on('click', '#bw-rev-generate', function () {
        var productIds = Object.keys(revSelectedProducts);
        if (!productIds.length) {
            alert(i18n.rev_no_products || 'No products selected');
            return;
        }

        var $btn = $(this).prop('disabled', true);

        // Save settings first
        var settings = revCollectSettings();
        post('bw_woo_ai_save_review_settings', settings, function () {
            revStartGeneration(productIds);
        }, function () {
            revStartGeneration(productIds); // Continue even if save fails
        });
    });

    function revStartGeneration(productIds) {
        revDoGeneration(productIds);
    }

    function revDoGeneration(productIds) {
        showRevStep(4);
        revGenerating = true;
        revPreviewData = {};

        var $results  = $('#bw-rev-preview-results').empty();
        var total     = productIds.length;
        var done      = 0;
        var successCount = 0;
        var provider  = $('#bw-rev-provider').val() || '';

        // Create placeholder cards
        productIds.forEach(function (pid) {
            var meta = revSelectedProducts[pid] || {};
            var name = meta.name || '#' + pid;
            var html = '<div class="bw-preview-card bw-compact-card bw-rev-card" id="bw-rev-compact-' + pid + '" data-pid="' + pid + '" data-approved="1">';
            html += '<div class="bw-compact-header">';
            html += '<span class="bw-compact-toggle dashicons dashicons-arrow-right-alt2"></span>';
            html += '<span class="bw-compact-name">' + escHtml(name) + ' <small>#' + pid + '</small></span>';
            html += '<span class="bw-compact-status bw-badge bw-badge-default">' + i18n.loading + '</span>';
            html += '</div>';
            html += '<div class="bw-compact-body" style="display:none;"></div>';
            html += '</div>';
            $results.append(html);
        });

        function updateProgress() {
            var pct = total > 0 ? Math.round((done / total) * 100) : 0;
            $('.bw-rev-progress-fill').css('width', pct + '%');
            $('#bw-rev-progress-count').text(done + '/' + total);
        }
        updateProgress();

        function processNext(idx) {
            if (idx >= productIds.length) {
                revGenerating = false;
                $('.bw-progress-text').text(i18n.done || 'Done!');
                $('#bw-rev-apply, #bw-rev-prev-4').show();
                $('#bw-rev-generate').prop('disabled', false);

                return;
            }

            var pid = productIds[idx];
            var $card = $('#bw-rev-compact-' + pid);
            $card.find('.bw-compact-status')
                .html('<span class="spinner is-active" style="float:none;margin:0;"></span> ' + (i18n.generating || 'Generating…'));

            post('bw_woo_ai_generate_reviews', {
                product_id: pid,
                provider: provider
            }, function (data) {
                done++;
                successCount++;
                updateProgress();
                revPreviewData[pid] = data;
                revExpandCard(pid, data);
                setTimeout(function () { processNext(idx + 1); }, 500);
            }, function (err) {
                done++;
                updateProgress();
                revExpandCard(pid, { error: err, product_name: (revSelectedProducts[pid] && revSelectedProducts[pid].name) || '#' + pid });
                setTimeout(function () { processNext(idx + 1); }, 500);
            });
        }

        processNext(0);
    }

    function revExpandCard(pid, result) {
        var $card = $('#bw-rev-compact-' + pid);
        var name = escHtml(result.product_name || 'Product #' + pid);

        $card.find('.bw-compact-name').html('<strong>' + name + '</strong> <small>#' + pid + '</small>');

        if (result.error) {
            $card.addClass('bw-preview-error').attr('data-approved', '0');
            $card.find('.bw-compact-status').html('<span class="bw-badge bw-badge-error">' + i18n.error + '</span>');
            $card.find('.bw-compact-body').html('<p class="bw-error-text">' + escHtml(result.error) + '</p>');
            return;
        }

        $card.find('.bw-compact-status').html(
            '<span class="bw-badge bw-badge-success">' + result.reviews.length + ' reviews</span> ' +
            '<button type="button" class="button button-small bw-rev-reject-btn" data-pid="' + pid + '" title="Reject">\u2715</button>'
        );

        // Build body showing individual reviews with editable content
        var bodyHtml = '';
        if (result.reviews && result.reviews.length) {
            result.reviews.forEach(function (r, i) {
                var stars = '';
                for (var s = 0; s < r.rating; s++) stars += '⭐';

                bodyHtml += '<div class="bw-rev-item" data-idx="' + i + '" style="padding:10px;margin:6px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">';
                bodyHtml += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
                bodyHtml += '<span style="font-weight:600;">' + stars + ' — ' + escHtml(r.name) + '</span>';
                bodyHtml += '<button type="button" class="button button-small bw-rev-remove-review" data-pid="' + pid + '" data-idx="' + i + '" title="Remove this review" style="color:#d63638;">✕</button>';
                bodyHtml += '</div>';
                if (r.title) {
                    bodyHtml += '<div style="font-weight:500;margin-bottom:4px;">' + escHtml(r.title) + '</div>';
                }
                bodyHtml += '<div class="bw-rev-content" contenteditable="true" style="padding:6px;background:white;border:1px solid #ddd;border-radius:4px;min-height:30px;">' + escHtml(r.content) + '</div>';
                bodyHtml += '</div>';
            });
        }

        $card.find('.bw-compact-body').html(bodyHtml);
    }

    // Reviews cards use the generic .bw-compact-header toggle handler above.
    // No duplicate handler needed here.

    // Reject / un-reject a product's reviews
    $(document).on('click', '.bw-rev-reject-btn', function (e) {
        e.stopPropagation();
        var $card = $(this).closest('.bw-preview-card');
        var isRejected = $card.hasClass('bw-rejected');
        if (isRejected) {
            $card.attr('data-approved', '1').removeClass('bw-rejected');
            $(this).removeClass('bw-active');
        } else {
            $card.attr('data-approved', '0').addClass('bw-rejected');
            $(this).addClass('bw-active');
        }
    });

    // Remove a single review from preview
    $(document).on('click', '.bw-rev-remove-review', function (e) {
        e.stopPropagation();
        var pid = $(this).data('pid');
        var idx = $(this).data('idx');
        if (revPreviewData[pid] && revPreviewData[pid].reviews) {
            revPreviewData[pid].reviews.splice(idx, 1);
        }
        $(this).closest('.bw-rev-item').slideUp(200, function () { $(this).remove(); });
        // Update badge count
        var remaining = $('#bw-rev-compact-' + pid + ' .bw-rev-item').length - 1;
        $('#bw-rev-compact-' + pid + ' .bw-badge-success').text(remaining + ' reviews');
    });

    // Sync edited review content back to revPreviewData on blur
    $(document).on('blur', '.bw-rev-content', function () {
        var $item = $(this).closest('.bw-rev-item');
        var $card = $(this).closest('.bw-rev-card');
        var pid   = $card.data('pid');
        var idx   = $item.data('idx');
        if (revPreviewData[pid] && revPreviewData[pid].reviews && revPreviewData[pid].reviews[idx]) {
            revPreviewData[pid].reviews[idx].content = $(this).text();
        }
    });

    /* --- Apply approved reviews --- */

    $(document).on('click', '#bw-rev-apply', function () {
        var approvedPids = [];
        $('.bw-rev-card[data-approved="1"]').each(function () {
            var pid = String($(this).data('pid'));
            if (revPreviewData[pid] && revPreviewData[pid].reviews && revPreviewData[pid].reviews.length) {
                approvedPids.push(pid);
            }
        });

        if (!approvedPids.length) {
            alert('No approved reviews to apply.');
            return;
        }

        if (!confirm('Apply reviews to ' + approvedPids.length + ' product(s)?')) return;

        var $btn = $(this).prop('disabled', true).text('⏳ Applying…');
        var total = approvedPids.length;
        var done  = 0;
        var totalInserted = 0;

        function applyNext(idx) {
            if (idx >= approvedPids.length) {
                $btn.text('✅ Applied ' + totalInserted + ' reviews');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('✅ Apply All Approved Reviews');
                }, 3000);
                return;
            }

            var pid = approvedPids[idx];
            var reviews = revPreviewData[pid].reviews;

            post('bw_woo_ai_apply_reviews', {
                product_id: pid,
                reviews: reviews
            }, function (data) {
                totalInserted += (data.inserted || 0);
                var $card = $('#bw-rev-compact-' + pid);
                $card.find('.bw-compact-status').html('<span class="bw-badge bw-badge-success">✅ ' + data.inserted + ' inserted</span>');
                done++;
                $btn.text('⏳ ' + done + '/' + total);
                applyNext(idx + 1);
            }, function (err) {
                var $card = $('#bw-rev-compact-' + pid);
                $card.find('.bw-compact-status').html('<span class="bw-badge bw-badge-error">❌ ' + escHtml(err) + '</span>');
                done++;
                $btn.text('⏳ ' + done + '/' + total);
                applyNext(idx + 1);
            });
        }

        applyNext(0);
    });

    // Delete AI reviews for a product
    $(document).on('click', '.bw-rev-delete-btn', function () {
        if (!confirm(i18n.rev_confirm_delete || 'Delete all AI-generated reviews for this product?')) return;
        var $btn = $(this);
        var pid = $btn.data('id');
        $btn.prop('disabled', true).text('⏳');

        post('bw_woo_ai_delete_ai_reviews', { product_id: pid }, function (data) {
            $btn.prop('disabled', false).text('🗑️');
            alert((i18n.rev_deleted || 'Deleted') + ' (' + data.deleted + ')');
            revLoadProducts();
        }, function (err) {
            $btn.prop('disabled', false).text('🗑️');
            alert(err);
        });
    });

    /* ================================================================
     *  INIT ON DOM READY
     * ================================================================ */

    $(function () {
        // Load category dropdown for filters
        loadCategoryDropdown();

        // Auto-load tables based on current tab
        if ($('#bw-categories-table-wrap').length) {
            loadCategories();
        }
        if ($('#bw-history-table-wrap').length) {
            loadHistoryCategoryDropdown();
            loadHistory(1);
        }

        // Bulk optimizer: load products if on bulk tab
        if ($('.bw-woo-bulk-wrap').length) {
            // Restore any selection from products tab
            var stored = sessionStorage.getItem('bw_woo_selected');
            if (stored) {
                try {
                    var ids = JSON.parse(stored);
                    ids.forEach(function (id) { selectedProducts[id] = true; });
                } catch (e) {}
                sessionStorage.removeItem('bw_woo_selected');
            }
            loadProducts(1);
        }

        // Reviews tab: load products and categories
        if ($('.bw-woo-reviews-wrap').length) {
            revLoadCategoryDropdown();
            revLoadProducts();
            // Trigger rating total calculation
            setTimeout(function () { $('.bw-rev-rating-pct').first().trigger('input'); }, 100);
        }
    });

})(jQuery);
