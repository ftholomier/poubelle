import { apiOptions, handleApi, apiTerritory } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export function GET(req: Request) {
  return handleApi(req, (ctx) => apiTerritory(ctx.territory));
}

export const OPTIONS = apiOptions;
