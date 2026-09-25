import { escapeHtml, renderEmail, TERRICOM_BRAND, type EmailBrand } from './layout';
import type { OutgoingEmail } from './send';

type TerritoryLike = { name: string; colorPrimary: string; colorAccent: string };

const brandOf = (t?: TerritoryLike | null): EmailBrand => (t ? { name: t.name, color: t.colorPrimary, accent: t.colorAccent } : TERRICOM_BRAND);

export function verifyEmailTemplate(p: { to: string; firstName: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: `Bienvenue ${p.firstName || ''}, confirmez votre adresse`.trim(),
    paragraphs: [
      'Un dernier clic pour activer votre compte terricom. Ce lien est valable 48 heures.',
      "Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement ce message.",
    ],
    cta: { label: 'Confirmer mon adresse', url: p.url },
  });
  return { to: p.to, subject: 'Confirmez votre adresse email', html, text, template: 'verify-email' };
}

export function passwordResetTemplate(p: { to: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: 'Réinitialisation de votre mot de passe',
    paragraphs: [
      'Vous avez demandé à changer votre mot de passe. Ce lien est valable 1 heure et ne fonctionne qu’une fois.',
      "Si vous n'avez rien demandé, votre compte est en sécurité : ignorez ce message.",
    ],
    cta: { label: 'Choisir un nouveau mot de passe', url: p.url },
  });
  return { to: p.to, subject: 'Réinitialisez votre mot de passe', html, text, template: 'password-reset' };
}

export function claimInvitationTemplate(p: { to: string; establishmentName: string; territory: TerritoryLike; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: `Offert par ${p.territory.name}`,
    title: `${p.establishmentName}, votre vitrine numérique vous attend`,
    paragraphs: [
      `${p.territory.name} a créé la fiche de votre établissement sur son portail économique. Elle est déjà visible sur la carte et référencée sur Google.`,
      'Prenez-en le contrôle en 5 minutes : horaires justes, photos, actualités, offres. C’est gratuit, sans carte bancaire.',
    ],
    cta: { label: 'Gérer ma fiche', url: p.url },
    footer: `${p.territory.name} vous contacte dans le cadre de l'animation économique du territoire. Vous pouvez demander la suppression de votre fiche à tout moment.`,
  });
  return {
    to: p.to,
    subject: `${p.establishmentName} : votre fiche vous attend sur le portail de ${p.territory.name}`,
    html,
    text,
    template: 'claim-invitation',
  };
}

export function claimSubmittedTemplate(p: { to: string; firstName: string; establishmentName: string; reviewer: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: `${p.reviewer} vérifie votre demande`,
    paragraphs: [
      `Bonjour ${p.firstName}, nous avons bien reçu votre demande de gestion de « ${p.establishmentName} ».`,
      'La vérification prend en moyenne moins de 24 h. Vous recevrez un email dès que la fiche sera à vous.',
    ],
  });
  return { to: p.to, subject: 'Demande de revendication reçue', html, text, template: 'claim-submitted' };
}

export function claimApprovedTemplate(p: { to: string; firstName: string; establishmentName: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Fiche validée !',
    title: `Bienvenue ${p.firstName}, la vitrine est à vous.`,
    paragraphs: [
      `Vous gérez désormais « ${p.establishmentName} ». Trois petites actions et votre fiche passe au niveau « Vitrine Or » : photos, horaires, produits phares.`,
    ],
    cta: { label: 'Accéder à mon tableau de bord', url: p.url },
  });
  return { to: p.to, subject: `« ${p.establishmentName} » est à vous`, html, text, template: 'claim-approved' };
}

export function claimNeedsInfoTemplate(p: { to: string; firstName: string; establishmentName: string; note: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: 'Un justificatif est nécessaire',
    paragraphs: [
      `Bonjour ${p.firstName}, pour finaliser votre demande concernant « ${p.establishmentName} », la collectivité a besoin d'un complément :`,
      p.note,
    ],
    cta: { label: 'Compléter ma demande', url: p.url },
  });
  return { to: p.to, subject: 'Votre demande de revendication : un complément est nécessaire', html, text, template: 'claim-needs-info' };
}

