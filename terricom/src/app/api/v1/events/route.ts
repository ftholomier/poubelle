import { apiOptions, handleApi, apiEvents } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export function GET(req: Request) {
  return handleApi(req, (ctx, url) => apiEvents(ctx.territory, url));
}

export const OPTIONS = apiOptions;
