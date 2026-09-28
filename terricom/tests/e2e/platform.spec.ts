import { expect, test } from '@playwright/test';

test.describe('plateforme : API publique, application installable', () => {
  test('API v1 : clé créée dans le back-office, données publiées, clé révoquée refusée', async ({ page, request }) => {
    await page.goto('/demo/entrer/collectivite?vers=/collectivite/api');
    await page.getByLabel('Nom de la clé').fill(`Essai e2e ${Date.now()}`);
    await page.getByRole('button', { name: 'Créer une clé' }).click();
    const key = (await page.locator('code', { hasText: 'tc_' }).first().innerText()).trim();
    expect(key).toMatch(/^tc_[a-z0-9]{8}_/);

    const headers = { Authorization: `Bearer ${key}` };
    const territory = await request.get('/api/v1/territory', { headers });
    expect(territory.ok()).toBeTruthy();
    expect((await territory.json()).data.slug).toBe('valdeloue');
    const list = await request.get('/api/v1/establishments?commune=ornans&per_page=5', { headers });
    const body = await list.json();
    expect(body.data.length).toBeGreaterThan(0);
    expect(body.meta.per_page).toBe(5);
    expect(body.data[0].commune.slug).toBe('ornans');
    expect(list.headers()['x-ratelimit-limit']).toBe('120');

    expect((await request.get('/api/v1/territory')).status()).toBe(401);

    await page.reload();
    await page
      .getByRole('row', { name: /Essai e2e/ })
      .first()
      .getByRole('button', { name: 'Révoquer' })
      .click();
    await expect(page.getByRole('row', { name: /Essai e2e/ }).first()).toContainText('Révoquée');
    expect((await request.get('/api/v1/territory', { headers })).status()).toBe(401);
  });

  test('description OpenAPI publique', async ({ request }) => {
    const res = await request.get('/api/v1/openapi.json');
    expect(res.ok()).toBeTruthy();
    const spec = await res.json();
    expect(spec.openapi).toBe('3.1.0');
    expect(Object.keys(spec.paths)).toContain('/establishments');
  });

  test('manifeste du portail, icônes et service worker', async ({ page, request }) => {
    const manifest = await (await request.get('/manifest.webmanifest?territoire=valdeloue')).json();
    expect(manifest.name).toBe('Val de Loue');
    expect(manifest.start_url).toContain('/valdeloue');
    const icon = await request.get(manifest.icons[0].src);
    expect(icon.headers()['content-type']).toContain('image/png');
    const sw = await request.get('/sw.js');
    expect(sw.headers()['content-type']).toContain('javascript');
    await page.goto('/valdeloue');
    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest?territoire=valdeloue');
  });
});

