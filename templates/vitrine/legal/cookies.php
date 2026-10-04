<?php
/** Cookies du site de l'association. */
use App\Vitrine\Host;
use App\Vitrine\Site;
?>
<article class="legal wrap">
  <header class="legal__head">
    <span class="eyebrow eyebrow--lg"><?= e(Site::name()) ?></span>
    <h1 class="h-xl legal__title"><?= e($title) ?></h1>
    <p class="legal__updated">Mise à jour : <?= e(date_fr($updated)) ?></p>
  </header>
  <div class="legal__body prose">
    <p>Ce site n’utilise <b>aucun cookie publicitaire ni de mesure d’audience</b>, et ne charge aucun service tiers (vidéos, réseaux sociaux) tant que vous ne cliquez pas pour vous y rendre : c’est pourquoi aucun bandeau ne vous demande votre accord.</p>
    <h2>Le seul cookie déposé</h2>
    <ul>
      <li><b>sr_session</b> : cookie technique, déposé quand vous utilisez un formulaire (adhésion, bénévolat, contact, newsletter). Il protège l’envoi contre les abus et s’efface à la fermeture du navigateur. Il est strictement nécessaire et ne sert à rien d’autre.</li>
    </ul>
    <h2>En quittant le site</h2>
    <p>Les liens vers YouTube, Facebook, Instagram, X ou vers les pages de paiement (Stripe, PayPal) vous mènent sur ces services, qui appliquent leurs propres règles en matière de cookies. Le musée en ligne (<a href="<?= e(Host::museum('/cookies/')) ?>"><?= e(Host::museumHost()) ?></a>) a sa propre page cookies.</p>
  </div>
</article>
