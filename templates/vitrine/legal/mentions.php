<?php
/** Mentions légales du site de l'association. Variables : voir App\Vitrine\Pages::legal() */
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
    <h2>Éditeur</h2>
    <p>Le site <b><?= e(Host::host()) ?></b> est édité par <b><?= e($publisher) ?></b><?= $status !== '' ? ', ' . e($status) : '' ?>.</p>
    <?php if ($address): ?><div class="legal__card"><?= $address ?></div><?php endif; ?>
    <ul>
      <?php if ($registration !== ''): ?><li>Numéro RNA ou SIREN : <?= e($registration) ?></li><?php endif; ?>
      <?php if ($director !== ''): ?><li>Directeur ou directrice de la publication : <?= e($director) ?></li><?php endif; ?>
      <?php if ($email !== ''): ?><li>Contact : <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
      <?php if ($phone !== ''): ?><li>Téléphone : <?= e($phone) ?></li><?php endif; ?>
      <li>Formulaire de contact : <a href="<?= e(Host::url('/contact/')) ?>"><?= e(Host::abs('/contact/')) ?></a></li>
    </ul>
    <h2>Hébergement</h2>
    <div class="legal__card"><?= $host ?: '<p>—</p>' ?></div>
    <h2>Développement</h2>
    <p>Le développement du site a été effectué par <b>Frédéric Tholomier | <a href="https://le-digital.com/" rel="noopener" target="_blank">LE-DIGITAL.com</a></b>.</p>
    <h2>Objet du site</h2>
    <p>Ce site présente l’association <?= e(Site::name()) ?>, ses actions, son agenda et les moyens de la soutenir (adhésion, bénévolat, dons, archives). Le musée en ligne de l’association est publié à l’adresse <a href="<?= e(Host::museum('/')) ?>"><?= e(Host::museumHost()) ?></a>, qui a ses propres mentions légales.</p>
    <h2>Propriété intellectuelle</h2>
    <p>Les textes et la mise en page du site sont protégés par le droit d’auteur. Les photographies et documents restent la propriété de leurs auteurs ou ayants droit, crédités lorsqu’ils sont connus. Toute reproduction nécessite une autorisation préalable.</p>
    <p>Les noms, écussons et marques du FC Sochaux-Montbéliard et des autres clubs cités appartiennent à leurs titulaires respectifs et ne sont utilisés qu’à des fins historiques et d’information. L’association n’est pas le club.</p>
    <p>Si vous détenez les droits d’un document publié et souhaitez qu’il soit crédité autrement ou retiré, <a href="<?= e(Host::url('/contact/')) ?>">écrivez-nous</a> : nous agirons rapidement.</p>
    <h2>Responsabilité</h2>
    <p>L’association apporte le plus grand soin aux informations publiées ; des erreurs peuvent toutefois subsister, n’hésitez pas à nous les signaler. Les liens vers d’autres sites n’engagent pas la responsabilité de l’éditeur.</p>
    <h2>Données personnelles et cookies</h2>
    <p>Voir la <a href="<?= e(Host::url('/confidentialite/')) ?>">politique de confidentialité</a> et la page <a href="<?= e(Host::url('/cookies/')) ?>">cookies</a>.</p>
    <?php if ($extra): ?><?= $extra ?><?php endif; ?>
    <h2>Droit applicable</h2>
    <p>Ce site est soumis au droit français.</p>
  </div>
</article>
