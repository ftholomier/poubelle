<?php
/** Qualité. Variables : $all, $cat, $sev, $items, $total, $page, $pages, $proof (orthographe : avancement) */
$labels = ['stats' => ['Statistiques et dates', 'Scores, buteurs, compositions, dates'], 'completer' => ['À compléter', '« xx » de l’ancien site, fiches à venir, vidéos'], 'liens' => ['Liens joueurs', 'Sans fiche, ou reliés par rapprochement'], 'orthographe' => ['Orthographe & syntaxe', 'Corrections proposées par le correcteur'], 'credits' => ['Photos sans crédit', 'Médiathèque'], 'carto' => ['Lieux de naissance inconnus', 'Carto des origines'], 'traductions' => ['Traductions à revoir', 'Version anglaise']];
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
  <span class="small muted"><?= (int) $total ?> alerte<?= $total > 1 ? 's' : '' ?><?= ($pages ?? 1) > 1 ? ' (page ' . (int) $page . ' sur ' . (int) $pages . ', les plus graves d’abord)' : '' ?> · recalculées automatiquement à chaque modification</span>
</div>
<?php if ($cat === 'orthographe' && $proof): ?>
  <p class="small muted proofinfo">Le correcteur vérifie en tâche de fond chaque fiche nouvelle ou modifiée : <b><?= number_format($proof['checked'], 0, ',', ' ') ?> / <?= number_format($proof['total'], 0, ',', ' ') ?></b> fiches vérifiées<?= \App\Services\Gemini::ready() ? ', dont ' . number_format($proof['ai'], 0, ',', ' ') . ' avec Gemini (orthographe, accords, syntaxe)' : ' avec les règles de base (clé Gemini non réglée : pas de vérification des accords ni de la syntaxe)' ?>. « Corriger » ouvre la fiche avec le correcteur : vous acceptez ou ignorez chaque correction, puis enregistrez. Les noms propres se protègent dans le <a href="/admin/collection/dictionnaire">dictionnaire du musée</a> (<?= count(\App\Services\Proofreader::dictionary()) ?> mot<?= count(\App\Services\Proofreader::dictionary()) > 1 ? 's' : '' ?>).</p>
<?php endif; ?>
<?php if ($cat === 'completer'): ?>
  <p class="small muted">Les « xx » viennent de l’ancien site (information inconnue au moment de la saisie) : ils sont cachés sur le site public. Complétez l’information si vous la connaissez, ou retirez le « xx ».</p>
<?php endif; ?>
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
<?php if (($pages ?? 1) > 1): ?>
  <nav class="row" style="gap:8px;flex-wrap:wrap" aria-label="Pages des alertes">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <a class="chip<?= $n === $page ? ' is-on' : '' ?>" href="/admin/qualite?cat=<?= e($cat) ?><?= $sev !== '' ? '&niveau=' . e($sev) : '' ?>&page=<?= $n ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>>Page <?= $n ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
