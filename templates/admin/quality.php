<?php
/** Qualité. Variables : $all, $cat, $sev, $items, $total */
$labels = ['stats' => ['Statistiques incohérentes', 'Scores, buteurs, dates'], 'liens' => ['Liens joueurs', 'Sans fiche, ou reliés par rapprochement'], 'credits' => ['Photos sans crédit', 'Médiathèque'], 'carto' => ['Lieux de naissance inconnus', 'Carto des origines'], 'traductions' => ['Traductions à revoir', 'Version anglaise']];
?>
<div class="kpis">
  <?php foreach ($labels as $k => [$l, $d]): ?>
    <a class="kpi<?= $cat === $k ? ' is-on kpi--yellow' : '' ?>" href="/admin/qualite?cat=<?= e($k) ?>"><b><?= count($all[$k]) >= 500 ? '500+' : count($all[$k]) ?></b><span><?= e($l) ?></span><small><?= e($d) ?></small></a>
  <?php endforeach; ?>
</div>
<div class="toolbar">
  <div class="chips">
    <?php foreach (['' => 'Toutes', 'haute' => 'Hautes', 'moyenne' => 'Moyennes', 'basse' => 'Basses'] as $k => $l): ?><a class="chip<?= $sev === $k ? ' is-on' : '' ?>" href="/admin/qualite?cat=<?= e($cat) ?><?= $k ? '&niveau=' . $k : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <span class="small muted"><?= (int) $total ?> alerte<?= $total > 1 ? 's' : '' ?> · recalculées automatiquement à chaque modification</span>
</div>
<?php if ($cat === 'liens'): ?>
  <p class="small muted">Un nom mal orthographié dans les compositions se relie à une fiche existante en l’ajoutant dans « Autres graphies dans les compositions » (fiche du joueur, onglet Identité). Les rapprochements automatiques (autre graphie, faute de frappe, nom incomplet) sont listés pour vérification.</p>
<?php endif; ?>
<div class="card">
  <?php foreach ($items as $i): ?>
    <div class="card__row qrow">
      <span><span class="sev sev--<?= e($i['sev']) ?>"><?= e(ucfirst($i['sev'])) ?></span></span>
      <span><?= e($i['msg']) ?></span>
      <span class="muted ellipsis qrow__t"><?= e($i['title']) ?></span>
      <a class="btn btn--sm" href="<?= e($i['url']) ?>"><?= $cat === 'liens' ? (str_contains($i['url'], '/nouvelle/') ? 'Créer la fiche' : 'Vérifier') : 'Corriger' ?></a>
    </div>
  <?php endforeach; ?>
  <?php if (!$items): ?><div class="empty" style="border:0">Aucune alerte · bravo !</div><?php endif; ?>
</div>
