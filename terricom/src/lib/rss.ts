/** Flux RSS 2.0 (actualités d'un territoire ou d'un établissement), lisible par les sites et les agrégateurs. */
export type RssItem = {
  guid: string;
  title: string;
  link: string;
  description?: string | null;
  publishedAt: Date;
  category?: string | null;
  imageUrl?: string | null;
};

function x(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

export function rssFeed(channel: { title: string; link: string; description: string; selfUrl: string; language?: string }, items: RssItem[]): string {
  const body = items
    .map(
      (i) =>
        `    <item>\n      <title>${x(i.title)}</title>\n      <link>${x(i.link)}</link>\n      <guid isPermaLink="false">${x(i.guid)}</guid>\n      <pubDate>${i.publishedAt.toUTCString()}</pubDate>\n${
          i.category ? `      <category>${x(i.category)}</category>\n` : ''
        }${i.description ? `      <description>${x(i.description)}</description>\n` : ''}${
          i.imageUrl ? `      <enclosure url="${x(i.imageUrl)}" type="image/jpeg" length="0"/>\n` : ''
        }    </item>`,
    )
    .join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>${x(channel.title)}</title>
    <link>${x(channel.link)}</link>
    <description>${x(channel.description)}</description>
    <language>${channel.language ?? 'fr'}</language>
    <atom:link href="${x(channel.selfUrl)}" rel="self" type="application/rss+xml"/>
    <lastBuildDate>${(items[0]?.publishedAt ?? new Date()).toUTCString()}</lastBuildDate>
${body}
  </channel>
</rss>
`;
}
