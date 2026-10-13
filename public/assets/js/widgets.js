/* Page « Intégrez le musée sur votre site » : copier le code, essayer le thème sombre, hauteur automatique. */
(() => {
  'use strict';
  const EN = document.documentElement.lang === 'en';
  document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    const t = document.getElementById(b.dataset.copy);
    if (!t) return;
    t.select();
    try { await navigator.clipboard.writeText(t.value); } catch (e) { document.execCommand && document.execCommand('copy'); }
    const old = b.textContent;
    b.textContent = EN ? 'Copied ✓' : 'Copié ✓';
    setTimeout(() => { b.textContent = old; }, 1600);
  }));
  document.querySelectorAll('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
    const k = b.dataset.themeToggle;
    const f = document.querySelector('iframe[data-musee="' + k + '"]');
    if (!f) return;
    const dark = !f.src.includes('theme=sombre');
    const swap = s => s.replace(/theme=(clair|sombre)/, 'theme=' + (dark ? 'sombre' : 'clair')).replace(/data-theme="(clair|sombre)"/, 'data-theme="' + (dark ? 'sombre' : 'clair') + '"');
    f.src = swap(f.src);
    document.querySelectorAll('[data-code="' + k + '"]').forEach(c => { c.value = swap(c.value); });
    b.textContent = dark ? (EN ? 'See in light' : 'Voir en clair') : (EN ? 'See in dark' : 'Voir en sombre');
  }));
  addEventListener('message', e => {
    const d = e.data;
    if (!d || d.type !== 'musee-widget') return;
    document.querySelectorAll('iframe[data-musee]').forEach(f => { if (f.contentWindow === e.source) f.style.height = d.height + 'px'; });
  });
})();

/* Mosaïque : [data-masonry] (grille CSS à rangées de 8 px) ; chaque enfant prend autant de
   rangées que sa hauteur réelle, recalculé à chaque changement de taille (aperçus en iframe). */
(() => {
  'use strict';
  document.querySelectorAll('[data-masonry]').forEach(g => {
    g.classList.add('is-masonry');
    const row = 8;
    const fit = el => {
      const mb = parseFloat(getComputedStyle(el).marginBottom) || 0;
      el.style.gridRowEnd = 'span ' + Math.max(1, Math.ceil((el.getBoundingClientRect().height + mb) / row));
    };
    const ro = 'ResizeObserver' in window ? new ResizeObserver(es => es.forEach(e => fit(e.target))) : null;
    [...g.children].forEach(el => { fit(el); if (ro) ro.observe(el); });
    addEventListener('resize', () => [...g.children].forEach(fit));
    addEventListener('load', () => [...g.children].forEach(fit));
  });
})();