export function claimRejectedTemplate(p: { to: string; firstName: string; establishmentName: string; note: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: 'Votre demande n’a pas pu être validée',
    paragraphs: [
      `Bonjour ${p.firstName}, la collectivité n'a pas pu confirmer votre lien avec « ${p.establishmentName} ».`,
      p.note || 'Vous pouvez répondre à cet email pour en savoir plus.',
    ],
  });
  return { to: p.to, subject: 'Votre demande de revendication', html, text, template: 'claim-rejected' };
}

export function verificationCodeTemplate(p: { to: string; code: string; establishmentName: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: `Votre code : ${p.code}`,
    paragraphs: [`Saisissez ce code pour prouver que vous gérez « ${p.establishmentName} ». Il est valable 30 minutes.`],
  });
  return { to: p.to, subject: `Code de vérification : ${p.code}`, html, text, template: 'verification-code' };
}

export function staffInvitationTemplate(p: { to: string; inviter: string; scopeName: string; roleLabel: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: p.scopeName,
    title: `${p.inviter} vous invite sur terricom`,
    paragraphs: [
      `Vous êtes invité·e en tant que « ${p.roleLabel} » pour animer l'économie locale : fiches des entreprises, campagnes, newsletter, statistiques.`,
      'Ce lien est personnel et valable 7 jours. La double authentification vous sera proposée à la création du compte.',
    ],
    cta: { label: 'Rejoindre l’équipe', url: p.url },
  });
  return { to: p.to, subject: `Invitation : ${p.scopeName} sur terricom`, html, text, template: 'staff-invitation' };
}

export function memberInvitationTemplate(p: { to: string; inviter: string; companyName: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: `${p.inviter} vous invite à gérer « ${p.companyName} »`,
    paragraphs: ['Vous pourrez mettre à jour la fiche, publier des actualités et consulter les statistiques.'],
    cta: { label: 'Accepter l’invitation', url: p.url },
  });
  return { to: p.to, subject: `Invitation à gérer ${p.companyName}`, html, text, template: 'member-invitation' };
}

export function newsletterConfirmTemplate(p: { to: string; territory: TerritoryLike; newsletterName: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: p.newsletterName,
    title: 'Confirmez votre inscription',
    paragraphs: [
      `Vous avez demandé à recevoir « ${p.newsletterName} » de ${p.territory.name}. Un clic pour confirmer (double validation, comme le veut le RGPD).`,
      'Sans confirmation, aucun email ne vous sera envoyé et votre adresse sera effacée sous 30 jours.',
    ],
    cta: { label: 'Je confirme', url: p.url },
    footer: 'Vos données ne sont jamais revendues. Désinscription en un clic depuis chaque lettre.',
  });
  return { to: p.to, subject: `Confirmez votre inscription à « ${p.newsletterName} »`, html, text, template: 'newsletter-confirm' };
}

export function followConfirmTemplate(p: { to: string; territory: TerritoryLike; establishmentName: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: p.establishmentName,
    title: 'Confirmez votre abonnement',
    paragraphs: [
      `Vous avez demandé à recevoir les nouveautés et les offres de ${p.establishmentName}. Un clic pour confirmer (double validation, comme le veut le RGPD).`,
      'Sans confirmation, aucun email ne vous sera envoyé et votre adresse sera effacée sous 30 jours.',
    ],
    cta: { label: 'Je confirme', url: p.url },
    footer: `Votre adresse n’est transmise qu’à ${p.establishmentName}, jamais revendue. Désinscription en un clic depuis chaque email.`,
  });
  return { to: p.to, subject: `Confirmez votre abonnement à ${p.establishmentName}`, html, text, template: 'follow-confirm' };
}

