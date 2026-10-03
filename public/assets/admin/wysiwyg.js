/* Éditeur de texte riche du back-office (sans dépendance).
   <textarea data-wysiwyg> (complet) ou <textarea data-wysiwyg="mini"> (gras, italique, lien).
   Le HTML est nettoyé au collage et, de nouveau, côté serveur (liste blanche). */
(function () {
  'use strict';
  const ALLOWED = { P: [], BR: [], STRONG: [], B: [], EM: [], I: [], U: [], H2: [], H3: [], H4: [], UL: [], OL: [], LI: [], BLOCKQUOTE: [], A: ['href', 'title', 'target'], IMG: ['src', 'alt', 'data-media', 'width', 'height'], FIGURE: [], FIGCAPTION: [], TABLE: [], THEAD: [], TBODY: [], TR: [], TH: ['colspan', 'rowspan'], TD: ['colspan', 'rowspan'], SUP: [], SUB: [], HR: [], CITE: [] };
  const RENAME = { B: 'STRONG', I: 'EM', DIV: 'P', H1: 'H2', H5: 'H4', H6: 'H4' };

  function clean(html) {
    const doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
    const walk = node => {
      [...node.childNodes].forEach(ch => {
        if (ch.nodeType === 8) { ch.remove(); return; }
        if (ch.nodeType !== 1) return;
        let tag = ch.tagName;
        if (['SCRIPT', 'STYLE', 'META', 'LINK', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON', 'SVG', 'TEMPLATE', 'XML'].includes(tag) || /^O:/.test(tag)) { ch.remove(); return; }
        if (RENAME[tag]) {
          const n = doc.createElement(RENAME[tag]);
          while (ch.firstChild) n.appendChild(ch.firstChild);
          [...ch.attributes].forEach(a => n.setAttribute(a.name, a.value));
          ch.replaceWith(n);
          ch = n;
          tag = n.tagName;
        }
        walk(ch);
        if (!ALLOWED[tag]) {
          // balise non permise (span, font…) : on garde le contenu
          const frag = doc.createDocumentFragment();
          while (ch.firstChild) frag.appendChild(ch.firstChild);
          ch.replaceWith(frag);
          return;
        }
        [...ch.attributes].forEach(a => { if (!ALLOWED[tag].includes(a.name)) ch.removeAttribute(a.name); });
        if (tag === 'A') {
          const h = (ch.getAttribute('href') || '').trim();
          if (!/^(https?:|mailto:|tel:|\/|#)/i.test(h)) ch.removeAttribute('href');
          if (ch.getAttribute('target')) ch.setAttribute('target', '_blank');
        }
        if (tag === 'IMG' && !/^(\/|https?:)/i.test(ch.getAttribute('src') || '')) ch.remove();
        if (tag === 'P' && !ch.textContent.trim() && !ch.querySelector('img,br')) ch.remove();
      });
    };
    walk(doc.body);
    return doc.body.innerHTML.replace(/(<br>\s*){3,}/g, '<br><br>').trim();
  }

  const TOOLS = {
    full: [['b', '<b>G</b>', 'Gras (Ctrl+B)'], ['i', '<i>I</i>', 'Italique (Ctrl+I)'], ['sep'], ['h3', 'Titre', 'Intertitre'], ['h4', 'Sous-titre', 'Sous-intertitre'], ['p', '¶', 'Paragraphe'], ['sep'], ['ul', '• Liste', 'Liste à puces'], ['ol', '1. Liste', 'Liste numérotée'], ['quote', '❝ Citation', 'Citation'], ['sep'], ['link', '🔗 Lien', 'Lien vers une adresse'], ['fiche', '🔗 Fiche', 'Lien vers une fiche du musée'], ['img', '🖼 Image', 'Image de la médiathèque'], ['minute', "⏱ Minute", 'Minute de jeu en gras'], ['sep'], ['clear', '⌫', 'Retirer la mise en forme'], ['undo', '↶', 'Annuler'], ['src', '&lt;/&gt;', 'Code HTML']],
    mini: [['b', '<b>G</b>', 'Gras'], ['i', '<i>I</i>', 'Italique'], ['link', '🔗 Lien', 'Lien'], ['fiche', '🔗 Fiche', 'Lien vers une fiche'], ['clear', '⌫', 'Retirer la mise en forme']],
  };

  function words(t) { return (t.trim().match(/\S+/g) || []).length; }

  function make(ta) {
    if (ta.dataset.wysReady) return;
    ta.dataset.wysReady = '1';
    const mode = ta.dataset.wysiwyg === 'mini' ? 'mini' : 'full';
    const box = document.createElement('div');
    box.className = 'wys';
    const bar = document.createElement('div');
    bar.className = 'wys__bar';
    bar.setAttribute('role', 'toolbar');
    bar.innerHTML = TOOLS[mode].map(([k, l, t]) => k === 'sep' ? '<span class="sep"></span>' : '<button type="button" data-cmd="' + k + '" title="' + t + '" aria-label="' + t + '">' + l + '</button>').join('');
    const area = document.createElement('div');
    area.className = 'wys__area';
    area.contentEditable = 'true';
    area.setAttribute('role', 'textbox');
    area.setAttribute('aria-multiline', 'true');
    if (ta.placeholder) area.dataset.ph = ta.placeholder;
    const lab = (ta.id && document.querySelector('label[for="' + ta.id + '"]')) || ta.closest('.f')?.querySelector('.f__k');
    const labText = lab ? [...lab.childNodes].filter(n => !n.classList?.contains('hint')).map(n => n.textContent).join('').replace(/\s+/g, ' ').trim() : '';
    area.setAttribute('aria-label', ta.getAttribute('aria-label') || labText || 'Texte');
    area.innerHTML = clean(ta.value);
    if (mode === 'mini') area.style.minHeight = '70px';
    const foot = document.createElement('div');
    foot.className = 'wys__foot';
    foot.innerHTML = '<span data-words></span><span>Ctrl+B gras · Ctrl+I italique</span>';
    const src = document.createElement('textarea');
    src.className = 'wys__src';
    src.hidden = true;
    src.setAttribute('data-nocollect', '');
    ta.hidden = true;
    ta.parentNode.insertBefore(box, ta);
    box.append(bar, area, src, foot, ta);
    const sync = () => {
      const html = src.hidden ? clean(area.innerHTML) : clean(src.value);
      if (ta.value !== html) { ta.value = html; ta.dispatchEvent(new Event('input', { bubbles: true })); }
      foot.querySelector('[data-words]').textContent = words(area.textContent) + ' mots';
    };
    foot.querySelector('[data-words]').textContent = words(area.textContent) + ' mots';
    area.addEventListener('input', sync);
    area.addEventListener('focus', () => box.classList.add('is-focus'));
    area.addEventListener('blur', () => { box.classList.remove('is-focus'); area.innerHTML = clean(area.innerHTML); sync(); });
    src.addEventListener('input', sync);
    // Valeur modifiée par programme (traduction, brouillon…) : on recharge l'éditeur.
    ta.addEventListener('bo:set', () => { area.innerHTML = clean(ta.value); src.value = ta.value; sync(); });
    area.addEventListener('paste', e => {
      e.preventDefault();
      const html = e.clipboardData.getData('text/html');
      const text = e.clipboardData.getData('text/plain');
      const out = html ? clean(html) : text.split(/\n{2,}/).map(p => '<p>' + p.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>') + '</p>').join('');
      document.execCommand('insertHTML', false, out);
      sync();
    });
    area.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && ['b', 'i'].includes(e.key.toLowerCase())) { e.preventDefault(); exec(e.key.toLowerCase()); }
    });
    let saved = null;
    const keep = () => { const s = getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); };
    const restore = () => { area.focus(); if (saved) { const s = getSelection(); s.removeAllRanges(); s.addRange(saved); } };
    area.addEventListener('keyup', keep);
    area.addEventListener('mouseup', keep);
    async function exec(cmd) {
      if (cmd === 'src') {
        if (src.hidden) { src.value = clean(area.innerHTML); src.hidden = false; area.hidden = true; } else { area.innerHTML = clean(src.value); src.hidden = true; area.hidden = false; }
        bar.querySelector('[data-cmd=src]').classList.toggle('is-on', !src.hidden);
        sync();
        return;
      }
      restore();
      switch (cmd) {
        case 'b': document.execCommand('bold'); break;
        case 'i': document.execCommand('italic'); break;
        case 'h3': document.execCommand('formatBlock', false, 'h3'); break;
        case 'h4': document.execCommand('formatBlock', false, 'h4'); break;
        case 'p': document.execCommand('formatBlock', false, 'p'); break;
        case 'ul': document.execCommand('insertUnorderedList'); break;
        case 'ol': document.execCommand('insertOrderedList'); break;
        case 'quote': document.execCommand('formatBlock', false, 'blockquote'); break;
        case 'undo': document.execCommand('undo'); break;
        case 'clear': document.execCommand('removeFormat'); document.execCommand('formatBlock', false, 'p'); break;
        case 'minute': {
          const m = prompt('Minute de jeu (ex. 33, 90+2) :');
          if (m) document.execCommand('insertHTML', false, '<strong>' + m.replace(/[^\d+]/g, '') + "'</strong>&nbsp;");
          break;
        }
        case 'link': {
          const sel = getSelection().toString();
          const url = prompt('Adresse du lien (https://… ou /chemin/) :', sel && /^https?:|^\//.test(sel) ? sel : 'https://');
          if (url && url !== 'https://') {
            if (sel) document.execCommand('createLink', false, url);
            else document.execCommand('insertHTML', false, '<a href="' + url.replace(/"/g, '%22') + '">' + url.replace(/[<>&]/g, '') + '</a>');
          }
          break;
        }
        case 'fiche': {
          const q = prompt('Fiche à relier (quelques lettres du titre) :', getSelection().toString());
          if (!q) break;
          const r = await fetch('/admin/api/ac?type=fiches&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(x => x.json()).catch(() => ({ items: [] }));
          const items = r.items || [];
          if (!items.length) { window.BO && BO.toast('Aucune fiche trouvée', true); break; }
          const choice = items.length === 1 ? items[0] : items[(parseInt(prompt(items.slice(0, 9).map((it, i) => (i + 1) + '. ' + it.label + (it.meta ? ' (' + it.meta + ')' : '')).join('\n') + '\n\nNuméro de la fiche :', '1'), 10) || 1) - 1];
          if (!choice) break;
          restore();
          const label = getSelection().toString() || choice.label;
          document.execCommand('insertHTML', false, '<a href="' + choice.url + '">' + label.replace(/[<>&]/g, '') + '</a>');
          break;
        }
        case 'img': {
          if (!window.BO) break;
          const files = await BO.pickMedia();
          if (files[0]) {
            restore();
            const rel = files[0];
            const alt = prompt('Texte alternatif (description de l’image) :', '') || '';
            document.execCommand('insertHTML', false, '<img src="' + BO.thumb(rel, 1200) + '" data-media="' + rel + '" alt="' + alt.replace(/"/g, '') + '">');
          }
          break;
        }
      }
      keep();
      sync();
    }
    bar.addEventListener('mousedown', e => { if (e.target.closest('button')) e.preventDefault(); });
    bar.addEventListener('click', e => { const b = e.target.closest('button[data-cmd]'); if (b) exec(b.dataset.cmd); });
  }

  window.BOWys = { init: root => (root || document).querySelectorAll('textarea[data-wysiwyg]').forEach(make), clean };
  document.addEventListener('DOMContentLoaded', () => window.BOWys.init(document));
  if (document.readyState !== 'loading') window.BOWys.init(document);
})();
