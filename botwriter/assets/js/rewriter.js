/**
 * Content Rewriter - Frontend JavaScript
 * Manages the 3-step wizard: Fetch URLs → Review Content → Create Rewrite Task
 */
document.addEventListener("DOMContentLoaded", () => {
    const fetchBtn = document.getElementById('rewriter_fetch_btn');
    const createBtn = document.getElementById('rewriter_create_btn');
    const addManualBtn = document.getElementById('rewriter_add_manual_btn');

    if (fetchBtn) {
        fetchBtn.addEventListener('click', rewriterFetchUrls);
    }
    if (createBtn) {
        createBtn.addEventListener('click', rewriterCreateTask);
    }
    if (addManualBtn) {
        addManualBtn.addEventListener('click', rewriterAddManualArticle);
    }
});

/**
 * Toggle custom post length input visibility.
 * (Kept as alias in case the meta box form uses rewriter-specific IDs)
 */
function rewriterToggleCustomLength() {
    var sel = document.getElementById('rewriter_post_length');
    var wrap = document.getElementById('rewriter_custom_length_wrap');
    if (sel && wrap) {
        wrap.style.display = sel.value === 'custom' ? 'block' : 'none';
    }
}

/**
 * Collect days checkboxes from the Super Task Properties form.
 */
function rewriterCollectDays() {
    var checked = document.querySelectorAll('input[name="days[]"]');
    var days = [];
    checked.forEach(function(cb) {
        if (cb.checked) days.push(cb.value);
    });
    return days.join(',');
}



// Store extracted articles
let rewriterArticles = [];
let rewriterArticleCounter = 0;

/**
 * Step 1: Fetch and extract content from URLs
 */
function rewriterFetchUrls() {
    const urlsTextarea = document.getElementById('rewriter_urls');
    const statusDiv = document.getElementById('rewriter_fetch_status');
    const fetchBtn = document.getElementById('rewriter_fetch_btn');

    const rawUrls = urlsTextarea.value.trim();
    if (!rawUrls) {
        alert('Please enter at least one URL.');
        return;
    }

    const urls = rawUrls.split('\n').map(u => u.trim()).filter(u => u.length > 0);
    if (urls.length === 0) {
        alert('Please enter at least one valid URL.');
        return;
    }

    if (urls.length > 20) {
        alert('Maximum 20 URLs allowed at once.');
        return;
    }

    // Disable UI
    fetchBtn.disabled = true;
    urlsTextarea.disabled = true;
    statusDiv.innerHTML = '<p>⏳ Fetching content from ' + urls.length + ' URL(s)... This may take a moment.</p>';

    jQuery.ajax({
        url: botwriter_rewriter_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'botwriter_rewriter_fetch',
            urls: urls,
            _ajax_nonce: botwriter_rewriter_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                rewriterArticles = response.data.articles;
                const errors = response.data.errors;

                // Count articles with content warnings
                let warningCount = 0;
                rewriterArticles.forEach(function (a) {
                    if (a.content_warning === 'no_content' || a.content_warning === 'short_content') {
                        warningCount++;
                    }
                });

                let statusMsg = '<p>✅ Fetched ' + rewriterArticles.length + ' article(s) successfully.';
                if (warningCount > 0) {
                    statusMsg += ' ⚠️ ' + warningCount + ' article(s) need manual content — see warnings below.';
                }
                if (errors.length > 0) {
                    statusMsg += '</p><p>❌ ' + errors.length + ' URL(s) failed:</p><ul>';
                    errors.forEach(function (err) {
                        statusMsg += '<li><code>' + err.url + '</code>: ' + err.error + '</li>';
                    });
                    statusMsg += '</ul>';
                } else {
                    statusMsg += '</p>';
                }
                statusDiv.innerHTML = statusMsg;

                // Show Step 2 with extracted content
                rewriterRenderArticles();
                document.getElementById('rewriter_step2').style.display = 'block';
                document.getElementById('rewriter_step3').style.display = 'block';
            } else {
                statusDiv.innerHTML = '<p>❌ Error: ' + response.data + '</p>';
                fetchBtn.disabled = false;
                urlsTextarea.disabled = false;
            }
        },
        error: function (xhr, status, error) {
            statusDiv.innerHTML = '<p>❌ AJAX error: ' + error + '</p>';
            fetchBtn.disabled = false;
            urlsTextarea.disabled = false;
        }
    });
}

