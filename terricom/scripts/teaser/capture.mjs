// Moteur de capture « comme une vidéo d'écran » : l'application tourne vraiment (serveur de production, données
// réelles), mais le temps de la page est piloté image par image (horloge Playwright + animations CSS pas à pas),
// et un curseur de souris dessiné suit des trajectoires lissées. Chaque image est un JPEG, 30 images par seconde.
import { mkdirSync, readFileSync, existsSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { W } from './commun.mjs';

export const BASE = process.env.BASE ?? 'http://localhost:3100';
const DT = 1000 / 30;
const UNSPLASH = {
  1506905925346: 'territoire.jpg',
  1509440159596: 'pain.jpg',
  1542838132: 'comte-cave.jpg',
  1512389142860: 'noel.jpg',
  1543589077: 'noel.jpg',
  1486297678162: 'montdor.jpg',
  1533900298318: 'roue.jpg',
};
/** Tuiles manquantes demandées pendant les captures (à rendre ensuite). */
export function saveMissing(ctx, name) {
  const f = path.join(W, 'manquantes.txt');
  const old = existsSync(f) ? readFileSync(f, 'utf8').split('\n').filter(Boolean) : [];
  writeFileSync(f, [...new Set([...old, ...ctx.missingTiles])].join('\n') + '\n');
  console.log(`${name} : ${ctx.missingTiles.size} tuiles manquantes`);
}

const HIDE = `body { --demo-bar-h: 0px !important; } .demo-bar, nextjs-portal { display: none !important; }
html { scrollbar-width: none; } ::-webkit-scrollbar { display: none; }
#__cur { position: fixed; left: 0; top: 0; width: 30px; height: 30px; z-index: 2147483647; pointer-events: none; will-change: transform; }
#__cur svg { width: 30px; height: 30px; display: block; filter: drop-shadow(0 2px 3px rgba(0,0,0,.35)); transform-origin: 3px 3px; }
#__rip { position: fixed; z-index: 2147483646; pointer-events: none; width: 16px; height: 16px; margin: -8px 0 0 -8px; border-radius: 50%; border: 3px solid rgba(244,178,102,.95); opacity: 0; }`;

const CURSOR = `<svg viewBox="0 0 30 30"><path d="M4 2.5 L4 24 L9.4 18.9 L13.1 27.2 L16.9 25.6 L13.3 17.5 L20.8 17.3 Z" fill="#fff" stroke="#14201B" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
const HAND = `<svg viewBox="0 0 30 30"><path d="M11 3.5c1.2 0 2 .9 2 2V13l.9-.1c.6-1.4 3-1.3 3.4.3.9-1 3-.6 3.2 1 .9-.7 2.9-.3 3.1 1.4.3 2 .3 5.2-.8 7.6L21.5 27h-9.2l-4.9-7.3c-.9-1.2-.7-2.6.4-3.2 1-.6 2.2-.2 2.9.8l.3.5V5.5c0-1.1.8-2 2-2z" fill="#fff" stroke="#14201B" stroke-width="1.5" stroke-linejoin="round"/></svg>`;

export async function launch() {
  return chromium.launch({ args: ['--enable-unsafe-swiftshader', '--ignore-gpu-blocklist', '--font-render-hinting=none'] });
}

/** Ouvre un contexte (connecté à un espace de démonstration si besoin), avec tuiles et photos locales. */
export async function context(browser, { space = null, w = 1440, h = 810, dpr = 1.5, mobile = false } = {}) {
  const ctx = await browser.newContext({
    viewport: { width: w, height: h },
    deviceScaleFactor: dpr,
    isMobile: mobile,
    hasTouch: mobile,
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    colorScheme: 'light',
  });
  await ctx.route('https://tuiles.terricom.test/**', (route) => {
    const p = new URL(route.request().url()).pathname;
    const f = path.join(W, 'tuiles', p);
    if (!existsSync(f)) {
      ctx.missingTiles?.add(p.slice(1).replace('.jpg', ''));
      return route.fulfill({ status: 404, body: '' });
    }
    route.fulfill({ status: 200, body: readFileSync(f), headers: { 'content-type': 'image/jpeg', 'cache-control': 'max-age=86400' } });
  });
  await ctx.route('https://photos.terricom.test/**', (route) => {
    const p = decodeURIComponent(new URL(route.request().url()).pathname);
    const f = path.join(W, 'photos-web', p);
    if (!existsSync(f)) return route.fulfill({ status: 404, body: '' });
    route.fulfill({ status: 200, body: readFileSync(f), headers: { 'content-type': 'image/jpeg', 'cache-control': 'max-age=86400' } });
  });
  // Photos Unsplash du jeu de démonstration (visibles en production) : remplacées par des photos libres du territoire.
  await ctx.route('https://images.unsplash.com/**', (route) => {
    const id = (new URL(route.request().url()).pathname.match(/photo-(\d+)/) ?? [])[1];
    const f = path.join(W, 'photos-web', UNSPLASH[id] ?? '_');
    if (!existsSync(f)) return route.fulfill({ status: 404, body: '' });
    route.fulfill({ status: 200, body: readFileSync(f), headers: { 'content-type': 'image/jpeg', 'cache-control': 'max-age=86400' } });
  });
  // Pas de réseau externe pendant les captures.
  const missing = new Set();
  ctx.missingTiles = missing;
  await ctx.route(/^https:\/\/(?!tuiles\.terricom\.test|photos\.terricom\.test|images\.unsplash\.com)/, (route) => route.fulfill({ status: 404, body: '' }));
  await ctx.addInitScript(
    ([css, cur, hand]) => {
      const add = () => {
        if (document.getElementById('__cur')) return;
        const s = document.createElement('style');
        s.textContent = css;
        document.head.appendChild(s);
        const c = document.createElement('div');
        c.id = '__cur';
        c.innerHTML = cur;
        c.dataset.a = cur;
        c.dataset.h = hand;
        document.documentElement.appendChild(c);
        const r = document.createElement('div');
        r.id = '__rip';
        document.documentElement.appendChild(r);
        const st = window.__curState;
        if (st) window.__curSet(st.x, st.y, st.p, st.hand, st.show);
      };
      window.__curSet = (x, y, p, hand, show = true) => {
        window.__curState = { x, y, p, hand, show };
        const c = document.getElementById('__cur');
        if (!c) return;
        const want = hand ? 'h' : 'a';
        if (c.dataset.k !== want) {
          c.innerHTML = c.dataset[want];
          c.dataset.k = want;
        }
        c.style.display = show ? 'block' : 'none';
        c.style.transform = `translate(${x - (hand ? 11 : 3)}px, ${y - 3}px) scale(${p ? 0.86 : 1})`;
      };
      window.__rip = (x, y, t) => {
        const r = document.getElementById('__rip');
        if (!r) return;
        r.style.left = x + 'px';
        r.style.top = y + 'px';
        r.style.opacity = t >= 1 ? 0 : String(0.9 * (1 - t));
        r.style.transform = `scale(${1 + t * 3.2})`;
      };
      // Adresses affichées dans les pages : celles de la production plutôt que le serveur local.
      window.__urls = () => {
        const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        for (let n = w.nextNode(); n; n = w.nextNode())
          if (n.nodeValue.includes('localhost:3100'))
            n.nodeValue = n.nodeValue.replace('localhost:3100/haut-doubs', 'haut-doubs.terricom.fr').replace('localhost:3100', 'terricom.fr');
      };
      // Anime les animations CSS (transitions, keyframes) au rythme des images, pas du temps réel.
      window.__step = (dt) => {
        for (const a of document.getAnimations()) {
          if (a.__v === undefined) {
            a.__v = a.currentTime ?? 0;
            try {
              a.pause();
            } catch {}
          }
          if (a.playState === 'finished') continue;
          a.__v += dt;
          const end = a.effect?.getComputedTiming?.().endTime;
          if (typeof end === 'number' && Number.isFinite(end) && a.__v >= end) {
            try {
              a.finish();
            } catch {
              a.currentTime = end;
            }
          } else a.currentTime = a.__v;
        }
      };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add);
      else add();
    },
    [HIDE, CURSOR, HAND],
  );
  await ctx.clock.install();
  if (space) {
    const p = await ctx.newPage();
    await p.goto(`${BASE}/demo/entrer/${space}`, { waitUntil: 'domcontentloaded' });
    await p.close();
  }
  return ctx;
}

const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

export class Rec {
  constructor(page, name) {
    this.page = page;
    this.dir = path.join(W, 'clips', name);
    rmSync(this.dir, { recursive: true, force: true });
    mkdirSync(this.dir, { recursive: true });
    this.n = 0;
    this.x = 700;
    this.y = 500;
    this.pressed = false;
    this.hand = false;
    this.show = true;
    this.marks = {};
    this.ripple = null;
  }
  mark(k) {
    this.marks[k] = this.n;
  }
  async cursor() {
    await this.page.evaluate(([x, y, p, h, s]) => window.__curSet?.(x, y, p, h, s), [this.x, this.y, this.pressed, this.hand, this.show]).catch(() => {});
  }
  /** Avance le temps de la page d'une image et enregistre. */
  async frame() {
    const pg = this.page;
    await pg.clock.runFor(DT).catch(() => {});
    await pg.evaluate((dt) => (window.__step?.(dt), window.__urls?.()), DT).catch(() => {});
    if (this.ripple) {
      const t = (this.n - this.ripple.n) / 14;
      await pg.evaluate(([x, y, t]) => window.__rip?.(x, y, t), [this.ripple.x, this.ripple.y, Math.min(1, t)]).catch(() => {});
      if (t >= 1) this.ripple = null;
    }
    await this.cursor();
    await pg.screenshot({
      path: path.join(this.dir, `f${String(this.n).padStart(5, '0')}.jpg`),
      type: 'jpeg',
      quality: 90,
      animations: 'allow',
      caret: 'initial',
    });
    this.n++;
  }
  async hold(frames) {
    for (let i = 0; i < frames; i++) await this.frame();
  }
  /** Déplacement du curseur sur une courbe douce (légère courbure, comme une main). */
  async move(x, y, frames = 24, { bend = 0.12, hover = true } = {}) {
    const x0 = this.x,
      y0 = this.y;
    const dx = x - x0,
      dy = y - y0;
    const cx = x0 + dx * 0.5 - dy * bend,
      cy = y0 + dy * 0.5 + dx * bend;
    for (let i = 1; i <= frames; i++) {
      const t = ease(i / frames);
      const u = 1 - t;
      this.x = u * u * x0 + 2 * u * t * cx + t * t * x;
      this.y = u * u * y0 + 2 * u * t * cy + t * t * y;
      if (hover) await this.page.mouse.move(this.x, this.y).catch(() => {});
      await this.frame();
    }
  }
  async moveTo(locator, frames = 24, opts = {}) {
    const b = await locator.boundingBox();
    if (!b) throw new Error('élément introuvable pour le curseur');
    const ox = opts.ox ?? 0.5,
      oy = opts.oy ?? 0.5;
    await this.move(b.x + b.width * ox, b.y + b.height * oy, frames, opts);
    return b;
  }
  /** Clic : appui visible, onde ambre, vrai clic dans la page. */
  async click({ after = 6 } = {}) {
    this.pressed = true;
    await this.frame();
    await this.frame();
    this.ripple = { x: this.x, y: this.y, n: this.n };
    await this.page.mouse.click(this.x, this.y).catch(() => {});
    this.pressed = false;
    await this.hold(after);
  }
  /** Frappe au clavier, une touche toutes les `per` images (irrégulier, comme une vraie frappe). */
  async type(text, per = 2) {
    let i = 0;
    for (const ch of text) {
      await this.page.keyboard.type(ch);
      const k = per + ((i * 7) % 3 === 0 ? 1 : 0) + (ch === ' ' ? 1 : 0);
      await this.hold(k);
      i++;
    }
  }
  /** Défilement de page doux (window.scrollBy image par image). */
  async scroll(dy, frames = 30, sel = null) {
    let done = 0;
    for (let i = 1; i <= frames; i++) {
      const target = Math.round(dy * ease(i / frames));
      const step = target - done;
      done = target;
      await this.page.evaluate(([s, sel]) => (sel ? document.querySelector(sel) : window).scrollBy(0, s), [step, sel]);
      await this.frame();
    }
  }
  /** Molette sur la carte (zoom Leaflet), à la position du curseur. */
  async wheel(dy, frames = 20) {
    await this.page.mouse.move(this.x, this.y);
    await this.page.mouse.wheel(0, dy);
    await this.hold(frames);
  }
  /** Attend un état de la page en continuant d'enregistrer (le temps s'écoule à l'écran). */
  async until(fn, { max = 150, min = 0 } = {}) {
    for (let i = 0; i < max; i++) {
      if (i >= min && (await fn().catch(() => false))) return true;
      await this.frame();
    }
    return false;
  }
  /** Attend sans enregistrer (chargement d'une page), en laissant le temps de la page s'écouler. */
  async settle(fn, max = 200) {
    for (let i = 0; i < max; i++) {
      if (await fn().catch(() => false)) break;
      await this.page.clock.runFor(50).catch(() => {});
      await new Promise((r) => setTimeout(r, 40));
    }
  }
  done(extra = {}) {
    writeFileSync(path.join(this.dir, 'clip.json'), JSON.stringify({ frames: this.n, marks: this.marks, ...extra }));
    console.log(`${path.basename(this.dir)} : ${this.n} images (${(this.n / 30).toFixed(1)} s)`);
  }
}

/** Charge une page en laissant le temps factice avancer jusqu'à ce qu'elle soit prête. */
export async function goto(page, url, ready = null) {
  const nav = page.goto(url.startsWith('http') ? url : BASE + url, { waitUntil: 'load' });
  let settled = false;
  nav.then(() => (settled = true)).catch(() => (settled = true));
  for (let i = 0; i < 400 && !settled; i++) {
    await page.clock.runFor(30).catch(() => {});
    await new Promise((r) => setTimeout(r, 25));
  }
  await nav.catch(() => {});
  for (let i = 0; i < 60; i++) {
    await page.clock.runFor(50).catch(() => {});
    await new Promise((r) => setTimeout(r, 30));
    if (!ready || (await ready().catch(() => false))) break;
  }
  // Images et tuiles chargées.
  for (let i = 0; i < 60; i++) {
    const ok = await page.evaluate(() => [...document.images].every((im) => im.complete)).catch(() => true);
    if (ok) break;
    await page.clock.runFor(50).catch(() => {});
    await new Promise((r) => setTimeout(r, 50));
  }
}

/** Position écran d'un point géographique sur la carte Leaflet affichée (déduite d'une tuile visible). */
export async function geoPx(page, lng, lat) {
  return page.evaluate(
    ([lng, lat]) => {
      const tiles = [...document.querySelectorAll('img.leaflet-tile')]
        .map((im) => {
          const m = im.src.match(/\/(\d+)\/(\d+)\/(\d+)\.jpg/);
          const r = im.getBoundingClientRect();
          return m && r.width > 0 ? { z: +m[1], x: +m[2], y: +m[3], r } : null;
        })
        .filter(Boolean)
        .sort((a, b) => b.z - a.z);
      const t = tiles[0];
      if (!t) return null;
      const n = 2 ** t.z;
      const wx = ((lng + 180) / 360) * n * 256;
      const wy = ((1 - Math.asinh(Math.tan((lat * Math.PI) / 180)) / Math.PI) / 2) * n * 256;
      const s = t.r.width / 256;
      return { x: t.r.left + (wx - t.x * 256) * s, y: t.r.top + (wy - t.y * 256) * s, z: t.z };
    },
    [lng, lat],
  );
}
