/**
 * Boutique › le livre « 100 récits du Lion » : couverture en direct, envoi de la photo du lecteur,
 * recherche du match et des joueurs, maillot floqué en 3D (shop3d.js, devant et dos), extrait à feuilleter.
 */
(() => {
  const root = document.querySelector('[data-livre-shop]');
  if (!root) return;
  const form = root.querySelector('[data-book-form]');
  const csrf = form.querySelector('input[name=_csrf]').value;
  const styles = JSON.parse(root.dataset.jerseys || '{}');
  const status = form.querySelector('[data-book-status]');
  const say = t => { if (status) status.textContent = t; };
  const q = s => form.querySelector(s);
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  // ---------------------------------------------------------------- couverture en direct
  const cover = root.querySelector('[data-book-cover]');
  const coverSvg = () => {
    const nom = (q('input[name="livre[nom]"]').value.trim() || 'Votre nom').toUpperCase();
    const pick = form.querySelector('input[name="livre[couverture]"]:checked');
    const img = pick && pick.dataset.img ? `<image href="${esc(pick.dataset.img)}" x="0" y="0" width="210" height="171" preserveAspectRatio="xMidYMid slice"/><rect x="0" y="120" width="210" height="51" fill="url(#cv)"/>` : '';
    const f = 'font-family="Big Shoulders Display,Impact,sans-serif" font-weight="900"';
    cover.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 210 270" role="img" aria-label="Couverture du livre"><defs><linearGradient id="cv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0E1F4D" stop-opacity="0"/><stop offset="1" stop-color="#0E1F4D"/></linearGradient></defs><rect width="210" height="270" fill="#0E1F4D"/>${img}<rect x="9" y="9" width="192" height="252" fill="none" stroke="#F6C400" stroke-width=".6"/><text x="16" y="203" ${f} font-size="74" fill="#F6C400">100</text><text x="105" y="180" ${f} font-size="20" fill="#fff">RÉCITS</text><text x="105" y="200" ${f} font-size="20" fill="#fff">DU LION</text><polygon points="0,218 210,208 210,219 0,229" fill="#F6C400"/><rect x="16" y="236" width="${Math.min(180, 20 + nom.length * 6.4)}" height="18" fill="#F3EDDF"/><rect x="16" y="236" width="2" height="18" fill="#F6C400"/><text x="22" y="249" ${f} font-size="10" fill="#0E1F4D">${esc(nom)}</text></svg>`;
  };
  form.querySelectorAll('[data-book-in]').forEach(i => i.addEventListener('input', coverSvg));
  form.querySelectorAll('[data-book-in]').forEach(i => i.addEventListener('change', coverSvg));

  // ---------------------------------------------------------------- envois (photo, maillot)
  async function send(blob, name, kind) {
    const fd = new FormData();
    fd.append('_csrf', csrf); fd.append('kind', kind); fd.append('file', blob, name);
    const r = await fetch(root.dataset.upload, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json().catch(() => ({ error: 'Envoi impossible.' }));
    if (d.error) throw new Error(d.error);
    return d.token;
  }
  const photo = q('[data-book-file="photo"]');
  if (photo) photo.addEventListener('change', async () => {
    const note = q('[data-book-file-note="photo"]'), tok = q('[data-book-token="photo"]');
    tok.value = '';
    if (!photo.files[0]) return;
    note.textContent = 'Envoi de votre photo…';
    try { tok.value = await send(photo.files[0], photo.files[0].name, 'photo'); note.textContent = '✓ Photo reçue : elle sera imprimée nettement.'; }
    catch (e) { note.textContent = e.message; photo.value = ''; }
  });

  // ---------------------------------------------------------------- match et joueurs
  form.querySelectorAll('[data-book-search]').forEach(input => {
    const kind = input.dataset.bookSearch, list = q(`[data-book-results="${kind}"]`), picked = q(`[data-book-picked="${kind}"]`);
    const hidden = q(`input[name="livre[${kind}]"]`);
    const labels = {};
    const show = () => {
      const ids = hidden.value.split(',').filter(Boolean);
      picked.innerHTML = ids.map(id => `<span class="shopbook__tag">${esc(labels[id] || 'n° ' + id)} <button type="button" data-rm="${esc(id)}" aria-label="Retirer">×</button></span>`).join(' ');
    };
    picked.addEventListener('click', e => {
      const id = e.target.dataset && e.target.dataset.rm;
      if (!id) return;
      hidden.value = hidden.value.split(',').filter(x => x && x !== id).join(',');
      show();
    });
    let t = null;
    input.addEventListener('input', () => {
      clearTimeout(t);
      const v = input.value.trim();
      if (v.length < 2) { list.innerHTML = ''; return; }
      t = setTimeout(async () => {
        const d = await (await fetch((kind === 'match' ? root.dataset.matches : root.dataset.players) + '?q=' + encodeURIComponent(v))).json().catch(() => ({}));
        list.innerHTML = (d.items || []).map(it => `<li><button type="button" data-id="${esc(it.id)}">${esc(it.label)}</button></li>`).join('');
      }, 250);
    });
    list.addEventListener('click', e => {
      const b = e.target.closest('button[data-id]');
      if (!b) return;
      labels[b.dataset.id] = b.textContent;
      if (kind === 'match') hidden.value = b.dataset.id;
      else { const ids = hidden.value.split(',').filter(Boolean); if (!ids.includes(b.dataset.id) && ids.length < 3) ids.push(b.dataset.id); hidden.value = ids.join(','); }
      list.innerHTML = ''; input.value = ''; show();
    });
    show();
  });

  // ---------------------------------------------------------------- maillot 3D
  let fontData = null, logoData = null;
  const dataUrl = async url => { const b = await (await fetch(url)).blob(); return new Promise(ok => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(b); }); };
  async function backSvg(name, num, st) {
    fontData = fontData || await dataUrl(root.dataset.font);
    const fs = Math.max(20, Math.min(46, 250 / Math.max(1, name.length) * 1.55));
    return `<svg xmlns="http://www.w3.org/2000/svg" width="280" height="350" viewBox="0 0 280 350"><style>@font-face{font-family:BS;src:url(${fontData}) format('woff2');font-weight:900}text{font-family:BS;font-weight:900;text-anchor:middle}</style><defs><path id="arc" d="M20 78 Q140 40 260 78"/></defs>`
      + (name ? `<text font-size="${fs}" letter-spacing="3" fill="#${st.name || st.text}"><textPath href="#arc" startOffset="50%">${esc(name)}</textPath></text>` : '')
      + (num ? `<text x="140" y="300" font-size="230" fill="#${st.text}" stroke="#${st.edge}" stroke-width="7" paint-order="stroke" stroke-linejoin="round">${esc(num)}</text>` : '') + '</svg>';
  }
  async function jersey(st, name, num, px) {
    const { snapshot } = await import(root.dataset['3d']);
    logoData = logoData || await dataUrl(root.dataset.logo);
    const design = { body: '#' + st.body, sleeve: st.sleeve ? '#' + st.sleeve : null, trim: '#' + st.trim, collar: st.collar, cuffs: !!st.cuffs, layers: (st.layers || []).map(l => Object.assign({}, l, { color: '#' + (l.color || st.trim) })) };
    const spec = faces => ({ kind: 'jersey', design, model: root.dataset['3d'].replace(/js\/shop3d\.js.*$/, '3d/tshirt.glb'), faces });
    const opt = view => ({ w: px, h: Math.round(px * 1.11), view, zoom: 0.78 });
    const heart = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="62" height="70" viewBox="0 0 62 70"><image href="${logoData}" xlink:href="${logoData}" width="62" height="70"/></svg>`;
    const back = await snapshot(spec({ dos: { w: 280, h: 350, svg: await backSvg(name, num, st) } }), opt([Math.PI - 0.28, 0.06]));
    const front = await snapshot(spec({ coeur: { w: 62, h: 70, x: 78, y: 600, svg: heart } }), opt([0.28, 0.06]));
    return { front, back };
  }
  const jIn = () => ({ style: (q('[name="livre[maillot_style]"]:checked') || q('select[name="livre[maillot_style]"]') || {}).value || '', name: ((q('[name="livre[maillot_nom]"]') || {}).value || '').trim().toUpperCase(), num: ((q('[name="livre[maillot_numero]"]') || {}).value || '').replace(/\D/g, '').slice(0, 2) });
  const jBox = root.querySelector('[data-book-jersey]');
  let jTimer = null, jSig = '', jDone = '';
  const jPreview = () => {
    clearTimeout(jTimer);
    jTimer = setTimeout(async () => {
      const j = jIn();
      if (!j.style) { if (jBox) jBox.hidden = true; jSig = ''; return; }
      const sig = JSON.stringify(j);
      if (sig === jSig) return;
      jSig = sig;
      try {
        const { front, back } = await jersey(styles[j.style], j.name, j.num, 520);
        if (sig !== jSig) return;
        jBox.hidden = false;
        for (const [sel, b] of [['[data-jersey-front]', front], ['[data-jersey-back]', back]]) {
          const el = jBox.querySelector(sel); el.innerHTML = '';
          const img = new Image(); img.src = URL.createObjectURL(b); img.alt = ''; el.appendChild(img);
        }
      } catch (e) { console.error(e); }
    }, 700);
  };
  form.querySelectorAll('[data-book-jersey-in]').forEach(i => { i.addEventListener('input', jPreview); i.addEventListener('change', jPreview); });
  jPreview();

  // ---------------------------------------------------------------- envoi du formulaire
  const exBtn = form.querySelector('[data-book-excerpt]');
  if (exBtn) exBtn.formTarget = 'extrait-livre';
  let going = false;
  form.addEventListener('submit', async e => {
    const btn = e.submitter;
    const excerpt = btn && btn.hasAttribute('data-book-excerpt');
    let win = null;
    if (excerpt) { win = window.open('', 'extrait-livre'); form.target = 'extrait-livre'; } else form.removeAttribute('target');
    if (going) return;
    const j = jIn();
    const want = j.style && (j.name || j.num) ? JSON.stringify(j) : '';
    if (!want || want === jDone) { if (excerpt && win) say('Composition de l’extrait (quelques secondes)…'); return; }
    e.preventDefault();
    say('Photo de votre maillot en 3D…');
    try {
      const { front, back } = await jersey(styles[j.style], j.name, j.num, 1800);
      q('[data-book-token="maillot_image"]').value = await send(back, 'dos.png', 'maillot');
      q('[data-book-token="maillot_devant"]').value = await send(front, 'devant.png', 'maillot');
      jDone = want;
    } catch (err) { console.error(err); say('Maillot 3D indisponible sur cet appareil : il sera dessiné.'); }
    going = true;
    say(excerpt ? 'Composition de l’extrait (quelques secondes)…' : 'Ajout au panier…');
    form.requestSubmit(btn);
    going = false;
  });
})();
