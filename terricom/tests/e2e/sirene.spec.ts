import { expect, test } from '@playwright/test';

// Suppose un jeu de démonstration frais (npm run db:reset) : les décisions ne sont proposées qu'une fois.
test.describe('mises à jour SIRENE', () => {
  test('nouveautés à valider, fermetures et réglages', async ({ page }) => {
    await page.goto('/demo/entrer/collectivite');
    await page.waitForURL(/\/collectivite/);
    await page.goto('/collectivite/entreprises/sirene');
    const creations = page.getByRole('region', { name: 'Nouvelles entreprises' });
    await expect(creations.getByText('Coiffure Élise')).toBeVisible();
    await expect(page.getByRole('link', { name: /Mises à jour SIRENE/ }).first()).toBeVisible();

    // Une seule nouveauté acceptée : fiche précréée et courrier d'invitation.
    for (const box of await creations.getByRole('checkbox').all()) await box.uncheck();
    await creations.getByRole('checkbox', { name: 'Coiffure Élise' }).check();
    await creations.getByRole('button', { name: 'Créer les fiches cochées' }).click();
    await expect(page.getByText('1 fiche précréée.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Imprimer les 1 courriers d’invitation (PDF)' })).toBeVisible();
    await expect(creations.getByText('Coiffure Élise')).toHaveCount(0);
    await expect(creations.getByText('Le Fournil de la Loue')).toBeVisible();

    // Fermetures : l'une archivée, l'autre gardée.
    const closures = page.getByRole('region', { name: 'Fermetures signalées' });
    const boxes = closures.getByRole('checkbox');
    await expect(boxes).toHaveCount(2);
    await boxes.nth(1).uncheck();
    await closures.getByRole('button', { name: 'Archiver les fiches cochées' }).click();
    await expect(page.getByText('1 fiche(s) archivée(s)')).toBeVisible();
    await closures.getByRole('button', { name: 'Garder en ligne' }).click();
    await expect(page.getByText('Aucune fermeture signalée.')).toBeVisible();

    // Réglages : professions de santé exclues en plus.
    const settings = page.getByRole('form', { name: 'Réglages SIRENE' });
    await settings.getByRole('checkbox', { name: /Professions de santé/ }).check();
    await settings.getByRole('textbox').fill('01.11Z, 4791');
    await settings.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Réglages SIRENE enregistrés.')).toBeVisible();
    await expect(settings.getByRole('checkbox', { name: /Professions de santé/ })).toBeChecked();
    await expect(settings.getByRole('textbox')).toHaveValue('0111Z, 4791');
  });
});
