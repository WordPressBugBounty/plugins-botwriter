/**
 * Site Rewriter – Frontend JavaScript
 * 3-step wizard: Crawl Website → Select & Fetch Content → Review & Create Task
 */
document.addEventListener('DOMContentLoaded', function () {
    var crawlBtn   = document.getElementById('siterewriter_crawl_btn');
    var stopBtn    = document.getElementById('siterewriter_stop_btn');
    var fetchBtn   = document.getElementById('siterewriter_fetch_btn');
    var createBtn  = document.getElementById('siterewriter_create_btn');
    var selectAll  = document.getElementById('siterewriter_select_all');

    if (crawlBtn)  crawlBtn.addEventListener('click', siterewriterCrawl);
    if (stopBtn)   stopBtn.addEventListener('click', siterewriterStopCrawl);
    if (fetchBtn)  fetchBtn.addEventListener('click', siterewriterFetchContent);
    if (createBtn) createBtn.addEventListener('click', siterewriterCreateTask);
    if (selectAll) selectAll.addEventListener('change', siterewriterToggleSelectAll);
});

/* ── helpers ── */

function siterewriterCollectDays() {
    var days = [];
    document.querySelectorAll('input[name="days[]"]').forEach(function (cb) {
        if (cb.checked) days.push(cb.value);
    });
    return days.join(',');
}

function siterewriterEscape(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text || ''));
    return div.innerHTML;
}

/* ── state ── */

var siterewriterCrawledPages = [];
var siterewriterArticles     = [];
var siterewriterCrawlStopped = false;

/* ═══════════════════════════════════════════
   Step 1 – Progressive Crawl (client-side BFS)
   ═══════════════════════════════════════════ */

function siterewriterGetDomain(url) {
    try { return new URL(url).hostname.toLowerCase(); } catch (e) { return ''; }
}

function siterewriterNormUrl(url) {
    try {
        var u = new URL(url);
        var p = u.pathname.replace(/\/+$/, '') || '/';
        return (u.protocol + '//' + u.hostname.toLowerCase() + p + u.search).replace(/\/$/, '');
    } catch (e) { return url; }
}

