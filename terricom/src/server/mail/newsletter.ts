import { escapeHtml as esc } from './layout';

/**
 * Gabarit des lettres d'information du territoire (aperçu du back-office et envoi) :
 * bandeau aux couleurs du territoire, visuel, titre, blocs d'établissements, bouton, pied RGPD.
 * Mise en page en tableaux et styles en ligne, compatible avec les clients de messagerie.
 */

export type ResolvedBlock =
  | { type: 'text'; text: string }
  | { type: 'cards'; title?: string; items: { name: string; text: string; image: string | null; url: string }[] }
  | { type: 'cta'; label: string; url: string };

export type NewsletterView = {
  brandName: string;
  number: number | null;
  color: string;
  accent: string;
  title: string;
  intro: string;
  preheader?: string | null;
  heroImageUrl: string | null;
  blocks: ResolvedBlock[];
  newsletterName: string;
  unsubscribeUrl: string;
  preferencesUrl: string;
  /** Réécriture des liens (mesure des clics) ; identité en aperçu. */
  link?: (url: string) => string;
  /** Pixel de mesure d'ouverture (absent en aperçu). */
  openPixelUrl?: string | null;
};

const FONT = "'Instrument Sans',Arial,Helvetica,sans-serif";
const DISPLAY = "'Bricolage Grotesque',Arial,Helvetica,sans-serif";

export function renderNewsletter(v: NewsletterView): { html: string; text: string } {
  const link = v.link ?? ((u: string) => u);
  const blocks = v.blocks
    .map((b) => {
      if (b.type === 'text') return `<p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#3D443F">${esc(b.text)}</p>`;
      if (b.type === 'cta')
        return `<table role="presentation" cellspacing="0" cellpadding="0" style="margin:4px 0 14px"><tr><td style="background:${v.accent};border-radius:8px"><a href="${esc(link(b.url))}" style="display:inline-block;padding:11px 16px;font-family:${FONT};font-size:14px;font-weight:800;color:#14201B;text-decoration:none">${esc(b.label)}</a></td></tr></table>`;
      const title = b.title ? `<div style="font-family:${DISPLAY};font-weight:800;font-size:16px;color:#14201B;margin:6px 0 10px">${esc(b.title)}</div>` : '';
      const cards = b.items
        .map(
          (it) => `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #EFEBE2;border-radius:10px;margin:0 0 10px;border-collapse:separate;overflow:hidden"><tr>
<td width="90" style="width:90px;padding:0;vertical-align:top">${
            it.image
              ? `<a href="${esc(link(it.url))}"><img src="${esc(it.image)}" width="90" height="80" alt="" style="display:block;width:90px;height:80px;object-fit:cover;border:0;border-radius:10px 0 0 10px"></a>`
              : `<div style="width:90px;height:80px;background:${v.color};border-radius:10px 0 0 10px"></div>`
          }</td>
<td style="padding:10px 10px 10px 14px;vertical-align:middle;font-family:${FONT}"><a href="${esc(link(it.url))}" style="color:#14201B;text-decoration:none;font-weight:700;font-size:14px">${esc(it.name)}</a><div style="font-size:13px;color:#5E655F;line-height:1.4">${esc(it.text)}</div></td>
</tr></table>`,
        )
        .join('');
      return title + cards;
    })
    .join('\n');
  const hero = v.heroImageUrl
    ? `<tr><td style="padding:0"><img src="${esc(v.heroImageUrl)}" width="560" alt="" style="display:block;width:100%;max-width:560px;height:220px;object-fit:cover;border:0"></td></tr>`
    : '';
  const pixel = v.openPixelUrl ? `<img src="${esc(v.openPixelUrl)}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">` : '';
  const html = `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(v.title)}</title></head>
<body style="margin:0;padding:0;background:#EDE8DC">
<span style="display:none;max-height:0;overflow:hidden;opacity:0">${esc(v.preheader || v.intro.slice(0, 120))}</span>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#EDE8DC;padding:26px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:6px;overflow:hidden;border-collapse:separate">
<tr><td style="background:${v.color};color:#ffffff;padding:18px 24px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
<td style="font-family:${DISPLAY};font-weight:800;font-size:18px;color:#ffffff">${esc(v.newsletterName)}</td>
<td align="right" style="font-family:${FONT};font-size:12px;color:#ffffff;opacity:.8">${v.number ? `n°${v.number}` : ''}</td>
</tr></table></td></tr>
${hero}
<tr><td style="padding:22px 24px;font-family:${FONT}">
<div style="font-family:${DISPLAY};font-weight:800;font-size:28px;line-height:1;letter-spacing:-0.5px;color:#14201B;margin:0 0 14px">${esc(v.title)}</div>
<p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#3D443F">${esc(v.intro)}</p>
${blocks}
<div style="font-size:11px;color:#9A9F95;border-top:1px solid #EFEBE2;padding-top:12px;margin-top:6px;line-height:1.5">Vous recevez cet email car vous êtes inscrit·e à la lettre ${esc(v.brandName)}. <a href="${esc(v.unsubscribeUrl)}" style="color:#9A9F95">Se désinscrire</a> · <a href="${esc(v.preferencesUrl)}" style="color:#9A9F95">Gérer mes préférences</a></div>
${pixel}
</td></tr></table>
</td></tr></table></body></html>`;
  const text = [
    `${v.newsletterName}${v.number ? ` · n°${v.number}` : ''}`,
    '',
    v.title,
    '',
    v.intro,
    '',
    ...v.blocks.flatMap((b) =>
      b.type === 'text' ? [b.text, ''] : b.type === 'cta' ? [`${b.label} : ${b.url}`, ''] : [...(b.title ? [b.title] : []), ...b.items.map((i) => `• ${i.name} — ${i.text} (${i.url})`), ''],
    ),
    `Se désinscrire : ${v.unsubscribeUrl}`,
  ].join('\n');
  return { html, text };
}
