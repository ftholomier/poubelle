import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { simulateApprovalAction } from '../../actions';
import { CodeEntryForm, DocumentForm, PendingRefresher } from '@/components/pro/ClaimForms';
import { CLAIM_STEPS, ClaimShell, ClaimTitle, SIGNUP_STEPS } from '@/components/pro/ClaimShell';
import { Confetti } from '@/components/ui/Feedback';
import { getSession } from '@/server/auth/session';
import { computeCompleteness, vitrineLevel } from '@/server/completeness';
import { env } from '@/server/env';
import { claimForUser, letterCodeOf, reviewerLabel } from '@/server/services/claims';
import { completenessInput } from '@/server/services/establishments';

export const metadata: Metadata = { title: 'Suivi de ma demande', robots: { index: false } };

type Props = { params: Promise<{ claim: string }> };

function Line({ tone, children }: { tone: 'ok' | 'wait' | 'ko'; children: React.ReactNode }) {
  const color = tone === 'ok' ? 'var(--green)' : tone === 'ko' ? 'var(--danger-fg)' : 'var(--brick)';
  return (
    <div style={{ color, fontWeight: 600 }}>
      {tone === 'ok' ? '✓' : tone === 'ko' ? '✗' : '●'} {children}
    </div>
  );
}

export default async function ClaimFollowUpPage({ params }: Props) {
  const { claim: claimId } = await params;
  if (!/^[0-9a-f-]{36}$/.test(claimId)) notFound();
  const session = await getSession();
  if (!session) redirect(`/connexion?next=${encodeURIComponent(`/pro/revendiquer/suivi/${claimId}`)}`);
  const ctx = await claimForUser(claimId, session.user.id);
  if (!ctx) notFound();
  const { claim, est, commune, territory, user } = ctx;
  // Fiche créée par le demandeur (inscription) : parcours en trois étapes.
  const creation = est.origin === 'PRO' && est.createdById === user.id;
  const steps = creation ? SIGNUP_STEPS : CLAIM_STEPS;
  const pendingStep = creation ? 1 : 4;

  if (claim.status === 'APPROVED') {
    const level = vitrineLevel(est.completeness);
    const input = await completenessInput(est.id);
    const missing = input ? computeCompleteness(input).items.filter((i) => !i.ok).length : 4;
    const WORDS = ['Zéro', 'Une', 'Deux', 'Trois'];
    return (
      <ClaimShell territory={territory} step={pendingStep + 1} steps={steps}>
        <div style={{ position: 'relative', display: 'flex', flexDirection: 'column', gap: 18, alignItems: 'flex-start' }}>
          <Confetti />
          <span
            className="display"
            style={{ background: 'var(--amber)', color: 'var(--ink)', fontSize: 15, padding: '8px 14px', borderRadius: 10, transform: 'rotate(-4deg)' }}
          >
            Fiche validée !
          </span>
          <ClaimTitle size={44}>
            Bienvenue {user.firstName || ''},
            <br />
            la vitrine est à vous.
          </ClaimTitle>
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 15 }}>
            Votre fiche est complète à {est.completeness}&nbsp;%.{' '}
            {missing === 0
              ? 'Elle est au niveau «\u00a0Vitrine Or\u00a0» : bravo !'
              : missing <= 3
                ? `${WORDS[missing]} petite${missing > 1 ? 's' : ''} action${missing > 1 ? 's' : ''} et vous passez au niveau «\u00a0Vitrine Or\u00a0».`
                : 'Quelques actions guidées (photos, horaires, description) et vous passez au niveau «\u00a0Vitrine Or\u00a0».'}
            <span className="sr-only"> Niveau actuel : {level.name}.</span>
          </p>
          <Link href={`/pro/${est.id}`} className="btn btn-brand" style={{ padding: '14px 20px', fontSize: 15 }}>
            Accéder à mon tableau de bord
          </Link>
        </div>
      </ClaimShell>
    );
  }

  if (claim.status === 'REJECTED' || claim.status === 'CANCELLED') {
    return (
      <ClaimShell territory={territory} step={pendingStep} steps={steps}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, alignItems: 'flex-start' }}>
          <ClaimTitle>Votre demande n&apos;a pas été validée</ClaimTitle>
          <p style={{ margin: 0, color: 'var(--muted)', lineHeight: 1.5 }}>
            {claim.decisionNote || `La collectivité n'a pas pu confirmer votre lien avec « ${est.name} ».`}
          </p>
          {territory.contactEmail ? (
            <p style={{ margin: 0, fontSize: 14 }}>
              Une question ? Écrivez à <a href={`mailto:${territory.contactEmail}`}>{territory.contactEmail}</a>.
            </p>
          ) : null}
          <Link href={`/pro/revendiquer?territoire=${territory.slug}`} className="btn btn-outline">
            Retour à la recherche
          </Link>
        </div>
      </ClaimShell>
    );
  }

  const reviewer = await reviewerLabel(est.communeId, commune.name, territory.name);
  const siretOk = claim.checks.some((c) => c.label === 'SIRET vérifié' && c.ok);
  const letterCode = env.DEMO_MODE ? letterCodeOf(claim) : null;
  const needsInfo = claim.status === 'NEEDS_INFO';

  return (
    <ClaimShell territory={territory} step={pendingStep} steps={steps}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 18, alignItems: 'flex-start' }}>
        {needsInfo ? null : (
          <div
            aria-hidden="true"
            style={{
              width: 64,
              height: 64,
              borderRadius: '50%',
              border: '5px solid var(--mint)',
              borderTopColor: 'var(--green)',
              animation: 'spin 1s linear infinite',
            }}
          />
        )}
        <ClaimTitle>{needsInfo ? `${reviewer} a besoin d’un complément` : `${reviewer} vérifie votre demande`}</ClaimTitle>
        {needsInfo ? (
          <div style={{ background: 'var(--warn-bg)', color: 'var(--warn-fg)', borderRadius: 14, padding: 16, fontSize: 14, lineHeight: 1.5, width: '100%' }}>
            <b>Message de la collectivité :</b> {claim.decisionNote}
          </div>
        ) : (
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 15, lineHeight: 1.5 }}>
            En moyenne sous 24 h. Préparez déjà vos photos et vos horaires : vous pourrez tout publier dès la validation.
          </p>
        )}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
          {claim.method === 'SIRET' ? (
            <Line tone={siretOk ? 'ok' : 'wait'}>{siretOk ? 'Identité vérifiée (SIRET)' : 'SIRET transmis · contrôle par la collectivité'}</Line>
          ) : claim.method === 'CODE' ? (
            <Line tone={claim.codeVerifiedAt ? 'ok' : 'wait'}>
              {claim.codeVerifiedAt ? 'Identité vérifiée (code)' : `Code envoyé · ${claim.codeSentTo ?? ''}`}
            </Line>
          ) : (
            <Line tone="ok">Kbis transmis</Line>
          )}
          <Line tone="ok">Compte créé{user.mfaEnabled ? ' · double authentification activée' : ''}</Line>
          <Line tone="wait">Validation par la collectivité</Line>
        </div>
        {claim.method === 'CODE' && !claim.codeVerifiedAt ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, width: '100%' }}>
            <b style={{ fontSize: 14 }}>Vous avez reçu votre code ?</b>
            <CodeEntryForm claimId={claim.id} />
            {letterCode ? (
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                Démonstration : le courrier contient le code <b style={{ fontFamily: 'var(--font-mono)' }}>{letterCode}</b>.
              </span>
            ) : null}
          </div>
        ) : null}
        {needsInfo ? <DocumentForm claimId={claim.id} /> : null}
        {!user.mfaEnabled ? (
          <Link href="/compte/securite" style={{ fontSize: 13, fontWeight: 700 }}>
            Activer la double authentification (recommandé) →
          </Link>
        ) : null}
        {env.DEMO_MODE ? (
          <form action={simulateApprovalAction}>
            <input type="hidden" name="claimId" value={claim.id} />
            <button type="submit" className="btn btn-dark" style={{ padding: '13px 18px' }}>
              Simuler la validation par la mairie →
            </button>
          </form>
        ) : (
          <PendingRefresher />
        )}
      </div>
    </ClaimShell>
  );
}
