<?php
/**
 * Nos bureaux : filtres (lieu, type, disponibilité) + grille du catalogue.
 * Chaque sélection à page propre (bureaux privés, ouverts, disponibles) a son
 * titre et son texte, saisis au back-office (« facets » de la page).
 *
 * @var string $facet     sélection courante ('' pour tout le catalogue)
 * @var array  $facetPage contenu de cette sélection
 */

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
 * Les pastilles sont exclusives : cliquer « Bureaux privés » montre les
 * bureaux privés, pas l'intersection avec le filtre précédent. Une pastille ne pose
 * donc qu'un seul paramètre dans l'URL, et recliquer la pastille active la
 * retire. Le compteur annonce exactement ce que le clic donnera.
 */
$facetUrl = static function (string $key, string $value) use ($lang): string {
    if ($value === '') {
        return Router::url('offices', $lang);
    }
    $facet = Router::facetOf($key, $value);
    return $facet !== ''
        ? Router::url('offices', $lang, ['facet' => $facet])
        : Router::url('offices', $lang, [], [$key => $value]);
};

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
  <?php $intro = static fn (string $key): string => (string) (($facetPage[$key] ?? '') !== '' ? $facetPage[$key] : Content::text($page, $key)); ?>
  <div class="kicker kicker--signal"><?= Text::e($intro('kicker')) ?></div>
  <h1 class="offices-title"><?= Text::e($intro('title')) ?></h1>
  <p class="section-lead"><?= Text::e($intro('text')) ?></p>
  <?php $priceNote = Content::text($page, 'priceNote'); if ($priceNote !== ''): ?>
    <p class="offices-note"><?= Text::e($priceNote) ?></p>
  <?php endif; ?>

  <div class="filters" id="bureaux" role="group" aria-label="<?= Text::e(I18n::t('filter.label')) ?>">
    <?= $chip(I18n::t('filter.all'), \count($all), Router::url('offices', $lang), $filterCount === 0) ?>
    <?php if (Offices::multiSite()) foreach ((array) ($settings['sites'] ?? []) as $site):
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
    <?php if (Offices::multiSite()): ?><span class="filters__sep" aria-hidden="true"></span><?php endif; ?>
    <?php foreach (['private', 'openspace'] as $type): ?>
      <?= $chip(
          I18n::t('filter.' . $type),
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
