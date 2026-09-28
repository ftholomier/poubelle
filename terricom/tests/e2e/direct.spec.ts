import { expect, test } from '@playwright/test';

test.describe('adhésion directe (collectivité non partenaire)', () => {
  test('une entreprise adhère seule et arrive dans la vitrine nationale', async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto('/pro/adhesion');
    await expect(page.getByRole('heading', { name: 'Adhérez à terricom' })).toBeVisible();
    // Code postal prérempli (25300) : les communes sont proposées automatiquement.
    await expect(page.getByRole('combobox', { name: 'Commune' }).getByRole('option', { name: 'Pontarlier' })).toBeAttached();
    await page.getByRole('combobox', { name: 'Commune' }).selectOption({ label: 'Pontarlier' });
    await page.getByRole('radio', { name: /Adhésion Communication/ }).click();
    await page.getByRole('radio', { name: /Annuel/ }).check();
    await expect(page.getByRole('button', { name: /Adhérer · 490 € HT \/ an/ })).toBeVisible();
    await page.getByRole('button', { name: /Adhérer/ }).click();
    await page.waitForURL(/\/pro\/[0-9a-f-]{36}\/offre\?adhesion=ok/, { timeout: 90_000 });
    await expect(page.getByRole('heading', { name: /Votre adhésion directe/ })).toBeVisible();
    await expect(page.getByText(/Adhésion Communication \(annuel\) actif depuis/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'PDF' }).first()).toBeVisible();
  });

  test('espace de l’adhérent : offre directe et résiliation proposée', async ({ page }) => {
    await page.goto('/demo/entrer/pro-direct?vers=/pro/{est}/offre');
    await expect(page.getByRole('heading', { name: /Votre adhésion directe/ })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Résilier mon adhésion' })).toBeVisible();
    await expect(page.getByText(/Adhésion \(mensuel\) actif depuis/)).toBeVisible();
  });

  test('vitrine nationale et suivi des adhésions dans la console', async ({ page }) => {
    await page.goto('/france');
    await expect(page.getByText('Les commerces et savoir-faire de France')).toBeVisible();
    await page.goto('/demo/entrer/console?vers=/console/adhesions');
    await page.waitForURL(/\/console/);
    await page.goto('/console/adhesions');
    await expect(page.getByText('fiches publiées dans la vitrine nationale')).toBeVisible();
    await expect(page.getByText('CC du Grand Pontarlier').first()).toBeVisible();
    await expect(page.getByText('Atelier de démonstration (adhésion directe)')).toBeVisible();
  });
});
