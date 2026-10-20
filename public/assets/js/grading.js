/* Grading page: live rubric total, copying an AI suggestion into the form (the teacher still saves), and the comment bank. */
(function () {
  'use strict';
  var form = document.getElementById('grade-form');
  if (!form) return;
  var S = window.Sibol.strings(form);
  var sumEl = form.querySelector('[data-rubric-sum]');
  function recalc() {
    if (!sumEl) return;
    var total = 0;
    var any = false;
    Array.prototype.forEach.call(form.querySelectorAll('[data-rubric-input]'), function (i) {
      if (i.value !== '') { any = true; total += parseFloat(i.value) || 0; }
    });
    sumEl.textContent = any ? (Math.round(total * 100) / 100).toString() : '—';
  }
  form.addEventListener('input', recalc);
  recalc();

  var apply = document.querySelector('[data-apply-suggestion]');
  if (apply) {
    apply.addEventListener('click', function () {
      Array.prototype.forEach.call(document.querySelectorAll('[data-suggest-criterion]'), function (row) {
        var input = form.querySelector('[data-rubric-input="' + row.getAttribute('data-suggest-criterion') + '"]');
        if (input) input.value = row.getAttribute('data-points');
      });
      var comment = document.querySelector('[data-suggest-comment]');
      var target = form.querySelector('[data-suggest-target="comment"]');
      if (comment && target && target.value.trim() === '') target.value = comment.textContent.trim();
      recalc();
      form.dispatchEvent(new Event('input', { bubbles: true }));
      window.Sibol.toast(S('suggestion_copied'), 'info');
    });
  }

  /* Comment bank: insert a saved comment into the comment box the teacher was last writing in, and save the comment
     being written without leaving the page. Without JavaScript the bank is a list to copy from. */
  var bank = form.querySelector('[data-comment-bank]');
  if (bank) {
    var csrf = (window.Sibol && window.Sibol.csrf) || '';
    var target = form.querySelector('[data-comment-target="overall"]');
    form.addEventListener('focusin', function (e) {
      if (e.target.hasAttribute && e.target.hasAttribute('data-comment-target')) target = e.target;
    });
    var post = function (url, body) {
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body ? body.toString() : ''
      });
    };
    var insert = function (text) {
      if (!target) return;
      var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
      var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : target.value.length;
      var before = target.value.slice(0, start);
      var gap = before !== '' && !/\s$/.test(before) ? (target.tagName === 'TEXTAREA' ? '\n' : ' ') : '';
      target.setRangeText(gap + text, start, end, 'end');
      target.focus();
      target.dispatchEvent(new Event('input', { bubbles: true }));
    };
    var addButton = function (list, id, text) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'comment-chip';
      b.setAttribute('data-insert-comment', text);
      b.setAttribute('data-comment-id', String(id));
      b.textContent = text;
      li.appendChild(b);
      list.insertBefore(li, list.firstChild);
    };
    bank.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-insert-comment]') : null;
      if (!btn) return;
      insert(btn.getAttribute('data-insert-comment'));
      post(bank.getAttribute('data-comments-url') + '/' + btn.getAttribute('data-comment-id') + '/used').catch(function () {});
    });
    // The search box and the criterion list are not part of the grade, so typing in them leaves the form clean.
    Array.prototype.forEach.call(bank.querySelectorAll('[data-bank-filter], [data-bank-criterion]'), function (el) {
      el.addEventListener('input', function (e) { e.stopPropagation(); });
      el.addEventListener('change', function (e) { e.stopPropagation(); });
    });
    var filter = bank.querySelector('[data-bank-filter]');
    if (filter) {
      filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        Array.prototype.forEach.call(bank.querySelectorAll('[data-insert-comment]'), function (b) {
          b.parentNode.hidden = q !== '' && b.getAttribute('data-insert-comment').toLowerCase().indexOf(q) === -1;
        });
      });
    }
    var save = bank.querySelector('[data-bank-save]');
    if (save) {
      save.hidden = false;
      save.querySelector('[data-bank-save-button]').addEventListener('click', function () {
        var source = target || form.querySelector('[data-comment-target="overall"]');
        var text = '';
        if (source) {
          text = source.value.slice(source.selectionStart || 0, source.selectionEnd || 0).trim() || source.value.trim();
        }
        if (text === '') {
          window.Sibol.toast(S('bank_empty'), 'info');
          return;
        }
        var body = new URLSearchParams();
        body.set('body', text);
        var criterion = save.querySelector('[data-bank-criterion]');
        if (criterion) body.set('criterion', criterion.value);
        post(bank.getAttribute('data-comments-url'), body)
          .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
          .then(function (res) {
            if (!res.ok) {
              window.Sibol.toast(res.data.error || S('bank_failed'), 'error');
              return;
            }
            var list = bank.querySelector('[data-bank-list]');
            if (list) addButton(list, res.data.id, res.data.body);
            var empty = bank.querySelector('[data-bank-empty]');
            if (empty) empty.hidden = true;
            window.Sibol.toast(S('bank_saved'), 'success');
          })
          .catch(function () { window.Sibol.toast(S('bank_failed'), 'error'); });
      });
    }
  }
})();
