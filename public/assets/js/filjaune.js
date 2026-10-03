/* Le Fil jaune : formulaire (inverser, au hasard) et défi du jour (choisir un coéquipier à chaque passe). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const en = (document.documentElement.lang || 'fr') === 'en';
  const T = (fr, eng) => (en ? eng : fr);
  const base = (en ? '/en' : '') + '/interactif/fil-jaune/';

  /* ---------------------------------------------------------- formulaire */
  $$('[data-fj-form]').forEach(form => {
    const [a, b] = $$('input[list]', form);
    const opts = $$('datalist option', form);
    const slugOf = v => { const o = opts.find(x => x.value.toLowerCase() === v.trim().toLowerCase()); return o ? o.dataset.slug : null; };
    $('[data-fj-swap]', form).addEventListener('click', () => { [a.value, b.value] = [b.value, a.value]; });
    $('[data-fj-random]', form).addEventListener('click', () => {
      const i = Math.floor(Math.random() * opts.length);
      let j = Math.floor(Math.random() * (opts.length - 1));
      if (j >= i) j++;
      location.href = base + opts[i].dataset.slug + '/' + opts[j].dataset.slug + '/';
    });
    // Noms choisis dans la liste : directement vers la bonne adresse.
    form.addEventListener('submit', e => {
      const sa = slugOf(a.value), sb = b.value.trim() ? slugOf(b.value) : '';
      if (sa && sb !== null) { e.preventDefault(); location.href = base + sa + '/' + (sb ? sb + '/' : ''); }
    });
  });

  /* ---------------------------------------------------------- défi du jour */
  const game = $('[data-fj-game]');
  if (!game) return;
  const from = +game.dataset.from, to = +game.dataset.to, best = +game.dataset.best, day = game.dataset.day;
  const names = {};
  $$('.fjgame__end b', game).forEach((b, i) => { names[i ? to : from] = b.textContent; });
  const chainEl = $('[data-fj-chain]', game), pick = $('[data-fj-pick]', game), mates = $('[data-fj-mates]', game);
  const filter = $('[data-fj-filter]', game), label = $('[data-fj-pick-label]', game), msg = $('[data-fj-msg]', game);
  const start = $('[data-fj-start]', game), undo = $('[data-fj-undo]', game), share = $('[data-fj-share]', game);
  const KEY = 'fj-defi-' + game.dataset.date;
  let chain = [from], done = false, list = [];
  try { const s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (s && Array.isArray(s.chain) && s.chain[0] === from) { chain = s.chain; done = !!s.done; Object.assign(names, s.names || {}); } } catch (e) {}
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify({ chain, done, names })); } catch (e) {} };
  const passes = () => chain.length - 1;

  function drawChain() {
    chainEl.textContent = '';
    chain.forEach(id => {
      const li = document.createElement('li');
      const s = document.createElement('span');
      s.textContent = names[id] || '…';
      li.appendChild(s);
      if (id === to) li.classList.add('is-goal');
      chainEl.appendChild(li);
    });
    undo.hidden = done || chain.length < 2;
    chainEl.hidden = chain.length < 2;
  }
  function drawMates() {
    const q = filter.value.trim().toLowerCase();
    mates.textContent = '';
    list.filter(m => !q || m.name.toLowerCase().includes(q)).forEach(m => {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      if (m.id === to) b.classList.add('is-target');
      if (chain.includes(m.id)) b.disabled = true;
      const n = document.createElement('span');
      n.textContent = m.name;
      const s = document.createElement('small');
      s.textContent = m.n + (en ? (m.n > 1 ? ' games' : ' game') : (m.n > 1 ? ' matchs' : ' match'));
      b.append(n, s);
      b.addEventListener('click', () => choose(m));
      li.appendChild(b);
      mates.appendChild(li);
    });
  }
  async function load() {
    const cur = chain[chain.length - 1];
    label.textContent = T('Coéquipiers de ', 'Teammates of ') + (names[cur] || '');
    mates.textContent = '';
    msg.textContent = T('Chargement…', 'Loading…');
    try {
      const r = await fetch('/api/fil-jaune?id=' + cur).then(x => x.json());
      if (!r.ok) throw new Error();
      list = r.teammates;
      list.forEach(m => { names[m.id] = m.name; });
      msg.textContent = '';
      filter.value = '';
      drawMates();
      pick.hidden = false;
    } catch (e) {
      msg.textContent = T('Impossible de charger les coéquipiers. Réessayez.', 'Could not load the teammates. Please try again.');
    }
  }
  function finish() {
    pick.hidden = true;
    start.hidden = true;
    undo.hidden = true;
    share.hidden = false;
    msg.classList.add('is-win');
    msg.textContent = (passes() === best ? T('Parfait ! ', 'Perfect! ') : T('Bravo ! ', 'Well done! '))
      + T('Relié en ', 'Linked in ') + passes() + T(' passes (le plus court : ', ' steps (shortest: ') + best + ').';
  }
  function choose(m) {
    chain.push(m.id);
    names[m.id] = m.name;
    drawChain();
    if (m.id === to) { done = true; save(); finish(); return; }
    save();
    if (passes() >= 10) {
      pick.hidden = true;
      msg.textContent = T('Déjà 10 passes : annulez quelques choix pour prendre un autre chemin, ou voyez la solution.', 'Already 10 steps: undo a few choices to try another path, or see the solution.');
      return;
    }
    load();
  }
  start.addEventListener('click', () => { start.hidden = true; load(); });
  undo.addEventListener('click', () => { if (chain.length > 1) { chain.pop(); save(); drawChain(); load(); } });
  filter.addEventListener('input', drawMates);
  share.addEventListener('click', () => {
    const text = '🟡 ' + T('Fil jaune du ', 'Fil jaune, ') + day + ' : ' + names[from] + ' → ' + names[to] + ' ' + T('en ', 'in ') + passes() + ' ' + T('passes', 'steps') + ' (' + T('le plus court : ', 'shortest: ') + best + ')';
    if (window.SR && SR.share) SR.share({ title: 'Le Fil jaune', text, url: location.origin + base + '#defi' });
  });
  drawChain();
  if (done) finish();
  else if (chain.length > 1) { start.hidden = true; load(); }
})();
