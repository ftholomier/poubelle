/**
 * Gabarit HTML des emails transactionnels, fidèle à la charte :
 * fond crème, carte papier, titres Bricolage Grotesque (repli Arial), bouton vert Loue.
 */
export type EmailBrand = { name: string; color: string; accent: string };

export const TERRICOM_BRAND: EmailBrand = { name: 'terricom', color: '#1F6B52', accent: '#F4B266' };

const esc = (s: string) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

export function escapeHtml(s: string): string {
  return esc(s);
}

export function paragraph(text: string): string {
  return `<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#3D443F">${esc(text)}</p>`;
}

export function renderEmail(opts: {
  brand?: EmailBrand;
  preheader?: string;
  eyebrow?: string;
  title: string;
  paragraphs?: string[];
  html?: string;
  cta?: { label: string; url: string };
  footer?: string;
  unsubscribeUrl?: string;
}): { html: string; text: string } {
  const brand = opts.brand ?? TERRICOM_BRAND;
  const body = (opts.paragraphs ?? []).map(paragraph).join('') + (opts.html ?? '');
  const button = opts.cta
    ? `<table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0 6px"><tr><td style="background:${brand.color};border-radius:12px"><a href="${esc(opts.cta.url)}" style="display:inline-block;padding:13px 20px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none">${esc(opts.cta.label)}</a></td></tr></table>`
    : '';
  const unsubscribe = opts.unsubscribeUrl
    ? ` · <a href="${esc(opts.unsubscribeUrl)}" style="color:#5E655F">Se désinscrire</a>`
    : '';
  const html = `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(opts.title)}</title></head>
<body style="margin:0;padding:0;background:#F7F4EC">
<span style="display:none;max-height:0;overflow:hidden;opacity:0">${esc(opts.preheader ?? opts.title)}</span>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F7F4EC;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px">
<tr><td style="padding:0 6px 16px;font-family:'Bricolage Grotesque',Arial,sans-serif;font-weight:800;font-size:22px;letter-spacing:-0.5px;color:#14201B">${esc(brand.name)}<span style="color:${brand.accent}">.</span></td></tr>
<tr><td style="background:#FFFDF8;border:1px solid #E4DFD3;border-radius:22px;padding:30px 28px;font-family:'Instrument Sans',Arial,sans-serif">
${opts.eyebrow ? `<div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#C8702A;margin-bottom:8px">${esc(opts.eyebrow)}</div>` : ''}
<h1 style="margin:0 0 16px;font-family:'Bricolage Grotesque',Arial,sans-serif;font-weight:800;font-size:26px;line-height:1.15;letter-spacing:-0.5px;color:#14201B">${esc(opts.title)}</h1>
${body}${button}
</td></tr>
<tr><td style="padding:16px 8px;font-family:Arial,sans-serif;font-size:12px;line-height:1.5;color:#5E655F">${esc(opts.footer ?? 'Vous recevez cet email car une action a été réalisée sur terricom.fr, la plateforme de valorisation économique de votre territoire.')}${unsubscribe}</td></tr>
</table></td></tr></table></body></html>`;
  const text = [
    opts.eyebrow?.toUpperCase(),
    opts.title,
    '',
    ...(opts.paragraphs ?? []),
    opts.cta ? `${opts.cta.label} : ${opts.cta.url}` : '',
    '',
    opts.footer ?? '— terricom',
    opts.unsubscribeUrl ? `Se désinscrire : ${opts.unsubscribeUrl}` : '',
  ]
    .filter((l) => l !== undefined)
    .join('\n');
  return { html, text };
}
