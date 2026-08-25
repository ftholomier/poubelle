/* =============================================================
   Assistant Suisse Immo — widget de conversation
   ============================================================= */
(function () {
  'use strict';

  const root = document.getElementById('bot');
  if (!root) return;

  const $ = (s, c) => (c || document).querySelector(s);
  const toggle = $('.bot__toggle', root);
  const panel = $('#bot-panel', root);
  const log = $('#bot-log', root);
  const form = $('#bot-form-front', root);
  const input = $('#bot-input', root);
  const chips = $('#bot-chips', root);
  const csrf = form.querySelector('[name="_csrf"]').value;
  const endpoint = root.dataset.endpoint;

  let history = [];
  let busy = false;
  let opened = false;

  try {
    const saved = sessionStorage.getItem('si_bot');
    if (saved) history = JSON.parse(saved) || [];
  } catch (e) { /* navigation privée */ }

  function persist() {
    try { sessionStorage.setItem('si_bot', JSON.stringify(history.slice(-12))); } catch (e) {}
  }

  function bubble(role, text, pending) {
    const el = document.createElement('div');
    el.className = 'bot__msg bot__msg--' + role + (pending ? ' is-pending' : '');
    if (pending) {
      el.innerHTML = '<span class="bot__typing"><i></i><i></i><i></i></span>';
    } else {
      // Rendu volontairement textuel : aucune balise venue du modèle n'est interprétée.
      text.split(/\n{2,}/).forEach((para) => {
        const p = document.createElement('p');
        p.textContent = para.replace(/\n/g, ' ').replace(/\*\*(.+?)\*\*/g, '$1').replace(/^\s*[*-]\s+/gm, '• ');
        el.appendChild(p);
      });
    }
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function replay() {
    log.innerHTML = '';
    bubble('bot', root.dataset.greeting || 'Bonjour !');
    history.forEach((t) => bubble(t.role === 'bot' ? 'bot' : 'user', t.text));
  }

  async function ask(question) {
    if (busy) return;
    const q = (question || input.value).trim();
    if (!q) return;
    busy = true;
    input.value = '';
    if (chips) chips.hidden = true;
    bubble('user', q);
    const pending = bubble('bot', '', true);

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ _csrf: csrf, question: q, history: history.slice(-8) })
      });
      const data = await res.json();
      pending.remove();
      if (data.ok) {
        bubble('bot', data.answer);
        history.push({ role: 'user', text: q }, { role: 'bot', text: data.answer });
        persist();
        if (window.siTrack) window.siTrack('cta_click', { source: 'bot-reponse' });
      } else {
        bubble('bot', data.error || 'Je ne parviens pas à répondre pour le moment.').classList.add('bot__msg--error');
      }
    } catch (e) {
      pending.remove();
      bubble('bot', 'La connexion a échoué. Réessayez, ou écrivez-nous depuis la page contact.').classList.add('bot__msg--error');
    } finally {
      busy = false;
      log.scrollTop = log.scrollHeight;
      input.focus();
    }
  }

  function open() {
    panel.hidden = false;
    root.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    if (!opened) { opened = true; replay(); }
    setTimeout(() => input.focus(), 260);
    if (window.siTrack) window.siTrack('cta_click', { source: 'bot-ouverture' });
  }

  function close() {
    root.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    setTimeout(() => { panel.hidden = true; }, 300);
    toggle.focus();
  }

  toggle.addEventListener('click', () => (root.classList.contains('is-open') ? close() : open()));
  $('.bot__close', root).addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && root.classList.contains('is-open')) close(); });

  form.addEventListener('submit', (e) => { e.preventDefault(); ask(); });
  if (chips) {
    chips.addEventListener('click', (e) => {
      const chip = e.target.closest('.bot__chip');
      if (chip) ask(chip.textContent);
    });
  }

  // N'importe quel élément peut ouvrir l'assistant sur une question donnée.
  document.querySelectorAll('[data-bot-ask]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      open();
      setTimeout(() => ask(el.dataset.botAsk), 320);
    });
  });
})();
