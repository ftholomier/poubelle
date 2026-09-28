import { and, eq, inArray, sql } from 'drizzle-orm';
import { db } from '../db';
import { communes, establishments } from '../db/schema';
import { claimLettersPdf } from '../print/letters';
import { appUrl } from '../urls';
import type { BoContext } from './backoffice';
import { estScope } from './backoffice';

/** Courriers d'invitation à revendiquer pour une liste de fiches du périmètre. */
export async function invitationLetters(ctx: BoContext, ids: string[]): Promise<Uint8Array | null> {
  if (!ids.length) return null;
  const rows = await db
    .select({
      id: establishments.id,
      name: establishments.name,
      street: establishments.street,
      postalCode: establishments.postalCode,
      communeName: communes.name,
    })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(and(estScope(ctx), inArray(establishments.id, ids.slice(0, 1000))));
  if (!rows.length) return null;
  await db
    .update(establishments)
    .set({ invitedAt: new Date(), invitationCount: sql`${establishments.invitationCount} + 1` })
    .where(
      inArray(
        establishments.id,
        rows.map((r) => r.id),
      ),
    );
  const t = ctx.territory;
  return claimLettersPdf(
    { name: t.name, legalName: t.legalName, colorPrimary: t.colorPrimary, colorAccent: t.colorAccent, contactEmail: t.contactEmail },
    rows.map((r) => ({
      name: r.name,
      street: r.street,
      postalCode: r.postalCode,
      communeName: r.communeName,
      url: appUrl(`/pro/revendiquer/${r.id}`),
      displayUrl: appUrl('/pro/revendiquer').replace(/^https?:\/\//, ''),
    })),
  );
}
