<?php /** @var array $posts */ ?>
<?php partial('page-hero', [
    'eyebrow' => 'Actualités',
    'title' => 'Le marché immobilier, décrypté.',
    'lead' => 'Taux, DPE, prix, volumes : ce que les mouvements du marché changent concrètement pour un agent commercial immobilier.',
]); ?>

<section class="section" style="padding-top:0">
  <div class="container">
    <?php if (!$posts): ?>
      <p class="lead">Aucun article publié pour le moment.</p>
    <?php else: ?>
      <?php $first = $posts[0]; ?>
      <article class="card" data-reveal style="padding:clamp(28px,4vw,52px);margin-bottom:28px">
        <div class="post-card__meta" style="margin-bottom:16px">
          <span class="chip chip--red"><?= e($first['category'] ?? 'Marché') ?></span>
          <span><?= e(fr_date($first['published_at'] ?? '')) ?></span>
        </div>
        <h2 class="h2" style="max-width:20ch"><?= e($first['title'] ?? '') ?></h2>
        <p class="lead" style="margin-top:16px"><?= e($first['excerpt'] ?? '') ?></p>
        <a class="btn" style="margin-top:26px" href="<?= e(url('actualites/' . ($first['slug'] ?? ''))) ?>">Lire l’analyse <?= icon('arrow') ?></a>
      </article>

      <div class="post-grid">
        <?php foreach (array_slice($posts, 1) as $i => $p): ?>
          <article class="post-card" data-reveal style="--d:<?= min($i * 60, 320) ?>ms">
            <div class="post-card__meta">
              <span><?= e(fr_date($p['published_at'] ?? '')) ?></span>
              <span>·</span>
              <span><?= e($p['category'] ?? '') ?></span>
            </div>
            <h3><a href="<?= e(url('actualites/' . ($p['slug'] ?? ''))) ?>"><?= e($p['title'] ?? '') ?></a></h3>
            <p><?= e(excerpt((string) ($p['excerpt'] ?: $p['body'] ?? ''), 130)) ?></p>
            <a class="link-arrow" href="<?= e(url('actualites/' . ($p['slug'] ?? ''))) ?>">Lire <?= icon('arrow') ?></a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php partial('cta-final'); ?>
