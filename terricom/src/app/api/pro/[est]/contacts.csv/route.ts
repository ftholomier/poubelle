import { eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { audit } from '@/server/audit';
import { canManageEstablishment, getActor } from '@/server/authz';
import { db } from '@/server/db';
import { companies, establishments } from '@/server/db/schema';
import { planLimits } from '@/server/services/billing';
import { listContacts } from '@/server/services/customers';

const csv = (v: string | null | undefined) => `"${String(v ?? '').replace(/"/g, '""')}"`;

/** Export des contacts clients consentis (offre Communication), réservé à l'entreprise elle-même. */
export async function GET(_req: Request, { params }: { params: Promise<{ est: string }> }) {
  const { est: estId } = await params;
  if (!/^[0-9a-f-]{36}$/.test(estId)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const [row] = await db
    .select({ est: establishments, plan: companies.plan })
    .from(establishments)
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(eq(establishments.id, estId))
    .limit(1);
  const role = row ? canManageEstablishment(actor, row.est) : null;
  // Les agents de la collectivité n'ont pas accès aux clients d'une entreprise.
  if (!row || !role || role === 'STAFF') return new NextResponse('Introuvable', { status: 404 });
  if (!(await planLimits(row.plan)).contactsExport) return new NextResponse('Export inclus dans l’offre Communication', { status: 403 });
  const list = await listContacts(row.est.companyId, 50_000);
  const status = (c: (typeof list)[number]) => (!c.subscribed ? 'désinscrit' : c.confirmedAt ? 'actif' : 'en attente');
  const lines = [
    ['email', 'nom', 'statut', 'origine', 'consentement', 'date_consentement', 'desinscription'].join(';'),
    ...list.map((c) =>
      [
        csv(c.email),
        csv(c.fullName),
        csv(status(c)),
        csv(c.source === 'FICHE' ? 'fiche' : c.source === 'MANUAL' ? 'ajout manuel' : c.source.toLowerCase()),
        csv(c.consentText),
        csv(c.consentAt?.toISOString().slice(0, 10)),
        csv(c.unsubscribedAt?.toISOString().slice(0, 10)),
      ].join(';'),
    ),
  ];
  await audit({
    actor: { user: actor.user },
    category: 'RGPD',
    action: 'contacts.export',
    summary: `Export des ${list.length} contacts clients de « ${row.est.name} »`,
    territoryId: row.est.territoryId,
    targetType: 'establishment',
    targetId: row.est.id,
  });
  return new NextResponse(`﻿${lines.join('\r\n')}\r\n`, {
    headers: {
      'content-type': 'text/csv; charset=utf-8',
      'content-disposition': `attachment; filename="clients-${row.est.slug}.csv"`,
      'cache-control': 'private, no-store',
    },
  });
}
