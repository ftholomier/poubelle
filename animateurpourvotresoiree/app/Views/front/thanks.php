<?php /** @var string $title @var string $text @var ?array $pro @var ?array $cta */ ?>
<section class="error-page" style="padding-top:70px">
  <div style="font-size:84px;line-height:1"><?= e($emoji ?? '🎉') ?></div>
  <h1 class="h2 mt-3"><?= e($title) ?></h1>
  <p class="lead" style="margin-left:auto;margin-right:auto"><?= e($text) ?></p>
  <div class="row-wrap mt-4" style="justify-content:center">
    <?php if (!empty($cta)): ?>
      <a class="btn btn-coral" href="<?= e($cta[0]) ?>"><?= e($cta[1]) ?></a>
      <a class="btn" href="/">Retour à l'accueil</a>
    <?php else: ?>
      <?php if (!empty($pro) && App\Services\Pros::isPublished($pro)): ?><a class="btn" href="<?= e(App\Core\Url::pro($pro)) ?>">Retour à la fiche</a><?php endif; ?>
      <a class="btn btn-ink" href="/recherche/">Découvrir d'autres pros</a>
      <a class="btn btn-coral" href="/devis/">Demander d'autres devis</a>
    <?php endif; ?>
  </div>
</section>
