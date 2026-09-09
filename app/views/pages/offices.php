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

$buildUrl = static function (array $override) use ($lang, $activeSite, $activeType, $activeStatus): string {
    $query = array_filter(array_replace([
        'site' => $activeSite,
        'type' => $activeType,
        'status' => $activeStatus,
    ], $override), static fn (string $v): bool => $v !== '');
    return Router::url('offices', $lang) . ($query === [] ? '' : '?' . http_build_query($query));
};
/**
 * Nombre de bureaux qu'un filtre donnerait réellement, compte tenu des filtres
 * déjà actifs. Un compteur calculé sur tout le catalogue mentirait : « Openspace 14 »
 * alors qu'en combinaison avec « Disponible » un seul poste répond.
 */
$countIf = static function (array $facet) use ($all, $activeSite, $activeType, $activeStatus): int {
    $criteria = array_replace([
        'site' => $activeSite,
        'type' => $activeType,
        'status' => $activeStatus,
    ], $facet);
    return \count(Offices::filter($all, array_filter($criteria, static fn (string $v): bool => $v !== '')));
};

/** Une pastille sans résultat n'est plus cliquable : elle ne mène plus dans le vide. */
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
    <?= $chip(
        I18n::t('filter.all'),
        \count($all),
        Router::url('offices', $lang),
        $activeSite === '' && $activeType === '' && $activeStatus === ''
    ) ?>
    <?php foreach ((array) ($settings['sites'] ?? []) as $site):
        if (($site['enabled'] ?? true) === false) { continue; }
        $id = (string) ($site['id'] ?? '');
        if ($id === '') { continue; } ?>
      <?= $chip(
          (string) ($site['shortName'] ?? $id),
          $countIf(['site' => $id]),
          $buildUrl(['site' => $activeSite === $id ? '' : $id]),
          $activeSite === $id
      ) ?>
    <?php endforeach; ?>
    <span class="filters__sep" aria-hidden="true"></span>
    <?php foreach (['private', 'openspace'] as $type): ?>
      <?= $chip(
          Offices::typeLabel($type),
          $countIf(['type' => $type]),
          $buildUrl(['type' => $activeType === $type ? '' : $type]),
          $activeType === $type
      ) ?>
    <?php endforeach; ?>
    <?= $chip(
        I18n::t('filter.available'),
        $countIf(['status' => 'available']),
        $buildUrl(['status' => $activeStatus === 'available' ? '' : 'available']),
        $activeStatus === 'available'
    ) ?>
  </div>

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
