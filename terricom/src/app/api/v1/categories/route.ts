import { apiOptions, handleApi, apiCategories } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export function GET(req: Request) {
  return handleApi(req, (ctx) => apiCategories(ctx.territory));
}

export const OPTIONS = apiOptions;
