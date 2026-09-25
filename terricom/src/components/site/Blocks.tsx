import Link from 'next/link';
import type { ReactNode } from 'react';
import { Photo } from '@/components/ui/Photo';

/** Blocs du site de la marque, d'après l'application « site web » de la charte. */

export function MkHero({
  eyebrow,
  title,
  text,
  actions,
  image,
  sticker,
  imageColor = '#1F6B52',
}: {
  eyebrow: string;
  title: ReactNode;
  text: ReactNode;
  actions?: ReactNode;
  image?: string;
  sticker?: string;
  imageColor?: string;
}) {
  return (
    <section className="mk-wrap mk-hero">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div className="mk-eyebrow" style={{ color: 'var(--green)' }}>
          {eyebrow}
        </div>
        <h1 className="display" style={{ fontSize: 'clamp(40px,5.4vw,76px)', letterSpacing: '-0.04em', lineHeight: 0.95, margin: 0, textWrap: 'balance' }}>
          {title}
        </h1>
        <div style={{ fontSize: 18, color: '#4A514C', maxWidth: 560, lineHeight: 1.5 }}>{text}</div>
        {actions ? <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 6 }}>{actions}</div> : null}
      </div>
      {image ? (
        <div style={{ position: 'relative', minHeight: 320 }}>
          <div style={{ position: 'absolute', inset: 0, borderRadius: 24, overflow: 'hidden' }}>
            <Photo src={image} alt="" label=" " color={imageColor} eager style={{ width: '100%', height: '100%' }} />
          </div>
          {sticker ? (
            <span
              className="display"
              style={{
                position: 'absolute',
                left: -12,
                top: 22,
                background: 'var(--amber)',
                fontSize: 15,
                padding: '8px 13px',
                borderRadius: 10,
                transform: 'rotate(-5deg)',
              }}
            >
              {sticker}
            </span>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}

export function MkSection({ eyebrow, title, children, intro }: { eyebrow?: string; title: string; intro?: ReactNode; children: ReactNode }) {
  return (
    <section className="mk-wrap">
      {eyebrow ? (
        <div className="mk-eyebrow" style={{ color: 'var(--brick)', marginBottom: 8 }}>
          {eyebrow}
        </div>
      ) : null}
      <h2 className="mk-h2" style={{ marginBottom: intro ? 10 : 24 }}>
        {title}
      </h2>
      {intro ? <p style={{ margin: '0 0 24px', fontSize: 17, color: '#4A514C', maxWidth: 760, lineHeight: 1.55 }}>{intro}</p> : null}
      {children}
    </section>
  );
}

export function MkFeature({ title, children, tag, tagBg }: { title: string; children: ReactNode; tag?: string; tagBg?: string }) {
  return (
    <div className="mk-card" style={{ padding: 22, display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <b className="display" style={{ fontSize: 21, letterSpacing: '-0.01em' }}>
          {title}
        </b>
        {tag ? <span style={{ fontSize: 10, fontWeight: 800, padding: '2px 6px', borderRadius: 4, background: tagBg ?? '#D6E8B4' }}>{tag}</span> : null}
      </div>
      <div style={{ fontSize: 14, color: '#4A514C', lineHeight: 1.55 }}>{children}</div>
    </div>
  );
}

export function MkGrid({ children, min = 280 }: { children: ReactNode; min?: number }) {
  return <div style={{ display: 'grid', gridTemplateColumns: `repeat(auto-fit,minmax(${min}px,1fr))`, gap: 14 }}>{children}</div>;
}

export function MkSteps({ steps }: { steps: [string, string][] }) {
  return (
    <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12 }}>
      {steps.map(([t, d], i) => (
        <li key={t} className="mk-card" style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span
            className="display"
            style={{ width: 36, height: 36, borderRadius: '50%', background: 'var(--amber)', color: 'var(--ink)', display: 'grid', placeItems: 'center' }}
            aria-hidden="true"
          >
            {i + 1}
          </span>
          <b style={{ fontSize: 16 }}>{t}</b>
          <span style={{ fontSize: 14, color: '#4A514C', lineHeight: 1.5 }}>{d}</span>
        </li>
      ))}
    </ol>
  );
}

export function MkFaq({ items }: { items: [string, ReactNode][] }) {
  return (
    <div className="mk-card" style={{ padding: '6px 22px' }}>
      {items.map(([q, a]) => (
        <details key={q} className="mk-faq">
          <summary>{q}</summary>
          <div style={{ fontSize: 15, color: '#4A514C', lineHeight: 1.6, padding: '0 0 16px' }}>{a}</div>
        </details>
      ))}
    </div>
  );
}

export function MkCta({ title = 'Voyez votre territoire en vitrine.', text }: { title?: string; text?: string }) {
  return (
    <section className="mk-wrap" style={{ paddingBottom: 80 }}>
      <div
        style={{
          background: 'var(--ink)',
          color: 'var(--cream)',
          borderRadius: 28,
          padding: 36,
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))',
          gap: 24,
          alignItems: 'center',
          position: 'relative',
          overflow: 'hidden',
        }}
      >
        <div
          style={{
            position: 'absolute',
            right: -70,
            bottom: -90,
            width: 260,
            height: 260,
            borderRadius: '50% 50% 50% 14%',
            transform: 'rotate(-45deg)',
            background: 'var(--green)',
            opacity: 0.5,
          }}
        />
        <div style={{ position: 'relative' }}>
          <h2 className="mk-h2" style={{ fontSize: 40, marginBottom: 10 }}>
            {title}
          </h2>
          <p style={{ margin: 0, color: '#AEBDB5', fontSize: 16, lineHeight: 1.5 }}>
            {text ?? 'Démonstration de 30 minutes en visio, préparée avec les entreprises de vos communes issues de la base SIRENE.'}
          </p>
        </div>
        <div style={{ position: 'relative', display: 'flex', gap: 10, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
          <Link href="/demo" className="btn btn-amber">
            Demander une démo
          </Link>
          <Link href="/tarifs" className="btn console-btn-ghost-dark">
            Voir les tarifs
          </Link>
        </div>
      </div>
    </section>
  );
}