export function contactMessageTemplate(p: {
  to: string;
  establishmentName: string;
  senderName: string;
  senderEmail: string | null;
  senderPhone: string | null;
  body: string;
  url: string;
}): OutgoingEmail {
  const details = `<div style="background:#F7F4EC;border-radius:12px;padding:14px 16px;margin:6px 0 10px;font-size:15px;line-height:1.6;color:#1C2320">${escapeHtml(p.body).replace(/\n/g, '<br>')}</div><div style="font-size:13px;color:#5E655F">${escapeHtml(p.senderName)}${p.senderEmail ? ` · ${escapeHtml(p.senderEmail)}` : ''}${p.senderPhone ? ` · ${escapeHtml(p.senderPhone)}` : ''}</div>`;
  const { html, text } = renderEmail({
    eyebrow: 'Nouveau message',
    title: `${p.senderName} vous a écrit`,
    html: details,
    cta: { label: 'Répondre depuis mon espace', url: p.url },
  });
  return {
    to: p.to,
    subject: `Nouveau message pour ${p.establishmentName}`,
    html,
    text: `${text}\n\n${p.body}`,
    template: 'contact-message',
    headers: p.senderEmail ? { 'Reply-To': p.senderEmail } : undefined,
  };
}

export function jobApplicationTemplate(p: { to: string; jobTitle: string; candidate: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Candidature',
    title: `${p.candidate} postule : ${p.jobTitle}`,
    paragraphs: ['Retrouvez la candidature et le CV dans votre espace. Pensez à répondre, même brièvement : c’est l’image du territoire.'],
    cta: { label: 'Voir la candidature', url: p.url },
  });
  return { to: p.to, subject: `Nouvelle candidature : ${p.jobTitle}`, html, text, template: 'job-application' };
}

export function applicationAckTemplate(p: { to: string; jobTitle: string; companyName: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Candidature envoyée !',
    title: `${p.companyName} a bien reçu votre candidature`,
    paragraphs: [
      `Votre candidature au poste « ${p.jobTitle} » a été transmise. Réponse en moyenne sous 5 jours.`,
      'Vos données sont transmises uniquement à l’employeur et supprimées au bout de 24 mois.',
    ],
  });
  return { to: p.to, subject: `Candidature envoyée : ${p.jobTitle}`, html, text, template: 'application-ack' };
}

export function appointmentRequestTemplate(p: { to: string; establishmentName: string; client: string; when: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Rendez-vous',
    title: `${p.client} souhaite un rendez-vous`,
    paragraphs: [`Créneau souhaité : ${p.when}. Confirmez ou proposez un autre horaire depuis votre espace.`],
    cta: { label: 'Répondre', url: p.url },
  });
  return { to: p.to, subject: `Demande de rendez-vous — ${p.establishmentName}`, html, text, template: 'appointment-request' };
}

export function appointmentResponseTemplate(p: {
  to: string;
  establishmentName: string;
  confirmed: boolean;
  cancelled?: boolean;
  when: string;
  note: string | null;
}): OutgoingEmail {
  const { html, text } = renderEmail({
    title: p.cancelled ? `Rendez-vous annulé : ${p.when}` : p.confirmed ? `Rendez-vous confirmé : ${p.when}` : 'Votre demande de rendez-vous',
    paragraphs: [
      p.cancelled
        ? `${p.establishmentName} doit annuler votre rendez-vous ${p.when}. Nous vous prions de l’excuser : n’hésitez pas à proposer un autre créneau.`
        : p.confirmed
          ? `${p.establishmentName} vous attend ${p.when}.`
          : `${p.establishmentName} ne peut pas vous recevoir sur ce créneau.`,
      ...(p.note ? [p.note] : []),
    ],
  });
  return {
    to: p.to,
    subject: `${p.establishmentName} : ${p.cancelled ? 'rendez-vous annulé' : p.confirmed ? 'rendez-vous confirmé' : 'réponse à votre demande'}`,
    html,
    text,
    template: 'appointment-response',
  };
}

