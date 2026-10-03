<?php
/** File des contributions. Variables : $rows, $tab, $counts */
use App\Admin\Base;
use App\Admin\Community;
use App\Front\Community as Front;

$tabs = ['a-traiter' => 'À traiter', 'valide' => 'Publiées', 'refuse' => 'Refusées', 'tout' => 'Tout'];
?>
<div class="toolbar">
  <div class="chips">
    <?php foreach ($tabs as $k => $l): ?><a class="chip<?= $tab === $k ? ' is-on' : '' ?>" href="/admin/contributions?statut=<?= e($k) ?>"><?= e($l) ?> <em>· <?= (int) ($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
  </div>
  <span class="grow"></span>
  <a class="btn" href="/contribuer/" target="_blank" rel="noopener">Voir le formulaire public ↗</a>
</div>
<p class="small muted" style="margin:0">Corrections, photos, documents et témoignages envoyés par les visiteurs. Chaque contribution porte un numéro de suivi ; le contributeur est prévenu par e-mail de votre décision.</p>
<div class="table">
  <table>
    <thead><tr><th>N° de suivi</th><th>Type</th><th>Contributeur</th><th>Fiche concernée</th><th>Fichiers</th><th>Reçue</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <?php $st = $c['status'] ?? 'nouveau'; ?>
      <tr data-href="/admin/contributions/<?= e($c['ticket']) ?>">
        <td class="t-num"><a class="rowlink" href="/admin/contributions/<?= e($c['ticket']) ?>"><?= e($c['ticket']) ?></a></td>
        <td><?= e((Front::TYPES[$c['type']][0] ?? '') . ' ' . (Front::TYPES[$c['type']][1] ?? $c['type'])) ?></td>
        <td><?= e($c['name'] ?? '') ?><br><span class="xs muted"><?= e($c['email'] ?? '') ?></span></td>
        <td class="ellipsis" style="max-width:260px"><?= e($c['fiche'] ?? '') ?: '<span class="muted">—</span>' ?></td>
        <td class="t-num"><?= count($c['files'] ?? []) ?: '—' ?></td>
        <td class="xs muted nowrap"><?= e(Base::ago($c['at'] ?? null)) ?></td>
        <td><span class="pill pill--<?= ['nouveau' => 'warn', 'info' => 'info', 'valide' => 'ok', 'refuse' => 'ko'][$st] ?? 'warn' ?>"><?= e(Community::C_STATUS[$st] ?? $st) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" style="padding:28px;text-align:center" class="muted">Aucune contribution dans cette file.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
