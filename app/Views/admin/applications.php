<?php
$stages = (array) settings('pipeline.stages', []);
?>
<div class="topbar">
  <h1><?= e($title) ?></h1>
  <div class="row">
    <a class="btn btn--sm <?= $showDrafts ? 'btn--ghost' : '' ?>" href="<?= e(url('admin/candidatures')) ?>">Envoyées</a>
    <a class="btn btn--sm <?= $showDrafts ? '' : 'btn--ghost' ?>" href="<?= e(url('admin/candidatures')) ?>?drafts=1">Abandonnées</a>
    <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures/export')) ?>"><?= icon('download') ?> CSV</a>
  </div>
</div>

<div class="panel">
  <form class="row" method="get" style="margin-bottom:18px">
    <?php if ($showDrafts): ?><input type="hidden" name="drafts" value="1"><?php endif; ?>
    <input class="input" style="max-width:280px" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, téléphone, secteur…">
    <?php if (!$showDrafts): ?>
      <select class="select" style="max-width:200px" name="stage" onchange="this.form.submit()">
        <option value="">Toutes les étapes</option>
        <?php foreach ($stages as $s): ?>
          <option value="<?= e($s['key']) ?>" <?= $stage === $s['key'] ? 'selected' : '' ?>><?= e($s['label']) ?> (<?= (int) ($by_stage[$s['key']] ?? 0) ?>)</option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button class="btn btn--sm" type="submit">Filtrer</button>
    <?php if ($q !== '' || $stage !== ''): ?>
      <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures')) ?><?= $showDrafts ? '?drafts=1' : '' ?>">Réinitialiser</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <span class="badge"><?= count($rows) ?> résultat<?= count($rows) > 1 ? 's' : '' ?></span>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <b>Rien à afficher</b>
      <?= $showDrafts ? 'Aucun tunnel abandonné — tant mieux.' : 'Aucune candidature ne correspond à ces filtres.' ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Candidat</th><th>Secteur</th><th>Disponibilité</th>
            <th><?= $showDrafts ? 'Étape atteinte' : 'Pipeline' ?></th>
            <th>Origine</th><th>Date</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $a): $st = null; foreach ($stages as $s) { if ($s['key'] === ($a['stage'] ?? '')) $st = $s; } ?>
            <tr>
              <td>
                <a class="strong" href="<?= e(url('admin/candidatures/' . $a['id'])) ?>"><?= e($a['name'] ?: '(nom non renseigné)') ?></a>
                <div style="font-size:.8rem;color:var(--muted)">
                  <?= e($a['email'] ?: '—') ?><?= !empty($a['phone']) ? ' · ' . e($a['phone']) : '' ?>
                </div>
              </td>
              <td><?= e($a['area'] ?: '—') ?></td>
              <td style="color:var(--muted)"><?= e($a['availability'] ?: '—') ?></td>
              <td>
                <?php if ($showDrafts): ?>
                  <span class="badge">Étape <?= (int) ($a['max_step'] ?? 1) ?>/4</span>
                <?php else: ?>
                  <span class="badge" style="color:<?= e($st['color'] ?? '#8d99ae') ?>"><i></i><?= e($st['label'] ?? 'Nouveau') ?></span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted)"><?= e($a['source'] ?: '—') ?></td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($a['submitted_at'] ?? $a['created_at'] ?? '', true)) ?></td>
              <td style="text-align:right">
                <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures/' . $a['id'])) ?>">Ouvrir</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
