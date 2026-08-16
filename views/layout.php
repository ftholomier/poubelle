<?php
/**
 * Gabarit de base. Le design définitif (noir & or, inspiré de la référence)
 * sera posé ici une fois la maquette de référence analysée.
 *
 * @var App\Core\View $view
 * @var array $config
 * @var App\Core\Content $content
 * @var string $slot
 * @var array|null $page
 */
$site  = $content->load('site');
$titre = $page['titre'] ?? $site['nom'];
$desc  = $page['meta']['description'] ?? $site['accroche'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> — <?= e($site['nom']) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
</head>
<body>
<?= $view->partial('header', ['site' => $site]) ?>
<main id="contenu"><?= $slot ?></main>
<?= $view->partial('footer', ['site' => $site]) ?>
</body>
</html>
