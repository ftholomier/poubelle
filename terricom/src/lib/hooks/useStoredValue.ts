'use client';

import { useCallback, useSyncExternalStore } from 'react';

const EVENT = 'terricom:local-storage';

function subscribe(cb: () => void) {
  window.addEventListener('storage', cb);
  window.addEventListener(EVENT, cb);
  return () => {
    window.removeEventListener('storage', cb);
    window.removeEventListener(EVENT, cb);
  };
}

/**
 * Valeur persistée dans le navigateur (préférences locales, jamais de donnée personnelle),
 * synchronisée entre composants et onglets. Renvoie null côté serveur.
 */
export function useStoredValue(key: string): [string | null, (value: string | null) => void] {
  const value = useSyncExternalStore(
    subscribe,
    () => {
      try {
        return localStorage.getItem(key);
      } catch {
        return null;
      }
    },
    () => null,
  );
  const set = useCallback(
    (v: string | null) => {
      try {
        if (v === null) localStorage.removeItem(key);
        else localStorage.setItem(key, v);
      } catch {
        /* navigation privée : la valeur n'est simplement pas conservée */
      }
      window.dispatchEvent(new Event(EVENT));
    },
    [key],
  );
  return [value, set];
}