function siterewriterCrawl() {
    var rootUrl  = document.getElementById('siterewriter_root_url').value.trim();
    var maxDepth = parseInt(document.getElementById('siterewriter_depth').value) || 1;
    var maxUrls  = parseInt(document.getElementById('siterewriter_max_urls').value) || 20;
    var btn      = document.getElementById('siterewriter_crawl_btn');
    var stopBtn  = document.getElementById('siterewriter_stop_btn');
    var status   = document.getElementById('siterewriter_crawl_status');
    var liveBox  = document.getElementById('siterewriter_crawl_live');

    if (!rootUrl) { alert('Please enter a website URL.'); return; }
    if (!/^https?:\/\//i.test(rootUrl)) rootUrl = 'https://' + rootUrl;

    var baseDomain = siterewriterGetDomain(rootUrl);
    if (!baseDomain) { alert('Invalid URL.'); return; }

    maxUrls = Math.max(1, Math.min(200, maxUrls));

    // Reset
    siterewriterCrawledPages = [];
    siterewriterCrawlStopped = false;
    btn.disabled = true;
    stopBtn.style.display = 'inline-block';
    status.innerHTML = '<p>⏳ Crawling website… <span id="siterewriter_crawl_counter">0</span> page(s) found</p>';
    liveBox.style.display = 'block';
    liveBox.innerHTML = '';

    document.getElementById('siterewriter_step2').style.display = 'none';
    document.getElementById('siterewriter_step3').style.display = 'none';

    // BFS state
    var queue   = [{ url: rootUrl, depth: 0 }];
    var visited = {};
    visited[siterewriterNormUrl(rootUrl)] = true;

    function crawlNext() {
        // Stop conditions
        if (siterewriterCrawlStopped || siterewriterCrawledPages.length >= maxUrls || queue.length === 0) {
            btn.disabled = false;
            stopBtn.style.display = 'none';
            if (siterewriterCrawledPages.length > 0) {
                status.innerHTML = '<p>✅ Found <strong>' + siterewriterCrawledPages.length + '</strong> page(s).' +
                    (siterewriterCrawlStopped ? ' (Stopped by user)' : '') + '</p>';
                siterewriterRenderPages();
                document.getElementById('siterewriter_step2').style.display = 'block';
            } else {
                status.innerHTML = '<p>⚠️ No pages discovered. Try a different URL or increase the depth.</p>';
            }
            return;
        }

        var item = queue.shift();

        jQuery.ajax({
            url:     botwriter_siterewriter_ajax.ajax_url,
            type:    'POST',
            timeout: 25000,
            data: {
                action:      'botwriter_siterewriter_crawl',
                url:         item.url,
                base_domain: baseDomain,
                _ajax_nonce: botwriter_siterewriter_ajax.nonce
            },
            success: function (r) {
                if (r.success) {
                    var page = {
                        url: r.data.url,
                        title: r.data.title,
                        depth: item.depth,
                        content: r.data.content || '',
                        content_warning: r.data.content_warning || ''
                    };
                    siterewriterCrawledPages.push(page);

                    // Live row
                    var row = document.createElement('div');
                    row.style.cssText = 'padding:3px 0; border-bottom:1px solid #eee; font-size:13px; display:flex; gap:10px; align-items:baseline;';
                    row.innerHTML = '<span style="color:#888; min-width:24px;">' + siterewriterCrawledPages.length + '</span>' +
                        '<span style="font-weight:500;">' + siterewriterEscape(page.title || '(No title)') + '</span>' +
                        '<a href="' + siterewriterEscape(page.url) + '" target="_blank" style="color:#0073aa; font-size:12px; word-break:break-all;">' + siterewriterEscape(page.url) + '</a>';
                    liveBox.appendChild(row);
                    liveBox.scrollTop = liveBox.scrollHeight;

                    // Update counter
                    var cnt = document.getElementById('siterewriter_crawl_counter');
                    if (cnt) cnt.textContent = siterewriterCrawledPages.length;

                    // Enqueue discovered links
                    if (item.depth < maxDepth && r.data.links && r.data.links.length) {
                        r.data.links.forEach(function (link) {
                            var norm = siterewriterNormUrl(link);
                            if (!visited[norm] && siterewriterCrawledPages.length + queue.length < maxUrls) {
                                visited[norm] = true;
                                queue.push({ url: link, depth: item.depth + 1 });
                            }
                        });
                    }
                }
                // Continue regardless of per-page failure
                crawlNext();
            },
            error: function () {
                // Skip failed URL, continue
                crawlNext();
            }
        });
    }

    crawlNext();
}

function siterewriterStopCrawl() {
    siterewriterCrawlStopped = true;
}

/* ── render crawled-page list with checkboxes ── */

function siterewriterRenderPages() {
    var container = document.getElementById('siterewriter_pages_list');
    container.innerHTML = '';

    siterewriterCrawledPages.forEach(function (page, i) {
        var row = document.createElement('div');
        row.className = 'siterewriter-page-row';
        row.innerHTML =
            '<label class="siterewriter-page-label">' +
                '<input type="checkbox" class="siterewriter-page-cb" data-index="' + i + '" checked> ' +
                '<span class="siterewriter-page-title">' + siterewriterEscape(page.title || '(No title)') + '</span>' +
                '<span class="siterewriter-page-url"><a href="' + siterewriterEscape(page.url) + '" target="_blank">' + siterewriterEscape(page.url) + '</a></span>' +
                '<span class="siterewriter-page-depth">Depth ' + page.depth + '</span>' +
            '</label>';
        container.appendChild(row);
    });

    siterewriterUpdateCount();

    container.querySelectorAll('.siterewriter-page-cb').forEach(function (cb) {
        cb.addEventListener('change', siterewriterUpdateCount);
    });
}

function siterewriterToggleSelectAll() {
    var checked = document.getElementById('siterewriter_select_all').checked;
    document.querySelectorAll('.siterewriter-page-cb').forEach(function (cb) { cb.checked = checked; });
    siterewriterUpdateCount();
}

function siterewriterUpdateCount() {
    var total    = document.querySelectorAll('.siterewriter-page-cb').length;
    var selected = document.querySelectorAll('.siterewriter-page-cb:checked').length;
    var el = document.getElementById('siterewriter_selected_count');
    if (el) el.textContent = selected + ' / ' + total + ' selected';
}

/* ═══════════════════════════════════════════
   Step 2 – Fetch content for selected pages
   ═══════════════════════════════════════════ */

function siterewriterFetchContent() {
    var btn    = document.getElementById('siterewriter_fetch_btn');
    var status = document.getElementById('siterewriter_fetch_status');

    var selectedPages = [];
    document.querySelectorAll('.siterewriter-page-cb:checked').forEach(function (cb) {
        var idx = parseInt(cb.getAttribute('data-index'));
        selectedPages.push(siterewriterCrawledPages[idx]);
    });

    if (selectedPages.length === 0) { alert('Please select at least one page.'); return; }

    // Content was already extracted during crawl — build articles directly from cache
    siterewriterArticles = [];
    var warningCount = 0;

    selectedPages.forEach(function (page) {
        var cw = page.content_warning || '';
        if (cw === 'no_content' || cw === 'short_content') warningCount++;

        siterewriterArticles.push({
            title:           page.title || '',
            content:         page.content || '',
            url:             page.url || '',
            content_warning: cw
        });
    });

    var msg = '<p>✅ Extracted <strong>' + siterewriterArticles.length + '</strong> article(s) (content cached from crawl).';
    if (warningCount > 0) {
        msg += ' ⚠️ ' + warningCount + ' article(s) need manual review — see warnings below.';
    }
    msg += '</p>';
    status.innerHTML = msg;

    siterewriterRenderArticles();
    document.getElementById('siterewriter_step3').style.display = 'block';
}

/* ═══════════════════════════════════════════
   Step 3 – Review articles & create task
   ═══════════════════════════════════════════ */

function siterewriterRenderArticles() {
    var container = document.getElementById('siterewriter_articles_list');
    container.innerHTML = '';

    siterewriterArticles.forEach(function (article, index) {
        container.appendChild(siterewriterCreateCard(article, index));
    });
}

function siterewriterCreateCard(article, index) {
    var card = document.createElement('div');
    card.className = 'rewriter-article-card';
    card.setAttribute('data-index', index);

    var hasWarning = article.content_warning === 'no_content' || article.content_warning === 'short_content';
    var isNoContent = article.content_warning === 'no_content';
    if (hasWarning) card.classList.add('rewriter-card-warning');

    var warningHtml = '';
    if (isNoContent) {
        warningHtml = '<div class="rewriter-card-alert"><span class="rewriter-alert-icon">⚠️</span> Content could not be extracted. Please paste it manually.</div>';
    } else if (article.content_warning === 'short_content') {
        warningHtml = '<div class="rewriter-card-alert rewriter-card-alert-soft"><span class="rewriter-alert-icon">⚠️</span> Only partial content extracted. Review and complete if needed.</div>';
    }

    card.innerHTML =
        '<div class="rewriter-card-header">' +
            '<span class="rewriter-card-num">#' + (index + 1) + '</span>' +
            '<input type="text" class="rewriter-title-input" value="' + siterewriterEscape(article.title) + '" />' +
            '<button type="button" class="button rewriter-toggle-btn" onclick="siterewriterToggleCard(this)" title="Expand/Collapse">' + (hasWarning ? '▲' : '▼') + '</button>' +
            '<button type="button" class="button rewriter-remove-btn" onclick="siterewriterRemoveCard(this)" title="Remove">❌</button>' +
        '</div>' +
        '<div class="rewriter-card-meta">' +
            (article.url ? '<small>Source: <a href="' + siterewriterEscape(article.url) + '" target="_blank">' + siterewriterEscape(article.url) + '</a></small>' : '') +
        '</div>' +
        warningHtml +
        '<div class="rewriter-card-content"' + (hasWarning ? '' : ' style="display: none;"') + '>' +
            '<textarea class="rewriter-content-textarea" rows="10">' + siterewriterEscape(article.content) + '</textarea>' +
        '</div>';

    return card;
}

function siterewriterToggleCard(btn) {
    var content = btn.closest('.rewriter-article-card').querySelector('.rewriter-card-content');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.textContent = '▲';
    } else {
        content.style.display = 'none';
        btn.textContent = '▼';
    }
}

