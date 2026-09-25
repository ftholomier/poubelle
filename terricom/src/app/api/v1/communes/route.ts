import { apiOptions, handleApi, apiCommunes } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export function GET(req: Request) {
  return handleApi(req, (ctx) => apiCommunes(ctx.territory));
}

export const OPTIONS = apiOptions;
