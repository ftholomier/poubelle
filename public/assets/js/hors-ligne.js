/* Page « Hors connexion » de l'application du musée : langue de l'adresse demandée, bouton
   « Réessayer », liste des pages gardées par le service worker (les plus récentes d'abord). */
(() => {
  'use strict';
  const en = location.pathname === '/en' || location.pathname.startsWith('/en/');
  // Nom du musée : fin du titre de la page (« Hors connexion · <musée> », adapté au club par le serveur).
  const site = document.title.split(' · ').slice(1).join(' · ');
  if (en) {
    document.documentElement.lang = 'en';
    document.title = 'Offline' + (site ? ' · ' + site : '');
    document.querySelectorAll('[data-t]').forEach((el) => { el.textContent = el.dataset.t.split('|')[1]; });
  }
  document.querySelector('[data-retry]').addEventListener('click', () => location.reload());
  addEventListener('online', () => location.reload());
  if (!('caches' in window)) return;
  (async () => {
    const cache = await caches.open('sr-pages');
    const keys = (await cache.keys()).reverse().slice(0, 40);
    const items = [];
    for (const req of keys) {
      const u = new URL(req.url);
      if (u.pathname === location.pathname || (u.pathname.startsWith('/en/') || u.pathname === '/en') !== en) continue;
      const res = await cache.match(req);
      const html = res ? await res.text() : '';
      const m = html.match(/<title>([^<]*)<\/title>/i);
      const t = document.createElement('textarea');
      t.innerHTML = m ? m[1] : u.pathname;
      const esc = site.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      items.push([u.pathname, (site ? t.value.replace(new RegExp('\\s*[|·–-]\\s*' + esc + '\\s*$'), '') : t.value) || u.pathname]);
    }
    if (!items.length) return;
    const ul = document.querySelector('[data-list]');
    for (const [href, title] of items) {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = href;
      a.textContent = title;
      li.append(a);
      ul.append(li);
    }
    document.querySelector('[data-saved]').hidden = false;
  })().catch(() => {});
})();
