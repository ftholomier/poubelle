<?php
/** <head> commun du back-office. */

use App\Config;
use App\Text;

$basePath = Config::basePath();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= Text::e(($title ?? 'Back-office') . ' — Le iOiO') ?></title>
<link rel="icon" href="<?= Text::e($basePath) ?>/assets/img/ioio-mark.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Text::e($basePath) ?>/admin/assets/admin.css?v=<?= Text::e((string) @filemtime(Config::publicPath('admin/assets/admin.css'))) ?>">
</head>
<body>
