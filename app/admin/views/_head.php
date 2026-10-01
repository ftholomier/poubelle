<?php
/** <head> commun du back-office. */

use App\Config;
use App\Content;
use App\Text;

$basePath = Config::basePath();
$siteName = (string) (Content::settings()['site']['name'] ?? 'Le Signal');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= Text::e(($title ?? 'Back-office') . ' — ' . $siteName) ?></title>
<link rel="icon" href="<?= Text::e($basePath) ?>/assets/img/lesignal-mark.svg" type="image/svg+xml">
<link rel="icon" href="<?= Text::e($basePath) ?>/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= Text::e($basePath) ?>/assets/css/fonts.css?v=<?= Text::e((string) @filemtime(Config::publicPath('assets/css/fonts.css'))) ?>">
<link rel="stylesheet" href="<?= Text::e($basePath) ?>/admin/assets/admin.css?v=<?= Text::e((string) @filemtime(Config::publicPath('admin/assets/admin.css'))) ?>">
</head>
<body>
