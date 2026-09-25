import { and, eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { audit } from '@/server/audit';
import { db } from '@/server/db';
import { claims, communes, establishments } from '@/server/db/schema';
import { claimLettersPdf } from '@/server/print/letters';
import { estScope } from '@/server/services/backoffice';
import { boApiContext } from '@/server/services/bo-api';
import { letterCodeOf } from '@/server/services/claims';
import { appUrl } from '@/server/urls';

/** Courrier contenant le code de vérification d'une revendication (à poster à l'adresse de l'établissement). */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const [row] = await db
    .select({ claim: claims, est: establishments, communeName: communes.name })
    .from(claims)
    .innerJoin(establishments, eq(establishments.id, claims.establishmentId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(and(eq(claims.id, id), estScope(ctx)))
    .limit(1);
  const code = row ? letterCodeOf(row.claim) : null;
  if (!row || !code) return new NextResponse('Aucun courrier à imprimer pour cette demande', { status: 404 });
  const t = ctx.territory;
  const pdf = await claimLettersPdf({ name: t.name, legalName: t.legalName, colorPrimary: t.colorPrimary, colorAccent: t.colorAccent, contactEmail: t.contactEmail }, [
    {
      name: row.est.name,
      street: row.est.street,
      postalCode: row.est.postalCode,
      communeName: row.communeName,
      url: appUrl(`/pro/revendiquer/suivi/${row.claim.id}`),
      displayUrl: appUrl('/pro').replace(/^https?:\/\//, ''),
      code,
    },
  ]);
  await audit({ actor: { user: ctx.actor.user }, category: 'ENVOI', action: 'claim.letter_printed', summary: `Courrier de vérification imprimé pour « ${row.est.name} »`, territoryId: t.id, targetType: 'claim', targetId: row.claim.id });
  return new NextResponse(Buffer.from(pdf), {
    headers: { 'content-type': 'application/pdf', 'content-disposition': 'inline; filename="courrier-code.pdf"', 'cache-control': 'private, no-store' },
  });
}
