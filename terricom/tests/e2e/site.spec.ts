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
    ]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
    }
    await page.goto('/marque');
    await expect(page.getByText('Le territoire, en vitrine.').first()).toBeVisible();
  });
});
