import { expect, test } from '@playwright/test';

test.describe('mobile', () => {
  test('portail et site sans défilement horizontal', async ({ page }) => {
    for (const path of ['/', '/valdeloue', '/collectivites', '/pro/revendiquer']) {
      await page.goto(path);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `${path} déborde de ${overflow}px`).toBeLessThanOrEqual(1);
    }
  });

  test('revendication : recherche de sa fiche', async ({ page }) => {
    await page.goto('/pro/revendiquer?q=Martin');
    await expect(page.getByText(/Boulangerie Martin/).first()).toBeVisible();
  });
});
