<?php
/** @var string $on  onglet actif : list|google|widget */
$gs = LD\GoogleReviews::state();
$gLabel = !LD\GoogleReviews::enabled() ? 'désactivé' : (!empty($gs['synced_at']) ? (int) ($gs['count'] ?? 0) . ' sur Google' : 'à configurer');
$cur = static fn(string $tab) => $on === $tab ? ' class="is-on" aria-current="page"' : '';
?>
<nav class="tabs tabs--section" aria-label="Avis clients">
  <a href="/admin/avis"<?= $cur('list') ?>>Avis du site <small><?= count(LD\Reviews::all()) ?></small></a>
  <a href="/admin/avis/google"<?= $cur('google') ?>><?= google_g(13) ?> Avis Google <small><?= e($gLabel) ?></small></a>
  <a href="/admin/avis/widget"<?= $cur('widget') ?>>Widget à coller <small><?= LD\Reviews::widgetCode() !== '' ? 'en ligne' : 'aucun' ?></small></a>
</nav>
