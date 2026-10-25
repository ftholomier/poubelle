<?php
/** @var array $leads @var ?string $type @var ?string $status @var string $q @var array $counts */
use LD\Leads;

$qs = static fn(array $over) => '?' . http_build_query(array_filter(array_merge(['type' => $type, 'statut' => $status, 'q' => $q], $over), static fn($v) => $v !== null && $v !== ''));
?>
<div class="toolbar">
  <div class="tabs">
    <a href="<?= e($qs(['type' => null])) ?>"<?= !$type ? ' class="is-on"' : '' ?>>Toutes <small><?= (int) $counts['total'] ?></small></a>
    <?php foreach (Leads::TYPES as $k => $label): ?>
      <a href="<?= e($qs(['type' => $k])) ?>"<?= $type === $k ? ' class="is-on"' : '' ?>><?= e($label) ?> <small><?= (int) ($counts['types'][$k] ?? 0) ?></small><?php if (!empty($counts['types_new'][$k])): ?><i class="dot"></i><?php endif; ?></a>
    <?php endforeach; ?>
  </div>
  <form class="search" method="get">
    <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
    <select name="statut" onchange="this.form.submit()" aria-label="Statut">
      <option value="">Tous statuts</option>
      <option value="open"<?= $status === 'open' ? ' selected' : '' ?>>À traiter</option>
      <?php foreach (Leads::STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher…" aria-label="Rechercher">
    <a class="btn btn--ghost" href="/admin/demandes/export.csv">Exporter (CSV)</a>
  </form>
</div>

<?php if (!$leads): ?>
  <div class="card empty"><p>Aucune demande<?= $type || $status || $q ? ' pour ce filtre' : ' pour l’instant' ?>.</p></div>
<?php else: ?>
  <div class="card card--flush">
    <table class="table">
      <thead><tr><th>Type</th><th>Contact</th><th class="hide-sm">Résumé</th><th>Reçue</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($leads as $l):
          $sum = $l['data']['time_text'] ?? $l['data']['summary'] ?? $l['data']['besoin'] ?? $l['data']['date_label'] ?? $l['data']['moment'] ?? $l['data']['transcript'] ?? ''; ?>
          <tr class="<?= $l['status'] === 'new' ? 'is-new' : '' ?>" data-href="/admin/demandes/<?= e($l['id']) ?>">
            <td><span class="pill pill--<?= e($l['type']) ?>"><?= e(Leads::TYPES[$l['type']] ?? $l['type']) ?></span></td>
            <td><a href="/admin/demandes/<?= e($l['id']) ?>"><b><?= e($l['name'] ?: 'Anonyme') ?></b></a><br><small class="muted"><?= e(implode(' · ', array_filter([$l['email'], $l['phone']]))) ?></small></td>
            <td class="hide-sm"><small><?= e(mb_strimwidth(is_string($sum) ? $sum : '', 0, 110, '…')) ?></small></td>
            <td><small><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></small></td>
            <td><span class="status status--<?= e($l['status']) ?>"><?= e(Leads::STATUSES[$l['status']] ?? $l['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