export function campaignInvitationTemplate(p: { to: string; territory: TerritoryLike; campaignName: string; message: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: 'Invitation de la collectivité',
    title: `Participez à « ${p.campaignName} »`,
    paragraphs: [p.message],
    cta: { label: 'Participer avec une offre', url: p.url },
  });
  return { to: p.to, subject: `${p.territory.name} vous invite : ${p.campaignName}`, html, text, template: 'campaign-invitation' };
}

export function demoRequestAckTemplate(p: { to: string; name: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: `Merci ${p.name}, on vous rappelle très vite`,
    paragraphs: [
      'Votre demande de démonstration est bien arrivée. Un membre de l’équipe terricom vous contacte sous 48 h ouvrées pour caler un créneau de 30 minutes.',
      'En attendant, découvrez le portail pilote du Val de Loue sur terricom.fr.',
    ],
  });
  return { to: p.to, subject: 'Votre demande de démo terricom', html, text, template: 'demo-ack' };
}

export function securityAlertTemplate(p: { to: string; title: string; detail: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Sécurité du compte',
    title: p.title,
    paragraphs: [p.detail, "Si ce n'est pas vous, changez votre mot de passe immédiatement et contactez-nous."],
  });
  return { to: p.to, subject: `Sécurité : ${p.title}`, html, text, template: 'security-alert' };
}

export function privacyExportTemplate(p: { to: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'RGPD',
    title: 'Votre export de données est prêt',
    paragraphs: ['Conformément à l’article 20 du RGPD, voici l’ensemble des données vous concernant. Le lien expire dans 7 jours.'],
    cta: { label: 'Télécharger mes données', url: p.url },
  });
  return { to: p.to, subject: 'Export de vos données personnelles', html, text, template: 'privacy-export' };
}

export function messageReplyTemplate(p: { to: string; establishmentName: string; reply: string; original: string; replyTo: string | null }): OutgoingEmail {
  const html = `<div style="font-size:15px;line-height:1.6;color:#1C2320;white-space:pre-line">${escapeHtml(p.reply)}</div><div style="margin-top:18px;padding-top:12px;border-top:1px solid #E4DFD3;font-size:13px;color:#5E655F">Votre message :<br>${escapeHtml(p.original).replace(/\n/g, '<br>')}</div>`;
  const { html: body, text } = renderEmail({ eyebrow: p.establishmentName, title: `Réponse de ${p.establishmentName}`, html });
  return {
    to: p.to,
    subject: `Réponse de ${p.establishmentName}`,
    html: body,
    text: `${text}\n\n${p.reply}`,
    template: 'message-reply',
    headers: p.replyTo ? { 'Reply-To': p.replyTo } : undefined,
  };
}

export function applicationStatusTemplate(p: { to: string; jobTitle: string; companyName: string; accepted: boolean; note: string | null }): OutgoingEmail {
  const { html, text } = renderEmail({
    title: p.accepted ? `Votre candidature retient l'attention de ${p.companyName}` : `Votre candidature chez ${p.companyName}`,
    paragraphs: [
      p.accepted
        ? `${p.companyName} souhaite échanger avec vous au sujet du poste « ${p.jobTitle} ». Vous serez recontacté·e très prochainement.`
        : `Merci pour votre intérêt pour le poste « ${p.jobTitle} ». ${p.companyName} ne donnera pas suite cette fois-ci.`,
      ...(p.note ? [p.note] : []),
    ],
  });
  return { to: p.to, subject: `${p.companyName} — ${p.jobTitle}`, html, text, template: 'application-status' };
}

