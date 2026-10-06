<?php
/** Politique de confidentialité (RGPD). Variables : réglages « legal », assistant, dons */
use App\Services\I18n;

$en = I18n::isEn();
$who = e($publisher) . ($address ? ', ' . e(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ', ', $address))), ', ')) : '');
$mail = $privacy !== '' ? '<a href="mailto:' . e($privacy) . '">' . e($privacy) . '</a>' : '<a href="' . e(url('/contact/') . '?objet=autre') . '">' . ($en ? 'our contact form' : 'notre formulaire de contact') . '</a>';
?>
<article class="legal wrap">
  <header class="legal__head">
    <span class="eyebrow eyebrow--lg"><?= e($site) ?></span>
    <h1 class="h-xl legal__title"><?= e($title) ?></h1>
    <p class="legal__updated"><?= e($en ? 'Last updated: ' : 'Mise à jour : ') . e(date_fr($updated)) ?></p>
  </header>
  <div class="legal__body prose">
  <?php if ($en): ?>
    <p class="lead" style="max-width:none">We collect as little data as possible, only for clearly identified purposes, and we never sell or pass it on for commercial purposes.</p>
    <h2>Data controller</h2>
    <p>The controller is <?= $who ?>. For any question about your data: <?= $mail ?>.</p>
    <h2>What we process, why, and for how long</h2>
    <div class="legal__table" role="region" aria-label="Processing" tabindex="0">
    <table>
      <thead><tr><th>Purpose</th><th>Data</th><th>Legal basis</th><th>Retention</th></tr></thead>
      <tbody>
        <tr><td>Answering your messages (contact form)</td><td>Name, email, organisation and phone (optional), message, page concerned</td><td>Legitimate interest / your request</td><td>3 years after the last exchange</td></tr>
        <tr><td>Contributions to the museum (photos, documents, testimonies)</td><td>Name, email, credit shown, description, files, rights confirmation</td><td>Consent</td><td>As long as the document is published; 3 years for unpublished submissions</td></tr>
        <tr><td>“On This Day” newsletter</td><td>Email, language, sign-up and confirmation dates</td><td>Consent (double opt-in)</td><td>Until you unsubscribe: your address is then erased</td></tr>
        <?php if ($push): ?>
        <tr><td>Supporter’s logbook</td><td>Email, matches ticked, nickname and public page if you choose them, anniversary reminders chosen (email, devices to notify), dates</td><td>Consent (you create the logbook)</td><td>Until you delete the logbook (“Delete my logbook” button); an empty logbook never opened from the emailed link is erased after 90 days</td></tr>
        <tr><td>Notifications of the museum app</td><td>The technical subscription address and keys provided by your browser, topics chosen, language, dates — no name, no email, no IP address</td><td>Consent (you switch them on)</td><td>Until you switch them off, or as soon as your browser's notification service reports the subscription has ended</td></tr>
        <?php endif; ?>
        <?php if ($donations): ?>
        <tr><td>Online donations<?= $receipts ? ' and tax receipts' : '' ?></td><td>First and last name, email<?= $receipts ? ', postal address (receipt)' : '' ?>, amount, frequency, name shown on the donors' wall (optional), payment references</td><td>Performance of the donation; legal obligation (accounting<?= $receipts ? ', tax receipts' : '' ?>)</td><td>Statutory retention period for accounting records (up to 10 years); details of payments never completed are erased after 30 days</td></tr>
        <?php endif; ?>
        <?php if ($aiOn): ?>
        <tr><td>AI assistant (questions and answers)</td><td>Questions asked, answers, page, language, technical measurements — no name, no IP address in clear</td><td>Legitimate interest (improving the museum)</td><td><?= (int) $aiDays ?> days</td></tr>
        <?php endif; ?>
        <tr><td>Votes (Legendary XI) and quiz</td><td>Anonymous choices; temporary fingerprint of the connection to prevent multiple votes</td><td>Legitimate interest</td><td>Fingerprint erased within 48 hours</td></tr>
        <tr><td>Clubhouse quiz (live game)</td><td>Nickname you choose, answers and points; a game key kept on your device</td><td>Legitimate interest (running the game)</td><td>Erased 24 hours after the game’s last activity</td></tr>
        <tr><td>Proof of your cookie choices</td><td>Choices, date, anonymised fingerprint</td><td>Legal obligation</td><td>13 months</td></tr>
        <tr><td>Audience measurement (internal)</td><td>Page viewed, hour, language — no cookie, no IP address, no identifier</td><td>Legitimate interest (exempt from consent)</td><td>Daily totals kept 13 months; raw logs deleted within 48 hours</td></tr>
        <tr><td>Security and abuse prevention</td><td>Encrypted fingerprints of IP addresses (request limits); server logs kept by the host</td><td>Legitimate interest; legal obligation of the host</td><td>48 hours for fingerprints; up to 1 year for the host's logs</td></tr>
        <tr><td>Back-office accounts (museum team)</td><td>Name, email, role, history of changes</td><td>Legitimate interest</td><td>As long as the account is active</td></tr>
      </tbody>
    </table>
    </div>
    <h2>What stays in your browser</h2>
    <p>Your card album, text size, cookie choices and the current assistant conversation are stored only in your browser (local storage). They are never sent to us, except the questions you choose to ask the assistant.</p>
    <h2>Recipients and processors</h2>
    <ul>
      <li><b>Our host</b> (see the <a href="<?= e(url('/mentions-legales/')) ?>">legal notice</a>), in France.</li>
      <?php if ($aiOn): ?><li><b>Google (Gemini API)</b>: receives your question and extracts from the museum's records in order to write the answer. Google may process this data outside the European Union, under its contractual guarantees (standard contractual clauses / EU-US Data Privacy Framework). Please do not include personal information in your questions.</li><?php endif; ?>
      <?php if ($donations): ?><li><b>Stripe</b> and <b>PayPal</b>: process payments on their own secure pages. Your card details never pass through our servers.</li><?php endif; ?>
      <li><b>Our email provider</b>: sends confirmations, replies and the newsletter.</li>
      <?php if ($push): ?><li><b>The notification service of your browser</b> (Google, Apple, Mozilla or Microsoft, depending on your device), if you switch on notifications: it delivers the messages, which are encrypted so that only your browser can read them.</li><?php endif; ?>
      <li><b>OpenStreetMap</b>: on the map page, map tiles are loaded from OpenStreetMap servers, which receive your IP address in order to display them.</li>
      <li><b>YouTube, Dailymotion, X, Instagram</b>: only if you accept them, when you play a video or display a post (see the <a href="<?= e(url('/cookies/')) ?>">cookies page</a>).</li>
    </ul>
    <h2>Your rights</h2>
    <p>You have the right to access, rectify and erase your data, to object to or restrict its processing, to data portability, and to give instructions about what happens to it after your death. You can withdraw your consent at any time (unsubscribe link in every newsletter, cookie settings at the bottom of every page). To exercise your rights: <?= $mail ?>. We reply within one month.</p>
    <p>If you believe your rights are not being respected, you can lodge a complaint with the CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>), the French data protection authority.</p>
    <h2>Security</h2>
    <p>The site uses HTTPS. Secret keys (payments, AI, email) are encrypted on the server, the data is stored outside the public folder, and regular backups are made. Access to the back-office is restricted to the museum team.</p>
  <?php else: ?>
    <p class="lead" style="max-width:none">Nous collectons le moins de données possible, pour des usages précis, et nous ne les vendons ni ne les cédons jamais à des fins commerciales.</p>
    <h2>Responsable du traitement</h2>
    <p>Le responsable du traitement est <?= $who ?>. Pour toute question sur vos données : <?= $mail ?>.</p>
    <h2>Ce que nous traitons, pourquoi et combien de temps</h2>
    <div class="legal__table" role="region" aria-label="Traitements" tabindex="0">
    <table>
      <thead><tr><th>Finalité</th><th>Données</th><th>Base légale</th><th>Durée de conservation</th></tr></thead>
      <tbody>
        <tr><td>Répondre à vos messages (formulaire de contact)</td><td>Nom, e-mail, entreprise et téléphone (facultatifs), message, page concernée</td><td>Intérêt légitime / votre demande</td><td>3 ans après le dernier échange</td></tr>
        <tr><td>Contributions au musée (photos, documents, témoignages)</td><td>Nom, e-mail, crédit affiché, description, fichiers, attestation des droits</td><td>Consentement</td><td>Tant que le document est publié ; 3 ans pour les envois non publiés</td></tr>
        <tr><td>Newsletter « Ce jour-là »</td><td>E-mail, langue, dates d’inscription et de confirmation</td><td>Consentement (double validation)</td><td>Jusqu’à la désinscription : l’adresse est alors effacée</td></tr>
        <?php if ($push): ?>
        <tr><td>Carnet du supporter</td><td>E-mail, matchs cochés, pseudo et page publique si vous les choisissez, rappels d’anniversaire choisis (e-mail, appareils à notifier), dates</td><td>Consentement (vous créez le carnet)</td><td>Jusqu’à la suppression du carnet (bouton « Supprimer mon carnet ») ; un carnet vide jamais ouvert par le lien reçu est effacé après 90 jours</td></tr>
        <tr><td>Notifications de l’appli du musée</td><td>L’adresse technique d’abonnement et les clés fournies par votre navigateur, sujets choisis, langue, dates — ni nom, ni e-mail, ni adresse IP</td><td>Consentement (vous les activez)</td><td>Jusqu’à leur désactivation, ou dès que le service de notifications de votre navigateur signale la fin de l’abonnement</td></tr>
        <?php endif; ?>
        <?php if ($donations): ?>
        <tr><td>Dons en ligne<?= $receipts ? ' et reçus fiscaux' : '' ?></td><td>Nom, prénom, e-mail<?= $receipts ? ', adresse postale (reçu)' : '' ?>, montant, fréquence, nom affiché sur le mur des donateurs (facultatif), références de paiement</td><td>Exécution du don ; obligation légale (comptabilité<?= $receipts ? ', reçus fiscaux' : '' ?>)</td><td>Durée légale de conservation des pièces comptables (jusqu’à 10 ans) ; les coordonnées des paiements jamais finalisés sont effacées après 30 jours</td></tr>
        <?php endif; ?>
        <?php if ($aiOn): ?>
        <tr><td>Assistant IA (questions et réponses)</td><td>Questions posées, réponses, page, langue, mesures techniques — ni nom, ni adresse IP en clair</td><td>Intérêt légitime (amélioration du musée)</td><td><?= (int) $aiDays ?> jours</td></tr>
        <?php endif; ?>
        <tr><td>Votes (Onze de légende) et quiz</td><td>Choix anonymes ; empreinte temporaire de la connexion pour éviter les votes multiples</td><td>Intérêt légitime</td><td>Empreinte effacée sous 48 heures</td></tr>
        <tr><td>Quiz du club-house (partie en direct)</td><td>Pseudo choisi, réponses et points ; une clé de partie gardée sur votre appareil</td><td>Intérêt légitime (faire tourner la partie)</td><td>Effacés 24 heures après la dernière activité de la partie</td></tr>
        <tr><td>Preuve de vos choix en matière de cookies</td><td>Choix, date, empreinte anonymisée</td><td>Obligation légale</td><td>13 mois</td></tr>
        <tr><td>Mesure d’audience (interne)</td><td>Page vue, heure, langue — sans cookie, sans adresse IP, sans identifiant</td><td>Intérêt légitime (exemptée de consentement)</td><td>Totaux quotidiens conservés 13 mois ; journaux bruts effacés sous 48 heures</td></tr>
        <tr><td>Sécurité et prévention des abus</td><td>Empreintes chiffrées des adresses IP (limites de requêtes) ; journaux du serveur tenus par l’hébergeur</td><td>Intérêt légitime ; obligation légale de l’hébergeur</td><td>48 heures pour les empreintes ; jusqu’à 1 an pour les journaux de l’hébergeur</td></tr>
        <tr><td>Comptes du back-office (équipe du musée)</td><td>Nom, e-mail, rôle, historique des modifications</td><td>Intérêt légitime</td><td>Tant que le compte est actif</td></tr>
      </tbody>
    </table>
    </div>
    <h2>Ce qui reste dans votre navigateur</h2>
    <p>Votre album de cartes, la taille du texte, vos choix de cookies et la conversation en cours avec l’assistant sont enregistrés uniquement dans votre navigateur (stockage local). Ils ne nous sont jamais transmis, sauf les questions que vous choisissez de poser à l’assistant.</p>
    <h2>Destinataires et sous-traitants</h2>
    <ul>
      <li><b>Notre hébergeur</b> (voir les <a href="<?= e(url('/mentions-legales/')) ?>">mentions légales</a>), en France.</li>
      <?php if ($aiOn): ?><li><b>Google (API Gemini)</b> : reçoit votre question et des extraits des fiches du musée pour rédiger la réponse. Google peut traiter ces données hors de l’Union européenne, avec ses garanties contractuelles (clauses contractuelles types / cadre de protection des données UE–États-Unis). Merci de ne pas inclure d’informations personnelles dans vos questions.</li><?php endif; ?>
      <?php if ($donations): ?><li><b>Stripe</b> et <b>PayPal</b> : traitent les paiements sur leurs propres pages sécurisées. Vos données bancaires ne transitent jamais par nos serveurs.</li><?php endif; ?>
      <li><b>Notre prestataire d’envoi d’e-mails</b> : confirmations, réponses et newsletter.</li>
      <?php if ($push): ?><li><b>Le service de notifications de votre navigateur</b> (Google, Apple, Mozilla ou Microsoft selon l’appareil), si vous activez les notifications : il achemine les messages, chiffrés pour que seul votre navigateur puisse les lire.</li><?php endif; ?>
      <li><b>OpenStreetMap</b> : sur la page Carte, les fonds de carte sont chargés depuis les serveurs d’OpenStreetMap, qui reçoivent votre adresse IP pour les afficher.</li>
      <li><b>YouTube, Dailymotion, X, Instagram</b> : uniquement si vous les acceptez, lorsque vous lancez une vidéo ou affichez une publication (voir la <a href="<?= e(url('/cookies/')) ?>">page cookies</a>).</li>
    </ul>
    <h2>Vos droits</h2>
    <p>Vous disposez d’un droit d’accès, de rectification et d’effacement de vos données, d’un droit d’opposition et de limitation, d’un droit à la portabilité, et du droit de définir des directives sur leur sort après votre décès. Vous pouvez retirer votre consentement à tout moment (lien de désinscription dans chaque newsletter, réglage des cookies en bas de chaque page). Pour exercer vos droits : <?= $mail ?>. Nous répondons sous un mois.</p>
    <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>).</p>
    <h2>Sécurité</h2>
    <p>Le site est servi en HTTPS. Les clés secrètes (paiements, IA, e-mail) sont chiffrées sur le serveur, les données sont stockées hors du dossier public et des sauvegardes régulières sont réalisées. L’accès au back-office est réservé à l’équipe du musée.</p>
  <?php endif; ?>
  </div>
</article>
