import { expect, test } from '@playwright/test';

test.describe('portail du territoire', () => {
  test('accueil du territoire pilote', async ({ page }) => {
    const res = await page.goto('/valdeloue');
    expect(res?.status()).toBe(200);
    await expect(page).toHaveTitle(/Val de Loue/);
  });

  test('recherche et carte : une fiche prête pour Google', async ({ page }) => {
    await page.goto('/valdeloue/explorer?q=boulangerie');
    const first = page.locator('.result-card').first();
    await expect(first).toBeVisible();
    await first.click();
    await page.waitForURL(/\/valdeloue\/[^/]+\/[^/]+\/[^/]+$/);
    const jsonLd = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(jsonLd.join(' ')).toMatch(/"@type":\s*"(LocalBusiness|Bakery|Store|Restaurant)/);
  });

  test('filtres avancés : accessibilité et labels', async ({ page }) => {
    await page.goto('/valdeloue/explorer');
    const count = page.locator('[aria-live=polite] b').first();
    const total = Number((await count.textContent())?.replace(/\s/g, ''));
    await page.getByRole('button', { name: /Plus de filtres/ }).click();
    await page
      .getByRole('button', { name: /Accès PMR/ })
      .first()
      .click();
    await expect(page).toHaveURL(/attributs=acces-pmr/);
    await expect.poll(async () => Number((await count.textContent())?.replace(/\s/g, ''))).toBeLessThan(total);
  });

  test('mini-site d’un commerce : bandeau, pages et formulaire personnalisé', async ({ page }) => {
    await page.goto('/valdeloue/ornans/caviste/la-cave-comtoise');
    const nav = page.getByRole('navigation', { name: 'Pages de La Cave Comtoise' });
    await expect(nav).toBeVisible();
    await expect(page.getByRole('link', { name: 'Commander un coffret' }).first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Commander un coffret' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Je m’abonne' })).toBeVisible();
    await nav.getByRole('link', { name: 'Dégustations du samedi' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Dégustations du samedi' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Au programme ce trimestre' })).toBeVisible();
  });

  test('offre Essentiel : ni pages, ni formulaires, ni abonnement', async ({ page }) => {
    await page.goto('/valdeloue/ornans/boulangerie/boulangerie-martin');
    await expect(page.getByRole('heading', { level: 1, name: 'Boulangerie Martin' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Je m’abonne' })).toHaveCount(0);
    await expect(page.locator('form.custom-form')).toHaveCount(0);
  });

  test('plan du site et robots', async ({ request }) => {
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.ok()).toBeTruthy();
    expect(await sitemap.text()).toContain('/valdeloue/explorer');
    expect(await sitemap.text()).toContain('/la-cave-comtoise/degustations-du-samedi');
    const robots = await request.get('/robots.txt');
    expect(await robots.text()).toMatch(/Sitemap:/i);
  });
});
