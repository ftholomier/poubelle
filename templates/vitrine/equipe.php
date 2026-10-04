<?php
/** L'équipe. Variables : $p, $team (membres nommés), $poles */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;

$byPole = [];
foreach ($team as $m) {
    $byPole[$m['pole'] ?? ''][] = $m;
}
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'L’association', 'crumbs' => [['L’association', Host::url('/association/')], [$p['title'], Host::url('/association/equipe/')]]]) ?>

<section class="section">
  <div class="wrap">
    <?php if ($team): ?>
      <div class="vteam">
        <?php foreach ($team as $m): ?>
          <div class="vmember" data-reveal>
            <div class="vmember__ph"><?php if (Content::hasImage($m['photo'] ?? null)): ?><img src="<?= e(img($m['photo'], 480)) ?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true"><?= e(\App\Admin\Base::initials((string) $m['name'])) ?></span><?php endif; ?></div>
            <h3><?= e($m['name']) ?></h3>
            <span class="vmember__role"><?= e($m['role']) ?></span>
            <?php if (!empty($m['text'])): ?><p><?= e($m['text']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="prose vnote"><?= Pages::rich($p['empty_text'] ?? '') ?></div>
    <?php endif; ?>
  </div>
</section>

<?php if ($poles): ?>
<section class="section bg-sand bt">
  <div class="wrap">
    <h2 class="h-section"><?= e($p['poles_title'] ?? '') ?></h2>
    <div class="vpoles mt-40">
      <?php foreach ($poles as $po): ?>
        <div class="vpole" data-reveal>
          <span class="vpole__i" aria-hidden="true"><?= e($po['icon'] ?? '★') ?></span>
          <h3><?= e($po['title']) ?></h3>
          <p><?= e($po['text']) ?></p>
          <?php foreach ($byPole[$po['key'] ?? ''] ?? [] as $m): ?><span class="vpole__who"><?= e($m['name']) ?> · <?= e($m['role']) ?></span><?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="vcta-band">
  <div class="wrap vcta-band__in">
    <div><h2 class="h-2"><?= e($p['join_title'] ?? '') ?></h2><p><?= e($p['join_text'] ?? '') ?></p></div>
    <a class="btn btn--navy" href="<?= e(Host::url('/nous-soutenir/benevolat/')) ?>">Devenir bénévole</a>
  </div>
</section>
