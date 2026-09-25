import { expect, test } from '@playwright/test';

test.describe('sécurité', () => {
  test('en-têtes de sécurité et CSP avec nonce', async ({ request }) => {
    const res = await request.get('/valdeloue');
    const h = res.headers();
    expect(h['content-security-policy']).toMatch(/script-src 'self' 'nonce-[A-Za-z0-9+/=]+'/);
    expect(h['content-security-policy']).toContain("frame-ancestors 'self'");
    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['x-frame-options']).toBe('SAMEORIGIN');
    expect(h['x-powered-by']).toBeUndefined();
  });

  test('les espaces protégés exigent une connexion', async ({ request }) => {
    for (const path of ['/console', '/collectivite', '/compte']) {
      const res = await request.get(path, { maxRedirects: 0 });
      expect([302, 303, 307]).toContain(res.status());
      expect(res.headers()['location']).toContain('/connexion');
    }
  });

  test('documents privés, exports et métriques refusés sans habilitation', async ({ request }) => {
    expect((await request.get('/api/documents/00000000-0000-4000-8000-000000000000')).status()).toBe(401);
    expect((await request.get('/api/console/audit.csv')).status()).toBe(401);
    expect([401, 403, 404]).toContain((await request.get('/api/metrics')).status());
  });

  test('sondes de santé', async ({ request }) => {
    expect((await request.get('/api/health')).ok()).toBeTruthy();
    const ready = await request.get('/api/ready');
    expect(ready.ok()).toBeTruthy();
    expect((await ready.json()).status).toBe('ready');
  });
});
