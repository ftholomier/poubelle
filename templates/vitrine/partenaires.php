<?php
/** Partenaires. Variables : $p, $partners */
use App\Vitrine\Host;
use App\Vitrine\Pages;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'L’association', 'crumbs' => [[$p['title'], Host::url('/partenaires/')]]]) ?>
<section class="section">
  <div class="wrap">
    <?php if ($partners): ?>
      <?= \App\Core\View::partial('vitrine/partials/partners', ['partners' => $partners]) ?>
      <?php $withText = array_filter($partners, fn ($x) => !empty($x['text'])); ?>
      <?php if ($withText): ?>
        <div class="vpartner-list mt-40">
          <?php foreach ($withText as $pa): ?><div class="vbox"><h3 class="h-3"><?= e($pa['name']) ?></h3><?php if (!empty($pa['kind'])): ?><span class="eyebrow"><?= e($pa['kind']) ?></span><?php endif; ?><p class="mt-20"><?= e($pa['text']) ?></p></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="prose vnote"><?= Pages::rich($p['empty_text'] ?? '') ?></div>
    <?php endif; ?>
  </div>
</section>
<section class="section bg-sand bt" id="devenir-partenaire">
  <div class="wrap">
    <span class="eyebrow">Rejoignez-nous</span>
    <h2 class="h-section mt-20"><?= e($p['become_title'] ?? '') ?></h2>
    <div class="prose vlead-prose mt-20" style="max-width:70ch"><?= Pages::rich($p['become_text'] ?? '') ?></div>
    <div class="voffers mt-40">
      <?php foreach ($p['offers'] ?? [] as $o): ?><div class="voffer" data-reveal><h3><?= e($o['title']) ?></h3><p><?= e($o['text']) ?></p></div><?php endforeach; ?>
    </div>
    <a class="btn btn--navy mt-40" href="<?= e(Host::url('/contact/?objet=partenariat')) ?>">Proposer un partenariat</a>
  </div>
</section>
