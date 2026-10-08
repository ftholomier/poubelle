<?php
/** Mentions légales. Variables : réglages « legal » (voir App\Front\Legal) */
use App\Services\I18n;

$en = I18n::isEn();
?>
<article class="legal wrap">
  <header class="legal__head">
    <span class="eyebrow eyebrow--lg"><?= e($site) ?></span>
    <h1 class="h-xl legal__title"><?= e($title) ?></h1>
    <p class="legal__updated"><?= e($en ? 'Last updated: ' : 'Mise à jour : ') . e(date_fr($updated)) ?></p>
  </header>
  <div class="legal__body prose">
    <?php if ($en): ?>
      <h2>Publisher</h2>
      <p>The website <b><?= e(preg_replace('#^https?://#', '', $base)) ?></b> is published by <b><?= e($publisher) ?></b><?= $status !== '' ? ', ' . e($status) : '' ?>.</p>
      <?php if ($address): ?><div class="legal__card"><?= $address ?></div><?php endif; ?>
      <ul>
        <?php if ($registration !== ''): ?><li>Registration number (RNA / SIREN): <?= e($registration) ?></li><?php endif; ?>
        <?php if ($director !== ''): ?><li>Publication director: <?= e($director) ?></li><?php endif; ?>
        <?php if ($email !== ''): ?><li>Contact: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li>Phone: <?= e($phone) ?></li><?php endif; ?>
        <li>Contact form: <a href="<?= e(url('/contact/')) ?>"><?= e($base . url('/contact/')) ?></a></li>
      </ul>
      <h2>Hosting</h2>
      <div class="legal__card"><?= $host ?: '<p>—</p>' ?></div>
      <h2>Development</h2>
      <p>The website was developed by <b>Frédéric Tholomier | <a href="https://le-digital.com/" rel="noopener" target="_blank">LE-DIGITAL.com</a></b>.</p>
      <h2>Purpose of the site</h2>
      <p><?= e($site) ?> is an online museum dedicated to the history of FC Sochaux-Montbéliard since 1928: matches, players, coaches, executives, supporters, stadiums and symbols. Its content is written and checked by volunteer historians.</p>
      <h2>Intellectual property</h2>
      <p>The texts, compilations, databases and layout of the site are protected by copyright. Photographs, posters, programmes, press cuttings and other documents remain the property of their authors or rights holders, credited whenever they are known. Any reproduction requires prior permission.</p>
      <p>The names, badges and trademarks of FC Sochaux-Montbéliard and of the other clubs mentioned belong to their respective owners and are used for historical and informational purposes only.</p>
      <p>If you hold the rights to a document published on the site and wish it to be credited differently or removed, please <a href="<?= e(url('/contact/') . '?objet=erreur') ?>">contact us</a>: we will act promptly.</p>
      <h2>Contributions</h2>
      <p>Documents and testimonies sent through the “Contribute” page are published only after review by the team, with the credit chosen by the contributor, who confirms holding the necessary rights.</p>
      <h2>Liability</h2>
      <p>The team takes great care over the accuracy of the information published. Errors may remain, however: do not hesitate to report them. Answers from the AI assistant are generated automatically and may contain mistakes; the museum's records remain the reference. Links to third-party sites do not engage the responsibility of the publisher.</p>
      <h2>Personal data and cookies</h2>
      <p>See the <a href="<?= e(url('/confidentialite/')) ?>">privacy policy</a> and the <a href="<?= e(url('/cookies/')) ?>">cookies page</a>.</p>
      <h2>Applicable law</h2>
      <p>This site is governed by French law.</p>
    <?php else: ?>
      <h2>Éditeur</h2>
      <p>Le site <b><?= e(preg_replace('#^https?://#', '', $base)) ?></b> est édité par <b><?= e($publisher) ?></b><?= $status !== '' ? ', ' . e($status) : '' ?>.</p>
      <?php if ($address): ?><div class="legal__card"><?= $address ?></div><?php endif; ?>
      <ul>
        <?php if ($registration !== ''): ?><li>Numéro RNA ou SIREN : <?= e($registration) ?></li><?php endif; ?>
        <?php if ($director !== ''): ?><li>Directeur ou directrice de la publication : <?= e($director) ?></li><?php endif; ?>
        <?php if ($email !== ''): ?><li>Contact : <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li>Téléphone : <?= e($phone) ?></li><?php endif; ?>
        <li>Formulaire de contact : <a href="<?= e(url('/contact/')) ?>"><?= e($base . url('/contact/')) ?></a></li>
      </ul>
      <h2>Hébergement</h2>
      <div class="legal__card"><?= $host ?: '<p>—</p>' ?></div>
      <h2>Développement</h2>
      <p>Le développement du site a été effectué par <b>Frédéric Tholomier | <a href="https://le-digital.com/" rel="noopener" target="_blank">LE-DIGITAL.com</a></b>.</p>
      <h2>Objet du site</h2>
      <p><?= e($site) ?> est un musée en ligne consacré à l’histoire du FC Sochaux-Montbéliard depuis 1928 : matchs, joueurs, entraîneurs, dirigeants, supporters, stades et symboles. Ses contenus sont rédigés et vérifiés par des historiens bénévoles.</p>
      <h2>Propriété intellectuelle</h2>
      <p>Les textes, compilations, bases de données et la mise en page du site sont protégés par le droit d’auteur. Les photographies, affiches, programmes, coupures de presse et autres documents restent la propriété de leurs auteurs ou ayants droit, crédités lorsqu’ils sont connus. Toute reproduction nécessite une autorisation préalable.</p>
      <p><b>Fouille de textes et de données, intelligence artificielle :</b> conformément à l’article L. 122-5-3 du Code de la propriété intellectuelle (directive européenne 2019/790), l’éditeur s’oppose à toute fouille de textes et de données des contenus du site, notamment pour entraîner ou alimenter des systèmes d’intelligence artificielle. L’aspiration automatisée du site (robots, scripts, logiciels de copie) est interdite sans accord écrit préalable et techniquement bloquée.</p>
      <p>Les noms, écussons et marques du FC Sochaux-Montbéliard et des autres clubs cités appartiennent à leurs titulaires respectifs et ne sont utilisés qu’à des fins historiques et d’information.</p>
      <p>Si vous détenez les droits d’un document publié et souhaitez qu’il soit crédité autrement ou retiré, <a href="<?= e(url('/contact/') . '?objet=erreur') ?>">écrivez-nous</a> : nous agirons rapidement.</p>
      <h2>Contributions</h2>
      <p>Les documents et témoignages envoyés depuis la page « Contribuer » ne sont publiés qu’après relecture par l’équipe, avec le crédit choisi par le contributeur, qui certifie disposer des droits nécessaires.</p>
      <h2>Responsabilité</h2>
      <p>L’équipe veille avec soin à l’exactitude des informations publiées. Des erreurs peuvent toutefois subsister : n’hésitez pas à les signaler. Les réponses de l’assistant IA sont générées automatiquement et peuvent comporter des erreurs ; les fiches du musée font foi. Les liens vers des sites tiers n’engagent pas la responsabilité de l’éditeur.</p>
      <h2>Données personnelles et cookies</h2>
      <p>Consultez la <a href="<?= e(url('/confidentialite/')) ?>">politique de confidentialité</a> et la <a href="<?= e(url('/cookies/')) ?>">page cookies</a>.</p>
      <h2>Droit applicable</h2>
      <p>Le présent site est soumis au droit français.</p>
    <?php endif; ?>
    <?= $extra ?>
  </div>
</article>
