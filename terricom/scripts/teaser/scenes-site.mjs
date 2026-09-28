// Scènes du site commercial (site-terricom/) : l'application utilisée, en boucles de 8 à 14 s qui reviennent à
// leur point de départ (défilement aller-retour), pour des vidéos muettes en lecture continue.
// Préalable : serveur de production et scripts/teaser/preparer.sh, comme pour scenes.mjs.
// Usage : node scripts/teaser/scenes-site.mjs [scène …]   → clips/site-* dans le dossier de travail du teaser
import { launch, context, goto, Rec, saveMissing } from './capture.mjs';

/** Explorateur : la liste et la carte occupent exactement l'écran (sans laisser voir le pied de page). */
async function fitExplorer(page) {
  await page.evaluate(() => {
    const e = document.querySelector('.explore');
    if (!e) return;
    const top = e.getBoundingClientRect().top + window.scrollY;
    const s = document.createElement('style');
    s.textContent = `.explore { height: calc(100vh - ${Math.round(top)}px) !important; min-height: 0 !important; }`;
    document.head.appendChild(s);
  });
}

/** Aller-retour : descend, s'attarde, remonte au point de départ (boucle sans saut). */
async function allerRetour(r, dy, { pause = 40, frames = 50 } = {}) {
  await r.scroll(dy, frames);
  await r.hold(pause);
  await r.scroll(-dy, frames);
}

