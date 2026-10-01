<?php
/**
 * Présentation audio du lieu (« Le Signal en 74 secondes »).
 *
 * Lecteur natif : <audio controls preload="none">, jamais de lecture
 * automatique, rien n'est téléchargé tant que le visiteur n'appuie pas sur
 * lecture. Un lien de téléchargement reste toujours visible, et sert de repli
 * aux navigateurs qui ne lisent pas l'audio. Le fichier, le titre et le
 * sous-titre se règlent dans Réglages → Site.
 *
 * @var string $kicker surtitre éditable de la page qui accueille le lecteur
 */

use App\Config;
use App\Content;
use App\I18n;
use App\Text;

$audio = (array) ($settings['audio'] ?? []);
$src = trim((string) ($audio['src'] ?? ''));
$file = $src !== '' ? Config::publicPath(ltrim($src, '/')) : '';
if (empty($audio['enabled']) || $src === '' || !is_file($file)) {
    return;
}
$lang = I18n::lang();
$url = Config::basePath() . $src;
$title = Content::i18n($audio, 'title', $lang);
$subtitle = Content::i18n($audio, 'subtitle', $lang);
$duration = trim((string) ($audio['duration'] ?? ''));
$bytes = (int) (filesize($file) ?: 0);
$size = $bytes > 0
    ? str_replace('.', $lang === 'fr' ? ',' : '.', (string) round($bytes / 1_048_576, 1)) . ($lang === 'fr' ? "\u{00A0}Mo" : "\u{00A0}MB")
    : '';
$id = 'audio-' . substr(md5($src), 0, 6);
?>
<section class="audio" aria-labelledby="<?= Text::e($id) ?>" data-reveal data-audio>
  <div class="audio__signal" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
  <div class="audio__body">
    <?php if (!empty($kicker)): ?><div class="kicker kicker--signal"><?= Text::e((string) $kicker) ?></div><?php endif; ?>
    <h2 class="audio__title" id="<?= Text::e($id) ?>"><?= Text::e($title) ?></h2>
    <?php if ($subtitle !== ''): ?><p class="audio__sub"><?= Text::e($subtitle) ?></p><?php endif; ?>
    <audio class="audio__player" controls preload="none" src="<?= Text::e($url) ?>" data-audio-player>
      <p><?= Text::e(I18n::t('audio.fallback')) ?> <a href="<?= Text::e($url) ?>" download><?= Text::e(I18n::t('audio.download')) ?></a></p>
    </audio>
    <p class="audio__meta">
      <a class="link-underline link-underline--sm" href="<?= Text::e($url) ?>" download><?= Text::e(I18n::t('audio.download')) ?><?= $size !== '' ? ' · ' . Text::e($size) : '' ?></a>
      <?php if ($duration !== ''): ?><span><?= Text::e(I18n::t('audio.duration')) ?> <?= Text::e($duration) ?></span><?php endif; ?>
    </p>
  </div>
</section>
