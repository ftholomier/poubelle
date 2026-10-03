<?php
/** Onze de légende. Variables : $results, $voters, $lines, $candidates, $byLine, $reveal, $canReset, $noLine */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$revealed = $reveal !== '' && strtotime($reveal) <= time();
?>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($voters) ?></b><span>votants</span><small>un vote par appareil et par jour</small></div>
  <div class="kpi"><b><?= $fmt($candidates) ?></b><span>joueurs candidats</span><small>G <?= (int) ($byLine['G'] ?? 0) ?> · D <?= (int) ($byLine['D'] ?? 0) ?> · M <?= (int) ($byLine['M'] ?? 0) ?> · A <?= (int) ($byLine['A'] ?? 0) ?></small></div>
  <div class="kpi<?= $noLine ? ' kpi--pink' : '' ?>"><b><?= $fmt($noLine) ?></b><span>joueurs sans ligne</span><small>renseignez « Ligne » dans leur fiche pour qu’ils soient candidats</small></div>
  <div class="kpi"><b><?= $reveal !== '' ? e(date_num($reveal)) : '—' ?></b><span>dévoilement</span><small><?= $revealed ? 'résultats visibles par le public' : 'résultats cachés au public jusque-là' ?></small></div>
</div>
<div class="cols cols--wide">
  <div class="card">
    <div class="card__head"><h2 class="card__t">Le Onze du public</h2><a class="linkbtn" href="/centenaire/#onze" target="_blank" rel="noopener">Page publique ↗</a></div>
    <?php foreach ($results as $r): ?>
      <div class="card__row" style="grid-template-columns:40px minmax(0,1fr) auto"><span class="pill pill--navy"><?= e($r['line']) ?></span><a href="<?= e($r['href']) ?>" target="_blank" rel="noopener"><?= e($r['name']) ?></a><b class="d"><?= (int) $r['pct'] ?> %</b></div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="card__body muted">Aucun vote pour l’instant.</div><?php endif; ?>
  </div>
  <div class="stack">
    <form class="card card--pad" method="post" action="/admin/onze">
      <?= csrf_field() ?><input type="hidden" name="action" value="date">
      <h2 class="card__t card__t--sm">Date de dévoilement</h2>
      <label class="f"><span class="f__k">Le Onze du public est révélé le</span><input type="date" name="reveal" value="<?= e($reveal) ?>"></label>
      <button type="submit" class="btn btn--navy" style="align-self:flex-start">Enregistrer</button>
    </form>
    <?php foreach ($lines as $l => $line): ?>
      <div class="card">
        <div class="card__head"><h2 class="card__t card__t--sm"><?= e($line['label']) ?></h2></div>
        <?php $max = max(1, $line['rows'][0]['n'] ?? 1); foreach ($line['rows'] as $r): ?>
          <div class="card__row" style="grid-template-columns:minmax(0,1fr) 120px 50px"><a href="/admin/fiche/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a><span class="meter meter--y"><i style="width:<?= round(100 * $r['n'] / $max) ?>%"></i></span><b class="d right"><?= $fmt($r['n']) ?></b></div>
        <?php endforeach; ?>
        <?php if (!$line['rows']): ?><div class="card__body muted small">Aucun vote.</div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($canReset): ?>
      <form method="post" action="/admin/onze" data-confirm="Remettre les votes à zéro ?|Les votes actuels sont archivés puis effacés.|Remettre à zéro|danger"><?= csrf_field() ?><button type="submit" name="action" value="reinitialiser" class="btn btn--danger">Remettre les votes à zéro</button></form>
    <?php endif; ?>
  </div>
</div>
