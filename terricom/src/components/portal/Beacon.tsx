'use client';

import { useEffect } from 'react';

export type BeaconPayload = {
  type: string;
  territoryId?: string;
  establishmentId?: string;
  communeId?: string;
  refId?: string;
  refIds?: string[];
};

/** Envoie un événement de mesure d'audience (sans cookie) au serveur. */
export function sendBeacon(p: BeaconPayload) {
  try {
    const params = new URLSearchParams(window.location.search);
    const body = JSON.stringify({
      ...p,
      path: window.location.pathname,
      referrer: document.referrer || null,
      src: params.get('src') ?? params.get('utm_source'),
      q: params.get('q'),
    });
    if (navigator.sendBeacon) navigator.sendBeacon('/api/t', new Blob([body], { type: 'application/json' }));
    else void fetch('/api/t', { method: 'POST', body, headers: { 'content-type': 'application/json' }, keepalive: true });
  } catch {
    /* la mesure d'audience ne doit jamais gêner la navigation */
  }
}

export function Beacon(props: BeaconPayload) {
  const key = JSON.stringify(props);
  useEffect(() => {
    sendBeacon(props);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  return null;
}

/** Lien suivi : clic d'appel, d'itinéraire, de site web… */
export function TrackedLink({
  href,
  track,
  children,
  className,
  style,
  target,
  ariaLabel,
}: {
  href: string;
  track: BeaconPayload;
  children: React.ReactNode;
  className?: string;
  style?: React.CSSProperties;
  target?: string;
  ariaLabel?: string;
}) {
  return (
    <a
      href={href}
      className={className}
      style={style}
      target={target}
      rel={target === '_blank' ? 'noopener noreferrer' : undefined}
      aria-label={ariaLabel}
      onClick={() => sendBeacon(track)}
    >
      {children}
    </a>
  );
}
