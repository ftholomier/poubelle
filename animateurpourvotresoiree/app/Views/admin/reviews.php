<?php
use App\Core\Url;
use App\Services\Reviews;
/** @var array $items @var array $counts @var array $filters */
$f = static fn (string $k) => (string) ($filters[$k] ?? '');
$back = http_build_query(array_intersect_key($filters, ['statut' => 1, 'page' => 1, 'pro' => 1]));
?>
<div class="adm-head"><div><h1>Avis <span class="serif">clients</span></h1><p>Les avis sont confirmés par email avant d'arriver ici. Les invitations envoyées par les pros portent la mention « client vérifié ».</p></div></div>
<div class="tabs">
  <a href="?"<?= $f('statut') === '' ? ' class="on"' : '' ?>>Tous <em><?= nf($counts[''] ?? 0) ?></em></a>
  <?php foreach (Reviews::STATUSES as $k => $l): ?><a href="?statut=<?= e($k) ?>"<?= $f('statut') === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<?php if (!$items): ?><div class="empty-sm">Aucun avis.</div><?php endif; ?>
<div class="stack">
  <?php foreach ($items as $r): $rid = (int) $r['id']; ?>
    <div class="box">
      <div class="box-head">
        <div><span class="c-coral" style="font-size:18px"><?= str_repeat('★', (int) $r['rating']) ?><?= str_repeat('☆', 5 - (int) $r['rating']) ?></span> <strong><?= e((string) ($r['title'] ?? '')) ?></strong>
          <div class="small muted"><?= e(App\Services\Reviews::author($r)) ?><?= !empty($r['author_email']) ? ' (' . e((string) $r['author_email']) . ')' : '' ?> · sur <a href="<?= e(Url::admin('pros/' . $r['pro_id'])) ?>"><?= e($r['pro_name']) ?></a> · <?= e(date_fr((string) ($r['created_at'] ?? ''), 'datetime')) ?><?= !empty($r['verified_client']) ? ' · <span class="verified">✓ client vérifié</span>' : '' ?></div></div>
        <span class="status-pill st-<?= e($r['status']) ?>"><?= e(Reviews::STATUSES[$r['status']] ?? $r['status']) ?></span>
      </div>
      <p><?= nl2br(e((string) $r['body'])) ?></p>
      <?php if (!empty($r['reply']['text'])): ?><div class="alert alert-info small"><div><strong>Réponse du pro :</strong> <?= nl2br(e((string) $r['reply']['text'])) ?></div></div><?php endif; ?>
      <div class="row-wrap mt-1">
        <?php foreach (['approve' => ['✅ Publier', $r['status'] !== 'approved'], 'reject' => ['Refuser', $r['status'] !== 'rejected'], 'spam' => ['Spam + bloquer', $r['status'] !== 'rejected'], 'remove-reply' => ['Retirer la réponse du pro', !empty($r['reply'])], 'delete' => ['Supprimer', true]] as $a => [$label, $show]): if (!$show) { continue; } ?>
          <form method="post" action="<?= e(Url::admin('avis/' . $rid)) ?>"<?= $a === 'delete' ? ' data-confirm="Supprimer cet avis ?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn btn-xs<?= $a === 'approve' ? ' btn-lime' : '' ?>" type="submit"><?= e($label) ?></button></form>
        <?php endforeach; ?>
        <details><summary class="small link">Corriger</summary>
          <form class="form mt-1" method="post" action="<?= e(Url::admin('avis/' . $rid)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="back" value="<?= e($back) ?>">
            <div class="form-grid"><div class="field"><label>Auteur</label><input type="text" name="author_name" value="<?= e($r['author_name']) ?>"></div><div class="field"><label>Note</label><input type="number" name="rating" min="1" max="5" value="<?= (int) $r['rating'] ?>"></div><div class="field"><label>Titre</label><input type="text" name="title" value="<?= e((string) ($r['title'] ?? '')) ?>"></div></div>
            <div class="field"><label>Texte</label><textarea name="body" rows="4"><?= e((string) $r['body']) ?></textarea></div>
            <button class="btn btn-xs btn-ink" type="submit">Enregistrer</button></form>
        </details>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
