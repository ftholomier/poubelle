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

// Formulaires : envoyés à la plateforme terricom (data-endpoint), qui enregistre la demande dans le suivi
// commercial, prévient l'équipe et envoie un accusé de réception. Si le serveur est injoignable, la demande est
// préparée dans la messagerie de l'internaute (data-mailto), pour qu'elle ne soit jamais perdue.
function mailtoFallback(form) {
  const lines = [];
  for (const el of form.elements) {
    if (!el.name || el.name === 'consentement' || el.name === 'website' || el.type === 'submit') continue;
    const v = el.tagName === 'SELECT' ? el.selectedOptions[0]?.textContent : el.value;
    if (!String(v ?? '').trim()) continue;
    const label = el.closest('.field')?.querySelector('label')?.textContent ?? el.name;
    lines.push(`${label.replace(/\s*\*$/, '')} : ${v}`);
  }
  const subject = form.dataset.subject ?? 'Demande depuis terricom.fr';
  window.location.href = `mailto:${form.dataset.mailto}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`;
}

document.querySelectorAll('form[data-endpoint], form[data-mailto]').forEach((form) => {
  const status = form.querySelector('.form-status');
  const button = form.querySelector('button[type=submit]');
  const say = (text, kind) => {
    if (!status) return;
    status.textContent = text;
    status.className = `form-status ${kind ?? ''}`.trim();
  };
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    if (!form.dataset.endpoint) {
      mailtoFallback(form);
      say('Votre messagerie s’ouvre avec la demande préremplie : il ne reste qu’à l’envoyer.');
      return;
    }
    const data = Object.fromEntries(new FormData(form));
    if (button) button.disabled = true;
    say('Envoi en cours…');
    try {
      const res = await fetch(form.dataset.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const body = await res.json().catch(() => ({}));
      if (res.ok && body.ok) {
        form.classList.add('sent');
        say(body.message ?? 'Merci ! Votre demande est bien arrivée.', 'ok');
        status?.focus?.();
        return;
      }
      if (res.status >= 500) throw new Error('serveur');
      say(body.message ?? 'Merci de vérifier le formulaire.', 'err');
    } catch {
      if (form.dataset.mailto) {
        mailtoFallback(form);
        say(`Notre serveur ne répond pas pour le moment : votre messagerie s’ouvre avec la demande préremplie, il ne reste qu’à l’envoyer à ${form.dataset.mailto}.`, 'err');
      } else say('Notre serveur ne répond pas pour le moment. Merci de réessayer dans quelques minutes.', 'err');
    } finally {
      if (button) button.disabled = false;
    }
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
