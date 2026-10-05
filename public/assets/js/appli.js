/* « L'appli du musée » : la bonne marche à suivre pour installer (déjà dans l'appli, bouton
   Android/ordinateur, Safari sur iPhone/iPad, autres navigateurs) et les notifications
   (abonnement, sujets, essai, désabonnement). */
(() => {
  'use strict';
  const SR = window.SR || {};
  const ua = navigator.userAgent;
  const ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const post = async (path, data) => {
    const r = await fetch('/api/push/' + path, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, credentials: 'same-origin', body: JSON.stringify(data) });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'HTTP ' + r.status);
    return d;
  };

  /* ---------------------------------------------------------- installation */
  const card = document.querySelector('[data-appli]');
  if (card) {
    const show = (state) => card.querySelectorAll('[data-state]').forEach((el) => { el.hidden = el.dataset.state !== state; });
    const update = () => {
      const app = SR.app || {};
      if (app.standalone) show('app');
      else if (app.installable) show('prompt');
      else if (ios) show('ios');
      else show('other');
    };
    card.querySelector('[data-install]')?.addEventListener('click', async () => {
      const p = SR.app && SR.app.prompt;
      if (!p) return;
      p.prompt();
      try { await p.userChoice; } catch (e) {}
      SR.app.prompt = null;
      SR.app.installable = false;
      update();
    });
    document.addEventListener('sr:app', update);
    update();
  }

  /* ---------------------------------------------------------- notifications */
  const box = document.querySelector('[data-push]');
  if (!box) return;
  const M = JSON.parse(box.dataset.msgs || '{}');
  const state = box.querySelector('[data-push-state]');
  const on = box.querySelector('[data-push-on]');
  const off = box.querySelector('[data-push-off]');
  const test = box.querySelector('[data-push-test]');
  const checks = [...box.querySelectorAll('[data-topic]')];
  const lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
  const topics = () => checks.filter((c) => c.checked).map((c) => c.value);
  const say = (key, err = false) => { state.textContent = M[key] || key; state.classList.toggle('is-error', err); };
  let sub = null;
  const ui = () => {
    on.hidden = !!sub;
    off.hidden = !sub;
    test.hidden = !sub;
  };
  const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
  const disable = (key) => { say(key); on.disabled = true; checks.forEach((c) => { c.disabled = true; }); };
  if (!supported) {
    // iPhone hors de l'appli installée : il faut d'abord l'installer.
    disable(ios && !(SR.app && SR.app.standalone) ? 'ios' : 'unsupported');
    return;
  }
  if (Notification.permission === 'denied') { disable('denied'); return; }
  const b64ToBytes = (s) => {
    const b = atob((s + '='.repeat((4 - (s.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(b, (c) => c.charCodeAt(0));
  };
  const registration = () => Promise.race([navigator.serviceWorker.ready, new Promise((_, no) => setTimeout(() => no(new Error('sw')), 8000))]);
  // Clé du musée et service worker préparés d'avance : sur iPhone, l'abonnement doit être demandé
  // tout de suite au toucher du bouton, sans attendre le réseau.
  let reg = null;
  let appKey = null;
  const ready = (async () => {
    reg = await registration();
    const r = await fetch('/api/push/cle', { headers: { Accept: 'application/json' } });
    appKey = b64ToBytes((await r.json()).key);
  })().catch(() => {});

  // Déjà abonné sur cet appareil ? Le musée redonne les sujets choisis (ou réenregistre l'abonnement).
  (async () => {
    try {
      await ready;
      if (!reg) throw new Error('sw');
      sub = await reg.pushManager.getSubscription();
      if (sub) {
        const d = await post('etat', { endpoint: sub.endpoint });
        if (d.subscribed) checks.forEach((c) => { c.checked = (d.topics || []).includes(c.value); });
        else await post('abonner', { sub: sub.toJSON(), topics: topics(), lang });
        say('on');
      }
    } catch (e) { /* pas encore de service worker : le bouton reste proposé */ }
    ui();
  })();

  on.addEventListener('click', async () => {
    on.disabled = true;
    say('busy');
    try {
      if (!reg || !appKey) await ready;
      if (!reg || !appKey) throw new Error('sw');
      // L'abonnement demande lui-même l'autorisation au navigateur (dans le geste de l'utilisateur).
      sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appKey }));
      await post('abonner', { sub: sub.toJSON(), topics: topics(), lang });
      say('on');
    } catch (e) {
      sub = null;
      if (Notification.permission === 'denied') say('denied', true);
      else say(Notification.permission === 'default' ? 'off' : 'error', Notification.permission !== 'default');
    } finally {
      on.disabled = false;
      ui();
    }
  });

  checks.forEach((c) => c.addEventListener('change', async () => {
    if (!sub) return;
    try { await post('sujets', { endpoint: sub.endpoint, topics: topics() }); say('saved'); } catch (e) { say('error', true); }
  }));

  test.addEventListener('click', async () => {
    test.disabled = true;
    try { await post('essai', { endpoint: sub.endpoint }); say('test'); } catch (e) { state.textContent = e.message; state.classList.add('is-error'); }
    setTimeout(() => { test.disabled = false; }, 4000);
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
