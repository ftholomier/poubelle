import { apiEstablishment, apiOptions, handleApi } from '@/server/services/public-api';

export const dynamic = 'force-dynamic';

export async function GET(req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return handleApi(req, (ctx) => apiEstablishment(ctx.territory, id));
}

export const OPTIONS = apiOptions;
