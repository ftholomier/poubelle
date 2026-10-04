<?php
/** Logos des partenaires. Variables : $partners */
use App\Vitrine\Content;
?>
<div class="vpartners">
  <?php foreach ($partners as $pa): $url = trim((string) ($pa['url'] ?? '')); $tag = preg_match('#^https?://#i', $url) ? 'a' : 'span'; ?>
    <<?= $tag ?> class="vpartner"<?= $tag === 'a' ? ' href="' . e($url) . '" target="_blank" rel="noopener"' : '' ?> title="<?= e($pa['name']) ?>">
      <?php if (Content::hasImage($pa['logo'] ?? null)): ?><img src="<?= e(img($pa['logo'], 320)) ?>" alt="<?= e($pa['name']) ?>" loading="lazy"><?php else: ?><span class="vpartner__name"><?= e($pa['name']) ?></span><?php endif; ?>
    </<?= $tag ?>>
  <?php endforeach; ?>
</div>
