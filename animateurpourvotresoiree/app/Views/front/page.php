<?php /** @var array $page @var array $crumbs */ ?>
<article class="article">
  <?= App\Core\View::partial('front/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <h1><?= e($page['title']) ?></h1>
  <div class="prose mt-3"><?= $page['body'] ?></div>
  <p class="small muted mt-4">Dernière mise à jour : <?= e(date_fr((string) ($page['updated_at'] ?? date('c')))) ?></p>
</article>
