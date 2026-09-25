import Link from 'next/link';
import type { ReactNode } from 'react';
import { CONTRACT_TYPES, EVENT_KINDS, type ContractType, type EventKind } from '@/lib/constants';
import { parisParts, relativeTime } from '@/lib/format';
import { Photo } from '@/components/ui/Photo';
import { sized } from '@/lib/images';
import type { EstablishmentCard } from '@/server/services/establishments';
import type { FeedItem } from '@/server/services/portal';

export function SectionHead({ eyebrow, title, action }: { eyebrow: string; title: ReactNode; action?: ReactNode }) {
  return (
    <div className="section-head">
      <div>
        <div className="eyebrow" style={{ marginBottom: 6 }}>
          {eyebrow}
        </div>
        <h2 className="h-section">{title}</h2>
      </div>
      {action}
    </div>
  );
}

/** Carte « Ouvert près de vous » (accueil). */
export function OpenCard({ e, base }: { e: EstablishmentCard; base: string }) {
  return (
    <Link href={`${base}${e.path}`} className="card card-link card-lift" style={{ borderRadius: 18, overflow: 'hidden' }}>
      <div style={{ position: 'relative', height: 190 }}>
        <Photo src={sized(e.coverUrl, 700, 480)} alt="" color={e.color} label={e.name} />
        <span
          className="pill"
          style={{ position: 'absolute', left: 12, top: 12, background: 'var(--paper)', color: 'var(--text)' }}
        >
          {e.open.open ? (
            <>
              <span className="live-dot" style={{ width: 7, height: 7 }} />
              Ouvert{e.open.until ? ` · ${e.open.until}` : ''}
            </>
          ) : (
            <>
              <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--closed)' }} />
              {e.open.next ? `Ouvre ${e.open.next.when ? `${e.open.next.when} ` : ''}à ${e.open.next.time}` : e.open.shortLabel}
            </>
          )}
        </span>
      </div>
      <div style={{ padding: '14px 16px 16px' }}>
        <div style={{ fontSize: 12, fontWeight: 700, color: e.color, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{e.activity}</div>
        <div className="h-card" style={{ margin: '3px 0 2px' }}>
          {e.name}
        </div>
        <div style={{ fontSize: 13, color: 'var(--muted)' }}>{e.communeName}</div>
      </div>
    </Link>
  );
}

/** Carte d'actualité du fil du territoire. */
export function FeedCard({ f, base, imgWidth = 120 }: { f: FeedItem; base: string; imgWidth?: number }) {
  const inner = (
    <>
      <div style={{ width: imgWidth, minHeight: imgWidth > 140 ? 120 : 130, position: 'relative' }}>
        <Photo src={sized(f.image, 300, 300)} alt="" label={f.who} color="var(--brand)" style={{ position: 'absolute', inset: 0 }} />
      </div>
      <div style={{ padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 6 }}>
        <span className="tag" style={{ background: f.bg }}>
          {f.kindLabel}
        </span>
        <div style={{ fontWeight: 700, fontSize: 15, lineHeight: 1.3 }}>{f.title}</div>
        <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 'auto' }}>
          {f.who} · {relativeTime(f.publishedAt)}
        </div>
      </div>
    </>
  );
  const style = { gridTemplateColumns: `${imgWidth}px minmax(0,1fr)` };
  return f.whoPath ? (
    <Link href={`${base}${f.whoPath}#actualites`} className="feed-card" style={style}>
      {inner}
    </Link>
  ) : (
    <div className="feed-card" style={style}>
      {inner}
    </div>
  );
}

