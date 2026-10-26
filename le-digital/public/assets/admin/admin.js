/* LE-DIGITAL.com — back-office (JavaScript natif). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const CSRF = ($('meta[name=csrf]') || {}).content || '';
  const el = (tag, attrs = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach(c => { if (c != null) n.append(c.nodeType ? c : document.createTextNode(c)); });
    return n;
  };

  function toast(msg, err = false) {
    const t = $('#toast');
    if (!t) return alert(msg);
    t.textContent = msg; t.classList.toggle('is-err', err); t.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(() => { t.hidden = true; }, err ? 6000 : 3200);
  }

  async function api(url, data, isForm = false) {
    const res = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: Object.assign({ 'X-CSRF': CSRF, Accept: 'application/json' }, isForm ? {} : { 'Content-Type': 'application/json' }),
      body: isForm ? data : JSON.stringify(data || {}),
    });
    let j = {};
    try { j = await res.json(); } catch (e) { /* non JSON */ }
    if (!res.ok || j.ok === false) throw new Error(j.error || `Erreur ${res.status}`);
    return j;
  }

  /* ------------------------------------------------------------------ général */
  function general() {
    $$('[data-side-toggle]').forEach(b => b.addEventListener('click', () => $('#side').classList.toggle('is-open')));
    $$('[data-dismiss]').forEach(b => b.addEventListener('click', () => b.parentElement.remove()));
    // Case à cocher qui affiche / masque un bloc de réglages (ex. SMS).
    $$('input[data-shows]').forEach(c => {
      const t = $(c.dataset.shows);
      if (t) c.addEventListener('change', () => { t.hidden = !c.checked; });
    });
    $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    $$('tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a,button,input,form')) location.href = tr.dataset.href; }));
    $$('[data-copy]').forEach(b => b.addEventListener('click', () => {
      const i = b.parentElement.querySelector('input');
      i.select(); navigator.clipboard ? navigator.clipboard.writeText(i.value).then(() => toast('Copié !')) : (document.execCommand('copy'), toast('Copié !'));
    }));
    $$('[data-copy-text]').forEach(b => b.addEventListener('click', () => {
      const v = location.origin + b.dataset.copyText;
      navigator.clipboard ? navigator.clipboard.writeText(v).then(() => toast('Lien copié !')) : prompt('Lien :', v);
    }));
    $$('[data-test-email]').forEach(b => b.addEventListener('click', async () => {
      b.classList.add('is-loading');
      try { toast((await api('/admin/api/test-email', {})).message); } catch (e) { toast(e.message, true); }
      b.classList.remove('is-loading');
    }));
    $$('[data-test-sms]').forEach(b => b.addEventListener('click', async () => {
      const phone = prompt('Numéro de téléphone pour le test (06… ou +33…) :');
      if (!phone) return;
      b.classList.add('is-loading');
      try { toast((await api('/admin/api/test-sms', { phone })).message); } catch (e) { toast(e.message, true); }
      b.classList.remove('is-loading');
    }));
  }

  /* ------------------------------------------------------------------ médiathèque : sélecteur d'images */
  async function upload(files, onProgress) {
    const fd = new FormData();
    Array.from(files).forEach(f => fd.append('files[]', f));
    if (onProgress) onProgress(`Envoi de ${files.length} image${files.length > 1 ? 's' : ''}…`);
    const r = await api('/admin/api/medias', fd, true);
    if (r.errors && r.errors.length) toast(r.errors.join('\n'), true);
    return r.items || [];
  }

  function pickMedia() {
    return new Promise(resolve => {
      let done = false;
      const close = v => { if (done) return; done = true; modal.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
      const onKey = e => { if (e.key === 'Escape') close(null); };
      const grid = el('div', { class: 'picker-grid' }, el('p', { class: 'muted', text: 'Chargement…' }));
      const file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
      const status = el('span', { class: 'muted small' });
      const render = items => {
        grid.innerHTML = '';
        if (!items.length) grid.append(el('p', { class: 'muted', text: 'Aucune image : envoie la première avec le bouton ci-dessus.' }));
        items.forEach(m => grid.append(el('button', { type: 'button', title: m.alt || m.name, onclick: () => close(m.path) }, el('img', { src: m.thumb, alt: m.alt || '', loading: 'lazy' }))));
      };
      file.addEventListener('change', async () => {
        if (!file.files.length) return;
        try {
          const items = await upload(file.files, t => { status.textContent = t; });
          status.textContent = '';
          if (items.length === 1) return close(items[0].path);
          load();
        } catch (e) { status.textContent = ''; toast(e.message, true); }
      });
      const load = () => fetch('/admin/api/medias', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => render(j.items || [])).catch(() => render([]));
      const modal = el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', onclick: e => { if (e.target === modal) close(null); } },
        el('div', { class: 'modal__box' },
          el('div', { class: 'modal__head' }, el('h2', { text: 'Choisir une image' }), el('button', { type: 'button', class: 'x', 'aria-label': 'Fermer', onclick: () => close(null) }, '×')),
          el('div', { class: 'modal__body' },
            el('div', { class: 'actions' }, el('button', { type: 'button', class: 'btn btn--red', onclick: () => file.click() }, 'Envoyer une image'), status, file),
            grid)));
      document.body.append(modal);
      document.addEventListener('keydown', onKey);
      load();
    });
  }

  /** Champ image réutilisable : aperçu + boutons Choisir / Retirer. */
  function imageField(value, onChange) {
    const prev = el('div', { class: 'imgfield__prev' });
    const path = el('span', { class: 'imgfield__path' });
    const show = v => {
      prev.innerHTML = '';
      if (v) prev.append(el('img', { src: v, alt: '' })); else prev.textContent = 'Aucune image';
      path.textContent = v || '';
      rm.hidden = !v;
    };
    const rm = el('button', { type: 'button', class: 'link-danger', onclick: () => { onChange(''); show(''); } }, 'Retirer');
    const choose = el('button', { type: 'button', class: 'btn btn--ghost', onclick: async () => { const v = await pickMedia(); if (v) { onChange(v); show(v); } } }, 'Choisir…');
    const wrap = el('div', { class: 'imgfield' }, prev, el('div', { class: 'imgfield__btns' }, choose, rm, path));
    show(value);
    return wrap;
  }

  function imageFields() {
    $$('[data-imgfield]').forEach(box => {
      const input = $('input[type=hidden]', box);
      const f = imageField(input.value, v => { input.value = v; });
      if (box.classList.contains('imgfield--dark')) f.classList.add('imgfield--dark');
      box.replaceWith(f);
      f.prepend(input);
    });
  }

  /* ------------------------------------------------------------------ éditeur de contenus générique */
  const LABELS = {
    made_in_france: 'Made in France', copyright: 'Mention en bas de page',
    seo: 'Référencement (titre et description Google)', title: 'Titre', description: 'Description', hero: 'Haut de page',
    tags: 'Étiquettes', tag: 'Étiquette', title_line1: 'Titre — ligne 1', title_line2: 'Titre — ligne 2 (dégradé rouge)',
    rotating_intro: 'Texte avant le mot qui change', rotating_words: 'Mots qui défilent', text: 'Texte', cta: 'Texte du bouton',
    cta_primary: 'Bouton principal', cta_secondary: 'Bouton secondaire', cta_secondary_href: 'Lien du bouton secondaire', note: 'Mention',
    photo: 'Photo', photo_alt: 'Description de la photo (accessibilité)', image: 'Image', bubble_client_name: 'Nom dans la bulle du visiteur',
    bubble_client: 'Bulle du visiteur', bubble_fred_name: 'Nom dans la bulle de Fred', bubble_fred: 'Bulle de Fred',
    card1_title: 'Mini-carte 1 — titre', card1_sub: 'Mini-carte 1 — sous-titre', card2_title: 'Mini-carte 2 — titre', card2_sub: 'Mini-carte 2 — sous-titre',
    marquee: 'Bandeau défilant rouge', stats: 'Bandeau de chiffres', value: 'Valeur', suffix: 'Suffixe', count: 'Compteur animé', accent: 'En rouge',
    label: 'Libellé', services: 'Prestations', ask: 'Bulle « Toi » (question du visiteur)', ask_dark: 'Seconde bulle « Toi » (sombre)', intro: 'Introduction',
    bureau: 'Animation « le bureau qui se range »', enabled: 'Afficher / activer', title_before: 'Titre avant', title_after: 'Titre après', window: 'Titre de la fenêtre',
    hint_start: 'Indication au départ', hint_progress: 'Indication pendant', hint_done: 'Indication à la fin', items: 'Éléments', before: 'Aujourd’hui (avant)', after: 'Avec ton outil (après)',
    kind: 'Type : post, xls ou mail', x: 'Position horizontale (%)', y: 'Position verticale (%)', rot: 'Rotation (degrés)', ia: 'Ce que l’IA fait pour toi',
    kicker: 'Surtitre', title_accent: 'Titre — partie colorée', title_end: 'Titre — fin', tab_before: 'Onglet « avant »', tab_after: 'Onglet « après »', rows: 'Lignes',
    control: 'Encadré rouge', benefits: 'Bénéfices', t: 'Titre', d: 'Texte', demo: 'Démo en direct', simulator: 'Simulateur gain de temps',
    two_ways: 'Deux façons de travailler ensemble', engagements: 'Engagements', about: 'Formateur et développeur', stat_value: 'Chiffre', stat_label: 'Libellé du chiffre',
    quote: 'Citation', solutions: 'Mes solutions', link: 'Lien', steps: 'Comment ça se passe', formula: 'La formule', hint: 'Indication (souris)', hint_touch: 'Indication (écran tactile)',
    reviews: 'Avis', placeholders: 'Emplacements affichés tant qu’il n’y a pas d’avis', who: 'Pour qui', final: 'Appel final', header: 'En-tête', nav: 'Menu',
    key: 'Identifiant technique', href: 'Lien', footer: 'Pied de page', slogan: 'Slogan', slogan_accent: 'Slogan — partie colorée', zones_title: 'Titre « Zones »',
    remote: 'Mention « à distance »', solutions_title: 'Titre « Mes solutions »', links_title: 'Titre « Liens »', links: 'Liens', linkedin_label: 'Libellé LinkedIn',
    id: 'Identifiant (ancre)', home_text: 'Texte court (accueil)', home_ia: '+ IA (accueil)', d_long: 'Texte long (page Accompagnement)', tokens: 'Lettres de la formule',
    tip: 'Secret révélé au survol', tasks: 'Tâches', short: 'Libellé court (récapitulatif)', rate: 'Taux d’automatisation (0 à 1)', default: 'Heures par défaut',
    result_intro: 'Texte avant le résultat', unit: 'Unité', today_label: 'Barre « aujourd’hui »', after_label: 'Barre « après »', weeks_per_year: 'Semaines travaillées / an',
    hours_per_day: 'Heures par journée', demo_devis: 'Démo « Ton devis en 10 secondes »', placeholder: 'Texte d’exemple dans le champ', default_prompt: 'Demande par défaut',
    examples: 'Exemples', prompt: 'Demande', button: 'Bouton', button_again: 'Bouton (à nouveau)', empty: 'Texte quand c’est vide', analyse: 'Analyse express',
    dock: 'Assistant & barre du bas', greeting: 'Message d’accueil de l’assistant', suggestions: 'Suggestions', voice_questions: 'Questions affichées pendant le vocal',
    social_proof: 'Afficher des notifications « preuve sociale » (uniquement si elles reflètent une activité réelle)', social_proof_messages: 'Messages de preuve sociale',
    blocks: 'Blocs des prestations', principles: 'Ma façon de construire', diagnostic: 'Diagnostic gain de temps', why: 'Pourquoi te former avec moi',
    formats: 'Trois façons de suivre la formation', k: 'Surtitre', big: 'Grand texte', todo: 'Encadré « à fournir » (vider quand c’est fourni)', detail: 'Détail (lieux, horaires…)',
    programme: 'Au programme', modules: 'Modules', financing: 'Financement', more: 'Autres financements', more_todo: 'Encadré « à fournir »',
    format: 'Format, durée, financement', format_todo: 'Encadré « à fournir »', skills: 'Ce que tu vas apprendre', skills_title: 'Titre de la liste',
    for_title: '« Pour qui » — titre', for_text: '« Pour qui » — texte', why_title: '« Pourquoi avec moi » — titre', why_text: '« Pourquoi avec moi » — texte',
    float_label: 'Encart flottant — libellé', float_title: 'Encart flottant — titre', badge: 'Badge', badge_text: 'Texte du badge', quiz: 'Quiz « quelle formule ? »',
    questions: 'Questions', options: 'Réponses', score: 'Points attribués (une valeur par formule)', results: 'Résultats (dans le même ordre que les points)', result_intro_q: 'Intro du résultat',
    restart: 'Recommencer', formules: '4 formules', si: 'Pour toi si…', si_label: 'Libellé « Pour toi si… »', how: 'Comment', how_label: 'Libellé « Comment »', badge_24h: 'Badge 24 h',
    intensiti: 'Intensiti', formaliance: 'Formaliance', name: 'Nom', tagline: 'Accroche', strengths: 'Points forts', media: 'Ce qu’on peut afficher', url: 'Lien du site',
    demo_cta: 'Bouton démo', links_todo: 'Mention « liens à fournir »', story: 'Parcours (paragraphes)', story_todo: 'Encadré « texte à personnaliser »',
    story_validated: 'Parcours validé (masque l’encadré « à personnaliser »)', photos: 'Photos en situation', filter_all: 'Filtre « Tous »', themes: 'Thèmes du blog',
    read_suffix: 'Suffixe du temps de lecture', author_name: 'Auteur', author_role: 'Rôle de l’auteur', toc_title: 'Titre du sommaire', article_cta_title: 'Fin d’article — titre',
    article_cta: 'Fin d’article — bouton', related_title: 'Titre « articles similaires »', final_title: 'Appel final — titre', final_cta: 'Appel final — bouton',
    diag: 'Bandeau « diagnostic transmis »', booking: 'Option 1 — réservation', form: 'Option 2 — formulaire', callback: 'Option 3 — rappel', contacts: 'Encarts de contact',
    reassure: 'Réassurance', interests: 'Choix « Je suis intéressé par »', moments: 'Moments (boutons)', moments_label: 'Moments (dans la phrase de confirmation)',
    cities: 'Villes', slug: 'Adresse (ex. besancon)', prep: 'Formule « à … »', of: 'Formule « de … »', cards: 'Cartes', sections: 'Sections', consent: 'Mention de consentement',
  };
  const LONG = /^(text|d|d_long|greeting|intro|description|home_text|ia|home_ia|why_text|for_text|consent|note|done_text|error_text|brief_done|voice_sent_text|voice_error_text|control|stat_label|result_intro|todo|detail|placeholder|default_prompt)$/;
  const IMG = /^(image|photo|logo)$|_image$/;
  const human = k => LABELS[k] || (k.charAt(0).toUpperCase() + k.slice(1)).replace(/_/g, ' ');
  const blank = v => Array.isArray(v) ? (v.length && typeof v[0] === 'number' ? v.map(() => 0) : []) : (v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([k, x]) => [k, blank(x)])) : (typeof v === 'number' ? 0 : (typeof v === 'boolean' ? false : '')));
  const summaryOf = (o, i) => {
    if (typeof o !== 'object' || !o) return String(o || '');
    for (const k of ['t', 'title', 'label', 'name', 'before', 'k', 'si', 'text', 'slug']) if (typeof o[k] === 'string' && o[k]) return o[k];
    return `Élément ${i + 1}`;
  };

  function editor() {
    const root = $('[data-editor]');
    if (!root) return;
    const form = root.closest('form'), hidden = $('[name=content_json]', form);
    const data = JSON.parse($('[data-editor-json]', form).textContent);
    const bar = $('.savebar', form), state = $('[data-dirty-label]', form);
    let dirty = false;
    const touch = () => { if (!dirty) { dirty = true; bar.classList.add('is-dirty'); state.textContent = 'Modifications non enregistrées'; } };
    const autoGrow = ta => { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight + 2) + 'px'; };

    function scalar(key, value, set) {
      if (typeof value === 'boolean') {
        const cb = el('input', { type: 'checkbox', onchange: e => { set(e.target.checked); touch(); } });
        cb.checked = value;
        return el('label', { class: 'ed-field ed-bool' }, cb, el('span', { text: human(key) }));
      }
      if (typeof value === 'number') {
        return el('label', { class: 'ed-field' }, el('span', { text: human(key) }), el('input', { type: 'number', step: 'any', value: String(value), oninput: e => { set(e.target.value === '' ? 0 : +e.target.value); touch(); } }));
      }
      if (IMG.test(key)) {
        return el('div', { class: 'ed-field' }, el('span', { text: human(key) }), imageField(value, v => { set(v); touch(); }));
      }
      const long = LONG.test(key) || String(value).length > 80 || String(value).includes('\n');
      const input = long
        ? el('textarea', { rows: '2', oninput: e => { set(e.target.value); autoGrow(e.target); touch(); } })
        : el('input', { type: 'text', oninput: e => { set(e.target.value); touch(); } });
      input.value = value == null ? '' : String(value);
      if (long) requestAnimationFrame(() => autoGrow(input));
      if (/href|url/.test(key)) input.placeholder = '/page, /page#ancre ou https://…';
      const help = /\{(prep|of|city|h|t|label|moment|name)\}/.test(String(value)) ? el('span', { class: 'ed-help', text: 'Garde les variables entre accolades : elles sont remplacées automatiquement.' }) : null;
      return el('label', { class: 'ed-field' }, el('span', { text: human(key) }), input, help);
    }

    function list(key, arr, setArr) {
      const box = el('div', { class: 'ed-group' }, el('div', { class: 'ed-title', text: human(key) }));
      // Liste de nombres (points du quiz) : une petite ligne de champs.
      if (arr.length && arr.every(v => typeof v === 'number')) {
        box.append(el('div', { class: 'ed-nums' }, arr.map((v, i) => el('input', { type: 'number', step: 'any', value: String(v), 'aria-label': `${human(key)} ${i + 1}`, oninput: e => { arr[i] = +e.target.value || 0; touch(); } }))));
        return box;
      }
      const wrap = el('div', { class: 'ed-list' });
      const isObj = arr.length ? typeof arr[0] === 'object' && arr[0] !== null : false;
      const template = arr.length ? blank(arr[0]) : '';
      const render = () => {
        wrap.innerHTML = '';
        arr.forEach((item, i) => {
          const tools = el('span', { class: 'ed-tools' },
            el('button', { type: 'button', title: 'Monter', 'aria-label': 'Monter', onclick: e => { e.preventDefault(); if (i > 0) { [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]]; touch(); render(); } } }, '↑'),
            el('button', { type: 'button', title: 'Descendre', 'aria-label': 'Descendre', onclick: e => { e.preventDefault(); if (i < arr.length - 1) { [arr[i + 1], arr[i]] = [arr[i], arr[i + 1]]; touch(); render(); } } }, '↓'),
            el('button', { type: 'button', class: 'ed-del', title: 'Supprimer', 'aria-label': 'Supprimer', onclick: e => { e.preventDefault(); if (confirm('Supprimer cet élément ?')) { arr.splice(i, 1); touch(); render(); } } }, '×'));
          if (isObj) {
            const d = el('details', { class: 'ed-item' }, el('summary', {}, el('span', { class: 'ed-num', text: String(i + 1) }), el('span', { class: 'ed-sum', text: summaryOf(item, i) }), tools));
            const body = el('div', { class: 'ed-body' });
            object(item, body);
            d.append(body);
            d.addEventListener('input', () => { $('.ed-sum', d).textContent = summaryOf(item, i); });
            wrap.append(d);
          } else {
            const f = scalar(key, item, v => { arr[i] = v; });
            const row = el('div', { class: 'ed-row' }, f.querySelector('input,textarea') || f, ...tools.childNodes);
            wrap.append(row);
          }
        });
      };
      render();
      box.append(wrap, el('button', { type: 'button', class: 'ed-add', onclick: () => { arr.push(JSON.parse(JSON.stringify(template))); touch(); render(); const last = wrap.lastElementChild; if (last && last.tagName === 'DETAILS') last.open = true; const f = last && last.querySelector('input,textarea'); if (f) f.focus(); } }, '+ Ajouter'));
      return box;
    }

    function object(obj, container) {
      Object.keys(obj).forEach(k => {
        const v = obj[k];
        if (Array.isArray(v)) container.append(list(k, v, nv => { obj[k] = nv; }));
        else if (v && typeof v === 'object') {
          const g = el('fieldset', { class: 'ed-group' }, el('legend', { text: human(k) }));
          object(v, g);
          container.append(g);
        } else container.append(scalar(k, v, nv => { obj[k] = nv; }));
      });
    }

    const scalars = Object.keys(data).filter(k => data[k] === null || typeof data[k] !== 'object');
    if (scalars.length) {
      const d = el('details', { class: 'ed-sec', open: true }, el('summary', { text: 'Textes généraux' }));
      const b = el('div', { class: 'ed-body' });
      scalars.forEach(k => b.append(scalar(k, data[k], nv => { data[k] = nv; })));
      d.append(b); root.append(d);
    }
    Object.keys(data).filter(k => !scalars.includes(k)).forEach((k, i) => {
      const d = el('details', { class: 'ed-sec', open: !scalars.length && i === 0 }, el('summary', { text: human(k) }));
      const b = el('div', { class: 'ed-body' });
      if (Array.isArray(data[k])) b.append(list(k, data[k], nv => { data[k] = nv; }));
      else object(data[k], b);
      d.append(b); root.append(d);
    });

    form.addEventListener('submit', () => { hidden.value = JSON.stringify(data); dirty = false; });
    addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  /* ------------------------------------------------------------------ éditeur d'articles */
  function rte() {
    const box = $('[data-rte]');
    if (!box) return;
    const area = $('[data-rte-area]', box), src = $('[data-rte-src]', box), form = box.closest('form');
    let sourceMode = false;
    const sync = () => { if (!sourceMode) src.value = area.innerHTML; };
    document.execCommand('defaultParagraphSeparator', false, 'p');
    $('.rte__bar', box).addEventListener('click', async e => {
      const b = e.target.closest('button');
      if (!b) return;
      const cmd = b.dataset.cmd;
      if (cmd === 'source') {
        sourceMode = !sourceMode;
        if (sourceMode) { src.value = area.innerHTML; src.hidden = false; area.hidden = true; } else { area.innerHTML = src.value; src.hidden = true; area.hidden = false; }
        b.classList.toggle('is-on', sourceMode);
        return;
      }
      if (sourceMode) return;
      area.focus();
      if (cmd === 'link') {
        const url = prompt('Adresse du lien (https://… ou /page) :');
        if (url) document.execCommand('createLink', false, url);
      } else if (cmd === 'image') {
        const sel = window.getSelection(), range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
        const url = await pickMedia();
        if (url) { area.focus(); if (range) { sel.removeAllRanges(); sel.addRange(range); } document.execCommand('insertHTML', false, `<figure><img src="${url}" alt=""></figure><p><br></p>`); }
      } else if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, b.dataset.val);
      } else {
        document.execCommand(cmd, false, null);
      }
      sync();
    });
    area.addEventListener('input', sync);
    area.addEventListener('paste', e => {
      // Colle en texte simple (évite les styles parasites de Word / Google Docs).
      const html = e.clipboardData.getData('text/html');
      if (!html) return;
      e.preventDefault();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      doc.querySelectorAll('style,script,meta,link').forEach(n => n.remove());
      doc.body.querySelectorAll('*').forEach(n => { [...n.attributes].forEach(a => { if (!['href', 'src', 'alt'].includes(a.name)) n.removeAttribute(a.name); }); });
      document.execCommand('insertHTML', false, doc.body.innerHTML);
    });
    form.addEventListener('submit', () => { if (sourceMode) area.innerHTML = src.value; src.value = area.innerHTML; });
  }

  /* ------------------------------------------------------------------ médiathèque */
  function mediaPage() {
    const dz = $('[data-dropzone]');
    if (!dz) return;
    const input = $('#media-files'), prog = $('[data-progress]', dz);
    const send = async files => {
      if (!files.length) return;
      prog.hidden = false;
      try { await upload(files, t => { prog.textContent = t; }); location.reload(); }
      catch (e) { prog.hidden = true; toast(e.message, true); }
    };
    input.addEventListener('change', () => send(input.files));
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-over'); }));
    dz.addEventListener('drop', e => send(e.dataTransfer.files));
    $$('[data-alt]').forEach(i => i.addEventListener('change', async () => {
      try { await api(`/admin/api/medias/${encodeURIComponent(i.dataset.alt)}/alt`, { alt: i.value }); toast('Texte alternatif enregistré.'); } catch (e) { toast(e.message, true); }
    }));
  }

  /* ------------------------------------------------------------------ IA : clés et modèles en direct */
  function aiPage() {
    const dataEl = $('#ai-data');
    if (!dataEl) return;
    const D = JSON.parse(dataEl.textContent);
    const fill = (select, provider) => {
      const keep = select.value || select.dataset.value || '';
      const models = D.models[provider] || [];
      select.innerHTML = '';
      if (!provider) { select.append(el('option', { value: '', text: '—' })); select.disabled = true; return; }
      select.disabled = false;
      if (!models.length) select.append(el('option', { value: keep, text: keep || 'Aucun modèle chargé' }));
      models.forEach(m => select.append(el('option', { value: m.id, text: m.name && m.name !== m.id ? `${m.name} — ${m.id}` : m.id })));
      if (keep && !models.some(m => m.id === keep) && models.length) select.prepend(el('option', { value: keep, text: `${keep} (indisponible)` }));
      select.value = keep && [...select.options].some(o => o.value === keep) ? keep : (select.options[0] ? select.options[0].value : '');
    };
    const refreshSelects = () => {
      $$('[data-prov-select]').forEach(ps => {
        const target = $(`[data-model-select="${ps.dataset.target}"]`);
        if (!target) return;
        const prov = ps.value || (ps.name && ps.name.startsWith('features') ? '' : ps.value);
        if (!ps.value && ps.name && ps.name.startsWith('features')) { target.innerHTML = ''; target.append(el('option', { value: '', text: 'Modèle par défaut' })); target.disabled = true; return; }
        fill(target, prov);
      });
    };
    $$('[data-prov-select]').forEach(ps => ps.addEventListener('change', () => {
      const t = $(`[data-model-select="${ps.dataset.target}"]`);
      if (t) t.dataset.value = '';
      refreshSelects();
    }));
    $$('[data-model-filter]').forEach(f => f.addEventListener('input', () => {
      const s = $(`[data-model-select="${f.dataset.modelFilter}"]`), q = f.value.toLowerCase();
      [...s.options].forEach(o => { o.hidden = q && !o.textContent.toLowerCase().includes(q); });
      const first = [...s.options].find(o => !o.hidden);
      if (first && s.selectedOptions[0] && s.selectedOptions[0].hidden) s.value = first.value;
    }));
    refreshSelects();

    const enableProvider = (key, on) => $$(`option[value="${key}"]`).forEach(o => { if (o.closest('[data-prov-select]')) { o.disabled = !on; o.textContent = D.labels[key] + (on || o.closest('[name^=features]') ? '' : ' (pas de clé)'); } });

    $$('[data-provider]').forEach(card => {
      const key = card.dataset.provider, input = $('[data-key]', card), msg = $('[data-msg]', card), status = $('[data-status]', card), acts = $('[data-acts]', card);
      const say = (t, ok) => { msg.textContent = t; msg.hidden = !t; msg.classList.toggle('is-ok', !!ok); };
      const setStatus = (has, masked, n) => {
        card.classList.toggle('is-on', has); acts.hidden = !has;
        status.innerHTML = has ? `<span class="badge badge--ok">Connectée</span> <code></code> · <span data-count>${n}</span> modèles · à l’instant` : '<span class="badge">Non configurée</span>';
        if (has) $('code', status).textContent = masked;
        input.placeholder = has ? 'Nouvelle clé (facultatif)' : 'Clé API';
        enableProvider(key, has);
      };
      $('[data-save-key]', card).addEventListener('click', async e => {
        const v = input.value.trim();
        if (!v) return say('Colle d’abord ta clé.');
        e.target.classList.add('is-loading'); say('Vérification de la clé et chargement des modèles…', true);
        try {
          const r = await api('/admin/api/ia/cle', { provider: key, key: v });
          D.models[key] = r.models.map(m => ({ id: m.id, name: m.name }));
          input.value = '';
          setStatus(true, r.masked, r.models.length);
          if (r.default_provider) { const dp = $('[name=default_provider]'); if (dp && !dp.value) dp.value = r.default_provider; const dm = $('[data-model-select=default_model]'); if (dm && r.default_model) dm.dataset.value = r.default_model; }
          refreshSelects();
          say(`Clé valide ✓ ${r.models.length} modèles disponibles.`, true);
        } catch (err) { say(err.message); }
        e.target.classList.remove('is-loading');
      });
      input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('[data-save-key]', card).click(); } });
      $('[data-refresh]', card).addEventListener('click', async e => {
        e.target.classList.add('is-loading'); say('Actualisation…', true);
        try {
          const r = await api('/admin/api/ia/modeles', { provider: key });
          D.models[key] = r.models.map(m => ({ id: m.id, name: m.name }));
          $('[data-count]', card) && ($('[data-count]', card).textContent = r.models.length);
          refreshSelects(); say(`${r.models.length} modèles à jour ✓`, true);
        } catch (err) { say(err.message); }
        e.target.classList.remove('is-loading');
      });
      $('[data-del-key]', card).addEventListener('click', async () => {
        if (!confirm('Supprimer cette clé API ?')) return;
        try { await api('/admin/api/ia/cle', { provider: key, key: '' }); D.models[key] = []; setStatus(false); refreshSelects(); say('Clé supprimée.', true); } catch (err) { say(err.message); }
      });
    });

    const t = $('[data-ai-test]');
    if (t) $('[data-test-go]', t).addEventListener('click', async e => {
      const out = $('[data-test-out]', t);
      out.hidden = false; out.classList.remove('is-err'); out.textContent = 'Le modèle réfléchit…';
      e.target.classList.add('is-loading');
      try {
        const r = await api('/admin/api/ia/test', { provider: $('[data-test-provider]', t).value, model: $('[data-test-model]', t).value, prompt: $('[data-test-prompt]', t).value });
        out.textContent = `${r.text}\n\n— ${r.secs} s · ${r.in} jetons envoyés · ${r.out} reçus`;
      } catch (err) { out.classList.add('is-err'); out.textContent = err.message; }
      e.target.classList.remove('is-loading');
    });
  }

  /* ------------------------------------------------------------------ avis Google */
  function googlePage() {
    const form = $('form[data-google]');
    if (!form) return;
    const sync = () => {
      const mode = (form.querySelector('input[name=mode]:checked') || {}).value || 'off';
      $$('[data-mode-block]', form).forEach(b => { b.hidden = !b.dataset.modeBlock.split(' ').includes(mode); });
    };
    $$('input[name=mode]', form).forEach(r => r.addEventListener('change', sync));
    sync();

    const q = $('[data-place-q]', form), btn = $('[data-place-search]', form), list = $('[data-place-results]', form);
    if (!q || !btn) return;
    const search = async () => {
      if (q.value.trim().length < 3) return toast('Tape au moins 3 caractères.', true);
      btn.classList.add('is-loading'); btn.disabled = true;
      try {
        const res = await fetch('/admin/api/google/lieux?q=' + encodeURIComponent(q.value.trim()), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || j.ok === false) throw new Error(j.error || `Erreur ${res.status}`);
        list.replaceChildren(...(j.places.length ? j.places.map(p => el('li', {},
          el('span', {}, el('b', { text: p.name }), el('small', { text: [p.address, p.rating ? `★ ${String(p.rating).replace('.', ',')} · ${p.count} avis` : ''].filter(Boolean).join(' · ') })),
          el('button', { type: 'button', class: 'btn btn--ghost', text: 'Choisir', onclick: () => {
            $('[data-place-id]', form).value = p.id; $('[data-place-name]', form).value = p.name;
            list.hidden = true; toast('Établissement choisi : enregistre pour valider.');
          } }))) : [el('li', { text: 'Aucun établissement trouvé. Essaie avec le nom exact de ta fiche Google et la ville.' })]));
        list.hidden = false;
      } catch (e) { toast(e.message, true); }
      btn.classList.remove('is-loading'); btn.disabled = false;
    };
    btn.addEventListener('click', search);
    q.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
  }

  /* ------------------------------------------------------------------ mises à jour */
  function updatesPage() {
    const run = async (btn, url, data, busy) => {
      const label = btn.textContent;
      btn.disabled = true; btn.classList.add('is-loading'); btn.textContent = busy;
      try {
        const r = await api(url, data);
        toast(r.message || 'C’est fait.');
        setTimeout(() => location.reload(), 1200);
      } catch (e) {
        toast(e.message, true);
        btn.disabled = false; btn.classList.remove('is-loading'); btn.textContent = label;
      }
    };
    $$('[data-maj-check]').forEach(b => b.addEventListener('click', () => run(b, '/admin/api/maj/verifier', {}, 'Vérification…')));
    $$('[data-maj-apply]').forEach(b => b.addEventListener('click', () => {
      if (confirm('Installer la dernière version du site maintenant ?\nUne sauvegarde est faite juste avant ; le site répond « mise à jour en cours » pendant quelques secondes.')) {
        run(b, '/admin/api/maj/appliquer', {}, 'Mise à jour en cours…');
      }
    }));
    $$('[data-maj-rollback]').forEach(b => b.addEventListener('click', () => {
      if (confirm('Remettre les fichiers tels qu’ils étaient avant cette mise à jour ?')) run(b, '/admin/api/maj/retour', { backup: b.dataset.majRollback }, 'Retour en cours…');
    }));
  }

  const init = () => [general, imageFields, editor, rte, mediaPage, aiPage, googlePage, updatesPage].forEach(fn => { try { fn(); } catch (e) { console.error(e); } });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
