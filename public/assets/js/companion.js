/* Learning companion: streaming answers (NDJSON over fetch), stop, retry, sources, dialogs.
   Streamed text is shown as plain text; the server returns the final, sanitised HTML. */
(function () {
  'use strict';
  var doc = document;
  var base = (doc.querySelector('meta[name="app-base"]') || {}).content || '';
  var csrf = (window.Sibol && window.Sibol.csrf) || '';
  var form = doc.getElementById('composer');
  var exchanges = doc.getElementById('exchanges');
  var scroller = doc.getElementById('thread-scroll');
  var sessions = doc.getElementById('sessions');
  var controller = null;

  function $(sel, root) { return (root || doc).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
  function el(tag, cls, text) {
    var node = doc.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  /* Sessions drawer on phones ------------------------------------------------- */
  $all('[data-sessions-open]').forEach(function (b) {
    b.addEventListener('click', function () { if (sessions) { sessions.classList.add('is-open'); var a = $('a', sessions); if (a) a.focus(); } });
  });
  $all('[data-sessions-close]').forEach(function (b) {
    b.addEventListener('click', function () { if (sessions) sessions.classList.remove('is-open'); });
  });

  /* Sources: clicking a citation highlights its margin note -------------------- */
  function activate(tag) {
    $all('.source-tag.is-active').forEach(function (t) { t.classList.remove('is-active'); });
    tag.classList.add('is-active');
  }
  doc.addEventListener('click', function (e) {
    var cite = e.target.closest && e.target.closest('a.cite');
    if (cite) {
      e.preventDefault();
      var tag = doc.getElementById(cite.getAttribute('href').slice(1));
      if (tag) {
        activate(tag);
        tag.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        tag.focus({ preventScroll: true });
      }
      return;
    }
    var copy = e.target.closest && e.target.closest('[data-copy-message]');
    if (copy) {
      var body = $(copy.getAttribute('data-copy-message'));
      if (body && navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(body.innerText.trim()).then(function () { window.Sibol.toast('Copied'); });
      } else if (body) {
        var range = doc.createRange();
        range.selectNodeContents(body);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        window.Sibol.toast('Text selected. Press Ctrl+C to copy.', 'info');
      }
      return;
    }
    var report = e.target.closest && e.target.closest('[data-report]');
    if (report) { openDialog('#report-dialog', '[data-report-form]', base + '/companion/messages/' + report.getAttribute('data-report') + '/report'); return; }
    var share = e.target.closest && e.target.closest('[data-share]');
    if (share) { openDialog('#share-dialog', '[data-share-form]', base + '/companion/messages/' + share.getAttribute('data-share') + '/share'); return; }
    var regen = e.target.closest && e.target.closest('[data-regenerate]');
    if (regen) { regenerate(); }
  });
  doc.addEventListener('mouseover', function (e) {
    var cite = e.target.closest && e.target.closest('a.cite');
    if (cite) { var tag = doc.getElementById(cite.getAttribute('href').slice(1)); if (tag) activate(tag); }
  });

  function openDialog(dialogSel, formSel, action) {
    var dialog = $(dialogSel);
    if (!dialog) return;
    var f = $(formSel, dialog);
    if (f) f.action = action;
    if (typeof dialog.showModal === 'function') dialog.showModal();
  }

  if (!form || !exchanges) return;

  var input = $('textarea', form);
  var sendBtn = $('[data-send]', form);
  var stopBtn = $('[data-stop]', form);
  var statusEl = $('[data-composer-status]', form);

  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight + 2, 220) + 'px';
  }
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      if (form.requestSubmit) form.requestSubmit(); else form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  function nearBottom() {
    var box = scroller && scroller.scrollHeight > scroller.clientHeight ? scroller : doc.scrollingElement;
    return box.scrollHeight - box.scrollTop - box.clientHeight < 160;
  }
  function toBottom() {
    var box = scroller && scroller.scrollHeight > scroller.clientHeight ? scroller : doc.scrollingElement;
    box.scrollTop = box.scrollHeight;
  }
  function busy(on) {
    sendBtn.hidden = on;
    stopBtn.hidden = !on;
    input.readOnly = on;
    if (statusEl) statusEl.textContent = on ? 'Sibol is answering.' : '';
  }

  function questionNode(text) {
    var wrap = el('div', 'msg msg--user');
    var who = el('div', 'msg__who');
    who.appendChild(el('span', null, 'You'));
    wrap.appendChild(who);
    wrap.appendChild(el('div', 'msg__body', text));
    return wrap;
  }
  function liveAnswer() {
    var art = el('article', 'msg msg--assistant is-streaming');
    art.setAttribute('aria-busy', 'true');
    var who = el('div', 'msg__who');
    who.appendChild(el('span', null, 'Sibol'));
    art.appendChild(who);
    var body = el('div', 'msg__body');
    var dots = el('span', 'thinking-dots');
    dots.appendChild(el('i'));
    dots.appendChild(el('i'));
    dots.appendChild(el('i'));
    dots.appendChild(doc.createTextNode(' Thinking'));
    body.appendChild(dots);
    art.appendChild(body);
    return art;
  }
  function dropOldRetryButtons() {
    $all('[data-regenerate]', exchanges).forEach(function (b) { b.remove(); });
  }

  function run(url, payload, exchange, live) {
    controller = new AbortController();
    busy(true);
    dropOldRetryButtons();
    var body = $('.msg__body', live);
    var started = false;
    var finished = false;

    function append(text) {
      if (!started) { body.textContent = ''; started = true; }
      var stick = nearBottom();
      body.appendChild(doc.createTextNode(text));
      if (stick) toBottom();
    }
    function fail(message, retry) {
      finished = true;
      live.classList.remove('is-streaming');
      live.classList.add('msg--error');
      live.removeAttribute('aria-busy');
      body.textContent = message;
      if (retry !== false) {
        var actions = el('div', 'msg__actions');
        var btn = el('button', 'btn btn--quiet', 'Try again');
        btn.type = 'button';
        btn.setAttribute('data-regenerate', '');
        actions.appendChild(btn);
        live.appendChild(actions);
      }
      if (statusEl) statusEl.textContent = message;
    }
    function handle(line) {
      if (!line.trim()) return;
      var ev;
      try { ev = JSON.parse(line); } catch (e) { return; }
      if (ev.type === 'meta' && ev.sources && !started) {
        $('.thinking-dots', body) && ($('.thinking-dots', body).lastChild.textContent = ' Reading ' + ev.sources + (ev.sources === 1 ? ' passage' : ' passages') + ' from your materials');
      } else if (ev.type === 'delta') {
        append(ev.text);
      } else if (ev.type === 'done') {
        finished = true;
        live.insertAdjacentHTML('beforebegin', ev.html);
        live.remove();
        exchange.classList.remove('exchange--live');
        if (window.Sibol.renderMath) window.Sibol.renderMath(exchange);
        if (statusEl) statusEl.textContent = 'Answer ready.';
        if (ev.title) {
          var heading = doc.getElementById('thread-title');
          if (heading) heading.textContent = ev.title;
          var current = doc.querySelector('.session-link[aria-current="page"] .session-link__title');
          if (current) current.textContent = ev.title;
          doc.title = ev.title + ' – Sibol';
        }
        if (ev.remaining) {
          var left = form.querySelector('[data-remaining]');
          if (left) left.textContent = ev.remaining;
        }
      } else if (ev.type === 'error') {
        fail(ev.message || 'Something went wrong.', ev.code !== 'limit');
      }
    }

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      signal: controller.signal,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/x-ndjson', 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch' },
      body: JSON.stringify(payload || {})
    }).then(function (res) {
      var type = res.headers.get('content-type') || '';
      if (!res.ok || type.indexOf('json') !== -1 && type.indexOf('ndjson') === -1) {
        return res.json().then(function (j) { fail(j.error || 'The request could not be completed.', res.status >= 500); },
          function () { fail('The request could not be completed (' + res.status + ').'); });
      }
      var reader = res.body.getReader();
      var decoder = new TextDecoder();
      var buffer = '';
      function pump() {
        return reader.read().then(function (r) {
          if (r.done) {
            if (buffer) handle(buffer);
            if (!finished) fail('The answer was interrupted. Try again.');
            return;
          }
          buffer += decoder.decode(r.value, { stream: true });
          var lines = buffer.split('\n');
          buffer = lines.pop();
          lines.forEach(handle);
          return pump();
        });
      }
      return pump();
    }).catch(function (err) {
      if (err && err.name === 'AbortError') {
        finished = true;
        live.classList.remove('is-streaming');
        live.removeAttribute('aria-busy');
        var note = el('p', 'meta', 'Stopped. What was written so far has been saved.');
        live.appendChild(note);
        var actions = el('div', 'msg__actions');
        var btn = el('button', 'btn btn--quiet', 'Try again');
        btn.type = 'button';
        btn.setAttribute('data-regenerate', '');
        actions.appendChild(btn);
        live.appendChild(actions);
      } else if (!finished) {
        fail('The connection was lost. Check your internet connection and try again.');
      }
    }).finally(function () {
      controller = null;
      busy(false);
      input.focus();
    });
  }

  function ask(text) {
    var empty = doc.getElementById('thread-empty');
    if (empty) empty.remove();
    var exchange = el('div', 'exchange exchange--live');
    exchange.appendChild(questionNode(text));
    var live = liveAnswer();
    exchange.appendChild(live);
    exchanges.appendChild(exchange);
    toBottom();
    run(form.action, { message: text }, exchange, live);
  }

  function regenerate() {
    if (controller) return;
    var all = $all('.exchange', exchanges);
    var exchange = all[all.length - 1];
    if (!exchange) return;
    $all('.msg--assistant, .notes', exchange).forEach(function (n) { n.remove(); });
    exchange.classList.add('exchange--live');
    var live = liveAnswer();
    exchange.appendChild(live);
    run(form.getAttribute('data-regenerate-url'), {}, exchange, live);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text || controller) return;
    input.value = '';
    autosize();
    ask(text);
  });
  stopBtn.addEventListener('click', function () { if (controller) controller.abort(); });

  // A first question typed on the "new session" screen is sent once the page opens.
  var pending = form.getAttribute('data-pending');
  if (pending) { form.removeAttribute('data-pending'); ask(pending); } else { toBottom(); }
})();