function siterewriterRemoveCard(btn) {
    btn.closest('.rewriter-article-card').remove();
}

/* ── create task ── */

function siterewriterCreateTask() {
    var btn    = document.getElementById('siterewriter_create_btn');
    var status = document.getElementById('siterewriter_create_status');

    var cards = document.querySelectorAll('#siterewriter_articles_list .rewriter-article-card');
    if (cards.length === 0) { alert('No articles to rewrite.'); return; }

    var articles = [];
    cards.forEach(function (card) {
        var title   = card.querySelector('.rewriter-title-input').value.trim();
        var content = card.querySelector('.rewriter-content-textarea').value.trim();
        if (title || content) articles.push({ title: title, content: content });
    });
    if (articles.length === 0) { alert('All articles are empty.'); return; }

    var rewritePrompt = document.getElementById('siterewriter_prompt').value.trim();
    var categoryId    = document.getElementById('siterewriter_category').value;

    // Shared meta-box properties
    var taskName        = document.getElementById('task_name')       ? document.getElementById('task_name').value.trim() : '';
    var postStatus      = document.getElementById('post_status')     ? document.getElementById('post_status').value      : 'draft';
    var postLanguage    = document.getElementById('post_language')   ? document.getElementById('post_language').value    : 'en';
    var authorEl        = document.querySelector('select[name="author_selection"]');
    var authorSelection = authorEl ? authorEl.value : '';
    var postLength      = document.getElementById('post_length')     ? document.getElementById('post_length').value      : '800';
    var customPostLength = '';
    if (postLength === 'custom') {
        customPostLength = document.getElementById('custom_post_length') ? document.getElementById('custom_post_length').value : '';
    }
    var templateId      = document.getElementById('template_id')     ? document.getElementById('template_id').value      : '';
    var days            = siterewriterCollectDays();
    var timesPerDayEl   = document.querySelector('input[name="times_per_day"]');
    var timesPerDay     = timesPerDayEl ? timesPerDayEl.value : '1';
    var disableAiImages = 0;
    var imgCb = document.getElementById('disable_ai_images_checkbox');
    if (imgCb && imgCb.checked) disableAiImages = 1;

    if (!days) { alert('Please select at least one day.'); return; }

    btn.disabled = true;
    status.innerHTML = '<p>⏳ Creating rewrite task…</p>';

    jQuery.ajax({
        url:  botwriter_siterewriter_ajax.ajax_url,
        type: 'POST',
        data: {
            action:            'botwriter_siterewriter_create_task',
            articles:          JSON.stringify(articles),
            rewrite_prompt:    rewritePrompt,
            category_id:       categoryId,
            post_status:       postStatus,
            post_language:     postLanguage,
            author_selection:  authorSelection,
            post_length:       postLength,
            custom_post_length: customPostLength,
            template_id:       templateId,
            days:              days,
            times_per_day:     timesPerDay,
            task_name:         taskName,
            disable_ai_images: disableAiImages,
            _ajax_nonce:       botwriter_siterewriter_ajax.nonce
        },
        success: function (r) {
            if (r.success) {
                status.innerHTML =
                    '<div class="rewriter-success-banner">' +
                        '<h3>✅ Rewrite Task Created!</h3>' +
                        '<p>' + articles.length + ' article(s) queued for rewriting.</p>' +
                        '<p><a href="' + botwriter_siterewriter_ajax.logs_url + '">→ View Logs (monitor task progress)</a></p>' +
                        '<p><a href="' + r.data.edit_url + '">→ Review & configure the Super Task</a></p>' +
                    '</div>';
            } else {
                status.innerHTML = '<p>❌ ' + (r.data || 'Unknown error') + '</p>';
                btn.disabled = false;
            }
        },
        error: function (xhr, st, err) {
            status.innerHTML = '<p>❌ ' + err + '</p>';
            btn.disabled = false;
        }
    });
}
