import { defineConfig, devices } from '@playwright/test';

/**
 * Parcours de bout en bout sur l'instance de démonstration (DEMO_MODE=true, jeu Val de Loue).
 * En local : serveur déjà lancé (npm run dev) ; en CI : `npm run start` après la compilation.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:3000';

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 60_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    { name: 'mobile', use: { ...devices['Pixel 7'] }, testMatch: /responsive\.spec\.ts/ },
  ],
  webServer: process.env.E2E_START ? { command: 'npm run start', url: `${baseURL}/api/ready`, reuseExistingServer: true, timeout: 120_000 } : undefined,
});
