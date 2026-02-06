(function($){
  var progressTimer = null;
  var pollTimer = null;
  var progressValue = 0;
  var currentSpan = 100; // percent to traverse in the current cycle
  var perSecond = currentSpan / 60; // 60s -> 1 min
  var doneFlag = false;
  var errorFlag = false;
  var currentLogId = null;
  var currentTaskId = null;
  var lastStatusText = 'Processing...';
  var $mediaBox, $videoEl, $doneImg;
  var $stepsBox;
  var startTs = null;
  var shownSteps = { t1:false, t2:false, t3:false, t4:false, finishing:false };
  var stepsMeta = []; // {key, start, end, expectedEnd, $fill, barHidden}

  function getLastStep(){ return stepsMeta.length ? stepsMeta[stepsMeta.length-1] : null; }

  function showFinalActions(editLink, viewLink){
    cacheMediaEls();
    if ($stepsBox && $stepsBox.length){
      // Clear processing messages and per-step bars
      $stepsBox.empty();
      stepsMeta = [];
      // Actions container in the right panel (beside the bot image)
      var $actions = $('<div/>', { id: 'bwqp-actions', css: { marginTop: '8px' } });
      var $edit = $('<a/>', { class: 'button button-primary', href: editLink, text: 'Edit post', css: { marginRight: '8px' } });
      $actions.append($edit);
      if (viewLink){
        var $view = $('<a/>', { class: 'button', href: viewLink, target: '_blank', rel: 'noopener', text: 'View' });
        $actions.append($view);
      }
      $stepsBox.append($actions);
    }
  }

  function cacheMediaEls(){
    if (!$mediaBox) { $mediaBox = $('#bwqp-media'); }
    if (!$videoEl) { $videoEl = $('#bwqp-video'); }
    if (!$doneImg) { $doneImg = $('#bwqp-done'); }
    if (!$stepsBox) { $stepsBox = $('#bwqp-steps'); }
  }

  function showWorkingMedia(){
    cacheMediaEls();
    if ($mediaBox.length){ $mediaBox.show(); }
    if ($doneImg && $doneImg.length){ $doneImg.hide(); }
    if ($videoEl && $videoEl.length){
      try { $videoEl.stop(true,true).fadeIn(200); } catch(e){ $videoEl.show(); }
      try { $videoEl.get(0).currentTime = 0; } catch(e){}
      try { $videoEl.get(0).play(); } catch(e){}
    }
  }

  function showDoneMedia(){
    cacheMediaEls();
    if ($videoEl && $videoEl.length){
      try { $videoEl.get(0).pause(); } catch(e){}
      try { $videoEl.stop(true,true).fadeOut(200); } catch(e){ $videoEl.hide(); }
    }
    if ($doneImg && $doneImg.length){ try { $doneImg.stop(true,true).fadeIn(200); } catch(e){ $doneImg.show(); } }
    if ($mediaBox.length){ $mediaBox.show(); }
  }

  function hideMedia(){
    cacheMediaEls();
    try { if ($videoEl && $videoEl.length) { $videoEl.get(0).pause(); } } catch(e){}
    if ($mediaBox && $mediaBox.length){ $mediaBox.hide(); }
  }

  function resetSteps(){
    cacheMediaEls();
    if ($stepsBox && $stepsBox.length){ $stepsBox.empty(); }
    shownSteps = { t1:false, t2:false, t3:false, t4:false, finishing:false };
    startTs = Date.now();
    stepsMeta = [];
  }

  function appendStep(text, key, expectedDurSec){
    cacheMediaEls();
    if ($stepsBox && $stepsBox.length){
      // Close previous step with an end time (actual boundary)
      var now = Date.now();
      var prev = getLastStep();
      if (prev && !prev.end){ prev.end = now; prev.expectedEnd = prev.end; }

      // Build step container with its own progress bar
      var $wrap = $('<div/>', { class: 'bwqp-step', css: { margin: '0 0 8px 0' } });
      var $p = $('<p/>', { text: text, css: { display: 'none', margin: '0 0 4px 0' } });
      var $bar = $('<div/>', { class: 'bwqp-stepbar', css: { background:'#e5e5e5', borderRadius:'4px', overflow:'hidden', height:'6px', display:'block' } });
      var $fill = $('<div/>', { class: 'bwqp-stepbar-fill', css: { width:'0%', background:'#2271b1', height:'6px', transition:'width .3s' } });
      $bar.append($fill);
      $wrap.append($p).append($bar);
      $stepsBox.append($wrap);
      try { $p.fadeIn(250); } catch(e){ $p.show(); }

      // Register step meta
      var expectedMs = (typeof expectedDurSec === 'number' && expectedDurSec > 0) ? (now + expectedDurSec*1000) : null;
      stepsMeta.push({ key: key || ('k'+stepsMeta.length), start: now, end: null, expectedEnd: expectedMs, $fill: $fill, barHidden: false });
    }
  }

  function updateSteps(){
    if (!startTs) return;
    var elapsed = Math.floor((Date.now() - startTs) / 1000);
    
    // Check if images are disabled for this task
    var imagesDisabled = $('#poststuff input[name="disable_ai_images"]:checked').length > 0;
    
    // Timeline
    if (elapsed <= 25 && !shownSteps.t1){ appendStep('Working on the text...', 't1', 26); shownSteps.t1 = true; }
    
    if (elapsed >= 26 && elapsed <= 35 && !shownSteps.t2){ 
      if (imagesDisabled) {
        appendStep('Optimizing content structure..', 't2', 10); 
      } else {
        appendStep('Designing the image..', 't2', 10); 
      }
      shownSteps.t2 = true; 
    }
    
    if (elapsed >= 36 && elapsed <= 50 && !shownSteps.t3){ 
      if (imagesDisabled) {
        appendStep('Enhancing SEO elements..', 't3', 14);
      } else {
        appendStep('Generating the image..', 't3', 14); 
      }
      shownSteps.t3 = true; 
    }
    
    if (elapsed >= 50 && !shownSteps.t4){ appendStep('Final composition..', 't4', 30); shownSteps.t4 = true; }
  }

  function updateStepBars(){
    var now = Date.now();
    for (var i=0;i<stepsMeta.length;i++){
      var s = stepsMeta[i];
      if (s.barHidden) continue;
      var endTs = s.end || s.expectedEnd;
      if (!endTs){
        // No known boundary yet; keep bar slowly moving to show activity but cap at 95%
        var softPct = Math.min(95, Math.floor((now - s.start) / 1000)); // 1% per second up to 95%
        s.$fill.css('width', softPct + '%');
        continue;
      }
      var total = Math.max(1, endTs - s.start);
      var prog = Math.max(0, Math.min(1, (now - s.start) / total));
      var pct = Math.floor(prog * 100);
      s.$fill.css('width', pct + '%');
      if (prog >= 1 && !s.barHidden){
        // Hide the bar once it reaches 100%
        try { s.$fill.parent().fadeOut(250); } catch(e){ s.$fill.parent().hide(); }
        s.barHidden = true;
      }
    }
  }

  function resetProgressLoop(){
    progressValue = 0;
    currentSpan = 100;
    perSecond = currentSpan / 60;
    doneFlag = false;
    errorFlag = false;
    lastStatusText = 'Processing...';
  }

  function startProgress(){
    resetProgressLoop();
    resetSteps();
    $('#bwqp-status').empty();
    $('#bwqp-retry').remove();
    lastStatusText = 'Starting...';
    setProgress(0, lastStatusText);
    if (progressTimer) clearInterval(progressTimer);
    progressTimer = setInterval(function(){
      if (doneFlag || errorFlag){
        setProgress(100, doneFlag ? 'Completed' : 'Finished with errors');
        clearInterval(progressTimer);
        progressTimer = null;
        return;
      }
      updateSteps();
      updateStepBars();
      progressValue += perSecond; // advance per second
      if (progressValue >= 100){
        // If not done yet, bounce back to 50% and continue a new 1-min cycle (50->100 over 60s)
        if (!doneFlag && !errorFlag){
          progressValue = 50;
          currentSpan = 50;
          perSecond = currentSpan / 60; // next loop: 50 -> 100 in 60s
          lastStatusText = lastStatusText || 'Processing...';
          setProgress(50, lastStatusText);
        } else {
          setProgress(100, doneFlag ? 'Completed' : 'Finished with errors');
          clearInterval(progressTimer);
          progressTimer = null;
        }
      } else {
        setProgress(Math.floor(progressValue), lastStatusText || 'Processing...');
      }
    }, 1000);
  }

  function collectFormData(){
    // Collect all inputs inside our quick form container
    var arr = $('#poststuff :input').serializeArray();
    var data = {};
    arr.forEach(function(it){
      if (it.name.endsWith('[]')){
        var key = it.name.slice(0,-2);
        if (!data[key]) data[key] = [];
        data[key].push(it.value);
      } else if (data[it.name] !== undefined) {
        // multiple with same name
        if (!Array.isArray(data[it.name])) data[it.name] = [data[it.name]];
        data[it.name].push(it.value);
      } else {
        data[it.name] = it.value;
      }
    });
    return data;
  }

  function setProgress(pct, text){
    pct = Math.max(0, Math.min(100, parseInt(pct,10)||0));
    $('#bwqp-progress').show();
    $('#bwqp-bar').css('width', pct + '%').text(pct + '%');
    if (text) $('#bwqp-status').text(text);
  }

  function showErrorUI(message){
    hideMedia();
    cacheMediaEls();
    if ($stepsBox && $stepsBox.length){
      $stepsBox.empty();
      var $err = $('<div/>', { class: 'notice notice-error', css: { padding:'8px', marginBottom:'8px' } }).text(message || 'An error occurred');
      $stepsBox.append($err);
      var $actions = $('<div/>', { css: { marginTop:'4px' } });
      var $retry = $('<button/>', { id:'bwqp-retry', class:'button button-primary', text:'Retry', css:{ marginRight:'8px' } });
      var $cancel = $('<button/>', { id:'bwqp-cancel', class:'button', text:'Cancel' });
      $actions.append($retry).append($cancel);
      $stepsBox.append($actions);
    }
  }

  $(document).on('click', '#botwriter-quick-create', function(){
    var $btn = $(this);
    $btn.prop('disabled', true);
    startProgress();
    showWorkingMedia();
    // Smooth scroll to progress area
    try {
      var $target = $('#bwqp-progress');
      if ($target.length){
        $('html, body').animate({ scrollTop: Math.max(0, $target.offset().top - 50) }, 400);
      }
    } catch(e){}

    var payload = collectFormData();
    payload.action = 'botwriter_quick_create';
    payload.nonce = botwriter_quickpost_ajax.nonce;

    $.post(botwriter_quickpost_ajax.ajax_url, payload)
      .done(function(resp){
        if (!resp || !resp.success){
          var msg = (resp && resp.data) ? resp.data : 'Unknown error';
          errorFlag = true;
          setProgress(100, 'Finished with errors: ' + msg);
          showErrorUI(msg);
          $btn.prop('disabled', false);
          return;
        }
        var logId = resp.data.log_id;
        var taskId = resp.data.task_id;
        currentLogId = logId;
        currentTaskId = taskId;
        var pollCount = 0;
  lastStatusText = 'Queued. Processing...';
  setProgress(Math.max(progressValue, 5), lastStatusText);

        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function(){
          pollCount++;
          $.post(botwriter_quickpost_ajax.ajax_url, {
            action: 'botwriter_quick_poll',
            nonce: botwriter_quickpost_ajax.nonce,
            log_id: logId
          }).done(function(p){
            if (!p || !p.success){
              errorFlag = true;
              setProgress(100, 'Finished with errors');
              showErrorUI('Error during polling');
              clearInterval(pollTimer);
              pollTimer = null;
              $btn.prop('disabled', false);
              return;
            }
            var d = p.data || {};
            var status = d.status || 'pending';
            if (status === 'pending') { lastStatusText = 'Processing...'; }
            else if (status === 'inqueue') { lastStatusText = 'Generating...'; }
            // Avoid flashing raw "error"/"completed" status text; handled below
            if (status !== 'completed' && status !== 'error') {
              setProgress(progressValue, lastStatusText);
            }

            if (status === 'completed' || status === 'error'){
              if (status === 'error'){
                clearInterval(pollTimer);
                pollTimer = null;
                errorFlag = true;
                lastStatusText = 'Finished with errors' + (d.error ? (': ' + d.error) : '');
                setProgress(100, lastStatusText);
                showErrorUI(d.error || 'Unknown error');
              } else if (status === 'completed'){
                // Server done; keep video until WordPress post exists
                if (!shownSteps.finishing){ appendStep('Finishing the post in WordPress... please wait a few seconds', 'finishing', 60); shownSteps.finishing = true; }
                if (d.id_post_published && d.edit_link){
                  // Mark end of finishing step
                  var last = getLastStep();
                  if (last && last.key === 'finishing' && !last.end){ last.end = Date.now(); last.expectedEnd = last.end; }
                  clearInterval(pollTimer);
                  pollTimer = null;
                  doneFlag = true;
                  lastStatusText = 'Completed';
                  setProgress(100, lastStatusText);
                  showDoneMedia();
                  showFinalActions(d.edit_link, d.view_link);
                  $btn.prop('disabled', false);
                } else {
                  // Keep polling; keep video visible; show 100% main bar with finalizing text
                  lastStatusText = 'Finalizing in WordPress...';
                  setProgress(Math.max(progressValue, 100), lastStatusText);
                }
              }
              if (status === 'error') { $btn.prop('disabled', false); }
            }
          }).fail(function(){
            errorFlag = true;
            lastStatusText = 'Polling failed';
            setProgress(100, lastStatusText);
            showErrorUI('Polling failed');
            clearInterval(pollTimer);
            pollTimer = null;
            $btn.prop('disabled', false);
          });
  }, 3000);
      })
      .fail(function(){
        errorFlag = true;
        lastStatusText = 'Request failed';
        setProgress(100, lastStatusText);
        $btn.prop('disabled', false);
      });
  });

  // Cancel handler
  $(document).on('click', '#bwqp-cancel', function(){
    var $cancel = $(this);
    if (!currentLogId || !currentTaskId){ return; }
    $cancel.prop('disabled', true);
    if (pollTimer){ clearInterval(pollTimer); pollTimer = null; }
    if (progressTimer){ clearInterval(progressTimer); progressTimer = null; }
    $.post(botwriter_quickpost_ajax.ajax_url, {
      action: 'botwriter_quick_cancel',
      nonce: botwriter_quickpost_ajax.nonce,
      log_id: currentLogId,
      task_id: currentTaskId
    }).done(function(r){
      if (!r || !r.success){
        showErrorUI('Cancel failed');
        $cancel.prop('disabled', false);
        return;
      }
      hideMedia();
      cacheMediaEls();
      if ($stepsBox && $stepsBox.length){
        $stepsBox.empty().append($('<div/>', { text: 'Canceled.', css:{ padding:'8px' } }));
      }
      setProgress(0, 'Canceled');
    }).fail(function(){
      showErrorUI('Cancel request failed');
      $cancel.prop('disabled', false);
    });
  });

  // Retry handler
  $(document).on('click', '#bwqp-retry', function(){
    var $retry = $(this);
    if (!currentLogId){ return; }
    $retry.prop('disabled', true);
    // reset flags and progress
    resetProgressLoop();
  lastStatusText = 'Retrying...';
  setProgress(5, lastStatusText);
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    showWorkingMedia();
  resetSteps();

    $.post(botwriter_quickpost_ajax.ajax_url, {
      action: 'botwriter_quick_retry',
      nonce: botwriter_quickpost_ajax.nonce,
      log_id: currentLogId
    }).done(function(r){
      if (!r || !r.success){
        errorFlag = true;
        setProgress(100, 'Retry failed');
        showErrorUI('Retry failed');
        $retry.prop('disabled', false);
        return;
      }
      // restart progress animation if it was stopped
      if (!progressTimer){ startProgress(); }
      // restart polling
      pollTimer = setInterval(function(){
        $.post(botwriter_quickpost_ajax.ajax_url, {
          action: 'botwriter_quick_poll',
          nonce: botwriter_quickpost_ajax.nonce,
          log_id: currentLogId
        }).done(function(p){
          if (!p || !p.success){
            errorFlag = true;
            setProgress(100, 'Finished with errors');
            showErrorUI('Error during polling');
            clearInterval(pollTimer);
            pollTimer = null;
            $retry.prop('disabled', false);
            return;
          }
          var d = p.data || {};
          var status = d.status || 'pending';
          if (status === 'pending') { lastStatusText = 'Processing...'; }
          else if (status === 'inqueue') { lastStatusText = 'Generating...'; }
          if (status !== 'completed' && status !== 'error') {
            setProgress(progressValue, lastStatusText);
          }
          if (status === 'error'){
            clearInterval(pollTimer);
            pollTimer = null;
            errorFlag = true;
            lastStatusText = 'Finished with errors' + (d.error ? (': ' + d.error) : '');
            setProgress(100, lastStatusText);
            showErrorUI(d.error || 'Unknown error');
            $retry.prop('disabled', false);
          } else if (status === 'completed'){
            if (!shownSteps.finishing){ appendStep('Finishing the post in WordPress... please wait a few seconds', 'finishing', 60); shownSteps.finishing = true; }
            if (d.id_post_published && d.edit_link){
              var last = getLastStep();
              if (last && last.key === 'finishing' && !last.end){ last.end = Date.now(); last.expectedEnd = last.end; }
              clearInterval(pollTimer);
              pollTimer = null;
              doneFlag = true;
              lastStatusText = 'Completed';
              setProgress(100, lastStatusText);
              showFinalActions(d.edit_link, d.view_link);
              $retry.prop('disabled', false);
            } else {
              lastStatusText = 'Finalizing in WordPress...';
              setProgress(Math.max(progressValue, 100), lastStatusText);
            }
          }
        }).fail(function(){
          errorFlag = true;
          lastStatusText = 'Polling failed';
          setProgress(100, lastStatusText);
          showErrorUI('Polling failed');
          clearInterval(pollTimer);
          pollTimer = null;
          $retry.prop('disabled', false);
        });
      }, 3000);
    }).fail(function(){
      errorFlag = true;
      lastStatusText = 'Retry request failed';
      setProgress(100, lastStatusText);
      showErrorUI('Retry request failed');
      $retry.prop('disabled', false);
    });
  });

  // On load: hide days/times fields and inject defaults (today + 1)
  $(function(){
    try {
      // Hide Days of the Week block
      $("label.form-label:contains('Days of the Week:')").closest('.col-md-6').hide()
        .find("input[name='days[]']").prop('disabled', true);
      // Hide Post per Day block
      $("label.form-label:contains('Post per Day:')").closest('.col-md-6').hide()
        .find("input[name='times_per_day']").prop('disabled', true);

      // Inject hidden defaults in the form scope
      var $form = $('#poststuff');
      if ($form.length){
        // Today in English
        var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var today = days[(new Date()).getDay()];
        if ($form.find("input[name='days[]']").length === 0){
          $('<input type="hidden" name="days[]" />').val(today).appendTo($form);
        }
        if ($form.find("input[name='times_per_day']").length === 0){
          $('<input type="hidden" name="times_per_day" value="1" />').appendTo($form);
        }
      }
    } catch(e) {
      // silent
    }
  });
})(jQuery);
