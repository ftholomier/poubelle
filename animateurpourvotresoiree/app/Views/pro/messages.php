<?php
use App\Core\View;

/** @var array $items @var int $total @var int $page @var int $pages */
?>
<div class="pro-head">
  <div><p class="mono muted small">Messages</p><h1 class="h2">Vos <span class="serif c-coral">messages</span></h1></div>
  <span class="tag"><?= nf($total) ?> au total</span>
</div>
<p class="lead mt-1">Les messages envoyés depuis votre fiche. Chaque message vous est aussi transmis par email : il suffit d'y répondre.</p>
<?php if (!$items): ?>
  <div class="empty">Aucun message pour le moment.</div>
<?php else: ?>
  <ul class="inbox big mt-2">
    <?php foreach ($items as $m): ?>
      <li class="<?= $m['read'] ? '' : 'unread' ?>"><a href="/espace-pro/messages/<?= (int) $m['id'] ?>/">
        <span class="row-wrap"><b><?= e($m['name']) ?></b><?php if (!$m['read']): ?><span class="tag tag-new">Non lu</span><?php endif; ?></span>
        <span class="muted small"><?= e(date_fr($m['created'], 'datetime')) ?></span>
        <span class="excerpt"><?= e($m['excerpt']) ?></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <?= View::partial('front/partials/pagination', ['page' => $page, 'pages' => $pages, 'link' => static fn (int $p): string => '/espace-pro/messages/' . ($p > 1 ? '?page=' . $p : '')]) ?>
<?php endif; ?>
