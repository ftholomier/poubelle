/* Espace pro : assistant de rédaction IA, envoi de photos par glisser-déposer. */
(() => {
  'use strict';
  const APVS = window.APVS || {};
  const $ = (s, r = document) => r.querySelector(s);
  const esc = APVS.esc || ((s) => String(s));

  /* Amélioration de la description par l'IA */
  const aiBtn = $('[data-ai-desc]');
  if (aiBtn) {
    aiBtn.addEventListener('click', async () => {
      const ta = $('#f-desc');
      const draft = ta && ta.editor ? ta.editor.get() : (ta ? ta.value : '');
      aiBtn.classList.add('is-loading');
      try {
        const d = await APVS.post('/espace-pro/ia/description', { draft });
        const dlg = document.createElement('dialog');
        dlg.className = 'modal';
        dlg.innerHTML = '<div class="modal-head"><h2>Proposition de l\'IA ✨</h2><button class="icon-btn" value="cancel" aria-label="Fermer">✕</button></div>'
          + '<div class="modal-body"><div class="prose ai-proposal">' + d.html + '</div>'
          + '<p class="small muted">Relisez et adaptez si besoin : vous restez responsable du contenu de votre fiche.</p>'
          + '<div class="row-wrap mt-2"><button class="btn btn-coral btn-sm" data-use>Utiliser ce texte</button><button class="btn btn-sm" data-cancel>Garder mon texte</button></div></div>';
        document.body.appendChild(dlg);
        const close = () => { dlg.close(); dlg.remove(); };
        dlg.querySelector('[data-use]').addEventListener('click', () => { if (ta && ta.editor) ta.editor.set(d.html); else if (ta) ta.value = d.html; close(); APVS.toast('Texte remplacé : pensez à enregistrer votre fiche.'); });
        dlg.querySelector('[data-cancel]').addEventListener('click', close);
        dlg.querySelector('.icon-btn').addEventListener('click', close);
        dlg.showModal();
      } catch (e) {
        APVS.toast(e.message || 'IA indisponible');
      }
      aiBtn.classList.remove('is-loading');
    });
  }

  /* Photos : glisser-déposer et barre de progression */
  const dz = $('[data-photo-upload]');
  if (dz) {
    const input = $('input[type=file]', dz);
    const bar = $('.dz-progress', dz);
    /* les photos de téléphone sont réduites dans le navigateur avant l'envoi (2 400 px max) :
       envoi plus rapide et pas de surcharge mémoire du serveur */
    const shrink = (file) => new Promise((resolve) => {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) return resolve(file);
      createImageBitmap(file).then((bmp) => {
        const max = 2400;
        const r = Math.min(1, max / Math.max(bmp.width, bmp.height));
        if (r === 1 && file.size < 3e6) return resolve(file);
        const c = document.createElement('canvas');
        c.width = Math.round(bmp.width * r); c.height = Math.round(bmp.height * r);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        c.toBlob((b) => resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.88);
      }).catch(() => resolve(file));
    });
    const send = async () => {
      if (!input.files.length) return;
      const fd = new FormData(dz);
      const files = await Promise.all(Array.from(input.files).map(shrink));
      fd.delete(input.name);
      files.forEach((f) => fd.append(input.name, f, f.name));
      const xhr = new XMLHttpRequest();
      xhr.open('POST', dz.action);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');
      bar.classList.remove('hidden');
      dz.classList.add('is-busy');
      xhr.upload.addEventListener('progress', (e) => { if (e.lengthComputable) $('i', bar).style.width = Math.round(e.loaded / e.total * 100) + '%'; });
      xhr.addEventListener('load', () => {
        let d = {};
        try { d = JSON.parse(xhr.responseText); } catch (e) { d = { error: 'Envoi impossible (fichiers trop lourds ?)' }; }
        if (d.ok) { APVS.toast(d.message); setTimeout(() => location.reload(), 600); }
        else { APVS.toast(d.error || d.message || 'Envoi impossible'); dz.classList.remove('is-busy'); bar.classList.add('hidden'); }
      });
      xhr.addEventListener('error', () => { APVS.toast('Connexion interrompue'); dz.classList.remove('is-busy'); });
      xhr.send(fd);
    };
    input.addEventListener('change', send);
    dz.addEventListener('submit', (e) => { e.preventDefault(); send(); });
    ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('is-over'); }));
    dz.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; send(); } });
  }
})();
