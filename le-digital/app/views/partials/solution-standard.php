<?php
/** Bloc générique d'une solution (créée dans le back-office › Solutions). @var array $so @var bool $flip */
$dark = ($so['theme'] ?? 'clair') === 'sombre';
$flip ??= false;
$name = (string) $so['name'];
?>
<section class="sec <?= $dark ? 'sec--ink sol-dark' : 'sec--rose2' ?>" id="<?= e($so['id']) ?>">
  <?php $shot = slot($so['image'], 'Capture ' . $name, 'Capture d’écran ' . $name, ['sizes' => '(max-width: 600px) 90vw, 560px']); ?>
  <div class="wrap sol-grid<?= $flip ? ' sol-grid--flip' : '' ?><?= $shot === '' ? ' sol-grid--solo' : '' ?>">
    <?php if ($shot !== ''): ?><div class="sol-shot<?= $dark ? ' sol-shot--dark' : '' ?>" data-reveal="scale"><?= $shot ?></div><?php endif; ?>
    <div class="sol-txt sol-txt--20" data-reveal>
      <?php if (!empty($so['logo'])): ?><img class="sol-logo" src="<?= e($so['logo']) ?>" alt="<?= e($name) ?>" loading="lazy"><?php else: ?><span class="sol-name"><?= t($name) ?></span><?php endif; ?>
      <?php if (trim((string) $so['tagline']) !== ''): ?><h2 class="sol-tag<?= $dark ? '' : ' sol-tag--red' ?>"><?= t($so['tagline']) ?></h2><?php endif; ?>
      <?php if (trim((string) $so['text']) !== ''): ?><p class="sol-p2<?= $dark ? '' : ' sol-p2--ink' ?>"><?= tp($so['text']) ?></p><?php endif; ?>
      <?php if (!empty($so['strengths'])): ?><div class="chips"><?php foreach ($so['strengths'] as $st): ?><span class="<?= $dark ? 'chip-dk' : 'chip-lt' ?>"><?= t($st) ?></span><?php endforeach; ?></div><?php endif; ?>
      <div class="btns mt6">
        <?php if (trim((string) $so['cta']) !== ''): ?><a class="btn <?= $dark ? 'btn-white btn-hover-lime' : 'btn-red' ?> btn-sol"<?= link_attrs($so['url'] ?: '#') ?>><?= t($so['cta']) ?></a><?php endif; ?>
        <?php if (trim((string) $so['demo_cta']) !== ''): ?><a class="btn <?= $dark ? 'btn-ghost-dark' : 'btn-outline' ?> btn-sol2"<?= link_attrs($so['demo_url'] ?: '/on-prend-rdv') ?>><?= t($so['demo_cta']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
