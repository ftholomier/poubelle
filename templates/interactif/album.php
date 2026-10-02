<?php
/** Album du centenaire (maquette « Album »). Variables : $cards, $total */
$tiers = ['legende' => t('Légende'), 'classique' => t('Classique'), 'actuel' => t('Actuel')];
?>
<section class="wrap ahead2">
  <div class="stack" style="gap:16px">
    <span class="eyebrow eyebrow--lg"><?= e(t("L'album du centenaire")) ?></span>
    <h1 class="h-xl ahead2__title"><?= e(t('Collectionnez')) ?><br><?= e(t('les Lions')) ?></h1>
    <p class="lead" style="max-width:44ch"><?= e(t("Chaque fiche visitée débloque sa carte. Un sans-faute au quiz en offre une rare. Pas de compte : votre album est gardé dans ce navigateur.")) ?></p>
  </div>
  <div class="abox" data-album-box>
    <div class="between" style="align-items:baseline;gap:12px"><span class="abox__n"><b data-album-owned>0</b><span> / <?= (int) $total ?></span></span><span class="abox__l"><?= e(t('cartes')) ?></span></div>
    <div class="abox__bar"><span data-album-bar style="width:0%"></span></div>
    <div class="abox__tiers">
      <?php foreach ($tiers as $k => $label): $n = count(array_filter($cards, fn ($c) => $c['tier'] === $k)); if (!$n) continue; ?>
        <span class="tierchip tierchip--<?= e($k) ?>"><?= e($label) ?> · <b data-album-tier="<?= e($k) ?>">0</b> / <?= $n ?></span>
      <?php endforeach; ?>
    </div>
    <div class="row" style="gap:10px;flex-wrap:wrap">
      <a class="btn btn--yellow btn--sm" href="<?= e(url('/interactif/quiz/')) ?>"><?= e(t('Gagner une carte rare')) ?></a>
      <button type="button" class="btn btn--ghost-light btn--sm" data-album-share><?= e(t('Partager mon album')) ?></button>
    </div>
  </div>
</section>
<div class="wrap" style="padding-bottom:18px">
  <div class="mtools__chips" data-album-filter>
    <button type="button" class="mchip is-on" data-f="all"><?= e(t('Toutes')) ?></button>
    <button type="button" class="mchip" data-f="own"><?= e(t('Obtenues')) ?></button>
    <button type="button" class="mchip" data-f="todo"><?= e(t('À trouver')) ?></button>
  </div>
</div>
<div class="wrap agrid" data-album data-total="<?= (int) $total ?>">
  <?php foreach ($cards as $c): ?>
  <div class="acard" data-card="<?= (int) $c['id'] ?>" data-tier="<?= e($c['tier']) ?>">
    <div class="acard__in">
      <div class="acard__face acard__back">
        <img src="/assets/img/logo-sochaux-retro.png" alt="" width="57" height="64" loading="lazy">
        <span class="acard__no">N° <?= e(str_pad((string) $c['n'], 3, '0', STR_PAD_LEFT)) ?></span>
        <span class="acard__hint"><?= e($c['tier'] === 'legende' ? t('Visitez sa fiche ou réussissez le quiz') : t('Visitez sa fiche')) ?></span>
        <a class="acard__go" href="<?= e($c['href']) ?>"><?= e(t('Visiter la fiche')) ?></a>
      </div>
      <a class="acard__face acard__front acard__front--<?= e($c['tier']) ?>" href="<?= e($c['href']) ?>" tabindex="-1">
        <span class="between"><b class="acard__num">N° <?= e(str_pad((string) $c['n'], 3, '0', STR_PAD_LEFT)) ?></b><span class="acard__tier"><?= e($tiers[$c['tier']] ?? '') ?></span></span>
        <span class="acard__photo"><?php if ($c['image']): ?><img src="<?= e($c['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
        <span class="acard__name"><b><?= e($c['name']) ?></b><small><?= e($c['pos']) ?></small></span>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php for ($i = count($cards); $i < $total; $i++): ?>
  <div class="acard acard--mystery" data-mystery>
    <div class="acard__in"><div class="acard__face acard__back"><img src="/assets/img/logo-sochaux-retro.png" alt="" width="57" height="64" loading="lazy"><span class="acard__no">N° <?= e(str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)) ?></span><span class="acard__hint"><?= e(t('Carte mystère · à débloquer au fil du centenaire')) ?></span></div></div>
  </div>
  <?php endfor; ?>
</div>
