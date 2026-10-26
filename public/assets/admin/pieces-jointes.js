/* Pièces jointes des tickets : choisir, glisser-déposer ou coller (Ctrl+V / ⌘+V) une capture
   d'écran n'importe où dans le formulaire. Aperçu des images, bouton « retirer », tailles vérifiées
   avant l'envoi (limites du serveur) ; une image trop lourde est recompressée en JPEG. */
(function () {
  'use strict';
  const human = n => n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(n / 1024)) + ' Ko';
  const pad = n => String(n).padStart(2, '0');
  const stamp = () => { const d = new Date(); return d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()); };

  /** Image trop lourde : réduite (2560 px au plus) et passée en JPEG, sinon null. */
  function shrink(file, max) {
    if (!/^image\/(png|jpeg|webp)$/.test(file.type)) return Promise.resolve(null);
    return new Promise(resolve => {
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        const k = Math.min(1, 2560 / Math.max(img.naturalWidth, img.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k);
        c.height = Math.round(img.naturalHeight * k);
        const g = c.getContext('2d');
        g.fillStyle = '#fff';
        g.fillRect(0, 0, c.width, c.height);
        g.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        const tryQ = q => c.toBlob(b => {
          if (b && b.size <= max) resolve(new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
          else if (q > 0.5) tryQ(q - 0.15);
          else resolve(null);
        }, 'image/jpeg', q);
        tryQ(0.85);
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  }

  function setup(zone) {
    const form = zone.closest('form');
    const input = zone.querySelector('[data-pj-input]');
    const list = zone.querySelector('[data-pj-list]');
    const err = zone.querySelector('[data-pj-err]');
    if (!form || !input || !list || typeof DataTransfer === 'undefined') return;
    const max = +zone.dataset.pjMax || 20971520;
    const total = +zone.dataset.pjTotal || 8388608;
    const count = +zone.dataset.pjCount || 10;
    let files = [];

    const say = msg => { err.textContent = msg || ''; err.hidden = !msg; };
    const sync = () => {
      const dt = new DataTransfer();
      files.forEach(f => dt.items.add(f));
      input.files = dt.files;
      list.textContent = '';
      files.forEach((f, i) => {
        const li = document.createElement('li');
        if (f.type.startsWith('image/')) {
          const im = document.createElement('img');
          im.src = URL.createObjectURL(f);
          im.alt = '';
          im.onload = () => URL.revokeObjectURL(im.src);
          li.appendChild(im);
        } else {
          li.appendChild(document.createTextNode('📄'));
        }
        const nm = document.createElement('span');
        nm.className = 'nm';
        nm.textContent = f.name + ' · ' + human(f.size);
        nm.title = f.name;
        li.appendChild(nm);
        const rm = document.createElement('button');
        rm.type = 'button';
        rm.textContent = '×';
        rm.setAttribute('aria-label', 'Retirer ' + f.name);
        rm.addEventListener('click', () => { files.splice(i, 1); sync(); check(); });
        li.appendChild(rm);
        list.appendChild(li);
      });
    };
    const check = () => {
      if (files.length > count) return say(count + ' pièces jointes au plus par envoi.'), false;
      const big = files.find(f => f.size > max);
      if (big) return say('« ' + big.name + ' » dépasse ' + human(max) + ' (limite du serveur).'), false;
      if (files.reduce((s, f) => s + f.size, 0) > total) return say('Les pièces jointes dépassent ' + human(total) + ' en tout : envoyez-les en plusieurs fois (une réponse au ticket).'), false;
      say('');
      return true;
    };
    const add = async incoming => {
      for (let f of incoming) {
        if (f.size > max) f = (await shrink(f, max)) || f;
        files.push(f);
      }
      sync();
      check();
    };

    input.addEventListener('change', () => {
      const picked = [...input.files];
      sync(); // remet la liste gardée (le choix ne remplace pas les fichiers déjà ajoutés)
      add(picked);
    });
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('is-over')));
    zone.addEventListener('drop', e => { e.preventDefault(); if (e.dataTransfer && e.dataTransfer.files.length) add([...e.dataTransfer.files]); });
    // Collage : une capture d'écran (fichier sans texte) devient une pièce jointe ; un texte collé
    // dans un champ reste un texte.
    form.addEventListener('paste', e => {
      const cd = e.clipboardData;
      if (!cd) return;
      const pasted = [...cd.files];
      if (!pasted.length) return;
      const textField = e.target && /^(TEXTAREA|INPUT)$/.test(e.target.tagName) && e.target.type !== 'file';
      if (textField && cd.getData('text/plain')) return;
      e.preventDefault();
      add(pasted.map((f, i) => /^image\.\w+$/i.test(f.name) || !f.name
        ? new File([f], 'capture-' + stamp() + (pasted.length > 1 ? '-' + (i + 1) : '') + '.' + ((f.type.split('/')[1] || 'png').replace('jpeg', 'jpg')), { type: f.type })
        : f));
    });
    form.addEventListener('submit', e => { if (!check()) { e.preventDefault(); zone.scrollIntoView({ block: 'center' }); } });
  }

  document.querySelectorAll('[data-pj]').forEach(setup);
})();
