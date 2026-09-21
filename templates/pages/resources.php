<?php
/** Index des pages éditoriales. @var array $pages */
use App\Services\I18n;
?>
<div class="container">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e(I18n::t('nav.resources')) ?></h1>
    <p><?= e(I18n::t('home.lede')) ?></p>
  </div>

  <?php if ($pages === []): ?>
    <div class="card empty" style="margin-top:26px">
      <h3><?= e(I18n::t('search.none')) ?></h3>
      <p><?= e(I18n::t('search.none_note')) ?></p>
    </div>
  <?php else: ?>
    <div class="grid-jobs" style="margin-top:26px">
      <?php foreach ($pages as $item): ?>
        <a class="card card-link" href="<?= e(I18n::url('/' . $item['slug'])) ?>" data-reveal>
          <h3><?= e($item['title']) ?></h3>
          <p style="color:var(--text);font-size:14.5px;margin:10px 0 0"><?= e($item['excerpt']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
