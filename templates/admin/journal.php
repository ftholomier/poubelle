<?php
/** Journal d'activité. Variables : $rows, $people, $who */
use App\Admin\Base;
?>
<form class="toolbar" method="get" action="/admin/journal">
  <select name="qui" data-autosubmit aria-label="Personne"><option value="">Toute l’équipe</option><?php foreach ($people as $p): ?><option<?= $p === $who ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select>
  <span class="small muted">Qui a fait quoi, quand · les modifications de fiches sont réversibles depuis leur historique</span>
</form>
<div class="card">
  <?php foreach ($rows as $j): ?>
    <div class="card__row" style="grid-template-columns:150px 150px minmax(0,1fr) 110px">
      <span class="small muted"><?= e(date('d/m/Y H:i', strtotime($j['at']))) ?></span>
      <b><?= e($j['by'] ?? '') ?></b>
      <span><?= e($j['action'] ?? '') ?> <?php if (!empty($j['title'])): ?><?= !empty($j['id']) ? '<a href="/admin/fiche/' . (int) $j['id'] . '">' . e($j['title']) . '</a>' : '<span>' . e($j['title']) . '</span>' ?><?php endif; ?></span>
      <?php if (!empty($j['id'])): ?><a class="btn btn--sm" href="/admin/fiche/<?= (int) $j['id'] ?>#historique">Historique</a><?php else: ?><span></span><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="card__body muted">Aucune activité enregistrée.</div><?php endif; ?>
</div>
