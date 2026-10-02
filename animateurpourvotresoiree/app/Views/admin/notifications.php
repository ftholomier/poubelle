<?php
use App\Core\Url;
use App\Services\Notify;
/** @var array $items @var string $type */
?>
<div class="adm-head">
  <div><h1>Notifications</h1><p><?= nf($total) ?> au total. Réglez ce qui vous est envoyé par email ou en push dans <a href="<?= e(Url::admin('reglages/notifications')) ?>">Alertes admin</a>.</p></div>
  <form method="post" action="<?= e(Url::admin('notifications/lues')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('check', 16) ?> Tout marquer comme lu</button></form>
</div>
<div class="tabs"><a href="?"<?= $type === '' ? ' class="on"' : '' ?>>Toutes</a><?php foreach (Notify::TYPES as $k => $l): ?><a href="?type=<?= e($k) ?>"<?= $type === $k ? ' class="on"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></div>
<div class="table-wrap">
  <?php foreach ($items as $n): ?>
    <a class="notif lv-<?= e($n['level']) ?><?= $n['read'] ? '' : ' unread' ?>" href="<?= e($n['link'] ?: '#') ?>"><i></i><span><b><?= e($n['title']) ?></b><?= $n['body'] !== '' ? nl2br(e($n['body'])) . '<br>' : '' ?><small><?= e(Notify::TYPES[$n['type']] ?? $n['type']) ?> · <?= e(date_fr($n['created'], 'datetime')) ?></small></span></a>
  <?php endforeach; ?>
  <?php if (!$items): ?><p class="empty-sm" style="margin:14px">Aucune notification.</p><?php endif; ?>
</div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
