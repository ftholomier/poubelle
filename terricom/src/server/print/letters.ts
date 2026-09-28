import { rgb } from 'pdf-lib';
import { drawQr, hex, newDoc, wrap } from './kit';

/**
 * Courriers A4 (enveloppe à fenêtre) : invitation à revendiquer une fiche, avec QR code,
 * et, pour la vérification par courrier, le code à usage unique.
 */

export type LetterItem = {
  name: string;
  street: string | null;
  postalCode: string | null;
  communeName: string;
  url: string;
  displayUrl: string;
  code?: string | null;
};

export type LetterSender = { name: string; legalName: string; colorPrimary: string; colorAccent: string; contactEmail: string | null };

const MM = 2.8346;

export async function claimLettersPdf(sender: LetterSender, items: LetterItem[], date = new Date()): Promise<Uint8Array> {
  const { pdf, fonts } = await newDoc();
  const W = 210 * MM;
  const H = 297 * MM;
  const ink = hex('#14201B');
  const muted = hex('#5E655F');
  const dateText = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Paris' }).format(date);
  for (const it of items) {
    const page = pdf.addPage([W, H]);
    // En-tête aux couleurs du territoire
    page.drawRectangle({ x: 0, y: H - 30 * MM, width: W, height: 30 * MM, color: hex(sender.colorPrimary) });
    page.drawText(sender.name, { x: 20 * MM, y: H - 18 * MM, size: 20, font: fonts.display, color: rgb(1, 1, 1) });
    page.drawText(sender.legalName, { x: 20 * MM, y: H - 25 * MM, size: 9, font: fonts.body, color: rgb(1, 1, 1), opacity: 0.85 });
    // Bloc adresse (fenêtre à droite, 45 mm du haut)
    let ay = H - 55 * MM;
    for (const line of [it.name, it.street, `${it.postalCode ?? ''} ${it.communeName}`.trim()].filter(Boolean) as string[]) {
      page.drawText(line, { x: 115 * MM, y: ay, size: 11, font: line === it.name ? fonts.bold : fonts.body, color: ink });
      ay -= 5.2 * MM;
    }
    page.drawText(`Le ${dateText}`, { x: 115 * MM, y: H - 85 * MM, size: 10, font: fonts.body, color: muted });
    // Objet et corps
    let y = H - 100 * MM;
    page.drawText(it.code ? 'Objet : votre code de vérification' : `Objet : votre vitrine numérique sur le portail de ${sender.name}`, {
      x: 20 * MM,
      y,
      size: 11,
      font: fonts.bold,
      color: ink,
    });
    y -= 12 * MM;
    const paragraphs = it.code
      ? [
          'Madame, Monsieur,',
          `Vous avez demandé à gérer la fiche de « ${it.name} » sur le portail économique de ${sender.name}. Pour confirmer que vous êtes bien lié·e à cet établissement, saisissez le code ci-dessous dans votre espace professionnel.`,
          'Ce code est personnel et valable 30 jours. Si vous n’êtes pas à l’origine de cette demande, ignorez ce courrier : la fiche restera inchangée.',
        ]
      : [
          'Madame, Monsieur,',
          `${sender.name} a créé la fiche de « ${it.name} » sur son portail économique, à partir des données publiques des entreprises. Elle est déjà visible sur la carte du territoire et référencée sur les moteurs de recherche.`,
          'Prenez-en le contrôle gratuitement, en quelques minutes : horaires justes, photos, actualités, offres, recrutement. Scannez le QR code ci-dessous ou rendez-vous à l’adresse indiquée.',
        ];
    for (const p of paragraphs) {
      for (const line of wrap(p, fonts.body, 11, 170 * MM)) {
        page.drawText(line, { x: 20 * MM, y, size: 11, font: fonts.body, color: ink });
        y -= 5.6 * MM;
      }
      y -= 3 * MM;
    }
    // Encadré QR / code
    const boxY = y - 52 * MM;
    page.drawRectangle({ x: 20 * MM, y: boxY, width: 170 * MM, height: 48 * MM, color: hex('#F7F4EC'), borderColor: hex('#E4DFD3'), borderWidth: 1 });
    drawQr(page, it.url, 26 * MM, boxY + 6 * MM, 36 * MM);
    let by = boxY + 36 * MM;
    if (it.code) {
      page.drawText('Votre code', { x: 70 * MM, y: by, size: 10, font: fonts.body, color: muted });
      by -= 11 * MM;
      page.drawText(it.code.replace(/(\d{3})(\d{3})/, '$1 $2'), { x: 70 * MM, y: by, size: 30, font: fonts.display, color: ink });
      by -= 8 * MM;
    } else {
      page.drawText('Votre fiche vous attend', { x: 70 * MM, y: by, size: 15, font: fonts.display, color: ink });
      by -= 8 * MM;
    }
    page.drawText(it.displayUrl, { x: 70 * MM, y: by, size: 10, font: fonts.bold, color: hex(sender.colorPrimary) });
    page.drawText('Gratuit, sans engagement, sans carte bancaire.', { x: 70 * MM, y: by - 6 * MM, size: 9, font: fonts.body, color: muted });
    // Signature et pied de page
    page.drawText('Le service développement économique', { x: 20 * MM, y: boxY - 16 * MM, size: 10, font: fonts.body, color: ink });
    page.drawText(sender.legalName, { x: 20 * MM, y: boxY - 21 * MM, size: 10, font: fonts.bold, color: ink });
    const foot = `Portail propulsé par terricom${sender.contactEmail ? ` · Contact : ${sender.contactEmail}` : ''} · Vous pouvez demander la suppression de votre fiche à tout moment.`;
    for (const [i, line] of wrap(foot, fonts.body, 8, 170 * MM).entries())
      page.drawText(line, { x: 20 * MM, y: 14 * MM - i * 4 * MM, size: 8, font: fonts.body, color: muted });
  }
  return pdf.save();
}
