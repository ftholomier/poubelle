<?php /** En-tête de page interne. */ ?>
<section class="hero" style="min-height:auto;padding-block:clamp(150px,20vh,210px) clamp(40px,6vw,70px)">
  <div class="hero__bg" aria-hidden="true">
    <span class="glow glow--red" style="width:620px;height:620px;top:-260px;right:-160px"></span>
    <span class="glow glow--amber" style="width:400px;height:400px;bottom:-200px;left:-120px;opacity:.35"></span>
  </div>
  <div class="hero__grid" aria-hidden="true"></div>
  <div class="container" style="position:relative;z-index:2;max-width:900px">
    <?php if (!empty($eyebrow)): ?><span class="eyebrow" data-reveal><?= e($eyebrow) ?></span><?php endif; ?>
    <h1 class="h1" style="margin:18px 0 20px"><span class="split-word"><i><?= e($title) ?></i></span></h1>
    <?php if (!empty($lead)): ?><p class="lead" data-reveal style="--d:120ms;font-size:clamp(1.05rem,1.7vw,1.3rem)"><?= e($lead) ?></p><?php endif; ?>
    <?php if (!empty($actions)): ?>
      <div class="cluster" style="margin-top:30px" data-reveal>
        <a class="btn btn--lg btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="<?= e($ctaSource ?? 'page-hero') ?>">Candidater <?= icon('arrow') ?></a>
        <a class="btn btn--ghost btn--lg" href="<?= e(url('contact')) ?>">Poser une question</a>
      </div>
    <?php endif; ?>
  </div>
</section>
