<?php
use App\Controllers\Admin\MessagesController;
use App\Core\Url;
/** @var array $items @var array $counts @var array $filters @var ?array $current */
$f = static fn (string $k) => (string) ($filters[$k] ?? '');
$kinds = ['visiteur' => 'Visiteur', 'pro' => 'Professionnel', 'partenariat' => 'Partenariat', 'presse' => 'Presse', 'rgpd' => 'Données personnelles'];
?>
<div class="adm-head"><div><h1>Formulaire <span class="serif">de contact</span></h1><p>Messages adressés à l'équipe du site. Répondez directement d'ici.</p></div></div>
<div class="tabs">
  <a href="?"<?= $f('statut') === '' ? ' class="on"' : '' ?>>Tous <em><?= nf($counts[''] ?? 0) ?></em></a>
  <?php foreach (MessagesController::CONTACT_STATUSES as $k => $l): ?><a href="?statut=<?= e($k) ?>"<?= $f('statut') === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<div class="adm-cols">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Date</th><th>De</th><th>Sujet</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($items as $c): ?>
          <tr<?= $current && (int) $current['id'] === (int) $c['id'] ? ' style="background:#fffbef"' : '' ?>>
            <td class="small"><?= e(date_fr($c['created'], 'short')) ?></td>
            <td><a class="t-main" href="?<?= e(http_build_query(array_merge($filters, ['id' => $c['id']]))) ?>"><?= e($c['name']) ?></a><span class="t-sub"><?= e($c['email']) ?></span></td>
            <td class="small"><?= e($c['subject'] ?: '—') ?></td>
            <td><span class="status-pill st-<?= e($c['status']) ?>"><?= e(MessagesController::CONTACT_STATUSES[$c['status']] ?? $c['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="4"><div class="empty-sm">Aucun message.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
  </div>
  <aside>
    <?php if ($current): $cid = (int) $current['id']; ?>
      <div class="box">
        <h2><?= e($current['subject'] ?: 'Message de ' . $current['name']) ?></h2>
        <p class="small"><strong><?= e($current['name']) ?></strong> · <a href="mailto:<?= e($current['email']) ?>"><?= e($current['email']) ?></a><br><span class="muted"><?= e($kinds[$current['kind'] ?? ''] ?? '') ?> · <?= e(date_fr((string) ($current['created_at'] ?? ''), 'datetime')) ?> · spam <?= (int) ($current['spam']['score'] ?? 0) ?></span></p>
        <div class="prose small"><?= nl2br(e((string) $current['message'])) ?></div>
        <?php foreach ((array) ($current['replies'] ?? []) as $rep): ?><div class="alert alert-info small mt-1"><div><strong>Réponse <?= e(ago((string) $rep['at'])) ?><?= !empty($rep['by']) ? ' par ' . e((string) $rep['by']) : '' ?> :</strong><br><?= nl2br(e((string) $rep['text'])) ?></div></div><?php endforeach; ?>
        <form class="form mt-2" method="post" action="<?= e(Url::admin('contacts/' . $cid)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reply">
          <div class="field"><label for="c-reply">Votre réponse</label><textarea id="c-reply" name="reply" rows="6" required></textarea></div>
          <button class="btn btn-sm btn-coral" type="submit"><?= icon('send', 14) ?> Envoyer la réponse</button></form>
        <div class="row-wrap mt-2">
          <?php foreach (['archive' => 'Archiver', 'new' => 'Marquer non traité'] as $a => $l): ?><form method="post" action="<?= e(Url::admin('contacts/' . $cid)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-xs" type="submit"><?= e($l) ?></button></form><?php endforeach; ?>
          <form method="post" action="<?= e(Url::admin('contacts/' . $cid)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="spam"><input type="hidden" name="block_email" value="1"><button class="btn btn-xs" type="submit">Spam + bloquer</button></form>
          <form method="post" action="<?= e(Url::admin('contacts/' . $cid)) ?>" data-confirm="Supprimer ce message ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-xs" type="submit">Supprimer</button></form>
        </div>
      </div>
    <?php else: ?>
      <div class="box"><p class="muted small">Sélectionnez un message pour le lire et y répondre.</p></div>
    <?php endif; ?>
  </aside>
</div>