export function invoiceReminderTemplate(p: { to: string; customerName: string; number: string; amount: string; dueAt: string; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: 'Facturation terricom',
    title: `Facture ${p.number} : rappel d’échéance`,
    paragraphs: [
      `Bonjour, sauf erreur de notre part, la facture ${p.number} adressée à ${p.customerName} (${p.amount} TTC) arrivait à échéance le ${p.dueAt}.`,
      'Si le règlement est en cours (mandat administratif, Chorus Pro), merci de ne pas tenir compte de ce message. Pour toute question, répondez simplement à cet email.',
    ],
    cta: { label: 'Télécharger la facture', url: p.url },
  });
  return { to: p.to, subject: `Rappel : facture ${p.number}`, html, text, template: 'invoice-reminder' };
}

export function ticketReplyTemplate(p: { to: string; number: number; subject: string; reply: string; resolved: boolean }): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: `Support terricom · ticket n°${p.number}`,
    title: p.resolved ? `Votre demande « ${p.subject} » est résolue` : `Réponse à votre demande « ${p.subject} »`,
    paragraphs: [
      p.reply,
      p.resolved ? 'Si le problème persiste, répondez à cet email : le ticket sera rouvert.' : 'Répondez à cet email pour compléter votre demande.',
    ],
  });
  return { to: p.to, subject: `[Support n°${p.number}] ${p.subject}`, html, text, template: 'ticket-reply' };
}

export function ticketCreatedTemplate(p: {
  to: string;
  number: number;
  subject: string;
  territory: string;
  author: string;
  body: string;
  url: string;
}): OutgoingEmail {
  const { html, text } = renderEmail({
    eyebrow: `Support · ${p.territory}`,
    title: `Nouveau ticket n°${p.number} : ${p.subject}`,
    paragraphs: [`${p.author} a ouvert une demande :`, p.body],
    cta: { label: 'Ouvrir le ticket', url: p.url },
  });
  return { to: p.to, subject: `[Support n°${p.number}] ${p.subject}`, html, text, template: 'ticket-created' };
}

export function claimReminderTemplate(p: { to: string; establishmentName: string; territory: TerritoryLike; url: string; views: number }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: `Rappel · offert par ${p.territory.name}`,
    title: p.views > 0 ? `${p.views} personnes ont consulté votre fiche ce mois-ci` : `${p.establishmentName}, votre fiche vous attend toujours`,
    paragraphs: [
      `La fiche de « ${p.establishmentName} » est en ligne sur le portail de ${p.territory.name}, mais elle n’est pas encore gérée par vous : horaires, photos et actualités restent à compléter.`,
      'Cinq minutes suffisent pour la revendiquer, gratuitement.',
    ],
    cta: { label: 'Revendiquer ma fiche', url: p.url },
    footer: `Vous ne souhaitez plus recevoir ces rappels ou voulez retirer votre fiche ? Répondez à ce message : ${p.territory.name} s’en occupe.`,
  });
  return { to: p.to, subject: `Rappel : la fiche de ${p.establishmentName} vous attend`, html, text, template: 'claim-reminder' };
}

export function pendingClaimsDigestTemplate(p: { to: string; territory: TerritoryLike; count: number; oldestHours: number; url: string }): OutgoingEmail {
  const { html, text } = renderEmail({
    brand: brandOf(p.territory),
    eyebrow: 'Back-office · revendications',
    title: `${p.count} revendication${p.count > 1 ? 's' : ''} en attente de validation`,
    paragraphs: [
      `La plus ancienne attend depuis ${p.oldestHours >= 48 ? `${Math.round(p.oldestHours / 24)} jours` : `${p.oldestHours} heures`}. Les professionnels ne peuvent pas modifier leur fiche avant votre validation.`,
    ],
    cta: { label: 'Traiter les revendications', url: p.url },
  });
  return { to: p.to, subject: `${p.count} revendication${p.count > 1 ? 's' : ''} à valider sur terricom`, html, text, template: 'claims-digest' };
}
