import { expect, test } from '@playwright/test';

test.describe('site de la marque', () => {
  test('présente la plateforme et les quatre espaces', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('valorisation économique du territoire');
    await expect(page.getByText('Quatre espaces, une seule plateforme')).toBeVisible();
    for (const s of ['Portail public', 'Espace entreprise', 'Back-office collectivité', 'Console plateforme']) {
      await expect(page.getByRole('link', { name: new RegExp(`^${s} →`) })).toBeVisible();
    }
  });

  test('affiche les offres tarifaires lues en base', async ({ page }) => {
    await page.goto('/tarifs');
    await expect(page.getByRole('heading', { name: 'Licence annuelle du territoire' })).toBeVisible();
    await expect(page.getByText('Premium', { exact: true }).first()).toBeVisible();
  });

  test('enregistre une demande de démonstration', async ({ page }) => {
    await page.goto('/demo');
    await page.getByLabel('Prénom *').fill('Nathalie');
    await page.getByLabel('Nom *', { exact: true }).fill('Essai');
    await page.getByLabel('Collectivité *').fill(`CC de test ${Date.now()}`);
    await page.getByLabel('Email professionnel *').fill(`n.essai.${Date.now()}@exemple.test`);
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Demander ma démo' }).click();
    await expect(page.getByRole('status')).toContainText('on vous rappelle sous 48 h');
  });

  test('publie les pages légales et la charte', async ({ page }) => {
    for (const [path, title] of [
      ['/mentions-legales', 'Mentions légales'],
      ['/cgu', 'Conditions générales d’utilisation'],
      ['/confidentialite', 'Politique de confidentialité'],
      ['/accessibilite', 'Déclaration d’accessibilité'],
      ['/plan-du-site', 'Plan du site'],
    ]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
    }
    // Le plan du site ne propose que les pages des modules actifs de chaque portail.
    await expect(page.getByRole('link', { name: 'Circuits' }).first()).toHaveAttribute('href', /\/valdeloue\/circuits$/);
    await page.goto('/marque');
    await expect(page.getByText('Le territoire, en vitrine.').first()).toBeVisible();
  });
});

test.describe('formulaires du site commercial statique (site-terricom)', () => {
  const demo = {
    collectivite: 'Communauté de communes du Test national',
    type: 'CC',
    communes: '18',
    nom: 'Alex Testeur',
    email: `alex.${Date.now()}@exemple.test`,
    format: 'Une visioconférence de trente minutes',
    consentement: 'on',
  };

  test('enregistre une demande de démonstration envoyée en JSON', async ({ request }) => {
    const res = await request.post('/api/site/demonstration', { data: demo });
    expect(res.status()).toBe(200);
    expect(await res.json()).toMatchObject({ ok: true });
  });

  test('refuse un formulaire incomplet avec un message en français', async ({ request }) => {
    const res = await request.post('/api/site/contact', { data: { nom: 'Alex' } });
    expect(res.status()).toBe(422);
    expect((await res.json()).message).toBe('Indiquez votre adresse électronique.');
  });

  test('n’accepte que les origines autorisées', async ({ request, baseURL }) => {
    const bad = await request.post('/api/site/contact', { data: {}, headers: { Origin: 'https://ailleurs.example' } });
    expect(bad.status()).toBe(403);
    // L'origine de l'application est toujours autorisée (en production s'y ajoutent celles de SITE_ORIGINS).
    const origin = new URL(baseURL ?? 'http://localhost:3000').origin;
    const pre = await request.fetch('/api/site/contact', { method: 'OPTIONS', headers: { Origin: origin, 'Access-Control-Request-Method': 'POST' } });
    expect(pre.status()).toBe(204);
    expect(pre.headers()['access-control-allow-origin']).toBe(origin);
  });

  test('ignore silencieusement les robots (champ piège rempli)', async ({ request }) => {
    const res = await request.post('/api/site/contact', { data: { website: 'http://spam.example' } });
    expect(res.status()).toBe(200);
  });
});
