import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { env } from '@/server/env';

/**
 * Service worker de la plateforme et des portails (même script sur chaque hôte) :
 *  - pages publiques : réseau d'abord, copie de secours pour la consultation hors connexion ;
 *  - espaces privés (pro, collectivité, console, compte), API et actions : jamais mis en cache ;
 *  - ressources versionnées (/_next/static, polices) : cache d'abord ; médias : cache puis mise à jour ;
 *  - notifications push : affichage et ouverture de la bonne page au clic.
 * En développement, aucun cache (les ressources changent à chaque modification).
 */
/** Identifiant de la version déployée : renouvelle les caches de pages à chaque déploiement. */
function buildId(): string {
  try {
    return readFileSync(join(process.cwd(), '.next', 'BUILD_ID'), 'utf8')
      .trim()
      .replace(/[^A-Za-z0-9_-]/g, '');
  } catch {
    return 'dev';
  }
}
const VERSION = buildId();

function script(dev: boolean): string {
  return `'use strict';
const CACHE = 'tc-pages-${VERSION}';
const STATIC = 'tc-static-${VERSION}';
const MEDIA = 'tc-media-v1';
const OFFLINE = '/hors-ligne';
const DEV = ${dev ? 'true' : 'false'};
const PRIVATE = /^\\/(pro|collectivite|console|compte|connexion|invitation|mot-de-passe|demo|api|q)(\\/|$)/;

self.addEventListener('install', (event) => {
  event.waitUntil(
    (DEV ? Promise.resolve() : caches.open(CACHE).then((c) => c.add(new Request(OFFLINE, { cache: 'reload' })))).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('tc-') && ![CACHE, STATIC, MEDIA].includes(k)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

// Pages de secours (plateforme et portails) : jamais évincées par le nettoyage.
const isOffline = (u) => new URL(u).pathname.endsWith(OFFLINE);

async function trim(name, max) {
  const cache = await caches.open(name);
  const keys = (await cache.keys()).filter((k) => !isOffline(k.url));
  for (const k of keys.slice(0, Math.max(0, keys.length - max))) await cache.delete(k);
}

// Portail servi par chemin (/<territoire>/…) : sa page de secours, à ses couleurs, est mise de côté.
self.addEventListener('message', (event) => {
  const d = event.data;
  if (DEV || !d || d.type !== 'cache-offline' || typeof d.url !== 'string' || !/^\\/[a-z0-9-]+\\/hors-ligne$/.test(d.url)) return;
  event.waitUntil(
    caches
      .open(CACHE)
      .then((c) => c.match(d.url).then((hit) => hit || c.add(new Request(d.url, { cache: 'reload' }))))
      .catch(() => undefined),
  );
});

async function offlineFor(url) {
  const seg = url.pathname.split('/')[1];
  return (seg && (await caches.match('/' + seg + OFFLINE))) || (await caches.match(OFFLINE));
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (DEV || req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (req.mode === 'navigate') {
    if (PRIVATE.test(url.pathname)) {
      event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
      return;
    }
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok && res.type === 'basic') {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy)).then(() => trim(CACHE, 60));
          }
          return res;
        })
        .catch(async () => (await caches.match(req)) || (await offlineFor(url)) || Response.error()),
    );
    return;
  }
  if (url.pathname.startsWith('/_next/static/') || url.pathname.startsWith('/fonts/')) {
    event.respondWith(
      caches.match(req).then(
        (hit) =>
          hit ||
          fetch(req).then((res) => {
            if (res.ok) {
              const copy = res.clone();
              caches.open(STATIC).then((c) => c.put(req, copy));
            }
            return res;
          }),
      ),
    );
    return;
  }
  if (url.pathname.startsWith('/media/') && !url.pathname.includes('/private/')) {
    event.respondWith(
      caches.open(MEDIA).then(async (cache) => {
        const hit = await cache.match(req);
        const network = fetch(req)
          .then((res) => {
            if (res.ok) cache.put(req, res.clone()).then(() => trim(MEDIA, 200));
            return res;
          })
          .catch(() => hit);
        return hit || network;
      }),
    );
  }
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'terricom', body: event.data ? event.data.text() : '' };
  }
  event.waitUntil(
    self.registration.showNotification(data.title || 'Nouvelle notification', {
      body: data.body || '',
      icon: '/api/pwa/icon?size=192',
      badge: '/api/pwa/icon?size=96&badge=1',
      tag: data.tag,
      renotify: Boolean(data.tag),
      data: { url: data.url || '/' },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin);
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (new URL(client.url).pathname === target.pathname && 'focus' in client) return client.focus();
      }
      return self.clients.openWindow(target.href);
    }),
  );
});
`;
}

export function GET() {
  return new Response(script(env.NODE_ENV !== 'production'), {
    headers: {
      'content-type': 'application/javascript; charset=utf-8',
      'cache-control': 'no-cache, no-store, must-revalidate',
      'service-worker-allowed': '/',
    },
  });
}
