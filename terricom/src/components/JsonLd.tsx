import { jsonLdString } from '@/server/seo';

/** Données structurées schema.org (non exécutées : compatibles avec la CSP). */
export function JsonLd({ data }: { data: unknown }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdString(data) }} />;
}
