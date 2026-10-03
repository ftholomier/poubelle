<?php
/**
 * Fiche article / page : infrastructures, symboles, supporters, bilans de saison, pages libres.
 * Variables : $doc, $crumbs, $kicker, $related, $seasonLink
 */

use App\Core\View;

$sections = array_values(array_filter($doc['sections'] ?? [], fn ($s) => trim(strip_tags((string) $s['html'], '<img><iframe>')) !== '' || !empty($s['title'])));
$withTitles = array_values(array_filter($sections, fn ($s) => !empty($s['title'])));
$hasKey = !empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text']);
$subtitle = trim((string) ($doc['article']['subtitle'] ?? ''));
$heading = trim((string) ($doc['article']['heading'] ?? ''));
$isBilan = ($doc['article']['kind'] ?? '') === 'bilan_saison';
// Encadré « la saison en chiffres » d'un bilan (mise en forme d'origine).
$headerHtml = (string) ($doc['article']['header_html'] ?? '');
if ($headerHtml === '' && $isBilan) {
    $headerHtml = (string) preg_replace('#^\s*<h[1-6][^>]*>.*?</h[1-6]>#is', '', (string) ($doc['legacy']['header_html'] ?? ''), 1);
}
if ($isBilan || mb_strtolower($heading) === mb_strtolower($kicker)) {
    $subtitle = $isBilan ? '' : $subtitle;
    $heading = '';
}
$isPage = $doc['type'] === 'page';
$hasAside = $hasKey || count($withTitles) >= 3 || $seasonLink || ($isBilan && trim(strip_tags($headerHtml)) !== '');
$anchor = fn (int $i, array $s): string => 's' . ($i + 1) . '-' . slugify((string) $s['title']);
$feat = $doc['featured_image'] ?? null;
$featInGallery = $feat && in_array($feat, array_column($doc['gallery'] ?? [], 'image'), true);
?>
<section class="ahero">
  <div class="wrap ahero__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
      <?php foreach ($crumbs as $c): ?><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><span aria-hidden="true">/</span><?php endforeach; ?>
      <span aria-current="page"><?= e(mb_strimwidth((string) $doc['title'], 0, 60, '…')) ?></span>
    </nav>
    <?php if ($kicker !== ''): ?><span class="ahero__kicker"><?= e($kicker) ?></span><?php endif; ?>
    <h1 class="ahero__title"><?= e($doc['title']) ?></h1>
    <?php if ($heading !== '' && mb_strtolower($heading) !== mb_strtolower((string) $doc['title'])): ?><p class="ahero__sub"><?= e($heading) ?></p><?php endif; ?>
    <?php if ($subtitle !== ''): ?><p class="ahero__sub"><?= e($subtitle) ?></p><?php endif; ?>
    <?php if (!$isPage): ?><div class="hero-actions"><?= View::partial('partials/pdf-button', ['href' => \App\Front\PdfExport::ficheUrl($doc), 'light' => true]) ?><?= !empty($audio) ? View::partial('partials/audio-button', ['audio' => $audio]) : '' ?></div><?php endif; ?>
  </div>
</section>

<div class="wrap abody<?= $hasAside ? ' abody--aside' : '' ?>">
  <article class="abody__main">
    <?php if ($feat && !$featInGallery && !$isPage): ?>
      <figure class="afeat" data-gallery>
        <a href="<?= e(img($feat, 1600)) ?>" data-lb data-caption="<?= e(\App\Data\Media::caption($feat, (string) $doc['title'])) ?>">
          <img src="<?= e(img($feat, 1200)) ?>" srcset="<?= e(srcset($feat, [640, 800, 1200, 1600])) ?>" sizes="(max-width: 900px) 100vw, 820px" alt="<?= e(\App\Data\Media::alt($feat) ?: $doc['title']) ?>" fetchpriority="high">
        </a>
        <?php if ($cap = \App\Data\Media::caption($feat)): ?><figcaption><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    <?php endif; ?>

    <?php if (!empty($doc['intro'])): ?><div class="prose lead" style="max-width:none"><?= safe_html($doc['intro']) ?></div><?php endif; ?>

    <?php foreach ($sections as $i => $s): ?>
      <section class="abody__sec"<?= !empty($s['title']) ? ' id="' . e($anchor($i, $s)) . '"' : '' ?>>
        <?php if (!empty($s['title'])): ?><h2 class="h-section"><?= e($s['title']) ?></h2><?php endif; ?>
        <?php if (trim((string) $s['html']) !== ''): ?><div class="prose"><?= safe_html($s['html']) ?></div><?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php foreach ($doc['images'] ?? [] as $im): $cap = trim(($im['caption'] ?? '') . (!empty($im['credit']) ? ' – ' . $im['credit'] : ''), ' –'); ?>
      <figure class="fm-figure" data-gallery>
        <a href="<?= e(img($im['image'], 1600)) ?>" data-lb data-caption="<?= e($cap) ?>"><img src="<?= e(img($im['image'], 800)) ?>" srcset="<?= e(srcset($im['image'], [480, 800, 1200])) ?>" sizes="(max-width: 900px) 100vw, 820px" alt="<?= e($im['caption'] ?? '') ?>" loading="lazy" decoding="async"></a>
        <?php if ($cap !== ''): ?><figcaption><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>

    <?= View::partial('partials/embeds', ['embeds' => $doc['embeds'] ?? []]) ?>

    <?php if (!empty($doc['tables'])): ?>
      <?php foreach ($doc['tables'] as $tb): ?>
        <section class="abody__sec">
          <?php if (!empty($tb['title'])): ?><h2 class="h-3"><?= e($tb['title']) ?></h2><?php endif; ?>
          <?= View::partial('partials/table', ['table' => $tb]) ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?= View::partial('partials/videos', ['videos' => $doc['videos'] ?? []]) ?>
  </article>

  <?php if ($hasAside): ?>
  <aside class="abody__aside">
    <?php if ($hasKey): ?>
      <div class="keyfig" data-reveal>
        <span class="keyfig__n" data-count><?= e($doc['key_figure']['number']) ?></span>
        <span class="keyfig__t"><?= e($doc['key_figure']['text']) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($isBilan && trim(strip_tags($headerHtml)) !== ''): ?>
      <div class="seasonbox">
        <div class="idcard__head"><?= e(t('La saison en chiffres')) ?></div>
        <div class="prose seasonbox__body"><?= safe_html($headerHtml) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($seasonLink): ?>
      <a class="btn btn--navy" href="<?= e($seasonLink) ?>"><?= e(t('Tous les matchs de la saison {s}', ['s' => $doc['article']['season']])) ?> →</a>
    <?php endif; ?>
    <?php if (count($withTitles) >= 3): ?>
      <nav class="toc" aria-label="<?= e(t('Sommaire')) ?>">
        <b><?= e(t('Sommaire')) ?></b>
        <?php foreach ($sections as $i => $s): if (empty($s['title'])) continue; ?>
          <a href="#<?= e($anchor($i, $s)) ?>"><?= e($s['title']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <button type="button" class="btn btn--ghost btn--sm" data-share data-share-title="<?= e($doc['title']) ?>"><?= e(t('Partager cette page')) ?></button>
  </aside>
  <?php endif; ?>
</div>

<?= View::partial('partials/gallery', ['items' => $doc['gallery'] ?? [], 'title' => t('Galerie'), 'anchor' => 'galerie', 'context' => $doc['title']]) ?>

<?= View::partial('partials/related', ['related' => $related]) ?>
