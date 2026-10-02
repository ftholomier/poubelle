/* Service worker : application installable, mode hors ligne et notifications push. */
const STATIC = VERSION + '-static';
const PAGES = VERSION + '-pages';
const MEDIA = VERSION + '-media';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC).then((c) => c.addAll(CORE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('apvs-') && !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

const trim = async (name, max) => {
  const c = await caches.open(name);
  const keys = await c.keys();
  for (let i = 0; i < keys.length - max; i++) await c.delete(keys[i]);
};

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (NO_CACHE.some((p) => url.pathname.startsWith(p))) return;

  // Ressources statiques versionnées : cache d'abord.
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(STATIC).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }
  // Photos : réponse en cache puis rafraîchissement en arrière-plan.
  if (url.pathname.startsWith('/media/')) {
    event.respondWith(caches.open(MEDIA).then(async (c) => {
      const hit = await c.match(req);
      const net = fetch(req).then((res) => { if (res.ok) { c.put(req, res.clone()); trim(MEDIA, 250); } return res; }).catch(() => hit);
      return hit || net;
    }));
    return;
  }
  // Pages : réseau d'abord, copie en cache, page hors ligne en dernier recours.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).then((res) => {
      // jamais les pages personnelles (back-office, espace pro, visiteur connecté) : en-têtes no-store / private
      if (res.ok && !/no-store|private/i.test(res.headers.get('cache-control') || '') && res.headers.get('content-type')?.includes('text/html')) {
        const copy = res.clone();
        caches.open(PAGES).then((c) => { c.put(req, copy); trim(PAGES, 60); });
      }
      return res;
    }).catch(async () => (await caches.match(req)) || (await caches.match('/hors-ligne/')) || Response.error()));
  }
});

self.addEventListener('push', (event) => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'Notification', body: event.data ? event.data.text() : '' }; }
  event.waitUntil(self.registration.showNotification(d.title || 'animateurpourvotresoirée', {
    body: d.body || '',
    icon: d.icon || '/assets/img/icon-192.png',
    badge: d.badge || '/assets/img/badge-72.png',
    tag: d.tag || undefined,
    renotify: !!d.tag,
    data: { url: d.url || '/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if (new URL(c.url).pathname === new URL(target, self.location.origin).pathname && 'focus' in c) return c.focus();
    }
    return self.clients.openWindow(target);
  }));
});
