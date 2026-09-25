import { and, eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { csvResponse, toCsv } from '@/server/csv';
import { db } from '@/server/db';
import { importBatches } from '@/server/db/schema';
import { boApiContext } from '@/server/services/bo-api';

const ACTION = { CREATE: 'Création', MERGE: 'Fusion avec une fiche existante', SKIP: 'Ignorée' } as const;

/** Rapport de contrôle ligne à ligne d'un import. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const [batch] = await db.select().from(importBatches).where(and(eq(importBatches.id, id), eq(importBatches.territoryId, ctx.territory.id))).limit(1);
  if (!batch) return new NextResponse('Introuvable', { status: 404 });
  const body = toCsv(
    ['Ligne', 'Nom', 'SIRET', 'Commune', 'Catégorie', 'Traitement', 'Remarques'],
    batch.rows.map((r) => [r.line, r.name, r.siret, r.communeName, r.categoryName, ACTION[r.action], r.errors.join(' · ')]),
  );
  return csvResponse(body, `import-${batch.filename.replace(/[^\w.-]+/g, '_')}-rapport.csv`);
}
