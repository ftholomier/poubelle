<?php
/**
 * Récit illustré (rubrique Infrastructures) : chapitres numérotés, chapô, citations, encadrés,
 * photos réparties, sommaire qui suit la lecture, frise de la rubrique, page précédente / suivante.
 * Variables : $r (App\Front\Recit::build), $doc, $aside (HTML ajouté en tête de la colonne : fiche
 * d'identité, chiffre clé…), $context (titre pour les légendes)
 */
use App\Core\View;

$titled = array_values(array_filter($r['chapters'], fn ($c) => $c['n'] > 0));
$figure = function (array $g, string $cls) use ($context): string {
    $cap = trim((string) ($g['caption'] ?? ''));
    $credit = trim((string) ($g['credit'] ?? ''));
    $lb = trim($cap . ($credit !== '' ? ' – ' . $credit : '') . ' · ' . $context, ' ·–');
    return '<figure class="rfig ' . $cls . '"><a href="' . e(img($g['image'], 1600)) . '" data-lb data-caption="' . e($lb) . '">'
        . '<img src="' . e(img($g['image'], 1200)) . '" srcset="' . e(srcset($g['image'], [480, 800, 1200, 1600])) . '" sizes="(max-width: 900px) 100vw, 900px" alt="' . e($cap ?: $context) . '" loading="lazy" decoding="async"></a>'
        . ($cap !== '' || $credit !== '' ? '<figcaption>' . e($cap) . ($credit !== '' ? ' <span>© ' . e($credit) . '</span>' : '') . '</figcaption>' : '') . '</figure>';
};
?>
<div class="recit wrap" data-recit>
  <article class="recit__main" data-gallery>
    <?php if ($r['tagline'] !== ''): ?><p class="recit__tagline"><?= e($r['tagline']) ?></p><?php endif; ?>
    <?php if ($r['lead'] !== ''): ?><div class="recit__lead"><?= $r['lead'] ?></div><?php endif; ?>

    <?php foreach ($r['chapters'] as $k => $c): ?>
      <section class="rch"<?= $c['id'] !== '' ? ' id="' . e($c['id']) . '" data-rch' : '' ?>>
        <?php if ($c['n'] > 0): ?>
          <header class="rch__head">
            <span class="rch__n" aria-hidden="true"><?= pad2($c['n']) ?></span>
            <h2 class="rch__t"><?= e($c['title']) ?></h2>
            <?php if ($c['year']): ?><span class="rch__year"><?= (int) $c['year'] ?></span><?php endif; ?>
          </header>
        <?php endif; ?>
        <div class="prose rch__body<?= $k === 0 ? ' rch__body--first' : '' ?>"><?= $c['html'] ?></div>
        <?php if (count($c['photos']) === 1): ?>
          <?= $figure($c['photos'][0], 'rfig--wide') ?>
        <?php elseif (count($c['photos']) > 1): ?>
          <div class="rfig-pair"><?php foreach ($c['photos'] as $g): ?><?= $figure($g, '') ?><?php endforeach; ?></div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?= View::partial('partials/embeds', ['embeds' => $doc['embeds'] ?? []]) ?>
    <?php foreach ($doc['tables'] ?? [] as $tb): ?>
      <section class="rch"><?php if (!empty($tb['title'])): ?><h2 class="h-3"><?= e($tb['title']) ?></h2><?php endif; ?><?= View::partial('partials/table', ['table' => $tb]) ?></section>
    <?php endforeach; ?>
    <?= View::partial('partials/videos', ['videos' => $doc['videos'] ?? []]) ?>
  </article>

  <aside class="recit__aside">
    <div class="recit__sticky">
      <?= $aside ?? '' ?>
      <?php if ($r['series']): ?>
        <nav class="rseries" aria-label="<?= e($r['series']['title']) ?>">
          <span class="rseries__k"><?= e(t('Série')) ?> · <?= e(t('partie {n} sur {t}', ['n' => $r['series']['n'], 't' => count($r['series']['parts'])])) ?></span>
          <b class="rseries__t"><?= e($r['series']['title']) ?></b>
          <ol>
            <?php foreach ($r['series']['parts'] as $p): ?>
              <li<?= $p['current'] ? ' class="is-on" aria-current="page"' : '' ?>><a href="<?= e($p['href']) ?>"><span><?= (int) $p['n'] ?></span><?= e($p['title']) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>
      <?php if (count($titled) >= 2): ?>
        <nav class="rtoc" aria-label="<?= e(t('Sommaire')) ?>" data-rtoc>
          <div class="rtoc__head"><b><?= e(t('Sommaire')) ?></b><span><?= e(t('{n} min de lecture', ['n' => $r['minutes']])) ?></span></div>
          <div class="rtoc__bar"><i data-rtoc-bar></i></div>
          <ol>
            <?php foreach ($titled as $c): ?>
              <li><a href="#<?= e($c['id']) ?>" data-rtoc-link="<?= e($c['id']) ?>"><span><?= pad2($c['n']) ?></span><?= e($c['title']) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>
      <button type="button" class="btn btn--ghost btn--sm" data-share data-share-title="<?= e($doc['title']) ?>"><?= e(t('Partager cette page')) ?></button>
    </div>
  </aside>
</div>

<?php if ($r['prev'] || $r['next']): ?>
<nav class="wrap rnext" aria-label="<?= e(t('Pages voisines')) ?>">
  <?php foreach (['prev' => t('Avant'), 'next' => t('Ensuite')] as $k => $label): $p = $r[$k]; ?>
    <?php if ($p): ?>
      <a class="rnext__card rnext__card--<?= $k ?>" href="<?= e($p['href']) ?>">
        <?php if (!empty($p['image'])): ?><img src="<?= e(img($p['image'], 800)) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
        <span class="rnext__k"><?= $k === 'prev' ? '← ' : '' ?><?= e($label) ?><?= $k === 'next' ? ' →' : '' ?><?= !empty($p['era']) ? ' · ' . e($p['era']) : (isset($p['n']) ? ' · ' . e(t('partie {n}', ['n' => $p['n']])) : '') ?></span>
        <b class="rnext__t"><?= e($p['title']) ?></b>
      </a>
    <?php else: ?><span></span><?php endif; ?>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (count($r['timeline']) >= 2): ?>
<section class="rfrise">
  <div class="wrap">
    <div class="rfrise__head"><span class="eyebrow"><?= e(t('Toute l’histoire')) ?></span><h2 class="h-2"><a href="<?= e($r['rubric']['href']) ?>"><?= e($r['rubric']['label']) ?></a></h2></div>
    <ol class="rfrise__list">
      <?php foreach ($r['timeline'] as $t): ?>
        <li class="rfrise__item<?= $t['current'] ? ' is-on' : '' ?> rfrise__item--<?= e($t['kind']) ?>">
          <a href="<?= e($t['href']) ?>"<?= $t['current'] ? ' aria-current="page"' : '' ?>>
            <span class="rfrise__img"><?php if (!empty($t['image'])): ?><img src="<?= e(img($t['image'], 480)) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?></span>
            <span class="rfrise__era"><?= e($t['era'] !== '' ? $t['era'] : ($t['kind'] === 'personne' ? t('Portrait') : '')) ?></span>
            <b class="rfrise__t"><?= e($t['title']) ?></b>
            <?php if ($t['current']): ?><span class="rfrise__here"><?= e(t('Vous êtes ici')) ?></span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<?= View::partial('partials/gallery', ['items' => $r['album'], 'title' => t('Album photo'), 'anchor' => 'galerie', 'context' => $doc['title']]) ?>
