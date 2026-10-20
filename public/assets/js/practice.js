/* Flashcards: flip with click/Enter/Space, mark known or not yet, then save results. */
(function () {
  'use strict';
  var form = document.querySelector('[data-flashcards]');
  if (!form) return;
  var cards = Array.prototype.slice.call(form.querySelectorAll('[data-card]'));
  var progress = form.querySelector('[data-card-progress]');
  var doneBox = form.querySelector('[data-cards-done]');
  var index = 0;
  var known = 0;

  function show(i) {
    cards.forEach(function (c, n) { c.hidden = n !== i; });
    if (progress) progress.textContent = 'Card ' + (i + 1) + ' of ' + cards.length;
    var flip = cards[i] && cards[i].querySelector('[data-flip]');
    if (flip) flip.focus();
  }
  function next(markKnown) {
    var box = cards[index].querySelector('[data-known-box]');
    if (box) box.checked = markKnown;
    if (markKnown) known++;
    index++;
    if (index >= cards.length) {
      cards.forEach(function (c) { c.hidden = true; });
      if (progress) progress.textContent = '';
      doneBox.hidden = false;
      var text = doneBox.querySelector('[data-done-text]');
      if (text) text.textContent = 'You knew ' + known + ' of ' + cards.length + ' cards.';
      var submit = doneBox.querySelector('button');
      if (submit) submit.focus();
      return;
    }
    show(index);
  }
  cards.forEach(function (card) {
    var flip = card.querySelector('[data-flip]');
    flip.addEventListener('click', function () {
      var front = card.querySelector('[data-front]');
      var back = card.querySelector('[data-back]');
      var side = card.querySelector('[data-side]');
      var showingBack = !back.hidden;
      back.hidden = showingBack;
      front.hidden = !showingBack;
      side.textContent = showingBack ? 'Front' : 'Back';
    });
    card.querySelector('[data-known]').addEventListener('click', function () { next(true); });
    card.querySelector('[data-again]').addEventListener('click', function () { next(false); });
  });
  // Without JavaScript every card and checkbox is visible; with it, one card at a time and buttons drive the boxes.
  form.querySelectorAll('[data-known-box]').forEach(function (b) { b.checked = false; });
  form.querySelectorAll('[data-known-label]').forEach(function (l) { l.hidden = true; });
  form.querySelectorAll('[data-known], [data-again]').forEach(function (b) { b.hidden = false; });
  form.querySelectorAll('noscript').forEach(function (n) { n.remove(); });
  cards.forEach(function (c) { c.style.marginBottom = '0'; });
  show(0);
})();