test.describe('portail multilingue', () => {
  test('langue par adresse, sélecteur mémorisé, fiche traduite et retour au français', async ({ page }) => {
    await page.goto('/valdeloue?lang=en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByRole('navigation', { name: 'Portal navigation' }).getByRole('link', { name: 'Explore' })).toBeVisible();
    await expect(page.locator('link[rel="alternate"][hreflang="de"]')).toHaveAttribute('href', /lang=de/);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /lang=en/);

    await page.getByRole('group', { name: 'Change language' }).getByRole('button', { name: 'DE' }).click();
    await expect(page).toHaveURL(/lang=de/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'de');

    // Sans paramètre, la préférence mémorisée s'applique ; la fiche affiche sa traduction.
    await page.goto('/valdeloue/ornans/boulangerie/boulangerie-martin');
    await expect(page.getByText('Natursauerteigbrot').first()).toBeVisible();
    await expect(page.getByText('Automatisch aus dem Französischen übersetzt.')).toBeVisible();

    // Les pages légales restent en français, avec un avertissement dans la langue du visiteur.
    await page.goto('/valdeloue/mentions-legales');
    await expect(page.getByText('Diese Seite ist nur auf Französisch verfügbar.')).toBeVisible();

    await page.goto('/valdeloue');
    await page.getByRole('group', { name: 'Sprache wechseln' }).getByRole('button', { name: 'FR' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.getByRole('navigation', { name: 'Navigation du portail' }).getByRole('link', { name: 'Explorer' })).toBeVisible();
  });

  test('back-office : traductions des textes du portail', async ({ page }) => {
    await page.goto('/demo/entrer/collectivite?vers=/collectivite/personnalisation/langues');
    await expect(page.getByLabel('Accroche du portail (Anglais)')).toHaveValue('Shops & local know-how');
    await page.getByLabel('Nom de la newsletter (Allemand)').fill('Der Freitagsbrief');
    await page.getByRole('button', { name: 'Enregistrer les traductions' }).click();
    await expect(page.getByText('Traductions enregistrées')).toBeVisible();
    await expect(page.getByText(/fiches? traduites? sur/)).toBeVisible();
  });
});

test.describe('marque blanche', () => {
  test('activée depuis la console : le portail ne mentionne plus terricom, puis retour à la normale', async ({ page }) => {
    const toggle = async (on: boolean) => {
      await page.goto('/demo/entrer/console?vers=/console/territoires');
      await page.getByRole('row', { name: /^Dole/ }).first().click();
      await expect(page.locator('.display', { hasText: /^Dole$/ })).toBeVisible();
      const box = page.getByLabel('Marque blanche');
      if (on) await box.check();
      else await box.uncheck();
      await page.locator('form', { has: box }).getByRole('button', { name: 'Appliquer' }).click();
      await expect(page.getByRole('button', { name: 'Appliquer' })).toBeEnabled();
      await page.reload();
      if (on) await expect(page.getByLabel('Marque blanche')).toBeChecked();
      else await expect(page.getByLabel('Marque blanche')).not.toBeChecked();
    };
    await toggle(false);
    await page.goto('/dole');
    await expect(page.getByText('Propulsé par terricom.')).toBeVisible();

    await toggle(true);
    await page.goto('/dole');
    await expect(page.getByRole('contentinfo')).toBeVisible();
    await expect(page.getByText('Propulsé par terricom.')).toHaveCount(0);

    await toggle(false);
    await page.goto('/dole');
    await expect(page.getByText('Propulsé par terricom.')).toBeVisible();
  });
});

test.describe('synchronisation des contenus', () => {
  test('flux publics : agenda iCal et actualités RSS du territoire et d’une fiche', async ({ request }) => {
    const ics = await request.get('/valdeloue/agenda.ics');
    expect(ics.headers()['content-type']).toContain('text/calendar');
    expect(await ics.text()).toContain('BEGIN:VEVENT');
    const rss = await request.get('/valdeloue/actualites.xml');
    expect(rss.headers()['content-type']).toContain('application/rss+xml');
    expect(await rss.text()).toContain('<item>');
    const fiche = await request.get('/valdeloue/ornans/boulangerie/boulangerie-martin/actualites.xml');
    expect(fiche.ok()).toBeTruthy();
    expect((await request.get('/valdeloue/ornans/boulangerie/boulangerie-martin/agenda.ics')).headers()['content-type']).toContain('text/calendar');
  });

  test('back-office : agenda externe synchronisé, affiché sur le portail, puis retiré', async ({ page }) => {
    await page.goto(`/demo/entrer/collectivite?vers=${encodeURIComponent('/collectivite/agenda?onglet=synchronisation')}`);
    await expect(page.getByText('Office de tourisme Loue-Lison')).toBeVisible();
    await page.getByLabel('Nom de l’agenda').fill('Agenda e2e');
    await page.getByLabel('Adresse de l’agenda (iCal)').fill('http://localhost:3000/demo/agenda-externe.ics');
    await page.getByRole('button', { name: 'Ajouter et synchroniser' }).click();
    await expect(page.getByText('Agenda e2e')).toBeVisible();
    await expect(page.getByText(/6 événements à venir/).first()).toBeVisible();

    await page.goto('/valdeloue/agenda');
    await expect(page.getByText('Marché nocturne des producteurs').first()).toBeVisible();

    await page.goto(`/demo/entrer/collectivite?vers=${encodeURIComponent('/collectivite/agenda?onglet=synchronisation')}`);
    const before = await page.getByText('Agenda e2e', { exact: true }).count();
    const row = page
      .locator('div', { has: page.getByText('Agenda e2e', { exact: true }) })
      .filter({ has: page.getByRole('button', { name: 'Retirer' }) })
      .last();
    await row.getByRole('button', { name: 'Retirer' }).click();
    await expect(page.getByText('Agenda e2e', { exact: true })).toHaveCount(before - 1);
  });

  test('espace pro : connecteur réservé aux offres Premium et Communication', async ({ page }) => {
    await page.goto('/demo/entrer/pro?vers=/pro/{est}/synchronisation');
    await expect(page.getByText('Vos actualités (RSS)')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Synchronisez vos contenus avec vos outils' })).toBeVisible();

    await page.goto('/demo/entrer/pro-communication?vers=/pro/{est}/synchronisation');
    const url = page.getByLabel('Adresse du connecteur');
    await url.fill('http://exemple.fr/hook');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Adresse sécurisée (https://) attendue.')).toBeVisible();

    // Adresse locale injoignable : acceptée en développement, l'essai échoue proprement.
    await url.fill('https://localhost:9/hook');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Secret de signature')).toBeVisible();
    await page.getByRole('button', { name: 'Envoyer un essai' }).click();
    await expect(page.getByText(/L’essai a échoué/)).toBeVisible();

    await url.fill('');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Secret de signature')).toHaveCount(0);
  });
});
