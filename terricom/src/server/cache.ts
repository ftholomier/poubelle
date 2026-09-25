/**
 * Petit cache mémoire à durée de vie courte (par instance) pour les agrégats coûteux
 * et peu sensibles au temps réel (compteurs d'accueil, repères de la carte).
 */
type Entry = { at: number; value: Promise<unknown> };

const store = new Map<string, Entry>();

export function memo<T>(key: string, ttlMs: number, fn: () => Promise<T>): Promise<T> {
  const hit = store.get(key);
  if (hit && Date.now() - hit.at < ttlMs) return hit.value as Promise<T>;
  const value = fn().catch((err) => {
    store.delete(key);
    throw err;
  });
  store.set(key, { at: Date.now(), value });
  if (store.size > 500) {
    const oldest = [...store.entries()].sort((a, b) => a[1].at - b[1].at).slice(0, 100);
    for (const [k] of oldest) store.delete(k);
  }
  return value;
}

export function invalidate(prefix: string): void {
  for (const k of store.keys()) if (k.startsWith(prefix)) store.delete(k);
}
