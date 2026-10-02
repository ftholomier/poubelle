/* Éditeur de texte enrichi léger (sans dépendance) : <textarea data-editor="basic|full" data-upload="/url">.
   Le HTML produit est de toute façon nettoyé côté serveur (liste blanche). */
(() => {
  'use strict';
  const TOOLS = {
    basic: ['bold', 'italic', '|', 'ul', 'ol', '|', 'link', 'unlink', '|', 'clear'],
    full: ['h2', 'h3', 'p', '|', 'bold', 'italic', 'underline', '|', 'ul', 'ol', 'quote', '|', 'link', 'unlink', 'image', 'hr', '|', 'clear', 'source'],
  };
  const LABELS = {
    bold: ['<b>G</b>', 'Gras'], italic: ['<i>I</i>', 'Italique'], underline: ['<u>S</u>', 'Souligné'],
    ul: ['• —', 'Liste à puces'], ol: ['1. —', 'Liste numérotée'], h2: ['T2', 'Titre de section'], h3: ['T3', 'Sous-titre'],
    p: ['¶', 'Paragraphe normal'], quote: ['❝', 'Citation'], link: ['🔗', 'Insérer un lien'], unlink: ['✂', 'Retirer le lien'],
    image: ['🖼', 'Insérer une image'], hr: ['—', 'Séparateur'], clear: ['⌫', 'Effacer la mise en forme'], source: ['&lt;/&gt;', 'Modifier le code HTML'],
  };
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
  const okUrl = (u) => /^(https?:\/\/|mailto:|tel:|\/)/i.test(u);

  const init = (ta) => {
    if (ta.dataset.editorReady) return;
    ta.dataset.editorReady = '1';
    const mode = ta.dataset.editor === 'full' ? 'full' : 'basic';
    const wrap = document.createElement('div');
    wrap.className = 'editor editor-' + mode;
    const bar = document.createElement('div');
    bar.className = 'editor-bar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Mise en forme');
    const area = document.createElement('div');
    area.className = 'editor-area prose';
    area.contentEditable = 'true';
    area.setAttribute('role', 'textbox');
    area.setAttribute('aria-multiline', 'true');
    const label = ta.id ? document.querySelector('label[for="' + ta.id + '"]') : null;
    if (label) { area.setAttribute('aria-label', label.textContent.trim()); label.addEventListener('click', () => area.focus()); }
    area.innerHTML = ta.value.trim() || '<p><br></p>';
    if (ta.placeholder) area.dataset.placeholder = ta.placeholder;

    const sync = () => {
      let html = area.innerHTML.replace(/<p><br><\/p>$/i, '').trim();
      if (html === '<br>') html = '';
      ta.value = html;
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const exec = (cmd, val) => { area.focus(); document.execCommand(cmd, false, val); sync(); };
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ancien navigateur */ }

    const actions = {
      bold: () => exec('bold'), italic: () => exec('italic'), underline: () => exec('underline'),
      ul: () => exec('insertUnorderedList'), ol: () => exec('insertOrderedList'),
      h2: () => exec('formatBlock', '<h2>'), h3: () => exec('formatBlock', '<h3>'), p: () => exec('formatBlock', '<p>'),
      quote: () => exec('formatBlock', '<blockquote>'), hr: () => exec('insertHorizontalRule'),
      unlink: () => exec('unlink'),
      clear: () => { exec('removeFormat'); exec('formatBlock', '<p>'); },
      link: () => {
        const sel = window.getSelection();
        const range = sel && sel.rangeCount ? sel.getRangeAt(0) : null;
        const url = prompt('Adresse du lien (https://…)', 'https://');
        if (!url || !okUrl(url.trim())) return;
        if (range) { sel.removeAllRanges(); sel.addRange(range); }
        if (range && range.collapsed) exec('insertHTML', '<a href="' + esc(url.trim()) + '">' + esc(url.trim()) + '</a>');
        else exec('createLink', url.trim());
      },
      image: () => {
        if (!ta.dataset.upload) {
          const url = prompt('Adresse de l\'image (https://…)');
          if (url && okUrl(url.trim())) exec('insertImage', url.trim());
          return;
        }
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp,image/gif';
        input.addEventListener('change', async () => {
          if (!input.files[0]) return;
          const fd = new FormData();
          fd.append('file', input.files[0]);
          const csrf = (window.APVS && window.APVS.cfg && window.APVS.cfg.csrf) || '';
          fd.append('_csrf', csrf);
          bar.classList.add('is-loading');
          try {
            const res = await fetch(ta.dataset.upload, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf }, credentials: 'same-origin' });
            const d = await res.json();
            if (!res.ok || !d.url) throw new Error(d.error || 'Envoi impossible');
            const alt = prompt('Texte alternatif (description de l\'image)', '') || '';
            exec('insertHTML', '<img src="' + esc(d.url) + '" alt="' + esc(alt) + '">');
          } catch (e) { alert(e.message); }
          bar.classList.remove('is-loading');
        });
        input.click();
      },
      source: () => {
        const showing = !ta.hidden;
        if (showing) { area.innerHTML = ta.value || '<p><br></p>'; ta.hidden = true; area.hidden = false; wrap.classList.remove('is-source'); area.focus(); }
        else { sync(); ta.hidden = false; area.hidden = true; wrap.classList.add('is-source'); ta.focus(); }
      },
    };
    (TOOLS[mode] || TOOLS.basic).forEach((t) => {
      if (t === '|') { const s = document.createElement('span'); s.className = 'sep'; bar.appendChild(s); return; }
      const b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = LABELS[t][0];
      b.title = LABELS[t][1];
      b.setAttribute('aria-label', LABELS[t][1]);
      b.addEventListener('mousedown', (e) => e.preventDefault());
      b.addEventListener('click', () => actions[t]());
      bar.appendChild(b);
    });
    area.addEventListener('input', sync);
    area.addEventListener('blur', sync);
    area.addEventListener('paste', (e) => {
      if (mode === 'full' && e.clipboardData.types.includes('text/html') && e.shiftKey) return;
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      const html = text.split(/\n{2,}/).map((p) => '<p>' + esc(p.trim()).replace(/\n/g, '<br>') + '</p>').join('');
      document.execCommand('insertHTML', false, html);
      sync();
    });
    area.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); actions.link(); }
    });
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar);
    wrap.appendChild(area);
    wrap.appendChild(ta);
    ta.hidden = true;
    ta.classList.add('editor-source');
    if (ta.form) ta.form.addEventListener('submit', () => { if (ta.hidden) sync(); });
    ta.editor = { set(html) { area.innerHTML = html || '<p><br></p>'; sync(); }, get() { sync(); return ta.value; }, area };
  };
  window.APVSEditor = { init };
  document.querySelectorAll('textarea[data-editor]').forEach(init);
})();
