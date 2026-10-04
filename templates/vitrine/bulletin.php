<?php
/** Bulletin d'adhésion à imprimer (A4). Variables : $tariffs, $address, $p */
use App\Vitrine\Site;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Bulletin d’adhésion · <?= e(Site::name()) ?></title>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
</head>
<body class="vbulletin">
<div class="vbulletin__tools"><button type="button" class="vbulletin__print" data-print>Imprimer</button></div>
<main class="vbulletin__page">
  <header class="vbulletin__head">
    <img src="/assets/img/logo-sochaux-retro.png" alt="" width="70" height="79">
    <div><h1>Bulletin d’adhésion <?= date('Y') ?></h1><p><?= e(Site::name()) ?> · <?= e(Site::tagline()) ?></p></div>
  </header>
  <section>
    <h2>Formule choisie</h2>
    <ul class="vbulletin__choices">
      <?php foreach ($tariffs as $t): ?><li><span class="vbulletin__box"></span><?= e($t['label']) ?> : <b><?= !empty($t['free']) ? 'à partir de ' : '' ?><?= (int) $t['amount'] ?> €</b><?php if (!empty($t['free'])): ?> (montant : ………… €)<?php endif; ?></li><?php endforeach; ?>
    </ul>
  </section>
  <section>
    <h2>Vos coordonnées</h2>
    <div class="vbulletin__lines">
      <p>Prénom : …………………………………………… Nom : ……………………………………………………</p>
      <p>Adresse : …………………………………………………………………………………………………</p>
      <p>Code postal : ……………… Ville : …………………………………………………………………</p>
      <p>E-mail : ………………………………………………… Téléphone : ……………………………………</p>
      <p>Adhésion famille, autres membres : ……………………………………………………………………</p>
    </div>
  </section>
  <section>
    <h2>Règlement</h2>
    <p>Par chèque à l’ordre de <b><?= e(Site::name()) ?></b>, à envoyer avec ce bulletin<?= $address ? ' à :' : ', ou à remettre à un membre du bureau.' ?></p>
    <?php if ($address): ?><div class="vbulletin__addr"><?= $address ?></div><?php endif; ?>
    <p><span class="vbulletin__box"></span> Je souhaite recevoir la newsletter « Ce jour-là » par e-mail.</p>
    <p><span class="vbulletin__box"></span> J’accepte que mes coordonnées soient utilisées pour la gestion de mon adhésion et la vie de l’association. Elles ne sont jamais cédées ni vendues.</p>
    <p class="vbulletin__sign">Date : ……………………………… Signature :</p>
  </section>
  <footer class="vbulletin__foot"><?= e(\App\Vitrine\Host::host()) ?> · <?= e((string) ($p['validity'] ?? '')) ?></footer>
</main>
<script src="<?= asset('js/vitrine.js') ?>" defer></script>
</body>
</html>