/**
 * Render extracted articles for review (Step 2)
 */
function rewriterRenderArticles() {
    const container = document.getElementById('rewriter_articles_list');
    container.innerHTML = '';
    rewriterArticleCounter = 0;

    rewriterArticles.forEach(function (article, index) {
        rewriterArticleCounter++;
        container.appendChild(rewriterCreateArticleCard(article, index));
    });
}

/**
 * Create a single article card DOM element
 */
function rewriterCreateArticleCard(article, index) {
    const card = document.createElement('div');
    card.className = 'rewriter-article-card';
    card.setAttribute('data-index', index);

    const hasWarning = article.content_warning === 'no_content' || article.content_warning === 'short_content';
    const isNoContent = article.content_warning === 'no_content';

    if (hasWarning) {
        card.classList.add('rewriter-card-warning');
    }

    // Build warning banner HTML
    let warningHtml = '';
    if (isNoContent) {
        warningHtml =
            '<div class="rewriter-card-alert">' +
            '<span class="rewriter-alert-icon">⚠️</span> ' +
            '<span>Content could not be extracted from this page. ' +
            'Please <strong>open the source URL</strong>, select the article text, ' +
            'copy it and paste it in the text area below.</span>' +
            '</div>';
    } else if (article.content_warning === 'short_content') {
        warningHtml =
            '<div class="rewriter-card-alert rewriter-card-alert-soft">' +
            '<span class="rewriter-alert-icon">⚠️</span> ' +
            '<span>Only a small portion of the content was extracted. ' +
            'Please review and paste the full article text if needed.</span>' +
            '</div>';
    }

    card.innerHTML =
        '<div class="rewriter-card-header">' +
        '<span class="rewriter-card-num">#' + (index + 1) + '</span>' +
        '<input type="text" class="rewriter-title-input" value="' + escapeHtml(article.title) + '" />' +
        '<button type="button" class="button rewriter-toggle-btn" onclick="rewriterToggleContent(this)" title="Expand/Collapse">' + (hasWarning ? '▲' : '▼') + '</button>' +
        '<button type="button" class="button rewriter-remove-btn" onclick="rewriterRemoveArticle(this)" title="Remove">❌</button>' +
        '</div>' +
        '<div class="rewriter-card-meta">' +
        (article.url ? '<small>Source: <a href="' + escapeHtml(article.url) + '" target="_blank">' + escapeHtml(article.url) + '</a></small>' : '') +
        '</div>' +
        warningHtml +
        '<div class="rewriter-card-content"' + (hasWarning ? '' : ' style="display: none;"') + '>' +
        '<textarea class="rewriter-content-textarea" rows="10" placeholder="' +
        (isNoContent ? 'Paste the original article content here...' : '') +
        '">' + escapeHtml(article.content) + '</textarea>' +
        '</div>';

    return card;
}

/**
 * Toggle content visibility
 */
function rewriterToggleContent(btn) {
    const card = btn.closest('.rewriter-article-card');
    const contentDiv = card.querySelector('.rewriter-card-content');
    if (contentDiv.style.display === 'none') {
        contentDiv.style.display = 'block';
        btn.textContent = '▲';
    } else {
        contentDiv.style.display = 'none';
        btn.textContent = '▼';
    }
}

/**
 * Remove an article card
 */
function rewriterRemoveArticle(btn) {
    const card = btn.closest('.rewriter-article-card');
    card.remove();
}

/**
 * Add a manual article card (for pasting content directly)
 */
