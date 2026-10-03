<?php
/** Publications de réseaux sociaux reprises de l'ancien site (texte conservé, sans script tiers). Variables : $embeds */
if (empty($embeds)) {
    return;
}
?>
<div class="embeds">
  <?php foreach ($embeds as $em): ?>
    <figure class="embed-post">
      <span class="embed-post__src"><?= e(($em['provider'] ?? '') === 'x' ? 'X (Twitter)' : ucfirst((string) ($em['provider'] ?? ''))) ?></span>
      <blockquote><?= rich_inline($em['text'] ?? '') ?></blockquote>
      <?php if (!empty($em['url'])): ?><figcaption><a href="<?= e($em['url']) ?>" target="_blank" rel="noopener nofollow"><?= e(t('Voir la publication')) ?> →</a></figcaption><?php endif; ?>
    </figure>
  <?php endforeach; ?>
</div>
