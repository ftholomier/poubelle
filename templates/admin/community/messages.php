<?php
/** Messages reçus par le formulaire de contact. Variables : $rows, $tab, $all */
use App\Admin\Base;
use App\Admin\Community;
use App\Front\Community as Front;

$n = ['actifs' => 0, 'nouveau' => 0, 'traite' => 0, 'tout' => count($all)];
foreach ($all as $m) {
    $s = $m['status'] ?? 'nouveau';
    if ($s !== 'traite') {
        $n['actifs']++;
    }
    if (isset($n[$s])) {
        $n[$s]++;
    }
}
$tabs = ['actifs' => 'En cours', 'nouveau' => 'Non lus', 'traite' => 'Traités', 'tout' => 'Tout'];
?>
<div class="toolbar">
  <div class="chips">
    <?php foreach ($tabs as $k => $l): ?><a class="chip<?= $tab === $k ? ' is-on' : '' ?>" href="/admin/messages?statut=<?= e($k) ?>"><?= e($l) ?> <em>· <?= (int) $n[$k] ?></em></a><?php endforeach; ?>
  </div>
</div>
<div class="table">
  <table>
    <thead><tr><th>Reçu</th><th>Objet</th><th>De</th><th>Message</th><th>Suivi par</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <?php $s = $m['status'] ?? 'nouveau'; ?>
      <tr data-href="/admin/messages/<?= e($m['id']) ?>"<?= $s === 'nouveau' ? ' style="font-weight:700"' : '' ?>>
        <td class="xs nowrap"><?= e(Base::ago($m['at'] ?? null)) ?></td>
        <td class="nowrap"><?php if (($m['site'] ?? '') === 'association'): ?><span class="pill pill--info" title="Message envoyé depuis le site de l’association">Association</span> <?= e(\App\Vitrine\Forms::REASONS[$m['reason']] ?? $m['reason']) ?><?php else: ?><?= e(Front::REASONS[$m['reason']] ?? $m['reason']) ?><?php endif; ?></td>
        <td><a class="rowlink" href="/admin/messages/<?= e($m['id']) ?>"><?= e($m['name'] ?? '') ?></a><?= !empty($m['org']) ? '<br><span class="xs muted">' . e($m['org']) . '</span>' : '' ?></td>
        <td class="ellipsis" style="max-width:380px"><?= e(mb_substr((string) ($m['message'] ?? ''), 0, 160)) ?></td>
        <td class="small"><?= e($m['assigned'] ?? '') ?: '<span class="muted">—</span>' ?></td>
        <td><span class="pill pill--<?= ['nouveau' => 'warn', 'lu' => 'info', 'traite' => 'ok'][$s] ?? 'warn' ?>"><?= e(Community::M_STATUS[$s] ?? $s) ?></span><?= !empty($m['replies']) ? ' <span class="xs muted">↩ ' . count($m['replies']) . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" style="padding:28px;text-align:center" class="muted">Aucun message.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="xs muted" style="margin:0">Les messages et leurs coordonnées servent uniquement à répondre ; supprimez-les une fois traités s’ils ne sont plus utiles (RGPD).</p>
