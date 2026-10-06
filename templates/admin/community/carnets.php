<?php
/** Carnets du supporter. Variables : $o (Carnet::overview) */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
<p class="alert alert--info" style="margin:0">Chaque supporter coche les matchs vus au stade (bouton « J’y étais ! » des fiches, ou saison par saison sur <a href="/carnet/" target="_blank" rel="noopener">/carnet/</a>) et obtient son bilan, ses badges et une carte à partager. Les carnets sont personnels : leurs e-mails ne sont jamais affichés ici. Un carnet jamais confirmé et vide est effacé après 90 jours.</p>
<div class="kpis">
  <div class="kpi"><b><?= $fmt($o['count']) ?></b><span>carnets</span><small>créés</small></div>
  <div class="kpi"><b><?= $fmt($o['ticks']) ?></b><span>matchs cochés</span><small>au total</small></div>
  <div class="kpi"><b><?= $fmt($o['count'] ? round($o['ticks'] / $o['count']) : 0) ?></b><span>matchs par carnet</span><small>en moyenne</small></div>
  <div class="kpi"><b><?= $fmt($o['public']) ?></b><span>pages publiques</span><small>sous pseudo</small></div>
</div>
<div class="card">
  <h2 class="h3" style="margin:0 0 12px">Les matchs les plus vécus</h2>
  <?php if (!$o['top']): ?><p class="muted">Aucun match coché pour l’instant.</p><?php else: ?>
  <div class="table"><table><thead><tr><th>Match</th><th class="t-num">Carnets</th></tr></thead><tbody>
    <?php foreach ($o['top'] as $t): ?><tr><td><a href="<?= e($t['m']['path']) ?>" target="_blank" rel="noopener"><?= e($t['m']['title']) ?></a></td><td class="t-num"><?= $fmt($t['n']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
