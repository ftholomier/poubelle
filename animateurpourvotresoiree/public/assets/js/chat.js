/* Assistant conversationnel (Gemini) : fenêtre de discussion, cartes de pros et devis pré-rempli. */
(() => {
  'use strict';
  const APVS = window.APVS || {};
  const cfg = (APVS.cfg && APVS.cfg.chat) || null;
  const launch = document.getElementById('chat-launch');
  if (!cfg || !launch) return;
  const esc = APVS.esc || ((s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]));
  const KEY = 'apvs:chat';
  const load = () => { try { return JSON.parse(sessionStorage.getItem(KEY) || 'null') || { cid: '', log: [] }; } catch (e) { return { cid: '', log: [] }; } };
  const save = (s) => { try { sessionStorage.setItem(KEY, JSON.stringify(s)); } catch (e) { /* navigation privée */ } };
  let state = load();
  let panel = null;
  let busy = false;
  const SUGG = ['Un DJ pour notre mariage', 'Un magicien pour un anniversaire enfant', 'Une animation pour une soirée d\'entreprise', 'Un photobooth près de chez moi'];

  const linkify = (t) => esc(t).replace(/(https?:\/\/[^\s<]+|\/(?:devis|recherche|blog|pro)\/[^\s<]*)/g, (u) => '<a href="' + u + '" class="link">' + u + '</a>');

  const cardHtml = (c) => '<a class="chat-card" href="' + esc(c.url) + '" style="--c:' + esc(c.color || '#ffd23f') + '">'
    + '<span class="t">' + (c.photo ? '<img src="' + esc(c.photo) + '" alt="" loading="lazy">' : '') + '</span>'
    + '<span><b>' + esc(c.name) + '</b>' + esc(c.cat) + (c.city ? ' · ' + esc(c.city) : '')
    + (c.rating ? ' · <span class="c-coral">★ ' + esc(c.rating) + '</span>' : '')
    + (c.price ? ' · dès ' + esc(c.price) + ' €' : '') + '</span></a>';

  const render = (m) => {
    const log = panel.querySelector('.chat-log');
    const el = document.createElement('div');
    el.className = 'msg ' + (m.role === 'user' ? 'me' : 'bot');
    el.innerHTML = linkify(m.text);
    log.appendChild(el);
    if (m.cards && m.cards.length) {
      const box = document.createElement('div');
      box.className = 'chat-cards';
      box.innerHTML = m.cards.map(cardHtml).join('');
      log.appendChild(box);
    }
    if (m.quote) {
      const a = document.createElement('a');
      a.className = 'btn btn-coral btn-sm';
      a.style.alignSelf = 'flex-start';
      a.href = m.quote;
      a.textContent = 'Finaliser ma demande de devis →';
      log.appendChild(a);
    }
    log.scrollTop = log.scrollHeight;
  };

  const build = () => {
    panel = document.createElement('div');
    panel.className = 'chat-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Assistant ' + cfg.name);
    panel.innerHTML = '<div class="chat-head"><span class="chat-launch-bubble" aria-hidden="true" style="width:34px;height:34px;border-radius:50%;background:var(--yellow);border:2px solid var(--cream);display:grid;place-items:center">✨</span>'
      + '<div><b>' + esc(cfg.name) + '</b><small>Assistant IA · réponses en quelques secondes</small></div>'
      + '<button type="button" class="icon-btn" data-reset title="Nouvelle conversation" aria-label="Nouvelle conversation">↺</button>'
      + '<button type="button" class="icon-btn" data-close aria-label="Fermer" style="margin-left:6px">✕</button></div>'
      + '<div class="chat-log" aria-live="polite"></div>'
      + '<form class="chat-form"><label class="sr-only" for="chat-input">Votre message</label>'
      + '<textarea id="chat-input" rows="1" maxlength="1000" placeholder="Votre événement, votre ville…" required></textarea>'
      + '<button class="btn btn-ink btn-sm" type="submit" aria-label="Envoyer">➤</button></form>'
      + '<p class="chat-note">Assistant automatique : vérifiez les informations importantes directement auprès des pros.</p>';
    document.body.appendChild(panel);
    const log = panel.querySelector('.chat-log');
    if (!state.log.length) {
      const hello = document.createElement('div');
      hello.className = 'msg bot';
      hello.textContent = cfg.greeting || 'Bonjour ! Que fêtez-vous, et où ?';
      log.appendChild(hello);
      const sugg = document.createElement('div');
      sugg.className = 'chat-sugg';
      sugg.innerHTML = SUGG.map((s) => '<button type="button" class="chip chip-sm">' + esc(s) + '</button>').join('');
      sugg.addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { sugg.remove(); send(b.textContent); } });
      log.appendChild(sugg);
    } else {
      state.log.forEach(render);
    }
    const form = panel.querySelector('form');
    const input = panel.querySelector('textarea');
    form.addEventListener('submit', (e) => { e.preventDefault(); const t = input.value.trim(); if (t) { input.value = ''; input.style.height = ''; send(t); } });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(120, input.scrollHeight) + 'px'; });
    panel.querySelector('[data-close]').addEventListener('click', close);
    panel.querySelector('[data-reset]').addEventListener('click', () => { state = { cid: '', log: [] }; save(state); panel.remove(); panel = null; open(); });
    panel.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  };

  async function send(text) {
    if (busy) return;
    busy = true;
    const user = { role: 'user', text };
    state.log.push(user);
    render(user);
    const log = panel.querySelector('.chat-log');
    const typing = document.createElement('div');
    typing.className = 'msg bot typing';
    typing.textContent = cfg.name + ' réfléchit';
    log.appendChild(typing);
    log.scrollTop = log.scrollHeight;
    try {
      const r = await APVS.post('/api/chat', { cid: state.cid, text, page: location.pathname });
      typing.remove();
      state.cid = r.cid || state.cid;
      const bot = { role: 'model', text: r.text || '', cards: r.cards || [], quote: r.quote || null };
      state.log.push(bot);
      render(bot);
    } catch (err) {
      typing.remove();
      if (err.body && err.body.reset) state = { cid: '', log: [] };
      render({ role: 'model', text: err.message || 'Oups, je n\'ai pas pu répondre. Réessayez dans un instant.' });
    }
    state.log = state.log.slice(-40);
    save(state);
    busy = false;
  }

  function open() {
    if (!panel) build();
    panel.hidden = false;
    launch.hidden = true;
    panel.querySelector('textarea').focus();
    state.open = true;
    save(state);
  }
  function close(refocus = true) {
    if (panel) panel.hidden = true;
    launch.hidden = false;
    if (refocus) launch.focus();
    state.open = false;
    save(state);
  }
  launch.addEventListener('click', open);
  // clic ou toucher en dehors de la fenêtre : elle se referme (sans voler le focus de ce qui a été cliqué)
  document.addEventListener('pointerdown', (e) => {
    if (!panel || panel.hidden || panel.contains(e.target) || launch.contains(e.target)) return;
    close(false);
  }, true);
  if (state.open && state.log.length) open();
})();
