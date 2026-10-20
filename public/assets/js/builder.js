/* Assignment builder: show only the fields that belong to the chosen question type,
   and add/remove rubric rows. Without JavaScript every field stays visible and labelled. */
(function () {
  'use strict';
  function sync(fields) {
    var select = fields.querySelector('[data-qtype]');
    if (!select) return;
    var type = select.value;
    Array.prototype.forEach.call(fields.querySelectorAll('[data-for]'), function (el) {
      var on = el.getAttribute('data-for') === type;
      el.hidden = !on;
      Array.prototype.forEach.call(el.querySelectorAll('input, textarea, select'), function (input) { input.disabled = !on; });
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-question-fields]'), function (fields) {
    var select = fields.querySelector('[data-qtype]');
    if (select) select.addEventListener('change', function () { sync(fields); });
    sync(fields);
  });

  var rubric = document.querySelector('[data-rubric]');
  if (rubric) {
    var body = rubric.querySelector('[data-rubric-rows]');
    var tpl = rubric.querySelector('template');
    var total = rubric.querySelector('[data-rubric-total]');
    function recalc() {
      var sum = 0;
      Array.prototype.forEach.call(body.querySelectorAll('input[name="criterion_points[]"]'), function (i) { sum += parseFloat(i.value) || 0; });
      if (total) total.textContent = (Math.round(sum * 100) / 100).toString();
    }
    rubric.addEventListener('input', recalc);
    rubric.addEventListener('click', function (e) {
      var add = e.target.closest('[data-rubric-add]');
      if (add && tpl) { body.appendChild(tpl.content.cloneNode(true)); var last = body.lastElementChild.querySelector('input'); if (last) last.focus(); recalc(); }
      var remove = e.target.closest('[data-rubric-remove]');
      if (remove) { remove.closest('[data-rubric-row]').remove(); recalc(); }
    });
    recalc();
  }
})();
