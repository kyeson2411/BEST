/* Sibol's service worker, registered by app.js. It keeps the stylesheet, scripts, fonts and images, and an
   offline page, so the app opens quickly and says plainly when there is no connection. Pages, answers and
   quizzes always come from the server and are never stored. The only class content kept on a device is what a
   person chooses to keep for offline reading (offline.js); app.js removes it when they sign out. */
'use strict';

var SHELL = 'sibol-shell-v1'; // the offline page and the files it needs
var STATIC = 'sibol-static-v1'; // other stylesheets, scripts, fonts and images, as pages use them
var KEPT = 'sibol-offline-materials'; // materials kept for offline reading (offline.js, app.js)
var MAX_STATIC = 120;
var scope = self.registration.scope;
var ASSETS = new URL(scope).pathname + 'assets/';
var OFFLINE_PAGE = scope + 'offline';

function isAsset(url) {
  return url.origin === self.location.origin && url.pathname.indexOf(ASSETS) === 0;
}

function isMaterialFile(url) {
  return /\/materials\/\d+\/file$/.test(url.pathname);
}

/* Files whose address carries ?v= change address whenever they change; the few without it (fonts) are
   checked again in the background, through the browser's own cache. */
function versioned(url) {
  return new URL(url).searchParams.has('v');
}

/* The offline page, in the language of the person's session, and every local file it links to. Files the
   page no longer uses are dropped, so the shell never grows. */
function storeShell() {
  return caches.open(SHELL).then(function (cache) {
    return fetch(OFFLINE_PAGE, { credentials: 'same-origin', cache: 'no-store' }).then(function (response) {
      if (!response.ok) throw new Error(String(response.status));
      return response.clone().text().then(function (html) {
        // The page names one file per image (no srcset), so only files it shows are fetched.
        var files = [];
        html.replace(/(?:href|src)="([^"]+)"/g, function (match, value) {
          var url = new URL(value.replace(/&amp;/g, '&'), scope);
          url.hash = '';
          if (isAsset(url) && files.indexOf(url.href) < 0) files.push(url.href);
          return match;
        });
        return Promise.all(files.map(function (file) {
          return cache.match(file).then(function (hit) { return hit && versioned(file) ? hit : cache.add(file); });
        })).then(function () {
          return cache.put(OFFLINE_PAGE, response);
        }).then(function () {
          files.push(OFFLINE_PAGE);
          return cache.keys();
        }).then(function (keys) {
          return Promise.all(keys.filter(function (key) { return files.indexOf(key.url) < 0; }).map(function (key) {
            return cache.delete(key);
          }));
        });
      });
    });
  });
}

function offlinePage() {
  return caches.match(OFFLINE_PAGE, { cacheName: SHELL }).then(function (page) { return page || Response.error(); });
}

/* Keep the cache of other files small: the least recently stored go first. */
function trim(cache) {
  return cache.keys().then(function (keys) {
    return Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_STATIC)).map(function (key) { return cache.delete(key); }));
  });
}

function fromNetwork(event, request) {
  return fetch(request).then(function (response) {
    if (response.ok) {
      var copy = response.clone();
      event.waitUntil(caches.open(STATIC).then(function (cache) {
        return cache.put(request, copy).then(function () { return trim(cache); });
      }));
    }
    return response;
  });
}

self.addEventListener('install', function (event) {
  event.waitUntil(storeShell().then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
  var current = [SHELL, STATIC, KEPT];
  event.waitUntil(caches.keys().then(function (names) {
    return Promise.all(names.filter(function (name) {
      return name.indexOf('sibol-') === 0 && current.indexOf(name) < 0;
    }).map(function (name) { return caches.delete(name); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') return;
  var url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Stylesheet, scripts, fonts and images: the stored copy first.
  if (isAsset(url)) {
    event.respondWith(caches.open(STATIC).then(function (cache) { return cache.match(request); }).then(function (hit) {
      return hit || caches.match(request, { cacheName: SHELL });
    }).then(function (hit) {
      if (!hit) return fromNetwork(event, request);
      if (!versioned(request.url)) event.waitUntil(fromNetwork(event, request).catch(function () { return null; }));
      return hit;
    }));
    return;
  }

  // A material file comes from the server while there is a connection (and refreshes a kept copy), and from
  // the copy kept on this device when there is none.
  if (isMaterialFile(url)) {
    var key = url.origin + url.pathname;
    event.respondWith(fetch(request).then(function (response) {
      if (response.ok && !response.redirected && !url.search) {
        var copy = response.clone();
        event.waitUntil(caches.match(key, { cacheName: KEPT }).then(function (hit) {
          return hit ? caches.open(KEPT).then(function (cache) { return cache.put(key, copy); }) : null;
        }));
      }
      return response;
    }, function () {
      return caches.match(key, { cacheName: KEPT }).then(function (hit) { return hit || offlinePage(); });
    }));
    return;
  }

  // A material's reading page is never stored; with no connection it falls back to the kept file, if there is one.
  var reading = /^(.*\/materials\/\d+)\/read$/.exec(url.pathname);
  if (reading && request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(function () {
      return caches.match(url.origin + reading[1] + '/file', { cacheName: KEPT }).then(function (hit) { return hit || offlinePage(); });
    }));
    return;
  }

  // Any other page comes from the server, or is the offline page when there is no connection. A page in
  // another language than the stored offline page brings a fresh copy of it.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).then(function (response) {
      var language = response.headers.get('Content-Language');
      if (language) {
        event.waitUntil(caches.match(OFFLINE_PAGE, { cacheName: SHELL }).then(function (page) {
          return page && page.headers.get('Content-Language') === language ? null : storeShell();
        }).catch(function () { return null; }));
      }
      return response;
    }, offlinePage));
  }
});