function rewriterAddManualArticle() {
    const container = document.getElementById('rewriter_articles_list');
    rewriterArticleCounter++;
    const index = rewriterArticleCounter;

    const article = {
        title: '',
        content: '',
        url: '',
        excerpt: ''
    };

    const card = document.createElement('div');
    card.className = 'rewriter-article-card';
    card.setAttribute('data-index', index);
    card.innerHTML =
        '<div class="rewriter-card-header">' +
        '<span class="rewriter-card-num">#' + index + '</span>' +
        '<input type="text" class="rewriter-title-input" placeholder="Article title" />' +
        '<button type="button" class="button rewriter-toggle-btn" title="Expand/Collapse">▲</button>' +
        '<button type="button" class="button rewriter-remove-btn" onclick="rewriterRemoveArticle(this)" title="Remove">❌</button>' +
        '</div>' +
        '<div class="rewriter-card-meta"><small>Manual entry</small></div>' +
        '<div class="rewriter-card-content">' +
        '<textarea class="rewriter-content-textarea" rows="10" placeholder="Paste the original article content here..."></textarea>' +
        '</div>';

    card.querySelector('.rewriter-toggle-btn').addEventListener('click', function () {
        rewriterToggleContent(this);
    });

    container.appendChild(card);

    // Scroll to new card
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Step 3: Create the rewrite task
 */
function rewriterCreateTask() {
    const createBtn = document.getElementById('rewriter_create_btn');
    const statusDiv = document.getElementById('rewriter_create_status');

    // Collect articles from the cards
    const cards = document.querySelectorAll('.rewriter-article-card');
    if (cards.length === 0) {
        alert('No articles to rewrite. Please fetch URLs or add articles manually.');
        return;
    }

    const articles = [];
    cards.forEach(function (card) {
        const title = card.querySelector('.rewriter-title-input').value.trim();
        const content = card.querySelector('.rewriter-content-textarea').value.trim();
        if (title || content) {
            articles.push({
                title: title,
                content: content
            });
        }
    });

    if (articles.length === 0) {
        alert('All articles are empty. Please add content.');
        return;
    }

    const rewritePrompt = document.getElementById('rewriter_prompt').value.trim();
    const categoryId = document.getElementById('rewriter_category').value;

    // Collect task properties from the Super Task Properties meta box form
    var taskName = document.getElementById('task_name') ? document.getElementById('task_name').value.trim() : '';
    var postStatus = document.getElementById('post_status') ? document.getElementById('post_status').value : 'draft';
    var postLanguage = document.getElementById('post_language') ? document.getElementById('post_language').value : 'en';
    var authorEl = document.querySelector('select[name="author_selection"]');
    var authorSelection = authorEl ? authorEl.value : '';
    var postLength = document.getElementById('post_length') ? document.getElementById('post_length').value : '800';
    var customPostLength = '';
    if (postLength === 'custom') {
        customPostLength = document.getElementById('custom_post_length') ? document.getElementById('custom_post_length').value : '';
    }
    var templateId = document.getElementById('template_id') ? document.getElementById('template_id').value : '';
    var days = rewriterCollectDays();
    var timesPerDayEl = document.querySelector('input[name="times_per_day"]');
    var timesPerDay = timesPerDayEl ? timesPerDayEl.value : '1';
    var disableAiImages = 0;
    var imgCheckbox = document.getElementById('disable_ai_images_checkbox');
    if (imgCheckbox && imgCheckbox.checked) {
        disableAiImages = 1;
    }

    // Validate days selection
    if (!days) {
        alert('Please select at least one day of the week.');
        return;
    }

    createBtn.disabled = true;
    statusDiv.innerHTML = '<p>⏳ Creating rewrite task...</p>';

    jQuery.ajax({
        url: botwriter_rewriter_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'botwriter_rewriter_create_task',
            articles: JSON.stringify(articles),
            rewrite_prompt: rewritePrompt,
            category_id: categoryId,
            post_status: postStatus,
            post_language: postLanguage,
            author_selection: authorSelection,
            post_length: postLength,
            custom_post_length: customPostLength,
            template_id: templateId,
            days: days,
            times_per_day: timesPerDay,
            task_name: taskName,
            disable_ai_images: disableAiImages,
            _ajax_nonce: botwriter_rewriter_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                statusDiv.innerHTML =
                    '<div class="rewriter-success-banner">' +
                    '<h3>✅ Rewrite Task Created!</h3>' +
                    '<p>' + articles.length + ' article(s) queued for rewriting.</p>' +
                    '<p><a href="' + botwriter_rewriter_ajax.logs_url + '">→ View Logs (monitor task progress)</a></p>' +
                    '<p><a href="' + response.data.edit_url + '">→ Review & configure the Super Task</a></p>' +
                    '</div>';
            } else {
                statusDiv.innerHTML = '<p>❌ Error: ' + response.data + '</p>';
                createBtn.disabled = false;
            }
        },
        error: function (xhr, status, error) {
            statusDiv.innerHTML = '<p>❌ AJAX error: ' + error + '</p>';
            createBtn.disabled = false;
        }
    });
}

/**
 * Utility: Escape HTML to prevent XSS in dynamic content
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
