<?php
use App\Core\Url;
use App\Core\View;
use App\Services\Ads;
use App\Services\Blog;

/** @var array $a @var array $related @var array $crumbs */
$img = Blog::imageUrl($a['image'] ?? null, 'lg');
?>
<article class="article">
  <?= View::partial('front/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <span class="tag mt-3" style="display:inline-flex"><?= e(Blog::CATEGORIES[$a['category'] ?? ''] ?? 'Conseils') ?></span>
  <h1><?= e($a['title']) ?></h1>
  <div class="article-meta">
    <span><?= e(date_fr((string) $a['published_at'])) ?></span>
    <span>· <?= (int) ($a['reading_time'] ?? 3) ?> min de lecture</span>
    <?php if (!empty($a['sponsored'])): ?><span class="soft-tag">Article partenaire</span><?php endif; ?>
    <button type="button" class="link" data-share="<?= e(Url::abs(Url::blog($a['slug']))) ?>" style="color:var(--ink)"><?= icon('share', 14) ?> Partager</button>
  </div>
  <?php if ($img): ?><div class="cover"><img src="<?= e($img) ?>" alt="<?= e($a['image_alt'] ?? $a['title']) ?>" width="1400" height="800" fetchpriority="high"></div><?php endif; ?>
  <div class="prose"><?= Blog::withInlineAd((string) $a['body'], Ads::slot('blog_inline', 'ad-inline')) ?></div>
  <div class="box box-ink mt-4">
    <h2 style="color:var(--cream)">Besoin d'un pro pour votre événement ?</h2>
    <p style="color:var(--soft)">Décrivez votre fête en une minute : les professionnels de votre secteur vous répondent gratuitement.</p>
    <div class="row-wrap mt-2"><a class="btn btn-yellow" href="/devis/">Demander des devis</a><a class="btn btn-ghost" style="color:var(--cream);border-color:var(--cream)" href="/recherche/">Trouver un pro</a></div>
  </div>
</article>
<?= Ads::slot('blog_bottom') ?>
<?php if ($related): ?>
<section class="section section-tight">
  <h2 class="h2">À lire <span class="serif">aussi</span></h2>
  <div class="blog-grid">
    <?php foreach ($related as $r): $ri = Blog::imageUrl($r['image'] ?: null, 'sm'); ?>
      <article class="post-card"><div class="ph"><?php if ($ri): ?><img src="<?= e($ri) ?>" alt="" loading="lazy"><?php endif; ?></div><div class="in"><h3><a href="<?= e(Url::blog($r['slug'])) ?>"><?= e($r['title']) ?></a></h3><p><?= e($r['excerpt']) ?></p></div></article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
