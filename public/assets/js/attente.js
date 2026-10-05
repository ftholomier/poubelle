/* Page d'attente du musée : « Prévenez-moi de l'ouverture ». L'appareil s'abonne aux nouvelles
   du musée (sujet « nouvelles ») ; le jour J, l'équipe envoie l'annonce depuis Communauté ›
   Notifications. Sur iPhone, les notifications passent par l'appli : il faut d'abord ajouter la
   page à l'écran d'accueil, puis l'ouvrir de là et toucher le bouton. */
(() => {
  'use strict';
  const box = document.querySelector('[data-wait-push]');
  if (!box) return;
  const M = JSON.parse(box.dataset.msgs || '{}');
  const on = box.querySelector('[data-wait-on]');
  const off = box.querySelector('[data-wait-off]');
  const state = box.querySelector('[data-wait-state]');
  const iosHelp = box.querySelector('[data-wait-ios]');
  const lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
  const ua = navigator.userAgent;
  const ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const say = (key, err = false) => { state.textContent = M[key] || key; state.classList.toggle('is-error', err); state.hidden = false; };
  const post = async (path, data) => {
    const r = await fetch('/api/push/' + path, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, credentials: 'same-origin', body: JSON.stringify(data) });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'HTTP ' + r.status);
    return d;
  };
  let sub = null;
  const ui = () => { on.hidden = !!sub; off.hidden = !sub; };
  box.hidden = false;

  const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
  if (!supported) {
    // iPhone dans Safari : d'abord l'écran d'accueil. Autre navigateur trop ancien : rien à proposer.
    if (ios && !standalone) { on.hidden = true; iosHelp.hidden = false; return; }
    box.hidden = true;
    return;
  }
  if (Notification.permission === 'denied') { on.disabled = true; say('denied'); return; }
  const b64ToBytes = (s) => {
    const b = atob((s + '='.repeat((4 - (s.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(b, (c) => c.charCodeAt(0));
  };
  // Service worker et clé du musée préparés d'avance : sur iPhone, l'abonnement doit partir dès le
  // toucher du bouton, sans attendre le réseau.
  let reg = null;
  let appKey = null;
  const ready = (async () => {
    reg = await Promise.race([navigator.serviceWorker.ready, new Promise((_, no) => setTimeout(() => no(new Error('sw')), 8000))]);
    const r = await fetch('/api/push/cle', { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error('cle');
    appKey = b64ToBytes((await r.json()).key);
  })().catch(() => {});

  // Déjà inscrit sur cet appareil ?
  (async () => {
    try {
      await ready;
      if (!reg) return;
      const s = await reg.pushManager.getSubscription();
      if (s) {
        const d = await post('etat', { endpoint: s.endpoint });
        if (!d.subscribed) await post('abonner', { sub: s.toJSON(), topics: ['nouvelles'], lang, src: 'attente' });
        sub = s;
        say('on');
      }
    } catch (e) { /* le bouton reste proposé */ }
    ui();
  })();

  on.addEventListener('click', async () => {
    on.disabled = true;
    say('busy');
    try {
      if (!reg || !appKey) await ready;
      if (!reg || !appKey) throw new Error('sw');
      sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appKey }));
      await post('abonner', { sub: sub.toJSON(), topics: ['nouvelles'], lang, src: 'attente' });
      say('on');
    } catch (e) {
      sub = null;
      if (Notification.permission === 'denied') say('denied', true);
      else say(Notification.permission === 'default' ? 'later' : 'error', Notification.permission !== 'default');
    } finally {
      on.disabled = false;
      ui();
    }
  });

  off.addEventListener('click', async () => {
    off.disabled = true;
    try {
      await post('desabonner', { endpoint: sub.endpoint });
      await sub.unsubscribe();
      sub = null;
      say('bye');
    } catch (e) { say('error', true); }
    off.disabled = false;
    ui();
  });
})();
