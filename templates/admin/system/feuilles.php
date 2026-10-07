<?php
/**
 * Système › Feuilles de match. Variables : $state, $summary, $recent, $seasons, $total
 */
use App\Services\FeuillesImport as F;

$planned = (bool) $state['items'];
$running = !empty($state['running']);
$labels = ['fait' => ['Créée', 'ok'], 'compare' => ['Comparée', 'info'], 'erreur' => ['Erreur', 'ko']];
?>
<p class="alert alert--info" style="margin:0">Import des <b><?= (int) $total ?> feuilles de match</b> des archives de l’association (matchs officiels et amicaux, 1928 à 2024). Un match absent du musée est <b>créé</b> (publié, rangé dans sa saison) avec score, mi-temps, stade, affluence, arbitre, buteurs, composition et entraîneur. Un match <b>déjà au musée</b> n’est jamais modifié : chaque écart (score, date, domicile/extérieur, affluence) et chaque information qui lui manque (arbitre, stade, buteurs, composition) devient une proposition dans <a href="/admin/trouvailles?source=feuilles">Trouvailles</a>, à valider ou écarter. Relancer est sans risque : une feuille n’est traitée qu’une fois.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $summary['fait'] ?></b><span>fiches créées</span><small>sur <?= (int) ($summary['fait'] + $summary['a-creer']) ?> à créer</small></div>
  <div class="kpi"><b><?= (int) $summary['compare'] ?></b><span>matchs comparés</span><small>sur <?= (int) ($summary['compare'] + $summary['a-comparer']) ?> déjà au musée</small></div>
  <div class="kpi"><b><?= (int) $summary['props'] ?></b><span>propositions</span><small><a href="/admin/trouvailles?source=feuilles">à valider dans Trouvailles</a></small></div>
  <div class="kpi"><b><?= (int) ($summary['erreur'] + $summary['sans-date']) ?></b><span>à revoir</span><small><?= (int) $summary['sans-date'] ?> feuille(s) sans date</small></div>
</div>

<section class="card card--pad stack">
  <div class="row" style="gap:8px;flex-wrap:wrap">
    <form method="post" action="/admin/import-feuilles"><?= csrf_field() ?><input type="hidden" name="action" value="analyser"><button class="btn" type="submit"><?= $planned ? '1. Analyser à nouveau' : '1. Analyser les feuilles' ?></button></form>
    <?php if ($planned): ?>
      <form method="post" action="/admin/import-feuilles"><?= csrf_field() ?><input type="hidden" name="action" value="essai"><button class="btn" type="submit"<?= $summary['left'] ? '' : ' disabled' ?>>2. Essai sur 10 matchs</button></form>
      <?php if (!$running): ?>
        <form method="post" action="/admin/import-feuilles"><?= csrf_field() ?><input type="hidden" name="action" value="lancer"><button class="btn btn--primary" type="submit"<?= $summary['left'] ? '' : ' disabled' ?>>3. Lancer tout l’import</button></form>
      <?php else: ?>
        <form method="post" action="/admin/import-feuilles"><?= csrf_field() ?><input type="hidden" name="action" value="pause"><button class="btn" type="submit">Mettre en pause</button></form>
      <?php endif; ?>
      <?php if ($summary['erreur']): ?>
        <form method="post" action="/admin/import-feuilles"><?= csrf_field() ?><input type="hidden" name="action" value="relancer"><button class="btn btn--ghost" type="submit">Refaire les erreurs</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($running): ?>
    <div class="stack" style="gap:6px;padding:12px 14px;border:2px solid var(--navy);background:var(--paper)" data-fm-run data-csrf="<?= e(\App\Core\Session::csrfToken()) ?>">
      <b data-fm-state>Import en cours</b>
      <span class="small"><b data-fm-left><?= (int) $summary['left'] ?></b> feuille(s) restante(s) · <b data-fm-done><?= (int) $summary['fait'] ?></b> fiche(s) créée(s) · <b data-fm-props><?= (int) $summary['props'] ?></b> proposition(s)</span>
      <span class="xs muted" data-fm-last>démarrage…</span>
    </div>
  <?php endif; ?>
  <p class="small" style="margin:0"><?= $planned ? 'Analyse du ' . e(date_fr($state['at'])) . ' à ' . e(date('H:i', strtotime((string) $state['at']))) . '.' : 'Les feuilles n’ont pas encore été analysées.' ?>
    <?= !empty($state['done_at']) ? ' Import terminé le ' . e(date_fr($state['done_at'])) . '.' : '' ?> Rapport détaillé : <code>docs/rapport-comparaison-matchs.md</code>.</p>
</section>

<?php if ($seasons): ?>
<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Saison par saison</h2>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>Saison</th><th class="num">À créer</th><th class="num">Créées</th><th class="num">À comparer</th><th class="num">Comparées</th><th class="num">Erreurs</th></tr></thead>
    <tbody>
    <?php foreach ($seasons as $y => $c): ?>
      <tr><td><?= (int) $y ?>-<?= (int) $y + 1 ?></td><td class="num"><?= (int) ($c['a-creer'] ?? 0) ?></td><td class="num"><?= (int) ($c['fait'] ?? 0) ?></td><td class="num"><?= (int) ($c['a-comparer'] ?? 0) ?></td><td class="num"><?= (int) ($c['compare'] ?? 0) ?></td><td class="num"><?= (int) ($c['erreur'] ?? 0) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Derniers matchs traités</h2>
  <ul class="stack" style="gap:4px;margin:0;padding:0;list-style:none">
    <?php foreach ($recent as $r): [$lab, $cls] = $labels[$r['status']]; $s = $r['sheet']; ?>
      <li class="small"><span class="pill pill--<?= $cls ?>"><?= $lab ?></span>
        <?= e(date('d/m/Y', strtotime((string) $s['date']))) ?> · <?= e($s['home'] . ' – ' . $s['away'] . ' ' . $s['score_home'] . '-' . $s['score_away']) ?>
        <?php if ($r['path']): ?> → <a href="<?= e($r['path']) ?>" target="_blank" rel="noopener"><?= e((string) $r['title']) ?></a> · <a href="/admin/fiches/<?= (int) $r['fiche'] ?>">modifier</a><?php endif; ?>
        <?php if ($r['status'] === 'compare'): ?> · <?= $r['props'] ? '<a href="/admin/trouvailles?source=feuilles">' . (int) $r['props'] . ' proposition(s)</a>' : 'aucun écart' ?><?php endif; ?>
        <?php if ($r['why']): ?> · <span class="ko"><?= e((string) $r['why']) ?></span><?php endif; ?>
        <span class="xs muted">(<?= e(F::fileLabel((string) $s['file'])) ?>)</span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
