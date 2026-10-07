<?php
/**
 * Système › Reprise 1928-1969 (fcsmstory.com). Variables : $state, $summary, $items, $filter, $gemini, $fetched
 */
$kinds = ['season' => 'Saisons', 'match' => 'Matchs', 'match-page' => 'Matchs racontés à part', 'tournament' => 'Tournois et coupes', 'article' => 'Articles', 'player' => 'Portraits'];
$states = ['a-faire' => ['À faire', 'info'], 'fait' => ['Créé', 'ok'], 'existe' => ['Déjà au musée', 'brouillon'], 'doublon' => ['Doublon écarté', 'brouillon'],
    'trop-proche' => ['Trop proche', 'ko'], 'erreur' => ['Erreur', 'ko']];
$count = fn (string $st) => array_sum(array_map(fn ($k) => (int) ($k[$st] ?? 0), $summary));
$total = count($state['items']);
$done = $count('fait');
$todo = $count('a-faire');
?>
<p class="alert alert--info" style="margin:0">Reprise des saisons 1928 à 1969 racontées sur <a href="https://fcsmstory.com/" target="_blank" rel="noopener">fcsmstory.com</a> : chaque match, saison, tournoi, article et portrait est <b>entièrement réécrit par Gemini</b> (style, ordre, tournures), avec un contrôle de ressemblance : un texte qui reprend plus de <?= (int) round(\App\Services\FcsmImport::SIM_MAX * 100) ?> % de suites de six mots de l’original est réécrit, puis écarté. Les faits (date, score, composition, buteurs) sont repris tels quels, <b>aucune image</b>. Un match déjà au musée à la même date, ou un joueur déjà présent, n’est jamais recréé ni modifié. Chaque fiche porte une ligne « Sources ».</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $done ?></b><span>fiches créées</span><small>sur <?= $total ?> éléments au plan</small></div>
  <div class="kpi"><b><?= $todo ?></b><span>à faire</span><small><?= !empty($state['running']) ? 'import en cours (un lot toutes les 5 min)' : 'import à l’arrêt' ?></small></div>
  <div class="kpi"><b><?= $count('existe') + $count('doublon') ?></b><span>écartés</span><small>déjà au musée</small></div>
  <div class="kpi"><b><?= $count('erreur') + $count('trop-proche') ?></b><span>à revoir</span><small>erreurs ou textes trop proches</small></div>
</div>

<section class="card card--pad stack">
  <div class="row" style="gap:8px;flex-wrap:wrap">
    <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="analyser"><button class="btn" type="submit"><?= $total ? '1. Analyser à nouveau' : '1. Analyser fcsmstory.com' ?></button></form>
    <?php if ($total): ?>
      <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="essai"><button class="btn" type="submit"<?= $gemini ? '' : ' disabled' ?>>2. Essai sur 3 matchs</button></form>
      <?php if (empty($state['running'])): ?>
        <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="lancer"><button class="btn btn--primary" type="submit"<?= $gemini && $todo ? '' : ' disabled' ?>>3. Lancer tout l’import</button></form>
      <?php else: ?>
        <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="pause"><button class="btn" type="submit">Mettre en pause</button></form>
        <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="traiter"><button class="btn" type="submit">Traiter un lot maintenant</button></form>
      <?php endif; ?>
      <?php if ($count('erreur') + $count('trop-proche')): ?>
        <form method="post" action="/admin/reprise-1928-1969"><?= csrf_field() ?><input type="hidden" name="action" value="relancer"><button class="btn btn--ghost" type="submit">Refaire les erreurs</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="small" style="margin:0"><?= $fetched ? 'Site lu le ' . e(date_fr($fetched)) . ' à ' . e(date('H:i', strtotime($fetched))) . '.' : 'Le site n’a pas encore été lu.' ?>
    <?= $gemini ? '' : ' <b class="ko">Clé Gemini absente : Réglages › Assistant IA.</b>' ?> Coût suivi en direct dans <a href="/admin/couts-ia">Coûts IA</a> (« Reprise des années 1928-1969 »).</p>
</section>

<?php if ($summary): ?>
<section class="card">
  <div class="card__head"><h2 class="card__t">Au plan</h2></div>
  <div class="table" style="border:0"><table>
    <thead><tr><th>Type</th><?php foreach ($states as [$l]): ?><th><?= e($l) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($kinds as $k => $label): if (empty($summary[$k])) { continue; } ?>
      <tr><td><?= e($label) ?></td><?php foreach (array_keys($states) as $st): ?><td><?= (int) ($summary[$k][$st] ?? 0) ?: '·' ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="card">
  <div class="card__head"><h2 class="card__t">Détail</h2>
    <span class="card__note"><a href="/admin/reprise-1928-1969"<?= $filter === '' ? ' aria-current="page"' : '' ?>>tout</a>
      <?php foreach ($states as $st => [$l]): ?> · <a href="/admin/reprise-1928-1969?etat=<?= e($st) ?>"<?= $filter === $st ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></span></div>
  <div class="table" style="border:0"><table>
    <thead><tr><th>Élément</th><th>Type</th><th>État</th><th>Ressemblance</th><th>Fiche</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): [$l, $tone] = $states[$it['status']] ?? [$it['status'], 'info']; ?>
      <tr>
        <td><?= e($it['label']) ?><br><a class="xs muted" href="<?= e($it['source']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', rtrim($it['source'], '/'))) ?></a><?= !empty($it['why']) ? '<br><span class="xs">' . e($it['why']) . '</span>' : '' ?></td>
        <td class="small"><?= e($kinds[$it['kind']] ?? $it['kind']) ?></td>
        <td><span class="pill pill--<?= e($tone) ?>"><?= e($l) ?></span></td>
        <td class="small"><?= isset($it['similarity']) ? e((string) round($it['similarity'] * 100)) . ' %' : '·' ?></td>
        <td class="small"><?php if (!empty($it['fiche'])): ?><a href="/admin/fiche/<?= (int) $it['fiche'] ?>">n° <?= (int) $it['fiche'] ?></a><?php elseif ($it['kind'] === 'season' && $it['status'] === 'fait'): ?><a href="/admin/rubriques">texte de la saison</a><?php else: ?>·<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<?php if (!empty($state['unlinked'])): ?>
<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Joueurs des compositions sans fiche au musée</h2>
  <p class="small" style="margin:0">Ces noms figurent dans les compositions reprises mais aucune fiche ne leur correspond. Ils ne sont pas créés automatiquement (un nom seul ne suffit pas à éviter les doublons) : créez leur fiche si besoin, les matchs s’y relieront d’eux-mêmes.</p>
  <p class="small" style="margin:0"><?= implode(' · ', array_map(fn ($n, $c) => e($n) . ' <span class="muted">(' . (int) $c . ')</span>', array_keys(array_slice($state['unlinked'], 0, 150, true)), array_slice($state['unlinked'], 0, 150, true))) ?></p>
</section>
<?php endif; ?>
<?php endif; ?>
