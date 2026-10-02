<?php
/**
 * Vidéos (YouTube sans cookie, Dailymotion, Vimeo, fichiers) : chargées seulement après accord (cookies « vidéos »).
 * Variables : $videos, $title, $anchor
 */
$list = array_values(array_filter(array_map('video_embed', $videos ?? [])));
if (!$list) {
    return;
}
$names = ['youtube' => 'YouTube', 'dailymotion' => 'Dailymotion', 'vimeo' => 'Vimeo', 'iframe' => t('un site tiers')];
?>
<section class="videos" id="<?= e($anchor ?? 'video') ?>">
  <h2 class="h-section"><?= e($title ?? (count($list) > 1 ? t('Vidéos') : t('Vidéo'))) ?></h2>
  <div class="videos__grid<?= count($list) > 1 ? ' videos__grid--multi' : '' ?>">
    <?php foreach ($list as $v): ?>
      <?php if ($v['provider'] === 'file'): ?>
        <div class="video"><video controls preload="none" src="<?= e($v['embed']) ?>"></video></div>
      <?php else: ?>
        <div class="video" data-embed="<?= e($v['embed']) ?>" data-title="<?= e($v['title'] ?: t('Vidéo')) ?>">
          <div class="ytconsent"<?= $v['thumb'] ? ' style="background-image:url(' . e(img($v['thumb'], 800)) . ')"' : '' ?>>
            <button type="button" class="play" data-embed-play aria-label="<?= e(t('Lire la vidéo')) ?>">▶</button>
            <small><?= e(t('En lançant la vidéo, vous acceptez les cookies de {site}.', ['site' => $names[$v['provider']] ?? $v['provider']])) ?>
              <a href="<?= e($v['link']) ?>" target="_blank" rel="noopener"><?= e(t('Voir sur {site}', ['site' => $names[$v['provider']] ?? t('le site d’origine')])) ?></a></small>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>
