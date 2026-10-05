/* « L'appli du musée » : affiche la bonne marche à suivre selon l'appareil (déjà dans l'appli,
   bouton d'installation Android/ordinateur, Safari sur iPhone/iPad, autres navigateurs). */
(() => {
  'use strict';
  const card = document.querySelector('[data-appli]');
  if (!card) return;
  const SR = window.SR || {};
  const ua = navigator.userAgent;
  const ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
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
})();
