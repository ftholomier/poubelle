/* Récit illustré : sommaire qui suit la lecture, barre d'avancement. */
(() => {
  const root = document.querySelector('[data-recit]');
  if (!root) return;
  const main = root.querySelector('.recit__main');
  const chapters = [...root.querySelectorAll('[data-rch]')];
  const links = new Map([...root.querySelectorAll('[data-rtoc-link]')].map(a => [a.dataset.rtocLink, a]));
  const bar = root.querySelector('[data-rtoc-bar]');
  const top = document.createElement('div');
  top.className = 'rprogress';
  top.setAttribute('aria-hidden', 'true');
  document.body.appendChild(top);

  let ticking = false;
  const update = () => {
    ticking = false;
    const r = main.getBoundingClientRect();
    const total = Math.max(1, r.height - innerHeight * 0.6);
    const pct = Math.min(100, Math.max(0, (-r.top + innerHeight * 0.2) / total * 100));
    top.style.width = pct + '%';
    if (bar) bar.style.width = pct + '%';
    // Chapitre en cours : le dernier dont le titre est passé sous le haut de l'écran.
    let current = null;
    for (const c of chapters) {
      if (c.getBoundingClientRect().top < innerHeight * 0.35) current = c.id;
    }
    links.forEach((a, id) => a.classList.toggle('is-on', id === current));
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  addEventListener('resize', update);
  update();
})();
