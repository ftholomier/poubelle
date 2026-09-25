import { appUrl } from '@/server/urls';
import { openApiSpec } from '@/server/services/openapi';
import { apiJson, apiOptions } from '@/server/services/public-api';

/** Description OpenAPI de l'API publique (sans authentification). */
export function GET() {
  return apiJson(openApiSpec(appUrl('/api/v1')), 200, { 'cache-control': 'public, max-age=3600' });
}

export const OPTIONS = apiOptions;
