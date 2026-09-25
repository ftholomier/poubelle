import type { CSSProperties } from 'react';

/**
 * Le symbole terricom : un carré aux trois coins arrondis, pivoté à 45°,
 * qui forme un repère de carte ; au centre le « t ». Sous 24 px, le « t » disparaît.
 */
export function Symbol({
  size = 40,
  bg = 'var(--green)',
  fg = 'var(--amber)',
  letter = 't',
  style,
}: {
  size?: number;
  bg?: string;
  fg?: string;
  letter?: string | null;
  style?: CSSProperties;
}) {
  const showLetter = letter && size >= 24;
  return (
    <span
      aria-hidden="true"
      style={{
        width: size,
        height: size,
        borderRadius: '50% 50% 50% 14%',
        transform: 'rotate(-45deg)',
        background: bg,
        display: 'grid',
        placeItems: 'center',
        flexShrink: 0,
        ...style,
      }}
    >
      {showLetter ? (
        <span
          style={{
            transform: 'rotate(45deg)',
            fontFamily: 'var(--font-display)',
            fontWeight: 800,
            fontSize: Math.round(size * 0.64),
            lineHeight: 1,
            color: fg,
            marginTop: -Math.round(size * 0.077),
          }}
        >
          {letter}
        </span>
      ) : null}
    </span>
  );
}

/** Mot-symbole « terricom. » (le point est en ambre). */
export function Wordmark({
  size = 24,
  color = 'var(--ink)',
  dot = 'var(--amber)',
  style,
}: {
  size?: number;
  color?: string;
  dot?: string;
  style?: CSSProperties;
}) {
  return (
    <span
      style={{
        fontFamily: 'var(--font-display)',
        fontWeight: 800,
        fontSize: size,
        letterSpacing: '-0.05em',
        lineHeight: 0.9,
        color,
        whiteSpace: 'nowrap',
        ...style,
      }}
    >
      terricom<span style={{ color: dot }}>.</span>
    </span>
  );
}

/** Logo principal horizontal : symbole + mot-symbole. */
export function Logo({
  size = 24,
  onDark = false,
  gap,
}: {
  size?: number;
  onDark?: boolean;
  gap?: number;
}) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: gap ?? Math.round(size * 0.35) }}>
      <Symbol
        size={Math.round(size * 1.05)}
        bg={onDark ? 'var(--amber)' : 'var(--green)'}
        fg={onDark ? 'var(--ink)' : 'var(--amber)'}
      />
      <Wordmark size={size} color={onDark ? 'var(--cream)' : 'var(--ink)'} />
    </span>
  );
}

/** Pastille d'initiales d'un territoire (« VL »), légèrement inclinée. */
export function TerritoryBadge({
  initials,
  size = 40,
  bg = 'var(--brand)',
  fg = 'var(--brand-accent)',
  logoUrl,
}: {
  initials: string;
  size?: number;
  bg?: string;
  fg?: string;
  logoUrl?: string | null;
}) {
  if (logoUrl) {
    return <img src={logoUrl} alt="" width={size} height={size} style={{ width: size, height: size, borderRadius: size * 0.3, objectFit: 'cover', transform: 'rotate(-4deg)' }} />;
  }
  return (
    <span
      aria-hidden="true"
      style={{
        width: size,
        height: size,
        borderRadius: Math.round(size * 0.3),
        background: bg,
        color: fg,
        display: 'grid',
        placeItems: 'center',
        fontFamily: 'var(--font-display)',
        fontWeight: 800,
        fontSize: Math.round(size * 0.42),
        transform: 'rotate(-4deg)',
        flexShrink: 0,
      }}
    >
      {initials}
    </span>
  );
}
