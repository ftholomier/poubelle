<?php
use App\Core\View;
use App\Services\Categories;
use App\Services\Leads;

/** @var array $items @var string $seen @var int $total @var int $page @var int $pages */
?>
<div class="pro-head">
  <div><p class="mono muted small">Demandes de devis</p><h1 class="h2">Vos <span class="serif c-coral">demandes</span></h1></div>
  <span class="tag"><?= nf($total) ?> au total</span>
</div>
<p class="lead mt-1">Les demandes des clients de votre secteur et de vos métiers. Contactez-les directement par téléphone ou par email : les plus rapides sont souvent retenus !</p>
<?php if (!$items): ?>
  <div class="empty">Aucune demande pour le moment. Vérifiez que vos <a class="link" href="/espace-pro/fiche/">départements d'intervention</a> sont bien renseignés.</div>
<?php else: ?>
  <ul class="inbox big mt-2">
    <?php foreach ($items as $r): ?>
      <li class="<?= $r['created'] > $seen ? 'unread' : '' ?>"><a href="/espace-pro/demandes/<?= (int) $r['id'] ?>/">
        <span class="row-wrap"><b><?= e(Leads::EVENT_TYPES[$r['type']] ?? 'Événement') ?></b>
          <?php if ($r['created'] > $seen): ?><span class="tag tag-new">Nouveau</span><?php endif; ?>
          <?php foreach (array_slice((array) $r['cats'], 0, 3) as $c): ?><span class="soft-tag"><?= e(Categories::name($c)) ?></span><?php endforeach; ?></span>
        <span class="muted small"><?= $r['city'] ? e($r['city']) . ($r['dep'] ? ' (' . e($r['dep']) . ')' : '') . ' · ' : '' ?><?= $r['date'] ? 'le ' . e(date_fr($r['date'], 'short')) . ' · ' : '' ?>reçue <?= e(ago($r['created'])) ?></span>
        <span class="excerpt"><?= e($r['excerpt']) ?></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <?= View::partial('front/partials/pagination', ['page' => $page, 'pages' => $pages, 'link' => static fn (int $p): string => '/espace-pro/demandes/' . ($p > 1 ? '?page=' . $p : '')]) ?>
<?php endif; ?>
