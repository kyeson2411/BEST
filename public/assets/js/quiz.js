/* Quiz attempt: background autosave and an answered counter. The server keeps the
   authoritative deadline; this only reduces the chance of losing answers. */
(function () {
  'use strict';
  var form = document.querySelector('[data-quiz]');
  if (!form) return;
  var S = window.Sibol.strings(form);
  var status = form.querySelector('[data-save-status]');
  var total = form.querySelectorAll('.question').length;
  var dirty = false;
  var saving = false;

  function answeredCount() {
    var names = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.name.indexOf('answers[') !== 0) return;
      if ((el.type === 'radio' && el.checked) || ((el.type === 'text' || el.tagName === 'TEXTAREA') && el.value.trim() !== '')) {
        names[el.name] = true;
      }
    });
    return Object.keys(names).length;
  }

  function updateCount(suffix) {
    if (!status) return;
    status.textContent = S('answered', { count: answeredCount(), total: total }) + (suffix ? ' · ' + suffix : '');
  }

  function save() {
    if (!dirty || saving) return;
    saving = true;
    var data = new FormData(form);
    data.set('action', 'save');
    fetch(form.getAttribute('data-autosave-url'), {
      method: 'POST',
      body: data,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (res.ok && res.body.saved) { dirty = false; updateCount(S('saved_at', { time: res.body.saved_at })); }
        else if (res.body && res.body.redirect) { window.location.href = res.body.redirect; }
        else { updateCount(S('not_saved')); }
      })
      .catch(function () { updateCount(S('offline')); })
      .finally(function () { saving = false; });
  }

  form.addEventListener('change', function () { dirty = true; updateCount(S('unsaved')); });
  form.addEventListener('input', function () { dirty = true; });
  setInterval(save, 30000);
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
  form.addEventListener('submit', function () { dirty = false; });
})();
