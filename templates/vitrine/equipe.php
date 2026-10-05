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
      <p class="tteam-hint"><span class="h-hover">Survolez une carte</span><span class="h-touch">Touchez une carte</span> pour découvrir la mission et l’anecdote de chacun.</p>
      <?= \App\Core\View::partial('partials/team-cards', ['team' => $team, 'theme' => 'asso']) ?>
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
