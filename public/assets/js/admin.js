/* Back-office — champs répétables, garde-fou de sortie, slug auto. */
(function () {
  'use strict';

  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  /* --------------------------------------- Listes répétables */

  /** Réindexe name="data[items][0][title]" après ajout/suppression/tri. */
  function reindex(wrap) {
    const base = wrap.dataset.name;
    $$('[data-repeat-item]', wrap).forEach((item, i) => {
      $$('[name]', item).forEach((input) => {
        input.name = input.name.replace(
          new RegExp('^' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[[^\\]]*\\]'),
          base + '[' + i + ']'
        );
      });
      const n = $('.repeat__n', item);
      if (n) n.textContent = 'Élément ' + (i + 1);
    });
  }

  $$('[data-repeat]').forEach((wrap) => {
    const template = wrap.parentElement.querySelector('[data-repeat-template]');

    wrap.addEventListener('click', (e) => {
      const item = e.target.closest('[data-repeat-item]');
      if (!item) return;

      if (e.target.closest('[data-repeat-remove]')) {
        if (!confirm('Supprimer cet élément ?')) return;
        item.remove();
        reindex(wrap);
      } else if (e.target.closest('[data-repeat-up]') && item.previousElementSibling) {
        wrap.insertBefore(item, item.previousElementSibling);
        reindex(wrap);
      } else if (e.target.closest('[data-repeat-down]') && item.nextElementSibling) {
        wrap.insertBefore(item.nextElementSibling, item);
        reindex(wrap);
      }
    });

    const add = wrap.parentElement.querySelector('[data-repeat-add]');
    if (add && template) {
      add.addEventListener('click', () => {
        const html = template.innerHTML.replace(/__INDEX__/g, String($$('[data-repeat-item]', wrap).length));
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const node = holder.firstElementChild;
        wrap.appendChild(node);
        reindex(wrap);
        const first = $('input, textarea, select', node);
        first && first.focus();
      });
    }

    reindex(wrap);
  });

  /* ------------------------------- Avertissement si non enregistré */

  $$('form[data-dirty-guard]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  });

  /* ---------------------------------------------- Slug automatique */

  const title = $('#title');
  const slug = $('#slug');
  if (title && slug) {
    title.addEventListener('blur', () => {
      if (slug.value.trim() !== '') return;
      slug.value = title.value
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }
})();

/* ----------------------------------------------- Bot IA : back-office */
(function () {
  'use strict';
  const form = document.getElementById('bot-form');
  if (!form) return;

  const $ = (s, c) => (c || document).querySelector(s);
  const csrf = form.querySelector('[name="_csrf"]').value;

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(Object.assign({ _csrf: csrf }, body))
    });
    return res.json();
  }

  /* Liste des modèles récupérée en direct auprès de Google */
  const refresh = $('#bot-refresh');
  if (refresh) {
    refresh.addEventListener('click', async () => {
      const info = $('#bot-models-info');
      const select = $('#model');
      const label = refresh.textContent;
      refresh.disabled = true;
      refresh.textContent = 'Chargement…';
      info.textContent = 'Interrogation de l’API Gemini…';
      try {
        const data = await post(window.SI_BOT.modelsUrl, { api_key: $('#api_key').value });
        if (!data.ok) {
          info.innerHTML = '<span style="color:#ff8290">' + (data.error || 'Échec du chargement.') + '</span>';
          return;
        }
        const current = select.value;
        select.innerHTML = '';
        data.models.forEach((m) => {
          const o = document.createElement('option');
          o.value = m.id;
          o.textContent = m.label + ' — ' + m.id.replace('models/', '') + (m.input ? ' · ' + m.input.toLocaleString('fr-FR') + ' jetons' : '');
          if (m.id === current) o.selected = true;
          select.appendChild(o);
        });
        info.textContent = data.models.length + ' modèle(s) disponible(s). Choisissez-en un puis enregistrez.';
      } catch (e) {
        info.innerHTML = '<span style="color:#ff8290">Requête impossible depuis ce serveur.</span>';
      } finally {
        refresh.disabled = false;
        refresh.textContent = label;
      }
    });
  }

  /* Console de test */
  const box = $('#bot-console');
  const input = $('#bot-q');
  const send = $('#bot-send');
  const history = [];

  function bubble(role, text) {
    const empty = box.querySelector('.bot-console__empty');
    if (empty) empty.remove();
    const el = document.createElement('div');
    el.className = 'bot-msg bot-msg--' + role;
    el.textContent = text;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
    return el;
  }

  async function ask() {
    const q = input.value.trim();
    if (!q) return;
    input.value = '';
    bubble('user', q);
    const pending = bubble('bot', '…');
    send.disabled = true;
    try {
      const data = await post(window.SI_BOT.testUrl, { question: q, history: history.slice(-8) });
      pending.textContent = data.ok ? data.answer : (data.error || 'Erreur inconnue.');
      pending.classList.toggle('bot-msg--error', !data.ok);
      if (data.ok) {
        history.push({ role: 'user', text: q }, { role: 'bot', text: data.answer });
        if (data.used && data.used.length) {
          const src = document.createElement('div');
          src.className = 'bot-sources';
          src.textContent = 'Sources : ' + data.used.slice(0, 4).join(' · ');
          box.appendChild(src);
        }
      }
    } catch (e) {
      pending.textContent = 'Requête impossible.';
      pending.classList.add('bot-msg--error');
    } finally {
      send.disabled = false;
      box.scrollTop = box.scrollHeight;
      input.focus();
    }
  }

  send && send.addEventListener('click', ask);
  input && input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ask(); } });
})();
