<?php /** @var array $post @var array $related */ ?>
<article class="section" style="padding-top:clamp(140px,18vh,190px)">
  <span class="glow glow--red" style="width:560px;height:560px;top:-240px;right:-180px;opacity:.25" aria-hidden="true"></span>
  <div class="container">
    <div class="article">
      <a class="link-arrow" href="<?= e(url('actualites')) ?>" style="font-size:.88rem">← Toutes les actualités</a>
      <div class="post-card__meta" style="margin:26px 0 14px">
        <span class="chip chip--red"><?= e($post['category'] ?? 'Marché') ?></span>
        <span><?= e(fr_date($post['published_at'] ?? '')) ?></span>
        <span>·</span>
        <span><?= e($post['author'] ?? '') ?></span>
      </div>
      <h1 class="h1"><?= e($post['title'] ?? '') ?></h1>
      <?php
      // Le chapô ne s'affiche que s'il n'est pas déjà le début de l'article
      // (les imports WordPress génèrent souvent un extrait redondant).
      $norm = static fn (string $t): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''));
      $ex = $norm((string) ($post['excerpt'] ?? ''));
      $body = $norm((string) ($post['body'] ?? ''));
      $showExcerpt = $ex !== '' && !str_starts_with($body, mb_substr($ex, 0, 45));
      ?>
      <?php if ($showExcerpt): ?>
        <p class="lead" style="margin-top:20px"><?= e($post['excerpt']) ?></p>
      <?php endif; ?>

      <div class="article__body"><?= $post['body'] ?? '' ?></div>

      <div class="card" style="margin-top:52px;text-align:center;padding:clamp(28px,4vw,44px)">
        <span class="eyebrow" style="justify-content:center">Et vous ?</span>
        <h2 class="h3" style="margin:14px 0 12px">Ce marché a besoin d’agents bien accompagnés.</h2>
        <p class="muted" style="max-width:52ch;margin-inline:auto">
          Suisse Immo recrute des agents commerciaux immobiliers indépendants en Bourgogne-Franche-Comté. Formation, outils et support inclus.
        </p>
        <a class="btn btn--lg btn--magnet" style="margin-top:24px" href="<?= e(url('candidater')) ?>" data-cta="article">
          Candidater en 2 minutes <?= icon('arrow') ?>
        </a>
      </div>
    </div>
  </div>
</article>

<?php if (!empty($related)): ?>
<section class="section section--tight">
  <div class="container">
    <h2 class="h3" style="margin-bottom:26px">À lire également</h2>
    <div class="post-grid">
      <?php foreach ($related as $p): ?>
        <article class="post-card" data-reveal>
          <div class="post-card__meta"><span><?= e(fr_date($p['published_at'] ?? '')) ?></span></div>
          <h3><a href="<?= e(url('actualites/' . ($p['slug'] ?? ''))) ?>"><?= e($p['title'] ?? '') ?></a></h3>
          <p><?= e(excerpt((string) ($p['excerpt'] ?: $p['body'] ?? ''), 120)) ?></p>
          <a class="link-arrow" href="<?= e(url('actualites/' . ($p['slug'] ?? ''))) ?>">Lire <?= icon('arrow') ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
