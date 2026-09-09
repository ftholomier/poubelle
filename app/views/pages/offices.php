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
$countWith = static fn (array $criteria): int => \count(Offices::filter($all, $criteria));
?>

<section class="shell section--first" style="padding-top:60px">
  <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
  <h1 class="offices-title"><?= Text::e(Content::text($page, 'title')) ?></h1>
  <p class="section-lead"><?= Text::e(Content::text($page, 'text')) ?></p>

  <div class="filters" id="bureaux" role="group" aria-label="Filtres">
    <a class="filter<?= $activeSite === '' && $activeType === '' && $activeStatus === '' ? ' is-active' : '' ?>" href="<?= Text::e(Router::url('offices', $lang)) ?>">
      <?= Text::e(I18n::t('filter.all')) ?> <span><?= \count($all) ?></span>
    </a>
    <?php foreach ((array) ($settings['sites'] ?? []) as $site):
        if (($site['enabled'] ?? true) === false) { continue; }
        $id = (string) ($site['id'] ?? ''); ?>
      <a class="filter<?= $activeSite === $id ? ' is-active' : '' ?>" href="<?= Text::e($buildUrl(['site' => $activeSite === $id ? '' : $id])) ?>">
        <?= Text::e((string) ($site['shortName'] ?? $id)) ?> <span><?= $countWith(['site' => $id]) ?></span>
      </a>
    <?php endforeach; ?>
    <span class="filters__sep" aria-hidden="true"></span>
    <?php foreach (['private', 'openspace'] as $type): ?>
      <a class="filter<?= $activeType === $type ? ' is-active' : '' ?>" href="<?= Text::e($buildUrl(['type' => $activeType === $type ? '' : $type])) ?>">
        <?= Text::e(Offices::typeLabel($type)) ?> <span><?= $countWith(['type' => $type]) ?></span>
      </a>
    <?php endforeach; ?>
    <a class="filter<?= $activeStatus === 'available' ? ' is-active' : '' ?>" href="<?= Text::e($buildUrl(['status' => $activeStatus === 'available' ? '' : 'available'])) ?>">
      <?= Text::e(I18n::t('filter.available')) ?> <span><?= $countWith(['status' => 'available']) ?></span>
    </a>
  </div>

  <?php if ($offices === []): ?>
    <p class="empty-note"><?= Text::e(I18n::t('office.none')) ?>
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
