<?php
use App\Core\Url;
use App\Core\View;
use App\Services\Ads;
use App\Services\Categories;
use App\Services\Search;
use App\Services\Settings;

/** @var array $result @var array $state @var array $criteria @var string $h1 @var string $intro @var array $crumbs */
$filtered = App\Controllers\Api\SearchCriteria::filtered($criteria);
$every = (int) Settings::get('ads.slots.listing.every', 8);
$cats = Categories::all();
$occasions = Categories::occasions();
// Mise en forme du titre : la partie « lieu » en italique (comme la maquette).
$h1Main = $h1;
$h1Em = '';
if (preg_match('/^(.*?)\s((?:dans (?:le|la|les|l\')|en|à|au|aux|pour)\s.*)$/u', $h1, $m)) {
    [$h1Main, $h1Em] = [$m[1], $m[2]];
}
$total = $result['total'];
$loaded = count($result['items']);
$page = $page ?? 1;
$moreUrl = ($self ?? '/recherche/') . '?' . http_build_query(array_merge(array_diff_key($_GET, ['page' => 1]), ['page' => $page + 1]));
?>
<section class="page-head">
  <?= View::partial('front/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <h1><?= e($h1Main) ?><?php if ($h1Em !== ''): ?> <span class="serif"><?= e($h1Em) ?></span><?php endif; ?></h1>
  <?php if ($intro !== ''): ?><p class="lead"><?= e($intro) ?></p><?php endif; ?>
  <?php if (!empty($children)): ?>
    <div class="sub-links" data-collapsible>
      <?php foreach (array_slice($children, 0, 14) as [$label, $url]): ?><a class="pill-ghost" href="<?= e($url) ?>"><?= e($label) ?></a><?php endforeach; ?>
      <?php if (count($children) > 14): ?><a class="pill-ghost" href="#plus-de-lieux">+ <?= count($children) - 14 ?> autres</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<div class="explore<?= !empty($mapFirst) ? '' : ' map-hidden' ?>" data-explore
     data-state="<?= ej($state) ?>" data-filtered="<?= $filtered ? '1' : '0' ?>" data-total="<?= (int) $total ?>"
     data-ids="<?= ej($filtered ? $result['ids'] : []) ?>" data-focus="<?= ej($filtered ? ($result['focus'] ?? null) : null) ?>" data-center="<?= ej($filtered ? ($result['center'] ?? null) : null) ?>" data-base="<?= e($self ?? '/recherche/') ?>" data-search-page="<?= !empty($search) ? '1' : '0' ?>">
  <div class="explore-list">
    <form class="explore-search" action="/recherche/" method="get" role="search" data-explore-form>
      <label class="search-field"><span>Quoi ?</span><input type="search" name="q" value="<?= e($state['q'] ?? '') ?>" placeholder="Nom, style, besoin…" autocomplete="off" aria-label="Rechercher un pro, un style, un besoin"></label>
      <label class="search-field autocomplete"><span>Où ?</span><input type="text" name="ou" value="<?= e($state['ou'] ?? '') ?>" placeholder="Ville ou code postal" autocomplete="off" data-commune-input aria-label="Ville"><input type="hidden" name="insee" value="<?= e($state['insee'] ?? '') ?>" data-commune-insee></label>
      <?php foreach (['cat', 'dep', 'region', 'occasion'] as $k): if (!empty($state[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= e($state[$k]) ?>" data-keep><?php endif; endforeach; ?>
      <button type="submit" class="btn-go">OK</button>
    </form>

    <div class="explore-filters scroll" role="group" aria-label="Métiers" data-chips="cat">
      <button type="button" class="chip<?= empty($state['cat']) ? ' is-on' : '' ?>" data-cat="" aria-pressed="<?= empty($state['cat']) ? 'true' : 'false' ?>"><span class="dot" style="background:#1c1233"></span>Tous</button>
      <?php foreach ($cats as $slug => $c): $on = ($state['cat'] ?? '') === $slug; ?>
        <button type="button" class="chip<?= $on ? ' is-on' : '' ?>" data-cat="<?= e($slug) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="explore-filters" role="group" aria-label="Filtres rapides">
      <?php foreach ($occasions as $slug => $o): $on = ($state['occasion'] ?? '') === $slug; ?>
        <button type="button" class="chip chip-sm<?= $on ? ' is-on' : '' ?>" data-occasion="<?= e($slug) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= e($o['emoji'] . ' ' . $o['name']) ?></button>
      <?php endforeach; ?>
      <button type="button" class="chip chip-sm<?= !empty($state['photo']) ? ' is-on' : '' ?>" data-toggle="photo" aria-pressed="<?= !empty($state['photo']) ? 'true' : 'false' ?>">📸 Avec photos</button>
      <button type="button" class="chip chip-sm<?= !empty($state['reviews']) ? ' is-on' : '' ?>" data-toggle="avis" aria-pressed="<?= !empty($state['reviews']) ? 'true' : 'false' ?>">⭐ Avec avis</button>
    </div>

    <div class="explore-bar">
      <span aria-live="polite" data-count-zone><b data-count><?= nf($total) ?></b> <span data-count-label><?= $total > 1 ? 'pros' : 'pro' ?></span></span>
      <span class="tools">
        <label class="sr-only" for="tri">Trier</label>
        <select id="tri" data-sort>
          <option value="">Pertinence</option>
          <option value="distance"<?= ($state['sort'] ?? '') === 'distance' ? ' selected' : '' ?>>Distance</option>
          <option value="rating"<?= ($state['sort'] ?? '') === 'rating' ? ' selected' : '' ?>>Mieux notés</option>
          <option value="recent"<?= ($state['sort'] ?? '') === 'recent' ? ' selected' : '' ?>>Nouveaux</option>
          <option value="price"<?= ($state['sort'] ?? '') === 'price' ? ' selected' : '' ?>>Prix</option>
        </select>
        <label class="sr-only" for="rayon">Rayon</label>
        <select id="rayon" data-radius<?= empty($state['insee']) && empty($state['lat']) ? ' class="hidden"' : '' ?>>
          <?php foreach ([10, 20, 40, 80, 150] as $r): ?><option value="<?= $r ?>"<?= (int) ($state['radius'] ?? 40) === $r ? ' selected' : '' ?>><?= $r ?> km</option><?php endforeach; ?>
        </select>
        <button type="button" class="link" data-near><?= icon('target', 14) ?> Autour de moi</button>
        <button type="button" class="btn btn-xs btn-ink explore-toggle" data-toggle-map><?= icon('map', 14) ?> <span>Carte</span></button>
      </span>
    </div>

    <div class="results" data-results>
      <?php foreach ($result['items'] as $i => $p): ?>
        <?= View::partial('front/partials/result-card', ['p' => $p]) ?>
        <?php if ($every > 0 && ($i + 1) % $every === 0): ?><?= Ads::slot('listing', 'ad-inline') ?><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="empty<?= $total > 0 ? ' hidden' : '' ?>" data-empty style="font-size:17px;padding:28px">
      Personne ici pour l'instant… Élargissez la zone ou <a class="link" href="/devis/">déposez une demande de devis</a> : nous la transmettrons aux pros les plus proches.
    </div>
    <a class="btn btn-sm more-btn<?= $loaded >= $total || $page < 1 ? ' hidden' : '' ?>" href="<?= e($moreUrl) ?>" rel="next" data-more>Voir plus (<span data-more-count><?= nf(max(0, $total - $loaded - ($page - 1) * 24)) ?></span>)</a>
    <div class="box box-cream mt-2">
      <strong>Pas le temps de comparer ?</strong>
      <p class="small mt-1" style="margin-bottom:12px">Décrivez votre événement une seule fois : votre demande est transmise aux pros du secteur, qui vous répondent directement.</p>
      <a class="btn btn-sm btn-coral" href="<?= e(Url::devis(array_filter(['cat' => $state['cat'] ?? null, 'insee' => $state['insee'] ?? null, 'dep' => $state['dep'] ?? null]))) ?>">Demander des devis gratuits →</a>
    </div>
  </div>
  <div class="explore-map">
    <div class="map-root" data-map role="region" aria-label="Carte des professionnels"></div>
  </div>
</div>
<script type="application/json" id="map-points"><?= js(Search::mapPoints()) ?></script>

<?php if (!empty($seo_content) || !empty($related) || count($children ?? []) > 14): ?>
<section class="section section-tight">
  <?php if (!empty($seo_content)): ?><div class="prose prose-block"><?= $seo_content ?></div><?php endif; ?>
  <div class="grid-2 mt-3">
    <?php if (!empty($related)): ?>
      <div>
        <h2 class="h3">Autres métiers<?= !empty($place['in']) ? ' ' . e($place['in']) : '' ?></h2>
        <div class="sub-links"><?php foreach ($related as [$label, $url]): ?><a class="pill-ghost" href="<?= e($url) ?>"><?= e($label) ?></a><?php endforeach; ?></div>
      </div>
    <?php endif; ?>
    <?php if (count($children ?? []) > 14): ?>
      <div id="plus-de-lieux">
        <h2 class="h3">Par lieu</h2>
        <div class="sub-links"><?php foreach ($children as [$label, $url]): ?><a class="pill-ghost" href="<?= e($url) ?>"><?= e($label) ?></a><?php endforeach; ?></div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
