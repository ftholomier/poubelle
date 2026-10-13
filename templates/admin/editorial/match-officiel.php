<?php
/** Éditorial › Match officiel. Variables : $listed, $shown, $missing, $added, $removed */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$row = function (array $f, string $action, string $label) { ?>
  <tr>
    <td><?php if ($f['path']): ?><a href="<?= e($f['path']) ?>" target="_blank" rel="noopener"><?= e($f['title']) ?></a><?php else: ?><?= e($f['title']) ?><?php endif; ?></td>
    <td class="num muted small"><?= (int) $f['id'] ?></td>
    <td><form method="post" action="/admin/match-officiel"><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="fiche" value="<?= (int) $f['id'] ?>"><button class="btn btn--sm" type="submit"><?= e($label) ?></button></form></td>
  </tr>
<?php };
?>
<p class="alert alert--info" style="margin:0">La sous-rubrique <a href="<?= e(\App\Data\OfficialPlayers::PATH) ?>" target="_blank" rel="noopener">Match officiel</a> (Nos lions › Joueurs) montre les joueurs ayant disputé au moins un match officiel avec l’équipe première, d’après le répertoire de FCSM Story et les feuilles de la Coupe Peugeot. La liste est livrée avec le site et suit ses mises à jour ; les fiches ne sont pas modifiées. Ici, vous pouvez ajouter ou retirer un joueur : votre réglage s’applique par-dessus la liste livrée.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($shown) ?></b><span>joueurs affichés</span><small>liste livrée : <?= $fmt($listed) ?> lignes</small></div>
  <div class="kpi"><b><?= $fmt(count($missing)) ?></b><span>lignes sans fiche</span><small>joueur non trouvé au musée</small></div>
</div>

<section class="card card--pad stack">
  <h2 class="card__t card__t--sm">Ajouter ou retirer un joueur</h2>
  <form method="post" action="/admin/match-officiel" class="stack" style="gap:10px">
    <?= csrf_field() ?>
    <label class="f"><span class="f__k">Joueur</span><input type="text" name="fiche" required placeholder="Tapez le nom du joueur (ou l’adresse, ou le numéro de sa fiche)" data-ac="fiches" data-ac-value="url"></label>
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <button class="btn btn--navy" type="submit" name="action" value="ajouter">Ajouter à la liste</button>
      <button class="btn" type="submit" name="action" value="retirer">Retirer de la liste</button>
    </div>
  </form>
</section>

<?php if ($added || $removed): ?>
<section class="card card--pad stack">
  <h2 class="card__t card__t--sm">Vos réglages</h2>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>Joueur</th><th class="num">Fiche</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($added as $f) { $row($f, 'annuler', 'Annuler l’ajout'); } ?>
    <?php foreach ($removed as $f) { $row($f, 'annuler', 'Annuler le retrait'); } ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($missing): ?>
<section class="card card--pad stack">
  <h2 class="card__t card__t--sm">Lignes de la liste sans fiche au musée</h2>
  <p class="small muted" style="margin:0">Ces joueurs apparaîtront d’eux-mêmes dès que leur fiche sera publiée (retrouvée par son nom). Sinon, ajoutez la fiche ci-dessus.</p>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>Joueur</th><th>Période</th><th class="num">Matchs officiels</th></tr></thead>
    <tbody>
    <?php foreach ($missing as $m): ?>
      <tr><td><?= e(trim(($m[2] ?? '') . ' ' . ($m[1] ?? ''))) ?></td><td><?= e((string) ($m[4] ?? '')) ?></td><td class="num"><?= (int) ($m[5] ?? 0) + (int) ($m[6] ?? 0) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
