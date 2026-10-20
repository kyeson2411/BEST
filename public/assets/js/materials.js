/* Starts reading a newly uploaded material in the background and shows real progress.
   Without JavaScript a "Process now" button does the same. */
(function () {
  'use strict';
  var box = document.querySelector('[data-material-status]');
  if (!box || box.getAttribute('data-working') !== '1') return;
  var csrf = (window.Sibol && window.Sibol.csrf) || '';
  var badge = box.querySelector('[data-status-badge]');
  var detail = box.querySelector('[data-status-detail]');

  function render(s) {
    if (badge) {
      badge.innerHTML = '';
      var b = document.createElement('span');
      b.className = 'badge badge--' + (s.tone || 'neutral');
      b.textContent = s.label || s.status;
      badge.appendChild(b);
    }
    if (detail) {
      detail.textContent = s.status === 'ready' ? s.chunks + ' passages indexed.' : (s.error || 'Working…');
    }
  }

  fetch(box.getAttribute('data-process-url'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf }
  }).then(function (r) { return r.json(); })
    .then(function (s) {
      render(s);
      // Reload once finished so the extracted text preview and steps are shown.
      if (s.status === 'ready' || s.status === 'failed' || s.status === 'unsupported') window.location.reload();
    })
    .catch(function () {
      if (detail) detail.textContent = 'Could not start processing. Reload the page to try again.';
    });
})();
