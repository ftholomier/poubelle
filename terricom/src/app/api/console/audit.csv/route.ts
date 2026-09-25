import { desc, eq } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { AUDIT_CATEGORIES, type AuditCategory } from '@/lib/constants';
import { audit } from '@/server/audit';
import { getActor } from '@/server/authz';
import { csvResponse, toCsv } from '@/server/csv';
import { db } from '@/server/db';
import { auditLog, territories } from '@/server/db/schema';
import { auditWhere } from '@/server/services/console-audit';

/** Export CSV du journal d'audit filtré (50 000 lignes au plus). */
export async function GET(req: NextRequest) {
  const actor = await getActor();
  if (!actor || !actor.isPlatformStaff || !actor.session.mfaVerified) return new NextResponse('Non autorisé', { status: 401 });
  const p = req.nextUrl.searchParams;
  const category = p.get('categorie') as AuditCategory | null;
  const where = auditWhere({
    category: category && category in AUDIT_CATEGORIES ? category : undefined,
    territoryId: /^[0-9a-f-]{36}$/.test(p.get('territoire') ?? '') ? p.get('territoire')! : undefined,
    q: p.get('q')?.slice(0, 100) || undefined,
    from: p.get('du') ?? undefined,
    to: p.get('au') ?? undefined,
  });
  const rows = await db
    .select({
      id: auditLog.id,
      at: auditLog.occurredAt,
      actor: auditLog.actorLabel,
      category: auditLog.category,
      action: auditLog.action,
      summary: auditLog.summary,
      territory: territories.name,
      hash: auditLog.hash,
    })
    .from(auditLog)
    .leftJoin(territories, eq(territories.id, auditLog.territoryId))
    .where(where)
    .orderBy(desc(auditLog.id))
    .limit(50_000);
  await audit({ actor: { user: actor.user }, category: 'SECURITE', action: 'audit.exported', summary: `Export du journal d’audit (${rows.length} entrées)` });
  return csvResponse(
    toCsv(
      ['N°', 'Date', 'Auteur', 'Catégorie', 'Action', 'Description', 'Territoire', 'Empreinte'],
      rows.map((r) => [
        r.id,
        r.at.toISOString(),
        r.actor,
        AUDIT_CATEGORIES[r.category as AuditCategory]?.label ?? r.category,
        r.action,
        r.summary,
        r.territory,
        r.hash,
      ]),
    ),
    `journal-audit-${new Date().toISOString().slice(0, 10)}.csv`,
  );
}
