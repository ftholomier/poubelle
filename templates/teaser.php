<?php /** /teaser/ : la vidéo de présentation du musée. */ ?>
<section class="bg-navy">
  <div class="wrap section stack gap-20">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Vidéo teaser')) ?></span></nav>
    <span class="eyebrow"><?= e(t('Le musée en vidéo')) ?></span>
    <h1 class="h-section"><?= e(t('Un siècle de Lions, réuni dans un seul musée')) ?></h1>
    <p class="hteaser__text"><?= e(t('Deux minutes pour découvrir le musée : près d’un siècle de matchs, de joueurs et de souvenirs du FC Sochaux-Montbéliard.')) ?></p>
    <video class="hteaser__video" controls playsinline preload="metadata" poster="<?= e(teaser_src('jpg', !\App\Front\Seo::closed())) ?>" width="1920" height="1080" aria-label="<?= e(t('Teaser vidéo du musée')) ?>" style="max-width:1100px">
      <source src="<?= e(teaser_src('mp4', !\App\Front\Seo::closed())) ?>" type="video/mp4">
    </video>
  </div>
</section>
