import { desc, eq } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { DeleteAccountForm } from '@/components/account/AccountForms';
import { fmtStamp } from '@/lib/format';
import { getActor } from '@/server/authz';
import { db } from '@/server/db';
import { privacyRequests } from '@/server/db/schema';

export const metadata: Metadata = { title: 'Mes données' };

const KIND = { EXPORT: 'Export de mes données', DELETE: 'Suppression', RECTIFY: 'Rectification' } as const;

export default async function DataPage() {
  const actor = await getActor();
  if (!actor) redirect('/connexion?next=/compte/donnees');
  const history = await db.select().from(privacyRequests).where(eq(privacyRequests.email, actor.user.email)).orderBy(desc(privacyRequests.createdAt)).limit(10);
  return (
    <div className="app-content" style={{ maxWidth: 860, display: 'flex', flexDirection: 'column', gap: 16 }}>
      <section className="card" style={{ borderRadius: 20, padding: 24, display: 'flex', flexDirection: 'column', gap: 12 }}>
        <h2 className="display" style={{ fontSize: 22, margin: 0 }}>
          Vos droits
        </h2>
        <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)', lineHeight: 1.55 }}>
          Vos données sont hébergées en France et ne sont jamais revendues. Conformément au RGPD, vous pouvez à tout moment y accéder, les corriger, les
          exporter ou demander leur effacement. Les informations publiques de votre établissement (nom, adresse, horaires) proviennent des registres officiels
          et restent consultables sur le portail du territoire, même sans compte.
        </p>
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <a href="/api/compte/export" className="btn btn-brand" download>
            Télécharger mes données (JSON)
          </a>
          <Link href="/compte" className="btn btn-outline">
            Corriger mon profil
          </Link>
          <Link href="/confidentialite" className="btn btn-ghost">
            Politique de confidentialité
          </Link>
        </div>
      </section>

      {history.length ? (
        <section className="card" style={{ borderRadius: 20, padding: 24, display: 'flex', flexDirection: 'column', gap: 6 }}>
          <h2 className="display" style={{ fontSize: 22, margin: '0 0 6px' }}>
            Historique de vos demandes
          </h2>
          {history.map((h) => (
            <div key={h.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 12, padding: '8px 0', borderTop: '1px solid var(--line-2)', fontSize: 14 }}>
              <span>
                {KIND[h.kind]} · <span style={{ color: h.status === 'DONE' ? 'var(--green)' : 'var(--brick)', fontWeight: 700 }}>{h.status === 'DONE' ? 'traitée' : h.status === 'OPEN' ? 'en cours' : 'refusée'}</span>
              </span>
              <span style={{ color: 'var(--muted)' }}>{fmtStamp(h.createdAt)}</span>
            </div>
          ))}
        </section>
      ) : null}

      <section className="card" style={{ borderRadius: 20, padding: 24, display: 'flex', flexDirection: 'column', gap: 12, borderColor: 'var(--danger-bg)' }}>
        <h2 className="display" style={{ fontSize: 22, margin: 0, color: 'var(--danger-fg)' }}>
          Supprimer mon compte
        </h2>
        <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)', lineHeight: 1.55 }}>
          Votre compte sera anonymisé immédiatement et vos accès retirés. Les factures sont conservées 10 ans (obligation légale) et le journal
          d&apos;audit conserve la trace anonymisée de vos actions. Vos fiches restent publiées mais ne seront plus gérées.
        </p>
        <DeleteAccountForm />
      </section>
    </div>
  );
}
