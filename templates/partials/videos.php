<?php
/**
 * Vidéos (YouTube sans cookie, Dailymotion, Vimeo, Rutube, publications X avec vidéo, fichiers) : chargées seulement après accord (cookies « vidéos »).
 * Variables : $videos, $title, $anchor
 */
// Vidéos retirées chez l'hébergeur (supprimées, privées) : masquées plutôt qu'un lecteur en erreur.
$goneV = \App\Services\VideoThumbs::gone();
$list = array_values(array_filter(array_map('video_embed', $videos ?? []), fn ($v) => $v && !isset($goneV[$v['provider'] . '-' . $v['id']])));
if (!$list) {
    return;
}
$names = ['youtube' => 'YouTube', 'dailymotion' => 'Dailymotion', 'vimeo' => 'Vimeo', 'rutube' => 'Rutube', 'x' => 'X', 'iframe' => t('un site tiers')];
?>
<section class="videos" id="<?= e($anchor ?? 'video') ?>">
  <h2 class="h-section"><?= e($title ?? (count($list) > 1 ? t('Vidéos') : t('Vidéo'))) ?></h2>
  <div class="videos__grid<?= count($list) > 1 ? ' videos__grid--multi' : '' ?>">
    <?php foreach ($list as $v): ?>
      <?php if ($v['provider'] === 'dailymotion'): // Dailymotion refuse désormais la lecture intégrée (« Forbidden ») : image et lien vers la vidéo. ?>
        <a class="video video--out" href="<?= e($v['link']) ?>" target="_blank" rel="noopener"<?= $v['thumb'] ? ' style="background-image:url(' . e(img($v['thumb'], 800)) . ')"' : '' ?>>
          <span class="play" aria-hidden="true">▶</span>
          <span class="video__out"><?= e(t('Regarder sur {site}', ['site' => 'Dailymotion'])) ?> ↗<?= $v['title'] ? '<small>' . e($v['title']) . '</small>' : '' ?></span>
        </a>
      <?php elseif ($v['provider'] === 'file'): ?>
        <div class="video"><video controls preload="none" src="<?= e($v['embed']) ?>"></video></div>
      <?php else: ?>
        <div class="video<?= $v['provider'] === 'x' ? ' video--x' : '' ?>" data-embed="<?= e($v['embed']) ?>" data-title="<?= e($v['title'] ?: t('Vidéo')) ?>">
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
