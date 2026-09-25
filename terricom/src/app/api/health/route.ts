/** Sonde de vivacité (Kubernetes liveness) : ne dépend d'aucun service externe. */
export const dynamic = 'force-dynamic';

export function GET() {
  return Response.json({ status: 'ok', uptime: Math.round(process.uptime()) }, { headers: { 'cache-control': 'no-store' } });
}
