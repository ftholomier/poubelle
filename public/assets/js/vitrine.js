/* Sochaux Rétro — site de l'association : menu mobile, formulaire d'adhésion, impression. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---------------------------------------------------------- menu mobile */
  const burger = $('[data-vburger]'), panel = $('[data-vmobile]');
  if (burger && panel) {
    const set = (on) => {
      panel.hidden = !on;
      burger.setAttribute('aria-expanded', on ? 'true' : 'false');
      const icon = $('[data-vburger-icon]', burger);
      if (icon) icon.textContent = on ? '✕' : '☰';
    };
    burger.addEventListener('click', () => set(panel.hidden));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { set(false); burger.focus(); } });
    addEventListener('resize', () => { if (innerWidth > 1100 && !panel.hidden) set(false); });
  }

  /* Sous-menus : Échap referme celui qui a le focus. */
  $$('.vh__item.has-sub').forEach(li => li.addEventListener('keydown', e => {
    if (e.key === 'Escape') { $('a', li)?.focus(); li.classList.add('is-closed'); setTimeout(() => li.classList.remove('is-closed'), 400); }
  }));

  /* ---------------------------------------------------------- adhésion */
  const form = $('[data-adh]');
  if (form) {
    const radios = $$('input[name=tariff]', form);
    const free = $$('[data-free-for]', form);
    const family = $('[data-family]', form);
    const submit = $('[data-adh-submit]', form);
    const amount = $('input[name=amount]', form);
    const update = () => {
      const r = radios.find(x => x.checked);
      free.forEach(f => f.classList.toggle('is-on', !!r && r.dataset.free !== undefined && f.dataset.freeFor === r.value));
      family?.classList.toggle('is-on', !!r && /famil/.test(r.value));
      let total = r ? +r.dataset.amount : 0;
      if (r && r.dataset.free !== undefined && amount) total = Math.max(total, parseInt(amount.value, 10) || 0);
      const online = $$('input[name=method]', form).find(x => x.checked)?.value !== 'cheque';
      if (submit) submit.textContent = total ? (online ? 'Payer ' + total + ' € et adhérer' : 'Valider mon adhésion (' + total + ' €)') : 'Valider mon adhésion';
    };
    form.addEventListener('change', update);
    amount?.addEventListener('input', update);
    update();
  }

  /* ---------------------------------------------------------- impression */
  $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
})();
