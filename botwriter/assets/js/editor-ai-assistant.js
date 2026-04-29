(function ($) {
  var cfg = window.botwriter_editor_ai || {};
  var i18n = cfg.i18n || {};
  var widgetSettings = cfg.settings || {};
  var seoModuleEnabled = String(widgetSettings.seo_module_enabled || '1') === '1';

  var targets = ['text', 'title', 'tags', 'excerpt', 'seo_meta', 'internal_links'];
  var suggestionsByTarget = {
    text: [
      'Add an introduction.',
      'Add a conclusion.',
      'Add an FAQ section.',
      'Create a post outline.',
      'Optimize this section for SEO while keeping it natural.',
      'Make the text clearer and easier to scan with short paragraphs.',
      'Improve readability and keep the same meaning and tone.',
      'Add a stronger call to action near the end.'
    ],
    title: [
      'Create a stronger SEO-friendly title under 65 characters.',
      'Make the title more specific and outcome-driven.',
      'Rewrite the title to increase click-through rate.',
      'Make the title concise and clear for search intent.'
    ],
    tags: [
      'Generate 8 relevant tags based on this content.',
      'Prioritize long-tail and specific tags only.',
      'Create tags focused on user intent and search queries.',
      'Remove generic tags and keep only high-value tags.'
    ],
    excerpt: [
      'Write a compelling excerpt under 155 characters.',
      'Summarize the main value in one short sentence.',
      'Create an excerpt that improves CTR from search results.',
      'Make the excerpt clear, benefit-focused, and concise.'
    ],
    seo_meta: [
      'Write an SEO meta description under 155 characters.',
      'Highlight the primary keyword naturally in the meta description.',
      'Create a meta description focused on user intent and value.',
      'Rewrite the meta description to improve click-through rate.'
    ],
    internal_links: [
      'Suggest internal links that support topic clusters and SEO depth.',
      'Find conversion-oriented internal links to relevant service pages.',
      'Prioritize educational links that keep users exploring the site.',
      'Suggest links for beginners first, then advanced readers.'
    ]
  };

  var state = {
    target: 'text',
    activeTab: 'prompt',
    seoSubtab: 'seo',
    seoReports: {
      seo: '',
      readability: ''
    },
    seoLoadedForPost: 0,
    seoLoading: false,
    loading: false,
    pendingChange: null,
    selectedSuggestion: '',
    linkSuggestions: [],
    drag: {
      active: false,
      source: '',
      startX: 0,
      startY: 0,
      offsetX: 0,
      offsetY: 0,
      moved: false,
      suppressClick: false,
      userPositioned: false
    }
  };

  function t(key, fallback) {
    return i18n[key] || fallback;
  }

  function isGutenberg() {
    return !!(window.wp && wp.data && wp.data.select && wp.data.dispatch);
  }

  function getEditorCanvasElement() {
    var selectors = [
      '.editor-styles-wrapper',
      '.interface-interface-skeleton__content',
      '.edit-post-layout__content',
      '.editor-visual-editor',
      '#wp-content-editor-container',
      '#post-body-content',
      '#poststuff'
    ];

    for (var i = 0; i < selectors.length; i++) {
      var candidate = document.querySelector(selectors[i]);
      if (candidate && candidate.getBoundingClientRect && candidate.getBoundingClientRect().width > 0) {
        return candidate;
      }
    }

    return null;
  }

  function positionWidgetInEditorArea() {
    var $widget = $('#bw-editor-ai-widget');
    if (!$widget.length || state.drag.active || state.drag.userPositioned) {
      return;
    }

    var gutter = window.innerWidth <= 782 ? 10 : 20;
    var panelW = 360;
    var rightOffset = gutter; // safe default near viewport right edge

    var area = getEditorCanvasElement();
    if (area && area.getBoundingClientRect) {
      var rect = area.getBoundingClientRect();
      // Use rect only when element is narrower than viewport (i.e. not a full-width fallback)
      if (rect.width > 0 && rect.right > 0 && rect.right < window.innerWidth - 10) {
        rightOffset = Math.max(window.innerWidth - rect.right + gutter, gutter);
      }
    }

    // Ensure the panel (panelW wide) fits inside the viewport
    rightOffset = Math.min(rightOffset, Math.max(window.innerWidth - panelW - gutter, gutter));

    $widget.css({
      left: 'auto',
      top: 'auto',
      right: rightOffset + 'px',
      bottom: gutter + 'px'
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeOutput(value) {
    var text = String(value || '').trim();
    text = text.replace(/^```(?:[a-zA-Z0-9_-]+)?\s*/i, '');
    text = text.replace(/\s*```$/, '');
    return text.trim();
  }

  function stripWrappingQuotes(value) {
    var text = String(value || '').trim();
    var quoteRegex = /^["'`\u201C\u201D\u00AB\u00BB\u2018\u2019]+|["'`\u201C\u201D\u00AB\u00BB\u2018\u2019]+$/g;
    var cleaned = text;

    for (var i = 0; i < 3; i++) {
      var next = cleaned.replace(quoteRegex, '').trim();
      if (next === cleaned) {
        break;
      }
      cleaned = next;
    }

    return cleaned;
  }

  function cleanResponseForTarget(target, value) {
    var clean = normalizeOutput(value);

    if (target === 'text') {
      clean = clean
        .replace(/\\r\\n/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\\r/g, '\n')
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
      clean = stripWrappingQuotes(clean);
      return clean;
    }

    if (target === 'title' || target === 'excerpt' || target === 'seo_meta') {
      clean = stripWrappingQuotes(clean);
      clean = clean.replace(/\s+/g, ' ').trim();
      return clean;
    }

    return clean;
  }

  function normalizeForCompare(value) {
    return String(value || '')
      .replace(/\u00a0/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function normalizeTagList(tagsValue) {
    var raw = String(tagsValue || '');
    var parts = raw.split(/[\n,]+/);
    var map = {};
    var normalized = [];

    parts.forEach(function (part) {
      var clean = $.trim(part);
      if (!clean) {
        return;
      }
      var lower = clean.toLowerCase();
      if (map[lower]) {
        return;
      }
      map[lower] = true;
      normalized.push(clean);
    });

    return normalized;
  }

  function getPostId() {
    var fromInput = parseInt($('#post_ID').val(), 10);
    if (fromInput) {
      return fromInput;
    }

    if (isGutenberg()) {
      try {
        var postId = wp.data.select('core/editor').getCurrentPostId();
        return parseInt(postId, 10) || 0;
      } catch (e) {
        return 0;
      }
    }

    return 0;
  }

  function getTitleValue() {
    if (isGutenberg()) {
      try {
        return String(wp.data.select('core/editor').getEditedPostAttribute('title') || '').trim();
      } catch (e) {
        // fall through
      }
    }
    return String($('#title').val() || '').trim();
  }

  function setTitleValue(value) {
    var clean = String(value || '').trim();
    if (isGutenberg()) {
      try {
        wp.data.dispatch('core/editor').editPost({ title: clean });
      } catch (e) {
        // fall through to classic field update
      }
    }
    $('#title').val(clean).trigger('input').trigger('change');
    $('input[name="post_title"]').val(clean).trigger('input').trigger('change');
  }

  function getClassicEditorContent() {
    if (typeof window.tinyMCE !== 'undefined') {
      var editor = window.tinyMCE.get('content');
      if (editor && !editor.isHidden()) {
        return String(editor.getContent({ format: 'raw' }) || '').trim();
      }
    }
    return String($('#content').val() || '').trim();
  }

  function getContentValue() {
    if (isGutenberg()) {
      try {
        var selector = wp.data.select('core/editor');
        if (selector && typeof selector.getEditedPostContent === 'function') {
          return String(selector.getEditedPostContent() || '').trim();
        }
        return String(selector.getEditedPostAttribute('content') || '').trim();
      } catch (e) {
        // fall through
      }
    }
    return getClassicEditorContent();
  }

  function setContentValue(value) {
    var clean = String(value || '').trim();

    if (isGutenberg()) {
      try {
        wp.data.dispatch('core/editor').editPost({ content: clean });
      } catch (e) {
        // fall through
      }
    }

    if (typeof window.tinyMCE !== 'undefined') {
      var editor = window.tinyMCE.get('content');
      if (editor && !editor.isHidden()) {
        editor.setContent(clean);
      }
    }

    $('#content').val(clean).trigger('input').trigger('change');
  }

  function getExcerptValue() {
    if (isGutenberg()) {
      try {
        return String(wp.data.select('core/editor').getEditedPostAttribute('excerpt') || '').trim();
      } catch (e) {
        // fall through
      }
    }
    return String($('#excerpt').val() || '').trim();
  }

  function setExcerptValue(value) {
    var clean = String(value || '').trim();
    if (isGutenberg()) {
      try {
        wp.data.dispatch('core/editor').editPost({ excerpt: clean });
      } catch (e) {
        // fall through
      }
    }
    $('#excerpt').val(clean).trigger('input').trigger('change');
  }

  function getSeoMetaSelectors() {
    return [
      '#yoast_wpseo_metadesc',
      'textarea[name="yoast_wpseo_metadesc"]',
      '#rank-math-description',
      'textarea[name="rank_math_description"]',
      '#aioseo-description',
      'textarea[name="aioseo-description"]',
      '#seopress_titles_desc',
      '#autodescription-metadescription',
      'textarea[name="_genesis_description"]'
    ];
  }

  function getSeoMetaValue() {
    var selectors = getSeoMetaSelectors();
    for (var i = 0; i < selectors.length; i++) {
      var $field = $(selectors[i]).first();
      if ($field.length) {
        var current = String($field.val() || '').trim();
        if (current) {
          return current;
        }
      }
    }

    return getExcerptValue();
  }

  function setSeoMetaValue(value) {
    var clean = String(value || '').trim();
    var selectors = getSeoMetaSelectors();
    var found = false;

    selectors.forEach(function (selector) {
      $(selector).each(function () {
        found = true;
        $(this).val(clean).trigger('input').trigger('change');
      });
    });

    if (!found) {
      // Fallback: keep excerpt in sync when no SEO plugin field is present.
      setExcerptValue(clean);
    }
  }

  function getGutenbergTagsFromStore() {
    if (!isGutenberg()) {
      return [];
    }

    try {
      var editorSelector = wp.data.select('core/editor');
      var coreSelector = wp.data.select('core');
      var ids = editorSelector.getEditedPostAttribute('tags') || [];

      if (!Array.isArray(ids) || !ids.length) {
        return [];
      }

      var terms = coreSelector.getEntityRecords('taxonomy', 'post_tag', {
        include: ids,
        per_page: ids.length,
        context: 'edit'
      });

      if (!Array.isArray(terms)) {
        return [];
      }

      var namesById = {};
      terms.forEach(function (term) {
        if (term && term.id) {
          namesById[term.id] = term.name;
        }
      });

      var ordered = [];
      ids.forEach(function (id) {
        if (namesById[id]) {
          ordered.push(namesById[id]);
        }
      });

      return ordered;
    } catch (e) {
      return [];
    }
  }

  function getGutenbergTagsFromDom() {
    var tags = [];
    $('.editor-post-taxonomies__flat-term-selector .components-form-token-field__token-text').each(function () {
      var value = $.trim($(this).text() || '');
      if (value) {
        tags.push(value);
      }
    });
    return tags;
  }

  function getTagsValue() {
    var classic = $.trim($('#tax-input-post_tag').val() || '');
    if (classic) {
      return classic;
    }

    var storeTags = getGutenbergTagsFromStore();
    if (storeTags.length) {
      return storeTags.join(', ');
    }

    var domTags = getGutenbergTagsFromDom();
    if (domTags.length) {
      return domTags.join(', ');
    }

    return '';
  }

  function slugifyTagName(tagName) {
    return String(tagName || '')
      .toLowerCase()
      .replace(/['"]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  function resolveExistingTagId(tagName) {
    if (!window.wp || !wp.apiFetch) {
      return Promise.resolve(0);
    }

    var searchPath = '/wp/v2/tags?search=' + encodeURIComponent(tagName) + '&per_page=100&context=edit';

    return wp.apiFetch({ path: searchPath })
      .then(function (records) {
        if (!Array.isArray(records)) {
          return null;
        }

        var lowered = tagName.toLowerCase();
        var slug = slugifyTagName(tagName);
        var exact = records.find(function (record) {
          var nameMatch = String(record.name || '').toLowerCase() === lowered;
          var slugMatch = String(record.slug || '').toLowerCase() === slug;
          return nameMatch || slugMatch;
        });

        if (exact && exact.id) {
          return exact.id;
        }

        return wp.apiFetch({
          path: '/wp/v2/tags',
          method: 'POST',
          data: { name: tagName }
        }).then(function (created) {
          return created && created.id ? created.id : 0;
        }).catch(function () {
          return 0;
        });
      })
      .catch(function () {
        return 0;
      });
  }

  function setTagsValue(value) {
    var tags = normalizeTagList(value);
    var csv = tags.join(', ');

    $('#tax-input-post_tag').val(csv).trigger('input').trigger('change');

    if (!isGutenberg() || !window.wp || !wp.data || !wp.data.dispatch) {
      return Promise.resolve(csv);
    }

    var chain = Promise.resolve([]);
    tags.forEach(function (tagName) {
      chain = chain.then(function (ids) {
        return resolveExistingTagId(tagName).then(function (id) {
          if (id) {
            ids.push(id);
          }
          return ids;
        });
      });
    });

    return chain.then(function (ids) {
      if (tags.length && !ids.length) {
        return csv;
      }

      try {
        wp.data.dispatch('core/editor').editPost({ tags: ids });
      } catch (e) {
        // keep classic hidden field value as fallback
      }
      return csv;
    });
  }

  function getCurrentValueForTarget(target) {
    if (target === 'title') {
      return getTitleValue();
    }
    if (target === 'tags') {
      return getTagsValue();
    }
    if (target === 'excerpt') {
      return getExcerptValue();
    }
    if (target === 'seo_meta') {
      return getSeoMetaValue();
    }
    return getContentValue();
  }

  function applyValueForTarget(target, value) {
    var clean = cleanResponseForTarget(target, value);

    if (target === 'title') {
      setTitleValue(clean);
      return Promise.resolve(clean);
    }

    if (target === 'tags') {
      return setTagsValue(clean);
    }

    if (target === 'excerpt') {
      setExcerptValue(clean);
      return Promise.resolve(clean);
    }

    if (target === 'seo_meta') {
      setSeoMetaValue(clean);
      return Promise.resolve(clean);
    }

    setContentValue(clean);
    return Promise.resolve(clean);
  }

  function buildContextPayload() {
    return {
      title: getTitleValue(),
      content: getContentValue(),
      tags: getTagsValue(),
      excerpt: getExcerptValue(),
      seo_meta: getSeoMetaValue()
    };
  }

  function getKeyphrasesValue() {
    return String($('#bw-editor-ai-keyphrases').val() || '').trim();
  }

  function updateTargetSpecificUI() {
    var isInternalLinks = state.target === 'internal_links';
    $('#bw-editor-ai-keyphrases-wrap').toggle(isInternalLinks);
    if (!isInternalLinks) {
      $('#bw-editor-ai-keyphrases').val('');
      state.linkSuggestions = [];
      $('#bw-editor-ai-link-results').hide().empty();
      $('#bw-editor-ai-prompt').attr('placeholder', t('prompt_placeholder', 'Describe exactly what you want to improve...'));
      return;
    }

    $('#bw-editor-ai-prompt').attr('placeholder', t('links_prompt_placeholder', 'What kind of internal links do you want (educational, conversion, cluster, etc.)?'));
  }

  function replaceFirstOccurrence(text, find, replacement) {
    var idx = String(text).indexOf(find);
    if (idx === -1) {
      return null;
    }
    return String(text).slice(0, idx) + replacement + String(text).slice(idx + String(find).length);
  }

  function escapeRegExp(value) {
    return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function replaceFirstOccurrenceInsensitive(text, find, replacementFactory) {
    var source = String(text || '');
    var needle = String(find || '').trim();
    if (!needle) {
      return null;
    }

    var regex = new RegExp(escapeRegExp(needle), 'i');
    var match = source.match(regex);
    if (!match || typeof match.index !== 'number') {
      return null;
    }

    var matchedText = String(match[0] || needle);
    var idx = match.index;
    var replacement = typeof replacementFactory === 'function'
      ? replacementFactory(matchedText)
      : String(replacementFactory || '');

    return source.slice(0, idx) + replacement + source.slice(idx + matchedText.length);
  }

  function isSkipHeadingLinksEnabled() {
    return String(widgetSettings.skip_heading_links || '1') === '1';
  }

  function buildAnchorNode(doc, url, text) {
    var link = doc.createElement('a');
    link.setAttribute('href', String(url || ''));
    link.textContent = String(text || 'Related article');
    return link;
  }

  function getAllowedTextNodeForNeedle(doc, root, needle, skipHeadingLinks) {
    var disallowedSelector = skipHeadingLinks
      ? 'a,script,style,h1,h2,h3,h4,h5,h6'
      : 'a,script,style';
    var regex = new RegExp(escapeRegExp(String(needle || '').trim()), 'i');
    var walker = doc.createTreeWalker(root, 4, null);
    var node = walker.nextNode();

    while (node) {
      var parent = node.parentElement;
      var value = String(node.nodeValue || '');

      if (parent && !parent.closest(disallowedSelector) && regex.test(value)) {
        var match = value.match(regex);
        if (match && typeof match.index === 'number') {
          return {
            node: node,
            index: match.index,
            matchedText: String(match[0] || '')
          };
        }
      }

      node = walker.nextNode();
    }

    return null;
  }

  function wrapNeedleInAllowedTextNode(html, needle, url, skipHeadingLinks) {
    if (!needle || typeof window.DOMParser === 'undefined') {
      return null;
    }

    var parser = new window.DOMParser();
    var doc = parser.parseFromString('<div id="bw-editor-ai-root"></div>', 'text/html');
    var root = doc.getElementById('bw-editor-ai-root');
    root.innerHTML = String(html || '');

    var found = getAllowedTextNodeForNeedle(doc, root, needle, !!skipHeadingLinks);
    if (!found || !found.node) {
      return null;
    }

    var source = String(found.node.nodeValue || '');
    var before = source.slice(0, found.index);
    var after = source.slice(found.index + found.matchedText.length);
    var fragment = doc.createDocumentFragment();

    if (before) {
      fragment.appendChild(doc.createTextNode(before));
    }
    fragment.appendChild(buildAnchorNode(doc, url, found.matchedText));
    if (after) {
      fragment.appendChild(doc.createTextNode(after));
    }

    found.node.parentNode.replaceChild(fragment, found.node);
    return root.innerHTML;
  }

  function insertLinkAfterNeedleInAllowedTextNode(html, needle, url, anchor, skipHeadingLinks) {
    if (!needle || typeof window.DOMParser === 'undefined') {
      return null;
    }

    var parser = new window.DOMParser();
    var doc = parser.parseFromString('<div id="bw-editor-ai-root"></div>', 'text/html');
    var root = doc.getElementById('bw-editor-ai-root');
    root.innerHTML = String(html || '');

    var found = getAllowedTextNodeForNeedle(doc, root, needle, !!skipHeadingLinks);
    if (!found || !found.node) {
      return null;
    }

    var source = String(found.node.nodeValue || '');
    var before = source.slice(0, found.index);
    var after = source.slice(found.index + found.matchedText.length);
    var fragment = doc.createDocumentFragment();

    if (before) {
      fragment.appendChild(doc.createTextNode(before));
    }
    fragment.appendChild(doc.createTextNode(found.matchedText + ' '));
    fragment.appendChild(buildAnchorNode(doc, url, anchor));
    if (after) {
      fragment.appendChild(doc.createTextNode(after));
    }

    found.node.parentNode.replaceChild(fragment, found.node);
    return root.innerHTML;
  }

  function buildInternalLinkHtml(linkUrl, anchor) {
    var safeUrl = String(linkUrl || '').replace(/"/g, '&quot;');
    var safeAnchor = escapeHtml(anchor || 'Related article');
    return '<a href="' + safeUrl + '">' + safeAnchor + '</a>';
  }

  function insertInternalLinkIntoContent(content, suggestion) {
    var original = String(content || '');
    var url = String(suggestion.url || '').trim();
    var anchor = String(suggestion.anchor || suggestion.title || '').trim();
    var insertAfter = String(suggestion.insert_after || '').trim();
    var skipHeadingLinks = isSkipHeadingLinksEnabled();

    if (!url || !anchor) {
      return { changed: false, content: original, reason: 'invalid' };
    }

    if (original.indexOf('href="' + url + '"') !== -1 || original.indexOf("href='" + url + "'") !== -1) {
      return { changed: false, content: original, reason: 'already_linked' };
    }

    var linkHtml = buildInternalLinkHtml(url, anchor);

    if (skipHeadingLinks) {
      var wrappedAnchorNode = wrapNeedleInAllowedTextNode(original, anchor, url, true);
      if (wrappedAnchorNode !== null) {
        return { changed: true, content: wrappedAnchorNode, reason: 'wrap_anchor_safe_nodes' };
      }

      if (insertAfter) {
        var shouldWrapPhraseSafe =
          insertAfter.toLowerCase().indexOf(anchor.toLowerCase()) !== -1 ||
          anchor.toLowerCase().indexOf(insertAfter.toLowerCase()) !== -1;

        if (shouldWrapPhraseSafe) {
          var wrappedPhraseNode = wrapNeedleInAllowedTextNode(original, insertAfter, url, true);
          if (wrappedPhraseNode !== null) {
            return { changed: true, content: wrappedPhraseNode, reason: 'wrap_insert_after_phrase_safe_nodes' };
          }
        } else {
          var insertedAfterPhraseNode = insertLinkAfterNeedleInAllowedTextNode(original, insertAfter, url, anchor, true);
          if (insertedAfterPhraseNode !== null) {
            return { changed: true, content: insertedAfterPhraseNode, reason: 'insert_after_phrase_safe_nodes' };
          }
        }
      }

      var appendSafeBlock = '<p>Related reading: ' + linkHtml + '</p>';
      return { changed: true, content: (original ? original + '\n\n' : '') + appendSafeBlock, reason: 'append_safe_nodes' };
    }

    // First choice: wrap the existing anchor text already present in the content.
    var wrappedAnchorInsensitive = replaceFirstOccurrenceInsensitive(original, anchor, function (matchedText) {
      return buildInternalLinkHtml(url, matchedText);
    });
    if (wrappedAnchorInsensitive !== null) {
      return { changed: true, content: wrappedAnchorInsensitive, reason: 'wrap_anchor' };
    }

    if (insertAfter) {
      var shouldWrapPhrase =
        insertAfter.toLowerCase().indexOf(anchor.toLowerCase()) !== -1 ||
        anchor.toLowerCase().indexOf(insertAfter.toLowerCase()) !== -1;

      if (shouldWrapPhrase) {
        var wrappedPhraseInsensitive = replaceFirstOccurrenceInsensitive(original, insertAfter, function (matchedText) {
          return buildInternalLinkHtml(url, matchedText);
        });
        if (wrappedPhraseInsensitive !== null) {
          return { changed: true, content: wrappedPhraseInsensitive, reason: 'wrap_insert_after_phrase' };
        }
      } else {
        var replacedByPhrase = replaceFirstOccurrenceInsensitive(original, insertAfter, function (matchedText) {
          return matchedText + ' ' + linkHtml;
        });
        if (replacedByPhrase !== null) {
          return { changed: true, content: replacedByPhrase, reason: 'insert_after_phrase' };
        }
      }
    }

    var replacedByAnchor = replaceFirstOccurrence(original, anchor, linkHtml);
    if (replacedByAnchor !== null) {
      return { changed: true, content: replacedByAnchor, reason: 'replace_anchor' };
    }

    var appendBlock = '<p>Related reading: ' + linkHtml + '</p>';
    return { changed: true, content: (original ? original + '\n\n' : '') + appendBlock, reason: 'append' };
  }

  function clearLinkResults() {
    state.linkSuggestions = [];
    $('#bw-editor-ai-link-results').hide().empty();
  }

  function renderLinkResults() {
    var $box = $('#bw-editor-ai-link-results');
    var list = Array.isArray(state.linkSuggestions) ? state.linkSuggestions : [];

    if (!list.length) {
      $box.hide().empty();
      return;
    }

    var html = '<div class="bw-editor-ai-links-title">' + escapeHtml(t('links_title', 'Suggested internal links')) + '</div>';

    list.forEach(function (item, index) {
      var title = escapeHtml(item.title || item.anchor || 'Related article');
      var anchor = escapeHtml(item.anchor || item.title || 'Related article');
      var reason = escapeHtml(item.reason || '');
      var url = String(item.url || '').trim();
      var btnLabel = item.inserted ? t('inserted_link', 'Inserted') : t('insert_link', 'Insert');

      html += '' +
        '<div class="bw-editor-ai-link-item' + (item.inserted ? ' is-inserted' : '') + '">' +
        '  <div class="bw-editor-ai-link-main">' +
        '    <a class="bw-editor-ai-link-url" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + title + '</a>' +
        '    <div class="bw-editor-ai-link-anchor">Anchor: ' + anchor + '</div>' +
        (reason ? '    <div class="bw-editor-ai-link-reason">' + reason + '</div>' : '') +
        '  </div>' +
        '  <button type="button" class="bw-editor-ai-link-insert" data-index="' + index + '"' + (item.inserted ? ' disabled' : '') + '>' + escapeHtml(btnLabel) + '</button>' +
        '</div>';
    });

    $box.html(html).show();
  }

  function applyInternalLinkSuggestion(index) {
    var idx = parseInt(index, 10);
    if (!Number.isFinite(idx) || idx < 0 || idx >= state.linkSuggestions.length) {
      return;
    }

    var suggestion = state.linkSuggestions[idx];
    if (!suggestion || suggestion.inserted) {
      return;
    }

    var previousContent = getContentValue();
    var result = insertInternalLinkIntoContent(previousContent, suggestion);

    if (!result.changed) {
      if (result.reason === 'already_linked') {
        setMessage(t('link_already_exists', 'This URL is already linked in the content.'), 'info');
      } else {
        setMessage(t('generic_error', 'Could not generate a response. Please try again.'), 'error');
      }
      return;
    }

    setContentValue(result.content);
    suggestion.inserted = true;
    state.linkSuggestions[idx] = suggestion;
    renderLinkResults();

    state.pendingChange = {
      target: 'text',
      previousValue: previousContent,
      appliedValue: result.content
    };

    showConfirmationBar();
    setMessage(t('link_inserted', 'Internal link inserted. Review and choose Keep or Undo.'), 'success');
  }

  function clampWidgetToViewport() {
    var $widget = $('#bw-editor-ai-widget');
    if (!$widget.length || state.drag.active) {
      return;
    }
    var gutter = 8;
    var panelW = 360;
    var elem = $widget[0];
    var s = elem.style;
    // When positioned by left (after drag), ensure panel doesn't overflow right
    if (s.left && s.left !== 'auto') {
      var leftVal = parseFloat(s.left) || 0;
      var maxLeft = Math.max(window.innerWidth - panelW - gutter, gutter);
      if (leftVal > maxLeft) {
        $widget.css('left', maxLeft + 'px');
      }
      return;
    }
    // When positioned by right, ensure value is sane
    if (s.right && s.right !== 'auto') {
      var rightVal = parseFloat(s.right) || 0;
      var minRight = gutter;
      var maxRight = Math.max(window.innerWidth - panelW - gutter, gutter);
      var clamped = Math.min(Math.max(rightVal, minRight), maxRight);
      if (clamped !== rightVal) {
        $widget.css('right', clamped + 'px');
      }
    }
  }

  function ensureWidget() {
    if ($('#bw-editor-ai-widget').length) {
      return;
    }

    var robot = escapeHtml(cfg.robot_image || '');
    var robotFace = escapeHtml(cfg.robot_face_image || cfg.robot_image || '');
    var seoTabHtml = seoModuleEnabled
      ? '        <button type="button" class="bw-editor-ai-tab" data-tab="seo">' + escapeHtml(t('tab_seo', 'SEO')) + '</button>'
      : '';
    var seoViewHtml = seoModuleEnabled
      ? '    <div class="bw-editor-ai-view bw-editor-ai-view-seo" id="bw-editor-ai-view-seo">' +
      '      <p class="bw-editor-ai-intro">' + escapeHtml(t('seo_intro', 'Review the current post SEO checks.')) + '</p>' +
      '      <div class="bw-editor-ai-seo-subtabs" id="bw-editor-ai-seo-subtabs">' +
      '        <button type="button" class="bw-editor-ai-seo-subtab is-active" data-mode="seo">' + escapeHtml(t('seo_subtab_analysis', 'SEO analysis')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-seo-subtab" data-mode="readability">' + escapeHtml(t('seo_subtab_readability', 'Readability')) + '</button>' +
      '      </div>' +
      '      <div class="bw-editor-ai-seo-loader" id="bw-editor-ai-seo-loader" style="display:none;">' +
      '        <span class="bw-editor-ai-dot"></span>' +
      '        <span class="bw-editor-ai-dot"></span>' +
      '        <span class="bw-editor-ai-dot"></span>' +
      '        <span class="bw-editor-ai-loader-text">' + escapeHtml(t('seo_loading', 'Loading SEO report...')) + '</span>' +
      '      </div>' +
      '      <div class="bw-editor-ai-seo-message" id="bw-editor-ai-seo-message"></div>' +
      '      <div class="bw-editor-ai-seo-pane is-active" data-mode="seo" id="bw-editor-ai-seo-pane-seo"></div>' +
      '      <div class="bw-editor-ai-seo-pane" data-mode="readability" id="bw-editor-ai-seo-pane-readability"></div>' +
      '    </div>'
      : '';

    var html = '' +
      '<div id="bw-editor-ai-widget" class="bw-editor-ai-widget is-minimized">' +
      '  <button type="button" class="bw-editor-ai-toggle" id="bw-editor-ai-toggle" aria-label="Open BotWriter assistant">' +
      '    <img src="' + robot + '" alt="BotWriter" />' +
      '  </button>' +
      '  <div class="bw-editor-ai-panel" id="bw-editor-ai-panel" aria-hidden="true">' +
      '    <div class="bw-editor-ai-header">' +
      '      <div class="bw-editor-ai-header-main">' +
      '        <img class="bw-editor-ai-header-robot" src="' + robotFace + '" alt="BotWriter" />' +
      '        <h3>' + escapeHtml(t('widget_title', 'BotWriter Copilot')) + '</h3>' +
      '      </div>' +
      '      <button type="button" class="bw-editor-ai-close" id="bw-editor-ai-close" aria-label="Minimize">x</button>' +
      '      <div class="bw-editor-ai-tabs" id="bw-editor-ai-tabs">' +
      '        <button type="button" class="bw-editor-ai-tab is-active" data-tab="prompt">' + escapeHtml(t('tab_prompt', 'Prompt')) + '</button>' +
      seoTabHtml +
      '      </div>' +
      '    </div>' +
      '    <div class="bw-editor-ai-view bw-editor-ai-view-prompt is-active" id="bw-editor-ai-view-prompt">' +
      '      <p class="bw-editor-ai-intro">' + escapeHtml(t('intro', 'Select what to update')) + '</p>' +
      '      <div class="bw-editor-ai-targets" id="bw-editor-ai-targets">' +
      '        <button type="button" class="bw-editor-ai-target is-active" data-target="text">' + escapeHtml(t('target_text', 'Text')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-target" data-target="title">' + escapeHtml(t('target_title', 'Title')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-target" data-target="tags">' + escapeHtml(t('target_tags', 'Tags')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-target" data-target="excerpt">' + escapeHtml(t('target_excerpt', 'Excerpt')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-target" data-target="seo_meta">' + escapeHtml(t('target_seo_meta', 'SEO Meta')) + '</button>' +
      '        <button type="button" class="bw-editor-ai-target" data-target="internal_links">' + escapeHtml(t('target_internal_links', 'Internal Links')) + '</button>' +
      '      </div>' +
      '      <div class="bw-editor-ai-keyphrases" id="bw-editor-ai-keyphrases-wrap" style="display:none;">' +
      '        <label for="bw-editor-ai-keyphrases">' + escapeHtml(t('keyphrases_label', 'Keyphrases (up to 5, comma-separated)')) + '</label>' +
      '        <input type="text" id="bw-editor-ai-keyphrases" placeholder="' + escapeHtml(t('keyphrases_placeholder', 'e.g. internal linking, seo writing, topic clusters')) + '" />' +
      '      </div>' +
      '      <div class="bw-editor-ai-suggestions-wrap">' +
      '        <div class="bw-editor-ai-suggestions-title">' + escapeHtml(t('suggestions_title', 'Suggestions')) + '</div>' +
      '        <div class="bw-editor-ai-suggestions" id="bw-editor-ai-suggestions"></div>' +
      '      </div>' +
      '      <div class="bw-editor-ai-prompt-wrap">' +
      '        <textarea id="bw-editor-ai-prompt" placeholder="' + escapeHtml(t('prompt_placeholder', 'Describe exactly what you want to improve...')) + '"></textarea>' +
      '        <button type="button" class="bw-editor-ai-send" id="bw-editor-ai-send" aria-label="Send">' +
      '          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12L21 3L14 21L11 13L3 12Z"></path></svg>' +
      '        </button>' +
      '      </div>' +
      '      <div class="bw-editor-ai-feedback">' +
      '        <div class="bw-editor-ai-loader" id="bw-editor-ai-loader" style="display:none;">' +
      '          <span class="bw-editor-ai-dot"></span>' +
      '          <span class="bw-editor-ai-dot"></span>' +
      '          <span class="bw-editor-ai-dot"></span>' +
      '          <span class="bw-editor-ai-loader-text">' + escapeHtml(t('sending', 'Thinking')) + '</span>' +
      '        </div>' +
      '        <div class="bw-editor-ai-message" id="bw-editor-ai-message"></div>' +
      '        <div id="bw-editor-ai-confirm" class="bw-editor-ai-confirm" style="display:none;">' +
      '          <span class="bw-editor-ai-confirm-text">' + escapeHtml(t('confirm_label', 'Apply this AI change?')) + '</span>' +
      '          <button type="button" class="bw-editor-ai-confirm-btn is-keep" data-action="keep">' + escapeHtml(t('keep', 'Keep')) + '</button>' +
      '          <button type="button" class="bw-editor-ai-confirm-btn is-undo" data-action="undo">' + escapeHtml(t('undo', 'Undo')) + '</button>' +
      '        </div>' +
      '        <div class="bw-editor-ai-link-results" id="bw-editor-ai-link-results" style="display:none;"></div>' +
      '      </div>' +
      '    </div>' +
      seoViewHtml +
      '  </div>' +
      '</div>';

    $('body').append(html);
    renderSuggestions();
    positionWidgetInEditorArea();
  }

  function setWidgetOpen(open) {
    var $widget = $('#bw-editor-ai-widget');
    $widget.toggleClass('is-open', !!open);
    $widget.toggleClass('is-minimized', !open);
    $('#bw-editor-ai-panel').attr('aria-hidden', open ? 'false' : 'true');
    if (!open) {
      positionWidgetInEditorArea();
    } else {
      clampWidgetToViewport();
    }
  }

  function setMainTab(tab) {
    var isSeo = (tab === 'seo' && seoModuleEnabled);
    state.activeTab = isSeo ? 'seo' : 'prompt';

    $('#bw-editor-ai-tabs .bw-editor-ai-tab').removeClass('is-active');
    $('#bw-editor-ai-tabs .bw-editor-ai-tab[data-tab="' + state.activeTab + '"]').addClass('is-active');

    $('#bw-editor-ai-view-prompt').toggleClass('is-active', !isSeo);
    $('#bw-editor-ai-view-seo').toggleClass('is-active', isSeo);

    if (isSeo) {
      loadSeoReport();
    }
  }

  function setSeoSubtab(mode) {
    var nextMode = mode === 'readability' ? 'readability' : 'seo';
    state.seoSubtab = nextMode;

    $('#bw-editor-ai-seo-subtabs .bw-editor-ai-seo-subtab').removeClass('is-active');
    $('#bw-editor-ai-seo-subtabs .bw-editor-ai-seo-subtab[data-mode="' + nextMode + '"]').addClass('is-active');

    $('#bw-editor-ai-view-seo .bw-editor-ai-seo-pane').removeClass('is-active');
    $('#bw-editor-ai-view-seo .bw-editor-ai-seo-pane[data-mode="' + nextMode + '"]').addClass('is-active');
  }

  function setSeoMessage(message, type) {
    var $msg = $('#bw-editor-ai-seo-message');
    $msg.removeClass('is-error is-success is-info');
    if (type) {
      $msg.addClass('is-' + type);
    }
    $msg.text(message || '');
  }

  function setSeoLoading(loading) {
    state.seoLoading = !!loading;
    $('#bw-editor-ai-seo-loader').toggle(!!loading);
  }

  function loadSeoReport() {
    if (state.seoLoading) {
      return;
    }

    var postId = getPostId();
    if (!postId) {
      setSeoMessage(t('seo_missing_post', 'Save the post first to view SEO reports.'), 'info');
      return;
    }

    if (state.seoLoadedForPost === postId && (state.seoReports.seo || state.seoReports.readability)) {
      return;
    }

    setSeoMessage('', '');
    setSeoLoading(true);

    $.ajax({
      url: cfg.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botwriter_editor_ai_get_seo_report',
        nonce: cfg.nonce,
        post_id: postId
      }
    }).done(function (resp) {
      if (!resp || !resp.success) {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : t('seo_error', 'Could not load SEO report.');
        setSeoMessage(msg, 'error');
        return;
      }

      var data = resp.data || {};
      state.seoReports.seo = String(data.seo_html || '');
      state.seoReports.readability = String(data.readability_html || '');
      state.seoLoadedForPost = postId;

      $('#bw-editor-ai-seo-pane-seo').html(state.seoReports.seo || '<div class="bw-editor-ai-empty">' + escapeHtml(t('seo_empty', 'No SEO checks available.')) + '</div>');
      $('#bw-editor-ai-seo-pane-readability').html(state.seoReports.readability || '<div class="bw-editor-ai-empty">' + escapeHtml(t('seo_empty', 'No SEO checks available.')) + '</div>');
      setSeoSubtab(state.seoSubtab || 'seo');
      setSeoMessage('', '');
    }).fail(function (xhr) {
      var msg = t('seo_error', 'Could not load SEO report.');
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        msg = xhr.responseJSON.data.message;
      }
      setSeoMessage(msg, 'error');
    }).always(function () {
      setSeoLoading(false);
    });
  }

  function setMessage(message, type) {
    var $msg = $('#bw-editor-ai-message');
    $msg.removeClass('is-error is-success is-info');
    if (type) {
      $msg.addClass('is-' + type);
    }
    $msg.text(message || '');
  }

  function setLoading(isLoading) {
    state.loading = !!isLoading;
    $('#bw-editor-ai-loader').toggle(!!isLoading);
    $('#bw-editor-ai-send').prop('disabled', !!isLoading);
    $('#bw-editor-ai-targets .bw-editor-ai-target').prop('disabled', !!isLoading);
    $('#bw-editor-ai-prompt').prop('disabled', !!isLoading);
  }

  function renderSuggestions() {
    var list = suggestionsByTarget[state.target] || [];
    var html = '';

    list.forEach(function (item) {
      var isActive = state.selectedSuggestion && state.selectedSuggestion === item;
      html += '<button type="button" class="bw-editor-ai-suggestion' + (isActive ? ' is-active' : '') + '" data-suggestion="' + escapeHtml(item) + '">' + escapeHtml(item) + '</button>';
    });

    $('#bw-editor-ai-suggestions').html(html);
  }

  function setTarget(target) {
    if (targets.indexOf(target) === -1) {
      return;
    }

    if (state.target !== target) {
      clearLinkResults();
      clearConfirmationBar();
    }

    state.target = target;
    state.selectedSuggestion = '';

    $('#bw-editor-ai-targets .bw-editor-ai-target').removeClass('is-active');
    $('#bw-editor-ai-targets .bw-editor-ai-target[data-target="' + target + '"]').addClass('is-active');

    renderSuggestions();
    updateTargetSpecificUI();
  }

  function showConfirmationBar() {
    if (!state.pendingChange) {
      $('#bw-editor-ai-confirm').hide();
      return;
    }
    $('#bw-editor-ai-confirm').show();
  }

  function clearConfirmationBar() {
    state.pendingChange = null;
    $('#bw-editor-ai-confirm').hide();
  }

  function generateFromAssistant() {
    if (state.loading) {
      return;
    }

    var prompt = $.trim($('#bw-editor-ai-prompt').val() || '');
    if (!prompt) {
      setMessage(t('missing_prompt', 'Write a prompt or choose a suggestion first.'), 'error');
      return;
    }

    var context = buildContextPayload();
    var postId = getPostId();
    var keyphrases = getKeyphrasesValue();

    setMessage('', '');
    setLoading(true);

    $.ajax({
      url: cfg.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botwriter_editor_ai_generate',
        nonce: cfg.nonce,
        post_id: postId,
        target: state.target,
        prompt: prompt,
        context_title: context.title,
        context_content: context.content,
        context_tags: context.tags,
        context_excerpt: context.excerpt,
        context_seo_meta: context.seo_meta,
        context_keyphrases: keyphrases
      }
    }).done(function (resp) {
      if (!resp || !resp.success) {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : t('generic_error', 'Could not generate a response. Please try again.');
        setMessage(msg, 'error');
        return;
      }

      var data = resp.data || {};
      var target = data.target || state.target;

      if (target === 'internal_links') {
        state.linkSuggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
        renderLinkResults();
        clearConfirmationBar();

        var strategyMessage = '';
        if (data.strategy === 'no_ai') {
          strategyMessage = t('links_mode_noai', 'Deterministic mode: suggestions ranked using taxonomy and keyword overlap (no AI call).');
        } else if (data.strategy === 'ai') {
          strategyMessage = t('links_mode_ai', 'AI mode: suggestions ranked by semantic relevance and anchor fit.');
        }

        if (!state.linkSuggestions.length) {
          setMessage(t('links_empty', 'No relevant internal links were found yet.'), 'info');
          return;
        }

        var readyText = t('links_ready', 'Suggestions are ready. Insert the links you want, then Keep or Undo.');
        setMessage((strategyMessage ? strategyMessage + ' ' : '') + readyText, 'success');
        return;
      }

      var content = cleanResponseForTarget(target, data.content || '');

      if (!content) {
        setMessage(t('empty_response', 'The assistant returned an empty response.'), 'error');
        return;
      }

      var previousValue = getCurrentValueForTarget(target);
      var previousComparable = normalizeForCompare(previousValue);

      applyValueForTarget(target, content)
        .then(function (appliedValue) {
          var appliedComparable = normalizeForCompare(appliedValue);
          if (appliedComparable === previousComparable) {
            clearConfirmationBar();
            setMessage(t('same_response_notice', 'AI returned the same text. No changes were applied.'), 'info');
            return;
          }

          state.pendingChange = {
            target: target,
            previousValue: previousValue,
            appliedValue: appliedValue
          };

          showConfirmationBar();
          setMessage(t('updated_notice', 'Updated. Review and choose Keep or Undo.'), 'success');
        })
        .catch(function () {
          setMessage(t('generic_error', 'Could not generate a response. Please try again.'), 'error');
        });
    }).fail(function (xhr) {
      var msg = t('generic_error', 'Could not generate a response. Please try again.');
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        msg = xhr.responseJSON.data.message;
      }
      setMessage(msg, 'error');
    }).always(function () {
      setLoading(false);
    });
  }

  function undoPendingChange() {
    if (!state.pendingChange) {
      return;
    }

    var pending = state.pendingChange;

    applyValueForTarget(pending.target, pending.previousValue)
      .then(function () {
        setMessage(t('reverted_notice', 'Change reverted.'), 'info');
      })
      .catch(function () {
        setMessage(t('generic_error', 'Could not generate a response. Please try again.'), 'error');
      })
      .then(function () {
        clearConfirmationBar();
      });
  }

  function keepPendingChange() {
    if (!state.pendingChange) {
      return;
    }
    clearConfirmationBar();
    setMessage(t('kept_notice', 'Change kept. Save or update the post when ready.'), 'info');
  }

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function startDrag(clientX, clientY, source) {
    var $widget = $('#bw-editor-ai-widget');
    var rect = $widget[0].getBoundingClientRect();

    if (!$widget[0].style.left || !$widget[0].style.top) {
      $widget.css({
        left: rect.left + 'px',
        top: rect.top + 'px',
        right: 'auto',
        bottom: 'auto'
      });
    }

    state.drag.active = true;
    state.drag.source = source || 'header';
    state.drag.startX = clientX;
    state.drag.startY = clientY;
    state.drag.offsetX = clientX - rect.left;
    state.drag.offsetY = clientY - rect.top;
    state.drag.moved = false;

    $widget.addClass('is-dragging');
    $('body').addClass('bw-editor-ai-no-select');
  }

  function dragTo(clientX, clientY) {
    if (!state.drag.active) {
      return;
    }

    var $widget = $('#bw-editor-ai-widget');
    var rect = $widget[0].getBoundingClientRect();
    var maxLeft = Math.max(0, window.innerWidth - rect.width);
    var maxTop = Math.max(0, window.innerHeight - rect.height);

    var nextLeft = clamp(clientX - state.drag.offsetX, 0, maxLeft);
    var nextTop = clamp(clientY - state.drag.offsetY, 0, maxTop);

    if (!state.drag.moved) {
      var distance = Math.abs(clientX - state.drag.startX) + Math.abs(clientY - state.drag.startY);
      if (distance > 4) {
        state.drag.moved = true;
      }
    }

    $widget.css({
      left: nextLeft + 'px',
      top: nextTop + 'px',
      right: 'auto',
      bottom: 'auto'
    });
  }

  function stopDrag() {
    if (!state.drag.active) {
      return;
    }

    if (state.drag.moved) {
      state.drag.userPositioned = true;
    }

    if (state.drag.source === 'bubble' && state.drag.moved) {
      state.drag.suppressClick = true;
      setTimeout(function () {
        state.drag.suppressClick = false;
      }, 120);
    }

    state.drag.active = false;
    state.drag.source = '';
    $('#bw-editor-ai-widget').removeClass('is-dragging');
    $('body').removeClass('bw-editor-ai-no-select');
  }

  $(document).on('click', '#bw-editor-ai-toggle', function () {
    if (state.drag.suppressClick) {
      return;
    }
    setWidgetOpen(true);
  });

  $(document).on('click', '#bw-editor-ai-close', function () {
    setWidgetOpen(false);
  });

  $(document).on('click', '#bw-editor-ai-targets .bw-editor-ai-target', function () {
    setTarget($(this).data('target'));
  });

  $(document).on('click', '#bw-editor-ai-tabs .bw-editor-ai-tab', function () {
    var tab = String($(this).data('tab') || 'prompt');
    setMainTab(tab);
  });

  $(document).on('click', '#bw-editor-ai-seo-subtabs .bw-editor-ai-seo-subtab', function () {
    var mode = String($(this).data('mode') || 'seo');
    setSeoSubtab(mode);
  });

  $(document).on('click', '.bw-editor-ai-suggestion', function () {
    var suggestion = $(this).attr('data-suggestion') || '';
    state.selectedSuggestion = suggestion;
    renderSuggestions();
    $('#bw-editor-ai-prompt').val(suggestion).trigger('input').focus();
  });

  $(document).on('input', '#bw-editor-ai-prompt', function () {
    var typed = String($(this).val() || '').trim();
    if (!typed || typed !== state.selectedSuggestion) {
      state.selectedSuggestion = '';
      renderSuggestions();
    }
  });

  $(document).on('click', '#bw-editor-ai-send', function () {
    generateFromAssistant();
  });

  $(document).on('keydown', '#bw-editor-ai-prompt', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      generateFromAssistant();
    }
  });

  $(document).on('click', '#bw-editor-ai-confirm .bw-editor-ai-confirm-btn', function () {
    var action = $(this).data('action');
    if (action === 'undo') {
      undoPendingChange();
      return;
    }
    keepPendingChange();
  });

  $(document).on('click', '#bw-editor-ai-link-results .bw-editor-ai-link-insert', function () {
    var index = $(this).attr('data-index');
    applyInternalLinkSuggestion(index);
  });

  $(document).on('mousedown', '#bw-editor-ai-widget .bw-editor-ai-header', function (e) {
    if ($(e.target).closest('.bw-editor-ai-close').length || $(e.target).closest('.bw-editor-ai-tabs').length) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    startDrag(e.clientX, e.clientY, 'header');
  });

  $(document).on('mousedown', '#bw-editor-ai-widget.is-minimized #bw-editor-ai-toggle', function (e) {
    e.preventDefault();
    e.stopPropagation();
    startDrag(e.clientX, e.clientY, 'bubble');
  });

  $(document).on('mousemove', function (e) {
    if (state.drag.active) {
      e.preventDefault();
      e.stopPropagation();
    }
    dragTo(e.clientX, e.clientY);
  });

  $(document).on('mouseup', function () {
    stopDrag();
  });

  $(document).on('touchstart', '#bw-editor-ai-widget .bw-editor-ai-header', function (e) {
    if ($(e.target).closest('.bw-editor-ai-close').length || $(e.target).closest('.bw-editor-ai-tabs').length) {
      return;
    }

    if (!e.originalEvent || !e.originalEvent.touches || !e.originalEvent.touches[0]) {
      return;
    }

    var touch = e.originalEvent.touches[0];
    e.preventDefault();
    e.stopPropagation();
    startDrag(touch.clientX, touch.clientY, 'header');
  });

  $(document).on('touchstart', '#bw-editor-ai-widget.is-minimized #bw-editor-ai-toggle', function (e) {
    if (!e.originalEvent || !e.originalEvent.touches || !e.originalEvent.touches[0]) {
      return;
    }

    var touch = e.originalEvent.touches[0];
    e.preventDefault();
    e.stopPropagation();
    startDrag(touch.clientX, touch.clientY, 'bubble');
  });

  $(document).on('touchmove', function (e) {
    if (!state.drag.active || !e.originalEvent || !e.originalEvent.touches || !e.originalEvent.touches[0]) {
      return;
    }

    var touch = e.originalEvent.touches[0];
    dragTo(touch.clientX, touch.clientY);
    e.preventDefault();
    e.stopPropagation();
  });

  $(document).on('touchend touchcancel', function () {
    stopDrag();
  });

  $(document).on('dragstart', '#bw-editor-ai-widget, #bw-editor-ai-widget *', function (e) {
    e.preventDefault();
    return false;
  });

  var bwPrevVW = window.innerWidth;
  var bwPrevVH = window.innerHeight;

  $(window).on('resize', function () {
    var dw = Math.abs(window.innerWidth - bwPrevVW);
    var dh = Math.abs(window.innerHeight - bwPrevVH);
    if (dw > 80 || dh > 80) {
      state.drag.userPositioned = false;
      bwPrevVW = window.innerWidth;
      bwPrevVH = window.innerHeight;
    }
    positionWidgetInEditorArea();
  });

  $(function () {
    ensureWidget();
    setTarget('text');
    setMainTab('prompt');
    setSeoSubtab('seo');
    setWidgetOpen(false);
    // Retry positioning once Gutenberg finishes rendering
    [400, 900, 2000, 4000].forEach(function (delay) {
      setTimeout(function () {
        if (!state.drag.userPositioned) {
          positionWidgetInEditorArea();
        }
      }, delay);
    });
  });
})(jQuery);
