import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { logoutAction } from '@/app/connexion/actions';
import { AccountForm, IdentityForm, MfaEnrollForm } from '@/components/pro/ClaimForms';
import { ClaimShell, ClaimTitle } from '@/components/pro/ClaimShell';
import { fullName } from '@/lib/format';
import { startEnrollment } from '@/server/auth/mfa';
import { getSession } from '@/server/auth/session';
import { randomToken } from '@/server/crypto';
import { env } from '@/server/env';
import { claimInfoRows, codeChannel, loadClaimTarget, openClaimOf } from '@/server/services/claims';
import { slugify } from '@/lib/slug';

export const metadata: Metadata = { title: 'Revendiquer ma fiche', robots: { index: false } };

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const ACTIVITY_WORDS = new Set([
  'BOULANGERIE',
  'BOUCHERIE',
  'PHARMACIE',
  'GARAGE',
  'FERME',
  'CABINET',
  'EPICERIE',
  'ÉPICERIE',
  'FROMAGERIE',
  'FRUITIERE',
  'FRUITIÈRE',
  'PIZZERIA',
  'BOUTIQUE',
  'CHAUFFAGE',
  'TERRES',
  'TABLE',
  'MAISON',
  'ATELIER',
  'SARL',
  'SAS',
  'SASU',
  'EURL',
  'SCEA',
  'EARL',
  'GAEC',
]);

/** Identité fictive préremplie en démonstration, cohérente avec le titulaire SIRENE. */
function demoIdentity(legalName: string) {
  const tokens = legalName
    .toUpperCase()
    .split(/[^A-ZÀ-ÖØ-Ý]+/)
    .filter((t) => t.length > 2 && !ACTIVITY_WORDS.has(t));
  const pick = tokens.reduce((best, t) => (t.length > best.length ? t : best), '') || 'Martin';
  const lastName = pick.charAt(0) + pick.slice(1).toLowerCase();
  return {
    firstName: 'Camille',
    lastName,
    email: `camille.${slugify(lastName)}.${randomToken(3)
      .replace(/[^a-z0-9]/gi, '')
      .toLowerCase()}@exemple.fr`,
    password: 'Terricom2026!',
  };
}

function formatSiret(s: string | null): string {
  if (!s) return '';
  return `${s.slice(0, 3)} ${s.slice(3, 6)} ${s.slice(6, 9)} ${s.slice(9)}`;
}

