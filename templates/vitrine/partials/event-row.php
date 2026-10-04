<?php
/** Ligne d'agenda : pastille de date, titre, quand, où. Variables : $e */
use App\Vitrine\Host;
use App\Vitrine\Site;

$ts = strtotime((string) $e['start']);
$own = $e['kind'] === 'asso';
$href = $own ? Host::url('/agenda/' . $e['slug'] . '/') : (string) ($e['href'] ?? '');
$ext = !$own && preg_match('#^https?://#', $href) && !str_starts_with($href, Host::base());
?>
<a class="vevent vevent--<?= e($e['kind']) ?>" href="<?= e($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?> data-reveal>
  <span class="vevent__date"><b><?= date('j', $ts) ?></b><span><?= e(Site::monthShort($ts)) ?></span><small><?= date('Y', $ts) ?></small></span>
  <span class="vevent__body">
    <span class="vevent__kind"><?= e(['asso' => 'Rendez-vous', 'retro' => 'En ligne · Rétro-Direct', 'centenaire' => 'Centenaire'][$e['kind']] ?? '') ?></span>
    <span class="vevent__t"><?= e($e['title']) ?><?= \App\Core\View::partial('vitrine/partials/verify', ['item' => $e]) ?></span>
    <span class="vevent__m"><?= e(Site::when($e)) ?><?= !empty($e['place']) ? ' · ' . e($e['place']) : '' ?></span>
  </span>
  <span class="vevent__go" aria-hidden="true"><?= $ext ? '↗' : '→' ?></span>
</a>
