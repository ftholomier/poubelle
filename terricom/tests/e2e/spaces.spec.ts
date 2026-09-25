import { expect, test } from '@playwright/test';

test.describe('espaces de démonstration', () => {
  test('espace entreprise : tableau de bord de la boulangerie', async ({ page }) => {
    await page.goto('/demo/entrer/pro');
    await page.waitForURL(/\/pro\/[0-9a-f-]{36}/);
    await expect(page.getByText(/Boulangerie Martin/).first()).toBeVisible();
  });

  test('back-office collectivité : tableau de bord et entreprises', async ({ page }) => {
    await page.goto('/demo/entrer/collectivite');
    await page.waitForURL(/\/collectivite/);
    await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible();
    await page.goto('/collectivite/entreprises');
    await expect(page.getByText(/établissements?/).first()).toBeVisible();
  });

  test('console : vue d’ensemble et suivi commercial en modale', async ({ page }) => {
    await page.goto('/demo/entrer/console');
    await page.waitForURL(/\/console/);
    await expect(page.getByText('Console terricom')).toBeVisible();
    await page
      .getByRole('link', { name: /Négociation/ })
      .first()
      .click();
    await expect(page.getByRole('dialog', { name: 'Suivi commercial' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
  });
});
