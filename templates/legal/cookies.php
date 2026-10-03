<?php
/** Cookies et traceurs. Variables : réglages (voir App\Front\Legal) */
use App\Services\I18n;

$en = I18n::isEn();
$rows = $en ? [
    ['sr_session', 'Cookie', 'Necessary', 'Keeps your session (forms, security token, donation follow-up).', 'End of the session'],
    ['fcsm-consent', 'Local storage', 'Necessary', 'Remembers your cookie choices.', '6 months'],
    ['fcsm-textsize', 'Local storage', 'Preference', 'Text size you have chosen.', 'Until you change it'],
    ['fcsm-album', 'Local storage', 'Feature', 'Cards unlocked in your centenary album.', 'Until you clear your browser'],
    ['fcsm-chat', 'Session storage', 'Feature', 'Current conversation with the assistant.', 'Closing the tab'],
] : [
    ['sr_session', 'Cookie', 'Nécessaire', 'Maintient votre session (formulaires, jeton de sécurité, suivi d’un don).', 'Fin de la session'],
    ['fcsm-consent', 'Stockage local', 'Nécessaire', 'Mémorise vos choix en matière de cookies.', '6 mois'],
    ['fcsm-textsize', 'Stockage local', 'Préférence', 'Taille du texte que vous avez choisie.', 'Jusqu’à modification'],
    ['fcsm-album', 'Stockage local', 'Fonctionnalité', 'Cartes débloquées dans votre album du centenaire.', 'Jusqu’à effacement par vos soins'],
    ['fcsm-chat', 'Stockage de session', 'Fonctionnalité', 'Conversation en cours avec l’assistant.', 'Fermeture de l’onglet'],
];
$third = $en ? [
    ['YouTube, Dailymotion', 'Videos', 'Set when you play a video, only after you have accepted the “Videos” category.'],
    ['X, Instagram', 'Social media', 'Set when an embedded post is displayed, only after you have accepted the “Social media” category.'],
] : [
    ['YouTube, Dailymotion', 'Vidéos', 'Déposés à la lecture d’une vidéo, uniquement après acceptation de la catégorie « Vidéos ».'],
    ['X, Instagram', 'Réseaux sociaux', 'Déposés à l’affichage d’une publication intégrée, uniquement après acceptation de la catégorie « Réseaux sociaux ».'],
];
if ($donations) {
    $third[] = $en ? ['Stripe, PayPal', 'Payment', 'Set on their own payment pages when you make a donation.'] : ['Stripe, PayPal', 'Paiement', 'Déposés sur leurs propres pages de paiement lorsque vous faites un don.'];
}
if ($analytics) {
    $third[] = $en ? ['Audience measurement', 'Statistics', 'Anonymised visit statistics, only after you have accepted them.'] : ['Mesure d’audience', 'Statistiques', 'Statistiques de visite anonymisées, uniquement après acceptation.'];
}
?>
<article class="legal wrap">
  <header class="legal__head">
    <span class="eyebrow eyebrow--lg"><?= e($site) ?></span>
    <h1 class="h-xl legal__title"><?= e($title) ?></h1>
    <p class="legal__updated"><?= e($en ? 'Last updated: ' : 'Mise à jour : ') . e(date_fr($updated)) ?></p>
  </header>
  <div class="legal__body prose">
    <p class="lead" style="max-width:none"><?= e($en ? 'The museum uses no advertising cookies and no tracking. Third-party services (videos, social media) are only loaded if you accept them.' : 'Le musée n’utilise aucun cookie publicitaire ni de pistage. Les services tiers (vidéos, réseaux sociaux) ne sont chargés que si vous les acceptez.') ?></p>
    <p><button type="button" class="btn btn--navy btn--sm" data-cookie-open><?= e($en ? 'Change my cookie choices' : 'Modifier mes choix de cookies') ?></button></p>
    <h2><?= e($en ? 'Cookies and storage set by the museum' : 'Cookies et stockages déposés par le musée') ?></h2>
    <div class="legal__table" role="region" tabindex="0" aria-label="<?= e($en ? 'Cookies' : 'Cookies') ?>">
      <table>
        <thead><tr><th><?= e($en ? 'Name' : 'Nom') ?></th><th><?= e($en ? 'Type' : 'Type') ?></th><th><?= e($en ? 'Category' : 'Catégorie') ?></th><th><?= e($en ? 'Purpose' : 'Finalité') ?></th><th><?= e($en ? 'Lifetime' : 'Durée') ?></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><td><code><?= e($r[0]) ?></code></td><td><?= e($r[1]) ?></td><td><?= e($r[2]) ?></td><td><?= e($r[3]) ?></td><td><?= e($r[4]) ?></td></tr><?php endforeach; ?></tbody>
      </table>
    </div>
    <h2><?= e($en ? 'Third-party services' : 'Services tiers') ?></h2>
    <div class="legal__table" role="region" tabindex="0" aria-label="<?= e($en ? 'Third parties' : 'Services tiers') ?>">
      <table>
        <thead><tr><th><?= e($en ? 'Service' : 'Service') ?></th><th><?= e($en ? 'Category' : 'Catégorie') ?></th><th><?= e($en ? 'When' : 'Quand') ?></th></tr></thead>
        <tbody><?php foreach ($third as $r): ?><tr><td><?= e($r[0]) ?></td><td><?= e($r[1]) ?></td><td><?= e($r[2]) ?></td></tr><?php endforeach; ?></tbody>
      </table>
    </div>
    <p><?= $en ? 'The fonts are hosted by the museum itself: no request is made to Google Fonts. On the map page, the map tiles are loaded from OpenStreetMap servers (no cookie).' : 'Les polices de caractères sont hébergées par le musée lui-même : aucune requête n’est faite à Google Fonts. Sur la page Carte, les fonds de carte proviennent des serveurs d’OpenStreetMap (sans cookie).' ?></p>
    <p><?= $en ? 'More information in our' : 'Plus d’informations dans notre' ?> <a href="<?= e(url('/confidentialite/')) ?>"><?= e($en ? 'privacy policy' : 'politique de confidentialité') ?></a>.</p>
  </div>
</article>
