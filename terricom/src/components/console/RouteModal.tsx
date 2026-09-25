'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useRef, type ReactNode } from 'react';

/**
 * Fenêtre modale pilotée par l'URL (?crm=…) : le contenu est rendu côté serveur,
 * la fermeture (clic sur le voile, Échap, bouton ×) revient à l'adresse de fond.
 */
export function RouteModal({ closeHref, label, children }: { closeHref: string; label: string; children: ReactNode }) {
  const router = useRouter();
  const ref = useRef<HTMLDivElement>(null);
  const close = useCallback(() => router.push(closeHref, { scroll: false }), [router, closeHref]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !(e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement))
        close();
    };
    window.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const opener = document.activeElement as HTMLElement | null;
    ref.current?.focus();
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
      opener?.focus?.();
    };
  }, [close]);

  return (
    <div className="route-modal" onClick={close}>
      <div ref={ref} role="dialog" aria-modal="true" aria-label={label} tabIndex={-1} className="route-modal-dialog" onClick={(e) => e.stopPropagation()}>
        {children}
      </div>
    </div>
  );
}
