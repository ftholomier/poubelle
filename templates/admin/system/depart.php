<?php
/**
 * Système › Contenus de départ. Variables : $summary, $state
 */
$total = array_sum(array_column($summary, 'total'));
$done = array_sum(array_column($summary, 'done'));
$running = !empty($state['running']);
?>
<p class="alert alert--info" style="margin:0">Fiches préparées pour démarrer le musée : les <b>faits</b> (dates, scores, compositions, buteurs, carrières) sont repris de sources vérifiées, et les <b>textes</b> sont entièrement rédigés à neuf. Elles sont <b>publiées tout de suite</b> et se vérifient ensuite, au fil de l’eau (écran Qualité, correction directe dans chaque fiche). Les fiches déjà créées « À relire » par une version précédente sont mises en ligne au prochain lancement. Relancer est sans risque : une fiche n’est créée qu’une fois, et une fiche supprimée n’est pas recréée.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $done ?></b><span>fiches créées</span><small>sur <?= (int) $total ?> préparées</small></div>
</div>

<section class="card card--pad stack">
  <?php if (!$summary): ?>
    <p class="muted" style="margin:0">Aucun contenu de départ n’est prévu pour ce musée.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>Paquet</th><th class="num">Préparées</th><th class="num">Créées</th></tr></thead>
    <tbody>
    <?php foreach ($summary as $p): ?>
      <tr><td><?= e($p['label']) ?></td><td class="num"><?= (int) $p['total'] ?></td><td class="num"><?= (int) $p['done'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="row" style="gap:8px;flex-wrap:wrap">
    <?php if (!$running): ?>
      <form method="post" action="/admin/contenus-de-depart"><?= csrf_field() ?><input type="hidden" name="action" value="lancer"><button class="btn btn--primary" type="submit">Créer et publier les fiches</button></form>
    <?php else: ?>
      <form method="post" action="/admin/contenus-de-depart"><?= csrf_field() ?><input type="hidden" name="action" value="pause"><button class="btn" type="submit">Mettre en pause</button></form>
    <?php endif; ?>
  </div>
  <?php if ($running): ?>
    <div class="stack" style="gap:6px;padding:12px 14px;border:2px solid var(--navy);background:var(--paper)" data-dp-run data-csrf="<?= e(\App\Core\Session::csrfToken()) ?>">
      <b data-dp-state>Création en cours</b>
      <span class="small"><b data-dp-done><?= (int) $done ?></b> fiche(s) créée(s) sur <?= (int) $total ?>. Gardez la page ouverte.</span>
      <span class="xs muted" data-dp-last>démarrage…</span>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</section>
