import { desc, eq } from 'drizzle-orm';
import Link from 'next/link';
import { respondAppointment } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { fmtLongDate, fmtHourOf, fmtPhone, fmtStamp, nowMs } from '@/lib/format';
import { db } from '@/server/db';
import { appointments } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }> };

const STATUS: Record<string, { label: string; bg: string }> = {
  REQUESTED: { label: 'À traiter', bg: 'var(--warn-bg)' },
  CONFIRMED: { label: 'Confirmé', bg: 'var(--ok-bg)' },
  DECLINED: { label: 'Décliné', bg: 'var(--sand)' },
  CANCELLED: { label: 'Annulé', bg: 'var(--sand)' },
};

export default async function AppointmentsPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, base, limits } = ctx;
  const list = await db.select().from(appointments).where(eq(appointments.establishmentId, est.id)).orderBy(desc(appointments.createdAt)).limit(100);
  const now = new Date(nowMs());
  return (
    <div className="app-content">
      {!est.appointmentsEnabled ? (
        <div className="alert alert-info">
          La prise de rendez-vous n&apos;est pas activée sur votre fiche.{' '}
          {limits.appointments ? (
            <Link href={`${base}/fiche`}>Activez-la dans « Ma fiche »</Link>
          ) : (
            <Link href={`${base}/offre`}>Incluse dans l’offre Premium</Link>
          )}
          .
        </div>
      ) : null}
      {list.length ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {list.map((a) => (
            <div key={a.id} className="card" style={{ borderRadius: 18, padding: 18, display: 'grid', gridTemplateColumns: 'minmax(0,1fr) auto', gap: 14 }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                  <b style={{ fontSize: 16 }}>{a.fullName}</b>
                  <span className="tag" style={{ background: STATUS[a.status].bg }}>
                    {STATUS[a.status].label}
                  </span>
                </div>
                <div style={{ fontSize: 15 }}>
                  {fmtLongDate(a.preferredAt)} à {fmtHourOf(a.preferredAt)}
                  {a.service ? ` · ${a.service}` : ''}
                </div>
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                  {a.email}
                  {a.phone ? ` · ${fmtPhone(a.phone)}` : ''} · demandé le {fmtStamp(a.createdAt)}
                </div>
                {a.message ? <div style={{ fontSize: 14, background: 'var(--cream)', borderRadius: 10, padding: 10, marginTop: 4 }}>{a.message}</div> : null}
                {a.responseNote ? <div style={{ fontSize: 13, color: 'var(--muted)' }}>Votre réponse : {a.responseNote}</div> : null}
              </div>
              {a.status === 'REQUESTED' ? (
                <ActionForm action={respondAppointment} style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 240 }}>
                  <input type="hidden" name="estId" value={est.id} />
                  <input type="hidden" name="appointmentId" value={a.id} />
                  <input name="note" className="input" placeholder="Message au client (facultatif)" maxLength={1000} />
                  <div style={{ display: 'flex', gap: 8 }}>
                    <button name="decision" value="CONFIRMED" className="btn btn-brand btn-sm">
                      Confirmer
                    </button>
                    <button name="decision" value="DECLINED" className="btn btn-outline btn-sm">
                      Décliner
                    </button>
                  </div>
                </ActionForm>
              ) : a.status === 'CONFIRMED' && a.preferredAt > now ? (
                <ActionForm action={respondAppointment} style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 240 }}>
                  <input type="hidden" name="estId" value={est.id} />
                  <input type="hidden" name="appointmentId" value={a.id} />
                  <input name="note" className="input" placeholder="Motif pour le client (facultatif)" aria-label="Motif de l’annulation" maxLength={1000} />
                  <button name="decision" value="CANCELLED" className="btn btn-outline btn-sm" style={{ color: 'var(--danger-fg)' }}>
                    Annuler le rendez-vous
                  </button>
                </ActionForm>
              ) : null}
            </div>
          ))}
        </div>
      ) : (
        <div className="card card-pad" style={{ color: 'var(--muted)' }}>
          Aucune demande de rendez-vous pour l&apos;instant.
        </div>
      )}
    </div>
  );
}
