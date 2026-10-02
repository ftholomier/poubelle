<?php
use App\Controllers\Admin\ProsController;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;

/** @var array $items @var array $counts @var array $filters @var int $total */
$f = static fn (string $k) => (string) ($filters[$k] ?? '');
$tab = $f('statut');
$tabUrl = static function (string $s) use ($filters): string {
    $q = $filters;
    unset($q['page']);
    $q['statut'] = $s;
    if ($s === '') {
        unset($q['statut']);
    }
    return '?' . http_build_query($q);
};
?>
<div class="adm-head">
  <div><h1>Pros <span class="serif">de l'annuaire</span></h1><p><?= nf($total) ?> fiche(s) correspondant aux filtres.</p></div>
  <div class="row-wrap">
    <a class="btn btn-sm" href="<?= e(Url::admin('pros/export.csv') . '?' . http_build_query($filters)) ?>"><?= icon('download', 16) ?> Exporter (CSV)</a>
    <a class="btn btn-sm btn-ink" href="<?= e(Url::admin('pros/nouveau')) ?>"><?= icon('plus', 16) ?> Ajouter</a>
  </div>
</div>
<div class="tabs">
  <a href="<?= e($tabUrl('')) ?>"<?= $tab === '' ? ' class="on"' : '' ?>>Toutes <em><?= nf($counts['all'] ?? 0) ?></em></a>
  <?php foreach (Pros::STATUSES as $k => $l): ?><a href="<?= e($tabUrl($k)) ?>"<?= $tab === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<form class="filters" method="get" data-autosubmit>
  <?php if ($tab !== ''): ?><input type="hidden" name="statut" value="<?= e($tab) ?>"><?php endif; ?>
  <div class="field grow"><label for="fq">Recherche</label><input id="fq" type="search" name="q" value="<?= e($f('q')) ?>" placeholder="Nom, email, identifiant, ville, téléphone, n°…"></div>
  <div class="field"><label for="fcat">Métier</label><select id="fcat" name="cat"><option value="">Tous</option><?php foreach (Categories::all(true) as $s => $c): ?><option value="<?= e($s) ?>"<?= $f('cat') === $s ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="fdep">Département</label><select id="fdep" name="dep"><option value="">Tous</option><?php foreach (Geo::departements() as $code => $d): ?><option value="<?= e($code) ?>"<?= $f('dep') === (string) $code ? ' selected' : '' ?>><?= e($code . ' ' . $d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="fph">Photos</label><select id="fph" name="photos"><option value="">Indifférent</option><option value="none"<?= $f('photos') === 'none' ? ' selected' : '' ?>>Sans photo</option><option value="some"<?= $f('photos') === 'some' ? ' selected' : '' ?>>Avec photos</option></select></div>
  <div class="field"><label for="flog">Connexion</label><select id="flog" name="connexion"><option value="">Indifférent</option><option value="never"<?= $f('connexion') === 'never' ? ' selected' : '' ?>>Jamais connecté</option><option value="recent"<?= $f('connexion') === 'recent' ? ' selected' : '' ?>>Connecté (90 j)</option></select></div>
  <div class="field"><label for="fsrc">Origine</label><select id="fsrc" name="source"><option value="">Toutes</option><option value="legacy"<?= $f('source') === 'legacy' ? ' selected' : '' ?>>Ancien site</option><option value="inscription"<?= $f('source') === 'inscription' ? ' selected' : '' ?>>Inscription</option><option value="admin"<?= $f('source') === 'admin' ? ' selected' : '' ?>>Back-office</option></select></div>
  <div class="field"><label for="ftri">Tri</label><select id="ftri" name="tri"><?php foreach (ProsController::SORTS as $k => $l): ?><option value="<?= e($k) ?>"<?= ($f('tri') ?: 'recent') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ink btn-sm" type="submit">Filtrer</button>
  <?php if (array_diff_key($filters, ['statut' => 1, 'page' => 1])): ?><a class="link small" href="?<?= $tab !== '' ? 'statut=' . e($tab) : '' ?>">Réinitialiser</a><?php endif; ?>
</form>
<form method="post" action="<?= e(Url::admin('pros/lot')) ?>">
  <?= csrf_field() ?>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th><input type="checkbox" data-check-all aria-label="Tout sélectionner"></th><th></th><th>Pro</th><th>Localisation</th><th>Métiers</th><th>Statut</th><th>Fiche</th><th class="num">Vues</th><th>Connexion</th></tr></thead>
      <tbody>
        <?php foreach ($items as $p): $cover = Pros::coverOf($p, 'sm'); ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Sélectionner"></td>
            <td><?php if ($cover): ?><img class="thumb" src="<?= e($cover) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb" style="--c:<?= e(Categories::color($p['cats'][0] ?? null)) ?>"><?= e(App\Core\Str::initials($p['name'])) ?></span><?php endif; ?></td>
            <td><a class="t-main" href="<?= e(Url::admin('pros/' . $p['id'])) ?>"><?= e($p['name']) ?></a><?= !empty($p['featured']) ? ' <span title="Mis en avant">⭐</span>' : '' ?><span class="t-sub">#<?= (int) $p['id'] ?> · <?= e($p['email'] ?: 'sans email') ?><?= $p['login'] && $p['login'] !== $p['email'] ? ' · ' . e($p['login']) : '' ?></span></td>
            <td><?= e($p['city'] ?: '—') ?><span class="t-sub"><?= e($p['dep']) ?><?= $p['zones'] ? ' + ' . count($p['zones']) . ' dép.' : '' ?><?= $p['france'] ? ' · France' : '' ?></span></td>
            <td class="small"><?= e(implode(', ', array_map([Categories::class, 'name'], array_slice($p['cats'], 0, 2)))) ?><?= count($p['cats']) > 2 ? '…' : '' ?></td>
            <td><span class="status-pill st-<?= e($p['status']) ?>"><?= e(Pros::STATUSES[$p['status']] ?? $p['status']) ?></span><?= empty($p['verified']) && $p['status'] === 'pending' ? '<span class="t-sub">email non confirmé</span>' : '' ?></td>
            <td><span class="bar-mini" title="<?= (int) $p['score'] ?> %"><i style="width:<?= (int) $p['score'] ?>%"></i></span> <span class="small"><?= (int) $p['photos'] ?> 📷</span></td>
            <td class="num"><?= nf($p['views']) ?></td>
            <td class="small"><?= $p['login_at'] ? e(ago($p['login_at'])) : '<span class="muted">jamais</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="9"><div class="empty-sm">Aucune fiche ne correspond.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="bulkbar">
      <span class="small"><b data-bulk-count>0</b> sélectionnée(s)</span>
      <select name="action" class="input" style="min-height:38px;width:auto;padding:6px 10px" aria-label="Action groupée">
        <option value="validate">Valider et publier (email au pro)</option>
        <option value="activate">Mettre en ligne (sans email)</option>
        <option value="suspend">Suspendre</option>
        <option value="inactive">Passer en « ancien membre »</option>
        <option value="feature">Mettre en avant ⭐</option>
        <option value="unfeature">Retirer la mise en avant</option>
        <option value="classify">Reclasser les métiers<?= $aiOn ? ' (IA)' : ' (mots-clés)' ?></option>
        <option value="export">Exporter la sélection (CSV)</option>
        <option value="delete">Supprimer (corbeille)</option>
      </select>
      <button class="btn btn-sm btn-ink" type="submit" data-bulk-needs data-confirm-click="Appliquer cette action aux fiches sélectionnées ?">Appliquer</button>
    </div>
  </div>
</form>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
