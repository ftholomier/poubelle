import { sql } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { z } from 'zod';
import { detectSource, track } from '@/server/analytics';
import { rateLimit } from '@/server/auth/rate-limit';
import { db } from '@/server/db';

/** Types d'événements acceptés depuis le navigateur (les autres sont émis côté serveur). */
const CLIENT_TYPES = [
  'PAGE_VIEW',
  'EST_VIEW',
  'PHONE_CLICK',
  'DIRECTIONS_CLICK',
  'WEBSITE_CLICK',
  'SHARE_CLICK',
  'POST_VIEW',
  'EVENT_VIEW',
  'JOB_VIEW',
  'CAMPAIGN_VIEW',
  'CIRCUIT_VIEW',
] as const;

const schema = z.object({
  type: z.enum(CLIENT_TYPES),
  territoryId: z.string().uuid().optional(),
  establishmentId: z.string().uuid().optional(),
  communeId: z.string().uuid().optional(),
  refId: z.string().uuid().optional(),
  refIds: z.array(z.string().uuid()).max(12).optional(),
  path: z.string().max(500).optional(),
  referrer: z.string().max(1000).nullable().optional(),
  src: z.string().max(40).nullable().optional(),
  q: z.string().max(200).nullable().optional(),
});

export async function POST(req: NextRequest) {
  const ip = (req.headers.get('x-forwarded-for')?.split(',')[0] ?? req.headers.get('x-real-ip') ?? '').trim();
  const limited = await rateLimit(`beacon:${ip || 'anon'}`, 240, 60);
  if (!limited.ok) return new NextResponse(null, { status: 204 });
  let body: unknown;
  try {
    body = JSON.parse(await req.text());
  } catch {
    return new NextResponse(null, { status: 204 });
  }
  const parsed = schema.safeParse(body);
  if (!parsed.success) return new NextResponse(null, { status: 204 });
  const d = parsed.data;
  const host = (req.headers.get('x-forwarded-host') ?? req.headers.get('host') ?? '').toLowerCase();
  const source = detectSource({ referrer: d.referrer, src: d.src, ownHosts: [host] });
  const ua = req.headers.get('user-agent');
  if (d.type === 'POST_VIEW' && d.refIds?.length) {
    await db.execute(
      sql`UPDATE posts SET view_count = view_count + 1 WHERE id IN (${sql.join(
        d.refIds.map((id) => sql`${id}::uuid`),
        sql`, `,
      )})`,
    );
    return new NextResponse(null, { status: 204 });
  }
  await track({
    type: d.type,
    territoryId: d.territoryId,
    establishmentId: d.establishmentId,
    communeId: d.communeId,
    refId: d.refId,
    source,
    query: d.type === 'EST_VIEW' && source === 'PLATFORM_SEARCH' ? (d.q ?? null) : null,
    path: d.path,
    referrer: d.referrer,
    userAgent: ua,
    ip,
  });
  return new NextResponse(null, { status: 204 });
}
