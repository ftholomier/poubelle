/* Service worker du mode écran (servi en /ecran/sw.js, portée /ecran/) : la page de l'écran
   passe par le réseau et sa dernière version est gardée ; sans réseau, la copie gardée repart
   (redémarrage de la borne pendant une coupure). Styles, scripts, polices et photos : la copie
   gardée d'abord. Rien d'autre n'est gardé ; aucune donnée personnelle. */
'use strict';
const CACHE = 'musee-ecran-v1';
const MAX = 250;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

async function keep(req, res) {
  const c = await caches.open(CACHE);
  await c.put(req, res);
  const keys = await c.keys();
  for (let i = 0; i < keys.length - MAX; i++) await c.delete(keys[i]);
}

self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET') return;
  const u = new URL(r.url);
  if (u.origin !== location.origin) return;
  if (r.mode === 'navigate' && u.pathname.startsWith('/ecran/')) {
    e.respondWith(fetch(r).then(res => {
      if (res.ok) keep(r, res.clone());
      return res;
    }).catch(() => caches.match(r).then(m => m || new Response('<!doctype html><meta charset="utf-8"><body style="background:#0e1f4d;color:#fff;font:48px sans-serif;display:grid;place-items:center;height:100vh;margin:0">Connexion au musée en cours…<script>setTimeout(()=>location.reload(),30000)</script>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
    return;
  }
  if (/^\/(assets|media|m)\//.test(u.pathname)) {
    e.respondWith(caches.match(r).then(m => m || fetch(r).then(res => {
      if (res.ok) keep(r, res.clone());
      return res;
    })));
  }
});
