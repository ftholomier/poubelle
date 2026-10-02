<?php
use App\Core\Url;
use App\Core\View;
use App\Services\Blog;

/** @var array $items @var int $page @var int $pages @var ?string $cat @var string $base */
?>
<section class="page-head">
  <?= View::partial('front/partials/crumbs', ['crumbs' => array_values(array_filter([['Accueil', '/'], ['Blog', '/blog/'], $cat ? [Blog::CATEGORIES[$cat], $base] : null]))]) ?>
  <h1>Idées & conseils pour <span class="serif">réussir votre fête</span></h1>
  <p class="lead">Mariage, anniversaire, soirée d'entreprise : organisation, animations, budget, location de matériel… nos guides pratiques.</p>
  <div class="sub-links">
    <a class="chip chip-sm<?= $cat === null ? ' is-on' : '' ?>" href="/blog/">Tous</a>
    <?php foreach (Blog::CATEGORIES as $k => $l): ?><a class="chip chip-sm<?= $cat === $k ? ' is-on' : '' ?>" href="/blog/categorie/<?= e($k) ?>/"><?= e($l) ?></a><?php endforeach; ?>
  </div>
</section>
<section class="section section-tight">
  <div class="blog-grid">
    <?php foreach ($items as $a): $img = Blog::imageUrl($a['image'] ?: null, 'sm'); ?>
      <article class="post-card">
        <div class="ph"><?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy" width="640" height="400"><?php endif; ?></div>
        <div class="in">
          <span class="tag" style="align-self:flex-start"><?= e(Blog::CATEGORIES[$a['cat']] ?? 'Conseils') ?></span>
          <h2><a href="<?= e(Url::blog($a['slug'])) ?>"><?= e($a['title']) ?></a></h2>
          <p><?= e($a['excerpt']) ?></p>
          <span class="small muted mt-1"><?= e(date_fr((string) $a['published'])) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= View::partial('front/partials/pagination', ['page' => $page, 'pages' => $pages, 'link' => static fn (int $p) => $base . ($p > 1 ? '?page=' . $p : '')]) ?>
</section>
