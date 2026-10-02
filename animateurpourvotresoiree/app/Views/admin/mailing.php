<?php
use App\Controllers\Admin\MailingController;
use App\Core\Url;
/** @var array $items @var array $queue @var string $driver */
?>
<div class="adm-head">
  <div><h1>Emailing <span class="serif">adhérents</span></h1><p>Écrivez aux pros (tous ou un segment) : envoi progressif, suivi des ouvertures et des clics, désinscription en un clic.</p></div>
  <a class="btn btn-sm btn-ink" href="<?= e(Url::admin('emailing/nouvelle')) ?>"><?= icon('plus', 16) ?> Nouvelle campagne</a>
</div>
<?php if ($driver === 'log'): ?><div class="alert alert-warning mb-2"><?= icon('alert', 18) ?><div>Les emails sont en <strong>mode test</strong> (MAIL_DRIVER=log) : ils sont enregistrés mais pas envoyés. Configurez le SMTP dans <a href="<?= e(Url::admin('reglages')) ?>">Configuration</a>.</div></div><?php endif; ?>
<div class="kpis mb-2">
  <a class="kpi" href="<?= e(Url::admin('emailing/file?statut=queued')) ?>"><span>Emails en attente</span><b><?= nf($queue['queued']) ?></b></a>
  <a class="kpi<?= $queue['failed'] ? ' alert' : '' ?>" href="<?= e(Url::admin('emailing/file?statut=failed')) ?>"><span>Échecs d'envoi</span><b><?= nf($queue['failed']) ?></b></a>
  <a class="kpi" href="<?= e(Url::admin('emailing/modeles')) ?>"><span>Modèles transactionnels</span><b><?= count(App\Services\Mail::defaults()) ?></b></a>
</div>
<div class="table-wrap">
  <table class="tbl">
    <thead><tr><th>Campagne</th><th>Statut</th><th class="num">Destinataires</th><th class="num">Envoyés</th><th class="num">Ouvertures</th><th class="num">Clics</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($items as $c): $sent = max(1, $c['sent']); ?>
        <tr>
          <td><a class="t-main" href="<?= e(Url::admin('emailing/' . $c['id'])) ?>"><?= e($c['name']) ?></a><span class="t-sub"><?= e($c['subject']) ?></span></td>
          <td><span class="status-pill st-<?= e($c['status']) ?>"><?= e(MailingController::CAMPAIGN_STATUSES[$c['status']] ?? $c['status']) ?></span></td>
          <td class="num"><?= nf($c['total']) ?></td>
          <td class="num"><?= nf($c['sent']) ?></td>
          <td class="num"><?= nf($c['opens']) ?> <span class="t-sub"><?= $c['sent'] ? round($c['opens'] / $sent * 100) . ' %' : '' ?></span></td>
          <td class="num"><?= nf($c['clicks']) ?> <span class="t-sub"><?= $c['sent'] ? round($c['clicks'] / $sent * 100) . ' %' : '' ?></span></td>
          <td class="small"><?= e(date_fr($c['scheduled'] ?: $c['created'], 'datetime')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="7"><div class="empty-sm">Aucune campagne pour l'instant.</div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
