<?php
use App\Core\Url;
use App\Services\Blog;
use App\Services\Categories;
use App\Services\Geo;
/** @var array $deps @var array $cities */
?>
<section class="page-head">
  <?= App\Core\View::partial('front/partials/crumbs', ['crumbs' => [['Accueil', '/'], ['Plan du site', '/plan-du-site/']]]) ?>
  <h1>Plan du <span class="serif">site</span></h1>
</section>
<section class="section section-tight">
  <h2 class="h3">Métiers</h2>
  <ul class="list-plain cols-links mt-2"><?php foreach (Categories::all() as $slug => $c): ?><li><a href="<?= e(Url::category($slug)) ?>"><?= e($c['emoji'] . ' ' . $c['name']) ?></a></li><?php endforeach; ?><li><a href="<?= e(Url::category(null)) ?>">Tous les pros</a></li></ul>
  <h2 class="h3 mt-4">Occasions</h2>
  <ul class="list-plain cols-links mt-2"><?php foreach (Categories::occasions() as $slug => $o): ?><li><a href="<?= e(Url::occasion($slug)) ?>"><?= e($o['title']) ?></a></li><?php endforeach; ?></ul>
  <h2 class="h3 mt-4">Régions</h2>
  <ul class="list-plain cols-links mt-2"><?php foreach (Geo::regions() as $code => $r): ?><li><a href="<?= e(Url::region(null, (string) $code)) ?>">Animateurs <?= e($r['in']) ?></a></li><?php endforeach; ?></ul>
  <h2 class="h3 mt-4">Départements</h2>
  <ul class="list-plain cols-links mt-2"><?php foreach (Geo::depOptions() as $code => $label): ?><li><a href="<?= e(Url::dep(null, (string) $code)) ?>"><?= e($label) ?></a><?= isset($deps[$code]) ? ' <span class="muted small">(' . (int) $deps[$code] . ')</span>' : '' ?></li><?php endforeach; ?></ul>
  <h2 class="h3 mt-4">Villes</h2>
  <ul class="list-plain cols-links mt-2"><?php foreach ($cities as $label => $url): ?><li><a href="<?= e($url) ?>"><?= e($label) ?></a></li><?php endforeach; ?></ul>
  <h2 class="h3 mt-4">Infos</h2>
  <ul class="list-plain cols-links mt-2"><li><a href="/devis/">Demande de devis</a></li><li><a href="/professionnels/">Espace professionnels</a></li><li><a href="/blog/">Blog</a></li><li><a href="/faq/">Questions fréquentes</a></li><li><a href="/contact/">Contact</a></li><li><a href="/mentions-legales/">Mentions légales</a></li><li><a href="/cgu/">CGU</a></li><li><a href="/confidentialite/">Confidentialité</a></li><li><a href="/charte-qualite/">Charte qualité</a></li></ul>
</section>
