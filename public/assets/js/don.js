/* Faire un don : choix du montant, impact, étape coordonnées, envoi vers le paiement sécurisé. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Jauge : remplissage animé (maquette : 1,6 s après 300 ms)
  const bar = $('[data-gauge-bar]');
  if (bar && motion) bar.classList.add('is-anim');

  // Retour « paiement en cours » : quelques rechargements automatiques
  if ($('[data-pending-reload]')) {
    let n = 0;
    try { n = +(sessionStorage.getItem('don-reload') || 0); } catch (e) { /* stockage indisponible */ }
    if (n < 4) {
      try { sessionStorage.setItem('don-reload', String(n + 1)); } catch (e) { /* idem */ }
      setTimeout(() => location.reload(), 4000);
    }
  }
  $$('[data-reload]').forEach(a => a.addEventListener('click', ev => { ev.preventDefault(); location.reload(); }));
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', ev => { if (!confirm(f.dataset.confirm)) ev.preventDefault(); }));

  const form = $('[data-don-form]');
  if (!form) return;
  const ts = $('input[name=_ts]', form);
  if (ts) ts.value = String(Math.floor(Date.now() / 1000));
  let i18n = {};
  try { i18n = JSON.parse(form.dataset.i18n || '{}'); } catch (e) { /* valeurs par défaut */ }
  const min = +form.dataset.min || 1, max = +form.dataset.max || 5000;
  const custom = $('[data-custom]', form);
  const impactLine = $('[data-impact-line]', form);
  const ctaAmount = $('[data-cta-amount]', form);
  const ctaMonth = $('[data-cta-month]', form);
  const step2 = $('[data-step2]', form);
  const errBox = $('[data-don-error]', form);
  const cta = $('[data-cta]', form);
  const tiers = $$('.dtier', form);
  const fill = (s, n) => String(s || '').replace('{n}', n);

  const state = () => {
    const freq = ($('input[name=frequency]:checked', form) || {}).value || 'once';
    const tier = $('input[name=amount]:checked', form);
    const c = parseInt(custom.value, 10);
    const amount = c > 0 ? c : (tier ? +tier.value : 0);
    return { monthly: freq === 'month', amount, isCustom: c > 0, tier };
  };
  const render = () => {
    const s = state();
    tiers.forEach(t => t.classList.toggle('is-on', !s.isCustom && t.contains(s.tier)));
    let impact;
    if (!s.isCustom && s.tier) impact = s.tier.dataset.impact + '.';
    else if (s.amount >= 10) impact = fill(Math.floor(s.amount / 10) > 1 ? i18n.many : i18n.one, Math.floor(s.amount / 10));
    else impact = i18n.small || '';
    if (impactLine) impactLine.textContent = (s.monthly ? (i18n.monthPrefix || '') : '') + impact;
    // Dons pas encore ouverts : pas de bouton d'envoi ni de montant à afficher.
    if (ctaAmount) ctaAmount.textContent = s.amount || '…';
    if (ctaMonth) ctaMonth.hidden = !s.monthly;
  };

  custom.addEventListener('input', () => {
    custom.value = custom.value.replace(/\D/g, '').slice(0, 5);
    if (custom.value) $$('input[name=amount]', form).forEach(r => { r.checked = false; });
    render();
  });
  $$('input[name=amount]', form).forEach(r => r.addEventListener('change', () => { custom.value = ''; render(); }));
  $$('input[name=frequency]', form).forEach(r => r.addEventListener('change', render));
  render();

  // Reçu fiscal : adresse obligatoire
  const receipt = $('[data-receipt]', form), rf = $('[data-receipt-fields]', form);
  if (receipt && rf) {
    const sync = () => {
      rf.hidden = !receipt.checked;
      $$('input', rf).forEach(i => { if (i.name !== 'country') i.required = receipt.checked; });
      const last = $('[data-last]', form);
      if (last) last.required = receipt.checked;
    };
    receipt.addEventListener('change', sync);
    sync();
  }

  // Mur des donateurs : nom proposé « Prénom N. »
  const wall = $('[data-wall]', form), wf = $('[data-wall-field]', form);
  if (wall && wf) {
    wall.addEventListener('change', () => {
      wf.hidden = !wall.checked;
      const input = $('input', wf);
      if (wall.checked && !input.value) {
        const first = (form.elements.first || {}).value || '', last = (form.elements.last || {}).value || '';
        input.value = (first.trim() + (last.trim() ? ' ' + last.trim()[0].toUpperCase() + '.' : '')).trim();
      }
      if (wall.checked) input.focus();
    });
  }

  // Étape 2 repliée tant que le montant n'est pas choisi
  if (step2 && !$('.alert--error:not([hidden])', form)) step2.classList.add('is-collapsed');

  const showError = msg => {
    errBox.textContent = msg;
    errBox.hidden = !msg;
    if (msg) errBox.scrollIntoView({ behavior: motion ? 'smooth' : 'auto', block: 'center' });
  };

  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    if (!step2) return;
    const s = state();
    if (!s.amount || s.amount < min) { showError(i18n.min); return; }
    if (s.amount > max) { showError(i18n.max); return; }
    showError('');
    if (step2.classList.contains('is-collapsed')) {
      step2.classList.remove('is-collapsed');
      if (motion) step2.classList.add('is-opening');
      const first = $('input[name=first]', step2);
      if (first) first.focus({ preventScroll: true });
      step2.scrollIntoView({ behavior: motion ? 'smooth' : 'auto', block: 'nearest' });
      return;
    }
    if (!form.reportValidity()) return;
    const data = {};
    new FormData(form).forEach((v, k) => { data[k] = v; });
    cta.classList.add('is-busy');
    cta.disabled = true;
    const thanks = $('[data-thanks]', form);
    try {
      const r = await fetch(form.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data), credentials: 'same-origin' });
      const j = await r.json().catch(() => ({}));
      if (j.ok && j.url) {
        thanks.hidden = false;
        setTimeout(() => { location.href = j.url; }, motion ? 700 : 0);
        return;
      }
      showError(j.error || i18n.error);
    } catch (e) {
      showError(i18n.error);
    }
    cta.classList.remove('is-busy');
    cta.disabled = false;
  });
})();
