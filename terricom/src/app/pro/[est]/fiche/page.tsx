import { and, asc, eq, isNull, or } from 'drizzle-orm';
import { FicheEditor, SaveFicheButton } from '@/components/pro/FicheEditor';
import { PhotoManager } from '@/components/pro/PhotoManager';
import { ProductManager } from '@/components/pro/ProductManager';
import { fmtStamp, truncate } from '@/lib/format';
import { upcomingHoliday, weeklyRows } from '@/lib/hours';
import { variantUrl } from '@/lib/images';
import { db } from '@/server/db';
import { attributes, categories, establishmentRevisions } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ est: string }> };

export default async function FicheEditorPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, completeness } = ctx;
  const [cats, attrs, revisions] = await Promise.all([
    db
      .select({ id: categories.id, name: categories.name, family: categories.family })
      .from(categories)
      .where(and(eq(categories.isActive, true), or(isNull(categories.territoryId), eq(categories.territoryId, est.territoryId))))
      .orderBy(asc(categories.name)),
    db
      .select({ slug: attributes.slug, label: attributes.label, group: attributes.group })
      .from(attributes)
      .where(or(isNull(attributes.territoryId), eq(attributes.territoryId, est.territoryId)))
      .orderBy(asc(attributes.sortOrder), asc(attributes.label)),
    db.select().from(establishmentRevisions).where(eq(establishmentRevisions.establishmentId, est.id)).orderBy(asc(establishmentRevisions.createdAt)),
  ]);
  const socials = (est.socials ?? {}) as Record<string, string | undefined>;
  const audit = [...completeness.items].sort((a, b) => Number(a.ok) - Number(b.ok)).slice(0, 7);
  const url = portalUrl(ctx.territory, est.path);
  const crumbs = url
    .replace(/^https?:\/\//, '')
    .split('/')
    .filter(Boolean)
    .slice(0, -1)
    .join(' › ');
  const today = weeklyRows(est.hours).find((r) => r.isToday);
  const seoTitle = `${est.name} à ${est.commune.name} – ${est.tagline ?? est.activity}`;
  const snippet = [
    today && !today.closed ? `Ouvert aujourd'hui ${today.label.replace(/ /g, '')}` : null,
    est.street,
    truncate(est.description ?? est.tagline ?? '', 120),
  ]
    .filter(Boolean)
    .join(' · ');
  const indexed = est.status !== 'PRECREATED' || Boolean(est.description);
  const holiday = upcomingHoliday(new Date(), 60);

  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) 360px', ['--gap' as string]: '22px', ['--align' as string]: 'start' }}>
        <FicheEditor
          estId={est.id}
          logoUrl={est.logoUrl}
          values={{
            name: est.name,
            categoryId: est.categoryId,
            activityLabel: est.activityLabel ?? '',
            tagline: est.tagline ?? '',
            description: est.description ?? '',
            street: est.street ?? '',
            postalCode: est.postalCode ?? est.commune.postalCodes[0] ?? '',
            phone: est.phone ?? '',
            email: est.email ?? '',
            website: est.website ?? '',
            facebook: socials.facebook ?? '',
            instagram: socials.instagram ?? '',
            linkedin: socials.linkedin ?? '',
            priceInfo: est.priceInfo ?? '',
            serviceArea: est.serviceArea ?? '',
            accessibilityInfo: est.accessibilityInfo ?? '',
            appointmentInfo: est.appointmentInfo ?? '',
            appointmentsEnabled: est.appointmentsEnabled,
          }}
          hours={est.hours}
          exceptions={est.exceptions.map((x) => ({
            date: x.date,
            closed: x.closed,
            opensAt: x.opensAt?.slice(0, 5) ?? null,
            closesAt: x.closesAt?.slice(0, 5) ?? null,
            label: x.label ?? null,
          }))}
          attributes={est.attributes.map((a) => a.slug)}
          categories={cats}
          allAttributes={attrs}
          canAppointments={ctx.limits.appointments && ctx.modules.has('APPOINTMENTS')}
          upcomingHoliday={holiday && !est.exceptions.some((x) => x.date === holiday.date) ? holiday : null}
          photosSlot={<PhotoManager key="photos" estId={est.id} max={20} photos={est.photos.map((m) => ({ id: m.id, src: variantUrl(m, 320), tag: m.tag }))} />}
          productsSlot={
            <ProductManager
              key="products"
              estId={est.id}
              products={est.products.map((pr) => ({
                id: pr.id,
                name: pr.name,
                priceText: pr.priceText,
                description: pr.description,
                imageUrl: pr.imageUrl,
                kind: pr.kind,
              }))}
            />
          }
        />
        <div className="sticky-aside" style={{ display: 'flex', flexDirection: 'column', gap: 14, position: 'sticky', top: 84 }}>
          <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <b style={{ color: 'var(--amber)' }}>✦ Audit de votre fiche</b>
              <span className="display" style={{ fontSize: 24 }}>
                {completeness.score}%
              </span>
            </div>
            {audit.map((a) => (
              <div key={a.key} style={{ display: 'flex', gap: 10, fontSize: 13, lineHeight: 1.4, borderTop: '1px solid var(--dark-3)', paddingTop: 8 }}>
                <span style={{ color: a.ok ? 'var(--pulse)' : 'var(--amber)', fontWeight: 800 }}>{a.ok ? '✓' : '!'}</span>
                <span style={{ color: 'var(--sage-5)' }}>{a.audit}</span>
              </div>
            ))}
          </div>
          <div className="card" style={{ borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.06em', color: 'var(--muted)', textTransform: 'uppercase' }}>
              Aperçu moteur de recherche
            </div>
            <div style={{ fontSize: 12, color: 'var(--muted-3)' }}>{crumbs}</div>
            <div style={{ fontSize: 18, color: '#2A4FA8', lineHeight: 1.25 }}>{truncate(seoTitle, 70)}</div>
            <div style={{ fontSize: 13, color: '#4A4F4B', lineHeight: 1.45 }}>
              {truncate(snippet, 160) || 'Ajoutez une description pour enrichir cet extrait.'}
            </div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 4 }}>
              {['Schema.org LocalBusiness ✓', 'OpenGraph ✓', indexed ? 'Sitemap ✓' : 'Sitemap : description requise'].map((b) => (
                <span
                  key={b}
                  style={{
                    fontSize: 11,
                    fontWeight: 700,
                    background: b.includes('requise') ? 'var(--warn-bg)' : 'var(--mint)',
                    color: b.includes('requise') ? 'var(--warn-fg)' : 'var(--green)',
                    padding: '3px 8px',
                    borderRadius: 6,
                  }}
                >
                  {b}
                </span>
              ))}
            </div>
          </div>
          <SaveFicheButton />
          <div style={{ fontSize: 12, color: 'var(--muted)', textAlign: 'center' }}>Chaque modification importante est historisée.</div>
          {revisions.length ? (
            <details className="card" style={{ borderRadius: 14, padding: '10px 14px', fontSize: 13 }}>
              <summary style={{ cursor: 'pointer', fontWeight: 700 }}>Historique des modifications ({revisions.length})</summary>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 8 }}>
                {revisions
                  .slice(-12)
                  .reverse()
                  .map((r) => (
                    <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 8, borderTop: '1px solid var(--line-2)', paddingTop: 6 }}>
                      <span>{r.summary}</span>
                      <span style={{ color: 'var(--muted)', whiteSpace: 'nowrap' }}>{fmtStamp(r.createdAt)}</span>
                    </div>
                  ))}
              </div>
            </details>
          ) : null}
        </div>
      </div>
    </div>
  );
}
