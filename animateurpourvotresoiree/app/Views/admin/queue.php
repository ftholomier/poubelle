<?php
use App\Core\Url;
/** @var array $items @var array $counts @var string $status @var string $driver */
$labels = ['queued' => 'En attente', 'sent' => 'Envoyé', 'failed' => 'Échec'];
?>
<div class="adm-head">
  <div><h1>File <span class="serif">d'envoi</span></h1><p>Tous les emails passent par cette file, envoyée par le cron au rythme réglé (<?= (int) App\Services\Settings::get('mailing.rate_per_minute', 60) ?>/min). Pilote actuel : <strong><?= e($driver) ?></strong>.</p></div>
  <div class="row-wrap">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="btn btn-sm btn-ink" type="submit"><?= icon('send', 16) ?> Envoyer maintenant</button></form>
    <?php if ($counts['failed']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn btn-sm" type="submit">Réessayer les échecs</button></form><?php endif; ?>
    <form method="post" data-confirm="Effacer l'historique des emails envoyés ?"><?= csrf_field() ?><input type="hidden" name="action" value="purge"><button class="btn btn-sm" type="submit">Purger les envoyés</button></form>
  </div>
</div>
<div class="tabs"><a href="?"<?= $status === '' ? ' class="on"' : '' ?>>Tous</a><?php foreach ($labels as $k => $l): ?><a href="?statut=<?= $k ?>"<?= $status === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?></div>
<div class="table-wrap"><table class="tbl"><thead><tr><th>Créé</th><th>Destinataire</th><th>Objet</th><th>Statut</th><th class="num">Essais</th><th></th></tr></thead><tbody>
  <?php foreach ($items as $m): ?><tr>
    <td class="small"><?= e(date_fr($m['created'], 'datetime')) ?></td>
    <td class="small"><?= e($m['to']) ?><?= $m['campaign'] ? '<span class="t-sub"><a href="' . e(Url::admin('emailing/' . $m['campaign'])) . '">campagne #' . (int) $m['campaign'] . '</a></span>' : '' ?></td>
    <td class="small"><?= e(App\Core\Str::limit($m['subject'], 80)) ?><?= $m['error'] !== '' ? '<span class="t-sub" style="color:var(--danger)">' . e($m['error']) . '</span>' : '' ?></td>
    <td><span class="status-pill st-<?= e($m['status']) ?>"><?= e($labels[$m['status']] ?? $m['status']) ?></span></td>
    <td class="num"><?= (int) $m['attempts'] ?></td>
    <td class="actions"><?php if ($m['status'] !== 'sent'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-xs" type="submit" title="Retirer"><?= icon('trash', 12) ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="6"><div class="empty-sm">File vide.</div></td></tr><?php endif; ?>
</tbody></table></div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
