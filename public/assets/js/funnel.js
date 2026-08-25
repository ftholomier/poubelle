/* =============================================================
   Tunnel de candidature — 4 étapes, capture progressive.
   Chaque étape validée est enregistrée côté serveur : un
   abandon en cours de route reste un contact exploitable.
   ============================================================= */
(function () {
  'use strict';

  const form = document.getElementById('apply-form');
  if (!form) return;

  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  const panes = $$('.funnel-pane', form);
  const steps = $$('.funnel-step');
  const bar = $('.funnel-progress i');
  const startedAt = Math.floor(Date.now() / 1000);
  let current = 0;
  let draftId = null;
  try { draftId = sessionStorage.getItem('si_draft'); } catch (e) {}

  /* --------------------------------------------------- Validation */

  const RULES = {
    name: (v) => v.trim().length >= 2 || 'Merci d’indiquer votre nom.',
    email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || 'Adresse e-mail invalide.',
    phone: (v) => v.replace(/\D/g, '').length >= 9 || 'Numéro de téléphone invalide.',
    area: (v) => v.trim().length >= 2 || 'Indiquez la ville ou le secteur souhaité.'
  };

  function setError(input, message) {
    const field = input.closest('.field') || input.closest('fieldset');
    if (!field) return;
    field.classList.toggle('has-error', Boolean(message));
    const old = $('.field__error', field);
    if (old) old.remove();
    if (message) {
      const p = document.createElement('p');
      p.className = 'field__error';
      p.textContent = message;
      field.appendChild(p);
    }
  }

  function validatePane(index) {
    let ok = true;
    let firstBad = null;
    $$('[data-required]', panes[index]).forEach((input) => {
      let message = '';
      if (input.type === 'radio') {
        const group = form.elements[input.name];
        const checked = group && (group.value || Array.from(group).some((r) => r.checked));
        if (!checked) message = 'Merci de faire un choix.';
      } else if (input.type === 'checkbox') {
        if (!input.checked) message = 'Cette validation est nécessaire.';
      } else {
        const rule = RULES[input.name];
        const res = rule ? rule(input.value) : (input.value.trim() !== '' || 'Ce champ est requis.');
        if (res !== true) message = res;
      }
      setError(input, message);
      if (message) { ok = false; firstBad = firstBad || input; }
    });
    if (firstBad) firstBad.focus();
    return ok;
  }

  /* ------------------------------------------------- Navigation */

  function show(index) {
    current = Math.max(0, Math.min(panes.length - 1, index));
    panes.forEach((p, i) => { p.hidden = i !== current; });
    steps.forEach((s, i) => {
      s.classList.toggle('is-active', i === current);
      s.classList.toggle('is-done', i < current);
    });
    if (bar) bar.style.width = ((current + 1) / panes.length * 100) + '%';

    const top = form.getBoundingClientRect().top + window.scrollY - 110;
    if (window.scrollY > top + 60) window.scrollTo({ top: top, behavior: 'smooth' });

    if (current === panes.length - 1) fillRecap();
  }

  function values() {
    const data = {};
    new FormData(form).forEach((v, k) => {
      if (k !== 'cv' && k !== '_csrf') data[k] = typeof v === 'string' ? v : '';
    });
    return data;
  }

  /** Sauvegarde silencieuse de l'étape franchie. */
  async function saveStep(step) {
    try {
      const payload = Object.assign(values(), { step: step, draft_id: draftId || '', _csrf: SI.csrf });
      const res = await fetch(SI.base + '/api/apply/step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SI.csrf },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.draft_id) {
        draftId = data.draft_id;
        try { sessionStorage.setItem('si_draft', draftId); } catch (e) {}
        const hidden = form.elements.draft_id;
        if (hidden) hidden.value = draftId;
      }
    } catch (e) { /* la progression ne doit jamais être bloquée */ }
  }

  function fillRecap() {
    const v = values();
    const map = {
      'recap-name': v.name,
      'recap-email': v.email,
      'recap-phone': v.phone,
      'recap-area': v.area,
      'recap-situation': v.situation,
      'recap-availability': v.availability
    };
    Object.keys(map).forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.textContent = map[id] || '—';
    });
  }

  $$('[data-next]', form).forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!validatePane(current)) return;
      saveStep(current + 1);
      if (window.siTrack) window.siTrack('funnel_step_' + (current + 1));
      show(current + 1);
    });
  });

  $$('[data-prev]', form).forEach((btn) => {
    btn.addEventListener('click', () => show(current - 1));
  });

  // Entrée = étape suivante (sauf sur la zone de texte et le dernier écran).
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA') return;
    if (current < panes.length - 1) {
      e.preventDefault();
      $('[data-next]', panes[current])?.click();
    }
  });

  /* ------------------------------------------------ Champ fichier */

  const drop = $('.filedrop', form);
  if (drop) {
    const input = $('input[type="file"]', drop);
    const label = $('[data-file-name]', drop);
    const setName = (name) => { if (label) label.textContent = name || 'Glissez votre CV ici ou cliquez pour le choisir'; };
    drop.addEventListener('click', () => input.click());
    drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => setName(input.files[0] ? input.files[0].name : ''));
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', (e) => {
      if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; setName(e.dataTransfer.files[0].name); }
    });
  }

  /* --------------------------------------------------- Soumission */

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validatePane(current)) return;

    const btn = $('[type="submit"]', form);
    const label = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = 'Envoi en cours…'; }

    const fd = new FormData(form);
    fd.set('elapsed', String(Math.floor(Date.now() / 1000) - startedAt));
    fd.set('draft_id', draftId || '');
    try {
      const sim = sessionStorage.getItem('si_sim');
      if (sim) {
        const s = JSON.parse(sim);
        Object.keys(s).forEach((k) => fd.set('simulation[' + k + ']', s[k]));
      }
    } catch (err) {}

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-Token': SI.csrf, 'Accept': 'application/json' },
        body: fd
      });
      const data = await res.json();
      if (data.ok) {
        try { sessionStorage.removeItem('si_draft'); } catch (err) {}
        location.href = data.redirect || SI.base + '/merci';
        return;
      }
      Object.keys(data.errors || {}).forEach((name) => {
        const input = form.elements[name];
        if (input) setError(input.length ? input[0] : input, data.errors[name]);
      });
      if (window.siToast) window.siToast(data.error || 'Merci de vérifier vos informations.', 'error');
      // Ramener le visiteur sur l'écran qui contient la première erreur.
      const bad = $('.field.has-error', form);
      if (bad) {
        const pane = bad.closest('.funnel-pane');
        const idx = panes.indexOf(pane);
        if (idx > -1 && idx !== current) show(idx);
      }
    } catch (err) {
      if (window.siToast) window.siToast('Connexion impossible. Réessayez dans un instant.', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = label; }
    }
  });

  show(0);
})();
