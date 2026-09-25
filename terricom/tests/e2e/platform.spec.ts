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
