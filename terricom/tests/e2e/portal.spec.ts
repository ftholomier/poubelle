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

  test('plan du site et robots', async ({ request }) => {
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.ok()).toBeTruthy();
    expect(await sitemap.text()).toContain('/valdeloue/explorer');
    const robots = await request.get('/robots.txt');
    expect(await robots.text()).toMatch(/Sitemap:/i);
  });
});
