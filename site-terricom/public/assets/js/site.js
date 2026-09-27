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


// L'application en action : boucles vidéo muettes, chargées et lues seulement quand elles sont visibles.
// Avec « réduire les animations », rien ne démarre seul : l'image d'attente reste, le bouton lance la lecture.
const loops = [...document.querySelectorAll('video.loop')];
const h264 = document.createElement('video').canPlayType('video/mp4; codecs="avc1.640028"') !== '';
const load = (v) => {
  if (!v.src && v.dataset.src) v.src = h264 || !v.dataset.webm ? v.dataset.src : v.dataset.webm;
};
const play = (v) => {
  const fig = v.closest('.anim');
  if (fig?.classList.contains('paused') || v.closest('[hidden]')) return;
  load(v);
  v.play().catch(() => {});
};
if (reduce) loops.forEach((v) => v.closest('.anim')?.classList.add('paused'));
if ('IntersectionObserver' in window) {
  const vio = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (e.isIntersecting) play(e.target);
        else e.target.pause();
      }
    },
    { threshold: 0.35 },
  );
  loops.forEach((v) => vio.observe(v));
} else if (!reduce) loops.forEach(play);
document.querySelectorAll('.anim-toggle').forEach((btn) => {
  btn.addEventListener('click', () => {
    const fig = btn.closest('.anim');
    const v = fig.querySelector('video');
    const paused = !fig.classList.contains('paused');
    fig.classList.toggle('paused', paused);
    btn.setAttribute('aria-label', paused ? 'Lire l’animation' : 'Mettre l’animation en pause');
    if (paused) v.pause();
    else {
      load(v);
      v.play().catch(() => {});
    }
    fig.closest('[data-showcase]')?.dispatchEvent(new CustomEvent('manuel'));
  });
});

// Démonstration par étapes : un onglet par usage ; on passe au suivant à la fin de chaque boucle,
// jusqu'à ce que le visiteur choisisse lui-même.
document.querySelectorAll('[data-showcase]').forEach((sc) => {
  const tabs = [...sc.querySelectorAll('[role=tab]')];
  let auto = !reduce;
  let current = 0;
  const select = (i, focus = false) => {
    current = i;
    tabs.forEach((t, k) => {
      const on = k === i;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      const panel = document.getElementById(t.getAttribute('aria-controls'));
      panel.hidden = !on;
      const v = panel.querySelector('video');
      t.querySelector('.prog').style.width = '0';
      if (!v) return;
      if (on) {
        v.currentTime = 0;
        play(v);
      } else v.pause();
    });
    if (focus) tabs[i].focus();
  };
  sc.addEventListener('manuel', () => (auto = false));
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => {
      auto = false;
      select(i);
    });
    t.addEventListener('keydown', (e) => {
      const d = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
      if (!d) return;
      e.preventDefault();
      auto = false;
      select((i + d + tabs.length) % tabs.length, true);
    });
    const v = document.getElementById(t.getAttribute('aria-controls')).querySelector('video');
    let last = 0;
    v?.addEventListener('timeupdate', () => {
      if (current !== i) return;
      t.querySelector('.prog').style.width = `${(100 * v.currentTime) / (v.duration || 1)}%`;
      if (auto && v.currentTime < last - 0.5) select((i + 1) % tabs.length);
      last = v.currentTime;
    });
  });
});