const SCENES = {
  async recherche(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs');
    const r = new Rec(page, 'site-recherche');
    r.x = 1180;
    r.y = 700;
    await r.hold(20);
    await r.moveTo(page.locator('#hero-q'), 30, { ox: 0.25 });
    await r.click({ after: 4 });
    await r.type('boulangerie', 3);
    await r.hold(8);
    await page.keyboard.press('Enter');
    await r.until(async () => page.url().includes('explorer') && (await page.locator('.leaflet-marker-icon').count()) > 0, { max: 120, min: 4 });
    await fitExplorer(page);
    await r.hold(20);
    const cards = page.locator('.explore a[href*="/haut-doubs/"]');
    for (const i of [0, 1, 2]) {
      const c = cards.nth(i);
      if (await c.count()) {
        await r.moveTo(c, 18, { ox: 0.55 });
        await r.hold(12);
      }
    }
    const mk = page.locator('.leaflet-marker-icon').nth(2);
    if (await mk.count()) {
      await r.moveTo(mk, 30);
      await r.hold(4);
      await r.click({ after: 50 });
    }
    saveMissing(ctx, 'site-recherche');
    r.done();
    await ctx.close();
  },

  async tableau(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite');
    const r = new Rec(page, 'site-tableau');
    r.x = 1250;
    r.y = 180;
    await r.hold(20);
    await r.move(520, 260, 30);
    await r.hold(16);
    await r.move(900, 300, 26);
    await r.hold(16);
    await r.move(760, 520, 26);
    await allerRetour(r, 420, { pause: 50 });
    await r.move(1250, 180, 30);
    await r.hold(10);
    saveMissing(ctx, 'site-tableau');
    r.done();
    await ctx.close();
  },

  async stats(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/statistiques');
    const r = new Rec(page, 'site-stats');
    r.x = 1200;
    r.y = 200;
    await r.hold(16);
    await r.move(600, 330, 28);
    await r.hold(14);
    await r.move(980, 360, 24);
    await r.hold(14);
    await allerRetour(r, 460, { pause: 50 });
    await r.move(1200, 200, 28);
    await r.hold(10);
    r.done();
    await ctx.close();
  },

  async sirene(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/entreprises/sirene');
    const r = new Rec(page, 'site-sirene');
    r.x = 1300;
    r.y = 250;
    await r.hold(16);
    await r.scroll(300, 34);
    const boxes = page.locator('input[name=id]');
    for (const i of [0, 2, 3]) {
      const bx = boxes.nth(i);
      if (!(await bx.count())) continue;
      await r.moveTo(bx, 20);
      await r.click({ after: 10 });
    }
    await r.hold(24);
    for (const i of [0, 2, 3]) {
      const bx = boxes.nth(i);
      if (!(await bx.count())) continue;
      await r.moveTo(bx, 12);
      await r.click({ after: 4 });
    }
    await r.move(1300, 250, 26);
    await r.scroll(-300, 34);
    await r.hold(10);
    r.done();
    await ctx.close();
  },

  async newsletter(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/newsletter');
    const r = new Rec(page, 'site-newsletter');
    r.x = 1200;
    r.y = 300;
    await r.hold(16);
    await r.move(640, 420, 28);
    await allerRetour(r, 420, { pause: 46 });
    await r.move(1200, 300, 28);
    await r.hold(10);
    r.done();
    await ctx.close();
  },

  async mairie(b) {
    const ctx = await context(b, { space: 'haut-doubs-commune' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite');
    const r = new Rec(page, 'site-mairie');
    r.x = 1150;
    r.y = 240;
    await r.hold(16);
    await r.move(560, 300, 28);
    await r.hold(14);
    await r.move(1000, 360, 26);
    await r.hold(10);
    await allerRetour(r, 360, { pause: 46 });
    await r.move(1150, 240, 28);
    await r.hold(10);
    saveMissing(ctx, 'site-mairie');
    r.done();
    await ctx.close();
  },

  async commune(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs/metabief');
    const r = new Rec(page, 'site-commune');
    r.x = 1100;
    r.y = 300;
    await r.hold(20);
    await r.move(760, 520, 26);
    await r.scroll(520, 44);
    await r.hold(30);
    await r.scroll(460, 40);
    await r.hold(30);
    await r.scroll(-980, 60);
    await r.move(1100, 300, 26);
    await r.hold(10);
    saveMissing(ctx, 'site-commune');
    r.done();
    await ctx.close();
  },

  async fiche(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration');
    const r = new Rec(page, 'site-fiche');
    r.x = 1100;
    r.y = 420;
    await r.hold(16);
    await r.move(900, 560, 24);
    await r.scroll(460, 40);
    const it = page.getByText('Itinéraire', { exact: true }).first();
    if (await it.count()) await r.moveTo(it, 24);
    await r.hold(30);
    await r.scroll(-460, 40);
    await r.move(1100, 420, 24);
    await r.hold(10);
    saveMissing(ctx, 'site-fiche');
    r.done();
    await ctx.close();
  },

  async pro(b) {
    const ctx = await context(b, { space: 'pro' });
    const page = await ctx.newPage();
    await goto(page, `/demo/entrer/pro?vers=${encodeURIComponent('/pro/{est}/publications')}`);
    const r = new Rec(page, 'site-pro');
    r.x = 1100;
    r.y = 500;
    await r.hold(14);
    const input = page.locator('input[placeholder^="ex."], input[placeholder*="Nouvelle"]').first();
    await r.moveTo(input, 26, { ox: 0.2 });
    await r.click({ after: 2 });
    await r.type('Arrivage de mont-d’or au lait cru pour les fêtes', 2);
    await r.hold(8);
    const go = page.getByRole('button', { name: /Rédiger pour tous mes canaux/ });
    await r.moveTo(go, 24);
    await r.click({ after: 2 });
    await r.until(async () => (await page.getByText('Instagram').count()) > 0, { max: 90 });
    await r.hold(30);
    await r.scroll(300, 36);
    await r.hold(40);
    await r.scroll(-300, 36);
    await r.hold(8);
    r.done();
    await ctx.close();
  },
};

const want = process.argv.slice(2);
const b = await launch();
for (const [k, fn] of Object.entries(SCENES)) {
  if (want.length && !want.includes(k)) continue;
  try {
    await fn(b);
  } catch (e) {
    console.log(`${k} : ERREUR ${e.message.split('\n')[0]}`);
  }
}
await b.close();
