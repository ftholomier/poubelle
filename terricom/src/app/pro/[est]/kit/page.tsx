import { and, count, eq, gte } from 'drizzle-orm';
import Link from 'next/link';
import { fmtInt } from '@/lib/format';
import { db } from '@/server/db';
import { analyticsEvents, circuitStops, circuits } from '@/server/db/schema';
import { KIT_STYLES, type KitStyle } from '@/server/print/kit';
import { qrDataUrl } from '@/server/qr';
import { loadProContext } from '@/server/services/pro';
import { qrUrl } from '@/server/urls';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

export default async function KitPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, base, territory } = ctx;
  const style = (sp.style && sp.style in KIT_STYLES ? sp.style : 'vert') as KitStyle;
  const s = KIT_STYLES[style];
  const monthStart = new Date();
  monthStart.setDate(1);
  monthStart.setHours(0, 0, 0, 0);
  const [scans, stopOf] = await Promise.all([
    db
      .select({ n: count() })
      .from(analyticsEvents)
      .where(and(eq(analyticsEvents.establishmentId, est.id), eq(analyticsEvents.type, 'QR_SCAN'), gte(analyticsEvents.occurredAt, monthStart))),
    db
      .select({ name: circuits.name })
      .from(circuitStops)
      .innerJoin(circuits, eq(circuits.id, circuitStops.circuitId))
      .where(and(eq(circuitStops.establishmentId, est.id), eq(circuits.status, 'PUBLISHED')))
      .limit(1),
  ]);
  const url = qrUrl(territory, est.qrCode);
  const qr = await qrDataUrl(url);
  const dl = (file: string) => `/api/pro/${est.id}/kit/${file}?style=${style}`;
  const items = [
    { label: 'Affichette vitrine A5', detail: `PDF prêt à imprimer · style ${s.label}`, href: dl('affichette.pdf') },
    { label: 'Autocollant rond 10 cm', detail: 'Pour la porte ou la caisse', href: dl('autocollant.pdf') },
    { label: 'Carte de visite avec QR', detail: 'Recto-verso, 85 × 55 mm', href: dl('carte.pdf') },
    { label: 'QR code seul (PNG)', detail: 'Haute définition, 2048 px', href: dl('qr.png') },
    { label: 'QR code seul (SVG)', detail: 'Vectoriel, pour votre imprimeur', href: dl('qr.svg') },
  ];

  return (
    <div className="app-content" style={{ gap: 18 }}>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <span style={{ fontSize: 14, color: 'var(--muted)' }}>Style de l&apos;affichette :</span>
        {(Object.keys(KIT_STYLES) as KitStyle[]).map((k) => (
          <Link
            key={k}
            href={`${base}/kit?style=${k}`}
            scroll={false}
            aria-current={k === style ? 'true' : undefined}
            style={{
              display: 'flex',
              gap: 8,
              alignItems: 'center',
              border: `1.5px solid ${k === style ? 'var(--ink)' : 'var(--line)'}`,
              background: 'var(--paper)',
              padding: '7px 12px',
              borderRadius: 999,
              fontWeight: 700,
              fontSize: 13,
              color: 'var(--text)',
            }}
          >
            <span style={{ width: 14, height: 14, borderRadius: '50%', background: KIT_STYLES[k].bg, border: '1px solid var(--line)' }} />
            {KIT_STYLES[k].label}
          </Link>
        ))}
        <span style={{ marginLeft: 'auto', fontSize: 13, color: 'var(--muted)' }}>
          <b style={{ color: 'var(--text)' }}>{fmtInt(Number(scans[0]?.n ?? 0))}</b> scans ce mois
        </span>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 22, alignItems: 'start' }}>
        <div
          style={{
            background: s.bg,
            color: s.fg,
            borderRadius: 24,
            padding: '34px 30px',
            aspectRatio: '0.72',
            display: 'flex',
            flexDirection: 'column',
            gap: 16,
            boxShadow: '0 24px 50px rgba(20,32,27,.18)',
            transform: 'rotate(-1.5deg)',
            maxWidth: 420,
          }}
        >
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div className="display" style={{ fontSize: 15 }}>
              {territory.name}
            </div>
            <span style={{ background: s.accent, color: 'var(--ink)', fontWeight: 800, fontSize: 11, padding: '4px 8px', borderRadius: 6, transform: 'rotate(4deg)' }}>Fait ici</span>
          </div>
          <div className="display" style={{ fontSize: 40, lineHeight: 0.95, letterSpacing: '-0.03em' }}>
            Retrouvez-nous en ligne !
          </div>
          <div style={{ fontSize: 14, opacity: 0.85 }}>Horaires, nouveautés, bons plans : scannez avec votre téléphone.</div>
          <div style={{ background: '#fff', borderRadius: 18, padding: 14, alignSelf: 'center', marginTop: 'auto' }}>
            <img src={qr} alt={`QR code menant à la fiche ${est.name}`} width={170} height={170} style={{ display: 'block' }} />
          </div>
          <div style={{ textAlign: 'center', fontWeight: 700, fontSize: 15 }}>{est.name}</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div className="display" style={{ fontSize: 30, letterSpacing: '-0.02em', lineHeight: 1 }}>
            Votre kit vitrine, prêt à imprimer
          </div>
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 15, lineHeight: 1.5 }}>
            Le QR code mène à votre fiche et compte chaque scan.
            {stopOf[0] ? ` Il sert aussi de tampon pour le circuit « ${stopOf[0].name} ».` : ''}
          </p>
          {items.map((it) => (
            <a
              key={it.label}
              href={it.href}
              download
              className="card card-link"
              style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderRadius: 14, padding: '14px 16px' }}
            >
              <div>
                <div style={{ fontWeight: 700 }}>{it.label}</div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{it.detail}</div>
              </div>
              <span style={{ fontSize: 13, fontWeight: 800, color: 'var(--green)' }}>Télécharger</span>
            </a>
          ))}
          <div style={{ fontSize: 12, color: 'var(--muted)' }}>
            Lien court : <span className="mono">{url.replace(/^https?:\/\//, '')}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
