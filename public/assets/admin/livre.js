/**
 * Livre des récits : avant l'envoi du formulaire, si un maillot est demandé, photographie en 3D
 * le t-shirt de la boutique floqué au nom et au numéro (shop3d.js, vu de dos), et joint l'image.
 */
(() => {
  const form = document.querySelector('form[data-livre]');
  if (!form) return;
  const styles = JSON.parse(form.dataset.jerseys || '{}');
  const fontUrl = form.dataset.font;
  let fontData = null;
  const font = async () => {
    if (fontData) return fontData;
    const buf = await (await fetch(fontUrl)).arrayBuffer();
    let s = ''; const b = new Uint8Array(buf);
    for (let i = 0; i < b.length; i += 0x8000) s += String.fromCharCode.apply(null, b.subarray(i, i + 0x8000));
    return (fontData = 'data:font/woff2;base64,' + btoa(s));
  };
  const esc = t => t.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  async function backSvg(name, num, st) {
    const f = await font();
    const fs = Math.max(20, Math.min(46, 250 / Math.max(1, name.length) * 1.55));
    return `<svg xmlns="http://www.w3.org/2000/svg" width="280" height="350" viewBox="0 0 280 350">
<style>@font-face{font-family:BS;src:url(${f}) format('woff2');font-weight:900}text{font-family:BS;font-weight:900;text-anchor:middle}</style>
<defs><path id="arc" d="M20 78 Q140 40 260 78"/></defs>
${name ? `<text font-size="${fs}" letter-spacing="3" fill="#${st.text}"><textPath href="#arc" startOffset="50%">${esc(name)}</textPath></text>` : ''}
${num ? `<text x="140" y="300" font-size="230" fill="#${st.text}" stroke="#${st.edge}" stroke-width="7" paint-order="stroke" stroke-linejoin="round">${esc(num)}</text>` : ''}
</svg>`;
  }
  /** Devant et dos du maillot en 3D (PNG transparents) : { front, back }. */
  async function jerseyImage(st, name, num, px = 1800) {
    const { snapshot } = await import('/assets/js/shop3d.js');
    const design = { body: '#' + st.body, sleeve: st.sleeve ? '#' + st.sleeve : null, trim: '#' + st.trim, collar: st.collar, cuffs: !!st.cuffs,
      layers: (st.layers || []).map(l => Object.assign({}, l, { color: '#' + (l.color || st.trim) })) };
    const spec = faces => ({ kind: 'jersey', design, model: '/assets/3d/tshirt.glb', faces });
    const opt = view => ({ w: px, h: Math.round(px * 1.11), view, zoom: 0.78 });
    const back = await snapshot(spec({ dos: { w: 280, h: 350, svg: await backSvg(name, num, st) } }), opt([Math.PI - 0.28, 0.06]));
    const front = await snapshot(spec({}), opt([0.28, 0.06]));
    return { front, back };
  }
  window.livreJersey = jerseyImage;

  // Écran de validation : aperçus 3D (devant, dos) de chaque modèle, l'un après l'autre.
  (async () => {
    for (const box of document.querySelectorAll('[data-jersey-3d]')) {
      const st = styles[box.dataset.jersey3d];
      if (!st) continue;
      try {
        const { front, back } = await jerseyImage(st, 'SOCHAUX', '10', 600);
        const [a, b] = box.children;
        for (const [el, blob] of [[a, front], [b, back]]) {
          el.textContent = '';
          const img = new Image(); img.src = URL.createObjectURL(blob); img.alt = ''; img.style.cssText = 'width:100%;height:100%;object-fit:contain';
          el.appendChild(img);
        }
      } catch (err) { console.error(err); }
    }
  })();
  window.livreJerseyStyles = styles;

  let busy = false;
  if (form.hidden) return;
  form.addEventListener('submit', async e => {
    if (busy) return;
    const name = (form.maillot_nom.value || '').trim().toUpperCase();
    const num = (form.maillot_numero.value || '').replace(/\D/g, '').slice(0, 2);
    if (!name && !num) return;
    e.preventDefault();
    busy = true;
    const btn = form.querySelector('button[type=submit]'), note = form.querySelector('[data-livre-note]');
    if (btn) btn.disabled = true;
    if (note) note.textContent = 'Photo du maillot en 3D…';
    try {
      const st = styles[form.maillot_style.value] || styles['2026'];
      const { front, back } = await jerseyImage(st, name, num);
      for (const [field, blob] of [['maillot_image', back], ['maillot_devant', front]]) {
        const dt = new DataTransfer();
        dt.items.add(new File([blob], field + '.png', { type: 'image/png' }));
        form[field].files = dt.files;
      }
    } catch (err) {
      console.error(err); // sans 3D : le maillot dessiné du livre
    }
    if (note) note.textContent = 'Composition du livre…';
    if (btn) btn.disabled = false;
    form.submit();
    busy = false;
  });
})();
