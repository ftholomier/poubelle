<?php
/** Quiz du club-house : le grand écran de l'animateur (page autonome, plein écran). Variables : $g, $key, $join, $qr */
$site = (string) \App\Core\Settings::get('general.site_name', 'Sochaux Rétro');
$i18n = [
    'lobby' => t('Rejoignez la partie !'), 'players' => t('{n} joueurs'), 'player' => t('1 joueur'), 'none' => t('En attente des joueurs…'),
    'start' => t('Lancer le quiz'), 'next' => t('Suivant'), 'close' => t('Afficher la réponse'), 'board' => t('Classement'),
    'podium' => t('Voir le podium'), 'qn' => t('Question {i} / {n}'), 'answered' => t('{a} / {n} réponses'),
    'ready' => t('Préparez-vous…'), 'top' => t('Classement après la question {i}'), 'end' => t('Le podium'),
    'pts' => t('{n} pts'), 'kick' => t('Retirer {p} de la partie ?'), 'finished' => t('Merci d’avoir joué !'),
    'kinds' => ['quiz' => t('Histoire du club'), 'score' => t('Le score'), 'year' => t('L’année'), 'opp' => t('L’adversaire'), 'scorer' => t('Le buteur')],
];
?><!DOCTYPE html>
<html lang="<?= e($g['lang']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('Quiz du club-house')) ?> · <?= e($g['code']) ?> | <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<link rel="stylesheet" href="<?= asset('css/quizlive.css') ?>">
</head>
<body class="qls">
<script type="application/json" id="qls-data"><?= json_encode(['code' => $g['code'], 'key' => $key, 'lang' => $g['lang'], 't' => $i18n], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<header class="qls__top">
  <img src="/assets/img/logo-sochaux-retro.png" alt="<?= e($site) ?>" width="62" height="70">
  <div class="qls__brand"><b><?= e(t('Quiz du club-house')) ?></b><span data-qls-progress></span></div>
  <div class="qls__code"><span><?= e(t('Code')) ?></span><b><?= e($g['code']) ?></b></div>
  <button type="button" class="qls__fs" data-qls-fs title="<?= e(t('Plein écran')) ?>" aria-label="<?= e(t('Plein écran')) ?>">⤢</button>
</header>
<main class="qls__stage" data-qls-stage>
  <section class="qls__lobby" data-qls-view="lobby" hidden>
    <div class="qls__qr"><?= $qr ?></div>
    <div class="qls__how">
      <h1><?= e(t('Rejoignez la partie !')) ?></h1>
      <ol>
        <li><?= e(t('Scannez le QR code, ou allez sur')) ?> <b><?= e(preg_replace('#^https?://#', '', strtok($join, '?'))) ?></b></li>
        <li><?= e(t('Saisissez le code')) ?> <b class="qls__big"><?= e($g['code']) ?></b></li>
        <li><?= e(t('Choisissez un pseudo… et regardez l’écran !')) ?></li>
      </ol>
      <p class="qls__count" data-qls-count></p>
      <ul class="qls__names" data-qls-names></ul>
    </div>
  </section>
  <section class="qls__question" data-qls-view="question" hidden>
    <p class="qls__kind" data-qls-kind></p>
    <h1 class="qls__q" data-qls-q></h1>
    <div class="qls__meta"><div class="qls__clock" data-qls-clock></div><div class="qls__answered" data-qls-answered></div></div>
    <ol class="qls__answers" data-qls-answers></ol>
    <p class="qls__fact" data-qls-fact hidden></p>
  </section>
  <section class="qls__board" data-qls-view="board" hidden>
    <h1 data-qls-board-t></h1>
    <ol class="qls__rank" data-qls-rank></ol>
  </section>
  <section class="qls__end" data-qls-view="end" hidden>
    <h1><?= e(t('Le podium')) ?></h1>
    <div class="qls__podium" data-qls-podium></div>
    <ol class="qls__rank qls__rank--rest" data-qls-rest start="4"></ol>
    <p class="qls__thanks"><?= e(t('Merci d’avoir joué !')) ?> <?= e(t('Toute l’histoire du FCSM sur')) ?> <b><?= e(preg_replace('#^https?://#', '', base_url())) ?></b></p>
  </section>
</main>
<footer class="qls__foot">
  <span class="qls__hint"><?= e(t('Barre d’espace : étape suivante')) ?></span>
  <button type="button" class="qbtn" data-qls-next><?= e(t('Lancer le quiz')) ?></button>
</footer>
<script src="<?= asset('js/quizlive-ecran.js') ?>" defer></script>
</body>
</html>
