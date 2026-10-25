<?php
/** @var array|null $r avis réel  @var string $tag  @var string $who  @var string $variant ('home'|'page')  @var int $delay */
$variant ??= 'home';
$delay ??= 0;
$real = !empty($r);
$rating = $real ? max(1, min(5, (int) ($r['rating'] ?? 5))) : 5;
$tagLabel = $real ? ($r['tag'] ?? '') : ($tag ?? '');
?>
<figure class="rv rv--<?= e($variant) ?>"<?= $variant === 'home' ? ' data-reveal data-delay="' . (int) $delay . '"' : '' ?>>
  <div class="rv__top">
    <span class="rv__stars" role="img" aria-label="<?= $rating ?> étoiles sur 5"><?= str_repeat('★', $rating) ?><span class="rv__off"><?= str_repeat('★', 5 - $rating) ?></span></span>
    <?php if ($tagLabel !== ''): ?><span class="rv__tag"><?= e($tagLabel) ?></span><?php endif; ?>
  </div>
  <?php if ($real): ?>
    <blockquote class="rv__q"><?= tp($r['text'] ?? '') ?></blockquote>
  <?php else: ?>
    <blockquote class="rv__q rv__q--todo"><?= e($empty ?? ('Témoignage réel à fournir' . (!empty($who) ? ' : ' . $who : '') . '.')) ?></blockquote>
  <?php endif; ?>
  <figcaption class="rv__who">
    <span class="rv__av"><?php if ($real && !empty($r['photo'])): ?><?= img_tag($r['photo'], '', ['sizes' => '44px']) ?><?php elseif ($real && !empty($r['name'])): ?><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?><?php endif; ?></span>
    <span class="rv__id"><b><?= e($real ? ($r['name'] ?? '') : 'Prénom') ?></b><span><?= e($real ? ($r['activity'] ?? '') : 'Activité') ?></span></span>
  </figcaption>
</figure>
