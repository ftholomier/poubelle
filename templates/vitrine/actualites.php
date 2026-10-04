<?php
/** Liste des actualités. Variables : $p, $items, $n (page), $pages */
use App\Vitrine\Host;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'La vie de l’association', 'crumbs' => [[$p['title'], Host::url('/actualites/')]]]) ?>
<section class="section">
  <div class="wrap">
    <?php if ($items): ?>
      <div class="vnews-list">
        <?php foreach ($items as $i => $it): ?><?= \App\Core\View::partial('vitrine/partials/news-card', ['n' => $it, 'big' => $i === 0 && $n === 1]) ?><?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="vpager" aria-label="Pages">
          <?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= e(Host::url('/actualites/') . ($i > 1 ? '?page=' . $i : '')) ?>"<?= $i === $n ? ' class="is-on" aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <p class="lead"><?= e($p['empty_text'] ?? '') ?></p>
    <?php endif; ?>
  </div>
</section>
