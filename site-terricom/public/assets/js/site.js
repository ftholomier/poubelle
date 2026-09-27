// terricom.fr — comportements communs : menu, en-tête, apparitions, compteurs, formulaires.
document.documentElement.classList.add('js');

// Menu mobile
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('.nav');
if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? 'Fermer' : 'Menu';
    document.body.style.overflow = open ? 'hidden' : '';
  });
  nav.addEventListener('click', (e) => {
    if (e.target.closest('a') && nav.classList.contains('open')) toggle.click();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('open')) toggle.click();
  });
}

// Filet sous l'en-tête au défilement
const header = document.querySelector('.site-header');
const onScroll = () => header?.classList.toggle('scrolled', window.scrollY > 8);
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

// Apparitions progressives et compteurs
const fmt = new Intl.NumberFormat('fr-FR');
function count(el) {
  const to = Number(el.dataset.count);
  const dec = Number(el.dataset.dec ?? 0);
  const suffix = el.dataset.suffix ?? '';
  const t0 = performance.now();
  const dur = 1400;
  const step = (t) => {
    const p = Math.min(1, (t - t0) / dur);
    const v = to * (1 - Math.pow(1 - p, 3));
    el.textContent = (dec ? v.toFixed(dec).replace('.', ',') : fmt.format(Math.round(v))) + suffix;
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if ('IntersectionObserver' in window && !reduce) {
  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (!e.isIntersecting) continue;
        e.target.classList.add('in');
        if (e.target.dataset.count) count(e.target);
        io.unobserve(e.target);
      }
    },
    { rootMargin: '0px 0px -8% 0px' },
  );
  document.querySelectorAll('.reveal, [data-count]').forEach((el) => io.observe(el));
} else {
  document.querySelectorAll('.reveal').forEach((el) => el.classList.add('in'));
}

// Formulaires : le message est préparé dans la messagerie de l'élu ou de l'agent (aucune donnée ne transite
// par un serveur tiers). Destinataire et objet sont portés par l'attribut data-mailto.
document.querySelectorAll('form[data-mailto]').forEach((form) => {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const lines = [];
    for (const [k, v] of data.entries()) {
      if (!String(v).trim() || k === 'consentement') continue;
      const label = form.querySelector(`[name="${k}"]`)?.closest('.field')?.querySelector('label')?.textContent ?? k;
      lines.push(`${label.replace(/\s*\*$/, '')} : ${v}`);
    }
    const subject = form.dataset.subject ?? 'Demande depuis terricom.fr';
    const href = `mailto:${form.dataset.mailto}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`;
    window.location.href = href;
    const status = form.querySelector('.form-status');
    if (status)
      status.textContent = 'Votre messagerie s’ouvre avec la demande préremplie : il ne reste qu’à l’envoyer. Nous répondons sous deux jours ouvrés.';
  });
});

