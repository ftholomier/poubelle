<?php
/** Politique de confidentialité du site de l'association. Variables : voir App\Vitrine\Pages::legal() */
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
    <p>L’association <?= e($publisher) ?> est responsable des données personnelles recueillies sur ce site. Elle ne collecte que ce qui est nécessaire, ne vend ni ne cède jamais vos données, et ne les utilise que pour les raisons indiquées ci-dessous.</p>
    <h2>Ce que nous recueillons, et pourquoi</h2>
    <ul>
      <li><b>Adhésion</b> : nom, prénom, adresse e-mail, et selon votre choix adresse postale et téléphone, formule et montant de la cotisation, date. Pour tenir la liste des adhérents, vous informer de la vie de l’association et vous convoquer à l’assemblée générale (base légale : contrat d’adhésion). Conservées le temps de l’adhésion, puis trois ans pour pouvoir vous recontacter, et dix ans pour les pièces comptables.</li>
      <li><b>Bénévolat</b> : nom, coordonnées, centres d’intérêt et disponibilités que vous indiquez. Pour vous recontacter (base légale : votre demande). Conservées deux ans au plus sans suite de votre part.</li>
      <li><b>Messages</b> (page Contact) : nom, e-mail, message. Pour vous répondre. Conservés deux ans au plus.</li>
      <li><b>Newsletter « Ce jour-là »</b> : adresse e-mail, avec double confirmation. Désinscription en un clic dans chaque envoi ; l’adresse est alors effacée.</li>
    </ul>
    <h2>Paiements en ligne</h2>
    <?php if ($online): ?>
      <p>Les cotisations payées en ligne le sont sur les pages sécurisées de Stripe (carte bancaire) ou de PayPal. L’association ne voit jamais vos coordonnées bancaires ; elle reçoit seulement la confirmation du paiement et sa référence.</p>
    <?php else: ?>
      <p>L’adhésion en ligne par carte bancaire n’est pas encore ouverte : la cotisation se règle par chèque ou en main propre. Les dons en ligne, sur le musée, passent par Stripe ou PayPal, qui traitent seuls vos coordonnées bancaires.</p>
    <?php endif; ?>
    <h2>Qui y a accès</h2>
    <p>Seuls les membres du bureau chargés du suivi des adhésions, des bénévoles et des messages, dans le back-office protégé de l’association. Le site est hébergé en France (voir les <a href="<?= e(Host::url('/mentions-legales/')) ?>">mentions légales</a>).</p>
    <h2>Mesure d’audience</h2>
    <p>Le site compte les pages vues sans cookie et sans conserver d’adresse IP ni d’identifiant : aucune donnée personnelle n’est concernée.</p>
    <h2>Vos droits</h2>
    <p>Vous pouvez demander l’accès à vos données, leur rectification ou leur effacement, ou vous opposer à leur utilisation<?php if ($privacy !== ''): ?> en écrivant à <a href="mailto:<?= e($privacy) ?>"><?= e($privacy) ?></a><?php endif; ?> ou depuis la page <a href="<?= e(Host::url('/contact/')) ?>">Contact</a>. En cas de difficulté, vous pouvez saisir la CNIL (cnil.fr).</p>
  </div>
</article>
