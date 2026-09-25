import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  test: {
    include: ['tests/unit/**/*.test.ts'],
    environment: 'node',
    // Configuration minimale : les modules serveur valident l'environnement au chargement.
    env: {
      SESSION_SECRET: 'test-session-secret-0123456789abcdef',
      DATA_ENCRYPTION_KEY: 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
      ANALYTICS_SALT: 'test-salt',
      DATABASE_URL: 'postgres://terricom:terricom@localhost:5432/terricom_test',
      TZ: 'UTC',
    },
  },
});
