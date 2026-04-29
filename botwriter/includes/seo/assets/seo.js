/* BotWriter SEO — admin scripts */
(function ($) {
  'use strict';

  function ajax(action, data, options) {
    var payload = $.extend({ action: action, _nonce: BotwriterSEO.nonce }, data || {});
    var settings = $.extend({
      url: BotwriterSEO.ajax,
      method: 'POST',
      data: payload
    }, options || {});
    return $.ajax(settings);
  }

  var jobPollTokens = {};

  function nextJobPollToken(job) {
    jobPollTokens[job] = (jobPollTokens[job] || 0) + 1;
    return jobPollTokens[job];
  }

  function isCurrentJobPoll(job, token) {
    return (jobPollTokens[job] || 0) === token;
  }

  function stopJobPolling(job) {
    nextJobPollToken(job);
  }

  function setLiveProgressTitle(label) {
    var $title = $('.bw-live-progress-title').first();
    if (!$title.length) { return; }
    var fallback = $title.attr('data-default-title') || 'Bulk action progress';
    $title.text(label || fallback);
  }

  function focusJobCard(job) {
    var $jobBox = $('.bw-job[data-job="' + job + '"]').first();
    if (!$jobBox.length) { return; }
    var $card = $jobBox.closest('.bw-card');
    if (!$card.length) { return; }
    if (!$card.is('[tabindex]')) {
      $card.attr('tabindex', '-1');
    }
    var cardEl = $card.get(0);
    if (!cardEl) { return; }
    try {
      cardEl.focus({ preventScroll: true });
    } catch (err) {
      cardEl.focus();
    }
    if (typeof cardEl.scrollIntoView === 'function') {
      cardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // Bulk job poller
  $(document).on('click', '.bw-job-start', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.prop('disabled')) { return; }
    var job = $btn.data('job');
    var args = $btn.data('args') || {};
    startJob(job, args, $btn);
  });

  // Open the configuration modal before running.
  $(document).on('click', '.bw-job-configure', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.prop('disabled')) { return; }
    var job = $btn.data('job');
    var mode = $btn.data('mode') || 'analysis';
    var args = $btn.data('args') || {};
    var features = $btn.data('features') || [];
    var actionLabel = $btn.data('action-label') || '';
    openScopeModal({ job: job, mode: mode, args: args, features: features, actionLabel: actionLabel, btn: $btn });
  });

  function startJob(job, args, $btn) {
    var $jobBox = $('.bw-job[data-job="' + job + '"]').first();
    var actionLabel = ($btn && $btn.length) ? ($btn.data('action-label') || '') : '';
    var pollToken = nextJobPollToken(job);
    if ($btn) { $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + (BotwriterSEO.i18n.starting || 'Starting...')); }
    // Disable all configure buttons targeting this job too.
    $('.bw-job-configure[data-job="' + job + '"], .bw-job-start[data-job="' + job + '"]').prop('disabled', true);
    $('.bw-job-cancel[data-job="' + job + '"]').prop('disabled', false);
    if ($jobBox.length) {
      setLiveProgressTitle(actionLabel);
      focusJobCard(job);
      $jobBox.find('.bw-progress > div').css('width', '0%');
      $jobBox.find('.bw-job-stats').text('0 / 0 (0%)');
      $jobBox.find('.bw-job-message').text('');
      $jobBox.find('.bw-job-label').text('Pending');
      $jobBox.find('.bw-job-feed').empty();
      $jobBox.find('.bw-job-feed-wrap').prop('hidden', false);
    }
    return ajax('bw_seo_job_start', { job: job, args: JSON.stringify(args) }).done(function (res) {
      if (!res.success) {
        alert(res.data && res.data.message ? res.data.message : 'Error');
        $('.bw-job-configure[data-job="' + job + '"], .bw-job-start[data-job="' + job + '"]').prop('disabled', false);
        if ($btn) { $btn.text(BotwriterSEO.i18n.retry || 'Retry'); }
        return;
      }
        if ($jobBox.length) { pollJob(job, $jobBox, pollToken); }
    }).fail(function () {
      $('.bw-job-configure[data-job="' + job + '"], .bw-job-start[data-job="' + job + '"]').prop('disabled', false);
      if ($btn) { $btn.text(BotwriterSEO.i18n.retry || 'Retry'); }
    });
  }

  $(document).on('click', '.bw-job-cancel', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.prop('disabled')) { return; }
    var job = $btn.data('job');
    if (!window.confirm((BotwriterSEO.i18n.confirm_cancel_job || 'Cancel current run?'))) { return; }
    $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + (BotwriterSEO.i18n.cancelling || 'Cancelling...'));
    ajax('bw_seo_job_cancel', { job: job }).done(function (res) {
      if (!res || !res.success) {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancel_job || 'Cancel current run'));
        alert((res && res.data && res.data.message) ? res.data.message : 'Error');
        return;
      }
      stopJobPolling(job);
      var d = res.data || {};
      var $jobBox = $('.bw-job[data-job="' + job + '"]').first();
      if ($jobBox.length) {
        var pct = d.total > 0 ? Math.round((d.done / d.total) * 100) : 0;
        $jobBox.find('.bw-progress > div').css('width', pct + '%');
        $jobBox.find('.bw-job-stats').text((d.done || 0) + ' / ' + (d.total || 0) + ' (' + pct + '%)');
        $jobBox.find('.bw-job-message').text(d.message || (BotwriterSEO.i18n.cancelled || 'Cancelled'));
        $jobBox.find('.bw-job-label').text(BotwriterSEO.i18n.cancelled || 'Cancelled');
        setLiveProgressTitle(d.action_label || '');
      }
      $('.bw-job-configure[data-job="' + job + '"], .bw-job-start[data-job="' + job + '"]').prop('disabled', false);
      $('.bw-job-cancel[data-job="' + job + '"]').prop('disabled', true).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancelled || 'Cancelled'));
      if (typeof window.bwSeoReloadUndoHistory === 'function') { window.bwSeoReloadUndoHistory(); }
    }).fail(function () {
      $btn.prop('disabled', false).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancel_job || 'Cancel current run'));
      alert('Network error');
    });
  });

  function pollJob(job, $container, pollToken) {
    pollToken = (typeof pollToken === 'number') ? pollToken : nextJobPollToken(job);
    var $progress = $container.find('.bw-progress > div');
    var $stats = $container.find('.bw-job-stats');
    var $msg = $container.find('.bw-job-message');
    var $label = $container.find('.bw-job-label');
    var $feedWrap = $container.find('.bw-job-feed-wrap');
    var $feed = $container.find('.bw-job-feed');
    var seen = {};
    var retryDelay = 1500;
    var maxRetryDelay = 12000;
    var requestTimeout = 45000;
    var isStopped = false;

    function scheduleNext(delay) {
      if (isStopped || !isCurrentJobPoll(job, pollToken)) { return; }
      setTimeout(tick, delay);
    }

    function toneOf(item) {
      if (item && item.result_tone) { return item.result_tone; }
      if (item && item.status === 'good') { return 'success'; }
      if (item && item.status === 'skipped') { return 'info'; }
      return 'warn';
    }

    function appendRecent(items) {
      if (!items || !items.length) return;
      $feedWrap.prop('hidden', false);
      var viewLabel = BotwriterSEO.i18n.view_analysis || 'View Analysis';
      var editLabel = BotwriterSEO.i18n.edit_post || 'Edit post';
      // Items come newest-first; prepend in reverse so newest stays on top.
      for (var i = items.length - 1; i >= 0; i--) {
        var it = items[i];
        if (!it || !it.id || seen[it.id]) continue;
        seen[it.id] = true;
        var tone = toneOf(it);
        var resultLabel = it.result_label || it.message || '';
        var detail = it.detail || '';
        var $li = $('<li class="bw-feed-item is-new"></li>').attr('data-id', it.id);
        var $main = $('<div class="bw-feed-main"></div>');
        var $primary = $('<div class="bw-feed-primary"></div>');
        var $meta = $('<div class="bw-feed-meta"></div>');
        var $actions = $('<span class="bw-feed-actions"></span>');

        $primary.append($('<span class="bw-feed-id"></span>').text('ID ' + it.id));
        $primary.append($('<span class="bw-feed-title"></span>').text(it.title || ('#' + it.id)));
        $meta.append($('<span class="bw-feed-status"></span>').addClass('is-' + tone).text(resultLabel));
        if (detail) {
          $meta.append($('<span class="bw-feed-detail"></span>').text(detail));
        }

        $actions.append(
          $('<button type="button" class="bw-button bw-view-report"></button>')
            .attr('data-post', it.id)
            .attr('title', viewLabel)
            .attr('aria-label', viewLabel)
            .append('<span class="dashicons dashicons-visibility"></span>')
        );

        $main.append($primary).append($meta);
        $li.append($main).append($actions);
        $feed.prepend($li);
        setTimeout((function ($el) { return function () { $el.removeClass('is-new'); }; })($li), 1200);
      }
      // Cap DOM at 50 items.
      $feed.children().slice(50).remove();
    }

    function tick() {
      if (isStopped || !isCurrentJobPoll(job, pollToken)) { return; }
      ajax('bw_seo_job_status', { job: job }, { timeout: requestTimeout, cache: false }).done(function (res) {
        if (!isCurrentJobPoll(job, pollToken)) { return; }
        if (!res || !res.success || !res.data) {
          if ($msg.length) {
            $msg.text((BotwriterSEO.i18n.retrying || 'Connection hiccup. Retrying...'));
          }
          retryDelay = Math.min(maxRetryDelay, retryDelay * 2);
          scheduleNext(retryDelay);
          return;
        }
        retryDelay = 1500;
        var d = res.data;
        var pct = d.total > 0 ? Math.round((d.done / d.total) * 100) : 0;
        setLiveProgressTitle(d.action_label || '');
        $progress.css('width', pct + '%');
        $stats.text(d.done + ' / ' + d.total + ' (' + pct + '%)');
        if ($msg.length) { $msg.text(d.message || ''); }
        $label.text(d.status ? (d.status.charAt(0).toUpperCase() + d.status.slice(1)) : '');
        appendRecent(d.recent || []);
        var $startBtn = $container.closest('.bw-card').find('.bw-job-start, .bw-job-configure');
        // Also re-enable configure buttons elsewhere (e.g. Bulk Actions cards).
        var $allBtns = $('.bw-job-configure[data-job="' + job + '"], .bw-job-start[data-job="' + job + '"]');
        var $cancelBtns = $('.bw-job-cancel[data-job="' + job + '"]');
        if (d.status === 'running' || d.status === 'pending') {
          $allBtns.prop('disabled', true);
          $cancelBtns.prop('disabled', false).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancel_job || 'Cancel current run'));
          scheduleNext(1500);
        } else if (d.status === 'cancelled') {
          isStopped = true;
          $label.text(BotwriterSEO.i18n.cancelled || 'Cancelled');
          $allBtns.prop('disabled', false);
          $cancelBtns.prop('disabled', true).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancelled || 'Cancelled'));
        } else if (d.status === 'done') {
          isStopped = true;
          $label.text(BotwriterSEO.i18n.completed || 'Completed');
          $allBtns.prop('disabled', false);
          $cancelBtns.prop('disabled', true).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancel_job || 'Cancel current run'));
          $startBtn.html('<span class="dashicons dashicons-controls-play"></span> ' + (BotwriterSEO.i18n.run_again || 'Run again'));
          // Refresh the analyses table once finished.
          if (typeof window.bwSeoReloadAnalyses === 'function') { window.bwSeoReloadAnalyses(); }
          if (typeof window.bwSeoReloadUndoHistory === 'function') { window.bwSeoReloadUndoHistory(); }
        } else {
          isStopped = true;
          $label.text(BotwriterSEO.i18n.error || 'Error');
          $allBtns.prop('disabled', false);
          $cancelBtns.prop('disabled', true).html('<span class="dashicons dashicons-no-alt"></span> ' + (BotwriterSEO.i18n.cancel_job || 'Cancel current run'));
        }
      }).fail(function () {
        if (isStopped || !isCurrentJobPoll(job, pollToken)) { return; }
        retryDelay = Math.min(maxRetryDelay, retryDelay * 2);
        if ($msg.length) {
          $msg.text((BotwriterSEO.i18n.retrying || 'Connection hiccup. Retrying...'));
        }
        scheduleNext(retryDelay);
      });
    }
    tick();
  }

  function ensureSharedModal() {
    var $modal = $('#bw-seo-modal');
    if (!$modal.length) {
      $modal = $('<div class="bw-modal-overlay" id="bw-seo-modal" hidden><div class="bw-modal" role="dialog" aria-modal="true"><button type="button" class="bw-modal-close" aria-label="Close"><span class="dashicons dashicons-no-alt"></span></button><div class="bw-modal-body"></div></div></div>');
      $('body').append($modal);
    }
    return $modal;
  }

  function openSharedModal(html) {
    var $modal = ensureSharedModal();
    $modal.find('.bw-modal-body').html(html || '');
    $modal.prop('hidden', false).addClass('is-open');
    $('body').addClass('bw-modal-open');
    return $modal;
  }

  // Modal: open per-post analysis report.
  function openReportModal(postId) {
    var $modal = openSharedModal('<div class="bw-empty"><span class="bw-spinner"></span><p>' + (BotwriterSEO.i18n.loading || 'Loading…') + '</p></div>');
    var $body = $modal.find('.bw-modal-body');
    ajax('bw_seo_post_report', { post_id: postId }).done(function (res) {
      if (res.success && res.data && res.data.html) {
        $body.html(res.data.html);
      } else {
        $body.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' + ((res.data && res.data.message) || 'Error') + '</div>');
      }
    }).fail(function () {
      $body.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> Network error</div>');
    });
  }

  function openUndoRunModal(runId) {
    var $modal = openSharedModal('<div class="bw-empty"><span class="bw-spinner"></span><p>' + (BotwriterSEO.i18n.loading || 'Loading…') + '</p></div>');
    var $body = $modal.find('.bw-modal-body');
    ajax('bw_seo_bulk_undo_detail', { run_id: runId }).done(function (res) {
      if (res && res.success && res.data && res.data.html) {
        $body.html(res.data.html);
      } else {
        $body.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' + (((res && res.data && res.data.message) || BotwriterSEO.i18n.undo_detail_error || 'Could not load undo details.')) + '</div>');
      }
    }).fail(function () {
      $body.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' + (BotwriterSEO.i18n.undo_network_error || 'Network error while loading undo data.') + '</div>');
    });
  }

  function closeReportModal() {
    $('#bw-seo-modal').prop('hidden', true).removeClass('is-open');
    $('body').removeClass('bw-modal-open');
  }

  $(document).on('click', '.bw-view-report', function (e) {
    e.preventDefault();
    var id = parseInt($(this).data('post'), 10);
    if (id > 0) { openReportModal(id); }
  });
  // Close on overlay click (but not when clicking inside the modal).
  $(document).on('click', '#bw-seo-modal', function (e) {
    if (e.target === this) { closeReportModal(); }
  });
  // Close button (delegate so dashicons child clicks also work).
  $(document).on('click', '.bw-modal-close', function (e) {
    e.preventDefault();
    e.stopPropagation();
    closeReportModal();
  });
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') { closeReportModal(); }
  });

  // Inner-tab switching inside the report modal.
  $(document).on('click', '.bw-report-tabs a', function (e) {
    e.preventDefault();
    var $a = $(this);
    var target = $a.data('tab');
    $a.closest('.bw-report-tabs').find('a').removeClass('is-active');
    $a.addClass('is-active');
    var $report = $a.closest('.bw-report');
    $report.find('.bw-tab').prop('hidden', true).removeClass('is-active');
    $report.find('#' + target).prop('hidden', false).addClass('is-active');
  });

  // Auto-resume polling for any job already in progress on page load.
  $(function () {
    $('.bw-job').each(function () {
      var $c = $(this);
      var job = $c.data('job');
      if (!job) { return; }
      var label = ($c.find('.bw-job-label').text() || '').toLowerCase();
      if (label === 'running' || label === 'pending') {
        $c.closest('.bw-card').find('.bw-job-start').prop('disabled', true);
        pollJob(job, $c, nextJobPollToken(job));
      }
    });
  });

  // Trigger AI suggestion in editor sidebar
  $(document).on('click', '.bw-ai-suggest', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var field = $btn.data('field');
    var postId = $btn.data('post');
    var $target = $('#' + $btn.data('target'));
    $btn.prop('disabled', true).html('<span class="bw-spinner"></span>');
    ajax('bw_seo_ai_suggest', { field: field, post_id: postId }).done(function (res) {
      $btn.prop('disabled', false).text(BotwriterSEO.i18n.suggest || 'Suggest');
      if (res.success && res.data && res.data.value) {
        $target.val(res.data.value).trigger('input');
      } else {
        alert((res.data && res.data.message) || 'Error');
      }
    });
  });

  // Inline run-once helpers (audit refresh, watchdog tick, etc.)
  $(document).on('click', '.bw-run-action', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var action = $btn.data('action');
    $btn.prop('disabled', true).html('<span class="bw-spinner"></span>');
    ajax(action, $btn.data('args') || {}).done(function (res) {
      $btn.prop('disabled', false).text($btn.data('label') || 'Done');
      if (res.success && res.data && res.data.message) {
        $btn.closest('.bw-card').find('.bw-action-result').html('<div class="bw-notice success">' + res.data.message + '</div>');
        if (res.data.reload) { setTimeout(function () { location.reload(); }, 800); }
      } else if (!res.success) {
        $btn.closest('.bw-card').find('.bw-action-result').html('<div class="bw-notice danger">' + ((res.data && res.data.message) || 'Error') + '</div>');
      }
    });
  });

  // ===== Analyses table (paginated + sortable) =====
  var analysesState = { page: 1, per_page: 25, orderby: 'score', order: 'asc', q: '', grade: '' };
  var analysesDebounce;

  function escHtml(s) { return $('<div/>').text(s == null ? '' : s).html(); }

  function renderAnalyses(data) {
    var $wrap = $('#bw-analyses');
    if (!$wrap.length) return;
    var $tbody = $wrap.find('.bw-analyses-table tbody');
    if (!data.items || !data.items.length) {
      $tbody.html('<tr><td colspan="5"><div class="bw-empty"><span class="dashicons dashicons-search"></span><p>' + (BotwriterSEO.i18n.no_results || 'No results') + '</p></div></td></tr>');
    } else {
      var html = '';
      data.items.forEach(function (it) {
        html += '<tr>' +
          '<td><a href="' + escHtml(it.edit_url) + '" target="_blank">' + escHtml(it.title || ('#' + it.id)) + '</a></td>' +
          '<td><span class="bw-seo-badge bw-seo-' + escHtml(it.grade) + '">' + it.score + '</span></td>' +
          '<td>' + (it.readability > 0 ? ('<span class="bw-seo-badge bw-seo-' + escHtml(it.r_grade) + '">' + it.readability + '</span>') : '<span class="bw-seo-badge bw-seo-na">–</span>') + '</td>' +
          '<td>' + escHtml(it.date) + '</td>' +
          '<td class="actions">' +
            '<button type="button" class="bw-button primary bw-view-report" data-post="' + it.id + '"><span class="dashicons dashicons-visibility"></span> ' + (BotwriterSEO.i18n.view_analysis || 'View Analysis') + '</button>' +
          '</td>' +
        '</tr>';
      });
      $tbody.html(html);
    }
    // Sort indicators
    $wrap.find('th.sortable').each(function () {
      var $th = $(this);
      var key = $th.data('orderby');
      $th.toggleClass('is-active', key === analysesState.orderby);
      $th.attr('data-direction', key === analysesState.orderby ? analysesState.order : '');
    });
    // Pager
    var $pager = $wrap.find('.bw-analyses-pager').empty();
    var totalPages = data.pages || 1;
    var p = data.page || 1;
    var info = (BotwriterSEO.i18n.showing || 'Showing') + ' ' + ((p - 1) * data.per + 1) + '–' +
      Math.min(p * data.per, data.total) + ' ' + (BotwriterSEO.i18n.of || 'of') + ' ' + data.total;
    $wrap.find('.bw-analyses-info').text(info);
    function btn(label, page, opts) {
      opts = opts || {};
      var cls = 'bw-page' + (opts.active ? ' is-active' : '') + (opts.disabled ? ' is-disabled' : '');
      return '<button type="button" class="' + cls + '" data-page="' + page + '"' + (opts.disabled ? ' disabled' : '') + '>' + label + '</button>';
    }
    $pager.append(btn('«', Math.max(1, p - 1), { disabled: p <= 1 }));
    var start = Math.max(1, p - 2), end = Math.min(totalPages, p + 2);
    if (start > 1) { $pager.append(btn('1', 1)); if (start > 2) { $pager.append('<span class="bw-page-ellipsis">…</span>'); } }
    for (var i = start; i <= end; i++) { $pager.append(btn(i, i, { active: i === p })); }
    if (end < totalPages) { if (end < totalPages - 1) { $pager.append('<span class="bw-page-ellipsis">…</span>'); } $pager.append(btn(totalPages, totalPages)); }
    $pager.append(btn('»', Math.min(totalPages, p + 1), { disabled: p >= totalPages }));
  }

  function loadAnalyses() {
    var $wrap = $('#bw-analyses');
    if (!$wrap.length) return;
    $wrap.find('.bw-analyses-table tbody').html('<tr><td colspan="5"><div class="bw-empty"><span class="bw-spinner"></span></div></td></tr>');
    ajax('bw_seo_analyses_list', analysesState).done(function (res) {
      if (res.success) { renderAnalyses(res.data); }
    });
  }

  // Expose so pollJob can refresh after a job finishes.
  window.bwSeoReloadAnalyses = loadAnalyses;

  $(function () {
    if ($('#bw-analyses').length) { loadAnalyses(); }
  });

  $(document).on('click', '#bw-analyses th.sortable', function () {
    var key = $(this).data('orderby');
    if (analysesState.orderby === key) {
      analysesState.order = (analysesState.order === 'asc') ? 'desc' : 'asc';
    } else {
      analysesState.orderby = key;
      analysesState.order = (key === 'score' || key === 'readability') ? 'asc' : 'desc';
    }
    analysesState.page = 1;
    loadAnalyses();
  });

  $(document).on('click', '#bw-analyses .bw-page', function () {
    var p = parseInt($(this).data('page'), 10);
    if (p > 0 && p !== analysesState.page) { analysesState.page = p; loadAnalyses(); }
  });

  $(document).on('input', '#bw-analyses .bw-analyses-q', function () {
    var v = $(this).val();
    clearTimeout(analysesDebounce);
    analysesDebounce = setTimeout(function () {
      analysesState.q = v;
      analysesState.page = 1;
      loadAnalyses();
    }, 300);
  });

  $(document).on('change', '#bw-analyses .bw-analyses-grade', function () {
    analysesState.grade = $(this).val();
    analysesState.page = 1;
    loadAnalyses();
  });

  $(document).on('change', '#bw-analyses .bw-analyses-perpage', function () {
    analysesState.per_page = parseInt($(this).val(), 10) || 25;
    analysesState.page = 1;
    loadAnalyses();
  });

  function buildUndoFeedbackHtml(kind, message, errors) {
    var icon = (kind === 'danger' || kind === 'warn') ? 'warning' : 'yes-alt';
    var html = '<div class="bw-notice ' + escapeAttr(kind || 'info') + '"><span class="dashicons dashicons-' + icon + '"></span><div>' + escapeHtml(message || '') + '</div></div>';
    if (errors && errors.length) {
      html += '<div class="bw-undo-error-list">' + errors.map(function (item) {
        return '<div class="bw-undo-error-item">' + escapeHtml(item) + '</div>';
      }).join('') + '</div>';
    }
    return html;
  }

  function showUndoFeedback(kind, message, errors) {
    var $box = $('#bw-undo-feedback');
    if (!$box.length) { return; }
    $box.html(buildUndoFeedbackHtml(kind, message, errors));
  }

  function showUndoDetailFeedback(kind, message, errors) {
    var $box = $('#bw-seo-modal .bw-undo-detail-feedback').first();
    if (!$box.length) { return; }
    $box.html(buildUndoFeedbackHtml(kind, message, errors));
  }

  function renderUndoHistoryFromResponse(data) {
    var $root = $('#bw-bulk-undo-root');
    if (!$root.length || !data) { return; }
    if (data.has_history) {
      $root.html(typeof data.html === 'string' ? data.html : '').prop('hidden', false);
    } else {
      $root.empty().prop('hidden', true);
    }
  }

  function renderUndoDetailFromResponse(data, runId) {
    var $body = $('#bw-seo-modal .bw-modal-body');
    if (!$body.length) { return; }
    if (data && typeof data.detail_html === 'string') {
      $body.html(data.detail_html);
      return;
    }
    if (runId > 0) {
      openUndoRunModal(runId);
    }
  }

  function handleUndoActionSuccess(data, fallbackRunId) {
    renderUndoHistoryFromResponse(data || {});
    renderUndoDetailFromResponse(data || {}, fallbackRunId || 0);
    showUndoFeedback((data && data.errors && data.errors.length) ? 'warn' : 'success', (data && data.message) || 'OK', (data && data.errors) || []);
  }

  function loadUndoHistory() {
    var $root = $('#bw-bulk-undo-root');
    if (!$root.length) { return; }
    if (!$root.prop('hidden')) {
      $root.html('<div class="bw-empty"><span class="bw-spinner"></span><p>' + (BotwriterSEO.i18n.loading || 'Loading…') + '</p></div>');
    }
    ajax('bw_seo_bulk_undo_history').done(function (res) {
      if (res && res.success && res.data && typeof res.data.html === 'string') {
        renderUndoHistoryFromResponse(res.data);
      } else {
        $root.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' + (((res && res.data && res.data.message) || BotwriterSEO.i18n.undo_history_error || 'Could not load undo history.')) + '</div>').prop('hidden', false);
      }
    }).fail(function () {
      $root.html('<div class="bw-notice danger"><span class="dashicons dashicons-warning"></span> ' + (BotwriterSEO.i18n.undo_network_error || 'Network error while loading undo data.') + '</div>').prop('hidden', false);
    });
  }

  window.bwSeoReloadUndoHistory = loadUndoHistory;

  $(document).on('click', '.bw-undo-run-details', function (e) {
    e.preventDefault();
    var runId = parseInt($(this).data('run-id'), 10);
    if (runId > 0) { openUndoRunModal(runId); }
  });

  $(document).on('click', '.bw-undo-run-revert', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var originalHtml = $btn.html();
    if ($btn.prop('disabled')) { return; }
    var runId = parseInt($btn.data('run-id'), 10);
    if (runId <= 0) { return; }
    if (!window.confirm(BotwriterSEO.i18n.undo_restore_confirm || 'Restore all snapshots from this run? This will overwrite newer edits made to the same fields.')) {
      return;
    }

    $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + (BotwriterSEO.i18n.undo_restoring || 'Restoring…'));
    ajax('bw_seo_bulk_undo_revert', { run_id: runId }).done(function (res) {
      if (res && res.success && res.data) {
        handleUndoActionSuccess(res.data, runId);
        return;
      }

      showUndoFeedback('danger', ((res && res.data && res.data.message) || (BotwriterSEO.i18n.undo_history_error || 'Could not load undo history.')));
      $btn.prop('disabled', false).html(originalHtml);
    }).fail(function () {
      showUndoFeedback('danger', BotwriterSEO.i18n.undo_network_error || 'Network error while loading undo data.');
      $btn.prop('disabled', false).html(originalHtml);
    });
  });

  $(document).on('click', '.bw-undo-detail-revert-run', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var originalHtml = $btn.html();
    var runId = parseInt($btn.data('run-id'), 10);
    if ($btn.prop('disabled') || runId <= 0) { return; }
    if (!window.confirm(BotwriterSEO.i18n.undo_restore_confirm || 'Restore all snapshots from this run? This will overwrite newer edits made to the same fields.')) {
      return;
    }

    $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + (BotwriterSEO.i18n.undo_restoring || 'Restoring…'));
    ajax('bw_seo_bulk_undo_revert', { run_id: runId }).done(function (res) {
      if (res && res.success && res.data) {
        handleUndoActionSuccess(res.data, runId);
        return;
      }

      showUndoDetailFeedback('danger', ((res && res.data && res.data.message) || (BotwriterSEO.i18n.undo_detail_error || 'Could not load undo details.')));
      $btn.prop('disabled', false).html(originalHtml);
    }).fail(function () {
      showUndoDetailFeedback('danger', BotwriterSEO.i18n.undo_network_error || 'Network error while loading undo data.');
      $btn.prop('disabled', false).html(originalHtml);
    });
  });

  $(document).on('click', '.bw-undo-item-revert', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var originalHtml = $btn.html();
    var itemId = parseInt($btn.data('item-id'), 10);
    var runId = parseInt($btn.data('run-id'), 10);
    if ($btn.prop('disabled') || itemId <= 0) { return; }
    if (!window.confirm(BotwriterSEO.i18n.undo_restore_item_confirm || 'Restore the stored snapshot for this post? This will overwrite newer edits made to the same fields.')) {
      return;
    }

    $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + (BotwriterSEO.i18n.undo_item_restoring || 'Restoring post…'));
    ajax('bw_seo_bulk_undo_revert_item', { item_id: itemId }).done(function (res) {
      if (res && res.success && res.data) {
        handleUndoActionSuccess(res.data, runId);
        return;
      }

      showUndoDetailFeedback('danger', ((res && res.data && res.data.message) || (BotwriterSEO.i18n.undo_detail_error || 'Could not load undo details.')));
      $btn.prop('disabled', false).html(originalHtml);
    }).fail(function () {
      showUndoDetailFeedback('danger', BotwriterSEO.i18n.undo_network_error || 'Network error while loading undo data.');
      $btn.prop('disabled', false).html(originalHtml);
    });
  });

  // ---------------------------------------------------------------------------
  // Scope modal — pick post types, taxonomies, filters before running a job.
  // ---------------------------------------------------------------------------
  var scopeState = {
    opts: null,
    $modal: null,
    countXhr: null,
    countTimer: null,
    termsCache: {} // key = sorted post_types csv → response
  };

  function i18n(key, fallback) {
    return (BotwriterSEO.i18n && BotwriterSEO.i18n[key]) || fallback || key;
  }

  function setScopeCountState($m, value, meta) {
    if (!$m || !$m.length) { return; }
    $m.find('.bw-scope-count-text').text(i18n('matching', 'Matching items'));
    $m.find('.bw-scope-count-value').text(value == null ? '—' : String(value));
    $m.find('.bw-scope-count-meta').text(meta || '');
  }

  function openScopeModal(opts) {
    closeScopeModal();
    scopeState.opts = opts;
    var pts = (BotwriterSEO.post_types && BotwriterSEO.post_types.length) ? BotwriterSEO.post_types : [
      { name: 'post', label: 'Posts', count: 0, icon: 'admin-post' }
    ];
    var preselected = Array.isArray(opts.args.post_types) && opts.args.post_types.length ? opts.args.post_types : pts.map(function (p) { return p.name; });

    var title = (opts.mode === 'action')
      ? i18n('scope_action_title', 'Configure action') + (opts.actionLabel ? ' — ' + opts.actionLabel : '')
      : i18n('scope_title', 'Configure analysis');

    var html = '' +
      '<div class="bw-modal-overlay bw-scope-overlay" id="bw-scope-modal" role="dialog" aria-modal="true">' +
        '<div class="bw-modal bw-scope-modal">' +
          '<div class="bw-modal-header">' +
            '<h2>' + escapeHtml(title) + '</h2>' +
            '<button type="button" class="bw-modal-close" aria-label="Close">&times;</button>' +
          '</div>' +
          '<div class="bw-modal-body">' +
            '<div class="bw-scope-section">' +
              '<h3>' + escapeHtml(i18n('post_types', 'Content types')) + '</h3>' +
              '<div class="bw-scope-types">' + pts.map(function (p) {
                var checked = preselected.indexOf(p.name) !== -1 ? ' checked' : '';
                return '<label class="bw-scope-type">' +
                  '<input type="checkbox" class="bw-scope-pt" value="' + escapeAttr(p.name) + '"' + checked + '> ' +
                  '<span class="dashicons dashicons-' + escapeAttr(p.icon || 'admin-post') + '"></span> ' +
                  '<span class="bw-scope-type-label">' + escapeHtml(p.label) + '</span> ' +
                  '<span class="bw-scope-type-count">(' + (parseInt(p.count, 10) || 0) + ')</span>' +
                '</label>';
              }).join('') + '</div>' +
            '</div>' +
            '<div class="bw-scope-section">' +
              '<h3>' + escapeHtml(i18n('categories', 'Categories & taxonomies')) + '</h3>' +
              '<div class="bw-scope-tax-wrap"><em class="bw-scope-tax-empty">' + escapeHtml(i18n('loading', 'Loading…')) + '</em></div>' +
            '</div>' +
            buildFiltersBlock(opts) +
            '<div class="bw-scope-section bw-scope-changed">' +
              '<label><input type="checkbox" class="bw-scope-only-changed"' + (opts.args.only_changed ? ' checked' : '') + '> ' +
                escapeHtml(opts.mode === 'action' ? i18n('only_changed_action', 'Only items modified since last action') : i18n('only_changed', 'Only items modified since last analysis')) +
              '</label>' +
            '</div>' +
            '<div class="bw-scope-count" aria-live="polite">' +
              '<span class="bw-scope-count-icon dashicons dashicons-search"></span> ' +
              '<span class="bw-scope-count-copy">' +
                '<span class="bw-scope-count-text">' + escapeHtml(i18n('matching', 'Matching items')) + '</span>' +
                '<span class="bw-scope-count-meta">' + escapeHtml(i18n('estimating', 'Estimating…')) + '</span>' +
              '</span>' +
              '<strong class="bw-scope-count-value">—</strong>' +
            '</div>' +
          '</div>' +
          '<div class="bw-modal-footer">' +
            '<button type="button" class="button bw-scope-cancel">' + escapeHtml(i18n('cancel', 'Cancel')) + '</button>' +
            '<button type="button" class="button button-primary bw-scope-run" disabled>' +
              '<span class="dashicons dashicons-controls-play"></span> ' + escapeHtml(i18n('run', 'Run')) +
            '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    var $m = $(html).appendTo('body');
    scopeState.$modal = $m;
    loadTermsForSelectedTypes();
    refreshCount();
  }

  function buildFiltersBlock(opts) {
    var feats = opts.features || [];
    if (!feats.length) { return ''; }
    var rows = '';
    if (feats.indexOf('missing_meta') !== -1) {
      var def = parseInt(opts.args.missing_meta_max, 10);
      if (isNaN(def)) { def = 30; }
      rows += '<div class="bw-scope-filter-row" title="' + escapeAttr(i18n('hint_missing_meta', 'Items whose meta description is empty or shorter than this will be processed.')) + '">' +
        '<label>' + escapeHtml(i18n('missing_meta', 'Missing or short meta description (max chars)')) + '</label> ' +
        '<input type="number" min="0" max="320" class="bw-scope-missing-meta" value="' + def + '">' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_missing_meta', 'Items whose meta description is empty or shorter than this will be processed.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('missing_seo_title') !== -1) {
      var checkedT = (opts.args.missing_seo_title || opts.args.missing_seo_title === undefined) ? ' checked' : '';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-missing-seo-title"' + checkedT + '> ' +
          escapeHtml(i18n('missing_seo_title', 'Only items without a custom SEO title')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_missing_seo_title', 'Skip posts that already have a Yoast / Rank Math / AIOSEO / native SEO title set.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('missing_faq') !== -1) {
      var checkedF = (opts.args.missing_faq || opts.args.missing_faq === undefined) ? ' checked' : '';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-missing-faq"' + checkedF + '> ' +
          escapeHtml(i18n('missing_faq', 'Only items without an FAQ block yet')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_missing_faq', 'Avoids regenerating FAQs that already exist (saves AI quota).')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('intro_paragraphs') !== -1) {
      var introParas = parseInt(opts.args.intro_paragraphs, 10);
      if (isNaN(introParas)) { introParas = 2; }
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('intro_paragraphs', 'Rewrite first paragraphs')) + '</label> ' +
        '<input type="number" min="1" max="3" class="bw-scope-intro-paragraphs" value="' + introParas + '">' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_intro_paragraphs', 'How many opening paragraphs should be regenerated. Recommended: 2.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('auto_keyword') !== -1) {
      var checkedAutoKeyword = (opts.args.auto_keyword || opts.args.auto_keyword === undefined) ? ' checked' : '';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-auto-keyword"' + checkedAutoKeyword + '> ' +
          escapeHtml(i18n('auto_keyword', 'Suggest and save a primary keyword if missing')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_auto_keyword', 'Useful when the post has no focus keyword yet and you still want the intro to place it early.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('target_words') !== -1) {
      var targetWords = parseInt(opts.args.target_words, 10);
      if (isNaN(targetWords)) { targetWords = 450; }
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('target_words', 'Target minimum word count')) + '</label> ' +
        '<input type="number" min="300" max="2500" class="bw-scope-target-words" value="' + targetWords + '">' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_target_words', 'If the post is already above this threshold, the action skips it.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('demote_h1') !== -1) {
      var checkedDemote = (opts.args.demote_h1 || opts.args.demote_h1 === undefined) ? ' checked' : '';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-demote-h1"' + checkedDemote + '> ' +
          escapeHtml(i18n('demote_h1', 'Convert in-content H1 to H2')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_demote_h1', 'Keeps the page with a single H1 while preserving the section title text.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('add_subheadings') !== -1) {
      var checkedSubheadings = (opts.args.add_subheadings || opts.args.add_subheadings === undefined) ? ' checked' : '';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-add-subheadings"' + checkedSubheadings + '> ' +
          escapeHtml(i18n('add_subheadings', 'Create an H3 level when the post only has H2s')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_add_subheadings', 'Applies a light hierarchy fix without rewriting the whole article.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('faq_visibility') !== -1) {
      var checkedVis = (opts.args.faq_visible === 0 || opts.args.faq_visible === '0') ? '' : ' checked';
      rows += '<div class="bw-scope-filter-row">' +
        '<label><input type="checkbox" class="bw-scope-faq-visible"' + checkedVis + '> ' +
          escapeHtml(i18n('faq_visible', 'Show FAQ block to readers inside post content')) + '</label>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_faq_visible', 'When disabled, only FAQ schema is kept for SEO rich results.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('link_role') !== -1) {
      var role = (opts.args.link_role || 'any');
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('link_role', 'Target by linking role')) + '</label> ' +
        '<select class="bw-scope-link-role">' +
          '<option value="any"' + (role === 'any' ? ' selected' : '') + '>' + escapeHtml(i18n('link_role_any', 'Any post')) + '</option>' +
          '<option value="orphan"' + (role === 'orphan' ? ' selected' : '') + '>' + escapeHtml(i18n('link_role_orphan', 'Orphans (no incoming links)')) + '</option>' +
          '<option value="deadend"' + (role === 'deadend' ? ' selected' : '') + '>' + escapeHtml(i18n('link_role_deadend', 'Dead-ends (no outgoing links)')) + '</option>' +
        '</select>' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_link_role', 'Use orphan/dead-end to focus on the posts that benefit most.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('slug_length_gt') !== -1) {
      var slen = parseInt(opts.args.slug_length_gt, 10);
      if (isNaN(slen)) { slen = 60; }
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('slug_length_gt', 'Slug longer than (chars)')) + '</label> ' +
        '<input type="number" min="0" max="200" class="bw-scope-slug-length" value="' + slen + '">' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_slug_length', 'Only rewrite URLs that are too long. Recommended ≥60.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('external_links_count') !== -1) {
      var externalLinksCount = parseInt(opts.args.external_links_count, 10);
      if (isNaN(externalLinksCount)) { externalLinksCount = 2; }
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('external_links_count', 'External references to add')) + '</label> ' +
        '<input type="number" min="1" max="5" class="bw-scope-external-links-count" value="' + externalLinksCount + '">' +
        '<span class="bw-scope-filter-hint">' + escapeHtml(i18n('hint_external_links_count', 'Number of outbound sources to append in the references block. Requires SERP API access.')) + '</span>' +
      '</div>';
    }
    if (feats.indexOf('score') !== -1) {
      rows += '<div class="bw-scope-filter-row">' +
        '<label>' + escapeHtml(i18n('score_lt', 'Score below')) + '</label> ' +
        '<input type="number" min="0" max="100" class="bw-scope-score-lt" value="' + (opts.args.score_lt != null ? parseInt(opts.args.score_lt, 10) : '') + '" placeholder="—">' +
        '<label class="bw-scope-filter-sep">' + escapeHtml(i18n('score_gt', 'Score above')) + '</label> ' +
        '<input type="number" min="0" max="100" class="bw-scope-score-gt" value="' + (opts.args.score_gt != null ? parseInt(opts.args.score_gt, 10) : '') + '" placeholder="—">' +
      '</div>';
    }
    if (feats.indexOf('confirm_destructive') !== -1) {
      rows += '<div class="bw-scope-filter-row bw-scope-confirm-destructive">' +
        '<label><input type="checkbox" class="bw-scope-confirm-destructive"> ' +
          '<strong>' + escapeHtml(i18n('confirm_destructive', 'I understand this changes URLs and creates 301 redirects')) + '</strong></label>' +
      '</div>';
    }
    if (!rows) { return ''; }
    return '<div class="bw-scope-section bw-scope-filters">' +
      '<h3>' + escapeHtml(i18n('filters', 'Filters')) + '</h3>' +
      rows +
    '</div>';
  }

  function getSelectedPostTypes() {
    if (!scopeState.$modal) { return []; }
    return scopeState.$modal.find('.bw-scope-pt:checked').map(function () { return $(this).val(); }).get();
  }

  function getSelectedTermIds() {
    if (!scopeState.$modal) { return []; }
    return scopeState.$modal.find('.bw-scope-term:checked').map(function () { return parseInt($(this).val(), 10); }).get();
  }

  function buildArgsFromModal() {
    var $m = scopeState.$modal;
    if (!$m) { return {}; }
    var base = $.extend({}, scopeState.opts.args || {});
    base.post_types = getSelectedPostTypes();
    base.term_ids = getSelectedTermIds();
    base.only_changed = $m.find('.bw-scope-only-changed').is(':checked') ? 1 : 0;
    var $mm = $m.find('.bw-scope-missing-meta');
    if ($mm.length) { var v = parseInt($mm.val(), 10); if (!isNaN(v)) { base.missing_meta_max = v; } }
    var $slt = $m.find('.bw-scope-score-lt');
    if ($slt.length && $slt.val() !== '') { base.score_lt = parseInt($slt.val(), 10); }
    var $sgt = $m.find('.bw-scope-score-gt');
    if ($sgt.length && $sgt.val() !== '') { base.score_gt = parseInt($sgt.val(), 10); }
    var $mst = $m.find('.bw-scope-missing-seo-title');
    if ($mst.length) { base.missing_seo_title = $mst.is(':checked') ? 1 : 0; }
    var $mfq = $m.find('.bw-scope-missing-faq');
    if ($mfq.length) { base.missing_faq = $mfq.is(':checked') ? 1 : 0; }
    var $ip = $m.find('.bw-scope-intro-paragraphs');
    if ($ip.length) { var ipv = parseInt($ip.val(), 10); if (!isNaN(ipv)) { base.intro_paragraphs = ipv; } }
    var $ak = $m.find('.bw-scope-auto-keyword');
    if ($ak.length) { base.auto_keyword = $ak.is(':checked') ? 1 : 0; }
    var $tw = $m.find('.bw-scope-target-words');
    if ($tw.length) { var twv = parseInt($tw.val(), 10); if (!isNaN(twv)) { base.target_words = twv; } }
    var $dh = $m.find('.bw-scope-demote-h1');
    if ($dh.length) { base.demote_h1 = $dh.is(':checked') ? 1 : 0; }
    var $as = $m.find('.bw-scope-add-subheadings');
    if ($as.length) { base.add_subheadings = $as.is(':checked') ? 1 : 0; }
    var $fvis = $m.find('.bw-scope-faq-visible');
    if ($fvis.length) { base.faq_visible = $fvis.is(':checked') ? 1 : 0; }
    var $lr = $m.find('.bw-scope-link-role');
    if ($lr.length) { base.link_role = $lr.val(); }
    var $sl = $m.find('.bw-scope-slug-length');
    if ($sl.length) { var sv = parseInt($sl.val(), 10); if (!isNaN(sv)) { base.slug_length_gt = sv; } }
    var $elc = $m.find('.bw-scope-external-links-count');
    if ($elc.length) { var elv = parseInt($elc.val(), 10); if (!isNaN(elv)) { base.external_links_count = elv; } }
    var $cd = $m.find('.bw-scope-confirm-destructive');
    if ($cd.length) { base.confirm_destructive = $cd.is(':checked') ? 1 : 0; }
    return base;
  }

  function loadTermsForSelectedTypes() {
    var $m = scopeState.$modal;
    if (!$m) { return; }
    var pts = getSelectedPostTypes();
    var $wrap = $m.find('.bw-scope-tax-wrap');
    if (!pts.length) {
      $wrap.html('<em>' + escapeHtml(i18n('no_results', 'No content types selected')) + '</em>');
      return;
    }
    var key = pts.slice().sort().join(',');
    if (scopeState.termsCache[key]) {
      renderTaxonomies(scopeState.termsCache[key]);
      return;
    }
    $wrap.html('<em>' + escapeHtml(i18n('loading', 'Loading…')) + '</em>');
    ajax('bw_seo_terms', { post_types: pts.join(',') }).done(function (res) {
      if (!res || !res.success) { $wrap.html('<em>' + escapeHtml(i18n('no_results', 'No taxonomies')) + '</em>'); return; }
      scopeState.termsCache[key] = res.data || {};
      renderTaxonomies(res.data || {});
    });
  }

  function renderTaxonomies(data) {
    var $wrap = scopeState.$modal.find('.bw-scope-tax-wrap');
    var taxes = (data && data.taxonomies) || {};
    var keys = Object.keys(taxes);
    if (!keys.length) {
      $wrap.html('<em>' + escapeHtml(i18n('no_results', 'No taxonomies available')) + '</em>');
      return;
    }
    var preselected = Array.isArray(scopeState.opts.args.term_ids) ? scopeState.opts.args.term_ids.map(String) : [];
    var html = keys.map(function (tk) {
      var t = taxes[tk];
      var terms = t.terms || [];
      if (!terms.length) { return ''; }
      var items = terms.map(function (term) {
        var checked = preselected.indexOf(String(term.id)) !== -1 ? ' checked' : '';
        return '<label class="bw-scope-term-row"><input type="checkbox" class="bw-scope-term" value="' + escapeAttr(term.id) + '"' + checked + '> ' +
          escapeHtml(term.name) + ' <span class="bw-scope-term-count">(' + (parseInt(term.count, 10) || 0) + ')</span></label>';
      }).join('');
      return '<details class="bw-scope-tax" open><summary>' + escapeHtml(t.label || tk) + '</summary>' +
        '<div class="bw-scope-term-list">' + items + '</div>' +
      '</details>';
    }).join('');
    $wrap.html(html || '<em>' + escapeHtml(i18n('no_results', 'No terms available')) + '</em>');
  }

  function refreshCount() {
    var $m = scopeState.$modal;
    if (!$m) { return; }
    var $run = $m.find('.bw-scope-run');
    var args = buildArgsFromModal();
    if (!args.post_types || !args.post_types.length) {
      setScopeCountState($m, 0, i18n('matching_select_type', 'Select at least one content type to estimate the run.'));
      $run.prop('disabled', true);
      return;
    }
    setScopeCountState($m, '—', i18n('estimating', 'Estimating…'));
    if (scopeState.countXhr && scopeState.countXhr.abort) { try { scopeState.countXhr.abort(); } catch (e) {} }
    scopeState.countXhr = ajax('bw_seo_target_count', { args: JSON.stringify(args) }).done(function (res) {
      if (!res || !res.success) {
        setScopeCountState($m, 0, i18n('matching_error', 'Could not estimate the current filters.'));
        $run.prop('disabled', true);
        return;
      }
      var n = parseInt(res.data && res.data.count, 10) || 0;
      var needsConfirm = $m.find('.bw-scope-confirm-destructive').length && !$m.find('.bw-scope-confirm-destructive').is(':checked');
      if (n <= 0) {
        setScopeCountState($m, 0, i18n('matching_empty', 'No items match the current filters yet.'));
      } else if (needsConfirm) {
        setScopeCountState($m, n, i18n('matching_confirm', 'Matches found. Confirm the destructive action to enable Run.'));
      } else {
        setScopeCountState($m, n, i18n('matching_ready', 'These items will be processed if you press Run.'));
      }
      $run.prop('disabled', n <= 0 || needsConfirm);
    }).fail(function (xhr, status) {
      if (status === 'abort') { return; }
      setScopeCountState($m, 0, i18n('matching_error', 'Could not estimate the current filters.'));
      $run.prop('disabled', true);
    });
  }

  function scheduleCountRefresh() {
    if (scopeState.countTimer) { clearTimeout(scopeState.countTimer); }
    scopeState.countTimer = setTimeout(refreshCount, 250);
  }

  function closeScopeModal() {
    if (scopeState.$modal) { scopeState.$modal.remove(); scopeState.$modal = null; }
    if (scopeState.countXhr && scopeState.countXhr.abort) { try { scopeState.countXhr.abort(); } catch (e) {} }
    if (scopeState.countTimer) { clearTimeout(scopeState.countTimer); scopeState.countTimer = null; }
  }

  $(document).on('click', '.bw-scope-overlay .bw-modal-close, .bw-scope-overlay .bw-scope-cancel', function () { closeScopeModal(); });
  $(document).on('click', '.bw-scope-overlay', function (e) { if (e.target === this) { closeScopeModal(); } });
  $(document).on('change', '.bw-scope-overlay .bw-scope-pt', function () { loadTermsForSelectedTypes(); scheduleCountRefresh(); });
  $(document).on('change', '.bw-scope-overlay .bw-scope-term, .bw-scope-overlay .bw-scope-only-changed, .bw-scope-overlay .bw-scope-missing-seo-title, .bw-scope-overlay .bw-scope-missing-faq, .bw-scope-overlay .bw-scope-link-role, .bw-scope-overlay .bw-scope-auto-keyword, .bw-scope-overlay .bw-scope-demote-h1, .bw-scope-overlay .bw-scope-add-subheadings', scheduleCountRefresh);
  $(document).on('input change', '.bw-scope-overlay .bw-scope-missing-meta, .bw-scope-overlay .bw-scope-score-lt, .bw-scope-overlay .bw-scope-score-gt, .bw-scope-overlay .bw-scope-slug-length, .bw-scope-overlay .bw-scope-intro-paragraphs, .bw-scope-overlay .bw-scope-target-words, .bw-scope-overlay .bw-scope-external-links-count', scheduleCountRefresh);
  $(document).on('change', '.bw-scope-overlay .bw-scope-confirm-destructive', refreshCount);
  $(document).on('click', '.bw-scope-overlay .bw-scope-run', function () {
    if (!scopeState.opts) { return; }
    var args = buildArgsFromModal();
    var opts = scopeState.opts;
    closeScopeModal();
    startJob(opts.job, args, opts.btn);
  });

  function initStaticProgress() {
    $('[data-progress]').each(function () {
      var value = parseInt($(this).attr('data-progress'), 10);
      if (isNaN(value)) { value = 0; }
      value = Math.max(0, Math.min(100, value));
      this.style.width = value + '%';
    });
    $('[data-ring-progress]').each(function () {
      var value = parseInt($(this).attr('data-ring-progress'), 10);
      if (isNaN(value)) { value = 0; }
      value = Math.max(0, Math.min(100, value));
      this.style.setProperty('--p', value);
    });
  }

  function inspectorSeverityClass(severity) {
    if (severity === 'danger' || severity === 'warn' || severity === 'success') {
      return severity;
    }
    return 'info';
  }

  function renderOutputInspector(data, $out) {
    if (!$out || !$out.length) { return; }
    if (!data || !data.ok) {
      $out.html('<div class="bw-notice danger">' + escapeHtml((data && data.error) || i18n('error', 'Error')) + '</div>');
      return;
    }

    var findings = Array.isArray(data.findings) ? data.findings : [];
    var descs = (data.meta || []).filter(function (m) { return (m.name || '').toLowerCase() === 'description'; });
    var og = (data.meta || []).filter(function (m) { return (m.property || '').toLowerCase().indexOf('og:') === 0; });
    var tw = (data.meta || []).filter(function (m) { return (m.name || '').toLowerCase().indexOf('twitter:') === 0; });
    var html = '';

    html += '<div class="bw-card">';
    html += '<h3><span class="dashicons dashicons-shield"></span> ' + escapeHtml(i18n('findings', 'Findings')) + '</h3>';
    if (!findings.length) {
      html += '<p class="bw-text-success">' + escapeHtml(i18n('no_issues_detected', 'No issues detected.')) + '</p>';
    } else {
      html += '<ul class="bw-inspect-list">';
      findings.forEach(function (finding) {
        var severity = inspectorSeverityClass(finding.severity);
        html += '<li class="bw-inspect-item"><span class="bw-inspect-severity is-' + severity + '">' + escapeHtml(finding.severity) + '</span> ' + escapeHtml(finding.message) + '</li>';
      });
      html += '</ul>';
    }
    html += '</div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-editor-textcolor"></span> ' + escapeHtml(i18n('title_label', 'Title')) + ' (' + (data.title || []).length + ')</h3>';
    (data.title || []).forEach(function (title) { html += '<div class="bw-inspect-mono">' + escapeHtml(title) + '</div>'; });
    html += '</div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-editor-paragraph"></span> ' + escapeHtml(i18n('meta_description_label', 'Meta description')) + ' (' + descs.length + ')</h3>';
    descs.forEach(function (meta) { html += '<div class="bw-inspect-mono">' + escapeHtml(meta.content || '') + '</div>'; });
    if (!descs.length) { html += '<p>—</p>'; }
    html += '</div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-admin-links"></span> ' + escapeHtml(i18n('canonical_label', 'Canonical')) + ' (' + (data.canonical || []).length + ')</h3>';
    (data.canonical || []).forEach(function (canonical) { html += '<div class="bw-inspect-mono">' + escapeHtml(canonical) + '</div>'; });
    if (!(data.canonical || []).length) { html += '<p>—</p>'; }
    html += '</div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-share"></span> ' + escapeHtml(i18n('open_graph_label', 'Open Graph')) + ' (' + og.length + ')</h3><table class="bw-table">';
    og.forEach(function (meta) { html += '<tr><td class="bw-inspect-key">' + escapeHtml(meta.property) + '</td><td>' + escapeHtml(meta.content || '') + '</td></tr>'; });
    if (!og.length) { html += '<tr><td>—</td></tr>'; }
    html += '</table></div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-twitter"></span> ' + escapeHtml(i18n('twitter_cards_label', 'Twitter Cards')) + ' (' + tw.length + ')</h3><table class="bw-table">';
    tw.forEach(function (meta) { html += '<tr><td class="bw-inspect-key">' + escapeHtml(meta.name) + '</td><td>' + escapeHtml(meta.content || '') + '</td></tr>'; });
    if (!tw.length) { html += '<tr><td>—</td></tr>'; }
    html += '</table></div>';

    html += '<div class="bw-card"><h3><span class="dashicons dashicons-editor-code"></span> ' + escapeHtml(i18n('json_ld_label', 'JSON-LD')) + ' (' + (data.jsonld || []).length + ' blocks)</h3>';
    (data.jsonld || []).forEach(function (block, index) {
      var validityClass = block.valid ? 'bw-text-success' : 'bw-text-danger';
      var validityLabel = block.valid ? i18n('valid', 'valid') : i18n('invalid', 'invalid');
      html += '<div class="bw-inspect-jsonld-row"><strong>#' + (index + 1) + '</strong> · <span class="' + validityClass + '">' + escapeHtml(validityLabel) + '</span> · ' + escapeHtml(String(block.bytes)) + ' bytes · types: ' + escapeHtml((block.types || []).join(', ') || '—') + '</div>';
    });
    if (!(data.jsonld || []).length) { html += '<p>—</p>'; }
    html += '</div>';

    $out.html(html);
  }

  function runOutputInspector() {
    var $form = $('#bw-inspect-form');
    var $input = $('#bw-inspect-url');
    var $btn = $('#bw-inspect-run');
    var $out = $('#bw-inspect-result');
    if (!$form.length || !$input.length || !$btn.length || !$out.length) { return; }
    var url = $.trim($input.val());
    if (!url) { return; }
    $btn.prop('disabled', true);
    $out.html('<div class="bw-card"><p>' + escapeHtml(i18n('fetching_live_html', 'Fetching live HTML…')) + '</p></div>');
    ajax('bw_seo_inspect_output', { url: url }).done(function (res) {
      renderOutputInspector(res && res.data ? res.data : res, $out);
    }).fail(function () {
      $out.html('<div class="bw-notice danger">' + escapeHtml(i18n('network_error', 'Network error.')) + '</div>');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  }

  $(document).on('click', '.notice[data-bw-seo-conflict="1"] .notice-dismiss', function () {
    ajax('bw_seo_dismiss_conflict');
  });

  $(document).on('click', '#bw-llmstxt-regen', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.prop('disabled')) { return; }
    var original = $btn.html();
    $btn.prop('disabled', true).html('<span class="bw-spinner"></span> ' + escapeHtml(i18n('regenerating', 'Regenerating…')));
    ajax('bw_seo_llmstxt_regen').done(function (res) {
      if (res && res.success) {
        window.location.reload();
        return;
      }
      $btn.prop('disabled', false).html(original);
    }).fail(function () {
      $btn.prop('disabled', false).html(original);
    });
  });

  $(document).on('click', '#bw-media-alt-run', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $bar = $('#bw-media-alt-bar');
    var $status = $('#bw-media-alt-status');
    var initial = parseInt($btn.attr('data-initial-missing'), 10) || 0;
    var batch = parseInt($btn.attr('data-batch'), 10) || 3;
    var done = 0;
    var cursor = 0;

    function tick() {
      ajax('bw_seo_media_alt_batch', { batch: batch, cursor: cursor }).done(function (res) {
        if (!res || !res.success) {
          $status.text(i18n('error', 'Error'));
          $btn.prop('disabled', false);
          return;
        }
        var data = res.data || {};
        done += (data.processed || []).length;
        cursor = data.cursor || cursor;
        var pct = initial > 0 ? Math.min(100, Math.round((done / initial) * 100)) : 100;
        $bar.css('width', pct + '%').attr('data-progress', pct);
        $status.text(done + ' / ' + initial + ' (' + pct + '%) — ' + i18n('remaining', 'remaining') + ': ' + (data.remaining || 0));
        if ((data.processed || []).length === 0 || (data.remaining || 0) <= 0) {
          $status.text($status.text() + ' — ' + i18n('done_label', 'done.'));
          $btn.prop('disabled', false);
          return;
        }
        window.setTimeout(tick, 400);
      }).fail(function () {
        $status.text(i18n('network_error', 'Network error.'));
        $btn.prop('disabled', false);
      });
    }

    $btn.prop('disabled', true);
    done = 0;
    cursor = 0;
    $bar.css('width', '0%').attr('data-progress', '0');
    $status.text(i18n('starting', 'Starting…'));
    tick();
  });

  $(document).on('submit', '#bw-inspect-form', function (e) {
    e.preventDefault();
    runOutputInspector();
  });

  $(document).on('click', '.bw-confirm-delete', function (e) {
    var message = $(this).attr('data-confirm-message') || 'Delete?';
    if (!window.confirm(message)) {
      e.preventDefault();
    }
  });

  $(function () {
    initStaticProgress();
    if ($('#bw-inspect-form').length && $.trim($('#bw-inspect-url').val())) {
      runOutputInspector();
    }
  });

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escapeAttr(s) { return escapeHtml(s); }

})(jQuery);
