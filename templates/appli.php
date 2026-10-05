<?php
/** « L'appli du musée » : installation (selon l'appareil, réglé par js/appli.js), avantages, notifications. */
$perks = [
    ['⤓', t('Sur l’écran d’accueil'), t('L’icône au blason, à côté de vos applis : le musée s’ouvre en plein écran, sans barre d’adresse.')],
    ['⚡', t('Plus rapide'), t('Styles, polices et photos déjà vus restent sur le téléphone : les pages s’affichent presque aussitôt.')],
    ['⌁', t('Même sans réseau'), t('Au stade, dans le train : les fiches déjà consultées restent lisibles hors connexion.')],
    ['☰', t('Raccourcis'), t('Un appui long sur l’icône : Rétro-Direct, les 100 moments, le quiz, la recherche.')],
];
?>
<section class="wrap appli">
  <div class="appli__hero">
    <div class="stack" style="gap:18px">
      <span class="eyebrow eyebrow--lg"><?= e(t('L’appli du musée')) ?></span>
      <h1 class="h-xl"><?= e(t('Le musée')) ?><br><span class="blue"><?= e(t('dans votre poche.')) ?></span></h1>
      <p class="lead"><?= e(t('Pas besoin de store : Sochaux Rétro s’installe directement depuis le site, gratuitement, en deux gestes.')) ?></p>
    </div>
    <div class="appli__card" data-appli>
      <img src="/assets/img/app/192.png" alt="" width="96" height="96">
      <!-- Déjà dans l'appli -->
      <div data-state="app" hidden>
        <h2 class="h-3"><?= e(t('Vous êtes dans l’appli')) ?></h2>
        <p><?= e(t('Le musée est installé sur cet appareil. Bonne visite !')) ?></p>
      </div>
      <!-- Android, ordinateur (Chrome, Edge…) -->
      <div data-state="prompt" hidden>
        <h2 class="h-3"><?= e(t('Installer l’appli')) ?></h2>
        <p><?= e(t('Un clic, et l’icône du musée rejoint votre écran d’accueil.')) ?></p>
        <button type="button" class="btn btn--yellow" data-install><?= e(t('Installer l’appli')) ?></button>
      </div>
      <!-- iPhone, iPad (Safari) -->
      <div data-state="ios" hidden>
        <h2 class="h-3"><?= e(t('Sur iPhone et iPad')) ?></h2>
        <ol class="appli__steps">
          <li><?= e(t('Ouvrez le musée dans Safari.')) ?></li>
          <li><?= t('Touchez le bouton Partager <span class="appli__ico" aria-hidden="true">⎙</span> en bas de l’écran.') ?></li>
          <li><?= e(t('Choisissez « Sur l’écran d’accueil », puis « Ajouter ».')) ?></li>
        </ol>
      </div>
      <!-- Autres navigateurs -->
      <div data-state="other">
        <h2 class="h-3"><?= e(t('Installer l’appli')) ?></h2>
        <p><?= e(t('Sur Android, ouvrez le musée dans Chrome : le menu ⋮ propose « Installer l’application ». Sur iPhone, utilisez Safari. Sur ordinateur, Chrome et Edge affichent une icône d’installation dans la barre d’adresse.')) ?></p>
      </div>
    </div>
  </div>
  <ul class="appli__perks">
    <?php foreach ($perks as [$ico, $title, $text]): ?>
      <li><span class="appli__perk-ico" aria-hidden="true"><?= e($ico) ?></span><h2 class="appli__perk-t"><?= e($title) ?></h2><p><?= e($text) ?></p></li>
    <?php endforeach; ?>
  </ul>
  <p class="appli__note"><?= e(t('L’appli ne demande aucun compte et ne collecte aucune donnée personnelle. Pour la retirer, supprimez l’icône comme n’importe quelle appli.')) ?></p>
</section>
