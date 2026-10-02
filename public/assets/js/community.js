/* Communauté : contact selon la demande, assistant de contribution, inscription newsletter. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const EN = document.documentElement.lang === 'en';
  const T = (fr, en) => (EN ? en : fr);

  // Contact : les champs changent avec l'objet de la demande, sans recharger la page.
  const cform = $('[data-contact-form]');
  if (cform) {
    $$('[data-reason]').forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      const r = a.dataset.reason;
      $$('[data-reason]').forEach(x => { const on = x === a; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); $('b', x).textContent = on ? '●' : '→'; });
      $('[data-reason-input]', cform).value = r;
      $('[data-reason-label]', cform).textContent = $('span', a).textContent;
      $$('[data-only]', cform).forEach(f => { f.hidden = f.dataset.only !== r; });
      $$('[data-not]', cform).forEach(f => { f.hidden = f.dataset.not === r; });
      try { history.replaceState(null, '', '?objet=' + r + '#formulaire'); } catch (err) {}
    }));
  }

  // Contribuer : 4 étapes (le formulaire reste complet sans JavaScript).
  const wz = $('[data-wizard]');
  if (wz) {
    const panes = $$('[data-wz]', wz), steps = $$('[data-wz-go]', wz), nav = $('.cwizard__nav', wz);
    let cur = 0;
    const valid = i => {
      if (i === 0) return !!$('input[name=type]:checked', wz);
      if (i === 1) { const d = $('textarea[name=description]', wz).value.trim(); return d.length >= 10 || $('[data-files]', wz).files.length > 0; }
      return true;
    };
    const show = i => {
      cur = i;
      panes.forEach((p, k) => p.classList.toggle('is-on', k === i));
      steps.forEach((s, k) => { s.classList.toggle('is-on', k === i); s.classList.toggle('is-done', k < i); });
      nav.classList.toggle('is-first', i === 0); nav.classList.toggle('is-last', i === panes.length - 1);
      $('[data-wz-next]', wz).disabled = !valid(i);
      // Champ fichiers seulement pour une photo ou un document
      const t = $('input[name=type]:checked', wz)?.value;
      $('[data-drop]', wz).hidden = !(t === 'photo' || t === 'document');
    };
    wz.addEventListener('input', () => { $('[data-wz-next]', wz).disabled = !valid(cur); });
    wz.addEventListener('change', () => { $('[data-wz-next]', wz).disabled = !valid(cur); if (cur === 0 && valid(0)) setTimeout(() => show(1), 220); });
    $('[data-wz-next]', wz).addEventListener('click', () => { if (valid(cur)) show(Math.min(cur + 1, panes.length - 1)); });
    $('[data-wz-prev]', wz).addEventListener('click', () => show(Math.max(0, cur - 1)));
    steps.forEach((s, k) => s.addEventListener('click', () => { if (k < cur || (k === cur + 1 && valid(cur))) show(k); }));
    const input = $('[data-files]', wz), list = $('[data-file-list]', wz), drop = $('[data-drop]', wz);
    input.addEventListener('change', () => { list.innerHTML = [...input.files].map(f => `<span>📎 ${f.name.replace(/[<>&]/g, '')} · ${(f.size / 1e6).toFixed(1)} Mo</span>`).join(''); });
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, () => drop.classList.add('is-over')));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
    show(0);
  }

  // Newsletter (double validation par e-mail)
  $$('[data-newsletter]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = f.parentElement.querySelector('[data-newsletter-msg]');
    const btn = $('button', f); btn.disabled = true;
    try {
      const r = await fetch('/api/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: f.email.value, website: f.website?.value || '' }) });
      const d = await r.json();
      if (msg) msg.textContent = d.ok ? (d.message || T('Merci !', 'Thanks!')) : (d.error || T('Inscription impossible.', 'Subscription failed.'));
      if (d.ok) f.reset();
    } catch (err) { if (msg) msg.textContent = T('Inscription impossible pour le moment.', 'Subscription unavailable right now.'); }
    btn.disabled = false;
  }));
})();
