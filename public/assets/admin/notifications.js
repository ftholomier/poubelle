/* Notifications : aperçu en direct et compteurs de caractères du formulaire d'envoi. */
(() => {
  'use strict';
  const form = document.querySelector('[data-notif-form]');
  if (!form) return;
  const def = { title: 'Titre de la notification', body: 'Le texte apparaît ici.' };
  form.querySelectorAll('[data-preview]').forEach((el) => {
    const k = el.dataset.preview;
    const out = form.querySelector(`[data-out="${k}"]`);
    const count = form.querySelector(`[data-count="${k}"]`);
    const sync = () => {
      out.textContent = el.value.trim() || def[k];
      if (count) count.textContent = el.value.length + '/' + el.maxLength;
    };
    el.addEventListener('input', sync);
    sync();
  });
})();
