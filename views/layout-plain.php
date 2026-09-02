<?php

use App\View;

/** Gabarit dépouillé : ni particules, ni animations — utile pour diagnostiquer. */

/** @var array<string,mixed> $site */
/** @var string $content */

$theme = $site['theme'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Diagnostic — <?= View::e($site['name'] ?? '') ?></title>
<?php /* La carte doit précéder tout module : elle indique au navigateur
             quelle version de chaque fichier charger. Sans elle, un point
             d'entrée à jour peut importer des dépendances restées en cache. */ ?>
    <script type="importmap"><?= View::importMap() ?></script>
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/admin.css')) ?>">
    <style>
        :root {
            --accent: <?= View::e($theme['accent'] ?? '#7b01f7') ?>;
            --accent-2: <?= View::e($theme['accent2'] ?? '#c001f7') ?>;
            --accent-3: <?= View::e($theme['accent3'] ?? '#25d5ff') ?>;
        }
    </style>
</head>
<body class="admin admin--diagnostic">
    <main class="admin__main"><?= $content ?></main>
</body>
</html>