export default async function ClaimEstablishmentPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  if (!/^[0-9a-f-]{36}$/.test(estId)) notFound();
  const t = await loadClaimTarget(estId);
  if (!t) notFound();
  const session = await getSession();
  if (session) {
    const open = await openClaimOf(session.user.id, t.est.id);
    if (open) redirect(`/pro/revendiquer/suivi/${open.id}`);
  }
  const base = `/pro/revendiquer/${t.est.id}`;
  const searchBack = `/pro/revendiquer?territoire=${encodeURIComponent(t.territory.slug)}&q=${encodeURIComponent(t.est.name)}`;
  const etape = sp.etape ?? 'infos';

  if (!t.claimable) {
    return (
      <ClaimShell territory={t.territory} step={0}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <ClaimTitle>Cette fiche est déjà gérée</ClaimTitle>
          <p style={{ margin: 0, color: 'var(--muted)', lineHeight: 1.5 }}>
            « {t.est.name} » est déjà administrée par un compte professionnel{t.est.status === 'SUSPENDED' ? ' ou suspendue par la collectivité' : ''}. Vous
            avez repris l&apos;activité ou pensez qu&apos;il s&apos;agit d&apos;une erreur ? Contactez {t.territory.name}
            {t.territory.contactEmail ? (
              <>
                {' '}
                à <a href={`mailto:${t.territory.contactEmail}`}>{t.territory.contactEmail}</a>
              </>
            ) : null}
            .
          </p>
          <Link href={searchBack} className="btn btn-outline" style={{ alignSelf: 'flex-start' }}>
            Retour à la recherche
          </Link>
        </div>
      </ClaimShell>
    );
  }

  if (etape === 'identite' || etape === 'securite') {
    if (!session) redirect(`${base}?etape=compte`);
    if (etape === 'securite') {
      const enrollment = session.user.mfaEnabled ? null : await startEnrollment(session.user.id);
      if (!enrollment) redirect(`${base}?etape=identite`);
      return (
        <ClaimShell territory={t.territory} step={2}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <ClaimTitle>Sécurisez votre accès</ClaimTitle>
            <p style={{ margin: 0, color: 'var(--muted)' }}>
              La double authentification protège votre vitrine même si votre mot de passe est dérobé : à chaque connexion, un code à usage unique vous sera
              demandé.
            </p>
            <MfaEnrollForm secret={enrollment.secret} qrSvg={enrollment.qrSvg} next={`${base}?etape=identite`} skip={`${base}?etape=identite`} />
          </div>
        </ClaimShell>
      );
    }
    const channel = codeChannel(t);
    return (
      <ClaimShell territory={t.territory} step={3}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <ClaimTitle>Prouvez que c&apos;est vous</ClaimTitle>
          <p style={{ margin: 0, color: 'var(--muted)' }}>Choisissez une méthode. C&apos;est ce qui protège votre fiche contre les usurpations.</p>
          <IdentityForm
            estId={t.est.id}
            back={`${base}?etape=compte`}
            siretPrefill={env.DEMO_MODE ? formatSiret(t.est.siret) : ''}
            codeLabel={
              channel.kind === 'SMS'
                ? `Envoyé par ${channel.label.replace('SMS au', 'SMS au numéro de l’établissement,')}`
                : 'Envoyé par courrier à l’adresse de l’établissement (sous 3 à 5 jours)'
            }
          />
        </div>
      </ClaimShell>
    );
  }

  if (etape === 'compte') {
    return (
      <ClaimShell territory={t.territory} step={2}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <ClaimTitle>Créez votre accès</ClaimTitle>
          {session ? (
            <>
              <p style={{ margin: 0, color: 'var(--muted)' }}>
                Vous êtes connecté·e en tant que <b style={{ color: 'var(--text)' }}>{fullName(session.user)}</b> ({session.user.email}). La fiche sera
                rattachée à ce compte.
              </p>
              <div style={{ display: 'flex', gap: 10 }}>
                <Link href={base} className="btn btn-outline" style={{ padding: '13px 18px' }}>
                  Retour
                </Link>
                <Link
                  href={`${base}?etape=${session.user.mfaEnabled ? 'identite' : 'securite'}`}
                  className="btn btn-brand"
                  style={{ flex: 1, justifyContent: 'center', padding: 13 }}
                >
                  Continuer
                </Link>
              </div>
              <form action={logoutAction}>
                <button type="submit" className="btn-link" style={{ fontSize: 13 }}>
                  Ce n&apos;est pas vous ? Se déconnecter
                </button>
              </form>
            </>
          ) : (
            <AccountForm
              estId={t.est.id}
              back={base}
              loginHref={`/connexion?next=${encodeURIComponent(`${base}?etape=identite`)}`}
              prefill={env.DEMO_MODE ? demoIdentity(t.company.legalName) : null}
            />
          )}
        </div>
      </ClaimShell>
    );
  }

  const rows = claimInfoRows(t);
  return (
    <ClaimShell territory={t.territory} step={1}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <ClaimTitle>Ces infos sont-elles justes ?</ClaimTitle>
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, overflow: 'hidden', background: '#fff' }}>
          {rows.map((r, i) => (
            <div
              key={r.k}
              className="claim-info-row"
              style={{
                display: 'grid',
                gridTemplateColumns: '140px 1fr auto',
                gap: 12,
                padding: '12px 16px',
                borderBottom: i < rows.length - 1 ? '1px solid var(--line-2)' : 0,
                fontSize: 14,
                alignItems: 'center',
              }}
            >
              <span style={{ color: 'var(--muted)' }}>{r.k}</span>
              <b style={{ minWidth: 0, overflowWrap: 'anywhere' }}>{r.v}</b>
              <span style={{ fontSize: 12, fontWeight: 700, color: r.ok ? 'var(--green)' : 'var(--brick)', whiteSpace: 'nowrap' }}>{r.s}</span>
            </div>
          ))}
        </div>
        <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Source : base SIRENE et OpenStreetMap. Vous pourrez tout corriger ensuite.</p>
        <div style={{ display: 'flex', gap: 10 }}>
          <Link href={searchBack} className="btn btn-outline" style={{ padding: '13px 18px', borderWidth: 1, borderColor: 'var(--line)', background: '#fff' }}>
            Retour
          </Link>
          <Link
            href={`${base}?etape=${session ? (session.user.mfaEnabled ? 'identite' : 'compte') : 'compte'}`}
            className="btn btn-brand"
            style={{ flex: 1, justifyContent: 'center', padding: 13 }}
          >
            Oui, c&apos;est bien mon entreprise
          </Link>
        </div>
      </div>
    </ClaimShell>
  );
}
