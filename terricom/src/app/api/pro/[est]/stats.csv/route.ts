import { eq, sql } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { canManageEstablishment, getActor } from '@/server/authz';
import { db } from '@/server/db';
import { establishments } from '@/server/db/schema';

/** Export CSV des statistiques quotidiennes d'une fiche (tableur, rapport annuel…). */
export async function GET(req: NextRequest, { params }: { params: Promise<{ est: string }> }) {
  const { est: estId } = await params;
  if (!/^[0-9a-f-]{36}$/.test(estId)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const [est] = await db.select().from(establishments).where(eq(establishments.id, estId)).limit(1);
  if (!est || !canManageEstablishment(actor, est)) return new NextResponse('Introuvable', { status: 404 });
  const days = Math.min(730, Math.max(1, Number(req.nextUrl.searchParams.get('periode')) || 30));
  const res = await db.execute<{ d: string; views: number; calls: number; directions: number; website: number; qr: number }>(sql`
    SELECT to_char(day, 'YYYY-MM-DD') AS d,
      count(*) FILTER (WHERE type = 'EST_VIEW')::int AS views,
      count(*) FILTER (WHERE type = 'PHONE_CLICK')::int AS calls,
      count(*) FILTER (WHERE type = 'DIRECTIONS_CLICK')::int AS directions,
      count(*) FILTER (WHERE type = 'WEBSITE_CLICK')::int AS website,
      count(*) FILTER (WHERE type = 'QR_SCAN')::int AS qr
    FROM (SELECT (occurred_at AT TIME ZONE 'Europe/Paris')::date AS day, type FROM analytics_events
          WHERE establishment_id = ${estId} AND occurred_at >= now() - make_interval(days => ${days})) x
    GROUP BY day ORDER BY day
  `);
  const lines = ['date;vues;appels;itineraires;site_web;scans_qr', ...res.rows.map((r) => [r.d, r.views, r.calls, r.directions, r.website, r.qr].join(';'))];
  const name = `statistiques-${est.slug}-${days}j.csv`;
  return new NextResponse(`﻿${lines.join('\r\n')}\r\n`, {
    headers: { 'content-type': 'text/csv; charset=utf-8', 'content-disposition': `attachment; filename="${name}"`, 'cache-control': 'private, no-store' },
  });
}
