<?php
/**
 * Page d'erreur (404 / 410 / 500).
 *
 * Une impasse sans issue fait repartir le visiteur : la page propose donc une
 * recherche et les quatre entrées principales du site.
 */
use App\Services\I18n;
use App\Support\Icon;
?>
<div class="container">
  <div class="page-head" data-reveal>
    <span class="tag tag-coral"><?= e((string) $code) ?></span>
    <h1 class="h1-sub" style="margin-top:14px"><?= e($title) ?></h1>
    <p><?= e($body) ?></p>

    <form class="searchbar searchbar-lg" method="get" action="<?= e(I18n::url('/offres')) ?>"
          role="search" style="margin-top:22px;max-width:520px">
      <label class="sr-only" for="err-q"><?= e(I18n::t('error.search_here')) ?></label>
      <span class="ico"><?= Icon::svg('search', 18, '#8A83A8', 2) ?></span>
      <input id="err-q" type="search" name="q" placeholder="<?= e(I18n::t('search.keyword')) ?>">
      <button type="submit" class="btn btn-coral btn-sm"><?= e(I18n::t('search.submit')) ?></button>
    </form>

    <h2 class="h3" style="margin-top:28px"><?= e(I18n::t('error.useful')) ?></h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
      <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/')) ?>"><?= e(I18n::t('error.home')) ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('nav.jobs')) ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/cv')) ?>"><?= e(I18n::t('nav.cv')) ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/employeurs')) ?>"><?= e(I18n::t('nav.employers')) ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/ressources')) ?>"><?= e(I18n::t('nav.resources')) ?></a>
    </div>
  </div>
</div>
