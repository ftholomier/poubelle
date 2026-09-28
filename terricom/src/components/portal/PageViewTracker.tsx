'use client';

import { usePathname } from 'next/navigation';
import { useEffect } from 'react';
import { sendBeacon } from './Beacon';

/** Page vue du portail à chaque changement d'URL (navigation client comprise). */
export function PageViewTracker({ territoryId }: { territoryId: string }) {
  const pathname = usePathname();
  useEffect(() => {
    sendBeacon({ type: 'PAGE_VIEW', territoryId });
  }, [pathname, territoryId]);
  return null;
}
