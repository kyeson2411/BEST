/* Practice sets kept on this device for practising without a connection. On a practice set, [data-pack-save]
   keeps the set (its questions with their answers, or its cards) in localStorage as sibol.pack.<id>. The offline
   page ([data-packs]) lists kept sets and plays them; a finished set waits as sibol.packdone.<id> until app.js
   sends it with a connection. app.js removes every sibol.* key on sign-out. All text is set as text, never HTML. */
(function () {
  'use strict';

  var doc = document;
  var store;
  try { store = window.localStorage; store.getItem('sibol.test'); } catch (e) { return; }
  var PACK = 'sibol.pack.';
  var DONE = 'sibol.packdone.';

  function read(key) {
    try { return JSON.parse(store.getItem(key) || 'null'); } catch (e) { return null; }
  }
  function el(tag, cls, text) {
    var node = doc.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  /* On a practice set: keep it on this device, or remove it. */
  var saveBtn = doc.querySelector('[data-pack-save]');
  if (saveBtn) {
    var S1 = window.Sibol.strings(saveBtn);
    var id = saveBtn.getAttribute('data-pack-id');
    var label = saveBtn.querySelector('[data-pack-label]');
    var show = function () {
      var kept = !!store.getItem(PACK + id);
      saveBtn.setAttribute('aria-pressed', kept ? 'true' : 'false');
      if (label) label.textContent = kept ? S1('kept') : S1('keep');
    };
    show();
    saveBtn.hidden = false;
    saveBtn.addEventListener('click', function () {
      if (store.getItem(PACK + id)) {
        store.removeItem(PACK + id);
        show();
        window.Sibol.toast(S1('released'), 'info');
        return;
      }
      saveBtn.setAttribute('aria-busy', 'true');
      fetch(saveBtn.getAttribute('data-pack-save'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok || r.redirected) throw new Error(String(r.status)); return r.json(); })
        .then(function (pack) {
          store.setItem(PACK + id, JSON.stringify(pack));
          show();
          window.Sibol.toast(S1('kept_done'), 'success');
        })
        .catch(function () { window.Sibol.toast(S1('keep_failed'), 'error'); })
        .then(function () { saveBtn.removeAttribute('aria-busy'); });
    });
  }

  /* On the offline page: the kept sets, and a player for one of them. */
  var root = doc.querySelector('[data-packs]');
  if (!root) return;
  var S = window.Sibol.strings(root);
  var list = root.querySelector('[data-pack-list]');
  var empty = root.querySelector('[data-pack-empty]');
  var player = root.querySelector('[data-pack-player]');

  function packs() {
    var out = [];
    for (var i = 0; i < store.length; i++) {
      var k = store.key(i);
      if (k && k.indexOf(PACK) === 0) {
        var p = read(k);
        if (p && p.id && Array.isArray(p.items)) out.push(p);
      }
    }
    return out.sort(function (a, b) { return String(a.title).localeCompare(String(b.title)); });
  }

  function showList() {
    player.hidden = true;
    player.textContent = '';
    list.textContent = '';
    var all = packs();
    empty.hidden = all.length > 0;
    list.hidden = all.length === 0;
    all.forEach(function (p) {
      var row = el('li', 'row');
      var main = el('div', 'row__main');
      main.appendChild(el('span', 'row__title', p.title));
      var meta = el('div', 'row__meta');
      meta.appendChild(el('span', '', p.kind === 'quiz' ? S('quiz') : S('cards')));
      meta.appendChild(el('span', '', S('items', { count: p.items.length })));
      if (p.place) meta.appendChild(el('span', '', p.place));
      if (store.getItem(DONE + p.id)) meta.appendChild(el('span', 'badge badge--amber', S('waiting')));
      main.appendChild(meta);
      row.appendChild(main);
      var aside = el('div', 'row__aside');
      var go = el('button', 'btn btn--secondary btn--sm', S('start'));
      go.type = 'button';
      go.addEventListener('click', function () { play(p); });
      aside.appendChild(go);
      row.appendChild(aside);
      list.appendChild(row);
    });
  }

  function play(p) {
    list.hidden = true;
    empty.hidden = true;
    player.hidden = false;
    player.textContent = '';
    player.appendChild(el('h3', 'section__title', p.title));
    var back = el('button', 'btn btn--quiet btn--sm', S('back'));
    back.type = 'button';
    back.addEventListener('click', showList);
    if (p.kind === 'quiz') { playQuiz(p); } else { playCards(p); }
    player.appendChild(back);
    var first = player.querySelector('input, button');
    if (first) first.focus();
  }

  function playQuiz(p) {
    var ol = el('ol', 'questions');
    var inputs = [];
    p.items.forEach(function (item, i) {
      var li = el('li', 'question');
      li.appendChild(el('p', 'question__prompt', item.question));
      var body = el('div', 'question__body');
      if (item.type === 'mcq') {
        var choices = el('div', 'choice-list');
        item.choices.forEach(function (choice, ci) {
          var lab = el('label', 'choice');
          var radio = el('input');
          radio.type = 'radio';
          radio.name = 'pack-' + p.id + '-' + i;
          radio.value = String(ci);
          lab.appendChild(radio);
          lab.appendChild(el('span', '', choice));
          choices.appendChild(lab);
        });
        body.appendChild(choices);
        inputs.push(function () { var c = choices.querySelector('input:checked'); return c ? c.value : ''; });
      } else {
        var box = el('input', 'input');
        box.setAttribute('aria-label', S('your_answer'));
        box.style.maxWidth = '360px';
        body.appendChild(box);
        inputs.push(function () { return box.value; });
      }
      li.appendChild(body);
      ol.appendChild(li);
    });
    player.appendChild(ol);
    var check = el('button', 'btn btn--primary', S('check'));
    check.type = 'button';
    var result = el('p', 'callout callout--leaf');
    result.hidden = true;
    check.addEventListener('click', function () {
      var answers = {};
      var score = 0;
      p.items.forEach(function (item, i) {
        var given = inputs[i]();
        answers[i] = given;
        var right = item.type === 'mcq'
          ? given !== '' && Number(given) === Number(item.answer)
          : String(given).trim().toLowerCase() === String(item.answer_text).trim().toLowerCase();
        if (right) score++;
        var li = ol.children[i];
        var fb = el('div', 'question__feedback');
        fb.appendChild(el('p', '', right ? S('correct') : S('wrong')));
        if (!right) fb.appendChild(el('p', '', S('answer', { a: item.type === 'mcq' ? item.choices[item.answer] : item.answer_text })));
        if (item.explanation) fb.appendChild(el('p', '', item.explanation));
        li.querySelector('.question__body').appendChild(fb);
        Array.prototype.forEach.call(li.querySelectorAll('input'), function (x) { x.disabled = true; });
      });
      store.setItem(DONE + p.id, JSON.stringify({ answers: answers, at: new Date().toISOString() }));
      result.textContent = S('score', { score: score, total: p.items.length });
      result.hidden = false;
      check.hidden = true;
      result.focus && result.setAttribute('tabindex', '-1');
      result.focus();
    });
    player.appendChild(check);
    player.appendChild(result);
  }

  function playCards(p) {
    var boxes = [];
    p.items.forEach(function (card, i) {
      var wrap = el('div', 'panel');
      wrap.style.marginBottom = '16px';
      wrap.appendChild(el('p', '', card.front));
      var backText = el('p', 'meta', card.back);
      backText.hidden = true;
      var flip = el('button', 'btn btn--secondary btn--sm', S('show_back'));
      flip.type = 'button';
      flip.addEventListener('click', function () { backText.hidden = false; flip.hidden = true; });
      wrap.appendChild(flip);
      wrap.appendChild(backText);
      var lab = el('label', 'check');
      var box = el('input', 'check__input');
      box.type = 'checkbox';
      box.value = String(i);
      lab.appendChild(box);
      lab.appendChild(el('span', 'check__label', S('knew')));
      wrap.appendChild(lab);
      boxes.push(box);
      player.appendChild(wrap);
    });
    var save = el('button', 'btn btn--primary', S('save_cards'));
    save.type = 'button';
    var result = el('p', 'callout callout--leaf');
    result.hidden = true;
    save.addEventListener('click', function () {
      var known = boxes.filter(function (b) { return b.checked; }).map(function (b) { return Number(b.value); });
      store.setItem(DONE + p.id, JSON.stringify({ known: known, at: new Date().toISOString() }));
      result.textContent = S('cards_saved', { score: known.length, total: p.items.length });
      result.hidden = false;
      save.hidden = true;
    });
    player.appendChild(save);
    player.appendChild(result);
  }

  showList();
})();
