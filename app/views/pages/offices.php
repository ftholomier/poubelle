<?php
/** Nos bureaux : filtres (lieu, type, disponibilité) + grille du catalogue. */

use App\Config;
use App\Content;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$all = Offices::published();
$activeSite = (string) ($filters['site'] ?? '');
$activeType = (string) ($filters['type'] ?? '');
$activeStatus = (string) ($filters['status'] ?? '');

$filterCount = \count(array_filter([$activeSite, $activeType, $activeStatus], static fn (string $v): bool => $v !== ''));

/**
 * Les pastilles sont exclusives : cliquer « Carnot » montre les bureaux de
 * Carnot, pas l'intersection avec le filtre précédent. Une pastille ne pose
 * donc qu'un seul paramètre dans l'URL, et recliquer la pastille active la
 * retire. Le compteur annonce exactement ce que le clic donnera.
 */
$facetUrl = static fn (string $facet, string $value): string => $value === ''
    ? Router::url('offices', $lang)
    : Router::url('offices', $lang, [], [$facet => $value]);

$countOf = static fn (array $criteria): int => \count(Offices::filter($all, $criteria));

$chip = static function (string $label, int $count, string $href, bool $active): string {
    $dead = $count === 0 && !$active;
    $classes = 'filter' . ($active ? ' is-active' : '') . ($dead ? ' filter--empty' : '');
    $inner = Text::e($label) . ' <span>' . $count . '</span>';
    return $dead
        ? '<span class="' . $classes . '" aria-disabled="true">' . $inner . '</span>'
        : '<a class="' . $classes . '" href="' . Text::e($href) . '">' . $inner . '</a>';
};
?>

<section class="shell section--first" style="padding-top:60px">
  <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
  <h1 class="offices-title"><?= Text::e(Content::text($page, 'title')) ?></h1>
  <p class="section-lead"><?= Text::e(Content::text($page, 'text')) ?></p>

  <div class="filters" id="bureaux" role="group" aria-label="Filtres">
    <?= $chip(I18n::t('filter.all'), \count($all), Router::url('offices', $lang), $filterCount === 0) ?>
    <?php foreach ((array) ($settings['sites'] ?? []) as $site):
        if (($site['enabled'] ?? true) === false) { continue; }
        $id = (string) ($site['id'] ?? '');
        if ($id === '') { continue; } ?>
      <?= $chip(
          (string) ($site['shortName'] ?? $id),
          $countOf(['site' => $id]),
          $facetUrl('site', $activeSite === $id ? '' : $id),
          $activeSite === $id
      ) ?>
    <?php endforeach; ?>
    <span class="filters__sep" aria-hidden="true"></span>
    <?php foreach (['private', 'openspace'] as $type): ?>
      <?= $chip(
          Offices::typeLabel($type),
          $countOf(['type' => $type]),
          $facetUrl('type', $activeType === $type ? '' : $type),
          $activeType === $type
      ) ?>
    <?php endforeach; ?>
    <?= $chip(
        I18n::t('filter.available'),
        $countOf(['status' => 'available']),
        $facetUrl('status', $activeStatus === 'available' ? '' : 'available'),
        $activeStatus === 'available'
    ) ?>
  </div>

  <?php if ($filterCount > 0): ?>
    <p class="filters__state">
      <?= Text::e(I18n::t(\count($offices) === 1 ? 'filter.resultOne' : 'filter.result', ['count' => \count($offices)])) ?>
      <a class="link-underline link-underline--sm" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('filter.reset')) ?></a>
    </p>
  <?php endif; ?>

  <?php if ($offices === []): ?>
    <p class="empty-note"><?= Text::e(I18n::t('office.none')) ?>
      <a class="link-underline link-underline--sm" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('filter.reset')) ?></a>
      <a class="link-underline link-underline--sm" href="<?= Text::e(Router::url('contact', $lang)) ?>"><?= Text::e(I18n::t('footer.writeUs')) ?></a>
    </p>
  <?php else: ?>
    <div class="grid grid--offices-lg">
      <?php foreach ($offices as $office): ?>
        <?= View::partial('partials/office-card', ['office' => $office, 'variant' => 'list']) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php $included = Content::list($page, 'included'); if ($included !== []): ?>
<section class="shell section--tight section">
  <h2 class="section-title" data-reveal style="margin:0"><?= Text::e(I18n::t('office.included')) ?></h2>
  <div class="grid grid--included">
    <?php foreach ($included as $item): ?>
      <div class="included"><span class="included__check">✓</span><span><?= Text::e((string) $item) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
