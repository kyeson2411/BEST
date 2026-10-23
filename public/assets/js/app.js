/* Sibol progressive enhancement. Every feature here has a working no-JS fallback. */
(function () {
  'use strict';

  var doc = document;
  var csrf = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';
  window.Sibol = window.Sibol || {};
  window.Sibol.csrf = csrf;

  function $(sel, root) { return (root || doc).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

  /* Interface text ---------------------------------------------------------------
     Scripts carry no English of their own. The server translates their text (Services/ScriptStrings)
     and passes it in data-i18n attributes: the layout's on <body>, a page script's on its root element.
     A [singular, plural] pair is chosen by params.count, as tp() does on the server. */
  function readStrings(el) {
    var raw = el && el.getAttribute('data-i18n');
    if (!raw) return {};
    try { return JSON.parse(raw) || {}; } catch (e) { return {}; }
  }
  function has(map, key) { return Object.prototype.hasOwnProperty.call(map, key); }
  function translator(own, fallback) {
    return function (key, params) {
      var text = has(own, key) ? own[key] : (fallback && has(fallback, key) ? fallback[key] : key);
      if (Array.isArray(text)) text = params && params.count === 1 ? text[0] : text[1];
      text = String(text);
      if (params) {
        // Longest names first so :count does not clobber :count_total.
        Object.keys(params).sort(function (a, b) { return b.length - a.length; }).forEach(function (name) {
          text = text.split(':' + name).join(String(params[name]));
        });
      }
      return text;
    };
  }
  var common = readStrings(doc.body);
  var S = translator(common);
  window.Sibol.strings = function (el) { return translator(readStrings(el), common); };

  /* Toasts -------------------------------------------------------------------- */
  function dismissToast(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    setTimeout(function () { toast.remove(); }, 220);
  }
  function armToast(toast) {
    var close = $('[data-dismiss]', toast);
    if (close) close.addEventListener('click', function () { dismissToast(toast); });
    if (toast.getAttribute('data-persist') !== '1') {
      setTimeout(function () { dismissToast(toast); }, 6500);
    }
  }
  $all('[data-toast]').forEach(armToast);

  window.Sibol.toast = function (message, type) {
    var region = $('#toasts');
    if (!region) return;
    type = type || 'success';
    var toast = doc.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.setAttribute('data-toast', '');
    toast.setAttribute('data-persist', type === 'error' ? '1' : '0');
    var text = doc.createElement('p');
    text.className = 'toast__text';
    text.textContent = message;
    var btn = doc.createElement('button');
    btn.type = 'button';
    btn.className = 'toast__close';
    btn.setAttribute('data-dismiss', '');
    btn.setAttribute('aria-label', S('dismiss'));
    btn.textContent = '×';
    toast.appendChild(text);
    toast.appendChild(btn);
    region.appendChild(toast);
    armToast(toast);
  };

  /* Confirmation dialogs for destructive or irreversible actions ------------------ */
  var dialog = $('#confirm-dialog');
  function confirmAction(opts, onConfirm) {
    if (!dialog || typeof dialog.showModal !== 'function') {
      if (window.confirm(opts.text || opts.title)) onConfirm();
      return;
    }
    $('#confirm-title', dialog).textContent = opts.title || S('confirm_title');
    $('#confirm-text', dialog).textContent = opts.text || '';
    var ok = $('[data-confirm-ok]', dialog);
    ok.textContent = opts.action || S('confirm_action');
    ok.className = opts.tone === 'danger' ? 'btn btn--danger' : 'btn btn--primary';
    dialog.returnValue = '';
    dialog.showModal();
    $('[data-confirm-cancel]', dialog).focus();
    dialog.addEventListener('close', function handler() {
      dialog.removeEventListener('close', handler);
      if (dialog.returnValue === 'confirm') onConfirm();
    });
  }
  window.Sibol.confirm = confirmAction;

  doc.addEventListener('submit', function (event) {
    var form = event.target;
    // A page script (quiz.js) already took care of this submission.
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    var submitter = event.submitter || null;
    // A button can carry its own confirmation (e.g. "Submit" next to "Save draft").
    var source = submitter && submitter.hasAttribute('data-confirm') ? submitter : (form.hasAttribute('data-confirm') ? form : null);
    if (source && !form.__confirmed) {
      event.preventDefault();
      confirmAction({
        title: source.getAttribute('data-confirm-title') || source.getAttribute('data-confirm'),
        text: source.getAttribute('data-confirm-title') ? source.getAttribute('data-confirm') : '',
        action: source.getAttribute('data-confirm-action'),
        tone: source.getAttribute('data-confirm-tone')
      }, function () {
        form.__confirmed = true;
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitter || undefined);
        } else {
          if (submitter && submitter.formAction) form.action = submitter.formAction;
          if (submitter && submitter.name) {
            var hidden = doc.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value;
            form.appendChild(hidden);
          }
          markBusy(form, submitter);
          form.submit();
        }
      });
      return;
    }
    form.__confirmed = false;
    markBusy(form, submitter);
  });

  /* Busy state on submit: prevents double submission and says what is happening. */
  function markBusy(form, submitter) {
    // Only forms that post to the server; a dialog's own form (method="dialog") just closes it.
    if (form.hasAttribute('data-no-busy') || form.method.toLowerCase() !== 'post') return;
    var btn = submitter || $('button[type="submit"], button:not([type])', form);
    if (!btn || btn.classList.contains('is-busy')) return;
    var label = btn.getAttribute('data-busy') || S('busy');
    setTimeout(function () {
      btn.classList.add('is-busy');
      btn.setAttribute('aria-disabled', 'true');
      btn.dataset.label = btn.innerHTML;
      btn.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>' + escapeHtml(label) + '</span>';
    }, 0);
    // Restore if the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', function () {
      if (btn.dataset.label) { btn.innerHTML = btn.dataset.label; btn.classList.remove('is-busy'); btn.removeAttribute('aria-disabled'); }
    }, { once: true });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  window.Sibol.escapeHtml = escapeHtml;

  /* Menus (<details>) close on outside click and Escape --------------------------- */
  var menus = $all('details[data-menu]');
  doc.addEventListener('click', function (event) {
    menus.forEach(function (menu) {
      if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
  });
  doc.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    menus.forEach(function (menu) {
      if (menu.open) { menu.open = false; $('summary', menu).focus(); }
    });
    closeDrawer();
  });
  menus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (!menu.open) return;
      menus.forEach(function (other) { if (other !== menu) other.open = false; });
      if (menu.hasAttribute('data-notifications')) loadNotifications(menu);
    });
  });

  function loadNotifications(menu) {
    var panel = $('[data-panel-src]', menu);
    if (!panel || panel.__loaded) return;
    panel.__loaded = true;
    fetch(panel.getAttribute('data-panel-src'), { headers: { 'X-Requested-With': 'fetch-html' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.text(); })
      .then(function (html) { panel.innerHTML = html; })
      .catch(function () {
        var list = $('.notif-panel__list', panel);
        if (list) {
          list.removeAttribute('aria-busy');
          var note = doc.createElement('p');
          note.className = 'notif';
          note.textContent = S('notifications_failed');
          list.innerHTML = '';
          list.appendChild(note);
        }
        panel.__loaded = false;
      });
  }

  /* Live notification badge: the unread count is fetched every 90 seconds while the tab is visible
     (and on returning to it). The response is the count only, and the request is marked as background
     work so it never keeps an unattended session alive. */
  var bell = $('details[data-notifications][data-count-src]');
  if (bell) {
    var bellButton = $('summary', bell);
    var unread = parseInt(bell.getAttribute('data-unread'), 10) || 0;
    var lastPoll = Date.now();
    var polling = true;
    var showUnread = function (n) {
      var dot = $('.icon-btn__dot', bellButton);
      if (n > 0 && !dot) {
        dot = doc.createElement('span');
        dot.className = 'icon-btn__dot';
        dot.setAttribute('aria-hidden', 'true');
        bellButton.appendChild(dot);
      }
      if (dot && n > 0) dot.textContent = n > 99 ? '99+' : String(n);
      if (dot && n <= 0) dot.remove();
      bellButton.setAttribute('aria-label', n > 0 ? S('notifications_unread', { count: n }) : S('notifications'));
    };
    var pollUnread = function () {
      if (!polling || doc.visibilityState !== 'visible') return;
      lastPoll = Date.now();
      fetch(bell.getAttribute('data-count-src'), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-Sibol-Poll': '1' }
      }).then(function (r) {
        if (!r.ok) { polling = false; throw new Error(String(r.status)); } // signed out: stop asking
        return r.json();
      }).then(function (j) {
        var n = parseInt(j.count, 10) || 0;
        if (n === unread) return;
        unread = n;
        showUnread(n);
        // The popover list is out of date: load it again the next time it opens.
        var panel = $('[data-panel-src]', bell);
        if (panel && !bell.open) panel.__loaded = false;
      }).catch(function () { /* offline or signed out: try again later */ });
    };
    setInterval(pollUnread, 90000);
    doc.addEventListener('visibilitychange', function () {
      if (doc.visibilityState === 'visible' && Date.now() - lastPoll >= 60000) pollUnread();
    });
  }

  /* Mobile navigation drawer --------------------------------------------------- */
  var spine = $('#spine');
  var scrim = null;
  function openDrawer() {
    if (!spine) return;
    spine.classList.add('is-open');
    scrim = doc.createElement('div');
    scrim.className = 'scrim';
    scrim.addEventListener('click', closeDrawer);
    doc.body.appendChild(scrim);
    $all('[data-drawer-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    var first = $('a', spine);
    if (first) first.focus();
  }
  function closeDrawer() {
    if (!spine || !spine.classList.contains('is-open')) return;
    spine.classList.remove('is-open');
    if (scrim) { scrim.remove(); scrim = null; }
    $all('[data-drawer-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }
  $all('[data-drawer-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function (event) {
      if (window.matchMedia('(max-width: 759px)').matches) {
        event.preventDefault();
        if (spine.classList.contains('is-open')) closeDrawer(); else openDrawer();
      }
    });
  });

  /* Mobile search toggle */
  var searchToggle = $('[data-search-toggle]');
  if (searchToggle) {
    searchToggle.addEventListener('click', function () {
      var form = $('#global-search');
      var open = form.classList.toggle('is-open');
      searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) $('input', form).focus();
    });
  }

  /* Filters that apply on change (the form still has a submit button without JS). */
  $all('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form) el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit(); });
  });

  /* Selects whose options are page addresses: [data-goto] */
  $all('select[data-goto]').forEach(function (sel) {
    sel.addEventListener('change', function () { if (sel.value) window.location.href = sel.value; });
  });

  /* A material list that follows a class list: select[data-follows="#class-select"] offers only the chosen
     class's <optgroup data-class>. With no class chosen it is switched off and its first option shows its
     data-none text. Without JS every class's materials stay listed (the server takes the material's class). */
  $all('select[data-follows]').forEach(function (list) {
    var classes = $(list.getAttribute('data-follows'));
    if (!classes) return;
    var groups = $all('optgroup', list);
    var first = list.options[0];
    var chosen = list.options[list.selectedIndex];
    if (classes.value === '' && chosen && chosen.parentNode.tagName === 'OPTGROUP') {
      classes.value = chosen.parentNode.getAttribute('data-class');
    }
    function follow() {
      var classId = classes.value;
      var kept = list.value;
      groups.forEach(function (g) {
        var match = classId !== '' && g.getAttribute('data-class') === classId;
        if (match && !g.parentNode) list.appendChild(g);
        if (!match && g.parentNode) g.parentNode.removeChild(g);
      });
      list.value = kept;
      if (list.selectedIndex < 0 || list.value !== kept) list.selectedIndex = 0;
      list.disabled = classId === '';
      if (first && first.hasAttribute('data-none')) {
        first.textContent = first.getAttribute(classId === '' ? 'data-none' : 'data-any');
      }
    }
    classes.addEventListener('change', follow);
    follow();
  });

  /* Copy buttons --------------------------------------------------------------- */
  $all('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = $(btn.getAttribute('data-copy'));
      if (!target) return;
      var value = target.value !== undefined ? target.value : target.textContent;
      var done = function () { window.Sibol.toast(btn.getAttribute('data-copied') || S('copied')); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value.trim()).then(done, function () { target.select && target.select(); });
      } else if (target.select) {
        target.select();
        try { doc.execCommand('copy'); done(); } catch (e) { /* user can copy manually */ }
      }
    });
  });

  /* Character counters --------------------------------------------------------- */
  $all('textarea[maxlength][data-charcount], input[maxlength][data-charcount]').forEach(function (el) {
    var max = parseInt(el.getAttribute('maxlength'), 10);
    var out = doc.createElement('span');
    out.className = 'char-count';
    out.setAttribute('aria-live', 'polite');
    el.insertAdjacentElement('afterend', out);
    var update = function () {
      var left = max - el.value.length;
      out.textContent = S('chars_left', { count: left });
      out.classList.toggle('is-over', left < 0);
    };
    el.addEventListener('input', update);
    update();
  });

  /* Countdown timers (quiz time limits). The server enforces the deadline. */
  $all('[data-countdown]').forEach(function (el) {
    var end = Date.parse(el.getAttribute('data-countdown'));
    if (isNaN(end)) return;
    var label = $('[data-countdown-label]', el) || el;
    var warned = false;
    var tick = function () {
      var left = Math.max(0, Math.round((end - Date.now()) / 1000));
      var m = Math.floor(left / 60);
      var s = left % 60;
      label.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      el.classList.toggle('is-low', left <= 120);
      if (left <= 120 && !warned) {
        warned = true;
        window.Sibol.toast(S('time_low'), 'warning');
      }
      if (left === 0) {
        clearInterval(timer);
        var form = el.getAttribute('data-autosubmit-form') ? $(el.getAttribute('data-autosubmit-form')) : null;
        // A page script can take over (quiz.js waits for the connection); otherwise hand the form in.
        if (form && form.dispatchEvent(new CustomEvent('sibol:timeup', { cancelable: true }))) {
          var finalAction = doc.createElement('input');
          finalAction.type = 'hidden';
          finalAction.name = 'action';
          finalAction.value = 'submit';
          form.appendChild(finalAction);
          form.__confirmed = true;
          form.submit();
        }
      }
    };
    var timer = setInterval(tick, 1000);
    tick();
  });

  /* Warn before leaving a form with unsaved changes. */
  $all('form[data-unsaved-warning]').forEach(function (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
  });

  /* Answers a quiz kept in this tab for sending later, and materials kept on this device for reading
     offline (offline.js), are removed on sign-out and on the sign-in pages (an idle sign-out ends there too). */
  function forgetKept() {
    [window.sessionStorage, window.localStorage].forEach(function (store) {
      try {
        for (var i = store.length - 1; i >= 0; i--) {
          var k = store.key(i);
          if (k && k.indexOf('sibol.') === 0) store.removeItem(k);
        }
      } catch (e) { /* storage blocked: nothing was kept */ }
    });
    if (window.caches) window.caches.delete('sibol-offline-materials').catch(function () {});
  }
  $all('form[data-signout]').forEach(function (f) { f.addEventListener('submit', forgetKept); });
  if (doc.body.hasAttribute('data-signed-out')) forgetKept();

  /* Practice done offline (practice-offline.js keeps sibol.packdone.<set id>) is sent once a signed-in page
     loads with a connection, as the same form the practice page sends. */
  (function sendOfflinePractice() {
    if (!csrf || !navigator.onLine || doc.body.getAttribute('data-role') !== 'student') return;
    var base = (doc.querySelector('meta[name="app-base"]') || {}).content || '';
    var waiting = [];
    try {
      for (var i = 0; i < localStorage.length; i++) {
        var k = localStorage.key(i);
        if (k && k.indexOf('sibol.packdone.') === 0) waiting.push(k);
      }
    } catch (e) { return; }
    var sent = 0;
    var next = function () {
      var key = waiting.shift();
      if (!key) { if (sent) window.Sibol.toast(S('pack_sent', { count: sent }), 'success'); return; }
      var done;
      try { done = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { done = null; }
      var id = key.slice('sibol.packdone.'.length);
      if (!done || !/^\d+$/.test(id)) { localStorage.removeItem(key); next(); return; }
      var body = new URLSearchParams();
      body.append('_token', csrf);
      Object.keys(done.answers || {}).forEach(function (q) { body.append('answers[' + q + ']', String(done.answers[q])); });
      (done.known || []).forEach(function (c) { body.append('known[]', String(c)); });
      fetch(base + '/student/learning/' + id + '/results', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) {
          // Sent, or the set is gone: either way nothing is left to send. Anything else is tried again later.
          if (r.ok || r.status === 404) { localStorage.removeItem(key); if (r.ok) sent++; }
          next();
        }, function () { /* still offline */ });
    };
    next();
  })();

  /* The installable app: a service worker keeps the stylesheet, scripts and an offline page (public/sw.js).
     Browsers run one only over HTTPS, or on this computer (localhost). It starts after the page has loaded,
     so it never slows the first visit. */
  var appBase = (doc.querySelector('meta[name="app-base"]') || {}).content;
  if (typeof appBase === 'string' && 'serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(appBase + '/sw.js', { scope: appBase + '/' }).catch(function () {});
    });
  }

  /* Back buttons on error pages */
  $all('[data-history-back]').forEach(function (b) {
    b.addEventListener('click', function () { history.back(); });
  });

  /* Print buttons (printable pages also print from the browser's own menu). */
  $all('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  /* Wide tables over time open at their newest end (the right) on narrow screens. */
  $all('[data-scroll-end]').forEach(function (el) { el.scrollLeft = el.scrollWidth; });

  /* KaTeX for maths. Pages that can show maths name its files on <body>; they are fetched only once maths
     (\( … \) or \[ … \]) actually appears, so pages without maths stay light on mobile data. */
  var mathJs = doc.body.getAttribute('data-katex-js');
  var mathWaiting = null;
  function typeset(root) {
    try {
      window.renderMathInElement(root, {
        delimiters: [
          { left: '\\[', right: '\\]', display: true },
          { left: '\\(', right: '\\)', display: false }
        ],
        throwOnError: false,
        trust: false,
        ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option', 'input']
      });
    } catch (e) { /* leave the TeX source readable */ }
  }
  function loadScript(src, done) {
    var s = doc.createElement('script');
    s.src = src;
    s.onload = done;
    s.onerror = function () { mathWaiting = []; }; // the TeX source stays readable
    doc.head.appendChild(s);
  }
  window.Sibol.renderMath = function (root) {
    root = root || doc.body;
    if (!mathJs || !/\\\(|\\\[/.test(root.textContent || '')) return;
    if (typeof window.renderMathInElement === 'function') { typeset(root); return; }
    if (mathWaiting) { mathWaiting.push(root); return; }
    mathWaiting = [root];
    var css = doc.createElement('link');
    css.rel = 'stylesheet';
    css.href = doc.body.getAttribute('data-katex-css');
    doc.head.appendChild(css);
    loadScript(mathJs, function () {
      loadScript(doc.body.getAttribute('data-katex-render'), function () {
        mathWaiting.splice(0).forEach(typeset);
      });
    });
  };
  window.addEventListener('load', function () { window.Sibol.renderMath(doc.querySelector('main')); });

  /* Chart tooltips on hover and keyboard focus: [data-tip]. Values are also in table views. */
  var tip = null;
  function showTip(target) {
    if (!tip) {
      tip = doc.createElement('div');
      tip.className = 'tooltip';
      tip.setAttribute('role', 'presentation');
      doc.body.appendChild(tip);
    }
    tip.textContent = target.getAttribute('data-tip');
    tip.hidden = false;
    var r = target.getBoundingClientRect();
    var left = Math.min(window.innerWidth - tip.offsetWidth - 8, Math.max(8, r.left + r.width / 2 - tip.offsetWidth / 2));
    var top = r.top - tip.offsetHeight - 8;
    tip.style.left = left + 'px';
    tip.style.top = (top < 8 ? r.bottom + 8 : top) + 'px';
  }
  function hideTip() { if (tip) tip.hidden = true; }
  doc.addEventListener('mouseover', function (e) { var t = e.target.closest && e.target.closest('[data-tip]'); if (t) showTip(t); });
  doc.addEventListener('mouseout', function (e) { if (e.target.closest && e.target.closest('[data-tip]')) hideTip(); });
  doc.addEventListener('focusin', function (e) { if (e.target.hasAttribute && e.target.hasAttribute('data-tip')) showTip(e.target); });
  doc.addEventListener('focusout', hideTip);
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideTip(); });
  window.addEventListener('scroll', hideTip, { passive: true });

  /* Simple disclosure toggles: [data-toggle="#id"] */
  $all('[data-toggle]').forEach(function (btn) {
    var target = $(btn.getAttribute('data-toggle'));
    if (!target) return;
    btn.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
    btn.addEventListener('click', function () {
      target.hidden = !target.hidden;
      btn.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
      if (!target.hidden) {
        var focusable = target.querySelector('input, textarea, select, button');
        if (focusable) focusable.focus();
      }
    });
  });

  /* Read aloud: [data-read-aloud="#selector"] reads that element's text with the device's own voice, in
     data-lang (en or fil). Buttons show only where the browser can speak (html.can-speak). */
  var synth = window.speechSynthesis;
  if (synth && window.SpeechSynthesisUtterance) {
    doc.documentElement.classList.add('can-speak');
    var speaking = null;
    var label = function (btn, on) {
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      var text = btn.querySelector('[data-read-aloud-label]');
      if (text) text.textContent = on ? S('read_stop') : S('read_listen');
    };
    var stop = function () {
      if (speaking) label(speaking, false);
      speaking = null;
      synth.cancel();
    };
    var voiceFor = function (lang) {
      var voices = synth.getVoices() || [];
      var wanted = lang === 'fil' ? ['fil', 'tl'] : ['en-ph', 'en'];
      for (var w = 0; w < wanted.length; w++) {
        for (var v = 0; v < voices.length; v++) {
          if (String(voices[v].lang).toLowerCase().replace('_', '-').indexOf(wanted[w]) === 0) return voices[v];
        }
      }
      return null;
    };
    // Short pieces, so long texts are not cut off by browsers that stop a single utterance early.
    var pieces = function (text) {
      var out = [];
      (String(text).replace(/\s+/g, ' ').match(/[^.!?]+[.!?]*\s*/g) || []).forEach(function (sentence) {
        while (sentence.length > 220) {
          var cut = sentence.lastIndexOf(' ', 220);
          if (cut < 60) cut = 220;
          out.push(sentence.slice(0, cut));
          sentence = sentence.slice(cut);
        }
        if (sentence.trim()) out.push(sentence);
      });
      return out;
    };
    doc.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-read-aloud]');
      if (!btn) return;
      if (speaking === btn) { stop(); return; }
      stop();
      var target = $(btn.getAttribute('data-read-aloud'));
      if (!target) return;
      var lang = btn.getAttribute('data-lang') === 'fil' ? 'fil' : 'en';
      var voice = voiceFor(lang);
      if (lang === 'fil' && (!voice || String(voice.lang).toLowerCase().indexOf('en') === 0)) {
        window.Sibol.toast(S('read_no_filipino'), 'info');
      }
      var parts = pieces(target.innerText);
      if (!parts.length) return;
      speaking = btn;
      label(btn, true);
      parts.forEach(function (part, i) {
        var u = new window.SpeechSynthesisUtterance(part);
        if (voice) { u.voice = voice; u.lang = voice.lang; } else { u.lang = lang === 'fil' ? 'fil-PH' : 'en-PH'; }
        u.rate = 0.95;
        if (i === parts.length - 1) u.onend = function () { if (speaking === btn) { label(btn, false); speaking = null; } };
        synth.speak(u);
      });
    });
    window.addEventListener('pagehide', function () { synth.cancel(); });
  }

  /* Native dialog openers: [data-dialog-open="#id"] */
  $all('[data-dialog-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = $(btn.getAttribute('data-dialog-open'));
      if (d && typeof d.showModal === 'function') d.showModal();
    });
  });
  $all('[data-dialog-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = btn.closest('dialog');
      if (d) d.close();
    });
  });
})();
