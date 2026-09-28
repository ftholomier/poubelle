import { apiOptions, handleApi, apiPosts } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export function GET(req: Request) {
  return handleApi(req, (ctx, url) => apiPosts(ctx.territory, url));
}

export const OPTIONS = apiOptions;
