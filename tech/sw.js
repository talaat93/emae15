/* EMAE Technicien — service worker : notifications push. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }
  var title = data.title || 'EMAE';
  event.waitUntil(self.registration.showNotification(title, {
    body: data.body || '',
    tag: data.tag || undefined,
    renotify: !!data.tag,
    icon: '../assets/img/icon-192.png',
    badge: '../assets/img/badge-96.png',
    data: { url: data.url || './dashboard.php' },
    vibrate: [120, 60, 120]
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || './dashboard.php';
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      if ('focus' in list[i]) { list[i].navigate(url); return list[i].focus(); }
    }
    return self.clients.openWindow(url);
  }));
});