/** Ligne de résultat (Explorer). */
export function ResultRow({ e, href, distance }: { e: EstablishmentCard; href: string; distance?: string }) {
  return (
    <Link href={href} className="result-card">
      <div style={{ width: 96, height: 96, borderRadius: 10, overflow: 'hidden' }}>
        <Photo src={sized(e.coverUrl, 300, 300)} alt="" color={e.color} label={e.name} />
      </div>
      <div style={{ minWidth: 0, display: 'flex', flexDirection: 'column', gap: 3, paddingTop: 2 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
          <span style={{ fontSize: 11, fontWeight: 800, color: e.color, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{e.activity}</span>
          <span style={{ fontSize: 12, fontWeight: 700, color: e.open.open ? 'var(--open)' : 'var(--closed)' }}>{e.open.unknown ? '' : e.open.shortLabel}</span>
        </div>
        <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 17 }}>{e.name}</div>
        <div style={{ fontSize: 12, color: 'var(--muted)' }}>
          {e.communeName}
          {distance ? ` · ${distance}` : ''}
        </div>
        {e.tags.length ? (
          <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginTop: 4 }}>
            {e.tags.slice(0, 3).map((t) => (
              <span key={t} className="soft-tag">
                {t}
              </span>
            ))}
          </div>
        ) : null}
      </div>
    </Link>
  );
}

export function CircuitCard({
  c,
  href,
}: {
  c: { name: string; meta: string | null; imageUrl: string | null; tagColor: string; stopCount: number };
  href: string;
}) {
  return (
    <Link href={href} className="circuit-card">
      <Photo src={sized(c.imageUrl, 800, 700)} alt="" style={{ position: 'absolute', inset: 0 }} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,0) 35%,rgba(20,32,27,.88))' }} />
      <span
        style={{
          position: 'absolute',
          top: 14,
          right: 14,
          background: c.tagColor,
          color: 'var(--ink)',
          fontWeight: 800,
          fontSize: 12,
          padding: '6px 10px',
          borderRadius: 999,
          transform: 'rotate(4deg)',
        }}
      >
        {c.stopCount} étapes
      </span>
      <div style={{ position: 'absolute', left: 20, right: 20, bottom: 18 }}>
        <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 26, lineHeight: 1, letterSpacing: '-0.02em' }}>{c.name}</div>
        {c.meta ? <div style={{ fontSize: 13, color: 'var(--sage-4)', marginTop: 6 }}>{c.meta}</div> : null}
      </div>
    </Link>
  );
}

const MONTHS = ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'];

export function DateBox({ date, kind, big = false }: { date: Date; kind: EventKind; big?: boolean }) {
  const p = parisParts(date);
  return (
    <div
      style={{
        background: EVENT_KINDS[kind].bg,
        borderRadius: big ? 16 : 10,
        textAlign: 'center',
        padding: big ? '10px 16px' : '6px 0',
        flexShrink: 0,
      }}
    >
      <div style={{ fontSize: big ? 12 : 11, fontWeight: 800, textTransform: 'uppercase' }}>{MONTHS[p.month - 1]}.</div>
      <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: big ? 44 : 24, lineHeight: 1 }}>{p.day}</div>
    </div>
  );
}

export function EventRow({
  href,
  title,
  where,
  kind,
  startsAt,
}: {
  href: string;
  title: string;
  where: string;
  kind: EventKind;
  startsAt: Date;
}) {
  return (
    <Link href={href} className="agenda-row">
      <DateBox date={startsAt} kind={kind} />
      <div style={{ minWidth: 0 }}>
        <div style={{ fontWeight: 700, fontSize: 15 }}>{title}</div>
        <div style={{ fontSize: 13, color: 'var(--muted)' }}>{where}</div>
      </div>
      <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--muted)', textTransform: 'uppercase' }}>{EVENT_KINDS[kind].label}</span>
    </Link>
  );
}

export function JobCard({
  href,
  title,
  company,
  commune,
  contract,
  image,
}: {
  href: string;
  title: string;
  company: string;
  commune: string;
  contract: ContractType;
  image: string | null;
}) {
  return (
    <Link href={href} className="job-card">
      <div style={{ width: 56, height: 56, borderRadius: 14, overflow: 'hidden' }}>
        <Photo src={sized(image, 120, 120)} alt="" label={company} color="var(--brand)" />
      </div>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 18 }}>{title}</div>
        <div style={{ fontSize: 13, color: 'var(--muted)' }}>
          {company} · {commune}
        </div>
      </div>
      <span className="pill" style={{ background: CONTRACT_TYPES[contract].bg, fontWeight: 800, color: 'var(--ink)' }}>
        {CONTRACT_TYPES[contract].label}
      </span>
    </Link>
  );
}
