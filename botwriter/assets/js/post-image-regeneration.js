(function ($) {
  var cfg = window.botwriter_post_image_regeneration || {};
  var i18n = cfg.i18n || {};
  var state = {
    postId: 0,
    generated: null,
    providerDisabled: false
  };

  function t(key, fallback) {
    return i18n[key] || fallback;
  }

  function getPostId() {
    var fromInput = parseInt($('#post_ID').val(), 10);
    if (fromInput) {
      return fromInput;
    }

    if (window.wp && wp.data && wp.data.select) {
      try {
        var postId = wp.data.select('core/editor').getCurrentPostId();
        return parseInt(postId, 10) || 0;
      } catch (e) {
        return 0;
      }
    }

    return 0;
  }

  function ensureStyles() {
    if ($('#botwriter-regenerate-modal-styles').length) {
      return;
    }

    var css = '' +
      '#botwriter-regenerate-modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.54);z-index:100000;display:none;align-items:center;justify-content:center;padding:20px;}' +
      '#botwriter-regenerate-modal{width:min(760px,96vw);max-height:90vh;overflow:auto;background:#fff;border-radius:16px;box-shadow:0 30px 80px rgba(2,6,23,.28);border:1px solid #dbe3ef;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}' +
      '.bw-rg-header{padding:18px 22px;border-bottom:1px solid #eef3fa;background:linear-gradient(135deg,#f8fbff,#f2f7ff);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;}' +
      '.bw-rg-title{font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;margin:0;}' +
      '.bw-rg-subtitle{font-size:13px;color:#475569;margin:6px 0 0 0;}' +
      '.bw-rg-close{background:#fff;border:1px solid #d5e1f2;border-radius:10px;width:34px;height:34px;cursor:pointer;color:#334155;font-size:18px;line-height:1;}' +
      '.bw-rg-body{padding:20px 22px 10px 22px;}' +
      '.bw-rg-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}' +
      '.bw-rg-chip{border:1px solid #dde7f5;background:#f8fbff;padding:10px 12px;border-radius:10px;}' +
      '.bw-rg-chip label{display:block;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;}' +
      '.bw-rg-chip span{font-size:13px;color:#0f172a;font-weight:600;word-break:break-word;}' +
      '.bw-rg-warning{display:none;margin:10px 0;padding:10px 12px;border-radius:10px;background:#fff8db;border:1px solid #ffe6a8;color:#7a5c00;font-size:12px;}' +
      '.bw-rg-error{display:none;margin:10px 0;padding:10px 12px;border-radius:10px;background:#fff0f0;border:1px solid #ffcaca;color:#b42318;font-size:12px;}' +
      '.bw-rg-label{display:block;font-size:12px;color:#475569;font-weight:600;margin:14px 0 6px 0;}' +
      '#bw-rg-prompt{width:100%;min-height:120px;border:1px solid #d7e1f0;border-radius:10px;padding:11px 12px;font-size:13px;line-height:1.5;resize:vertical;}' +
      '#bw-rg-cleanup{width:100%;border:1px solid #d7e1f0;border-radius:10px;padding:9px 11px;font-size:13px;}' +
      '.bw-rg-progress{display:none;margin-top:14px;border:1px solid #dbe7f9;border-radius:10px;padding:10px 12px;background:#f7fbff;}' +
      '.bw-rg-progress-label{font-size:12px;color:#1d4f91;margin-bottom:7px;}' +
      '.bw-rg-progress-track{width:100%;height:10px;border-radius:999px;background:#d9e7fb;overflow:hidden;position:relative;}' +
      '.bw-rg-progress-bar{position:absolute;inset:0;width:42%;background:linear-gradient(90deg,#2563eb,#60a5fa,#2563eb);border-radius:999px;animation:bw-rg-slide 1.4s infinite ease-in-out;}' +
      '@keyframes bw-rg-slide{0%{transform:translateX(-110%);}100%{transform:translateX(260%);}}' +
      '.bw-rg-preview{display:none;margin-top:16px;border:1px solid #dde7f5;border-radius:12px;padding:12px;background:#f8fbff;}' +
      '.bw-rg-preview img{display:block;width:100%;max-height:320px;object-fit:contain;border-radius:9px;background:#fff;border:1px solid #d8e3f2;}' +
      '.bw-rg-preview-meta{margin-top:8px;font-size:12px;color:#334155;}' +
      '.bw-rg-status{margin-top:12px;min-height:20px;font-size:12px;font-weight:600;}' +
      '.bw-rg-status.info{color:#145ca5;}' +
      '.bw-rg-status.error{color:#b42318;}' +
      '.bw-rg-status.success{color:#087443;}' +
      '.bw-rg-footer{padding:14px 22px 20px 22px;display:flex;gap:10px;justify-content:flex-end;}' +
      '.bw-rg-btn{border:1px solid #d4deef;border-radius:10px;padding:9px 14px;background:#fff;color:#334155;font-size:13px;font-weight:600;cursor:pointer;}' +
      '.bw-rg-btn-primary{background:#0f7ae5;border-color:#0f7ae5;color:#fff;}' +
      '.bw-rg-btn-success{background:#0a8f55;border-color:#0a8f55;color:#fff;}' +
      '.bw-rg-btn[disabled]{opacity:.55;cursor:not-allowed;}' +
      '.botwriter-regenerate-link-wrap{margin-top:8px;}' +
      '.botwriter-regenerate-inline-link{font-size:13px;color:#a21caf;text-decoration:none;font-weight:500;display:inline-flex;align-items:center;gap:4px;}' +
      '.botwriter-regenerate-inline-link:hover{text-decoration:underline;color:#86198f;}';

    $('<style id="botwriter-regenerate-modal-styles"></style>').text(css).appendTo('head');
  }

  function ensureModal() {
    if ($('#botwriter-regenerate-modal-overlay').length) {
      return;
    }

    var html = '' +
      '<div id="botwriter-regenerate-modal-overlay" aria-hidden="true">' +
      '  <div id="botwriter-regenerate-modal" role="dialog" aria-modal="true">' +
      '    <div class="bw-rg-header">' +
      '      <div>' +
      '        <h2 class="bw-rg-title">&#129302; ' + t('modal_title', 'BotWriter Image Regeneration') + '</h2>' +
      '        <p class="bw-rg-subtitle">' + t('modal_subtitle', 'Generate and preview a featured image before applying it.') + '</p>' +
      '      </div>' +
      '      <button type="button" class="bw-rg-close" id="bw-rg-close" aria-label="' + t('btn_close', 'Close') + '">×</button>' +
      '    </div>' +
      '    <div class="bw-rg-body">' +
      '      <div class="bw-rg-grid">' +
      '        <div class="bw-rg-chip"><label>' + t('provider', 'Current provider') + '</label><span id="bw-rg-provider">—</span></div>' +
      '        <div class="bw-rg-chip"><label>' + t('model', 'Current model') + '</label><span id="bw-rg-model">—</span></div>' +
      '      </div>' +
      '      <div id="bw-rg-warning" class="bw-rg-warning"></div>' +
      '      <div id="bw-rg-error" class="bw-rg-error"></div>' +
      '      <label class="bw-rg-label" for="bw-rg-prompt">' + t('prompt_label', 'Image Prompt') + '</label>' +
      '      <textarea id="bw-rg-prompt"></textarea>' +
      '      <label class="bw-rg-label" for="bw-rg-cleanup">' + t('cleanup_label', 'Previous featured image') + '</label>' +
      '      <select id="bw-rg-cleanup">' +
      '        <option value="keep_old">' + t('cleanup_keep', 'Keep in media library') + '</option>' +
      '        <option value="delete_old">' + t('cleanup_delete', 'Delete permanently (if not used elsewhere)') + '</option>' +
      '      </select>' +
      '      <div id="bw-rg-progress" class="bw-rg-progress">' +
      '        <div id="bw-rg-progress-label" class="bw-rg-progress-label"></div>' +
      '        <div class="bw-rg-progress-track"><div class="bw-rg-progress-bar"></div></div>' +
      '      </div>' +
      '      <div id="bw-rg-preview" class="bw-rg-preview">' +
      '        <img id="bw-rg-preview-img" src="" alt="Generated image preview" />' +
      '        <div id="bw-rg-preview-meta" class="bw-rg-preview-meta"></div>' +
      '      </div>' +
      '      <div id="bw-rg-status" class="bw-rg-status"></div>' +
      '    </div>' +
      '    <div class="bw-rg-footer">' +
      '      <button type="button" class="bw-rg-btn" id="bw-rg-cancel">' + t('btn_close', 'Close') + '</button>' +
      '      <button type="button" class="bw-rg-btn bw-rg-btn-primary" id="bw-rg-regenerate">' + t('btn_regenerate', 'Regenerate') + '</button>' +
      '      <button type="button" class="bw-rg-btn bw-rg-btn-success" id="bw-rg-accept" disabled>' + t('btn_accept', 'Accept') + '</button>' +
      '    </div>' +
      '  </div>' +
      '</div>';

    $('body').append(html);
  }

  function setStatus(text, type) {
    var $status = $('#bw-rg-status');
    $status.removeClass('info error success').addClass(type || '').text(text || '');
  }

  function showErrorBox(text) {
    var $box = $('#bw-rg-error');
    if (!text) {
      $box.hide().text('');
      return;
    }
    $box.text(text).show();
  }

  function showWarningBox(text) {
    var $box = $('#bw-rg-warning');
    if (!text) {
      $box.hide().text('');
      return;
    }
    $box.text(text).show();
  }

  function setProgress(active, label) {
    var $progress = $('#bw-rg-progress');
    if (active) {
      $('#bw-rg-progress-label').text(label || '');
      $progress.show();
    } else {
      $progress.hide();
      $('#bw-rg-progress-label').text('');
    }
  }

  function setButtonsState(opts) {
    opts = opts || {};
    if (typeof opts.regenerateDisabled !== 'undefined') {
      $('#bw-rg-regenerate').prop('disabled', !!opts.regenerateDisabled);
    }
    if (typeof opts.acceptDisabled !== 'undefined') {
      $('#bw-rg-accept').prop('disabled', !!opts.acceptDisabled);
    }
    if (typeof opts.cancelDisabled !== 'undefined') {
      $('#bw-rg-cancel, #bw-rg-close').prop('disabled', !!opts.cancelDisabled);
    }
  }

  function updateFeaturedImagePreview(newSrc) {
    if (!newSrc) {
      return;
    }

    $('#postimagediv img, #set-post-thumbnail img, .editor-post-featured-image img').attr('src', newSrc);
  }

  function setEditorFeaturedMedia(attachmentId) {
    var id = parseInt(attachmentId, 10);
    if (!id || !window.wp || !wp.data || !wp.data.dispatch) {
      return;
    }
    try {
      wp.data.dispatch('core/editor').editPost({ featured_media: id });
    } catch (e) {
      // noop
    }
  }

  function openModal() {
    state.postId = getPostId();
    state.generated = null;
    state.providerDisabled = false;

    if (!state.postId) {
      return;
    }

    $('#bw-rg-preview').hide();
    $('#bw-rg-preview-img').attr('src', '');
    $('#bw-rg-preview-meta').text('');
    $('#bw-rg-prompt').val('');
    $('#bw-rg-provider').text('—');
    $('#bw-rg-model').text('—');
    showErrorBox('');
    showWarningBox('');
    setStatus(t('loading_context', 'Loading data...'), 'info');
    setProgress(false, '');
    setButtonsState({ regenerateDisabled: true, acceptDisabled: true, cancelDisabled: false });

    $('#botwriter-regenerate-modal-overlay').css('display', 'flex');

    $.ajax({
      url: cfg.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botwriter_get_post_image_regeneration_context',
        nonce: cfg.nonce,
        post_id: state.postId
      }
    }).done(function (resp) {
      if (!resp || !resp.success) {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : t('generic_error', 'Could not regenerate the image. Please try again.');
        showErrorBox(msg);
        setStatus(msg, 'error');
        return;
      }

      var data = resp.data || {};
      $('#bw-rg-provider').text(data.provider || '—');
      $('#bw-rg-model').text(data.model || '—');
      $('#bw-rg-prompt').val(data.prompt || '');

      if (!data.has_prompt) {
        showWarningBox(t('missing_log', 'No BotWriter log with image prompt was found for this post. Please write your prompt manually.'));
      }

      if (data.provider_disabled) {
        state.providerDisabled = true;
        showErrorBox(t('provider_none', 'Image provider is currently set to "none" in settings. Select an image provider first.'));
        setButtonsState({ regenerateDisabled: true, acceptDisabled: true });
        setStatus('', '');
        return;
      }

      setButtonsState({ regenerateDisabled: false, acceptDisabled: true });
      setStatus('', '');
    }).fail(function (xhr) {
      var msg = t('generic_error', 'Could not regenerate the image. Please try again.');
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        msg = xhr.responseJSON.data.message;
      }
      showErrorBox(msg);
      setStatus(msg, 'error');
    });
  }

  function closeModal() {
    $('#botwriter-regenerate-modal-overlay').hide();
    setProgress(false, '');
    setButtonsState({ regenerateDisabled: false, acceptDisabled: true, cancelDisabled: false });
  }

  function regeneratePreview() {
    var prompt = ($('#bw-rg-prompt').val() || '').trim();
    if (!prompt) {
      setStatus(t('empty_prompt', 'Please enter an image prompt before regenerating.'), 'error');
      return;
    }
    if (state.providerDisabled) {
      setStatus(t('provider_none', 'Image provider is currently set to "none" in settings. Select an image provider first.'), 'error');
      return;
    }

    state.generated = null;
    setButtonsState({ regenerateDisabled: true, acceptDisabled: true, cancelDisabled: true });
    showErrorBox('');
    setStatus(t('generating', 'Generating image preview...'), 'info');
    setProgress(true, t('generating', 'Generating image preview...'));

    $.ajax({
      url: cfg.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botwriter_generate_post_image_preview',
        nonce: cfg.nonce,
        post_id: state.postId,
        prompt: prompt
      }
    }).done(function (resp) {
      if (!resp || !resp.success) {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : t('generic_error', 'Could not regenerate the image. Please try again.');
        showErrorBox(msg);
        setStatus(msg, 'error');
        return;
      }

      var data = resp.data || {};
      state.generated = {
        image_url: data.image_url || '',
        provider: data.provider || '',
        model: data.model || '',
        prompt: data.prompt || prompt
      };

      if (!state.generated.image_url) {
        var err = t('generic_error', 'Could not regenerate the image. Please try again.');
        showErrorBox(err);
        setStatus(err, 'error');
        return;
      }

      $('#bw-rg-preview-img').attr('src', state.generated.image_url);
      $('#bw-rg-preview-meta').text((state.generated.provider || '') + (state.generated.model ? ' / ' + state.generated.model : ''));
      $('#bw-rg-preview').show();
      setStatus('Preview ready. ' + t('btn_accept', 'Accept') + ' or ' + t('btn_regenerate', 'Regenerate') + '.', 'success');
      setButtonsState({ acceptDisabled: false });
    }).fail(function (xhr) {
      var msg = t('generic_error', 'Could not regenerate the image. Please try again.');
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        msg = xhr.responseJSON.data.message;
      }
      showErrorBox(msg);
      setStatus(msg, 'error');
    }).always(function () {
      setProgress(false, '');
      setButtonsState({ regenerateDisabled: false, cancelDisabled: false });
      if (!state.generated) {
        setButtonsState({ acceptDisabled: true });
      }
    });
  }

  function acceptPreview() {
    if (!state.generated || !state.generated.image_url) {
      return;
    }

    var prompt = ($('#bw-rg-prompt').val() || '').trim();
    var cleanupPolicy = ($('#bw-rg-cleanup').val() || 'keep_old').trim();

    setButtonsState({ regenerateDisabled: true, acceptDisabled: true, cancelDisabled: true });
    showErrorBox('');
    setStatus(t('applying', 'Applying featured image...'), 'info');
    setProgress(true, t('applying', 'Applying featured image...'));

    $.ajax({
      url: cfg.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botwriter_apply_post_regenerated_image',
        nonce: cfg.nonce,
        post_id: state.postId,
        prompt: prompt,
        image_url: state.generated.image_url,
        provider: state.generated.provider,
        model: state.generated.model,
        cleanup_policy: cleanupPolicy
      }
    }).done(function (resp) {
      if (!resp || !resp.success) {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : t('generic_error', 'Could not regenerate the image. Please try again.');
        showErrorBox(msg);
        setStatus(msg, 'error');
        return;
      }

      var data = resp.data || {};
      updateFeaturedImagePreview(data.featured_image_src || state.generated.image_url);
      setEditorFeaturedMedia(data.attachment_id || 0);

      var msg = data.message || 'Featured image regenerated successfully.';
      if (data.delete_note) {
        msg += ' ' + data.delete_note;
      }
      setStatus(msg, 'success');

      setTimeout(function () {
        closeModal();
      }, 700);
    }).fail(function (xhr) {
      var msg = t('generic_error', 'Could not regenerate the image. Please try again.');
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        msg = xhr.responseJSON.data.message;
      }
      showErrorBox(msg);
      setStatus(msg, 'error');
    }).always(function () {
      setProgress(false, '');
      setButtonsState({ regenerateDisabled: false, acceptDisabled: !state.generated, cancelDisabled: false });
    });
  }

  function ensureRegenerateLink() {
    var inserted = false;
    var linkHtml = '<a href="#" class="botwriter-regenerate-inline-link" title="' + t('btn_regenerate', 'Regenerate') + '">&#129302; ' + t('link_text', 'Regenerate') + '</a>';

    // Gutenberg sidebar featured image panel
    var $gbPanel = $('.editor-post-featured-image').first();
    if ($gbPanel.length && !$gbPanel.find('.botwriter-regenerate-inline-link').length) {
      var $holder = $gbPanel.find('.editor-post-featured-image__container').first();
      var $wrap = $('<div class="botwriter-regenerate-link-wrap"></div>').append(linkHtml);
      if ($holder.length) {
        $holder.after($wrap);
      } else {
        $gbPanel.append($wrap);
      }
      inserted = true;
    }

    // Classic editor featured image box
    var $classic = $('#postimagediv .inside').first();
    if ($classic.length && !$classic.find('.botwriter-regenerate-inline-link').length) {
      $('<div class="botwriter-regenerate-link-wrap"></div>').append(linkHtml).appendTo($classic);
      inserted = true;
    }

    return inserted;
  }

  function initLinkWatcher() {
    if (!window.MutationObserver) {
      setInterval(function () {
        ensureRegenerateLink();
      }, 1200);
      return;
    }

    var observer = new MutationObserver(function () {
      ensureRegenerateLink();
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  $(document).on('click', '.botwriter-regenerate-inline-link', function (e) {
    e.preventDefault();
    openModal();
  });

  $(document).on('click', '#bw-rg-regenerate', function () {
    regeneratePreview();
  });

  $(document).on('click', '#bw-rg-accept', function () {
    acceptPreview();
  });

  $(document).on('click', '#bw-rg-cancel, #bw-rg-close', function () {
    closeModal();
  });

  $(document).on('click', '#botwriter-regenerate-modal-overlay', function (e) {
    if (e.target && e.target.id === 'botwriter-regenerate-modal-overlay') {
      closeModal();
    }
  });

  $(function () {
    ensureStyles();
    ensureModal();
    ensureRegenerateLink();
    initLinkWatcher();
  });
})(jQuery);
