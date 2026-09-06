<?php
/**
 * Layout des écrans non connectés (connexion, première configuration).
 *
 * @var string $slot
 * @var array|null $page
 * @var App\Core\Content $content
 */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php /* Même raison qu'au gabarit principal : le nom et le blason viennent
         du contenu. Cet écran-ci est le premier que voit un agent, et le
         premier qu'aurait vu la commune suivante avec le blason d'Angeot. */ ?>
<?php $siteBo = $content->load('site'); ?>
<?php $mairieBo = trim('Mairie ' . de_nom((string) ($siteBo['nom'] ?? ''))); ?>
<title><?= e($page['titre'] ?? 'Administration') ?> — <?= e($mairieBo) ?></title>
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="bo bo--centre">
<main class="bo-carte-acces">
  <img class="bo-carte-acces__logo"
       src="<?= asset((string) ($siteBo['logo']['clair'] ?? 'assets/img/logo/logo-clair.svg')) ?>"
       alt="<?= e($mairieBo) ?>">
  <?= $slot ?>
</main>
</body>
</html>
