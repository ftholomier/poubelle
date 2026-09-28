// Scènes filmées dans l'application réelle (serveur de production, données du Haut-Doubs).
// Préalable : serveur de production en mode démonstration (voir docs/teaser/README.md), base préparée par
// scripts/teaser/preparer.sh. Usage : node scripts/teaser/scenes.mjs portail bo-ia …   (sans argument : toutes)
import { launch, context, goto, Rec, saveMissing, geoPx } from './capture.mjs';

const MB = [6.3585, 46.7672]; // Métabief

async function nearestMarker(page, p) {
  return page.evaluate(
    ([px, py]) => {
      let best = null;
      for (const el of document.querySelectorAll('.leaflet-marker-icon')) {
        const b = el.getBoundingClientRect();
        if (b.width === 0) continue;
        const d = Math.hypot(b.x + b.width / 2 - px, b.y + b.height / 2 - py);
        if (!best || d < best.d) best = { d, x: b.x + b.width / 2, y: b.y + b.height / 2 };
      }
      return best;
    },
    [p.x, p.y],
  );
}

const SCENES = {
  // Accueil du portail → recherche → carte → zoom sur Métabief → repère.
  async portail(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs');
    const r = new Rec(page, 'portail');
    r.x = 1180;
    r.y = 760;
    await r.hold(8);
    await r.moveTo(page.locator('#hero-q'), 30, { ox: 0.25 });
    await r.click({ after: 4 });
    r.mark('type');
    await r.type('fromagerie', 3);
    await r.hold(6);
    r.mark('enter');
    await page.keyboard.press('Enter');
    await r.until(async () => page.url().includes('explorer') && (await page.locator('.leaflet-marker-icon').count()) > 0, { max: 120, min: 4 });
    r.mark('explorer');
    await r.hold(16);
    let p = await geoPx(page, ...MB);
    await r.move(p.x, p.y, 30);
    r.mark('map');
    await r.hold(4);
    for (let i = 0; i < 3; i++) {
      await r.wheel(-120, 16);
      p = await geoPx(page, ...MB);
      await r.move(p.x + (i % 2 ? -5 : 6), p.y - 3, 8);
    }
    r.mark('zoomed');
    await r.hold(8);
    const mk = await nearestMarker(page, p);
    await r.move(mk.x, mk.y - 6, 20);
    await r.hold(4);
    await r.click({ after: 30 });
    r.mark('popup');
    saveMissing(ctx, 'portail');
    r.done();
    await ctx.close();
  },

  // Page commune de Métabief (photo, chiffres, carte).
  async commune(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs/metabief');
    const r = new Rec(page, 'commune');
    r.x = 1000;
    r.y = 300;
    await r.hold(10);
    await r.move(760, 520, 24);
    await r.scroll(520, 40);
    await r.hold(8);
    await r.move(1080, 470, 20);
    await r.scroll(420, 36);
    await r.hold(20);
    saveMissing(ctx, 'commune');
    r.done();
    await ctx.close();
  },

  // Fiche d'un commerce (exemple signalé), avec photos.
  async fiche(b) {
    const ctx = await context(b);
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration');
    const r = new Rec(page, 'fiche');
    r.x = 1100;
    r.y = 420;
    await r.hold(10);
    await r.move(900, 560, 22);
    await r.scroll(460, 40);
    await r.hold(10);
    const call = page.getByText('Itinéraire', { exact: true }).first();
    if (await call.count()) await r.moveTo(call, 22);
    await r.hold(16);
    saveMissing(ctx, 'fiche');
    r.done();
    await ctx.close();
  },

  // Assistant de campagne : une phrase, puis « Préparer » (le clic tombe sur la reprise de la musique).
  async 'bo-ia'(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/campagnes');
    const r = new Rec(page, 'bo-ia');
    r.x = 900;
    r.y = 300;
    await r.hold(6);
    const ta = page.locator('#ai-prompt');
    await r.moveTo(ta, 22, { ox: 0.3 });
    await r.click({ after: 2 });
    await page.keyboard.press('Control+A');
    await page.keyboard.press('Delete');
    r.mark('type');
    await r.type('Noël dans le Haut-Doubs : un calendrier de l’Avent avec les commerces des 32 communes', 1);
    r.mark('typed');
    await r.hold(6);
    const btn = page.getByRole('button', { name: /Préparer/ });
    await r.moveTo(btn, 44, { bend: 0.05 });
    r.mark('ready');
    await r.hold(4);
    r.mark('click');
    await r.click({ after: 2 });
    await r.until(async () => (await page.getByText('Créer la campagne').count()) > 0, { max: 90 });
    r.mark('result');
    await r.hold(12);
    await r.scroll(260, 30);
    await r.hold(14);
    const create = page.getByRole('button', { name: 'Créer la campagne' });
    await r.moveTo(create, 26);
    await r.hold(4);
    r.mark('create');
    await r.click({ after: 2 });
    await r.until(async () => page.url().match(/campagnes\/[0-9a-f-]{36}/) !== null, { max: 120 });
    r.mark('campaign');
    await r.hold(40);
    await r.scroll(380, 40);
    await r.hold(20);
    saveMissing(ctx, 'bo-ia');
    r.done();
    await ctx.close();
  },

  // Mises à jour SIRENE : nouveautés réelles du territoire à valider.
  async 'bo-sirene'(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/entreprises/sirene');
    const r = new Rec(page, 'bo-sirene');
    r.x = 1300;
    r.y = 250;
    await r.hold(8);
    await r.scroll(300, 30);
    const boxes = page.locator('input[name=id]');
    for (const i of [1, 3]) {
      const bx = boxes.nth(i);
      await r.moveTo(bx, 20);
      await r.click({ after: 6 });
    }
    await r.scroll(360, 36);
    await r.hold(16);
    r.done();
    await ctx.close();
  },

  // Tableau de bord de la communauté de communes (météo du commerce, carte, podium).
  async 'bo-tableau'(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite');
    const r = new Rec(page, 'bo-tableau');
    r.x = 1200;
    r.y = 200;
    await r.hold(10);
    await r.move(760, 330, 26);
    await r.hold(8);
    await r.scroll(380, 40);
    await r.move(700, 520, 24);
    await r.hold(24);
    saveMissing(ctx, 'bo-tableau');
    r.done();
    await ctx.close();
  },

  async 'bo-stats'(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/statistiques');
    const r = new Rec(page, 'bo-stats');
    r.x = 1100;
    r.y = 180;
    await r.hold(8);
    const pdf = page.getByText(/Générer le rapport/).first();
    if (await pdf.count()) await r.moveTo(pdf, 26);
    await r.hold(10);
    await r.scroll(420, 40);
    await r.hold(16);
    r.done();
    await ctx.close();
  },

  async 'bo-newsletter'(b) {
    const ctx = await context(b, { space: 'haut-doubs' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite/newsletter');
    const r = new Rec(page, 'bo-newsletter');
    r.x = 1200;
    r.y = 300;
    await r.hold(8);
    await r.move(640, 420, 24);
    await r.scroll(360, 40);
    await r.hold(10);
    const send = page.getByText('Programmer l’envoi').first();
    if (await send.count()) await r.moveTo(send, 26);
    await r.hold(16);
    r.done();
    await ctx.close();
  },

  // Espace commerçant : une phrase → l'assistant rédige pour tous les canaux.
  async 'pro-pub'(b) {
    const ctx = await context(b, { space: 'pro' });
    const page = await ctx.newPage();
    await goto(page, `/demo/entrer/pro?vers=${encodeURIComponent('/pro/{est}/publications')}`);
    const r = new Rec(page, 'pro-pub');
    r.x = 1100;
    r.y = 500;
    await r.hold(8);
    const input = page.locator('input[placeholder^="ex."], input[placeholder*="Nouvelle"]').first();
    await r.moveTo(input, 24, { ox: 0.2 });
    await r.click({ after: 2 });
    r.mark('type');
    await r.type('Arrivage de mont-d’or au lait cru pour les fêtes', 1);
    await r.hold(6);
    const go = page.getByRole('button', { name: /Rédiger pour tous mes canaux/ });
    await r.moveTo(go, 24);
    r.mark('click');
    await r.click({ after: 2 });
    await r.until(async () => (await page.getByText('Instagram').count()) > 0, { max: 90 });
    r.mark('result');
    await r.hold(20);
    await r.scroll(300, 36);
    await r.hold(24);
    r.done();
    await ctx.close();
  },

  // Espace de la mairie de Métabief.
  async mairie(b) {
    const ctx = await context(b, { space: 'haut-doubs-commune' });
    const page = await ctx.newPage();
    await goto(page, '/collectivite');
    const r = new Rec(page, 'mairie');
    r.x = 1100;
    r.y = 300;
    await r.hold(10);
    await r.move(700, 400, 26);
    await r.scroll(300, 36);
    await r.hold(20);
    saveMissing(ctx, 'mairie');
    r.done();
    await ctx.close();
  },

  // Téléphone : portail, fiche, allemand.
  async mobile(b) {
    const ctx = await context(b, { w: 390, h: 844, dpr: 2.5, mobile: true });
    const page = await ctx.newPage();
    await goto(page, '/haut-doubs');
    const r = new Rec(page, 'mobile');
    r.show = false;
    await r.hold(12);
    await r.scroll(700, 50);
    await r.hold(14);
    await r.scroll(600, 44);
    await r.hold(12);
    r.mark('fiche');
    await goto(page, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration');
    await r.hold(24);
    await r.scroll(520, 44);
    await r.hold(16);
    r.mark('de');
    await goto(page, '/haut-doubs/explorer?lang=de');
    await r.hold(30);
    saveMissing(ctx, 'mobile');
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
