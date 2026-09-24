(function ($) {
    'use strict';
    const root = document.getElementById('bw-rewrite');
    if (!root) return;
    const words = BotwriterRewrite.i18n;
    const selected = new Map();
    const dialog = document.getElementById('bw-rewrite-dialog');
    let view = 'select';
    let page = 1;
    let pages = 1;
    let rows = [];
    let busy = false;
    let stopped = false;
    let detail = null;
    let dirty = false;
    let listSequence = 0;
    let returnFocus = null;
    const find = (suffix) => document.getElementById('bw-rewrite-' + suffix);
    const message = (text, error = false) => {
        find('message').textContent = text;
        find('message').classList.toggle('is-error', error);
    };
    async function request(operation, data = {}) {
        try {
            const result = await $.ajax({url: BotwriterSEO.ajax, method: 'POST', timeout: 240000,
                data: {...data, action: 'bw_seo_rewrite', nonce: BotwriterSEO.nonce, operation}});
            if (!result || !result.success) throw new Error(result?.data?.message || words.error);
            return result.data;
        } catch (error) {
            throw new Error(error.message || error.responseJSON?.data?.message || words.error);
        }
    }
    function selectionState() {
        find('count').textContent = words.selected + ' ' + selected.size;
        find('generate').disabled = busy || !selected.size || root.dataset.available !== '1';
        find('apply').disabled = busy || !selected.size || root.dataset.available !== '1';
        const eligible = rows.filter((row) => view !== 'pending' || row.approved);
        const checked = eligible.filter((row) => selected.has(row.id)).length;
        find('all').checked = eligible.length > 0 && checked === eligible.length;
        find('all').indeterminate = checked > 0 && checked < eligible.length;
        find('all').disabled = busy || !eligible.length;
        find('prev').disabled = busy || page <= 1;
        find('next').disabled = busy || page >= pages;
    }
    function setBusy(value) {
        busy = value;
        root.querySelectorAll('button, input, select, textarea').forEach((element) => { element.disabled = value; });
        find('stop').disabled = false;
        find('stop').hidden = !value;
        if (!value) {
            find('rows').querySelectorAll('input').forEach((input) => { input.disabled = view === 'pending' && !rows.find((row) => row.id === Number(input.value))?.approved; });
            find('approve').disabled = !!detail?.conflict;
            find('save-proposal').disabled = !!detail?.conflict;
        }
        selectionState();
    }
    function renderRows() {
        const body = find('rows');
        body.replaceChildren();
        rows.forEach((row) => {
            const line = document.createElement('tr');
            const checkboxCell = document.createElement('th');
            checkboxCell.className = 'check-column';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = row.id;
            checkbox.setAttribute('aria-label', row.title);
            checkbox.checked = selected.has(row.id);
            checkbox.disabled = busy || (view === 'pending' && !row.approved);
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) selected.set(row.id, row.title); else selected.delete(row.id);
                selectionState();
            });
            checkboxCell.append(checkbox);
            const titleCell = document.createElement('td');
            const link = document.createElement('a');
            link.href = row.edit_url;
            link.textContent = row.title;
            titleCell.append(link);
            const typeCell = document.createElement('td');
            typeCell.textContent = row.type + ' / ' + row.status;
            const stateCell = document.createElement('td');
            stateCell.textContent = row.approved ? words.approved : (row.pending ? words.pending : words.none);
            const actionCell = document.createElement('td');
            if (row.pending) {
                const review = document.createElement('button');
                review.type = 'button';
                review.className = 'button';
                review.textContent = words.review;
                review.disabled = busy;
                review.addEventListener('click', () => openDetail(row.id, review));
                actionCell.append(review);
            }
            line.append(checkboxCell, titleCell, typeCell, stateCell, actionCell);
            body.append(line);
        });
        if (!rows.length) {
            const line = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.textContent = words.empty;
            line.append(cell);
            body.append(line);
        }
        find('page').textContent = words.page + ' ' + page + ' ' + words.of + ' ' + Math.max(1, pages);
        selectionState();
    }
    async function loadList() {
        const sequence = ++listSequence;
        find('library').setAttribute('aria-busy', 'true');
        try {
            const result = await request('list', {page, type: find('type').value, status: find('status').value,
                term: find('term').value, search: find('search').value, pending: view === 'pending' ? 1 : 0});
            if (sequence !== listSequence) return;
            pages = result.pages;
            if (page > Math.max(1, pages)) { page = Math.max(1, pages); return loadList(); }
            rows = result.items;
            rows.forEach((row) => { if (view === 'pending' && !row.approved) selected.delete(row.id); });
            renderRows();
        } catch (error) { message(error.message, true); }
        finally { if (sequence === listSequence) find('library').removeAttribute('aria-busy'); }
    }
    function preview(frame, html) {
        frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https: http: data:; style-src \'unsafe-inline\'"><style>body{font:15px/1.6 Georgia,serif;color:#242424;padding:12px;overflow-wrap:anywhere}img,video{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}h1,h2,h3{line-height:1.3}</style></head><body>' + html + '</body></html>';
    }
    async function openDetail(postId, trigger) {
        setBusy(true);
        try {
            detail = await request('detail', {post_id: postId});
            dirty = false;
            find('dialog-title').textContent = detail.title;
            find('detail-prompt').value = detail.instructions;
            find('detail-message').textContent = '';
            find('conflict').textContent = words.conflict;
            find('conflict').hidden = !detail.conflict;
            preview(find('original-preview'), detail.original_html);
            preview(find('preview'), detail.preview_html);
            find('fragments').replaceChildren();
            Object.entries(detail.original).forEach(([key, text], index) => {
                const row = document.createElement('div');
                row.className = 'bw-rewrite-fragment';
                const original = document.createElement('p');
                original.textContent = text;
                const label = document.createElement('label');
                label.textContent = words.fragment + ' ' + (index + 1);
                const input = document.createElement('textarea');
                input.value = detail.texts[key];
                input.rows = Math.min(10, Math.max(2, Math.ceil(input.value.length / 70)));
                input.dataset.key = key;
                input.addEventListener('input', () => { dirty = true; });
                label.append(input);
                row.append(original, label);
                find('fragments').append(row);
            });
            if (!dialog.open) { returnFocus = trigger; dialog.showModal(); }
        } catch (error) { message(error.message, true); }
        finally { setBusy(false); }
    }
    function editedTexts() {
        const texts = {};
        find('fragments').querySelectorAll('textarea').forEach((input) => { texts[input.dataset.key] = input.value; });
        return texts;
    }
    async function saveProposal(approved) {
        setBusy(true);
        try {
            const texts = editedTexts();
            const result = await request('save', {post_id: detail.id, texts: JSON.stringify(texts), version: detail.version, approved: approved ? 1 : 0});
            detail.version = result.version;
            detail.texts = texts;
            dirty = false;
            preview(find('preview'), result.preview_html);
            find('detail-message').textContent = words.saved;
            if (approved) { if (view === 'pending') selected.set(detail.id, detail.title); dialog.close(); }
            else selected.delete(detail.id);
            await loadList();
        } catch (error) { find('detail-message').textContent = error.message; }
        finally { setBusy(false); }
    }
    async function refreshHistory() {
        try {
            const result = await $.post(BotwriterSEO.ajax, {action: 'bw_seo_bulk_undo_history', _nonce: BotwriterSEO.nonce});
            if (result.success) $('#bw-bulk-undo-root').html(result.data.html).prop('hidden', !result.data.html);
        } catch (error) { message(words.error, true); }
    }
    async function runBatch(operation) {
        if (!window.confirm(operation === 'generate' ? words.confirmGenerate : words.confirmApply)) return;
        const items = Array.from(selected);
        const instructions = find('prompt').value;
        stopped = false;
        setBusy(true);
        find('results').replaceChildren();
        find('progress').hidden = false;
        find('progress').max = items.length;
        find('progress').value = 0;
        let succeeded = 0;
        let failed = 0;
        for (const [postId, title] of items) {
            if (stopped) break;
            message((operation === 'generate' ? words.generating : words.applying) + ': ' + title);
            const entry = document.createElement('li');
            try {
                if (operation === 'generate') await request('generate', {post_id: postId, instructions});
                else {
                    const proposal = await request('detail', {post_id: postId});
                    await request('apply', {post_id: postId, version: proposal.version, texts: JSON.stringify(proposal.texts)});
                }
                succeeded++;
                selected.delete(postId);
                entry.textContent = title + ': ' + words.success;
            } catch (error) {
                failed++;
                entry.textContent = title + ': ' + error.message;
                entry.className = 'is-error';
            }
            find('results').append(entry);
            find('progress').value++;
            selectionState();
        }
        const hasProposals = operation === 'generate' && succeeded > 0;
        message((stopped ? words.stopped : words.done) + '. ' + words.success + ': ' + succeeded + '. ' + words.failed + ': ' + failed + (hasProposals ? '. ' + words.reviewReady : ''), failed > 0);
        const openPending = hasProposals && failed === 0 && !stopped;
        if (openPending) {
            find('search').value = '';
            find('status').value = '';
            await switchView('pending');
        } else {
            await loadList();
        }
        if (operation === 'apply') await refreshHistory();
        setBusy(false);
        if (openPending) {
            find('pending-tab').focus();
            find('pending-tab').scrollIntoView({block: 'start', behavior: 'smooth'});
        }
    }
    function filterTerms() {
        find('term').value = '';
        find('term').querySelectorAll('option[data-type]').forEach((option) => {
            option.hidden = option.dataset.type !== find('type').value;
            option.disabled = option.hidden;
        });
    }
    async function switchView(nextView) {
        view = nextView;
        const button = root.querySelector('[data-view="' + view + '"]');
        page = 1;
        selected.clear();
        root.querySelectorAll('[data-view]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === button)));
        find('library').setAttribute('aria-labelledby', button.id);
        find('instructions').hidden = view === 'pending';
        find('type-label').hidden = view === 'pending';
        find('term-label').hidden = view === 'pending';
        find('apply').hidden = view !== 'pending';
        await loadList();
    }
    root.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => switchView(button.dataset.view)));
    find('filters').addEventListener('submit', (event) => { event.preventDefault(); page = 1; loadList(); });
    find('type').addEventListener('change', filterTerms);
    find('prev').addEventListener('click', () => { page--; loadList(); });
    find('next').addEventListener('click', () => { page++; loadList(); });
    find('clear').addEventListener('click', () => { selected.clear(); renderRows(); });
    find('all').addEventListener('change', (event) => {
        rows.filter((row) => view !== 'pending' || row.approved).forEach((row) => {
            if (event.target.checked) selected.set(row.id, row.title); else selected.delete(row.id);
        });
        renderRows();
    });
    find('generate').addEventListener('click', () => runBatch('generate'));
    find('apply').addEventListener('click', () => runBatch('apply'));
    find('stop').addEventListener('click', () => { stopped = true; find('stop').disabled = true; });
    find('reset-template').addEventListener('click', () => { find('prompt').value = BotwriterRewrite.defaultPrompt; });
    find('save-template').addEventListener('click', async () => {
        setBusy(true);
        try { await request('template', {instructions: find('prompt').value}); message(words.saved); }
        catch (error) { message(error.message, true); }
        finally { setBusy(false); }
    });
    function closeDialog(event) {
        if (busy || (dirty && !window.confirm(words.unsaved))) { if (event) event.preventDefault(); return; }
        dirty = false;
        dialog.close();
    }
    find('close').addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', closeDialog);
    dialog.addEventListener('close', () => { if (returnFocus?.isConnected) returnFocus.focus(); else find('pending-tab').focus(); });
    find('save-proposal').addEventListener('click', () => saveProposal(false));
    find('approve').addEventListener('click', () => saveProposal(true));
    find('discard').addEventListener('click', async () => {
        if (!window.confirm(words.confirmDiscard)) return;
        setBusy(true);
        try {
            await request('discard', {post_id: detail.id, version: detail.version});
            selected.delete(detail.id);
            dirty = false;
            dialog.close();
            await loadList();
        } catch (error) { find('detail-message').textContent = error.message; }
        finally { setBusy(false); }
    });
    find('regenerate').addEventListener('click', async () => {
        if (!window.confirm(words.confirmRegenerate)) return;
        setBusy(true);
        find('detail-message').textContent = words.generating;
        try {
            await request('generate', {post_id: detail.id, instructions: find('detail-prompt').value});
            selected.delete(detail.id);
            await openDetail(detail.id);
            await loadList();
        } catch (error) { find('detail-message').textContent = error.message; }
        finally { setBusy(false); }
    });
    window.addEventListener('beforeunload', (event) => {
        if (busy || dirty) { event.preventDefault(); event.returnValue = words.unsaved; }
    });
    filterTerms();
    loadList();
})(jQuery);