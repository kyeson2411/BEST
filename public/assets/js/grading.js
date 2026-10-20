/* Grading page: live rubric total, and copying an AI suggestion into the form (the teacher still saves). */
(function () {
  'use strict';
  var form = document.getElementById('grade-form');
  if (!form) return;
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
      if (window.Sibol && window.Sibol.toast) window.Sibol.toast('Suggestion copied into the form. Check it, then save.', 'info');
    });
  }
})();
