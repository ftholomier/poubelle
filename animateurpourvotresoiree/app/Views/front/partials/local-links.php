<?php
use App\Core\Cache;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;

// Maillage interne : villes et départements où des pros sont présents (bloc mis en cache).
$data = Cache::remember('local_links', 3600, static function (): array {
    $cities = [];
    $deps = [];
    foreach (Pros::publicIndex() as $p) {
        if ($p['insee']) {
            $cities[$p['insee']] = ($cities[$p['insee']] ?? 0) + 1;
        }
        foreach (array_unique(array_filter(array_merge([$p['dep']], $p['zones']))) as $d) {
            $deps[$d] = ($deps[$d] ?? 0) + 1;
        }
    }
    arsort($cities);
    arsort($deps);
    $topCities = [];
    foreach (array_slice($cities, 0, 24, true) as $insee => $n) {
        $c = Geo::commune((string) $insee);
        if ($c) {
            $topCities[] = ['name' => $c['n'], 'url' => Url::city(null, (string) $insee)];
        }
    }
    $topDeps = [];
    foreach (array_slice($deps, 0, 24, true) as $code => $n) {
        $d = Geo::dep((string) $code);
        if ($d) {
            $topDeps[] = ['name' => $d['name'] . ' (' . $d['code'] . ')', 'url' => Url::dep(null, (string) $code)];
        }
    }
    return ['cities' => $topCities, 'deps' => $topDeps];
});
?>
<section class="local-links" aria-label="Trouver un pro près de chez vous">
  <div class="wrap">
    <div>
      <h3>Les métiers</h3>
      <ul>
        <?php foreach (Categories::all() as $slug => $c): ?><li><a href="<?= e(Url::category($slug)) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3>Villes les plus actives</h3>
      <ul><?php foreach ($data['cities'] as $c): ?><li><a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?></ul>
    </div>
    <div>
      <h3>Départements</h3>
      <ul><?php foreach ($data['deps'] as $d): ?><li><a href="<?= e($d['url']) ?>"><?= e($d['name']) ?></a></li><?php endforeach; ?></ul>
    </div>
    <div>
      <h3>Occasions</h3>
      <ul>
        <?php foreach (Categories::occasions() as $slug => $o): ?><li><a href="<?= e(Url::occasion($slug)) ?>"><?= e($o['title']) ?></a></li><?php endforeach; ?>
        <li><a href="/plan-du-site/">Toutes les régions et villes →</a></li>
      </ul>
    </div>
  </div>
</section>
