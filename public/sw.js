/*
 * Sochaux Rétro — service worker du musée (application installable).
 *
 * Pages : le réseau d'abord ; la copie gardée sert quand le réseau manque ou traîne (stade
 * plein, métro), sinon la page « Hors connexion ». Styles, scripts, polices (adresses
 * versionnées) et images redimensionnées : la copie gardée d'abord. Rien n'est gardé ni
 * intercepté pour le back-office, l'espace imprimeur, l'API, la boutique, les dons, les
 * formulaires et les vidéos : ces adresses passent toujours par le réseau.
 */
'use strict';

const SHELL = 'sr-shell-v1';
const PAGES = 'sr-pages';
const STATIC = 'sr-static';
const IMAGES = 'sr-images';
const KEEP = [SHELL, PAGES, STATIC, IMAGES];
const OFFLINE = '/hors-ligne.html';
const LIMITS = { [PAGES]: 80, [STATIC]: 120, [IMAGES]: 300 };
/** Au-delà, la copie gardée de la page est montrée pendant que le réseau termine. */
const SLOW_MS = 6000;

const SHELL_FILES = [
  OFFLINE,
  '/assets/js/hors-ligne.js',
  '/assets/fonts/big-shoulders-display-normal-latin.woff2',
  '/assets/fonts/newsreader-normal-latin.woff2',
  '/assets/img/app/192.png',
];

/** Jamais interceptées : back-office, espace imprimeur, API, vidéos, aperçu du site de l'association. */
const BYPASS = /^\/(?:en\/)?(?:admin|imprimeur|api|video|apercu-association)(?:\/|$)/;
/** Jamais gardées (paiement, formulaires, données personnelles) : sans réseau, la page « Hors connexion ». */
const NOSTORE = /^\/(?:en\/)?(?:boutique|faire-un-don|contribuer|contact|newsletter|souvenir)(?:\/|$)/;

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL);
    await cache.addAll(SHELL_FILES.map((u) => new Request(u, { cache: 'reload' })));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const name of await caches.keys()) {
      if (name.startsWith('sr-') && !KEEP.includes(name)) await caches.delete(name);
    }
    if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
    await self.clients.claim();
  })());
});

/** Réponse qu'on peut garder : complète, du site, et que le serveur n'interdit pas de garder. */
function keepable(res) {
  if (!res || res.status !== 200 || res.type !== 'basic' || res.redirected) return false;
  const cc = res.headers.get('Cache-Control') || '';
  return !/no-store|private/i.test(cc);
}

async function trim(name) {
  const cache = await caches.open(name);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - LIMITS[name]; i++) await cache.delete(keys[i]);
}

async function put(name, key, res) {
  try {
    const cache = await caches.open(name);
    await cache.put(key, res);
    await trim(name);
  } catch (e) { /* quota dépassé : tant pis, la page reste servie */ }
}

/** Application désactivée (Réglages › Application du musée) : copies effacées, service worker retiré. */
async function retire() {
  for (const name of await caches.keys()) if (name.startsWith('sr-')) await caches.delete(name);
  await self.registration.unregister();
}

async function page(event, url) {
  const key = url.origin + url.pathname;
  const network = (async () => {
    const res = (await event.preloadResponse) || (await fetch(event.request));
    if (keepable(res) && (res.headers.get('Content-Type') || '').includes('text/html')) {
      const copy = res.clone();
      event.waitUntil((async () => {
        const html = await copy.text();
        if (html.includes('data-app="0"')) return retire();
        if (!url.search) await put(PAGES, key, new Response(html, { headers: copy.headers }));
      })().catch(() => {}));
    }
    return res;
  })();
  // Adresse avec paramètres (tri, recherche) : jamais remplacée par la copie sans paramètres tant que le réseau répond.
  const kept = await caches.match(key, { cacheName: PAGES });
  try {
    if (!kept || url.search) return await network;
    // Réseau lent : la copie gardée s'affiche, la page à jour sera gardée pour la prochaine fois.
    const slow = new Promise((resolve) => setTimeout(() => resolve(null), SLOW_MS));
    const res = await Promise.race([network, slow]);
    if (res) return res;
    event.waitUntil(network.catch(() => null));
    return kept;
  } catch (e) {
    return kept || (await caches.match(OFFLINE, { cacheName: SHELL })) || Response.error();
  }
}

async function cacheFirst(event, name) {
  const hit = await caches.match(event.request, { cacheName: name }) || await caches.match(event.request, { cacheName: SHELL });
  if (hit) return hit;
  const res = await fetch(event.request);
  if (keepable(res)) event.waitUntil(put(name, event.request, res.clone()));
  return res;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET' || req.headers.has('range')) return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || BYPASS.test(url.pathname)) return;
  if (req.mode === 'navigate' && NOSTORE.test(url.pathname)) {
    event.respondWith((async () => {
      try {
        return (await event.preloadResponse) || (await fetch(req));
      } catch (e) {
        return (await caches.match(OFFLINE, { cacheName: SHELL })) || Response.error();
      }
    })());
  } else if (NOSTORE.test(url.pathname)) {
    return;
  } else if (req.mode === 'navigate') {
    event.respondWith(page(event, url));
  } else if (url.pathname.startsWith('/assets/')) {
    event.respondWith(cacheFirst(event, STATIC));
  } else if (/^\/media\/\d+\//.test(url.pathname)) {
    event.respondWith(cacheFirst(event, IMAGES));
  }
});
