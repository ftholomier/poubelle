<?php
// Page de l'appli. Générée en PHP pour que chaque mise à jour du site soit vue immédiatement :
// les fichiers CSS/JS portent un numéro de version calculé à partir de leur date de modification,
// ce qui empêche tout cache (navigateur, service worker, hébergeur) de servir une ancienne version.

require __DIR__ . '/../app/bootstrap.php';

header('Cache-Control: no-cache, no-store, must-revalidate');

// Identité de l'agence (Paramètres) appliquée à l'interface dès le premier affichage
$theme = ['logo' => uploaded_logo_path() !== null, 'agence' => (string) $CONFIG['agence']];
header('Content-Type: text/html; charset=utf-8');

// Tous les modules JS (js/ et js/vues/) : ajouter un fichier suffit, il est versionné automatiquement
$modules = array_map(fn ($f) => substr($f, strlen(__DIR__) + 1), array_merge(glob(__DIR__ . '/js/*.js') ?: [], glob(__DIR__ . '/js/vues/*.js') ?: []));
$assets = array_merge(['css/app.css', 'css/apercu.css'], $modules);
$mtimes = array_map(fn ($f) => $f . (string) @filemtime(__DIR__ . "/$f"), $assets);
$v = substr(md5(implode('|', $mtimes)), 0, 10);
$url = fn ($f) => "$f?v=$v";

// Les imports entre modules JS (./api.js…) reçoivent aussi le numéro de version
$importmap = ['imports' => []];
foreach ($modules as $f) if ($f !== 'js/app.js') $importmap['imports']["./$f"] = './' . $url($f);

// Politique de sécurité du contenu : seuls nos scripts (et Leaflet sur cdnjs) s'exécutent ; les deux petits scripts
// en ligne sont autorisés par leur empreinte. Une donnée piégée dans un dossier ne peut donc pas lancer de code.
$jsTheme = 'window.THEME = ' . json_encode($theme, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';';
$jsImportmap = json_encode($importmap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
$empreinte = fn (string $js) => "'sha256-" . base64_encode(hash('sha256', $js, true)) . "'";
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com " . $empreinte($jsTheme) . ' ' . $empreinte($jsImportmap)
    . "; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: blob: https://*.tile.openstreetmap.org"
    . "; media-src 'self' blob: data:; connect-src 'self' blob: https://cdnjs.cloudflare.com https://*.tile.openstreetmap.org wss://generativelanguage.googleapis.com; font-src 'self' data:"
    . "; worker-src 'self'; manifest-src 'self'; frame-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Synapse · Visite Immo</title>
  <meta name="description" content="Enregistrez vos visites, l'IA rédige la fiche, l'annonce et les rapports.">
  <meta name="theme-color" content="#f4f1ea">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Visite Immo">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="icon-180.png">
  <link rel="stylesheet" href="<?= $url('css/app.css') ?>">
  <script><?= $jsTheme ?></script>
  <script type="importmap"><?= $jsImportmap ?></script>
</head>
<body>
  <div id="app"><div class="splash"><img src="img/synapse-icone.svg" alt=""></div></div>
  <script type="module" src="<?= $url('js/app.js') ?>"></script>
</body>
</html>
