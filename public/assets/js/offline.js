/* Class files kept on this device for reading without a connection. A student chooses the files to keep
   ([data-offline-save] on a class's Materials tab); the service worker (public/sw.js) opens a kept copy when
   there is no connection, and app.js removes them all on sign-out. The list of kept files (address, title,
   class) is stored in the same cache, so it leaves with them. The offline page shows it ([data-offline-list]). */
(function () {
  'use strict';

  var doc = document;
  var root = doc.querySelector('[data-offline]');
  if (!root || !window.caches || !('serviceWorker' in navigator) || !window.isSecureContext) return;
  var S = window.Sibol.strings(root);
  var KEPT = 'sibol-offline-materials';
  var base = (doc.querySelector('meta[name="app-base"]') || {}).content || '';
  var INDEX = location.origin + base + '/offline-index.json';
  var queue = Promise.resolve();

  function $all(sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

  /* One address per file, whatever the query (a kept copy is found from the download link too). */
  function keyFor(href) {
    var url = new URL(href, location.href);
    return url.origin + url.pathname;
  }

  function readIndex() {
    return caches.match(INDEX, { cacheName: KEPT }).then(function (r) { return r ? r.json() : []; })
      .then(function (items) { return Array.isArray(items) ? items : []; }, function () { return []; });
  }

  function writeIndex(cache, items) {
    return cache.put(INDEX, new Response(JSON.stringify(items), { headers: { 'Content-Type': 'application/json' } }));
  }

  /* Changes run one after another, so two quick taps never lose an entry in the list. */
  function inTurn(fn) {
    queue = queue.then(fn, fn);
    return queue;
  }

  function keep(btn) {
    var href = btn.getAttribute('data-offline-save');
    var key = keyFor(href);
    return fetch(href, { credentials: 'same-origin' }).then(function (response) {
      // A sign-in page after an expired session is not the file.
      if (!response.ok || response.redirected) throw new Error(String(response.status));
      return caches.open(KEPT).then(function (cache) {
        return cache.put(key, response).then(readIndex).then(function (items) {
          items = items.filter(function (item) { return item.url !== key; });
          items.push({ url: key, title: btn.getAttribute('data-offline-title') || '', place: root.getAttribute('data-offline-class') || '' });
          return writeIndex(cache, items);
        });
      });
    });
  }

  function release(btn) {
    var key = keyFor(btn.getAttribute('data-offline-save'));
    return caches.open(KEPT).then(function (cache) {
      return cache.delete(key).then(readIndex).then(function (items) {
        return writeIndex(cache, items.filter(function (item) { return item.url !== key; }));
      });
    });
  }

  /* Keep offline toggles on a class's materials. */
  var buttons = $all('[data-offline-save]');
  if (buttons.length) {
    readIndex().then(function (items) {
      var kept = items.map(function (item) { return item.url; });
      buttons.forEach(function (btn) {
        btn.setAttribute('aria-pressed', kept.indexOf(keyFor(btn.getAttribute('data-offline-save'))) >= 0 ? 'true' : 'false');
        btn.hidden = false;
      });
      $all('[data-offline-hint]').forEach(function (el) { el.hidden = false; });
    });
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.getAttribute('aria-busy') === 'true') return;
        var pressed = btn.getAttribute('aria-pressed') === 'true';
        var title = btn.getAttribute('data-offline-title') || '';
        btn.setAttribute('aria-busy', 'true');
        inTurn(function () { return pressed ? release(btn) : keep(btn); }).then(function () {
          btn.setAttribute('aria-pressed', pressed ? 'false' : 'true');
          window.Sibol.toast(pressed ? S('released', { title: title }) : S('kept', { title: title }));
        }, function () {
          window.Sibol.toast(pressed ? S('release_failed') : S('keep_failed'), 'error');
        }).then(function () { btn.removeAttribute('aria-busy'); });
      });
    });
  }

  /* The offline page: what is kept on this device, and trying the page again. */
  var list = root.querySelector('[data-offline-list]');
  if (list) {
    readIndex().then(function (items) {
      items = items.filter(function (item) { return typeof item.url === 'string' && item.url.indexOf(location.origin + base + '/') === 0; });
      items.forEach(function (item) {
        var li = doc.createElement('li');
        li.className = 'row';
        var main = doc.createElement('div');
        main.className = 'row__main';
        var link = doc.createElement('a');
        link.className = 'row__title';
        link.href = item.url;
        link.textContent = item.title || item.url;
        main.appendChild(link);
        if (item.place) {
          var meta = doc.createElement('div');
          meta.className = 'row__meta';
          var place = doc.createElement('span');
          place.textContent = item.place;
          meta.appendChild(place);
          main.appendChild(meta);
        }
        li.appendChild(main);
        list.appendChild(li);
      });
      list.hidden = items.length === 0;
      $all('[data-offline-empty]').forEach(function (el) { el.hidden = items.length > 0; });
    });
  }
  $all('[data-offline-retry]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      location.reload();
    });
  });
})();
