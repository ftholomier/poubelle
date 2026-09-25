'use client';

import { useEffect } from 'react';

/**
 * Portail servi par chemin (/<territoire>) : demande au service worker de mettre de côté la page hors connexion
 * du portail, à ses couleurs. Sur un domaine dédié, « /hors-ligne » est déjà celle du portail.
 */
export function PortalOffline({ base }: { base: string }) {
  useEffect(() => {
    if (!base || !('serviceWorker' in navigator)) return;
    navigator.serviceWorker.ready.then((reg) => reg.active?.postMessage({ type: 'cache-offline', url: `${base}/hors-ligne` })).catch(() => undefined);
  }, [base]);
  return null;
}
