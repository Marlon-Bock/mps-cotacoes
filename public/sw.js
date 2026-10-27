const CACHE = 'mps-cotacoes-v1';

self.addEventListener('install', e => {
  e.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// ── Web Push: recebe notificação mesmo com browser fechado ────────────────────
self.addEventListener('push', e => {
  var data = {};
  try { data = e.data ? e.data.json() : {}; } catch (_) {}
  var title   = data.title || 'Mesquita Prime';
  var options = {
    body:               data.body  || '',
    icon:               data.icon  || '/assets/img/logo-mark.png',
    badge:              '/assets/img/logo-mark.png',
    tag:                'notif-' + (data.id || Date.now()),
    renotify:           true,
    requireInteraction: false,
    vibrate:            [200, 100, 200],
    data:               { url: data.url || '/' }
  };
  e.waitUntil(self.registration.showNotification(title, options));
});

// ── Clique na notificação ─────────────────────────────────────────────────────
self.addEventListener('notificationclick', e => {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) ? e.notification.data.url : '/';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (var c of list) {
        if (c.url.indexOf(url) !== -1 && 'focus' in c) return c.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});

// ── Mensagem do JS da página (enquanto aba está aberta) ───────────────────────
self.addEventListener('message', e => {
  if (!e.data || e.data.type !== 'SHOW_NOTIF') return;
  var d = e.data.payload || {};
  self.registration.showNotification(d.titulo || 'Mesquita Prime', {
    body:               d.mensagem || '',
    icon:               d.icon || '/assets/img/logo-mark.png',
    badge:              '/assets/img/logo-mark.png',
    tag:                'notif-' + (d.id || Date.now()),
    renotify:           false,
    requireInteraction: false,
    data:               { url: d.destUrl || '/' }
  });
});

// ── Cache de assets estáticos ─────────────────────────────────────────────────
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('/api/')) return;
  if (e.request.url.includes('.php')) return;
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});
