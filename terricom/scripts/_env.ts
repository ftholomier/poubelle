import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** Charge .env pour les scripts lancés hors de Next.js (sans écraser l'environnement existant). */
export function loadEnvFile(file = '.env') {
  const path = resolve(process.cwd(), file);
  if (!existsSync(path)) return;
  for (const line of readFileSync(path, 'utf8').split('\n')) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (!m) continue;
    const [, key, raw] = m;
    if (process.env[key] !== undefined) continue;
    process.env[key] = raw.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1');
  }
}
