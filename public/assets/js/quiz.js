/* Quiz attempt. Answers are sent shortly after each change and when the page is hidden. Until the
   server confirms them they are also kept in this tab's sessionStorage, so a reload or a dropped
   connection does not lose them: they are restored when the page opens and sent again on reconnect.
   They are removed once the server has them, on submit and on sign-out. The server keeps the
   authoritative deadline; this only reduces the chance of losing answers. */
(function () {
  'use strict';
  var form = document.querySelector('[data-quiz]');
  if (!form) return;
  var S = window.Sibol.strings(form);
  var status = form.querySelector('[data-save-status]');
  var notice = form.querySelector('[data-connection]');
  var noticeText = notice && notice.querySelector('[data-connection-text]');
  var live = form.querySelector('[data-connection-live]');
  var url = form.getAttribute('data-autosave-url');
  var key = 'sibol.quiz.' + form.getAttribute('data-attempt');
  var total = form.querySelectorAll('.question').length;
  var SOON = 1500;    // after a change
  var RETRY = 15000;  // between attempts while sending fails
  var rev = 0;        // bumped on every change
  var savedRev = 0;   // newest revision the server has confirmed
  var sending = false;
  var finishing = null; // "user" (Submit was confirmed) or "time" (time ran out) while handing in
  var timer = null;

  function fields() {
    return Array.prototype.filter.call(form.elements, function (el) { return el.name && el.name.indexOf('answers[') === 0; });
  }
  function answers() {
    var out = {};
    fields().forEach(function (el) {
      if (el.type !== 'radio') out[el.name] = el.value;
      else if (el.checked) out[el.name] = el.value;
    });
    return out;
  }
  function restore(kept) {
    fields().forEach(function (el) {
      if (!Object.prototype.hasOwnProperty.call(kept, el.name)) return;
      if (el.type === 'radio') el.checked = el.value === kept[el.name];
      else el.value = kept[el.name];
    });
  }

  /* This tab's copy of answers the server has not confirmed. Storage can be full or blocked;
     then only the server copy counts. */
  function keep() {
    try { sessionStorage.setItem(key, JSON.stringify({ answers: answers(), at: Date.now() })); } catch (e) { /* see above */ }
  }
  function kept() {
    try { var raw = sessionStorage.getItem(key); return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
  }
  function forget() {
    try { sessionStorage.removeItem(key); } catch (e) { /* nothing to remove */ }
  }

  function show(suffix) {
    if (!status) return;
    var given = answers();
    var count = Object.keys(given).filter(function (k) { return String(given[k]).trim() !== ''; }).length;
    status.textContent = S('answered', { count: count, total: total }) + (suffix ? ' · ' + suffix : '');
  }
  /* A notice in the sticky quiz bar: kind "offline" (amber, no signal) or "info"; null hides it.
     Screen readers hear it through a live region that is always present. */
  function connection(message, kind) {
    if (!notice) return;
    if (live && message && live.textContent !== message) live.textContent = message;
    notice.hidden = !message;
    if (!message) return;
    var offline = kind !== 'info';
    notice.classList.toggle('callout--amber', offline);
    var use = notice.querySelector('use');
    if (use) use.setAttribute('href', use.getAttribute('href').replace(/#i-[a-z-]+$/, offline ? '#i-wifi-off' : '#i-info'));
    if (noticeText) noticeText.textContent = message;
  }

  function schedule(delay) {
    clearTimeout(timer);
    timer = setTimeout(function () { if (finishing) finish(); else save(); }, delay);
  }

  /* Sends every answer. Resolves "saved", "redirected" (the server handed the attempt in, for example
     because time ran out) or "failed". */
  function save(options) {
    options = options || {};
    if (sending && !options.keepalive) return Promise.resolve('failed');
    if (rev === savedRev && !options.force) return Promise.resolve('saved');
    var sent = rev;
    var data = new FormData(form);
    data.set('action', 'save');
    sending = true;
    show(S('saving'));
    return fetch(url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      keepalive: !!options.keepalive,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    }).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j || {} }; });
    }).then(function (res) {
      sending = false;
      if (res.body.redirect) {
        forget();
        finishing = finishing || 'time';
        window.location.href = res.body.redirect;
        return 'redirected';
      }
      if (res.ok && res.body.saved) {
        savedRev = Math.max(savedRev, sent);
        connection(null);
        if (savedRev === rev) {
          forget();
          show(S('saved_at', { time: res.body.saved_at }));
        } else {
          schedule(SOON);
        }
        return 'saved';
      }
      if (res.status === 409) {
        // The attempt is no longer open (handed in elsewhere): nothing here can be saved.
        forget();
        finishing = finishing || 'time';
        window.location.href = form.getAttribute('data-assignment-url');
        return 'redirected';
      }
      show(S('not_saved'));
      schedule(RETRY);
      return 'failed';
    }).catch(function () {
      sending = false;
      if (finishing === 'time') connection(S('time_up_offline'), 'offline');
      else if (!finishing) connection(S('offline_notice'), 'offline');
      show(S('kept'));
      schedule(RETRY);
      return 'failed';
    });
  }

  /* Hands the attempt in once the server has every answer. When time ran out it keeps trying on its
     own; when the student pressed Submit and it failed, the attempt stays open for them to try again. */
  function finish(reason) {
    finishing = reason || finishing || 'time';
    clearTimeout(timer);
    save({ force: true }).then(function (state) {
      if (state === 'redirected') return;
      if (state === 'saved') {
        forget();
        var action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'submit';
        form.appendChild(action);
        HTMLFormElement.prototype.submit.call(form);
      } else if (finishing === 'user') {
        finishing = null;
        connection(S('submit_offline'), 'offline');
      }
    });
  }

  function changed() {
    if (finishing) return;
    rev++;
    keep();
    show(S('unsaved'));
    schedule(SOON);
  }
  form.addEventListener('input', changed);
  form.addEventListener('change', changed);

  form.addEventListener('submit', function (event) {
    var submitter = event.submitter || null;
    if (!submitter || submitter.value !== 'submit') {
      // "Save answers" (or Enter in a text box): send now and stay on the page.
      event.preventDefault();
      save({ force: true });
      return;
    }
    // Handing in. The confirmation dialog in app.js comes first; act once it is confirmed.
    if (!form.__confirmed) return;
    form.__confirmed = false;
    event.preventDefault();
    if (!navigator.onLine) {
      connection(S('submit_offline'), 'offline');
      return;
    }
    finish('user');
  });

  /* Time ran out (app.js countdown). Hand in what the server has; offline, wait for the connection. */
  form.addEventListener('sibol:timeup', function (event) {
    event.preventDefault();
    finishing = 'time';
    clearTimeout(timer);
    if (navigator.onLine) finish('time');
    else connection(S('time_up_offline'), 'offline');
  });

  window.addEventListener('offline', function () {
    connection(finishing === 'time' ? S('time_up_offline') : S('offline_notice'), 'offline');
    show(S('kept'));
  });
  window.addEventListener('online', function () {
    if (finishing === 'time') { finish('time'); return; }
    connection(S('back_online'), 'info');
    save();
  });
  // Leaving or switching away: send what is unsent (keepalive lets it finish after the page goes).
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden' && rev !== savedRev && !finishing) save({ keepalive: true });
  });
  window.addEventListener('pagehide', function () {
    if (rev !== savedRev && !finishing) save({ keepalive: true });
  });
  window.addEventListener('beforeunload', function (e) {
    if (rev !== savedRev && !finishing) { e.preventDefault(); e.returnValue = ''; }
  });

  // Answers from earlier in this tab that the server never confirmed: put them back and send them.
  var stored = kept();
  if (stored && stored.answers) {
    restore(stored.answers);
    rev = 1;
    connection(S('restored'), 'info');
    show(S('unsaved'));
    if (navigator.onLine) save(); else connection(S('offline_notice'), 'offline');
  } else {
    show('');
  }
})();
