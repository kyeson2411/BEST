/* Anti-cheating on a quiz attempt or an assignment's typed response ([data-anticheat]). Copy, cut, paste,
   drag-and-drop and the right-click menu are blocked in the guarded area, with a short note. On a quiz
   (data-anticheat-url), leaving the page for another tab, window or app is sent to the server as it happens,
   and the time away when the student comes back, who then sees how many times it was recorded. A browser
   cannot stop anyone leaving, so this records it; leaving because the quiz itself navigates (Submit, time up,
   a link) is not counted. */
(function () {
  'use strict';
  var root = document.querySelector('[data-anticheat]');
  if (!root) return;
  var S = window.Sibol.strings(root);
  var area = document.querySelector(root.getAttribute('data-anticheat-target')) || root;
  var url = root.getAttribute('data-anticheat-url');
  var note = root.querySelector('[data-anticheat-note]');
  var dialog = root.querySelector('[data-anticheat-dialog]');
  var countText = root.querySelector('[data-anticheat-count]');
  var token = document.querySelector('meta[name="csrf-token"]');
  var left = parseInt(root.getAttribute('data-anticheat-left') || '0', 10) || 0;
  var awaySince = null;
  var unloading = false;
  var noteTimer = null;

  function send(event, seconds) {
    if (!url) return;
    var data = new FormData();
    data.append('_token', token ? token.getAttribute('content') : '');
    data.append('event', event);
    if (seconds) data.append('seconds', String(seconds));
    try {
      // keepalive: the request still goes out while the page is hidden or closing.
      fetch(url, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true, headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
        .catch(function () { /* offline: nothing more to do */ });
    } catch (e) { /* fetch unavailable */ }
  }

  function blocked() {
    if (!note) return;
    note.textContent = S('blocked');
    note.hidden = false;
    clearTimeout(noteTimer);
    noteTimer = setTimeout(function () { note.hidden = true; }, 5000);
  }

  ['copy', 'cut', 'paste', 'drop', 'dragstart', 'contextmenu'].forEach(function (type) {
    area.addEventListener(type, function (event) {
      event.preventDefault();
      if (type === 'paste' || type === 'drop') send('paste');
      blocked();
    });
  });

  if (!url) return;

  function away() {
    if (awaySince !== null || unloading) return;
    awaySince = Date.now();
    send('left');
  }
  function returned() {
    if (awaySince === null) return;
    var seconds = Math.round((Date.now() - awaySince) / 1000);
    awaySince = null;
    left++;
    send('back', seconds);
    if (countText) countText.textContent = S('left', { count: left });
    if (dialog && typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') away(); else returned();
  });
  window.addEventListener('blur', away);
  window.addEventListener('focus', returned);
  // The quiz leaving the page by itself (Submit, time up, a link): not the student going elsewhere.
  window.addEventListener('beforeunload', function () {
    unloading = true;
    setTimeout(function () { unloading = false; }, 1500);
  });
  window.addEventListener('pagehide', function () { unloading = true; });
})();
