/* Assistant IA du musée : bulle, conversation conservée pendant la visite, sources, avis. */
(function () {
  'use strict';
  const root = document.querySelector('[data-chat]');
  if (!root) return;
  const $ = (s, r = root) => r.querySelector(s);
  const fab = $('[data-chat-toggle]'), panel = $('#chat-panel'), log = $('[data-chat-log]'), form = $('[data-chat-form]');
  const input = form.querySelector('textarea'), send = form.querySelector('button');
  let i18n = {};
  try { i18n = JSON.parse(root.dataset.i18n || '{}'); } catch (e) { /* défauts */ }
  const KEY = 'fcsm-chat';
  let state = { open: false, msgs: [], seen: false };
  try { state = Object.assign(state, JSON.parse(sessionStorage.getItem(KEY) || '{}')); } catch (e) { /* stockage indisponible */ }
  const save = () => { try { sessionStorage.setItem(KEY, JSON.stringify({ open: state.open, msgs: state.msgs.slice(-30), seen: state.seen })); } catch (e) { /* idem */ } };
  let busy = false;

  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const scroll = () => { log.scrollTop = log.scrollHeight; };

  function bubble(m) {
    const el = document.createElement('div');
    if (m.role === 'user') {
      el.className = 'chat__msg chat__msg--user';
      el.textContent = m.text;
    } else if (m.role === 'error') {
      el.className = 'chat__msg chat__msg--error';
      el.setAttribute('role', 'alert');
      el.textContent = m.text;
    } else {
      el.className = 'chat__msg chat__msg--bot';
      el.innerHTML = m.html; // HTML produit et assaini par le serveur
      if (m.sources && m.sources.length) {
        const src = document.createElement('div');
        src.className = 'chat__src';
        src.innerHTML = '<span>' + esc(i18n.sources || 'Sources') + '</span>' + m.sources.map(s => '<a href="' + esc(s.url) + '">' + [].concat(s.n || []).map(n => '<i class="chat-cite">' + (+n) + '</i>').join('') + '<b>' + esc(typeLabel(s.type)) + '</b><span>' + esc(s.title) + '</span></a>').join('');
        el.appendChild(src);
      }
      if (m.id) {
        const fb = document.createElement('div');
        fb.className = 'chat__fb';
        fb.innerHTML = '<button type="button" data-v="1" aria-label="' + esc(i18n.useful || '') + '" title="' + esc(i18n.useful || '') + '">👍</button><button type="button" data-v="-1" aria-label="' + esc(i18n.useless || '') + '" title="' + esc(i18n.useless || '') + '">👎</button><small></small>';
        if (m.fb) fb.querySelector('[data-v="' + m.fb + '"]').classList.add('is-on');
        fb.addEventListener('click', ev => {
          const b = ev.target.closest('button[data-v]');
          if (!b || m.fb) return;
          m.fb = +b.dataset.v;
          b.classList.add('is-on');
          fb.querySelector('small').textContent = i18n.thanks || '';
          save();
          fetch(root.dataset.feedback, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-SR-Chat': '1' }, body: JSON.stringify({ id: m.id, v: m.fb }) }).catch(() => {});
        });
        el.appendChild(fb);
      }
    }
    log.appendChild(el);
    return el;
  }
  function typeLabel(t) {
    return ({ match: 'Match', personne: 'Fiche', article: 'Article', page: 'Page', objet: 'Objet', moment: 'Moment', records: 'Records', h2h: 'Face-à-face', saison: 'Saison', stade: 'Stade', frise: 'Frise', palmares: 'Palmarès' })[t] || 'Fiche';
  }
  function render() {
    log.querySelectorAll('.chat__msg:not(.chat__welcome), .chat__typing').forEach(n => n.remove());
    state.msgs.forEach(bubble);
    const sugg = $('[data-chat-sugg]');
    if (sugg) sugg.hidden = state.msgs.length > 0;
    scroll();
  }

  function open(focus = true) {
    state.open = true;
    state.seen = true;
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    fab.classList.remove('is-new');
    document.body.classList.add('chat-open');
    save();
    scroll();
    if (focus) setTimeout(() => input.focus({ preventScroll: true }), 50);
  }
  function close() {
    state.open = false;
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('chat-open');
    save();
    fab.focus({ preventScroll: true });
  }

  async function ask(q) {
    q = q.trim();
    if (!q || busy) return;
    busy = true;
    send.disabled = true;
    const history = state.msgs.filter(m => m.role === 'user' || m.role === 'model').slice(-6).map(m => ({ role: m.role, text: m.role === 'user' ? m.text : (m.plain || '') }));
    const um = { role: 'user', text: q };
    state.msgs.push(um);
    bubble(um);
    const sugg = $('[data-chat-sugg]');
    if (sugg) sugg.hidden = true;
    const typing = document.createElement('div');
    typing.className = 'chat__typing';
    typing.setAttribute('aria-label', '…');
    typing.innerHTML = '<i></i><i></i><i></i>';
    log.appendChild(typing);
    scroll();
    save();
    try {
      const r = await fetch(root.dataset.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-SR-Chat': '1' }, body: JSON.stringify({ q, history, page: location.pathname }) });
      const j = await r.json().catch(() => ({}));
      typing.remove();
      if (r.ok && j.html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = j.html;
        const bm = { role: 'model', html: j.html, plain: tmp.textContent.slice(0, 1500), sources: j.sources || [], id: j.id };
        state.msgs.push(bm);
        bubble(bm);
      } else {
        const em = { role: 'error', text: j.error || i18n.error };
        state.msgs.push(em);
        bubble(em);
      }
    } catch (e) {
      typing.remove();
      const em = { role: 'error', text: i18n.error };
      state.msgs.push(em);
      bubble(em);
    }
    save();
    scroll();
    busy = false;
    send.disabled = false;
    input.focus({ preventScroll: true });
  }

  fab.addEventListener('click', () => (state.open ? close() : open()));
  $('[data-chat-close]').addEventListener('click', close);
  $('[data-chat-clear]').addEventListener('click', () => {
    if (state.msgs.length && !confirm(i18n.clear || 'OK ?')) return;
    state.msgs = [];
    save();
    render();
    input.focus();
  });
  document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && state.open && panel.contains(document.activeElement)) close(); });
  form.addEventListener('submit', ev => {
    ev.preventDefault();
    const q = input.value;
    input.value = '';
    input.style.height = '';
    ask(q);
  });
  input.addEventListener('keydown', ev => {
    if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
      ev.preventDefault();
      form.requestSubmit();
    }
  });
  input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(140, input.scrollHeight) + 'px'; });
  // Toute question proposée dans la page (« Demander au guide ») ouvre l'assistant.
  document.addEventListener('click', ev => {
    const b = ev.target.closest('[data-chat-ask]');
    if (!b) return;
    ev.preventDefault();
    open(false);
    ask(b.dataset.chatAsk);
  });

  render();
  if (state.open) open(false);
  else if (!state.seen) fab.classList.add('is-new');
})();
